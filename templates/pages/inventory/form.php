<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = ($isEdit ? 'Edit ' . $editing['asset_tag'] : 'Add asset') . ' – ' . label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory'), 'url' => '/inventory'],
];
if ($isEdit) {
    $breadcrumbs[] = ['label' => $editing['asset_tag'], 'url' => '/inventory/' . (int) $editing['id']];
}
$breadcrumbs[] = ['label' => $isEdit ? 'Edit' : 'Add'];
$action = $isEdit ? '/inventory/' . (int) $editing['id'] . '/edit' : '/inventory/create';
$v = static fn(string $k, $d = '') => old($k, (string) ($editing[$k] ?? $d));
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit <span class="font-monospace">' . e($editing['asset_tag']) . '</span>' : 'Add asset' ?></h2>
    <a href="<?= $isEdit ? '/inventory/' . (int) $editing['id'] : '/inventory' ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Identity</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="asset_tag" class="form-label fw-semibold">Asset tag</label>
                            <input type="text" class="form-control font-monospace" id="asset_tag" name="asset_tag" value="<?= e($v('asset_tag')) ?>" <?= $isEdit ? '' : 'placeholder="' . e($nextTag) . ' (auto)"' ?>>
                            <?php if (!$isEdit): ?><div class="form-text">Leave blank to use the next number.</div><?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <label for="serial" class="form-label fw-semibold">Serial</label>
                            <input type="text" class="form-control font-monospace" id="serial" name="serial" value="<?= e($v('serial')) ?>">
                        </div>
                        <div class="col-12">
                            <label for="name" class="form-label fw-semibold">Name <span class="text-muted fw-normal">(hostname, label…)</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($v('name')) ?>">
                        </div>
                        <div class="col-12">
                            <label for="model_id" class="form-label fw-semibold">Model <span class="text-danger">*</span></label>
                            <select class="form-select" id="model_id" name="model_id" required>
                                <option value="">— choose —</option>
                                <?php $cat = null; foreach ($models as $m): ?>
                                    <?php if ($m['category_name'] !== $cat): if ($cat !== null) echo '</optgroup>'; $cat = $m['category_name']; ?><optgroup label="<?= e($cat) ?>"><?php endif; ?>
                                    <option value="<?= (int) $m['id'] ?>" <?= (int) $v('model_id') === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?><?= $m['manufacturer_name'] ? ' · ' . e($m['manufacturer_name']) : '' ?></option>
                                <?php endforeach; if ($cat !== null) echo '</optgroup>'; ?>
                            </select>
                            <div class="form-text">Missing a model? Add it under <a href="/admin/settings/inventory/models">Settings › Inventory</a>.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="status_id" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status_id" name="status_id" required>
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (int) $v('status_id', $statuses[0]['id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="image" class="form-label fw-semibold">Photo</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/png,image/jpeg,image/gif,image/webp">
                        </div>
                        <div class="col-sm-6">
                            <label for="location_id" class="form-label fw-semibold"><?= label('location.singular') ?></label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">—</option>
                                <?php foreach ($locations as $l): ?>
                                <option value="<?= (int) $l['id'] ?>" <?= (int) $v('location_id') === (int) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="default_location_id" class="form-label fw-semibold">Default <?= label('location.singular') ?></label>
                            <select class="form-select" id="default_location_id" name="default_location_id">
                                <option value="">—</option>
                                <?php foreach ($locations as $l): ?>
                                <option value="<?= (int) $l['id'] ?>" <?= (int) $v('default_location_id') === (int) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Where it goes when checked in.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Purchase &amp; lifecycle</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="supplier_id" class="form-label fw-semibold">Supplier</label>
                            <select class="form-select" id="supplier_id" name="supplier_id">
                                <option value="">—</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (int) $v('supplier_id') === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="order_number" class="form-label fw-semibold">Order number</label>
                            <input type="text" class="form-control" id="order_number" name="order_number" value="<?= e($v('order_number')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="purchase_date" class="form-label fw-semibold">Purchase date</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?= e($v('purchase_date')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="purchase_cost" class="form-label fw-semibold">Purchase cost</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="purchase_cost" name="purchase_cost" value="<?= e($v('purchase_cost')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="warranty_months" class="form-label fw-semibold">Warranty (months)</label>
                            <input type="number" min="0" class="form-control" id="warranty_months" name="warranty_months" value="<?= e($v('warranty_months')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="eol_date" class="form-label fw-semibold">End of life</label>
                            <input type="date" class="form-control" id="eol_date" name="eol_date" value="<?= e($v('eol_date')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="next_audit_date" class="form-label fw-semibold">Next audit</label>
                            <input type="date" class="form-control" id="next_audit_date" name="next_audit_date" value="<?= e($v('next_audit_date')) ?>">
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?= e($v('notes')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save changes' : 'Create asset' ?></button>
        <a href="<?= $isEdit ? '/inventory/' . (int) $editing['id'] : '/inventory' ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
