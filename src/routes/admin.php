<?php

declare(strict_types=1);

/* ==================================================================
 * ADMIN – Onboarding
 * ================================================================== */

$router->post('/admin/onboarding/dismiss', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect('/admin');
    }
    setSetting('show_onboarding', '0');
    redirect('/admin');
});

/* ==================================================================
 * ADMIN – Documentation
 * ================================================================== */

$validDocPages = [
    'getting-started', 'tickets', 'users', 'email',
    'sla', 'automations', 'branding', 'portal', 'import', 'kb',
];

$router->get('/admin/docs', function () {
    Auth::requireRole('admin');
    render('admin/docs/index', [
        'sidebarItems' => adminSidebar('docs'),
        'layout'       => 'app',
        'pageTitle'    => 'Documentation',
        'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Docs']],
    ]);
});

$router->get('/admin/docs/{page}', function (array $p) use ($validDocPages) {
    Auth::requireRole('admin');
    $page = $p['page'] ?? '';
    if (!in_array($page, $validDocPages, true)) {
        redirect('/admin/docs');
    }
    $titles = [
        'getting-started' => 'Getting Started',
        'tickets'         => 'Tickets',
        'users'           => 'Users & Roles',
        'email'           => 'Email & Notifications',
        'sla'             => 'SLA Policies',
        'automations'     => 'Automations',
        'branding'        => 'Branding',
        'portal'          => 'Portal',
        'import'          => 'Importing Tickets',
        'kb'              => 'Knowledge Base',
    ];
    render('admin/docs/' . $page, [
        'sidebarItems' => adminSidebar('docs'),
        'layout'       => 'app',
        'pageTitle'    => 'Docs: ' . ($titles[$page] ?? $page),
        'breadcrumbs'  => [
            ['label' => 'Admin', 'url' => '/admin'],
            ['label' => 'Docs',  'url' => '/admin/docs'],
            ['label' => $titles[$page] ?? $page],
        ],
    ]);
});

/* ==================================================================
 * ADMIN – User Management
 * ================================================================== */

$router->get('/admin/users', function () {
    Auth::requireRole('admin');
    $db   = Database::connect();

    if (isset($_GET['reset'])) {
        redirect('/admin/users');
    }

    $role  = $_GET['role'] ?? '';
    $locId = trim($_GET['location'] ?? '');
    $q     = trim($_GET['q'] ?? '');

    $sql    = 'SELECT u.*, l.name AS location_name FROM users u LEFT JOIN locations l ON u.location_id = l.id';
    $where  = [];
    $params = [];
    if (in_array($role, ['admin', 'agent', 'user'], true)) {
        $where[]  = 'u.role = ?';
        $params[] = $role;
    }
    if ($locId !== '') {
        if ($locId === 'none') {
            $where[] = 'u.location_id IS NULL';
        } else {
            $where[]  = 'u.location_id = ?';
            $params[] = (int) $locId;
        }
    }
    if ($q !== '') {
        $where[]  = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // Sorting
    $sortableColumns = [
        'name'       => 'u.first_name',
        'email'      => 'u.email',
        'role'       => 'u.role',
        'location'   => 'l.name',
        'created_at' => 'u.created_at',
    ];
    $sort = $_GET['sort'] ?? 'created_at';
    $dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $orderCol = $sortableColumns[$sort] ?? 'u.created_at';

    $sql .= " ORDER BY {$orderCol} {$dir}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $locations = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();

    render('admin/users/index', [
        'users'      => $users,
        'roleFilter' => $role,
        'locFilter'  => $locId,
        'qFilter'    => $q,
        'locations'  => $locations,
        'sort'       => $sort,
        'dir'        => strtolower($dir),
    ]);
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

    $fn              = trim($_POST['first_name'] ?? '');
    $ln              = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $role            = $_POST['role'] ?? 'user';
    $phone           = trim($_POST['work_phone'] ?? '');
    $locId           = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
    $canViewLocTix   = !empty($_POST['can_view_location_tickets']) ? 1 : 0;

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
            'INSERT INTO users (first_name, last_name, email, password, role, avatar, work_phone, location_id, can_view_location_tickets)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fn, $ln, $email, password_hash($password, PASSWORD_DEFAULT), $role, $avatar, $phone, $locId, $canViewLocTix]);
        $newId = (int) $db->lastInsertId();
        logAudit('user.create', $newId, 'user', "{$fn} {$ln} ({$email}), role={$role}");
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

$router->get('/admin/users/{id}', function (array $p) {
    Auth::requireRole('admin');
    $db  = Database::connect();
    $uid = (int) $p['id'];

    $stmt = $db->prepare(
        'SELECT u.*, l.name AS location_name
         FROM users u LEFT JOIN locations l ON u.location_id = l.id
         WHERE u.id = ?'
    );
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }

    // Open (non-resolved/closed) tickets submitted by this user
    $tStmt = $db->prepare(
        "SELECT t.id, t.subject, t.status, t.created_at,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name,
                CONCAT(a.first_name, ' ', a.last_name) AS assigned_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types     tt ON t.type_id     = tt.id
         LEFT JOIN users             a  ON t.assigned_to = a.id
         WHERE t.created_by = ?
           AND t.status NOT IN ('resolved', 'closed')
         ORDER BY t.created_at DESC"
    );
    $tStmt->execute([$uid]);
    $openTickets = $tStmt->fetchAll();

    // Counts needed for the delete/transfer modal
    $cStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_by = ?');
    $cStmt->execute([$uid]);
    $createdCount = (int) $cStmt->fetchColumn();

    $aStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE assigned_to = ?');
    $aStmt->execute([$uid]);
    $assignedCount = (int) $aStmt->fetchColumn();

    $kbStmt = $db->prepare('SELECT COUNT(*) FROM kb_articles WHERE created_by = ?');
    $kbStmt->execute([$uid]);
    $kbCount = (int) $kbStmt->fetchColumn();

    render('admin/users/view', [
        'profileUser'   => $user,
        'openTickets'   => $openTickets,
        'createdCount'  => $createdCount,
        'assignedCount' => $assignedCount,
        'kbCount'       => $kbCount,
    ]);
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

    $fn            = trim($_POST['first_name'] ?? '');
    $ln            = trim($_POST['last_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $role          = $_POST['role'] ?? 'user';
    $phone         = trim($_POST['work_phone'] ?? '');
    $locId         = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
    $canViewLocTix = !empty($_POST['can_view_location_tickets']) ? 1 : 0;

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
                'UPDATE users SET first_name=?, last_name=?, email=?, password=?, role=?, avatar=?, work_phone=?, location_id=?, can_view_location_tickets=? WHERE id=?'
            );
            $stmt->execute([$fn, $ln, $email, password_hash($password, PASSWORD_DEFAULT), $role, $avatar, $phone, $locId, $canViewLocTix, $id]);
        } else {
            $stmt = $db->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, role=?, avatar=?, work_phone=?, location_id=?, can_view_location_tickets=? WHERE id=?'
            );
            $stmt->execute([$fn, $ln, $email, $role, $avatar, $phone, $locId, $canViewLocTix, $id]);
        }
        logAudit('user.update', $id, 'user', "{$fn} {$ln} ({$email}), role={$role}");
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

    $transferTo = !empty($_POST['transfer_to']) ? (int) $_POST['transfer_to'] : null;

    // Count records associated with this user
    $cStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_by = ?');
    $cStmt->execute([$id]);
    $createdCount = (int) $cStmt->fetchColumn();

    $aStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE assigned_to = ?');
    $aStmt->execute([$id]);
    $assignedCount = (int) $aStmt->fetchColumn();

    $kbStmt = $db->prepare('SELECT COUNT(*) FROM kb_articles WHERE created_by = ?');
    $kbStmt->execute([$id]);
    $kbCount = (int) $kbStmt->fetchColumn();

    $hasAssociated = $createdCount > 0 || $assignedCount > 0 || $kbCount > 0;

    // If there are associated records a transfer target is required
    if ($hasAssociated && $transferTo === null) {
        flash('error', 'This user has associated tickets or KB articles. Select a user to transfer them to before deleting.');
        redirect("/admin/users/{$id}?delete=1");
    }

    // Validate and perform transfer
    if ($transferTo !== null) {
        $targetStmt = $db->prepare('SELECT id FROM users WHERE id = ? AND id != ?');
        $targetStmt->execute([$transferTo, $id]);
        if (!$targetStmt->fetch()) {
            flash('error', 'Transfer target user not found.');
            redirect("/admin/users/{$id}?delete=1");
        }
        if ($createdCount > 0) {
            $db->prepare('UPDATE tickets SET created_by = ? WHERE created_by = ?')->execute([$transferTo, $id]);
        }
        if ($assignedCount > 0) {
            $db->prepare('UPDATE tickets SET assigned_to = ? WHERE assigned_to = ?')->execute([$transferTo, $id]);
        }
        if ($kbCount > 0) {
            $db->prepare('UPDATE kb_articles SET created_by = ? WHERE created_by = ?')->execute([$transferTo, $id]);
        }
    }

    // Remove avatar file
    $avatar = $db->prepare('SELECT avatar FROM users WHERE id = ?');
    $avatar->execute([$id]);
    $file = $avatar->fetchColumn();
    if ($file && file_exists(ROOT_DIR . '/public/uploads/avatars/' . $file)) {
        unlink(ROOT_DIR . '/public/uploads/avatars/' . $file);
    }

    $nameStmt = $db->prepare('SELECT CONCAT(first_name, " ", last_name, " (", email, ")") FROM users WHERE id = ?');
    $nameStmt->execute([$id]);
    $deletedName = $nameStmt->fetchColumn() ?: "id={$id}";

    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    logAudit('user.delete', $id, 'user', $deletedName);

    if ($transferTo !== null) {
        flash('success', "User \"{$deletedName}\" was deleted and their records were transferred successfully.");
    } else {
        flash('success', "User \"{$deletedName}\" was deleted.");
    }
    redirect('/admin/users');
});

$router->post('/admin/users/{id}/reset-2fa', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/users/{$id}");
    }
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT CONCAT(first_name, " ", last_name, " (", email, ")") FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn() ?: "id={$id}";
    $db->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?')->execute([$id]);
    logAudit('2fa.admin_reset', $id, 'user', $name);
    flash('success', '2FA has been reset for this user.');
    redirect("/admin/users/{$id}");
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
 * ADMIN – Ticket Templates
 * ================================================================== */

$router->get('/admin/ticket-templates', function () {
    Auth::requireRole('admin', 'agent');
    $db = Database::connect();
    $templates = $db->query(
        "SELECT t.*,
                CONCAT(u.first_name, ' ', u.last_name) AS creator_name,
                tp.name  AS type_name,
                pri.name AS priority_name
         FROM ticket_templates t
         LEFT JOIN users             u   ON t.created_by  = u.id
         LEFT JOIN ticket_types      tp  ON t.type_id     = tp.id
         LEFT JOIN ticket_priorities pri ON t.priority_id = pri.id
         ORDER BY t.name"
    )->fetchAll();
    render('admin/ticket-templates/index', ['templates' => $templates]);
});

$router->get('/admin/ticket-templates/create', function () {
    Auth::requireRole('admin', 'agent');
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    render('admin/ticket-templates/form', ['types' => $types, 'priorities' => $priorities]);
});

$router->post('/admin/ticket-templates/create', function () {
    Auth::requireRole('admin', 'agent');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/ticket-templates/create');
    }
    $name     = trim($_POST['name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $typeId   = !empty($_POST['type_id'])     ? (int) $_POST['type_id']     : null;
    $priId    = !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null;
    $isShared = !empty($_POST['is_shared'])   ? 1 : 0;

    if ($name === '') {
        flash('error', 'Template name is required.');
        flashInput($_POST);
        redirect('/admin/ticket-templates/create');
    }

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO ticket_templates (name, description, subject, body, type_id, priority_id, is_shared, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$name, $desc ?: null, $subject, $body, $typeId, $priId, $isShared, Auth::id()]);

    flash('success', 'Template created.');
    redirect('/admin/ticket-templates');
});

$router->get('/admin/ticket-templates/{id}/edit', function (array $p) {
    Auth::requireRole('admin', 'agent');
    $db   = Database::connect();
    $tpl  = $db->prepare('SELECT * FROM ticket_templates WHERE id = ?');
    $tpl->execute([(int) $p['id']]);
    $editing = $tpl->fetch();
    if (!$editing) {
        flash('error', 'Template not found.');
        redirect('/admin/ticket-templates');
    }
    // Only creator or admin may edit
    if (Auth::role() !== 'admin' && $editing['created_by'] !== Auth::id()) {
        flash('error', 'You can only edit your own templates.');
        redirect('/admin/ticket-templates');
    }
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    render('admin/ticket-templates/form', ['editing' => $editing, 'types' => $types, 'priorities' => $priorities]);
});

$router->post('/admin/ticket-templates/{id}/edit', function (array $p) {
    Auth::requireRole('admin', 'agent');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/ticket-templates/{$id}/edit");
    }
    $db  = Database::connect();
    $tpl = $db->prepare('SELECT * FROM ticket_templates WHERE id = ?');
    $tpl->execute([$id]);
    $existing = $tpl->fetch();
    if (!$existing || (Auth::role() !== 'admin' && $existing['created_by'] !== Auth::id())) {
        flash('error', 'Not found or insufficient permissions.');
        redirect('/admin/ticket-templates');
    }
    $name     = trim($_POST['name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $typeId   = !empty($_POST['type_id'])     ? (int) $_POST['type_id']     : null;
    $priId    = !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null;
    $isShared = !empty($_POST['is_shared'])   ? 1 : 0;

    if ($name === '') {
        flash('error', 'Template name is required.');
        flashInput($_POST);
        redirect("/admin/ticket-templates/{$id}/edit");
    }
    $db->prepare(
        'UPDATE ticket_templates SET name=?, description=?, subject=?, body=?, type_id=?, priority_id=?, is_shared=? WHERE id=?'
    )->execute([$name, $desc ?: null, $subject, $body, $typeId, $priId, $isShared, $id]);

    flash('success', 'Template updated.');
    redirect('/admin/ticket-templates');
});

$router->post('/admin/ticket-templates/{id}/delete', function (array $p) {
    Auth::requireRole('admin', 'agent');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/ticket-templates');
    }
    $db  = Database::connect();
    $tpl = $db->prepare('SELECT created_by FROM ticket_templates WHERE id = ?');
    $tpl->execute([$id]);
    $existing = $tpl->fetch();
    if (!$existing || (Auth::role() !== 'admin' && $existing['created_by'] !== Auth::id())) {
        flash('error', 'Not found or insufficient permissions.');
        redirect('/admin/ticket-templates');
    }
    $db->prepare('DELETE FROM ticket_templates WHERE id = ?')->execute([$id]);
    flash('success', 'Template deleted.');
    redirect('/admin/ticket-templates');
});

/* ==================================================================
 * ADMIN – Ticket Viewing
 * ================================================================== */

$router->get('/admin/tickets', function () {
    Auth::requireRole('admin');
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
                redirect('/admin/tickets?' . http_build_query($filterData));
            }
        }
    }

    // Read filter params
    $filters = [
        'status'    => trim($_GET['status'] ?? ''),
        'priority'  => trim($_GET['priority'] ?? ''),
        'type'      => trim($_GET['type'] ?? ''),
        'location'  => trim($_GET['location'] ?? ''),
        'agent'     => trim($_GET['agent'] ?? ''),
        'group'     => trim($_GET['group'] ?? ''),
        'q'         => trim($_GET['q'] ?? ''),
        'date_from' => trim($_GET['date_from'] ?? ''),
        'date_to'   => trim($_GET['date_to'] ?? ''),
    ];

    $filterResult = buildTicketFilterQuery($filters);
    $whereClause  = $filterResult['where'];
    $params       = $filterResult['params'];

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
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

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

    render('admin/tickets/index', [
        'tickets'        => $tickets,
        'priorities'     => $priorities,
        'types'          => $types,
        'locations'      => $locations,
        'agents'         => $agents,
        'groups'         => $groups,
        'filters'        => $filters,
        'savedFilters'   => $savedFilters,
        'page'           => $page,
        'totalPages'     => $totalPages,
        'totalTickets'   => $totalTickets,
        'sort'           => $sort,
        'dir'            => strtolower($dir),
        'visibleColumns' => getUserColumns(Auth::id()),
    ]);
});

/* ── Export Tickets (CSV) ─────────────────────────────────────────── */

