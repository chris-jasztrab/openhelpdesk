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
 * Ticket Tag Management (JSON API)
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/tags', function (array $p) {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json');

    $ticketId = (int) $p['id'];
    $input    = json_decode(file_get_contents('php://input'), true);
    $action   = $input['action'] ?? '';
    $tagName  = trim(preg_replace('/[^a-zA-Z0-9_\-\s]/', '', strtolower($input['tag'] ?? '')));

    if ($tagName === '' || !in_array($action, ['add', 'remove'], true)) {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    $db = Database::connect();

    // Verify ticket exists
    $stmt = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Ticket not found']);
        exit;
    }

    if ($action === 'add') {
        // Find or create tag
        $findTag = $db->prepare('SELECT id FROM ticket_tags WHERE name = ?');
        $findTag->execute([$tagName]);
        $tagId = $findTag->fetchColumn();
        if (!$tagId) {
            $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)')->execute([$tagName]);
            $tagId = (int) $db->lastInsertId();
        }
        // Check if already linked
        $check = $db->prepare('SELECT 1 FROM ticket_tag_map WHERE ticket_id = ? AND tag_id = ?');
        $check->execute([$ticketId, $tagId]);
        if (!$check->fetch()) {
            $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)')->execute([$ticketId, $tagId]);
        }
    } else {
        // Remove tag link
        $findTag = $db->prepare('SELECT id FROM ticket_tags WHERE name = ?');
        $findTag->execute([$tagName]);
        $tagId = $findTag->fetchColumn();
        if ($tagId) {
            $db->prepare('DELETE FROM ticket_tag_map WHERE ticket_id = ? AND tag_id = ?')->execute([$ticketId, $tagId]);
        }
    }

    // Return updated tag list
    $tags = $db->prepare(
        'SELECT tt.name FROM ticket_tags tt
         INNER JOIN ticket_tag_map ttm ON tt.id = ttm.tag_id
         WHERE ttm.ticket_id = ? ORDER BY tt.name'
    );
    $tags->execute([$ticketId]);
    echo json_encode(['tags' => $tags->fetchAll(\PDO::FETCH_COLUMN)]);
    exit;
});

/* ------------------------------------------------------------------
 * User Search for CC (JSON API)
 * ------------------------------------------------------------------ */
$router->get('/api/user-search', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        http_response_code(403);
        echo json_encode([]);
        exit;
    }
    header('Content-Type: application/json');

    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) {
        echo json_encode([]);
        exit;
    }

    $like = '%' . $q . '%';
    $db   = Database::connect();
    $stmt = $db->prepare(
        "SELECT id, first_name, last_name, email, role
         FROM users
         WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
               OR CONCAT(first_name, ' ', last_name) LIKE ?
         ORDER BY first_name
         LIMIT 8"
    );
    $stmt->execute([$like, $like, $like, $like]);
    echo json_encode($stmt->fetchAll());
    exit;
});

/* ------------------------------------------------------------------
 * Ticket CC Management (JSON API)
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/cc', function (array $p) {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json');

    $ticketId = (int) $p['id'];
    $input    = json_decode(file_get_contents('php://input'), true);
    $action   = $input['action'] ?? '';
    $userId   = (int) ($input['user_id'] ?? 0);

    if ($userId <= 0 || !in_array($action, ['add', 'remove'], true)) {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    $db = Database::connect();

    // Verify ticket exists
    $stmt = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Ticket not found']);
        exit;
    }

    if ($action === 'add') {
        $check = $db->prepare('SELECT 1 FROM ticket_cc WHERE ticket_id = ? AND user_id = ?');
        $check->execute([$ticketId, $userId]);
        if (!$check->fetch()) {
            $db->prepare('INSERT INTO ticket_cc (ticket_id, user_id, added_by) VALUES (?, ?, ?)')
                ->execute([$ticketId, $userId, Auth::id()]);
        }
    } else {
        $db->prepare('DELETE FROM ticket_cc WHERE ticket_id = ? AND user_id = ?')
            ->execute([$ticketId, $userId]);
    }

    // Return updated CC list
    $cc = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role
         FROM ticket_cc tc
         JOIN users u ON tc.user_id = u.id
         WHERE tc.ticket_id = ?
         ORDER BY u.first_name'
    );
    $cc->execute([$ticketId]);
    echo json_encode(['cc' => $cc->fetchAll()]);
    exit;
});

/* ------------------------------------------------------------------
 * Mention Autocomplete (JSON API)
 * ------------------------------------------------------------------ */
