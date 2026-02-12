<?php

declare(strict_types=1);

/* ==================================================================
 * PORTAL – Ticket List (user's own tickets)
 * ================================================================== */

$router->get('/portal/tickets', function () {
    Auth::requireAuth();
    $tickets = Database::connect()->query(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         WHERE t.created_by = " . Auth::id() . "
         ORDER BY t.created_at DESC"
    )->fetchAll();
    render('portal/tickets/index', ['tickets' => $tickets]);
});

/* ==================================================================
 * PORTAL – Create Ticket
 * ================================================================== */

$router->get('/portal/tickets/create', function () {
    Auth::requireAuth();
    $db = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin') ORDER BY first_name")->fetchAll();
    $tags       = $db->query('SELECT * FROM ticket_tags ORDER BY name')->fetchAll();
    render('portal/tickets/create', [
        'types'      => $types,
        'locations'  => $locations,
        'priorities' => $priorities,
        'agents'     => $agents,
        'tags'       => $tags,
    ]);
});

$router->post('/portal/tickets/create', function () {
    Auth::requireAuth();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/portal/tickets/create');
    }

    $subject     = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $typeId      = !empty($_POST['type_id']) ? (int) $_POST['type_id'] : null;
    $locationId  = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
    $priorityId  = !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null;
    $assignedTo  = !empty($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null;
    $browserInfo = trim($_POST['browser_info'] ?? '');
    $osInfo      = trim($_POST['os_info'] ?? '');
    $tagIds      = $_POST['tags'] ?? [];

    if ($subject === '' || $description === '') {
        flashInput($_POST);
        flash('error', 'Subject and description are required.');
        redirect('/portal/tickets/create');
    }

    $db = Database::connect();

    $stmt = $db->prepare(
        'INSERT INTO tickets (subject, description, browser_info, os_info, created_by, type_id, location_id, status, priority_id, assigned_to)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $subject, $description,
        $browserInfo ?: null, $osInfo ?: null,
        Auth::id(), $typeId, $locationId, 'open', $priorityId, $assignedTo,
    ]);
    $ticketId = (int) $db->lastInsertId();

    // Attach selected tags
    if (!empty($tagIds)) {
        $mapStmt = $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)');
        foreach ($tagIds as $tagId) {
            $mapStmt->execute([$ticketId, (int) $tagId]);
        }
    }

    // Timeline: ticket created
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details) VALUES (?, ?, ?, ?)'
    )->execute([$ticketId, Auth::id(), 'created', 'Ticket created.']);

    flash('success', 'Ticket #' . $ticketId . ' created successfully.');
    redirect('/portal/tickets/' . $ticketId);
});

/* ==================================================================
 * PORTAL – View Ticket (must be own ticket)
 * ================================================================== */

$router->get('/portal/tickets/{id}', function (array $p) {
    Auth::requireAuth();
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         WHERE t.id = ? AND t.created_by = ?"
    );
    $stmt->execute([(int) $p['id'], Auth::id()]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/portal/tickets');
    }

    // Tags
    $tags = $db->prepare(
        'SELECT tt.name FROM ticket_tags tt
         INNER JOIN ticket_tag_map ttm ON tt.id = ttm.tag_id
         WHERE ttm.ticket_id = ?'
    );
    $tags->execute([$ticket['id']]);
    $ticket['tags'] = $tags->fetchAll(PDO::FETCH_COLUMN);

    // Timeline (exclude internal notes for portal users)
    $tl = $db->prepare(
        "SELECT tl.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_timeline tl
         LEFT JOIN users u ON tl.user_id = u.id
         WHERE tl.ticket_id = ? AND tl.is_internal = 0
         ORDER BY tl.created_at ASC"
    );
    $tl->execute([$ticket['id']]);
    $timeline = $tl->fetchAll();

    render('portal/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline]);
});

/* ==================================================================
 * PORTAL – Add Comment to Ticket
 * ================================================================== */

$router->post('/portal/tickets/{id}/comment', function (array $p) {
    Auth::requireAuth();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/portal/tickets/{$id}");
    }

    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        flash('error', 'Comment cannot be empty.');
        redirect("/portal/tickets/{$id}");
    }

    $db = Database::connect();

    // Verify ownership
    $stmt = $db->prepare('SELECT id FROM tickets WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, Auth::id()]);
    if (!$stmt->fetch()) {
        flash('error', 'Ticket not found.');
        redirect('/portal/tickets');
    }

    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
    )->execute([$id, Auth::id(), 'comment', $message]);

    flash('success', 'Comment added.');
    redirect("/portal/tickets/{$id}");
});
