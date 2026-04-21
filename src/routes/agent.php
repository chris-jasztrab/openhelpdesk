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
    if (!in_array(Auth::role(), ['agent', 'power_user'], true)) {
        return; // admins: confidential access handled by re-auth flow in the route
    }

    $ticketId = isset($ticket['id']) ? (int) $ticket['id'] : null;
    $userId   = (int) Auth::id();

    $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
    $gs->execute([$userId]);
    $agentGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

    if (empty($agentGroups)) {
        // Agent not in any group — block confidential tickets they are not authorised for
        if (!empty($ticket['type_id'])) {
            $cStmt = $db->prepare('SELECT is_confidential, group_id FROM ticket_types WHERE id = ?');
            $cStmt->execute([$ticket['type_id']]);
            $cType = $cStmt->fetch();
            if ($cType && $cType['is_confidential'] && $cType['group_id']) {
                $inGroup = $db->prepare('SELECT 1 FROM group_user_map WHERE group_id = ? AND user_id = ?');
                $inGroup->execute([$cType['group_id'], $userId]);
                if (!$inGroup->fetchColumn() && !ticketAccessExempt($db, $userId, $ticketId)) {
                    flash('error', 'You do not have access to this ticket.');
                    redirect('/agent/tickets');
                }
            }
        }
        return;
    }

    if ($ticket['group_id'] === null) {
        return; // unassigned ticket: visible to all agents
    }

    if (!in_array((int) $ticket['group_id'], $agentGroups, true)
        && !ticketAccessExempt($db, $userId, $ticketId)) {
        flash('error', 'You do not have access to this ticket.');
        redirect('/agent/tickets');
    }
}

/* ==================================================================
 * AGENT – Help / Documentation
 * ================================================================== */

$validHelpPages = ['dashboard', 'ticket-list', 'working-tickets', 'canned-responses'];

$router->get('/agent/help', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
    render('agent/docs/index', [
        'sidebarItems' => agentSidebar('help'),
        'layout'       => 'app',
        'pageTitle'    => 'Agent Help',
        'breadcrumbs'  => [['label' => 'Help']],
    ]);
});

$router->get('/agent/help/{page}', function (array $p) use ($validHelpPages) {
    Auth::requireRole('agent', 'admin', 'power_user');
    $page = $p['page'] ?? '';
    if (!in_array($page, $validHelpPages, true)) {
        redirect('/agent/help');
    }
    $titles = [
        'dashboard'        => 'Dashboard',
        'ticket-list'      => 'Ticket List & Filters',
        'working-tickets'  => 'Working on Tickets',
        'canned-responses' => 'Canned Responses',
    ];
    render('agent/docs/' . $page, [
        'sidebarItems' => agentSidebar('help'),
        'layout'       => 'app',
        'pageTitle'    => 'Help: ' . ($titles[$page] ?? $page),
        'breadcrumbs'  => [
            ['label' => 'Help', 'url' => '/agent/help'],
            ['label' => $titles[$page] ?? $page],
        ],
    ]);
});

/* ==================================================================
 * AGENT – Ticket Viewing
 * ================================================================== */

