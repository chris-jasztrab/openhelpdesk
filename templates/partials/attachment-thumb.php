<?php
/**
 * Inline thumbnail for an image attachment.
 *
 * Screenshots are the bulk of helpdesk attachments, and every one of them used
 * to be a blind "file.png" download link — you had to fetch it to find out
 * whether it was the error dialog or a photo of a printer. This renders the
 * bytes we already serve, in place, so the answer is visible.
 *
 * Expects in scope:
 *   $att            attachment row (needs mime_type + original_name)
 *   $attUrl         panel-appropriate download URL for this attachment
 *   $attThumbMax    optional max height in px (default 96)
 *
 * Callers must gate on attachmentIsImage($att['mime_type']) — this partial
 * assumes that check has already passed.
 *
 * The download route still sends Content-Disposition: attachment; browsers apply
 * that to navigation, not to <img>, so no inline-disposition escape hatch (and
 * no new XSS surface) is needed. alt carries the filename, which also fixes the
 * accessibility gap the old icon-only links had.
 */
$attThumbMax = $attThumbMax ?? 96;
?>
<img src="<?= e($attUrl) ?>"
     alt="<?= e($att['original_name']) ?>"
     loading="lazy"
     class="rounded border bg-white"
     style="max-height:<?= (int) $attThumbMax ?>px;max-width:100%;object-fit:contain;">
