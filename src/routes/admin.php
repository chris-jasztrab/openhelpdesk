<?php

declare(strict_types=1);

/* ==================================================================
 * ADMIN – SSO Settings
 * ================================================================== */

$router->get('/admin/settings/sso', function () {
    Auth::requireRole('admin');
    render('admin/settings/sso', [
        'ssoEnabled'        => getSetting('sso_enabled',        '0'),
        'ssoTenantId'       => getSetting('sso_tenant_id',      ''),
        'ssoClientId'       => getSetting('sso_client_id',      ''),
        'ssoClientSecret'   => getSetting('sso_client_secret',  ''),
        'ssoLocationPrompt' => getSetting('sso_location_prompt','sso_only'),
    ]);
});

$router->post('/admin/settings/sso', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect('/admin/settings/sso');
    }

    $enabled        = isset($_POST['sso_enabled'])       ? '1' : '0';
    $tenantId       = trim($_POST['sso_tenant_id']       ?? '');
    $clientId       = trim($_POST['sso_client_id']       ?? '');
    $clientSecret   = $_POST['sso_client_secret']        ?? '';
    $locationPrompt = in_array($_POST['sso_location_prompt'] ?? '', ['sso_only', 'all'], true)
                        ? $_POST['sso_location_prompt']
                        : 'sso_only';

    if ($enabled === '1' && ($tenantId === '' || $clientId === '')) {
        flash('error', 'Tenant ID and Client ID are required to enable SSO.');
        redirect('/admin/settings/sso');
    }

    setSetting('sso_enabled',        $enabled);
    setSetting('sso_tenant_id',      $tenantId);
    setSetting('sso_client_id',      $clientId);
    setSetting('sso_location_prompt', $locationPrompt);

    // Only overwrite the secret if a new value was provided
    if ($clientSecret !== '') {
        setSetting('sso_client_secret', $clientSecret);
    }

    flash('success', 'SSO settings saved successfully.');
    redirect('/admin/settings/sso');
});

$router->get('/admin/settings/sso/help', function () {
    Auth::requireRole('admin');
    render('admin/settings/sso-help');
});

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

    $roleFilter = array_values(array_filter(array_map('trim', (array) ($_GET['role']     ?? []))));
    $locFilter  = array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? []))));
    $q          = trim($_GET['q'] ?? '');

    $sql    = 'SELECT u.*, l.name AS location_name FROM users u LEFT JOIN locations l ON u.location_id = l.id';
    $where  = [];
    $params = [];

    $validRoles = ['admin', 'agent', 'power_user', 'user'];
    $roles = array_values(array_filter($roleFilter, fn($r) => in_array($r, $validRoles, true)));
    if (!empty($roles)) {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $where[]  = "u.role IN ($placeholders)";
        $params   = array_merge($params, $roles);
    }

    $hasNone = in_array('none', $locFilter, true);
    $locIds  = array_values(array_filter($locFilter, fn($l) => $l !== 'none' && ctype_digit((string) $l)));
    if ($hasNone && !empty($locIds)) {
        $placeholders = implode(',', array_fill(0, count($locIds), '?'));
        $where[]  = "(u.location_id IS NULL OR u.location_id IN ($placeholders))";
        $params   = array_merge($params, array_map('intval', $locIds));
    } elseif ($hasNone) {
        $where[] = 'u.location_id IS NULL';
    } elseif (!empty($locIds)) {
        $placeholders = implode(',', array_fill(0, count($locIds), '?'));
        $where[]  = "u.location_id IN ($placeholders)";
        $params   = array_merge($params, array_map('intval', $locIds));
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
        'roleFilter' => $roleFilter,
        'locFilter'  => $locFilter,
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
    $roleRaw         = $_POST['role'] ?? 'user';
    $role            = in_array($roleRaw, ['admin', 'agent', 'power_user', 'user'], true) ? $roleRaw : 'user';
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
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $userGroups = [];
    if (in_array($editing['role'], ['agent', 'admin', 'power_user'])) {
        $gStmt = $db->prepare(
            'SELECT g.id, g.name FROM `groups` g
             JOIN group_user_map gum ON gum.group_id = g.id
             WHERE gum.user_id = ? ORDER BY g.name'
        );
        $gStmt->execute([(int) $p['id']]);
        $userGroups = $gStmt->fetchAll();
    }
    render('admin/users/form', ['locations' => $locations, 'editing' => $editing, 'userGroups' => $userGroups]);
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
    $roleRaw       = $_POST['role'] ?? 'user';
    $role          = in_array($roleRaw, ['admin', 'agent', 'power_user', 'user'], true) ? $roleRaw : 'user';
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
 * ADMIN – Merge Users
 * ================================================================== */

$router->get('/admin/users/merge', function () {
    Auth::requireRole('admin');
    $suggestDeleteId = (int) ($_GET['suggest_delete'] ?? 0);
    $suggestUser     = null;
    if ($suggestDeleteId > 0) {
        $s = Database::connect()->prepare('SELECT id, first_name, last_name, email FROM users WHERE id = ?');
        $s->execute([$suggestDeleteId]);
        $suggestUser = $s->fetch() ?: null;
    }
    render('admin/users/merge', ['step' => 'search', 'keepUser' => null, 'deleteUser' => null, 'stats' => null, 'suggestUser' => $suggestUser]);
});

$router->post('/admin/users/merge', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/users/merge');
    }

    $db     = Database::connect();
    $action = $_POST['action'] ?? 'preview';

    // ── Preview step ────────────────────────────────────────────────
    if ($action === 'preview') {
        $keepId   = (int) ($_POST['keep_id']   ?? 0);
        $deleteId = (int) ($_POST['delete_id'] ?? 0);

        if ($keepId === 0 || $deleteId === 0 || $keepId === $deleteId) {
            flash('error', 'Please select two different users.');
            redirect('/admin/users/merge');
        }

        $stmt = $db->prepare('SELECT id, first_name, last_name, email, role, created_at, location_id FROM users WHERE id = ?');

        $stmt->execute([$keepId]);
        $keepUser = $stmt->fetch();
        $stmt->execute([$deleteId]);
        $deleteUser = $stmt->fetch();

        if (!$keepUser || !$deleteUser) {
            flash('error', 'One or both users not found.');
            redirect('/admin/users/merge');
        }

        // Build stats for each user
        $stats = [];
        foreach ([['keep', $keepId], ['delete', $deleteId]] as [$role, $uid]) {
            $tc = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_by = ?');
            $tc->execute([$uid]);

            $ta = $db->prepare('SELECT COUNT(*) FROM tickets WHERE assigned_to = ?');
            $ta->execute([$uid]);

            $tl = $db->prepare('SELECT COUNT(*) FROM ticket_timeline WHERE user_id = ?');
            $tl->execute([$uid]);

            $stats[$role] = [
                'tickets_created'  => (int) $tc->fetchColumn(),
                'tickets_assigned' => (int) $ta->fetchColumn(),
                'comments'         => (int) $tl->fetchColumn(),
            ];
        }

        render('admin/users/merge', [
            'step'       => 'preview',
            'keepUser'   => $keepUser,
            'deleteUser' => $deleteUser,
            'stats'      => $stats,
        ]);
        return;
    }

    // ── Execute step ────────────────────────────────────────────────
    if ($action === 'execute') {
        $keepId   = (int) ($_POST['keep_id']   ?? 0);
        $deleteId = (int) ($_POST['delete_id'] ?? 0);

        if ($keepId === 0 || $deleteId === 0 || $keepId === $deleteId) {
            flash('error', 'Invalid merge request.');
            redirect('/admin/users/merge');
        }

        $stmt = $db->prepare('SELECT id, email FROM users WHERE id = ?');
        $stmt->execute([$keepId]);
        $keepUser = $stmt->fetch();
        $stmt->execute([$deleteId]);
        $deleteUser = $stmt->fetch();

        if (!$keepUser || !$deleteUser) {
            flash('error', 'One or both users not found.');
            redirect('/admin/users/merge');
        }

        // Prevent merging the currently logged-in admin into another account
        if ($deleteId === Auth::id()) {
            flash('error', 'You cannot merge your own account.');
            redirect('/admin/users/merge');
        }

        $db->beginTransaction();
        try {
            // ── Simple column reassignments ──────────────────────────
            $simpleUpdates = [
                ['tickets',              'created_by'],
                ['tickets',              'assigned_to'],
                ['ticket_timeline',      'user_id'],
                ['ticket_attachments',   'uploaded_by'],
                ['ticket_cc',            'added_by'],
                ['notifications',        'user_id'],
                ['notifications',        'mentioned_by'],
                ['audit_log',            'user_id'],
                ['csat_surveys',         'user_id'],
                ['canned_responses',     'user_id'],
                ['saved_filters',        'user_id'],
                ['api_tokens',           'user_id'],
                ['kb_articles',          'created_by'],
                ['kb_article_revisions', 'edited_by'],
                ['ticket_templates',     'created_by'],
            ];

            foreach ($simpleUpdates as [$table, $col]) {
                $db->prepare("UPDATE `{$table}` SET `{$col}` = ? WHERE `{$col}` = ?")->execute([$keepId, $deleteId]);
            }

            // ── Pivot tables — reassign non-duplicates then drop duplicates ──
            // ticket_cc — unique on (ticket_id, user_id)
            $db->prepare(
                'UPDATE ticket_cc SET user_id = ? WHERE user_id = ?
                 AND ticket_id NOT IN (SELECT ticket_id FROM (SELECT ticket_id FROM ticket_cc WHERE user_id = ?) AS t)'
            )->execute([$keepId, $deleteId, $keepId]);
            $db->prepare('DELETE FROM ticket_cc WHERE user_id = ?')->execute([$deleteId]);

            // ticket_watchers — unique on (ticket_id, user_id)
            $db->prepare(
                'UPDATE ticket_watchers SET user_id = ? WHERE user_id = ?
                 AND ticket_id NOT IN (SELECT ticket_id FROM (SELECT ticket_id FROM ticket_watchers WHERE user_id = ?) AS t)'
            )->execute([$keepId, $deleteId, $keepId]);
            $db->prepare('DELETE FROM ticket_watchers WHERE user_id = ?')->execute([$deleteId]);

            // group_user_map — unique on (group_id, user_id)
            $db->prepare(
                'UPDATE group_user_map SET user_id = ? WHERE user_id = ?
                 AND group_id NOT IN (SELECT group_id FROM (SELECT group_id FROM group_user_map WHERE user_id = ?) AS t)'
            )->execute([$keepId, $deleteId, $keepId]);
            $db->prepare('DELETE FROM group_user_map WHERE user_id = ?')->execute([$deleteId]);

            // ticket_presence — transient, just remove
            $db->prepare('DELETE FROM ticket_presence WHERE user_id = ?')->execute([$deleteId]);

            // ── Delete the source user (cascades any remaining FK refs) ──
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$deleteId]);

            $db->commit();

            logAudit('user.merged', $deleteId, 'user',
                "Merged {$deleteUser['email']} into {$keepUser['email']} (kept id={$keepId})");

        } catch (\Throwable $e) {
            $db->rollBack();
            flash('error', 'Merge failed: ' . $e->getMessage());
            redirect('/admin/users/merge');
        }

        flash('success', "Accounts merged. \"{$deleteUser['email']}\" has been removed and all data transferred to \"{$keepUser['email']}\".");
        redirect("/admin/users/{$keepId}");
    }

    flash('error', 'Unknown action.');
    redirect('/admin/users/merge');
});

