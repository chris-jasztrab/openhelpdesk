<?php

declare(strict_types=1);

/**
 * OpenHelpDesk Mobile REST API — /api/v1/
 *
 * Authentication: Bearer token obtained via POST /api/v1/auth/login.
 * All endpoints accept JSON bodies and return JSON responses.
 * HTTP status codes follow REST conventions.
 *
 * Endpoints
 * ─────────────────────────────────────────────────────────────────────────────
 * POST /api/v1/auth/login              — Exchange credentials for a token
 * POST /api/v1/auth/logout             — Revoke the current token
 * POST /api/v1/auth/rotate             — Issue a new token, invalidate the old one
 *
 * GET  /api/v1/me                      — Current user profile
 *
 * GET  /api/v1/dashboard               — Agent/admin stats
 *
 * GET  /api/v1/tickets                 — List tickets (paginated, filterable)
 * POST /api/v1/tickets                 — Create a new ticket
 * GET  /api/v1/tickets/{id}            — Ticket detail (tags, CC, watchers)
 * POST /api/v1/tickets/{id}/update     — Update status/priority/assignment
 * GET  /api/v1/tickets/{id}/timeline   — Full ticket timeline
 * POST /api/v1/tickets/{id}/replies    — Post a reply or internal note
 *
 * GET  /api/v1/kb/categories           — KB categories + folders
 * GET  /api/v1/kb/articles             — List published KB articles
 * GET  /api/v1/kb/articles/{id}        — Full KB article
 *
 * GET  /api/v1/notifications           — @mention notifications
 * POST /api/v1/notifications/read-all  — Mark all notifications as read
 * POST /api/v1/notifications/{id}/read — Mark one notification as read
 *
 * GET  /api/v1/canned-responses        — Canned reply snippets (agent/admin)
 *
 * GET  /api/v1/meta                    — Reference data for dropdowns
 *
 * GET  /api/v1/users/search            — Search users (agent/admin)
 */

// ── Private helpers ───────────────────────────────────────────────────────────

/**
 * Emit a JSON response and exit.
 */
function _apiJson(mixed $data, int $status = 200): never
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validate the Authorization: Bearer <token> header.
 * Returns the authenticated user row; exits 401 if invalid.
 *
 * The raw bearer token is never stored on disk; only its SHA-256 digest
 * lives in api_tokens.token_hash.
 */
function _apiAuth(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        _apiJson(['error' => 'Authorization: Bearer <token> header required'], 401);
    }
    $hash = hash('sha256', trim($m[1]));
    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.avatar, u.location_id
           FROM users u
           JOIN api_tokens t ON t.user_id = u.id
          WHERE t.token_hash = ?
            AND t.expires_at > NOW()'
    );
    $stmt->execute([$hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        _apiJson(['error' => 'Invalid or expired token'], 401);
    }
    $db->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?')->execute([$hash]);

    // Every authenticated endpoint funnels through here, so this is the single
    // choke point for per-user request rate limiting.
    _apiRateLimit($db, (int) $user['id']);

    return $user;
}

/**
 * Per-user fixed-window request rate limit for the authenticated API.
 *
 * A counter row per (user, 60-second window) is incremented on every call; once
 * it passes API_RATE_LIMIT_PER_MIN (default 120) the caller gets 429 with a
 * Retry-After until the window rolls over. Keyed on the user — not the token —
 * so spinning up extra tokens doesn't multiply the allowance. `X-RateLimit-*`
 * headers are emitted on every response so clients can self-throttle.
 *
 * Fails OPEN: a limiter DB error must never take the API down (a rate limiter
 * that hard-fails is itself a denial-of-service vector), so on error we log and
 * allow the request. Set API_RATE_LIMIT_PER_MIN=0 to disable entirely.
 */
function _apiRateLimit(PDO $db, int $userId): void
{
    $limit = (int) env('API_RATE_LIMIT_PER_MIN', '120');
    if ($limit <= 0) {
        return; // disabled
    }

    $now    = time();
    $window = $now - ($now % 60);
    $bucket = 'user:' . $userId;

    try {
        $db->prepare(
            'INSERT INTO api_rate_limits (bucket, window_start, count)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE count = count + 1'
        )->execute([$bucket, $window]);

        $c = $db->prepare('SELECT count FROM api_rate_limits WHERE bucket = ? AND window_start = ?');
        $c->execute([$bucket, $window]);
        $count = (int) $c->fetchColumn();

        // Opportunistically prune windows older than two minutes (~1% of calls)
        // so the table never accumulates stale rows.
        if (random_int(1, 100) === 1) {
            $db->prepare('DELETE FROM api_rate_limits WHERE window_start < ?')
                ->execute([$window - 120]);
        }
    } catch (\Throwable $e) {
        error_log('_apiRateLimit failed (allowing request): ' . $e->getMessage());
        return; // fail open
    }

    header('X-RateLimit-Limit: ' . $limit);
    header('X-RateLimit-Remaining: ' . max(0, $limit - $count));

    if ($count > $limit) {
        $retry = 60 - ($now % 60);
        header('Retry-After: ' . $retry);
        _apiJson(['error' => 'Rate limit exceeded. Try again in ' . $retry . ' second(s).'], 429);
    }
}

/**
 * Role helpers for token-authenticated API callers. These resolve the user's
 * role slug through the same cached roles map the web Auth class uses, so
 * custom permission levels created by an admin are honoured here too (a bare
 * string compare against ['admin','agent','power_user'] would 403 them).
 */
function _apiIsStaff(array $user): bool
{
    return roleIsStaff($user['role'] ?? null);
}

function _apiIsAdmin(array $user): bool
{
    return roleIsAdmin($user['role'] ?? null);
}

/**
 * Require the user to be staff (agent interface); exits 403 otherwise.
 */
function _apiRequireAgent(array $user): void
{
    if (!_apiIsStaff($user)) {
        _apiJson(['error' => 'Forbidden — agents and admins only'], 403);
    }
}

/**
 * Decode the JSON request body. Returns an array (may be empty).
 */
