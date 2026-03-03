<?php

declare(strict_types=1);

/**
 * Enforce group-based visibility for individual agent ticket routes.
 *
 * Rules (mirrors _apiEnforceTicketAccess in api.php):
 *   admin  → unrestricted
 *   agent  → if the agent belongs to one or more groups, only tickets
 *             whose group_id is in that set are accessible; tickets
 *             with group_id = NULL are visible to all agents.
 *             Agents with no group assignment are unrestricted.
 *
 * $ticket must contain group_id (int|null).
 * Redirects to /agent/tickets with an error flash if access is denied.
 */
function _agentRequireTicketAccess(PDO $db, array $ticket): void
{
    if (Auth::role() !== 'agent') {
        return; // admins: unrestricted
    }

    $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
    $gs->execute([Auth::id()]);
    $agentGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

    if (empty($agentGroups)) {
        return; // agent not in any group: unrestricted
    }

    if ($ticket['group_id'] === null) {
        return; // unassigned ticket: visible to all agents
    }

    if (!in_array((int) $ticket['group_id'], $agentGroups, true)) {
        flash('error', 'You do not have access to this ticket.');
        redirect('/agent/tickets');
    }
}

/* ==================================================================
 * AGENT – Ticket Viewing
 * ================================================================== */

$router->get('/agent/tickets', function () {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();

    // Auto-apply default filter when visiting the bare URL (no query params, not an explicit reset)
    if (empty($_GET)) {
        $defStmt = $db->prepare(
            'SELECT filters FROM saved_filters WHERE user_id = ? AND is_default = 1 LIMIT 1'
        );
        $defStmt->execute([Auth::id()]);
        $defaultFilter = $defStmt->fetchColumn();
        if ($defaultFilter) {
            $filterData = json_decode($defaultFilter, true) ?: [];
            if ($filterData) {
                redirect('/agent/tickets?' . http_build_query($filterData));
            }
        }
    }

    // Read filter params
    $fStatus   = trim($_GET['status'] ?? '');
    $fPriority = trim($_GET['priority'] ?? '');
    $fType     = trim($_GET['type'] ?? '');
    $fLocation = trim($_GET['location'] ?? '');
    $fAgent    = trim($_GET['agent'] ?? '');
    $fGroup    = trim($_GET['group'] ?? '');
    $fSearch   = trim($_GET['q'] ?? '');

    $where  = [];
    $params = [];

    if ($fStatus !== '') {
        $where[]  = 't.status = ?';
        $params[] = $fStatus;
    }
    if ($fPriority !== '') {
        $where[]  = 't.priority_id = ?';
        $params[] = (int) $fPriority;
    }
    if ($fType !== '') {
        $where[]  = 't.type_id = ?';
        $params[] = (int) $fType;
    }
    if ($fLocation !== '') {
        $where[]  = 't.location_id = ?';
        $params[] = (int) $fLocation;
    }
    if ($fAgent !== '') {
        if ($fAgent === 'unassigned') {
            $where[] = 't.assigned_to IS NULL';
        } elseif ($fAgent === 'mine') {
            $where[]  = 't.assigned_to = ?';
            $params[] = Auth::id();
        } else {
            $where[]  = 't.assigned_to = ?';
            $params[] = (int) $fAgent;
        }
    }
    if ($fGroup !== '') {
        if ($fGroup === 'none') {
            $where[] = 't.group_id IS NULL';
        } else {
            $where[]  = 't.group_id = ?';
            $params[] = (int) $fGroup;
        }
    }
    if ($fSearch !== '') {
        $where[]  = 't.subject LIKE ?';
        $params[] = '%' . $fSearch . '%';
    }

    // Group-based visibility: agents who belong to groups can only see those groups' tickets.
    // Agents in no groups, and all admins, see all tickets.
    $agentGroupIds = [];
    if (Auth::role() === 'agent') {
        $gStmt = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gStmt->execute([Auth::id()]);
        $agentGroupIds = array_map('intval', $gStmt->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($agentGroupIds)) {
            $placeholders = implode(',', array_fill(0, count($agentGroupIds), '?'));
            $where[]      = 't.group_id IN (' . $placeholders . ')';
            $params       = array_merge($params, $agentGroupIds);
        }
    }

    $sql = "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name,
                g.name  AS group_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         LEFT JOIN `groups` g          ON t.group_id     = g.id";

    $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    // Count total matching tickets
    $countSql = "SELECT COUNT(*) FROM tickets t" . $whereClause;
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalTickets = (int) $countStmt->fetchColumn();

    // Sorting
    $sortableColumns = [
        'id'         => 't.id',
        'subject'    => 't.subject',
        'status'     => 't.status',
        'priority'   => 'tp.sort_order',
        'type'       => 'tt.name',
        'agent'      => 'a.first_name',
        'creator'    => 'c.first_name',
        'group'      => 'g.name',
        'location'   => 'l.name',
        'created_at' => 't.created_at',
        'due_date'   => 't.due_date',
    ];
    $sort = $_GET['sort'] ?? 'created_at';
    $dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $orderCol = $sortableColumns[$sort] ?? 't.created_at';

    // Pagination
    $perPage    = 30;
    $totalPages = max(1, (int) ceil($totalTickets / $perPage));
    $page       = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
    $offset     = ($page - 1) * $perPage;

    $sql .= $whereClause . " ORDER BY {$orderCol} {$dir} LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    // Load filter dropdown options
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin') ORDER BY first_name")->fetchAll();
    if (!empty($agentGroupIds)) {
        $placeholders = implode(',', array_fill(0, count($agentGroupIds), '?'));
        $gDropStmt = $db->prepare("SELECT * FROM `groups` WHERE id IN ($placeholders) ORDER BY sort_order, name");
        $gDropStmt->execute($agentGroupIds);
        $groups = $gDropStmt->fetchAll();
    } else {
        $groups = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    }

    $filters = [
        'status'   => $fStatus,
        'priority' => $fPriority,
        'type'     => $fType,
        'location' => $fLocation,
        'agent'    => $fAgent,
        'group'    => $fGroup,
        'q'        => $fSearch,
    ];

    // Load saved filters (own + shared)
    $sfStmt = $db->prepare(
        "SELECT sf.*, CONCAT(u.first_name, ' ', u.last_name) AS owner_name
         FROM saved_filters sf
         JOIN users u ON sf.user_id = u.id
         WHERE sf.user_id = ? OR sf.is_shared = 1
         ORDER BY sf.user_id = ? DESC, sf.name ASC"
    );
    $sfStmt->execute([Auth::id(), Auth::id()]);
    $savedFilters = $sfStmt->fetchAll();

    render('agent/tickets/index', [
        'tickets'         => $tickets,
        'priorities'      => $priorities,
        'types'           => $types,
        'locations'       => $locations,
        'agents'          => $agents,
        'groups'          => $groups,
        'filters'         => $filters,
        'savedFilters'    => $savedFilters,
        'page'            => $page,
        'totalPages'      => $totalPages,
        'totalTickets'    => $totalTickets,
        'sort'            => $sort,
        'dir'             => strtolower($dir),
        'visibleColumns'  => getUserColumns(Auth::id()),
        'groupRestricted' => !empty($agentGroupIds),
    ]);
});

