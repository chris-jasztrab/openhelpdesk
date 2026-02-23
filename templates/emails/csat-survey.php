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
            <h2 style="margin:0 0 8px; font-size:18px; color:#1e293b;">How did we do?</h2>
            <p style="margin:0 0 4px; font-size:14px; color:#64748b;">Hi <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>,</p>
            <p style="margin:0 0 24px; font-size:14px; color:#64748b;">
                Your ticket <strong>[#<?= (int) $ticketId ?>] <?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></strong>
                has been resolved. We'd love to hear how we did — it only takes one click!
            </p>

            <!-- Star rating buttons -->
            <p style="margin:0 0 12px; font-size:14px; color:#334155; font-weight:600;">Rate your experience:</p>
            <table cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                <tr>
                    <?php
                    $stars = ['1' => '⭐', '2' => '⭐⭐', '3' => '⭐⭐⭐', '4' => '⭐⭐⭐⭐', '5' => '⭐⭐⭐⭐⭐'];
                    $labels = ['1' => 'Poor', '2' => 'Fair', '3' => 'Good', '4' => 'Great', '5' => 'Excellent'];
                    foreach ($stars as $value => $star):
                    ?>
                    <td style="padding:0 4px 0 0;">
                        <a href="<?= htmlspecialchars($surveyUrl, ENT_QUOTES, 'UTF-8') ?>?r=<?= $value ?>"
                           style="display:inline-block; padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; text-align:center; min-width:64px;">
                            <span style="display:block; font-size:20px; line-height:1;"><?= $value ?>★</span>
                            <span style="display:block; font-size:11px; color:#64748b; margin-top:4px;"><?= $labels[$value] ?></span>
                        </a>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </table>

            <p style="margin:0; font-size:13px; color:#94a3b8;">
                You can also leave an optional comment after clicking a rating.
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
