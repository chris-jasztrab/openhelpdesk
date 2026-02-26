<?php
$layout       = 'app';
$pageTitle    = 'Label Customisation';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Labels'],
];

// Errors from a previous upload attempt (stored in session by the route)
$uploadErrors  = $_SESSION['label_upload_errors']  ?? [];
$uploadPreview = $_SESSION['label_upload_preview'] ?? null;
unset($_SESSION['label_upload_errors'], $_SESSION['label_upload_preview']);

// Load defaults for the "current values" table
$defaultFile  = ROOT_DIR . '/config/labels.default.json';
$defaults     = is_file($defaultFile) ? (json_decode(file_get_contents($defaultFile), true) ?: []) : [];
$custom       = json_decode(getSetting('custom_labels', '{}'), true) ?: [];
$merged       = array_merge($defaults, $custom);

// Strip the readme key from display
unset($defaults['_readme']);
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-translate me-2"></i>Label Customisation</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        Change any terminology in the app — rename "Ticket" to "Issue", "Agent" to "Staff", and so on.
        Download the template, edit the values, then upload it back.
    </p>
</div>

<?php if (!empty($uploadErrors)): ?>
<div class="alert alert-danger">
    <h6 class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Upload failed — fix the errors below and try again.</h6>
    <ul class="mb-0 ps-3 small">
        <?php foreach ($uploadErrors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($uploadPreview !== null): ?>
    <div class="mt-3">
        <label class="form-label fw-semibold small">Your uploaded file (edit and re-upload):</label>
        <textarea class="form-control font-monospace small" rows="12" id="errorPreviewJson"><?= e($uploadPreview) ?></textarea>
        <div class="mt-2 d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="copyJsonToClipboard()">
                <i class="bi bi-clipboard me-1"></i>Copy JSON
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Labels updated successfully.</div>
<?php endif; ?>

<?php if (isset($_GET['reset'])): ?>
<div class="alert alert-info"><i class="bi bi-arrow-counterclockwise me-2"></i>Labels reset to defaults.</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left column: download + upload -->
    <div class="col-lg-5">

        <!-- Download -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-download me-2"></i>Step 1 — Download the template</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Download a JSON file containing every label key and its current value.
                    Edit the values on the right-hand side only — do not change the keys.
                </p>
                <a href="/admin/settings/labels/download" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Download labels.json
                </a>
            </div>
        </div>

        <!-- Upload -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-upload me-2"></i>Step 2 — Upload your edited file</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Upload the edited JSON file. The system will validate every key and surface any
                    problems before applying changes.
                </p>
                <form method="POST" action="/admin/settings/labels/upload" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <input type="file" name="labels_file" class="form-control form-control-sm" accept=".json,application/json" required>
                    </div>
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-cloud-upload me-1"></i>Upload & Apply
                    </button>
                </form>
            </div>
        </div>

        <!-- Reset -->
        <div class="card border-0 shadow-sm border-danger-subtle">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold text-danger"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset to Defaults</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Remove all custom labels and restore the original built-in terminology.
                </p>
                <form method="POST" action="/admin/settings/labels/reset"
                      onsubmit="return confirm('Reset all labels to their defaults?')">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Labels
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right column: current values -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>Current Label Values</h6>
                <?php if (!empty($custom)): ?>
                <span class="badge bg-warning text-dark"><?= count($custom) ?> customised</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width:40%;">Key</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($defaults as $key => $defaultValue):
                            $isCustom      = array_key_exists($key, $custom);
                            $currentValue  = $isCustom ? $custom[$key] : $defaultValue;
                        ?>
                        <tr class="<?= $isCustom ? 'table-warning' : '' ?>">
                            <td class="font-monospace small text-muted"><?= e($key) ?></td>
                            <td class="small">
                                <?= e($currentValue) ?>
                                <?php if ($isCustom): ?>
                                <span class="badge bg-warning text-dark ms-1" title="Default: <?= e($defaultValue) ?>">custom</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyJsonToClipboard() {
    const ta = document.getElementById('errorPreviewJson');
    navigator.clipboard.writeText(ta.value).then(() => {
        const btn = event.target.closest('button');
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy JSON'; }, 2000);
    });
}
</script>