/* ==================================================================
 * ADMIN – Location Management
 * ================================================================== */

$router->get('/admin/locations', function () {
    Auth::requireRole('admin');
    $locations = Database::connect()->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    render('admin/locations/index', [
        'locations'  => $locations,
        'tzMode'     => getSetting('location_timezone_mode', 'shared'),
        'sharedTz'   => getSetting('location_timezone_shared', 'UTC'),
        'timezones'  => commonTimezones(),
    ]);
});

$router->post('/admin/locations/timezone-settings', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/locations');
    }
    $mode = ($_POST['location_timezone_mode'] ?? '') === 'per_location' ? 'per_location' : 'shared';
    $sharedTz = trim($_POST['location_timezone_shared'] ?? '');
    setSetting('location_timezone_mode', $mode);
    if ($sharedTz !== '') {
        setSetting('location_timezone_shared', $sharedTz);
    }
    flash('success', 'Timezone settings saved.');
    redirect('/admin/locations');
});

$router->get('/admin/locations/create', function () {
    Auth::requireRole('admin');
    render('admin/locations/form', [
        'editing'   => null,
        'tzMode'    => getSetting('location_timezone_mode', 'shared'),
        'sharedTz'  => getSetting('location_timezone_shared', 'UTC'),
        'timezones' => commonTimezones(),
    ]);
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
    $tz   = trim($_POST['timezone'] ?? '') ?: null;
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Location name is required.');
        redirect('/admin/locations/create');
    }
    Database::connect()->prepare('INSERT INTO locations (name, address, description, timezone) VALUES (?, ?, ?, ?)')
        ->execute([$name, $addr, $desc, $tz]);
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
    render('admin/locations/form', [
        'editing'   => $editing,
        'tzMode'    => getSetting('location_timezone_mode', 'shared'),
        'sharedTz'  => getSetting('location_timezone_shared', 'UTC'),
        'timezones' => commonTimezones(),
    ]);
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
    $tz   = trim($_POST['timezone'] ?? '') ?: null;
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Location name is required.');
        redirect("/admin/locations/{$id}/edit");
    }
    Database::connect()->prepare('UPDATE locations SET name=?, address=?, description=?, timezone=? WHERE id=?')
        ->execute([$name, $addr, $desc, $tz, $id]);
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
 * ADMIN – Canned Responses (global, admin-managed)
 * ================================================================== */

$router->get('/admin/settings/canned-responses', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    $responses = $db->query(
        'SELECT * FROM canned_responses WHERE user_id IS NULL ORDER BY sort_order, title'
    )->fetchAll();
    render('admin/settings/canned-responses/index', ['responses' => $responses]);
});

$router->get('/admin/settings/canned-responses/create', function () {
    Auth::requireRole('admin');
    render('admin/settings/canned-responses/form', ['editing' => null]);
});

$router->post('/admin/settings/canned-responses/create', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/canned-responses/create');
    }
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($title === '' || $body === '') {
        flashInput($_POST);
        flash('error', 'Title and body are required.');
        redirect('/admin/settings/canned-responses/create');
    }
    Database::connect()->prepare(
        'INSERT INTO canned_responses (user_id, title, body, sort_order) VALUES (NULL, ?, ?, ?)'
    )->execute([$title, $body, $order]);
    flash('success', 'Canned response created.');
    redirect('/admin/settings/canned-responses');
});

$router->get('/admin/settings/canned-responses/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM canned_responses WHERE id = ? AND user_id IS NULL');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Canned response not found.');
        redirect('/admin/settings/canned-responses');
    }
    render('admin/settings/canned-responses/form', ['editing' => $editing]);
});

$router->post('/admin/settings/canned-responses/{id}/edit', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/settings/canned-responses/{$id}/edit");
    }
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($title === '' || $body === '') {
        flashInput($_POST);
        flash('error', 'Title and body are required.');
        redirect("/admin/settings/canned-responses/{$id}/edit");
    }
    Database::connect()->prepare(
        'UPDATE canned_responses SET title = ?, body = ?, sort_order = ? WHERE id = ? AND user_id IS NULL'
    )->execute([$title, $body, $order, $id]);
    flash('success', 'Canned response updated.');
    redirect('/admin/settings/canned-responses');
});

$router->post('/admin/settings/canned-responses/{id}/delete', function (array $p) {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/canned-responses');
    }
    Database::connect()->prepare(
        'DELETE FROM canned_responses WHERE id = ? AND user_id IS NULL'
    )->execute([(int) $p['id']]);
    flash('success', 'Canned response deleted.');
    redirect('/admin/settings/canned-responses');
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
        "SELECT id, first_name, last_name, role FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name, last_name"
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
        "SELECT id, first_name, last_name, role FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name, last_name"
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
    Auth::requireRole('admin', 'agent', 'power_user');
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
    Auth::requireRole('admin', 'agent', 'power_user');
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    render('admin/ticket-templates/form', ['types' => $types, 'priorities' => $priorities]);
});

$router->post('/admin/ticket-templates/create', function () {
    Auth::requireRole('admin', 'agent', 'power_user');
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
    Auth::requireRole('admin', 'agent', 'power_user');
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
    Auth::requireRole('admin', 'agent', 'power_user');
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
    Auth::requireRole('admin', 'agent', 'power_user');
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

    // Read filter params (multi-select arrays for status/priority/type/location/agent/group)
    $filters = [
        'status'    => array_values(array_filter(array_map('trim', (array) ($_GET['status']   ?? [])))),
        'priority'  => array_values(array_filter(array_map('trim', (array) ($_GET['priority'] ?? [])))),
        'type'      => array_values(array_filter(array_map('trim', (array) ($_GET['type']     ?? [])))),
        'location'  => array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? [])))),
        'agent'     => array_values(array_filter(array_map('trim', (array) ($_GET['agent']    ?? [])))),
        'group'     => array_values(array_filter(array_map('trim', (array) ($_GET['group']    ?? [])))),
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
    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name")->fetchAll();
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
        'status'    => array_values(array_filter(array_map('trim', (array) ($_GET['status']   ?? [])))),
        'priority'  => array_values(array_filter(array_map('trim', (array) ($_GET['priority'] ?? [])))),
        'type'      => array_values(array_filter(array_map('trim', (array) ($_GET['type']     ?? [])))),
        'location'  => array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? [])))),
        'agent'     => array_values(array_filter(array_map('trim', (array) ($_GET['agent']    ?? [])))),
        'group'     => array_values(array_filter(array_map('trim', (array) ($_GET['group']    ?? [])))),
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
    Auth::requireRole('admin', 'agent', 'power_user');
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $agents     = $db->query(
        "SELECT id, first_name, last_name, email FROM users
         WHERE role IN ('admin','agent','power_user') ORDER BY first_name, last_name"
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
    Auth::requireRole('admin', 'agent', 'power_user');
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
        $redirectBase = in_array(Auth::role(), ['agent', 'power_user'], true) ? '/agent' : '/admin';
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

    // Notify group members watching new tickets
    notifyGroupMembers($db, $ticketId);

    flash('success', 'Ticket #' . $ticketId . ' created.');
    $redirectBase = Auth::role() === 'agent' ? '/agent' : '/admin';
    redirect("{$redirectBase}/tickets/{$ticketId}");
});

