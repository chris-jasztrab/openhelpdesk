<?php
$layout       = 'app';
$pageTitle    = 'Import KB Articles – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Import KB Articles'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-book me-1"></i>Import Knowledge Base Articles
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Upload a CSV file to import knowledge base articles. The CSV must contain at least
                    <strong>title</strong> and <strong>body_markdown</strong> columns.
                </p>

                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Supported columns:</strong>
                    <code>title</code>, <code>body_markdown</code>, <code>category</code>,
                    <code>status</code> (draft/published), <code>tags</code>.
                    Articles will be organized into categories automatically. A "General" folder
                    is created within each category.
                </div>

                <form method="POST" action="/admin/settings/import-kb/preview" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">Maximum file size: 10 MB.</div>
                    </div>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-upload me-1"></i>Upload &amp; Preview
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
