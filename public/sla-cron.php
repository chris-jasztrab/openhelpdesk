<?php

/**
 * SLA Recalculation Cron Script
 *
 * Run via cron every 5 minutes:
 *   * /5 * * * * php /path/to/public/sla-cron.php
 *
 * Or call via HTTP with a secret token:
 *   GET /sla-cron.php?token=YOUR_SECRET_TOKEN
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

// Minimal bootstrap (no session, no router)
require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Sla.php';
require_once ROOT_DIR . '/src/CronRun.php';

loadEnv(ROOT_DIR . '/.env');

// CLI only: honours --dry-run and takes the single-instance lock. A no-op on
// the HTTP token path below, which keeps its original behaviour.
CronRun::boot($argv ?? []);

// Security: if called via web, require a token
if (php_sapi_name() !== 'cli') {
    $expectedToken = env('SLA_CRON_TOKEN');
    $providedToken = $_GET['token'] ?? '';
    // Fail closed if no token is configured; constant-time compare otherwise.
    if ($expectedToken === '' || !hash_equals($expectedToken, (string) $providedToken)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$db = Database::connect();
$count = Sla::recalculateAll($db);
// These ride this required 5-minute job rather than adding cron lines of their own.
$followups = processDueTicketFollowups($db);
$reminders = processTicketReminders($db);
$swept     = sweepNoGroupTickets($db);
$completed = resolveParentsWithClosedTasks($db);

$message = date('Y-m-d H:i:s') . " - SLA recalculated: {$count} ticket(s) updated. Follow-up reminders sent: {$followups}."
    . " Ticket reminders: {$reminders['overdue']} overdue, {$reminders['update']} update due."
    . ($completed > 0 ? " Resolved {$completed} ticket(s) whose task tickets are all closed." : '')
    . ($swept > 0 ? " WARN: routed {$swept} ticket(s) with no group to the default group." : '') . "\n";

if (php_sapi_name() === 'cli') {
    echo $message;
} else {
    header('Content-Type: text/plain');
    echo $message;
}
