<?php

/**
 * LocalDesk — AI classification backfill
 *
 * Walks open tickets that haven't been classified yet (or were
 * classified before the current feature was enabled) and runs them
 * through classifyTicketWithAI(). Designed to be idempotent and rate-
 * limit friendly: each ticket gets a small sleep between calls, and
 * the run is bounded by --limit so a single invocation can't blow
 * through your provider quota.
 *
 * Usage:
 *   php scripts/ai-classify-backfill.php [--limit=N] [--statuses=open,in_progress] [--dry-run]
 *
 * Defaults:
 *   --limit=50
 *   --statuses=open,in_progress,pending
 *   --dry-run not set (i.e. real run)
 *
 * Cron-friendly. Safe to re-run — already-classified tickets are
 * skipped (we look at tickets.ai_classification_id IS NULL).
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/AI.php';

loadEnv(ROOT_DIR . '/.env');

// ── Parse args ────────────────────────────────────────────────────
$limit     = 50;
$statuses  = ['open', 'in_progress', 'pending'];
$dryRun    = false;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(500, (int) substr($arg, 8)));
    } elseif (str_starts_with($arg, '--statuses=')) {
        $statuses = array_filter(array_map('trim', explode(',', substr($arg, 11))));
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    }
}

function bf_log(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

bf_log('AI classification backfill — limit=' . $limit . ', statuses=' . implode(',', $statuses) . ($dryRun ? ', DRY RUN' : ''));

if (getSetting('ai_enabled', '0') !== '1') {
    bf_log('AI classification is disabled in settings — nothing to do.');
    exit(0);
}

$db = Database::connect();

$ph = implode(',', array_fill(0, count($statuses), '?'));
$stmt = $db->prepare(
    "SELECT t.id, t.subject
     FROM tickets t
     LEFT JOIN ticket_types tt ON tt.id = t.type_id
     WHERE t.ai_classification_id IS NULL
       AND t.status IN ($ph)
       AND COALESCE(tt.is_confidential, 0) = 0
     ORDER BY t.id DESC
     LIMIT $limit"
);
$stmt->execute($statuses);
$tickets = $stmt->fetchAll();

bf_log(count($tickets) . ' eligible ticket(s) found.');

$ok = 0; $fail = 0;
foreach ($tickets as $t) {
    $tid = (int) $t['id'];
    if ($dryRun) {
        bf_log("  Would classify ticket #{$tid}: " . mb_strimwidth((string) $t['subject'], 0, 80, '…'));
        $ok++;
        continue;
    }
    try {
        $verdict = classifyTicketWithAI($tid);
        if ($verdict === null) {
            bf_log("  Ticket #{$tid}: skipped or failed silently.");
            $fail++;
        } else {
            $skills = !empty($verdict['skill_ids']) ? implode(',', $verdict['skill_ids']) : '(none)';
            $conf   = (int) round(((float) ($verdict['confidence'] ?? 0)) * 100);
            bf_log("  Ticket #{$tid}: skills=[$skills] conf={$conf}% sentiment=" . ($verdict['sentiment'] ?? 'neutral'));
            $ok++;
        }
    } catch (\Throwable $e) {
        bf_log("  Ticket #{$tid}: ERROR " . $e->getMessage());
        $fail++;
    }
    // Be a polite neighbour to the API
    usleep(250000); // 250ms
}

bf_log("Done. classified={$ok} skipped/failed={$fail}.");
exit(0);