/* ── Column Preferences (Agent) ───────────────────────────────────── */

$router->post('/agent/tickets/columns', function () {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/tickets');
    }

    $columns = $_POST['columns'] ?? [];
    if (!is_array($columns)) {
        $columns = [];
    }
    setUserColumns(Auth::id(), $columns);
    redirect($_POST['_redirect'] ?? '/agent/tickets');
});

/* ── Saved Filters (Agent) ────────────────────────────────────────── */

$router->post('/agent/tickets/filters/save', function () {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/tickets');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', 'Filter name is required.');
        redirect('/agent/tickets');
    }

    $filterData = [];
    foreach (['status', 'priority', 'type', 'location', 'agent', 'q'] as $key) {
        $val = trim($_POST[$key] ?? '');
        if ($val !== '') {
            $filterData[$key] = $val;
        }
    }

    $db = Database::connect();
    $stmt = $db->prepare('INSERT INTO saved_filters (user_id, name, filters) VALUES (?, ?, ?)');
    $stmt->execute([Auth::id(), $name, json_encode($filterData)]);

    flash('success', 'Filter "' . e($name) . '" saved.');
    $qs = http_build_query($filterData);
    redirect('/agent/tickets' . ($qs ? '?' . $qs : ''));
});

$router->post('/agent/tickets/filters/{id}/delete', function (array $p) {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    if (!$stmt->fetch()) {
        flash('error', 'Filter not found or access denied.');
        redirect('/agent/tickets');
    }

    $db->prepare('DELETE FROM saved_filters WHERE id = ?')->execute([$id]);
    flash('success', 'Filter deleted.');
    redirect('/agent/tickets');
});

$router->post('/agent/tickets/filters/{id}/toggle-share', function (array $p) {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    $filter = $stmt->fetch();
    if (!$filter) {
        flash('error', 'Filter not found or access denied.');
        redirect('/agent/tickets');
    }

    $newShared = $filter['is_shared'] ? 0 : 1;
    $db->prepare('UPDATE saved_filters SET is_shared = ? WHERE id = ?')->execute([$newShared, $id]);
    flash('success', $newShared ? 'Filter is now shared.' : 'Filter is now private.');
    redirect('/agent/tickets');
});

$router->post('/agent/tickets/filters/{id}/toggle-default', function (array $p) {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    $filter = $stmt->fetch();
    if (!$filter) {
        flash('error', 'Filter not found or access denied.');
        redirect('/agent/tickets');
    }

    if ($filter['is_default']) {
        // Unset default
        $db->prepare('UPDATE saved_filters SET is_default = 0 WHERE id = ?')->execute([$id]);
        flash('success', 'Default filter removed.');
    } else {
        // Clear any existing default for this user, then set this one
        $db->prepare('UPDATE saved_filters SET is_default = 0 WHERE user_id = ?')->execute([Auth::id()]);
        $db->prepare('UPDATE saved_filters SET is_default = 1 WHERE id = ?')->execute([$id]);
        flash('success', '"' . e($filter['name']) . '" is now your default filter.');
    }
    redirect('/agent/tickets');
});

