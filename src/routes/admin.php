<?php

declare(strict_types=1);

/* ==================================================================
 * ADMIN – User Management
 * ================================================================== */

$router->get('/admin/users', function () {
    Auth::requireRole('admin');
    $db    = Database::connect();
    $users = $db->query(
        'SELECT u.*, l.name AS location_name
         FROM users u LEFT JOIN locations l ON u.location_id = l.id
         ORDER BY u.created_at DESC'
    )->fetchAll();
    render('admin/users/index', ['users' => $users]);
});

$router->get('/admin/users/create', function () {
    Auth::requireRole('admin');
    $locations = Database::connect()->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    render('admin/users/form', ['locations' => $locations, 'editing' => null]);
});

$router->post('/admin/users/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/users/create');
    }

    $fn       = trim($_POST['first_name'] ?? '');
    $ln       = trim($_POST['last_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user';
    $phone    = trim($_POST['work_phone'] ?? '');
    $locId    = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;

    if ($fn === '' || $ln === '' || $email === '' || $password === '') {
        flashInput($_POST);
        flash('error', 'First name, last name, email and password are required.');
        redirect('/admin/users/create');
    }

    // Avatar upload
    $avatar = handleAvatarUpload();

    $db = Database::connect();
    try {
        $stmt = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role, avatar, work_phone, location_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fn, $ln, $email, password_hash($password, PASSWORD_DEFAULT), $role, $avatar, $phone, $locId]);
        flash('success', 'User created successfully.');
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate entry')
            ? 'A user with this email already exists.'
            : 'Database error: ' . $e->getMessage());
        flashInput($_POST);
        redirect('/admin/users/create');
    }
    redirect('/admin/users');
});

$router->get('/admin/users/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $db   = Database::connect();
    $user = $db->prepare('SELECT * FROM users WHERE id = ?');
    $user->execute([(int) $p['id']]);
    $editing = $user->fetch();
    if (!$editing) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }
    $locations = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    render('admin/users/form', ['locations' => $locations, 'editing' => $editing]);
});

$router->post('/admin/users/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/users/{$id}/edit");
    }

    $fn    = trim($_POST['first_name'] ?? '');
    $ln    = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role  = $_POST['role'] ?? 'user';
    $phone = trim($_POST['work_phone'] ?? '');
    $locId = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;

    if ($fn === '' || $ln === '' || $email === '') {
        flashInput($_POST);
        flash('error', 'First name, last name, and email are required.');
        redirect("/admin/users/{$id}/edit");
    }

    $db = Database::connect();

    // Handle avatar
    $avatar = handleAvatarUpload();
    if ($avatar === null && empty($_POST['remove_avatar'])) {
        // Keep existing avatar
        $existing = $db->prepare('SELECT avatar FROM users WHERE id = ?');
        $existing->execute([$id]);
        $avatar = $existing->fetchColumn() ?: null;
    }

    $password = $_POST['password'] ?? '';

    try {
        if ($password !== '') {
            $stmt = $db->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, password=?, role=?, avatar=?, work_phone=?, location_id=? WHERE id=?'
            );
            $stmt->execute([$fn, $ln, $email, password_hash($password, PASSWORD_DEFAULT), $role, $avatar, $phone, $locId, $id]);
        } else {
            $stmt = $db->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, role=?, avatar=?, work_phone=?, location_id=? WHERE id=?'
            );
            $stmt->execute([$fn, $ln, $email, $role, $avatar, $phone, $locId, $id]);
        }
        flash('success', 'User updated successfully.');
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate entry')
            ? 'A user with this email already exists.'
            : 'Database error: ' . $e->getMessage());
        flashInput($_POST);
        redirect("/admin/users/{$id}/edit");
    }
    redirect('/admin/users');
});