function _apiInput(): array
{
    $raw  = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Enforce per-ticket access control for all individual-ticket endpoints.
 *
 * Rules (mirrors _agentRequireTicketAccess):
 *   - admin  → unrestricted (confidential gated/audited elsewhere)
 *   - user   → may only access tickets they created
 *   - confidential type → ONLY the type's confidential-group members or the
 *               ticket creator (view_all / assignment / watching do NOT grant)
 *   - non-confidential → view_all holders; group members (+ group-less tickets);
 *               or the creator / assignee / watcher. A staff user with no group
 *               and no view_all sees only their own / assigned / watched tickets.
 *
 * Only $ticket['id'] is trusted; group/type/creator are re-fetched so a caller
 * that SELECTed a narrow column set can't bypass a check. Exits HTTP 403 if
 * access is denied.
 */
function _apiEnforceTicketAccess(PDO $db, array $user, array $ticket): void
{
    if (_apiIsAdmin($user)) {
        return; // unrestricted
    }

    if (!_apiIsStaff($user)) {
        // Portal (non-staff) callers may only touch tickets they created.
        if ((int) $ticket['created_by'] !== (int) $user['id']) {
            _apiJson(['error' => 'Forbidden'], 403);
        }
        return;
    }

    $userId   = (int) $user['id'];
    $ticketId = (int) ($ticket['id'] ?? 0);

    // Authoritative re-fetch of the fields the rules depend on.
    $groupId        = array_key_exists('group_id', $ticket) ? $ticket['group_id'] : null;
    $createdBy      = (int) ($ticket['created_by'] ?? 0);
    $isConfidential = false;
    $confGroupId    = null;
    if ($ticketId > 0) {
        $meta = $db->prepare(
            'SELECT t.group_id, t.created_by, tt.is_confidential, tt.group_id AS conf_group_id
             FROM tickets t LEFT JOIN ticket_types tt ON t.type_id = tt.id WHERE t.id = ?'
        );
        $meta->execute([$ticketId]);
        $row = $meta->fetch();
        if ($row) {
            $groupId        = $row['group_id'];
            $createdBy      = (int) $row['created_by'];
            $isConfidential = (int) ($row['is_confidential'] ?? 0) === 1;
            $confGroupId    = $row['conf_group_id'] !== null ? (int) $row['conf_group_id'] : null;
        }
    }

    $userGroups = userGroupIds($db, $userId);

    // Confidential tickets: confidential-group members or the creator ONLY.
    if ($isConfidential) {
        $isMember = $confGroupId !== null && in_array($confGroupId, $userGroups, true);
        if (!$isMember && $createdBy !== $userId) {
            _apiJson(['error' => 'Forbidden'], 403);
        }
        return;
    }

    // Non-confidential tickets.
    if (roleCan($user['role'] ?? null, 'tickets.view_all')) {
        return;
    }
    $gid = $groupId !== null ? (int) $groupId : null;
    if (!empty($userGroups) && ($gid === null || in_array($gid, $userGroups, true))) {
        return;
    }
    if ($createdBy === $userId) {
        return;
    }
    if (ticketAccessExempt($db, $userId, $ticketId)) {
        return;
    }
    _apiJson(['error' => 'Forbidden'], 403);
}

/**
 * Login throttle.
 *
 * Two independent limits on failed attempts inside a 15-minute sliding window:
 *   - 5 failures for a given email (defends one account from a guesser)
 *   - 10 failures from a given IP  (defends against credential stuffing across many emails)
 *
 * On the first successful login from this IP for this email we wipe the
 * failed-attempt rows for that pair, so a legitimate user who fat-fingers
 * the password a couple of times then logs in cleanly is not locked out
 * for the rest of the window.
 */
const _API_LOGIN_WINDOW_MINUTES        = 15;
const _API_LOGIN_MAX_FAILURES_PER_EMAIL = 5;
const _API_LOGIN_MAX_FAILURES_PER_IP    = 10;

function _apiLoginThrottleCheck(PDO $db, string $email, string $ip): void
{
    $window = _API_LOGIN_WINDOW_MINUTES;

    $byEmail = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts
          WHERE email = ? AND succeeded = 0
            AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)"
    );
    $byEmail->execute([$email]);
    if ((int) $byEmail->fetchColumn() >= _API_LOGIN_MAX_FAILURES_PER_EMAIL) {
        _apiJson(['error' => 'Too many failed login attempts. Please try again in a few minutes.'], 429);
    }

    $byIp = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts
          WHERE ip = ? AND succeeded = 0
            AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)"
    );
    $byIp->execute([$ip]);
    if ((int) $byIp->fetchColumn() >= _API_LOGIN_MAX_FAILURES_PER_IP) {
        _apiJson(['error' => 'Too many failed login attempts. Please try again in a few minutes.'], 429);
    }
}

function _apiLoginRecordAttempt(PDO $db, string $email, string $ip, bool $succeeded): void
{
    $db->prepare(
        'INSERT INTO login_attempts (email, ip, succeeded) VALUES (?, ?, ?)'
    )->execute([$email, $ip, $succeeded ? 1 : 0]);

    if ($succeeded) {
        $db->prepare(
            'DELETE FROM login_attempts WHERE email = ? AND ip = ? AND succeeded = 0'
        )->execute([$email, $ip]);
    }
}

/**
 * Block API access to confidential tickets for users who are not in the type's group.
 * Call after _apiEnforceTicketAccess().
 */
function _apiEnforceConfidential(PDO $db, array $user, array $ticket): void
{
    if (empty($ticket['type_id'])) {
        return;
    }
    $stmt = $db->prepare('SELECT is_confidential, group_id FROM ticket_types WHERE id = ?');
    $stmt->execute([$ticket['type_id']]);
    $type = $stmt->fetch();
    if (!$type || !$type['is_confidential'] || !$type['group_id']) {
        return;
    }
    $gs = $db->prepare('SELECT 1 FROM group_user_map WHERE group_id = ? AND user_id = ? LIMIT 1');
    $gs->execute([$type['group_id'], $user['id']]);
    if (!$gs->fetchColumn()) {
        _apiJson(['error' => 'This ticket is confidential. Access via the web interface is required.'], 403);
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// AUTH
// ══════════════════════════════════════════════════════════════════════════════

/**
 * POST /api/v1/auth/login
 *
 * Body: { "email": "...", "password": "...", "device_name": "My iPhone" }
 *
 * Returns:
 * {
 *   "token": "<64-char hex>",
 *   "user": { id, first_name, last_name, email, role, avatar }
 * }
 */
$router->post('/api/v1/auth/login', function () {
    $input    = _apiInput();
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $device   = trim(substr($input['device_name'] ?? 'Mobile App', 0, 255));
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if ($email === '' || $password === '') {
        _apiJson(['error' => 'email and password are required'], 422);
    }

    $db = Database::connect();
    _apiLoginThrottleCheck($db, $email, $ip);

    $stmt = $db->prepare(
        'SELECT id, first_name, last_name, email, password, role, avatar, totp_enabled, totp_secret
           FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always run a verify (against a dummy hash when the user is missing) so a
    // non-existent account can't be distinguished by response latency.
    $passwordOk = password_verify($password, $user['password'] ?? Auth::DUMMY_PASSWORD_HASH);

    if (!$user || !$passwordOk) {
        _apiLoginRecordAttempt($db, $email, $ip, false);
        // Tie the failed attempt to the targeted account when one exists,
        // so the audit log can surface stuffing patterns against real users.
        logAudit(
            'auth.api_login_failed',
            null,
            null,
            'email=' . $email . '; device=' . $device,
            $user ? (int) $user['id'] : null
        );
        _apiJson(['error' => 'Invalid credentials'], 401);
    }

    // Enforce the second factor: the web login redirects 2FA-enabled users to
    // /2fa before completing, so the API must likewise require a valid TOTP
    // code rather than issuing a token on the password alone.
    if (!empty($user['totp_enabled'])) {
        $code = trim((string) ($input['totp_code'] ?? $input['code'] ?? ''));
        if ($code === '') {
            _apiJson(['error' => 'A two-factor authentication code is required.', 'totp_required' => true], 401);
        }
        if (!totpVerify((string) $user['totp_secret'], $code)) {
            _apiLoginRecordAttempt($db, $email, $ip, false);
            logAudit('auth.api_2fa_failed', null, null, 'device=' . $device, (int) $user['id']);
            _apiJson(['error' => 'Invalid two-factor authentication code.', 'totp_required' => true], 401);
        }
    }

    _apiLoginRecordAttempt($db, $email, $ip, true);

    $token     = bin2hex(random_bytes(32)); // 64-char hex — returned to client, never stored
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));
    $db->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, device_name, expires_at) VALUES (?, ?, ?, ?)'
    )->execute([$user['id'], $tokenHash, $device, $expiresAt]);
    $tokenId = (int) $db->lastInsertId();

    logAudit('auth.api_login',    null,     null,          'device=' . $device, (int) $user['id']);
    logAudit('api_token.created', $tokenId, 'api_token',   'device=' . $device, (int) $user['id']);

    _apiJson([
        'token' => $token,
        'user'  => [
            'id'         => (int) $user['id'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'avatar'     => $user['avatar'],
        ],
    ]);
});

/**
 * POST /api/v1/auth/logout
 *
 * Deletes the current Bearer token from the database.
 * Returns: { "message": "Logged out" }
 */
$router->post('/api/v1/auth/logout', function () {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        $hash = hash('sha256', trim($m[1]));
        $db   = Database::connect();
        // Look up the owner before deleting so we can attribute the audit row.
        $owner = $db->prepare('SELECT user_id, device_name FROM api_tokens WHERE token_hash = ?');
        $owner->execute([$hash]);
        $row = $owner->fetch(\PDO::FETCH_ASSOC) ?: null;
        $db->prepare('DELETE FROM api_tokens WHERE token_hash = ?')
            ->execute([$hash]);
        if ($row) {
            logAudit(
                'auth.api_logout',
                null,
                null,
                'device=' . ($row['device_name'] ?? ''),
                (int) $row['user_id']
            );
        }
    }
    _apiJson(['message' => 'Logged out']);
});

