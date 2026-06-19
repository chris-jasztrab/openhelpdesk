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
            <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:700;">OpenHelpDesk</h1>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px;">
            <h2 style="margin:0 0 8px; font-size:18px; color:#1e293b;">[Ticket #<?= (int) $ticketId ?>] <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></h2>
            <div style="margin:0 0 24px; font-size:14px; color:#64748b;"><?= $introText ?? 'Your ticket has been updated.' ?></div>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td style="padding:8px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-bottom:none; border-radius:8px 8px 0 0; font-size:13px; color:#64748b; font-weight:600;">
                        Comment from <?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px; border:1px solid #e2e8f0; border-radius:0 0 8px 8px; font-size:14px; color:#334155; line-height:1.6;"><?= emailContent($message) ?></td>
                </tr>
            </table>

            <table cellpadding="0" cellspacing="0">
                <tr>
                    <td style="border-radius:6px; background:<?= e(getSetting('branding_primary_color', '#4f46e5')) ?>;">
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
                <?= $footerText ?? 'This is an automated message from OpenHelpDesk. Please do not reply directly to this email.' ?>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