$router->get('/api/mention-search', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        http_response_code(403);
        echo json_encode([]);
        exit;
    }
    header('Content-Type: application/json');

    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) {
        echo json_encode([]);
        exit;
    }

    $like = '%' . $q . '%';
    $db   = Database::connect();
    $stmt = $db->prepare(
        "SELECT id, first_name, last_name, role
         FROM users
         WHERE role IN ('agent','admin')
           AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
         ORDER BY first_name
         LIMIT 8"
    );
    $stmt->execute([$like, $like, $like]);
    echo json_encode($stmt->fetchAll());
    exit;
});

/* ------------------------------------------------------------------
 * Ticket Presence (Concurrent Viewer Tracking)
 * ------------------------------------------------------------------ */

// Register / refresh presence (ping every 20s)
$router->post('/api/tickets/{id}/presence', function (array $p) {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $db = Database::connect();
    $db->prepare(
        'INSERT INTO ticket_presence (ticket_id, user_id, last_seen) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE last_seen = NOW()'
    )->execute([(int) $p['id'], Auth::id()]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
});

// Get other active viewers of a ticket
$router->get('/api/tickets/{id}/presence', function (array $p) {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $db = Database::connect();
    // Clean up stale records (older than 45 seconds)
    $db->prepare(
        'DELETE FROM ticket_presence WHERE last_seen < DATE_SUB(NOW(), INTERVAL 45 SECOND)'
    )->execute();
    // Return other current viewers (excluding self)
    $stmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.role
         FROM ticket_presence tp
         JOIN users u ON u.id = tp.user_id
         WHERE tp.ticket_id = ? AND tp.user_id != ?'
    );
    $stmt->execute([(int) $p['id'], Auth::id()]);
    header('Content-Type: application/json');
    echo json_encode(['viewers' => $stmt->fetchAll()]);
    exit;
});

// Remove presence on page leave (called via sendBeacon)
$router->post('/api/tickets/{id}/presence/leave', function (array $p) {
    Auth::requireAuth();
    $db = Database::connect();
    $db->prepare(
        'DELETE FROM ticket_presence WHERE ticket_id = ? AND user_id = ?'
    )->execute([(int) $p['id'], Auth::id()]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
});

/* ------------------------------------------------------------------
 * Global Search (JSON API)
 * ------------------------------------------------------------------ */
$router->get('/search', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');

    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['tickets' => [], 'contacts' => [], 'kb' => []]);
        exit;
    }

    $type = $_GET['type'] ?? 'all';
    $db   = Database::connect();
    $like = '%' . $q . '%';
    $role = Auth::role();

    $result = ['tickets' => [], 'contacts' => [], 'kb' => []];

    // --- Tickets ---
    if ($type === 'all' || $type === 'tickets') {
        $ticketWhere = '(t.subject LIKE ? OR t.description LIKE ?)';
        $ticketParams = [$like, $like];

        // Regular users only see their own tickets
        if ($role === 'user') {
            $ticketWhere .= ' AND t.created_by = ?';
            $ticketParams[] = Auth::id();
        }

        $stmt = $db->prepare(
            "SELECT t.id, t.subject, t.status,
                    CONCAT(a.first_name, ' ', a.last_name) AS agent_name
             FROM tickets t
             LEFT JOIN users a ON t.assigned_to = a.id
             WHERE {$ticketWhere}
             ORDER BY t.created_at DESC
             LIMIT 5"
        );
        $stmt->execute($ticketParams);
        $result['tickets'] = $stmt->fetchAll();
    }

    // --- Contacts (admin/agent only) ---
    if (($type === 'all' || $type === 'contacts') && in_array($role, ['admin', 'agent'], true)) {
        $stmt = $db->prepare(
            "SELECT id, first_name, last_name, email, role
             FROM users
             WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                   OR CONCAT(first_name, ' ', last_name) LIKE ?
             ORDER BY first_name
             LIMIT 5"
        );
        $stmt->execute([$like, $like, $like, $like]);
        $result['contacts'] = $stmt->fetchAll();
    }

    // --- KB Articles ---
    if ($type === 'all' || $type === 'kb') {
        $stmt = $db->prepare(
            "SELECT a.title, a.slug, f.name AS folder_name, c.name AS category_name
             FROM kb_articles a
             LEFT JOIN kb_folders f    ON a.folder_id   = f.id
             LEFT JOIN kb_categories c ON f.category_id = c.id
             WHERE a.status = 'published' AND (a.title LIKE ? OR a.body_markdown LIKE ?)
             ORDER BY a.updated_at DESC
             LIMIT 5"
        );
        $stmt->execute([$like, $like]);
        $result['kb'] = $stmt->fetchAll();
    }

    echo json_encode($result);
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
 * Profile (all authenticated users)
 * ------------------------------------------------------------------ */

