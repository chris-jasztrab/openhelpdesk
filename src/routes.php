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
 * Credits roll (local-only easter egg; kept out of the public repo).
 * Only wired in when the gitignored route file is actually present.
 * ------------------------------------------------------------------ */
if (is_file(ROOT_DIR . '/src/routes/credits.php')) {
    require ROOT_DIR . '/src/routes/credits.php';
}

/* ------------------------------------------------------------------
 * Shared: enforce ticket-level access for JSON API endpoints.
 * Mirrors _agentRequireTicketAccess() but returns JSON 403 instead of redirect.
 * ------------------------------------------------------------------ */
function _apiRequireTicketAccess(PDO $db, int $ticketId): void
{
    $forbid = static function (): void {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'You do not have access to this ticket.']);
        exit;
    };

    if (Auth::isAdmin()) {
        return; // admins: unrestricted (confidential gated/audited on the detail page)
    }
    if (!Auth::isStaff()) {
        $forbid();
    }

    $userId = (int) Auth::id();

    // Authoritative fetch of the fields the rules depend on.
    $meta = $db->prepare(
        'SELECT t.group_id, t.created_by, tt.is_confidential, tt.group_id AS conf_group_id
         FROM tickets t
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         WHERE t.id = ?'
    );
    $meta->execute([$ticketId]);
    $row = $meta->fetch();
    if (!$row) {
        return; // ticket-not-found is handled by the caller
    }

    $userGroups = userGroupIds($db, $userId);

    // Confidential tickets: confidential-group members or the creator ONLY.
    if ((int) ($row['is_confidential'] ?? 0) === 1) {
        $confGroupId = $row['conf_group_id'] !== null ? (int) $row['conf_group_id'] : null;
        $isMember    = $confGroupId !== null && in_array($confGroupId, $userGroups, true);
        $isCreator   = (int) $row['created_by'] === $userId;
        if (!$isMember && !$isCreator) {
            $forbid();
        }
        return;
    }

    // Non-confidential tickets.
    if (Auth::can('tickets.view_all')) {
        return;
    }
    $gid = $row['group_id'] !== null ? (int) $row['group_id'] : null;
    if (!empty($userGroups) && ($gid === null || in_array($gid, $userGroups, true))) {
        return;
    }
    if ((int) $row['created_by'] === $userId) {
        return;
    }
    if (ticketAccessExempt($db, $userId, $ticketId)) {
        return;
    }
    $forbid();
}

