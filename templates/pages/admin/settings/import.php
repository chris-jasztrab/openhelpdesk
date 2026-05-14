<?php
$layout       = 'app';
$pageTitle    = 'Import Tickets';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Import Tickets'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<?php if (!empty($hasSkippedDownload)): ?>
<div class="card border-0 shadow-sm mb-4 border-start border-warning border-4">
    <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
        <div>
            <h6 class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Skipped Rows Available</h6>
            <p class="text-muted small mb-0">
                Some rows from your last import were skipped due to missing subject or email. Download the CSV below,
                correct the missing data, and re-upload to import those tickets.
            </p>
        </div>
        <a href="/admin/settings/import/download-skipped" class="btn btn-warning text-white flex-shrink-0">
            <i class="bi bi-download me-1"></i>Download Skipped Rows
        </a>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-cloud-upload me-2"></i>Import Tickets from CSV</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            Upload a CSV export from your previous ticketing system. Any CSV format is supported — you will be able to
            map your file's columns to the correct OpenHelpDesk fields before the import runs.
        </p>

        <div class="alert alert-info d-flex align-items-start" role="alert">
            <i class="bi bi-info-circle-fill me-2 mt-1"></i>
            <div>
                <strong>What happens during import:</strong>
                <ul class="mb-0 mt-1">
                    <li>Tickets are created with their original timestamps and metadata</li>
                    <li>Unknown submitters are created as <strong>User</strong> accounts</li>
                    <li>Unknown agents are created as <strong>Agent</strong> accounts</li>
                    <li>Missing locations and ticket types are created automatically</li>
                    <li>Original ticket IDs are preserved as legacy references</li>
                </ul>
            </div>
        </div>

        <form method="POST" action="/admin/settings/import/preview" enctype="multipart/form-data">
            <?= csrfField() ?>

            <div class="mb-4">
                <label for="csv_file" class="form-label fw-semibold">CSV File</label>
                <input type="file" class="form-control" id="csv_file" name="csv_file"
                       accept=".csv" required>
                <div class="form-text">Maximum file size: 10 MB. File must be in CSV format with a header row.</div>
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-eye me-1"></i>Upload &amp; Preview
            </button>
        </form>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
