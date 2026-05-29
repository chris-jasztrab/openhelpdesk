<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Category' : 'Add Category';
$sidebarItems = Auth::isAdmin() ? adminSidebar('kb') : staffSidebar('kb-structure');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'KB Categories', 'url' => '/admin/kb/categories'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/kb/categories/{$editing['id']}/edit" : '/admin/kb/categories/create';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Category' : 'Add Category' ?></h2>
    <a href="/admin/kb/categories" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required>
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

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_public" id="isPublic" value="1"
                           <?= !empty($editing['is_public']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="isPublic">
                        Public (visible without login at <code>/kb/…</code>)
                    </label>
                </div>
                <div class="form-text">When enabled, this category and its published articles will be accessible to anyone without signing in.</div>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Category' : 'Create Category' ?>
                </button>
                <a href="/admin/kb/categories" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