/* ------------------------------------------------------------------
 * Ticket Tag Management (JSON API)
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/tags', function (array $p) {
    Auth::requireAuth();
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

    if (Auth::isStaff()) {
        _apiRequireTicketAccess($db, $ticketId);
    } elseif ((int) $ticket['created_by'] !== (int) Auth::id()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }

    if (in_array($ticket['status'], ticketClosedBucketSlugs(), true) || $ticket['merged_into_ticket_id']) {
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

    if (Auth::isStaff()) {
        _apiRequireTicketAccess($db, $ticketId);
    } elseif ((int) $ticket['created_by'] !== (int) Auth::id()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }

    if (in_array($ticket['status'], ticketClosedBucketSlugs(), true) || $ticket['merged_into_ticket_id']) {
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
        // Confidential tickets never carry watchers — access there is limited to
        // the confidential group, so the previous assignee is not re-added.
        if ($fromUserId && $fromUserId !== $toUserId && !ticketIsConfidential($db, (int) $ticketId)) {
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
    if (!Auth::isStaff()) {
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
        // Type maps 1:1 to a default group — move the ticket into the new type's group.
        syncTicketGroupToType($db, $ticketId, $typeId, Auth::id());
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
    if (!Auth::isStaff()) {
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

    $typeCleared = false;
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
        // A ticket type maps 1:1 to a default group — clear a now-mismatched type.
        $typeCleared = clearTicketTypeIfGroupMismatch($db, $ticketId, $groupId, Auth::id());
        if ($groupId) { notifyAssignedGroup($db, $ticketId, $groupId); }
        runAutomations($db, $ticketId, 'ticket_updated');
    }

    // Return agents for the new group so the client can update the agent dropdown
    if ($groupId) {
        $as = $db->prepare("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name
                             FROM users u JOIN group_user_map gum ON u.id = gum.user_id
                             WHERE gum.group_id = ? AND " . staffRoleSqlIn('u.role') . "
                             ORDER BY u.first_name, u.last_name");
        $as->execute([$groupId]);
    } else {
        $as = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users
                          WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name");
    }
    $agents = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $as->fetchAll());
    echo json_encode(['success' => true, 'group_name' => $groupName, 'agents' => $agents, 'type_cleared' => $typeCleared]);
    exit;
});

/* ------------------------------------------------------------------
 * Quick status change from the ticket list (JSON API)
 * Mirrors the side effects of /agent/tickets/{id}/update so the list
 * inline dropdown behaves identically to the ticket detail page.
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/set-status', function (array $p) {
    Auth::requireAuth();
    if (!Auth::isStaff()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); exit;
    }
    header('Content-Type: application/json');

    $ticketId  = (int) $p['id'];
    $input     = json_decode(file_get_contents('php://input'), true);
    $newStatus = isset($input['status']) ? (string) $input['status'] : '';

    if ($newStatus === '' || !in_array($newStatus, ticketActiveStatusSlugs(), true)) {
        http_response_code(422); echo json_encode(['error' => 'Invalid status value']); exit;
    }

    $db = Database::connect();
    _apiRequireTicketAccess($db, $ticketId);
    $stmt = $db->prepare('SELECT id, status FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) { http_response_code(404); echo json_encode(['error' => 'Ticket not found']); exit; }

    // Optimistic-lock guard: if the client tells us the status it last saw and
    // the ticket has since moved on (another agent changed it), reject rather
    // than silently clobber. `expected_status` is optional — omitting it keeps
    // the old last-write-wins behaviour for any caller that doesn't send it.
    $expectedStatus = isset($input['expected_status']) ? (string) $input['expected_status'] : '';
    if ($expectedStatus !== '' && $expectedStatus !== $ticket['status']) {
        http_response_code(409);
        echo json_encode([
            'error'          => 'conflict',
            'message'        => 'This ticket\'s status was changed by someone else. The list has been refreshed.',
            'current_status' => $ticket['status'],
            'status_html'    => ticketStatusBadgeHtml($ticket['status']),
        ]);
        exit;
    }

    if ($newStatus !== $ticket['status']) {
        $oldStatus = $ticket['status'];
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$newStatus, $ticketId]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$ticketId, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$newStatus}"]);

        if (in_array($newStatus, ticketClosedBucketSlugs(), true)) {
            notifyRequesterStatusChanged($db, $ticketId, $newStatus);
        }

        // CSAT survey trigger
        $csatTrigger = getSetting('csat_trigger_status', ticketDefaultResolvedStatusSlug());
        if ($newStatus === $csatTrigger) {
            sendCsatSurvey($db, $ticketId);
        }

        // SLA pause/resume
        $pausingStatuses = ticketSlaPausingSlugs();
        if (in_array($newStatus, $pausingStatuses, true)) {
            Sla::pause($db, $ticketId);
        } elseif (in_array($oldStatus, $pausingStatuses, true)) {
            Sla::resume($db, $ticketId);
        }

        runAutomations($db, $ticketId, 'ticket_updated');
    }

    echo json_encode([
        'success'     => true,
        'status'      => $newStatus,
        'status_html' => ticketStatusBadgeHtml($newStatus),
    ]);
    exit;
});

/* ------------------------------------------------------------------
 * Quick priority change from the ticket list (JSON API)
 * Mirrors the priority side effects of /agent/tickets/{id}/update.
 * ------------------------------------------------------------------ */
