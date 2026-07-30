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
$uploadNotices = $_SESSION['label_upload_notices'] ?? [];
unset($_SESSION['label_upload_errors'], $_SESSION['label_upload_preview'], $_SESSION['label_upload_notices']);

// Load defaults for the "current values" table
$defaultFile  = ROOT_DIR . '/config/labels.default.json';
$defaults     = is_file($defaultFile) ? (json_decode(file_get_contents($defaultFile), true) ?: []) : [];
$custom       = json_decode(getSetting('custom_labels', '{}'), true) ?: [];

// Strip the readme key from display
unset($defaults['_readme']);

// Presentation metadata — see config/labels.meta.php for the contract.
// A key with a `where` note is wired up; a key absent from meta['keys'] is
// inert (present in the JSON but never read by label()), so it gets shown
// separately rather than passed off as something a rename would affect.
$labelMeta   = require ROOT_DIR . '/config/labels.meta.php';
$metaKeys    = $labelMeta['keys']   ?? [];
$metaGroups  = $labelMeta['groups'] ?? [];

$grouped = [];
foreach ($metaGroups as $gid => $g) {
    $grouped[$gid] = [];
}
$inert = [];
foreach ($defaults as $key => $defaultValue) {
    $gid = $metaKeys[$key]['group'] ?? null;
    if ($gid !== null && isset($grouped[$gid])) {
        $grouped[$gid][$key] = $defaultValue;
    } else {
        $inert[$key] = $defaultValue;
    }
}

// Inert keys are sub-grouped by their key prefix purely so the long list
// scans; no metadata to maintain, it falls out of the naming convention.
$inertFamilies = [];
foreach ($inert as $key => $defaultValue) {
    $family = strpos($key, '.') !== false ? substr($key, 0, strpos($key, '.')) : $key;
    $inertFamilies[$family][$key] = $defaultValue;
}
ksort($inertFamilies);

$wiredCount = count($metaKeys);
$inertCount = count($inert);

/**
 * One row of the label table. $where is null for inert keys.
 */
$renderLabelRow = function (string $key, string $defaultValue, ?string $where, array $custom, bool $isInert): void {
    $isCustom     = array_key_exists($key, $custom);
    $currentValue = $isCustom ? $custom[$key] : $defaultValue;
    ?>
    <tr class="<?= $isCustom ? 'table-warning' : '' ?>"
        data-label-key="<?= e($key) ?>"
        data-label-default="<?= e($defaultValue) ?>"
        <?= $isInert ? 'data-label-inert="1"' : '' ?>>
        <td class="font-monospace small text-muted align-top"><?= e($key) ?></td>
        <td class="small">
            <span class="ld-label-value" role="button" tabindex="0"
                  title="Click to edit"><?= e($currentValue) ?></span>
            <span class="badge bg-warning text-dark ms-1 ld-label-custom-badge<?= $isCustom ? '' : ' d-none' ?>"
                  title="Default: <?= e($defaultValue) ?>">custom</span>
            <span class="ld-label-state ms-1 small"></span>
            <?php if ($where !== null): ?>
            <div class="ld-label-where text-muted mt-1"><?= e($where) ?></div>
            <?php endif; ?>
        </td>
    </tr>
    <?php
};
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-translate me-2"></i>Label Customisation</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        Change the terminology your users see — rename "Location" to "Branch", reword the plain-English
        statuses on the portal, retitle the navigation. Click any value in the list on the right to edit it
        in place, or download the template, edit it in bulk, and upload it back.
        <br>
        <span class="d-inline-block mt-1">
            <i class="bi bi-info-circle me-1"></i><strong><?= $wiredCount ?></strong> labels are wired up and
            grouped by where they appear. The other <strong><?= $inertCount ?></strong> are listed at the bottom
            under <em>Not wired up yet</em> — they exist in the template file but nothing reads them, so
            renaming those has no effect anywhere in the app.
        </span>
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

<?php if (!empty($uploadNotices)): ?>
<div class="alert alert-info">
    <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-2"></i>Some keys in your file are no longer used.</h6>
    <ul class="mb-0 ps-3 small">
        <?php foreach ($uploadNotices as $notice): ?>
        <li><?= e($notice) ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="mb-0 mt-2 small">Everything else in the file was applied. Download a fresh template to stop seeing this.</p>
