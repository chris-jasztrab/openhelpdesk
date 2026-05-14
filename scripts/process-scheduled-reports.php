<?php

/**
 * OpenHelpDesk — Scheduled Reports Processor
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
$todayDate = date('Y-m-d');

foreach ($reports as $report) {
    $id             = (int) $report['id'];
    $name           = $report['name'];
    $type           = $report['report_type'];
    $frequency      = $report['frequency'];
    $sendDay        = $report['send_day'] !== null ? (int) $report['send_day'] : null;
    $dateRangeDays  = max(1, (int) ($report['date_range_days'] ?? 30));
    $lastSent       = $report['last_sent_at'];

    // ── Due check ────────────────────────────────────────────────────
    $isDue = false;
    if ($frequency === 'daily') {
        $lastSentDate = $lastSent ? date('Y-m-d', strtotime($lastSent)) : null;
        $isDue        = ($lastSentDate !== $todayDate);
    } elseif ($frequency === 'weekly') {
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
    $from  = date('Y-m-d', strtotime("-{$dateRangeDays} days"));
    $to    = $todayDate;
    $toEnd = $to . ' 23:59:59';
    $stats = [];

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
                    "SELECT COUNT(*) AS total FROM tickets WHERE created_at BETWEEN ? AND ?"
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

            case 'response_times':
                $stmt = $db->prepare(
                    "SELECT
                        AVG(TIMESTAMPDIFF(MINUTE, t.created_at, first_reply.replied_at)) AS avg_first_reply_min,
                        AVG(CASE WHEN t.resolved_at IS NOT NULL
                            THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) END) AS avg_resolve_min
                     FROM tickets t
                     LEFT JOIN (
                         SELECT ticket_id, MIN(created_at) AS replied_at
                         FROM ticket_timeline WHERE action = 'reply_sent'
                         GROUP BY ticket_id
                     ) first_reply ON first_reply.ticket_id = t.id
                     WHERE t.created_at BETWEEN ? AND ?"
                );
                $stmt->execute([$from, $toEnd]);
                $d = $stmt->fetch();
                $frt = $d['avg_first_reply_min'] !== null
                    ? round((float) $d['avg_first_reply_min'] / 60, 1) . ' hrs' : 'N/A';
                $rt  = $d['avg_resolve_min'] !== null
                    ? round((float) $d['avg_resolve_min'] / 60, 1) . ' hrs' : 'N/A';
                $stats = [
                    'Period'                => "{$from} to {$to}",
                    'Avg First Reply Time'  => $frt,
                    'Avg Resolution Time'   => $rt,
                ];
                break;

            case 'sla':
                $stmt = $db->prepare(
                    "SELECT COUNT(*) AS total,
                            SUM(CASE WHEN sla_state = 'breached' THEN 1 ELSE 0 END) AS breached,
                            SUM(CASE WHEN sla_state != 'breached' AND sla_state IS NOT NULL THEN 1 ELSE 0 END) AS compliant
                     FROM tickets WHERE created_at BETWEEN ? AND ?"
                );
                $stmt->execute([$from, $toEnd]);
                $d = $stmt->fetch();
                $total    = (int) $d['total'];
                $breached = (int) $d['breached'];
                $pct = $total > 0 ? round((($total - $breached) / $total) * 100) : 0;
                $stats = [
                    'Period'           => "{$from} to {$to}",
                    'Total Tickets'    => $total,
                    'SLA Compliant'    => (int) $d['compliant'],
                    'SLA Breached'     => $breached,
                    'Compliance Rate'  => "{$pct}%",
                ];
                break;

            case 'unresolved':
                $stmt = $db->prepare(
                    "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN 1 ELSE 0 END) AS lt_24h,
                        SUM(CASE WHEN TIMESTAMPDIFF(DAY,  created_at, NOW()) BETWEEN 1  AND 7  THEN 1 ELSE 0 END) AS d1_7,
                        SUM(CASE WHEN TIMESTAMPDIFF(DAY,  created_at, NOW()) BETWEEN 8  AND 30 THEN 1 ELSE 0 END) AS d8_30,
                        SUM(CASE WHEN TIMESTAMPDIFF(DAY,  created_at, NOW()) > 30 THEN 1 ELSE 0 END) AS gt_30d
                     FROM tickets WHERE status NOT IN ('resolved','closed')"
                );
                $stmt->execute();
                $d = $stmt->fetch();
                $stats = [
                    'Total Unresolved' => (int) $d['total'],
                    'Under 24 hours'   => (int) $d['lt_24h'],
                    '1–7 days old'     => (int) $d['d1_7'],
                    '8–30 days old'    => (int) $d['d8_30'],
                    'Over 30 days old' => (int) $d['gt_30d'],
                ];
                break;

            case 'lifecycle':
                $stmt = $db->prepare(
                    "SELECT
                        COUNT(*) AS created,
                        SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) AS closed_count,
                        AVG(CASE WHEN resolved_at IS NOT NULL
                            THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) AS avg_hours
                     FROM tickets WHERE created_at BETWEEN ? AND ?"
                );
                $stmt->execute([$from, $toEnd]);
                $d = $stmt->fetch();
                $stats = [
                    'Period'               => "{$from} to {$to}",
                    'Tickets Created'      => (int) $d['created'],
                    'Tickets Resolved'     => (int) $d['closed_count'],
                    'Avg Resolution Time'  => $d['avg_hours'] !== null
                        ? round((float) $d['avg_hours'], 1) . ' hrs' : 'N/A',
                ];
                break;

            case 'location':
                $stmt = $db->prepare(
                    "SELECT COALESCE(l.name, 'No Location') AS location_name, COUNT(t.id) AS cnt
                     FROM tickets t
                     LEFT JOIN locations l ON t.location_id = l.id
                     WHERE t.created_at BETWEEN ? AND ?
                     GROUP BY l.id, l.name
                     ORDER BY cnt DESC
                     LIMIT 8"
                );
                $stmt->execute([$from, $toEnd]);
                $rows = $stmt->fetchAll();
                $stats['Period'] = "{$from} to {$to}";
                foreach ($rows as $r) {
                    $stats[$r['location_name']] = (int) $r['cnt'];
                }
                break;

            case 'csat':
                $stmt = $db->prepare(
                    "SELECT COUNT(*) AS sent,
                            SUM(CASE WHEN rating IS NOT NULL THEN 1 ELSE 0 END) AS responded,
                            AVG(rating) AS avg_rating
                     FROM csat_surveys
                     WHERE sent_at BETWEEN ? AND ?"
                );
                $stmt->execute([$from, $toEnd]);
                $d = $stmt->fetch();
                $responseRate = (int) $d['sent'] > 0
                    ? round(((int) $d['responded'] / (int) $d['sent']) * 100) : 0;
                $stats = [
                    'Period'           => "{$from} to {$to}",
                    'Surveys Sent'     => (int) $d['sent'],
                    'Responses'        => (int) $d['responded'],
                    'Response Rate'    => "{$responseRate}%",
                    'Average Rating'   => $d['avg_rating'] !== null
                        ? round((float) $d['avg_rating'], 1) . ' / 5' : 'N/A',
                ];
                break;

            case 'workload':
                $stmt = $db->prepare(
                    "SELECT CONCAT(u.first_name,' ',u.last_name) AS agent_name,
                            COUNT(t.id) AS open_count
                     FROM users u
                     LEFT JOIN tickets t ON t.assigned_to = u.id
                         AND t.status NOT IN ('resolved','closed')
                     WHERE u.role IN ('admin','agent')
                     GROUP BY u.id, u.first_name, u.last_name
                     ORDER BY open_count DESC"
                );
                $stmt->execute();
                $agents = $stmt->fetchAll();
                $stats['As of'] = $todayDate;
                foreach ($agents as $a) {
                    $stats[$a['agent_name']] = (int) $a['open_count'] . ' open tickets';
                }
                break;

            case 'trends':
                // Compare this period vs previous period of same length
                $prevFrom = date('Y-m-d', strtotime("-{$dateRangeDays} days", strtotime($from)));
                $prevTo   = date('Y-m-d', strtotime('-1 day', strtotime($from)));
                $stmtCur  = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_at BETWEEN ? AND ?");
                $stmtCur->execute([$from, $toEnd]);
                $curCount = (int) $stmtCur->fetchColumn();
                $stmtPrev = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_at BETWEEN ? AND ?");
                $stmtPrev->execute([$prevFrom, $prevTo . ' 23:59:59']);
                $prevCount = (int) $stmtPrev->fetchColumn();
                $diff = $curCount - $prevCount;
                $pct  = $prevCount > 0 ? round(abs($diff) / $prevCount * 100) : 0;
                $trend = $diff >= 0 ? "+{$diff} ({$pct}% increase)" : "{$diff} ({$pct}% decrease)";
                $stats = [
                    'Current Period'  => "{$from} to {$to}: {$curCount} tickets",
                    'Previous Period' => "{$prevFrom} to {$prevTo}: {$prevCount} tickets",
                    'Change'          => $trend,
                ];
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

            default:
                logLine("Unknown report type \"{$type}\" for \"{$name}\". Skipping.");
                continue 2;
        }
    } catch (\Throwable $e) {
        logLine("ERROR building data for \"{$name}\": " . $e->getMessage());
        continue;
    }

    // ── Build email ───────────────────────────────────────────────────
    $appName    = getSetting('app_name', 'OpenHelpDesk');
    $appUrl     = env('APP_URL', 'http://localhost');
    $brandColor = getSetting('branding_primary_color', '#4f46e5');
    $footerText = "Sent by {$appName} · Scheduled Reports";
    $typeLabels = [
        'overview'          => 'Overview',
        'agent_performance' => 'Agent Performance',
        'ticket_volume'     => 'Ticket Volume',
        'response_times'    => 'Response Times',
        'sla'               => 'SLA Compliance',
        'unresolved'        => 'Unresolved Tickets',
        'lifecycle'         => 'Ticket Lifecycle',
        'location'          => 'By Location',
        'csat'              => 'CSAT / Satisfaction',
        'workload'          => 'Agent Workload',
        'trends'            => 'Ticket Trends',
        'fcr'               => 'FCR Rate',
    ];

    $typeLabel   = $typeLabels[$type] ?? $type;
    $periodLabel = in_array($type, ['unresolved', 'workload'], true)
        ? "As of {$to}"
        : "Previous {$dateRangeDays} days ({$from} to {$to})";

    $html = renderEmail('scheduled-report', [
        'appName'     => $appName,
        'reportName'  => $typeLabel,
        'periodLabel' => $periodLabel,
        'frequency'   => ucfirst($frequency),
        'stats'       => $stats,
        'brandColor'  => $brandColor,
        'reportsUrl'  => rtrim($appUrl, '/') . '/admin/reports',
        'footerText'  => $footerText,
    ]);

    // ── Send to each recipient ────────────────────────────────────────
    $sent = 0;
    $subject = "[{$appName}] {$typeLabel} - {$frequency} report";
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
