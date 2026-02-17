<?php

declare(strict_types=1);

/* ==================================================================
 * AGENT – Ticket Viewing
 * ================================================================== */

$router->get('/agent/tickets', function () {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();

    // Read filter params
    $fStatus   = trim($_GET['status'] ?? '');
    $fPriority = trim($_GET['priority'] ?? '');
    $fType     = trim($_GET['type'] ?? '');
    $fLocation = trim($_GET['location'] ?? '');
    $fAgent    = trim($_GET['agent'] ?? '');
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
    if ($fSearch !== '') {
        $where[]  = 't.subject LIKE ?';
        $params[] = '%' . $fSearch . '%';
    }

    $sql = "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id";

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

    $filters = [
        'status'   => $fStatus,
        'priority' => $fPriority,
        'type'     => $fType,
        'location' => $fLocation,
        'agent'    => $fAgent,
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
        'tickets'      => $tickets,
        'priorities'   => $priorities,
        'types'        => $types,
        'locations'    => $locations,
        'agents'       => $agents,
        'filters'      => $filters,
        'savedFilters' => $savedFilters,
        'page'         => $page,
        'totalPages'   => $totalPages,
        'totalTickets' => $totalTickets,
        'sort'         => $sort,
        'dir'          => strtolower($dir),
    ]);
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

$router->get('/agent/tickets/{id}', function (array $p) {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name, c.email AS creator_email,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         WHERE t.id = ?"
    );
    $stmt->execute([(int) $p['id']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/agent/tickets');
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
         ORDER BY tl.created_at ASC"
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

    render('agent/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'priorities' => $priorities, 'attachments' => $attachments, 'ccUsers' => $ccUsers]);
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

    // Verify ticket exists
    $stmt = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        flash('error', 'Ticket not found.');
        redirect('/agent/tickets');
    }

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

    $changes = [];

    // Status change
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
    if ($newStatus !== '' && in_array($newStatus, $validStatuses, true) && $newStatus !== $ticket['status']) {
        $oldStatus = $ticket['status'];
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$newStatus}"]);
        $changes[] = 'status';

        if ($newStatus === 'pending') {
            Sla::pause($db, $id);
        } elseif ($oldStatus === 'pending') {
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

    if (!empty($changes)) {
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

    $stmt = $db->prepare('SELECT * FROM ticket_attachments WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $att = $stmt->fetch();

    if (!$att) {
        flash('error', 'Attachment not found.');
        redirect('/agent/tickets');
    }

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