/* ==================================================================
 * AGENT – Ticket Search (JSON, for merge modal typeahead)
 * ================================================================== */

$router->get('/agent/tickets/search', function () {
    Auth::requireRole('agent', 'admin');
    $db      = Database::connect();
    $q       = trim($_GET['q'] ?? '');
    $exclude = (int) ($_GET['exclude'] ?? 0);

    if ($q === '') {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    $params = [];
    $idMatch = is_numeric($q) ? (int) $q : 0;

    if ($idMatch > 0) {
        $where = 't.id = ? AND t.merged_into_ticket_id IS NULL';
        $params[] = $idMatch;
    } else {
        $where = 't.subject LIKE ? AND t.merged_into_ticket_id IS NULL';
        $params[] = '%' . $q . '%';
    }

    if ($exclude > 0) {
        $where .= ' AND t.id != ?';
        $params[] = $exclude;
    }

    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.status,
                CONCAT(u.first_name, ' ', u.last_name) AS creator_name
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE {$where}
         ORDER BY t.id DESC
         LIMIT 10"
    );
    $stmt->execute($params);

    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
});

$router->get('/agent/tickets/create', function () {
    Auth::requireRole('agent', 'admin');
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $agents     = $db->query(
        "SELECT id, first_name, last_name, email FROM users
         WHERE role IN ('admin','agent') ORDER BY first_name, last_name"
    )->fetchAll();
    $templates  = $db->query('SELECT * FROM ticket_templates ORDER BY name')->fetchAll();
    render('admin/tickets/create', [
        'types'      => $types,
        'priorities' => $priorities,
        'locations'  => $locations,
        'groups'     => $groups,
        'agents'     => $agents,
        'templates'  => $templates,
        'isAgent'    => true,
        'formAction' => '/admin/tickets/create',
    ]);
});

