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

    <!-- Alert banner -->
    <tr>
        <td style="background:#fef2f2; border-bottom:3px solid #ef4444; padding:12px 32px;">
            <p style="margin:0; font-size:13px; font-weight:700; color:#b91c1c; text-transform:uppercase; letter-spacing:.5px;">
                ⚠ Escalation Alert
            </p>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px;">
            <h2 style="margin:0 0 8px; font-size:18px; color:#1e293b;">
                Hi <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>,
            </h2>
            <p style="margin:0 0 20px; font-size:14px; color:#64748b;">
                <?= $introText ?? 'An escalation rule has been triggered for a ticket that requires your attention.' ?>
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">
                <tr>
                    <td style="padding:8px 16px; background:#f8fafc; font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid #e2e8f0;">
                        Ticket Details
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px;">
                        <table width="100%" cellpadding="0" cellspacing="4">
                            <tr>
                                <td style="font-size:13px; color:#64748b; width:140px; padding:2px 0;">Ticket</td>
                                <td style="font-size:13px; color:#1e293b; font-weight:600;">[#<?= (int) $ticketId ?>] <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr>
                                <td style="font-size:13px; color:#64748b; padding:2px 0;">Escalation Rule</td>
                                <td style="font-size:13px; color:#1e293b;"><?= htmlspecialchars($ruleName, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table cellpadding="0" cellspacing="0">
                <tr>
                    <td style="border-radius:6px; background:<?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8') ?>;">
                        <a href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>"
                           style="display:inline-block; padding:10px 24px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600;">
                            <?= $buttonLabel ?? 'View Ticket' ?>
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
