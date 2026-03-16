<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-6px); }
}
@keyframes wobble {
    0%, 100% { transform: rotate(0deg); }
    25%       { transform: rotate(-8deg); }
    75%       { transform: rotate(8deg); }
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50%       { transform: scale(1.15); }
}
.emoji-1 { display:inline-block; animation: wobble 1.8s ease-in-out infinite; }
.emoji-2 { display:inline-block; animation: bounce 2s ease-in-out infinite 0.2s; }
.emoji-3 { display:inline-block; animation: pulse 2.2s ease-in-out infinite 0.4s; }
.emoji-4 { display:inline-block; animation: bounce 1.9s ease-in-out infinite 0.1s; }
.emoji-5 { display:inline-block; animation: pulse 1.7s ease-in-out infinite 0.3s; }
</style>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,.12);">

    <!-- Header -->
    <tr>
        <td style="background:linear-gradient(135deg,#7c3aed,#4f46e5,#06b6d4); padding:24px 32px;">
            <h1 style="margin:0; color:#ffffff; font-size:20px; font-weight:700;"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
        </td>
    </tr>

    <!-- Body -->
    <tr>
        <td style="padding:32px;">
            <p style="margin:0 0 4px; font-size:14px; color:#64748b;">Hi <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>,</p>
            <p style="margin:0 0 28px; font-size:14px; color:#64748b;">
                <?= $introText ?>
            </p>

            <!-- Step 1: Was your issue resolved? -->
            <div style="background:#f8fafc; border-radius:12px; padding:24px; margin-bottom:24px; border:1px solid #e2e8f0;">
                <h2 style="margin:0 0 8px; font-size:17px; color:#1e293b; font-weight:700;">
                    Was your issue resolved? 🤔
                </h2>
                <p style="margin:0 0 20px; font-size:13px; color:#64748b;">
                    Ticket <strong>#<?= (int) $ticketId ?></strong>
                </p>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding-right:12px;">
                            <a href="<?= htmlspecialchars($reopenUrl, ENT_QUOTES, 'UTF-8') ?>"
                               style="display:inline-block; padding:11px 24px; background:#fee2e2; border:2px solid #fca5a5; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; color:#dc2626;">
                                😞 No, please reopen it
                            </a>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($surveyUrl, ENT_QUOTES, 'UTF-8') ?>"
                               style="display:inline-block; padding:11px 24px; background:#dcfce7; border:2px solid #86efac; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; color:#16a34a;">
                                ✅ Yes, it was resolved!
                            </a>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Step 2: Rate your experience -->
            <div style="background:#faf5ff; border-radius:12px; padding:24px; border:1px solid #e9d5ff;">
                <h2 style="margin:0 0 6px; font-size:17px; color:#1e293b; font-weight:700;">
                    How was your experience? ✨
                </h2>
                <p style="margin:0 0 20px; font-size:13px; color:#64748b;">Click an emoji to rate us — takes one second!</p>

                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <?php
                        $emojis = [
                            1 => ['face' => '😣', 'label' => 'Terrible',  'bg' => '#fee2e2', 'border' => '#fca5a5', 'color' => '#dc2626', 'anim' => 'wobble'],
                            2 => ['face' => '😕', 'label' => 'Poor',      'bg' => '#fff7ed', 'border' => '#fdba74', 'color' => '#ea580c', 'anim' => 'bounce'],
                            3 => ['face' => '😐', 'label' => 'Okay',      'bg' => '#fefce8', 'border' => '#fde047', 'color' => '#ca8a04', 'anim' => 'pulse'],
                            4 => ['face' => '😊', 'label' => 'Good',      'bg' => '#f0fdf4', 'border' => '#86efac', 'color' => '#16a34a', 'anim' => 'bounce'],
                            5 => ['face' => '😄', 'label' => 'Excellent', 'bg' => '#eff6ff', 'border' => '#93c5fd', 'color' => '#2563eb', 'anim' => 'pulse'],
                        ];
                        foreach ($emojis as $value => $emoji):
                        ?>
                        <td style="padding:0 6px 0 0;">
                            <a href="<?= htmlspecialchars($surveyUrl, ENT_QUOTES, 'UTF-8') ?>?r=<?= $value ?>"
                               style="display:inline-block; padding:12px 10px; background:<?= $emoji['bg'] ?>; border:2px solid <?= $emoji['border'] ?>; border-radius:12px; text-decoration:none; text-align:center; min-width:76px;">
                                <span class="emoji-<?= $value ?>" style="display:block; font-size:32px; line-height:1; margin-bottom:6px;"><?= $emoji['face'] ?></span>
                                <span style="display:block; font-size:11px; font-weight:700; color:<?= $emoji['color'] ?>;"><?= $emoji['label'] ?></span>
                            </a>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </table>

                <p style="margin:16px 0 0; font-size:12px; color:#94a3b8;">
                    You can also leave an optional comment after clicking.
                </p>
            </div>
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