$router->get('/agent/tickets', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
    $db = Database::connect();

    // Compute default filter URL for client-side persistence logic
    $defaultFilterUrl = '';
    $defStmt = $db->prepare(
        'SELECT filters FROM saved_filters WHERE user_id = ? AND is_default = 1 LIMIT 1'
    );
    $defStmt->execute([Auth::id()]);
    $defaultFilter = $defStmt->fetchColumn();
    if ($defaultFilter) {
        $filterData = json_decode($defaultFilter, true) ?: [];
        if ($filterData) {
            $defaultFilterUrl = '/agent/tickets?' . http_build_query($filterData);
        }
    }

    // Read filter params (multi-select arrays for status/priority/type/location/agent/group)
    $fStatus   = array_values(array_filter(array_map('trim', (array) ($_GET['status']   ?? []))));
    $fPriority = array_values(array_filter(array_map('trim', (array) ($_GET['priority'] ?? []))));
    $fType     = array_values(array_filter(array_map('trim', (array) ($_GET['type']     ?? []))));
    $fLocation = array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? []))));
    $fAgent    = array_values(array_filter(array_map('trim', (array) ($_GET['agent']    ?? []))));
    $fGroup    = array_values(array_filter(array_map('trim', (array) ($_GET['group']    ?? []))));
    $fSearch      = trim($_GET['q'] ?? '');
    $fWatched     = !empty($_GET['watched']) ? '1' : '';
    $fResolvedToday = !empty($_GET['resolved_today']);
    $fEscalatedToMe = !empty($_GET['escalated_to_me']);

    $where  = [];
    $params = [];

    if (!empty($fStatus)) {
        $placeholders = implode(',', array_fill(0, count($fStatus), '?'));
        $where[]  = 't.status IN (' . $placeholders . ')';
        $params   = array_merge($params, $fStatus);
    }
    if (!empty($fPriority)) {
        $ids = array_map('intval', $fPriority);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[]  = 't.priority_id IN (' . $placeholders . ')';
        $params   = array_merge($params, $ids);
    }
    if (!empty($fType)) {
        $ids = array_map('intval', $fType);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[]  = 't.type_id IN (' . $placeholders . ')';
        $params   = array_merge($params, $ids);
    }
    if (!empty($fLocation)) {
        $ids = array_map('intval', $fLocation);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[]  = 't.location_id IN (' . $placeholders . ')';
        $params   = array_merge($params, $ids);
    }
    if (!empty($fAgent)) {
        $agentConds  = [];
        $otherAgents = [];
        foreach ($fAgent as $v) {
            if ($v === 'unassigned') {
                $agentConds[] = 't.assigned_to IS NULL';
            } elseif ($v === 'mine') {
                $agentConds[] = 't.assigned_to = ?';
                $params[]     = Auth::id();
            } else {
                $otherAgents[] = (int) $v;
            }
        }
        if (!empty($otherAgents)) {
            $placeholders = implode(',', array_fill(0, count($otherAgents), '?'));
            $agentConds[] = 't.assigned_to IN (' . $placeholders . ')';
            $params       = array_merge($params, $otherAgents);
        }
        if (!empty($agentConds)) {
            $where[] = '(' . implode(' OR ', $agentConds) . ')';
        }
    }
    if (!empty($fGroup)) {
        $groupConds  = [];
        $otherGroups = [];
        foreach ($fGroup as $v) {
            if ($v === 'none') {
                $groupConds[] = 't.group_id IS NULL';
            } else {
                $otherGroups[] = (int) $v;
            }
        }
        if (!empty($otherGroups)) {
            $placeholders = implode(',', array_fill(0, count($otherGroups), '?'));
            $groupConds[] = 't.group_id IN (' . $placeholders . ')';
            $params       = array_merge($params, $otherGroups);
        }
        if (!empty($groupConds)) {
            $where[] = '(' . implode(' OR ', $groupConds) . ')';
        }
    }
    if ($fSearch !== '') {
        $where[]  = 't.subject LIKE ?';
        $params[] = '%' . $fSearch . '%';
    }

    if ($fWatched) {
        $where[]  = 't.id IN (SELECT ticket_id FROM ticket_watchers WHERE user_id = ?)';
        $params[] = Auth::id();
    }

    if ($fResolvedToday) {
        $where[] = "t.status = 'resolved' AND DATE(t.updated_at) = CURDATE()";
    }

    if ($fEscalatedToMe) {
        $where[]  = "t.assigned_to = ? AND t.escalation_level > 0 AND t.status IN ('open','in_progress','pending')";
        $params[] = Auth::id();
    }

    // Group-based visibility: agents who belong to groups can only see those groups' tickets.
    // Agents in no groups, and all admins, see all tickets (except confidential restrictions).
    $agentGroupIds = [];
    if (in_array(Auth::role(), ['agent', 'power_user'], true)) {
        $gStmt = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gStmt->execute([Auth::id()]);
        $agentGroupIds = array_map('intval', $gStmt->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($agentGroupIds)) {
            $placeholders = implode(',', array_fill(0, count($agentGroupIds), '?'));
            $where[]      = 't.group_id IN (' . $placeholders . ')';
            $params       = array_merge($params, $agentGroupIds);
        } else {
            // Agent has no group restrictions but must still not see confidential tickets
            // they are not authorised for
            $where[] = "NOT EXISTS (
                SELECT 1 FROM ticket_types ct
                WHERE ct.id = t.type_id
                  AND ct.is_confidential = 1
                  AND ct.group_id IS NOT NULL
                  AND ct.group_id NOT IN (SELECT group_id FROM group_user_map WHERE user_id = ?)
            )";
            $params[] = Auth::id();
        }
    }

    // Preload confidential ticket type info for admin redaction in the template
    $confidentialTypeIds = [];
    $adminGroupIds       = [];
    if (Auth::role() === 'admin') {
        $confStmt = $db->query('SELECT id, group_id FROM ticket_types WHERE is_confidential = 1 AND group_id IS NOT NULL');
        foreach ($confStmt->fetchAll() as $ct) {
            $confidentialTypeIds[] = (int) $ct['id'];
        }
        if (!empty($confidentialTypeIds)) {
            $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
            $gs->execute([Auth::id()]);
            $adminGroupIds = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    $sql = "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name, tt.color AS type_color,
                tt.is_confidential AS type_confidential, tt.group_id AS type_group_id,
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

    // Count all visible tickets (no user filters, just group restriction)
    if (!empty($agentGroupIds)) {
        $allPlaceholders = implode(',', array_fill(0, count($agentGroupIds), '?'));
        $allStmt = $db->prepare('SELECT COUNT(*) FROM tickets t WHERE t.group_id IN (' . $allPlaceholders . ')');
        $allStmt->execute($agentGroupIds);
    } else {
        $allStmt = $db->query('SELECT COUNT(*) FROM tickets');
    }
    $allTickets = (int) $allStmt->fetchColumn();

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
    $allowedPerPage = [25, 50, 100, 200];
    $perPage    = (isset($_GET['per_page']) && in_array((int) $_GET['per_page'], $allowedPerPage, true)) ? (int) $_GET['per_page'] : 25;
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

    // Build group → agents map for quick-assign dropdowns
    $gaRows = $db->query(
        "SELECT gum.group_id, u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
         FROM group_user_map gum
         JOIN users u ON gum.user_id = u.id
         WHERE u.role IN ('agent','admin','power_user')
         ORDER BY u.first_name, u.last_name"
    )->fetchAll();
    $groupAgents = [];
    foreach ($gaRows as $row) {
        $groupAgents[(int) $row['group_id']][] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
    $allAgentsForAssign = $db->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name, last_name"
    )->fetchAll();

    if (!empty($agentGroupIds)) {
        $placeholders = implode(',', array_fill(0, count($agentGroupIds), '?'));
        $gDropStmt = $db->prepare("SELECT * FROM `groups` WHERE id IN ($placeholders) ORDER BY sort_order, name");
        $gDropStmt->execute($agentGroupIds);
        $groups = $gDropStmt->fetchAll();
    } else {
        $groups = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    }

    $filters = [
        'status'        => $fStatus,   // array
        'priority'      => $fPriority, // array
        'type'          => $fType,     // array
        'location'      => $fLocation, // array
        'agent'         => $fAgent,    // array
        'group'         => $fGroup,    // array
        'q'             => $fSearch,
        'watched'       => $fWatched,
        'resolved_today' => $fResolvedToday,
        'escalated_to_me' => $fEscalatedToMe,
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
        'tickets'            => $tickets,
        'priorities'         => $priorities,
        'types'              => $types,
        'locations'          => $locations,
        'agents'             => $agents,
        'groups'             => $groups,
        'groupAgents'        => $groupAgents,
        'allAgentsForAssign' => $allAgentsForAssign,
        'filters'            => $filters,
        'savedFilters'       => $savedFilters,
        'page'               => $page,
        'perPage'            => $perPage,
        'totalPages'         => $totalPages,
        'totalTickets'       => $totalTickets,
        'allTickets'         => $allTickets,
        'sort'               => $sort,
        'dir'                => strtolower($dir),
        'visibleColumns'   => getUserColumns(Auth::id()),
        'groupRestricted'    => !empty($agentGroupIds),
        'defaultFilterUrl'   => $defaultFilterUrl,
        'confidentialTypeIds' => $confidentialTypeIds,
        'adminGroupIds'       => $adminGroupIds,
    ]);
});

