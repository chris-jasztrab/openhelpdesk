<?php

/**
 * LocalDesk — Scheduled Reports Processor
 *
 * Checks for due scheduled reports and emails summaries to configured recipients.
 * Designed to run via cron every 30 minutes:
 *
 *   * /30 * * * * php /path/to/app/scripts/process-scheduled-reports.php \
 *       >> /path/to/app/storage/logs/scheduled-reports.log 2>&1
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

logLine('Scheduled report processor started.');

$db = Database::connect();

// Load all enabled scheduled reports
$reports = $db->query('SELECT * FROM scheduled_reports WHERE is_enabled = 1')->fetchAll();

if (empty($reports)) {
    logLine('No enabled scheduled reports. Exiting.');
    exit(0);
}

logLine(count($reports) . ' enabled report schedule(s) loaded.');

$totalSent = 0;
$today     = (int) date('w'); // 0=Sun … 6=Sat (day of week)
$todayDom  = (int) date('j'); // 1–31 (day of month)

foreach ($reports as $report) {
    $id        = (int) $report['id'];
    $name      = $report['name'];
    $type      = $report['report_type'];
    $frequency = $report['frequency'];
    $sendDay   = (int) $report['send_day'];
    $lastSent  = $report['last_sent_at'];

    // ── Due check ────────────────────────────────────────────────────
    $isDue = false;
    if ($frequency === 'weekly') {
        $dayMatch  = ($today === $sendDay);
        $notRecent = ($lastSent === null || strtotime($lastSent) < strtotime('-6 days'));
        $isDue     = $dayMatch && $notRecent;
    } else { // monthly
        $dayMatch  = ($todayDom === $sendDay);
        $notRecent = ($lastSent === null || strtotime($lastSent) < strtotime('-27 days'));
        $isDue     = $dayMatch && $notRecent;
    }

    if (!$isDue) {
        logLine("Report \"{$name}\" is not due today. Skipping.");
        continue;
    }

    logLine("Report \"{$name}\" is due — building data...");

    $recipients = json_decode($report['recipients'], true) ?: [];
    if (empty($recipients)) {
        logLine("Report \"{$name}\" has no recipients. Skipping.");
        continue;
    }

    // ── Build report data ─────────────────────────────────────────────
    $from    = date('Y-m-d', strtotime('-30 days'));
    $to      = date('Y-m-d');
    $toEnd   = $to . ' 23:59:59';
    $stats   = [];

    try {
        switch ($type) {
            case 'overview':
                $row = $db->prepare(
                    "SELECT COUNT(*) AS total,
                            SUM(CASE WHEN status NOT IN ('resolved','closed') THEN 1 ELSE 0 END) AS open_count,
                            SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved_count,
                            SUM(CASE WHEN sla_state = 'breached' THEN 1 ELSE 0 END) AS breached_count
                     FROM tickets WHERE created_at BETWEEN ? AND ?"
                );
                $row->execute([$from, $toEnd]);
                $d = $row->fetch();
                $stats = [
                    'Period'         => "{$from} to {$to}",
                    'Total Tickets'  => (int) $d['total'],
                    'Open'           => (int) $d['open_count'],
                    'Resolved'       => (int) $d['resolved_count'],
                    'SLA Breached'   => (int) $d['breached_count'],
                ];
                break;

            case 'agent_performance':
                $stmt = $db->prepare(
                    "SELECT CONCAT(u.first_name,' ',u.last_name) AS agent_name,
                            COUNT(t.id) AS assigned,
                            SUM(CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved
                     FROM users u
                     LEFT JOIN tickets t ON t.assigned_to = u.id AND t.created_at BETWEEN ? AND ?
                     WHERE u.role IN ('admin','agent')
                     GROUP BY u.id, u.first_name, u.last_name
                     ORDER BY resolved DESC
                     LIMIT 5"
                );
                $stmt->execute([$from, $toEnd]);
                $agents = $stmt->fetchAll();
                $stats['Period'] = "{$from} to {$to}";
                foreach ($agents as $a) {
                    $stats[$a['agent_name']] = "Assigned: {$a['assigned']}, Resolved: {$a['resolved']}";
                }
                break;

            case 'ticket_volume':
                $stmt = $db->prepare(
                    "SELECT COUNT(*) AS total,
                            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_count
                     FROM tickets WHERE created_at BETWEEN ? AND ?"
                );
                $stmt->execute([$from, $toEnd]);
                $d = $stmt->fetch();
                $byType = $db->prepare(
                    "SELECT COALESCE(tt.name,'Untyped') AS name, COUNT(t.id) AS cnt
                     FROM tickets t LEFT JOIN ticket_types tt ON t.type_id = tt.id
                     WHERE t.created_at BETWEEN ? AND ?
                     GROUP BY tt.id, tt.name ORDER BY cnt DESC LIMIT 3"
                );
                $byType->execute([$from, $toEnd]);
                $types = $byType->fetchAll();
                $stats['Period']        = "{$from} to {$to}";
                $stats['Total Created'] = (int) $d['total'];
                foreach ($types as $t) {
                    $stats["Type: {$t['name']}"] = (int) $t['cnt'];
                }
                break;

            case 'fcr':
                $stmt = $db->prepare(
                    "SELECT COUNT(*) AS total_resolved,
                            SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
                     FROM (
                         SELECT t.id,
                             (SELECT COUNT(*) FROM ticket_timeline tl
                              WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
                         FROM tickets t
                         WHERE t.status IN ('resolved','closed') AND t.created_at BETWEEN ? AND ?
                     ) sub"
                );
                $stmt->execute([$from, $toEnd]);
                $d   = $stmt->fetch();
                $pct = $d['total_resolved'] > 0
                    ? round($d['fcr_count'] / $d['total_resolved'] * 100) : 0;
                $stats = [
                    'Period'         => "{$from} to {$to}",
                    'Total Resolved' => (int) $d['total_resolved'],
                    'FCR Tickets'    => (int) $d['fcr_count'],
                    'FCR Rate'       => "{$pct}%",
                ];
                break;
        }
    } catch (\Throwable $e) {
        logLine("ERROR building data for \"{$name}\": " . $e->getMessage());
        continue;
    }

    // ── Build email ───────────────────────────────────────────────────
    $appName    = getSetting('app_name', 'LocalDesk');
    $appUrl     = env('APP_URL', 'http://localhost');
    $brandColor = getSetting('branding_primary_color', '#4f46e5');
    $footerText = "Sent by {$appName} · Scheduled Reports";
    $typeLabels = [
        'overview'          => 'Overview',
        'agent_performance' => 'Agent Performance',
        'ticket_volume'     => 'Ticket Volume',
        'fcr'               => 'FCR Rate',
    ];

    $html = renderEmail('scheduled-report', [
        'appName'     => $appName,
        'reportName'  => $typeLabels[$type] ?? $type,
        'periodLabel' => "{$from} to {$to}",
        'frequency'   => ucfirst($frequency),
        'stats'       => $stats,
        'brandColor'  => $brandColor,
        'reportsUrl'  => rtrim($appUrl, '/') . '/admin/reports',
        'footerText'  => $footerText,
    ]);

    // ── Send to each recipient ────────────────────────────────────────
    $sent = 0;
    $subject = "[{$appName}] {$typeLabels[$type] ?? $type} — {$frequency} report";
    foreach ($recipients as $email) {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            logLine("Skipping invalid email: {$email}");
            continue;
        }
        $result = sendMail($email, $email, $subject, $html);
        if ($result !== false) {
            $sent++;
        } else {
            logLine("Failed to send to {$email}.");
        }
    }

    if ($sent > 0) {
        // Update last_sent_at
        $db->prepare('UPDATE scheduled_reports SET last_sent_at = NOW() WHERE id = ?')->execute([$id]);
        logLine("Report \"{$name}\" sent to {$sent} recipient(s).");
        $totalSent++;
    }
}

$elapsed = round(microtime(true) - $startTime, 2);
logLine("Done. {$totalSent} report(s) sent in {$elapsed}s.");

// ── Persist log to file ───────────────────────────────────────────
$logDir  = ROOT_DIR . '/storage/logs';
$logFile = $logDir . '/scheduled-reports.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@file_put_contents($logFile, implode(PHP_EOL, $logLines) . PHP_EOL, FILE_APPEND);

exit(0);