$router->post('/admin/tickets/bulk', function () {
    Auth::requireRole('admin', 'agent', 'power_user');
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
         ORDER BY tl.created_at DESC"
    );
    $tl->execute([$ticket['id']]);
    $timeline = $tl->fetchAll();

    // Agents list for @mention suggestions and assignment dropdown
    $agents = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name")->fetchAll();

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

    // Is the current user watching this ticket?
    $watchStmt = $db->prepare('SELECT 1 FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?');
    $watchStmt->execute([$ticket['id'], Auth::id()]);
    $isWatching = (bool) $watchStmt->fetchColumn();

    render('admin/tickets/view', ['ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents, 'priorities' => $priorities, 'attachments' => $attachments, 'ccUsers' => $ccUsers, 'groups' => $groups, 'customFields' => $customFields, 'fieldValues' => $fieldValues, 'fieldOptions' => $fieldOptions, 'isWatching' => $isWatching]);
});

/* ==================================================================
 * ADMIN – Watch / Unwatch Ticket
 * ================================================================== */

$router->post('/admin/tickets/{id}/watch', function (array $p) {
    Auth::requireRole('admin');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect("/admin/tickets/{$id}");
    }
    $db = Database::connect();
    $check = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        redirect('/admin/tickets');
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
    redirect("/admin/tickets/{$id}");
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
 * ADMIN – Split Ticket
 * ================================================================== */

$router->get('/admin/tickets/{id}/split', function (array $p) {
    Auth::requireRole('admin');
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*, l.name AS location_name, tt.name AS type_name,
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
        redirect('/admin/tickets');
    }

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

    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name")->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

    render('admin/tickets/split', compact('ticket', 'comments', 'agents', 'priorities', 'types', 'groups'));
});

$router->post('/admin/tickets/{id}/split', function (array $p) {
    Auth::requireRole('admin');
    $sourceId = (int) $p['id'];

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$sourceId}/split");
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
        redirect("/admin/tickets/{$sourceId}/split");
    }

    $db = Database::connect();

    $src = $db->prepare('SELECT * FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $src->execute([$sourceId]);
    $sourceTicket = $src->fetch();
    if (!$sourceTicket) {
        flash('error', 'Source ticket not found or already merged.');
        redirect('/admin/tickets');
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
        redirect("/admin/tickets/{$sourceId}/split");
    }

    flash('success', "Ticket #{$sourceId} split — new Ticket #{$newId} created.");
    redirect("/admin/tickets/{$newId}");
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
        notifyAgentNoteAdded($db, $id, $message);
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
            if (in_array($statusAfter, ['resolved', 'closed'], true)) {
                notifyRequesterStatusChanged($db, $id, $statusAfter);
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

        if (in_array($newStatus, ['resolved', 'closed'], true)) {
            notifyRequesterStatusChanged($db, $id, $newStatus);
        }

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
        if ($newGroup) { notifyAssignedGroup($db, $id, $newGroup); }
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
 * ADMIN – KB Export (CSV) / Import (JSON)
 * ================================================================== */

$router->get('/admin/kb/export', function () {
    Auth::requireRole('admin');
    $db = Database::connect();

    $stmt = $db->query(
        "SELECT a.title, a.body_markdown, c.name AS category, a.status
         FROM kb_articles a
         JOIN kb_folders f  ON a.folder_id     = f.id
         JOIN kb_categories c ON f.category_id = c.id
         ORDER BY c.sort_order, c.name, f.sort_order, f.name, a.sort_order, a.title"
    );

    $filename = 'kb-export-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    fputcsv($out, ['title', 'body_markdown', 'category', 'status', 'tags']);

    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['title'],
            $row['body_markdown'],
            $row['category'],
            $row['status'],
            '', // tags — no tag field on KB articles
        ]);
    }

    fclose($out);
    exit;
});

$router->get('/admin/kb/import', function () {
    Auth::requireRole('admin');
    render('admin/kb/import', [
        'layout'       => 'app',
        'pageTitle'    => 'Import Knowledge Base',
        'sidebarItems' => adminSidebar('kb'),
        'breadcrumbs'  => [
            ['label' => 'Admin',          'url' => '/admin'],
            ['label' => 'Knowledge Base', 'url' => '/admin/kb/articles'],
            ['label' => 'Import'],
        ],
    ]);
});

$router->post('/admin/kb/import/preview', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/import');
        return;
    }

    if (empty($_FILES['json_file']['tmp_name']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please upload a valid JSON file.');
        redirect('/admin/kb/import');
        return;
    }

    if ($_FILES['json_file']['size'] > 20 * 1024 * 1024) {
        flash('error', 'File too large. Maximum 20 MB.');
        redirect('/admin/kb/import');
        return;
    }

    $raw = file_get_contents($_FILES['json_file']['tmp_name']);
    if ($raw === false) {
        flash('error', 'Unable to read file.');
        redirect('/admin/kb/import');
        return;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['categories']) || !is_array($data['categories'])) {
        flash('error', 'Invalid format. Please upload a JSON file exported from LocalDesk.');
        redirect('/admin/kb/import');
        return;
    }

    // Collect summary stats and build article preview
    $totalCategories = 0;
    $totalFolders    = 0;
    $totalArticles   = 0;
    $draftCount      = 0;
    $publishedCount  = 0;
    $previewArticles = [];

    foreach ($data['categories'] as $cat) {
        if (empty($cat['name'])) { continue; }
        $totalCategories++;
        foreach ($cat['folders'] ?? [] as $folder) {
            if (empty($folder['name'])) { continue; }
            $totalFolders++;
            foreach ($folder['articles'] ?? [] as $article) {
                if (empty($article['title']) || empty($article['body_markdown'])) { continue; }
                $totalArticles++;
                if (($article['status'] ?? 'draft') === 'published') {
                    $publishedCount++;
                } else {
                    $draftCount++;
                }
                if (count($previewArticles) < 15) {
                    $previewArticles[] = [
                        'title'        => $article['title'],
                        'category'     => $cat['name'],
                        'folder'       => $folder['name'],
                        'status'       => $article['status'] ?? 'draft',
                        'body_preview' => mb_strimwidth($article['body_markdown'], 0, 80, '…'),
                    ];
                }
            }
        }
    }

    if ($totalArticles === 0) {
        flash('error', 'No valid articles found in the JSON file.');
        redirect('/admin/kb/import');
        return;
    }

    // Check which categories already exist
    $db = Database::connect();
    $existingCatKeys = array_map('strtolower', $db->query('SELECT name FROM kb_categories')->fetchAll(PDO::FETCH_COLUMN));

    $newCategories      = [];
    $existingCategories = [];
    foreach ($data['categories'] as $cat) {
        if (empty($cat['name'])) { continue; }
        if (in_array(strtolower($cat['name']), $existingCatKeys, true)) {
            $existingCategories[] = $cat['name'];
        } else {
            $newCategories[] = $cat['name'];
        }
    }

    $_SESSION['kb_json_import_data'] = $data;

    render('admin/kb/import-preview', [
        'layout'       => 'app',
        'pageTitle'    => 'Preview KB Import',
        'sidebarItems' => adminSidebar('kb'),
        'breadcrumbs'  => [
            ['label' => 'Admin',          'url' => '/admin'],
            ['label' => 'Knowledge Base', 'url' => '/admin/kb/articles'],
            ['label' => 'Import',         'url' => '/admin/kb/import'],
            ['label' => 'Preview'],
        ],
        'summary' => [
            'total_categories'   => $totalCategories,
            'total_folders'      => $totalFolders,
            'total_articles'     => $totalArticles,
            'new_categories'     => $newCategories,
            'existing_categories'=> $existingCategories,
            'draft_count'        => $draftCount,
            'published_count'    => $publishedCount,
        ],
        'previewArticles' => $previewArticles,
    ]);
});