$router->get('/profile', function () {
    Auth::requireAuth();
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([Auth::id()]);
    $user = $stmt->fetch();

    if (!$user) {
        Auth::logout();
        redirect('/login');
    }

    $theme = getSetting('ui_theme:' . Auth::id(), 'light');
    render('profile/edit', ['user' => $user, 'theme' => $theme]);
});

$router->post('/profile', function () {
    Auth::requireAuth();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/profile');
        return;
    }

    $fn = trim($_POST['first_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');

    if ($fn === '' || $ln === '') {
        flashInput($_POST);
        flash('error', 'First name and last name are required.');
        redirect('/profile');
        return;
    }

    $db     = Database::connect();
    $userId = Auth::id();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Handle password change
    if ($newPassword !== '' || $currentPassword !== '') {
        // Fetch current hash
        $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            flashInput($_POST);
            flash('error', 'Current password is incorrect.');
            redirect('/profile');
            return;
        }

        if (strlen($newPassword) < 8) {
            flashInput($_POST);
            flash('error', 'New password must be at least 8 characters.');
            redirect('/profile');
            return;
        }

        if ($newPassword !== $confirmPassword) {
            flashInput($_POST);
            flash('error', 'New password and confirmation do not match.');
            redirect('/profile');
            return;
        }

        $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, password = ? WHERE id = ?');
        $stmt->execute([$fn, $ln, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
    } else {
        $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?');
        $stmt->execute([$fn, $ln, $userId]);
    }

    // Save theme preference
    $theme = in_array($_POST['theme'] ?? '', ['light', 'dark'], true) ? $_POST['theme'] : 'light';
    setSetting('ui_theme:' . $userId, $theme);

    // Refresh session so navbar reflects changes immediately
    $_SESSION['user']['first_name'] = $fn;
    $_SESSION['user']['last_name']  = $ln;

    flash('success', 'Profile updated successfully.');
    redirect('/profile');
});

/* ------------------------------------------------------------------
 * CSAT Survey (public — no login required)
 * ------------------------------------------------------------------ */

$router->get('/survey/{token}', function (array $p) {
    $token = preg_replace('/[^a-f0-9]/', '', $p['token'] ?? '');
    if (strlen($token) !== 64) {
        http_response_code(404);
        render('errors/404');
        return;
    }

    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT cs.*, t.subject, t.id AS ticket_id
         FROM csat_surveys cs
         JOIN tickets t ON cs.ticket_id = t.id
         WHERE cs.token = ?'
    );
    $stmt->execute([$token]);
    $survey = $stmt->fetch();

    if (!$survey) {
        http_response_code(404);
        render('errors/404');
        return;
    }

    $preselect = (int) ($_GET['r'] ?? 0);
    if ($preselect < 1 || $preselect > 5) {
        $preselect = 0;
    }

    render('survey/form', [
        'survey'      => $survey,
        'preselect'   => $preselect,
        'alreadyDone' => $survey['responded_at'] !== null,
        'appName'     => getSetting('app_name', 'LocalDesk'),
        'brandColor'  => getSetting('branding_primary_color', '#4f46e5'),
        'error'       => $_GET['error'] ?? '',
    ]);
});

