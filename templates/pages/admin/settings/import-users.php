<?php
$layout       = 'app';
$pageTitle    = 'Import Users';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Import Users'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-person-plus me-2"></i>Import Users from CSV</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            Upload a CSV file containing user accounts to bulk-create staff records.
            Any CSV format is supported — you will be able to map your file's columns to the
            correct fields before the import runs.
        </p>

        <div class="alert alert-info d-flex align-items-start" role="alert">
            <i class="bi bi-info-circle-fill me-2 mt-1"></i>
            <div>
                <strong>What happens during import:</strong>
                <ul class="mb-0 mt-1">
                    <li>Each row creates one user account with a randomly generated password</li>
                    <li>Users will need to reset their password before signing in</li>
                    <li>Existing accounts (matched by email) are <strong>skipped</strong> — they are not overwritten</li>
                    <li>Unknown locations are created automatically</li>
                    <li>Role defaults to <strong>User</strong> if not provided or unrecognised</li>
                </ul>
            </div>
        </div>

        <form method="POST" action="/admin/settings/import-users/preview" enctype="multipart/form-data">
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
