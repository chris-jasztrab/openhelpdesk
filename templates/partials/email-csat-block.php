<?php
/**
 * The survey call-to-action inside an email — shared by the standalone survey
 * email (csat-survey) and the closed/resolved notice that carries the survey
 * (ticket-status-changed). Expects $ticketId, $surveyUrl, $reopenUrl, $showReopen.
 */
$brandColor = $brandColor ?? getSetting('branding_primary_color', '#4f46e5');
?>
            <?php if (!empty($showReopen)): ?>
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
            <?php else: ?>
            <!-- Single CTA: rate your experience -->
            <div style="background:#f8fafc; border-radius:12px; padding:24px; margin-bottom:24px; border:1px solid #e2e8f0; text-align:center;">
                <h2 style="margin:0 0 8px; font-size:17px; color:#1e293b; font-weight:700;">
                    How did we do? ⭐
                </h2>
                <p style="margin:0 0 20px; font-size:13px; color:#64748b;">
                    Ticket <strong>#<?= (int) $ticketId ?></strong>
                </p>
                <a href="<?= htmlspecialchars($surveyUrl, ENT_QUOTES, 'UTF-8') ?>"
                   style="display:inline-block; padding:12px 28px; background:<?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8') ?>; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; color:#ffffff;">
                    Rate your experience
                </a>
            </div>
            <?php endif; ?>