$router->post('/admin/kb/import/confirm', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/import');
        return;
    }

    $data = $_SESSION['kb_json_import_data'] ?? null;
    unset($_SESSION['kb_json_import_data']);

    if (!is_array($data) || empty($data['categories'])) {
        flash('error', 'No import data found. Please upload the file again.');
        redirect('/admin/kb/import');
        return;
    }

    $db         = Database::connect();
    $publishAll = !empty($_POST['publish_all']);

    // Build lookup maps (find existing by name, case-insensitive)
    $catLookup = [];
    foreach ($db->query('SELECT id, name FROM kb_categories')->fetchAll() as $c) {
        $catLookup[strtolower($c['name'])] = (int) $c['id'];
    }
    $folderLookup = [];
    foreach ($db->query('SELECT id, category_id, name FROM kb_folders')->fetchAll() as $f) {
        $folderLookup[(int) $f['category_id'] . ':' . strtolower($f['name'])] = (int) $f['id'];
    }

    $db->beginTransaction();
    try {
        $insertCat = $db->prepare(
            'INSERT INTO kb_categories (name, slug, description, is_public, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $insertFolder = $db->prepare(
            'INSERT INTO kb_folders (category_id, name, slug, description, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $insertArticle = $db->prepare(
            'INSERT INTO kb_articles (folder_id, title, slug, body_markdown, status, published_at, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertRevision = $db->prepare(
            'INSERT INTO kb_article_revisions (article_id, title, body_markdown, edited_by) VALUES (?, ?, ?, ?)'
        );
        $checkCatSlug    = $db->prepare('SELECT id FROM kb_categories WHERE slug = ?');
        $checkFolderSlug = $db->prepare('SELECT id FROM kb_folders WHERE slug = ?');
        $checkArticleSlug = $db->prepare('SELECT id FROM kb_articles WHERE slug = ?');

        $imported     = 0;
        $nextCatOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order),0) FROM kb_categories')->fetchColumn();

        foreach ($data['categories'] as $cat) {
            if (empty($cat['name'])) { continue; }
            $catKey = strtolower($cat['name']);

            // Find or create category
            if (!isset($catLookup[$catKey])) {
                $nextCatOrder++;
                $catSlug = slugify($cat['name']);
                $checkCatSlug->execute([$catSlug]);
                if ($checkCatSlug->fetch()) {
                    $catSlug .= '-' . substr(md5(uniqid('', true)), 0, 6);
                }
                $insertCat->execute([
                    $cat['name'],
                    $catSlug,
                    $cat['description'] ?? null,
                    (int) ($cat['is_public'] ?? 0),
                    (int) ($cat['sort_order'] ?? $nextCatOrder),
                ]);
                $catLookup[$catKey] = (int) $db->lastInsertId();
            }
            $catId = $catLookup[$catKey];

            foreach ($cat['folders'] ?? [] as $folder) {
                if (empty($folder['name'])) { continue; }
                $folderKey = $catId . ':' . strtolower($folder['name']);

                // Find or create folder
                if (!isset($folderLookup[$folderKey])) {
                    $folderSlug = slugify($cat['name'] . '-' . $folder['name']);
                    $checkFolderSlug->execute([$folderSlug]);
                    if ($checkFolderSlug->fetch()) {
                        $folderSlug .= '-' . substr(md5(uniqid('', true)), 0, 6);
                    }
                    $insertFolder->execute([
                        $catId,
                        $folder['name'],
                        $folderSlug,
                        $folder['description'] ?? null,
                        (int) ($folder['sort_order'] ?? 0),
                    ]);
                    $folderLookup[$folderKey] = (int) $db->lastInsertId();
                }
                $folderId = $folderLookup[$folderKey];

                foreach ($folder['articles'] ?? [] as $article) {
                    if (empty($article['title']) || empty($article['body_markdown'])) { continue; }

                    $slug = slugify($article['title']);
                    $checkArticleSlug->execute([$slug]);
                    if ($checkArticleSlug->fetch()) {
                        $slug .= '-' . substr(md5(uniqid('', true)), 0, 6);
                    }

                    $status      = $publishAll ? 'published' : ($article['status'] ?? 'draft');
                    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

                    $insertArticle->execute([
                        $folderId,
                        $article['title'],
                        $slug,
                        $article['body_markdown'],
                        $status,
                        $publishedAt,
                        Auth::id(),
                        (int) ($article['sort_order'] ?? 0),
                    ]);
                    $articleId = (int) $db->lastInsertId();
                    $insertRevision->execute([$articleId, $article['title'], $article['body_markdown'], Auth::id()]);

                    $imported++;
                }
            }
        }

        $db->commit();
        flash('success', "Successfully imported {$imported} KB article(s).");
        redirect('/admin/kb/articles');
    } catch (PDOException $e) {
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/kb/import');
    }
});

/* ==================================================================
 * ADMIN – Settings (Email / SMTP Configuration)
 * ================================================================== */

$router->get('/admin/settings', function () {
    Auth::requireRole('admin');
    $keys = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'mail_from_address', 'mail_from_name', 'smtp_debug',
        'graph_enabled', 'graph_reply_to', 'graph_tenant_id', 'graph_client_id',
        'graph_client_secret', 'graph_mailbox', 'graph_secret_expires_at',
        'email_to_ticket_enabled', 'email_to_ticket_auto_create_users',
        'email_to_ticket_default_type_id', 'email_to_ticket_default_priority_id',
    ];
    $settings = [];
    foreach ($keys as $k) {
        $settings[$k] = getSetting($k);
    }
    // Retrieve and clear any stored processor run output
    $runOutput = $_SESSION['_processor_run'] ?? null;
    unset($_SESSION['_processor_run']);

    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();

    render('admin/settings/index', ['settings' => $settings, 'runOutput' => $runOutput, 'types' => $types, 'priorities' => $priorities]);
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
        'smtp_debug'       => isset($_POST['smtp_debug']) ? '1' : '0',
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

    // Save secret expiry date and reset reminder flags if date changed
    $newExpiry = trim($_POST['graph_secret_expires_at'] ?? '');
    if ($newExpiry !== '') {
        $existing = getSetting('graph_secret_expires_at', '');
        if ($newExpiry !== $existing) {
            setSetting('graph_secret_remind_1month', '0');
            setSetting('graph_secret_remind_1week',  '0');
            setSetting('graph_secret_remind_day',     '0');
        }
        setSetting('graph_secret_expires_at', $newExpiry);
    }

    // Ensure log directory exists
    @mkdir(ROOT_DIR . '/storage/logs', 0755, true);

    flash('success', 'Inbound mail settings saved.');
    redirect('/admin/settings');
});

$router->post('/admin/settings/email-to-ticket', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $fields = [
        'email_to_ticket_enabled'            => isset($_POST['email_to_ticket_enabled']) ? '1' : '0',
        'email_to_ticket_auto_create_users'  => isset($_POST['email_to_ticket_auto_create_users']) ? '1' : '0',
        'email_to_ticket_default_type_id'    => trim($_POST['email_to_ticket_default_type_id'] ?? ''),
        'email_to_ticket_default_priority_id' => trim($_POST['email_to_ticket_default_priority_id'] ?? ''),
    ];

    foreach ($fields as $key => $value) {
        setSetting($key, $value);
    }

    flash('success', 'Email-to-Ticket settings saved.');
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
    $cmd    = escapeshellarg(phpBinary()) . ' ' . escapeshellarg($script) . ' 2>&1';

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
        'LocalDesk - Test Email',
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
        'email_subject_ticket_created',   'email_intro_ticket_created',   'email_button_ticket_created',
        'email_subject_ticket_updated',   'email_intro_ticket_updated',   'email_button_ticket_updated',
        'email_subject_ticket_merged',    'email_intro_ticket_merged',    'email_button_ticket_merged',
        'email_subject_csat_survey',      'email_intro_csat_survey',
        'email_subject_ticket_reminder',  'email_intro_ticket_reminder',  'email_button_ticket_reminder',
        'email_subject_group_alerts',     'email_intro_group_alerts',     'email_button_group_alerts',
        'email_footer_text',
    ];
    $tplValues = [];
    foreach ($keys as $k) {
        $tplValues[$k] = getSetting($k);
    }

    $db = Database::connect();
    $groups = $db->query(
        'SELECT g.id, g.name, g.notify_new_ticket, COUNT(gum.user_id) AS member_count
         FROM `groups` g
         LEFT JOIN group_user_map gum ON gum.group_id = g.id
         GROUP BY g.id
         ORDER BY g.sort_order, g.name'
    )->fetchAll();

    render('admin/settings/email-templates', ['tplValues' => $tplValues, 'groups' => $groups]);
});

