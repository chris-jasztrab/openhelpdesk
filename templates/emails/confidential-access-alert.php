<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.1);">

    <!-- Header — amber/warning colour for security notice -->
    <tr>
        <td style="background:linear-gradient(135deg,#92400e,#b45309); padding:20px 32px;">
            <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:700;">OpenHelpDesk — Security Notice</h1>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px;">
            <h2 style="margin:0 0 8px; font-size:18px; color:#1e293b;">[Ticket #<?= (int) $ticketId ?>] <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></h2>
            <p style="margin:0 0 24px; font-size:14px; color:#64748b;"><?= $introText ?? 'A confidential ticket was accessed by an administrator.' ?></p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td style="padding:8px 12px; background:#fef3c7; border:1px solid #fbbf24; border-bottom:none; border-radius:8px 8px 0 0; font-size:13px; color:#92400e; font-weight:600;">Access Details</td>
                </tr>
                <tr>
                    <td style="padding:16px; border:1px solid #fbbf24; border-radius:0 0 8px 8px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#334155;">
                            <tr>
                                <td style="padding:4px 0; width:120px; color:#64748b; vertical-align:top;">Admin Name</td>
                                <td style="padding:4px 0; font-weight:600;"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;">Admin Email</td>
                                <td style="padding:4px 0;"><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;">IP Address</td>
                                <td style="padding:4px 0;"><?= htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;">Timestamp</td>
                                <td style="padding:4px 0;"><?= htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php if (!empty($typeName)): ?>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;">Ticket Type</td>
                                <td style="padding:4px 0;"><?= htmlspecialchars($typeName, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </td>
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
