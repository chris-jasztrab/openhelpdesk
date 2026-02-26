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
        // Location prompt: redirect to picker when needed
        $locationPrompt = getSetting('sso_location_prompt', 'sso_only');
        if (!empty($_SESSION['sso_needs_location']) ||
            ($locationPrompt === 'all' && Auth::role() !== 'admin')) {
            $uid = Auth::id();
            $row = Database::connect()
                ->prepare('SELECT location_id FROM users WHERE id = ?');
            $row->execute([$uid]);
            $locId = $row->fetchColumn();
            if (empty($locId)) {
                redirect('/sso/pick-location');
            }
            unset($_SESSION['sso_needs_location']);
        }
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
        // Check if 2FA is required before completing login
        $uid   = Auth::id();
        $db    = Database::connect();
        $tfRow = $db->prepare('SELECT totp_enabled FROM users WHERE id = ?');
        $tfRow->execute([$uid]);
        $tf = $tfRow->fetch();
        if ($tf && $tf['totp_enabled']) {
            unset($_SESSION['user']); // undo session set by Auth::attempt()
            $_SESSION['2fa_pending'] = $uid;
            redirect('/2fa');
        }
        logAudit('login');
        redirect('/');
    }

    render('login', ['error' => 'Invalid email or password.', 'email' => $email]);
});

$router->get('/logout', function () {
    logAudit('logout');
    Auth::logout();
    redirect('/login');
});

/* ------------------------------------------------------------------
 * Microsoft 365 SSO – OAuth 2.0 Authorization Code Flow
 * ------------------------------------------------------------------ */

function ssoHttpPost(string $url, array $fields): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if ($body === false) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function ssoHttpGet(string $url, string $token): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if ($body === false) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function ssoSetSessionUser(array $user): void
{
    $_SESSION['user'] = [
        'id'          => $user['id'],
        'first_name'  => $user['first_name'],
        'last_name'   => $user['last_name'],
        'email'       => $user['email'],
        'role'        => $user['role'],
        'avatar'      => $user['avatar'] ?? null,
        'location_id' => $user['location_id'] ?? null,
    ];
}

// Initiate Microsoft SSO – redirect user to Microsoft login
$router->get('/auth/microsoft', function () {
    if (getSetting('sso_enabled', '0') !== '1') {
        redirect('/login?sso_error=disabled');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['sso_state'] = $state;

    $tenantId    = getSetting('sso_tenant_id');
    $clientId    = getSetting('sso_client_id');
    $redirectUri = rtrim(env('APP_URL', ''), '/') . '/auth/microsoft/callback';

    $params = http_build_query([
        'client_id'     => $clientId,
        'response_type' => 'code',
        'redirect_uri'  => $redirectUri,
        'scope'         => 'openid email profile User.Read',
        'state'         => $state,
        'response_mode' => 'query',
    ]);

    redirect("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?{$params}");
});

// Microsoft OAuth callback – exchange code, look up / create user, log in
$router->get('/auth/microsoft/callback', function () {
    // 1. Verify state (CSRF guard on OAuth flow)
    $state = $_GET['state'] ?? '';
    if ($state === '' || $state !== ($_SESSION['sso_state'] ?? '')) {
        unset($_SESSION['sso_state']);
        redirect('/login?sso_error=state');
    }
    unset($_SESSION['sso_state']);

    // 2. Check for user-denied / consent errors
    if (!empty($_GET['error'])) {
        redirect('/login?sso_error=denied');
    }

    $code = $_GET['code'] ?? '';
    if ($code === '') {
        redirect('/login?sso_error=token');
    }

    $tenantId     = getSetting('sso_tenant_id');
    $clientId     = getSetting('sso_client_id');
    $clientSecret = getSetting('sso_client_secret');
    $redirectUri  = rtrim(env('APP_URL', ''), '/') . '/auth/microsoft/callback';

    // 3. Exchange code for access token
    $tokens = ssoHttpPost(
        "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
        [
            'grant_type'    => 'authorization_code',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
        ]
    );

    if (empty($tokens['access_token'])) {
        redirect('/login?sso_error=token');
    }

    // 4. Fetch user profile from Microsoft Graph
    $me = ssoHttpGet('https://graph.microsoft.com/v1.0/me', $tokens['access_token']);

    if (empty($me['id'])) {
        redirect('/login?sso_error=graph');
    }

    $oid       = $me['id'];
    $email     = $me['mail'] ?? $me['userPrincipalName'] ?? '';
    $parts     = explode(' ', trim($me['displayName'] ?? 'SSO User'));
    $firstName = $me['givenName'] ?? $parts[0];
    $lastName  = $me['surname']   ?? (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'User');

    if ($email === '') {
        redirect('/login?sso_error=graph');
    }

    $db   = Database::connect();
    $user = null;

    // 5. Look up by Azure OID first, then by email
    $stmt = $db->prepare('SELECT * FROM users WHERE azure_oid = ? LIMIT 1');
    $stmt->execute([$oid]);
    $user = $stmt->fetch() ?: null;

    if (!$user) {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch() ?: null;
        if ($user) {
            // Link OID to the existing password-login account
            $db->prepare('UPDATE users SET azure_oid = ? WHERE id = ?')
               ->execute([$oid, $user['id']]);
            $user['azure_oid'] = $oid;
        }
    }

    $isBrandNew = false;

    // 6. Auto-create account if no match found
    if (!$user) {
        $db->prepare(
            'INSERT INTO users (first_name, last_name, email, azure_oid, password, role)
             VALUES (?, ?, ?, ?, \'\', \'user\')'
        )->execute([$firstName, $lastName, $email, $oid]);

        $newId = (int) $db->lastInsertId();
        $stmt  = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$newId]);
        $user       = $stmt->fetch();
        $isBrandNew = true;
    }

    // 7. Log in
    session_regenerate_id(true);
    ssoSetSessionUser($user);
    logAudit('login');

    // 8. Redirect to location picker when needed
    $locationPrompt = getSetting('sso_location_prompt', 'sso_only');
    if (empty($user['location_id']) && ($isBrandNew || $locationPrompt === 'all')) {
        $_SESSION['sso_needs_location'] = true;
    }

    redirect('/');
});

