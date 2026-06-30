<?php
/**
 * OpenHelpDesk — Out-of-Office (OOF) Coverage Processor
 *
 * Solves the "single-person group" problem: when the only agent in a group
 * goes on vacation, their unanswered tickets would otherwise sit untouched
 * until they return.
 *
 * Two phases, run together every ~15 minutes via cron:
 *
 *   1. REFRESH — poll each group-member's Outlook automatic-replies (OOF)
 *      state via the Microsoft Graph API and cache it in `agent_oof_status`.
 *      (Requires the `MailboxSettings.Read` application permission on the
 *      Azure app registration — admin consent.)
 *
 *   2. COVER — for each active, in-scope ticket whose responsible agent is
 *      out of office:
 *        • reassign it to an available (non-OOF) group member, if one exists;
 *        • otherwise (single-person groups, or everyone away) auto-reply the
 *          requester with the agent's Outlook out-of-office message — once.
 *
 * Behaviour is driven by Admin → Settings → Out of Office:
 *   oof_enabled        '1' to run at all.
 *   oof_action         reassign_then_reply | reassign_only | reply_only
 *   oof_scope          unanswered | all
 *   oof_reply_template the patron-facing message (tokens below).
 *
 * Usage (cron — every 15 minutes):
 *   * /15 * * * * php /path/to/app/scripts/process-oof-coverage.php >> /path/to/app/storage/logs/oof-coverage.log 2>&1
 *   (remove the space between * and /15)
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';

loadEnv(ROOT_DIR . '/.env');

// ─── Logging helper (defined before graph.php so it uses this timestamped one) ─
function logMsg(string $level, string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $msg . PHP_EOL;
}

require_once ROOT_DIR . '/src/graph.php';   // curlGet + getAccessToken + getAutomaticReplies

/**
 * Default patron-facing auto-reply. Tokens: {requester_name}, {ticket_id},
 * {agent_name}, {return_date}. The agent's Outlook external OOF message is
 * appended below this text automatically when present.
 */
const DEFAULT_OOF_TEMPLATE =
    "Hello {requester_name},\n\n" .
    "Thank you for your request (ticket #{ticket_id}). The staff member handling it, " .
    "{agent_name}, is currently out of office until {return_date}. Your ticket remains " .
    "open and will be addressed as soon as possible.\n\n" .
    "We appreciate your patience.";

/**
 * Auto-reply used when the agent's out-of-office has NO return date (Outlook
 * "automatic replies" toggled on without a time range). Deliberately makes no
 * promise about when they'll be back. Tokens: {requester_name}, {ticket_id},
 * {agent_name} — {return_date} is intentionally not used here.
 */
const DEFAULT_OOF_TEMPLATE_NO_DATE =
    "Hello {requester_name},\n\n" .
    "Thank you for your request (ticket #{ticket_id}). The staff member handling it, " .
    "{agent_name}, is currently out of office. Your ticket remains open and will be " .
    "addressed as soon as possible.\n\n" .
    "We appreciate your patience.";

// ─── Guard checks ──────────────────────────────────────────────────────────────
if (!extension_loaded('curl')) {
    logMsg('ERROR', 'PHP cURL extension is not loaded. Enable extension=curl in php.ini.');
    exit(1);
}

if (getSetting('oof_enabled') !== '1') {
    logMsg('INFO', 'OOF coverage is disabled. Enable it in Admin → Settings → Out of Office.');
    exit(0);
}

$tenantId     = getSetting('graph_tenant_id');
$clientId     = getSetting('graph_client_id');
$clientSecret = getSetting('graph_client_secret');

if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
    logMsg('ERROR', 'Graph API credentials (Tenant ID, Client ID, Client Secret) are not configured.');
    exit(1);
}

$action = getSetting('oof_action', 'reassign_then_reply');
if (!in_array($action, ['reassign_then_reply', 'reassign_only', 'reply_only'], true)) {
    $action = 'reassign_then_reply';
}
$scope = getSetting('oof_scope', 'unanswered') === 'all' ? 'all' : 'unanswered';