$router->post('/admin/users/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/users');
    }
    if ($id === Auth::id()) {
        flash('error', 'You cannot delete your own account.');
        redirect('/admin/users');
    }
    $db = Database::connect();
    // Remove avatar file
    $avatar = $db->prepare('SELECT avatar FROM users WHERE id = ?');
    $avatar->execute([$id]);
    $file = $avatar->fetchColumn();
    if ($file && file_exists(ROOT_DIR . '/public/uploads/avatars/' . $file)) {
        unlink(ROOT_DIR . '/public/uploads/avatars/' . $file);
    }
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    flash('success', 'User deleted.');
    redirect('/admin/users');
});

/* ==================================================================
 * ADMIN – Location Management
 * ================================================================== */

$router->get('/admin/locations', function () {
    Auth::requireRole('admin');
    $locations = Database::connect()->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    render('admin/locations/index', ['locations' => $locations]);
});

$router->get('/admin/locations/create', function () {
    Auth::requireRole('admin');
    render('admin/locations/form', ['editing' => null]);
});

$router->post('/admin/locations/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/locations/create');
    }
    $name = trim($_POST['name'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Location name is required.');
        redirect('/admin/locations/create');
    }
    Database::connect()->prepare('INSERT INTO locations (name, address, description) VALUES (?, ?, ?)')
        ->execute([$name, $addr, $desc]);
    flash('success', 'Location created.');
    redirect('/admin/locations');
});

$router->get('/admin/locations/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $stmt = Database::connect()->prepare('SELECT * FROM locations WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Location not found.');
        redirect('/admin/locations');
    }
    render('admin/locations/form', ['editing' => $editing]);
});

$router->post('/admin/locations/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/locations/{$id}/edit");
    }
    $name = trim($_POST['name'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Location name is required.');
        redirect("/admin/locations/{$id}/edit");
    }
    Database::connect()->prepare('UPDATE locations SET name=?, address=?, description=? WHERE id=?')
        ->execute([$name, $addr, $desc, $id]);
    flash('success', 'Location updated.');
    redirect('/admin/locations');
});

$router->post('/admin/locations/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/locations');
    }
    Database::connect()->prepare('DELETE FROM locations WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Location deleted.');
    redirect('/admin/locations');
});

/* ==================================================================
 * ADMIN – Priority Management
 * ================================================================== */

$router->get('/admin/priorities', function () {
    Auth::requireRole('admin');
    $priorities = Database::connect()->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    render('admin/priorities/index', ['priorities' => $priorities]);
});

$router->get('/admin/priorities/create', function () {
    Auth::requireRole('admin');
    render('admin/priorities/form', ['editing' => null]);
});

$router->post('/admin/priorities/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/priorities/create');
    }
    $name  = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#6c757d');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Priority name is required.');
        redirect('/admin/priorities/create');
    }
    Database::connect()->prepare('INSERT INTO ticket_priorities (name, color, sort_order) VALUES (?, ?, ?)')
        ->execute([$name, $color, $order]);
    flash('success', 'Priority created.');
    redirect('/admin/priorities');
});

$router->get('/admin/priorities/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $stmt = Database::connect()->prepare('SELECT * FROM ticket_priorities WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Priority not found.');
        redirect('/admin/priorities');
    }
    render('admin/priorities/form', ['editing' => $editing]);
});

$router->post('/admin/priorities/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/priorities/{$id}/edit");
    }
    $name  = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#6c757d');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Priority name is required.');
        redirect("/admin/priorities/{$id}/edit");
    }
    Database::connect()->prepare('UPDATE ticket_priorities SET name=?, color=?, sort_order=? WHERE id=?')
        ->execute([$name, $color, $order, $id]);
    flash('success', 'Priority updated.');
    redirect('/admin/priorities');
});

$router->post('/admin/priorities/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/priorities');
    }
    Database::connect()->prepare('DELETE FROM ticket_priorities WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Priority deleted.');
    redirect('/admin/priorities');
});

/* ==================================================================
 * ADMIN – Ticket Type Management
 * ================================================================== */

