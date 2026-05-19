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
 * Progressive Web App (manifest, service worker, icons, offline page)
 * ------------------------------------------------------------------ */
require ROOT_DIR . '/src/routes/pwa.php';

/* ------------------------------------------------------------------
 * Floor mode (tablet-friendly card view + bottom-sheet quick-create)
 * ------------------------------------------------------------------ */
require ROOT_DIR . '/src/routes/floor.php';

/* ------------------------------------------------------------------
 * Shared: enforce ticket-level access for JSON API endpoints.
 * Mirrors _agentRequireTicketAccess() but returns JSON 403 instead of redirect.
 * ------------------------------------------------------------------ */
function _apiRequireTicketAccess(PDO $db, int $ticketId): void
{
    $role = Auth::role();
    if ($role === 'admin') {
        return; // admins: unrestricted
    }

    $userId = (int) Auth::id();

    $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
    $gs->execute([$userId]);
    $agentGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

    $ts = $db->prepare('SELECT group_id, type_id FROM tickets WHERE id = ?');
    $ts->execute([$ticketId]);
    $ticket = $ts->fetch();
    if (!$ticket) {
        return; // ticket-not-found is handled by the caller
    }

    if (empty($agentGroups)) {
        // Agent not in any group — block confidential tickets they're not authorised for
        if (!empty($ticket['type_id'])) {
            $cStmt = $db->prepare('SELECT is_confidential, group_id FROM ticket_types WHERE id = ?');
            $cStmt->execute([$ticket['type_id']]);
            $cType = $cStmt->fetch();
            if ($cType && $cType['is_confidential'] && $cType['group_id']) {
                $inGroup = $db->prepare('SELECT 1 FROM group_user_map WHERE group_id = ? AND user_id = ?');
                $inGroup->execute([$cType['group_id'], $userId]);
                if (!$inGroup->fetchColumn() && !ticketAccessExempt($db, $userId, $ticketId)) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'You do not have access to this ticket.']);
                    exit;
                }
            }
        }
        return;
    }

    if ($ticket['group_id'] === null) {
        return; // unassigned ticket: visible to all agents
    }

    if (!in_array((int) $ticket['group_id'], $agentGroups, true)
        && !ticketAccessExempt($db, $userId, $ticketId)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'You do not have access to this ticket.']);
        exit;
    }
}

/* ------------------------------------------------------------------
 * Ticket Tag Management (JSON API)
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/tags', function (array $p) {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
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
    _apiRequireTicketAccess($db, $ticketId);

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
 * Quick Assign Ticket (JSON API)
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/assign', function (array $p) {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    header('Content-Type: application/json');

    $ticketId = (int) $p['id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $assignedToRaw = $input['assigned_to'] ?? null;
    $assignedTo = ($assignedToRaw !== null && $assignedToRaw !== '') ? (int) $assignedToRaw : null;

    $db = Database::connect();
    _apiRequireTicketAccess($db, $ticketId);

    $stmt = $db->prepare('SELECT id, group_id, assigned_to FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found']);
        exit;
    }

    // Verify assigned agent is in the ticket's group (if group is set)
    if ($assignedTo && $ticket['group_id']) {
        $check = $db->prepare('SELECT 1 FROM group_user_map WHERE group_id = ? AND user_id = ?');
        $check->execute([$ticket['group_id'], $assignedTo]);
        if (!$check->fetch()) {
            http_response_code(422);
            echo json_encode(['error' => 'Agent not in ticket group']);
            exit;
        }
    }

    $oldAssigned = $ticket['assigned_to'] ? (int) $ticket['assigned_to'] : null;

    // Compute agent name
    $agentName = null;
    if ($assignedTo) {
        $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
        $s->execute([$assignedTo]);
        $agentName = $s->fetchColumn() ?: 'Unknown';
    }

    if ($assignedTo !== $oldAssigned) {
        $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$assignedTo, $ticketId]);
        $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
           ->execute([$ticketId, Auth::id(), 'assigned', 'Assigned to ' . ($agentName ?? 'Unassigned')]);
        if ($assignedTo) { notifyAssignedAgent($db, $ticketId, $assignedTo); }
        runAutomations($db, $ticketId, 'ticket_updated');
    }

    echo json_encode(['success' => true, 'agent_name' => $agentName]);
    exit;
});

/* ------------------------------------------------------------------
 * Escalate Ticket (JSON API) — manual escalation up the path
 * ------------------------------------------------------------------ */
$router->get('/api/tickets/{id}/escalate/preview', function (array $p) {
    Auth::requireAuth();
    header('Content-Type: application/json');

    $ticketId = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT id, type_id, status, assigned_to, created_by, merged_into_ticket_id, escalation_level FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) { http_response_code(404); echo json_encode(['error' => 'Ticket not found']); exit; }

    if (in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        _apiRequireTicketAccess($db, $ticketId);
    } elseif ((int) $ticket['created_by'] !== (int) Auth::id()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }

    if (in_array($ticket['status'], ['resolved', 'closed'], true) || $ticket['merged_into_ticket_id']) {
        echo json_encode(['eligible' => false, 'reason' => 'This ticket is closed or merged and cannot be escalated.']);
        exit;
    }
    if (!$ticket['type_id']) {
        echo json_encode(['eligible' => false, 'reason' => 'Set a ticket type first — escalation paths are defined per type.']);
        exit;
    }

    $next = nextEscalationStep(
        $db,
        (int) $ticket['type_id'],
        (int) $ticket['escalation_level'],
        (int) Auth::id(),
        $ticket['assigned_to'] ? (int) $ticket['assigned_to'] : null
    );
    if (!$next) {
        echo json_encode(['eligible' => false, 'reason' => 'No further escalation step is defined for this ticket type.']);
        exit;
    }

    echo json_encode([
        'eligible'         => true,
        'next_user_id'     => $next['user_id'],
        'next_user_name'   => $next['user_name'],
        'next_step_order'  => $next['step_order'],
        'next_step_label'  => $next['label'],
        'current_level'    => (int) $ticket['escalation_level'],
    ]);
    exit;
});