/**
 * POST /api/v1/auth/rotate
 *
 * Issues a new token for the current session and immediately invalidates the
 * old one. Clients should call this before the 90-day expiry to maintain
 * an active session without re-entering credentials.
 *
 * Returns: { "token": "<new 64-char hex>", "expires_at": "<ISO datetime>" }
 */
$router->post('/api/v1/auth/rotate', function () {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        _apiJson(['error' => 'Authorization: Bearer <token> header required'], 401);
    }
    $oldHash = hash('sha256', trim($m[1]));

    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT id, user_id FROM api_tokens
          WHERE token_hash = ? AND expires_at > NOW()'
    );
    $stmt->execute([$oldHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        _apiJson(['error' => 'Invalid or expired token'], 401);
    }

    $newToken  = bin2hex(random_bytes(32));
    $newHash   = hash('sha256', $newToken);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

    // Swap old hash for new hash atomically — if the new token can't be
    // inserted (e.g. hash collision), the old one is preserved.
    $db->prepare(
        'UPDATE api_tokens SET token_hash = ?, expires_at = ?, last_used_at = NOW()
          WHERE id = ?'
    )->execute([$newHash, $expiresAt, $row['id']]);

    logAudit('api_token.rotated', (int) $row['id'], 'api_token', null, (int) $row['user_id']);

    _apiJson(['token' => $newToken, 'expires_at' => $expiresAt]);
});

// ══════════════════════════════════════════════════════════════════════════════
// PROFILE
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/me
 *
 * Returns the authenticated user's profile with their location (if set).
 */
$router->get('/api/v1/me', function () {
    $user = _apiAuth();
    $db   = Database::connect();

    $loc = null;
    if ($user['location_id']) {
        $s = $db->prepare('SELECT id, name, address FROM locations WHERE id = ?');
        $s->execute([$user['location_id']]);
        $loc = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    _apiJson([
        'id'         => (int) $user['id'],
        'first_name' => $user['first_name'],
        'last_name'  => $user['last_name'],
        'email'      => $user['email'],
        'role'       => $user['role'],
        'avatar'     => $user['avatar'],
        'location'   => $loc,
    ]);
});

// ══════════════════════════════════════════════════════════════════════════════
// DASHBOARD
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/dashboard
 *
 * Agent/admin summary stats and 10 most recent open tickets.
 *
 * Returns:
 * {
 *   "unassigned":     N,
 *   "my_tickets":     N,
 *   "pending":        N,
 *   "resolved_today": N,
 *   "recent_tickets": [...]
 * }
 */
$router->get('/api/v1/dashboard', function () {
    $user = _apiAuth();
    _apiRequireAgent($user);

    $db      = Database::connect();
    $agentId = (int) $user['id'];

    // Fail-closed visibility — same predicate as the web dashboard, with the
    // token user passed explicitly (no session Auth in the API context).
    $vis = ticketStaffVisibilitySql($db, $agentId, $user['role'] ?? null, 't');

    $openInT = ticketStatusSqlIn(ticketOpenBucketSlugs(), 't.status');

    $s = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.assigned_to IS NULL AND $openInT AND {$vis['sql']}");
    $s->execute($vis['params']);
    $unassigned = (int) $s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = ? AND $openInT AND {$vis['sql']}");
    $s->execute(array_merge([$agentId], $vis['params']));
    $myTickets = (int) $s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.status = 'pending' AND {$vis['sql']}");
    $s->execute($vis['params']);
    $pending = (int) $s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.status = ? AND DATE(t.updated_at) = CURDATE() AND {$vis['sql']}");
    $s->execute(array_merge([ticketDefaultResolvedStatusSlug()], $vis['params']));
    $resolvedToday = (int) $s->fetchColumn();

    $s = $db->prepare(
        "SELECT t.id, t.subject, t.status, t.created_at, t.updated_at,
                tp.name AS priority_name, tp.color AS priority_color,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN users c ON t.created_by  = c.id
         LEFT JOIN users a ON t.assigned_to = a.id
         WHERE $openInT AND {$vis['sql']}
         ORDER BY t.created_at DESC
         LIMIT 10"
    );
    $s->execute($vis['params']);
    $recent = $s->fetchAll(PDO::FETCH_ASSOC);

    _apiJson([
        'unassigned'     => $unassigned,
        'my_tickets'     => $myTickets,
        'pending'        => $pending,
        'resolved_today' => $resolvedToday,
        'recent_tickets' => $recent,
    ]);
});

// ══════════════════════════════════════════════════════════════════════════════
// TICKETS — LIST
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/tickets
 *
 * Query params:
 *   status      — comma-separated (open,in_progress,pending,waiting_on_customer,
 *                 waiting_on_third_party,resolved,closed)
 *   priority_id — integer
 *   type_id     — integer
 *   location_id — integer
 *   group_id    — integer
 *   assigned_to — integer or "me"
 *   unassigned  — 1 to show only unassigned tickets
 *   search      — keyword in subject
 *   page        — default 1
 *   per_page    — default 25, max 100
 *   sort        — created_at|updated_at|subject|status|priority_id (default updated_at)
 *   dir         — asc|desc (default desc)
 *
 * Returns:
 * { "data": [...], "total": N, "page": N, "per_page": N, "last_page": N }
 */
$router->get('/api/v1/tickets', function () {
    $user = _apiAuth();
    $db   = Database::connect();

    $validStatuses = ticketActiveStatusSlugs();

    $where  = ['t.merged_into_ticket_id IS NULL'];
    $params = [];

    // Visibility
    if (!_apiIsStaff($user)) {
        $where[]  = 't.created_by = ?';
        $params[] = (int) $user['id'];
    } else {
        // Fail-closed staff visibility (group / view_all / assignment rules, with
        // confidential types restricted to their confidential group or the creator).
        $vis      = ticketStaffVisibilitySql($db, (int) $user['id'], $user['role'] ?? null, 't');
        $where[]  = $vis['sql'];
        $params   = array_merge($params, $vis['params']);
    }

    // Filter: status (comma-separated)
    $statusRaw = trim($_GET['status'] ?? '');
    if ($statusRaw !== '') {
        $statuses = array_filter(
            array_map('trim', explode(',', $statusRaw)),
            fn ($s) => in_array($s, $validStatuses, true)
        );
        if (!empty($statuses)) {
            $ph      = implode(',', array_fill(0, count($statuses), '?'));
            $where[] = "t.status IN ($ph)";
            $params  = array_merge($params, array_values($statuses));
        }
    }

    if (isset($_GET['priority_id']) && $_GET['priority_id'] !== '') {
        $where[]  = 't.priority_id = ?';
        $params[] = (int) $_GET['priority_id'];
    }
    if (isset($_GET['type_id']) && $_GET['type_id'] !== '') {
        $where[]  = 't.type_id = ?';
        $params[] = (int) $_GET['type_id'];
    }
    if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
        $where[]  = 't.location_id = ?';
        $params[] = (int) $_GET['location_id'];
    }
    if (isset($_GET['group_id']) && $_GET['group_id'] !== '') {
        $where[]  = 't.group_id = ?';
        $params[] = (int) $_GET['group_id'];
    }

    $assignedTo = $_GET['assigned_to'] ?? '';
    if ($assignedTo === 'me') {
        $where[]  = 't.assigned_to = ?';
        $params[] = (int) $user['id'];
    } elseif ($assignedTo !== '') {
        $where[]  = 't.assigned_to = ?';
        $params[] = (int) $assignedTo;
    }

    if (($_GET['unassigned'] ?? '') === '1') {
        $where[] = 't.assigned_to IS NULL';
    }

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $where[]  = 't.subject LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $whereClause = implode(' AND ', $where);

    // Count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Pagination
    $perPage  = max(1, min(100, (int) ($_GET['per_page'] ?? 25)));
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $offset   = ($page - 1) * $perPage;
    $lastPage = max(1, (int) ceil($total / $perPage));

    // Sort
    $validSorts = ['created_at', 'updated_at', 'subject', 'status', 'priority_id'];
    $sort       = in_array($_GET['sort'] ?? '', $validSorts, true) ? $_GET['sort'] : 'updated_at';
    $dir        = strtolower($_GET['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';

    $dataStmt = $db->prepare(
        "SELECT t.id, t.subject, t.status, t.priority_id, t.type_id, t.location_id,
                t.group_id, t.assigned_to, t.created_by, t.created_at, t.updated_at,
                t.due_date, t.sla_state, t.merged_into_ticket_id,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name,
                l.name  AS location_name,
                g.name  AS group_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types      tt ON t.type_id     = tt.id
         LEFT JOIN locations          l ON t.location_id  = l.id
         LEFT JOIN groups             g ON t.group_id     = g.id
         LEFT JOIN users              c ON t.created_by   = c.id
         LEFT JOIN users              a ON t.assigned_to  = a.id
         WHERE $whereClause
         ORDER BY t.$sort $dir
         LIMIT $perPage OFFSET $offset"
    );
    $dataStmt->execute($params);

    _apiJson([
        'data'      => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => $lastPage,
    ]);
});

// ══════════════════════════════════════════════════════════════════════════════
// TICKETS — CREATE
// ══════════════════════════════════════════════════════════════════════════════

/**
 * POST /api/v1/tickets
 *
 * Body:
 * {
 *   "subject":     "Printer not working",   (required)
 *   "description": "Details...",            (required)
 *   "type_id":     1,                       (optional)
 *   "priority_id": 2,                       (optional)
 *   "location_id": 3,                       (optional)
 *   "group_id":    4,                       (optional, agent/admin only)
 *   "assigned_to": 5,                       (optional, agent/admin only)
 *   "due_date":    "2026-04-01"             (optional, agent/admin only)
 * }
 *
 * Returns: the created ticket object (HTTP 201).
 */
$router->post('/api/v1/tickets', function () {
    $user  = _apiAuth();
    $input = _apiInput();
    $db    = Database::connect();

    $subject     = trim($input['subject'] ?? '');
    $description = trim($input['description'] ?? '');

    if ($subject === '') {
        _apiJson(['error' => 'subject is required'], 422);
    }
    if ($description === '') {
        _apiJson(['error' => 'description is required'], 422);
    }

    $typeId     = isset($input['type_id'])     && $input['type_id']     !== null ? (int) $input['type_id']     : null;
    $priorityId = isset($input['priority_id']) && $input['priority_id'] !== null ? (int) $input['priority_id'] : null;
    $locationId = isset($input['location_id']) && $input['location_id'] !== null ? (int) $input['location_id'] : null;
    $groupId    = null;
    $assignedTo = null;
    $dueDate    = null;

    // Fields only agents/admins may set
    if (_apiIsStaff($user)) {
        $groupId    = isset($input['group_id'])    && $input['group_id']    !== null ? (int) $input['group_id']    : null;
        $assignedTo = isset($input['assigned_to']) && $input['assigned_to'] !== null ? (int) $input['assigned_to'] : null;
        if (isset($input['due_date']) && $input['due_date'] !== null && $input['due_date'] !== '') {
            $ts      = strtotime($input['due_date']);
            $dueDate = $ts ? date('Y-m-d', $ts) : null;
        }
    }

    // For portal-role API callers (no group permission) the explicit
    // $groupId stays null; resolveTicketGroup() then falls through to
    // the ticket type's default group, then to the system-wide
    // default_group_id setting, so a portal API ticket never lands
    // with NULL group.
    $groupId = resolveTicketGroup($db, $groupId, $typeId);

    $db->prepare(
        'INSERT INTO tickets
             (subject, description, created_by, status, type_id, priority_id,
              location_id, group_id, assigned_to, due_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $subject, $description, (int) $user['id'], 'open',
        $typeId, $priorityId, $locationId, $groupId, $assignedTo, $dueDate,
    ]);
    $ticketId = (int) $db->lastInsertId();

    // Timeline entry
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, ?, ?, ?, 0)'
    )->execute([$ticketId, (int) $user['id'], 'created', 'Ticket created via mobile app']);

    // Initialise SLA if priority is set
    if ($priorityId) {
        Sla::initializeForTicket($db, $ticketId, $priorityId, $typeId);
    }

    // AI classification (if enabled & non-confidential type) + auto-assign.
    // Always runs — sentiment-driven priority bumps still fire on tickets that
    // arrive with an explicit assignee.
    $picked = runPostTicketCreateHooks($db, $ticketId);
    if ($assignedTo === null && $picked !== null) {
        $assignedTo = $picked;
    }

    // Send confirmation email to requester and notify assignee if one was chosen
    notifyRequesterTicketCreated($db, $ticketId);
    if ($assignedTo) {
        notifyAssignedAgent($db, $ticketId, $assignedTo);
        notifyRequesterTicketAssigned($db, $ticketId, $assignedTo);
    }

    // Return the new ticket with joins.
    // Columns are enumerated rather than t.* so that any future ticket column
    // (e.g. an internal-only flag) doesn't auto-leak through the API.
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.description, t.legacy_id, t.browser_info, t.os_info,
                t.created_by, t.created_at, t.due_date, t.type_id, t.location_id,
                t.status, t.priority_id, t.assigned_to, t.escalation_level, t.group_id,
                t.first_response_due_at, t.resolution_due_at, t.first_responded_at,
                t.sla_state, t.sla_paused_at, t.updated_at, t.merged_into_ticket_id,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name, l.name AS location_name, g.name AS group_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types      tt ON t.type_id     = tt.id
         LEFT JOIN locations          l ON t.location_id  = l.id
         LEFT JOIN groups             g ON t.group_id     = g.id
         LEFT JOIN users              c ON t.created_by   = c.id
         LEFT JOIN users              a ON t.assigned_to  = a.id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    _apiJson($stmt->fetch(PDO::FETCH_ASSOC), 201);
});

