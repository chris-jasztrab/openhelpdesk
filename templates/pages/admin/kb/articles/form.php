<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Article' : 'Add Article';
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'KB Articles', 'url' => '/admin/kb/articles'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/kb/articles/{$editing['id']}/edit" : '/admin/kb/articles/create';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Article' : 'Add Article' ?></h2>
    <a href="/admin/kb/articles" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title"
                       value="<?= e(old('title', $editing['title'] ?? '')) ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="folder_id" class="form-label fw-semibold">Folder <span class="text-danger">*</span></label>
                    <select class="form-select" id="folder_id" name="folder_id" required>
                        <option value="">— Select folder —</option>
                        <?php foreach ($folders as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= old('folder_id', (string) ($editing['folder_id'] ?? '')) == $f['id'] ? 'selected' : '' ?>>
                            <?= e($f['category_name'] . ' / ' . $f['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label fw-semibold">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="draft" <?= old('status', $editing['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= old('status', $editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order" min="0"
                           value="<?= e(old('sort_order', (string) ($editing['sort_order'] ?? '0'))) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="body_markdown" class="form-label fw-semibold">
                    Body (Markdown) <span class="text-danger">*</span>
                </label>
                <textarea class="form-control font-monospace" id="body_markdown" name="body_markdown" rows="18" required><?= e(old('body_markdown', $editing['body_markdown'] ?? '')) ?></textarea>
                <div class="form-text">Supports Markdown formatting: **bold**, *italic*, # headings, - lists, [links](url), ```code blocks```, etc.</div>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:#4f46e5;">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Article' : 'Create Article' ?>
                </button>
                <?php if ($isEdit): ?>
                <a href="/admin/kb/articles/<?= $editing['id'] ?>/preview" class="btn btn-outline-info">
                    <i class="bi bi-eye me-1"></i>Preview
                </a>
                <?php endif; ?>
                <a href="/admin/kb/articles" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