</div>
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
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success-emphasis"><?= $wiredCount ?> wired up</span>
                    <span class="badge bg-warning text-dark<?= empty($custom) ? ' d-none' : '' ?>" id="labelCustomCount">
                        <?= count($custom) ?> customised
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="px-3 pt-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control border-start-0 ps-0" id="labelSearch"
                               placeholder="Search keys and values&hellip;" autocomplete="off"
                               aria-label="Search labels" aria-describedby="labelSearchCount">
                        <button class="btn btn-outline-secondary d-none" type="button"
                                id="labelSearchClear" title="Clear search" aria-label="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="form-text mb-0" id="labelSearchCount" role="status" aria-live="polite"></div>
                </div>
                <p class="text-muted small px-3 pt-2 mb-0">
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
                        <?php foreach ($metaGroups as $gid => $group):
                            if (empty($grouped[$gid])) continue; ?>
                        <tr class="ld-label-group" data-label-group="<?= e($gid) ?>">
                            <td colspan="2" class="bg-light py-2">
                                <span class="fw-semibold small"><?= e($group['title']) ?></span>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1 ld-label-group-count"
                                      data-total="<?= count($grouped[$gid]) ?>"><?= count($grouped[$gid]) ?></span>
                                <?php if (!empty($group['blurb'])): ?>
                                <div class="text-muted mt-1" style="font-size:.75rem;"><?= e($group['blurb']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                            <?php foreach ($grouped[$gid] as $key => $defaultValue): ?>
                                <?php $renderLabelRow($key, $defaultValue, $metaKeys[$key]['where'] ?? null, $custom, false); ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>

                        <?php if ($inertCount > 0): ?>
                        <tr class="ld-label-group" data-label-group="__inert__">
                            <td colspan="2" class="bg-light py-2">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold"
                                        id="labelInertToggle" aria-expanded="false" aria-controls="labelTable">
                                    <i class="bi bi-chevron-right me-1" id="labelInertChevron"></i>Not wired up yet
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= $inertCount ?></span>
                                </button>
                                <div class="text-muted mt-1" style="font-size:.75rem;">
                                    These keys exist in the label file but nothing in the app reads them, so renaming
                                    them changes nothing. They're listed for completeness — and because they're the
                                    shortlist for what to wire up next. You can still edit them; the value is stored
                                    and will take effect if the key is ever hooked up.
                                </div>
                            </td>
                        </tr>
                            <?php foreach ($inertFamilies as $family => $familyKeys): ?>
                            <tr class="ld-label-group ld-label-family" data-label-group="__inert__"
                                data-label-inert-group="1">
                                <td colspan="2" class="py-1 ps-4">
                                    <span class="font-monospace small text-muted"><?= e($family) ?>.*</span>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1 ld-label-group-count"
                                          data-total="<?= count($familyKeys) ?>"><?= count($familyKeys) ?></span>
                                </td>
                            </tr>
                                <?php foreach ($familyKeys as $key => $defaultValue): ?>
                                    <?php $renderLabelRow($key, $defaultValue, null, $custom, true); ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <tr id="labelNoMatch" class="d-none">
                            <td colspan="2" class="text-center text-muted py-4">
                                <i class="bi bi-search me-1"></i>No labels match your search.
                            </td>
                        </tr>
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
    .ld-label-where {
        font-size: .75rem;
        line-height: 1.35;
        max-width: 34rem;
    }
    .ld-label-where::before {
        content: '\21B3\00A0';          /* ↳ — a literal glyph, not an icon-font codepoint */
        opacity: .6;
    }
    /* We supply our own clear button, so hide the native one type="search" adds. */
    #labelSearch::-webkit-search-cancel-button {
        -webkit-appearance: none;
        appearance: none;
    }
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

    /* ── Search / filter + the collapsed "not wired up" section ───
       Row visibility has two independent inputs — whether the row matches
       the search, and whether the inert section is expanded — so both are
       resolved in one pass rather than by two functions fighting over the
       same d-none class.

       Matching is on the key and the currently displayed value; the value
       is read live rather than cached, so a row stays findable by whatever
       it was just renamed to. Space-separated terms must all match, which
       is what lets "portal open" find portal.status.open.

       A search reveals matching inert rows even while the section is
       collapsed — otherwise searching for a key would report "no matches"
       for something that's sitting right there. */
    var search       = document.getElementById('labelSearch');
    var searchClear  = document.getElementById('labelSearchClear');
    var searchCount  = document.getElementById('labelSearchCount');
    var noMatch      = document.getElementById('labelNoMatch');
    var inertToggle  = document.getElementById('labelInertToggle');
    var inertChevron = document.getElementById('labelInertChevron');
    var rows         = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-label-key]'));
    var inertOpen    = false;

    // Group the rows under their heading row so a heading can hide itself
    // when everything beneath it is filtered out.
    var sections = [];
    (function buildSections() {
        var current = null;
        Array.prototype.forEach.call(table.querySelectorAll('tbody > tr'), function (tr) {
            if (tr.id === 'labelNoMatch') return;
            if (tr.classList.contains('ld-label-group')) {
                current = {
                    header: tr,
                    rows:   [],
                    // The "Not wired up yet" heading owns the toggle and has no
                    // rows directly under it — the family sub-headings do.
                    master: tr.dataset.labelGroup === '__inert__' && !tr.dataset.labelInertGroup
                };
                sections.push(current);
                return;
            }
            if (tr.dataset.labelKey && current) current.rows.push(tr);
        });
    })();

    function haystack(row) {
        // Mid-edit the span is swapped out for an input — read whichever is there.
        var el = row.querySelector('.ld-label-value') || row.querySelector('.ld-label-input');
        var value = el ? (el.value !== undefined ? el.value : el.textContent) : '';
        return (row.dataset.labelKey + ' ' + value).toLowerCase();
    }

    function apply() {
        var terms  = search ? search.value.toLowerCase().split(/\s+/).filter(Boolean) : [];
        var active = terms.length > 0;
        var shown = 0, inertShown = 0;

        rows.forEach(function (row) {
            var hay = haystack(row);
            var hit = terms.every(function (t) { return hay.indexOf(t) !== -1; });
            var isInert = row.dataset.labelInert === '1';
            var visible = hit && (!isInert || inertOpen || active);
            row.classList.toggle('d-none', !visible);
            if (visible) {
                shown++;
                if (isInert) inertShown++;
            }
        });

        sections.forEach(function (s) {
            var hits = s.rows.filter(function (r) { return !r.classList.contains('d-none'); }).length;
            var visible = s.master
                // Keep the toggle reachable unless a search rules the whole section out.
                ? !(active && inertShown === 0)
                : hits > 0;
            s.header.classList.toggle('d-none', !visible);

            // While filtering, the heading count would otherwise claim more rows
            // than are on screen.
            var badge = s.header.querySelector('.ld-label-group-count');
            if (badge) {
                var total = badge.dataset.total;
                badge.textContent = active && !s.master ? hits + ' / ' + total : total;
            }
        });

        noMatch.classList.toggle('d-none', shown > 0);
        if (searchClear) searchClear.classList.toggle('d-none', !active);
        if (searchCount) {
            searchCount.textContent = active
                ? 'Showing ' + shown + ' of ' + rows.length + ' labels.'
                : '';
        }
        if (inertChevron) {
            var expanded = inertOpen || (active && inertShown > 0);
            inertChevron.classList.toggle('bi-chevron-down', expanded);
            inertChevron.classList.toggle('bi-chevron-right', !expanded);
            inertToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    if (inertToggle) {
        inertToggle.addEventListener('click', function () {
            inertOpen = !inertOpen;
            apply();
        });
    }

    if (search) {
        search.addEventListener('input', apply);
        searchClear.addEventListener('click', function () {
            search.value = '';
            apply();
            search.focus();
        });
        // Escape clears the box rather than leaving a filter you can't see.
        search.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && search.value !== '') {
                ev.preventDefault();
                search.value = '';
                apply();
            }
        });
    }
    apply();   // collapses the inert section, and honours a value the browser restored

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