// ══════════════════════════════════════════════════════════════════════════════
// TICKETS — DETAIL
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/tickets/{id}
 *
 * Returns the full ticket including tags, CC users, and watchers.
 */
$router->get('/api/v1/tickets/{id}', function (array $p) {
    $user     = _apiAuth();
    $db       = Database::connect();
    $ticketId = (int) $p['id'];

    // Columns are enumerated rather than t.* so that any future ticket column
    // (e.g. an internal-only flag) doesn't auto-leak through the API.
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.description, t.legacy_id, t.browser_info, t.os_info,
                t.created_by, t.created_at, t.due_date, t.type_id, t.location_id,
                t.status, t.priority_id, t.assigned_to, t.escalation_level, t.group_id,
                t.first_response_due_at, t.resolution_due_at, t.first_responded_at,
                t.sla_state, t.sla_paused_at, t.updated_at, t.merged_into_ticket_id,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name,
                l.name  AS location_name,
                g.name  AS group_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                c.email AS creator_email,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name,
                a.email AS agent_email
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types      tt ON t.type_id     = tt.id
         LEFT JOIN locations          l ON t.location_id  = l.id
         LEFT JOIN groups             g ON t.group_id     = g.id
         LEFT JOIN users              c ON t.created_by   = c.id
         LEFT JOIN users              a ON t.assigned_to  = a.id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        _apiJson(['error' => 'Ticket not found'], 404);
    }

    _apiEnforceTicketAccess($db, $user, $ticket);
    _apiEnforceConfidential($db, $user, $ticket);

    // Tags
    $s = $db->prepare(
        'SELECT tt.name FROM ticket_tags tt
         JOIN ticket_tag_map ttm ON tt.id = ttm.tag_id
         WHERE ttm.ticket_id = ? ORDER BY tt.name'
    );
    $s->execute([$ticketId]);
    $ticket['tags'] = $s->fetchAll(PDO::FETCH_COLUMN);

    // CC users
    $s = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role
           FROM ticket_cc tc JOIN users u ON tc.user_id = u.id
          WHERE tc.ticket_id = ?'
    );
    $s->execute([$ticketId]);
    $ticket['cc_users'] = $s->fetchAll(PDO::FETCH_ASSOC);

    // Watchers
    $s = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email
           FROM ticket_watchers tw JOIN users u ON tw.user_id = u.id
          WHERE tw.ticket_id = ?'
    );
    $s->execute([$ticketId]);
    $ticket['watchers'] = $s->fetchAll(PDO::FETCH_ASSOC);

    // Is the current user watching?
    $ticket['is_watching'] = false;
    foreach ($ticket['watchers'] as $w) {
        if ((int) $w['id'] === (int) $user['id']) {
            $ticket['is_watching'] = true;
            break;
        }
    }

    _apiJson($ticket);
});