$router->get('/admin/tickets/export', function () {
    Auth::requireRole('admin');
    $db = Database::connect();

    // Build filters from query params
    $filters = [
        'status'    => trim($_GET['status'] ?? ''),
        'priority'  => trim($_GET['priority'] ?? ''),
        'type'      => trim($_GET['type'] ?? ''),
        'location'  => trim($_GET['location'] ?? ''),
        'agent'     => trim($_GET['agent'] ?? ''),
        'group'     => trim($_GET['group'] ?? ''),
        'q'         => trim($_GET['q'] ?? ''),
        'date_from' => trim($_GET['date_from'] ?? ''),
        'date_to'   => trim($_GET['date_to'] ?? ''),
    ];

    $filterResult = buildTicketFilterQuery($filters);
    $whereClause  = $filterResult['where'];
    $params       = $filterResult['params'];

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
    $sort     = $_GET['sort'] ?? 'created_at';
    $dir      = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $orderCol = $sortableColumns[$sort] ?? 't.created_at';

    $sql = "SELECT t.*,
                tp.name AS priority_name,
                l.name  AS location_name,
                tt.name AS type_name,
                g.name  AS group_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name,
                (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ')
                 FROM ticket_tag_map ttm
                 JOIN ticket_tags tg ON ttm.tag_id = tg.id
                 WHERE ttm.ticket_id = t.id) AS tag_list
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         LEFT JOIN `groups` g          ON t.group_id     = g.id"
         . $whereClause
         . " ORDER BY {$orderCol} {$dir}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $statusLabels = [
        'open'                   => 'Open',
        'in_progress'            => 'In Progress',
        'pending'                => 'Pending',
        'waiting_on_customer'    => 'Waiting on Customer',
        'waiting_on_third_party' => 'Waiting on Third Party',
        'resolved'               => 'Resolved',
        'closed'                 => 'Closed',
    ];

    $filename = 'tickets-export-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // BOM for Excel UTF-8 compatibility
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($out, [
        'ID', 'Subject', 'Status', 'Priority', 'Type', 'Location',
        'Group', 'Assigned To', 'Created By', 'Tags',
        'Created', 'Due Date', 'SLA State',
    ]);

    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'],
            $row['subject'],
            $statusLabels[$row['status']] ?? $row['status'],
            $row['priority_name'] ?? '',
            $row['type_name'] ?? '',
            $row['location_name'] ?? '',
            $row['group_name'] ?? '',
            $row['agent_name'] ?? 'Unassigned',
            $row['creator_name'] ?? '',
            $row['tag_list'] ?? '',
            $row['created_at'] ? date('Y-m-d H:i', strtotime($row['created_at'])) : '',
            $row['due_date'] ? date('Y-m-d H:i', strtotime($row['due_date'])) : '',
            $row['sla_state'] ?? '',
        ]);
    }

    fclose($out);
    exit;
});

/* ── Column Preferences (Admin) ───────────────────────────────────── */

$router->post('/admin/tickets/columns', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $columns = $_POST['columns'] ?? [];
    if (!is_array($columns)) {
        $columns = [];
    }
    setUserColumns(Auth::id(), $columns);
    redirect($_POST['_redirect'] ?? '/admin/tickets');
});

/* ── Saved Filters (Admin) ────────────────────────────────────────── */

$router->post('/admin/tickets/filters/save', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', 'Filter name is required.');
        redirect('/admin/tickets');
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
    redirect('/admin/tickets' . ($qs ? '?' . $qs : ''));
});

$router->post('/admin/tickets/filters/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    if (!$stmt->fetch()) {
        flash('error', 'Filter not found or access denied.');
        redirect('/admin/tickets');
    }

    $db->prepare('DELETE FROM saved_filters WHERE id = ?')->execute([$id]);
    flash('success', 'Filter deleted.');
    redirect('/admin/tickets');
});

$router->post('/admin/tickets/filters/{id}/toggle-share', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    $filter = $stmt->fetch();
    if (!$filter) {
        flash('error', 'Filter not found or access denied.');
        redirect('/admin/tickets');
    }

    $newShared = $filter['is_shared'] ? 0 : 1;
    $db->prepare('UPDATE saved_filters SET is_shared = ? WHERE id = ?')->execute([$newShared, $id]);
    flash('success', $newShared ? 'Filter is now shared.' : 'Filter is now private.');
    redirect('/admin/tickets');
});

$router->post('/admin/tickets/filters/{id}/toggle-default', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    $filter = $stmt->fetch();
    if (!$filter) {
        flash('error', 'Filter not found or access denied.');
        redirect('/admin/tickets');
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
    redirect('/admin/tickets');
});

/* ==================================================================
 * ADMIN – Ticket Search (JSON, for merge modal typeahead)
 * ================================================================== */

$router->get('/admin/tickets/search', function () {
    Auth::requireRole('admin');
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

$router->get('/admin/tickets/create', function () {
    Auth::requireRole('admin', 'agent');
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $agents     = $db->query(
        "SELECT id, first_name, last_name, email FROM users
         WHERE role IN ('admin','agent') ORDER BY first_name, last_name"
    )->fetchAll();
    $templates  = $db->query(
        'SELECT * FROM ticket_templates ORDER BY name'
    )->fetchAll();
    render('admin/tickets/create', [
        'types'      => $types,
        'priorities' => $priorities,
        'locations'  => $locations,
        'groups'     => $groups,
        'agents'     => $agents,
        'templates'  => $templates,
        'isAgent'    => false,
    ]);
});

$router->post('/admin/tickets/create', function () {
    Auth::requireRole('admin', 'agent');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets/create');
    }

    $subject    = trim($_POST['subject'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $typeId     = !empty($_POST['type_id'])      ? (int) $_POST['type_id']      : null;
    $priId      = !empty($_POST['priority_id'])  ? (int) $_POST['priority_id']  : null;
    $locationId = !empty($_POST['location_id'])  ? (int) $_POST['location_id']  : null;
    $assignedTo = !empty($_POST['assigned_to'])  ? (int) $_POST['assigned_to']  : null;
    $groupId    = !empty($_POST['group_id'])      ? (int) $_POST['group_id']     : null;
    $status     = $_POST['status'] ?? 'open';
    $dueDate    = trim($_POST['due_date'] ?? '') ?: null;
    $tagNames   = $_POST['tags'] ?? [];
    // Admin can create on behalf of another user
    $onBehalf   = !empty($_POST['on_behalf_of_id']) ? (int) $_POST['on_behalf_of_id'] : null;
    $createdBy  = (Auth::role() === 'admin' && $onBehalf) ? $onBehalf : Auth::id();

    $validStatuses = ['open','in_progress','pending','waiting_on_customer','waiting_on_third_party','resolved','closed'];
    if (!in_array($status, $validStatuses, true)) {
        $status = 'open';
    }

    if ($subject === '' || $desc === '') {
        flashInput($_POST);
        flash('error', 'Subject and description are required.');
        $redirectBase = Auth::role() === 'agent' ? '/agent' : '/admin';
        redirect("{$redirectBase}/tickets/create");
    }

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO tickets (subject, description, created_by, type_id, location_id, status, priority_id, assigned_to, group_id, due_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$subject, $desc, $createdBy, $typeId, $locationId, $status, $priId, $assignedTo, $groupId, $dueDate]);
    $ticketId = (int) $db->lastInsertId();

    // Tags
    if (!empty($tagNames)) {
        $findTag   = $db->prepare('SELECT id FROM ticket_tags WHERE name = ?');
        $createTag = $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)');
        $mapStmt   = $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)');
        foreach ($tagNames as $rawName) {
            $name = trim(preg_replace('/[^a-zA-Z0-9_\-\s]/', '', strtolower($rawName)));
            if ($name === '') continue;
            $findTag->execute([$name]);
            $tagId = $findTag->fetchColumn();
            if (!$tagId) {
                $createTag->execute([$name]);
                $tagId = (int) $db->lastInsertId();
            }
            $mapStmt->execute([$ticketId, (int) $tagId]);
        }
    }

    // Timeline
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details) VALUES (?, ?, ?, ?)'
    )->execute([$ticketId, Auth::id(), 'created', 'Ticket created by ' . Auth::fullName() . '.']);

    // Automations
    runAutomations($db, $ticketId, 'ticket_created');

    flash('success', 'Ticket #' . $ticketId . ' created.');
    $redirectBase = Auth::role() === 'agent' ? '/agent' : '/admin';
    redirect("{$redirectBase}/tickets/{$ticketId}");
});

$router->post('/admin/tickets/bulk', function () {
    Auth::requireRole('admin', 'agent');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $action    = $_POST['action'] ?? '';
    $rawIds    = $_POST['ticket_ids'] ?? [];
    $ticketIds = array_values(array_unique(array_map('intval', (array) $rawIds)));
    $ticketIds = array_filter($ticketIds, fn($id) => $id > 0);

    if (empty($ticketIds)) {
        flash('error', 'No tickets selected.');
        redirect('/admin/tickets');
    }

    $db          = Database::connect();
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
                redirect('/admin/tickets');
            }
            sort($ticketIds);
            $targetId = array_shift($ticketIds); // lowest ID becomes the primary
            $tgt = $db->prepare('SELECT id, subject FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
            $tgt->execute([$targetId]);
            $targetTicket = $tgt->fetch();
            if (!$targetTicket) {
                flash('error', 'Primary ticket not found or already merged.');
                redirect('/admin/tickets');
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
            redirect("/admin/tickets/{$targetId}");

        case 'delete':
            Auth::requireRole('admin');
            $files = $db->prepare("SELECT stored_name FROM ticket_attachments WHERE ticket_id IN ({$placeholders})");
            $files->execute($ticketIds);
            foreach ($files->fetchAll() as $f) {
                $path = ATTACHMENT_STORAGE_PATH . $f['stored_name'];
                if (file_exists($path)) unlink($path);
            }
            $db->prepare("DELETE FROM tickets WHERE id IN ({$placeholders})")->execute($ticketIds);
            flash('success', count($ticketIds) . ' ticket(s) deleted.');
            break;

        default:
            flash('error', 'Unknown action.');
    }

    redirect('/admin/tickets');
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
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name,
                g.name AS group_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         LEFT JOIN `groups` g          ON t.group_id     = g.id
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

    $groups = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

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

    render('admin/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'priorities' => $priorities, 'attachments' => $attachments, 'ccUsers' => $ccUsers, 'groups' => $groups, 'customFields' => $customFields, 'fieldValues' => $fieldValues, 'fieldOptions' => $fieldOptions]);
});

/* ==================================================================
 * ADMIN – Merge Ticket into Another
 * ================================================================== */

$router->post('/admin/tickets/{id}/merge', function (array $p) {
    Auth::requireRole('admin');
    $sourceId = (int) $p['id'];

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$sourceId}");
    }

    $targetId = (int) ($_POST['merge_into_id'] ?? 0);

    if ($targetId === 0 || $targetId === $sourceId) {
        flash('error', 'Please select a valid ticket to merge into.');
        redirect("/admin/tickets/{$sourceId}");
    }

    $db = Database::connect();

    // Validate source ticket (must exist and not already merged)
    $src = $db->prepare('SELECT id, subject FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $src->execute([$sourceId]);
    $sourceTicket = $src->fetch();
    if (!$sourceTicket) {
        flash('error', 'Source ticket not found or already merged.');
        redirect("/admin/tickets/{$sourceId}");
    }

    // Validate target ticket (must exist and not itself be merged)
    $tgt = $db->prepare('SELECT id, subject FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $tgt->execute([$targetId]);
    $targetTicket = $tgt->fetch();
    if (!$targetTicket) {
        flash('error', 'Target ticket not found or is itself a merged ticket.');
        redirect("/admin/tickets/{$sourceId}");
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
        redirect("/admin/tickets/{$sourceId}");
    }

    notifyTicketMerged($db, $sourceId, $targetId);

    flash('success', "Ticket #{$sourceId} merged into #{$targetId}.");
    redirect("/admin/tickets/{$targetId}");
});

/* ==================================================================
 * ADMIN – Save Custom Field Values on a Ticket
 * ================================================================== */

$router->post('/admin/tickets/{id}/fields', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$id}");
    }

    $db = Database::connect();
    // Verify ticket exists
    $t = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $t->execute([$id]);
    if (!$t->fetch()) {
        flash('error', 'Ticket not found.');
        redirect('/admin/tickets');
    }

    $fields = $db->query('SELECT * FROM ticket_form_fields WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
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
            // Delete stored value if cleared
            $db->prepare('DELETE FROM ticket_field_values WHERE ticket_id = ? AND field_id = ?')
               ->execute([$id, $field['id']]);
            continue;
        }
        $saveStmt->execute([$id, $field['id'], $val]);
    }

    flash('success', 'Custom fields updated.');
    redirect("/admin/tickets/{$id}");
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
    $validStatuses = ['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party', 'resolved', 'closed'];
    if ($newStatus !== '' && in_array($newStatus, $validStatuses, true) && $newStatus !== $ticket['status']) {
        $oldStatus = $ticket['status'];
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$newStatus}"]);
        $changes[] = 'status';

        // SLA: pause on waiting statuses, resume when leaving them
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
            $s = $db->prepare('SELECT name FROM `groups` WHERE id = ?');
            $s->execute([$oldGroup]);
            $oldGroupName = $s->fetchColumn() ?: 'None';
        }
        if ($newGroup) {
            $s = $db->prepare('SELECT name FROM `groups` WHERE id = ?');
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
    redirect("/admin/tickets/{$id}");
});

/* ==================================================================
 * ADMIN – Delete All Tickets
 * ================================================================== */
$router->post('/admin/tickets/delete-all', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $db = Database::connect();

    // Delete attachment files from disk
    $files = $db->query('SELECT stored_name FROM ticket_attachments')->fetchAll();
    foreach ($files as $f) {
        $path = ATTACHMENT_STORAGE_PATH . $f['stored_name'];
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // Delete all tickets (cascades to timeline, attachments, notifications)
    $count = $db->exec('DELETE FROM tickets');

    flash('success', "Deleted {$count} ticket(s) and all associated data.");
    redirect('/admin/settings/danger-zone');
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
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $db->prepare('INSERT INTO kb_categories (name, slug, description, is_public, sort_order) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $slug, $desc, $isPublic, $order]);
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
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $db->prepare('UPDATE kb_categories SET name=?, slug=?, description=?, is_public=?, sort_order=? WHERE id=?')
        ->execute([$name, $slug, $desc, $isPublic, $order, $id]);
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
    $newId = (int) $db->lastInsertId();
    // Save initial revision
    $db->prepare('INSERT INTO kb_article_revisions (article_id, title, body_markdown, edited_by) VALUES (?, ?, ?, ?)')
       ->execute([$newId, $title, $body, Auth::id()]);
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

    // Save revision snapshot before overwriting
    $snap = $db->prepare('SELECT title, body_markdown FROM kb_articles WHERE id = ?');
    $snap->execute([$id]);
    $snapRow = $snap->fetch();
    if ($snapRow) {
        $db->prepare('INSERT INTO kb_article_revisions (article_id, title, body_markdown, edited_by) VALUES (?, ?, ?, ?)')
           ->execute([$id, $snapRow['title'], $snapRow['body_markdown'], Auth::id()]);
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
 * ADMIN – KB Article Version History
 * ================================================================== */

$router->get('/admin/kb/articles/{id}/history', function (array $p) {
    Auth::requireRole('admin');
    $db      = Database::connect();
    $id      = (int) $p['id'];
    $stmt    = $db->prepare('SELECT * FROM kb_articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) {
        flash('error', 'Article not found.');
        redirect('/admin/kb/articles');
    }
    $revStmt = $db->prepare(
        "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS editor_name
         FROM kb_article_revisions r
         LEFT JOIN users u ON r.edited_by = u.id
         WHERE r.article_id = ?
         ORDER BY r.created_at DESC"
    );
    $revStmt->execute([$id]);
    $revisions = $revStmt->fetchAll();
    render('admin/kb/articles/history', compact('article', 'revisions'));
});

$router->get('/admin/kb/articles/{id}/history/{rid}', function (array $p) {
    Auth::requireRole('admin');
    $db      = Database::connect();
    $id      = (int) $p['id'];
    $rid     = (int) $p['rid'];

    $artStmt = $db->prepare('SELECT * FROM kb_articles WHERE id = ?');
    $artStmt->execute([$id]);
    $article = $artStmt->fetch();
    if (!$article) {
        flash('error', 'Article not found.');
        redirect('/admin/kb/articles');
    }

    $revStmt = $db->prepare(
        "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS editor_name
         FROM kb_article_revisions r
         LEFT JOIN users u ON r.edited_by = u.id
         WHERE r.id = ? AND r.article_id = ?"
    );
    $revStmt->execute([$rid, $id]);
    $revision = $revStmt->fetch();
    if (!$revision) {
        flash('error', 'Revision not found.');
        redirect("/admin/kb/articles/{$id}/history");
    }

    // LCS-based line diff (revision body vs current body)
    $computeDiff = function (string $old, string $new): array {
        $a = explode("\n", $old);
        $b = explode("\n", $new);
        $m = count($a);
        $n = count($b);
        if ($m > 800 || $n > 800) {
            return [['type' => 'too_large']];
        }
        // Build LCS table
        $dp = [];
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i] = array_fill(0, $n + 1, 0);
        }
        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $dp[$i][$j] = $a[$i-1] === $b[$j-1]
                    ? $dp[$i-1][$j-1] + 1
                    : max($dp[$i-1][$j], $dp[$i][$j-1]);
            }
        }
        // Traceback
        $diff = [];
        $i = $m; $j = $n;
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i-1] === $b[$j-1]) {
                array_unshift($diff, ['type' => 'eq', 'line' => $a[$i-1]]);
                $i--; $j--;
            } elseif ($j > 0 && ($i === 0 || $dp[$i][$j-1] >= $dp[$i-1][$j])) {
                array_unshift($diff, ['type' => 'add', 'line' => $b[$j-1]]);
                $j--;
            } else {
                array_unshift($diff, ['type' => 'del', 'line' => $a[$i-1]]);
                $i--;
            }
        }
        return $diff;
    };

    $diff = $computeDiff($revision['body_markdown'], $article['body_markdown']);

    render('admin/kb/articles/diff', compact('article', 'revision', 'diff'));
});

