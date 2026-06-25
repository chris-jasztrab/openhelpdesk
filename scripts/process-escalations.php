<?php

/**
 * OpenHelpDesk — Escalation Rules Processor
 *
 * Evaluates all enabled escalation rules against active tickets.
 * Designed to run via cron every 15 minutes:
 *
 *   * /15 * * * * php /path/to/app/scripts/process-escalations.php \
 *       >> /path/to/app/storage/logs/escalations.log 2>&1
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

logLine('Escalation processor started.');

$db = Database::connect();

// ── Load all enabled rules ────────────────────────────────────────
$rules = $db->query(
    'SELECT * FROM escalation_rules WHERE is_enabled = 1 ORDER BY sort_order, id'
)->fetchAll();

if (empty($rules)) {
    logLine('No enabled escalation rules found. Exiting.');
    exit(0);
}

logLine(count($rules) . ' enabled rule(s) loaded.');

// ── Load all active tickets ───────────────────────────────────────
$activeStatuses = ['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party'];
$placeholders   = implode(',', array_fill(0, count($activeStatuses), '?'));

$stmt = $db->prepare(
    "SELECT t.*,
            tp.name AS priority_name
     FROM tickets t
     LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
     WHERE t.status IN ($placeholders)"
);
$stmt->execute($activeStatuses);
$tickets = $stmt->fetchAll();

logLine(count($tickets) . ' active ticket(s) found.');

$totalFired = 0;

// ── Evaluate each rule against each ticket ────────────────────────
foreach ($rules as $rule) {
    $ruleId        = (int) $rule['id'];
    $ruleName      = $rule['name'];
    $cooldownMins  = (int) $rule['cooldown_minutes'];
    $conditions    = json_decode($rule['conditions'], true) ?: [];
    $ruleFired     = 0;

    foreach ($tickets as $ticket) {
        $ticketId = (int) $ticket['id'];

        // ── Cooldown / dedup check ────────────────────────────────
        if ($cooldownMins === 0) {
            // Fire at most once ever per ticket
            $check = $db->prepare(
                'SELECT id FROM escalation_log WHERE rule_id = ? AND ticket_id = ? LIMIT 1'
            );
            $check->execute([$ruleId, $ticketId]);
            if ($check->fetch()) {
                continue; // already fired
            }
        } else {
            // Fire at most once per cooldown window
            $check = $db->prepare(
                'SELECT id FROM escalation_log
                 WHERE rule_id = ? AND ticket_id = ?
                   AND fired_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 LIMIT 1'
            );
            $check->execute([$ruleId, $ticketId, $cooldownMins]);
            if ($check->fetch()) {
                continue; // still within cooldown
            }
        }

        // ── Evaluate conditions ───────────────────────────────────
        if (!evaluateEscalationConditions($db, $conditions, $ticket)) {
            continue;
        }

        // ── All conditions matched — execute actions ──────────────
        logLine("Rule #{$ruleId} \"{$ruleName}\" matched ticket #{$ticketId}.");

        try {
            runEscalationRule($db, $rule, $ticket);

            // Record in escalation log
            $db->prepare('INSERT INTO escalation_log (rule_id, ticket_id) VALUES (?, ?)')
               ->execute([$ruleId, $ticketId]);

            $ruleFired++;
            $totalFired++;
        } catch (\Throwable $e) {
            logLine("ERROR on ticket #{$ticketId}: " . $e->getMessage());
        }
    }

    if ($ruleFired > 0) {
        logLine("Rule \"{$ruleName}\": fired on {$ruleFired} ticket(s).");
    }
}

$elapsed = round(microtime(true) - $startTime, 2);
logLine("Done. {$totalFired} escalation(s) fired in {$elapsed}s.");

// ── Persist log to file ───────────────────────────────────────────
$logDir  = ROOT_DIR . '/storage/logs';
$logFile = $logDir . '/escalations.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@file_put_contents($logFile, implode(PHP_EOL, $logLines) . PHP_EOL, FILE_APPEND);

exit(0);
