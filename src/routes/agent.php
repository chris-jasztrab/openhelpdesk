<?php

declare(strict_types=1);

/**
 * Enforce ticket-level visibility for individual agent ticket routes.
 *
 * Rules (mirrors _apiEnforceTicketAccess in api.php):
 *   admin  → unrestricted (confidential access is gated by the re-auth flow in
 *            the detail route, and audited).
 *   confidential ticket type → ONLY the type's confidential-group members or the
 *            ticket creator. `tickets.view_all`, assignment and watching do NOT
 *            grant access.
 *   non-confidential → holders of `tickets.view_all`; or, if the agent belongs to
 *            one or more groups, tickets in those groups plus group-less tickets;
 *            or the creator/assignee/watcher of the ticket. A staff user with no
 *            group and no view_all sees only tickets they created / are assigned /
 *            watch (fail closed).
 *
 * Only $ticket['id'] is trusted; the authoritative group/type/creator are
 * re-fetched so a caller that SELECTed a narrow column set can't bypass a check.
 * Redirects to /agent/tickets with an error flash if access is denied.
 */
function _agentRequireTicketAccess(PDO $db, array $ticket): void
{
    if (Auth::isAdmin()) {
        return; // admins: confidential access handled by the re-auth flow in the route
    }
    if (!Auth::isStaff()) {
        return; // non-staff never reach agent routes (requireStaff upstream)
    }

    $ticketId = isset($ticket['id']) ? (int) $ticket['id'] : 0;
    $userId   = (int) Auth::id();
    if ($ticketId <= 0) {
        return;
    }

    // Authoritative re-fetch: never trust whatever columns the caller happened to
    // SELECT — several pass only id + group_id, which would skip confidential checks.
    $meta = $db->prepare(
        'SELECT t.group_id, t.created_by, tt.is_confidential, tt.group_id AS conf_group_id
         FROM tickets t
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         WHERE t.id = ?'
    );
    $meta->execute([$ticketId]);
    $row = $meta->fetch();
    if (!$row) {
        return; // ticket vanished; the caller handles not-found
    }

    $deny = static function (): void {
        flash('error', 'You do not have access to this ticket.');
        redirect('/agent/tickets');
    };

    $userGroups = userGroupIds($db, $userId);

    // Confidential tickets: confidential-group members or the creator ONLY.
    if ((int) ($row['is_confidential'] ?? 0) === 1) {
        $confGroupId = $row['conf_group_id'] !== null ? (int) $row['conf_group_id'] : null;
        $isMember    = $confGroupId !== null && in_array($confGroupId, $userGroups, true);
        $isCreator   = (int) $row['created_by'] === $userId;
        if (!$isMember && !$isCreator) {
            $deny();
        }
        return;
    }

    // Non-confidential tickets.
    if (Auth::can('tickets.view_all')) {
        return;
    }
    $gid = $row['group_id'] !== null ? (int) $row['group_id'] : null;
    if (!empty($userGroups) && ($gid === null || in_array($gid, $userGroups, true))) {
        return; // group-less (claimable) ticket, or one of the user's groups
    }
    if ((int) $row['created_by'] === $userId) {
        return; // own ticket
    }
    if (ticketAccessExempt($db, $userId, $ticketId)) {
        return; // current assignee or watcher
    }
    $deny();
}

/* ==================================================================
 * AGENT – Help / Documentation
 * ================================================================== */

$validHelpPages = ['dashboard', 'ticket-list', 'working-tickets', 'floor', 'canned-responses'];

$router->get('/agent/help', function () {
    Auth::requireStaff();
    render('agent/docs/index', [
        'sidebarItems' => agentSidebar('help'),
        'layout'       => 'app',
        'pageTitle'    => 'Agent Help',
        'breadcrumbs'  => [['label' => 'Help']],
    ]);
});

