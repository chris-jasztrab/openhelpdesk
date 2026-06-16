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
            <p style="margin:0 0 24px; font-size:14px; color:#64748b;"><?= $introText ?? '' ?></p>

            <?php if (!empty($forwardNote)): ?>
            <!-- Agent's forward note -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                    <td style="padding:8px 12px; background:#eef2ff; border:1px solid #c7d2fe; border-bottom:none; border-radius:8px 8px 0 0; font-size:13px; color:#3730a3; font-weight:600;">
                        Note from <?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px; border:1px solid #c7d2fe; border-radius:0 0 8px 8px; font-size:14px; color:#334155; line-height:1.6;"><?= emailContent($forwardNote) ?></td>
                </tr>
            </table>
            <?php endif; ?>

            <!-- Ticket details -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px; border:1px solid #e2e8f0; border-radius:8px;">
                <tr><td style="padding:8px 12px; background:#f8fafc; border-bottom:1px solid #e2e8f0; border-radius:8px 8px 0 0; font-size:13px; color:#64748b; font-weight:600;">Ticket Details</td></tr>
                <?php
                $rows = [
                    'Requester' => $requesterName ?? '',
                    'Status'    => $statusLabel ?? '',
                    'Type'      => $typeName ?? '',
                    'Priority'  => $priorityName ?? '',
                    'Location'  => $locationName ?? '',
                    'Opened'    => $openedAt ?? '',
                ];
                foreach ($rows as $label => $value):
                    if (trim((string) $value) === '') continue;
                ?>
                <tr>
                    <td style="padding:8px 12px; font-size:13px; color:#334155; border-bottom:1px solid #f1f5f9;">
                        <span style="color:#94a3b8;"><?= $label ?>:</span>
                        <strong><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <!-- Conversation thread -->
            <h3 style="margin:0 0 12px; font-size:14px; color:#1e293b;">Conversation</h3>
            <?php foreach (($thread ?? []) as $entry): ?>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                <tr>
                    <td style="padding:8px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-bottom:none; border-radius:8px 8px 0 0; font-size:13px; color:#64748b; font-weight:600;">
                        <?= htmlspecialchars($entry['author'], ENT_QUOTES, 'UTF-8') ?>
                        <span style="font-weight:400; color:#94a3b8;">&middot; <?= htmlspecialchars($entry['date'], ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px; border:1px solid #e2e8f0; border-radius:0 0 8px 8px; font-size:14px; color:#334155; line-height:1.6;"><?= emailContent($entry['message']) ?></td>
                </tr>
            </table>
            <?php endforeach; ?>

            <?php if (!empty($attachmentNote)): ?>
            <p style="margin:16px 0 0; font-size:13px; color:#64748b;"><i><?= htmlspecialchars($attachmentNote, ENT_QUOTES, 'UTF-8') ?></i></p>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="padding:16px 32px; background:#f8fafc; border-top:1px solid #e2e8f0;">
            <p style="margin:0; font-size:12px; color:#94a3b8; text-align:center;">
                <?= $footerText ?? 'Reply to this email to add to the ticket.' ?>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
