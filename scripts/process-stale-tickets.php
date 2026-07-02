<?php

/**
 * OpenHelpDesk — Stale Ticket Notifier
 *
 * Finds active tickets that have had no update for longer than the
 * configured stale threshold and emails the assigned agent (or group)
 * plus the requester. Re-notifications are rate-limited via the
 * ticket_timeline `stale_notification_sent` entry so we don't spam.
 *
 * When a ticket's type + priority matches an SLA policy, idle time is
 * counted only on the days that policy's timer counts (skipping unticked
 * weekdays, SLA-excluded holidays, and business-schedule closed days).
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
require_once ROOT_DIR . '/src/Sla.php';

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

$globalThreshold = (int) (getSetting('stale_threshold_minutes', '4320') ?: '4320');
$recheckMins     = (int) (getSetting('stale_recheck_minutes', '1440') ?: '1440');

if ($globalThreshold <= 0) {
    logLine('Global stale threshold is 0 or invalid — feature disabled. Exiting.');
    exit(0);
}

logLine('Global threshold: ' . formatDuration($globalThreshold) . ' | Re-notify gap: ' . formatDuration($recheckMins));

// Only flag tickets the requester is still waiting on. Exclude statuses
// that are intentionally idle (waiting_on_customer / waiting_on_third_party)
// and closed-out states (resolved / closed).
$activeStatuses = ['open', 'in_progress', 'pending'];
$placeholders   = implode(',', array_fill(0, count($activeStatuses), '?'));

$stmt = $db->prepare(
    "SELECT t.*,
            COALESCE(tt.stale_threshold_minutes, ?) AS effective_threshold,
            TIMESTAMPDIFF(MINUTE, t.updated_at, NOW()) AS mins_since_update
     FROM tickets t
     LEFT JOIN ticket_types tt ON tt.id = t.type_id
     WHERE t.status IN ($placeholders)
       AND TIMESTAMPDIFF(MINUTE, t.updated_at, NOW()) >= COALESCE(tt.stale_threshold_minutes, ?)"
);
$params = array_merge([$globalThreshold], $activeStatuses, [$globalThreshold]);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

logLine(count($tickets) . ' ticket(s) past their stale threshold (wall-clock).');

// The staleness clock follows the same days the ticket's SLA policy timer
// counts: weekdays toggled off on the policy, holidays excluded from SLA, and
// days the business schedule marks closed don't advance it. Full 24-hour days
// are still counted on counting days, so a "3d" threshold means 3 counted
// days. Tickets with no matching SLA policy (or with SLA / business hours
// unconfigured) keep plain wall-clock counting. The SQL filter above remains a
// valid pre-filter: counted time can only be <= wall-clock time.
$biz          = slaEnabled() ? Sla::getBusinessSchedule() : null;
$excluded     = $biz !== null ? Sla::getExcludedDates($db) : [];
$countedCache = [];

$notified = 0;
$skipped  = 0;
$belowCountedThreshold = 0;

foreach ($tickets as $ticket) {
    $ticketId        = (int) $ticket['id'];
    $minsSinceUpdate = (int) $ticket['mins_since_update'];
    $thresholdMins   = (int) $ticket['effective_threshold'];

    $onSlaDays = false;
    if ($biz !== null && !empty($ticket['priority_id'])) {
        $cacheKey = (int) ($ticket['type_id'] ?? 0) . ':' . (int) $ticket['priority_id'];
        if (!array_key_exists($cacheKey, $countedCache)) {
            $policy = Sla::findPolicy($db, !empty($ticket['type_id']) ? (int) $ticket['type_id'] : null, (int) $ticket['priority_id']);
            $countedCache[$cacheKey] = $policy
                ? ['apply' => true, 'days' => Sla::parseCountedDays($policy['counted_days'] ?? null)]
                : ['apply' => false, 'days' => null];
        }
        if ($countedCache[$cacheKey]['apply']) {
            $onSlaDays = true;
            $tzObj = new DateTimeZone($biz['tz']);
            $minsSinceUpdate = Sla::countElapsedMinutesOnCountedDays(
                new DateTimeImmutable($ticket['updated_at'], $tzObj),
                new DateTimeImmutable('now', $tzObj),
                $biz['tz'],
                $biz['schedule'],
                $excluded,
                $countedCache[$cacheKey]['days']
            );
            if ($minsSinceUpdate < $thresholdMins) {
                $belowCountedThreshold++;
                continue;
            }
        }
    }
    // The stale emails still speak in whole hours; the timeline keeps full precision.
    $hoursSinceUpdate = intdiv($minsSinceUpdate, 60);
    $thresholdHours   = intdiv($thresholdMins, 60);

    // Dedup: skip if we already sent a stale notification within the recheck window.
    $dedup = $db->prepare(
        "SELECT id FROM ticket_timeline
         WHERE ticket_id = ? AND action = 'stale_notification_sent'
           AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
         ORDER BY id DESC LIMIT 1"
    );
    $dedup->execute([$ticketId, $recheckMins]);
    if ($dedup->fetch()) {
        $skipped++;
        continue;
    }

    try {
        notifyStaleTicketAgent($db, $ticket, $hoursSinceUpdate, $thresholdHours);
        notifyStaleTicketRequester($db, $ticket, $hoursSinceUpdate);

        $details = 'No activity for ' . formatDuration($minsSinceUpdate)
            . ($onSlaDays ? ' on SLA-counted days' : '')
            . ' (threshold ' . formatDuration($thresholdMins) . '). Notified agent and requester.';
        $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
             VALUES (?, NULL, 'stale_notification_sent', ?, 1)"
        )->execute([$ticketId, $details]);

        $notified++;
        logLine("Ticket #{$ticketId}: notified (" . formatDuration($minsSinceUpdate) . ' / ' . formatDuration($thresholdMins)
            . ($onSlaDays ? ', SLA-counted days' : '') . ').');
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
logLine("Done. Notified: {$notified}, skipped (cooldown): {$skipped}, below threshold on SLA-counted days: {$belowCountedThreshold}, no-group swept: {$noGroupSwept}. Took {$elapsed}s.");

// ── Persist log to file ───────────────────────────────────────────
$logDir  = ROOT_DIR . '/storage/logs';
$logFile = $logDir . '/stale-tickets.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@file_put_contents($logFile, implode(PHP_EOL, $logLines) . PHP_EOL, FILE_APPEND);

exit(0);
