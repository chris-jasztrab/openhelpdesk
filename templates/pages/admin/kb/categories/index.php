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
        <table class="table table-hover align-middle mb-0"
               data-sortable-list data-reorder-url="/admin/kb/categories/reorder">
            <thead class="table-light">
                <tr>
                    <th data-sort-col="name">Name</th>
                    <th data-sort-col="slug">Slug</th>
                    <th>Description</th>
                    <th data-sort-col="public">Public</th>
                    <th style="width:130px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No categories yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr data-id="<?= (int) $cat['id'] ?>">
                        <td class="fw-semibold" data-sort-value="<?= e($cat['name']) ?>">
                            <i class="bi bi-collection text-muted me-1"></i><?= e($cat['name']) ?>
                        </td>
                        <td class="text-muted small"><?= e($cat['slug']) ?></td>
                        <td class="text-muted small" style="max-width:260px;"><?= e($cat['description'] ? mb_strimwidth($cat['description'], 0, 80, '...') : '—') ?></td>
                        <td data-sort-value="<?= $cat['is_public'] ? '1' : '0' ?>">
                            <?php if ($cat['is_public']): ?>
                                <a href="/kb/<?= e($cat['slug']) ?>" target="_blank"
                                   class="badge bg-success text-decoration-none" title="View public page">
                                    <i class="bi bi-globe2 me-1"></i>Public
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">Private</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/kb/categories/<?= $cat['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteCategoryModal"
                                        data-id="<?= $cat['id'] ?>"
                                        data-name="<?= e($cat['name']) ?>">
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

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteCategoryModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete category <strong id="deleteCategoryName"></strong>? All folders and articles in it will also be deleted. This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteCategoryForm" action="">
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
document.getElementById('deleteCategoryModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteCategoryName').textContent = btn.dataset.name;
    document.getElementById('deleteCategoryForm').action = '/admin/kb/categories/' + btn.dataset.id + '/delete';
});
</script>
<?php require ROOT_DIR . '/templates/partials/sortable-list.php'; ?>
