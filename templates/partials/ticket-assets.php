<?php
/**
 * Linked assets on the ticket page (agent + admin views). Shown only to staff
 * who can view the inventory. Lists the assets linked to this ticket, offers
 * the requester's own checked-out devices as one-click links, and a typeahead
 * over the whole inventory. Expects $ticket.
 */
if (!Assets::canView() || !Auth::isStaff()) {
    return;
}
$_adb = Database::connect();
$_tid = (int) $ticket['id'];
$_rid = (int) ($ticket['created_by'] ?? 0);

$_stmt = $_adb->prepare(
    'SELECT a.id, a.asset_tag, a.name, a.serial, a.assigned_type, a.assigned_id, a.import_assignee, a.deleted_at,
            m.name AS model_name, s.name AS status_name, s.kind AS status_kind, s.color AS status_color,
            CONCAT(u.first_name, " ", u.last_name) AS assigned_user_name, al.name AS assigned_location_name, pa.asset_tag AS assigned_asset_tag
     FROM ticket_asset_map tam
     JOIN assets a ON a.id = tam.asset_id
     JOIN asset_models m ON m.id = a.model_id
     JOIN asset_statuses s ON s.id = a.status_id
     LEFT JOIN users u ON a.assigned_type = "user" AND u.id = a.assigned_id
     LEFT JOIN locations al ON a.assigned_type = "location" AND al.id = a.assigned_id
     LEFT JOIN assets pa ON a.assigned_type = "asset" AND pa.id = a.assigned_id
     WHERE tam.ticket_id = ? ORDER BY a.asset_tag'
);
$_stmt->execute([$_tid]);
$_linked = $_stmt->fetchAll();
$_linkedIds = array_map(static fn(array $a): int => (int) $a['id'], $_linked);

$_mine = [];
if ($_rid > 0) {
    $_stmt = $_adb->prepare(
        'SELECT a.id, a.asset_tag, a.name, m.name AS model_name
         FROM assets a JOIN asset_models m ON m.id = a.model_id
         WHERE a.assigned_type = "user" AND a.assigned_id = ? AND a.deleted_at IS NULL ORDER BY a.asset_tag'
    );
    $_stmt->execute([$_rid]);
    $_mine = array_values(array_filter($_stmt->fetchAll(), static fn(array $a): bool => !in_array((int) $a['id'], $_linkedIds, true)));
}
?>
<div class="card border-0 shadow-sm mb-4" id="ticketAssetsCard">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center gap-2">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-pc-display me-2"></i>Assets</h5>
        <?php if ($_linked): ?><span class="badge text-bg-light"><?= count($_linked) ?></span><?php endif; ?>
    </div>
    <?php if ($_linked): ?>
    <ul class="list-group list-group-flush small">
        <?php foreach ($_linked as $a): ?>
        <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <a href="/inventory/<?= (int) $a['id'] ?>" class="fw-medium text-decoration-none font-monospace"><?= e($a['asset_tag']) ?></a>
                    <span class="text-break"><?= e($a['model_name']) ?><?= $a['name'] ? ' (' . e($a['name']) . ')' : '' ?></span>
                    <?php if ($a['deleted_at']): ?><span class="badge bg-danger ms-1">Deleted</span><?php endif; ?>
                    <div class="text-muted"><?= Assets::assigneeHtml($a) ?><?= $a['serial'] ? ' · <span class="font-monospace">' . e($a['serial']) . '</span>' : '' ?></div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <?= Assets::statusBadgeHtml($a) ?>
                    <form method="POST" action="/tickets/<?= $_tid ?>/assets/<?= (int) $a['id'] ?>/remove" class="d-inline" onsubmit="return confirm('Unlink <?= e($a['asset_tag']) ?> from this ticket?');">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Unlink" aria-label="Unlink <?= e($a['asset_tag']) ?>"><i class="bi bi-x-lg"></i></button>
                    </form>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="card-body small">
        <?php if ($_mine): ?>
        <div class="text-muted mb-1">Requester's devices</div>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <?php foreach ($_mine as $a): ?>
            <form method="POST" action="/tickets/<?= $_tid ?>/assets" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="asset_id" value="<?= (int) $a['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Link <?= e($a['model_name']) ?> to this ticket">
                    <i class="bi bi-plus-lg me-1"></i><span class="font-monospace"><?= e($a['asset_tag']) ?></span> <?= e($a['name'] ?: $a['model_name']) ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php elseif (!$_linked): ?>
        <div class="text-muted mb-2">No assets linked. <?= $_rid > 0 ? 'The requester has no devices checked out.' : '' ?></div>
        <?php endif; ?>
        <form method="POST" action="/tickets/<?= $_tid ?>/assets" id="ticketAssetLinkForm" class="position-relative" autocomplete="off">
            <?= csrfField() ?>
            <input type="hidden" name="asset_id" id="ticketAssetId" value="">
            <div class="position-relative">
                <i class="bi bi-search position-absolute text-muted" style="left:.6rem;top:50%;transform:translateY(-50%);font-size:.8rem;pointer-events:none;"></i>
                <input type="search" class="form-control form-control-sm" id="ticketAssetSearch" placeholder="Link an asset: tag, name, serial, model…" aria-label="Search assets to link" style="padding-left:1.9rem;">
            </div>
            <div id="ticketAssetResults" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1030;max-height:260px;overflow-y:auto;"></div>
        </form>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('ticketAssetSearch');
    var list = document.getElementById('ticketAssetResults');
    var form = document.getElementById('ticketAssetLinkForm');
    var idField = document.getElementById('ticketAssetId');
    if (!box) return;
    var linked = <?= json_encode($_linkedIds) ?>;
    var timer = null, inflight = null;
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function hide() { list.classList.add('d-none'); list.innerHTML = ''; }
    function render(items) {
        items = items.filter(function (a) { return linked.indexOf(a.id) === -1; });
        if (!items.length) { list.innerHTML = '<div class="list-group-item text-muted small">No matching assets.</div>'; list.classList.remove('d-none'); return; }
        list.innerHTML = items.map(function (a) {
            return '<button type="button" class="list-group-item list-group-item-action small d-flex justify-content-between align-items-center gap-2" data-id="' + a.id + '">' +
                '<span class="text-truncate"><span class="font-monospace fw-semibold">' + esc(a.tag) + '</span> ' + esc(a.name || a.model) +
                (a.name ? ' <span class="text-muted">' + esc(a.model) + '</span>' : '') +
                (a.assigned ? ' <span class="text-muted">· ' + esc(a.assigned) + '</span>' : '') + '</span>' +
                '<span class="badge flex-shrink-0" style="background:' + esc(a.color) + ';">' + esc(a.status) + '</span></button>';
        }).join('');
        list.classList.remove('d-none');
    }
    box.addEventListener('input', function () {
        clearTimeout(timer);
        var q = box.value.trim();
        if (q.length < 2) { hide(); return; }
        timer = setTimeout(function () {
            if (inflight) inflight.abort();
            inflight = new AbortController();
            fetch('/inventory/search?q=' + encodeURIComponent(q) + '&user_id=<?= $_rid ?>', { credentials: 'same-origin', signal: inflight.signal })
                .then(function (r) { return r.json(); }).then(render).catch(function () {});
        }, 250);
    });
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-id]');
        if (!btn) return;
        idField.value = btn.getAttribute('data-id');
        form.submit();
    });
    box.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); if (e.key === 'Escape') hide(); });
    document.addEventListener('click', function (e) { if (!form.contains(e.target)) hide(); });
})();
</script>
