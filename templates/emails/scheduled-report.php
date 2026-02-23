<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.1);">

    <!-- Header -->
    <tr>
        <td style="background:linear-gradient(135deg,#1e1b4b,#312e81); padding:20px 32px;">
            <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:700;"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
        </td>
    </tr>

    <!-- Title banner -->
    <tr>
        <td style="background:#f0fdf4; border-bottom:3px solid #22c55e; padding:12px 32px;">
            <p style="margin:0; font-size:13px; font-weight:700; color:#15803d; text-transform:uppercase; letter-spacing:.5px;">
                📊 Scheduled Report: <?= htmlspecialchars($reportName, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px;">
            <p style="margin:0 0 8px; font-size:14px; color:#64748b;">
                Period: <strong><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
            <p style="margin:0 0 24px; font-size:14px; color:#64748b;">
                Here is your <?= htmlspecialchars(strtolower($frequency), ENT_QUOTES, 'UTF-8') ?> summary.
            </p>

            <!-- Stats table -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">
                <tr>
                    <td style="padding:8px 16px; background:#f8fafc; font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid #e2e8f0;">
                        Summary
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px;">
                        <table width="100%" cellpadding="0" cellspacing="4">
                            <?php foreach ($stats as $label => $value): ?>
                            <tr>
                                <td style="font-size:13px; color:#64748b; width:200px; padding:4px 0;">
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td style="font-size:13px; color:#1e293b; font-weight:600;">
                                    <?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- CTA button -->
            <table cellpadding="0" cellspacing="0">
                <tr>
                    <td style="border-radius:6px; background:<?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8') ?>;">
                        <a href="<?= htmlspecialchars($reportsUrl, ENT_QUOTES, 'UTF-8') ?>"
                           style="display:inline-block; padding:10px 24px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600;">
                            View Reports
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="padding:16px 32px; background:#f8fafc; border-top:1px solid #e2e8f0;">
            <p style="margin:0; font-size:12px; color:#94a3b8; text-align:center;">
                <?= htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