$router->post('/api/tickets/{id}/escalate', function (array $p) {
    Auth::requireAuth();
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); exit;
    }
    header('Content-Type: application/json');

    $ticketId = (int) $p['id'];
    $input    = json_decode(file_get_contents('php://input'), true) ?: [];
    $reason   = trim((string) ($input['reason'] ?? ''));
    if ($reason !== '' && mb_strlen($reason) > 2000) {
        $reason = mb_substr($reason, 0, 2000);
    }

    $db = Database::connect();

    $stmt = $db->prepare('SELECT id, type_id, status, assigned_to, created_by, merged_into_ticket_id, escalation_level FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) { http_response_code(404); echo json_encode(['error' => 'Ticket not found']); exit; }

    if (in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        _apiRequireTicketAccess($db, $ticketId);
    } elseif ((int) $ticket['created_by'] !== (int) Auth::id()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }

    if (in_array($ticket['status'], ['resolved', 'closed'], true) || $ticket['merged_into_ticket_id']) {
        http_response_code(422);
        echo json_encode(['error' => 'This ticket is closed or merged and cannot be escalated.']);
        exit;
    }
    if (!$ticket['type_id']) {
        http_response_code(422);
        echo json_encode(['error' => 'Set a ticket type first — escalation paths are defined per type.']);
        exit;
    }

    $actorId = (int) Auth::id();
    $next = nextEscalationStep(
        $db,
        (int) $ticket['type_id'],
        (int) $ticket['escalation_level'],
        $actorId,
        $ticket['assigned_to'] ? (int) $ticket['assigned_to'] : null
    );
    if (!$next) {
        http_response_code(422);
        echo json_encode(['error' => 'No further escalation step is defined for this ticket type.']);
        exit;
    }

    $fromUserId = $ticket['assigned_to'] ? (int) $ticket['assigned_to'] : null;
    $toUserId   = $next['user_id'];
    $stepOrder  = $next['step_order'];

    $db->beginTransaction();
    try {
        $db->prepare('UPDATE tickets SET assigned_to = ?, escalation_level = ? WHERE id = ?')
           ->execute([$toUserId, $stepOrder, $ticketId]);

        $detail = 'Escalated to ' . $next['user_name'] . ' (Level ' . $stepOrder;
        if (!empty($next['label'])) {
            $detail .= ' — ' . $next['label'];
        }
        $detail .= ')';
        if ($reason !== '') {
            $detail .= "\nReason: " . $reason;
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$ticketId, $actorId, 'escalated', $detail]);

        $db->prepare(
            'INSERT INTO ticket_escalations (ticket_id, from_user_id, to_user_id, step_order, reason, escalated_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$ticketId, $fromUserId, $toUserId, $stepOrder, $reason !== '' ? $reason : null, $actorId]);

        // Keep the previous assignee in the loop as a watcher (visible backup).
        if ($fromUserId && $fromUserId !== $toUserId) {
            $db->prepare('INSERT IGNORE INTO ticket_watchers (ticket_id, user_id) VALUES (?, ?)')
               ->execute([$ticketId, $fromUserId]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to escalate: ' . $e->getMessage()]);
        exit;
    }

    logAudit('ticket.escalated', $ticketId, 'ticket', 'Level ' . $stepOrder . ' → ' . $next['user_name']
        . ($reason !== '' ? ' | Reason: ' . $reason : ''));

    notifyEscalation($db, $ticketId, $toUserId, $actorId, $stepOrder, $next['label'], $reason !== '' ? $reason : null, $fromUserId);

    runAutomations($db, $ticketId, 'ticket_updated');

    echo json_encode([
        'success'         => true,
        'to_user_id'      => $toUserId,
        'to_user_name'    => $next['user_name'],
        'step_order'      => $stepOrder,
        'step_label'      => $next['label'],
        'escalation_level'=> $stepOrder,
    ]);
    exit;
});

/* ------------------------------------------------------------------
 * Quick Set Type (JSON API)
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/set-type', function (array $p) {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); exit;
    }
    header('Content-Type: application/json');

    $ticketId = (int) $p['id'];
    $input    = json_decode(file_get_contents('php://input'), true);
    $typeId   = isset($input['type_id']) && $input['type_id'] !== null && $input['type_id'] !== ''
                ? (int) $input['type_id'] : null;

    $db = Database::connect();
    _apiRequireTicketAccess($db, $ticketId);
    $stmt = $db->prepare('SELECT id, type_id FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) { http_response_code(404); echo json_encode(['error' => 'Ticket not found']); exit; }

    $oldTypeId = $ticket['type_id'] ? (int) $ticket['type_id'] : null;
    $typeName  = null;
    $typeColor = null;
    if ($typeId) {
        $ts = $db->prepare('SELECT name, color FROM ticket_types WHERE id = ?');
        $ts->execute([$typeId]);
        $row = $ts->fetch();
        if ($row) { $typeName = $row['name']; $typeColor = $row['color']; }
    }

    if ($typeId !== $oldTypeId) {
        $db->prepare('UPDATE tickets SET type_id = ? WHERE id = ?')->execute([$typeId, $ticketId]);
        $oldName = 'None';
        if ($oldTypeId) {
            $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
            $s->execute([$oldTypeId]);
            $oldName = $s->fetchColumn() ?: 'None';
        }
        $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
           ->execute([$ticketId, Auth::id(), 'type_changed', 'Type changed from ' . $oldName . ' to ' . ($typeName ?? 'None')]);
        runAutomations($db, $ticketId, 'ticket_updated');
    }
    echo json_encode(['success' => true, 'type_name' => $typeName, 'type_color' => $typeColor ?: '#6c757d']);
    exit;
});

/* ------------------------------------------------------------------
 * Quick Set Group (JSON API)
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/set-group', function (array $p) {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); exit;
    }
    header('Content-Type: application/json');

    $ticketId = (int) $p['id'];
    $input    = json_decode(file_get_contents('php://input'), true);
    $groupId  = isset($input['group_id']) && $input['group_id'] !== null && $input['group_id'] !== ''
                ? (int) $input['group_id'] : null;

    $db = Database::connect();
    _apiRequireTicketAccess($db, $ticketId);
    $stmt = $db->prepare('SELECT id, group_id FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) { http_response_code(404); echo json_encode(['error' => 'Ticket not found']); exit; }

    $oldGroupId = $ticket['group_id'] ? (int) $ticket['group_id'] : null;
    $groupName  = null;
    if ($groupId) {
        $gs = $db->prepare('SELECT name FROM `groups` WHERE id = ?');
        $gs->execute([$groupId]);
        $groupName = $gs->fetchColumn() ?: null;
    }

    if ($groupId !== $oldGroupId) {
        $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$groupId, $ticketId]);
        $oldName = 'None';
        if ($oldGroupId) {
            $s = $db->prepare('SELECT name FROM `groups` WHERE id = ?');
            $s->execute([$oldGroupId]);
            $oldName = $s->fetchColumn() ?: 'None';
        }
        $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
           ->execute([$ticketId, Auth::id(), 'group_changed', 'Group changed from ' . $oldName . ' to ' . ($groupName ?? 'None')]);
        if ($groupId) { notifyAssignedGroup($db, $ticketId, $groupId); }
        runAutomations($db, $ticketId, 'ticket_updated');
    }

    // Return agents for the new group so the client can update the agent dropdown
    if ($groupId) {
        $as = $db->prepare("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name
                             FROM users u JOIN group_user_map gum ON u.id = gum.user_id
                             WHERE gum.group_id = ? AND u.role IN ('agent','admin','power_user')
                             ORDER BY u.first_name, u.last_name");
        $as->execute([$groupId]);
    } else {
        $as = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users
                          WHERE role IN ('agent','admin','power_user') ORDER BY first_name, last_name");
    }
    $agents = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $as->fetchAll());
    echo json_encode(['success' => true, 'group_name' => $groupName, 'agents' => $agents]);
    exit;
});

/* ------------------------------------------------------------------
 * User Search for CC (JSON API)
 * ------------------------------------------------------------------ */
$router->get('/api/user-search', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
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
 * CC User Search for portal users (any authenticated user)
 * ------------------------------------------------------------------ */
$router->get('/api/cc-search', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        http_response_code(403);
        echo json_encode([]);
        exit;
    }
    header('Content-Type: application/json');

    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $like = '%' . $q . '%';
    $db   = Database::connect();
    $stmt = $db->prepare(
        "SELECT id, first_name, last_name, email
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
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
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
    _apiRequireTicketAccess($db, $ticketId);

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
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
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
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    $db = Database::connect();
    _apiRequireTicketAccess($db, (int) $p['id']);
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
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $db = Database::connect();
    _apiRequireTicketAccess($db, (int) $p['id']);
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
    _apiRequireTicketAccess($db, (int) $p['id']);
    $db->prepare(
        'DELETE FROM ticket_presence WHERE ticket_id = ? AND user_id = ?'
    )->execute([(int) $p['id'], Auth::id()]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
});

/* ------------------------------------------------------------------
 * Global User Presence (Logged-in / app-open tracking)
 * ------------------------------------------------------------------
 * The base layout heartbeats every 30s for any authenticated user.
 * `last_seen` within the last 60s is treated as "currently online" by
 * the admin Who's Online page and the First Available auto-assign
 * strategy.
 *
 * No CSRF on the heartbeat: it's idempotent, takes no body, and a
 * sendBeacon-style leave call can't easily attach custom headers.
 * Auth + same-origin cookie is sufficient.
 * ------------------------------------------------------------------ */
$router->post('/api/presence', function () {
    Auth::requireAuth();
    $db = Database::connect();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
    $db->prepare(
        'INSERT INTO user_presence (user_id, last_seen, ip_address, user_agent)
         VALUES (?, NOW(), ?, ?)
         ON DUPLICATE KEY UPDATE last_seen = NOW(), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent)'
    )->execute([Auth::id(), $ip, $ua]);
    sweepStalePresence(120);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
});

$router->post('/api/presence/leave', function () {
    Auth::requireAuth();
    $db  = Database::connect();
    $uid = Auth::id();
    $row = null;
    try {
        $stmt = $db->prepare('SELECT ip_address, user_agent FROM user_presence WHERE user_id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (\Throwable $e) {
        // ignore — audit detail is best-effort
    }
    $db->prepare('DELETE FROM user_presence WHERE user_id = ?')->execute([$uid]);
    if ($row) {
        $detail = 'ua=' . (string) ($row['user_agent'] ?? '');
        logAudit('session.tab_closed', null, null, $detail);
    } else {
        logAudit('session.tab_closed');
    }
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
        // Strip leading '#' so users can type e.g. "#123" to find ticket 123
        $qStripped = ltrim($q, '#');
        $ticketIdClause = '';
        $ticketIdParams = [];
        if (is_numeric($qStripped) && (int)$qStripped > 0) {
            $ticketIdClause = ' OR t.id = ?';
            $ticketIdParams[] = (int)$qStripped;
        }
        $ticketWhere = '(t.subject LIKE ? OR t.description LIKE ?' . $ticketIdClause . ')';
        $ticketParams = array_merge([$like, $like], $ticketIdParams);

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
    if (($type === 'all' || $type === 'contacts') && in_array($role, ['admin', 'agent', 'power_user'], true)) {
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
            'admin'      => redirect('/admin'),
            'agent'      => redirect('/agent'),
            'power_user' => redirect('/agent'),
            default      => redirect('/portal'),
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
    // Accept ?next=/path so direct links like /login?next=/portal/tickets/create?type_id=5
    // round-trip through login. Apply the same safe-relative-URL gate that
    // consumeIntendedUrl() uses on the read side.
    if (isset($_GET['next']) && is_string($_GET['next'])) {
        $next = $_GET['next'];
        if (
            $next !== '' && strlen($next) <= 2000
            && $next[0] === '/'
            && (!isset($next[1]) || ($next[1] !== '/' && $next[1] !== '\\'))
            && $next !== '/login' && strncmp($next, '/login?', 7) !== 0
        ) {
            $_SESSION['intended_url'] = $next;
        }
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
            // Keep $_SESSION['intended_url'] set — consumed after 2FA succeeds.
            redirect('/2fa');
        }
        logAudit('auth.login');
        redirect(consumeIntendedUrl());
    }

    // Resolve the email to a user_id so the failed attempt is tied to the
    // targeted account when one exists — important for spotting credential-
    // stuffing patterns from the audit log.
    $db = Database::connect();
    $uidStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $uidStmt->execute([$email]);
    $targetUid = $uidStmt->fetchColumn() ?: null;
    logAudit('auth.login_failed', null, null, 'email=' . $email, $targetUid ? (int) $targetUid : null);

    render('login', ['error' => 'Invalid email or password.', 'email' => $email]);
});

$router->get('/logout', function () {
    logAudit('auth.logout');
    Auth::logout();
    redirect('/login');
});

/* ------------------------------------------------------------------
 * Forgot password — email-a-link flow
 *
 * Three routes:
 *   GET  /forgot           — request form
 *   POST /forgot           — accept email, mint token, email link
 *   GET  /reset?token=...  — show "set new password" form (token in URL)
 *   POST /reset            — accept token + new password, update + sign in
 *
 * The submit handler always shows the same generic "if the email
 * matches an account we sent a link" response so an attacker can't
 * use this endpoint to enumerate registered email addresses. We also
 * rate-limit reset requests per email + per IP in the existing
 * `login_attempts` table (reusing the same 15-minute sliding window)
 * so this can't be used to flood a user's inbox.
 *
 * Tokens are 32 random bytes; only their SHA-256 hash is stored.
 * They expire after 60 minutes and are single-use (the row's
 * `used_at` is stamped on successful reset).
 * ------------------------------------------------------------------ */

const _PASSWORD_RESET_TTL_MINUTES = 60;
const _PASSWORD_RESET_MAX_PER_EMAIL_WINDOW = 5;
const _PASSWORD_RESET_WINDOW_MINUTES = 15;

$router->get('/forgot', function () {
    if (Auth::check()) {
        redirect('/');
    }
    render('forgot');
});

$router->post('/forgot', function () {
    $email = trim($_POST['email'] ?? '');
    $token = $_POST['_token'] ?? '';

    if (!verifyCsrf($token)) {
        render('forgot', ['error' => 'Invalid request. Please try again.', 'email' => $email]);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        render('forgot', ['error' => 'Please enter a valid email address.', 'email' => $email]);
    }

    $db = Database::connect();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Throttle: cap requests in a sliding window so this endpoint can't
    // be used to spam someone's inbox or hammer SMTP. We piggyback on
    // login_attempts with a synthetic email key so the existing column
    // and indexes do the work.
    $window = _PASSWORD_RESET_WINDOW_MINUTES;
    $throttleKey = 'pwreset:' . strtolower($email);
    $recentByEmail = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts
          WHERE email = ? AND succeeded = 0
            AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)"
    );
    $recentByEmail->execute([$throttleKey]);
    $tooMany = (int) $recentByEmail->fetchColumn() >= _PASSWORD_RESET_MAX_PER_EMAIL_WINDOW;

    // Record the request attempt regardless of whether we end up sending
    // (so attackers can't enumerate via timing or throttle behaviour).
    $db->prepare('INSERT INTO login_attempts (email, ip, succeeded) VALUES (?, ?, 0)')
       ->execute([$throttleKey, $ip]);

    if (!$tooMany) {
        $stmt = $db->prepare('SELECT id, first_name, last_name, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expires   = (new DateTime('+' . _PASSWORD_RESET_TTL_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

            $db->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip)
                 VALUES (?, ?, ?, ?)'
            )->execute([(int) $user['id'], $tokenHash, $expires, $ip]);

            $resetUrl   = appUrl() . '/reset?token=' . $rawToken;
            $appName    = getSetting('branding_app_name', 'OpenHelpDesk');
            $brandColor = getSetting('branding_primary_color', '#4f46e5');
            $footerText = getSetting('email_footer_text')
                ?: 'This is an automated message from ' . $appName . '. If you did not request a password reset you can safely ignore this email.';

            $emailHtml = renderEmail('password-reset', [
                'firstName'  => $user['first_name'] ?: $user['email'],
                'resetUrl'   => $resetUrl,
                'ttlMinutes' => _PASSWORD_RESET_TTL_MINUTES,
                'appName'    => $appName,
                'brandColor' => $brandColor,
                'footerText' => $footerText,
            ]);

            sendMail(
                $user['email'],
                trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'Reset your ' . $appName . ' password',
                $emailHtml
            );

            logAudit('auth.password_reset_requested', (int) $user['id'], 'user', 'email=' . $email);
        }
    }

    render('forgot', [
        'sent'  => true,
        'email' => $email,
    ]);
});

