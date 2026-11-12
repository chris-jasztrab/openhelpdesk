<?php
/**
 * New Ticket forms (staff + portal) opened for one device — from the asset
 * page's New ticket button or a scanned asset label. Carries the id in a
 * hidden field; the create handlers link it. Expects $linkAsset (array|null)
 * from Assets::forNewTicket().
 */
if (empty($linkAsset)) {
    return;
}
?>
<input type="hidden" name="asset_id" value="<?= (int) $linkAsset['id'] ?>">
<div class="alert alert-secondary d-flex align-items-center gap-2 py-2 px-3 mb-3 small" id="ticketCreateAsset">
    <i class="bi bi-pc-display flex-shrink-0"></i>
    <span>This ticket will be linked to device <span class="font-monospace fw-semibold"><?= e($linkAsset['asset_tag']) ?></span> (<?= e($linkAsset['model_name']) ?>).</span>
</div>
