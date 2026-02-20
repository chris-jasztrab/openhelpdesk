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
            <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:700;">LocalDesk</h1>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px;">
            <h2 style="margin:0 0 8px; font-size:18px; color:#1e293b;">Your ticket has been merged</h2>
            <p style="margin:0 0 24px; font-size:14px; color:#64748b;"><?= $introText ?? 'Ticket #' . (int) $sourceTicketId . ' has been consolidated with a related ticket. You can view updates and add comments on the master ticket.' ?></p>

            <!-- Source ticket -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                <tr>
                    <td style="padding:8px 12px; background:#fef9c3; border:1px solid #fde68a; border-bottom:none; border-radius:8px 8px 0 0; font-size:13px; color:#92400e; font-weight:600;">
                        Merged ticket (now closed)
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 16px; border:1px solid #fde68a; border-radius:0 0 8px 8px; font-size:14px; color:#334155;">
                        [Ticket #<?= (int) $sourceTicketId ?>] <?= htmlspecialchars($sourceSubject, ENT_QUOTES, 'UTF-8') ?>
                    </td>
                </tr>
            </table>

            <!-- Target ticket -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td style="padding:8px 12px; background:#f0fdf4; border:1px solid #86efac; border-bottom:none; border-radius:8px 8px 0 0; font-size:13px; color:#166534; font-weight:600;">
                        Master ticket (all updates will appear here)
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 16px; border:1px solid #86efac; border-radius:0 0 8px 8px; font-size:14px; color:#334155;">
                        [Ticket #<?= (int) $targetTicketId ?>] <?= htmlspecialchars($targetSubject, ENT_QUOTES, 'UTF-8') ?>
                    </td>
                </tr>
            </table>

            <table cellpadding="0" cellspacing="0">
                <tr>
                    <td style="border-radius:6px; background:<?= e(getSetting('branding_primary_color', '#4f46e5')) ?>;">
                        <a href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>"
                           style="display:inline-block; padding:10px 24px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600;">
                            <?= $buttonLabel ?? 'View Master Ticket' ?>
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
                <?= $footerText ?? 'This is an automated message from LocalDesk. Please do not reply directly to this email.' ?>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
