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
        Click any value in the list on the right to edit it in place, or download the template,
        edit it in bulk, and upload it back.
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
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#resetLabelsModal">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Labels
                </button>
            </div>
        </div>
    </div>

    <!-- Right column: current values -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>Current Label Values</h6>
                <span class="badge bg-warning text-dark<?= empty($custom) ? ' d-none' : '' ?>" id="labelCustomCount">
                    <?= count($custom) ?> customised
                </span>
            </div>
            <div class="card-body p-0">
                <p class="text-muted small px-3 pt-3 mb-0">
                    <i class="bi bi-pencil-square me-1"></i>Click a value to edit it. It saves when you click away.
                    Clear the box to restore the built-in wording.
                </p>
                <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0 align-middle" id="labelTable">
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
                        <tr class="<?= $isCustom ? 'table-warning' : '' ?>"
                            data-label-key="<?= e($key) ?>"
                            data-label-default="<?= e($defaultValue) ?>">
                            <td class="font-monospace small text-muted"><?= e($key) ?></td>
                            <td class="small">
                                <span class="ld-label-value" role="button" tabindex="0"
                                      title="Click to edit"><?= e($currentValue) ?></span>
                                <span class="badge bg-warning text-dark ms-1 ld-label-custom-badge<?= $isCustom ? '' : ' d-none' ?>"
                                      title="Default: <?= e($defaultValue) ?>">custom</span>
                                <span class="ld-label-state ms-1 small"></span>
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

<style>
    .ld-label-value {
        display: inline-block;
        min-width: 3rem;
        padding: .1rem .35rem;
        margin: -.1rem -.35rem;
        border-radius: .25rem;
        border-bottom: 1px dashed transparent;
        cursor: text;
    }
    .ld-label-value:hover,
    .ld-label-value:focus-visible {
        background: rgba(13, 110, 253, .08);
        border-bottom-color: rgba(13, 110, 253, .5);
        outline: none;
    }
    .ld-label-input {
        width: 100%;
        max-width: 22rem;
        padding: .1rem .35rem;
        font-size: inherit;
        border: 1px solid var(--ld-primary, #0d6efd);
        border-radius: .25rem;
    }
    .ld-label-input.is-saving { opacity: .6; }
</style>

<script>
/* ── Inline label editing ──────────────────────────────────────────
   Click a value → it becomes a text input. Blur saves; Enter saves via
   blur; Escape reverts without a request. A value matching the default
   (or left blank) clears the override server-side, so the "custom"
   badge and the header count are refreshed from the response. */
(function () {
    'use strict';

    var table = document.getElementById('labelTable');
    if (!table) return;

    var csrf       = <?= json_encode(csrfToken()) ?>;
    var countBadge = document.getElementById('labelCustomCount');

    function flash(row, message, isError) {
        var state = row.querySelector('.ld-label-state');
        if (!state) return;
        state.className = 'ld-label-state ms-1 small ' + (isError ? 'text-danger' : 'text-success');
        state.textContent = message;
        if (!isError) {
            setTimeout(function () {
                if (state.textContent === message) state.textContent = '';
            }, 2000);
        }
    }

    function showValue(row, span, input, value) {
        input.replaceWith(span);
        span.textContent = value;
        span.classList.remove('d-none');
    }

    function save(row, span, input, original) {
        var value = input.value.trim();

        if (value === original) {
            showValue(row, span, input, original);
            return;
        }

        input.disabled = true;
        input.classList.add('is-saving');

        fetch('/admin/settings/labels/inline', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body:    JSON.stringify({ key: row.dataset.labelKey, value: value })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    // Keep the input open so the admin can correct the value.
                    input.disabled = false;
                    input.classList.remove('is-saving');
                    input.focus();
                    flash(row, (data && data.error) || 'Save failed.', true);
                    return;
                }
                showValue(row, span, input, data.value);
                row.classList.toggle('table-warning', data.is_custom);
                row.querySelector('.ld-label-custom-badge').classList.toggle('d-none', !data.is_custom);
                if (countBadge) {
                    countBadge.textContent = data.custom + ' customised';
                    countBadge.classList.toggle('d-none', data.custom === 0);
                }
                flash(row, '✓ Saved', false);
            })
            .catch(function () {
                input.disabled = false;
                input.classList.remove('is-saving');
                flash(row, 'Network error — not saved.', true);
            });
    }

    function edit(span) {
        var row = span.closest('tr');
        if (!row || row.querySelector('.ld-label-input')) return;

        var original = span.textContent.trim();
        var input    = document.createElement('input');
        input.type      = 'text';
        input.className = 'ld-label-input';
        input.value     = original;
        input.maxLength = 255;
        input.setAttribute('aria-label', 'Value for ' + row.dataset.labelKey);

        var cancelled = false;
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                cancelled = true;
                showValue(row, span, input, original);
                span.focus();
            } else if (ev.key === 'Enter') {
                ev.preventDefault();
                input.blur();   // blur is the single save path
            }
        });
        input.addEventListener('blur', function () {
            if (cancelled || input.disabled) return;
            save(row, span, input, original);
        });

        span.classList.add('d-none');
        span.replaceWith(input);
        input.focus();
        input.select();
    }

    table.addEventListener('click', function (ev) {
        var span = ev.target.closest('.ld-label-value');
        if (span) edit(span);
    });

    table.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        var span = ev.target.closest('.ld-label-value');
        if (!span) return;
        ev.preventDefault();
        edit(span);
    });
})();

function copyJsonToClipboard() {
    const ta = document.getElementById('errorPreviewJson');
    navigator.clipboard.writeText(ta.value).then(() => {
        const btn = event.target.closest('button');
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy JSON'; }, 2000);
    });
}
</script>

<!-- Reset Labels Modal -->
<div class="modal fade" id="resetLabelsModal" tabindex="-1" aria-labelledby="resetLabelsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="resetLabelsModalLabel">
                    <i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>Reset Labels
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Reset all labels to their defaults? All custom labels will be removed.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/admin/settings/labels/reset">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Labels
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