$router->post('/agent/tickets/bulk', function () {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/tickets');
    }

    $action    = $_POST['action'] ?? '';
    $rawIds    = $_POST['ticket_ids'] ?? [];
    $ticketIds = array_values(array_unique(array_map('intval', (array) $rawIds)));
    $ticketIds = array_filter($ticketIds, fn($id) => $id > 0);

    if (empty($ticketIds)) {
        flash('error', 'No tickets selected.');
        redirect('/agent/tickets');
    }

    $db           = Database::connect();
    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

    switch ($action) {
        case 'close':
            $db->prepare("UPDATE tickets SET status = 'closed' WHERE id IN ({$placeholders})")
               ->execute($ticketIds);
            flash('success', count($ticketIds) . ' ticket(s) closed.');
            break;

        case 'assign':
            $assignTo = !empty($_POST['assign_to']) ? (int) $_POST['assign_to'] : null;
            $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id IN ({$placeholders})")
               ->execute(array_merge([$assignTo], $ticketIds));
            $label = $assignTo ? 'reassigned' : 'unassigned';
            flash('success', count($ticketIds) . ' ticket(s) ' . $label . '.');
            break;

        case 'merge':
            if (count($ticketIds) < 2) {
                flash('error', 'Select at least 2 tickets to merge.');
                redirect('/agent/tickets');
            }
            sort($ticketIds);
            $targetId = array_shift($ticketIds);
            $tgt = $db->prepare('SELECT id, subject FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
            $tgt->execute([$targetId]);
            $targetTicket = $tgt->fetch();
            if (!$targetTicket) {
                flash('error', 'Primary ticket not found or already merged.');
                redirect('/agent/tickets');
            }
            $actor  = Auth::fullName();
            $merged = 0;
            foreach ($ticketIds as $sourceId) {
                $src = $db->prepare('SELECT id, subject FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
                $src->execute([$sourceId]);
                $sourceTicket = $src->fetch();
                if (!$sourceTicket) continue;
                $db->beginTransaction();
                try {
                    $db->prepare('INSERT IGNORE INTO ticket_cc (ticket_id, user_id, added_by) SELECT ?, user_id, ? FROM ticket_cc WHERE ticket_id = ?')
                       ->execute([$targetId, Auth::id(), $sourceId]);
                    $db->prepare('INSERT IGNORE INTO ticket_tag_map (ticket_id, tag_id) SELECT ?, tag_id FROM ticket_tag_map WHERE ticket_id = ?')
                       ->execute([$targetId, $sourceId]);
                    $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
                       ->execute([$targetId, Auth::id(), 'merged', "Ticket #{$sourceId} ({$sourceTicket['subject']}) was merged into this ticket by {$actor}"]);
                    $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
                       ->execute([$sourceId, Auth::id(), 'merged', "This ticket was merged into #{$targetId} ({$targetTicket['subject']}) by {$actor}"]);
                    $db->prepare('UPDATE tickets SET status = ?, merged_into_ticket_id = ? WHERE id = ?')
                       ->execute(['closed', $targetId, $sourceId]);
                    $db->commit();
                    notifyTicketMerged($db, $sourceId, $targetId);
                    $merged++;
                } catch (\Throwable $e) {
                    $db->rollBack();
                }
            }
            flash('success', "{$merged} ticket(s) merged into #{$targetId}.");
            redirect("/agent/tickets/{$targetId}");

        default:
            flash('error', 'Unknown action.');
    }

    redirect('/agent/tickets');
});

$router->get('/agent/tickets/{id}', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name,
                c.first_name AS creator_first_name, c.last_name AS creator_last_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name, c.email AS creator_email,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name,
                g.name AS group_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         LEFT JOIN groups g            ON t.group_id     = g.id
         WHERE t.id = ?"
    );
    $stmt->execute([(int) $p['id']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/tickets');
    }

    _agentRequireTicketAccess($db, $ticket);

    // Tags
    $tags = $db->prepare(
        'SELECT tt.name FROM ticket_tags tt
         INNER JOIN ticket_tag_map ttm ON tt.id = ttm.tag_id
         WHERE ttm.ticket_id = ?'
    );
    $tags->execute([$ticket['id']]);
    $ticket['tags'] = $tags->fetchAll(PDO::FETCH_COLUMN);

    // Timeline
    $tl = $db->prepare(
        "SELECT tl.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_timeline tl
         LEFT JOIN users u ON tl.user_id = u.id
         WHERE tl.ticket_id = ?
         ORDER BY tl.created_at DESC"
    );
    $tl->execute([$ticket['id']]);
    $timeline = $tl->fetchAll();

    // Agents list for @mention suggestions and assignment dropdown
    $agents = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin') ORDER BY first_name")->fetchAll();

    // Priorities for update dropdown
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();

    // Attachments (agents see all including internal)
    $attStmt = $db->prepare(
        'SELECT ta.*, tl.is_internal FROM ticket_attachments ta
         LEFT JOIN ticket_timeline tl ON ta.timeline_id = tl.id
         WHERE ta.ticket_id = ?
         ORDER BY ta.created_at ASC'
    );
    $attStmt->execute([$ticket['id']]);
    $attachments = $attStmt->fetchAll();

    // CC'd users
    $ccStmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role
         FROM ticket_cc tc
         JOIN users u ON tc.user_id = u.id
         WHERE tc.ticket_id = ?
         ORDER BY u.first_name'
    );
    $ccStmt->execute([$ticket['id']]);
    $ccUsers = $ccStmt->fetchAll();

    // Groups for assignment dropdown
    $groups = $db->query('SELECT id, name FROM groups ORDER BY name')->fetchAll();

    // Custom form fields + stored values
    $customFields = $db->query('SELECT * FROM ticket_form_fields WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    $fieldValues  = [];
    $fieldOptions = [];
    if ($customFields) {
        $fvStmt = $db->prepare('SELECT field_id, value FROM ticket_field_values WHERE ticket_id = ?');
        $fvStmt->execute([$ticket['id']]);
        foreach ($fvStmt->fetchAll() as $fv) {
            $fieldValues[$fv['field_id']] = $fv['value'];
        }
        foreach ($customFields as $f) {
            if (in_array($f['field_type'], ['dropdown', 'dependent'], true)) {
                $s = $db->prepare(
                    'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
                );
                $s->execute([$f['id']]);
                $fieldOptions[$f['id']] = $s->fetchAll();
            }
        }
    }

    // Is the current user watching this ticket?
    $watchStmt = $db->prepare('SELECT 1 FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?');
    $watchStmt->execute([$ticket['id'], Auth::id()]);
    $isWatching = (bool) $watchStmt->fetchColumn();

    render('agent/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'priorities' => $priorities, 'attachments' => $attachments, 'ccUsers' => $ccUsers, 'groups' => $groups, 'customFields' => $customFields, 'fieldValues' => $fieldValues, 'fieldOptions' => $fieldOptions, 'isWatching' => $isWatching]);
});

/* ==================================================================
 * AGENT – Watch / Unwatch Ticket
 * ================================================================== */

$router->post('/agent/tickets/{id}/watch', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect("/agent/tickets/{$id}");
    }
    $db = Database::connect();
    $check = $db->prepare('SELECT id, group_id FROM tickets WHERE id = ?');
    $check->execute([$id]);
    $watchTicket = $check->fetch();
    if (!$watchTicket) {
        redirect('/agent/tickets');
    }
    _agentRequireTicketAccess($db, $watchTicket);
    $existing = $db->prepare('SELECT 1 FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?');
    $existing->execute([$id, Auth::id()]);
    if ($existing->fetchColumn()) {
        $db->prepare('DELETE FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?')
           ->execute([$id, Auth::id()]);
    } else {
        $db->prepare('INSERT IGNORE INTO ticket_watchers (ticket_id, user_id) VALUES (?, ?)')
           ->execute([$id, Auth::id()]);
    }
    redirect("/agent/tickets/{$id}");
});

/* ==================================================================
 * AGENT – Merge Ticket into Another
 * ================================================================== */