/* ── Column Preferences (Agent) ───────────────────────────────────── */

$router->post('/agent/tickets/columns', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    foreach (['status', 'priority', 'type', 'location', 'agent', 'group'] as $key) {
        $vals = array_values(array_filter(array_map('trim', (array) ($_POST[$key] ?? []))));
        if (!empty($vals)) {
            $filterData[$key] = $vals;
        }
    }
    if (trim($_POST['q'] ?? '') !== '') {
        $filterData['q'] = trim($_POST['q']);
    }

    $db = Database::connect();
    $stmt = $db->prepare('INSERT INTO saved_filters (user_id, name, filters) VALUES (?, ?, ?)');
    $stmt->execute([Auth::id(), $name, json_encode($filterData)]);

    flash('success', 'Filter "' . e($name) . '" saved.');
    $qs = http_build_query($filterData);
    redirect('/agent/tickets' . ($qs ? '?' . $qs : ''));
});

$router->post('/agent/tickets/filters/{id}/delete', function (array $p) {
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    $results = $stmt->fetchAll();

    // Redact confidential ticket subjects for users not in the type's group
    if (Auth::role() === 'admin') {
        $confStmt = $db->query('SELECT id, group_id FROM ticket_types WHERE is_confidential = 1 AND group_id IS NOT NULL');
        $confTypes = [];
        foreach ($confStmt->fetchAll() as $ct) {
            $confTypes[(int) $ct['id']] = (int) $ct['group_id'];
        }
        if (!empty($confTypes)) {
            $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
            $gs->execute([Auth::id()]);
            $myGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

            foreach ($results as &$r) {
                $tTypeId = null;
                if (!empty($r['id'])) {
                    $ts = $db->prepare('SELECT type_id FROM tickets WHERE id = ?');
                    $ts->execute([$r['id']]);
                    $tTypeId = $ts->fetchColumn();
                }
                if ($tTypeId && isset($confTypes[(int) $tTypeId])
                    && !in_array($confTypes[(int) $tTypeId], $myGroups, true)) {
                    $r['subject'] = '[Confidential]';
                    $r['creator_name'] = '—';
                }
            }
            unset($r);
        }
    }

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
});