$router->get('/admin/types', function () {
    Auth::requireRole('admin');
    $types = Database::connect()->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    render('admin/types/index', ['types' => $types]);
});

$router->get('/admin/types/create', function () {
    Auth::requireRole('admin');
    render('admin/types/form', ['editing' => null]);
});

$router->post('/admin/types/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/types/create');
    }
    $name  = trim($_POST['name'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Type name is required.');
        redirect('/admin/types/create');
    }
    Database::connect()->prepare('INSERT INTO ticket_types (name, sort_order) VALUES (?, ?)')
        ->execute([$name, $order]);
    flash('success', 'Ticket type created.');
    redirect('/admin/types');
});

$router->get('/admin/types/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $stmt = Database::connect()->prepare('SELECT * FROM ticket_types WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Ticket type not found.');
        redirect('/admin/types');
    }
    render('admin/types/form', ['editing' => $editing]);
});

$router->post('/admin/types/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/types/{$id}/edit");
    }
    $name  = trim($_POST['name'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Type name is required.');
        redirect("/admin/types/{$id}/edit");
    }
    Database::connect()->prepare('UPDATE ticket_types SET name=?, sort_order=? WHERE id=?')
        ->execute([$name, $order, $id]);
    flash('success', 'Ticket type updated.');
    redirect('/admin/types');
});

$router->post('/admin/types/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/types');
    }
    Database::connect()->prepare('DELETE FROM ticket_types WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Ticket type deleted.');
    redirect('/admin/types');
});

/* ==================================================================
 * ADMIN – Group Management
 * ================================================================== */

$router->get('/admin/groups', function () {
    Auth::requireRole('admin');
    $groups = Database::connect()->query(
        'SELECT g.*, COUNT(gum.user_id) AS member_count
         FROM `groups` g
         LEFT JOIN group_user_map gum ON g.id = gum.group_id
         GROUP BY g.id
         ORDER BY g.sort_order, g.name'
    )->fetchAll();
    render('admin/groups/index', ['groups' => $groups]);
});

$router->get('/admin/groups/create', function () {
    Auth::requireRole('admin');
    $users = Database::connect()->query(
        "SELECT id, first_name, last_name, role FROM users WHERE role IN ('agent','admin') ORDER BY first_name, last_name"
    )->fetchAll();
    render('admin/groups/form', ['editing' => null, 'users' => $users, 'memberIds' => []]);
});

$router->post('/admin/groups/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/groups/create');
    }

    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);

    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Group name is required.');
        redirect('/admin/groups/create');
    }

    $db = Database::connect();
    $db->prepare('INSERT INTO `groups` (name, description, sort_order) VALUES (?, ?, ?)')
        ->execute([$name, $desc, $order]);
    $groupId = (int) $db->lastInsertId();

    // Assign members
    $userIds = isset($_POST['members']) && is_array($_POST['members']) ? $_POST['members'] : [];
    if (!empty($userIds)) {
        $stmt = $db->prepare('INSERT INTO group_user_map (group_id, user_id) VALUES (?, ?)');
        foreach ($userIds as $uid) {
            $stmt->execute([$groupId, (int) $uid]);
        }
    }

    flash('success', 'Group created.');
    redirect('/admin/groups');
});

$router->get('/admin/groups/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM `groups` WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Group not found.');
        redirect('/admin/groups');
    }

    $users = $db->query(
        "SELECT id, first_name, last_name, role FROM users WHERE role IN ('agent','admin') ORDER BY first_name, last_name"
    )->fetchAll();

    $memberStmt = $db->prepare('SELECT user_id FROM group_user_map WHERE group_id = ?');
    $memberStmt->execute([$editing['id']]);
    $memberIds = $memberStmt->fetchAll(PDO::FETCH_COLUMN);

    render('admin/groups/form', ['editing' => $editing, 'users' => $users, 'memberIds' => $memberIds]);
});

