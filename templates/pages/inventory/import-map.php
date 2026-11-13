<?php
$layout       = 'app';
$pageTitle    = 'Match columns – Import – ' . label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory'), 'url' => '/inventory'],
    ['label' => 'Import', 'url' => '/inventory/import'],
    ['label' => 'Match columns'],
];
$matched = count(array_filter($mapping));
$unused  = array_values(array_diff($headers, array_filter($mapping)));
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0 min-w-0"><i class="bi bi-arrow-left-right me-2 text-muted"></i>Match your columns</h2>
    <span class="badge bg-secondary fs-6"><?= e($file) ?> — <?= number_format($rowCount) ?> row<?= $rowCount === 1 ? '' : 's' ?></span>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            <?= $matched ?> of <?= count($fields) ?> fields were matched to a column in your file automatically.
            Check each one, and pick the right column for anything that was missed or matched wrongly.
            A field left on <em>Not in my file</em> is simply not imported.
        </p>
        <div class="alert alert-warning py-2 small <?= $unused ? '' : 'd-none' ?>" id="unusedCols">
            <i class="bi bi-exclamation-triangle me-1"></i>Columns in your file that nothing uses yet:
            <span id="unusedList"><?php foreach ($unused as $u): ?><span class="badge text-bg-light border font-monospace me-1"><?= e($u) ?></span><?php endforeach; ?></span>
        </div>

        <form method="POST" action="/inventory/import/map">
            <?= csrfField() ?>
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0" data-no-resize>
                    <thead class="table-light">
                        <tr><th style="width:230px;">Field</th><th style="min-width:14rem;">Column in your file</th><th>First values in that column</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $key => [$label, $hint]): $sel = (string) ($mapping[$key] ?? ''); ?>
                        <tr>
                            <td>
                                <label class="fw-semibold mb-0" for="map_<?= $key ?>"><?= e($label) ?><?php if ($key === 'model'): ?> <span class="text-danger">*</span><?php endif; ?></label>
                                <?php if ($hint): ?><div class="text-muted small"><?= e($hint) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <select name="mapping[<?= $key ?>]" id="map_<?= $key ?>" class="form-select form-select-sm col-select" data-field="<?= $key ?>">
                                    <option value="">— Not in my file —</option>
                                    <?php foreach ($headers as $col): ?>
                                    <option value="<?= e($col) ?>" <?= $sel === $col ? 'selected' : '' ?>><?= e($col) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><span class="text-muted small font-monospace sample-cell" data-field="<?= $key ?>"></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="default_status_id">Status for new assets when the file does not say <span class="text-danger">*</span></label>
                    <select class="form-select" name="default_status_id" id="default_status_id" required>
                        <?php foreach ($statuses as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $opts['default_status_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="existing">When a row's asset tag already exists</label>
                    <select class="form-select" name="existing" id="existing">
                        <option value="skip" <?= $opts['existing'] === 'skip' ? 'selected' : '' ?>>Skip the row — never change existing assets</option>
                        <option value="update" <?= $opts['existing'] === 'update' ? 'selected' : '' ?>>Update that asset with the row's non-blank cells</option>
                    </select>
                    <div class="form-text">Updating never blanks a field out, and each change is written to the asset's history.</div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);"><i class="bi bi-eye me-1"></i>Preview the import</button>
                <a href="/inventory/import" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Choose another file</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var sampleRows = <?= json_encode($sampleRows, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var headers    = <?= json_encode($headers, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var selects    = Array.prototype.slice.call(document.querySelectorAll('.col-select'));
    function samples(col) {
        if (!col) return '';
        var vals = sampleRows.map(function (r) { return (r[col] || '').trim(); }).filter(function (v) { return v !== ''; });
        return vals.length ? vals.join(' / ') : '(empty in the first rows)';
    }
    function refresh() {
        var used = {};
        selects.forEach(function (sel) {
            document.querySelector('.sample-cell[data-field="' + sel.dataset.field + '"]').textContent = samples(sel.value);
            if (sel.value) used[sel.value] = true;
        });
        var box = document.getElementById('unusedCols');
        if (!box) return;
        var left = headers.filter(function (h) { return !used[h]; });
        box.classList.toggle('d-none', !left.length);
        var list = document.getElementById('unusedList');
        list.textContent = '';
        left.forEach(function (h) {
            var b = document.createElement('span');
            b.className = 'badge text-bg-light border font-monospace me-1';
            b.textContent = h;
            list.appendChild(b);
        });
    }
    selects.forEach(function (sel) { sel.addEventListener('change', refresh); });
    refresh();
})();
</script>
