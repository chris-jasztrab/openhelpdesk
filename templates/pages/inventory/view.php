<?php
$layout       = 'app';
$pageTitle    = $asset['asset_tag'] . ' – ' . label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory'), 'url' => '/inventory'],
    ['label' => $asset['asset_tag']],
];
$tabs = ['tickets' => 'Tickets', 'details' => 'Details', 'history' => 'History', 'files' => 'Files'];
if (!isset($tabs[$tab])) {
    $tab = 'tickets';
}
$when = static fn(?string $ts): string => $ts ? date('M j, Y g:i A', strtotime($ts)) : '—';
$day  = static fn(?string $d): string => $d ? date('M j, Y', strtotime($d)) : '—';
$money = static fn($v): string => $v !== null && $v !== '' ? '$' . number_format((float) $v, 2) : '—';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <?php if ($asset['image']): ?>
                    <img src="/inventory/<?= (int) $asset['id'] ?>/image" alt="" class="rounded border" width="72" height="72" style="object-fit:contain;">
                <?php elseif ($asset['model_image']): ?>
                    <img src="/inventory/models/<?= (int) $asset['model_id'] ?>/image" alt="" class="rounded border" width="72" height="72" style="object-fit:contain;">
                <?php else: ?>
                    <div class="rounded bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:72px;height:72px;font-size:1.8rem;">
                        <i class="bi bi-pc-display"></i>
                    </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <h3 class="fw-bold mb-1 font-monospace"><?= e($asset['asset_tag']) ?>
                        <?php if ($asset['name']): ?><span class="fw-normal text-muted fs-5">— <?= e($asset['name']) ?></span><?php endif; ?>
                    </h3>
                    <div class="text-muted mb-2">
                        <?= e($asset['model_name']) ?><?= $asset['manufacturer_name'] ? ' · ' . e($asset['manufacturer_name']) : '' ?> · <?= e($asset['category_name']) ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?= Assets::statusBadgeHtml($asset) ?>
                        <?php if ($asset['deleted_at']): ?>
                        <span class="badge bg-danger">Deleted <?= e($day($asset['deleted_at'])) ?></span>
                        <?php endif; ?>
                        <span class="text-muted small"><i class="bi bi-person me-1"></i><?= Assets::assigneeHtml($asset) ?></span>
                        <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e($asset['location_name'] ?: 'No ' . label('location.singular')) ?></span>
                    </div>
                </div>
            </div>
            <div class="text-muted small text-lg-end">
                <?php if (!$asset['deleted_at']): ?>
                <a href="<?= e(Assets::newTicketUrl((int) $asset['id'])) ?>" class="btn btn-sm btn-outline-secondary mb-2" id="assetNewTicket"><i class="bi bi-plus-lg me-1"></i>New ticket</a>
                <?php endif; ?>
                <?php if ($asset['serial']): ?><div>Serial <span class="font-monospace text-dark"><?= e($asset['serial']) ?></span></div><?php endif; ?>
                <?php if ($asset['snipeit_id']): ?><div>Snipe-IT #<?= (int) $asset['snipeit_id'] ?></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (Assets::canManage()): $checkedOut = $asset['assigned_type'] !== null || $asset['import_assignee'] !== null; ?>
<div class="d-flex flex-wrap gap-2 mb-3" id="assetActions">
    <?php if (!$asset['deleted_at']): ?>
    <a href="/inventory/<?= (int) $asset['id'] ?>/edit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
    <a href="/inventory/labels?ids=<?= (int) $asset['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Print a QR code label for this asset"><i class="bi bi-qr-code me-1"></i>Print label</a>
    <?php if ($checkedOut): ?>
    <form method="POST" action="/inventory/<?= (int) $asset['id'] ?>/checkin" class="d-inline" onsubmit="return confirm('Check this asset in?');">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-in-left me-1"></i>Check in</button>
    </form>
    <?php else: ?>
    <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" onclick="document.getElementById('assetCheckout').classList.toggle('d-none');"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
    <?php endif; ?>
    <form method="POST" action="/inventory/<?= (int) $asset['id'] ?>/delete" class="d-inline ms-auto" onsubmit="return confirm('Delete <?= e($asset['asset_tag']) ?>? It can be restored later.');">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
    </form>
    <?php else: ?>
    <form method="POST" action="/inventory/<?= (int) $asset['id'] ?>/restore" class="d-inline">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button>
    </form>
    <?php endif; ?>
