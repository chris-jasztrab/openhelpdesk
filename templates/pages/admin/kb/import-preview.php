<?php
$layout       = 'app';
$pageTitle    = 'Preview KB Import';
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin',          'url' => '/admin'],
    ['label' => 'Knowledge Base', 'url' => '/admin/kb/articles'],
    ['label' => 'Import',         'url' => '/admin/kb/import'],
    ['label' => 'Preview'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Import Preview</h2>
</div>

<!-- Summary stat cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold" style="color:var(--ld-primary);"><?= $summary['total_categories'] ?></div>
            <div class="text-muted small">Categories</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-secondary"><?= $summary['total_folders'] ?></div>
            <div class="text-muted small">Folders</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-success"><?= $summary['published_count'] ?></div>
            <div class="text-muted small">Published</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-info"><?= $summary['draft_count'] ?></div>
            <div class="text-muted small">Drafts</div>
        </div>
    </div>
</div>

<?php if (!empty($summary['new_categories'])): ?>
<div class="alert alert-warning small mb-3">
    <i class="bi bi-plus-circle me-1"></i>
    <strong>New categories to be created:</strong> <?= e(implode(', ', $summary['new_categories'])) ?>
</div>
<?php endif; ?>

<?php if (!empty($summary['existing_categories'])): ?>
<div class="alert alert-info small mb-4">
    <i class="bi bi-arrow-right-circle me-1"></i>
    <strong>Merging into existing categories:</strong> <?= e(implode(', ', $summary['existing_categories'])) ?>
    — new folders and articles will be added without touching existing content.
</div>
<?php endif; ?>

<!-- Article preview table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-eye me-1"></i>
        Preview — first <?= count($previewArticles) ?> of <?= $summary['total_articles'] ?> article(s)
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Category / Folder</th>
                    <th>Status</th>
                    <th>Body Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previewArticles as $i => $row): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e(mb_strimwidth($row['title'], 0, 60, '…')) ?></td>
                    <td class="small text-muted">
                        <?= e($row['category']) ?>
                        <i class="bi bi-chevron-right" style="font-size:.65rem;"></i>
                        <?= e($row['folder']) ?>
                    </td>
                    <td>
                        <span class="badge <?= $row['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= ucfirst(e($row['status'])) ?>
                        </span>
                    </td>
                    <td class="text-muted small" style="max-width:280px;">
                        <?= e($row['body_preview']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Confirm form -->
<form method="POST" action="/admin/kb/import/confirm">
    <?= csrfField() ?>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="publish_all" name="publish_all" value="1">
        <label class="form-check-label" for="publish_all">
            Publish all articles on import
            <span class="text-muted small">(overrides draft status in the export file)</span>
        </label>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);"
                onclick="this.disabled=true; this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Importing…'; this.form.submit();">
            <i class="bi bi-check-lg me-1"></i>Confirm Import (<?= $summary['total_articles'] ?> articles)
        </button>
        <a href="/admin/kb/import" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>Cancel
        </a>
    </div>
</form>