/* ==================================================================
 * ADMIN – Settings (Email / SMTP Configuration)
 * ================================================================== */

$router->get('/admin/settings', function () {
    Auth::requireRole('admin');
    $keys = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'mail_from_address', 'mail_from_name',
        'graph_enabled', 'graph_reply_to', 'graph_tenant_id', 'graph_client_id',
        'graph_client_secret', 'graph_mailbox',
    ];
    $settings = [];
    foreach ($keys as $k) {
        $settings[$k] = getSetting($k);
    }
    // Retrieve and clear any stored processor run output
    $runOutput = $_SESSION['_processor_run'] ?? null;
    unset($_SESSION['_processor_run']);

    render('admin/settings/index', ['settings' => $settings, 'runOutput' => $runOutput]);
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

$router->post('/admin/settings/graph', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $fields = [
        'graph_enabled'   => isset($_POST['graph_enabled']) ? '1' : '0',
        'graph_reply_to'  => trim($_POST['graph_reply_to'] ?? ''),
        'graph_tenant_id' => trim($_POST['graph_tenant_id'] ?? ''),
        'graph_client_id' => trim($_POST['graph_client_id'] ?? ''),
        'graph_mailbox'   => trim($_POST['graph_mailbox'] ?? ''),
    ];

    // Only update client secret if a new one was provided
    $secret = $_POST['graph_client_secret'] ?? '';
    if ($secret !== '') {
        $fields['graph_client_secret'] = $secret;
    }

    foreach ($fields as $key => $value) {
        setSetting($key, $value);
    }

    // Ensure log directory exists
    @mkdir(ROOT_DIR . '/storage/logs', 0755, true);

    flash('success', 'Inbound mail settings saved.');
    redirect('/admin/settings');
});

$router->get('/admin/settings/email-reply-help', function () {
    Auth::requireRole('admin');
    render('admin/settings/email-reply-help', []);
});

$router->post('/admin/settings/run-reply-processor', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $script = ROOT_DIR . '/scripts/process-replies.php';
    $cmd    = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';

    $outputLines = [];
    $returnCode  = 0;
    exec($cmd, $outputLines, $returnCode);

    $_SESSION['_processor_run'] = [
        'lines' => $outputLines,
        'code'  => $returnCode,
        'time'  => date('Y-m-d H:i:s'),
    ];

    redirect('/admin/settings');
});

$router->post('/admin/settings/test-email', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $toEmail = trim($_POST['to_email'] ?? '');
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid recipient email address.');
        redirect('/admin/settings');
    }

    $user     = Auth::user();
    $htmlBody = '<h2>It works!</h2><p>This is a test email from <strong>LocalDesk</strong>. Your SMTP configuration is correct.</p>';
    $result   = sendMail(
        $toEmail,
        $toEmail,
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
 * ADMIN – Email Templates
 * ================================================================== */

$router->get('/admin/settings/email-templates', function () {
    Auth::requireRole('admin');

    $keys = [
        'email_subject_ticket_created',  'email_intro_ticket_created',  'email_button_ticket_created',
        'email_subject_ticket_updated',  'email_intro_ticket_updated',  'email_button_ticket_updated',
        'email_subject_ticket_merged',   'email_intro_ticket_merged',   'email_button_ticket_merged',
        'email_subject_csat_survey',     'email_intro_csat_survey',
        'email_subject_ticket_reminder', 'email_intro_ticket_reminder', 'email_button_ticket_reminder',
        'email_footer_text',
    ];
    $tplValues = [];
    foreach ($keys as $k) {
        $tplValues[$k] = getSetting($k);
    }

    render('admin/settings/email-templates', ['tplValues' => $tplValues]);
});

$router->post('/admin/settings/email-templates', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/email-templates');
    }

    $tab = $_POST['tab'] ?? 'ticket_created';

    // Reset buttons clear settings back to default (empty = use hardcoded default)
    if (isset($_POST['reset_template']) && in_array($_POST['reset_template'], ['ticket_created', 'ticket_updated', 'ticket_merged', 'csat_survey', 'ticket_reminder'], true)) {
        $tpl = $_POST['reset_template'];
        setSetting("email_subject_{$tpl}", '');
        setSetting("email_intro_{$tpl}", '');
        if ($tpl !== 'csat_survey') {
            setSetting("email_button_{$tpl}", '');
        }
        flash('success', 'Email template reset to defaults.');
        redirect('/admin/settings/email-templates?tab=' . $tpl);
    }

    if (isset($_POST['reset_footer'])) {
        setSetting('email_footer_text', '');
        flash('success', 'Footer text reset to default.');
        redirect('/admin/settings/email-templates?tab=shared');
    }

    if ($tab === 'shared') {
        setSetting('email_footer_text', trim($_POST['email_footer_text'] ?? ''));
        flash('success', 'Footer text saved.');
    } elseif (in_array($tab, ['ticket_created', 'ticket_updated', 'ticket_merged', 'csat_survey', 'ticket_reminder'], true)) {
        setSetting("email_subject_{$tab}", trim($_POST["email_subject_{$tab}"] ?? ''));
        setSetting("email_intro_{$tab}",   trim($_POST["email_intro_{$tab}"]   ?? ''));
        if ($tab !== 'csat_survey') {
            setSetting("email_button_{$tab}",  trim($_POST["email_button_{$tab}"]  ?? ''));
        }
        flash('success', 'Email template saved.');
    }

    redirect('/admin/settings/email-templates?tab=' . urlencode($tab));
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
 * ADMIN – Import Tickets from CSV
 * ================================================================== */

$router->get('/admin/settings/import', function () {
    Auth::requireRole('admin');
    render('admin/settings/import');
});

$router->post('/admin/settings/import/preview', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import');
    }

    // Validate upload
    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please select a valid CSV file.');
        redirect('/admin/settings/import');
    }
    if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
        flash('error', 'File is too large. Maximum 10 MB.');
        redirect('/admin/settings/import');
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        flash('error', 'Could not read the uploaded file.');
        redirect('/admin/settings/import');
    }

    // Read header row, strip BOM
    $header = fgetcsv($handle);
    if (!$header || count($header) < 2) {
        fclose($handle);
        flash('error', 'The CSV file appears to be empty or has too few columns.');
        redirect('/admin/settings/import');
    }
    $header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

    // Read all data rows
    $rawRows = [];
    while (($csvRow = fgetcsv($handle)) !== false) {
        if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) {
            continue; // skip blank rows
        }
        $row = [];
        foreach ($header as $i => $col) {
            $row[$col] = trim($csvRow[$i] ?? '');
        }
        $rawRows[] = $row;
    }
    fclose($handle);

    if (empty($rawRows)) {
        flash('error', 'No data rows found in the CSV file.');
        redirect('/admin/settings/import');
    }

    $_SESSION['import_raw'] = ['headers' => $header, 'rows' => $rawRows];
    unset($_SESSION['import_data'], $_SESSION['import_summary']);
    redirect('/admin/settings/import/map');
});

$router->get('/admin/settings/import/map', function () {
    Auth::requireRole('admin');
    $raw = $_SESSION['import_raw'] ?? null;
    if (!$raw) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import');
    }

    $headers = $raw['headers'];

    $systemFields = [
        ['key' => 'subject',      'label' => 'Subject',             'required' => true],
        ['key' => 'description',  'label' => 'Description',         'required' => false],
        ['key' => 'legacy_id',    'label' => 'Ticket ID (Legacy)',   'required' => false],
        ['key' => 'email',        'label' => 'Submitter Email',      'required' => true],
        ['key' => 'full_name',    'label' => 'Submitter Name',       'required' => false],
        ['key' => 'status',       'label' => 'Status',               'required' => false],
        ['key' => 'priority',     'label' => 'Priority',             'required' => false],
        ['key' => 'agent',        'label' => 'Assigned Agent',       'required' => false],
        ['key' => 'type',         'label' => 'Ticket Type',          'required' => false],
        ['key' => 'location',     'label' => 'Location',             'required' => false],
        ['key' => 'created_at',   'label' => 'Created Date',         'required' => false],
        ['key' => 'due_date',     'label' => 'Due Date',             'required' => false],
        ['key' => 'updated_at',   'label' => 'Last Updated',         'required' => false],
        ['key' => 'responded_at', 'label' => 'First Response Date',  'required' => false],
        ['key' => 'tags',         'label' => 'Tags',                 'required' => false],
    ];

    $aliases = [
        'subject'     => ['subject', 'title', 'issue', 'summary', 'ticket subject'],
        'description' => ['description', 'body', 'details', 'content', 'message', 'issue description'],
        'legacy_id'   => ['ticket id', 'id', 'ticket_id', 'legacy id', '#', 'ticket number'],
        'email'       => ['email', 'requester email', 'customer email', 'contact email', 'submitter email'],
        'full_name'   => ['full name', 'full_name', 'name', 'customer name', 'requester name', 'submitter'],
        'status'      => ['status', 'ticket status', 'state'],
        'priority'    => ['priority', 'priority name', 'urgency'],
        'agent'       => ['agent', 'assigned agent', 'assignee', 'agent name', 'assigned to'],
        'type'        => ['type of ticket', 'type', 'ticket type', 'category', 'issue type'],
        'location'    => ['location', 'department', 'branch', 'site', 'office'],
        'created_at'  => ['created time', 'created_at', 'created date', 'date created', 'open date', 'created_time'],
        'due_date'    => ['due by time', 'due_date', 'due date', 'deadline', 'due_by_time'],
        'updated_at'  => ['last update time', 'updated_at', 'last updated', 'modified', 'last_update_time'],
        'responded_at'=> ['initial response time', 'responded_at', 'first response', 'initial_response_time'],
        'tags'        => ['tags', 'tag', 'labels', 'label'],
    ];

    $lowerHeaders = array_map('strtolower', $headers);
    $autoMapping  = [];
    foreach ($systemFields as $field) {
        $autoMapping[$field['key']] = null;
        $aliasList = $aliases[$field['key']] ?? [strtolower($field['label'])];
        foreach ($aliasList as $alias) {
            $idx = array_search($alias, $lowerHeaders, true);
            if ($idx !== false) {
                $autoMapping[$field['key']] = $headers[$idx];
                break;
            }
        }
    }

    render('admin/settings/import-map', [
        'headers'      => $headers,
        'systemFields' => $systemFields,
        'autoMapping'  => $autoMapping,
        'sampleRows'   => array_slice($raw['rows'], 0, 3),
    ]);
});

$router->post('/admin/settings/import/map', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import');
    }

    $raw = $_SESSION['import_raw'] ?? null;
    if (!$raw) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import');
    }

    $userMapping = $_POST['mapping'] ?? [];
    $subjectCol  = $userMapping['subject'] ?? '';
    $emailCol    = $userMapping['email']   ?? '';

    if ($subjectCol === '' || $emailCol === '') {
        flash('error', 'Subject and Submitter Email are required. Please map them to a CSV column.');
        redirect('/admin/settings/import/map');
    }

    // Normalize raw rows using the user-supplied mapping
    $rows = [];
    foreach ($raw['rows'] as $rawRow) {
        $get = function (string $fieldKey) use ($rawRow, $userMapping): string {
            $col = $userMapping[$fieldKey] ?? '';
            return $col !== '' ? trim($rawRow[$col] ?? '') : '';
        };

        $subject = $get('subject');
        $email   = strtolower($get('email'));
        if ($subject === '' || $email === '') {
            continue;
        }

        $statusRaw = strtolower($get('status'));
        $statusMap = ['open' => 'open', 'closed' => 'closed', 'pending' => 'pending', 'resolved' => 'resolved'];

        $rows[] = [
            'legacy_id'      => $get('legacy_id'),
            'subject'        => $subject,
            'description'    => $get('description'),
            'email'          => $email,
            'submitter_name' => $get('full_name'),
            'status'         => $statusMap[$statusRaw] ?? 'open',
            'priority'       => $get('priority'),
            'agent'          => $get('agent'),
            'type'           => $get('type'),
            'location'       => $get('location'),
            'created_at'     => $get('created_at'),
            'due_date'       => $get('due_date'),
            'updated_at'     => $get('updated_at'),
            'responded_at'   => $get('responded_at'),
            'tags'           => $get('tags'),
        ];
    }

    if (empty($rows)) {
        flash('error', 'No valid rows found after applying the mapping. Ensure Subject and Submitter Email columns contain data.');
        redirect('/admin/settings/import/map');
    }

    // Build summary using DB lookups
    $db = Database::connect();
    $existingUsers = [];
    foreach ($db->query("SELECT id, email, CONCAT(first_name, ' ', last_name) AS full_name FROM users")->fetchAll() as $u) {
        $existingUsers[strtolower($u['email'])] = $u;
        $existingUsers['name:' . strtolower($u['full_name'])] = $u;
    }
    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = $l['id'];
    }

    $newUserEmails    = [];
    $newAgentNames    = [];
    $newLocationNames = [];
    foreach ($rows as $row) {
        if ($row['email'] !== '' && !isset($existingUsers[$row['email']])) {
            $newUserEmails[$row['email']] = $row['submitter_name'];
        }
        if ($row['agent'] !== '' && $row['agent'] !== 'No Agent' && !isset($existingUsers['name:' . strtolower($row['agent'])])) {
            $newAgentNames[strtolower($row['agent'])] = $row['agent'];
        }
        if ($row['location'] !== '' && !isset($existingLocations[strtolower($row['location'])])) {
            $newLocationNames[strtolower($row['location'])] = $row['location'];
        }
    }

    $_SESSION['import_data']    = $rows;
    $_SESSION['import_summary'] = [
        'total_tickets'     => count($rows),
        'new_users'         => count($newUserEmails),
        'new_agents'        => count($newAgentNames),
        'new_locations'     => count($newLocationNames),
        'new_user_list'     => array_values($newUserEmails),
        'new_agent_list'    => array_values($newAgentNames),
        'new_location_list' => array_values($newLocationNames),
    ];
    redirect('/admin/settings/import/preview');
});

$router->get('/admin/settings/import/preview', function () {
    Auth::requireRole('admin');
    $rows    = $_SESSION['import_data']    ?? null;
    $summary = $_SESSION['import_summary'] ?? null;
    if (!$rows || !$summary) {
        flash('error', 'No import data found. Please start the import again.');
        redirect('/admin/settings/import');
    }
    render('admin/settings/import-preview', [
        'summary'     => $summary,
        'previewRows' => array_slice($rows, 0, 15),
    ]);
});

