<?php

declare(strict_types=1);

/* ------------------------------------------------------------------
 * Health check
 * ------------------------------------------------------------------ */
$router->get('/health', function () {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
});

/* ------------------------------------------------------------------
 * Home – redirect authenticated users by role
 * ------------------------------------------------------------------ */
$router->get('/', function () {
    if (Auth::check()) {
        match (Auth::role()) {
            'admin' => redirect('/admin'),
            'agent' => redirect('/agent'),
            default => redirect('/portal'),
        };
    }
    render('home');
});

/* ------------------------------------------------------------------
 * Authentication
 * ------------------------------------------------------------------ */
$router->get('/login', function () {
    if (Auth::check()) {
        redirect('/');
    }
    render('login');
});

$router->post('/login', function () {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token    = $_POST['_token'] ?? '';

    if (!verifyCsrf($token)) {
        render('login', ['error' => 'Invalid request. Please try again.']);
    }

    if ($email === '' || $password === '') {
        render('login', ['error' => 'Please enter both email and password.', 'email' => $email]);
    }

    if (Auth::attempt($email, $password)) {
        session_regenerate_id(true);
        redirect('/');
    }

    render('login', ['error' => 'Invalid email or password.', 'email' => $email]);
});

$router->get('/logout', function () {
    Auth::logout();
    redirect('/login');
});

/* ------------------------------------------------------------------
 * Portal (all authenticated users)
 * ------------------------------------------------------------------ */
$router->get('/portal', function () {
    Auth::requireAuth();
    $db = Database::connect();
    $userId = Auth::id();

    $openCount = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_by = ? AND status IN ('open','in_progress')");
    $openCount->execute([$userId]);
    $openCount = (int) $openCount->fetchColumn();

    $resolvedCount = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_by = ? AND status IN ('resolved','closed')");
    $resolvedCount->execute([$userId]);
    $resolvedCount = (int) $resolvedCount->fetchColumn();

    $recentTickets = $db->prepare(
        "SELECT t.*, tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         WHERE t.created_by = ?
         ORDER BY t.created_at DESC LIMIT 5"
    );
    $recentTickets->execute([$userId]);
    $recentTickets = $recentTickets->fetchAll();

    render('portal/dashboard', [
        'openCount'     => $openCount,
        'resolvedCount' => $resolvedCount,
        'recentTickets' => $recentTickets,
    ]);
});

require ROOT_DIR . '/src/routes/portal.php';

/* ------------------------------------------------------------------
 * Notifications
 * ------------------------------------------------------------------ */
$router->get('/notifications', function () {
    Auth::requireAuth();
    $db = Database::connect();
    $stmt = $db->prepare(
        "SELECT n.*, t.subject AS ticket_subject,
                CONCAT(m.first_name, ' ', m.last_name) AS mentioned_by_name,
                tl.details AS message
         FROM notifications n
         JOIN tickets t          ON n.ticket_id   = t.id
         JOIN users m            ON n.mentioned_by = m.id
         JOIN ticket_timeline tl ON n.timeline_id  = tl.id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC
         LIMIT 50"
    );
    $stmt->execute([Auth::id()]);
    $notifications = $stmt->fetchAll();

    // Determine which area to render in based on role
    $role = Auth::role();
    if ($role === 'admin') {
        $sidebarFn = 'adminSidebar';
        $areaPrefix = '/admin';
    } elseif ($role === 'agent') {
        $sidebarFn = 'agentSidebar';
        $areaPrefix = '/agent';
    } else {
        $sidebarFn = 'portalSidebar';
        $areaPrefix = '/portal';
    }

    render('notifications', [
        'notifications' => $notifications,
        'sidebarFn'     => $sidebarFn,
        'areaPrefix'    => $areaPrefix,
    ]);
});

$router->get('/notifications/count', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');
    echo json_encode(['count' => notificationCount()]);
    exit;
});

$router->post('/notifications/{id}/read', function (array $p) {
    Auth::requireAuth();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/notifications');
    }
    $db = Database::connect();
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([(int) $p['id'], Auth::id()]);
    redirect('/notifications');
});

$router->post('/notifications/read-all', function () {
    Auth::requireAuth();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/notifications');
    }
    $db = Database::connect();
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
        ->execute([Auth::id()]);
    flash('success', 'All notifications marked as read.');
    redirect('/notifications');
});

/* ------------------------------------------------------------------
 * Agent area
 * ------------------------------------------------------------------ */
$router->get('/agent', function () {
    Auth::requireRole('agent', 'admin');
    $db = Database::connect();
    $agentId = Auth::id();

    $unassigned = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status IN ('open','in_progress','pending')")->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND status IN ('open','in_progress','pending')");
    $stmt->execute([$agentId]);
    $myTickets = (int) $stmt->fetchColumn();

    $pending = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'pending'")->fetchColumn();

    $resolvedToday = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()")->fetchColumn();

    // Recent tickets (open/in_progress/pending, newest first)
    $recent = $db->query(
        "SELECT t.id, t.subject, t.status, t.created_at,
                tp.name AS priority_name, tp.color AS priority_color,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN users c ON t.created_by = c.id
         LEFT JOIN users a ON t.assigned_to = a.id
         WHERE t.status IN ('open','in_progress','pending')
         ORDER BY t.created_at DESC
         LIMIT 10"
    )->fetchAll();

    render('agent/dashboard', [
        'unassigned'    => $unassigned,
        'myTickets'     => $myTickets,
        'pending'       => $pending,
        'resolvedToday' => $resolvedToday,
        'recentTickets' => $recent,
    ]);
});

require ROOT_DIR . '/src/routes/agent.php';

/* ------------------------------------------------------------------
 * Admin area
 * ------------------------------------------------------------------ */
$router->get('/admin', function () {
    Auth::requireRole('admin');
    $db = Database::connect();

    $totalTickets = (int) $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $openTickets = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress','pending')")->fetchColumn();
    $totalUsers = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalAgents = (int) $db->query("SELECT COUNT(*) FROM users WHERE role IN ('agent','admin')")->fetchColumn();

    // Recent activity (last 10 timeline entries)
    $recentActivity = $db->query(
        "SELECT tl.*, t.subject AS ticket_subject,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_timeline tl
         LEFT JOIN tickets t ON tl.ticket_id = t.id
         LEFT JOIN users u ON tl.user_id = u.id
         ORDER BY tl.created_at DESC
         LIMIT 10"
    )->fetchAll();

    render('admin/dashboard', [
        'totalTickets'   => $totalTickets,
        'openTickets'    => $openTickets,
        'totalUsers'     => $totalUsers,
        'totalAgents'    => $totalAgents,
        'recentActivity' => $recentActivity,
    ]);
});

require ROOT_DIR . '/src/routes/admin.php';