$router->post('/api/tickets/{id}/set-priority', function (array $p) {
    Auth::requireAuth();
    if (!Auth::isStaff()) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); exit;
    }
    header('Content-Type: application/json');

    $ticketId   = (int) $p['id'];
    $input      = json_decode(file_get_contents('php://input'), true);
    $priorityId = isset($input['priority_id']) && $input['priority_id'] !== null && $input['priority_id'] !== ''
                  ? (int) $input['priority_id'] : null;

    $db = Database::connect();
    _apiRequireTicketAccess($db, $ticketId);
    $stmt = $db->prepare('SELECT id, priority_id, type_id FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) { http_response_code(404); echo json_encode(['error' => 'Ticket not found']); exit; }

    // Validate the priority exists (when one is given) and fetch its name/color.
    $priorityName  = null;
    $priorityColor = null;
    if ($priorityId !== null) {
        $ps = $db->prepare('SELECT name, color FROM ticket_priorities WHERE id = ?');
        $ps->execute([$priorityId]);
        $prow = $ps->fetch();
        if (!$prow) { http_response_code(422); echo json_encode(['error' => 'Invalid priority']); exit; }
        $priorityName  = $prow['name'];
        $priorityColor = $prow['color'];
    }

    $oldPriority = $ticket['priority_id'] ? (int) $ticket['priority_id'] : null;
    if ($priorityId !== $oldPriority) {
        $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')->execute([$priorityId, $ticketId]);

        $oldName = 'None';
        if ($oldPriority) {
            $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
            $s->execute([$oldPriority]);
            $oldName = $s->fetchColumn() ?: 'None';
        }
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$ticketId, Auth::id(), 'priority_changed', 'Priority changed from ' . $oldName . ' to ' . ($priorityName ?? 'None')]);

        if ($priorityId) {
            Sla::onPriorityChanged($db, $ticketId, $priorityId, $ticket['type_id'] ? (int) $ticket['type_id'] : null);
        }

        runAutomations($db, $ticketId, 'ticket_updated');
    }

    echo json_encode([
        'success'        => true,
        'priority_name'  => $priorityName,
        'priority_color' => $priorityColor,
    ]);
    exit;
});

/* ------------------------------------------------------------------
 * User Search for CC (JSON API)
 * ------------------------------------------------------------------ */