// ══════════════════════════════════════════════════════════════════════════════
// TICKETS — UPDATE (agent/admin only)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * POST /api/v1/tickets/{id}/update
 *
 * All fields are optional — only those included in the body are changed.
 *
 * Body:
 * {
 *   "status":      "open|in_progress|pending|waiting_on_customer|
 *                   waiting_on_third_party|resolved|closed",
 *   "priority_id": 2,           (null to remove)
 *   "assigned_to": 5,           (null to unassign)
 *   "group_id":    4,           (null to remove group)
 *   "due_date":    "2026-04-01" (null to clear)
 * }
 *
 * Returns: { "updated": ["status", "priority", ...] }
 */
$router->post('/api/v1/tickets/{id}/update', function (array $p) {
    $user = _apiAuth();
    _apiRequireAgent($user);

    $db       = Database::connect();
    $ticketId = (int) $p['id'];
    $userId   = (int) $user['id'];

    // Internal-only fetch — drives access checks and old-vs-new comparisons.
    // Never returned to the client; the response is the {updated:[...]} list at the bottom.
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        _apiJson(['error' => 'Ticket not found'], 404);
    }

    _apiEnforceTicketAccess($db, $user, $ticket);
    _apiEnforceConfidential($db, $user, $ticket);

    $input   = _apiInput();
    $changes = [];

    $validStatuses    = ticketActiveStatusSlugs();
    $slaStatusPause   = ticketSlaPausingSlugs();
    // "Resume" set = open-bucket slugs that don't pause SLA.
    $slaStatusResume  = array_values(array_diff(ticketOpenBucketSlugs(), $slaStatusPause));

    // ── Status ────────────────────────────────────────────────────────────────
    if (array_key_exists('status', $input)) {
        $newStatus = $input['status'];
        if (!in_array($newStatus, $validStatuses, true)) {
            _apiJson(['error' => 'Invalid status value'], 422);
        }
        if ($newStatus !== $ticket['status']) {
            $oldStatus = $ticket['status'];
            $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$newStatus, $ticketId]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
                 VALUES (?, ?, ?, ?, 0)'
            )->execute([$ticketId, $userId, 'status_changed', "Status changed from {$oldStatus} to {$newStatus}"]);
            notifyAgentStatusChanged($db, $ticketId, $oldStatus, $newStatus, $userId);
            $changes[] = 'status';

            // CSAT survey trigger
            if ($newStatus === getSetting('csat_trigger_status', 'resolved')) {
                sendCsatSurvey($db, $ticketId);
            }
            // SLA pause/resume
            if (in_array($newStatus, $slaStatusPause, true)) {
                Sla::pause($db, $ticketId);
            } elseif (in_array($newStatus, $slaStatusResume, true)) {
                Sla::resume($db, $ticketId);
            }
        }
    }

    // ── Priority ─────────────────────────────────────────────────────────────
    if (array_key_exists('priority_id', $input)) {
        $newPriority = $input['priority_id'] !== null ? (int) $input['priority_id'] : null;
        $oldPriority = $ticket['priority_id'] !== null ? (int) $ticket['priority_id'] : null;
        if ($newPriority !== $oldPriority) {
            $oldName = 'None';
            if ($oldPriority) {
                $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
                $s->execute([$oldPriority]);
                $oldName = $s->fetchColumn() ?: 'None';
            }
            $newName = 'None';
            if ($newPriority) {
                $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
                $s->execute([$newPriority]);
                $newName = $s->fetchColumn() ?: 'None';
            }
            $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')->execute([$newPriority, $ticketId]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
                 VALUES (?, ?, ?, ?, 0)'
            )->execute([$ticketId, $userId, 'priority_changed', "Priority changed from {$oldName} to {$newName}"]);
            $changes[] = 'priority';
            if ($newPriority) {
                Sla::onPriorityChanged($db, $ticketId, $newPriority, $ticket['type_id'] ? (int) $ticket['type_id'] : null);
            }
        }
    }

    // ── Assigned to ──────────────────────────────────────────────────────────
    if (array_key_exists('assigned_to', $input)) {
        $newAssigned = $input['assigned_to'] !== null ? (int) $input['assigned_to'] : null;
        $oldAssigned = $ticket['assigned_to'] !== null ? (int) $ticket['assigned_to'] : null;
        if ($newAssigned !== $oldAssigned) {
            $agentName = 'Unassigned';
            if ($newAssigned) {
                // Only staff may be assigned a ticket — never a portal user.
                $s = $db->prepare(
                    "SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ? AND " . staffRoleSqlIn('role')
                );
                $s->execute([$newAssigned]);
                $agentName = $s->fetchColumn();
                if ($agentName === false) {
                    _apiJson(['error' => 'Invalid assignee'], 422);
                }
            }
            $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$newAssigned, $ticketId]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
                 VALUES (?, ?, ?, ?, 0)'
            )->execute([$ticketId, $userId, 'assigned', "Assigned to {$agentName}"]);
            $changes[] = 'assignment';
        }
    }

    // ── Group ────────────────────────────────────────────────────────────────
    if (array_key_exists('group_id', $input)) {
        $newGroup = $input['group_id'] !== null ? (int) $input['group_id'] : null;
        $oldGroup = $ticket['group_id'] !== null ? (int) $ticket['group_id'] : null;
        if ($newGroup !== $oldGroup) {
            if ($newGroup !== null) {
                $chk = $db->prepare('SELECT 1 FROM `groups` WHERE id = ?');
                $chk->execute([$newGroup]);
                if (!$chk->fetchColumn()) {
                    _apiJson(['error' => 'Invalid group'], 422);
                }
            }
            $oldGroupName = 'None';
            if ($oldGroup) {
                $s = $db->prepare('SELECT name FROM groups WHERE id = ?');
                $s->execute([$oldGroup]);
                $oldGroupName = $s->fetchColumn() ?: 'None';
            }
            $newGroupName = 'None';
            if ($newGroup) {
                $s = $db->prepare('SELECT name FROM groups WHERE id = ?');
                $s->execute([$newGroup]);
                $newGroupName = $s->fetchColumn() ?: 'None';
            }
            $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$newGroup, $ticketId]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
                 VALUES (?, ?, ?, ?, 0)'
            )->execute([$ticketId, $userId, 'group_changed', "Group changed from {$oldGroupName} to {$newGroupName}"]);
            $changes[] = 'group';
        }
    }

    // ── Due date ──────────────────────────────────────────────────────────────
    if (array_key_exists('due_date', $input)) {
        $dueDate = null;
        if ($input['due_date'] !== null && $input['due_date'] !== '') {
            $ts      = strtotime($input['due_date']);
            $dueDate = $ts ? date('Y-m-d', $ts) : null;
        }
        $db->prepare('UPDATE tickets SET due_date = ? WHERE id = ?')->execute([$dueDate, $ticketId]);
        $changes[] = 'due_date';
    }

    _apiJson(['updated' => $changes]);
});

// ══════════════════════════════════════════════════════════════════════════════
// TICKETS — TIMELINE
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/tickets/{id}/timeline
 *
 * Query params:
 *   include_internal — 1 to include internal notes (agent/admin only)
 *
 * Returns:
 * { "data": [{ id, action, details, is_internal, created_at, user, attachments }, ...] }
 */
