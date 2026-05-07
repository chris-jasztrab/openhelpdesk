<?php

declare(strict_types=1);

/* ==================================================================
 * FLOOR MODE — tablet-friendly card view of the ticket queue.
 *
 * Designed for staff walking the building (printer jam in the
 * makerspace, frozen public PC, lock-up at the self-checkout) and
 * patrons reporting things from a phone in the stacks. Same backing
 * data as the existing list views, just rendered as a touch-first
 * card grid with 44pt+ targets, large type, and a single-screen
 * quick-create flow that captures a photo straight from the camera.
 *
 *   GET  /agent/floor                  Tablet queue for staff
 *   POST /agent/floor/quick-create     Bottom-sheet quick ticket (JSON)
 *   GET  /portal/floor                 Patron-side "my tickets" cards
 * ================================================================== */

$router->get('/agent/floor', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
    $db      = Database::connect();
    $agentId = (int) Auth::id();

    // Tabs: All / Mine / Unassigned. URL: /agent/floor?view=mine etc.
    $view = $_GET['view'] ?? 'all';
    if (!in_array($view, ['all', 'mine', 'unassigned'], true)) $view = 'all';

    $where  = ["t.status IN ('open','in_progress','pending')"];
    $params = [];

    // Group restriction — same rule as /agent/tickets.
    if (in_array(Auth::role(), ['agent', 'power_user'], true)) {
        $gStmt = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gStmt->execute([$agentId]);
        $myGroups = array_map('intval', $gStmt->fetchAll(PDO::FETCH_COLUMN));
        if (!empty($myGroups)) {
            $placeholders = implode(',', array_fill(0, count($myGroups), '?'));
            $where[]      = 't.group_id IN (' . $placeholders . ')';
            $params       = array_merge($params, $myGroups);
        }
    }

    if ($view === 'mine') {
        $where[]  = 't.assigned_to = ?';
        $params[] = $agentId;
    } elseif ($view === 'unassigned') {
        $where[] = 't.assigned_to IS NULL';
    }

    $sql = "SELECT t.id, t.subject, t.status, t.created_at, t.updated_at, t.due_date,
                   tp.name AS priority_name, tp.color AS priority_color, tp.sort_order AS priority_sort,
                   tt.name AS type_name, tt.color AS type_color,
                   l.name  AS location_name,
                   g.name  AS group_name,
                   CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                   CONCAT(a.first_name, ' ', a.last_name) AS agent_name,
                   t.assigned_to
            FROM tickets t
            LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
            LEFT JOIN ticket_types     tt ON t.type_id     = tt.id
            LEFT JOIN locations        l  ON t.location_id = l.id
            LEFT JOIN `groups`         g  ON t.group_id    = g.id
            LEFT JOIN users c ON t.created_by  = c.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(tp.sort_order, 0) DESC, t.created_at DESC
            LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    // Tab counts (all / mine / unassigned within group restriction)
    $countWhere  = ["t.status IN ('open','in_progress','pending')"];
    $countParams = [];
    if (in_array(Auth::role(), ['agent', 'power_user'], true) && !empty($myGroups)) {
        $countWhere[]  = 't.group_id IN (' . implode(',', array_fill(0, count($myGroups), '?')) . ')';
        $countParams   = array_merge($countParams, $myGroups);
    }
    $baseSql   = 'SELECT COUNT(*) FROM tickets t WHERE ' . implode(' AND ', $countWhere);
    $cAll      = (int) (function () use ($db, $baseSql, $countParams) { $s = $db->prepare($baseSql); $s->execute($countParams); return $s->fetchColumn(); })();
    $sM        = $db->prepare($baseSql . ' AND t.assigned_to = ?');
    $sM->execute(array_merge($countParams, [$agentId]));
    $cMine     = (int) $sM->fetchColumn();
    $sU        = $db->prepare($baseSql . ' AND t.assigned_to IS NULL');
    $sU->execute($countParams);
    $cUnass    = (int) $sU->fetchColumn();

    // Quick-create dropdown data
    $types     = $db->query('SELECT * FROM ticket_types WHERE is_confidential = 0 ORDER BY sort_order, name')->fetchAll();
    $locations = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();

    // Note: must NOT pass a key named 'view' — render() uses its own
    // $view parameter internally for the template path, and extract()
    // would clobber it. Use 'activeView' here and in the template.
    render('agent/floor', [
        'pageTitle'    => 'Floor mode',
        'layout'       => 'app',
        'sidebarItems' => Auth::role() === 'power_user' ? powerUserSidebar('floor') : agentSidebar('floor'),
        'tickets'      => $tickets,
        'activeView'   => $view,
        'counts'       => ['all' => $cAll, 'mine' => $cMine, 'unassigned' => $cUnass],
        'types'        => $types,
        'locations'    => $locations,
    ]);
});

