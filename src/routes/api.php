<?php

declare(strict_types=1);

/**
 * LocalDesk Mobile REST API — /api/v1/
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
    return $user;
}

/**
 * Require the user to be an agent or admin; exits 403 otherwise.
 */
function _apiRequireAgent(array $user): void
{
    if (!in_array($user['role'], ['admin', 'agent'], true)) {
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
 * Build a group-based ticket-visibility restriction for agents.
 * Returns [sql_fragment, params_array].
 * Admins receive an empty restriction (no filtering).
 * Agents not in any group also receive no restriction (can see everything).
 */
function _apiGroupRestriction(PDO $db, array $user): array
{
    if ($user['role'] !== 'agent') {
        return ['', []];
    }
    $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
    $gs->execute([$user['id']]);
    $groupIds = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));
    if (empty($groupIds)) {
        return ['', []];
    }
    $ph = implode(',', array_fill(0, count($groupIds), '?'));
    return [" AND group_id IN ($ph)", $groupIds];
}

/**
 * Enforce per-ticket access control for all individual-ticket endpoints.
 *
 * Rules:
 *   - admin  → unrestricted
 *   - user   → may only access tickets they created
 *   - agent  → if the agent belongs to one or more groups, only tickets
 *               whose group_id is in that set (or whose group_id is NULL)
 *               are accessible; agents with no group assignment are unrestricted
 *
 * $ticket must contain at minimum: created_by (int), group_id (int|null).
 * Exits with HTTP 403 if access is denied.
 */