$router->get('/api/v1/tickets/{id}/timeline', function (array $p) {
    $user     = _apiAuth();
    $db       = Database::connect();
    $ticketId = (int) $p['id'];

    $tStmt = $db->prepare('SELECT id, created_by, group_id FROM tickets WHERE id = ?');
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        _apiJson(['error' => 'Ticket not found'], 404);
    }

    _apiEnforceTicketAccess($db, $user, $ticket);
    _apiEnforceConfidential($db, $user, $ticket);

    // Users always see public entries; agents/admins may request internal ones
    $internalFilter = '';
    if (!_apiIsStaff($user) || ($_GET['include_internal'] ?? '0') !== '1') {
        $internalFilter = ' AND tl.is_internal = 0';
    }

    $stmt = $db->prepare(
        "SELECT tl.id, tl.action, tl.details, tl.is_internal, tl.created_at,
                tl.user_id,
                u.first_name, u.last_name, u.email, u.role AS user_role, u.avatar
         FROM ticket_timeline tl
         LEFT JOIN users u ON tl.user_id = u.id
         WHERE tl.ticket_id = ?{$internalFilter}
         ORDER BY tl.created_at ASC"
    );
    $stmt->execute([$ticketId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch attachments for this ticket and group by timeline_id
    $aStmt = $db->prepare(
        'SELECT id, timeline_id, original_name, mime_type, file_size, created_at
           FROM ticket_attachments WHERE ticket_id = ?'
    );
    $aStmt->execute([$ticketId]);
    $attByTimeline = [];
    foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $att) {
        $attByTimeline[$att['timeline_id']][] = $att;
    }

    // Reshape each row
    foreach ($rows as &$row) {
        $row['user'] = $row['user_id'] !== null ? [
            'id'         => (int) $row['user_id'],
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'email'      => $row['email'],
            'role'       => $row['user_role'],
            'avatar'     => $row['avatar'],
        ] : null;
        unset($row['user_id'], $row['first_name'], $row['last_name'],
              $row['email'], $row['user_role'], $row['avatar']);
        $row['attachments'] = $attByTimeline[$row['id']] ?? [];
    }
    unset($row);

    _apiJson(['data' => $rows]);
});

// ══════════════════════════════════════════════════════════════════════════════
// TICKETS — POST REPLY / INTERNAL NOTE
// ══════════════════════════════════════════════════════════════════════════════

/**
 * POST /api/v1/tickets/{id}/replies
 *
 * Body:
 * {
 *   "message":      "Thanks, all fixed!",  (required)
 *   "is_internal":  false,                 (optional, agent/admin only)
 *   "status_after": "resolved"             (optional, agent/admin only)
 * }
 *
 * Returns: the created timeline entry (HTTP 201).
 */
$router->post('/api/v1/tickets/{id}/replies', function (array $p) {
    $user     = _apiAuth();
    $db       = Database::connect();
    $ticketId = (int) $p['id'];
    $userId   = (int) $user['id'];

    // Internal-only fetch — drives access checks and SLA/first-response logic.
    // Never returned to the client; the response is the timeline row built below.
    $tStmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        _apiJson(['error' => 'Ticket not found'], 404);
    }

    _apiEnforceTicketAccess($db, $user, $ticket);
    _apiEnforceConfidential($db, $user, $ticket);

    $input   = _apiInput();
    $message = trim($input['message'] ?? '');
    if ($message === '') {
        _apiJson(['error' => 'message is required'], 422);
    }

    // Internal notes are agent/admin only
    $isInternal = false;
    if (_apiIsStaff($user)) {
        $isInternal = !empty($input['is_internal']);
    }

    // Insert timeline entry
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$ticketId, $userId, 'comment', $message, $isInternal ? 1 : 0]);
    $timelineId = (int) $db->lastInsertId();

    // Process @mentions
    processAtMentions($db, $message, $ticketId, $timelineId, $userId);

    // First-response tracking (public replies by someone other than the creator)
    if (!$isInternal && (int) $ticket['created_by'] !== $userId) {
        if ($ticket['first_responded_at'] === null) {
            $db->prepare('UPDATE tickets SET first_responded_at = NOW() WHERE id = ?')->execute([$ticketId]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
                 VALUES (?, NULL, ?, ?, 1)'
            )->execute([$ticketId, 'sla_set', 'First response recorded']);
        }
    }

    // Optional: change status after reply (agent/admin only)
    $validStatuses   = ticketActiveStatusSlugs();
    $slaStatusPause  = ticketSlaPausingSlugs();
    $slaStatusResume = array_values(array_diff(ticketOpenBucketSlugs(), $slaStatusPause));

    if (_apiIsStaff($user)) {
        $statusAfter = $input['status_after'] ?? '';
        if ($statusAfter !== '' && in_array($statusAfter, $validStatuses, true)
            && $statusAfter !== $ticket['status']
        ) {
            $oldStatus = $ticket['status'];
            $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$statusAfter, $ticketId]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
                 VALUES (?, ?, ?, ?, 0)'
            )->execute([$ticketId, $userId, 'status_changed', "Status changed from {$oldStatus} to {$statusAfter}"]);
            notifyAgentStatusChanged($db, $ticketId, $oldStatus, $statusAfter, $userId);
            if ($statusAfter === getSetting('csat_trigger_status', 'resolved')) {
                sendCsatSurvey($db, $ticketId);
            }
            if (in_array($statusAfter, $slaStatusPause, true)) {
                Sla::pause($db, $ticketId);
            } elseif (in_array($statusAfter, $slaStatusResume, true)) {
                Sla::resume($db, $ticketId);
            }
        }
    }

    // Email notifications (public replies only)
    if (!$isInternal) {
        $authorName = $user['first_name'] . ' ' . $user['last_name'];
        notifyTicketCreator($db, $ticketId, $message, $authorName);
        notifyCcUsers($db, $ticketId, $message, $authorName);
        notifyWatchers($db, $ticketId, $message, $authorName);
        notifyAssignedAgentReply($db, $ticketId, $message, $authorName, (int) $user['id']);
    }

    // Return the created timeline entry. Columns are enumerated to match
    // GET /api/v1/tickets/{id}/timeline so any future timeline column doesn't
    // auto-leak through this endpoint.
    $stmt = $db->prepare(
        "SELECT tl.id, tl.action, tl.details, tl.is_internal, tl.created_at, tl.user_id,
                u.first_name, u.last_name, u.email, u.avatar
           FROM ticket_timeline tl
           LEFT JOIN users u ON tl.user_id = u.id
          WHERE tl.id = ?"
    );
    $stmt->execute([$timelineId]);
    _apiJson($stmt->fetch(PDO::FETCH_ASSOC), 201);
});

// ══════════════════════════════════════════════════════════════════════════════
// KNOWLEDGE BASE
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/kb/categories
 *
 * Returns KB categories with their folders and published article counts.
 * Users only see public categories.
 */