// Show location picker
$router->get('/sso/pick-location', function () {
    Auth::requireAuth();

    if (!empty(Auth::user()['location_id'])) {
        redirect('/');
    }

    $locations = Database::connect()
        ->query('SELECT id, name, address FROM locations ORDER BY name')
        ->fetchAll();

    render('sso-pick-location', ['locations' => $locations]);
});

// Save location choice
$router->post('/sso/pick-location', function () {
    Auth::requireAuth();

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect('/sso/pick-location');
    }

    $locationId = (int) ($_POST['location_id'] ?? 0);
    $db         = Database::connect();

    if ($locationId > 0) {
        $loc = $db->prepare('SELECT id FROM locations WHERE id = ?');
        $loc->execute([$locationId]);
        if (!$loc->fetch()) {
            render('sso-pick-location', [
                'locations' => $db->query('SELECT id, name, address FROM locations ORDER BY name')->fetchAll(),
                'error'     => 'Please select a valid ' . label('location.singular', 'location') . '.',
            ]);
        }
        $db->prepare('UPDATE users SET location_id = ? WHERE id = ?')
           ->execute([$locationId, Auth::id()]);
        $_SESSION['user']['location_id'] = $locationId;
    }

    unset($_SESSION['sso_needs_location']);
    redirect('/');
});

/* ------------------------------------------------------------------
 * First-run Setup Wizard
 * Accessible only when: no users exist OR a fresh reset was triggered.
 * ------------------------------------------------------------------ */
$router->get('/setup', function () {
    if (Auth::check()) {
        redirect('/admin');
    }
    $db        = Database::connect();
    $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    // Allow only if truly empty OR the reset flag is set
    if ($userCount > 0 && empty($_SESSION['setup_allowed'])) {
        redirect('/login');
    }
    $errors   = $_SESSION['setup_errors'] ?? [];
    $formData = $_SESSION['setup_form']   ?? [];
    unset($_SESSION['setup_errors'], $_SESSION['setup_form']);
    render('setup/wizard', ['errors' => $errors, 'formData' => $formData]);
});

