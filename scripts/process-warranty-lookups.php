<?php

/**
 * OpenHelpDesk — Warranty Lookups
 *
 * Asks Dell and Lenovo for the warranty of every inventory asset never looked
 * up, or last looked up 30+ days ago (oldest first, up to --limit, default
 * 150), and saves the start, end and coverage on the asset. Sends no email.
 * Dell is skipped until a TechDirect key is saved in Settings › Inventory.
 *
 * Run via cron once daily:
 *   30 6 * * * php /path/to/app/scripts/process-warranty-lookups.php \
 *       >> /path/to/app/storage/logs/warranty-lookups.log 2>&1
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/CronRun.php';
require_once ROOT_DIR . '/src/Warranty.php';

loadEnv(ROOT_DIR . '/.env');
CronRun::boot($argv ?? []);

$limit = 150;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, (int) $m[1]);
    }
}

$log = static function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

$log("Warranty lookups started (up to {$limit}).");
$n = Warranty::refreshDue(Database::connect(), $limit, $log);
$log("Done: {$n['checked']} looked up, {$n['found']} with a warranty on record, {$n['failed']} without.");
