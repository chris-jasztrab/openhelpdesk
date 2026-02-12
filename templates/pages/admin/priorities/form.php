<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Priority' : 'Add Priority';
$sidebarItems = adminSidebar('priorities');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Priorities', 'url' => '/admin/priorities'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/priorities/{$editing['id']}/edit" : '/admin/priorities/create';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Priority' : 'Add Priority' ?></h2>
    <a href="/admin/priorities" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm" style="max-width:600px;">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Priority Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required
                       placeholder="e.g. Low, Medium, High, Critical">
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

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:#4f46e5;">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Priority' : 'Create Priority' ?>
                </button>
                <a href="/admin/priorities" class="btn btn-outline-secondary">Cancel</a>
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