$router->post('/setup', function () {
    if (Auth::check()) {
        redirect('/admin');
    }
    $db        = Database::connect();
    $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount > 0 && empty($_SESSION['setup_allowed'])) {
        redirect('/login');
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $password  = $_POST['password']        ?? '';
    $confirm   = $_POST['password_confirm'] ?? '';
    $appName   = trim($_POST['app_name']   ?? 'LocalDesk');

    $errors = [];
    if ($firstName === '') $errors[] = 'First name is required.';
    if ($lastName  === '') $errors[] = 'Last name is required.';
    if ($email     === '') $errors[] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if ($appName   === '') $appName = 'LocalDesk';

    if (!empty($errors)) {
        $_SESSION['setup_errors'] = $errors;
        $_SESSION['setup_form']   = compact('firstName', 'lastName', 'email', 'appName');
        redirect('/setup');
    }

    // Create admin user
    $stmt = $db->prepare(
        'INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, \'admin\')'
    );
    $stmt->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);

    // Seed default ticket priorities
    $db->exec("INSERT IGNORE INTO ticket_priorities (name, color, sort_order) VALUES
        ('Low',      '#198754', 1),
        ('Medium',   '#ffc107', 2),
        ('High',     '#fd7e14', 3),
        ('Critical', '#dc3545', 4)");

    // Seed default ticket types
    $db->exec("INSERT IGNORE INTO ticket_types (name) VALUES
        ('General'),
        ('Technical'),
        ('Billing'),
        ('Other')");

    // Restore app name and trigger onboarding tour
    $settingStmt = $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $settingStmt->execute(['branding_app_name', $appName]);
    $settingStmt->execute(['show_onboarding', '1']);

    // Clear setup session flags
    unset($_SESSION['setup_allowed'], $_SESSION['setup_errors'], $_SESSION['setup_form']);

    redirect('/login?setup=1');
});

/* ------------------------------------------------------------------
 * Two-Factor Authentication challenge
 * ------------------------------------------------------------------ */
$router->get('/2fa', function () {
    if (Auth::check()) {
        redirect('/');
    }
    if (empty($_SESSION['2fa_pending'])) {
        redirect('/login');
    }
    render('2fa');
});

$router->post('/2fa', function () {
    if (Auth::check()) {
        redirect('/');
    }
    $uid = $_SESSION['2fa_pending'] ?? null;
    if (!$uid) {
        redirect('/login');
    }
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        render('2fa', ['error' => 'Invalid request. Please try again.']);
    }

    $code = trim($_POST['code'] ?? '');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND totp_enabled = 1');
    $stmt->execute([(int) $uid]);
    $u = $stmt->fetch();

    if ($u && totpVerify($u['totp_secret'], $code)) {
        unset($_SESSION['2fa_pending']);
        $_SESSION['user'] = [
            'id'         => (int) $u['id'],
            'first_name' => $u['first_name'],
            'last_name'  => $u['last_name'],
            'email'      => $u['email'],
            'role'       => $u['role'],
            'avatar'     => $u['avatar'],
        ];
        logAudit('login');
        redirect('/');
    }

    render('2fa', ['error' => 'Invalid code. Please try again.']);
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

    // Save notification preferences
    $db->prepare(
        'UPDATE users SET
            notify_ticket_created = ?,
            notify_ticket_updated = ?,
            notify_ticket_cc      = ?,
            notify_ticket_merged  = ?,
            notify_escalation     = ?,
            notify_csat           = ?
         WHERE id = ?'
    )->execute([
        isset($_POST['notify_ticket_created']) ? 1 : 0,
        isset($_POST['notify_ticket_updated']) ? 1 : 0,
        isset($_POST['notify_ticket_cc'])      ? 1 : 0,
        isset($_POST['notify_ticket_merged'])  ? 1 : 0,
        isset($_POST['notify_escalation'])     ? 1 : 0,
        isset($_POST['notify_csat'])           ? 1 : 0,
        $userId,
    ]);

    // Refresh session so navbar reflects changes immediately
    $_SESSION['user']['first_name'] = $fn;
    $_SESSION['user']['last_name']  = $ln;

    flash('success', 'Profile updated successfully.');
    redirect('/profile');
});

/* ------------------------------------------------------------------
 * 2FA Setup (admin / agent only)
 * ------------------------------------------------------------------ */
$router->get('/profile/2fa/setup', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        redirect('/profile');
    }

    // Generate (or reuse if already in session from a failed verify attempt)
    if (empty($_SESSION['totp_pending_secret'])) {
        $_SESSION['totp_pending_secret'] = totpGenerateSecret();
    }
    $secret = $_SESSION['totp_pending_secret'];

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT email, totp_enabled FROM users WHERE id = ?');
    $stmt->execute([Auth::id()]);
    $u = $stmt->fetch();

    if ($u['totp_enabled']) {
        flash('info', '2FA is already enabled on your account.');
        redirect('/profile');
    }

    $uri   = totpGetUri($secret, $u['email']);
    $qrUrl = totpGetQrUrl($uri);

    render('profile/2fa-setup', ['secret' => $secret, 'qrUrl' => $qrUrl]);
});