$router->get('/agent/tickets/create', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
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

    // Unified field list (system + custom) for rendering
    $unifiedFields = getUnifiedFieldList($db, false);
    $customFields  = array_map(fn($u) => $u['field'], array_filter($unifiedFields, fn($u) => $u['kind'] === 'custom'));
    $fieldOptions  = [];
    foreach ($customFields as $f) {
        if (in_array($f['field_type'], ['dropdown', 'dependent'], true)) {
            $s = $db->prepare(
                'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
            );
            $s->execute([$f['id']]);
            $fieldOptions[$f['id']] = $s->fetchAll();
        }
    }
    $fieldTypeMap = getFieldTypeMap($db);

    render('admin/tickets/create', [
        'types'         => $types,
        'priorities'    => $priorities,
        'locations'     => $locations,
        'groups'        => $groups,
        'agents'        => $agents,
        'templates'     => $templates,
        'isAgent'       => true,
        'formAction'    => '/admin/tickets/create',
        'customFields'  => $customFields,
        'fieldOptions'  => $fieldOptions,
        'fieldTypeMap'  => $fieldTypeMap,
        'unifiedFields' => $unifiedFields,
    ]);
});

$router->post('/agent/tickets/bulk', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
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

    // Filter out confidential tickets the user cannot access
    if (Auth::role() === 'admin') {
        $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gs->execute([Auth::id()]);
        $myGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

        $allowed = [];
        foreach ($ticketIds as $tid) {
            $ts = $db->prepare(
                'SELECT t.type_id, tt.is_confidential, tt.group_id AS type_group_id
                 FROM tickets t LEFT JOIN ticket_types tt ON t.type_id = tt.id WHERE t.id = ?'
            );
            $ts->execute([$tid]);
            $tRow = $ts->fetch();
            if ($tRow && $tRow['is_confidential'] && $tRow['type_group_id']
                && !in_array((int) $tRow['type_group_id'], $myGroups, true)) {
                continue;
            }
            $allowed[] = $tid;
        }
        $ticketIds = $allowed;
        if (empty($ticketIds)) {
            flash('error', 'No accessible tickets selected (confidential tickets were excluded).');
            redirect('/agent/tickets');
        }
    }

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
            $primaryId = (int) ($_POST['primary_ticket_id'] ?? 0);
            if ($primaryId > 0 && in_array($primaryId, $ticketIds)) {
                $targetId  = $primaryId;
                $ticketIds = array_values(array_filter($ticketIds, fn($id) => $id !== $primaryId));
            } else {
                sort($ticketIds);
                $targetId = array_shift($ticketIds);
            }
            $tgt = $db->prepare('SELECT id, subject FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
            $tgt->execute([$targetId]);
            $targetTicket = $tgt->fetch();
            if (!$targetTicket) {
                flash('error', 'Primary ticket not found or already merged.');
                redirect('/agent/tickets');
            }
            $actor  = Auth::fullName();
            // Find the highest priority across all tickets being merged (target + sources)
            $allIds = array_merge([$targetId], $ticketIds);
            $allPlaceholders = implode(',', array_fill(0, count($allIds), '?'));
            $hpStmt = $db->prepare(
                "SELECT tp.id FROM ticket_priorities tp
                 JOIN tickets t ON t.priority_id = tp.id
                 WHERE t.id IN ({$allPlaceholders})
                 ORDER BY tp.sort_order DESC LIMIT 1"
            );
            $hpStmt->execute($allIds);
            $bestPriorityId = $hpStmt->fetchColumn() ?: null;
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
            // Escalate primary ticket priority to the highest across all merged tickets
            if ($bestPriorityId) {
                $curPriStmt = $db->prepare('SELECT priority_id FROM tickets WHERE id = ?');
                $curPriStmt->execute([$targetId]);
                $curPriority = $curPriStmt->fetchColumn();
                if ($bestPriorityId != $curPriority) {
                    $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')
                       ->execute([$bestPriorityId, $targetId]);
                    $npStmt = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
                    $npStmt->execute([$bestPriorityId]);
                    $priorityLabel = $npStmt->fetchColumn();
                    $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
                       ->execute([$targetId, Auth::id(), 'priority_changed', "Priority escalated to {$priorityLabel} during merge by {$actor}"]);
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
    Auth::requireRole('agent', 'admin', 'power_user');
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name, tt.color AS type_color, tt.group_id AS type_group_id,
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

    // Confidential ticket re-authentication gate (admins only)
    if (requiresConfidentialReAuth($db, $ticket)) {
        $ticketId   = (int) $ticket['id'];
        $sessionKey = "confidential_access_{$ticketId}";
        $granted    = $_SESSION[$sessionKey] ?? 0;
        $ttl        = 300; // 5-minute window after re-auth

        if (!$granted || (time() - $granted) > $ttl) {
            render('agent/tickets/confidential-reauth', [
                'ticketId' => $ticketId,
            ]);
            return; // stop — re-auth page rendered instead of the ticket
        }
    }

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

    // Agents list for assignment dropdown — filter by the ticket type's group if set
    if (!empty($ticket['type_group_id'])) {
        $agentStmt = $db->prepare(
            "SELECT u.id, u.first_name, u.last_name
             FROM users u
             INNER JOIN group_user_map gum ON u.id = gum.user_id
             WHERE gum.group_id = ? AND u.role IN ('agent','admin','power_user')
             ORDER BY u.first_name"
        );
        $agentStmt->execute([$ticket['type_group_id']]);
        $agents = $agentStmt->fetchAll();
    } else {
        $agents = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name")->fetchAll();
    }

    // Priorities for update dropdown
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();

    // Ticket types for update dropdown
    $ticketTypes = $db->query('SELECT id, name FROM ticket_types ORDER BY sort_order, name')->fetchAll();

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

    // Escalation history (oldest → newest)
    $escStmt = $db->prepare(
        "SELECT te.step_order, te.reason, te.created_at,
                CONCAT(u_to.first_name,  ' ', u_to.last_name)  AS to_name,
                CONCAT(u_by.first_name,  ' ', u_by.last_name)  AS by_name,
                CONCAT(u_fr.first_name,  ' ', u_fr.last_name)  AS from_name
         FROM ticket_escalations te
         LEFT JOIN users u_to ON te.to_user_id   = u_to.id
         LEFT JOIN users u_by ON te.escalated_by = u_by.id
         LEFT JOIN users u_fr ON te.from_user_id = u_fr.id
         WHERE te.ticket_id = ?
         ORDER BY te.created_at ASC"
    );
    $escStmt->execute([$ticket['id']]);
    $escalationHistory = $escStmt->fetchAll();

    // Does this ticket's type have an escalation path configured, and is there a next step from here?
    $hasEscalationPath = false;
    $nextEscalationStep = null;
    if ($ticket['type_id']) {
        $hStmt = $db->prepare('SELECT COUNT(*) FROM ticket_escalation_steps WHERE ticket_type_id = ?');
        $hStmt->execute([$ticket['type_id']]);
        $hasEscalationPath = (int) $hStmt->fetchColumn() > 0;
        if ($hasEscalationPath) {
            $nextEscalationStep = nextEscalationStep(
                $db,
                (int) $ticket['type_id'],
                (int) ($ticket['escalation_level'] ?? 0),
                (int) Auth::id(),
                !empty($ticket['assigned_to']) ? (int) $ticket['assigned_to'] : null
            );
        }
    }

    render('agent/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'priorities' => $priorities, 'ticketTypes' => $ticketTypes, 'attachments' => $attachments, 'ccUsers' => $ccUsers, 'groups' => $groups, 'customFields' => $customFields, 'fieldValues' => $fieldValues, 'fieldOptions' => $fieldOptions, 'isWatching' => $isWatching, 'escalationHistory' => $escalationHistory, 'hasEscalationPath' => $hasEscalationPath, 'nextEscalationStep' => $nextEscalationStep]);
});

/* ==================================================================
 * AGENT – Confidential Ticket Re-Authentication
 * ================================================================== */

$router->post('/agent/tickets/{id}/confidential-auth', function (array $p) {
    Auth::requireRole('admin');
    $ticketId = (int) $p['id'];

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/tickets/{$ticketId}");
    }

    $password = $_POST['password'] ?? '';
    $db = Database::connect();

    // Verify password
    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([Auth::id()]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        flash('error', 'Incorrect password. Please try again.');
        redirect("/agent/tickets/{$ticketId}");
    }

    // Grant access — store timestamp in session
    $_SESSION["confidential_access_{$ticketId}"] = time();

    // Audit log
    logAudit(
        'confidential_ticket_viewed',
        $ticketId,
        'ticket',
        'Admin ' . Auth::fullName() . ' (ID: ' . Auth::id() . ') accessed confidential ticket #' . $ticketId
    );

    // Timeline entry on the ticket
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, ?, ?, ?, 1)'
    )->execute([
        $ticketId,
        Auth::id(),
        'confidential_access',
        Auth::fullName() . ' viewed this confidential ticket (IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ')',
    ]);

    // Notify all group members via email
    notifyConfidentialAccess($db, $ticketId);

    redirect("/agent/tickets/{$ticketId}");
});

