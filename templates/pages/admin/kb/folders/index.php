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
        <a href="/admin/kb/folders/create" class="btn text-white" style="background:var(--ld-primary);">
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
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteFolderModal"
                                        data-id="<?= $f['id'] ?>"
                                        data-name="<?= e($f['name']) ?>">
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

<!-- Delete Folder Modal -->
<div class="modal fade" id="deleteFolderModal" tabindex="-1" aria-labelledby="deleteFolderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteFolderModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Folder
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete folder <strong id="deleteFolderName"></strong>? All articles in it will also be deleted. This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteFolderForm" action="">
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
document.getElementById('deleteFolderModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteFolderName').textContent = btn.dataset.name;
    document.getElementById('deleteFolderForm').action = '/admin/kb/folders/' + btn.dataset.id + '/delete';
});
</script>