$router->post('/profile/2fa/setup', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        redirect('/profile');
    }
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/profile/2fa/setup');
    }

    $secret = $_SESSION['totp_pending_secret'] ?? '';
    if ($secret === '') {
        flash('error', 'Setup session expired. Please try again.');
        redirect('/profile/2fa/setup');
    }

    $code = trim($_POST['code'] ?? '');
    if (!totpVerify($secret, $code)) {
        flash('error', 'Invalid code. Make sure your authenticator app is synced and try again.');
        redirect('/profile/2fa/setup');
    }

    $db = Database::connect();
    $db->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?')
       ->execute([$secret, Auth::id()]);

    unset($_SESSION['totp_pending_secret']);
    logAudit('2fa.enable');
    flash('success', 'Two-factor authentication has been enabled.');
    redirect('/profile');
});

$router->post('/profile/2fa/disable', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent'], true)) {
        redirect('/profile');
    }
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/profile');
    }

    $code = trim($_POST['code'] ?? '');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT totp_secret, totp_enabled FROM users WHERE id = ?');
    $stmt->execute([Auth::id()]);
    $u = $stmt->fetch();

    if (!$u || !$u['totp_enabled'] || !totpVerify($u['totp_secret'], $code)) {
        flash('error', 'Invalid code. 2FA was not disabled.');
        redirect('/profile');
    }

    $db->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?')
       ->execute([Auth::id()]);

    logAudit('2fa.disable');
    flash('success', 'Two-factor authentication has been disabled.');
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

/* ==================================================================
 * PUBLIC KNOWLEDGE BASE  (no authentication required)
 * ================================================================== */