$router->get('/api/user-search', function () {
    Auth::requireAuth();
    if (!Auth::isStaff()) {
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
    if (!Auth::isStaff()) {
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
    if (!Auth::isStaff()) {
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
         WHERE " . staffRoleSqlIn('role') . "
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
    if (!Auth::isStaff()) {
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
    if (!Auth::isStaff()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $db = Database::connect();
    _apiRequireTicketAccess($db, (int) $p['id']);

    // Stale rows are excluded at read time (filter, not delete) so this hot poll
    // path no longer writes on every request. Actual deletion is amortised: it
    // runs on ~1 in 50 polls, which is plenty to keep the tiny table tidy.
    if (random_int(1, 50) === 1) {
        $db->prepare(
            'DELETE FROM ticket_presence WHERE last_seen < DATE_SUB(NOW(), INTERVAL 45 SECOND)'
        )->execute();
    }

    // Return other current viewers (excluding self), ignoring stale rows.
    $stmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.role
         FROM ticket_presence tp
         JOIN users u ON u.id = tp.user_id
         WHERE tp.ticket_id = ?
           AND tp.user_id != ?
           AND tp.last_seen >= DATE_SUB(NOW(), INTERVAL 45 SECOND)'
    );
    $stmt->execute([(int) $p['id'], Auth::id()]);

    // Piggyback the ticket's last-modified timestamp so the detail page can warn
    // when the ticket changed under the agent (stale-view banner). Cheap PK lookup.
    $uStmt = $db->prepare('SELECT updated_at FROM tickets WHERE id = ?');
    $uStmt->execute([(int) $p['id']]);
    $updatedAt = $uStmt->fetchColumn() ?: null;

    header('Content-Type: application/json');
    echo json_encode(['viewers' => $stmt->fetchAll(), 'updated_at' => $updatedAt]);
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
 * Status-banner dismissal (per-session)
 * ------------------------------------------------------------------
 * Stores "user X dismissed banner Y@updated_at Z" in $_SESSION so the
 * banner stays hidden across tabs and page loads for the rest of the
 * session. Editing a banner bumps updated_at, which invalidates prior
 * dismissals (intentional — the message changed, surface it again).
 * ------------------------------------------------------------------ */
$router->post('/api/banners/{id}/dismiss', function (array $p) {
    Auth::requireAuth();
    header('Content-Type: application/json');
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    $id = (int) $p['id'];
    $stmt = Database::connect()->prepare('SELECT updated_at FROM status_banners WHERE id = ?');
    $stmt->execute([$id]);
    $updatedAt = $stmt->fetchColumn();
    if (!$updatedAt) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Banner not found']);
        exit;
    }
    $key = $id . '_' . strtotime((string) $updatedAt);
    $_SESSION['dismissed_banners'][$key] = true;
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
    if (($type === 'all' || $type === 'contacts') && roleIsStaff($role)) {
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
            ($locationPrompt === 'all' && !Auth::isAdmin())) {
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
        // Send each role to its configured landing area (admin/agent/portal).
        redirect(roleLandingPath(Auth::role()));
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

/*
 * Brute-force throttle for the interactive web login. Mirrors the mobile API
 * login limits (_API_LOGIN_* in routes/api.php): cap failed attempts per email
 * and per IP in a 15-minute sliding window using the shared login_attempts
 * table. A clean login clears the failed rows for that email+IP pair so a user
 * who fat-fingers their password then logs in is not locked out.
 */
const _WEB_LOGIN_WINDOW_MINUTES         = 15;
const _WEB_LOGIN_MAX_FAILURES_PER_EMAIL = 5;
const _WEB_LOGIN_MAX_FAILURES_PER_IP    = 10;

// Max wrong TOTP codes allowed on the /2fa step before the pending login is
// discarded and the user must re-enter their password.
const _2FA_MAX_ATTEMPTS = 5;

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

    $db = Database::connect();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Throttle before verifying the password so an attacker can't keep guessing.
    $window = _WEB_LOGIN_WINDOW_MINUTES;
    $byEmail = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts
          WHERE email = ? AND succeeded = 0
            AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)"
    );
    $byEmail->execute([$email]);
    $byIp = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts
          WHERE ip = ? AND succeeded = 0
            AND attempted_at >= (NOW() - INTERVAL {$window} MINUTE)"
    );
    $byIp->execute([$ip]);
    if ((int) $byEmail->fetchColumn() >= _WEB_LOGIN_MAX_FAILURES_PER_EMAIL
        || (int) $byIp->fetchColumn() >= _WEB_LOGIN_MAX_FAILURES_PER_IP) {
        logAudit('auth.login_throttled', null, null, 'email=' . $email);
        render('login', [
            'error' => 'Too many failed login attempts. Please wait a few minutes and try again.',
            'email' => $email,
        ]);
    }

    if (Auth::attempt($email, $password)) {
        session_regenerate_id(true);
        // Correct password — clear the failed-attempt rows for this email+IP.
        $db->prepare('INSERT INTO login_attempts (email, ip, succeeded) VALUES (?, ?, 1)')
           ->execute([$email, $ip]);
        $db->prepare('DELETE FROM login_attempts WHERE email = ? AND ip = ? AND succeeded = 0')
           ->execute([$email, $ip]);
        // Check if 2FA is required before completing login
        $uid   = Auth::id();
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

    // Failed: record the attempt for the throttle and tie it to the targeted
    // account when one exists — important for spotting credential-stuffing
    // patterns from the audit log.
    $db->prepare('INSERT INTO login_attempts (email, ip, succeeded) VALUES (?, ?, 0)')
       ->execute([$email, $ip]);
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
    // Revoke all mobile/API bearer tokens — after a reset (e.g. following an
    // account compromise) an attacker-held 90-day token must not stay valid.
    $db->prepare('DELETE FROM api_tokens WHERE user_id = ?')->execute([$userId]);

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

/**
 * Extract the `tid` (tenant id) claim from the id_token returned by the token
 * exchange. The token came directly from Microsoft's token endpoint over
 * verified TLS, so reading its claims is trustworthy without re-verifying the
 * signature. Returns null when no id_token / tid is present.
 */
function ssoTokenTenantId(?array $tokens): ?string
{
    $jwt = is_array($tokens) ? ($tokens['id_token'] ?? null) : null;
    if (!is_string($jwt)) {
        return null;
    }
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return null;
    }
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '', true);
    return (is_array($payload) && !empty($payload['tid'])) ? (string) $payload['tid'] : null;
}

/**
 * True when SSO is configured for a multi-tenant audience (common / organizations
 * / consumers) rather than a specific tenant GUID. In that mode any Azure user
 * worldwide can complete the flow, so email-based account linking/provisioning
 * is unsafe and must be refused.
 */
function ssoIsMultiTenant(string $tenantId): bool
{
    return in_array(strtolower(trim($tenantId)), ['common', 'organizations', 'consumers'], true);
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

    // Tenant trust gate. Identity is keyed to the immutable Azure object id
    // (oid); email is a mutable, spoofable attribute. With a specific tenant
    // GUID configured, Microsoft only issues tokens for that tenant, so we also
    // verify the token's tid claim matches (defence-in-depth against misconfig).
    $multiTenant = ssoIsMultiTenant($tenantId);
    if (!$multiTenant && trim((string) $tenantId) !== '') {
        $tid = ssoTokenTenantId($tokens);
        if ($tid !== null && strcasecmp($tid, trim((string) $tenantId)) !== 0) {
            ssoDebugLog("Tenant mismatch: token tid={$tid} != configured {$tenantId} - refusing");
            redirect('/login?sso_error=tenant');
        }
    }

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
    } elseif ($multiTenant) {
        // No oid match and any tenant is trusted — linking/creating by a mutable
        // email would let a user from any Azure tenant take over a matching local
        // account or self-provision. Refuse; admins must use a specific tenant.
        ssoDebugLog("No azure_oid match under multi-tenant config - refusing email link/auto-provision for {$email}");
        redirect('/login?sso_error=tenant');
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
        unset($_SESSION['2fa_pending'], $_SESSION['2fa_attempts']);
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

    // Cap online guessing of the 6-digit code: with a ±1 step window three
    // codes are valid at any moment, so an unbounded retry loop is brute-
    // forceable. After too many misses, drop the pending state and force the
    // user back through password login.
    $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;
    if ($_SESSION['2fa_attempts'] >= _2FA_MAX_ATTEMPTS) {
        logAudit('auth.2fa_throttled', null, null, null, (int) $uid);
        unset($_SESSION['2fa_pending'], $_SESSION['2fa_attempts']);
        render('login', ['error' => 'Too many incorrect authentication codes. Please sign in again.']);
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
    render('profile/edit', [
        'profileUser'        => $user,
        'theme'              => $theme,
        'ticketView'         => getUserTicketView((int) Auth::id()),
        'aiNotesVisible'     => aiNotesVisible(),
        'systemNotesVisible' => systemNotesVisible(),
    ]);
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

    // Save ticket-list view preference (table vs. inbox)
    setUserTicketView((int) $userId, $_POST['ticket_view'] ?? 'table');

    // Save AI / system note timeline preferences. The toggles only render for
    // admins, so a non-admin POST must not be allowed to clear these settings.
    if (Auth::isAdmin()) {
        setSetting('ai_notes_visible:' . $userId, isset($_POST['ai_notes_visible']) ? '1' : '0');
        setSetting('system_notes_visible:' . $userId, isset($_POST['system_notes_visible']) ? '1' : '0');
    }

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

/**
 * AJAX endpoint that saves a single profile setting immediately, so the
 * profile page no longer needs a "Save Changes" button for anything except
 * the password form. Each control on the page POSTs `field` + `value` here on
 * change and we persist just that one setting. Returns JSON.
 */
$router->post('/profile/setting', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $field  = (string) ($_POST['field'] ?? '');
    $value  = (string) ($_POST['value'] ?? '');
    $db     = Database::connect();
    $userId = (int) Auth::id();

    // Notification preferences are boolean columns on the users table. Keep an
    // explicit allow-list so `field` can never be used to write an arbitrary
    // column name.
    $notifyColumns = [
        'notify_ticket_created', 'notify_ticket_updated', 'notify_ticket_cc',
        'notify_ticket_merged', 'notify_escalation', 'notify_csat',
        'notify_group_new_ticket', 'notify_assigned_to_me', 'notify_assigned_to_group',
        'notify_requester_replied', 'notify_note_added', 'notify_ticket_solved',
        'notify_ticket_closed', 'notify_ticket_assigned',
    ];

    if (in_array($field, ['first_name', 'last_name'], true)) {
        $name = trim($value);
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Name cannot be empty.']);
            exit;
        }
        $beforeStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
        $beforeStmt->execute([$userId]);
        $before = $beforeStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $db->prepare("UPDATE users SET {$field} = ? WHERE id = ?")->execute([$name, $userId]);
        logAuditChange('user.profile_updated', $userId, 'user', $before, [$field => $name]);

        // Keep the navbar/session name in sync.
        $_SESSION['user'][$field] = $name;

        echo json_encode(['ok' => true, 'message' => 'Saved']);
        exit;
    }

    if ($field === 'theme') {
        $theme = in_array($value, ['light', 'dark'], true) ? $value : 'light';
        setSetting('ui_theme:' . $userId, $theme);
        echo json_encode(['ok' => true, 'message' => 'Appearance saved']);
        exit;
    }

    if ($field === 'ticket_view') {
        if (!in_array((string) (Auth::user()['role'] ?? ''), ['agent', 'admin'], true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Not allowed.']);
            exit;
        }
        setUserTicketView($userId, $value !== '' ? $value : 'table');
        echo json_encode(['ok' => true, 'message' => 'Saved']);
        exit;
    }

    if (in_array($field, ['ai_notes_visible', 'system_notes_visible'], true)) {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Not allowed.']);
            exit;
        }
        setSetting($field . ':' . $userId, $value === '1' ? '1' : '0');
        echo json_encode(['ok' => true, 'message' => 'Saved']);
        exit;
    }

    if (in_array($field, $notifyColumns, true)) {
        $db->prepare("UPDATE users SET {$field} = ? WHERE id = ?")
           ->execute([$value === '1' ? 1 : 0, $userId]);
        echo json_encode(['ok' => true, 'message' => 'Saved']);
        exit;
    }

    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Unknown setting.']);
    exit;
});

/**
 * AJAX endpoint for the AI-notes show/hide slider on ticket timelines.
 * Persists the same per-user `ai_notes_visible` setting the profile form
 * saves, so the slider and the profile toggle stay in sync.
 */
$router->post('/profile/ai-notes', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }
    $visible = ($_POST['visible'] ?? '') === '1' ? '1' : '0';
    setSetting('ai_notes_visible:' . Auth::id(), $visible);
    echo json_encode(['ok' => true, 'visible' => $visible === '1']);
    exit;
});

/**
 * AJAX endpoint for the system-notes show/hide slider on ticket timelines.
 * Persists the same per-user `system_notes_visible` setting the profile form
 * saves, so the slider and the profile toggle stay in sync.
 */
$router->post('/profile/system-notes', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }
    $visible = ($_POST['visible'] ?? '') === '1' ? '1' : '0';
    setSetting('system_notes_visible:' . Auth::id(), $visible);
    echo json_encode(['ok' => true, 'visible' => $visible === '1']);
    exit;
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
    // Revoke existing mobile/API bearer tokens so a password change signs the
    // account out of other devices (standard "log out everywhere" behaviour).
    $db->prepare('DELETE FROM api_tokens WHERE user_id = ?')->execute([$userId]);

    logAudit('auth.password_changed', $userId, 'user');

    flash('success', 'Password updated successfully. You may need to sign in again on the mobile app.');
    redirect('/profile');
});