$router->get('/api/v1/kb/categories', function () {
    $user = _apiAuth();
    $db   = Database::connect();

    $publicFilter = !_apiIsStaff($user) ? ' WHERE c.is_public = 1' : '';
    $cats = $db->query(
        "SELECT c.id, c.name, c.slug, c.description, c.is_public, c.sort_order
           FROM kb_categories c
           {$publicFilter}
           ORDER BY c.sort_order, c.name"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cats as &$cat) {
        $s = $db->prepare(
            "SELECT f.id, f.name, f.slug, f.description, f.sort_order,
                    COUNT(a.id) AS article_count
               FROM kb_folders f
               LEFT JOIN kb_articles a ON a.folder_id = f.id AND a.status = 'published'
              WHERE f.category_id = ?
              GROUP BY f.id
              ORDER BY f.sort_order, f.name"
        );
        $s->execute([$cat['id']]);
        $cat['folders'] = $s->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($cat);

    _apiJson(['data' => $cats]);
});

/**
 * GET /api/v1/kb/articles
 *
 * Query params:
 *   folder_id, category_id, search, page, per_page
 *
 * Returns published articles only.
 * Users only see articles from public categories.
 */
$router->get('/api/v1/kb/articles', function () {
    $user = _apiAuth();
    $db   = Database::connect();

    $where  = ["a.status = 'published'"];
    $params = [];

    if (!_apiIsStaff($user)) {
        $where[] = 'c.is_public = 1';
    }
    if (isset($_GET['folder_id']) && $_GET['folder_id'] !== '') {
        $where[]  = 'a.folder_id = ?';
        $params[] = (int) $_GET['folder_id'];
    }
    if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
        $where[]  = 'f.category_id = ?';
        $params[] = (int) $_GET['category_id'];
    }
    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $where[]  = 'a.title LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM kb_articles a
           JOIN kb_folders f ON a.folder_id = f.id
           JOIN kb_categories c ON f.category_id = c.id
          WHERE $whereClause"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $perPage  = max(1, min(100, (int) ($_GET['per_page'] ?? 25)));
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $offset   = ($page - 1) * $perPage;
    $lastPage = max(1, (int) ceil($total / $perPage));

    $stmt = $db->prepare(
        "SELECT a.id, a.title, a.slug, a.status, a.published_at, a.created_at, a.updated_at,
                f.id AS folder_id, f.name AS folder_name,
                c.id AS category_id, c.name AS category_name
           FROM kb_articles a
           JOIN kb_folders f ON a.folder_id = f.id
           JOIN kb_categories c ON f.category_id = c.id
          WHERE $whereClause
          ORDER BY a.sort_order, a.title
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    _apiJson([
        'data'      => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => $lastPage,
    ]);
});

/**
 * GET /api/v1/kb/articles/{id}
 *
 * Returns the full article including body_markdown.
 * Users can only access articles in public categories.
 */
$router->get('/api/v1/kb/articles/{id}', function (array $p) {
    $user      = _apiAuth();
    $db        = Database::connect();
    $articleId = (int) $p['id'];

    $stmt = $db->prepare(
        "SELECT a.*, f.name AS folder_name,
                c.name AS category_name, c.is_public,
                CONCAT(u.first_name, ' ', u.last_name) AS author_name
           FROM kb_articles a
           JOIN kb_folders f ON a.folder_id = f.id
           JOIN kb_categories c ON f.category_id = c.id
           LEFT JOIN users u ON a.created_by = u.id
          WHERE a.id = ? AND a.status = 'published'"
    );
    $stmt->execute([$articleId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        _apiJson(['error' => 'Article not found'], 404);
    }
    if (!_apiIsStaff($user) && !$article['is_public']) {
        _apiJson(['error' => 'Forbidden'], 403);
    }

    // Helpful / not-helpful rating counts
    $rStmt = $db->prepare(
        'SELECT SUM(rating = 1) AS helpful, SUM(rating = -1) AS not_helpful
           FROM kb_article_ratings WHERE article_id = ?'
    );
    $rStmt->execute([$articleId]);
    $r = $rStmt->fetch(PDO::FETCH_ASSOC);
    $article['ratings'] = [
        'helpful'     => (int) ($r['helpful']     ?? 0),
        'not_helpful' => (int) ($r['not_helpful']  ?? 0),
    ];
    unset($article['is_public']);

    _apiJson($article);
});

// ══════════════════════════════════════════════════════════════════════════════
// NOTIFICATIONS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/notifications
 *
 * Query params:
 *   read    — 0 (unread only) | 1 (read only) | omit for all
 *   page, per_page
 *
 * Returns:
 * { "data": [...], "unread": N, "total": N, "page": N, "per_page": N, "last_page": N }
 */
$router->get('/api/v1/notifications', function () {
    $user = _apiAuth();
    $db   = Database::connect();

    $where  = ['n.user_id = ?'];
    $params = [(int) $user['id']];

    if (isset($_GET['read']) && $_GET['read'] !== '') {
        $where[]  = 'n.is_read = ?';
        $params[] = $_GET['read'] === '1' ? 1 : 0;
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM notifications n WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $perPage  = max(1, min(100, (int) ($_GET['per_page'] ?? 25)));
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $offset   = ($page - 1) * $perPage;
    $lastPage = max(1, (int) ceil($total / $perPage));

    $stmt = $db->prepare(
        "SELECT n.id, n.ticket_id, n.is_read, n.created_at,
                t.subject AS ticket_subject,
                SUBSTRING(tl.details, 1, 200) AS comment_excerpt,
                CONCAT(u.first_name, ' ', u.last_name) AS mentioned_by_name,
                u.avatar AS mentioned_by_avatar
           FROM notifications n
           JOIN tickets t ON n.ticket_id = t.id
           JOIN ticket_timeline tl ON n.timeline_id = tl.id
           JOIN users u ON n.mentioned_by = u.id
          WHERE $whereClause
          ORDER BY n.created_at DESC
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unread count (always the total unread, regardless of current filter)
    $unreadStmt = $db->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
    );
    $unreadStmt->execute([(int) $user['id']]);
    $unread = (int) $unreadStmt->fetchColumn();

    _apiJson([
        'data'      => $rows,
        'unread'    => $unread,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => $lastPage,
    ]);
});

/**
 * POST /api/v1/notifications/read-all
 *
 * Marks all notifications as read for the current user.
 * NOTE: This route must be registered BEFORE the {id}/read pattern route.
 */
$router->post('/api/v1/notifications/read-all', function () {
    $user = _apiAuth();
    Database::connect()
        ->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
        ->execute([(int) $user['id']]);
    _apiJson(['ok' => true]);
});

/**
 * POST /api/v1/notifications/{id}/read
 *
 * Marks one notification as read (must belong to the current user).
 */
$router->post('/api/v1/notifications/{id}/read', function (array $p) {
    $user = _apiAuth();
    Database::connect()
        ->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([(int) $p['id'], (int) $user['id']]);
    _apiJson(['ok' => true]);
});

// ══════════════════════════════════════════════════════════════════════════════
// CANNED RESPONSES
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/canned-responses
 *
 * Returns global canned responses (user_id IS NULL) and the current
 * user's own personal ones, sorted for display.
 */
$router->get('/api/v1/canned-responses', function () {
    $user = _apiAuth();
    _apiRequireAgent($user);
    $db   = Database::connect();

    $stmt = $db->prepare(
        'SELECT id, user_id, title, body, sort_order
           FROM canned_responses
          WHERE user_id IS NULL OR user_id = ?
          ORDER BY sort_order, title'
    );
    $stmt->execute([(int) $user['id']]);
    _apiJson(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
});

// ══════════════════════════════════════════════════════════════════════════════
// META — REFERENCE DATA FOR DROPDOWNS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/meta
 *
 * Returns all reference data needed to populate dropdowns:
 * priorities, types, locations, groups, agents, statuses.
 */
$router->get('/api/v1/meta', function () {
    $user = _apiAuth();
    $db   = Database::connect();

    $priorities = $db->query(
        'SELECT id, name, color FROM ticket_priorities ORDER BY sort_order, name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $types = $db->query(
        'SELECT id, name FROM ticket_types ORDER BY sort_order, name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $locations = $db->query(
        'SELECT id, name FROM locations ORDER BY name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $groups = $db->query(
        'SELECT id, name, description FROM groups ORDER BY sort_order, name'
    )->fetchAll(PDO::FETCH_ASSOC);

    // Agent list only exposed to agents/admins
    $agents = [];
    if (_apiIsStaff($user)) {
        $agents = $db->query(
            "SELECT id, first_name, last_name, email, role, avatar
               FROM users
              WHERE " . staffRoleSqlIn('role') . "
              ORDER BY first_name, last_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    _apiJson([
        'priorities' => $priorities,
        'types'      => $types,
        'locations'  => $locations,
        'groups'     => $groups,
        'agents'     => $agents,
        'statuses'   => [
            ['value' => 'open',                   'label' => 'Open'],
            ['value' => 'in_progress',            'label' => 'In Progress'],
            ['value' => 'pending',                'label' => 'Pending'],
            ['value' => 'waiting_on_customer',    'label' => 'Waiting on Customer'],
            ['value' => 'waiting_on_third_party', 'label' => 'Waiting on Third Party'],
            ['value' => 'resolved',               'label' => 'Resolved'],
            ['value' => 'closed',                 'label' => 'Closed'],
        ],
    ]);
});

// ══════════════════════════════════════════════════════════════════════════════
// USER SEARCH
// ══════════════════════════════════════════════════════════════════════════════

/**
 * GET /api/v1/users/search?q=<term>
 *
 * Searches users by name or email. Returns up to 10 matches.
 * Agent/admin only.
 */
$router->get('/api/v1/users/search', function () {
    $user = _apiAuth();
    _apiRequireAgent($user);
    $db   = Database::connect();

    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) {
        _apiJson([]);
    }

    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        "SELECT id, first_name, last_name, email, role, avatar
           FROM users
          WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
          ORDER BY first_name, last_name
          LIMIT 10"
    );
    $stmt->execute([$like, $like, $like]);
    _apiJson($stmt->fetchAll(PDO::FETCH_ASSOC));
});

// ══════════════════════════════════════════════════════════════════════════════
// ATTACHMENTS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Validate and store uploaded files for the API.
 *
 * Unlike the web handleAttachmentUploads() (which flashes errors to the session
 * and silently skips bad files), this fails loudly with HTTP 422 so a mobile
 * client gets a clear reason. Reuses the same allow-list, size cap, storage
 * path and MIME→extension mapping as the web uploader. Returns
 * [{original_name, stored_name, mime_type, file_size}, ...] on success.
 */
function _apiStoreUploads(string $field = 'attachments'): array
{
    if (empty($_FILES[$field]['tmp_name'])) {
        _apiJson(['error' => 'No files uploaded. Send multipart/form-data with an "' . $field . '" field.'], 422);
    }

    $files = $_FILES[$field];
    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;
    $stored = [];

    for ($i = 0; $i < $count; $i++) {
        $tmp   = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $error = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
        $name  = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
        $size  = is_array($files['size'])     ? (int) $files['size'][$i] : (int) $files['size'];

        if ($error === UPLOAD_ERR_NO_FILE || $tmp === '') {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            _apiJson(['error' => 'Upload failed for "' . $name . '" (PHP upload error ' . $error . ')'], 422);
        }
        if (!is_uploaded_file($tmp)) {
            _apiJson(['error' => 'Invalid upload for "' . $name . '"'], 422);
        }

        $mime = mime_content_type($tmp) ?: 'application/octet-stream';
        if (!in_array($mime, UPLOAD_ALLOWED_TYPES, true)) {
            _apiJson(['error' => 'File type not allowed for "' . $name . '": ' . $mime], 422);
        }
        if ($size > UPLOAD_MAX_SIZE) {
            $maxMb = round(UPLOAD_MAX_SIZE / 1048576, 1);
            _apiJson(['error' => 'File too large (max ' . $maxMb . ' MB): "' . $name . '"'], 422);
        }

        $storedName = uniqid('att_', true) . '.' . safeUploadExtension($mime, $name);
        if (!is_dir(ATTACHMENT_STORAGE_PATH)) {
            mkdir(ATTACHMENT_STORAGE_PATH, 0755, true);
        }
        if (!move_uploaded_file($tmp, ATTACHMENT_STORAGE_PATH . $storedName)) {
            _apiJson(['error' => 'Could not store uploaded file "' . $name . '"'], 500);
        }
        $stored[] = [
            'original_name' => $name,
            'stored_name'   => $storedName,
            'mime_type'     => $mime,
            'file_size'     => $size,
        ];
    }

    if (empty($stored)) {
        _apiJson(['error' => 'No valid files in the upload'], 422);
    }
    return $stored;
}

/**
 * POST /api/v1/tickets/{id}/attachments
 *
 * multipart/form-data:
 *   attachments    — one file, or attachments[] for several
 *   timeline_id    — (optional) attach to an existing reply/note on this ticket
 *
 * Returns (201):
 * { "data": [ { id, ticket_id, timeline_id, original_name, mime_type, file_size }, ... ] }
 */
$router->post('/api/v1/tickets/{id}/attachments', function (array $p) {
    $user     = _apiAuth();
    $db       = Database::connect();
    $ticketId = (int) $p['id'];

    $tStmt = $db->prepare('SELECT id, created_by, group_id, type_id FROM tickets WHERE id = ?');
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        _apiJson(['error' => 'Ticket not found'], 404);
    }
    _apiEnforceTicketAccess($db, $user, $ticket);
    _apiEnforceConfidential($db, $user, $ticket);

    // Optional timeline_id must belong to THIS ticket.
    $timelineId = null;
    if (isset($_POST['timeline_id']) && $_POST['timeline_id'] !== '') {
        $timelineId = (int) $_POST['timeline_id'];
        $chk = $db->prepare('SELECT 1 FROM ticket_timeline WHERE id = ? AND ticket_id = ?');
        $chk->execute([$timelineId, $ticketId]);
        if (!$chk->fetchColumn()) {
            _apiJson(['error' => 'timeline_id does not belong to this ticket'], 422);
        }
    }

    $stored = _apiStoreUploads('attachments');

    $ins = $db->prepare(
        'INSERT INTO ticket_attachments
            (ticket_id, timeline_id, uploaded_by, original_name, stored_name, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $out = [];
    foreach ($stored as $att) {
        $ins->execute([
            $ticketId, $timelineId, (int) $user['id'],
            $att['original_name'], $att['stored_name'], $att['mime_type'], $att['file_size'],
        ]);
        $out[] = [
            'id'            => (int) $db->lastInsertId(),
            'ticket_id'     => $ticketId,
            'timeline_id'   => $timelineId,
            'original_name' => $att['original_name'],
            'mime_type'     => $att['mime_type'],
            'file_size'     => $att['file_size'],
        ];
    }

    _apiJson(['data' => $out], 201);
});

/**
 * GET /api/v1/attachments/{id}
 *
 * Streams the raw attachment bytes (NOT JSON). Anyone who can access the parent
 * ticket can download its attachments.
 */
$router->get('/api/v1/attachments/{id}', function (array $p) {
    $user = _apiAuth();
    $db   = Database::connect();
    $id   = (int) $p['id'];

    $stmt = $db->prepare(
        'SELECT id, ticket_id, original_name, stored_name, mime_type, file_size
           FROM ticket_attachments WHERE id = ?'
    );
    $stmt->execute([$id]);
    $att = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$att) {
        _apiJson(['error' => 'Attachment not found'], 404);
    }

    $tStmt = $db->prepare('SELECT id, created_by, group_id, type_id FROM tickets WHERE id = ?');
    $tStmt->execute([(int) $att['ticket_id']]);
    $ticket = $tStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        _apiJson(['error' => 'Attachment not found'], 404);
    }
    _apiEnforceTicketAccess($db, $user, $ticket);
    _apiEnforceConfidential($db, $user, $ticket);

    $path = ATTACHMENT_STORAGE_PATH . $att['stored_name'];
    if (!is_file($path)) {
        _apiJson(['error' => 'Attachment file is missing on the server'], 404);
    }

    // Release the session lock before streaming so a large download can't block
    // other requests (mirrors the web download routes).
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $att['original_name']) . '"');
    header('Content-Length: ' . $att['file_size']);
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
});

// ══════════════════════════════════════════════════════════════════════════════
// PUSH NOTIFICATIONS — DEVICE REGISTRATION
// ══════════════════════════════════════════════════════════════════════════════

/**
 * POST /api/v1/push/register
 *
 * Body: { "token": "<APNs/FCM token>", "platform": "ios|android|web",
 *         "device_name": "Jane's iPhone" }
 *
 * Registers (or refreshes) the device's push token for the current user.
 * Returns: { "ok": true }
 */
$router->post('/api/v1/push/register', function () {
    $user  = _apiAuth();
    $input = _apiInput();

    $token    = trim($input['token'] ?? '');
    $platform = trim($input['platform'] ?? '');
    $device   = isset($input['device_name']) ? trim(substr((string) $input['device_name'], 0, 255)) : null;

    if ($token === '') {
        _apiJson(['error' => 'token is required'], 422);
    }
    if (strlen($token) > 512) {
        _apiJson(['error' => 'token is too long (max 512 characters)'], 422);
    }
    if (!in_array($platform, ['ios', 'android', 'web'], true)) {
        _apiJson(['error' => 'platform must be one of: ios, android, web'], 422);
    }

    registerPushToken(Database::connect(), (int) $user['id'], $platform, $token, $device);
    _apiJson(['ok' => true]);
});

/**
 * POST /api/v1/push/unregister
 *
 * Body: { "token": "<APNs/FCM token>" }
 *
 * Removes the device token (e.g. on sign-out). Returns: { "ok": true }
 */
$router->post('/api/v1/push/unregister', function () {
    $user  = _apiAuth();
    $input = _apiInput();
    $token = trim($input['token'] ?? '');
    if ($token === '') {
        _apiJson(['error' => 'token is required'], 422);
    }
    removePushToken(Database::connect(), (int) $user['id'], $token);
    _apiJson(['ok' => true]);
});