function _apiEnforceTicketAccess(PDO $db, array $user, array $ticket): void
{
    if ($user['role'] === 'admin') {
        return; // unrestricted
    }

    if ($user['role'] === 'user') {
        if ((int) $ticket['created_by'] !== (int) $user['id']) {
            _apiJson(['error' => 'Forbidden'], 403);
        }
        return;
    }

    // Agent: enforce group-based visibility
    $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
    $gs->execute([$user['id']]);
    $agentGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

    // Agents not in any group can see all tickets (matches list-endpoint behaviour)
    if (empty($agentGroups)) {
        return;
    }

    // Tickets with no group assigned are visible to all agents
    if ($ticket['group_id'] === null) {
        return;
    }

    if (!in_array((int) $ticket['group_id'], $agentGroups, true)) {
        _apiJson(['error' => 'Forbidden'], 403);
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

    if ($email === '' || $password === '') {
        _apiJson(['error' => 'email and password are required'], 422);
    }

    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT id, first_name, last_name, email, password, role, avatar
           FROM users WHERE email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        _apiJson(['error' => 'Invalid credentials'], 401);
    }

    $token     = bin2hex(random_bytes(32)); // 64-char hex — returned to client, never stored
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));
    $db->prepare(
        'INSERT INTO api_tokens (user_id, token_hash, device_name, expires_at) VALUES (?, ?, ?, ?)'
    )->execute([$user['id'], $tokenHash, $device, $expiresAt]);

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
        Database::connect()
            ->prepare('DELETE FROM api_tokens WHERE token_hash = ?')
            ->execute([$hash]);
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

    // Replicate the group-based visibility logic from the web dashboard
    $groupRestriction = '';
    $groupParams      = [];
    if ($user['role'] === 'agent') {
        $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gs->execute([$agentId]);
        $agentGroupIds = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));
        if (!empty($agentGroupIds)) {
            $ph               = implode(',', array_fill(0, count($agentGroupIds), '?'));
            $groupRestriction = " AND group_id IN ($ph)";
            $groupParams      = $agentGroupIds;
        }
    }

    $s = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status IN ('open','in_progress','pending')" . $groupRestriction);
    $s->execute($groupParams);
    $unassigned = (int) $s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND status IN ('open','in_progress','pending')" . $groupRestriction);
    $s->execute(array_merge([$agentId], $groupParams));
    $myTickets = (int) $s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'pending'" . $groupRestriction);
    $s->execute($groupParams);
    $pending = (int) $s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()" . $groupRestriction);
    $s->execute($groupParams);
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
         WHERE t.status IN ('open','in_progress','pending')" . $groupRestriction . "
         ORDER BY t.created_at DESC
         LIMIT 10"
    );
    $s->execute($groupParams);
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

    $validStatuses = ['open', 'in_progress', 'pending', 'waiting_on_customer',
                      'waiting_on_third_party', 'resolved', 'closed'];

    $where  = ['t.merged_into_ticket_id IS NULL'];
    $params = [];

    // Visibility
    if ($user['role'] === 'user') {
        $where[]  = 't.created_by = ?';
        $params[] = (int) $user['id'];
    } else {
        [$groupRestriction, $groupParams] = _apiGroupRestriction($db, $user);
        if ($groupRestriction !== '') {
            // Strip leading " AND " — we add it ourselves via implode
            $where[] = substr($groupRestriction, 5);
            $params  = array_merge($params, $groupParams);
        }
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
    if (in_array($user['role'], ['admin', 'agent'], true)) {
        $groupId    = isset($input['group_id'])    && $input['group_id']    !== null ? (int) $input['group_id']    : null;
        $assignedTo = isset($input['assigned_to']) && $input['assigned_to'] !== null ? (int) $input['assigned_to'] : null;
        if (isset($input['due_date']) && $input['due_date'] !== null && $input['due_date'] !== '') {
            $ts      = strtotime($input['due_date']);
            $dueDate = $ts ? date('Y-m-d', $ts) : null;
        }
    }

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
        Sla::initializeForTicket($db, $ticketId, $priorityId);
    }

    // Return the new ticket with joins
    $stmt = $db->prepare(
        "SELECT t.*, tp.name AS priority_name, tp.color AS priority_color,
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

    $stmt = $db->prepare(
        "SELECT t.*,
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

    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        _apiJson(['error' => 'Ticket not found'], 404);
    }

    _apiEnforceTicketAccess($db, $user, $ticket);

    $input   = _apiInput();
    $changes = [];

    $validStatuses    = ['open', 'in_progress', 'pending', 'waiting_on_customer',
                         'waiting_on_third_party', 'resolved', 'closed'];
    $slaStatusPause   = ['pending', 'waiting_on_customer', 'waiting_on_third_party'];
    $slaStatusResume  = ['open', 'in_progress'];

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
                Sla::onPriorityChanged($db, $ticketId, $newPriority);
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
                $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
                $s->execute([$newAssigned]);
                $agentName = $s->fetchColumn() ?: 'Unknown';
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

    // Users always see public entries; agents/admins may request internal ones
    $internalFilter = '';
    if ($user['role'] === 'user' || ($_GET['include_internal'] ?? '0') !== '1') {
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

    $tStmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        _apiJson(['error' => 'Ticket not found'], 404);
    }

    _apiEnforceTicketAccess($db, $user, $ticket);

    $input   = _apiInput();
    $message = trim($input['message'] ?? '');
    if ($message === '') {
        _apiJson(['error' => 'message is required'], 422);
    }

    // Internal notes are agent/admin only
    $isInternal = false;
    if (in_array($user['role'], ['admin', 'agent'], true)) {
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
    $validStatuses   = ['open', 'in_progress', 'pending', 'waiting_on_customer',
                        'waiting_on_third_party', 'resolved', 'closed'];
    $slaStatusPause  = ['pending', 'waiting_on_customer', 'waiting_on_third_party'];
    $slaStatusResume = ['open', 'in_progress'];

    if (in_array($user['role'], ['admin', 'agent'], true)) {
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
    }

    // Return the created timeline entry
    $stmt = $db->prepare(
        "SELECT tl.*, u.first_name, u.last_name, u.email, u.avatar
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

    $publicFilter = $user['role'] === 'user' ? ' WHERE c.is_public = 1' : '';
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

    if ($user['role'] === 'user') {
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
    if ($user['role'] === 'user' && !$article['is_public']) {
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
    if (in_array($user['role'], ['admin', 'agent'], true)) {
        $agents = $db->query(
            "SELECT id, first_name, last_name, email, role, avatar
               FROM users
              WHERE role IN ('admin','agent')
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