/* ==================================================================
 * AGENT – Watch / Unwatch Ticket
 * ================================================================== */

$router->post('/agent/tickets/{id}/watch', function (array $p) {
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    $src = $db->prepare('SELECT id, subject, group_id, type_id FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $src->execute([$sourceId]);
    $sourceTicket = $src->fetch();
    if (!$sourceTicket) {
        flash('error', 'Source ticket not found or already merged.');
        redirect("/agent/tickets/{$sourceId}");
    }
    _agentRequireTicketAccess($db, $sourceTicket);

    // Validate target ticket (must exist and not itself be merged)
    $tgt = $db->prepare('SELECT id, subject, group_id, type_id FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $tgt->execute([$targetId]);
    $targetTicket = $tgt->fetch();
    if (!$targetTicket) {
        flash('error', 'Target ticket not found or is itself a merged ticket.');
        redirect("/agent/tickets/{$sourceId}");
    }
    _agentRequireTicketAccess($db, $targetTicket);

    // Block merging if either ticket is confidential and user is not in the group
    if (requiresConfidentialReAuth($db, $sourceTicket) || requiresConfidentialReAuth($db, $targetTicket)) {
        flash('error', 'Cannot merge confidential tickets without proper access. Please view each ticket first.');
        redirect("/agent/tickets/{$sourceId}");
    }

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
    Auth::requireRole('agent', 'admin', 'power_user');
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*, l.name AS location_name, tt.name AS type_name, tt.color AS type_color,
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
    Auth::requireRole('agent', 'admin', 'power_user');
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

    if (requiresConfidentialReAuth($db, $sourceTicket)) {
        flash('error', 'Re-authenticate to access this confidential ticket before splitting.');
        redirect("/agent/tickets/{$sourceId}");
    }

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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    $stmt = $db->prepare('SELECT id, group_id, assigned_to FROM tickets WHERE id = ?');
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
    } else {
        // Internal note: notify the assigned agent (if not the note author)
        notifyAgentNoteAdded($db, $id, $message);
    }

    // Auto-assign unassigned ticket to the replying agent
    if (!$isInternal && $commentTicket['assigned_to'] === null) {
        $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([Auth::id(), $id]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'assigned', 'Ticket auto-assigned to ' . Auth::fullName() . ' upon reply']);
        flash('info', 'This ticket was unassigned — it has been automatically assigned to you.');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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

        if (in_array($newStatus, ['resolved', 'closed'], true)) {
            notifyRequesterStatusChanged($db, $id, $newStatus);
        }

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
            Sla::onPriorityChanged($db, $id, $newPriority, $ticket['type_id'] ? (int) $ticket['type_id'] : null);
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
        if ($newAssigned) { notifyAssignedAgent($db, $id, $newAssigned); }
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
        if ($newGroup) { notifyAssignedGroup($db, $id, $newGroup); }
    }

    // Type change
    $newTypeRaw = $_POST['type_id'] ?? '';
    $newType = $newTypeRaw === '' ? null : (int) $newTypeRaw;
    $oldType = $ticket['type_id'] ? (int) $ticket['type_id'] : null;
    if ($newType !== $oldType) {
        $db->prepare('UPDATE tickets SET type_id = ? WHERE id = ?')->execute([$newType, $id]);

        $oldTypeName = 'None';
        $newTypeName = 'None';
        if ($oldType) {
            $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
            $s->execute([$oldType]);
            $oldTypeName = $s->fetchColumn() ?: 'None';
        }
        if ($newType) {
            $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
            $s->execute([$newType]);
            $newTypeName = $s->fetchColumn() ?: 'None';
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'type_changed', "Type changed from {$oldTypeName} to {$newTypeName}"]);
        $changes[] = 'type';

        // Recalculate SLA for new type
        Sla::onTypeChanged($db, $id, $newType);
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
    Auth::requireRole('agent', 'admin', 'power_user');
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

    _agentRequireTicketAccess($db, [
        'id'       => (int) $att['ticket_id'],
        'group_id' => $att['ticket_group_id'],
    ]);

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
 * AGENT – Knowledge Base (read-only browse)
 * ================================================================== */

$_agentKbVars = [
    'sidebarItems' => agentSidebar('kb'),
    'kbBase'       => '/agent/kb',
    'kbPanelLabel' => 'Agent',
    'kbPanelUrl'   => '/agent',
];

$router->get('/agent/kb', function () use ($_agentKbVars) {
    Auth::requireRole('agent', 'admin', 'power_user');
    $categories = Database::connect()->query(
        'SELECT c.*, COUNT(DISTINCT f.id) AS folder_count
         FROM kb_categories c
         LEFT JOIN kb_folders f ON f.category_id = c.id
         GROUP BY c.id
         ORDER BY c.sort_order, c.name'
    )->fetchAll();
    render('portal/kb/index', array_merge($_agentKbVars, ['categories' => $categories]));
});

$router->get('/agent/kb/search', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    $db   = Database::connect();
    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        "SELECT a.title, a.slug, f.name AS folder_name, c.name AS category_name
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         WHERE a.status = 'published' AND (a.title LIKE ? OR a.body_markdown LIKE ?)
         ORDER BY a.updated_at DESC
         LIMIT 10"
    );
    $stmt->execute([$like, $like]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
});

$router->get('/agent/kb/articles/{slug}', function (array $p) use ($_agentKbVars) {
    Auth::requireRole('agent', 'admin', 'power_user');
    $db   = Database::connect();
    $stmt = $db->prepare(
        "SELECT a.*, f.name AS folder_name, f.slug AS folder_slug,
                c.name AS category_name, c.slug AS category_slug
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         WHERE a.slug = ? AND a.status = 'published'"
    );
    $stmt->execute([$p['slug']]);
    $article = $stmt->fetch();
    if (!$article) {
        flash('error', 'Article not found.');
        redirect('/agent/kb');
    }
    $article['body_html'] = renderMarkdown($article['body_markdown']);

    $fc = $db->prepare(
        "SELECT
            SUM(CASE WHEN rating =  1 THEN 1 ELSE 0 END) AS helpful,
            SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) AS not_helpful
         FROM kb_article_ratings WHERE article_id = ?"
    );
    $fc->execute([$article['id']]);
    $counts = $fc->fetch();

    $vq = $db->prepare('SELECT rating FROM kb_article_ratings WHERE article_id = ? AND user_id = ?');
    $vq->execute([$article['id'], Auth::id()]);
    $myVote = $vq->fetchColumn() ?: null;

    $feedback = [
        'helpful'     => (int)($counts['helpful']     ?? 0),
        'not_helpful' => (int)($counts['not_helpful'] ?? 0),
        'my_vote'     => $myVote !== null ? (int)$myVote : null,
    ];

    render('portal/kb/article', array_merge($_agentKbVars, ['article' => $article, 'feedback' => $feedback]));
});

