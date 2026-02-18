<?php

declare(strict_types=1);

/* ==================================================================
 * ADMIN – User Management
 * ================================================================== */

$router->get('/admin/users', function () {
    Auth::requireRole('admin');
    $db   = Database::connect();
    $role  = $_GET['role'] ?? '';
    $locId = trim($_GET['location'] ?? '');

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

    // Check for records that would prevent deletion
    $ticketCount = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_by = ?');
    $ticketCount->execute([$id]);
    $tickets = (int) $ticketCount->fetchColumn();

    $kbCount = $db->prepare('SELECT COUNT(*) FROM kb_articles WHERE created_by = ?');
    $kbCount->execute([$id]);
    $kbArticles = (int) $kbCount->fetchColumn();

    if ($tickets > 0 || $kbArticles > 0) {
        $parts = [];
        if ($tickets > 0) $parts[] = $tickets . ' ticket' . ($tickets > 1 ? 's' : '');
        if ($kbArticles > 0) $parts[] = $kbArticles . ' KB article' . ($kbArticles > 1 ? 's' : '');
        flash('error', 'Cannot delete this user because they have ' . implode(' and ', $parts) . '. Reassign or remove those records first.');
        redirect('/admin/users');
    }

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
    $db = Database::connect();

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
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

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

    render('admin/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'priorities' => $priorities, 'attachments' => $attachments, 'ccUsers' => $ccUsers, 'groups' => $groups]);
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

    // Read header row
    $header = fgetcsv($handle);
    if (!$header || !in_array('Ticket ID', $header, true) || !in_array('Subject', $header, true)) {
        fclose($handle);
        flash('error', 'Invalid CSV format. Expected Freshdesk export with "Ticket ID" and "Subject" columns.');
        redirect('/admin/settings/import');
    }

    // Build column index map
    $colMap = array_flip($header);

    // Load existing data for lookups
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

    $existingTypes = [];
    foreach ($db->query('SELECT id, name FROM ticket_types')->fetchAll() as $t) {
        $existingTypes[strtolower($t['name'])] = $t['id'];
    }

    $existingPriorities = [];
    foreach ($db->query('SELECT id, name FROM ticket_priorities')->fetchAll() as $p) {
        $existingPriorities[strtolower($p['name'])] = $p['id'];
    }

    // Parse all rows
    $rows = [];
    $newUserEmails = [];
    $newAgentNames = [];
    $newLocationNames = [];

    while (($csvRow = fgetcsv($handle)) !== false) {
        if (count($csvRow) < count($header)) {
            continue; // skip malformed rows
        }

        $data = [];
        foreach ($colMap as $col => $idx) {
            $data[$col] = trim($csvRow[$idx] ?? '');
        }

        $legacyId   = $data['Ticket ID'] ?? '';
        $subject    = $data['Subject'] ?? '';
        $status     = strtolower($data['Status'] ?? 'open');
        $priority   = $data['Priority'] ?? '';
        $agentName  = $data['Agent'] ?? '';
        $createdAt  = $data['Created time'] ?? '';
        $dueDate    = $data['Due by Time'] ?? '';
        $updatedAt  = $data['Last update time'] ?? '';
        $respondedAt = $data['Initial response time'] ?? '';
        $typeName   = $data['Type of Ticket'] ?? '';
        $location   = $data['Location'] ?? '';
        $fullName   = $data['Full name'] ?? '';
        $email      = strtolower(trim($data['Email'] ?? ''));
        $tags       = $data['Tags'] ?? '';

        if ($subject === '' || $legacyId === '') {
            continue;
        }

        // Normalize status
        $statusMap = ['open' => 'open', 'closed' => 'closed', 'pending' => 'pending', 'resolved' => 'resolved'];
        $status = $statusMap[$status] ?? 'open';

        // Track new users (submitters)
        if ($email !== '' && !isset($existingUsers[$email])) {
            $newUserEmails[$email] = $fullName;
        }

        // Track new agents
        if ($agentName !== '' && $agentName !== 'No Agent' && !isset($existingUsers['name:' . strtolower($agentName)])) {
            $newAgentNames[strtolower($agentName)] = $agentName;
        }

        // Track new locations
        if ($location !== '' && !isset($existingLocations[strtolower($location)])) {
            $newLocationNames[strtolower($location)] = $location;
        }

        $rows[] = [
            'legacy_id'     => $legacyId,
            'subject'       => $subject,
            'status'        => $status,
            'priority'      => $priority,
            'agent'         => $agentName,
            'created_at'    => $createdAt,
            'due_date'      => $dueDate,
            'updated_at'    => $updatedAt,
            'responded_at'  => $respondedAt,
            'type'          => $typeName,
            'location'      => $location,
            'submitter_name'=> $fullName,
            'email'         => $email,
            'tags'          => $tags,
        ];
    }
    fclose($handle);

    if (empty($rows)) {
        flash('error', 'No valid ticket rows found in the CSV file.');
        redirect('/admin/settings/import');
    }

    // Store in session for the confirm step
    $_SESSION['import_data'] = $rows;

    $summary = [
        'total_tickets'     => count($rows),
        'new_users'         => count($newUserEmails),
        'new_agents'        => count($newAgentNames),
        'new_locations'     => count($newLocationNames),
        'new_user_list'     => array_values($newUserEmails),
        'new_agent_list'    => array_values($newAgentNames),
        'new_location_list' => array_values($newLocationNames),
    ];

    $previewRows = array_slice($rows, 0, 15);

    render('admin/settings/import-preview', [
        'summary'     => $summary,
        'previewRows' => $previewRows,
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
                '(Imported from legacy system)',
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
 * ADMIN – Settings: Branding
 * ================================================================== */

$router->get('/admin/settings/branding', function () {
    Auth::requireRole('admin');
    render('admin/settings/branding', [
        'appName'      => getSetting('branding_app_name', 'LocalDesk'),
        'primaryColor' => getSetting('branding_primary_color', '#4f46e5'),
        'primaryHover' => getSetting('branding_primary_hover', '#4338ca'),
        'navbarStart'  => getSetting('branding_navbar_start', '#1e1b4b'),
        'navbarEnd'    => getSetting('branding_navbar_end', '#312e81'),
        'logo'         => getSetting('branding_logo', ''),
    ]);
});

$router->post('/admin/settings/branding', function () {
    Auth::requireRole('admin');
    verifyCsrf();

    $appName      = trim($_POST['app_name'] ?? 'LocalDesk');
    $primaryColor = trim($_POST['primary_color'] ?? '#4f46e5');
    $primaryHover = trim($_POST['primary_hover'] ?? '#4338ca');
    $navbarStart  = trim($_POST['navbar_start'] ?? '#1e1b4b');
    $navbarEnd    = trim($_POST['navbar_end'] ?? '#312e81');

    // Validate hex colors
    $colorPattern = '/^#[0-9a-fA-F]{6}$/';
    foreach ([$primaryColor, $primaryHover, $navbarStart, $navbarEnd] as $color) {
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
    setSetting('branding_primary_color', $primaryColor);
    setSetting('branding_primary_hover', $primaryHover);
    setSetting('branding_navbar_start', $navbarStart);
    setSetting('branding_navbar_end', $navbarEnd);

    flash('success', 'Branding settings updated successfully.');
    redirect('/admin/settings/branding');
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
 * ADMIN – Settings: Danger Zone
 * ================================================================== */
$router->get('/admin/settings/danger-zone', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    $ticketCount = (int) $db->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
    render('admin/settings/danger-zone', ['ticketCount' => $ticketCount]);
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