$router->post('/agent/tickets/{id}/merge', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $sourceId = (int) $p['id'];

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/tickets/{$sourceId}");
    }

    $targetId = (int) ($_POST['merge_into_id'] ?? 0);

    if ($targetId === 0 || $targetId === $sourceId) {
        flash('error', 'Please select a valid ticket to merge into.');
        redirect("/agent/tickets/{$sourceId}");
    }

    $db = Database::connect();

    // Validate source ticket (must exist and not already merged)
    $src = $db->prepare('SELECT id, subject, group_id FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $src->execute([$sourceId]);
    $sourceTicket = $src->fetch();
    if (!$sourceTicket) {
        flash('error', 'Source ticket not found or already merged.');
        redirect("/agent/tickets/{$sourceId}");
    }
    _agentRequireTicketAccess($db, $sourceTicket);

    // Validate target ticket (must exist and not itself be merged)
    $tgt = $db->prepare('SELECT id, subject, group_id FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $tgt->execute([$targetId]);
    $targetTicket = $tgt->fetch();
    if (!$targetTicket) {
        flash('error', 'Target ticket not found or is itself a merged ticket.');
        redirect("/agent/tickets/{$sourceId}");
    }
    _agentRequireTicketAccess($db, $targetTicket);

    $db->beginTransaction();
    try {
        $actor = Auth::fullName();

        // Copy CC users from source to target (skip duplicates)
        $db->prepare(
            'INSERT IGNORE INTO ticket_cc (ticket_id, user_id, added_by)
             SELECT ?, user_id, ? FROM ticket_cc WHERE ticket_id = ?'
        )->execute([$targetId, Auth::id(), $sourceId]);

        // Copy tags from source to target (skip duplicates)
        $db->prepare(
            'INSERT IGNORE INTO ticket_tag_map (ticket_id, tag_id)
             SELECT ?, tag_id FROM ticket_tag_map WHERE ticket_id = ?'
        )->execute([$targetId, $sourceId]);

        // Timeline entry on master ticket
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $targetId, Auth::id(), 'merged',
            "Ticket #{$sourceId} ({$sourceTicket['subject']}) was merged into this ticket by {$actor}",
        ]);

        // Timeline entry on source ticket
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $sourceId, Auth::id(), 'merged',
            "This ticket was merged into #{$targetId} ({$targetTicket['subject']}) by {$actor}",
        ]);

        // Close source ticket and set merged_into_ticket_id
        $db->prepare(
            'UPDATE tickets SET status = ?, merged_into_ticket_id = ? WHERE id = ?'
        )->execute(['closed', $targetId, $sourceId]);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        flash('error', 'Merge failed. Please try again.');
        redirect("/agent/tickets/{$sourceId}");
    }

    notifyTicketMerged($db, $sourceId, $targetId);

    flash('success', "Ticket #{$sourceId} merged into #{$targetId}.");
    redirect("/agent/tickets/{$targetId}");
});

/* ==================================================================
 * AGENT – Split Ticket
 * ================================================================== */

$router->get('/agent/tickets/{id}/split', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*, l.name AS location_name, tt.name AS type_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name
         FROM tickets t
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         LEFT JOIN locations l     ON t.location_id = l.id
         LEFT JOIN users c         ON t.created_by = c.id
         WHERE t.id = ? AND t.merged_into_ticket_id IS NULL"
    );
    $stmt->execute([(int) $p['id']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found or already merged.');
        redirect('/agent/tickets');
    }

    _agentRequireTicketAccess($db, $ticket);

    // Public comments only
    $commentsStmt = $db->prepare(
        "SELECT tl.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_timeline tl
         LEFT JOIN users u ON tl.user_id = u.id
         WHERE tl.ticket_id = ? AND tl.action = 'comment' AND tl.is_internal = 0
         ORDER BY tl.created_at ASC"
    );
    $commentsStmt->execute([$ticket['id']]);
    $comments = $commentsStmt->fetchAll();

    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin') ORDER BY first_name")->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

    render('agent/tickets/split', compact('ticket', 'comments', 'agents', 'priorities', 'types', 'groups'));
});