$router->post('/agent/kb/articles/{slug}/feedback', function (array $p) {
    Auth::requireRole('agent', 'admin', 'power_user');
    header('Content-Type: application/json');

    $rating = (int)($_POST['rating'] ?? 0);
    if (!in_array($rating, [1, -1], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid rating.']);
        exit;
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT id FROM kb_articles WHERE slug = ? AND status = ?');
    $stmt->execute([$p['slug'], 'published']);
    $article = $stmt->fetch();
    if (!$article) {
        echo json_encode(['status' => 'error', 'message' => 'Article not found.']);
        exit;
    }

    try {
        $db->prepare(
            'INSERT INTO kb_article_ratings (article_id, user_id, rating)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating)'
        )->execute([(int)$article['id'], Auth::id(), $rating]);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Could not save vote.']);
        exit;
    }

    $fc = $db->prepare(
        "SELECT SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END) AS helpful,
                SUM(CASE WHEN rating=-1 THEN 1 ELSE 0 END) AS not_helpful
         FROM kb_article_ratings WHERE article_id = ?"
    );
    $fc->execute([(int)$article['id']]);
    $counts = $fc->fetch();
    echo json_encode([
        'status'      => 'ok',
        'helpful'     => (int)($counts['helpful']     ?? 0),
        'not_helpful' => (int)($counts['not_helpful'] ?? 0),
    ]);
    exit;
});

$router->get('/agent/kb/{slug}/{folder_slug}', function (array $p) use ($_agentKbVars) {
    Auth::requireRole('agent', 'admin', 'power_user');
    $db = Database::connect();

    $catStmt = $db->prepare('SELECT * FROM kb_categories WHERE slug = ?');
    $catStmt->execute([$p['slug']]);
    $category = $catStmt->fetch();
    if (!$category) {
        flash('error', 'Category not found.');
        redirect('/agent/kb');
    }

    $folderStmt = $db->prepare('SELECT * FROM kb_folders WHERE slug = ? AND category_id = ?');
    $folderStmt->execute([$p['folder_slug'], $category['id']]);
    $folder = $folderStmt->fetch();
    if (!$folder) {
        flash('error', 'Folder not found.');
        redirect('/agent/kb/' . $p['slug']);
    }

    $artStmt = $db->prepare(
        "SELECT id, title, slug, published_at, sort_order
         FROM kb_articles
         WHERE folder_id = ? AND status = 'published'
         ORDER BY sort_order, title"
    );
    $artStmt->execute([$folder['id']]);
    $articles = $artStmt->fetchAll();

    render('portal/kb/folder', array_merge($_agentKbVars, ['category' => $category, 'folder' => $folder, 'articles' => $articles]));
});

$router->get('/agent/kb/{slug}', function (array $p) use ($_agentKbVars) {
    Auth::requireRole('agent', 'admin', 'power_user');
    $db = Database::connect();

    $catStmt = $db->prepare('SELECT * FROM kb_categories WHERE slug = ?');
    $catStmt->execute([$p['slug']]);
    $category = $catStmt->fetch();
    if (!$category) {
        flash('error', 'Category not found.');
        redirect('/agent/kb');
    }

    $folders = $db->prepare(
        'SELECT f.*, COUNT(a.id) AS article_count
         FROM kb_folders f
         LEFT JOIN kb_articles a ON a.folder_id = f.id AND a.status = \'published\'
         WHERE f.category_id = ?
         GROUP BY f.id
         ORDER BY f.sort_order, f.name'
    );
    $folders->execute([$category['id']]);
    $folders = $folders->fetchAll();

    render('portal/kb/category', array_merge($_agentKbVars, ['category' => $category, 'folders' => $folders]));
});

/* ==================================================================
 * AGENT – Canned Responses JSON (used by ticket reply picker)
 * ================================================================== */

$router->get('/agent/canned-responses/json', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
    render('agent/canned-responses/form', ['editing' => null]);
});

$router->post('/agent/canned-responses/create', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
    Auth::requireRole('agent', 'admin', 'power_user');
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