$router->post('/admin/settings/email-templates', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/email-templates');
    }

    $tab = $_POST['tab'] ?? 'ticket_created';

    // Reset buttons clear settings back to default (empty = use hardcoded default)
    if (isset($_POST['reset_template']) && in_array($_POST['reset_template'], ['ticket_created', 'ticket_updated', 'ticket_merged', 'csat_survey', 'ticket_reminder', 'group_alerts'], true)) {
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
    } elseif ($tab === 'group_alerts') {
        setSetting('email_subject_group_alerts', trim($_POST['email_subject_group_alerts'] ?? ''));
        setSetting('email_intro_group_alerts',   trim($_POST['email_intro_group_alerts']   ?? ''));
        setSetting('email_button_group_alerts',  trim($_POST['email_button_group_alerts']  ?? ''));
        // Save notify_new_ticket flag for each group
        $db2 = Database::connect();
        $allGroups = $db2->query('SELECT id FROM `groups`')->fetchAll(PDO::FETCH_COLUMN);
        $checked   = $_POST['notify_group'] ?? [];
        $upd = $db2->prepare('UPDATE `groups` SET notify_new_ticket = ? WHERE id = ?');
        foreach ($allGroups as $gid) {
            $upd->execute([isset($checked[(string) $gid]) ? 1 : 0, (int) $gid]);
        }
        flash('success', 'Group alerts settings saved.');
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
 * ADMIN – Email Notifications Settings
 * ================================================================== */

$router->get('/admin/settings/email-notifications', function () {
    Auth::requireRole('admin');

    $keys = [
        'agent_new_ticket', 'agent_assigned_group', 'agent_assigned_agent',
        'agent_requester_reply', 'agent_note_added',
        'requester_new_ticket', 'requester_agent_comment',
        'requester_ticket_resolved', 'requester_ticket_closed',
        'cc_new_ticket', 'cc_note_added',
    ];

    $settings = [];
    foreach ($keys as $k) {
        $settings[$k] = getSetting('email_notify:' . $k, '1');
    }

    render('admin/settings/email-notifications', ['settings' => $settings]);
});

$router->post('/admin/settings/email-notifications', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/email-notifications');
    }

    $keys = [
        'agent_new_ticket', 'agent_assigned_group', 'agent_assigned_agent',
        'agent_requester_reply', 'agent_note_added',
        'requester_new_ticket', 'requester_agent_comment',
        'requester_ticket_resolved', 'requester_ticket_closed',
        'cc_new_ticket', 'cc_note_added',
    ];

    foreach ($keys as $k) {
        setSetting('email_notify:' . $k, isset($_POST[$k]) ? '1' : '0');
    }

    flash('success', 'Email notification settings saved.');
    redirect('/admin/settings/email-notifications');
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
 * ADMIN – Holidays / Closed Days Settings
 * ================================================================== */

$router->get('/admin/settings/holidays', function () {
    Auth::requireRole('admin');
    $db = Database::connect();
    $holidays = $db->query('SELECT * FROM holidays ORDER BY holiday_date ASC')->fetchAll();
    render('admin/settings/holidays', ['holidays' => $holidays]);
});

$router->post('/admin/settings/holidays', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/holidays');
    }

    $dateStr = trim($_POST['holiday_date'] ?? '');
    $name    = trim($_POST['name'] ?? '');
    $exclude = isset($_POST['exclude_from_sla']) ? 1 : 0;

    $parsed = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$parsed || $parsed->format('Y-m-d') !== $dateStr) {
        flash('error', 'Please enter a valid date.');
        redirect('/admin/settings/holidays');
    }
    if ($name === '') {
        flash('error', 'Please enter a name for the holiday.');
        redirect('/admin/settings/holidays');
    }

    $db = Database::connect();
    try {
        $db->prepare('INSERT INTO holidays (holiday_date, name, exclude_from_sla) VALUES (?, ?, ?)')
           ->execute([$dateStr, $name, $exclude]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            flash('error', 'A holiday is already defined for that date.');
        } else {
            flash('error', 'Could not save holiday.');
        }
        redirect('/admin/settings/holidays');
    }

    Sla::recalculateAll($db);
    flash('success', 'Holiday added.');
    redirect('/admin/settings/holidays');
});

$router->post('/admin/settings/holidays/delete', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/holidays');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $db = Database::connect();
    $db->prepare('DELETE FROM holidays WHERE id = ?')->execute([$id]);

    Sla::recalculateAll($db);
    flash('success', 'Holiday removed.');
    redirect('/admin/settings/holidays');
});

$router->post('/admin/settings/holidays/toggle', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/holidays');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $db = Database::connect();
    $db->prepare('UPDATE holidays SET exclude_from_sla = 1 - exclude_from_sla WHERE id = ?')->execute([$id]);

    Sla::recalculateAll($db);
    redirect('/admin/settings/holidays');
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
    $skippedFile = $_SESSION['import_skipped_file'] ?? null;
    render('admin/settings/import', [
        'hasSkippedDownload' => $skippedFile !== null && file_exists($skippedFile),
    ]);
});

$router->get('/admin/settings/import/download-skipped', function () {
    Auth::requireRole('admin');
    $filePath = $_SESSION['import_skipped_file'] ?? '';
    if ($filePath === '' || !file_exists($filePath)) {
        flash('error', 'Skipped rows file not found or already downloaded.');
        redirect('/admin/settings/import');
    }
    unset($_SESSION['import_skipped_file']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="import_skipped_rows.csv"');
    header('Cache-Control: no-store');
    readfile($filePath);
    @unlink($filePath);
    exit;
});

$router->post('/admin/settings/import/preview', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import');
    }

    // Validate upload
    $uploadErr = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadErr !== UPLOAD_ERR_OK) {
        $errMsg = match ($uploadErr) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the allowed upload size. Check your server upload limits.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            default => 'Upload error (code ' . $uploadErr . ').',
        };
        flash('error', $errMsg);
        redirect('/admin/settings/import');
    }
    if ($_FILES['csv_file']['size'] > 64 * 1024 * 1024) {
        flash('error', 'File is too large. Maximum 64 MB.');
        redirect('/admin/settings/import');
    }

    // Save file to disk — avoids storing large CSV data in the PHP session
    $storageDir = ROOT_DIR . '/storage/imports/';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        flash('error', 'Import directory could not be created. On the server run: mkdir -p storage/imports && chmod -R 775 storage/');
        redirect('/admin/settings/import');
    }
    if (!is_writable($storageDir)) {
        flash('error', 'Import directory is not writable. On the server run: chmod -R 775 storage/ && chown -R www-data:www-data storage/');
        redirect('/admin/settings/import');
    }
    $importId   = bin2hex(random_bytes(16));
    $importPath = $storageDir . $importId . '.csv';
    $tmpPath    = $_FILES['csv_file']['tmp_name'];
    // move_uploaded_file() can fail when destination is on a network path;
    // fall back to copy() in that case.
    if (!move_uploaded_file($tmpPath, $importPath) && !copy($tmpPath, $importPath)) {
        flash('error', 'Could not save the uploaded file to storage/imports/. Check web server write permissions.');
        redirect('/admin/settings/import');
    }

    $handle = fopen($importPath, 'r');
    if (!$handle) {
        @unlink($importPath);
        flash('error', 'Could not read the uploaded file.');
        redirect('/admin/settings/import');
    }

    // Auto-detect delimiter: read the first line and pick the most common candidate
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = ',';
    if ($firstLine !== false) {
        $counts = [
            ','  => substr_count($firstLine, ','),
            "\t" => substr_count($firstLine, "\t"),
            ';'  => substr_count($firstLine, ';'),
            '|'  => substr_count($firstLine, '|'),
        ];
        arsort($counts);
        $delimiter = key($counts) ?: ',';
    }

    // Read header row, strip UTF-8 BOM
    $header = fgetcsv($handle, 0, $delimiter);
    if (!$header || count($header) < 2) {
        fclose($handle);
        @unlink($importPath);
        flash('error', 'The CSV file appears to be empty or has too few columns. Ensure the file has a header row and uses comma, tab, or semicolon as its delimiter.');
        redirect('/admin/settings/import');
    }
    $header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

    // Read up to 3 sample rows for the column-mapping preview
    $sampleRows = [];
    $hasData    = false;
    while (count($sampleRows) < 3 && ($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($header as $i => $col) {
            $row[$col] = trim($csvRow[$i] ?? '');
        }
        $sampleRows[] = $row;
        $hasData = true;
    }
    fclose($handle);

    if (!$hasData) {
        @unlink($importPath);
        flash('error', 'No data rows found in the CSV file.');
        redirect('/admin/settings/import');
    }

    // Clean up any previous import file still on disk
    if (!empty($_SESSION['import_file']) && file_exists($_SESSION['import_file'])) {
        @unlink($_SESSION['import_file']);
    }

    unset($_SESSION['import_raw'], $_SESSION['import_data'], $_SESSION['import_summary'], $_SESSION['import_mapping']);
    $_SESSION['import_file']        = $importPath;
    $_SESSION['import_delimiter']   = $delimiter;
    $_SESSION['import_headers']     = $header;
    $_SESSION['import_sample_rows'] = $sampleRows;

    redirect('/admin/settings/import/map');
});