$router->post('/admin/groups/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/groups/{$id}/edit");
    }

    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);

    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Group name is required.');
        redirect("/admin/groups/{$id}/edit");
    }

    $db = Database::connect();
    $db->prepare('UPDATE `groups` SET name=?, description=?, sort_order=? WHERE id=?')
        ->execute([$name, $desc, $order, $id]);

    // Sync members: delete existing, insert new
    $db->prepare('DELETE FROM group_user_map WHERE group_id = ?')->execute([$id]);
    $userIds = isset($_POST['members']) && is_array($_POST['members']) ? $_POST['members'] : [];
    if (!empty($userIds)) {
        $stmt = $db->prepare('INSERT INTO group_user_map (group_id, user_id) VALUES (?, ?)');
        foreach ($userIds as $uid) {
            $stmt->execute([$id, (int) $uid]);
        }
    }

    flash('success', 'Group updated.');
    redirect('/admin/groups');
});

$router->post('/admin/groups/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/groups');
    }
    Database::connect()->prepare('DELETE FROM `groups` WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Group deleted.');
    redirect('/admin/groups');
});

/* ==================================================================
 * ADMIN – Ticket Viewing
 * ================================================================== */

$router->get('/admin/tickets', function () {
    Auth::requireRole('admin');
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
    render('admin/tickets/index', ['tickets' => $tickets]);
});

$router->get('/admin/tickets/{id}', function (array $p) {
    Auth::requireRole('admin');
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
        redirect('/admin/tickets');
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

    // Attachments (admins see all including internal)
    $attStmt = $db->prepare(
        'SELECT ta.*, tl.is_internal FROM ticket_attachments ta
         LEFT JOIN ticket_timeline tl ON ta.timeline_id = tl.id
         WHERE ta.ticket_id = ?
         ORDER BY ta.created_at ASC'
    );
    $attStmt->execute([$ticket['id']]);
    $attachments = $attStmt->fetchAll();

    render('admin/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'priorities' => $priorities, 'attachments' => $attachments]);
});

/* ==================================================================
 * ADMIN – Add Comment / Internal Note to Ticket
 * ================================================================== */

$router->post('/admin/tickets/{id}/comment', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$id}");
    }

    $message    = trim($_POST['message'] ?? '');
    $isInternal = !empty($_POST['is_internal']) ? 1 : 0;

    if ($message === '') {
        flash('error', 'Message cannot be empty.');
        redirect("/admin/tickets/{$id}");
    }

    $db = Database::connect();

    // Verify ticket exists
    $stmt = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        flash('error', 'Ticket not found.');
        redirect('/admin/tickets');
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
    redirect("/admin/tickets/{$id}");
});

/* ==================================================================
 * ADMIN – Update Ticket (status, priority, assignment)
 * ================================================================== */

$router->post('/admin/tickets/{id}/update', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$id}");
    }

    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/admin/tickets');
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

        // SLA: pause on pending, resume when leaving pending
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
    redirect("/admin/tickets/{$id}");
});

/* ==================================================================
 * ADMIN – Download Attachment
 * ================================================================== */

$router->get('/admin/attachments/{id}/download', function (array $p) {
    Auth::requireRole('admin');
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM ticket_attachments WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $att = $stmt->fetch();

    if (!$att) {
        flash('error', 'Attachment not found.');
        redirect('/admin/tickets');
    }

    $filePath = ATTACHMENT_STORAGE_PATH . $att['stored_name'];
    if (!file_exists($filePath)) {
        flash('error', 'File not found on server.');
        redirect('/admin/tickets/' . $att['ticket_id']);
    }

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $att['original_name']) . '"');
    header('Content-Length: ' . $att['file_size']);
    readfile($filePath);
    exit;
});

/* ==================================================================
 * ADMIN – KB Category Management
 * ================================================================== */

