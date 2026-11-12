<?php

/**
 * OpenHelpDesk — Domain Checks
 *
 * Re-checks every tracked domain (registrar + expiry, DNS, SPF/DKIM/DMARC,
 * certificates), then emails all administrators ONE digest of the domains and
 * certificates that have newly entered the expiry window. Each item is
 * mentioned at most three times per expiry date: on entering the window
 * (Admin → Settings → Domains → Alert window), at 7 days, and once expired.
 *
 * Run via cron once daily:
 *   0 7 * * * php /path/to/app/scripts/process-domain-checks.php \
 *       >> /path/to/app/storage/logs/domain-checks.log 2>&1
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/CronRun.php';
require_once ROOT_DIR . '/src/Domains.php';

loadEnv(ROOT_DIR . '/.env');
CronRun::boot($argv ?? []);

function logLine(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

$db = Database::connect();

$ids = $db->query('SELECT id, name FROM domains ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
logLine('Domain checks started: ' . count($ids) . ' domain(s).');
foreach ($ids as $id => $name) {
    try {
        Domains::refresh($db, (int) $id);
        logLine("  checked {$name}");
    } catch (\Throwable $e) {
        logLine("  FAILED {$name}: " . $e->getMessage());
    }
}

$days   = Domains::alertDays();
$alerts = Domains::alerts($db, $days);
logLine(count($alerts) . " item(s) expiring within {$days} days.");

// Keep only alerts not yet emailed at their current tier for this expiry date.
$seen = $db->prepare('SELECT 1 FROM domain_alerts_sent WHERE alert_key = ? AND expires_on = ? AND tier = ?');
$due  = [];
foreach ($alerts as $a) {
    $seen->execute([$a['key'], $a['expires_on'], Domains::tier($a['days_left'])]);
    if (!$seen->fetchColumn()) {
        $due[] = $a;
    }
}

if ($due === []) {
    logLine('Nothing new to email.');
    exit(0);
}
if (getSetting('domain_alerts_email', '1') !== '1') {
    logLine(count($due) . ' new alert(s), but email alerts are switched off. They still show in the app.');
    exit(0);
}

$admins = $db->query(
    "SELECT u.first_name, u.last_name, u.email FROM users u
     JOIN roles r ON r.slug = u.role
     WHERE r.is_admin = 1 AND u.email != '' ORDER BY u.first_name"
)->fetchAll(PDO::FETCH_ASSOC);
if ($admins === []) {
    logLine('No admin users found. Exiting.');
    exit(0);
}

$appName = getSetting('branding_app_name', 'OpenHelpDesk');
$pageUrl = rtrim(getSetting('app_url', ''), '/') . '/admin/settings/domains';
$expired = count(array_filter($due, static fn ($a) => $a['days_left'] <= 0));
$subject = "[{$appName}] " . ($expired > 0 ? 'EXPIRED: ' : '') . count($due) . ' domain/certificate expiry alert' . (count($due) === 1 ? '' : 's');

$rows = '';
$text = "Expiring domains and certificates\n\n";
foreach ($due as $a) {
    $what  = $a['kind'] === 'domain' ? 'Domain registration' : 'SSL certificate';
    $when  = $a['days_left'] <= 0 ? 'EXPIRED' : "in {$a['days_left']} day" . ($a['days_left'] === 1 ? '' : 's');
    $color = $a['days_left'] <= 7 ? '#dc3545' : '#b45309';
    $rows .= '<tr>'
           . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($a['label']) . '</td>'
           . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">' . $what . '</td>'
           . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($a['expires_on']) . '</td>'
           . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;color:' . $color . ';font-weight:600;">' . $when . '</td>'
           . '</tr>';
    $text .= "- {$a['label']} ({$what}): {$a['expires_on']}, {$when}\n";
}
$text .= "\n{$pageUrl}\n";

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:32px 16px;background:#f8fafc;font-family:sans-serif;color:#374151;">
  <table width="640" cellpadding="0" cellspacing="0" align="center" style="background:#fff;border-radius:8px;max-width:640px;">
    <tr><td style="padding:28px 32px;">
      <h2 style="margin:0 0 12px;font-size:20px;color:#111827;">Expiring domains and certificates</h2>
      <p style="margin:0 0 16px;line-height:1.6;">These are inside the {$days}-day alert window. Each is mentioned again at 7 days and once it has expired.</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
        <tr style="text-align:left;color:#6b7280;"><th style="padding:8px 12px;">Name</th><th style="padding:8px 12px;">What</th><th style="padding:8px 12px;">Expires</th><th style="padding:8px 12px;">When</th></tr>
        {$rows}
      </table>
      <p style="margin:24px 0 0;"><a href="{$pageUrl}" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:600;font-size:14px;">Open Domains</a></p>
    </td></tr>
    <tr><td style="padding:14px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">Automated alert from {$appName}. You are receiving this because you are an administrator.</td></tr>
  </table>
</body></html>
HTML;

$sent = 0;
foreach ($admins as $admin) {
    if (sendMail($admin['email'], trim($admin['first_name'] . ' ' . $admin['last_name']), $subject, $htmlBody, $text) !== false) {
        logLine("  emailed {$admin['email']}");
        $sent++;
    } else {
        logLine("  FAILED to email {$admin['email']}");
    }
}

// Only mark as sent if someone actually got it, so a mail outage retries tomorrow.
if ($sent > 0) {
    $mark = $db->prepare('INSERT IGNORE INTO domain_alerts_sent (alert_key, expires_on, tier) VALUES (?, ?, ?)');
    foreach ($due as $a) {
        $mark->execute([$a['key'], $a['expires_on'], Domains::tier($a['days_left'])]);
    }
}
logLine(count($due) . " alert(s) emailed to {$sent} admin(s).");