logMsg('INFO', "Requesting access token from Microsoft... (action={$action}, scope={$scope})");
$accessToken = getAccessToken($tenantId, $clientId, $clientSecret);
if ($accessToken === null) {
    logMsg('ERROR', 'Failed to obtain access token. Check Tenant ID, Client ID, and Client Secret.');
    exit(1);
}

$db = Database::connect();

/* ═══════════════════════════════════════════════════════════════════════════
 * PHASE 1 — Refresh the OOF cache for every group member
 * ═══════════════════════════════════════════════════════════════════════════ */

// Only poll staff who actually belong to a group — they're the only people who
// can own group tickets, and it avoids reading mailboxes we have no reason to.
$memberStmt = $db->query(
    "SELECT DISTINCT u.id, u.email, u.first_name, u.last_name
     FROM users u
     JOIN group_user_map gum ON gum.user_id = u.id
     WHERE " . staffRoleSqlIn('u.role') . " AND u.email <> ''
     ORDER BY u.id"
);
$members = $memberStmt->fetchAll();

$nowUtc   = new DateTime('now', new DateTimeZone('UTC'));
$refreshed = 0;
$oofCount  = 0;

foreach ($members as $m) {
    $userId = (int) $m['id'];
    $email  = $m['email'];

    $httpCode = null;
    $setting  = getAutomaticReplies($accessToken, $email, $httpCode);
    if ($setting === null) {
        if ($httpCode === 404) {
            // No readable Exchange Online mailbox for this address — unlicensed,
            // on-prem-only, an alias, or a non-mailbox/test account. Benign: this
            // person simply can't be tracked for OOF. Skip quietly.
            logMsg('INFO', "  No mailbox for {$email} (user #{$userId}) — skipping (no OOF tracking).");
        } else {
            // Real failure (403 consent, 5xx, transport) — leave the cache row
            // untouched and surface it.
            logMsg('WARN', "  Could not read OOF for {$email} (user #{$userId}, HTTP {$httpCode}); keeping previous state.");
        }
        continue;
    }

    $status   = $setting['status'] ?? 'disabled';
    $extMsg   = trim((string) ($setting['externalReplyMessage'] ?? ''));
    if ($extMsg === '') {
        $extMsg = trim((string) ($setting['internalReplyMessage'] ?? ''));
    }

    // Graph populates scheduledStart/EndDateTime with placeholder values even
    // when status is alwaysEnabled (typically ~tomorrow) — only a 'scheduled'
    // status has a genuine return date. Ignore the dates otherwise, so an agent
    // who turned OOF on with no time range has NO return date (not a bogus one).
    $start = null;
    $end   = null;
    if ($status === 'scheduled') {
        $start = oofParseGraphDate($setting['scheduledStartDateTime'] ?? null);
        $end   = oofParseGraphDate($setting['scheduledEndDateTime'] ?? null);
    }

    // Effective OOF = always-on, or scheduled and we're inside the window now.
    $isOof = 0;
    if ($status === 'alwaysEnabled') {
        $isOof = 1;
    } elseif ($status === 'scheduled') {
        $afterStart = ($start === null) || ($nowUtc >= $start);
        $beforeEnd  = ($end === null) || ($nowUtc <= $end);
        $isOof = ($afterStart && $beforeEnd) ? 1 : 0;
    }

    $db->prepare(
        "INSERT INTO agent_oof_status
            (user_id, status, scheduled_start, scheduled_end, external_message, is_oof, checked_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            scheduled_start = VALUES(scheduled_start),
            scheduled_end = VALUES(scheduled_end),
            external_message = VALUES(external_message),
            is_oof = VALUES(is_oof),
            checked_at = NOW()"
    )->execute([
        $userId,
        $status,
        $start ? $start->format('Y-m-d H:i:s') : null,
        $end ? $end->format('Y-m-d H:i:s') : null,
        $extMsg !== '' ? $extMsg : null,
        $isOof,
    ]);

    $refreshed++;
    if ($isOof) {
        $oofCount++;
        logMsg('INFO', "  {$email} is OUT OF OFFICE ({$status}).");
    }
}

logMsg('INFO', "Refreshed {$refreshed} mailbox(es); {$oofCount} currently out of office.");

// Nobody is out of office → no coverage work to do.
if ($oofCount === 0) {
    logMsg('INFO', 'No agents out of office. Done.');
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * PHASE 2 — Reassign or auto-reply tickets owned by an out-of-office agent
 * ═══════════════════════════════════════════════════════════════════════════ */

// Map of OOF agents → their cached status row (for messages + return dates).
$oofRows = [];
foreach ($db->query("SELECT user_id, status, scheduled_end, external_message FROM agent_oof_status WHERE is_oof = 1") as $r) {
    $oofRows[(int) $r['user_id']] = $r;
}
$oofIds = array_keys($oofRows);

$notClosed = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status', true);

// "Unanswered" = no public reply/comment by a staff member yet.
$unansweredClause = $scope === 'unanswered'
    ? "AND NOT EXISTS (
           SELECT 1 FROM ticket_timeline tt
           JOIN users uu ON tt.user_id = uu.id
           WHERE tt.ticket_id = t.id
             AND tt.is_internal = 0
             AND tt.action IN ('reply','comment')
             AND " . staffRoleSqlIn('uu.role') . "
       )"
    : '';

$oofPlace = implode(',', array_fill(0, count($oofIds), '?'));
$candStmt = $db->prepare(
    "SELECT t.id, t.group_id, t.assigned_to, t.subject, t.created_by, t.oof_autoreply_at
     FROM tickets t
     WHERE t.group_id IS NOT NULL
       AND {$notClosed}
       AND (t.assigned_to IS NULL OR t.assigned_to IN ({$oofPlace}))
       {$unansweredClause}
     ORDER BY t.id"
);
$candStmt->execute($oofIds);
$candidates = $candStmt->fetchAll();

$reassigned = 0;
$replied    = 0;
$skipped    = 0;

foreach ($candidates as $t) {
    $ticketId   = (int) $t['id'];
    $groupId    = (int) $t['group_id'];
    $assignedTo = $t['assigned_to'] !== null ? (int) $t['assigned_to'] : null;

    $groupMembers = _autoAssignGroupMembers($db, $groupId);
    if (empty($groupMembers)) {
        continue; // empty group — nothing we can do
    }

    // Determine the "away" agent this ticket is blocked on, if any.
    $awayAgentId = null;
    if ($assignedTo !== null) {
        if (!in_array($assignedTo, $oofIds, true)) {
            continue; // assignee is present — ticket is being handled
        }
        $awayAgentId = $assignedTo;
    } else {
        // Unassigned ticket. OOF coverage only steps in when the absence is what
        // is stalling it — i.e. EVERY group member is out of office (a
        // single-person group whose sole member is away, or a group where
        // everyone is away). If even one member is available, the ticket isn't
        // blocked by anyone's absence; normal triage / auto-assign owns it, so
        // we leave it alone rather than hijacking the unassigned queue.
        $availableNow = array_values(array_diff($groupMembers, $oofIds));
        if (!empty($availableNow)) {
            continue;
        }
        // Everyone is out — announce the absence to the requester. Pick the
        // member returning soonest as the "face" of the reply.
        $awayAgentId = oofSoonestReturning($groupMembers, $oofRows);
    }

    // Eligible coverage = group members who are NOT out of office and not the away agent.
    $eligible = array_values(array_diff($groupMembers, $oofIds, [$awayAgentId]));

    // Try to reassign to a present colleague first.
    if ($action !== 'reply_only' && !empty($eligible)) {
        $pick = _autoAssignLeastLoaded($db, $eligible);
        if ($pick !== null && oofReassign($db, $ticketId, $pick, $awayAgentId)) {
            $awayName = oofUserName($db, $awayAgentId);
            logMsg('INFO', "  Ticket #{$ticketId}: reassigned from {$awayName} (OOF) to member #{$pick}.");
            $reassigned++;
            continue;
        }
    }

    // No coverage available (single-person group, or all away).
    if ($action === 'reassign_only') {
        logMsg('INFO', "  Ticket #{$ticketId}: no coverage and auto-reply disabled — left in place.");
        $skipped++;
        continue;
    }

    // Auto-reply path — once per ticket only.
    if ($t['oof_autoreply_at'] !== null) {
        $skipped++;
        continue;
    }
    if ($t['created_by'] !== null && (int) $t['created_by'] === $awayAgentId) {
        $skipped++; // requester IS the away agent — nobody to inform
        continue;
    }

    if (oofAutoReply($db, $ticketId, $awayAgentId, $oofRows[$awayAgentId] ?? null)) {
        logMsg('INFO', "  Ticket #{$ticketId}: auto-replied (assignee out of office, no coverage).");
        $replied++;
    } else {
        $skipped++;
    }
}

logMsg('INFO', "Done. Reassigned: {$reassigned}, Auto-replied: {$replied}, Skipped: {$skipped}.");
exit(0);

/* ─── Helper functions ──────────────────────────────────────────────────────── */

/**
 * Parse a Graph dateTimeTimeZone object (already requested in UTC) into a
 * UTC DateTime, or null.
 */
function oofParseGraphDate($obj): ?DateTime
{
    if (!is_array($obj) || empty($obj['dateTime'])) {
        return null;
    }
    try {
        // Graph returns fractional seconds (e.g. 2026-07-01T12:00:00.0000000);
        // DateTime parses the leading datetime fine. We requested UTC via Prefer.
        return new DateTime($obj['dateTime'], new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        return null;
    }
}

/** Pick the OOF member whose absence ends soonest (the soonest to return). */
function oofSoonestReturning(array $memberIds, array $oofRows): int
{
    $best = null;
    $bestEnd = null;
    foreach ($memberIds as $id) {
        if (!isset($oofRows[$id])) {
            continue;
        }
        $end = $oofRows[$id]['scheduled_end']; // string|null
        if ($best === null
            || ($bestEnd === null && $end !== null)        // any dated end beats "no end"
            || ($end !== null && $bestEnd !== null && $end < $bestEnd)) {
            $best = $id;
            $bestEnd = $end;
        }
    }
    return $best ?? (int) $memberIds[0];
}

/** Reassign a ticket and record it on the timeline. Returns true on success. */
function oofReassign(PDO $db, int $ticketId, int $newAgentId, ?int $awayAgentId): bool
{
    $newName = oofUserName($db, $newAgentId);
    try {
        $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$newAgentId, $ticketId]);

        $detail = $awayAgentId !== null
            ? "Auto-reassigned to {$newName} — " . oofUserName($db, $awayAgentId) . " is out of office."
            : "Auto-assigned to {$newName} (out-of-office coverage).";

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
        )->execute([$ticketId, 'auto_assigned', $detail]);
    } catch (Throwable $e) {
        logMsg('ERROR', "  Ticket #{$ticketId}: reassign failed — " . $e->getMessage());
        return false;
    }

    // Best-effort notify of the new assignee (in-app + email per their prefs).
    if (function_exists('notifyAssignedAgent')) {
        try {
            notifyAssignedAgent($db, $ticketId, $newAgentId);
        } catch (Throwable $e) {
            logMsg('WARN', "  Ticket #{$ticketId}: notifyAssignedAgent failed — " . $e->getMessage());
        }
    }
    return true;
}

