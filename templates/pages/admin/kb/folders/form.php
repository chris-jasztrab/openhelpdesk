<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Folder' : 'Add Folder';
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'KB Folders', 'url' => '/admin/kb/folders'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/kb/folders/{$editing['id']}/edit" : '/admin/kb/folders/create';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Folder' : 'Add Folder' ?></h2>
    <a href="/admin/kb/folders" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Folder Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required>
            </div>

            <div class="mb-3">
                <label for="category_id" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">— Select category —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= old('category_id', (string) ($editing['category_id'] ?? '')) == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" min="0"
                       value="<?= e(old('sort_order', (string) ($editing['sort_order'] ?? '0'))) ?>" style="max-width:120px;">
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:#4f46e5;">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Folder' : 'Create Folder' ?>
                </button>
                <a href="/admin/kb/folders" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