$router->post('/admin/settings/import/confirm', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import');
    }

    $rows = $_SESSION['import_data'] ?? [];
    unset($_SESSION['import_data']);

    if (empty($rows)) {
        flash('error', 'No import data found. Please upload the CSV again.');
        redirect('/admin/settings/import');
    }

    $db = Database::connect();

    // Load lookups
    $existingUsers = [];
    foreach ($db->query("SELECT id, email, CONCAT(first_name, ' ', last_name) AS full_name FROM users")->fetchAll() as $u) {
        $existingUsers[strtolower($u['email'])] = (int) $u['id'];
        $existingUsers['name:' . strtolower($u['full_name'])] = (int) $u['id'];
    }

    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = (int) $l['id'];
    }

    $existingTypes = [];
    foreach ($db->query('SELECT id, name FROM ticket_types')->fetchAll() as $t) {
        $existingTypes[strtolower($t['name'])] = (int) $t['id'];
    }

    $existingPriorities = [];
    foreach ($db->query('SELECT id, name FROM ticket_priorities')->fetchAll() as $p) {
        $existingPriorities[strtolower($p['name'])] = (int) $p['id'];
    }

    $existingTags = [];
    foreach ($db->query('SELECT id, name FROM ticket_tags')->fetchAll() as $t) {
        $existingTags[strtolower($t['name'])] = (int) $t['id'];
    }

    $imported = 0;
    $skipped  = 0;
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $db->beginTransaction();
    try {
        // Prepared statements
        $insertUser = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role, location_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertLocation = $db->prepare(
            'INSERT INTO locations (name) VALUES (?)'
        );
        $insertType = $db->prepare(
            'INSERT INTO ticket_types (name, sort_order) VALUES (?, ?)'
        );
        $insertTicket = $db->prepare(
            'INSERT INTO tickets (subject, description, legacy_id, created_by, created_at, due_date, type_id, location_id, status, priority_id, assigned_to, first_responded_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertTimeline = $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at) VALUES (?, ?, ?, ?, 0, ?)'
        );
        $insertTag = $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)');
        $insertTagMap = $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)');

        $nextTypeOrder = (int) ($db->query('SELECT COALESCE(MAX(sort_order), 0) FROM ticket_types')->fetchColumn()) + 1;

        foreach ($rows as $row) {
            // --- Resolve submitter ---
            $creatorId = null;
            if ($row['email'] !== '') {
                if (isset($existingUsers[$row['email']])) {
                    $creatorId = $existingUsers[$row['email']];
                } else {
                    $nameParts = splitFullName($row['submitter_name']);
                    $locId = $row['location'] !== '' ? ($existingLocations[strtolower($row['location'])] ?? null) : null;
                    $insertUser->execute([$nameParts[0], $nameParts[1], $row['email'], $randomPassword, 'user', $locId]);
                    $creatorId = (int) $db->lastInsertId();
                    $existingUsers[$row['email']] = $creatorId;
                    $existingUsers['name:' . strtolower($row['submitter_name'])] = $creatorId;
                }
            }

            if ($creatorId === null) {
                $skipped++;
                continue;
            }

            // --- Resolve agent ---
            $agentId = null;
            $agentName = $row['agent'];
            if ($agentName !== '' && $agentName !== 'No Agent') {
                $agentKey = 'name:' . strtolower($agentName);
                if (isset($existingUsers[$agentKey])) {
                    $agentId = $existingUsers[$agentKey];
                } else {
                    $nameParts = splitFullName($agentName);
                    $agentEmail = strtolower(str_replace(' ', '.', $agentName)) . '@imported.local';
                    $insertUser->execute([$nameParts[0], $nameParts[1], $agentEmail, $randomPassword, 'agent', null]);
                    $agentId = (int) $db->lastInsertId();
                    $existingUsers[$agentKey] = $agentId;
                    $existingUsers[$agentEmail] = $agentId;
                }
            }

            // --- Resolve location ---
            $locationId = null;
            if ($row['location'] !== '') {
                $locKey = strtolower($row['location']);
                if (isset($existingLocations[$locKey])) {
                    $locationId = $existingLocations[$locKey];
                } else {
                    $insertLocation->execute([$row['location']]);
                    $locationId = (int) $db->lastInsertId();
                    $existingLocations[$locKey] = $locationId;
                }
            }

            // --- Resolve type ---
            $typeId = null;
            if ($row['type'] !== '') {
                $typeKey = strtolower($row['type']);
                if (isset($existingTypes[$typeKey])) {
                    $typeId = $existingTypes[$typeKey];
                } else {
                    $insertType->execute([$row['type'], $nextTypeOrder++]);
                    $typeId = (int) $db->lastInsertId();
                    $existingTypes[$typeKey] = $typeId;
                }
            }

            // --- Resolve priority ---
            $priorityId = null;
            if ($row['priority'] !== '') {
                $priorityId = $existingPriorities[strtolower($row['priority'])] ?? null;
            }

            // --- Parse dates ---
            $createdAt   = $row['created_at'] !== '' ? $row['created_at'] : date('Y-m-d H:i:s');
            $dueDate     = $row['due_date'] !== '' ? substr($row['due_date'], 0, 10) : null;
            $updatedAt   = $row['updated_at'] !== '' ? $row['updated_at'] : $createdAt;
            $respondedAt = $row['responded_at'] !== '' ? $row['responded_at'] : null;

            // --- Insert ticket ---
            $insertTicket->execute([
                $row['subject'],
                $row['description'] !== '' ? $row['description'] : '(Imported from legacy system)',
                $row['legacy_id'],
                $creatorId,
                $createdAt,
                $dueDate,
                $typeId,
                $locationId,
                $row['status'],
                $priorityId,
                $agentId,
                $respondedAt,
                $updatedAt,
            ]);
            $ticketId = (int) $db->lastInsertId();

            // --- Timeline entry ---
            $insertTimeline->execute([$ticketId, $creatorId, 'created', 'Ticket created (imported from legacy system).', $createdAt]);

            // --- Tags ---
            $tagStr = trim($row['tags'], '" ');
            if ($tagStr !== '') {
                $tagNames = array_filter(array_map('trim', explode(',', $tagStr)));
                foreach ($tagNames as $tagName) {
                    $tagKey = strtolower($tagName);
                    if (!isset($existingTags[$tagKey])) {
                        $insertTag->execute([$tagName]);
                        $existingTags[$tagKey] = (int) $db->lastInsertId();
                    }
                    $insertTagMap->execute([$ticketId, $existingTags[$tagKey]]);
                }
            }

            $imported++;
        }

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/settings/import');
    }

    $msg = "Successfully imported {$imported} ticket(s).";
    if ($skipped > 0) {
        $msg .= " {$skipped} row(s) skipped (missing email).";
    }
    flash('success', $msg);
    redirect('/admin/tickets');
});

/* ==================================================================
 * ADMIN – Settings: Import KB Articles
 * ================================================================== */

$router->get('/admin/settings/import-kb', function () {
    Auth::requireRole('admin');
    render('admin/settings/import-kb');
});

$router->post('/admin/settings/import-kb/preview', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-kb');
        return;
    }

    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please upload a valid CSV file.');
        redirect('/admin/settings/import-kb');
        return;
    }

    if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
        flash('error', 'File too large. Maximum 10 MB.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        flash('error', 'Unable to read file.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $header = fgetcsv($handle);
    if (!$header || !in_array('title', $header) || !in_array('body_markdown', $header)) {
        fclose($handle);
        flash('error', 'CSV must contain "title" and "body_markdown" columns.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $colMap = array_flip($header);
    $rows   = [];
    $categories = [];

    while (($csvRow = fgetcsv($handle)) !== false) {
        $title    = trim($csvRow[$colMap['title']] ?? '');
        $body     = trim($csvRow[$colMap['body_markdown']] ?? '');
        $category = trim($csvRow[$colMap['category'] ?? -1] ?? '') ?: 'General';
        $status   = strtolower(trim($csvRow[$colMap['status'] ?? -1] ?? ''));
        $status   = in_array($status, ['published', 'draft']) ? $status : 'draft';
        $tags     = trim($csvRow[$colMap['tags'] ?? -1] ?? '');

        if ($title === '' || $body === '') {
            continue; // skip empty rows
        }

        $categories[$category] = true;

        $rows[] = [
            'title'    => $title,
            'body'     => $body,
            'category' => $category,
            'status'   => $status,
            'tags'     => $tags,
        ];
    }
    fclose($handle);

    if (empty($rows)) {
        flash('error', 'No valid articles found in CSV.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $_SESSION['kb_import_data'] = $rows;

    // Check which categories already exist
    $db = Database::connect();
    $existingCats = $db->query('SELECT name FROM kb_categories')->fetchAll(PDO::FETCH_COLUMN);
    $newCategories = array_diff(array_keys($categories), $existingCats);

    $summary = [
        'total_articles'  => count($rows),
        'categories'      => array_keys($categories),
        'new_categories'  => $newCategories,
        'draft_count'     => count(array_filter($rows, fn($r) => $r['status'] === 'draft')),
        'published_count' => count(array_filter($rows, fn($r) => $r['status'] === 'published')),
    ];

    $previewRows = array_slice($rows, 0, 15);

    render('admin/settings/import-kb-preview', [
        'summary'     => $summary,
        'previewRows' => $previewRows,
    ]);
});

$router->post('/admin/settings/import-kb/confirm', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $rows = $_SESSION['kb_import_data'] ?? [];
    unset($_SESSION['kb_import_data']);

    if (empty($rows)) {
        flash('error', 'No import data found. Please upload the CSV again.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $db = Database::connect();

    // Build lookup maps for existing categories and folders
    $catLookup    = [];
    $folderLookup = [];

    foreach ($db->query('SELECT id, name FROM kb_categories')->fetchAll() as $c) {
        $catLookup[strtolower($c['name'])] = (int) $c['id'];
    }
    foreach ($db->query('SELECT id, category_id, name FROM kb_folders')->fetchAll() as $f) {
        $folderLookup[(int) $f['category_id'] . ':' . strtolower($f['name'])] = (int) $f['id'];
    }

    $db->beginTransaction();
    try {
        $insertCat    = $db->prepare('INSERT INTO kb_categories (name, slug, sort_order) VALUES (?, ?, ?)');
        $insertFolder = $db->prepare('INSERT INTO kb_folders (category_id, name, slug, sort_order) VALUES (?, ?, ?, ?)');
        $insertArticle = $db->prepare(
            'INSERT INTO kb_articles (folder_id, title, slug, body_markdown, status, published_at, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $checkSlug = $db->prepare('SELECT id FROM kb_articles WHERE slug = ?');

        $imported    = 0;
        $publishAll  = !empty($_POST['publish_all']);
        $nextCatOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order),0) FROM kb_categories')->fetchColumn();

        foreach ($rows as $row) {
            $catName = $row['category'];
            $catKey  = strtolower($catName);

            // Find or create category
            if (!isset($catLookup[$catKey])) {
                $nextCatOrder++;
                $insertCat->execute([$catName, slugify($catName), $nextCatOrder]);
                $catLookup[$catKey] = (int) $db->lastInsertId();
            }
            $catId = $catLookup[$catKey];

            // Find or create "General" folder in this category
            $folderKey = $catId . ':general';
            if (!isset($folderLookup[$folderKey])) {
                $insertFolder->execute([$catId, 'General', slugify($catName . '-general'), 0]);
                $folderLookup[$folderKey] = (int) $db->lastInsertId();
            }
            $folderId = $folderLookup[$folderKey];

            // Generate unique slug
            $slug = slugify($row['title']);
            $checkSlug->execute([$slug]);
            if ($checkSlug->fetch()) {
                $slug .= '-' . time() . '-' . $imported;
            }

            $status     = $publishAll ? 'published' : $row['status'];
            $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

            $insertArticle->execute([
                $folderId,
                $row['title'],
                $slug,
                $row['body'],
                $status,
                $publishedAt,
                Auth::id(),
                0,
            ]);
            $imported++;
        }

        $db->commit();
        flash('success', "Successfully imported {$imported} KB article(s).");
        redirect('/admin/kb/articles');
    } catch (PDOException $e) {
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/settings/import-kb');
    }
});

/* ==================================================================
 * ADMIN – Settings: Branding
 * ================================================================== */

$router->get('/admin/settings/branding', function () {
    Auth::requireRole('admin');
    render('admin/settings/branding', [
        'appName'             => getSetting('branding_app_name', 'LocalDesk'),
        'primaryColor'        => getSetting('branding_primary_color', '#4f46e5'),
        'primaryHover'        => getSetting('branding_primary_hover', '#4338ca'),
        'navbarStart'         => getSetting('branding_navbar_start', '#1e1b4b'),
        'navbarEnd'           => getSetting('branding_navbar_end', '#312e81'),
        'logo'                => getSetting('branding_logo', ''),
        'navbarIcon'          => getSetting('branding_navbar_icon', 'bi-headset'),
        'timelineNoteBg'      => getSetting('branding_timeline_note_bg',      '#fefce8'),
        'timelineNoteAccent'  => getSetting('branding_timeline_note_accent',  '#ca8a04'),
        'timelineSystemBg'    => getSetting('branding_timeline_system_bg',    '#eff6ff'),
        'timelineSystemAccent'=> getSetting('branding_timeline_system_accent','#3b82f6'),
    ]);
});

$router->post('/admin/settings/branding', function () {
    Auth::requireRole('admin');
    verifyCsrf($_POST['_token'] ?? '');

    $appName              = trim($_POST['app_name'] ?? 'LocalDesk');
    $navbarIconRaw        = trim($_POST['navbar_icon'] ?? 'bi-headset');
    // Normalise: ensure it starts with bi- and only contains safe chars
    if (!str_starts_with($navbarIconRaw, 'bi-')) {
        $navbarIconRaw = 'bi-' . $navbarIconRaw;
    }
    $navbarIcon = 'bi-' . preg_replace('/[^a-zA-Z0-9\-]/', '', substr($navbarIconRaw, 3));
    if ($navbarIcon === 'bi-') {
        $navbarIcon = 'bi-headset';
    }
    $primaryColor         = trim($_POST['primary_color'] ?? '#4f46e5');
    $primaryHover         = trim($_POST['primary_hover'] ?? '#4338ca');
    $navbarStart          = trim($_POST['navbar_start'] ?? '#1e1b4b');
    $navbarEnd            = trim($_POST['navbar_end'] ?? '#312e81');
    $timelineNoteBg       = trim($_POST['timeline_note_bg']       ?? '#fefce8');
    $timelineNoteAccent   = trim($_POST['timeline_note_accent']   ?? '#ca8a04');
    $timelineSystemBg     = trim($_POST['timeline_system_bg']     ?? '#eff6ff');
    $timelineSystemAccent = trim($_POST['timeline_system_accent'] ?? '#3b82f6');

    // Validate hex colors
    $colorPattern = '/^#[0-9a-fA-F]{6}$/';
    foreach ([$primaryColor, $primaryHover, $navbarStart, $navbarEnd,
              $timelineNoteBg, $timelineNoteAccent, $timelineSystemBg, $timelineSystemAccent] as $color) {
        if (!preg_match($colorPattern, $color)) {
            flash('error', 'Invalid color format. Use hex colors like #4f46e5.');
            redirect('/admin/settings/branding');
            return;
        }
    }

    // Handle logo upload
    $currentLogo = getSetting('branding_logo', '');

    // Remove logo checkbox
    if (!empty($_POST['remove_logo'])) {
        if ($currentLogo && file_exists(ROOT_DIR . '/public/uploads/branding/' . $currentLogo)) {
            unlink(ROOT_DIR . '/public/uploads/branding/' . $currentLogo);
        }
        setSetting('branding_logo', '');
        $currentLogo = '';
    }

    // New logo upload
    if (!empty($_FILES['logo']['tmp_name'])) {
        $file = $_FILES['logo'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            flash('error', 'Invalid logo file type. Allowed: JPG, PNG, GIF, WEBP, SVG.');
            redirect('/admin/settings/branding');
            return;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            flash('error', 'Logo file is too large. Maximum 2 MB.');
            redirect('/admin/settings/branding');
            return;
        }

        // Delete old logo
        if ($currentLogo && file_exists(ROOT_DIR . '/public/uploads/branding/' . $currentLogo)) {
            unlink(ROOT_DIR . '/public/uploads/branding/' . $currentLogo);
        }

        $ext = match ($mime) {
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/gif'     => 'gif',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
            default         => 'png',
        };
        $filename = 'logo_' . uniqid() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], ROOT_DIR . '/public/uploads/branding/' . $filename);
        setSetting('branding_logo', $filename);
    }

    // Save settings
    setSetting('branding_app_name', $appName);
    setSetting('branding_navbar_icon', $navbarIcon);
    setSetting('branding_primary_color', $primaryColor);
    setSetting('branding_primary_hover', $primaryHover);
    setSetting('branding_navbar_start', $navbarStart);
    setSetting('branding_navbar_end', $navbarEnd);
    setSetting('branding_timeline_note_bg',       $timelineNoteBg);
    setSetting('branding_timeline_note_accent',   $timelineNoteAccent);
    setSetting('branding_timeline_system_bg',     $timelineSystemBg);
    setSetting('branding_timeline_system_accent', $timelineSystemAccent);

    flash('success', 'Branding settings updated successfully.');
    redirect('/admin/settings/branding');
});

/* ==================================================================
 * ADMIN – Settings: Labels
 * ================================================================== */

$router->get('/admin/settings/labels', function () {
    Auth::requireRole('admin');
    render('admin/settings/labels');
});

$router->get('/admin/settings/labels/download', function () {
    Auth::requireRole('admin');
    $defaultFile = ROOT_DIR . '/config/labels.default.json';
    $defaults    = is_file($defaultFile)
        ? (json_decode(file_get_contents($defaultFile), true) ?: [])
        : [];
    $custom  = json_decode(getSetting('custom_labels', '{}'), true) ?: [];
    $merged  = array_merge($defaults, $custom);

    // Remove the internal readme key from the download
    unset($merged['_readme']);
    $merged = array_merge(
        ['_readme' => 'Edit the values (right-hand side) only. Keys must stay exactly as written. Re-upload to apply.'],
        $merged
    );

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="labels.json"');
    echo json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
});

$router->post('/admin/settings/labels/upload', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/labels');
    }

    if (empty($_FILES['labels_file']['tmp_name']) || $_FILES['labels_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please select a valid JSON file.');
        redirect('/admin/settings/labels');
    }

    if ($_FILES['labels_file']['size'] > 512 * 1024) {
        flash('error', 'File is too large. Maximum 512 KB.');
        redirect('/admin/settings/labels');
    }

    $raw = file_get_contents($_FILES['labels_file']['tmp_name']);
    $uploaded = json_decode($raw, true);

    if ($uploaded === null) {
        $_SESSION['label_upload_errors']  = ['The file is not valid JSON: ' . json_last_error_msg()];
        $_SESSION['label_upload_preview'] = $raw;
        redirect('/admin/settings/labels');
    }

    // Load known keys from the default file
    $defaultFile = ROOT_DIR . '/config/labels.default.json';
    $defaults    = is_file($defaultFile)
        ? (json_decode(file_get_contents($defaultFile), true) ?: [])
        : [];

    $errors   = [];
    $custom   = [];

    foreach ($uploaded as $key => $value) {
        if ($key === '_readme') {
            continue;
        }
        if (!array_key_exists($key, $defaults)) {
            $errors[] = "Unknown key: \"$key\" — only keys from the default template are allowed.";
            continue;
        }
        if (!is_string($value)) {
            $errors[] = "Key \"$key\" must have a string value.";
            continue;
        }
        if (trim($value) === '') {
            $errors[] = "Key \"$key\" has an empty value — provide a non-empty string.";
            continue;
        }
        // Only store keys that differ from the default
        if ($value !== $defaults[$key]) {
            $custom[$key] = $value;
        }
    }

    if (!empty($errors)) {
        $_SESSION['label_upload_errors']  = $errors;
        $_SESSION['label_upload_preview'] = json_encode($uploaded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        redirect('/admin/settings/labels');
    }

    setSetting('custom_labels', json_encode($custom));
    redirect('/admin/settings/labels?saved=1');
});

