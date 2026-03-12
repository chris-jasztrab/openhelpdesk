<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Ticket Type' : 'Add Ticket Type';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Ticket Types', 'url' => '/admin/types'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/types/{$editing['id']}/edit" : '/admin/types/create';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Ticket Type' : 'Add Ticket Type' ?></h2>
    <a href="/admin/types" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm" style="max-width:600px;">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Type Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required
                       placeholder="e.g. IT, Marketing, Facilities">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="color" class="form-label fw-semibold">Color</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" class="form-control form-control-color" id="color" name="color"
                               value="<?= e(old('color', $editing['color'] ?? '#6c757d')) ?>">
                        <span class="badge" id="colorPreview" style="background:<?= e(old('color', $editing['color'] ?? '#6c757d')) ?>;">
                            <?= e(old('name', $editing['name'] ?? 'Preview')) ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order"
                       value="<?= e(old('sort_order', (string) ($editing['sort_order'] ?? '0'))) ?>" min="0">
                    <div class="form-text">Lower numbers appear first.</div>
                </div>
            </div>

            <div class="mb-3">
                <label for="group_id" class="form-label fw-semibold">Default Group</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">— No group —</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= (int) old('group_id', (string) ($editing['group_id'] ?? '')) === (int) $g['id'] ? 'selected' : '' ?>>
                        <?= e($g['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">When assigning agents to tickets of this type, only agents in this group will be shown.</div>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Type' : 'Create Type' ?>
                </button>
                <a href="/admin/types" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('color').addEventListener('input', function() {
    document.getElementById('colorPreview').style.background = this.value;
});
document.getElementById('name').addEventListener('input', function() {
    document.getElementById('colorPreview').textContent = this.value || 'Preview';
});
</script>
