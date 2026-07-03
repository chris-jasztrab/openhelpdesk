<?php

/* ==================================================================
 * Ticket Drafts (JSON API)
 *
 * Server-side autosave for the new-ticket forms and per-ticket reply
 * boxes (public/assets/js/ticket-draft.js). One draft per user per
 * context (+ ticket for replies); rows are private to their author —
 * every query is keyed on Auth::id(), so there is nothing to enumerate.
 * ================================================================== */

/**
 * Contexts the client may save under. Staff-only contexts are gated so a
 * portal account can't stash drafts against the staff form, and ticket-bound
 * contexts require the ticket to exist (bounds junk rows to real tickets).
 */
const TICKET_DRAFT_CONTEXTS = [
    'ticket_create' => ['staff' => true,  'ticket' => false],
    'portal_create' => ['staff' => false, 'ticket' => false],
    'reply'         => ['staff' => true,  'ticket' => true],
    'portal_reply'  => ['staff' => false, 'ticket' => true],
];

/**
 * Hard cap on a stored payload. Reply HTML can carry pasted Base64 images,
 * but MySQL's default max_allowed_packet is 1MB — anything a draft can't
 * store here couldn't be stored by the real ticket submit either.
 */
const TICKET_DRAFT_MAX_BYTES = 1000000;

/**
 * Validate the context/ticket_id pair for the current user, or emit a JSON
 * error and exit. Returns [context, ticketId].
 */
function _draftValidateTarget(PDO $db, string $context, int $ticketId): array
{
    $rules = TICKET_DRAFT_CONTEXTS[$context] ?? null;
    if ($rules === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown draft context.']);
        exit;
    }
    if ($rules['staff'] && !Auth::isStaff()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden.']);
        exit;
    }
    if ($rules['ticket']) {
        if ($rules['staff']) {
            $stmt = $db->prepare('SELECT id FROM tickets WHERE id = ?');
            $stmt->execute([$ticketId]);
            $ok = (bool) $stmt->fetch();
        } else {
            // Mirror the portal comment access rule (own ticket, merged
            // master, or location-visible non-confidential ticket) and
            // answer 404 for "exists but not yours" exactly like the rest
            // of the portal — otherwise this endpoint becomes a ticket-ID
            // existence oracle.
            $uid      = Auth::id();
            $permStmt = $db->prepare('SELECT can_view_location_tickets, location_id FROM users WHERE id = ?');
            $permStmt->execute([$uid]);
            $userPerms = $permStmt->fetch();

            $accessCond   = '(tickets.created_by = ? OR tickets.id IN (SELECT DISTINCT merged_into_ticket_id FROM tickets t2 WHERE t2.created_by = ? AND t2.merged_into_ticket_id IS NOT NULL))';
            $accessParams = [$ticketId, $uid, $uid];
            if ($userPerms && $userPerms['can_view_location_tickets'] && $userPerms['location_id']) {
                $accessCond   = '(' . $accessCond . ' OR (tickets.location_id = ? AND NOT EXISTS (
                                    SELECT 1 FROM ticket_types ct
                                    WHERE ct.id = tickets.type_id
                                      AND (ct.is_confidential = 1 OR ct.show_to_location_visibility = 0)
                                )))';
                $accessParams[] = (int) $userPerms['location_id'];
            }
            $stmt = $db->prepare("SELECT tickets.id FROM tickets WHERE tickets.id = ? AND {$accessCond}");
            $stmt->execute($accessParams);
            $ok = (bool) $stmt->fetch();
        }
        if (!$ok) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
            exit;
        }
    } else {
        $ticketId = 0;
    }
    return [$context, $ticketId];
}

/* ------------------------------------------------------------------
 * GET /drafts — fetch the current user's draft for a form
 * ------------------------------------------------------------------ */
$router->get('/drafts', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');

    $db = Database::connect();
    [$context, $ticketId] = _draftValidateTarget(
        $db,
        (string) ($_GET['context'] ?? ''),
        (int) ($_GET['ticket_id'] ?? 0)
    );

    // Opportunistic sweep: abandoned drafts (incl. those for deleted tickets)
    // expire after 90 days. Indexed on updated_at, almost always 0 rows.
    $db->prepare('DELETE FROM ticket_drafts WHERE updated_at < NOW() - INTERVAL 90 DAY')->execute();

    $stmt = $db->prepare(
        'SELECT payload, updated_at FROM ticket_drafts WHERE user_id = ? AND context = ? AND ticket_id = ?'
    );
    $stmt->execute([Auth::id(), $context, $ticketId]);
    $row = $stmt->fetch();

    $draft = null;
    if ($row) {
        $payload = json_decode($row['payload'], true);
        if (is_array($payload)) {
            $draft = ['payload' => $payload, 'updated_at' => $row['updated_at']];
        }
    }
    echo json_encode(['success' => true, 'draft' => $draft]);
    exit;
});

/* ------------------------------------------------------------------
 * POST /drafts — upsert the current user's draft (null payload deletes)
 * ------------------------------------------------------------------ */
$router->post('/drafts', function () {
    Auth::requireAuth();
    requireJsonCsrf();
    header('Content-Type: application/json');

    $raw = file_get_contents('php://input');
    if (strlen($raw) > TICKET_DRAFT_MAX_BYTES) {
        http_response_code(413);
        echo json_encode(['success' => false, 'error' => 'Draft is too large to autosave.']);
        exit;
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request body.']);
        exit;
    }

    $db = Database::connect();
    [$context, $ticketId] = _draftValidateTarget(
        $db,
        (string) ($body['context'] ?? ''),
        (int) ($body['ticket_id'] ?? 0)
    );

    $payload = $body['payload'] ?? null;
    if ($payload === null) {
        // Emptied form — the draft no longer exists.
        $db->prepare('DELETE FROM ticket_drafts WHERE user_id = ? AND context = ? AND ticket_id = ?')
           ->execute([Auth::id(), $context, $ticketId]);
        echo json_encode(['success' => true, 'deleted' => true]);
        exit;
    }
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid payload.']);
        exit;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    try {
        $db->prepare(
            'INSERT INTO ticket_drafts (user_id, context, ticket_id, payload, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = NOW()'
        )->execute([Auth::id(), $context, $ticketId, $json]);
    } catch (PDOException $e) {
        // A payload the server can't store (e.g. over MySQL's
        // max_allowed_packet) must degrade to "not autosaved", not a 500.
        http_response_code(413);
        echo json_encode(['success' => false, 'error' => 'Draft is too large to autosave.']);
        exit;
    }

    echo json_encode(['success' => true, 'saved_at' => date('Y-m-d H:i:s')]);
    exit;
});

/* ------------------------------------------------------------------
 * POST /drafts/discard — explicit discard (note's Discard button)
 * ------------------------------------------------------------------ */
$router->post('/drafts/discard', function () {
    Auth::requireAuth();
    requireJsonCsrf();
    header('Content-Type: application/json');

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $db = Database::connect();
    [$context, $ticketId] = _draftValidateTarget(
        $db,
        (string) ($body['context'] ?? ''),
        (int) ($body['ticket_id'] ?? 0)
    );

    $db->prepare('DELETE FROM ticket_drafts WHERE user_id = ? AND context = ? AND ticket_id = ?')
       ->execute([Auth::id(), $context, $ticketId]);
    echo json_encode(['success' => true]);
    exit;
});