$router->post('/survey/{token}', function (array $p) {
    $token = preg_replace('/[^a-f0-9]/', '', $p['token'] ?? '');
    if (strlen($token) !== 64) {
        http_response_code(404);
        render('errors/404');
        return;
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM csat_surveys WHERE token = ?');
    $stmt->execute([$token]);
    $survey = $stmt->fetch();

    if (!$survey || $survey['responded_at'] !== null) {
        redirect('/survey/' . $token);
        return;
    }

    $rating = (int) ($_POST['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        redirect('/survey/' . $token . '?error=rating');
        return;
    }

    $comment = trim($_POST['comment'] ?? '');
    if (mb_strlen($comment) > 2000) {
        $comment = mb_substr($comment, 0, 2000);
    }

    $db->prepare(
        'UPDATE csat_surveys SET rating = ?, comment = ?, responded_at = NOW() WHERE token = ?'
    )->execute([$rating, $comment !== '' ? $comment : null, $token]);

    redirect('/survey/' . $token . '/thanks');
});

$router->get('/survey/{token}/thanks', function (array $p) {
    $token = preg_replace('/[^a-f0-9]/', '', $p['token'] ?? '');

    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT cs.rating, t.subject, t.id AS ticket_id
         FROM csat_surveys cs JOIN tickets t ON cs.ticket_id = t.id
         WHERE cs.token = ?'
    );
    $stmt->execute([$token]);
    $survey = $stmt->fetch();

    if (!$survey) {
        http_response_code(404);
        render('errors/404');
        return;
    }

    render('survey/thanks', [
        'survey'     => $survey,
        'appName'    => getSetting('app_name', 'LocalDesk'),
        'brandColor' => getSetting('branding_primary_color', '#4f46e5'),
    ]);
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
    $db      = Database::connect();
    $agentId = Auth::id();

    // Group-based visibility: agents in groups only see those groups' tickets.
    $groupRestriction = '';
    $groupParams      = [];
    if (Auth::role() === 'agent') {
        $gStmt = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gStmt->execute([$agentId]);
        $agentGroupIds = array_map('intval', $gStmt->fetchAll(PDO::FETCH_COLUMN));
        if (!empty($agentGroupIds)) {
            $placeholders     = implode(',', array_fill(0, count($agentGroupIds), '?'));
            $groupRestriction = " AND group_id IN ($placeholders)";
            $groupParams      = $agentGroupIds;
        }
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status IN ('open','in_progress','pending')" . $groupRestriction);
    $stmt->execute($groupParams);
    $unassigned = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND status IN ('open','in_progress','pending')" . $groupRestriction);
    $stmt->execute(array_merge([$agentId], $groupParams));
    $myTickets = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'pending'" . $groupRestriction);
    $stmt->execute($groupParams);
    $pending = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()" . $groupRestriction);
    $stmt->execute($groupParams);
    $resolvedToday = (int) $stmt->fetchColumn();

    // Recent tickets (open/in_progress/pending, newest first)
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.status, t.created_at,
                tp.name AS priority_name, tp.color AS priority_color,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN users c ON t.created_by = c.id
         LEFT JOIN users a ON t.assigned_to = a.id
         WHERE t.status IN ('open','in_progress','pending')" . $groupRestriction . "
         ORDER BY t.created_at DESC
         LIMIT 10"
    );
    $stmt->execute($groupParams);
    $recent = $stmt->fetchAll();

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
        'showOnboarding' => getSetting('show_onboarding', '0') === '1' || isset($_GET['tour']),
    ]);
});

require ROOT_DIR . '/src/routes/admin.php';