$router->get('/reset', function () {
    if (Auth::check()) {
        redirect('/');
    }
    $rawToken = (string) ($_GET['token'] ?? '');
    if ($rawToken === '' || !ctype_xdigit($rawToken) || strlen($rawToken) !== 64) {
        render('reset', ['error' => 'This reset link is invalid or has expired. Please request a new one.']);
    }

    $db   = Database::connect();
    $hash = hash('sha256', $rawToken);
    $stmt = $db->prepare(
        'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email
           FROM password_resets pr
           JOIN users u ON u.id = pr.user_id
          WHERE pr.token_hash = ?
          LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
        render('reset', ['error' => 'This reset link is invalid or has expired. Please request a new one.']);
    }

    render('reset', [
        'token' => $rawToken,
        'email' => $row['email'],
    ]);
});

$router->post('/reset', function () {
    $rawToken = (string) ($_POST['token'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');
    $csrf     = $_POST['_token'] ?? '';

    if (!verifyCsrf($csrf)) {
        render('reset', ['error' => 'Invalid request. Please try again.', 'token' => $rawToken]);
    }
    if ($rawToken === '' || !ctype_xdigit($rawToken) || strlen($rawToken) !== 64) {
        render('reset', ['error' => 'This reset link is invalid or has expired. Please request a new one.']);
    }
    if (strlen($password) < 8) {
        render('reset', ['error' => 'Password must be at least 8 characters.', 'token' => $rawToken]);
    }
    if ($password !== $confirm) {
        render('reset', ['error' => 'Passwords do not match.', 'token' => $rawToken]);
    }

    $db   = Database::connect();
    $hash = hash('sha256', $rawToken);
    $stmt = $db->prepare(
        'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at
           FROM password_resets pr
          WHERE pr.token_hash = ?
          LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
        render('reset', ['error' => 'This reset link is invalid or has expired. Please request a new one.']);
    }

    $userId = (int) $row['user_id'];
    $db->prepare('UPDATE users SET password = ? WHERE id = ?')
       ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
       ->execute([(int) $row['id']]);
    // Invalidate any other outstanding reset links for this account.
    $db->prepare('UPDATE password_resets SET used_at = NOW()
                  WHERE user_id = ? AND used_at IS NULL')
       ->execute([$userId]);

    logAudit('auth.password_reset_completed', $userId, 'user', null, $userId);

    flash('success', 'Your password has been reset. Please sign in.');
    redirect('/login');
});

/* ------------------------------------------------------------------
 * Microsoft 365 SSO – OAuth 2.0 Authorization Code Flow
 * ------------------------------------------------------------------ */

function ssoDebugLog(string $message): void
{
    if (getSetting('sso_debug', '0') !== '1') {
        return;
    }
    $logDir  = ROOT_DIR . '/storage/logs';
    $logFile = $logDir . '/sso-debug.log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

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
    $body     = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        ssoDebugLog("POST {$url} -> curl error: {$curlErr}");
        return null;
    }
    $data    = json_decode($body, true);
    $errCode = is_array($data) ? ($data['error'] ?? null) : null;
    $errMsg  = is_array($data) ? ($data['error_description'] ?? null) : null;
    if ($errCode) {
        ssoDebugLog("POST {$url} HTTP {$httpCode} -> error={$errCode} | {$errMsg}");
    } else {
        ssoDebugLog("POST {$url} HTTP {$httpCode} -> " . (is_array($data) ? 'OK' : 'invalid JSON'));
    }
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
    $body     = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        ssoDebugLog("GET {$url} -> curl error: {$curlErr}");
        return null;
    }
    $data    = json_decode($body, true);
    $errCode = is_array($data) ? ($data['error'] ?? null) : null;
    if ($errCode) {
        ssoDebugLog("GET {$url} HTTP {$httpCode} -> error=" . json_encode($data['error'] ?? $data));
    } else {
        ssoDebugLog("GET {$url} HTTP {$httpCode} -> OK");
    }
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
    $redirectUri = appUrl() . '/auth/microsoft/callback';

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
    ssoDebugLog('--- SSO callback start | IP=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

    // 1. Verify state (CSRF guard on OAuth flow)
    $state        = $_GET['state'] ?? '';
    $sessionState = $_SESSION['sso_state'] ?? '';
    if ($state === '' || $state !== $sessionState) {
        ssoDebugLog('State mismatch – received=' . substr($state, 0, 16) . '... session=' . substr($sessionState, 0, 16) . '...');
        unset($_SESSION['sso_state']);
        redirect('/login?sso_error=state');
    }
    unset($_SESSION['sso_state']);
    ssoDebugLog('State verified OK');

    // 2. Check for user-denied / consent errors
    if (!empty($_GET['error'])) {
        ssoDebugLog('Microsoft returned error: ' . ($_GET['error'] ?? '') . ' | ' . ($_GET['error_description'] ?? ''));
        redirect('/login?sso_error=denied');
    }

    $code = $_GET['code'] ?? '';
    if ($code === '') {
        ssoDebugLog('No authorization code in callback');
        redirect('/login?sso_error=token');
    }
    ssoDebugLog('Authorization code received (length=' . strlen($code) . ')');

    $tenantId     = getSetting('sso_tenant_id');
    $clientId     = getSetting('sso_client_id');
    $clientSecret = getSetting('sso_client_secret');
    $redirectUri  = rtrim(env('APP_URL', ''), '/') . '/auth/microsoft/callback';

    ssoDebugLog("Config: tenant_id={$tenantId} | client_id={$clientId} | secret_set=" . ($clientSecret !== '' ? 'yes' : 'NO') . " | redirect_uri={$redirectUri}");

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
        ssoDebugLog('Token exchange failed - no access_token. Keys=' . json_encode(array_keys($tokens ?? [])));
        redirect('/login?sso_error=token');
    }
    ssoDebugLog('Token exchange OK | expires_in=' . ($tokens['expires_in'] ?? '?') . ' | token_type=' . ($tokens['token_type'] ?? '?'));

    // 4. Fetch user profile from Microsoft Graph
    $me = ssoHttpGet('https://graph.microsoft.com/v1.0/me', $tokens['access_token']);

    if (empty($me['id'])) {
        ssoDebugLog('Graph /me failed - no id. Keys=' . json_encode(array_keys($me ?? [])));
        redirect('/login?sso_error=graph');
    }

    $oid       = $me['id'];
    $email     = $me['mail'] ?? $me['userPrincipalName'] ?? '';
    $parts     = explode(' ', trim($me['displayName'] ?? 'SSO User'));
    $firstName = $me['givenName'] ?? $parts[0];
    $lastName  = $me['surname']   ?? (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'User');

    ssoDebugLog("Graph /me: oid={$oid} | email={$email} | name={$firstName} {$lastName} | mail=" . ($me['mail'] ?? 'null') . " | upn=" . ($me['userPrincipalName'] ?? 'null'));

    if ($email === '') {
        ssoDebugLog('Email empty - neither mail nor userPrincipalName set');
        redirect('/login?sso_error=graph');
    }

    $db   = Database::connect();
    $user = null;

    // 5. Look up by Azure OID first, then by email
    $stmt = $db->prepare('SELECT * FROM users WHERE azure_oid = ? LIMIT 1');
    $stmt->execute([$oid]);
    $user = $stmt->fetch() ?: null;

    if ($user) {
        ssoDebugLog("User found by azure_oid: id={$user['id']} | email={$user['email']} | role={$user['role']}");
    } else {
        ssoDebugLog("No user by azure_oid={$oid} - trying email lookup");
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch() ?: null;
        if ($user) {
            ssoDebugLog("User found by email: id={$user['id']} | role={$user['role']} - linking azure_oid");
            // Link OID to the existing password-login account
            $db->prepare('UPDATE users SET azure_oid = ? WHERE id = ?')
               ->execute([$oid, $user['id']]);
            $user['azure_oid'] = $oid;
        } else {
            ssoDebugLog("No user found by email={$email} - will auto-create");
        }
    }

    $isBrandNew = false;

    // 6. Auto-create account if no match found
    if (!$user) {
        ssoDebugLog("Auto-creating user: {$firstName} {$lastName} <{$email}> role=user");
        $db->prepare(
            'INSERT INTO users (first_name, last_name, email, azure_oid, password, role)
             VALUES (?, ?, ?, ?, \'\', \'user\')'
        )->execute([$firstName, $lastName, $email, $oid]);

        $newId = (int) $db->lastInsertId();
        $stmt  = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$newId]);
        $user       = $stmt->fetch();
        $isBrandNew = true;
        ssoDebugLog("New user created: id={$newId}");
    }

    // 7. Log in
    session_regenerate_id(true);
    ssoSetSessionUser($user);
    logAudit('auth.login');
    ssoDebugLog("Login successful: user_id={$user['id']} | email={$user['email']} | brand_new=" . ($isBrandNew ? 'yes' : 'no'));

    // 8. Redirect to location picker when needed
    $locationPrompt = getSetting('sso_location_prompt', 'sso_only');
    if (empty($user['location_id']) && ($isBrandNew || $locationPrompt === 'all')) {
        $_SESSION['sso_needs_location'] = true;
        ssoDebugLog('Location picker required - user has no location_id');
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
    $appName   = trim($_POST['app_name']   ?? 'OpenHelpDesk');

    $errors = [];
    if ($firstName === '') $errors[] = 'First name is required.';
    if ($lastName  === '') $errors[] = 'Last name is required.';
    if ($email     === '') $errors[] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if ($appName   === '') $appName = 'OpenHelpDesk';

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
        logAudit('auth.login');
        redirect(consumeIntendedUrl());
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
    render('profile/edit', ['profileUser' => $user, 'theme' => $theme]);
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

    // Snapshot the fields we care about for the audit log before updating
    $beforeStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $beforeStmt->execute([$userId]);
    $beforeProfile = $beforeStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

    // Update name (password handled by /profile/password)
    $db->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?')
       ->execute([$fn, $ln, $userId]);

    logAuditChange(
        'user.profile_updated',
        $userId,
        'user',
        $beforeProfile,
        ['first_name' => $fn, 'last_name' => $ln]
    );

    // Save theme preference
    $theme = in_array($_POST['theme'] ?? '', ['light', 'dark'], true) ? $_POST['theme'] : 'light';
    setSetting('ui_theme:' . $userId, $theme);

    // Save notification preferences
    $db->prepare(
        'UPDATE users SET
            notify_ticket_created    = ?,
            notify_ticket_updated    = ?,
            notify_ticket_cc         = ?,
            notify_ticket_merged     = ?,
            notify_escalation        = ?,
            notify_csat              = ?,
            notify_group_new_ticket  = ?,
            notify_assigned_to_me    = ?,
            notify_assigned_to_group = ?,
            notify_requester_replied = ?,
            notify_note_added        = ?,
            notify_ticket_solved     = ?,
            notify_ticket_closed     = ?,
            notify_ticket_assigned   = ?
         WHERE id = ?'
    )->execute([
        isset($_POST['notify_ticket_created'])    ? 1 : 0,
        isset($_POST['notify_ticket_updated'])    ? 1 : 0,
        isset($_POST['notify_ticket_cc'])         ? 1 : 0,
        isset($_POST['notify_ticket_merged'])     ? 1 : 0,
        isset($_POST['notify_escalation'])        ? 1 : 0,
        isset($_POST['notify_csat'])              ? 1 : 0,
        isset($_POST['notify_group_new_ticket'])  ? 1 : 0,
        isset($_POST['notify_assigned_to_me'])    ? 1 : 0,
        isset($_POST['notify_assigned_to_group']) ? 1 : 0,
        isset($_POST['notify_requester_replied']) ? 1 : 0,
        isset($_POST['notify_note_added'])        ? 1 : 0,
        isset($_POST['notify_ticket_solved'])     ? 1 : 0,
        isset($_POST['notify_ticket_closed'])     ? 1 : 0,
        isset($_POST['notify_ticket_assigned'])   ? 1 : 0,
        $userId,
    ]);

    // Refresh session so navbar reflects changes immediately
    $_SESSION['user']['first_name'] = $fn;
    $_SESSION['user']['last_name']  = $ln;

    flash('success', 'Profile updated successfully.');
    redirect('/profile');
});