$router->post('/admin/settings/labels/reset', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/labels');
    }
    setSetting('custom_labels', '{}');
    redirect('/admin/settings/labels?reset=1');
});

/* ==================================================================
 * ADMIN – Settings: Cron Jobs
 * ================================================================== */

$router->get('/admin/settings/cron-jobs', function () {
    Auth::requireRole('admin');
    render('admin/settings/cron-jobs');
});

/* ==================================================================
 * ADMIN – Settings: Automations
 * ================================================================== */

$router->get('/admin/settings/automations', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    $automations = $db->query('SELECT * FROM automations ORDER BY sort_order, id')->fetchAll();
    render('admin/automations/index', ['automations' => $automations]);
});

$router->get('/admin/settings/automations/create', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    $refData = loadAutomationRefData($db);
    render('admin/automations/form', $refData);
});

$router->post('/admin/settings/automations/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/automations/create');
    }

    $name         = trim($_POST['name'] ?? '');
    $triggerEvent = $_POST['trigger_event'] ?? '';
    $isEnabled    = !empty($_POST['is_enabled']) ? 1 : 0;
    $sortOrder    = (int) ($_POST['sort_order'] ?? 0);

    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Name is required.');
        redirect('/admin/settings/automations/create');
    }
    if (!in_array($triggerEvent, ['ticket_created', 'ticket_updated'], true)) {
        flashInput($_POST);
        flash('error', 'Invalid trigger event.');
        redirect('/admin/settings/automations/create');
    }

    $conditions = buildAutomationConditions($_POST);
    $actions    = buildAutomationActions($_POST);

    if (empty($actions)) {
        flashInput($_POST);
        flash('error', 'At least one action is required.');
        redirect('/admin/settings/automations/create');
    }

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO automations (name, trigger_event, conditions, actions, is_enabled, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$name, $triggerEvent, json_encode($conditions), json_encode($actions), $isEnabled, $sortOrder]);

    flash('success', 'Automation created.');
    redirect('/admin/settings/automations');
});

$router->get('/admin/settings/automations/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM automations WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Automation not found.');
        redirect('/admin/settings/automations');
    }
    $editing['conditions'] = json_decode($editing['conditions'], true) ?: [];
    $editing['actions']    = json_decode($editing['actions'], true) ?: [];

    $refData = loadAutomationRefData($db);
    $refData['editing'] = $editing;
    render('admin/automations/form', $refData);
});

$router->post('/admin/settings/automations/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/settings/automations/{$id}/edit");
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT id FROM automations WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        flash('error', 'Automation not found.');
        redirect('/admin/settings/automations');
    }

    $name         = trim($_POST['name'] ?? '');
    $triggerEvent = $_POST['trigger_event'] ?? '';
    $isEnabled    = !empty($_POST['is_enabled']) ? 1 : 0;
    $sortOrder    = (int) ($_POST['sort_order'] ?? 0);

    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Name is required.');
        redirect("/admin/settings/automations/{$id}/edit");
    }
    if (!in_array($triggerEvent, ['ticket_created', 'ticket_updated'], true)) {
        flashInput($_POST);
        flash('error', 'Invalid trigger event.');
        redirect("/admin/settings/automations/{$id}/edit");
    }

    $conditions = buildAutomationConditions($_POST);
    $actions    = buildAutomationActions($_POST);

    if (empty($actions)) {
        flashInput($_POST);
        flash('error', 'At least one action is required.');
        redirect("/admin/settings/automations/{$id}/edit");
    }

    $db->prepare(
        'UPDATE automations SET name = ?, trigger_event = ?, conditions = ?, actions = ?, is_enabled = ?, sort_order = ? WHERE id = ?'
    )->execute([$name, $triggerEvent, json_encode($conditions), json_encode($actions), $isEnabled, $sortOrder, $id]);

    flash('success', 'Automation updated.');
    redirect('/admin/settings/automations');
});

$router->post('/admin/settings/automations/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/automations');
    }
    $db = Database::connect();
    $db->prepare('DELETE FROM automations WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Automation deleted.');
    redirect('/admin/settings/automations');
});

$router->post('/admin/settings/automations/{id}/toggle', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/automations');
    }
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT is_enabled FROM automations WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Automation not found.');
        redirect('/admin/settings/automations');
    }
    $newState = $row['is_enabled'] ? 0 : 1;
    $db->prepare('UPDATE automations SET is_enabled = ? WHERE id = ?')->execute([$newState, (int) $p['id']]);
    flash('success', $newState ? 'Automation enabled.' : 'Automation disabled.');
    redirect('/admin/settings/automations');
});

/**
 * Load reference data for automation forms.
 */
function loadAutomationRefData(PDO $db): array
{
    return [
        'types'      => $db->query('SELECT id, name FROM ticket_types ORDER BY sort_order, name')->fetchAll(),
        'priorities' => $db->query('SELECT id, name FROM ticket_priorities ORDER BY sort_order')->fetchAll(),
        'locations'  => $db->query('SELECT id, name FROM locations ORDER BY name')->fetchAll(),
        'groups'     => $db->query('SELECT id, name FROM groups ORDER BY name')->fetchAll(),
        'agents'     => $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin') ORDER BY first_name")->fetchAll(),
        'allUsers'   => $db->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name")->fetchAll(),
    ];
}

/**
 * Build conditions array from POST data.
 */
function buildAutomationConditions(array $post): array
{
    $conditions = [];
    $fields    = $post['cond_field'] ?? [];
    $operators = $post['cond_operator'] ?? [];
    $values    = $post['cond_value'] ?? [];

    $validFields    = ['type_id', 'priority_id', 'status', 'location_id', 'group_id', 'assigned_to'];
    $validOperators = ['equals', 'not_equals', 'is_empty', 'is_not_empty'];

    for ($i = 0, $n = count($fields); $i < $n; $i++) {
        $f = $fields[$i] ?? '';
        $o = $operators[$i] ?? '';
        $v = $values[$i] ?? '';
        if (in_array($f, $validFields, true) && in_array($o, $validOperators, true)) {
            $conditions[] = ['field' => $f, 'operator' => $o, 'value' => $v];
        }
    }
    return $conditions;
}

/**
 * Build actions array from POST data.
 */
function buildAutomationActions(array $post): array
{
    $actions     = [];
    $actionTypes = $post['act_type'] ?? [];
    $actionVals  = $post['act_value'] ?? [];

    $validActions = ['set_group', 'set_assigned_to', 'set_priority', 'set_status', 'add_tag', 'add_cc'];

    for ($i = 0, $n = count($actionTypes); $i < $n; $i++) {
        $a = $actionTypes[$i] ?? '';
        $v = $actionVals[$i] ?? '';
        if (in_array($a, $validActions, true) && $v !== '') {
            $actions[] = ['action' => $a, 'value' => $v];
        }
    }
    return $actions;
}

/* ==================================================================
 * ADMIN – Settings: Escalation Rules
 * ================================================================== */

$router->get('/admin/settings/escalations', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    $rules = $db->query('SELECT * FROM escalation_rules ORDER BY sort_order, id')->fetchAll();
    render('admin/settings/escalations/index', ['rules' => $rules]);
});

$router->get('/admin/settings/escalations/create', function () {
    Auth::requireRole('admin');
    $db      = Database::connect();
    $refData = loadEscalationRefData($db);
    render('admin/settings/escalations/form', $refData);
});

$router->post('/admin/settings/escalations/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', 'Rule name is required.');
        redirect('/admin/settings/escalations/create');
    }

    $conditions    = buildEscalationConditions($_POST);
    $actions       = buildEscalationActions($_POST);
    $cooldown      = max(0, (int) ($_POST['cooldown_hours'] ?? 0));
    $isEnabled     = isset($_POST['is_enabled']) ? 1 : 0;
    $sortOrder     = max(0, (int) ($_POST['sort_order'] ?? 0));

    if (empty($actions)) {
        flash('error', 'At least one action is required.');
        redirect('/admin/settings/escalations/create');
    }

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO escalation_rules (name, conditions, actions, cooldown_hours, is_enabled, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$name, json_encode($conditions), json_encode($actions), $cooldown, $isEnabled, $sortOrder]);

    flash('success', 'Escalation rule created.');
    redirect('/admin/settings/escalations');
});

$router->get('/admin/settings/escalations/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM escalation_rules WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $rule = $stmt->fetch();
    if (!$rule) {
        flash('error', 'Rule not found.');
        redirect('/admin/settings/escalations');
    }
    $refData            = loadEscalationRefData($db);
    $refData['editing'] = $rule;
    $refData['editing']['conditions_decoded'] = json_decode($rule['conditions'], true) ?: [];
    $refData['editing']['actions_decoded']    = json_decode($rule['actions'],    true) ?: [];
    render('admin/settings/escalations/form', $refData);
});

$router->post('/admin/settings/escalations/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }

    $db = Database::connect();
    $stmt = $db->prepare('SELECT id FROM escalation_rules WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    if (!$stmt->fetch()) {
        flash('error', 'Rule not found.');
        redirect('/admin/settings/escalations');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', 'Rule name is required.');
        redirect('/admin/settings/escalations/' . (int) $p['id'] . '/edit');
    }

    $conditions = buildEscalationConditions($_POST);
    $actions    = buildEscalationActions($_POST);
    $cooldown   = max(0, (int) ($_POST['cooldown_hours'] ?? 0));
    $isEnabled  = isset($_POST['is_enabled']) ? 1 : 0;
    $sortOrder  = max(0, (int) ($_POST['sort_order'] ?? 0));

    if (empty($actions)) {
        flash('error', 'At least one action is required.');
        redirect('/admin/settings/escalations/' . (int) $p['id'] . '/edit');
    }

    $db->prepare(
        'UPDATE escalation_rules SET name = ?, conditions = ?, actions = ?, cooldown_hours = ?, is_enabled = ?, sort_order = ? WHERE id = ?'
    )->execute([$name, json_encode($conditions), json_encode($actions), $cooldown, $isEnabled, $sortOrder, (int) $p['id']]);

    flash('success', 'Escalation rule updated.');
    redirect('/admin/settings/escalations');
});

$router->post('/admin/settings/escalations/{id}/toggle', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }
    $db = Database::connect();
    $db->prepare('UPDATE escalation_rules SET is_enabled = NOT is_enabled WHERE id = ?')
       ->execute([(int) $p['id']]);
    redirect('/admin/settings/escalations');
});

$router->post('/admin/settings/escalations/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }
    $db = Database::connect();
    $db->prepare('DELETE FROM escalation_rules WHERE id = ?')->execute([(int) $p['id']]);
    flash('success', 'Escalation rule deleted.');
    redirect('/admin/settings/escalations');
});

$router->post('/admin/settings/escalations/run-now', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }
    $script = ROOT_DIR . '/scripts/process-escalations.php';
    $cmd    = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1';
    $outputLines = [];
    $returnCode  = 0;
    exec($cmd, $outputLines, $returnCode);
    $_SESSION['_escalation_run'] = [
        'lines' => $outputLines,
        'code'  => $returnCode,
        'time'  => date('Y-m-d H:i:s'),
    ];
    redirect('/admin/settings/escalations');
});

/* ── Escalation helper functions ─────────────────────────────────── */

function loadEscalationRefData(PDO $db): array
{
    return [
        'priorities' => $db->query('SELECT id, name FROM ticket_priorities ORDER BY sort_order')->fetchAll(),
        'groups'     => $db->query('SELECT id, name FROM groups ORDER BY name')->fetchAll(),
        'agents'     => $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin') ORDER BY first_name")->fetchAll(),
        'allUsers'   => $db->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name")->fetchAll(),
    ];
}

function buildEscalationConditions(array $post): array
{
    $conditions = [];
    $fields     = $post['cond_field']    ?? [];
    $operators  = $post['cond_operator'] ?? [];
    $values     = $post['cond_value']    ?? [];

    $validFields = ['sla_state', 'hours_open', 'hours_since_update', 'hours_in_status',
                    'is_assigned', 'priority_id', 'status', 'group_id'];
    $noValueOps  = ['is_empty', 'is_not_empty'];

    for ($i = 0, $n = count($fields); $i < $n; $i++) {
        $f = $fields[$i]    ?? '';
        $o = $operators[$i] ?? 'equals';
        $v = $values[$i]    ?? '';
        if (!in_array($f, $validFields, true)) continue;
        if (!in_array($o, $noValueOps, true) && $v === '') continue;
        $conditions[] = ['field' => $f, 'operator' => $o, 'value' => $v];
    }
    return $conditions;
}

function buildEscalationActions(array $post): array
{
    $actions     = [];
    $actionTypes = $post['act_type']  ?? [];
    $actionVals  = $post['act_value'] ?? [];

    $validActions    = ['set_priority', 'set_assigned_to', 'set_group', 'set_status',
                        'notify_user', 'notify_assigned_agent', 'notify_ticket_creator', 'add_internal_note'];
    $noValueRequired = ['notify_assigned_agent', 'notify_ticket_creator'];

    for ($i = 0, $n = count($actionTypes); $i < $n; $i++) {
        $a = $actionTypes[$i] ?? '';
        $v = $actionVals[$i]  ?? '';
        if (!in_array($a, $validActions, true)) continue;
        if (!in_array($a, $noValueRequired, true) && $v === '') continue;
        $actions[] = ['action' => $a, 'value' => $v];
    }
    return $actions;
}

/* ==================================================================
 * ADMIN – Settings: Danger Zone
 * ================================================================== */
$router->get('/admin/settings/danger-zone', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    $ticketCount = (int) $db->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
    render('admin/settings/danger-zone', ['ticketCount' => $ticketCount]);
});

$router->post('/admin/settings/danger-zone/reset', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/danger-zone');
    }

    $db = Database::connect();

    // Truncate all data tables in safe order (disable FK checks first)
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    $tables = [
        'escalation_log',
        'csat_surveys',
        'audit_log',
        'notifications',
        'ticket_field_values',
        'ticket_form_field_options',
        'ticket_form_fields',
        'ticket_tag_map',
        'ticket_tags',
        'ticket_attachments',
        'ticket_cc',
        'ticket_presence',
        'ticket_timeline',
        'tickets',
        'ticket_templates',
        'saved_filters',
        'group_user_map',
        'groups',
        'kb_article_ratings',
        'kb_article_revisions',
        'kb_articles',
        'kb_folders',
        'kb_categories',
        'escalation_rules',
        'scheduled_reports',
        'automations',
        'sla_policies',
        'ticket_priorities',
        'ticket_types',
        'locations',
        'settings',
        'users',
    ];

    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE `{$table}`");
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Destroy the current session so the admin is logged out
    session_unset();
    session_destroy();

    // Start a fresh session and flag that setup is needed
    session_start();
    $_SESSION['setup_allowed'] = true;

    redirect('/setup');
});

/* ==================================================================
 * Reports & Analytics
 * ================================================================== */

/** Helper: format minutes into human-readable string */
function formatMinutes(float $minutes): string
{
    if ($minutes < 1) return '< 1m';
    if ($minutes < 60) return round($minutes) . 'm';
    if ($minutes < 1440) return round($minutes / 60, 1) . 'h';
    return round($minutes / 1440, 1) . 'd';
}

/** Helper: parse date-range query params with defaults */
function reportDateRange(): array
{
    $to   = (!empty($_GET['to']) && strtotime($_GET['to'])) ? $_GET['to'] : date('Y-m-d');
    $from = (!empty($_GET['from']) && strtotime($_GET['from'])) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days', strtotime($to)));
    return [$from, $to];
}

/* ── Reports Overview ─────────────────────────────────────────────── */

$router->get('/admin/reports', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $stmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_at BETWEEN ? AND ?');
    $stmt->execute([$from, $toEnd]);
    $ticketsCreated = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status IN ('resolved','closed') AND created_at BETWEEN ? AND ?");
    $stmt->execute([$from, $toEnd]);
    $ticketsResolved = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('resolved','closed')");
    $stmt->execute();
    $unresolvedCount = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_responded_at)) FROM tickets WHERE first_responded_at IS NOT NULL AND created_at BETWEEN ? AND ?');
    $stmt->execute([$from, $toEnd]);
    $avgFirstResponse = formatMinutes((float) $stmt->fetchColumn());

    // Avg resolution: time from creation to the status_changed → resolved timeline entry
    $stmt = $db->prepare(
        "SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at))
         FROM tickets t
         JOIN ticket_timeline tl ON tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
         WHERE t.created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $avgResolution = formatMinutes((float) $stmt->fetchColumn());

    // SLA compliance
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM tickets WHERE first_response_due_at IS NOT NULL AND created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $slaTotal = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM tickets WHERE sla_state = 'breached' AND created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $slaBreached = (int) $stmt->fetchColumn();
    $slaCompliance = $slaTotal > 0 ? round(($slaTotal - $slaBreached) / $slaTotal * 100) : 100;

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status NOT IN ('resolved','closed')");
    $stmt->execute();
    $unassignedCount = (int) $stmt->fetchColumn();

    render('admin/reports/index', compact(
        'from', 'to', 'ticketsCreated', 'ticketsResolved', 'unresolvedCount',
        'avgFirstResponse', 'avgResolution', 'slaCompliance', 'slaBreached', 'unassignedCount'
    ));
});