$router->post('/agent/floor/quick-create', function () {
    Auth::requireRole('agent', 'admin', 'power_user');
    header('Content-Type: application/json');

    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $subject     = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $typeId      = !empty($_POST['type_id'])     ? (int) $_POST['type_id']     : null;
    $locationId  = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;

    if ($subject === '' || mb_strlen($subject) < 3) {
        echo json_encode(['ok' => false, 'error' => 'Subject must be at least 3 characters.']);
        exit;
    }
    if ($description === '') {
        $description = $subject; // Floor capture often only knows the headline.
    }

    $db = Database::connect();

    // Default type if missing — picks the first non-confidential, lowest sort_order.
    if ($typeId === null) {
        $typeId = (int) $db->query('SELECT id FROM ticket_types WHERE is_confidential = 0 ORDER BY sort_order, id LIMIT 1')->fetchColumn() ?: null;
    } else {
        $check = $db->prepare('SELECT id FROM ticket_types WHERE id = ? AND is_confidential = 0');
        $check->execute([$typeId]);
        if (!$check->fetchColumn()) {
            echo json_encode(['ok' => false, 'error' => 'Selected ticket type is not available from quick-create.']);
            exit;
        }
    }

    // Default location: explicit > agent's profile location
    if ($locationId === null) {
        $locStmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
        $locStmt->execute([Auth::id()]);
        $locationId = $locStmt->fetchColumn() ?: null;
    }

    $groupId    = resolveTicketGroup($db, null, $typeId);
    $priorityId = (int) ($db->query('SELECT id FROM ticket_priorities ORDER BY sort_order LIMIT 1')->fetchColumn() ?: 0) ?: null;

    $ins = $db->prepare(
        'INSERT INTO tickets (subject, description, created_by, type_id, location_id, status, priority_id, group_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$subject, $description, Auth::id(), $typeId, $locationId, 'open', $priorityId, $groupId]);
    $ticketId = (int) $db->lastInsertId();

    // Run the same post-create hooks as the regular path so AI routing,
    // auto-assign and SLA timers all kick in.
    $autoAssignedTo = runPostTicketCreateHooks($db, $ticketId);

    $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details) VALUES (?, ?, ?, ?)')
        ->execute([$ticketId, Auth::id(), 'created', 'Ticket created from floor mode quick-capture.']);

    // Photo / file attachments captured by the camera input.
    if (!empty($_FILES['attachments']['name'][0] ?? null)) {
        $attachments = handleAttachmentUploads('attachments');
        saveAttachments($db, $ticketId, null, (int) Auth::id(), $attachments);
    }

    notifyRequesterTicketCreated($db, $ticketId);
    notifyGroupMembers($db, $ticketId);
    if ($autoAssignedTo !== null) {
        notifyAssignedAgent($db, $ticketId, $autoAssignedTo);
        notifyRequesterTicketAssigned($db, $ticketId, $autoAssignedTo);
    }
    if ($priorityId) {
        Sla::initializeForTicket($db, $ticketId, $priorityId, $typeId);
    }

    echo json_encode([
        'ok'           => true,
        'ticket_id'    => $ticketId,
        'redirect_url' => '/agent/tickets/' . $ticketId,
    ]);
    exit;
});

$router->get('/portal/floor', function () {
    Auth::requireAuth();
    $db = Database::connect();

    // Open tickets first, then recently-resolved (so people on the floor
    // can confirm something they reported earlier was acted on).
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.status, t.created_at, t.updated_at,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name, tt.color AS type_color,
                l.name  AS location_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types     tt ON t.type_id     = tt.id
         LEFT JOIN locations        l  ON t.location_id = l.id
         WHERE t.created_by = ?
         ORDER BY (t.status IN ('open','in_progress','pending')) DESC, t.updated_at DESC
         LIMIT 50"
    );
    $stmt->execute([Auth::id()]);
    $tickets = $stmt->fetchAll();

    render('portal/floor', [
        'pageTitle'    => 'My help requests',
        'layout'       => 'app',
        'sidebarItems' => portalSidebar('floor'),
        'tickets'      => $tickets,
    ]);
});