/**
 * Post the OOF auto-reply to the requester (public timeline + email) and stamp
 * oof_autoreply_at so it never fires twice. Returns true if posted.
 */
function oofAutoReply(PDO $db, int $ticketId, int $awayAgentId, ?array $oofRow): bool
{
    $tStmt = $db->prepare(
        "SELECT t.subject, t.group_id, u.email, u.first_name, u.last_name, u.notify_ticket_updated
         FROM tickets t JOIN users u ON t.created_by = u.id WHERE t.id = ?"
    );
    $tStmt->execute([$ticketId]);
    $row = $tStmt->fetch();
    if (!$row) {
        return false;
    }

    $requesterName = trim($row['first_name'] . ' ' . $row['last_name']);
    $agentName     = oofUserName($db, $awayAgentId);

    // A genuine return date only exists for a 'scheduled' OOF (the refresh
    // stores NULL otherwise). With no date we use a separate message that makes
    // no promise about when the agent is back, rather than inventing one.
    $returnTs = ($oofRow && !empty($oofRow['scheduled_end'])) ? strtotime((string) $oofRow['scheduled_end']) : false;
    $hasDate  = $returnTs !== false;

    if ($hasDate) {
        $template = getSetting('oof_reply_template', DEFAULT_OOF_TEMPLATE);
        if (trim($template) === '') {
            $template = DEFAULT_OOF_TEMPLATE;
        }
    } else {
        $template = getSetting('oof_reply_template_no_date', DEFAULT_OOF_TEMPLATE_NO_DATE);
        if (trim($template) === '') {
            $template = DEFAULT_OOF_TEMPLATE_NO_DATE;
        }
    }

    $text = strtr($template, [
        '{requester_name}' => $requesterName,
        '{ticket_id}'      => (string) $ticketId,
        '{agent_name}'     => $agentName,
        // Still substituted in case a custom no-date template references it.
        '{return_date}'    => $hasDate ? date('F j, Y', $returnTs) : 'further notice',
    ]);

    // Body for the timeline + email. The template is plain text; the agent's
    // Outlook message is already HTML and is appended verbatim below a divider.
    $bodyHtml = nl2br(e($text));
    $extMsg   = $oofRow['external_message'] ?? '';
    if (is_string($extMsg) && trim($extMsg) !== '') {
        $bodyHtml .= '<hr><blockquote>' . $extMsg . '</blockquote>';
    }

    try {
        // Public reply on the ticket so the requester sees it in the portal too.
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 0)'
        )->execute([$ticketId, 'reply', $bodyHtml]);

        $db->prepare('UPDATE tickets SET oof_autoreply_at = NOW() WHERE id = ?')->execute([$ticketId]);
    } catch (Throwable $e) {
        logMsg('ERROR', "  Ticket #{$ticketId}: failed to post auto-reply — " . $e->getMessage());
        return false;
    }

    // Email the requester (respects their update-email preference + global mail config).
    if ((bool) ($row['notify_ticket_updated'] ?? 1)) {
        $appUrl    = env('APP_URL', 'http://localhost:8000');
        $ticketUrl = $appUrl . '/portal/tickets/' . $ticketId;
        $groupId   = $row['group_id'] ? (int) $row['group_id'] : null;

        $tpl = getEmailTpl('ticket-updated', [
            'ticket_id'  => $ticketId,
            'subject'    => $row['subject'],
            'message'    => $text,
            'author'     => 'Automatic Reply',
            'user_name'  => $requesterName,
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
        ], $groupId);

        $emailHtml = renderEmail('ticket-updated', [
            'ticketId'    => $ticketId,
            'subject'     => $row['subject'],
            'message'     => $bodyHtml,
            'authorName'  => 'Automatic Reply',
            'ticketUrl'   => $ticketUrl,
            'introText'   => $tpl['intro'],
            'buttonLabel' => $tpl['button'],
            'footerText'  => $tpl['footer'],
        ]);

        sendMail($row['email'], $requesterName, $tpl['subject'], $emailHtml, '', $ticketId);
    }

    return true;
}

/** Full name for a user id, with a stable fallback. */
function oofUserName(PDO $db, int $userId): string
{
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $stmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    $name = $u ? trim($u['first_name'] . ' ' . $u['last_name']) : "User #{$userId}";
    return $cache[$userId] = ($name !== '' ? $name : "User #{$userId}");
}