/* ── Agent Performance ────────────────────────────────────────────── */

$router->get('/admin/reports/agent-performance', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $stmt = $db->prepare(
        "SELECT
            u.id AS agent_id,
            CONCAT(u.first_name, ' ', u.last_name) AS agent_name,
            COUNT(t.id) AS assigned,
            SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved,
            SUM(CASE WHEN t.status NOT IN ('resolved','closed') THEN 1 ELSE 0 END) AS open_count,
            AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END) AS avg_first_response_min,
            AVG(
                CASE WHEN t.status IN ('resolved','closed') THEN
                    (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                     FROM ticket_timeline tl
                     WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                     ORDER BY tl.created_at DESC LIMIT 1)
                END
            ) AS avg_resolution_min,
            SUM(CASE WHEN t.first_response_due_at IS NOT NULL THEN 1 ELSE 0 END) AS sla_total,
            SUM(CASE WHEN t.sla_state = 'breached' THEN 1 ELSE 0 END) AS sla_breached
         FROM users u
         LEFT JOIN tickets t ON t.assigned_to = u.id AND t.created_at BETWEEN ? AND ?
         WHERE u.role IN ('admin','agent')
         GROUP BY u.id, u.first_name, u.last_name
         ORDER BY resolved DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $agents = $stmt->fetchAll();

    foreach ($agents as &$a) {
        $a['avg_first_response'] = $a['avg_first_response_min'] !== null ? formatMinutes((float) $a['avg_first_response_min']) : '—';
        $a['avg_resolution'] = $a['avg_resolution_min'] !== null ? formatMinutes((float) $a['avg_resolution_min']) : '—';
        $a['sla_compliance'] = $a['sla_total'] > 0
            ? round(($a['sla_total'] - $a['sla_breached']) / $a['sla_total'] * 100)
            : 100;
    }
    unset($a);

    render('admin/reports/agent-performance', compact('from', 'to', 'agents'));
});

/* ── Response Times ───────────────────────────────────────────────── */

$router->get('/admin/reports/response-times', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Overall averages
    $stmt = $db->prepare(
        'SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_responded_at)) FROM tickets WHERE first_responded_at IS NOT NULL AND created_at BETWEEN ? AND ?'
    );
    $stmt->execute([$from, $toEnd]);
    $overallFirstResponse = formatMinutes((float) $stmt->fetchColumn());

    $stmt = $db->prepare(
        "SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at))
         FROM tickets t
         JOIN ticket_timeline tl ON tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
         WHERE t.created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $overallResolution = formatMinutes((float) $stmt->fetchColumn());

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE first_responded_at IS NOT NULL AND created_at BETWEEN ? AND ?");
    $stmt->execute([$from, $toEnd]);
    $ticketsMeasured = (int) $stmt->fetchColumn();

    // By priority
    $stmt = $db->prepare(
        "SELECT
            tp.name AS priority_name, tp.color AS priority_color,
            COUNT(t.id) AS ticket_count,
            AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END) AS avg_fr_min,
            AVG(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at DESC LIMIT 1)
            ) AS avg_res_min,
            MIN(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at ASC LIMIT 1)
            ) AS fastest_min,
            MAX(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at DESC LIMIT 1)
            ) AS slowest_min
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY tp.id, tp.name, tp.color, tp.sort_order
         ORDER BY tp.sort_order"
    );
    $stmt->execute([$from, $toEnd]);
    $byPriority = $stmt->fetchAll();

    foreach ($byPriority as &$row) {
        $row['avg_first_response'] = $row['avg_fr_min'] !== null ? formatMinutes((float) $row['avg_fr_min']) : '—';
        $row['avg_resolution'] = $row['avg_res_min'] !== null ? formatMinutes((float) $row['avg_res_min']) : '—';
        $row['fastest'] = $row['fastest_min'] !== null ? formatMinutes((float) $row['fastest_min']) : '—';
        $row['slowest'] = $row['slowest_min'] !== null ? formatMinutes((float) $row['slowest_min']) : '—';
    }
    unset($row);

    // Weekly trend
    $stmt = $db->prepare(
        "SELECT
            DATE_FORMAT(t.created_at, '%Y-%u') AS week_key,
            DATE_FORMAT(MIN(t.created_at), '%b %e') AS week_label,
            ROUND(AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END) / 60, 1) AS avg_response_hrs,
            ROUND(AVG(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at DESC LIMIT 1)
            ) / 60, 1) AS avg_resolution_hrs
         FROM tickets t
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY week_key
         ORDER BY week_key"
    );
    $stmt->execute([$from, $toEnd]);
    $weeklyTrend = $stmt->fetchAll();

    render('admin/reports/response-times', compact(
        'from', 'to', 'overallFirstResponse', 'overallResolution', 'ticketsMeasured',
        'byPriority', 'weeklyTrend'
    ));
});

/* ── SLA Compliance ───────────────────────────────────────────────── */

$router->get('/admin/reports/sla', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Overall compliance
    $stmt = $db->prepare(
        "SELECT
            SUM(CASE WHEN first_responded_at IS NOT NULL AND first_response_due_at IS NOT NULL AND first_responded_at <= first_response_due_at THEN 1 ELSE 0 END) AS response_met,
            SUM(CASE WHEN first_responded_at IS NOT NULL AND first_response_due_at IS NOT NULL AND first_responded_at > first_response_due_at THEN 1 ELSE 0 END) AS response_breached,
            SUM(CASE WHEN first_response_due_at IS NOT NULL AND (first_responded_at IS NULL OR first_responded_at <= first_response_due_at) AND sla_state != 'breached' THEN 1 ELSE 0 END) AS resolution_met_approx,
            SUM(CASE WHEN sla_state = 'breached' THEN 1 ELSE 0 END) AS sla_breached_count,
            COUNT(CASE WHEN first_response_due_at IS NOT NULL THEN 1 END) AS sla_total
         FROM tickets
         WHERE created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $overall = $stmt->fetch();

    $responseMet     = (int) ($overall['response_met'] ?? 0);
    $responseBreached = (int) ($overall['response_breached'] ?? 0);
    $totalBreached    = (int) ($overall['sla_breached_count'] ?? 0);
    $slaTotal         = (int) ($overall['sla_total'] ?? 0);
    $totalMet         = max(0, $slaTotal - $totalBreached);

    $firstResponseCompliance = ($responseMet + $responseBreached) > 0
        ? round($responseMet / ($responseMet + $responseBreached) * 100) : 100;

    $resolutionCompliance = $slaTotal > 0
        ? round($totalMet / $slaTotal * 100) : 100;

    $overallCompliance = $slaTotal > 0
        ? round($totalMet / $slaTotal * 100) : 100;

    // By priority
    $stmt = $db->prepare(
        "SELECT
            tp.name AS priority_name, tp.color AS priority_color,
            COUNT(t.id) AS total,
            SUM(CASE WHEN t.first_responded_at IS NOT NULL AND t.first_response_due_at IS NOT NULL AND t.first_responded_at <= t.first_response_due_at THEN 1 ELSE 0 END) AS response_met,
            SUM(CASE WHEN t.first_responded_at IS NOT NULL AND t.first_response_due_at IS NOT NULL AND t.first_responded_at > t.first_response_due_at THEN 1 ELSE 0 END) AS response_breached,
            SUM(CASE WHEN t.sla_state != 'breached' AND t.first_response_due_at IS NOT NULL THEN 1 ELSE 0 END) AS resolution_met,
            SUM(CASE WHEN t.sla_state = 'breached' THEN 1 ELSE 0 END) AS resolution_breached
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         WHERE t.first_response_due_at IS NOT NULL AND t.created_at BETWEEN ? AND ?
         GROUP BY tp.id, tp.name, tp.color, tp.sort_order
         ORDER BY tp.sort_order"
    );
    $stmt->execute([$from, $toEnd]);
    $byPriority = $stmt->fetchAll();

    foreach ($byPriority as &$row) {
        $row['compliance'] = $row['total'] > 0
            ? round(($row['total'] - $row['resolution_breached']) / $row['total'] * 100) : 100;
    }
    unset($row);

    // Breached tickets
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.created_at, t.sla_state,
                t.first_responded_at, t.first_response_due_at,
                tp.name AS priority_name, tp.color AS priority_color,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN users a ON t.assigned_to = a.id
         WHERE t.sla_state = 'breached' AND t.created_at BETWEEN ? AND ?
         ORDER BY t.created_at DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $breachedTickets = $stmt->fetchAll();

    foreach ($breachedTickets as &$bt) {
        $bt['response_breached'] = ($bt['first_responded_at'] && $bt['first_response_due_at'] && $bt['first_responded_at'] > $bt['first_response_due_at']);
        $bt['resolution_breached'] = ($bt['sla_state'] === 'breached');
    }
    unset($bt);

    render('admin/reports/sla', compact(
        'from', 'to', 'overallCompliance', 'firstResponseCompliance', 'resolutionCompliance',
        'totalBreached', 'totalMet', 'byPriority', 'breachedTickets'
    ));
});

/* ── Unresolved Tickets ───────────────────────────────────────────── */

$router->get('/admin/reports/unresolved', function () {
    Auth::requireRole('admin');
    $db = Database::connect();

    // No date filter for unresolved — it's current state
    $stmt = $db->prepare(
        "SELECT t.*, tp.name AS priority_name, tp.color AS priority_color,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN users a ON t.assigned_to = a.id
         WHERE t.status NOT IN ('resolved','closed')
         ORDER BY t.created_at ASC"
    );
    $stmt->execute();
    $tickets = $stmt->fetchAll();

    $totalUnresolved = count($tickets);
    $unassigned = 0;
    $breachedCount = 0;
    $totalAgeMin = 0;
    $agingBuckets = [0, 0, 0, 0, 0]; // <1d, 1-3d, 3-7d, 7-14d, >14d
    $byStatus = [];

    $now = new DateTime();
    foreach ($tickets as &$t) {
        if (empty($t['assigned_to'])) $unassigned++;
        if ($t['sla_state'] === 'breached') $breachedCount++;

        $created = new DateTime($t['created_at']);
        $diffMin = ($now->getTimestamp() - $created->getTimestamp()) / 60;
        $totalAgeMin += $diffMin;
        $t['age_display'] = formatMinutes($diffMin);

        $days = $diffMin / 1440;
        if ($days < 1) $agingBuckets[0]++;
        elseif ($days < 3) $agingBuckets[1]++;
        elseif ($days < 7) $agingBuckets[2]++;
        elseif ($days < 14) $agingBuckets[3]++;
        else $agingBuckets[4]++;

        $byStatus[$t['status']] = ($byStatus[$t['status']] ?? 0) + 1;
    }
    unset($t);

    $avgAge = $totalUnresolved > 0 ? formatMinutes($totalAgeMin / $totalUnresolved) : '—';

    $byStatusArr = [];
    foreach ($byStatus as $status => $count) {
        $byStatusArr[] = ['status' => $status, 'count' => $count];
    }
    $byStatus = $byStatusArr;

    render('admin/reports/unresolved', compact(
        'tickets', 'totalUnresolved', 'unassigned', 'breachedCount', 'avgAge',
        'agingBuckets', 'byStatus'
    ));
});

/* ── Ticket Volume ────────────────────────────────────────────────── */

$router->get('/admin/reports/ticket-volume', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Daily volume
    $stmt = $db->prepare(
        "SELECT DATE(created_at) AS date_label, COUNT(*) AS count
         FROM tickets
         WHERE created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY DATE(created_at)"
    );
    $stmt->execute([$from, $toEnd]);
    $dailyVolume = $stmt->fetchAll();

    // Format date labels
    foreach ($dailyVolume as &$row) {
        $row['date_label'] = date('M j', strtotime($row['date_label']));
    }
    unset($row);

    // By priority
    $stmt = $db->prepare(
        "SELECT tp.name, tp.color, COUNT(t.id) AS count
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY tp.id, tp.name, tp.color, tp.sort_order
         ORDER BY tp.sort_order"
    );
    $stmt->execute([$from, $toEnd]);
    $byPriority = $stmt->fetchAll();

    // By type
    $stmt = $db->prepare(
        "SELECT COALESCE(tt.name, 'Untyped') AS name, COUNT(t.id) AS count
         FROM tickets t
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY tt.id, tt.name
         ORDER BY count DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $byType = $stmt->fetchAll();

    // By location
    $stmt = $db->prepare(
        "SELECT COALESCE(l.name, 'No Location') AS name, COUNT(t.id) AS count
         FROM tickets t
         LEFT JOIN locations l ON t.location_id = l.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY l.id, l.name
         ORDER BY count DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $byLocation = $stmt->fetchAll();

    render('admin/reports/ticket-volume', compact(
        'from', 'to', 'dailyVolume', 'byPriority', 'byType', 'byLocation'
    ));
});

/* ── Ticket Lifecycle ─────────────────────────────────────────────── */

$router->get('/admin/reports/lifecycle', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Get status transitions from timeline
    $stmt = $db->prepare(
        "SELECT tl.ticket_id, tl.details, tl.created_at
         FROM ticket_timeline tl
         JOIN tickets t ON t.id = tl.ticket_id
         WHERE tl.action = 'status_changed' AND t.created_at BETWEEN ? AND ?
         ORDER BY tl.ticket_id, tl.created_at"
    );
    $stmt->execute([$from, $toEnd]);
    $changes = $stmt->fetchAll();

    // Also get ticket creation times
    $stmt = $db->prepare(
        "SELECT id, created_at, status FROM tickets WHERE created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $ticketRows = $stmt->fetchAll();

    $ticketCreated = [];
    foreach ($ticketRows as $tr) {
        $ticketCreated[$tr['id']] = $tr['created_at'];
    }

    // Build status duration map per ticket
    $statusTimes = []; // status => [minutes, ...]
    $transitionCounts = []; // "from→to" => [count, total_minutes]

    // Group changes by ticket
    $byTicket = [];
    foreach ($changes as $c) {
        $byTicket[$c['ticket_id']][] = $c;
    }

    foreach ($byTicket as $ticketId => $ticketChanges) {
        // Start from 'open' at created_at
        $prevStatus = 'open';
        $prevTime = $ticketCreated[$ticketId] ?? null;
        if (!$prevTime) continue;

        foreach ($ticketChanges as $change) {
            // Parse "Status → New Status" from details
            if (preg_match('/^(.+?)\s*→\s*(.+)$/', $change['details'] ?? '', $m)) {
                $toStatus = strtolower(trim($m[2]));
                $toStatus = str_replace(' ', '_', $toStatus);

                $minutes = (strtotime($change['created_at']) - strtotime($prevTime)) / 60;
                if ($minutes > 0) {
                    $statusTimes[$prevStatus][] = $minutes;

                    $key = $prevStatus . '→' . $toStatus;
                    if (!isset($transitionCounts[$key])) {
                        $transitionCounts[$key] = ['count' => 0, 'total_min' => 0];
                    }
                    $transitionCounts[$key]['count']++;
                    $transitionCounts[$key]['total_min'] += $minutes;
                }

                $prevStatus = $toStatus;
                $prevTime = $change['created_at'];
            }
        }
    }

    // Build statusDurations array
    $statusOrder = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
    $statusDurations = [];
    foreach ($statusOrder as $status) {
        $times = $statusTimes[$status] ?? [];
        $avg = count($times) > 0 ? array_sum($times) / count($times) : 0;
        $statusDurations[] = [
            'status' => $status,
            'avg_duration' => count($times) > 0 ? formatMinutes($avg) : '—',
            'avg_hours' => round($avg / 60, 1),
            'transitions' => count($times),
        ];
    }

    // Build transitions array
    $transitions = [];
    arsort($transitionCounts);
    foreach ($transitionCounts as $key => $data) {
        [$fromS, $toS] = explode('→', $key);
        $transitions[] = [
            'from_status' => $fromS,
            'to_status' => $toS,
            'count' => $data['count'],
            'avg_duration' => formatMinutes($data['total_min'] / $data['count']),
        ];
    }

    // By priority: avg to first response and resolution
    $stmt = $db->prepare(
        "SELECT
            tp.name AS priority_name, tp.color AS priority_color,
            AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END) AS avg_fr_min,
            AVG(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at DESC LIMIT 1)
            ) AS avg_res_min
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY tp.id, tp.name, tp.color, tp.sort_order
         ORDER BY tp.sort_order"
    );
    $stmt->execute([$from, $toEnd]);
    $byPriority = $stmt->fetchAll();

    foreach ($byPriority as &$row) {
        $row['avg_to_first_response'] = $row['avg_fr_min'] !== null ? formatMinutes((float) $row['avg_fr_min']) : '—';
        $row['avg_to_resolution'] = $row['avg_res_min'] !== null ? formatMinutes((float) $row['avg_res_min']) : '—';
    }
    unset($row);

    render('admin/reports/lifecycle', compact(
        'from', 'to', 'statusDurations', 'transitions', 'byPriority'
    ));
});

/* ── Location Report ──────────────────────────────────────────────── */