$router->get('/agent/help/{page}', function (array $p) use ($validHelpPages) {
    Auth::requireStaff();
    $page = $p['page'] ?? '';
    if (!in_array($page, $validHelpPages, true)) {
        redirect('/agent/help');
    }
    $titles = [
        'dashboard'        => 'Dashboard',
        'ticket-list'      => 'Ticket List & Filters',
        'working-tickets'  => 'Working on Tickets',
        'floor'            => 'Floor Mode',
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
    Auth::requireStaff();
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
    $fResolvedToday = !empty($_GET['resolved_today']) ? '1' : '';
    $fEscalatedToMe = !empty($_GET['escalated_to_me']) ? '1' : '';

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
        // Ticking the "System Wide" meta-branch means "any location":
        // skip the constraint instead of matching the literal row.
        if (!array_intersect($ids, metaLocationIds())) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[]  = 't.location_id IN (' . $placeholders . ')';
            $params   = array_merge($params, $ids);
        }
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
        $where[]  = "t.assigned_to = ? AND t.escalation_level > 0 AND " . ticketStatusSqlIn(ticketOpenBucketSlugs(), 't.status');
        $params[] = Auth::id();
    }

    // Fail-closed ticket visibility (one source of truth in ticketStaffVisibilitySql):
    // non-admin staff see their groups' tickets plus anything view_all / assignment
    // grants, and never a confidential type outside their confidential group. Admins
    // are unrestricted here; confidential rows are redacted in the template below.
    // $agentGroupIds is still used for the "all visible" count and group dropdown.
    $agentGroupIds = [];
    if (Auth::isStaff() && !Auth::isAdmin()) {
        $agentGroupIds = userGroupIds($db, (int) Auth::id());
    }
    $vis      = ticketStaffVisibilitySql($db, (int) Auth::id(), Auth::role(), 't');
    $where[]  = $vis['sql'];
    $params   = array_merge($params, $vis['params']);

    // Preload confidential ticket type info for admin redaction in the template
    $confidentialTypeIds = [];
    $adminGroupIds       = [];
    if (Auth::isAdmin()) {
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
                c.email AS creator_email,
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

    // Count all visible tickets (no user filters, just the visibility predicate)
    $visAll  = ticketStaffVisibilitySql($db, (int) Auth::id(), Auth::role(), 't');
    $allStmt = $db->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $visAll['sql']);
    $allStmt->execute($visAll['params']);
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
    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name")->fetchAll();

    // Build group → agents map for quick-assign dropdowns
    $gaRows = $db->query(
        "SELECT gum.group_id, u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
         FROM group_user_map gum
         JOIN users u ON gum.user_id = u.id
         WHERE " . staffRoleSqlIn('u.role') . "
         ORDER BY u.first_name, u.last_name"
    )->fetchAll();
    $groupAgents = [];
    foreach ($gaRows as $row) {
        $groupAgents[(int) $row['group_id']][] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
    $allAgentsForAssign = $db->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();

    // Group picker scope: by default a non-admin agent can only move tickets into
    // groups they belong to. The 'agents_assign_any_group' setting lifts that and
    // shows every group (admins are never restricted — $agentGroupIds is empty).
    // $myGroups is always the agent's own groups — the banner lists these, never
    // the (possibly site-wide) dropdown scope, which would leak confidential groups.
    $assignAnyGroup = getSetting('agents_assign_any_group', '0') === '1';
    $myGroups = [];
    if (!empty($agentGroupIds)) {
        $placeholders = implode(',', array_fill(0, count($agentGroupIds), '?'));
        $gDropStmt = $db->prepare("SELECT * FROM `groups` WHERE id IN ($placeholders) ORDER BY sort_order, name");
        $gDropStmt->execute($agentGroupIds);
        $myGroups = $gDropStmt->fetchAll();
    }
    if (!empty($agentGroupIds) && !$assignAnyGroup) {
        $groups = $myGroups;
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
        'myGroups'           => $myGroups,
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
        'ticketView'         => getUserTicketView((int) Auth::id()),
        'groupRestricted'    => !empty($agentGroupIds),
        'defaultFilterUrl'   => $defaultFilterUrl,
        'confidentialTypeIds' => $confidentialTypeIds,
        'adminGroupIds'       => $adminGroupIds,
    ]);
});

/* ── Open tickets for one user (from the inbox-view person card) ───── */

$router->get('/agent/tickets/by-user/{userId}', function (array $p) {
    Auth::requireStaff();
    renderTicketsByUserPage((int) $p['userId'], '/agent/tickets');
});

/* ── Column Preferences (Agent) ───────────────────────────────────── */

$router->post('/agent/tickets/columns', function () {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/tickets');
    }

    $columns = $_POST['columns'] ?? [];
    if (!is_array($columns)) {
        $columns = [];
    }
    setUserColumns(Auth::id(), $columns);
    redirect(safeRedirectPath($_POST['_redirect'] ?? null, '/agent/tickets'));
});

/* ── Saved Filters (Agent) ────────────────────────────────────────── */

$router->post('/agent/tickets/filters/save', function () {
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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

        // If the source ticket is confidential, only same-type tickets are valid
        // merge candidates — confidential content must never cross ticket types.
        $srcType = $db->prepare(
            'SELECT t.type_id, COALESCE(tt.is_confidential, 0) AS conf
             FROM tickets t LEFT JOIN ticket_types tt ON t.type_id = tt.id WHERE t.id = ?'
        );
        $srcType->execute([$exclude]);
        $srcRow = $srcType->fetch();
        if ($srcRow && (int) $srcRow['conf'] === 1) {
            $where   .= ' AND t.type_id = ?';
            $params[] = (int) $srcRow['type_id'];
        }
    }

    // Never surface tickets the searcher isn't allowed to see (e.g. confidential
    // types outside their group, or other groups' tickets). Admins: unrestricted,
    // with confidential subjects redacted below.
    $vis    = ticketStaffVisibilitySql($db, (int) Auth::id(), Auth::role(), 't');
    $where .= ' AND ' . $vis['sql'];
    $params = array_merge($params, $vis['params']);

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
    if (Auth::isAdmin()) {
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

$router->get('/agent/tickets/dup-preview', function () {
    Auth::requireStaff();
    header('Content-Type: application/json');
    $tid = (int) ($_GET['id'] ?? 0);
    $row = getDupPreviewTicket((int) Auth::id(), $tid, true);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false]);
        exit;
    }
    echo json_encode(['ok' => true, 'ticket' => $row]);
    exit;
});

$router->post('/agent/tickets/check-duplicates', function () {
    Auth::requireStaff();
    header('Content-Type: application/json');

    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'matches' => []]);
        exit;
    }

    $subject    = (string) ($_POST['subject']     ?? '');
    $body       = (string) ($_POST['description'] ?? '');
    $typeId     = (int)    ($_POST['type_id']     ?? 0);
    $locationId = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;

    if ($locationId === null) {
        $db   = Database::connect();
        $stmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
        $stmt->execute([Auth::id()]);
        $locationId = $stmt->fetchColumn() ?: null;
        $locationId = $locationId ? (int) $locationId : null;
    }

    $result = checkTicketDuplicates(Auth::id(), $typeId, $locationId, $subject, $body);
    echo json_encode([
        'ok'        => true,
        'matches'   => $result['matches'],
        'threshold' => $result['threshold'],
    ]);
    exit;
});