$router->get('/admin/kb/categories', function () {
    Auth::requireRole('admin');
    $categories = Database::connect()->query('SELECT * FROM kb_categories ORDER BY sort_order, name')->fetchAll();
    render('admin/kb/categories/index', ['categories' => $categories]);
});

$router->get('/admin/kb/categories/create', function () {
    Auth::requireRole('admin');
    render('admin/kb/categories/form', ['editing' => null]);
});

$router->post('/admin/kb/categories/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/categories/create');
    }
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Category name is required.');
        redirect('/admin/kb/categories/create');
    }
    $slug = slugify($name);
    $db   = Database::connect();
    // Ensure unique slug
    $existing = $db->prepare('SELECT id FROM kb_categories WHERE slug = ?');
    $existing->execute([$slug]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }
    $db->prepare('INSERT INTO kb_categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?)')
        ->execute([$name, $slug, $desc, $order]);
    flash('success', 'Category created.');
    redirect('/admin/kb/categories');
});

$router->get('/admin/kb/categories/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $stmt = Database::connect()->prepare('SELECT * FROM kb_categories WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Category not found.');
        redirect('/admin/kb/categories');
    }
    render('admin/kb/categories/form', ['editing' => $editing]);
});

$router->post('/admin/kb/categories/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/kb/categories/{$id}/edit");
    }
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Category name is required.');
        redirect("/admin/kb/categories/{$id}/edit");
    }
    $slug = slugify($name);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_categories WHERE slug = ? AND id != ?');
    $existing->execute([$slug, $id]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }
    $db->prepare('UPDATE kb_categories SET name=?, slug=?, description=?, sort_order=? WHERE id=?')
        ->execute([$name, $slug, $desc, $order, $id]);
    flash('success', 'Category updated.');
    redirect('/admin/kb/categories');
});

$router->post('/admin/kb/categories/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/categories');
    }
    Database::connect()->prepare('DELETE FROM kb_categories WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Category deleted.');
    redirect('/admin/kb/categories');
});

/* ==================================================================
 * ADMIN – KB Folder Management
 * ================================================================== */

$router->get('/admin/kb/folders', function () {
    Auth::requireRole('admin');
    $folders = Database::connect()->query(
        'SELECT f.*, c.name AS category_name
         FROM kb_folders f
         LEFT JOIN kb_categories c ON f.category_id = c.id
         ORDER BY c.sort_order, c.name, f.sort_order, f.name'
    )->fetchAll();
    render('admin/kb/folders/index', ['folders' => $folders]);
});

$router->get('/admin/kb/folders/create', function () {
    Auth::requireRole('admin');
    $categories = Database::connect()->query('SELECT * FROM kb_categories ORDER BY sort_order, name')->fetchAll();
    render('admin/kb/folders/form', ['editing' => null, 'categories' => $categories]);
});

$router->post('/admin/kb/folders/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/folders/create');
    }
    $name       = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $desc       = trim($_POST['description'] ?? '');
    $order      = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '' || $categoryId === null) {
        flashInput($_POST);
        flash('error', 'Folder name and category are required.');
        redirect('/admin/kb/folders/create');
    }
    $slug = slugify($name);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_folders WHERE slug = ?');
    $existing->execute([$slug]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }
    $db->prepare('INSERT INTO kb_folders (category_id, name, slug, description, sort_order) VALUES (?, ?, ?, ?, ?)')
        ->execute([$categoryId, $name, $slug, $desc, $order]);
    flash('success', 'Folder created.');
    redirect('/admin/kb/folders');
});

$router->get('/admin/kb/folders/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM kb_folders WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Folder not found.');
        redirect('/admin/kb/folders');
    }
    $categories = $db->query('SELECT * FROM kb_categories ORDER BY sort_order, name')->fetchAll();
    render('admin/kb/folders/form', ['editing' => $editing, 'categories' => $categories]);
});