$router->get('/admin/reports/location', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $stmt = $db->prepare(
        "SELECT
            COALESCE(l.name, 'No Location') AS location_name,
            COUNT(t.id) AS total,
            SUM(CASE WHEN t.status NOT IN ('resolved','closed') THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved,
            AVG(
                CASE WHEN t.status IN ('resolved','closed') THEN
                    (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                     FROM ticket_timeline tl
                     WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                     ORDER BY tl.created_at DESC LIMIT 1)
                END
            ) AS avg_res_min,
            SUM(CASE WHEN t.first_response_due_at IS NOT NULL THEN 1 ELSE 0 END) AS sla_total,
            SUM(CASE WHEN t.sla_state = 'breached' THEN 1 ELSE 0 END) AS sla_breached
         FROM tickets t
         LEFT JOIN locations l ON t.location_id = l.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY l.id, l.name
         ORDER BY total DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $locations = $stmt->fetchAll();

    foreach ($locations as &$loc) {
        $loc['resolution_rate'] = $loc['total'] > 0
            ? round($loc['resolved'] / $loc['total'] * 100) : 0;
        $loc['avg_resolution'] = $loc['avg_res_min'] !== null
            ? formatMinutes((float) $loc['avg_res_min']) : '—';
        $loc['sla_compliance'] = $loc['sla_total'] > 0
            ? round(($loc['sla_total'] - $loc['sla_breached']) / $loc['sla_total'] * 100) : 100;
    }
    unset($loc);

    render('admin/reports/location', compact('from', 'to', 'locations'));
});

/* ── CSAT Satisfaction Report ─────────────────────────────────────── */

$router->get('/admin/reports/csat', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $stmt = $db->prepare('SELECT COUNT(*) FROM csat_surveys WHERE sent_at BETWEEN ? AND ?');
    $stmt->execute([$from, $toEnd]);
    $totalSent = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM csat_surveys WHERE sent_at BETWEEN ? AND ? AND responded_at IS NOT NULL');
    $stmt->execute([$from, $toEnd]);
    $totalResponded = (int) $stmt->fetchColumn();

    $responseRate = $totalSent > 0 ? round($totalResponded / $totalSent * 100) : 0;

    $stmt = $db->prepare('SELECT AVG(rating) FROM csat_surveys WHERE sent_at BETWEEN ? AND ? AND rating IS NOT NULL');
    $stmt->execute([$from, $toEnd]);
    $avgRating = round((float) $stmt->fetchColumn(), 1);

    // Distribution: count per star rating
    $stmt = $db->prepare(
        'SELECT rating, COUNT(*) AS cnt
         FROM csat_surveys
         WHERE sent_at BETWEEN ? AND ? AND rating IS NOT NULL
         GROUP BY rating ORDER BY rating'
    );
    $stmt->execute([$from, $toEnd]);
    $rawDist = $stmt->fetchAll();
    $distribution = array_fill(1, 5, 0);
    foreach ($rawDist as $row) {
        $distribution[(int) $row['rating']] = (int) $row['cnt'];
    }

    // Responses table
    $stmt = $db->prepare(
        "SELECT cs.rating, cs.comment, cs.responded_at,
                cs.ticket_id, t.subject,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM csat_surveys cs
         JOIN tickets t ON cs.ticket_id = t.id
         JOIN users u   ON cs.user_id   = u.id
         WHERE cs.sent_at BETWEEN ? AND ? AND cs.responded_at IS NOT NULL
         ORDER BY cs.responded_at DESC
         LIMIT 200"
    );
    $stmt->execute([$from, $toEnd]);
    $responses = $stmt->fetchAll();

    render('admin/reports/csat', compact(
        'from', 'to', 'totalSent', 'totalResponded', 'responseRate',
        'avgRating', 'distribution', 'responses'
    ));
});

/* ==================================================================
 * ADMIN – CSAT Settings
 * ================================================================== */

$router->get('/admin/settings/csat', function () {
    Auth::requireRole('admin');
    $settings = [
        'csat_enabled'        => getSetting('csat_enabled', '0'),
        'csat_trigger_status' => getSetting('csat_trigger_status', 'resolved'),
    ];
    render('admin/settings/csat', compact('settings'));
});

$router->post('/admin/settings/csat', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/csat');
    }

    setSetting('csat_enabled', isset($_POST['csat_enabled']) ? '1' : '0');
    $trigger = in_array($_POST['csat_trigger_status'] ?? '', ['resolved', 'closed'], true)
        ? $_POST['csat_trigger_status'] : 'resolved';
    setSetting('csat_trigger_status', $trigger);

    flash('success', 'CSAT settings saved.');
    redirect('/admin/settings/csat');
});

/* ==================================================================
 * ADMIN – Report: Agent Workload Heatmap
 * ================================================================== */

$router->get('/admin/reports/workload', function () {
    Auth::requireRole('admin');
    $db = Database::connect();

    $stmt = $db->query(
        "SELECT COALESCE(CONCAT(u.first_name,' ',u.last_name),'Unassigned') AS agent_name,
                u.id AS agent_id,
                COUNT(t.id) AS open_total,
                SUM(CASE WHEN t.sla_state='breached' THEN 1 ELSE 0 END) AS breached_count,
                SUM(CASE WHEN t.status='open' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN t.status='in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN t.status='pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN t.status IN ('waiting_on_customer','waiting_on_third_party') THEN 1 ELSE 0 END) AS waiting_count
         FROM tickets t
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE t.status NOT IN ('resolved','closed')
         GROUP BY u.id, u.first_name, u.last_name
         ORDER BY open_total DESC"
    );
    $agents  = $stmt->fetchAll();
    $maxLoad = !empty($agents) ? (int) $agents[0]['open_total'] : 1;

    render('admin/reports/workload', compact('agents', 'maxLoad'));
});

/* ==================================================================
 * ADMIN – Report: Ticket Trends
 * ================================================================== */

$router->get('/admin/reports/trends', function () {
    Auth::requireRole('admin');
    $db      = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd   = $to . ' 23:59:59';
    $groupBy = in_array($_GET['group_by'] ?? '', ['type', 'location'], true)
        ? $_GET['group_by'] : 'type';

    if ($groupBy === 'type') {
        $stmt = $db->prepare(
            "SELECT DATE(t.created_at) AS day,
                    COALESCE(tt.name,'Untyped') AS segment,
                    COUNT(t.id) AS cnt
             FROM tickets t
             LEFT JOIN ticket_types tt ON t.type_id = tt.id
             WHERE t.created_at BETWEEN ? AND ?
             GROUP BY day, segment
             ORDER BY day, segment"
        );
    } else {
        $stmt = $db->prepare(
            "SELECT DATE(t.created_at) AS day,
                    COALESCE(l.name,'No Location') AS segment,
                    COUNT(t.id) AS cnt
             FROM tickets t
             LEFT JOIN locations l ON t.location_id = l.id
             WHERE t.created_at BETWEEN ? AND ?
             GROUP BY day, segment
             ORDER BY day, segment"
        );
    }
    $stmt->execute([$from, $toEnd]);
    $rows = $stmt->fetchAll();

    // Pivot: labels (dates) + datasets (one per segment)
    $labelSet   = [];
    $segmentSet = [];
    $matrix     = [];
    foreach ($rows as $row) {
        $d = date('M j', strtotime($row['day']));
        $labelSet[$d]               = true;
        $segmentSet[$row['segment']] = true;
        $matrix[$d][$row['segment']] = (int) $row['cnt'];
    }
    $labels   = array_keys($labelSet);
    $segments = array_keys($segmentSet);

    $palette = ['#4f46e5','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#84cc16','#f97316'];
    $datasets = [];
    foreach ($segments as $i => $seg) {
        $data = [];
        foreach ($labels as $lbl) {
            $data[] = $matrix[$lbl][$seg] ?? 0;
        }
        $datasets[] = [
            'label'           => $seg,
            'data'            => $data,
            'borderColor'     => $palette[$i % count($palette)],
            'backgroundColor' => $palette[$i % count($palette)] . '22',
            'fill'            => false,
            'tension'         => 0.3,
        ];
    }

    render('admin/reports/trends', compact('from', 'to', 'groupBy', 'labels', 'datasets'));
});

/* ==================================================================
 * ADMIN – Report: First Contact Resolution (FCR)
 * ================================================================== */

$router->get('/admin/reports/fcr', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Overall FCR
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total_resolved,
                SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
         FROM (
             SELECT t.id,
                 (SELECT COUNT(*) FROM ticket_timeline tl
                  WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
             FROM tickets t
             WHERE t.status IN ('resolved','closed')
               AND t.created_at BETWEEN ? AND ?
         ) sub"
    );
    $stmt->execute([$from, $toEnd]);
    $overall = $stmt->fetch();
    $overallFcr = [
        'total'   => (int) $overall['total_resolved'],
        'fcr'     => (int) $overall['fcr_count'],
        'pct'     => $overall['total_resolved'] > 0
            ? round($overall['fcr_count'] / $overall['total_resolved'] * 100)
            : 0,
    ];

    // FCR by agent
    $stmt = $db->prepare(
        "SELECT CONCAT(u.first_name,' ',u.last_name) AS agent_name,
                COUNT(*) AS total_resolved,
                SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
         FROM (
             SELECT t.id, t.assigned_to,
                 (SELECT COUNT(*) FROM ticket_timeline tl
                  WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
             FROM tickets t
             WHERE t.status IN ('resolved','closed')
               AND t.created_at BETWEEN ? AND ?
         ) sub
         LEFT JOIN users u ON sub.assigned_to = u.id
         GROUP BY sub.assigned_to, u.first_name, u.last_name
         ORDER BY total_resolved DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $fcrByAgent = $stmt->fetchAll();
    foreach ($fcrByAgent as &$a) {
        $a['agent_name'] = $a['agent_name'] ?? 'Unassigned';
        $a['fcr_pct']    = $a['total_resolved'] > 0
            ? round($a['fcr_count'] / $a['total_resolved'] * 100) : 0;
    }
    unset($a);

    // FCR by type
    $stmt = $db->prepare(
        "SELECT COALESCE(tt.name,'Untyped') AS type_name,
                COUNT(*) AS total_resolved,
                SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
         FROM (
             SELECT t.id, t.type_id,
                 (SELECT COUNT(*) FROM ticket_timeline tl
                  WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
             FROM tickets t
             WHERE t.status IN ('resolved','closed')
               AND t.created_at BETWEEN ? AND ?
         ) sub
         LEFT JOIN ticket_types tt ON sub.type_id = tt.id
         GROUP BY sub.type_id, tt.name
         ORDER BY total_resolved DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $fcrByType = $stmt->fetchAll();
    foreach ($fcrByType as &$r) {
        $r['fcr_pct'] = $r['total_resolved'] > 0
            ? round($r['fcr_count'] / $r['total_resolved'] * 100) : 0;
    }
    unset($r);

    // Weekly trend
    $stmt = $db->prepare(
        "SELECT DATE_FORMAT(sub.created_at,'%Y-%u') AS week_key,
                DATE_FORMAT(MIN(sub.created_at),'%b %e') AS week_label,
                COUNT(*) AS total_resolved,
                SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
         FROM (
             SELECT t.id, t.created_at,
                 (SELECT COUNT(*) FROM ticket_timeline tl
                  WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
             FROM tickets t
             WHERE t.status IN ('resolved','closed')
               AND t.created_at BETWEEN ? AND ?
         ) sub
         GROUP BY week_key
         ORDER BY week_key"
    );
    $stmt->execute([$from, $toEnd]);
    $weeklyFcr = $stmt->fetchAll();
    foreach ($weeklyFcr as &$w) {
        $w['fcr_pct'] = $w['total_resolved'] > 0
            ? round($w['fcr_count'] / $w['total_resolved'] * 100) : 0;
    }
    unset($w);

    render('admin/reports/fcr', compact('from', 'to', 'overallFcr', 'fcrByAgent', 'fcrByType', 'weeklyFcr'));
});

/* ==================================================================
 * ADMIN – Report: Custom Report Builder
 * ================================================================== */

$router->get('/admin/reports/custom', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $metricOptions = [
        'ticket_count'       => 'Ticket Count',
        'avg_first_response' => 'Avg First Response Time',
        'avg_resolution'     => 'Avg Resolution Time',
        'sla_compliance'     => 'SLA Compliance %',
    ];
    $groupByOptions = [
        'agent'    => 'Agent',
        'type'     => 'Ticket Type',
        'location' => 'Location',
        'priority' => 'Priority',
        'status'   => 'Status',
    ];

    $metric  = array_key_exists($_GET['metric'] ?? '', $metricOptions)  ? $_GET['metric']   : null;
    $groupBy = array_key_exists($_GET['group_by'] ?? '', $groupByOptions) ? $_GET['group_by'] : null;
    $rows    = [];
    $metricLabel = $metric ? $metricOptions[$metric] : '';

    if ($metric && $groupBy) {
        // Group-by label and JOIN expressions (whitelisted)
        $groupConfig = [
            'agent'    => ['label' => "COALESCE(CONCAT(u.first_name,' ',u.last_name),'Unassigned')",
                           'join'  => "LEFT JOIN users u ON t.assigned_to = u.id",
                           'group' => 'u.id, u.first_name, u.last_name'],
            'type'     => ['label' => "COALESCE(tt.name,'Untyped')",
                           'join'  => "LEFT JOIN ticket_types tt ON t.type_id = tt.id",
                           'group' => 'tt.id, tt.name'],
            'location' => ['label' => "COALESCE(l.name,'No Location')",
                           'join'  => "LEFT JOIN locations l ON t.location_id = l.id",
                           'group' => 'l.id, l.name'],
            'priority' => ['label' => "COALESCE(tp.name,'None')",
                           'join'  => "LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id",
                           'group' => 'tp.id, tp.name, tp.sort_order'],
            'status'   => ['label' => 't.status',
                           'join'  => '',
                           'group' => 't.status'],
        ];

        $gc  = $groupConfig[$groupBy];
        $lbl = $gc['label'];
        $jn  = $gc['join'];
        $grp = $gc['group'];
        $ord = $groupBy === 'priority' ? 'tp.sort_order, grp_label' : 'value DESC';

        $metricSql = match ($metric) {
            'ticket_count'       => 'COUNT(t.id)',
            'avg_first_response' => 'ROUND(AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END), 1)',
            'avg_resolution'     => 'ROUND(AVG((SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at) FROM ticket_timeline tl WHERE tl.ticket_id = t.id AND tl.action = \'status_changed\' AND tl.details LIKE \'%→ Resolved%\' ORDER BY tl.created_at DESC LIMIT 1)), 1)',
            'sla_compliance'     => 'ROUND(SUM(CASE WHEN t.first_response_due_at IS NOT NULL AND t.sla_state != \'breached\' THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN t.first_response_due_at IS NOT NULL THEN 1 ELSE 0 END),0) * 100, 1)',
        };

        $sql = "SELECT {$lbl} AS grp_label, {$metricSql} AS value
                FROM tickets t
                {$jn}
                WHERE t.created_at BETWEEN ? AND ?
                GROUP BY {$grp}
                ORDER BY {$ord}";

        $stmt = $db->prepare($sql);
        $stmt->execute([$from, $toEnd]);
        $rawRows = $stmt->fetchAll();

        foreach ($rawRows as $row) {
            $displayVal = $row['value'] ?? 0;
            if (in_array($metric, ['avg_first_response', 'avg_resolution'], true) && $displayVal !== null) {
                $displayVal = formatMinutes((float) $displayVal);
            } elseif ($metric === 'sla_compliance') {
                $displayVal = ($displayVal ?? 0) . '%';
            }
            $rows[] = ['label' => $row['grp_label'], 'raw' => $row['value'] ?? 0, 'display' => $displayVal];
        }
    }

    render('admin/reports/custom', compact(
        'from', 'to', 'metric', 'groupBy', 'rows',
        'metricLabel', 'metricOptions', 'groupByOptions'
    ));
});

/* ==================================================================
 * ADMIN – Settings: Scheduled Reports
 * ================================================================== */

$router->get('/admin/settings/scheduled-reports', function () {
    Auth::requireRole('admin');
    $db      = Database::connect();
    $reports = $db->query('SELECT * FROM scheduled_reports ORDER BY name')->fetchAll();
    render('admin/settings/scheduled-reports', compact('reports'));
});

$router->get('/admin/settings/scheduled-reports/create', function () {
    Auth::requireRole('admin');
    $report = null;
    render('admin/settings/scheduled-reports-form', compact('report'));
});

$router->post('/admin/settings/scheduled-reports/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/scheduled-reports');
    }
    $db             = Database::connect();
    $name           = trim($_POST['name'] ?? '');
    $allowedTypes   = ['overview','agent_performance','ticket_volume','response_times','sla',
                       'unresolved','lifecycle','location','csat','workload','trends','fcr'];
    $reportType     = in_array($_POST['report_type'] ?? '', $allowedTypes, true)
        ? $_POST['report_type'] : 'overview';
    $frequency      = in_array($_POST['frequency'] ?? '', ['daily','weekly','monthly'], true)
        ? $_POST['frequency'] : 'weekly';
    $sendDay        = $frequency === 'daily' ? null : max(0, min(31, (int)($_POST['send_day'] ?? 1)));
    $dateRangeDays  = max(1, min(365, (int)($_POST['date_range_days'] ?? 30)));
    $rawEmails      = trim($_POST['recipients'] ?? '');
    $recipients     = array_filter(array_map('trim', explode("\n", $rawEmails)));
    $enabled        = isset($_POST['is_enabled']) ? 1 : 0;

    if (empty($name) || empty($recipients)) {
        flash('error', 'Name and at least one recipient are required.');
        redirect('/admin/settings/scheduled-reports/create');
    }

    $db->prepare(
        'INSERT INTO scheduled_reports (name, report_type, recipients, frequency, send_day, date_range_days, is_enabled)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$name, $reportType, json_encode(array_values($recipients)), $frequency, $sendDay, $dateRangeDays, $enabled]);

    flash('success', "Scheduled report \"{$name}\" created.");
    redirect('/admin/settings/scheduled-reports');
});

$router->get('/admin/settings/scheduled-reports/{id}/edit', function (array $vars) {
    Auth::requireRole('admin');
    $db     = Database::connect();
    $stmt   = $db->prepare('SELECT * FROM scheduled_reports WHERE id = ?');
    $stmt->execute([(int)$vars['id']]);
    $report = $stmt->fetch();
    if (!$report) { http_response_code(404); echo 'Not found'; exit; }
    render('admin/settings/scheduled-reports-form', compact('report'));
});

$router->post('/admin/settings/scheduled-reports/{id}/edit', function (array $vars) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/scheduled-reports');
    }
    $db             = Database::connect();
    $id             = (int)$vars['id'];
    $name           = trim($_POST['name'] ?? '');
    $allowedTypes   = ['overview','agent_performance','ticket_volume','response_times','sla',
                       'unresolved','lifecycle','location','csat','workload','trends','fcr'];
    $reportType     = in_array($_POST['report_type'] ?? '', $allowedTypes, true)
        ? $_POST['report_type'] : 'overview';
    $frequency      = in_array($_POST['frequency'] ?? '', ['daily','weekly','monthly'], true)
        ? $_POST['frequency'] : 'weekly';
    $sendDay        = $frequency === 'daily' ? null : max(0, min(31, (int)($_POST['send_day'] ?? 1)));
    $dateRangeDays  = max(1, min(365, (int)($_POST['date_range_days'] ?? 30)));
    $rawEmails      = trim($_POST['recipients'] ?? '');
    $recipients     = array_filter(array_map('trim', explode("\n", $rawEmails)));
    $enabled        = isset($_POST['is_enabled']) ? 1 : 0;

    if (empty($name) || empty($recipients)) {
        flash('error', 'Name and at least one recipient are required.');
        redirect("/admin/settings/scheduled-reports/{$id}/edit");
    }

    $db->prepare(
        'UPDATE scheduled_reports SET name=?, report_type=?, recipients=?, frequency=?, send_day=?, date_range_days=?, is_enabled=? WHERE id=?'
    )->execute([$name, $reportType, json_encode(array_values($recipients)), $frequency, $sendDay, $dateRangeDays, $enabled, $id]);

    flash('success', "Scheduled report \"{$name}\" updated.");
    redirect('/admin/settings/scheduled-reports');
});

