<?php
$layout       = 'app';
$pageTitle    = 'Preview KB Import – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Import KB Articles', 'url' => '/admin/settings/import-kb'],
    ['label' => 'Preview'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<h5 class="fw-bold mb-3">Import Preview</h5>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold" style="color:var(--ld-primary);"><?= $summary['total_articles'] ?></div>
            <div class="text-muted small">Articles</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-success"><?= count($summary['categories']) ?></div>
            <div class="text-muted small">Categories</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-info"><?= $summary['draft_count'] ?></div>
            <div class="text-muted small">Drafts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-primary"><?= $summary['published_count'] ?></div>
            <div class="text-muted small">Published</div>
        </div>
    </div>
</div>

<?php if (!empty($summary['new_categories'])): ?>
<div class="alert alert-warning small mb-4">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>New categories to be created:</strong>
    <?= e(implode(', ', $summary['new_categories'])) ?>
</div>
<?php endif; ?>

<!-- Preview table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-eye me-1"></i>Preview (first <?= count($previewRows) ?> articles)
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Body Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previewRows as $i => $row): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e(mb_strimwidth($row['title'], 0, 60, '...')) ?></td>
                    <td><span class="badge bg-secondary"><?= e($row['category']) ?></span></td>
                    <td>
                        <span class="badge <?= $row['status'] === 'published' ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= e(ucfirst($row['status'])) ?>
                        </span>
                    </td>
                    <td class="text-muted small" style="max-width:300px;"><?= e(mb_strimwidth($row['body'], 0, 80, '...')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Confirm / Cancel -->
<form method="POST" action="/admin/settings/import-kb/confirm">
    <?= csrfField() ?>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="publish_all" name="publish_all" value="1">
        <label class="form-check-label" for="publish_all">
            Publish all articles on import <span class="text-muted small">(override draft status)</span>
        </label>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);"
                onclick="this.disabled=true; this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Importing...'; this.form.submit();">
            <i class="bi bi-check-lg me-1"></i>Confirm Import (<?= $summary['total_articles'] ?> articles)
        </button>
        <a href="/admin/settings/import-kb" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>Cancel
        </a>
    </div>
</form>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