$router->post('/profile/password', function () {
    Auth::requireAuth();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/profile');
        return;
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '') {
        flash('error', 'Please fill in both your current and new password.');
        redirect('/profile');
        return;
    }

    $db     = Database::connect();
    $userId = Auth::id();

    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($currentPassword, $hash)) {
        flash('error', 'Current password is incorrect.');
        redirect('/profile');
        return;
    }

    if (strlen($newPassword) < 8) {
        flash('error', 'New password must be at least 8 characters.');
        redirect('/profile');
        return;
    }

    if ($newPassword !== $confirmPassword) {
        flash('error', 'New password and confirmation do not match.');
        redirect('/profile');
        return;
    }

    $db->prepare('UPDATE users SET password = ? WHERE id = ?')
       ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

    logAudit('auth.password_changed', $userId, 'user');

    flash('success', 'Password updated successfully.');
    redirect('/profile');
});

/* ------------------------------------------------------------------
 * 2FA Setup (admin / agent only)
 * ------------------------------------------------------------------ */
$router->get('/profile/2fa/setup', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
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
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
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
    logAudit('auth.2fa_enabled');
    flash('success', 'Two-factor authentication has been enabled.');
    redirect('/profile');
});

$router->post('/profile/2fa/disable', function () {
    Auth::requireAuth();
    if (!in_array(Auth::role(), ['admin', 'agent', 'power_user'], true)) {
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

    logAudit('auth.2fa_disabled');
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
        'appName'     => getSetting('app_name', 'OpenHelpDesk'),
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
        'appName'    => getSetting('app_name', 'OpenHelpDesk'),
        'brandColor' => getSetting('branding_primary_color', '#4f46e5'),
    ]);
});