$router->get('/admin/settings/import/map', function () {
    Auth::requireRole('admin');
    if (empty($_SESSION['import_headers'])) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import');
    }

    $headers    = $_SESSION['import_headers'];
    $sampleRows = $_SESSION['import_sample_rows'] ?? [];

    $systemFields = [
        ['key' => 'subject',      'label' => 'Subject',             'required' => true],
        ['key' => 'description',  'label' => 'Description',         'required' => false],
        ['key' => 'legacy_id',    'label' => 'Ticket ID (Legacy)',   'required' => false],
        ['key' => 'email',        'label' => 'Submitter Email',      'required' => true],
        ['key' => 'full_name',    'label' => 'Submitter Name',       'required' => false],
        ['key' => 'status',       'label' => 'Status',               'required' => false],
        ['key' => 'priority',     'label' => 'Priority',             'required' => false],
        ['key' => 'agent',        'label' => 'Assigned Agent',       'required' => false],
        ['key' => 'group',        'label' => 'Group',                'required' => false],
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
        'full_name'   => ['full name', 'full_name', 'name', 'customer name', 'requester name', 'submitter',
                          'submitted by (if using shared computer)', 'requester'],
        'status'      => ['status', 'ticket status', 'state', 'resolution status'],
        'priority'    => ['priority', 'priority name', 'urgency'],
        'agent'       => ['agent', 'assigned agent', 'assignee', 'agent name', 'assigned to'],
        'group'       => ['group', 'group name', 'team'],
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
        'sampleRows'   => $sampleRows,
    ]);
});

$router->post('/admin/settings/import/map', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import');
    }

    if (empty($_SESSION['import_file']) || !file_exists($_SESSION['import_file'])) {
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

    $importPath  = $_SESSION['import_file'];
    $delimiter   = $_SESSION['import_delimiter'] ?? ',';
    $fileHeaders = $_SESSION['import_headers'] ?? [];

    $handle = fopen($importPath, 'r');
    if (!$handle) {
        flash('error', 'Could not read the import file. Please upload again.');
        redirect('/admin/settings/import');
    }
    fgetcsv($handle, 0, $delimiter); // skip header row

    // Build DB lookups for summary stats
    $db = Database::connect();
    $existingUsers = [];
    foreach ($db->query("SELECT id, email, TRIM(CONCAT(first_name, ' ', last_name)) AS full_name FROM users")->fetchAll() as $u) {
        $existingUsers[strtolower($u['email'])] = true;
        $existingUsers['name:' . strtolower($u['full_name'])] = true;
    }
    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = true;
    }

    $totalRows        = 0;
    $newUserEmails    = [];
    $newAgentNames    = [];
    $newLocationNames = [];

    // Stream the file row-by-row — never load all rows into memory
    while (($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) {
            continue;
        }
        $rawRow = [];
        foreach ($fileHeaders as $i => $col) {
            $rawRow[$col] = trim($csvRow[$i] ?? '');
        }
        $get = function (string $fieldKey) use ($rawRow, $userMapping): string {
            $col = $userMapping[$fieldKey] ?? '';
            return $col !== '' ? trim($rawRow[$col] ?? '') : '';
        };

        $subject = $get('subject');
        $email   = strtolower($get('email'));
        if ($subject === '' || $email === '') {
            continue;
        }
        $totalRows++;

        if (!isset($existingUsers[$email])) {
            $newUserEmails[$email] = $get('full_name');
        }
        $agent = $get('agent');
        if ($agent !== '' && $agent !== 'No Agent' && !isset($existingUsers['name:' . strtolower($agent)])) {
            $newAgentNames[strtolower($agent)] = $agent;
        }
        $location = $get('location');
        if ($location !== '' && !isset($existingLocations[strtolower($location)])) {
            $newLocationNames[strtolower($location)] = $location;
        }
    }
    fclose($handle);

    if ($totalRows === 0) {
        flash('error', 'No valid rows found after applying the mapping. Ensure Subject and Submitter Email columns contain data.');
        redirect('/admin/settings/import/map');
    }

    $_SESSION['import_mapping'] = $userMapping;
    $_SESSION['import_summary'] = [
        'total_tickets'     => $totalRows,
        'new_users'         => count($newUserEmails),
        'new_agents'        => count($newAgentNames),
        'new_locations'     => count($newLocationNames),
        'new_user_list'     => array_values($newUserEmails),
        'new_agent_list'    => array_values($newAgentNames),
        'new_location_list' => array_values($newLocationNames),
    ];
    unset($_SESSION['import_data']);
    redirect('/admin/settings/import/preview');
});

