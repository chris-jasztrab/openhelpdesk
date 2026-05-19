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

    <!-- Body -->
    <tr>
        <td style="padding:32px;">
            <h2 style="margin:0 0 8px; font-size:18px; color:#1e293b;">
                Hi <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>,
            </h2>
            <p style="margin:0 0 16px; font-size:14px; color:#64748b; line-height:1.6;">
                We received a request to reset the password for your <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> account.
                Click the button below to choose a new one. This link expires in <strong><?= (int) $ttlMinutes ?> minutes</strong> and can only be used once.
            </p>

            <table cellpadding="0" cellspacing="0" style="margin:24px 0;">
                <tr>
                    <td style="border-radius:6px; background:<?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8') ?>;">
                        <a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>"
                           style="display:inline-block; padding:10px 24px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600;">
                            Reset my password
                        </a>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px; font-size:12px; color:#94a3b8; line-height:1.6;">
                If the button doesn&rsquo;t work, copy and paste this URL into your browser:
            </p>
            <p style="margin:0 0 24px; font-size:12px; color:#475569; word-break:break-all;">
                <a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>" style="color:#475569;"><?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?></a>
            </p>

            <p style="margin:0; font-size:13px; color:#64748b; line-height:1.6;">
                If you didn&rsquo;t request a password reset, you can safely ignore this email &mdash; your password will not change.
            </p>
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