$router->get('/survey/{token}/reopen', function (array $p) {
    $token = preg_replace('/[^a-f0-9]/', '', $p['token'] ?? '');
    if (strlen($token) !== 64) {
        http_response_code(404);
        render('errors/404');
        return;
    }

    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT cs.*, t.subject, t.id AS ticket_id, t.status AS ticket_status
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

    // If already reopened or already rated, just show the page without re-doing it
    if ($survey['reopened_at'] === null && $survey['responded_at'] === null) {
        // Reopen the ticket
        $db->prepare('UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?')
           ->execute(['open', $survey['ticket_id']]);

        // Record the reopen in the survey
        $db->prepare('UPDATE csat_surveys SET reopened_at = NOW() WHERE token = ?')
           ->execute([$token]);

        // Add a timeline entry
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
             VALUES (?, NULL, ?, ?, 1)'
        )->execute([
            $survey['ticket_id'],
            'status_changed',
            'Customer reported issue not resolved via CSAT survey — ticket reopened to open',
        ]);
    }

    render('survey/reopened', [
        'ticketId' => (int) $survey['ticket_id'],
        'appName'  => getSetting('app_name', 'OpenHelpDesk'),
    ]);
});

/* ------------------------------------------------------------------
 * Portal (all authenticated users)
 * ------------------------------------------------------------------ */