$router->post('/admin/kb/folders/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/kb/folders/{$id}/edit");
    }
    $name       = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $desc       = trim($_POST['description'] ?? '');
    $order      = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '' || $categoryId === null) {
        flashInput($_POST);
        flash('error', 'Folder name and category are required.');
        redirect("/admin/kb/folders/{$id}/edit");
    }
    $slug = slugify($name);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_folders WHERE slug = ? AND id != ?');
    $existing->execute([$slug, $id]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }
    $db->prepare('UPDATE kb_folders SET category_id=?, name=?, slug=?, description=?, sort_order=? WHERE id=?')
        ->execute([$categoryId, $name, $slug, $desc, $order, $id]);
    flash('success', 'Folder updated.');
    redirect('/admin/kb/folders');
});

$router->post('/admin/kb/folders/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/folders');
    }
    Database::connect()->prepare('DELETE FROM kb_folders WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Folder deleted.');
    redirect('/admin/kb/folders');
});

/* ==================================================================
 * ADMIN – KB Article Management
 * ================================================================== */

$router->get('/admin/kb/articles', function () {
    Auth::requireRole('admin');
    $articles = Database::connect()->query(
        "SELECT a.*, f.name AS folder_name, c.name AS category_name,
                CONCAT(u.first_name, ' ', u.last_name) AS author_name
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         LEFT JOIN users u         ON a.created_by  = u.id
         ORDER BY a.updated_at DESC"
    )->fetchAll();
    render('admin/kb/articles/index', ['articles' => $articles]);
});

$router->get('/admin/kb/articles/create', function () {
    Auth::requireRole('admin');
    $folders = Database::connect()->query(
        'SELECT f.id, f.name, c.name AS category_name
         FROM kb_folders f
         LEFT JOIN kb_categories c ON f.category_id = c.id
         ORDER BY c.sort_order, c.name, f.sort_order, f.name'
    )->fetchAll();
    render('admin/kb/articles/form', ['editing' => null, 'folders' => $folders]);
});

$router->post('/admin/kb/articles/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/articles/create');
    }
    $title    = trim($_POST['title'] ?? '');
    $folderId = !empty($_POST['folder_id']) ? (int) $_POST['folder_id'] : null;
    $body     = $_POST['body_markdown'] ?? '';
    $status   = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $order    = (int) ($_POST['sort_order'] ?? 0);

    if ($title === '' || $folderId === null || $body === '') {
        flashInput($_POST);
        flash('error', 'Title, folder, and body are required.');
        redirect('/admin/kb/articles/create');
    }

    $slug = slugify($title);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_articles WHERE slug = ?');
    $existing->execute([$slug]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }

    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

    $db->prepare(
        'INSERT INTO kb_articles (folder_id, title, slug, body_markdown, status, published_at, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$folderId, $title, $slug, $body, $status, $publishedAt, Auth::id(), $order]);
    flash('success', 'Article created.');
    redirect('/admin/kb/articles');
});

$router->get('/admin/kb/articles/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM kb_articles WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Article not found.');
        redirect('/admin/kb/articles');
    }
    $folders = $db->query(
        'SELECT f.id, f.name, c.name AS category_name
         FROM kb_folders f
         LEFT JOIN kb_categories c ON f.category_id = c.id
         ORDER BY c.sort_order, c.name, f.sort_order, f.name'
    )->fetchAll();
    render('admin/kb/articles/form', ['editing' => $editing, 'folders' => $folders]);
});

