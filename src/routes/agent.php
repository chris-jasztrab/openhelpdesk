<?php

declare(strict_types=1);

/* ==================================================================
 * AGENT – Ticket Viewing
 * ================================================================== */

$router->get('/agent/tickets', function () {
    Auth::requireRole('agent', 'admin');
    $tickets = Database::connect()->query(
        "SELECT t.*,
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
         LEFT JOIN users a             ON t.assigned_to  = a.id
         ORDER BY t.created_at DESC"
    )->fetchAll();
    render('agent/tickets/index', ['tickets' => $tickets]);
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

    render('agent/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'priorities' => $priorities, 'attachments' => $attachments]);
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
