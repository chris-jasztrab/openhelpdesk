<?php

/**
 * OpenHelpDesk — Stale Ticket Notifier
 *
 * Finds active tickets that have had no update for longer than the
 * configured stale threshold and emails the assigned agent (or group)
 * plus the requester. Re-notifications are rate-limited via the
 * ticket_timeline `stale_notification_sent` entry so we don't spam.
 *
 * Runs hourly via cron:
 *
 *   0 * * * * php /path/to/app/scripts/process-stale-tickets.php \
 *       >> /path/to/app/storage/logs/stale-tickets.log 2>&1
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';

loadEnv(ROOT_DIR . '/.env');

$startTime = microtime(true);
$logLines  = [];

function logLine(string $msg): void
{
    global $logLines;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    $logLines[] = $line;
    echo $line . PHP_EOL;
}

logLine('Stale ticket processor started.');

$db = Database::connect();

$globalThreshold = (int) (getSetting('stale_threshold_hours', '72') ?: '72');
$recheckHours    = (int) (getSetting('stale_recheck_hours', '24') ?: '24');

if ($globalThreshold <= 0) {
    logLine('Global stale threshold is 0 or invalid — feature disabled. Exiting.');
    exit(0);
}

logLine("Global threshold: {$globalThreshold}h | Re-notify gap: {$recheckHours}h");

// Only flag tickets the requester is still waiting on. Exclude statuses
// that are intentionally idle (waiting_on_customer / waiting_on_third_party)
// and closed-out states (resolved / closed).
$activeStatuses = ['open', 'in_progress', 'pending'];
$placeholders   = implode(',', array_fill(0, count($activeStatuses), '?'));

$stmt = $db->prepare(
    "SELECT t.*,
            COALESCE(tt.stale_threshold_hours, ?) AS effective_threshold,
            TIMESTAMPDIFF(HOUR, t.updated_at, NOW()) AS hours_since_update
     FROM tickets t
     LEFT JOIN ticket_types tt ON tt.id = t.type_id
     WHERE t.status IN ($placeholders)
       AND TIMESTAMPDIFF(HOUR, t.updated_at, NOW()) >= COALESCE(tt.stale_threshold_hours, ?)"
);
$params = array_merge([$globalThreshold], $activeStatuses, [$globalThreshold]);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

logLine(count($tickets) . ' ticket(s) past their stale threshold.');

$notified = 0;
$skipped  = 0;

foreach ($tickets as $ticket) {
    $ticketId         = (int) $ticket['id'];
    $hoursSinceUpdate = (int) $ticket['hours_since_update'];
    $threshold        = (int) $ticket['effective_threshold'];

    // Dedup: skip if we already sent a stale notification within the recheck window.
    $dedup = $db->prepare(
        "SELECT id FROM ticket_timeline
         WHERE ticket_id = ? AND action = 'stale_notification_sent'
           AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
         ORDER BY id DESC LIMIT 1"
    );
    $dedup->execute([$ticketId, $recheckHours]);
    if ($dedup->fetch()) {
        $skipped++;
        continue;
    }

    try {
        notifyStaleTicketAgent($db, $ticket, $hoursSinceUpdate, $threshold);
        notifyStaleTicketRequester($db, $ticket, $hoursSinceUpdate);

        $details = "No activity for {$hoursSinceUpdate}h (threshold {$threshold}h). Notified agent and requester.";
        $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
             VALUES (?, NULL, 'stale_notification_sent', ?, 1)"
        )->execute([$ticketId, $details]);

        $notified++;
        logLine("Ticket #{$ticketId}: notified ({$hoursSinceUpdate}h / {$threshold}h).");
    } catch (\Throwable $e) {
        logLine("ERROR on ticket #{$ticketId}: " . $e->getMessage());
    }
}

// ── No-group queue sweep ─────────────────────────────────────────────
// Recovery layer for tickets that somehow ended up with group_id = NULL
// despite resolveTicketGroup() being plumbed through every creation path.
// In normal operation this finds zero tickets — it exists to catch
// historical strays, hand-edited rows, and any new creation path a future
// developer adds without remembering to call resolveTicketGroup(). When
// it fires we log it loudly so the bug is visible.
$noGroupSwept = 0;
$defaultGroupId = (int) (getSetting('default_group_id', '') ?: 0);
if ($defaultGroupId > 0) {
    $verifyDefault = $db->prepare('SELECT 1 FROM `groups` WHERE id = ?');
    $verifyDefault->execute([$defaultGroupId]);
    if ($verifyDefault->fetchColumn()) {
        $orphans = $db->query(
            "SELECT id FROM tickets
              WHERE group_id IS NULL
                AND status NOT IN ('resolved','closed','merged')"
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($orphans) {
            $update = $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?');
            $tline  = $db->prepare(
                "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
                 VALUES (?, NULL, 'group_set_from_default', ?, 1)"
            );
            foreach ($orphans as $orphanId) {
                $update->execute([$defaultGroupId, (int) $orphanId]);
                $tline->execute([
                    (int) $orphanId,
                    "Stale-ticket sweep found this ticket with no group and routed it to the configured default group (#{$defaultGroupId}). " .
                    'Investigate which creation path produced a NULL group_id — every supported path should call resolveTicketGroup().',
                ]);
                $noGroupSwept++;
            }
            logLine("WARN: swept {$noGroupSwept} ticket(s) out of the no-group queue into default group #{$defaultGroupId}.");
        }
    } else {
        logLine("WARN: default_group_id is set to {$defaultGroupId} but that group does not exist. No-group sweep skipped.");
    }
} else {
    // Don't error-log every hour just because the admin hasn't picked a
    // default yet on a fresh install — but DO surface it if any tickets
    // are actually stuck there, since that's the situation this exists to
    // prevent.
    $stuckCount = (int) $db->query(
        "SELECT COUNT(*) FROM tickets
          WHERE group_id IS NULL
            AND status NOT IN ('resolved','closed','merged')"
    )->fetchColumn();
    if ($stuckCount > 0) {
        logLine("WARN: {$stuckCount} ticket(s) sitting in the no-group queue, but default_group_id is not configured. " .
            'Set Admin → Settings → Ticket Routing Defaults → Default Group to enable automatic recovery.');
    }
}

$elapsed = round(microtime(true) - $startTime, 2);
logLine("Done. Notified: {$notified}, skipped (cooldown): {$skipped}, no-group swept: {$noGroupSwept}. Took {$elapsed}s.");

// ── Persist log to file ───────────────────────────────────────────
$logDir  = ROOT_DIR . '/storage/logs';
$logFile = $logDir . '/stale-tickets.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@file_put_contents($logFile, implode(PHP_EOL, $logLines) . PHP_EOL, FILE_APPEND);

exit(0);