$router->get('/admin/settings/import/preview', function () {
    Auth::requireRole('admin');
    $summary = $_SESSION['import_summary'] ?? null;
    if (!$summary || empty($_SESSION['import_file'])) {
        flash('error', 'No import data found. Please start the import again.');
        redirect('/admin/settings/import');
    }

    // Stream up to 15 preview rows from disk — no row data stored in session
    $previewRows = [];
    $importPath  = $_SESSION['import_file'];
    $delimiter   = $_SESSION['import_delimiter'] ?? ',';
    $fileHeaders = $_SESSION['import_headers'] ?? [];
    $userMapping = $_SESSION['import_mapping'] ?? [];
    $statusMap   = [
        'open' => 'open', 'new' => 'open', 'closed' => 'closed',
        'pending' => 'pending', 'resolved' => 'resolved',
        'in progress' => 'in_progress', 'in_progress' => 'in_progress',
        'waiting' => 'waiting_on_customer',
        'waiting on customer' => 'waiting_on_customer',
        'waiting on third party' => 'waiting_on_third_party',
    ];

    if (file_exists($importPath) && !empty($fileHeaders)) {
        $handle = fopen($importPath, 'r');
        if ($handle) {
            fgetcsv($handle, 0, $delimiter); // skip header row
            while (count($previewRows) < 15 && ($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) continue;
                $rawRow = [];
                foreach ($fileHeaders as $i => $col) {
                    $rawRow[$col] = trim($csvRow[$i] ?? '');
                }
                $get = function (string $k) use ($rawRow, $userMapping): string {
                    $col = $userMapping[$k] ?? '';
                    return $col !== '' ? trim($rawRow[$col] ?? '') : '';
                };
                $subject = $get('subject');
                $email   = strtolower($get('email'));
                if ($subject === '' || $email === '') continue;
                $statusRaw   = strtolower($get('status'));
                $previewRows[] = [
                    'legacy_id'      => $get('legacy_id'),
                    'subject'        => $subject,
                    'description'    => $get('description'),
                    'email'          => $email,
                    'submitter_name' => $get('full_name'),
                    'status'         => $statusMap[$statusRaw] ?? 'open',
                    'priority'       => $get('priority'),
                    'agent'          => $get('agent'),
                    'group'          => $get('group'),
                    'type'           => $get('type'),
                    'location'       => $get('location'),
                    'created_at'     => $get('created_at'),
                    'due_date'       => $get('due_date'),
                    'updated_at'     => $get('updated_at'),
                    'responded_at'   => $get('responded_at'),
                    'tags'           => $get('tags'),
                ];
            }
            fclose($handle);
        }
    }

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

    $importPath  = $_SESSION['import_file'] ?? '';
    $delimiter   = $_SESSION['import_delimiter'] ?? ',';
    $fileHeaders = $_SESSION['import_headers'] ?? [];
    $userMapping = $_SESSION['import_mapping'] ?? [];

    if ($importPath === '' || !file_exists($importPath) || empty($fileHeaders) || empty($userMapping)) {
        flash('error', 'No import data found. Please upload the CSV again.');
        redirect('/admin/settings/import');
    }

    $handle = fopen($importPath, 'r');
    if (!$handle) {
        flash('error', 'Could not read the import file. Please upload again.');
        redirect('/admin/settings/import');
    }
    fgetcsv($handle, 0, $delimiter); // skip header row

    $statusMap = [
        'open'                   => 'open',
        'new'                    => 'open',
        'closed'                 => 'closed',
        'pending'                => 'pending',
        'resolved'               => 'resolved',
        'in progress'            => 'in_progress',
        'in_progress'            => 'in_progress',
        'waiting'                => 'waiting_on_customer',
        'waiting on customer'    => 'waiting_on_customer',
        'waiting on third party' => 'waiting_on_third_party',
    ];

    // Robust date parser — interprets $raw as a datetime in $sourceTz, stores as UTC.
    // Pass the location's timezone as $sourceTz so imported timestamps from each
    // location are correctly normalised to UTC for storage.
    $parseDateTime = function (string $raw, string $sourceTz = 'UTC'): ?string {
        if ($raw === '') return null;
        try {
            $dt = new DateTime($raw, new DateTimeZone($sourceTz));
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $ts = strtotime($raw);
            return ($ts !== false && $ts > 0) ? gmdate('Y-m-d H:i:s', $ts) : null;
        }
    };
    $parseDateOnly = function (string $raw): ?string {
        if ($raw === '') return null;
        $ts = strtotime($raw);
        return ($ts !== false && $ts > 0) ? date('Y-m-d', $ts) : null;
    };

    $db = Database::connect();

    // Load all lookups
    $existingUsers = [];
    foreach ($db->query("SELECT id, email, TRIM(CONCAT(first_name, ' ', last_name)) AS full_name FROM users")->fetchAll() as $u) {
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
    $existingGroups = [];
    foreach ($db->query('SELECT id, name FROM `groups`')->fetchAll() as $g) {
        $existingGroups[strtolower($g['name'])] = (int) $g['id'];
    }
    $existingPriorities = [];
    foreach ($db->query('SELECT id, name FROM ticket_priorities')->fetchAll() as $p) {
        $existingPriorities[strtolower($p['name'])] = (int) $p['id'];
    }
    $existingTags = [];
    foreach ($db->query('SELECT id, name FROM ticket_tags')->fetchAll() as $t) {
        $existingTags[strtolower($t['name'])] = (int) $t['id'];
    }

    $imported    = 0;
    $skipped     = 0;
    $skippedRows = []; // raw CSV rows that couldn't be imported, available for download
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $db->beginTransaction();
    try {
        $insertUser = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role, location_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertLocation = $db->prepare('INSERT INTO locations (name) VALUES (?)');
        $insertType     = $db->prepare('INSERT INTO ticket_types (name, sort_order) VALUES (?, ?)');
        $insertTicket   = $db->prepare(
            'INSERT INTO tickets (subject, description, legacy_id, created_by, created_at, due_date, type_id, location_id, status, priority_id, assigned_to, group_id, first_responded_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertTimeline = $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at) VALUES (?, ?, ?, ?, 0, ?)'
        );
        $insertTag    = $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)');
        $insertTagMap = $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)');

        $nextTypeOrder = (int) ($db->query('SELECT COALESCE(MAX(sort_order), 0) FROM ticket_types')->fetchColumn()) + 1;

        // Stream rows from disk — no large session data
        while (($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) continue;

            $rawRow = [];
            foreach ($fileHeaders as $i => $col) {
                $rawRow[$col] = trim($csvRow[$i] ?? '');
            }
            $get = function (string $fieldKey) use ($rawRow, $userMapping): string {
                $col = $userMapping[$fieldKey] ?? '';
                return $col !== '' ? trim($rawRow[$col] ?? '') : '';
            };

            $subject = $get('subject');
            $email   = strtolower($get('email'));
            if ($subject === '' || $email === '') {
                $reason = $subject === '' && $email === '' ? 'Missing subject and email'
                        : ($subject === '' ? 'Missing subject' : 'Missing email');
                $skippedRows[] = ['_reason' => $reason] + $rawRow;
                $skipped++;
                continue;
            }

            $statusRaw = strtolower($get('status'));
            $row = [
                'legacy_id'      => $get('legacy_id'),
                'subject'        => $subject,
                'description'    => $get('description'),
                'email'          => $email,
                'submitter_name' => $get('full_name'),
                'status'         => $statusMap[$statusRaw] ?? 'open',
                'priority'       => $get('priority'),
                'agent'          => $get('agent'),
                'group'          => $get('group'),
                'type'           => $get('type'),
                'location'       => $get('location'),
                'created_at'     => $get('created_at'),
                'due_date'       => $get('due_date'),
                'updated_at'     => $get('updated_at'),
                'responded_at'   => $get('responded_at'),
                'tags'           => $get('tags'),
            ];

            // --- Resolve submitter ---
            $creatorId = null;
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

            if ($creatorId === null) {
                $skipped++;
                continue;
            }

            // --- Resolve agent ---
            $agentId   = null;
            $agentName = $row['agent'];
            if ($agentName !== '' && $agentName !== 'No Agent') {
                $agentKey   = 'name:' . strtolower($agentName);
                $agentEmail = strtolower(str_replace(' ', '.', $agentName)) . '@imported.local';
                if (isset($existingUsers[$agentKey])) {
                    $agentId = $existingUsers[$agentKey];
                } elseif (isset($existingUsers[$agentEmail])) {
                    // Match by generated email — covers re-imports where TRIM mismatch occurred
                    $agentId = $existingUsers[$agentEmail];
                    $existingUsers[$agentKey] = $agentId;
                } else {
                    $nameParts = splitFullName($agentName);
                    $insertUser->execute([$nameParts[0], $nameParts[1], $agentEmail, $randomPassword, 'agent', null]);
                    $agentId = (int) $db->lastInsertId();
                    $existingUsers[$agentKey]  = $agentId;
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
            $priorityId = $row['priority'] !== '' ? ($existingPriorities[strtolower($row['priority'])] ?? null) : null;

            // --- Resolve group ---
            $groupId = ($row['group'] ?? '') !== '' ? ($existingGroups[strtolower($row['group'])] ?? null) : null;

            // --- Parse dates — treat source timestamps as being in the location's timezone ---
            $locationTz  = getLocationTimezone($locationId);
            $createdAt   = $parseDateTime($row['created_at'], $locationTz) ?? gmdate('Y-m-d H:i:s');
            $dueDate     = $parseDateOnly($row['due_date']);
            $updatedAt   = $parseDateTime($row['updated_at'], $locationTz) ?? $createdAt;
            $respondedAt = $parseDateTime($row['responded_at'], $locationTz);

            // --- Insert ticket ---
            $insertTicket->execute([
                $row['subject'],
                $row['description'] !== '' ? $row['description'] : '(Imported from legacy system)',
                $row['legacy_id'] !== '' ? $row['legacy_id'] : null,
                $creatorId,
                $createdAt,
                $dueDate,
                $typeId,
                $locationId,
                $row['status'],
                $priorityId,
                $agentId,
                $groupId,
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

        fclose($handle);
        $db->commit();
    } catch (PDOException $e) {
        fclose($handle);
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/settings/import');
    }

    // Clean up disk file and session import state
    @unlink($importPath);
    unset(
        $_SESSION['import_file'],
        $_SESSION['import_delimiter'],
        $_SESSION['import_headers'],
        $_SESSION['import_sample_rows'],
        $_SESSION['import_mapping'],
        $_SESSION['import_summary'],
        $_SESSION['import_skipped_file']
    );

    // Write skipped rows to a downloadable CSV if any were skipped
    if (!empty($skippedRows)) {
        $skippedPath = ROOT_DIR . '/storage/imports/' . bin2hex(random_bytes(8)) . '_skipped.csv';
        $fp = fopen($skippedPath, 'w');
        if ($fp) {
            // Header: reason column first, then original CSV columns
            $csvHeaders = array_keys($skippedRows[0]);
            fputcsv($fp, array_map(fn($h) => $h === '_reason' ? 'Skipped Reason' : $h, $csvHeaders));
            foreach ($skippedRows as $sr) {
                fputcsv($fp, array_values($sr));
            }
            fclose($fp);
            $_SESSION['import_skipped_file'] = $skippedPath;
        }
    }

    $msg = "Successfully imported {$imported} ticket(s).";
    if ($skipped > 0) {
        $msg .= " {$skipped} row(s) were skipped — see the import page to download them.";
    }
    flash('success', $msg);
    redirect('/admin/tickets');
});

/* ==================================================================
 * ADMIN – Import Users from CSV
 * ================================================================== */

$router->get('/admin/settings/import-users', function () {
    Auth::requireRole('admin');
    render('admin/settings/import-users');
});

$router->post('/admin/settings/import-users/preview', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-users');
    }

    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please select a valid CSV file.');
        redirect('/admin/settings/import-users');
    }
    if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
        flash('error', 'File is too large. Maximum 10 MB.');
        redirect('/admin/settings/import-users');
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        flash('error', 'Could not read the uploaded file.');
        redirect('/admin/settings/import-users');
    }

    $header = fgetcsv($handle);
    if (!$header || count($header) < 1) {
        fclose($handle);
        flash('error', 'The CSV file appears to be empty or has no columns.');
        redirect('/admin/settings/import-users');
    }
    $header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

    $rawRows = [];
    while (($csvRow = fgetcsv($handle)) !== false) {
        if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) {
            continue;
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
        redirect('/admin/settings/import-users');
    }

    $_SESSION['user_import_raw'] = ['headers' => $header, 'rows' => $rawRows];
    unset($_SESSION['user_import_data'], $_SESSION['user_import_summary']);
    redirect('/admin/settings/import-users/map');
});

