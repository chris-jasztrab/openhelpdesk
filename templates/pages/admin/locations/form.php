<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? ('Edit ' . label('location.singular')) : ('Add ' . label('location.singular'));
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => label('location.plural'), 'url' => '/admin/locations'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/locations/{$editing['id']}/edit" : '/admin/locations/create';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? ('Edit ' . label('location.singular')) : ('Add ' . label('location.singular')) ?></h2>
    <a href="/admin/locations" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold"><?= label('location.singular') ?> Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required>
            </div>

            <div class="mb-3">
                <label for="address" class="form-label fw-semibold">Address</label>
                <textarea class="form-control" id="address" name="address" rows="2"><?= e(old('address', $editing['address'] ?? '')) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? ('Update ' . label('location.singular')) : ('Create ' . label('location.singular')) ?>
                </button>
                <a href="/admin/locations" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
