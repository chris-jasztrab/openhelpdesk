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
<?php if (!empty($authorFilter)): ?>
<div class="alert alert-info d-flex align-items-center justify-content-between mb-3 py-2">
    <span><i class="bi bi-person-fill me-2"></i>Showing articles authored by <strong><?= e($authorFilter) ?></strong></span>
    <a href="/admin/kb/articles" class="btn btn-sm btn-outline-secondary">Clear filter</a>
</div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">KB Articles</h2>
    <div class="d-flex gap-2">
        <a href="/admin/kb/categories" class="btn btn-outline-secondary">
            <i class="bi bi-collection me-1"></i>Categories
        </a>
        <a href="/admin/kb/folders" class="btn btn-outline-secondary">
            <i class="bi bi-folder me-1"></i>Folders
        </a>
        <a href="/admin/kb/export" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a href="/admin/settings/import-kb" class="btn btn-outline-secondary">
            <i class="bi bi-upload me-1"></i>Import CSV
        </a>
        <a href="/admin/kb/articles/create" class="btn text-white" style="background:var(--ld-primary);">
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
                                <a href="/admin/kb/articles/<?= $a['id'] ?>/history" class="btn btn-sm btn-outline-secondary" title="History">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteArticleModal"
                                        data-id="<?= $a['id'] ?>"
                                        data-title="<?= e($a['title']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Article Modal -->
<div class="modal fade" id="deleteArticleModal" tabindex="-1" aria-labelledby="deleteArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteArticleModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Article
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete article <strong id="deleteArticleTitle"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteArticleForm" action="">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('deleteArticleModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteArticleTitle').textContent = btn.dataset.title;
    document.getElementById('deleteArticleForm').action = '/admin/kb/articles/' + btn.dataset.id + '/delete';
});
</script>