/* ------------------------------------------------------------------
 * 2FA Setup (admin / agent only)
 * ------------------------------------------------------------------ */
$router->get('/profile/2fa/setup', function () {
    Auth::requireAuth();
    if (!Auth::isStaff()) {
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
    if (!Auth::isStaff()) {
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
    if (!Auth::isStaff()) {
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

    recordCsatResponse($db, $survey, $rating, $_POST['comment'] ?? '');

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

/**
 * External CSAT webhook — lets a third-party survey tool post a response back
 * so it lands on the ticket exactly like a built-in survey would. Tool-agnostic:
 * the body is JSON {ticket_id|token, rating:1-5, comment?} and authenticity is
 * proven by an HMAC-SHA256 of the raw body in the X-CSAT-Signature header, keyed
 * with the shared secret configured under Settings → CSAT.
 */
$router->post('/api/csat/webhook', function () {
    header('Content-Type: application/json');

    $secret = trim((string) getSetting('csat_webhook_secret', ''));
    if ($secret === '') {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'CSAT webhook is not configured']);
        return;
    }

    $raw = file_get_contents('php://input') ?: '';
    $sig = $_SERVER['HTTP_X_CSAT_SIGNATURE'] ?? '';
    $expected = hash_hmac('sha256', $raw, $secret);
    if (!is_string($sig) || $sig === '' || !hash_equals($expected, $sig)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid or missing signature']);
        return;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Body must be a JSON object']);
        return;
    }

    $rating = (int) ($data['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'rating must be an integer from 1 to 5']);
        return;
    }

    $db = Database::connect();

    // Locate the survey by token (preferred — unguessable) or ticket_id.
    $token = preg_replace('/[^a-f0-9]/', '', (string) ($data['token'] ?? ''));
    if (strlen($token) === 64) {
        $stmt = $db->prepare('SELECT * FROM csat_surveys WHERE token = ?');
        $stmt->execute([$token]);
    } else {
        $ticketId = (int) ($data['ticket_id'] ?? 0);
        if ($ticketId < 1) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'token or ticket_id is required']);
            return;
        }
        $stmt = $db->prepare('SELECT * FROM csat_surveys WHERE ticket_id = ?');
        $stmt->execute([$ticketId]);
    }
    $survey = $stmt->fetch();
    if (!$survey) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No survey found for that ticket']);
        return;
    }

    $recorded = recordCsatResponse($db, $survey, $rating, (string) ($data['comment'] ?? ''));
    echo json_encode(['ok' => true, 'status' => $recorded ? 'recorded' : 'already_recorded']);
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

    // "Active" = open-bucket statuses that don't pause SLA. Pending/waiting
    // statuses are intentionally excluded from this tile.
    $activeSlugs = array_values(array_diff(ticketOpenBucketSlugs(), ticketSlaPausingSlugs()));
    $openCount = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_by = ? AND " . ticketStatusSqlIn($activeSlugs, 'status'));
    $openCount->execute([$userId]);
    $openCount = (int) $openCount->fetchColumn();

    $closedIn    = ticketStatusSqlIn(ticketClosedBucketSlugs(), 'status');
    $notClosedIn = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status', true);

    $resolvedCount = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_by = ? AND $closedIn");
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
         WHERE t.created_by = ? AND $notClosedIn
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
    $notifications = notificationsFeedRows($db, (int) Auth::id());

    // Determine which area to render in based on permissions.
    if (Auth::isAdmin()) {
        $sidebarFn = 'adminSidebar';
    } elseif (Auth::isStaff()) {
        $sidebarFn = 'staffSidebar';
    } else {
        $sidebarFn = 'portalSidebar';
    }

    render('notifications', [
        'notifications' => $notifications,
        'sidebarFn'     => $sidebarFn,
        'areaPrefix'    => notificationsAreaPrefix(),
    ]);
});