$router->post('/portal/tour/dismiss', function () {
    Auth::requireAuth();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false]);
        exit;
    }
    $db = Database::connect();
    $db->prepare('UPDATE users SET show_portal_tour = 0 WHERE id = ?')->execute([Auth::id()]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
});

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
                tt.name AS type_name, tt.color AS type_color,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         WHERE t.created_by = ? AND t.status NOT IN ('resolved','closed')
         ORDER BY t.created_at DESC LIMIT 5"
    );
    $recentTickets->execute([$userId]);
    $recentTickets = $recentTickets->fetchAll();

    // Determine whether to auto-show the portal tour
    $autoShowTour = false;
    $tourStmt = $db->prepare('SELECT show_portal_tour FROM users WHERE id = ?');
    $tourStmt->execute([$userId]);
    $showFlag = $tourStmt->fetchColumn();
    if ($showFlag !== false) {
        $autoShowTour = (bool) $showFlag || isset($_GET['tour']);
    } elseif (isset($_GET['tour'])) {
        $autoShowTour = true;
    }

    render('portal/dashboard', [
        'openCount'     => $openCount,
        'resolvedCount' => $resolvedCount,
        'recentTickets' => $recentTickets,
        'autoShowTour'  => $autoShowTour,
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
$router->post('/agent/tour/dismiss', function () {
    Auth::requireRole('agent', 'admin');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false]);
        exit;
    }
    $db = Database::connect();
    $db->prepare('UPDATE users SET show_agent_tour = 0 WHERE id = ?')->execute([Auth::id()]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
});

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

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to = ? AND escalation_level > 0 AND status IN ('open','in_progress','pending')" . $groupRestriction);
    $stmt->execute(array_merge([$agentId], $groupParams));
    $escalatedToMe = (int) $stmt->fetchColumn();

    // Recent tickets (open/in_progress/pending, newest first)
    $trRestriction = $groupRestriction ? str_replace('AND group_id', 'AND t.group_id', $groupRestriction) : '';
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.status, t.created_at, t.group_id, t.sla_state, t.due_date,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name, tt.color AS type_color, tt.is_confidential AS type_confidential, tt.group_id AS type_group_id,
                g.name AS group_name,
                l.name AS location_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt      ON t.type_id     = tt.id
         LEFT JOIN `groups` g           ON t.group_id    = g.id
         LEFT JOIN locations l          ON t.location_id = l.id
         LEFT JOIN users c ON t.created_by = c.id
         LEFT JOIN users a ON t.assigned_to = a.id
         WHERE t.status IN ('open','in_progress','pending')" . $trRestriction . "
         ORDER BY t.created_at DESC
         LIMIT 10"
    );
    $stmt->execute($groupParams);
    $recent = $stmt->fetchAll();

    // Quick-assign / type / group dropdown data for dashboard
    $dashTypes = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $gaRows2 = $db->query(
        "SELECT gum.group_id, u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
         FROM group_user_map gum
         JOIN users u ON gum.user_id = u.id
         WHERE u.role IN ('agent','admin','power_user')
         ORDER BY u.first_name, u.last_name"
    )->fetchAll();
    $dashGroupAgents = [];
    foreach ($gaRows2 as $row) {
        $dashGroupAgents[(int) $row['group_id']][] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
    $dashAllAgents = $db->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role IN ('agent','admin','power_user') ORDER BY first_name, last_name"
    )->fetchAll();
    if (!empty($agentGroupIds)) {
        $ph2 = implode(',', array_fill(0, count($agentGroupIds), '?'));
        $gStmt2 = $db->prepare("SELECT * FROM `groups` WHERE id IN ($ph2) ORDER BY sort_order, name");
        $gStmt2->execute($agentGroupIds);
        $dashGroups = $gStmt2->fetchAll();
    } else {
        $dashGroups = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    }
    $dashVisibleColumns = getUserColumns(Auth::id());

    // Determine whether to auto-show the agent tour
    $autoShowTour = false;
    if (Auth::role() === 'agent') {
        $tourStmt = $db->prepare('SELECT show_agent_tour FROM users WHERE id = ?');
        $tourStmt->execute([$agentId]);
        $autoShowTour = (bool) $tourStmt->fetchColumn() || isset($_GET['tour']);
    } elseif (isset($_GET['tour'])) {
        $autoShowTour = true; // Let admins preview the agent tour
    }

    render('agent/dashboard', [
        'unassigned'         => $unassigned,
        'myTickets'          => $myTickets,
        'pending'            => $pending,
        'resolvedToday'      => $resolvedToday,
        'escalatedToMe'      => $escalatedToMe,
        'recentTickets'      => $recent,
        'autoShowTour'       => $autoShowTour,
        'types'              => $dashTypes,
        'groups'             => $dashGroups,
        'groupAgents'        => $dashGroupAgents,
        'allAgentsForAssign' => $dashAllAgents,
        'visibleColumns'     => $dashVisibleColumns,
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

/* ------------------------------------------------------------------
 * Group manager area (delegated skill management)
 * ------------------------------------------------------------------ */
require ROOT_DIR . '/src/routes/manager.php';

/* ------------------------------------------------------------------
 * Mobile REST API
 * ------------------------------------------------------------------ */
require ROOT_DIR . '/src/routes/api.php';
