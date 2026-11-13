<?php

/**
 * OpenHelpDesk — Inventory Digest
 *
 * Emails the inventory expiry digest (warranties ending soon, warranties that
 * just ended, end-of-life dates coming up, audits overdue) on the weekly or
 * monthly schedule set under Inventory › Reports › Email digest. Does nothing
 * until the digest is switched on there, and nothing on days that are not a
 * scheduled day, so it is safe to leave in the crontab.
 *
 * Run via cron once daily:
 *   30 7 * * * php /path/to/app/scripts/process-asset-digest.php \
 *       >> /path/to/app/storage/logs/asset-digest.log 2>&1
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/CronRun.php';
require_once ROOT_DIR . '/src/AssetDigest.php';

loadEnv(ROOT_DIR . '/.env');
CronRun::boot($argv ?? []);

$log = static function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

$s     = AssetDigest::settings();
$today = date('Y-m-d');

if (!$s['enabled']) {
    $log('Inventory digest is switched off (Inventory › Reports › Email digest).');
    exit(0);
}
if (!AssetDigest::isDue($s, $today)) {
    $log('Not a digest day — next one is ' . AssetDigest::nextSlot($s, $today) . '.');
    exit(0);
}

$log("Inventory digest due ({$s['frequency']}, {$s['days']}-day window).");
$r = AssetDigest::send(Database::connect(), $s, $today, null, $log);
$log("Done: {$r['items']} item(s), emailed to {$r['sent']} recipient(s).");