$router->post('/admin/kb/articles/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/kb/articles/{$id}/edit");
    }
    $title    = trim($_POST['title'] ?? '');
    $folderId = !empty($_POST['folder_id']) ? (int) $_POST['folder_id'] : null;
    $body     = $_POST['body_markdown'] ?? '';
    $status   = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $order    = (int) ($_POST['sort_order'] ?? 0);

    if ($title === '' || $folderId === null || $body === '') {
        flashInput($_POST);
        flash('error', 'Title, folder, and body are required.');
        redirect("/admin/kb/articles/{$id}/edit");
    }

    $slug = slugify($title);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_articles WHERE slug = ? AND id != ?');
    $existing->execute([$slug, $id]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }

    // Determine published_at
    $oldStmt = $db->prepare('SELECT status, published_at FROM kb_articles WHERE id = ?');
    $oldStmt->execute([$id]);
    $old = $oldStmt->fetch();
    if ($status === 'published' && ($old['status'] ?? '') !== 'published') {
        $publishedAt = date('Y-m-d H:i:s');
    } elseif ($status === 'published') {
        $publishedAt = $old['published_at'];
    } else {
        $publishedAt = null;
    }

    $db->prepare(
        'UPDATE kb_articles SET folder_id=?, title=?, slug=?, body_markdown=?, status=?, published_at=?, sort_order=? WHERE id=?'
    )->execute([$folderId, $title, $slug, $body, $status, $publishedAt, $order, $id]);
    flash('success', 'Article updated.');
    redirect('/admin/kb/articles');
});

$router->post('/admin/kb/articles/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/articles');
    }
    Database::connect()->prepare('DELETE FROM kb_articles WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Article deleted.');
    redirect('/admin/kb/articles');
});

$router->get('/admin/kb/articles/{id}/preview', function (array $p) {
    Auth::requireRole('admin');
    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT a.*, f.name AS folder_name, f.slug AS folder_slug,
                c.name AS category_name, c.slug AS category_slug
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         WHERE a.id = ?'
    );
    $stmt->execute([(int) $p['id']]);
    $article = $stmt->fetch();
    if (!$article) {
        flash('error', 'Article not found.');
        redirect('/admin/kb/articles');
    }
    $article['body_html'] = renderMarkdown($article['body_markdown']);
    render('admin/kb/articles/preview', ['article' => $article]);
});

/* ==================================================================
 * ADMIN – Settings (Email / SMTP Configuration)
 * ================================================================== */

$router->get('/admin/settings', function () {
    Auth::requireRole('admin');
    $keys = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'mail_from_address', 'mail_from_name'];
    $settings = [];
    foreach ($keys as $k) {
        $settings[$k] = getSetting($k);
    }
    render('admin/settings/index', ['settings' => $settings]);
});

$router->post('/admin/settings', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $fields = [
        'smtp_host'        => trim($_POST['smtp_host'] ?? ''),
        'smtp_port'        => trim($_POST['smtp_port'] ?? '587'),
        'smtp_encryption'  => in_array($_POST['smtp_encryption'] ?? '', ['none', 'tls', 'ssl'], true) ? $_POST['smtp_encryption'] : 'tls',
        'smtp_username'    => trim($_POST['smtp_username'] ?? ''),
        'mail_from_address' => trim($_POST['mail_from_address'] ?? ''),
        'mail_from_name'   => trim($_POST['mail_from_name'] ?? ''),
    ];

    // Only update password if a new one was provided (don't blank it on save)
    $password = $_POST['smtp_password'] ?? '';
    if ($password !== '') {
        $fields['smtp_password'] = $password;
    }

    foreach ($fields as $key => $value) {
        setSetting($key, $value);
    }

    flash('success', 'Email settings saved.');
    redirect('/admin/settings');
});

$router->post('/admin/settings/test-email', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $user    = Auth::user();
    $toEmail = $user['email'] ?? '';
    if ($toEmail === '') {
        flash('error', 'Your account has no email address set.');
        redirect('/admin/settings');
    }

    $htmlBody = '<h2>It works!</h2><p>This is a test email from <strong>LocalDesk</strong>. Your SMTP configuration is correct.</p>';
    $result   = sendMail(
        $toEmail,
        $user['first_name'] . ' ' . $user['last_name'],
        'LocalDesk – Test Email',
        $htmlBody,
        "It works!\n\nThis is a test email from LocalDesk. Your SMTP configuration is correct."
    );

    if ($result !== false) {
        flash('success', 'Test email sent to ' . $toEmail . '.');
    } else {
        flash('error', 'Failed to send test email. Check your SMTP settings and server error log.');
    }

    redirect('/admin/settings');
});