$router->post('/agent/tickets/{id}/split', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $sourceId = (int) $p['id'];

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/tickets/{$sourceId}/split");
    }

    $subject  = trim($_POST['subject'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $typeId   = ($_POST['type_id'] ?? '') !== '' ? (int) $_POST['type_id'] : null;
    $priId    = ($_POST['priority_id'] ?? '') !== '' ? (int) $_POST['priority_id'] : null;
    $assignTo = ($_POST['assigned_to'] ?? '') !== '' ? (int) $_POST['assigned_to'] : null;
    $groupId  = ($_POST['group_id'] ?? '') !== '' ? (int) $_POST['group_id'] : null;
    $moveIds  = array_filter(array_map('intval', (array) ($_POST['move_comments'] ?? [])));

    if ($subject === '') {
        flash('error', 'Subject is required for the new ticket.');
        redirect("/agent/tickets/{$sourceId}/split");
    }

    $db = Database::connect();

    $src = $db->prepare('SELECT * FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $src->execute([$sourceId]);
    $sourceTicket = $src->fetch();
    if (!$sourceTicket) {
        flash('error', 'Source ticket not found or already merged.');
        redirect('/agent/tickets');
    }

    _agentRequireTicketAccess($db, $sourceTicket);

    $newId = null;
    $db->beginTransaction();
    try {
        $actor = Auth::fullName();

        // Create new ticket (inherit location from source)
        $db->prepare(
            'INSERT INTO tickets (subject, description, created_by, type_id, location_id, status, priority_id, assigned_to, group_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$subject, $desc, Auth::id(), $typeId, $sourceTicket['location_id'], 'open', $priId, $assignTo, $groupId]);
        $newId = (int) $db->lastInsertId();

        // Timeline entry on new ticket
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $newId, Auth::id(), 'split',
            "This ticket was created by splitting Ticket #{$sourceId} ({$sourceTicket['subject']}) by {$actor}.",
        ]);

        // Move selected comments to new ticket
        $moved = 0;
        if ($moveIds) {
            $placeholders = implode(',', array_fill(0, count($moveIds), '?'));
            $verifyStmt = $db->prepare(
                "SELECT id FROM ticket_timeline WHERE id IN ({$placeholders}) AND ticket_id = ? AND action = 'comment' AND is_internal = 0"
            );
            $verifyStmt->execute(array_merge(array_values($moveIds), [$sourceId]));
            $validIds = $verifyStmt->fetchAll(PDO::FETCH_COLUMN);

            if ($validIds) {
                $ph2 = implode(',', array_fill(0, count($validIds), '?'));
                $db->prepare("UPDATE ticket_timeline SET ticket_id = ? WHERE id IN ({$ph2})")
                   ->execute(array_merge([$newId], $validIds));
                $db->prepare("UPDATE ticket_attachments SET ticket_id = ? WHERE timeline_id IN ({$ph2})")
                   ->execute(array_merge([$newId], $validIds));
                $moved = count($validIds);
            }
        }

        // Timeline entry on source ticket
        $moveLine = $moved > 0 ? " {$moved} comment(s) moved to new ticket." : '';
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $sourceId, Auth::id(), 'split',
            "Ticket split: new Ticket #{$newId} (\"{$subject}\") created by {$actor}.{$moveLine}",
        ]);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        flash('error', 'Split failed. Please try again.');
        redirect("/agent/tickets/{$sourceId}/split");
    }

    flash('success', "Ticket #{$sourceId} split — new Ticket #{$newId} created.");
    redirect("/agent/tickets/{$newId}");
});

/* ==================================================================
 * AGENT – Save Custom Field Values on a Ticket
 * ================================================================== */

$router->post('/agent/tickets/{id}/fields', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/tickets/{$id}");
    }

    $db = Database::connect();
    $t  = $db->prepare('SELECT id, group_id FROM tickets WHERE id = ?');
    $t->execute([$id]);
    $fieldsTicket = $t->fetch();
    if (!$fieldsTicket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/tickets');
    }
    _agentRequireTicketAccess($db, $fieldsTicket);

    $fields   = $db->query('SELECT * FROM ticket_form_fields WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
    $saveStmt = $db->prepare(
        'INSERT INTO ticket_field_values (ticket_id, field_id, value) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );

    foreach ($fields as $field) {
        $key = 'field_' . $field['id'];
        if ($field['field_type'] === 'dependent') {
            $val = json_encode([
                'l1' => $_POST[$key . '_l1'] ?? null,
                'l2' => $_POST[$key . '_l2'] ?? null,
                'l3' => $_POST[$key . '_l3'] ?? null,
            ]);
        } elseif ($field['field_type'] === 'checkbox') {
            $val = isset($_POST[$key]) ? '1' : '0';
        } else {
            $val = $_POST[$key] ?? null;
        }
        if ($val === null || $val === '') {
            $db->prepare('DELETE FROM ticket_field_values WHERE ticket_id = ? AND field_id = ?')
               ->execute([$id, $field['id']]);
            continue;
        }
        $saveStmt->execute([$id, $field['id'], $val]);
    }

    flash('success', 'Custom fields updated.');
    redirect("/agent/tickets/{$id}");
});

/* ==================================================================
 * AGENT – Add Comment / Internal Note to Ticket
 * ================================================================== */

