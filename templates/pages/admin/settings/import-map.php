<?php
$layout       = 'app';
$pageTitle    = 'Map Columns';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Import Tickets', 'url' => '/admin/settings/import'],
    ['label' => 'Map Columns'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-arrow-left-right me-2"></i>Map CSV Columns</h5>
        <span class="badge bg-secondary"><?= count($headers) ?> columns detected</span>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            Match each OpenHelpDesk field to the corresponding column from your CSV file.
            Fields marked <span class="text-danger fw-semibold">*</span> are required.
            Columns with a likely match have been pre-selected — review them and adjust as needed.
        </p>

        <form method="POST" action="/admin/settings/import/map">
            <?= csrfField() ?>

            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:220px;">OpenHelpDesk Field</th>
                            <th>CSV Column</th>
                            <th style="width:280px;">Sample Values</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($systemFields as $field): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <?= e($field['label']) ?>
                                    <?php if ($field['required']): ?>
                                    <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($field['required']): ?>
                                <div class="text-muted small">Required</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select name="mapping[<?= e($field['key']) ?>]"
                                        class="form-select form-select-sm col-select"
                                        data-field="<?= e($field['key']) ?>">
                                    <option value="">— Not mapped —</option>
                                    <?php foreach ($headers as $col): ?>
                                    <option value="<?= e($col) ?>"
                                        <?= ($autoMapping[$field['key']] ?? null) === $col ? 'selected' : '' ?>>
                                        <?= e($col) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <span class="text-muted small font-monospace sample-cell"
                                      data-field="<?= e($field['key']) ?>"></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="border rounded p-3 mb-4">
                <div class="fw-semibold mb-1"><i class="bi bi-stopwatch me-2"></i>SLA handling</div>
                <p class="text-muted small mb-3">
                    Imported tickets carry their original open date. Choose what that means for SLA timers.
                </p>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="sla_handling" id="sla_exclude"
                           value="exclude" <?= ($slaHandling ?? 'exclude') === 'exclude' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sla_exclude">
                        <span class="fw-semibold">Exclude from SLA</span>
                        <span class="badge bg-secondary ms-1">Recommended</span>
                        <div class="text-muted small">
                            No SLA clock, now or later. These tickets stay out of SLA reports and
                            compliance figures, and a future priority or type change won't
                            retroactively breach them.
                        </div>
                    </label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="sla_handling" id="sla_start_now"
                           value="start_now" <?= ($slaHandling ?? '') === 'start_now' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sla_start_now">
                        <span class="fw-semibold">Start SLA clocks now</span>
                        <div class="text-muted small">
                            Treats the backlog as if it arrived today. Nothing is instantly breached
                            and agents get a full response window, but SLA figures won't reflect how
                            long these tickets really waited.
                        </div>
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sla_handling" id="sla_historical"
                           value="historical" <?= ($slaHandling ?? '') === 'historical' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sla_historical">
                        <span class="fw-semibold">Apply from original open date</span>
                        <div class="text-muted small">
                            Reproduces each ticket's real historical SLA. Accurate for reporting —
                            but any still-open ticket older than its target lands already breached.
                        </div>
                    </label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-eye me-1"></i>Apply &amp; Preview
                </button>
                <a href="/admin/settings/import" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Start Over
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const sampleRows = <?= json_encode($sampleRows, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function getSamples(colName) {
    if (!colName) return '';
    const vals = sampleRows
        .map(r => (r[colName] || '').trim())
        .filter(v => v !== '')
        .slice(0, 3);
    return vals.length ? vals.join(' / ') : '(empty)';
}

function updateSample(selectEl) {
    const field = selectEl.dataset.field;
    const cell  = document.querySelector(`.sample-cell[data-field="${field}"]`);
    if (cell) cell.textContent = getSamples(selectEl.value);
}

document.querySelectorAll('.col-select').forEach(sel => {
    updateSample(sel);
    sel.addEventListener('change', () => updateSample(sel));
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
