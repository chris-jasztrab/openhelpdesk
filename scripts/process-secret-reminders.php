<?php

/**
 * OpenHelpDesk — App Secret Expiry Reminder
 *
 * Sends email reminders to all admin users when the Microsoft Graph
 * app secret is approaching its expiry date.
 *
 * Reminder thresholds: 30 days, 7 days, and day-of expiry.
 * Each reminder is sent only once (flags stored in settings table).
 *
 * Run via cron once daily:
 *   0 8 * * * php /path/to/app/scripts/process-secret-reminders.php \
 *       >> /path/to/app/storage/logs/secret-reminders.log 2>&1
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';

loadEnv(ROOT_DIR . '/.env');

function logLine(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
}

logLine('Secret reminder processor started.');

$db = Database::connect();

// ── Check if an expiry date is configured ─────────────────────────
$expiry = getSetting('graph_secret_expires_at', '');
if ($expiry === '') {
    logLine('No app secret expiry date configured. Nothing to do.');
    exit(0);
}

$expiryTs      = strtotime($expiry);
$daysLeft      = (int) ceil(($expiryTs - time()) / 86400);
$expiryDisplay = date('F j, Y', $expiryTs);

logLine("App secret expiry: {$expiry} ({$daysLeft} days remaining).");

// ── Define thresholds ──────────────────────────────────────────────
// Each entry: [days_threshold, flag_key, label]
$thresholds = [
    [30, 'graph_secret_remind_1month', '30-day'],
    [7,  'graph_secret_remind_1week',  '7-day'],
    [0,  'graph_secret_remind_day',    'day-of'],
];

// ── Load all admin email addresses ────────────────────────────────
$admins = $db->query(
    "SELECT first_name, last_name, email FROM users WHERE role = 'admin' AND email != '' ORDER BY first_name"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($admins)) {
    logLine('No admin users found. Exiting.');
    exit(0);
}

logLine('Admin recipients: ' . implode(', ', array_column($admins, 'email')));

$appName    = getSetting('branding_app_name', 'OpenHelpDesk');
$appUrl     = getSetting('app_url', '');
$settingsUrl = rtrim($appUrl, '/') . '/admin/settings#graph-secret';

// ── Check and send each threshold ─────────────────────────────────
foreach ($thresholds as [$threshold, $flagKey, $label]) {
    if ($daysLeft > $threshold) {
        logLine("  [{$label}] Not yet in window (days left: {$daysLeft}, threshold: {$threshold}). Skipping.");
        continue;
    }

    if (getSetting($flagKey, '0') === '1') {
        logLine("  [{$label}] Reminder already sent. Skipping.");
        continue;
    }

    // Build email content
    if ($daysLeft <= 0) {
        $subject  = "[{$appName}] URGENT: Microsoft Graph app secret has expired";
        $urgency  = 'expired';
        $headline = 'Your Microsoft Graph app secret has expired.';
        $detail   = 'Reply-by-email processing has stopped working. Rotate the secret in Azure Portal immediately and update it in your helpdesk settings.';
        $callout  = 'Expired on: <strong>' . htmlspecialchars($expiryDisplay) . '</strong>';
    } elseif ($daysLeft <= 7) {
        $subject  = "[{$appName}] ACTION REQUIRED: App secret expires in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's');
        $urgency  = 'critical';
        $headline = "Your Microsoft Graph app secret expires in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's') . '.';
        $detail   = 'Rotate the secret in Azure Portal now and update it in your helpdesk settings before reply-by-email stops working.';
        $callout  = 'Expiry date: <strong>' . htmlspecialchars($expiryDisplay) . '</strong>';
    } else {
        $subject  = "[{$appName}] Reminder: App secret expires in {$daysLeft} days";
        $urgency  = 'warning';
        $headline = "Your Microsoft Graph app secret expires in {$daysLeft} days.";
        $detail   = 'Plan to rotate the secret in Azure Portal and update it in your helpdesk settings before it expires.';
        $callout  = 'Expiry date: <strong>' . htmlspecialchars($expiryDisplay) . '</strong>';
    }

    $borderColor = $urgency === 'expired' || $urgency === 'critical' ? '#dc3545' : '#fd7e14';

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);overflow:hidden;max-width:600px;">
        <tr><td style="background:{$borderColor};padding:4px 0;"></td></tr>
        <tr><td style="padding:32px 40px;">
          <h2 style="margin:0 0 16px;font-size:20px;color:#111827;">{$headline}</h2>
          <p style="margin:0 0 16px;color:#374151;line-height:1.6;">{$detail}</p>
          <div style="background:#fff8f0;border-left:4px solid {$borderColor};padding:12px 16px;border-radius:4px;margin:0 0 24px;font-size:14px;color:#374151;">
            {$callout}<br>
            <strong>Credential:</strong> Microsoft Graph Client Secret<br>
            <strong>Location:</strong> Azure Portal → App Registration → Certificates &amp; secrets
          </div>
          <p style="margin:0 0 8px;color:#374151;line-height:1.6;font-size:14px;"><strong>Steps to rotate:</strong></p>
          <ol style="margin:0 0 24px;padding-left:20px;color:#374151;font-size:14px;line-height:1.8;">
            <li>Sign in to <a href="https://portal.azure.com" style="color:#4f46e5;">Azure Portal</a></li>
            <li>Navigate to your App Registration → Certificates &amp; secrets</li>
            <li>Create a new client secret and note its value and expiry date</li>
            <li>Update the secret and expiry date in <a href="{$settingsUrl}" style="color:#4f46e5;">{$appName} Inbound Mail Settings</a></li>
            <li>Delete the old secret from Azure Portal</li>
          </ol>
          <p style="margin:0;">
            <a href="{$settingsUrl}"
               style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:600;font-size:14px;">
              Go to Inbound Mail Settings
            </a>
          </p>
        </td></tr>
        <tr><td style="padding:16px 40px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">
          This is an automated reminder from {$appName}. You are receiving this because you are an administrator.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</h2>'], "\n", $htmlBody));

    $sent = 0;
    foreach ($admins as $admin) {
        $name = trim($admin['first_name'] . ' ' . $admin['last_name']);
        $result = sendMail($admin['email'], $name, $subject, $htmlBody, $textBody);
        if ($result !== false) {
            logLine("  [{$label}] Email sent to {$admin['email']}.");
            $sent++;
        } else {
            logLine("  [{$label}] Failed to send email to {$admin['email']}.");
        }
    }

    if ($sent > 0) {
        setSetting($flagKey, '1');
        logLine("  [{$label}] Flag set. {$sent} email(s) sent.");
    }
}

logLine('Secret reminder processor finished.');
exit(0);