$router->post('/agent/tickets/{id}/comment', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/tickets/{$id}");
    }

    $message    = trim($_POST['message'] ?? '');
    $isInternal = !empty($_POST['is_internal']) ? 1 : 0;

    if ($message === '') {
        flash('error', 'Message cannot be empty.');
        redirect("/agent/tickets/{$id}");
    }

    $db = Database::connect();

    // Verify ticket exists and agent has access
    $stmt = $db->prepare('SELECT id, group_id FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    $commentTicket = $stmt->fetch();
    if (!$commentTicket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/tickets');
    }
    _agentRequireTicketAccess($db, $commentTicket);

    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, ?)'
    )->execute([$id, Auth::id(), 'comment', $message, $isInternal]);
    $timelineId = (int) $db->lastInsertId();

    // Process @mentions and create notifications
    processAtMentions($db, $message, $id, $timelineId, Auth::id());

    // Handle file attachments
    $attachments = handleAttachmentUploads('attachments');
    saveAttachments($db, $id, $timelineId, Auth::id(), $attachments);

    // Email the ticket creator for non-internal comments
    if (!$isInternal) {
        notifyTicketCreator($db, $id, $message, Auth::fullName());
        notifyCcUsers($db, $id, $message, Auth::fullName());
        notifyWatchers($db, $id, $message, Auth::fullName());

        // SLA: record first response if this is the first agent/admin public reply
        $ticket = $db->prepare('SELECT created_by, first_responded_at FROM tickets WHERE id = ?');
        $ticket->execute([$id]);
        $tRow = $ticket->fetch();
        if ($tRow && $tRow['first_responded_at'] === null && (int) $tRow['created_by'] !== Auth::id()) {
            $db->prepare('UPDATE tickets SET first_responded_at = NOW() WHERE id = ?')->execute([$id]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
            )->execute([$id, 'sla_set', 'First response recorded']);
        }
    }

    $base = $isInternal ? 'Internal note added.' : 'Comment added.';
    if (!empty($attachments)) {
        $base .= ' ' . count($attachments) . ' file(s) attached.';
    }

    // Optional: change ticket status after posting
    $statusAfter  = trim($_POST['status_after'] ?? '');
    $validStatuses = ['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party', 'resolved', 'closed'];
    if ($statusAfter !== '' && in_array($statusAfter, $validStatuses, true)) {
        $csStmt = $db->prepare('SELECT status FROM tickets WHERE id = ?');
        $csStmt->execute([$id]);
        $currentTicket = $csStmt->fetch();
        if ($currentTicket && $currentTicket['status'] !== $statusAfter) {
            $oldStatus = $currentTicket['status'];
            $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$statusAfter, $id]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
            )->execute([$id, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$statusAfter}"]);
            $csatTrigger = getSetting('csat_trigger_status', 'resolved');
            if ($statusAfter === $csatTrigger) {
                sendCsatSurvey($db, $id);
            }
            $pausingStatuses = ['pending', 'waiting_on_customer', 'waiting_on_third_party'];
            if (in_array($statusAfter, $pausingStatuses, true)) {
                Sla::pause($db, $id);
            } elseif (in_array($oldStatus, $pausingStatuses, true)) {
                Sla::resume($db, $id);
            }
            $statusLabelsMap = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'waiting_on_customer' => 'Waiting on Customer', 'waiting_on_third_party' => 'Waiting on Third Party', 'resolved' => 'Resolved', 'closed' => 'Closed'];
            $base .= ' Status set to ' . ($statusLabelsMap[$statusAfter] ?? $statusAfter) . '.';
        }
    }

    flash('success', $base);
    redirect("/agent/tickets/{$id}");
});

/* ==================================================================
 * AGENT – Update Ticket (status, priority, assignment)
 * ================================================================== */

$router->post('/agent/tickets/{id}/update', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/tickets/{$id}");
    }

    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/tickets');
    }

    _agentRequireTicketAccess($db, $ticket);

    $changes = [];

    // Status change
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party', 'resolved', 'closed'];
    if ($newStatus !== '' && in_array($newStatus, $validStatuses, true) && $newStatus !== $ticket['status']) {
        $oldStatus = $ticket['status'];
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$newStatus}"]);
        $changes[] = 'status';

        // CSAT survey trigger
        $csatTrigger = getSetting('csat_trigger_status', 'resolved');
        if ($newStatus === $csatTrigger) {
            sendCsatSurvey($db, $id);
        }

        $pausingStatuses = ['pending', 'waiting_on_customer', 'waiting_on_third_party'];
        if (in_array($newStatus, $pausingStatuses, true)) {
            Sla::pause($db, $id);
        } elseif (in_array($oldStatus, $pausingStatuses, true)) {
            Sla::resume($db, $id);
        }
    }

    // Priority change
    $newPriorityRaw = $_POST['priority_id'] ?? '';
    $newPriority = $newPriorityRaw === '' ? null : (int) $newPriorityRaw;
    $oldPriority = $ticket['priority_id'] ? (int) $ticket['priority_id'] : null;
    if ($newPriority !== $oldPriority) {
        $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')->execute([$newPriority, $id]);

        $oldName = 'None';
        $newName = 'None';
        if ($oldPriority) {
            $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
            $s->execute([$oldPriority]);
            $oldName = $s->fetchColumn() ?: 'None';
        }
        if ($newPriority) {
            $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
            $s->execute([$newPriority]);
            $newName = $s->fetchColumn() ?: 'None';
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'priority_changed', "Priority changed from {$oldName} to {$newName}"]);
        $changes[] = 'priority';

        if ($newPriority) {
            Sla::onPriorityChanged($db, $id, $newPriority);
        }
    }

    // Assignment change
    $newAssignedRaw = $_POST['assigned_to'] ?? '';
    $newAssigned = $newAssignedRaw === '' ? null : (int) $newAssignedRaw;
    $oldAssigned = $ticket['assigned_to'] ? (int) $ticket['assigned_to'] : null;
    if ($newAssigned !== $oldAssigned) {
        $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$newAssigned, $id]);

        $agentName = 'Unassigned';
        if ($newAssigned) {
            $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
            $s->execute([$newAssigned]);
            $agentName = $s->fetchColumn() ?: 'Unknown';
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'assigned', "Assigned to {$agentName}"]);
        $changes[] = 'assignment';
    }

    // Group change
    $newGroupRaw = $_POST['group_id'] ?? '';
    $newGroup = $newGroupRaw === '' ? null : (int) $newGroupRaw;
    $oldGroup = $ticket['group_id'] ? (int) $ticket['group_id'] : null;
    if ($newGroup !== $oldGroup) {
        $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$newGroup, $id]);

        $oldGroupName = 'None';
        $newGroupName = 'None';
        if ($oldGroup) {
            $s = $db->prepare('SELECT name FROM groups WHERE id = ?');
            $s->execute([$oldGroup]);
            $oldGroupName = $s->fetchColumn() ?: 'None';
        }
        if ($newGroup) {
            $s = $db->prepare('SELECT name FROM groups WHERE id = ?');
            $s->execute([$newGroup]);
            $newGroupName = $s->fetchColumn() ?: 'None';
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'group_changed', "Group changed from {$oldGroupName} to {$newGroupName}"]);
        $changes[] = 'group';
    }

    // Run automations on ticket update
    if (!empty($changes)) {
        runAutomations($db, $id, 'ticket_updated');
        flash('success', 'Ticket updated: ' . implode(', ', $changes) . '.');
    } else {
        flash('info', 'No changes made.');
    }
    redirect("/agent/tickets/{$id}");
});

