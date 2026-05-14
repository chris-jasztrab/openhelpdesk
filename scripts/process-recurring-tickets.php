<?php

/**
 * OpenHelpDesk — Recurring / Preventive-Maintenance Ticket Processor
 *
 * Walks every active row in `recurring_tickets` whose `next_run_at` has
 * passed and mints a real ticket from it, then advances `next_run_at`
 * to the next firing slot per the schedule's cadence rules.
 *
 * Designed to run via cron every 15 minutes:
 *
 *   * /15 * * * * php /path/to/app/scripts/process-recurring-tickets.php \
 *       >> /path/to/app/storage/logs/recurring-tickets.log 2>&1
 *
 * The processor is missed-tick-safe: if the cron didn't run for a day,
 * each schedule fires once on the next pass and is then re-scheduled
 * forward — it does not back-fill missed runs. That's deliberate: you
 * don't want the system to dump fourteen "monthly toner audit" tickets
 * into the queue because the cron was paused for a year.
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Sla.php';
require_once ROOT_DIR . '/src/Holidays.php';
require_once ROOT_DIR . '/src/AI.php';
require_once ROOT_DIR . '/src/RecurringTickets.php';

loadEnv(ROOT_DIR . '/.env');

$startTime = microtime(true);

function rtLog(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

rtLog('Recurring-tickets processor started.');

$db  = Database::connect();
$now = new DateTimeImmutable('now');

$stmt = $db->prepare(
    'SELECT * FROM recurring_tickets
     WHERE is_active = 1
       AND next_run_at <= ?
     ORDER BY next_run_at ASC'
);
$stmt->execute([$now->format('Y-m-d H:i:s')]);
$due = $stmt->fetchAll();

if (empty($due)) {
    rtLog('No schedules due. Exiting.');
    exit(0);
}

rtLog(count($due) . ' due schedule(s) found.');

$fired   = 0;
$skipped = 0;

foreach ($due as $schedule) {
    $id   = (int) $schedule['id'];
    $name = (string) ($schedule['name'] ?? "schedule #{$id}");
    rtLog("→ Processing \"{$name}\" (id={$id})");

    try {
        $ticketId = RecurringTickets::mintTicket($db, $schedule);
    } catch (Throwable $e) {
        rtLog("   ERROR creating ticket: " . $e->getMessage());
        // Push next_run_at forward by one cycle so we don't tight-loop
        // on a permanently broken row every cron pass.
        try {
            $next = RecurringTickets::computeNextRun($schedule, $now, true);
            $db->prepare('UPDATE recurring_tickets SET next_run_at = ? WHERE id = ?')
               ->execute([$next->format('Y-m-d H:i:s'), $id]);
        } catch (Throwable $e2) {
            rtLog("   FAILED to advance next_run_at: " . $e2->getMessage());
        }
        $skipped++;
        continue;
    }

    if (!$ticketId) {
        rtLog("   SKIPPED — schedule unfit to fire (missing requester or required fields). Auto-disabling.");
        $db->prepare('UPDATE recurring_tickets SET is_active = 0 WHERE id = ?')->execute([$id]);
        $skipped++;
        continue;
    }

    $next = RecurringTickets::computeNextRun($schedule, $now, true);
    $db->prepare(
        'UPDATE recurring_tickets
            SET last_run_at    = ?,
                last_ticket_id = ?,
                next_run_at    = ?,
                run_count      = run_count + 1
          WHERE id = ?'
    )->execute([
        $now->format('Y-m-d H:i:s'),
        $ticketId,
        $next->format('Y-m-d H:i:s'),
        $id,
    ]);

    rtLog("   Created ticket #{$ticketId}; next run: " . $next->format('Y-m-d H:i'));
    $fired++;
}

$elapsed = round(microtime(true) - $startTime, 2);
rtLog("Done. Fired {$fired}, skipped {$skipped}, in {$elapsed}s.");
exit(0);
