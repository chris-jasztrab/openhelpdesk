<?php
$layout       = 'app';
$pageTitle    = 'KB Categories';
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Knowledge Base'],
    ['label' => 'Categories'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">KB Categories</h2>
    <div class="d-flex gap-2">
        <a href="/admin/kb/folders" class="btn btn-outline-secondary">
            <i class="bi bi-folder me-1"></i>Folders
        </a>
        <a href="/admin/kb/articles" class="btn btn-outline-secondary">
            <i class="bi bi-file-text me-1"></i>Articles
        </a>
        <a href="/admin/kb/categories/create" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>Add Category
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Order</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No categories yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-collection text-muted me-1"></i><?= e($cat['name']) ?>
                        </td>
                        <td class="text-muted small"><?= e($cat['slug']) ?></td>
                        <td class="text-muted small" style="max-width:300px;"><?= e($cat['description'] ? mb_strimwidth($cat['description'], 0, 80, '...') : '—') ?></td>
                        <td class="text-muted"><?= (int) $cat['sort_order'] ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/kb/categories/<?= $cat['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/kb/categories/<?= $cat['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('Delete this category? All folders and articles in it will also be deleted.')">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
