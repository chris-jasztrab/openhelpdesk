<?php
$layout       = 'app';
$pageTitle    = 'KB Articles';
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Knowledge Base'],
    ['label' => 'Articles'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">KB Articles</h2>
    <div class="d-flex gap-2">
        <a href="/admin/kb/categories" class="btn btn-outline-secondary">
            <i class="bi bi-collection me-1"></i>Categories
        </a>
        <a href="/admin/kb/folders" class="btn btn-outline-secondary">
            <i class="bi bi-folder me-1"></i>Folders
        </a>
        <a href="/admin/kb/articles/create" class="btn text-white" style="background:#4f46e5;">
            <i class="bi bi-plus-lg me-1"></i>Add Article
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Folder</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th style="width:150px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($articles)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No articles yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($articles as $a): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-file-text text-muted me-1"></i><?= e($a['title']) ?>
                        </td>
                        <td class="small">
                            <span class="text-muted"><?= e($a['category_name'] ?? '') ?></span>
                            <?php if ($a['folder_name']): ?>
                                <i class="bi bi-chevron-right text-muted" style="font-size:.65rem;"></i>
                                <span><?= e($a['folder_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($a['author_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($a['status'] === 'published'): ?>
                                <span class="badge bg-success">Published</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($a['updated_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/kb/articles/<?= $a['id'] ?>/preview" class="btn btn-sm btn-outline-info" title="Preview">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/admin/kb/articles/<?= $a['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/kb/articles/<?= $a['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('Delete this article?')">
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