</div>
<?php if (!$asset['deleted_at'] && !$checkedOut): ?>
<div class="card border-0 shadow-sm mb-3 d-none" id="assetCheckout">
    <div class="card-body p-3">
        <form method="POST" action="/inventory/<?= (int) $asset['id'] ?>/checkout" class="row g-2 align-items-end">
            <?= csrfField() ?>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1" for="coType">Check out to</label>
                <select class="form-select form-select-sm" name="assigned_type" id="coType" onchange="document.querySelectorAll('[data-co]').forEach(function(el){el.classList.toggle('d-none', el.getAttribute('data-co')!==this.value);}.bind(this));">
                    <option value="user">A person</option>
                    <option value="location">A <?= label('location.singular') ?></option>
                    <option value="asset">Another asset</option>
                </select>
            </div>
            <div class="col-sm-4" data-co="user">
                <label class="form-label small fw-semibold mb-1" for="coUser">Person</label>
                <select class="form-select form-select-sm" name="user_id" id="coUser">
                    <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?> — <?= e($u['email']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 d-none" data-co="location">
                <label class="form-label small fw-semibold mb-1" for="coLoc"><?= label('location.singular') ?></label>
                <select class="form-select form-select-sm" name="location_id" id="coLoc">
                    <?php foreach ($locations as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 d-none" data-co="asset">
                <label class="form-label small fw-semibold mb-1" for="coAsset">Asset tag</label>
                <input type="text" class="form-control form-control-sm font-monospace" name="asset_tag" id="coAsset" placeholder="e.g. 00123">
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1" for="coNote">Note</label>
                <input type="text" class="form-control form-control-sm" name="note" id="coNote">
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-sm text-white w-100" style="background:var(--ld-primary);">Check out</button>
            </div>
        </form>
    </div>
</div>
<?php endif; endif; ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => $labelText): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="/inventory/<?= (int) $asset['id'] ?>?tab=<?= $key ?>">
            <?= $labelText ?>
            <?php if ($key === 'tickets'): ?><span class="badge bg-secondary ms-1"><?= count($tickets) ?></span><?php endif; ?>
            <?php if ($key === 'files' && $files): ?><span class="badge bg-secondary ms-1"><?= count($files) ?></span><?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'tickets'): ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:70px">#</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Requester</th>
                    <th>Assigned to</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tickets): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No tickets have been linked to this asset yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $t): ?>
                <tr style="cursor:pointer;" onclick="window.location='<?= e(staffTicketUrl((int) $t['id'])) ?>'">
                    <td class="text-muted">#<?= (int) $t['id'] ?></td>
                    <td class="fw-semibold"><a href="<?= e(staffTicketUrl((int) $t['id'])) ?>" class="text-decoration-none text-dark"><?= e($t['subject']) ?></a>
                        <?php if ($t['type_name']): ?><div class="text-muted small fw-normal"><?= e($t['type_name']) ?></div><?php endif; ?></td>
                    <td><?= ticketStatusBadgeHtml((string) $t['status']) ?></td>
                    <td><?= e($t['requester_name'] ?: '—') ?></td>
                    <td><?= e($t['agent_name'] ?: 'Unassigned') ?></td>
                    <td class="text-muted small text-nowrap"><?= e($day($t['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($hiddenTickets > 0): ?>
    <div class="card-footer bg-white text-muted small">
        <i class="bi bi-eye-slash me-1"></i><?= $hiddenTickets ?> linked ticket<?= $hiddenTickets === 1 ? '' : 's' ?> you don't have access to <?= $hiddenTickets === 1 ? 'is' : 'are' ?> not shown.
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'details'): ?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Identity</h6>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Asset tag</dt><dd class="col-7 font-monospace"><?= e($asset['asset_tag']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Name</dt><dd class="col-7"><?= e($asset['name'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Serial</dt><dd class="col-7 font-monospace"><?= e($asset['serial'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Model</dt><dd class="col-7"><?= e($asset['model_name']) ?><?= $asset['model_number'] ? ' <span class="text-muted">(' . e($asset['model_number']) . ')</span>' : '' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Manufacturer</dt><dd class="col-7"><?= e($asset['manufacturer_name'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Category</dt><dd class="col-7"><?= e($asset['category_name']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Status</dt><dd class="col-7"><?= e(Assets::statusLabel($asset)) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Assigned to</dt><dd class="col-7"><?= Assets::assigneeHtml($asset) ?></dd>
                    <dt class="col-5 text-muted fw-normal"><?= label('location.singular') ?></dt><dd class="col-7"><?= e($asset['location_name'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Default <?= label('location.singular') ?></dt><dd class="col-7"><?= e($asset['default_location_name'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Purchase &amp; lifecycle</h6>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Supplier</dt><dd class="col-7"><?= e($asset['supplier_name'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Order number</dt><dd class="col-7"><?= e($asset['order_number'] ?: '—') ?></dd>
                    <dt class="col-5 text-muted fw-normal">Purchase date</dt><dd class="col-7"><?= e($day($asset['purchase_date'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Purchase cost</dt><dd class="col-7"><?= e($money($asset['purchase_cost'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Warranty</dt>
                    <dd class="col-7" id="assetWarranty">
                        <?php if ($asset['warranty_end']): $left = (int) round((strtotime($asset['warranty_end']) - strtotime(date('Y-m-d'))) / 86400); ?>
                            <?php if ($left < 0): ?>
                            <span class="badge bg-danger">Expired</span> <?= e($day($asset['warranty_end'])) ?> <span class="text-muted">(<?= -$left ?> day<?= $left === -1 ? '' : 's' ?> ago)</span>
                            <?php else: ?>
                            <span class="badge <?= $left <= 90 ? 'bg-warning text-dark' : 'bg-success' ?>">In warranty</span> until <?= e($day($asset['warranty_end'])) ?> <span class="text-muted">(<?= $left ?> day<?= $left === 1 ? '' : 's' ?> left)</span>
                            <?php endif; ?>
                            <?php if ($asset['warranty_start']): ?><div class="text-muted">Started <?= e($day($asset['warranty_start'])) ?></div><?php endif; ?>
                            <?php if ($asset['warranty_service']): ?><div class="text-muted"><?= e($asset['warranty_service']) ?></div><?php endif; ?>
                        <?php elseif ($asset['warranty_months'] !== null): ?>
                            <?= (int) $asset['warranty_months'] ?> months
                        <?php else: ?>—<?php endif; ?>
                        <?php if ($asset['warranty_error']): ?><div class="text-muted"><i class="bi bi-exclamation-triangle text-warning me-1"></i><?= e($asset['warranty_error']) ?></div><?php endif; ?>
                        <?php if ($asset['warranty_checked_at']): ?><div class="text-muted">Looked up from <?= e(ucfirst((string) Warranty::vendor($asset['manufacturer_name']))) ?> <?= e($day($asset['warranty_checked_at'])) ?></div><?php endif; ?>
                        <?php if (Assets::canManage() && !$asset['deleted_at'] && Warranty::canLookUp($asset['manufacturer_name'], $asset['serial'])): ?>
                        <form method="POST" action="/inventory/<?= (int) $asset['id'] ?>/warranty" class="mt-1">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-arrow-repeat me-1"></i>Look up warranty</button>
                        </form>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 text-muted fw-normal">End of life</dt><dd class="col-7"><?= e($day($asset['eol_date'])) ?><?= !$asset['eol_date'] && $asset['eol_months'] ? ' <span class="text-muted">(model: ' . (int) $asset['eol_months'] . ' months)</span>' : '' ?></dd>
                    <dt class="col-5 text-muted fw-normal">Last checkout</dt><dd class="col-7"><?= e($when($asset['last_checkout_at'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Last check-in</dt><dd class="col-7"><?= e($when($asset['last_checkin_at'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Last audit</dt><dd class="col-7"><?= e($when($asset['last_audit_at'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Next audit</dt><dd class="col-7"><?= e($day($asset['next_audit_date'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Created</dt><dd class="col-7"><?= e($when($asset['created_at'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Updated</dt><dd class="col-7"><?= e($when($asset['updated_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <?php if ($asset['notes'] || $asset['import_assignee']): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-2">Notes</h6>
                <?php if ($asset['import_assignee']): ?>
                <p class="text-muted small mb-2"><i class="bi bi-box-arrow-in-down me-1"></i>Snipe-IT had this checked out to <strong><?= e($asset['import_assignee']) ?></strong>, which has no helpdesk account.</p>
                <?php endif; ?>
                <?php if ($asset['notes']): ?><div class="small" style="white-space:pre-wrap;"><?= e($asset['notes']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($children): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-2">Assets checked out to this one</h6>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($children as $c): ?>
                    <li><a href="/inventory/<?= (int) $c['id'] ?>" class="text-decoration-none font-monospace"><?= e($c['asset_tag']) ?></a> — <?= e($c['model_name']) ?><?= $c['name'] ? ' (' . e($c['name']) . ')' : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'history'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (!$history): ?>
        <div class="text-center py-4 text-muted">No history yet.</div>
        <?php endif; ?>
        <?php foreach ($history as $h): $d = $h['details'] ? (json_decode((string) $h['details'], true) ?: []) : []; ?>
        <div class="d-flex gap-3 px-4 py-3 border-bottom">
            <div class="text-muted" style="width:1.5rem;"><i class="bi <?= Assets::historyIcon((string) $h['action']) ?>"></i></div>
            <div class="flex-grow-1 min-w-0 small">
                <div><?= Assets::historyText((string) $h['action'], $d) ?></div>
                <?php if (!empty($d['changes']) && is_array($d['changes'])): ?>
                <ul class="mb-0 mt-1 ps-3 text-muted">
                    <?php foreach ($d['changes'] as $field => $chg): if (!is_array($chg)) continue; ?>
                    <li><span class="fw-semibold"><?= e(str_replace('_', ' ', (string) $field)) ?></span>:
                        <?= e(is_scalar($chg['old'] ?? null) ? (string) $chg['old'] : (($chg['old'] ?? null) === null ? '—' : json_encode($chg['old']))) ?>
                        <i class="bi bi-arrow-right mx-1"></i>
                        <?= e(is_scalar($chg['new'] ?? null) ? (string) $chg['new'] : (($chg['new'] ?? null) === null ? '—' : json_encode($chg['new']))) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if (!empty($d['note'])): ?><div class="text-muted fst-italic mt-1"><?= e((string) $d['note']) ?></div><?php endif; ?>
            </div>
            <div class="text-muted small text-end text-nowrap">
                <div><?= e($when($h['created_at'])) ?></div>
                <div><?= e($h['user_name'] ?: ($h['actor_name'] ?: '—')) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php else: ?>
<?php if (Assets::canManage() && !$asset['deleted_at']): ?>
<form method="POST" action="/inventory/<?= (int) $asset['id'] ?>/files" enctype="multipart/form-data" class="d-flex gap-2 align-items-center mb-3 flex-wrap">
    <?= csrfField() ?>
    <input type="file" class="form-control form-control-sm" name="file" required style="max-width:360px;" aria-label="File to attach">
    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-upload me-1"></i>Upload</button>
    <span class="text-muted small">Receipts, warranty documents, photos.</span>
</form>
<?php endif; ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>File</th><th>Size</th><th>Uploaded by</th><th>Uploaded</th><?php if (Assets::canManage()): ?><th style="width:60px"></th><?php endif; ?></tr>
            </thead>
            <tbody>
                <?php if (!$files): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No files attached.</td></tr>
                <?php endif; ?>
                <?php foreach ($files as $f): ?>
                <tr>
                    <td><a href="/inventory/<?= (int) $asset['id'] ?>/files/<?= (int) $f['id'] ?>" class="text-decoration-none"><i class="bi bi-paperclip me-1"></i><?= e($f['original_name']) ?></a></td>
                    <td class="text-muted small"><?= number_format((int) $f['file_size'] / 1024) ?> KB</td>
                    <td><?= e($f['uploader_name'] ?: '—') ?></td>
                    <td class="text-muted small"><?= e($when($f['created_at'])) ?></td>
                    <?php if (Assets::canManage()): ?>
                    <td>
                        <form method="POST" action="/inventory/<?= (int) $asset['id'] ?>/files/<?= (int) $f['id'] ?>/delete" onsubmit="return confirm('Remove <?= e(addslashes($f['original_name'])) ?>?');">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Remove file"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
