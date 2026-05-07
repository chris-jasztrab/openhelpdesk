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
 *   GET  /agent/floor                       Tablet queue for staff
 *   POST /agent/floor/quick-create          Bottom-sheet quick ticket (JSON)
 *   GET  /agent/floor/tickets/{id}          Tablet ticket detail (3-tap actions)
 *   POST /agent/floor/tickets/{id}/action   Status/claim/note from floor detail
 *   GET  /portal/floor                      Patron-side "my tickets" cards
 *
 * The agent detail view links back to the dense /agent/tickets/{id}
 * page with ?from=floor — that flag asks the layout to drop the
 * sidebar/navbar (embedMode) and the template to overlay an ✕ that
 * returns to /agent/floor/tickets/{id}.
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

$router->get('/agent/floor/tickets/{id}', function (array $p) {
    Auth::requireRole('agent', 'admin', 'power_user');
    $db = Database::connect();
    $id = (int) $p['id'];

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name, tt.color AS type_color,
                l.name  AS location_name,
                g.name  AS group_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                c.email AS creator_email,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types     tt ON t.type_id     = tt.id
         LEFT JOIN locations        l  ON t.location_id = l.id
         LEFT JOIN `groups`         g  ON t.group_id    = g.id
         LEFT JOIN users c             ON t.created_by  = c.id
         LEFT JOIN users a             ON t.assigned_to = a.id
         WHERE t.id = ?"
    );
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/floor');
    }

    _agentRequireTicketAccess($db, $ticket);

    // Recent timeline (last 8). Internal notes included — floor staff often
    // need the agent-side context (who's already looked at this).
    $tl = $db->prepare(
        "SELECT tl.id, tl.action, tl.details, tl.is_internal, tl.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_timeline tl
         LEFT JOIN users u ON tl.user_id = u.id
         WHERE tl.ticket_id = ?
         ORDER BY tl.created_at DESC
         LIMIT 8"
    );
    $tl->execute([$id]);
    $timeline = $tl->fetchAll();

    render('agent/floor-ticket', [
        'pageTitle'    => '#' . $id . ' · ' . $ticket['subject'],
        'layout'       => 'app',
        'sidebarItems' => Auth::role() === 'power_user' ? powerUserSidebar('floor') : agentSidebar('floor'),
        'ticket'       => $ticket,
        'timeline'     => $timeline,
        'isMine'       => (int) ($ticket['assigned_to'] ?? 0) === (int) Auth::id(),
    ]);
});

$router->post('/agent/floor/tickets/{id}/action', function (array $p) {
    Auth::requireRole('agent', 'admin', 'power_user');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/agent/floor/tickets/{$id}");
    }

    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/floor');
    }
    _agentRequireTicketAccess($db, $ticket);

    $action = $_POST['action'] ?? '';
    $userId = (int) Auth::id();

    if ($action === 'claim' || $action === 'unclaim') {
        $newAssigned = $action === 'claim' ? $userId : null;
        $oldAssigned = $ticket['assigned_to'] ? (int) $ticket['assigned_to'] : null;
        if ($newAssigned !== $oldAssigned) {
            $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$newAssigned, $id]);
            $agentName = $newAssigned ? Auth::fullName() : 'Unassigned';
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
            )->execute([$id, $userId, 'assigned', "Assigned to {$agentName} (floor)"]);
            if ($newAssigned) { notifyAssignedAgent($db, $id, $newAssigned); }
            runAutomations($db, $id, 'ticket_updated');
            flash('success', $newAssigned ? 'Claimed by you.' : 'Released.');
        }
    } elseif ($action === 'status') {
        $newStatus = $_POST['status'] ?? '';
        $valid = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
        if (in_array($newStatus, $valid, true) && $newStatus !== $ticket['status']) {
            $oldStatus = $ticket['status'];
            $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
            )->execute([$id, $userId, 'status_changed', "Status changed from {$oldStatus} to {$newStatus} (floor)"]);

            $pausing = ['pending', 'waiting_on_customer', 'waiting_on_third_party'];
            if (in_array($newStatus, $pausing, true)) {
                Sla::pause($db, $id);
            } elseif (in_array($oldStatus, $pausing, true)) {
                Sla::resume($db, $id);
            }
            if (in_array($newStatus, ['resolved', 'closed'], true)) {
                notifyRequesterStatusChanged($db, $id, $newStatus);
            }
            $csatTrigger = getSetting('csat_trigger_status', 'resolved');
            if ($newStatus === $csatTrigger) {
                sendCsatSurvey($db, $id);
            }
            runAutomations($db, $id, 'ticket_updated');
            flash('success', 'Status updated.');
        }
    } elseif ($action === 'comment') {
        $message    = trim($_POST['message'] ?? '');
        $isInternal = !empty($_POST['is_internal']) ? 1 : 0;
        if ($message === '') {
            flash('error', 'Note cannot be empty.');
            redirect("/agent/floor/tickets/{$id}");
        }
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, $userId, 'comment', $message, $isInternal]);
        $timelineId = (int) $db->lastInsertId();

        processAtMentions($db, $message, $id, $timelineId, $userId);

        $attachments = !empty($_FILES['attachments']['name'][0] ?? null) ? handleAttachmentUploads('attachments') : [];
        if ($attachments) saveAttachments($db, $id, $timelineId, $userId, $attachments);

        if (!$isInternal) {
            notifyTicketCreator($db, $id, $message, Auth::fullName());
            notifyCcUsers($db, $id, $message, Auth::fullName());
            notifyWatchers($db, $id, $message, Auth::fullName());

            // First-response SLA mark — same rule as the regular comment endpoint.
            $r = $db->prepare('SELECT created_by, first_responded_at FROM tickets WHERE id = ?');
            $r->execute([$id]);
            $row = $r->fetch();
            if ($row && $row['first_responded_at'] === null && (int) $row['created_by'] !== $userId) {
                $db->prepare('UPDATE tickets SET first_responded_at = NOW() WHERE id = ?')->execute([$id]);
                $db->prepare(
                    'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                )->execute([$id, 'sla_set', 'First response recorded']);
            }
        } else {
            notifyAgentNoteAdded($db, $id, $message);
        }

        // Auto-claim on public reply (matches /agent/tickets/{id}/comment behaviour).
        if (!$isInternal && $ticket['assigned_to'] === null) {
            $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$userId, $id]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
            )->execute([$id, $userId, 'assigned', 'Ticket auto-assigned to ' . Auth::fullName() . ' upon reply']);
        }

        flash('success', $isInternal ? 'Internal note added.' : 'Reply posted.');
    } else {
        flash('error', 'Unknown action.');
    }

    redirect("/agent/floor/tickets/{$id}");
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
