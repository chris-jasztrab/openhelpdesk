<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.1);">

    <!-- Header — red/danger colour for security alert -->
    <tr>
        <td style="background:linear-gradient(135deg,#991b1b,#dc2626); padding:20px 32px;">
            <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:700;">OpenHelpDesk — Security Alert</h1>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px;">
            <h2 style="margin:0 0 8px; font-size:18px; color:#1e293b;">Confidential <?= htmlspecialchars(ucfirst($targetType), ENT_QUOTES, 'UTF-8') ?> Deleted</h2>
            <p style="margin:0 0 24px; font-size:14px; color:#64748b;"><?= $introText ?? 'A confidential entity you were associated with has been deleted.' ?></p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td style="padding:8px 12px; background:#fee2e2; border:1px solid #fca5a5; border-bottom:none; border-radius:8px 8px 0 0; font-size:13px; color:#991b1b; font-weight:600;">Deletion Details</td>
                </tr>
                <tr>
                    <td style="padding:16px; border:1px solid #fca5a5; border-radius:0 0 8px 8px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#334155;">
                            <tr>
                                <td style="padding:4px 0; width:140px; color:#64748b; vertical-align:top;">Action</td>
                                <td style="padding:4px 0; font-weight:600; color:#dc2626;">Deleted</td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;"><?= htmlspecialchars(ucfirst($targetType), ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="padding:4px 0; font-weight:600;"><?= htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;">Deleted By</td>
                                <td style="padding:4px 0; font-weight:600;"><?= htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;">Email</td>
                                <td style="padding:4px 0;"><?= htmlspecialchars($actorEmail, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;">IP Address</td>
                                <td style="padding:4px 0;"><?= htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; color:#64748b; vertical-align:top;">Timestamp</td>
                                <td style="padding:4px 0;"><?= htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div style="padding:12px 16px; background:#fef3c7; border:1px solid #fbbf24; border-radius:8px; margin-bottom:24px; font-size:13px; color:#92400e;">
                <strong>What this means:</strong> The confidential <?= htmlspecialchars($targetType, ENT_QUOTES, 'UTF-8') ?> "<?= htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') ?>" and all associated security protections have been permanently removed.
            </div>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="padding:16px 32px; background:#f8fafc; border-top:1px solid #e2e8f0;">
            <p style="margin:0; font-size:12px; color:#94a3b8; text-align:center;">
                <?= $footerText ?: 'This is an automated message from OpenHelpDesk. Please do not reply directly to this email.' ?>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