/** Return a guest-session KB token (32-char hex), create once per session. */
function kbSessionId(): string
{
    if (empty($_SESSION['_kb_sid'])) {
        $_SESSION['_kb_sid'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_kb_sid'];
}

// GET /kb  — list all is_public categories
$router->get('/kb', function () {
    $db   = Database::connect();
    $cats = $db->query(
        "SELECT c.*,
                COUNT(DISTINCT a.id) AS article_count
         FROM kb_categories c
         LEFT JOIN kb_folders f  ON f.category_id = c.id
         LEFT JOIN kb_articles a ON a.folder_id = f.id AND a.status = 'published'
         WHERE c.is_public = 1
         GROUP BY c.id
         ORDER BY c.sort_order, c.name"
    )->fetchAll();

    $layout    = 'public';
    $pageTitle = 'Help Center';
    render('kb/index', compact('cats', 'layout', 'pageTitle'));
});

// GET /kb/search  — JSON live-search across public articles
$router->get('/kb/search', function () {
    header('Content-Type: application/json');
    $q    = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    $like = '%' . $q . '%';
    $db   = Database::connect();
    $stmt = $db->prepare(
        "SELECT a.title, a.slug
         FROM kb_articles a
         LEFT JOIN kb_folders f     ON a.folder_id   = f.id
         LEFT JOIN kb_categories c  ON f.category_id = c.id
         WHERE a.status = 'published' AND c.is_public = 1
           AND (a.title LIKE ? OR a.body_markdown LIKE ?)
         LIMIT 8"
    );
    $stmt->execute([$like, $like]);
    echo json_encode($stmt->fetchAll());
    exit;
});

// GET /kb/articles/{slug}  — single public article (MUST come before /{cat_slug})
$router->get('/kb/articles/{slug}', function (array $p) {
    $db   = Database::connect();
    $stmt = $db->prepare(
        "SELECT a.*, f.name AS folder_name, f.slug AS folder_slug,
                c.name AS category_name, c.slug AS category_slug, c.is_public
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         WHERE a.slug = ? AND a.status = 'published'"
    );
    $stmt->execute([$p['slug']]);
    $article = $stmt->fetch();
    if (!$article || !$article['is_public']) {
        http_response_code(404);
        render('errors/404', ['layout' => 'public', 'pageTitle' => 'Not Found']);
        exit;
    }
    $article['body_html'] = renderMarkdown($article['body_markdown']);

    // Feedback counts
    $fc = $db->prepare(
        "SELECT SUM(CASE WHEN rating =  1 THEN 1 ELSE 0 END) AS helpful,
                SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) AS not_helpful
         FROM kb_article_ratings WHERE article_id = ?"
    );
    $fc->execute([$article['id']]);
    $counts = $fc->fetch();

    // Guest vote check (session-based)
    $myVote = $_SESSION['kb_voted'][$article['id']] ?? null;

    $feedback = [
        'helpful'     => (int)($counts['helpful']     ?? 0),
        'not_helpful' => (int)($counts['not_helpful'] ?? 0),
        'my_vote'     => $myVote,
    ];

    $layout    = 'public';
    $pageTitle = $article['title'];
    render('kb/article', compact('article', 'feedback', 'layout', 'pageTitle'));
});

// POST /kb/articles/{slug}/feedback  — guest feedback vote
$router->post('/kb/articles/{slug}/feedback', function (array $p) {
    header('Content-Type: application/json');

    $rating = (int)($_POST['rating'] ?? 0);
    if (!in_array($rating, [1, -1], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid rating.']);
        exit;
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT id FROM kb_articles WHERE slug = ? AND status = ?');
    $stmt->execute([$p['slug'], 'published']);
    $article = $stmt->fetch();
    if (!$article) {
        echo json_encode(['status' => 'error', 'message' => 'Article not found.']);
        exit;
    }
    $articleId = (int)$article['id'];

    // Check session-based dedup
    if (isset($_SESSION['kb_voted'][$articleId])) {
        $fc = $db->prepare(
            "SELECT SUM(CASE WHEN rating =  1 THEN 1 ELSE 0 END) AS helpful,
                    SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) AS not_helpful
             FROM kb_article_ratings WHERE article_id = ?"
        );
        $fc->execute([$articleId]);
        $counts = $fc->fetch();
        echo json_encode([
            'status'      => 'already_voted',
            'helpful'     => (int)($counts['helpful']     ?? 0),
            'not_helpful' => (int)($counts['not_helpful'] ?? 0),
        ]);
        exit;
    }

    $sid = kbSessionId();
    try {
        $db->prepare(
            'INSERT INTO kb_article_ratings (article_id, session_id, rating) VALUES (?, ?, ?)'
        )->execute([$articleId, $sid, $rating]);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Could not save vote.']);
        exit;
    }

    $_SESSION['kb_voted'][$articleId] = $rating;

    $fc = $db->prepare(
        "SELECT SUM(CASE WHEN rating =  1 THEN 1 ELSE 0 END) AS helpful,
                SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) AS not_helpful
         FROM kb_article_ratings WHERE article_id = ?"
    );
    $fc->execute([$articleId]);
    $counts = $fc->fetch();

    echo json_encode([
        'status'      => 'ok',
        'helpful'     => (int)($counts['helpful']     ?? 0),
        'not_helpful' => (int)($counts['not_helpful'] ?? 0),
    ]);
    exit;
});

// GET /kb/{cat_slug}  — public category page (folders list)
$router->get('/kb/{cat_slug}', function (array $p) {
    $db      = Database::connect();
    $catStmt = $db->prepare('SELECT * FROM kb_categories WHERE slug = ? AND is_public = 1');
    $catStmt->execute([$p['cat_slug']]);
    $category = $catStmt->fetch();
    if (!$category) {
        http_response_code(404);
        render('errors/404', ['layout' => 'public', 'pageTitle' => 'Not Found']);
        exit;
    }

    $folders = $db->prepare(
        "SELECT f.*, COUNT(a.id) AS article_count
         FROM kb_folders f
         LEFT JOIN kb_articles a ON a.folder_id = f.id AND a.status = 'published'
         WHERE f.category_id = ?
         GROUP BY f.id ORDER BY f.sort_order, f.name"
    );
    $folders->execute([$category['id']]);
    $folders = $folders->fetchAll();

    $layout    = 'public';
    $pageTitle = $category['name'];
    render('kb/category', compact('category', 'folders', 'layout', 'pageTitle'));
});

// GET /kb/{cat_slug}/{folder_slug}  — public folder (article list)
$router->get('/kb/{cat_slug}/{folder_slug}', function (array $p) {
    $db      = Database::connect();
    $catStmt = $db->prepare('SELECT * FROM kb_categories WHERE slug = ? AND is_public = 1');
    $catStmt->execute([$p['cat_slug']]);
    $category = $catStmt->fetch();
    if (!$category) {
        http_response_code(404);
        render('errors/404', ['layout' => 'public', 'pageTitle' => 'Not Found']);
        exit;
    }

    $folStmt = $db->prepare('SELECT * FROM kb_folders WHERE slug = ? AND category_id = ?');
    $folStmt->execute([$p['folder_slug'], $category['id']]);
    $folder = $folStmt->fetch();
    if (!$folder) {
        http_response_code(404);
        render('errors/404', ['layout' => 'public', 'pageTitle' => 'Not Found']);
        exit;
    }

    $artStmt = $db->prepare(
        "SELECT id, title, slug, created_at FROM kb_articles
         WHERE folder_id = ? AND status = 'published'
         ORDER BY sort_order, title"
    );
    $artStmt->execute([$folder['id']]);
    $articles = $artStmt->fetchAll();

    $layout    = 'public';
    $pageTitle = $folder['name'];
    render('kb/folder', compact('category', 'folder', 'articles', 'layout', 'pageTitle'));
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