$router->get('/admin/settings/import-users/map', function () {
    Auth::requireRole('admin');
    $raw = $_SESSION['user_import_raw'] ?? null;
    if (!$raw) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import-users');
    }

    $headers = $raw['headers'];

    $systemFields = [
        ['key' => 'email',      'label' => 'Email Address',  'required' => true,  'hint' => 'Required'],
        ['key' => 'full_name',  'label' => 'Full Name',       'required' => false, 'hint' => 'Used if First/Last Name not mapped'],
        ['key' => 'first_name', 'label' => 'First Name',      'required' => false, 'hint' => null],
        ['key' => 'last_name',  'label' => 'Last Name',       'required' => false, 'hint' => null],
        ['key' => 'role',       'label' => 'Role',             'required' => false, 'hint' => 'user / agent / admin'],
        ['key' => 'work_phone', 'label' => 'Work Phone',      'required' => false, 'hint' => null],
        ['key' => 'location',   'label' => label('location.singular'), 'required' => false, 'hint' => 'Matched by name; created if missing'],
    ];

    $aliases = [
        'email'      => ['email', 'email address', 'e-mail', 'user email', 'work email', 'mail'],
        'full_name'  => ['full name', 'full_name', 'name', 'display name', 'contact name'],
        'first_name' => ['first name', 'first_name', 'given name', 'firstname', 'first'],
        'last_name'  => ['last name', 'last_name', 'surname', 'family name', 'lastname', 'last'],
        'role'       => ['role', 'user role', 'account type', 'type', 'access level'],
        'work_phone' => ['phone', 'work phone', 'telephone', 'work_phone', 'phone number', 'tel'],
        'location'   => ['location', 'branch', 'department', 'site', 'office'],
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

    render('admin/settings/import-users-map', [
        'headers'      => $headers,
        'systemFields' => $systemFields,
        'autoMapping'  => $autoMapping,
        'sampleRows'   => array_slice($raw['rows'], 0, 3),
    ]);
});

$router->post('/admin/settings/import-users/map', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-users');
    }

    $raw = $_SESSION['user_import_raw'] ?? null;
    if (!$raw) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import-users');
    }

    $userMapping = $_POST['mapping'] ?? [];
    $emailCol    = $userMapping['email'] ?? '';

    if ($emailCol === '') {
        flash('error', 'Email Address is required. Please map it to a CSV column.');
        redirect('/admin/settings/import-users/map');
    }

    $db = Database::connect();
    $existingEmails = [];
    foreach ($db->query('SELECT LOWER(email) AS email FROM users')->fetchAll(PDO::FETCH_COLUMN) as $e) {
        $existingEmails[$e] = true;
    }
    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = $l['id'];
    }

    $validRoles = ['user', 'agent', 'power_user', 'admin'];
    $rows = [];
    $duplicateEmails  = [];
    $newLocationNames = [];

    foreach ($raw['rows'] as $rawRow) {
        $get = function (string $fieldKey) use ($rawRow, $userMapping): string {
            $col = $userMapping[$fieldKey] ?? '';
            return $col !== '' ? trim($rawRow[$col] ?? '') : '';
        };

        $email = strtolower($get('email'));
        if ($email === '') {
            continue;
        }

        $isDuplicate = isset($existingEmails[$email]);
        if ($isDuplicate) {
            $duplicateEmails[$email] = true;
        }

        // Resolve name: prefer first_name + last_name, fall back to full_name split
        $firstName = $get('first_name');
        $lastName  = $get('last_name');
        $fullName  = $get('full_name');
        if ($firstName === '' && $lastName === '' && $fullName !== '') {
            [$firstName, $lastName] = splitFullName($fullName);
        }

        $roleRaw = strtolower($get('role'));
        $role    = in_array($roleRaw, $validRoles, true) ? $roleRaw : 'user';

        $location = $get('location');
        if ($location !== '' && !isset($existingLocations[strtolower($location)])) {
            $newLocationNames[strtolower($location)] = $location;
        }

        $rows[] = [
            'email'        => $email,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'role'         => $role,
            'work_phone'   => $get('work_phone'),
            'location'     => $location,
            'is_duplicate' => $isDuplicate,
        ];
    }

    if (empty($rows)) {
        flash('error', 'No valid rows found after applying the mapping. Ensure the Email column contains data.');
        redirect('/admin/settings/import-users/map');
    }

    $newCount = count(array_filter($rows, fn($r) => !$r['is_duplicate']));

    $_SESSION['user_import_data']    = $rows;
    $_SESSION['user_import_summary'] = [
        'total_rows'       => count($rows),
        'new_users'        => $newCount,
        'duplicates'       => count($duplicateEmails),
        'new_locations'    => count($newLocationNames),
        'duplicate_list'   => array_keys($duplicateEmails),
        'new_location_list'=> array_values($newLocationNames),
    ];
    redirect('/admin/settings/import-users/preview');
});

$router->get('/admin/settings/import-users/preview', function () {
    Auth::requireRole('admin');
    $rows    = $_SESSION['user_import_data']    ?? null;
    $summary = $_SESSION['user_import_summary'] ?? null;
    if (!$rows || !$summary) {
        flash('error', 'No import data found. Please start the import again.');
        redirect('/admin/settings/import-users');
    }
    render('admin/settings/import-users-preview', [
        'summary'     => $summary,
        'previewRows' => array_slice($rows, 0, 15),
    ]);
});

$router->post('/admin/settings/import-users/confirm', function () {
    Auth::requireRole('admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-users');
    }

    $rows = $_SESSION['user_import_data'] ?? [];
    unset($_SESSION['user_import_data'], $_SESSION['user_import_summary']);

    if (empty($rows)) {
        flash('error', 'No import data found. Please upload the CSV again.');
        redirect('/admin/settings/import-users');
    }

    $db = Database::connect();

    $existingEmails = [];
    foreach ($db->query('SELECT LOWER(email) AS email FROM users')->fetchAll(PDO::FETCH_COLUMN) as $e) {
        $existingEmails[$e] = true;
    }
    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = (int) $l['id'];
    }

    $imported = 0;
    $skipped  = 0;
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $db->beginTransaction();
    try {
        $insertUser = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role, work_phone, location_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insertLocation = $db->prepare('INSERT INTO locations (name) VALUES (?)');

        foreach ($rows as $row) {
            $email = $row['email'];
            if ($email === '' || isset($existingEmails[$email])) {
                $skipped++;
                continue;
            }

            // Resolve location
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

            $insertUser->execute([
                $row['first_name'],
                $row['last_name'],
                $email,
                $randomPassword,
                $row['role'],
                $row['work_phone'] !== '' ? $row['work_phone'] : null,
                $locationId,
            ]);
            $existingEmails[$email] = true;
            $imported++;
        }

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/settings/import-users');
    }

    $msg = "Successfully imported {$imported} user(s).";
    if ($skipped > 0) {
        $msg .= " {$skipped} row(s) skipped (duplicate email or missing email).";
    }
    flash('success', $msg);
    redirect('/admin/users');
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
    if ($header) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // strip UTF-8 BOM if present
    }
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
        'navbarIcon'          => getSetting('branding_navbar_icon', 'bi-person-raised-hand'),
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
    $navbarIconRaw        = trim($_POST['navbar_icon'] ?? 'bi-person-raised-hand');
    // Normalise: ensure it starts with bi- and only contains safe chars
    if (!str_starts_with($navbarIconRaw, 'bi-')) {
        $navbarIconRaw = 'bi-' . $navbarIconRaw;
    }
    $navbarIcon = 'bi-' . preg_replace('/[^a-zA-Z0-9\-]/', '', substr($navbarIconRaw, 3));
    if ($navbarIcon === 'bi-') {
        $navbarIcon = 'bi-person-raised-hand';
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
    $refData     = loadAutomationRefData($db);
    render('admin/automations/index', array_merge(['automations' => $automations], $refData));
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
        'agents'     => $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name")->fetchAll(),
        'allUsers'   => $db->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name")->fetchAll(),
    ];
}

/**
 * Build conditions structure from POST data (v2 group format).
 * Accepts a JSON blob submitted by the group-based condition builder.
 */
function buildAutomationConditions(array $post): array
{
    $raw = json_decode($post['conditions_json'] ?? '{}', true);
    if (!is_array($raw) || empty($raw['groups'])) {
        return ['v' => 2, 'groups' => []];
    }

    $validFields    = ['type_id', 'priority_id', 'status', 'location_id', 'group_id', 'assigned_to'];
    $validOperators = ['equals', 'not_equals', 'is_empty', 'is_not_empty'];

    $groups = [];
    foreach ($raw['groups'] as $rg) {
        $gMatch = ($rg['match'] ?? 'all') === 'any' ? 'any' : 'all';
        $conds  = [];
        foreach ($rg['conditions'] ?? [] as $c) {
            $f = $c['field']    ?? '';
            $o = $c['operator'] ?? '';
            $v = $c['value']    ?? '';
            if (in_array($f, $validFields, true) && in_array($o, $validOperators, true)) {
                $conds[] = ['field' => $f, 'operator' => $o, 'value' => $v];
            }
        }
        if (!empty($conds)) {
            $groups[] = ['match' => $gMatch, 'conditions' => $conds];
        }
    }

    return ['v' => 2, 'groups' => $groups];
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
    $cmd    = escapeshellarg(phpBinary()) . ' ' . escapeshellarg($script) . ' 2>&1';
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
        'agents'     => $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name")->fetchAll(),
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
    Auth::requireRole('admin', 'power_user');
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