$router->get('/notifications/count', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');
    echo json_encode(['count' => notificationCount()]);
    exit;
});

/**
 * AJAX feed for the notifications page — returns the rendered list partial
 * plus the unread count, so the page can poll and refresh in place without a
 * full reload. Renders the same partial the page uses (single source of truth).
 */
$router->get('/notifications/feed', function () {
    Auth::requireAuth();
    $db = Database::connect();
    $notifications = notificationsFeedRows($db, (int) Auth::id());
    $areaPrefix    = notificationsAreaPrefix();

    ob_start();
    require ROOT_DIR . '/templates/partials/notifications-list.php';
    $html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'html'      => $html,
        'has_items' => !empty($notifications),
        'unread'    => notificationCount(),
    ]);
    exit;
});

$router->post('/notifications/{id}/read', function (array $p) {
    Auth::requireAuth();
    $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
            exit;
        }
        flash('error', 'Invalid request.');
        redirect('/notifications');
    }
    $db = Database::connect();
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([(int) $p['id'], Auth::id()]);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    redirect('/notifications');
});

$router->post('/notifications/read-all', function () {
    Auth::requireAuth();
    $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
            exit;
        }
        flash('error', 'Invalid request.');
        redirect('/notifications');
    }
    $db = Database::connect();
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
        ->execute([Auth::id()]);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    flash('success', 'All notifications marked as read.');
    redirect('/notifications');
});