/* ==================================================================
 * AGENT – Download Attachment
 * ================================================================== */

$router->get('/agent/attachments/{id}/download', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();

    $stmt = $db->prepare(
        'SELECT ta.*, t.group_id AS ticket_group_id
         FROM ticket_attachments ta
         JOIN tickets t ON ta.ticket_id = t.id
         WHERE ta.id = ?'
    );
    $stmt->execute([(int) $p['id']]);
    $att = $stmt->fetch();

    if (!$att) {
        flash('error', 'Attachment not found.');
        redirect('/agent/tickets');
    }

    _agentRequireTicketAccess($db, ['group_id' => $att['ticket_group_id']]);

    $filePath = ATTACHMENT_STORAGE_PATH . $att['stored_name'];
    if (!file_exists($filePath)) {
        flash('error', 'File not found on server.');
        redirect('/agent/tickets/' . $att['ticket_id']);
    }

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $att['original_name']) . '"');
    header('Content-Length: ' . $att['file_size']);
    readfile($filePath);
    exit;
});

/* ==================================================================
 * AGENT – Canned Responses JSON (used by ticket reply picker)
 * ================================================================== */

$router->get('/agent/canned-responses/json', function () {
    Auth::requireRole('agent', 'admin');
    $db  = Database::connect();
    $uid = Auth::id();

    $personal = $db->prepare(
        'SELECT id, title, body, sort_order, 0 AS is_global FROM canned_responses
          WHERE user_id = ? ORDER BY sort_order, title'
    );
    $personal->execute([$uid]);

    $global = $db->query(
        'SELECT id, title, body, sort_order, 1 AS is_global FROM canned_responses
          WHERE user_id IS NULL ORDER BY sort_order, title'
    )->fetchAll();

    header('Content-Type: application/json');
    echo json_encode(array_merge($personal->fetchAll(), $global));
    exit;
});

/* ==================================================================
 * AGENT – Personal Canned Responses
 * ================================================================== */

$router->get('/agent/canned-responses', function () {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();
    $personal = $db->prepare(
        'SELECT * FROM canned_responses WHERE user_id = ? ORDER BY sort_order, title'
    );
    $personal->execute([Auth::id()]);
    $myResponses = $personal->fetchAll();

    $global = $db->query(
        'SELECT * FROM canned_responses WHERE user_id IS NULL ORDER BY sort_order, title'
    )->fetchAll();

    render('agent/canned-responses/index', ['myResponses' => $myResponses, 'globalResponses' => $global]);
});

$router->get('/agent/canned-responses/create', function () {
    Auth::requireRole('agent', 'admin');
    render('agent/canned-responses/form', ['editing' => null]);
});

$router->post('/agent/canned-responses/create', function () {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/canned-responses/create');
    }
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($title === '' || $body === '') {
        flashInput($_POST);
        flash('error', 'Title and body are required.');
        redirect('/agent/canned-responses/create');
    }
    Database::connect()->prepare(
        'INSERT INTO canned_responses (user_id, title, body, sort_order) VALUES (?, ?, ?, ?)'
    )->execute([Auth::id(), $title, $body, $order]);
    flash('success', 'Canned response created.');
    redirect('/agent/canned-responses');
});

$router->get('/agent/canned-responses/{id}/edit', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM canned_responses WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $p['id'], Auth::id()]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Canned response not found.');
        redirect('/agent/canned-responses');
    }
    render('agent/canned-responses/form', ['editing' => $editing]);
});

$router->post('/agent/canned-responses/{id}/edit', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/canned-responses/{$id}/edit");
    }
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($title === '' || $body === '') {
        flashInput($_POST);
        flash('error', 'Title and body are required.');
        redirect("/agent/canned-responses/{$id}/edit");
    }
    Database::connect()->prepare(
        'UPDATE canned_responses SET title = ?, body = ?, sort_order = ? WHERE id = ? AND user_id = ?'
    )->execute([$title, $body, $order, $id, Auth::id()]);
    flash('success', 'Canned response updated.');
    redirect('/agent/canned-responses');
});

$router->post('/agent/canned-responses/{id}/delete', function (array $p) {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/canned-responses');
    }
    Database::connect()->prepare(
        'DELETE FROM canned_responses WHERE id = ? AND user_id = ?'
    )->execute([(int) $p['id'], Auth::id()]);
    flash('success', 'Canned response deleted.');
    redirect('/agent/canned-responses');
});