$router->get('/agent/tickets/create', function () {
    Auth::requireStaff();
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $agents     = $db->query(
        "SELECT id, first_name, last_name, email FROM users
         WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();
    $templates  = $db->query('SELECT * FROM ticket_templates ORDER BY name')->fetchAll();

    // Map each ticket type to its group, and each group to its staff members,
    // so the Assign-To dropdown can narrow to the type's group on the client.
    $typeGroups   = [];
    foreach ($types as $t) {
        $typeGroups[(int) $t['id']] = $t['group_id'] !== null ? (int) $t['group_id'] : null;
    }
    $groupMembers = [];
    $memberRows   = $db->query(
        "SELECT gum.group_id, gum.user_id FROM group_user_map gum
         JOIN users u ON u.id = gum.user_id
         WHERE " . staffRoleSqlIn('u.role')
    )->fetchAll();
    foreach ($memberRows as $r) {
        $groupMembers[(int) $r['group_id']][] = (int) $r['user_id'];
    }

    $formLayouts  = [];
    $customFields = [];
    $seenCustomIds = [];
    foreach ($types as $t) {
        $layout = getFormLayoutForType($db, (int) $t['id'], false);
        $slim = [];
        foreach ($layout as $row) {
            $slim[] = [
                'kind'       => $row['kind'],
                'key'        => $row['key'],
                'sort_order' => $row['sort_order'],
                'visibility' => $row['visibility'],
                'label'      => $row['label'],
            ];
            if ($row['kind'] === 'custom' && $row['field'] && !isset($seenCustomIds[$row['field']['id']])) {
                $seenCustomIds[$row['field']['id']] = true;
                $customFields[] = $row['field'];
            }
        }
        $formLayouts[(int) $t['id']] = $slim;
    }

    $fieldOptions = [];
    foreach ($customFields as $f) {
        if (in_array($f['field_type'], ['dropdown', 'dependent'], true)) {
            $s = $db->prepare(
                'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
            );
            $s->execute([$f['id']]);
            $fieldOptions[$f['id']] = $s->fetchAll();
        }
    }

    render('admin/tickets/create', [
        'types'         => $types,
        'priorities'    => $priorities,
        'locations'     => $locations,
        'groups'        => $groups,
        'agents'        => $agents,
        'typeGroups'    => $typeGroups,
        'groupMembers'  => $groupMembers,
        'templates'     => $templates,
        'isAgent'       => true,
        'formAction'    => '/admin/tickets/create',
        'customFields'  => $customFields,
        'fieldOptions'  => $fieldOptions,
        'formLayouts'   => $formLayouts,
    ]);
});

$router->post('/agent/tickets/bulk', function () {
    Auth::requireStaff();
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

    // Each bulk action is gated by its own granular permission (migration 048).
    // Admins bypass every check; staff need the matching grant on their role.
    $bulkPerms = [
        'assign'   => 'tickets.bulk_assign',
        'close'    => 'tickets.bulk_close',
        'merge'    => 'tickets.bulk_merge',
        'status'   => 'tickets.bulk_status',
        'priority' => 'tickets.bulk_priority',
        'group'    => 'tickets.bulk_group',
        'delete'   => 'tickets.bulk_delete',
    ];
    if (!isset($bulkPerms[$action]) || !Auth::can($bulkPerms[$action])) {
        flash('error', 'You do not have permission to perform that bulk action.');
        redirect('/agent/tickets');
    }

    $db           = Database::connect();

    // Filter out confidential tickets the user cannot access
    if (Auth::isAdmin()) {
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
            logAudit(
                'ticket.bulk_closed',
                null,
                'ticket',
                'count=' . count($ticketIds) . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) closed.');
            break;

        case 'assign':
            $assignTo = !empty($_POST['assign_to']) ? (int) $_POST['assign_to'] : null;
            $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id IN ({$placeholders})")
               ->execute(array_merge([$assignTo], $ticketIds));
            $label = $assignTo ? 'reassigned' : 'unassigned';
            logAudit(
                'ticket.bulk_assigned',
                $assignTo,
                'user',
                'count=' . count($ticketIds) . '; assign_to=' . ($assignTo ?? 'none') . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) ' . $label . '.');
            break;

        case 'status':
            $newStatus = (string) ($_POST['status'] ?? '');
            if ($newStatus === '' || !in_array($newStatus, ticketActiveStatusSlugs(), true)) {
                flash('error', 'Invalid status.');
                redirect('/agent/tickets');
            }
            $db->prepare("UPDATE tickets SET status = ? WHERE id IN ({$placeholders})")
               ->execute(array_merge([$newStatus], $ticketIds));
            logAudit(
                'ticket.bulk_status_changed',
                null,
                'ticket',
                'count=' . count($ticketIds) . '; status=' . $newStatus . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) updated.');
            break;

        case 'priority':
            $priorityId = !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null;
            if ($priorityId !== null) {
                $chk = $db->prepare('SELECT 1 FROM ticket_priorities WHERE id = ?');
                $chk->execute([$priorityId]);
                if (!$chk->fetchColumn()) { flash('error', 'Invalid priority.'); redirect('/agent/tickets'); }
            }
            $db->prepare("UPDATE tickets SET priority_id = ? WHERE id IN ({$placeholders})")
               ->execute(array_merge([$priorityId], $ticketIds));
            logAudit(
                'ticket.bulk_priority_changed',
                $priorityId,
                'ticket',
                'count=' . count($ticketIds) . '; priority_id=' . ($priorityId ?? 'none') . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) updated.');
            break;

        case 'group':
            $groupId = !empty($_POST['group_id']) ? (int) $_POST['group_id'] : null;
            if ($groupId !== null) {
                $chk = $db->prepare('SELECT 1 FROM `groups` WHERE id = ?');
                $chk->execute([$groupId]);
                if (!$chk->fetchColumn()) { flash('error', 'Invalid group.'); redirect('/agent/tickets'); }
            }
            $db->prepare("UPDATE tickets SET group_id = ? WHERE id IN ({$placeholders})")
               ->execute(array_merge([$groupId], $ticketIds));
            // Type maps 1:1 to a default group — clear a now-mismatched type per ticket.
            foreach ($ticketIds as $bulkTicketId) {
                clearTicketTypeIfGroupMismatch($db, (int) $bulkTicketId, $groupId, Auth::id());
            }
            logAudit(
                'ticket.bulk_group_changed',
                $groupId,
                'ticket',
                'count=' . count($ticketIds) . '; group_id=' . ($groupId ?? 'none') . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) updated.');
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
            // Confidential guard: if any ticket in the merge set is a confidential
            // type, every ticket must share that exact type so confidential content
            // can never flow into a ticket of a different (visible) type.
            $mergeIds = array_merge([$targetId], $ticketIds);
            $mph      = implode(',', array_fill(0, count($mergeIds), '?'));
            $typeRows = $db->prepare(
                "SELECT DISTINCT t.type_id, COALESCE(tt.is_confidential, 0) AS conf
                 FROM tickets t LEFT JOIN ticket_types tt ON t.type_id = tt.id
                 WHERE t.id IN ({$mph})"
            );
            $typeRows->execute($mergeIds);
            $anyConfidential = false;
            $typeSet         = [];
            foreach ($typeRows->fetchAll() as $tr) {
                if ((int) $tr['conf'] === 1) {
                    $anyConfidential = true;
                }
                $typeSet[(string) $tr['type_id']] = true;
            }
            if ($anyConfidential && count($typeSet) > 1) {
                flash('error', 'Confidential tickets can only be merged with tickets of the same type.');
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
            logAudit(
                'ticket.bulk_merged',
                $targetId,
                'ticket',
                'count=' . $merged . '; primary=' . $targetId . '; sources=' . implode(',', $ticketIds)
            );
            flash('success', "{$merged} ticket(s) merged into #{$targetId}.");
            redirect("/agent/tickets/{$targetId}");

        case 'delete':
            $files = $db->prepare("SELECT stored_name FROM ticket_attachments WHERE ticket_id IN ({$placeholders})");
            $files->execute($ticketIds);
            foreach ($files->fetchAll() as $f) {
                $path = ATTACHMENT_STORAGE_PATH . $f['stored_name'];
                if (file_exists($path)) unlink($path);
            }
            $db->prepare("DELETE FROM tickets WHERE id IN ({$placeholders})")->execute($ticketIds);
            logAudit(
                'ticket.bulk_deleted',
                null,
                'ticket',
                'count=' . count($ticketIds) . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) deleted.');
            break;

        default:
            flash('error', 'Unknown action.');
    }

    redirect('/agent/tickets');
});

$router->get('/agent/tickets/{id}', function (array $p) {
    Auth::requireStaff();
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name, tt.color AS type_color, tt.group_id AS type_group_id,
                c.first_name AS creator_first_name, c.last_name AS creator_last_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name, c.email AS creator_email,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name,
                CONCAT(s.first_name, ' ', s.last_name) AS submitter_name, s.email AS submitter_email,
                g.name AS group_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         LEFT JOIN users s             ON t.submitted_by = s.id
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
        "SELECT tl.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                u.is_external AS author_is_external
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
             WHERE gum.group_id = ? AND " . staffRoleSqlIn('u.role') . "
             ORDER BY u.first_name"
        );
        $agentStmt->execute([$ticket['type_group_id']]);
        $agents = $agentStmt->fetchAll();
    } else {
        $agents = $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name")->fetchAll();
    }

    // Group → members map so the Assigned To dropdown can re-filter live when
    // the Group field is changed in the update panel (no save needed). Key '0'
    // holds all staff, used when the group is set to None.
    $assignableByGroup = ['0' => array_map(static function ($a) {
        return ['id' => (int) $a['id'], 'name' => trim($a['first_name'] . ' ' . $a['last_name'])];
    }, $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name")->fetchAll())];
    foreach ($db->query(
        "SELECT gum.group_id AS gid, u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
         FROM group_user_map gum
         JOIN users u ON gum.user_id = u.id
         WHERE " . staffRoleSqlIn('u.role') . "
         ORDER BY u.first_name, u.last_name"
    )->fetchAll() as $gaRow) {
        $assignableByGroup[(string) (int) $gaRow['gid']][] = ['id' => (int) $gaRow['id'], 'name' => $gaRow['name']];
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

    // Custom form fields + stored values. Only show fields on this ticket
    // type's form — plus any field that already has a stored value, so a
    // value submitted before the form changed is never silently dropped.
    $fieldValues = [];
    $fvStmt = $db->prepare('SELECT field_id, value FROM ticket_field_values WHERE ticket_id = ?');
    $fvStmt->execute([$ticket['id']]);
    foreach ($fvStmt->fetchAll() as $fv) {
        $fieldValues[(int) $fv['field_id']] = $fv['value'];
    }

    $customFields = [];
    $seenFieldIds = [];
    foreach (getFormLayoutForType($db, (int) $ticket['type_id'], true) as $row) {
        if ($row['kind'] === 'custom' && $row['field']) {
            $customFields[] = $row['field'];
            $seenFieldIds[(int) $row['field']['id']] = true;
        }
    }
    // Fields with a stored value but no longer on this type's form.
    $orphanIds = array_diff(array_keys($fieldValues), array_keys($seenFieldIds));
    if ($orphanIds) {
        $ph = implode(',', array_fill(0, count($orphanIds), '?'));
        $oStmt = $db->prepare(
            "SELECT id, field_type, label, placeholder, config
             FROM ticket_form_fields
             WHERE id IN ($ph) AND deleted_at IS NULL ORDER BY id"
        );
        $oStmt->execute(array_values($orphanIds));
        $customFields = array_merge($customFields, $oStmt->fetchAll());
    }

    $fieldOptions = [];
    foreach ($customFields as $f) {
        if (in_array($f['field_type'], ['dropdown', 'dependent'], true)) {
            $s = $db->prepare(
                'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
            );
            $s->execute([$f['id']]);
            $fieldOptions[$f['id']] = $s->fetchAll();
        }
    }

    // Drop fields with nothing to show — the view would render them as a
    // bare "—" placeholder, which is just noise on the ticket.
    $customFields = array_values(array_filter(
        $customFields,
        fn($f) => customFieldHasDisplayValue(
            $f['field_type'],
            $fieldValues[$f['id']] ?? '',
            $fieldOptions[$f['id']] ?? []
        )
    ));

    // Is the current user watching this ticket? Confidential tickets never carry
    // watchers, so the watch control is hidden for them in the template.
    $isConfidential = ticketIsConfidential($db, (int) $ticket['id']);
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

    // Floor-mode entry: hide app chrome (sidebar/navbar) and overlay an ✕
    // that returns to /agent/floor/tickets/{id}. Picked up below in view.php.
    $fromFloor = ($_GET['from'] ?? '') === 'floor';

    render('agent/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'assignableByGroup' => $assignableByGroup, 'priorities' => $priorities, 'ticketTypes' => $ticketTypes, 'attachments' => $attachments, 'ccUsers' => $ccUsers, 'groups' => $groups, 'customFields' => $customFields, 'fieldValues' => $fieldValues, 'fieldOptions' => $fieldOptions, 'isWatching' => $isWatching, 'isConfidential' => $isConfidential, 'escalationHistory' => $escalationHistory, 'hasEscalationPath' => $hasEscalationPath, 'nextEscalationStep' => $nextEscalationStep, 'fromFloor' => $fromFloor, 'embedMode' => $fromFloor]);
});

/* ==================================================================
 * AGENT – Confidential Ticket Re-Authentication
 * ================================================================== */

$router->post('/agent/tickets/{id}/confidential-auth', function (array $p) {
    Auth::requireAdmin();
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
        'ticket.confidential_viewed',
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
    Auth::requireStaff();
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
    // Confidential tickets never carry watchers — one fewer accidental-leak path.
    if (ticketIsConfidential($db, $id)) {
        flash('error', 'Confidential tickets cannot be watched.');
        redirect("/agent/tickets/{$id}");
    }
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
 * AGENT – Mark / Unmark a Comment as the Solution
 *
 * Pass timeline_id in the form body to mark; pass it empty (or omit)
 * to clear. Internal notes are intentionally rejected — the portal
 * timeline filters out is_internal=1 rows, so marking one would create
 * a "Go to solution" link that anchors to nothing on the requester's
 * view, and would leak the existence of the internal note via the URL
 * fragment.
 * ================================================================== */

$router->post('/agent/tickets/{id}/solution', function (array $p) {
    Auth::requireStaff();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/tickets/{$id}");
    }

    $db = Database::connect();
    $tStmt = $db->prepare('SELECT id, group_id FROM tickets WHERE id = ?');
    $tStmt->execute([$id]);
    $solTicket = $tStmt->fetch();
    if (!$solTicket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/tickets');
    }
    _agentRequireTicketAccess($db, $solTicket);

    $rawTl = trim((string) ($_POST['timeline_id'] ?? ''));

    if ($rawTl === '' || $rawTl === '0') {
        $db->prepare('UPDATE tickets SET solution_timeline_id = NULL WHERE id = ?')
           ->execute([$id]);
        flash('success', 'Solution unmarked.');
        redirect("/agent/tickets/{$id}");
    }

    $tlId = (int) $rawTl;
    $check = $db->prepare(
        'SELECT id, action, is_internal FROM ticket_timeline WHERE id = ? AND ticket_id = ?'
    );
    $check->execute([$tlId, $id]);
    $row = $check->fetch();
    if (!$row) {
        flash('error', 'That comment is not on this ticket.');
        redirect("/agent/tickets/{$id}");
    }
    if (!in_array($row['action'], ['comment'], true)) {
        flash('error', 'Only customer-visible replies can be marked as the solution.');
        redirect("/agent/tickets/{$id}");
    }
    if ((int) $row['is_internal'] === 1) {
        flash('error', 'Internal notes cannot be marked as the solution — the requester would not be able to see it.');
        redirect("/agent/tickets/{$id}");
    }

    $db->prepare('UPDATE tickets SET solution_timeline_id = ? WHERE id = ?')
       ->execute([$tlId, $id]);
    flash('success', 'Marked as the solution.');
    redirect("/agent/tickets/{$id}#timeline-entry-{$tlId}");
});

/* ==================================================================
 * AGENT – Merge Ticket into Another
 * ================================================================== */

$router->post('/agent/tickets/{id}/merge', function (array $p) {
    Auth::requireStaff();
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

    // Confidential tickets may only merge with a ticket of the SAME type, so
    // confidential content can never flow into a differently-typed ticket.
    if (ticketIsConfidential($db, $sourceId) || ticketIsConfidential($db, $targetId)) {
        if ((int) ($sourceTicket['type_id'] ?? 0) !== (int) ($targetTicket['type_id'] ?? 0)) {
            flash('error', 'Confidential tickets can only be merged with tickets of the same type.');
            redirect("/agent/tickets/{$sourceId}");
        }
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
    Auth::requireStaff();
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

    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name")->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

    render('agent/tickets/split', compact('ticket', 'comments', 'agents', 'priorities', 'types', 'groups'));
});

$router->post('/agent/tickets/{id}/split', function (array $p) {
    Auth::requireStaff();
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
        $groupId = resolveTicketGroup($db, $groupId, $typeId);
        $db->prepare(
            'INSERT INTO tickets (subject, description, created_by, type_id, location_id, status, priority_id, assigned_to, group_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$subject, $desc, Auth::id(), $typeId, $sourceTicket['location_id'], 'open', $priId, $assignTo, $groupId]);
        $newId = (int) $db->lastInsertId();

        // AI classification (if enabled & non-confidential) + auto-assign.
        // Both no-op cleanly when their preconditions aren't met.
        runPostTicketCreateHooks($db, $newId);

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
    Auth::requireStaff();
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

    $fields   = $db->query('SELECT * FROM ticket_form_fields WHERE deleted_at IS NULL ORDER BY id')->fetchAll();
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
    Auth::requireStaff();
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
    if ($statusAfter !== '' && in_array($statusAfter, ticketActiveStatusSlugs(), true)) {
        $csStmt = $db->prepare('SELECT status FROM tickets WHERE id = ?');
        $csStmt->execute([$id]);
        $currentTicket = $csStmt->fetch();
        if ($currentTicket && $currentTicket['status'] !== $statusAfter) {
            $oldStatus = $currentTicket['status'];
            $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$statusAfter, $id]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
            )->execute([$id, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$statusAfter}"]);
            notifyAgentStatusChanged($db, $id, $oldStatus, $statusAfter, Auth::id());
            $csatTrigger = getSetting('csat_trigger_status', ticketDefaultResolvedStatusSlug());
            if ($statusAfter === $csatTrigger) {
                sendCsatSurvey($db, $id);
            }
            $pausingStatuses = ticketSlaPausingSlugs();
            if (in_array($statusAfter, $pausingStatuses, true)) {
                Sla::pause($db, $id);
            } elseif (in_array($oldStatus, $pausingStatuses, true)) {
                Sla::resume($db, $id);
            }
            $base .= ' Status set to ' . ticketStatusLabel($statusAfter) . '.';
        }
    }

    flash('success', $base);
    redirect("/agent/tickets/{$id}");
});

/* ==================================================================
 * AGENT – Forward a ticket to external third parties / contacts
 * ================================================================== */

$router->post('/agent/tickets/{id}/forward', function (array $p) {
    Auth::requireStaff();
    Auth::requirePermission('tickets.forward.internal', 'tickets.forward.external');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/tickets/{$id}");
    }

    $db = Database::connect();

    // Verify ticket exists, access, and confidential status (driven by type).
    $stmt = $db->prepare(
        'SELECT t.id, t.group_id, t.assigned_to, t.created_by, tt.is_confidential
         FROM tickets t
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         WHERE t.id = ?'
    );
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/tickets');
    }
    _agentRequireTicketAccess($db, $ticket);

    // Confidential tickets can never be forwarded — they may only be shared by
    // a human deliberately copying the relevant details into a separate email.
    if ((int) ($ticket['is_confidential'] ?? 0) === 1) {
        flash('error', 'Forwarding is disabled for confidential tickets. To share details with someone outside the helpdesk, copy the specific information you need and paste it into a new email manually.');
        redirect("/agent/tickets/{$id}");
    }

    // Parse recipients (comma / semicolon / newline separated).
    $rawTo     = $_POST['forward_to'] ?? '';
    $emails    = array_values(array_filter(array_map('trim', preg_split('/[,;\n\r]+/', $rawTo))));
    if (empty($emails)) {
        flash('error', 'Enter at least one email address to forward to.');
        redirect("/agent/tickets/{$id}");
    }

    // Gate by recipient class: internal contacts vs external addresses.
    $permErr = forwardPermissionError($db, $emails);
    if ($permErr !== null) {
        flash('error', $permErr);
        redirect("/agent/tickets/{$id}");
    }

    $note        = trim($_POST['message'] ?? '');
    $attachments = handleAttachmentUploads('attachments');

    $res = forwardTicket($db, $id, $emails, $note, $attachments, Auth::id(), Auth::fullName());

    if (empty($res['sent'])) {
        flash('error', 'Nothing was forwarded — no valid email addresses were provided.');
        redirect("/agent/tickets/{$id}");
    }

    // Audit entry (staff-only).
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 1)'
    )->execute([$id, Auth::id(), 'forwarded', 'Forwarded to ' . implode(', ', $res['sent'])]);

    $msg = 'Ticket forwarded to ' . count($res['sent']) . ' recipient(s).';
    if (!empty($res['invalid'])) {
        $msg .= ' Skipped invalid address(es): ' . implode(', ', $res['invalid']) . '.';
    }
    flash('success', $msg);
    redirect("/agent/tickets/{$id}");
});

/* ==================================================================
 * AGENT – Update Ticket (status, priority, assignment)
 * ================================================================== */

$router->post('/agent/tickets/{id}/update', function (array $p) {
    Auth::requireStaff();
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
    // Optimistic-lock guard: the form carries the status the agent saw when the
    // page loaded (`expected_status`). If the ticket moved on since then, skip
    // the status change so we don't clobber another agent's update — the other
    // field changes in this submit still apply. Optional: a submit without the
    // hidden field falls back to the old last-write-wins behaviour.
    $expectedStatus  = $_POST['expected_status'] ?? '';
    $newStatus       = $_POST['status'] ?? '';
    $statusConflict  = $expectedStatus !== '' && $expectedStatus !== $ticket['status']
                       && $newStatus !== '' && $newStatus !== $ticket['status'];
    if ($statusConflict) {
        flash('error', 'Status was not changed: it had already been updated to "'
            . ticketStatusLabel($ticket['status']) . '" by someone else. Your other changes were saved.');
    }
    if (!$statusConflict && $newStatus !== '' && in_array($newStatus, ticketActiveStatusSlugs(), true) && $newStatus !== $ticket['status']) {
        $oldStatus = $ticket['status'];
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$newStatus}"]);
        notifyAgentStatusChanged($db, $id, $oldStatus, $newStatus, Auth::id());
        $changes[] = 'status';

        if (in_array($newStatus, ticketClosedBucketSlugs(), true)) {
            notifyRequesterStatusChanged($db, $id, $newStatus);
        }

        // CSAT survey trigger
        $csatTrigger = getSetting('csat_trigger_status', ticketDefaultResolvedStatusSlug());
        if ($newStatus === $csatTrigger) {
            sendCsatSurvey($db, $id);
        }

        $pausingStatuses = ticketSlaPausingSlugs();
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

    // Type change — handled before the group change below so an explicit
    // type+group pairing in the same save is preserved, while a group change
    // on its own can still clear a now-mismatched type.
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

    // Group change
    $newGroupRaw = $_POST['group_id'] ?? '';
    $newGroup = $newGroupRaw === '' ? null : (int) $newGroupRaw;
    $oldGroup = $ticket['group_id'] ? (int) $ticket['group_id'] : null;

    // Type maps 1:1 to a default group — a type change with no explicit group
    // change in the same save moves the ticket into the new type's group.
    if ($newType !== $oldType && $newGroup === $oldGroup
        && syncTicketGroupToType($db, $id, $newType, Auth::id())) {
        $changes[] = 'group';
    }
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
        // Type maps 1:1 to a default group — clear a now-mismatched type.
        if (clearTicketTypeIfGroupMismatch($db, $id, $newGroup, Auth::id()) && !in_array('type', $changes, true)) {
            $changes[] = 'type';
        }
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
    render('agent/canned-responses/form', ['editing' => null]);
});

$router->post('/agent/canned-responses/create', function () {
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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
    Auth::requireStaff();
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

/* ==================================================================
 * AGENT – Status Banners (public status page / incident notices)
 *
 * Any agent can post, edit, or clear an incident banner — they're the
 * folks who hear "Wi-Fi is down at Eastside" first and the whole point
 * of the feature is that the next twelve duplicate tickets never get
 * filed. Display logic (visibility scoping, dismiss-for-me, etc.)
 * lives in templates/partials/status-banner.php and the helpers
 * `getActiveBanners()` / `sanitizeBannerHtml()` in src/helpers.php.
 * ================================================================== */

function _agentValidateBannerInput(): array
{
    $title    = trim($_POST['title'] ?? '');
    $body     = trim($_POST['body_html'] ?? '');
    $severity = $_POST['severity'] ?? 'warning';
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'warning';
    }
    // Multi-branch targeting: empty array = all branches (global). Ids are
    // validated against the locations table so the FK insert can't blow up.
    $locationIds = array_values(array_unique(array_map('intval', array_filter(
        (array) ($_POST['location_ids'] ?? []),
        static fn($v) => ctype_digit((string) $v) && (int) $v > 0
    ))));
    if ($locationIds) {
        $placeholders = implode(',', array_fill(0, count($locationIds), '?'));
        $stmt = Database::connect()->prepare("SELECT id FROM locations WHERE id IN ($placeholders)");
        $stmt->execute($locationIds);
        $locationIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $startsRaw  = trim($_POST['starts_at']  ?? '');
    $expiresRaw = trim($_POST['expires_at'] ?? '');

    $toMysql = function (string $raw): ?string {
        if ($raw === '') return null;
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    };
    $startsAt  = $toMysql($startsRaw);
    $expiresAt = $toMysql($expiresRaw);

    return [
        'title'        => $title !== '' ? $title : null,
        'body'         => $body,
        'severity'     => $severity,
        'location_ids' => $locationIds,
        'starts_at'    => $startsAt,
        'expires_at'   => $expiresAt,
        'errors'       => $body === '' ? ['Banner body is required.'] : [],
    ];
}

/**
 * Branch choices for the banner form. The built-in "All branches" choice
 * already covers a system-wide banner, so a meta-branch row named
 * "System Wide" / "All Branches" (used to tag tickets that affect every
 * branch) is hidden here to avoid offering the same thing twice. Branches
 * already targeted by the banner being edited are always kept, so saving
 * never silently drops one.
 */
function _agentBannerLocations(PDO $db, array $keepIds = []): array
{
    $locations = $db->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();
    return array_values(array_filter($locations, static function ($l) use ($keepIds) {
        return in_array((int) $l['id'], $keepIds, true)
            || !isMetaLocationName((string) $l['name']);
    }));
}

function _agentSaveBannerLocations(PDO $db, int $bannerId, array $locationIds): void
{
    $db->prepare('DELETE FROM status_banner_locations WHERE banner_id = ?')->execute([$bannerId]);
    if (!$locationIds) {
        return; // no rows = global (all branches)
    }
    $stmt = $db->prepare('INSERT IGNORE INTO status_banner_locations (banner_id, location_id) VALUES (?, ?)');
    foreach ($locationIds as $locId) {
        $stmt->execute([$bannerId, $locId]);
    }
}

$router->get('/agent/banners', function () {
    Auth::requireStaff();
    $db = Database::connect();
    $banners = $db->query(
        'SELECT b.*,
                (SELECT GROUP_CONCAT(l.name ORDER BY l.name SEPARATOR ", ")
                   FROM status_banner_locations sbl
                   JOIN locations l ON l.id = sbl.location_id
                  WHERE sbl.banner_id = b.id) AS location_names,
                CONCAT(u.first_name, " ", u.last_name) AS posted_by_name
         FROM status_banners b
         LEFT JOIN users     u ON b.created_by  = u.id
         ORDER BY b.is_active DESC,
                  FIELD(b.severity, "critical", "warning", "info"),
                  b.updated_at DESC'
    )->fetchAll();
    render('agent/banners/index', ['banners' => $banners]);
});

$router->get('/agent/banners/create', function () {
    Auth::requireStaff();
    $locations = _agentBannerLocations(Database::connect());
    render('agent/banners/form', [
        'editing'             => null,
        'locations'           => $locations,
        'selectedLocationIds' => [],
    ]);
});

$router->post('/agent/banners/create', function () {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/banners/create');
    }
    $v = _agentValidateBannerInput();
    if (!empty($v['errors'])) {
        flashInput($_POST);
        flash('error', $v['errors'][0]);
        redirect('/agent/banners/create');
    }
    $db = Database::connect();
    $db->prepare(
        'INSERT INTO status_banners
            (title, body_html, severity, starts_at, expires_at, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $v['title'], $v['body'], $v['severity'],
        $v['starts_at'], $v['expires_at'], Auth::id(), Auth::id(),
    ]);
    _agentSaveBannerLocations($db, (int) $db->lastInsertId(), $v['location_ids']);
    flash('success', 'Status banner posted.');
    redirect('/agent/banners');
});

$router->get('/agent/banners/{id}/edit', function (array $p) {
    Auth::requireStaff();
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM status_banners WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Banner not found.');
        redirect('/agent/banners');
    }
    $sel = $db->prepare('SELECT location_id FROM status_banner_locations WHERE banner_id = ?');
    $sel->execute([(int) $editing['id']]);
    $selectedLocationIds = array_map('intval', $sel->fetchAll(PDO::FETCH_COLUMN));
    render('agent/banners/form', [
        'editing'             => $editing,
        'locations'           => _agentBannerLocations($db, $selectedLocationIds),
        'selectedLocationIds' => $selectedLocationIds,
    ]);
});

$router->post('/agent/banners/{id}/edit', function (array $p) {
    Auth::requireStaff();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/banners/{$id}/edit");
    }
    $v = _agentValidateBannerInput();
    if (!empty($v['errors'])) {
        flashInput($_POST);
        flash('error', $v['errors'][0]);
        redirect("/agent/banners/{$id}/edit");
    }
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $db = Database::connect();
    $db->prepare(
        'UPDATE status_banners
            SET title = ?, body_html = ?, severity = ?,
                starts_at = ?, expires_at = ?, is_active = ?, updated_by = ?
          WHERE id = ?'
    )->execute([
        $v['title'], $v['body'], $v['severity'],
        $v['starts_at'], $v['expires_at'], $isActive, Auth::id(), $id,
    ]);
    _agentSaveBannerLocations($db, $id, $v['location_ids']);
    flash('success', 'Banner updated.');
    redirect('/agent/banners');
});

$router->post('/agent/banners/{id}/clear', function (array $p) {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/banners');
    }
    Database::connect()
        ->prepare('UPDATE status_banners SET is_active = 0, updated_by = ? WHERE id = ?')
        ->execute([Auth::id(), (int) $p['id']]);
    flash('success', 'Banner cleared.');
    $back = $_POST['_back'] ?? '/agent/banners';
    if (!is_string($back) || $back === '' || $back[0] !== '/' || (isset($back[1]) && ($back[1] === '/' || $back[1] === '\\'))) {
        $back = '/agent/banners';
    }
    redirect($back);
});

$router->post('/agent/banners/{id}/reactivate', function (array $p) {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/banners');
    }
    Database::connect()
        ->prepare('UPDATE status_banners SET is_active = 1, updated_by = ? WHERE id = ?')
        ->execute([Auth::id(), (int) $p['id']]);
    flash('success', 'Banner re-posted.');
    redirect('/agent/banners');
});

$router->post('/agent/banners/{id}/delete', function (array $p) {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/agent/banners');
    }
    Database::connect()
        ->prepare('DELETE FROM status_banners WHERE id = ?')
        ->execute([(int) $p['id']]);
    flash('success', 'Banner deleted.');
    redirect('/agent/banners');
});