$router->post('/admin/settings/scheduled-reports/{id}/delete', function (array $vars) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/scheduled-reports');
    }
    $db = Database::connect();
    $db->prepare('DELETE FROM scheduled_reports WHERE id = ?')->execute([(int)$vars['id']]);
    flash('success', 'Scheduled report deleted.');
    redirect('/admin/settings/scheduled-reports');
});

$router->post('/admin/settings/scheduled-reports/{id}/toggle', function (array $vars) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/scheduled-reports');
    }
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT is_enabled FROM scheduled_reports WHERE id = ?');
    $stmt->execute([(int)$vars['id']]);
    $row  = $stmt->fetch();
    if (!$row) { redirect('/admin/settings/scheduled-reports'); }
    $db->prepare('UPDATE scheduled_reports SET is_enabled = ? WHERE id = ?')
       ->execute([$row['is_enabled'] ? 0 : 1, (int)$vars['id']]);
    redirect('/admin/settings/scheduled-reports');
});

/* ==================================================================
 * ADMIN – Workflows: Ticket Fields Builder
 * ================================================================== */

// Builder page
$router->get('/admin/workflows/ticket-fields', function () {
    Auth::requireRole('admin');
    $db = Database::connect();

    $fields = $db->query('SELECT * FROM ticket_form_fields WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();

    // Load options for each field that needs them
    $fieldOptions = [];
    foreach ($fields as $f) {
        if (in_array($f['field_type'], ['dropdown', 'dependent'], true)) {
            $stmt = $db->prepare(
                'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
            );
            $stmt->execute([$f['id']]);
            $fieldOptions[$f['id']] = $stmt->fetchAll();
        }
    }

    render('admin/workflows/ticket-fields', [
        'layout'       => 'app',
        'pageTitle'    => 'Ticket Fields',
        'fields'       => $fields,
        'fieldOptions' => $fieldOptions,
    ]);
});

// Add a new field (AJAX)
$router->post('/admin/workflows/ticket-fields/add', function () {
    Auth::requireRole('admin');
    header('Content-Type: application/json');

    $allowed = ['text','textarea','checkbox','dropdown','date','number','decimal','dependent'];
    $type    = $_POST['field_type'] ?? '';
    if (!in_array($type, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid field type.']);
        exit;
    }

    $db = Database::connect();
    $maxOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order),0) FROM ticket_form_fields WHERE deleted_at IS NULL')->fetchColumn();

    $labelMap = [
        'text'      => 'Text Field',
        'textarea'  => 'Multi-line Text',
        'checkbox'  => 'Checkbox',
        'dropdown'  => 'Dropdown',
        'date'      => 'Date',
        'number'    => 'Number',
        'decimal'   => 'Decimal',
        'dependent' => 'Dependent Field',
    ];

    $stmt = $db->prepare(
        'INSERT INTO ticket_form_fields (field_type, label, sort_order) VALUES (?, ?, ?)'
    );
    $stmt->execute([$type, $labelMap[$type], $maxOrder + 1]);
    $newId = (int) $db->lastInsertId();

    $field = $db->prepare('SELECT * FROM ticket_form_fields WHERE id = ?');
    $field->execute([$newId]);

    echo json_encode(['success' => true, 'field' => $field->fetch()]);
    exit;
});

// Reorder fields (AJAX)
$router->post('/admin/workflows/ticket-fields/reorder', function () {
    Auth::requireRole('admin');
    header('Content-Type: application/json');

    $body  = json_decode(file_get_contents('php://input'), true);
    $order = $body['order'] ?? [];

    if (!is_array($order)) {
        echo json_encode(['success' => false]);
        exit;
    }

    $db   = Database::connect();
    $stmt = $db->prepare('UPDATE ticket_form_fields SET sort_order = ? WHERE id = ?');
    foreach ($order as $i => $fid) {
        $stmt->execute([$i, (int) $fid]);
    }

    echo json_encode(['success' => true]);
    exit;
});

// Get options for a field (AJAX, used to pre-load modal on edit)
$router->get('/admin/workflows/ticket-fields/{id}/options', function (array $p) {
    Auth::requireRole('admin');
    header('Content-Type: application/json');

    $id   = (int) $p['id'];
    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
    );
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll());
    exit;
});

// Update field properties + options (AJAX)
$router->post('/admin/workflows/ticket-fields/{id}/update', function (array $p) {
    Auth::requireRole('admin');
    header('Content-Type: application/json');

    $id   = (int) $p['id'];
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON.']);
        exit;
    }

    $label       = trim($body['label']       ?? '');
    $placeholder = trim($body['placeholder'] ?? '');
    $isRequired  = !empty($body['is_required']) ? 1 : 0;
    $isVisible   = !empty($body['is_visible'])  ? 1 : 0;
    $config      = isset($body['config'])  ? json_encode($body['config'])  : null;

    if ($label === '') {
        echo json_encode(['success' => false, 'error' => 'Label is required.']);
        exit;
    }

    $db = Database::connect();

    // Verify field exists and is not deleted
    $check = $db->prepare('SELECT field_type FROM ticket_form_fields WHERE id = ? AND deleted_at IS NULL');
    $check->execute([$id]);
    $fieldType = $check->fetchColumn();
    if (!$fieldType) {
        echo json_encode(['success' => false, 'error' => 'Field not found.']);
        exit;
    }

    $db->prepare(
        'UPDATE ticket_form_fields SET label=?, placeholder=?, is_required=?, is_visible=?, config=?, updated_at=NOW() WHERE id=?'
    )->execute([$label, $placeholder ?: null, $isRequired, $isVisible, $config, $id]);

    // Replace options for dropdown / dependent fields
    if (in_array($fieldType, ['dropdown', 'dependent'], true) && isset($body['options'])) {
        $db->prepare('DELETE FROM ticket_form_field_options WHERE field_id = ?')->execute([$id]);

        $insertOpt = $db->prepare(
            'INSERT INTO ticket_form_field_options (field_id, parent_option_id, label, sort_order) VALUES (?, ?, ?, ?)'
        );

        if ($fieldType === 'dropdown') {
            // Flat array of {label}
            foreach ($body['options'] as $i => $opt) {
                $optLabel = trim($opt['label'] ?? '');
                if ($optLabel !== '') {
                    $insertOpt->execute([$id, null, $optLabel, $i]);
                }
            }
        } else {
            // Nested tree for dependent: [{label, children:[{label, children:[{label}]}]}]
            $sort1 = 0;
            foreach ($body['options'] as $l1) {
                $l1Label = trim($l1['label'] ?? '');
                if ($l1Label === '') continue;
                $insertOpt->execute([$id, null, $l1Label, $sort1++]);
                $l1Id = (int) $db->lastInsertId();

                $sort2 = 0;
                foreach ($l1['children'] ?? [] as $l2) {
                    $l2Label = trim($l2['label'] ?? '');
                    if ($l2Label === '') continue;
                    $insertOpt->execute([$id, $l1Id, $l2Label, $sort2++]);
                    $l2Id = (int) $db->lastInsertId();

                    $sort3 = 0;
                    foreach ($l2['children'] ?? [] as $l3) {
                        $l3Label = trim($l3['label'] ?? '');
                        if ($l3Label === '') continue;
                        $insertOpt->execute([$id, $l2Id, $l3Label, $sort3++]);
                    }
                }
            }
        }
    }

    echo json_encode(['success' => true]);
    exit;
});

// Delete a field (AJAX)
$router->post('/admin/workflows/ticket-fields/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    header('Content-Type: application/json');

    $id = (int) $p['id'];
    $db = Database::connect();
    $db->prepare('UPDATE ticket_form_fields SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL')->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
});

/* ==================================================================
 * Backup
 * ================================================================== */

$router->get('/admin/settings/backup', function () {
    Auth::requireRole('admin');
    $backupDir = ROOT_DIR . '/storage/backups/';
    @mkdir($backupDir, 0755, true);

    $backups = [];
    foreach (glob($backupDir . 'localdesk_backup_*.zip') ?: [] as $file) {
        $backups[] = [
            'name'    => basename($file),
            'size'    => filesize($file),
            'created' => filemtime($file),
        ];
    }
    usort($backups, fn ($a, $b) => $b['created'] - $a['created']);

    render('admin/settings/backup', [
        'backups'      => $backups,
        'zipAvailable' => class_exists('ZipArchive'),
    ]);
});

$router->post('/admin/settings/backup/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/backup');
    }

    if (!class_exists('ZipArchive')) {
        flash('error', 'ZipArchive PHP extension is not available on this server.');
        redirect('/admin/settings/backup');
    }

    set_time_limit(300);

    $db        = Database::connect();
    $backupDir = ROOT_DIR . '/storage/backups/';
    @mkdir($backupDir, 0755, true);

    $filename = 'localdesk_backup_' . date('Ymd_His') . '.zip';
    $zipPath  = $backupDir . $filename;

    try {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create zip file. Check storage/backups/ is writable.');
        }

        // --- SQL dump ---
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $sql  = "-- LocalDesk Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Tables: " . count($tables) . "\n";
        $sql .= "-- ------------------------------------------------\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

        foreach ($tables as $table) {
            $sql .= "-- ---- `$table` ----\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sql .= $create[1] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols = implode(', ', array_map(fn ($c) => "`$c`", array_keys($rows[0])));
                foreach (array_chunk($rows, 500) as $chunk) {
                    $vals = [];
                    foreach ($chunk as $row) {
                        $rowVals = array_map(
                            fn ($v) => $v === null ? 'NULL' : $db->quote((string) $v),
                            $row
                        );
                        $vals[] = '(' . implode(', ', $rowVals) . ')';
                    }
                    $sql .= "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $vals) . ";\n";
                }
                $sql .= "\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $zip->addFromString('database.sql', $sql);

        // --- Uploaded files ---
        $fileDirs = [
            ROOT_DIR . '/storage/attachments/' => 'attachments',
            ROOT_DIR . '/public/uploads/'      => 'uploads',
        ];
        foreach ($fileDirs as $dir => $zipPrefix) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $entry) {
                $rel = $zipPrefix . '/' . str_replace('\\', '/', substr($entry->getPathname(), strlen($dir)));
                if ($entry->isDir()) {
                    $zip->addEmptyDir($rel);
                } else {
                    $zip->addFile($entry->getPathname(), $rel);
                }
            }
        }

        $zip->close();
        flash('success', "Backup created: $filename");
    } catch (Exception $e) {
        @unlink($zipPath);
        flash('error', 'Backup failed: ' . $e->getMessage());
    }

    redirect('/admin/settings/backup');
});

$router->get('/admin/settings/backup/download', function () {
    Auth::requireRole('admin');
    $filename = $_GET['file'] ?? '';
    if (!preg_match('/^localdesk_backup_\d{8}_\d{6}\.zip$/', $filename)) {
        flash('error', 'Invalid backup filename.');
        redirect('/admin/settings/backup');
    }
    $path = ROOT_DIR . '/storage/backups/' . $filename;
    if (!file_exists($path)) {
        flash('error', 'Backup file not found.');
        redirect('/admin/settings/backup');
    }
    // Clear any buffered output before streaming the file
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

$router->post('/admin/settings/backup/delete', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/backup');
    }
    $filename = $_POST['filename'] ?? '';
    if (!preg_match('/^localdesk_backup_\d{8}_\d{6}\.zip$/', $filename)) {
        flash('error', 'Invalid backup filename.');
        redirect('/admin/settings/backup');
    }
    $path = ROOT_DIR . '/storage/backups/' . $filename;
    if (file_exists($path)) {
        unlink($path);
        flash('success', 'Backup deleted.');
    }
    redirect('/admin/settings/backup');
});

/* ==================================================================
 * Audit Log
 * ================================================================== */

$router->get('/admin/audit-log', function () {
    Auth::requireRole('admin');
    $db = Database::connect();

    // Filters
    $filterUser   = isset($_GET['user_id'])  ? (int) $_GET['user_id']  : null;
    $filterAction = trim($_GET['action']     ?? '');
    $filterFrom   = trim($_GET['from']       ?? '');
    $filterTo     = trim($_GET['to']         ?? '');
    $page         = max(1, (int) ($_GET['page'] ?? 1));
    $perPage      = 50;
    $offset       = ($page - 1) * $perPage;

    // Build WHERE clause
    $where  = [];
    $params = [];

    if ($filterUser) {
        $where[]  = 'al.user_id = ?';
        $params[] = $filterUser;
    }
    if ($filterAction !== '') {
        $where[]  = 'al.action = ?';
        $params[] = $filterAction;
    }
    if ($filterFrom !== '') {
        $where[]  = 'al.created_at >= ?';
        $params[] = $filterFrom . ' 00:00:00';
    }
    if ($filterTo !== '') {
        $where[]  = 'al.created_at <= ?';
        $params[] = $filterTo . ' 23:59:59';
    }

    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log al {$whereSQL}");
    $countStmt->execute($params);
    $total     = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($total / $perPage);

    // Rows
    $rowsStmt = $db->prepare(
        "SELECT al.*,
                CONCAT(u.first_name, ' ', u.last_name) AS actor_name
         FROM audit_log al
         LEFT JOIN users u ON al.user_id = u.id
         {$whereSQL}
         ORDER BY al.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $rowsStmt->execute($params);
    $entries = $rowsStmt->fetchAll();

    // List of users for filter dropdown (those who have audit entries)
    $actorList = $db->query(
        "SELECT DISTINCT al.user_id,
                CONCAT(u.first_name, ' ', u.last_name) AS name
         FROM audit_log al
         JOIN users u ON al.user_id = u.id
         ORDER BY name"
    )->fetchAll();

    // Distinct action types for filter dropdown
    $actionList = $db->query(
        "SELECT DISTINCT action FROM audit_log ORDER BY action"
    )->fetchAll(\PDO::FETCH_COLUMN);

    render('admin/audit-log/index', [
        'entries'      => $entries,
        'actorList'    => $actorList,
        'actionList'   => $actionList,
        'filterUser'   => $filterUser,
        'filterAction' => $filterAction,
        'filterFrom'   => $filterFrom,
        'filterTo'     => $filterTo,
        'total'        => $total,
        'page'         => $page,
        'totalPages'   => $totalPages,
        'perPage'      => $perPage,
    ]);
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
