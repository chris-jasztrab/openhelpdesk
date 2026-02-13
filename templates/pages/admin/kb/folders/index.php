<?php
$layout       = 'app';
$pageTitle    = 'KB Folders';
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Knowledge Base'],
    ['label' => 'Folders'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">KB Folders</h2>
    <div class="d-flex gap-2">
        <a href="/admin/kb/categories" class="btn btn-outline-secondary">
            <i class="bi bi-collection me-1"></i>Categories
        </a>
        <a href="/admin/kb/articles" class="btn btn-outline-secondary">
            <i class="bi bi-file-text me-1"></i>Articles
        </a>
        <a href="/admin/kb/folders/create" class="btn text-white" style="background:#4f46e5;">
            <i class="bi bi-plus-lg me-1"></i>Add Folder
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Slug</th>
                    <th>Order</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($folders)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No folders yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($folders as $f): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-folder text-muted me-1"></i><?= e($f['name']) ?>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= e($f['category_name'] ?? '—') ?></span></td>
                        <td class="text-muted small"><?= e($f['slug']) ?></td>
                        <td class="text-muted"><?= (int) $f['sort_order'] ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/kb/folders/<?= $f['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/kb/folders/<?= $f['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('Delete this folder? All articles in it will also be deleted.')">
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
