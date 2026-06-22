<?php

declare(strict_types=1);

/**
 * Kanban board ticket view — page routes + management/placement API.
 *
 * The board is a fourth ticket view (separate page, not one of the table /
 * inbox / card list layouts). Built-in boards group by a ticket field
 * (status / priority / assignee) and a card-drop reuses the existing
 * /api/tickets/{id}/set-status|set-priority|assign endpoints. Custom boards are
 * a personal organizer: agents define their own buckets and drag tickets into
 * them; placement is stored (kanban_card_placements) and no ticket field
 * changes. See migration 058 and the kanban* helpers in helpers.php.
 *
 * Agent and admin share one controller + one template so the board is never
 * duplicated across parallel templates.
 */

/**
 * How many tickets a board will load before truncating. Boards have no
 * pagination; this is a sanity cap so a huge backlog can't render the whole
 * table at once. The template shows a notice when the cap is hit.
 */
const KANBAN_TICKET_CAP = 500;

/**
 * Build the columns + grouped tickets for the requested board and render the
 * shared template. $boardBase is the link/back base ('/agent/tickets' or
 * '/admin/tickets').
 */
function renderKanbanBoard(string $boardBase): never
{
    Auth::requireStaff();
    $db     = Database::connect();
    $userId = (int) Auth::id();

    $boardParam    = (string) ($_GET['board'] ?? 'status');
    $search        = trim((string) ($_GET['q'] ?? ''));
    $fGroup        = array_values(array_filter(array_map('intval', (array) ($_GET['group'] ?? []))));
    $teammatesOnly = !empty($_GET['teammates']);

    $myGroupIds = userGroupIds($db, $userId);

    // Teammates = staff who share at least one group with the viewer, plus the
    // viewer themselves. Drives both the "my team's tickets only" filter and the
    // assignee board's column scope.
    $teammateIds = [$userId];
    if (!empty($myGroupIds)) {
        $ph = implode(',', array_fill(0, count($myGroupIds), '?'));
        $tm = $db->prepare(
            "SELECT DISTINCT u.id FROM group_user_map gum JOIN users u ON gum.user_id = u.id
             WHERE gum.group_id IN ($ph) AND " . staffRoleSqlIn('u.role')
        );
        $tm->execute($myGroupIds);
        foreach ($tm->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $teammateIds[] = (int) $id;
        }
        $teammateIds = array_values(array_unique($teammateIds));
    }

    // ── Visible ticket set (same fail-closed predicate as the list) ──────────
    $where  = [];
    $params = [];
    if ($search !== '') {
        $where[]  = 't.subject LIKE ?';
        $params[] = '%' . $search . '%';
    }
    // Group filter: a non-admin can only pick from their own groups (built in the
    // template); the visibility predicate below still applies regardless, so this
    // only ever narrows, never widens, what's shown.
    if (!empty($fGroup)) {
        $ph      = implode(',', array_fill(0, count($fGroup), '?'));
        $where[] = "t.group_id IN ($ph)";
        $params  = array_merge($params, $fGroup);
    }
    // "My team's tickets only": tickets assigned to a teammate, or unassigned.
    if ($teammatesOnly) {
        $ph      = implode(',', array_fill(0, count($teammateIds), '?'));
        $where[] = "(t.assigned_to IS NULL OR t.assigned_to IN ($ph))";
        $params  = array_merge($params, $teammateIds);
    }
    $vis     = ticketStaffVisibilitySql($db, $userId, Auth::role(), 't');
    $where[] = $vis['sql'];
    $params  = array_merge($params, $vis['params']);

    // Admin confidential-redaction inputs (mirrors the list route).
    $confidentialTypeIds = [];
    $adminGroupIds       = [];
    if (Auth::isAdmin()) {
        foreach ($db->query('SELECT id FROM ticket_types WHERE is_confidential = 1 AND group_id IS NOT NULL')->fetchAll() as $ct) {
            $confidentialTypeIds[] = (int) $ct['id'];
        }
        if (!empty($confidentialTypeIds)) {
            $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
            $gs->execute([$userId]);
            $adminGroupIds = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    $sql = "SELECT t.*,
                   tp.name AS priority_name, tp.color AS priority_color,
                   l.name  AS location_name,
                   tt.name AS type_name, tt.color AS type_color,
                   tt.is_confidential AS type_confidential, tt.group_id AS type_group_id,
                   g.name  AS group_name,
                   CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                   c.email AS creator_email,
                   CONCAT(a.first_name, ' ', a.last_name) AS agent_name
            FROM tickets t
            LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
            LEFT JOIN ticket_types tt      ON t.type_id     = tt.id
            LEFT JOIN locations l          ON t.location_id = l.id
            LEFT JOIN users c              ON t.created_by  = c.id
            LEFT JOIN users a              ON t.assigned_to = a.id
            LEFT JOIN `groups` g           ON t.group_id    = g.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.created_at DESC
            LIMIT " . (KANBAN_TICKET_CAP + 1);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
    $capped  = count($tickets) > KANBAN_TICKET_CAP;
    if ($capped) {
        array_pop($tickets);
    }

    $ticketPresence = ticketListPresenceMap($db, array_column($tickets, 'id'), $userId);

    // ── Resolve the board into columns + a ticket→column map ────────────────
    $builtIns       = kanbanBuiltInBoards();
    $columns        = []; // each: ['key' => string, 'label' => string, 'color' => string]
    $cardColumn     = []; // ticket_id => column key
    $board          = null;
    $dimension      = 'status';
    $dropEndpoint   = '';
    $dropPayloadKey = '';

    if (isset($builtIns[$boardParam])) {
        $dimension      = $boardParam;
        $dropEndpoint   = $builtIns[$boardParam]['endpoint'];
        $dropPayloadKey = $builtIns[$boardParam]['payload'];

        if ($boardParam === 'status') {
            foreach ($db->query('SELECT slug, label, color FROM ticket_statuses WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll() as $r) {
                $columns[] = ['key' => $r['slug'], 'label' => $r['label'], 'color' => $r['color']];
            }
            foreach ($tickets as $t) {
                $cardColumn[(int) $t['id']] = (string) $t['status'];
            }
        } elseif ($boardParam === 'priority') {
            foreach ($db->query('SELECT id, name, color FROM ticket_priorities ORDER BY sort_order, id')->fetchAll() as $r) {
                $columns[] = ['key' => (string) $r['id'], 'label' => $r['name'], 'color' => $r['color']];
            }
            $columns[] = ['key' => '', 'label' => 'No priority', 'color' => '#adb5bd'];
            foreach ($tickets as $t) {
                $cardColumn[(int) $t['id']] = $t['priority_id'] !== null ? (string) $t['priority_id'] : '';
            }
        } else { // assignee
            $columns[] = ['key' => '', 'label' => 'Unassigned', 'color' => '#adb5bd'];

            // Which agents get a column? Not the whole staff roster — only people
            // the viewer can sensibly hand a ticket to:
            //   - "teammates only" OR a non-admin viewer → their teammates;
            //   - a non-admin viewer also keeps anyone already holding a visible
            //     ticket (e.g. a watched ticket assigned outside their groups);
            //   - an admin with the filter off → all staff.
            // Tickets are already scoped by visibility (and by the teammates
            // filter when on), so no card can land under an agent it shouldn't.
            $candidates = []; // id => name

            if ($teammatesOnly || !Auth::isAdmin()) {
                if (!empty($teammateIds)) {
                    $ph   = implode(',', array_fill(0, count($teammateIds), '?'));
                    $rows = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE id IN ($ph)");
                    $rows->execute($teammateIds);
                    foreach ($rows->fetchAll() as $a) {
                        $candidates[(int) $a['id']] = $a['name'];
                    }
                }
                if (!$teammatesOnly) {
                    foreach ($tickets as $t) {
                        if ($t['assigned_to'] !== null && $t['agent_name'] !== null) {
                            $candidates[(int) $t['assigned_to']] = $t['agent_name'];
                        }
                    }
                }
            } else {
                foreach ($db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE " . staffRoleSqlIn('role'))->fetchAll() as $a) {
                    $candidates[(int) $a['id']] = $a['name'];
                }
            }

            asort($candidates, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($candidates as $id => $name) {
                $columns[] = ['key' => (string) $id, 'label' => $name, 'color' => '#6366f1'];
            }
            foreach ($tickets as $t) {
                $cardColumn[(int) $t['id']] = $t['assigned_to'] !== null ? (string) $t['assigned_to'] : '';
            }
        }
    } else {
        // Custom board: param is 'c<id>'.
        $dimension = 'custom';
        $boardId   = (int) ltrim($boardParam, 'c');
        $board     = $boardId ? kanbanLoadBoard($db, $boardId, $userId) : null;
        if ($board) {
            $columns[] = ['key' => 'unsorted', 'label' => 'Unsorted', 'color' => '#adb5bd'];
            foreach ($board['buckets'] as $b) {
                $columns[] = ['key' => (string) $b['id'], 'label' => $b['name'], 'color' => $b['color']];
            }
            $bucketIds = array_map('intval', array_column($board['buckets'], 'id'));
            $placement = [];
            if (!empty($bucketIds)) {
                $ph = implode(',', array_fill(0, count($bucketIds), '?'));
                $ps = $db->prepare("SELECT bucket_id, ticket_id FROM kanban_card_placements WHERE bucket_id IN ($ph)");
                $ps->execute($bucketIds);
                foreach ($ps->fetchAll() as $row) {
                    $placement[(int) $row['ticket_id']] = (string) $row['bucket_id'];
                }
            }
            foreach ($tickets as $t) {
                $cardColumn[(int) $t['id']] = $placement[(int) $t['id']] ?? 'unsorted';
            }
        }
    }

    // Group filter options: a non-admin filters within their own groups; an admin
    // within any group.
    if (Auth::isAdmin()) {
        $groupOptions = $db->query('SELECT id, name FROM `groups` ORDER BY name')->fetchAll();
    } elseif (!empty($myGroupIds)) {
        $ph = implode(',', array_fill(0, count($myGroupIds), '?'));
        $go = $db->prepare("SELECT id, name FROM `groups` WHERE id IN ($ph) ORDER BY name");
        $go->execute($myGroupIds);
        $groupOptions = $go->fetchAll();
    } else {
        $groupOptions = [];
    }

    render('shared/kanban-board', [
        'pageTitle'           => 'Ticket Board',
        'boardBase'           => $boardBase,
        'isAdminBoard'        => str_starts_with($boardBase, '/admin'),
        'boardParam'          => $boardParam,
        'dimension'           => $dimension,
        'dropEndpoint'        => $dropEndpoint,
        'dropPayloadKey'      => $dropPayloadKey,
        'columns'             => $columns,
        'cardColumn'          => $cardColumn,
        'tickets'             => $tickets,
        'board'               => $board,
        'customBoards'        => kanbanBoardsForUser($db, $userId),
        'builtIns'            => $builtIns,
        'search'              => $search,
        'fGroup'              => $fGroup,
        'teammatesOnly'       => $teammatesOnly,
        'groupOptions'        => $groupOptions,
        'capped'              => $capped,
        'kanbanCap'           => KANBAN_TICKET_CAP,
        'ticketPresence'      => $ticketPresence,
        'confidentialTypeIds' => $confidentialTypeIds,
        'adminGroupIds'       => $adminGroupIds,
    ]);
}

$router->get('/agent/tickets/board', function () {
    renderKanbanBoard('/agent/tickets');
});
$router->get('/admin/tickets/board', function () {
    Auth::requireAdmin();
    renderKanbanBoard('/admin/tickets');
});

/* ──────────────────────────────────────────────────────────────────────────
 * Kanban management + placement API (JSON)
 *
 * Small shared guards keep each handler short. All require an authenticated
 * staff user and a valid CSRF header, matching the other /api/tickets/* writes.
 * ────────────────────────────────────────────────────────────────────────── */

/** Reject non-staff / bad-CSRF callers; emit JSON + exit. Returns the user id. */
function _kanbanApiGuard(): int
{
    Auth::requireAuth();
    header('Content-Type: application/json');
    if (!Auth::isStaff()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    return (int) Auth::id();
}

/** Decode the JSON request body to an array (empty array if none/invalid). */
function _kanbanApiBody(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : [];
}

/** Load a custom board the user OWNS, or emit 403/404 and exit. */
function _kanbanRequireOwnedBoard(PDO $db, int $boardId, int $userId): array
{
    $stmt = $db->prepare('SELECT * FROM kanban_boards WHERE id = ?');
    $stmt->execute([$boardId]);
    $board = $stmt->fetch();
    if (!$board) {
        http_response_code(404);
        echo json_encode(['error' => 'Board not found']);
        exit;
    }
    if ((int) $board['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the board owner can change it']);
        exit;
    }
    return $board;
}

// Create a custom board.
$router->post('/api/kanban/boards', function () {
    $userId = _kanbanApiGuard();
    $body   = _kanbanApiBody();
    $name   = trim((string) ($body['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) {
        http_response_code(422);
        echo json_encode(['error' => 'A board name (1–100 chars) is required']);
        exit;
    }
    $db = Database::connect();
    $db->prepare('INSERT INTO kanban_boards (user_id, name) VALUES (?, ?)')->execute([$userId, $name]);
    $id = (int) $db->lastInsertId();
    echo json_encode(['success' => true, 'id' => $id, 'param' => 'c' . $id, 'name' => $name]);
    exit;
});

// Update a custom board (rename and/or share toggle). Owner only.
$router->post('/api/kanban/boards/{id}', function (array $p) {
    $userId = _kanbanApiGuard();
    $db     = Database::connect();
    $board  = _kanbanRequireOwnedBoard($db, (int) $p['id'], $userId);
    $body   = _kanbanApiBody();

    $name     = array_key_exists('name', $body) ? trim((string) $body['name']) : $board['name'];
    $isShared = array_key_exists('is_shared', $body) ? (int) (bool) $body['is_shared'] : (int) $board['is_shared'];
    if ($name === '' || mb_strlen($name) > 100) {
        http_response_code(422);
        echo json_encode(['error' => 'A board name (1–100 chars) is required']);
        exit;
    }
    $db->prepare('UPDATE kanban_boards SET name = ?, is_shared = ? WHERE id = ?')
       ->execute([$name, $isShared, (int) $board['id']]);
    echo json_encode(['success' => true, 'name' => $name, 'is_shared' => $isShared]);
    exit;
});

// Delete a custom board (cascades to its buckets + placements). Owner only.
$router->post('/api/kanban/boards/{id}/delete', function (array $p) {
    $userId = _kanbanApiGuard();
    $db     = Database::connect();
    $board  = _kanbanRequireOwnedBoard($db, (int) $p['id'], $userId);
    $db->prepare('DELETE FROM kanban_boards WHERE id = ?')->execute([(int) $board['id']]);
    echo json_encode(['success' => true]);
    exit;
});

// Add a bucket (column) to a custom board. Owner only.
$router->post('/api/kanban/boards/{id}/buckets', function (array $p) {
    $userId = _kanbanApiGuard();
    $db     = Database::connect();
    $board  = _kanbanRequireOwnedBoard($db, (int) $p['id'], $userId);
    $body   = _kanbanApiBody();
    $name   = trim((string) ($body['name'] ?? ''));
    $color  = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($body['color'] ?? '')) ? (string) $body['color'] : '#6c757d';
    if ($name === '' || mb_strlen($name) > 100) {
        http_response_code(422);
        echo json_encode(['error' => 'A bucket name (1–100 chars) is required']);
        exit;
    }
    $next = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM kanban_buckets WHERE board_id = ' . (int) $board['id'])->fetchColumn();
    $db->prepare('INSERT INTO kanban_buckets (board_id, name, color, sort_order) VALUES (?, ?, ?, ?)')
       ->execute([(int) $board['id'], $name, $color, $next]);
    $bid = (int) $db->lastInsertId();
    echo json_encode(['success' => true, 'id' => $bid, 'name' => $name, 'color' => $color]);
    exit;
});

// Rename / recolor a bucket. Owner of the parent board only.
$router->post('/api/kanban/buckets/{id}', function (array $p) {
    $userId   = _kanbanApiGuard();
    $db       = Database::connect();
    $bucketId = (int) $p['id'];
    $row = $db->prepare(
        'SELECT b.*, bo.user_id AS owner_id FROM kanban_buckets b JOIN kanban_boards bo ON b.board_id = bo.id WHERE b.id = ?'
    );
    $row->execute([$bucketId]);
    $bucket = $row->fetch();
    if (!$bucket) {
        http_response_code(404);
        echo json_encode(['error' => 'Bucket not found']);
        exit;
    }
    if ((int) $bucket['owner_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the board owner can change it']);
        exit;
    }
    $body  = _kanbanApiBody();
    $name  = array_key_exists('name', $body) ? trim((string) $body['name']) : $bucket['name'];
    $color = (array_key_exists('color', $body) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $body['color']))
             ? (string) $body['color'] : $bucket['color'];
    if ($name === '' || mb_strlen($name) > 100) {
        http_response_code(422);
        echo json_encode(['error' => 'A bucket name (1–100 chars) is required']);
        exit;
    }
    $db->prepare('UPDATE kanban_buckets SET name = ?, color = ? WHERE id = ?')->execute([$name, $color, $bucketId]);
    echo json_encode(['success' => true, 'name' => $name, 'color' => $color]);
    exit;
});

// Delete a bucket (cards in it fall back to Unsorted). Owner only.
$router->post('/api/kanban/buckets/{id}/delete', function (array $p) {
    $userId   = _kanbanApiGuard();
    $db       = Database::connect();
    $bucketId = (int) $p['id'];
    $row = $db->prepare(
        'SELECT bo.user_id AS owner_id FROM kanban_buckets b JOIN kanban_boards bo ON b.board_id = bo.id WHERE b.id = ?'
    );
    $row->execute([$bucketId]);
    $ownerId = $row->fetchColumn();
    if ($ownerId === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Bucket not found']);
        exit;
    }
    if ((int) $ownerId !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the board owner can change it']);
        exit;
    }
    $db->prepare('DELETE FROM kanban_buckets WHERE id = ?')->execute([$bucketId]);
    echo json_encode(['success' => true]);
    exit;
});

// Move a ticket between buckets on a custom board (personal organizer; no
// ticket field changes). bucket_id 0/empty/'unsorted' clears the placement.
$router->post('/api/kanban/place', function () {
    $userId = _kanbanApiGuard();
    $db     = Database::connect();
    $body   = _kanbanApiBody();

    $boardId  = (int) ($body['board_id'] ?? 0);
    $ticketId = (int) ($body['ticket_id'] ?? 0);
    $rawBucket = $body['bucket_id'] ?? '';
    $bucketId  = ($rawBucket === '' || $rawBucket === 'unsorted') ? 0 : (int) $rawBucket;

    // Board must be visible to the user (owner or shared).
    $board = kanbanLoadBoard($db, $boardId, $userId);
    if (!$board) {
        http_response_code(404);
        echo json_encode(['error' => 'Board not found']);
        exit;
    }
    // Ticket must be one the user is allowed to touch.
    _apiRequireTicketAccess($db, $ticketId);

    $boardBucketIds = array_map('intval', array_column($board['buckets'], 'id'));

    // Target bucket (if any) must belong to this board.
    if ($bucketId !== 0 && !in_array($bucketId, $boardBucketIds, true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Bucket does not belong to this board']);
        exit;
    }

    // A ticket sits in at most one bucket per board: clear any existing
    // placement among this board's buckets, then (re)place if a target is given.
    if (!empty($boardBucketIds)) {
        $ph = implode(',', array_fill(0, count($boardBucketIds), '?'));
        $del = $db->prepare("DELETE FROM kanban_card_placements WHERE ticket_id = ? AND bucket_id IN ($ph)");
        $del->execute(array_merge([$ticketId], $boardBucketIds));
    }
    if ($bucketId !== 0) {
        $db->prepare('INSERT INTO kanban_card_placements (bucket_id, ticket_id) VALUES (?, ?)')
           ->execute([$bucketId, $ticketId]);
    }

    echo json_encode(['success' => true, 'bucket_id' => $bucketId ?: 'unsorted']);
    exit;
});