/* ==================================================================
 * ADMIN – Business Hours Settings
 * ================================================================== */

$router->get('/admin/settings/business-hours', function () {
    Auth::requireRole('admin');
    $timezone = getSetting('business_hours_timezone');
    $json = getSetting('business_hours_schedule');
    $schedule = $json !== '' ? (json_decode($json, true) ?: []) : [];
    render('admin/settings/business-hours', ['timezone' => $timezone, 'schedule' => $schedule]);
});

$router->post('/admin/settings/business-hours', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/business-hours');
    }

    $timezone = trim($_POST['timezone'] ?? '');
    $days = $_POST['days'] ?? [];

    $schedule = [];
    foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
        if (!empty($days[$day]['active'])) {
            $start = $days[$day]['start'] ?? '09:00';
            $end = $days[$day]['end'] ?? '17:00';
            $schedule[$day] = [$start, $end];
        } else {
            $schedule[$day] = null;
        }
    }

    setSetting('business_hours_timezone', $timezone);
    setSetting('business_hours_schedule', json_encode($schedule));

    flash('success', 'Business hours saved.');
    redirect('/admin/settings/business-hours');
});

/* ==================================================================
 * ADMIN – SLA Policy Settings
 * ================================================================== */

$router->get('/admin/settings/sla-policies', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();

    // Load existing policies
    $policyStmt = $db->query('SELECT * FROM sla_policies');
    $policies = [];
    while ($row = $policyStmt->fetch()) {
        $policies[(int) $row['priority_id']] = $row;
    }

    render('admin/settings/sla-policies', ['priorities' => $priorities, 'policies' => $policies]);
});

$router->post('/admin/settings/sla-policies', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/sla-policies');
    }

    $db = Database::connect();
    $policiesData = $_POST['policies'] ?? [];

    $upsert = $db->prepare(
        'INSERT INTO sla_policies (priority_id, first_response_minutes, resolution_minutes)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE first_response_minutes = VALUES(first_response_minutes), resolution_minutes = VALUES(resolution_minutes)'
    );
    $delete = $db->prepare('DELETE FROM sla_policies WHERE priority_id = ?');

    foreach ($policiesData as $priorityId => $data) {
        $priorityId = (int) $priorityId;
        $firstResponse = (int) ($data['first_response_minutes'] ?? 0);
        $resolution = (int) ($data['resolution_minutes'] ?? 0);

        if ($firstResponse > 0 && $resolution > 0) {
            $upsert->execute([$priorityId, $firstResponse, $resolution]);
        } else {
            $delete->execute([$priorityId]);
        }
    }

    flash('success', 'SLA policies saved.');
    redirect('/admin/settings/sla-policies');
});

$router->post('/admin/settings/sla-recalculate', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/sla-policies');
    }

    $count = Sla::recalculateAll(Database::connect());
    flash('success', "SLA recalculated for {$count} ticket(s).");
    redirect('/admin/settings/sla-policies');
});

/* ==================================================================
 * Avatar upload helper
 * ================================================================== */

function handleAvatarUpload(): ?string
{
    if (empty($_FILES['avatar']['tmp_name']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mime = mime_content_type($_FILES['avatar']['tmp_name']);
    if (!in_array($mime, $allowed, true) || $_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        flash('error', 'Avatar must be a JPG/PNG/GIF/WEBP image under 2 MB.');
        return null;
    }
    $ext       = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $filename  = uniqid('avatar_', true) . '.' . strtolower($ext);
    $uploadDir = ROOT_DIR . '/public/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename);
    return $filename;
}
