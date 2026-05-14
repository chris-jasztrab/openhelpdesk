<?php
$layout       = 'app';
$pageTitle    = 'Import Knowledge Base';
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin',          'url' => '/admin'],
    ['label' => 'Knowledge Base', 'url' => '/admin/kb/articles'],
    ['label' => 'Import'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Import Knowledge Base</h2>
    <a href="/admin/kb/articles" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Articles
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-upload me-1"></i>Import from JSON Export
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Upload a <strong>.json</strong> file previously exported from OpenHelpDesk.
                    The import will recreate all categories, folders, and articles. Where a category
                    or folder name already exists on this server, new content will be merged into it.
                </p>

                <div class="alert alert-info small mb-4">
                    <i class="bi bi-info-circle me-1"></i>
                    To create an export file, go to <strong>KB Articles</strong> and click <strong>Export JSON</strong>.
                    You can then import that file on any OpenHelpDesk installation.
                </div>

                <form method="POST" action="/admin/kb/import/preview" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="mb-4">
                        <label for="json_file" class="form-label fw-semibold">JSON Export File</label>
                        <input type="file" class="form-control" id="json_file" name="json_file"
                               accept=".json,application/json" required>
                        <div class="form-text">Maximum file size: 20 MB.</div>
                    </div>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-search me-1"></i>Preview Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
