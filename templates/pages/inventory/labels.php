<?php
/**
 * QR code label printing. Uses the bare "base" layout; everything except
 * #labelSheet is hidden when printing, each label is exactly one page of the
 * label size, and @page is kept in sync with the chosen preset so roll
 * printers (Dymo, Zebra, Brother…) feed one label per asset with nothing
 * cut off. Preview is true-to-size (mm units).
 */
$pageTitle   = 'Print labels – ' . label('nav.inventory', 'Inventory');
$breadcrumbs = [
    ['label' => label('nav.inventory', 'Inventory'), 'url' => '/inventory'],
    ['label' => 'Print labels'],
];
?>
<div class="no-print">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="fw-bold mb-0"><i class="bi bi-qr-code me-2 text-muted"></i>Print QR labels</h2>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <?php if (!empty($assets)): ?>
            <span class="badge bg-secondary fs-6"><?= number_format(count($assets)) ?> label<?= count($assets) === 1 ? '' : 's' ?></span>
            <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print
            </button>
            <?php endif; ?>
            <a href="/inventory" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to <?= e(strtolower(label('nav.inventory', 'Inventory'))) ?></a>
        </div>
    </div>

    <?php if (empty($assets)): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">What would you like to print?</h5>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="/inventory/labels?set=new" class="btn btn-outline-primary <?= $newCount === 0 ? 'disabled' : '' ?>">
                    <i class="bi bi-qr-code me-1"></i>Assets without a QR label <span class="badge bg-primary ms-1"><?= number_format($newCount) ?></span>
                </a>
                <a href="/inventory/labels?set=all" class="btn btn-outline-secondary <?= $allCount === 0 ? 'disabled' : '' ?>">
                    All assets <span class="badge bg-secondary ms-1"><?= number_format($allCount) ?></span>
                </a>
            </div>
            <p class="text-muted small mb-0">
                "Assets without a QR label" are the ones created here — assets imported from Snipe-IT already carry a printed QR code.
                To print a specific set, tick assets on the <a href="/inventory">inventory list</a> and use its Labels button,
                or open one asset and click <em>Print label</em>.
            </p>
        </div>
    </div>
    <?php else: ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small fw-semibold mb-1" for="lblPreset">Label printer</label>
                    <select class="form-select form-select-sm" id="lblPreset">
                        <?php foreach ($presets as $k => $p): ?>
                        <option value="<?= e($k) ?>" <?= $k === $selectedPreset ? 'selected' : '' ?>><?= e($p[0]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1" for="lblW">Width mm</label>
                    <input type="number" class="form-control form-control-sm" id="lblW" min="10" max="300" step="0.1" style="width:5.5rem;">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1" for="lblH">Height mm</label>
                    <input type="number" class="form-control form-control-sm" id="lblH" min="8" max="300" step="0.1" style="width:5.5rem;">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1" for="lblPad" title="White space inside the label edge — also the QR quiet zone">Margin mm</label>
                    <input type="number" class="form-control form-control-sm" id="lblPad" min="0" max="20" step="0.5" style="width:5rem;">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1" for="lblQr">QR mm</label>
                    <input type="number" class="form-control form-control-sm" id="lblQr" min="6" max="100" step="0.5" style="width:5rem;">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1" for="lblF1">Tag pt</label>
                    <input type="number" class="form-control form-control-sm" id="lblF1" min="4" max="40" step="0.5" style="width:4.8rem;">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1" for="lblF2">Text pt</label>
                    <input type="number" class="form-control form-control-sm" id="lblF2" min="4" max="40" step="0.5" style="width:4.8rem;">
                </div>
                <div class="col-auto">
                    <div class="form-label small fw-semibold mb-1">Show</div>
                    <div class="d-flex gap-3 pb-1">
                        <?php foreach (['tag' => 'Tag', 'name' => 'Name', 'model' => 'Model', 'serial' => 'Serial'] as $f => $t): ?>
                        <label class="form-check form-check-inline m-0 small"><input type="checkbox" class="form-check-input lbl-field" value="<?= $f ?>"> <?= $t ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-auto ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="lblSave" title="Save this layout for the selected printer and make it the default">
                        <i class="bi bi-save me-1"></i>Save for this printer
                    </button>
                    <span class="small ms-1" id="lblSaveMsg"></span>
                </div>
            </div>
        </div>
    </div>

    <details class="mb-3 text-muted small">
        <summary class="fw-semibold">Printing tips — if labels come out cut off or shifted</summary>
        <ul class="mt-2 mb-0">
            <li>In the print dialog, pick the <strong>paper size that matches your labels</strong> (e.g. 30334 on a Dymo, 2″ × 1″ on a Zebra), set margins to <strong>None</strong> and scale to <strong>100%</strong> — never "Fit to page".</li>
            <li>The preview below is true to size: one block = one label, exactly as it will print.</li>
            <li>Zebra: if prints drift, run a SmartCal media calibration and make sure the driver is set to gap/web sensing, not continuous.</li>
            <li>Dymo: pick the label stock in the driver's paper list; Dymo printers only feed Dymo-sized stock.</li>
        </ul>
    </details>
    <?php endif; ?>
</div>

<?php if (!empty($assets)): ?>
<div id="labelSheet">
    <?php foreach ($assets as $a): ?>
    <div class="asset-label">
        <div class="al-qr" data-url="<?= e($labelBase . '/inventory/' . (int) $a['id']) ?>"></div>
        <div class="al-text">
            <div class="al-tag al-f-tag"><?= e($a['asset_tag']) ?></div>
            <?php if ($a['name'] !== null && $a['name'] !== ''): ?><div class="al-line al-f-name"><?= e($a['name']) ?></div><?php endif; ?>
            <div class="al-line al-f-model"><?= e($a['model_name']) ?></div>
            <?php if ($a['serial'] !== null && $a['serial'] !== ''): ?><div class="al-line al-f-serial"><?= e($a['serial']) ?></div><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
#labelSheet {
    display: flex; flex-wrap: wrap; gap: 6px;
    --lw: 57.2mm; --lh: 31.8mm; --lp: 2mm; --lq: 24mm; --f1: 11pt; --f2: 7pt;
}
/* Labels are always black on white, whatever the app theme. */
.asset-label {
    width: var(--lw); height: var(--lh); padding: var(--lp);
    box-sizing: border-box; overflow: hidden; flex: 0 0 auto;
    display: flex; align-items: center; gap: 1.5mm;
    background: #fff; color: #000; border: 1px dashed #adb5bd;
}
.al-qr, .al-qr svg { width: var(--lq); height: var(--lq); flex: 0 0 auto; display: block; }
.al-text { min-width: 0; line-height: 1.15; }
.al-tag  { font-family: var(--bs-font-monospace, monospace); font-weight: 700; font-size: var(--f1); }
.al-line { font-size: var(--f2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#labelSheet.hide-tag .al-f-tag, #labelSheet.hide-name .al-f-name,
#labelSheet.hide-model .al-f-model, #labelSheet.hide-serial .al-f-serial { display: none; }

@media print {
    /* Only the labels print: navbar, toolbars, breadcrumbs, banners all vanish. */
    body > :not(main), main > :not(#labelSheet) { display: none !important; }
    /* main.container beats Bootstrap's .py-4/.container !important utilities on specificity. */
    body, main, main.container { margin: 0 !important; padding: 0 !important; max-width: none !important; width: auto !important; background: #fff !important; }
    /* block + zero font: the whitespace between label divs must not become
       line boxes, or each one overflows onto a blank page of its own. */
    #labelSheet { display: block; font-size: 0; line-height: 0; }
    /* One label per page of the label size (see #pageStyle for @page). A hair
       under the page height, and never split: mm→pt rounding otherwise spills
       a label's last line onto a page of its own. */
    .asset-label {
        border: none; margin: 0; height: calc(var(--lh) - 0.3mm);
        break-inside: avoid; page-break-inside: avoid;
    }
    /* break-BEFORE each following label: it collapses with the natural page
       break when the previous label already filled its page, where a forced
       break-after would add a blank page between labels. */
    .asset-label + .asset-label { break-before: page; page-break-before: always; }
}
</style>
<style id="pageStyle"></style>

<script src="/assets/vendor/qrcode/qrcode.js"></script>
<script>
(function () {
    var sheet = document.getElementById('labelSheet');
    if (!sheet) return;

    /* Generate the QR codes once — the URL never changes, only the CSS size does. */
    sheet.querySelectorAll('.al-qr').forEach(function (el) {
        var qr = qrcode(0, 'M');
        qr.addData(el.getAttribute('data-url'));
        qr.make();
        el.innerHTML = qr.createSvgTag({ cellSize: 2, margin: 0, scalable: true });
    });

    var PRESETS = <?= json_encode(array_map(static fn(array $p): array => ['name' => $p[0], 'w' => $p[1], 'h' => $p[2]], $presets)) ?>;
    var SAVED   = <?= json_encode($profiles) ?>;
    var FIELDS  = ['tag', 'name', 'model', 'serial'];
    var inputs  = { w: 'lblW', h: 'lblH', pad: 'lblPad', qr: 'lblQr', f1: 'lblF1', f2: 'lblF2' };

    function defaultsFor(key) {
        var p = PRESETS[key];
        return { w: p.w, h: p.h, pad: 2, qr: Math.min(Math.round((p.h - 4) * 2) / 2, 26), f1: 11, f2: 7, fields: FIELDS.slice() };
    }
    function profileFor(key) {
        var d = defaultsFor(key), s = SAVED[key] || {};
        return Object.assign(d, s, { fields: (s.fields && s.fields.length ? s.fields : d.fields) });
    }
    function readForm() {
        var v = {};
        Object.keys(inputs).forEach(function (k) { v[k] = parseFloat(document.getElementById(inputs[k]).value) || 0; });
        v.fields = Array.prototype.filter.call(document.querySelectorAll('.lbl-field'), function (c) { return c.checked; })
            .map(function (c) { return c.value; });
        return v;
    }
    function fillForm(p) {
        Object.keys(inputs).forEach(function (k) { document.getElementById(inputs[k]).value = p[k]; });
        document.querySelectorAll('.lbl-field').forEach(function (c) { c.checked = p.fields.indexOf(c.value) !== -1; });
    }
    function apply() {
        var p = readForm();
        sheet.style.setProperty('--lw', p.w + 'mm');
        sheet.style.setProperty('--lh', p.h + 'mm');
        sheet.style.setProperty('--lp', p.pad + 'mm');
        sheet.style.setProperty('--lq', Math.max(4, Math.min(p.qr, p.h - 2 * p.pad, p.w - 2 * p.pad)) + 'mm');
        sheet.style.setProperty('--f1', p.f1 + 'pt');
        sheet.style.setProperty('--f2', p.f2 + 'pt');
        FIELDS.forEach(function (f) { sheet.classList.toggle('hide-' + f, p.fields.indexOf(f) === -1); });
        document.getElementById('pageStyle').textContent = '@page { size: ' + p.w + 'mm ' + p.h + 'mm; margin: 0; }';
    }

    var presetSel = document.getElementById('lblPreset');
    presetSel.addEventListener('change', function () { fillForm(profileFor(presetSel.value)); apply(); });
    Object.keys(inputs).forEach(function (k) { document.getElementById(inputs[k]).addEventListener('input', apply); });
    document.querySelectorAll('.lbl-field').forEach(function (c) { c.addEventListener('change', apply); });

    document.getElementById('lblSave').addEventListener('click', function () {
        var p = readForm(), body = new FormData(), msg = document.getElementById('lblSaveMsg');
        body.append('_token', <?= json_encode(csrfToken()) ?>);
        body.append('preset', presetSel.value);
        Object.keys(inputs).forEach(function (k) { body.append(k, p[k]); });
        p.fields.forEach(function (f) { body.append('fields[]', f); });
        fetch('/inventory/labels/settings', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                SAVED[presetSel.value] = p;
                msg.textContent = r.ok ? 'Saved' : (r.error || 'Save failed');
                msg.className = 'small ms-1 ' + (r.ok ? 'text-success' : 'text-danger');
                setTimeout(function () { msg.textContent = ''; }, 3000);
            })
            .catch(function () { msg.textContent = 'Save failed'; msg.className = 'small ms-1 text-danger'; });
    });

    fillForm(profileFor(presetSel.value));
    apply();
})();
</script>