/* ------------------------------------------------------------------
 * Agent area
 * ------------------------------------------------------------------ */
$router->post('/agent/tour/dismiss', function () {
    Auth::requireStaff();
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
    Auth::requireStaff();
    $db      = Database::connect();
    $agentId = Auth::id();

    // Fail-closed ticket visibility, shared with the ticket list. Non-admin staff
    // see only their groups' tickets (+ view_all / assignment rules) and never a
    // confidential type outside their confidential group. $agentGroupIds is still
    // needed for the group dropdown further down.
    $agentGroupIds = [];
    if (Auth::isStaff() && !Auth::isAdmin()) {
        $agentGroupIds = userGroupIds($db, $agentId);
    }
    $vis = ticketStaffVisibilitySql($db, $agentId, Auth::role(), 't');

    $openInT  = ticketStatusSqlIn(ticketOpenBucketSlugs(), 't.status');

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.assigned_to IS NULL AND $openInT AND {$vis['sql']}");
    $stmt->execute($vis['params']);
    $unassigned = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = ? AND $openInT AND {$vis['sql']}");
    $stmt->execute(array_merge([$agentId], $vis['params']));
    $myTickets = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.status = 'pending' AND {$vis['sql']}");
    $stmt->execute($vis['params']);
    $pending = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.status = ? AND DATE(t.updated_at) = CURDATE() AND {$vis['sql']}");
    $stmt->execute(array_merge([ticketDefaultResolvedStatusSlug()], $vis['params']));
    $resolvedToday = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE t.assigned_to = ? AND t.escalation_level > 0 AND $openInT AND {$vis['sql']}");
    $stmt->execute(array_merge([$agentId], $vis['params']));
    $escalatedToMe = (int) $stmt->fetchColumn();

    // Sorting (mirror the ticket-list sort options)
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
    $sort     = isset($sortableColumns[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'created_at';
    $dir      = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $orderCol = $sortableColumns[$sort];

    // Recent tickets (open/in_progress/pending)
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
         WHERE $openInT AND {$vis['sql']}
         ORDER BY {$orderCol} {$dir}
         LIMIT 10"
    );
    $stmt->execute($vis['params']);
    $recent = $stmt->fetchAll();

    // Quick-assign / type / group / priority dropdown data for dashboard
    $dashTypes = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $dashPriorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $gaRows2 = $db->query(
        "SELECT gum.group_id, u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
         FROM group_user_map gum
         JOIN users u ON gum.user_id = u.id
         WHERE " . staffRoleSqlIn('u.role') . "
         ORDER BY u.first_name, u.last_name"
    )->fetchAll();
    $dashGroupAgents = [];
    foreach ($gaRows2 as $row) {
        $dashGroupAgents[(int) $row['group_id']][] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
    $dashAllAgents = $db->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
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
    if ((Auth::isStaff() && !Auth::isAdmin())) {
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
        'sort'               => $sort,
        'dir'                => strtolower($dir),
        'autoShowTour'       => $autoShowTour,
        'types'              => $dashTypes,
        'priorities'         => $dashPriorities,
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
    // Staff who hold an admin-area permission but aren't full admins land here
    // via the "Admin" breadcrumb on shared admin pages. Send them to their own
    // dashboard instead of a dead-end 403.
    Auth::requireStaff();
    if (!Auth::isAdmin()) {
        redirect('/agent');
    }
    $db = Database::connect();

    $totalTickets = (int) $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $openTickets = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE " . ticketStatusSqlIn(ticketOpenBucketSlugs(), 'status'))->fetchColumn();
    $totalUsers = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalAgents = (int) $db->query("SELECT COUNT(*) FROM users WHERE " . staffRoleSqlIn('role') . "")->fetchColumn();

    // Recent tickets (last 10 by most-recent activity). Hide tickets whose
    // type is confidential + group-scoped unless the current admin is a
    // member of that group.
    $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
    $gs->execute([Auth::id()]);
    $adminGroupIds = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));
    $groupInList   = $adminGroupIds ? implode(',', $adminGroupIds) : '0';

    $recentTickets = $db->query(
        "SELECT t.id, t.subject, t.status, t.updated_at,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name, tt.color AS type_color
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         WHERE NOT (
             COALESCE(tt.is_confidential, 0) = 1
             AND tt.group_id IS NOT NULL
             AND tt.group_id NOT IN ({$groupInList})
         )
         ORDER BY t.updated_at DESC
         LIMIT 10"
    )->fetchAll();

    render('admin/dashboard', [
        'totalTickets'   => $totalTickets,
        'openTickets'    => $openTickets,
        'totalUsers'     => $totalUsers,
        'totalAgents'    => $totalAgents,
        'recentTickets'  => $recentTickets,
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
