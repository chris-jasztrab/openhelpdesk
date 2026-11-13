<?php
$layout       = 'app';
$pageTitle    = 'Reports – ' . label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory'), 'url' => '/inventory'],
    ['label' => 'Reports'],
];
$loc = label('location.singular');
// Current query (scalars only) for CSV links and forms that keep the other settings.
$self   = array_filter($_GET, static fn($v, $k) => is_string($v) && !in_array($k, ['export', 'page'], true), ARRAY_FILTER_USE_BOTH);
$csvUrl = e('/inventory/reports?' . http_build_query($self + ['tab' => $tab, 'export' => 'csv']));
$list   = static fn(array $f = []): string => '/inventory' . ($f ? '?' . http_build_query($f) : '');
/** A count that links to the list rows behind it (a plain muted 0 when there are none). */
$cell = static function (mixed $n, ?string $url): string {
    $n = (int) $n;
    return $n === 0 || $url === null ? '<span class="' . ($n === 0 ? 'text-muted' : '') . '">' . number_format($n) . '</span>'
        : '<a href="' . e($url) . '" class="text-decoration-none">' . number_format($n) . '</a>';
};
$tile = static function (string $title, mixed $n, ?string $url, string $tone = 'body', string $sub = '') use ($cell): string {
    return '<div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">'
        . '<div class="text-muted small">' . $title . '</div>'
        . '<div class="fs-3 fw-bold text-' . $tone . '">' . $cell($n, $url) . '</div>'
        . ($sub !== '' ? '<div class="small text-muted">' . $sub . '</div>' : '')
        . '</div></div></div>';
};
$gname = static fn(array $r): string => $r['gname'] !== null ? e((string) $r['gname']) : '<span class="text-muted fst-italic">None</span>';
$day   = static fn(?string $d): string => $d ? date('M j, Y', strtotime($d)) : '—';
$pct   = static fn(int $part, int $whole): int => $whole > 0 ? (int) round(100 * $part / $whole) : 0;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="fw-bold mb-0 min-w-0"><i class="bi bi-bar-chart me-2 text-muted"></i><?= e(label('nav.inventory', 'Inventory')) ?> reports</h2>
    <a href="/inventory" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i>Asset list</a>
</div>

<ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto" style="scrollbar-width:thin;">
    <?php foreach (INVENTORY_REPORT_TABS as $k => [$t, $icon]): if ($k === 'digest' && !Assets::canManage()) continue; ?>
    <li class="nav-item"><a class="nav-link text-nowrap <?= $k === $tab ? 'active' : '' ?>" href="/inventory/reports?tab=<?= $k ?>"><i class="bi <?= $icon ?> me-1"></i><?= $t ?></a></li>
    <?php endforeach; ?>
</ul>

<?php
/** "Group by" picker + CSV button, shared by the grouped tabs. $extra = hidden inputs to keep. */
$groupBar = static function (array $extra = [], string $before = '') use ($tab, $by, $dims, $csvUrl): string {
    $h = '<form method="GET" action="/inventory/reports" class="d-flex align-items-center gap-2 flex-wrap mb-3">'
        . '<input type="hidden" name="tab" value="' . e($tab) . '">';
    foreach ($extra as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    $h .= $before . '<label for="repBy" class="small text-muted">Group by</label><select id="repBy" name="by" class="form-select form-select-sm w-auto" onchange="this.form.submit()">';
    foreach ($dims as $k => [$t]) {
        $h .= '<option value="' . $k . '"' . ($k === $by ? ' selected' : '') . '>' . e($t) . '</option>';
    }
    return $h . '</select><a href="' . $csvUrl . '" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-download me-1"></i>CSV</a></form>';
};
?>

<?php if ($tab === 'overview'): $t = $totals; ?>
<div class="row g-3 mb-4">
    <?= $tile('Assets', $t['total'] ?? 0, $list()) ?>
    <?= $tile('Deployed', $t['deployed'] ?? 0, $list(['status' => ['deployed']]), 'primary') ?>
    <?= $tile('In warranty', $t['w_active'] ?? 0, $list(['warranty' => 'active']), 'success') ?>
    <?= $tile('Warranty expired', $t['w_expired'] ?? 0, $list(['warranty' => 'expired']), 'danger') ?>
    <?= $tile('Warranty unknown', $t['w_unknown'] ?? 0, $list(['warranty' => 'unknown']), 'secondary') ?>
    <?= $tile("Not audited in {$months} months", $t['not_audited'] ?? 0, $list(['audit_before' => $cut]), 'warning') ?>
</div>
<?= $groupBar() ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr>
                <th><?= e($dims[$by][0]) ?></th><th class="text-end">Assets</th><th class="text-end">Deployed</th>
                <th class="text-end">In warranty</th><th class="text-end">Expired</th><th class="text-end">Unknown</th>
                <th class="text-end">Audited (<?= $months ?> mo)</th><th class="text-end">Not audited</th>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $r): $L = static fn(array $more = []) => inventoryReportLink($by, $r['gid'], $more); ?>
                <tr>
                    <td><?= $gname($r) ?></td>
                    <td class="text-end fw-semibold"><?= $cell($r['total'], $L()) ?></td>
                    <td class="text-end"><?= $cell($r['deployed'], $by === 'status' ? null : $L(['status' => ['deployed']])) ?></td>
                    <td class="text-end"><?= $cell($r['w_active'], $L(['warranty' => 'active'])) ?></td>
                    <td class="text-end"><?= $cell($r['w_expired'], $L(['warranty' => 'expired'])) ?></td>
                    <td class="text-end"><?= $cell($r['w_unknown'], $L(['warranty' => 'unknown'])) ?></td>
                    <td class="text-end"><?= $cell($r['audited'], $L(['audit_after' => $cut])) ?></td>
                    <td class="text-end"><?= $cell($r['not_audited'], $L(['audit_before' => $cut])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold"><tr>
                <td>Total</td><td class="text-end"><?= number_format($t['total'] ?? 0) ?></td><td class="text-end"><?= number_format($t['deployed'] ?? 0) ?></td>
                <td class="text-end"><?= number_format($t['w_active'] ?? 0) ?></td><td class="text-end"><?= number_format($t['w_expired'] ?? 0) ?></td>
                <td class="text-end"><?= number_format($t['w_unknown'] ?? 0) ?></td><td class="text-end"><?= number_format($t['audited'] ?? 0) ?></td>
                <td class="text-end"><?= number_format($t['not_audited'] ?? 0) ?></td>
            </tr></tfoot>
        </table>
    </div>
</div>

<?php elseif ($tab === 'warranty'): $t = $totals; $activeAll = ($t['w_active'] ?? 0) + ($t['w_soon'] ?? 0); ?>
<form method="GET" action="/inventory/reports" class="d-flex align-items-center gap-2 flex-wrap mb-3">
    <input type="hidden" name="tab" value="warranty"><input type="hidden" name="by" value="<?= e($by) ?>">
    <label for="repDays" class="small text-muted">Ending within</label>
    <input type="number" id="repDays" name="days" min="1" max="3650" value="<?= (int) $days ?>" class="form-control form-control-sm" style="width:6rem;">
    <span class="small text-muted">days</span>
    <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
    <span class="small text-muted ms-2">or</span>
    <?php foreach ([30, 90, 180, 365] as $d): ?>
    <a href="/inventory/reports?<?= e(http_build_query(['tab' => 'warranty', 'by' => $by, 'days' => $d])) ?>" class="btn btn-sm <?= $d === $days ? 'btn-secondary' : 'btn-outline-secondary' ?>"><?= $d ?></a>
    <?php endforeach; ?>
</form>
<div class="row g-3 mb-4">
    <?= $tile('In warranty', $activeAll, $list(['warranty' => 'active']), 'success') ?>
    <?= $tile("Ends within {$days} days", $t['w_soon'] ?? 0, $list(['warranty' => 'soon', 'warranty_days' => $days]), 'warning', 'by ' . $day($until)) ?>
    <?= $tile('Expired', $t['w_expired'] ?? 0, $list(['warranty' => 'expired']), 'danger') ?>
    <?= $tile('Unknown', $t['w_unknown'] ?? 0, $list(['warranty' => 'unknown']), 'secondary',
        $lookupMf ? '<a href="' . e($list(['warranty' => 'unknown', 'manufacturer' => $lookupMf])) . '" class="text-decoration-none">' . number_format($t['w_lookup'] ?? 0) . ' Dell/Lenovo</a> not looked up' : '') ?>
    <?= $tile('Lookup failed', $t['w_error'] ?? 0, $list(['warranty' => 'error']), 'body') ?>
</div>

<?php if (isset($scan)): ?>
<div class="card border-0 shadow-sm mb-4" id="warrantyScan">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-1"><i class="bi bi-arrow-repeat me-1"></i>Look up warranties now</h6>
        <p class="text-muted small mb-3">
            <?= number_format(count($scan['all'])) ?> <?= $dellReady ? 'Dell and Lenovo' : 'Lenovo' ?> assets have a serial number<?= $dellReady ? '' : ' (Dell is skipped until a TechDirect key is saved in <a href="/admin/settings/inventory#warranty">Settings › Inventory</a>)' ?>;
            <?= number_format(count($scan['due'])) ?> of them were never looked up or were last looked up over <?= Warranty::RECHECK_DAYS ?> days ago.
            <?php if ($lastLookup): ?>Last answer <?= e($day($lastLookup)) ?>.<?php endif; ?>
            The daily Warranty Lookups job does 150 at a time; this does them all now. Keep this page open while it runs — Lenovo takes about two seconds an asset.
        </p>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" data-scan="all" <?= $scan['all'] ? '' : 'disabled' ?>><i class="bi bi-search me-1"></i>Look up all <?= number_format(count($scan['all'])) ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-scan="due" <?= $scan['due'] ? '' : 'disabled' ?>>Only the <?= number_format(count($scan['due'])) ?> due</button>
            <button type="button" class="btn btn-sm btn-outline-danger d-none" id="scanStop"><i class="bi bi-stop-fill me-1"></i>Stop</button>
        </div>
        <div class="d-none mt-3" id="scanProgress">
            <div class="progress" role="progressbar" aria-label="Warranty lookups done" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="scanBarWrap">
                <div class="progress-bar" id="scanBar" style="width:0%;background:var(--ld-primary);"></div>
            </div>
            <div class="small mt-2" id="scanStatus" aria-live="polite"></div>
            <pre class="small bg-light border rounded p-2 mt-2 mb-0" id="scanLog" style="max-height:220px;overflow:auto;white-space:pre-wrap;"></pre>
        </div>
    </div>
</div>
<script>
(function () {
    var ids = <?= json_encode($scan) ?>, token = <?= json_encode(csrfToken()) ?>;
    var running = false, stopping = false;
    var $ = function (id) { return document.getElementById(id); };
    function say(msg) { $('scanStatus').textContent = msg; }
    window.addEventListener('beforeunload', function (e) { if (running) { e.preventDefault(); e.returnValue = ''; } });
    $('scanStop').addEventListener('click', function () { stopping = true; this.disabled = true; say('Stopping after this batch…'); });
    document.querySelectorAll('[data-scan]').forEach(function (b) {
        b.addEventListener('click', function () { run(ids[b.getAttribute('data-scan')] || []); });
    });
    async function run(list) {
        if (running || !list.length) return;
        running = true; stopping = false;
        document.querySelectorAll('[data-scan]').forEach(function (b) { b.disabled = true; });
        $('scanStop').classList.remove('d-none'); $('scanStop').disabled = false;
        $('scanProgress').classList.remove('d-none'); $('scanLog').textContent = '';
        var found = 0, failed = 0, done = 0, why = '';
        for (var i = 0; i < list.length && !stopping; i += 10) {
            say('Looking up ' + (i + 1) + '–' + Math.min(i + 10, list.length) + ' of ' + list.length + '…');
            var fd = new FormData();
            fd.append('_token', token);
            list.slice(i, i + 10).forEach(function (id) { fd.append('ids[]', id); });
            var r;
            try {
                var res = await fetch('/inventory/warranty/scan', { method: 'POST', body: fd, credentials: 'same-origin' });
                r = await res.json();
            } catch (e) { why = 'the server did not answer (' + e.message + ')'; break; }
            if (r.error) { why = r.error; break; }
            found += r.found; failed += r.failed; done = Math.min(i + 10, list.length);
            if (r.lines.length) { $('scanLog').textContent += r.lines.join('\n') + '\n'; $('scanLog').scrollTop = $('scanLog').scrollHeight; }
            var p = Math.round(100 * done / list.length);
            $('scanBar').style.width = p + '%'; $('scanBarWrap').setAttribute('aria-valuenow', p);
            if (r.stalled) { why = 'Lenovo stopped answering (three failures in a row) — try again later'; break; }
        }
        running = false;
        $('scanStop').classList.add('d-none');
        say((why ? 'Stopped: ' + why + '. ' : (stopping ? 'Stopped. ' : 'Done. ')) + done + ' of ' + list.length + ' processed — '
            + found + ' with a warranty on record, ' + failed + ' without. Reload the page to update the counts.');
        document.querySelectorAll('[data-scan]').forEach(function (b) { b.disabled = false; });
    }
})();
</script>
<?php endif; ?>

<?= $groupBar(['days' => $days]) ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr>
                <th><?= e($dims[$by][0]) ?></th><th class="text-end">Assets</th><th class="text-end">In warranty</th>
                <th class="text-end">Ends ≤ <?= (int) $days ?> days</th><th class="text-end">Expired</th><th class="text-end">Unknown</th><th class="text-end">Lookup failed</th>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $r): $L = static fn(array $more = []) => inventoryReportLink($by, $r['gid'], $more); ?>
                <tr>
                    <td><?= $gname($r) ?></td>
                    <td class="text-end fw-semibold"><?= $cell($r['total'], $L()) ?></td>
                    <td class="text-end"><?= $cell((int) $r['w_active'] + (int) $r['w_soon'], $L(['warranty' => 'active'])) ?></td>
                    <td class="text-end"><?= $cell($r['w_soon'], $L(['warranty' => 'soon', 'warranty_days' => $days])) ?></td>
                    <td class="text-end"><?= $cell($r['w_expired'], $L(['warranty' => 'expired'])) ?></td>
                    <td class="text-end"><?= $cell($r['w_unknown'], $L(['warranty' => 'unknown'])) ?></td>
                    <td class="text-end"><?= $cell($r['w_error'], $L(['warranty' => 'error'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold"><tr>
                <td>Total</td><td class="text-end"><?= number_format($t['total'] ?? 0) ?></td><td class="text-end"><?= number_format($activeAll) ?></td>
                <td class="text-end"><?= number_format($t['w_soon'] ?? 0) ?></td><td class="text-end"><?= number_format($t['w_expired'] ?? 0) ?></td>
                <td class="text-end"><?= number_format($t['w_unknown'] ?? 0) ?></td><td class="text-end"><?= number_format($t['w_error'] ?? 0) ?></td>
            </tr></tfoot>
        </table>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Ending next</span>
                <a href="<?= e($list(['warranty' => 'soon', 'warranty_days' => $days, 'sort' => 'warranty_end'])) ?>" class="small text-decoration-none">All <?= number_format($t['w_soon'] ?? 0) ?> in the list →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" data-no-resize>
                    <thead class="table-light"><tr><th>Tag</th><th>Model</th><th><?= e($loc) ?></th><th>Assigned to</th><th>Ends</th></tr></thead>
                    <tbody>
                        <?php if (!$ending): ?><tr><td colspan="5" class="text-muted text-center py-3">No warranties end in the next <?= (int) $days ?> days.</td></tr><?php endif; ?>
                        <?php foreach ($ending as $a): ?>
                        <tr>
                            <td class="font-monospace"><a href="/inventory/<?= (int) $a['id'] ?>" class="text-decoration-none"><?= e($a['asset_tag']) ?></a></td>
                            <td><?= e($a['model_name']) ?></td>
                            <td class="text-muted"><?= e($a['location_name'] ?: '—') ?></td>
                            <td><?= Assets::assigneeHtml($a) ?></td>
                            <td class="text-nowrap"><?= e($day($a['warranty_end'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Why lookups failed</div>
            <ul class="list-group list-group-flush small">
                <?php if (!$errors): ?><li class="list-group-item text-muted">No failed lookups.</li><?php endif; ?>
                <?php foreach ($errors as $er): ?>
                <li class="list-group-item d-flex justify-content-between gap-2"><span class="min-w-0"><?= e($er['warranty_error']) ?></span><span class="fw-semibold"><?= (int) $er['n'] ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($errors): ?><div class="card-footer bg-white small"><a href="<?= e($list(['warranty' => 'error'])) ?>" class="text-decoration-none">See them in the list →</a></div><?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($tab === 'audit'): $t = $totals; ?>
<form method="GET" action="/inventory/reports" class="d-flex align-items-center gap-2 flex-wrap mb-3">
    <input type="hidden" name="tab" value="audit"><input type="hidden" name="by" value="<?= e($by) ?>">
    <label for="repSince" class="small text-muted">Not audited since</label>
    <input type="date" id="repSince" name="since" value="<?= e($since) ?>" class="form-control form-control-sm w-auto">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
    <span class="small text-muted ms-2">or the last</span>
    <?php foreach ([3, 6, 12, 24] as $m): $d = date('Y-m-d', strtotime("-{$m} months")); ?>
    <a href="/inventory/reports?<?= e(http_build_query(['tab' => 'audit', 'by' => $by, 'since' => $d])) ?>" class="btn btn-sm <?= $d === $since ? 'btn-secondary' : 'btn-outline-secondary' ?>"><?= $m ?> months</a>
    <?php endforeach; ?>
</form>
<div class="row g-3 mb-4">
    <?= $tile('Audited since ' . $day($since), $t['audited'] ?? 0, $list(['audit_after' => $since]), 'success') ?>
    <?= $tile('Not audited since', $t['not_audited'] ?? 0, $list(['audit_before' => $since]), 'warning', 'includes never audited') ?>
    <?= $tile('Never audited', $t['never'] ?? 0, $list(['audit' => 'never']), 'secondary') ?>
    <?= $tile('Next audit date passed', $t['overdue'] ?? 0, $list(['audit' => 'overdue']), 'danger') ?>
</div>
<?= $groupBar(['since' => $since], Assets::canManage() ? '<a href="/inventory/audit" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-upc-scan me-1"></i>Audit mode</a>' : '') ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr>
                <th><?= e($dims[$by][0]) ?></th><th class="text-end">Assets</th><th class="text-end">Audited</th><th class="text-end">Not audited</th>
                <th class="text-end">Never</th><th class="text-end">Overdue</th><th style="min-width:9rem;">Done</th>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $r): $L = static fn(array $more = []) => inventoryReportLink($by, $r['gid'], $more); $p = $pct((int) $r['audited'], (int) $r['total']); ?>
                <tr>
                    <td><?= $gname($r) ?></td>
                    <td class="text-end fw-semibold"><?= $cell($r['total'], $L()) ?></td>
                    <td class="text-end"><?= $cell($r['audited'], $L(['audit_after' => $since])) ?></td>
                    <td class="text-end"><?= $cell($r['not_audited'], $L(['audit_before' => $since])) ?></td>
                    <td class="text-end"><?= $cell($r['never'], $L(['audit' => 'never'])) ?></td>
                    <td class="text-end"><?= $cell($r['overdue'], $L(['audit' => 'overdue'])) ?></td>
                    <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:.5rem;" role="progressbar" aria-label="Audited" aria-valuenow="<?= $p ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:<?= $p ?>%;"></div></div><span class="small text-muted" style="width:2.5rem;"><?= $p ?>%</span></div></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold"><tr>
                <td>Total</td><td class="text-end"><?= number_format($t['total'] ?? 0) ?></td><td class="text-end"><?= number_format($t['audited'] ?? 0) ?></td>
                <td class="text-end"><?= number_format($t['not_audited'] ?? 0) ?></td><td class="text-end"><?= number_format($t['never'] ?? 0) ?></td>
                <td class="text-end"><?= number_format($t['overdue'] ?? 0) ?></td><td class="small text-muted"><?= $pct((int) ($t['audited'] ?? 0), (int) ($t['total'] ?? 0)) ?>%</td>
            </tr></tfoot>
        </table>
    </div>
</div>

<?php elseif ($tab === 'age'): $t = $totals; ?>
<p class="text-muted small">
    Age runs from the purchase date, or from the start of the manufacturer's warranty when there is no purchase date
    (<?= number_format((int) $source['purchased']) ?> assets have a purchase date, <?= number_format((int) $source['warranty']) ?> use the warranty start).
    Assets with neither are <em>Unknown</em> — look up warranties on the <a href="/inventory/reports?tab=warranty">Warranty</a> tab to fill in Dell and Lenovo.
</p>
<?= $groupBar() ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr>
                <th><?= e($dims[$by][0]) ?></th><th class="text-end">Assets</th>
                <?php foreach (ASSET_AGE_BUCKETS as $k => [$label, $min]): ?>
                <th class="text-end text-nowrap <?= $min !== null && $min >= 5 ? 'text-danger' : '' ?>"><?= e($label) ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= $gname($r) ?></td>
                    <td class="text-end fw-semibold"><?= $cell($r['total'], inventoryReportLink($by, $r['gid'])) ?></td>
                    <?php foreach (array_keys(ASSET_AGE_BUCKETS) as $k): ?>
                    <td class="text-end"><?= $cell($r[$k] ?? 0, inventoryReportLink($by, $r['gid'], ['age' => $k, 'sort' => 'acquired'])) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold"><tr>
                <td>Total</td><td class="text-end"><?= number_format($t['total'] ?? 0) ?></td>
                <?php foreach (array_keys(ASSET_AGE_BUCKETS) as $k): ?>
                <td class="text-end"><?= $cell($t[$k] ?? 0, $list(['age' => $k, 'sort' => 'acquired'])) ?></td>
                <?php endforeach; ?>
            </tr></tfoot>
        </table>
    </div>
</div>

<?php elseif ($tab === 'people'): ?>
<form method="GET" action="/inventory/reports" class="d-flex align-items-center gap-2 flex-wrap mb-3">
    <input type="hidden" name="tab" value="people">
    <label for="repCat" class="small text-muted">Category</label>
    <select id="repCat" name="category" class="form-select form-select-sm w-auto">
        <option value="0">Any</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $cat ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <label for="repMin" class="small text-muted">holding at least</label>
    <input type="number" id="repMin" name="min" min="1" value="<?= (int) $min ?>" class="form-control form-control-sm" style="width:5rem;">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
    <a href="<?= $csvUrl ?>" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-download me-1"></i>CSV</a>
</form>
<p class="text-muted small">
    <?= number_format(count($people)) ?> <?= count($people) === 1 ? 'person holds' : 'people hold' ?> <?= number_format(array_sum(array_map(static fn($p) => count($p['assets']), $people))) ?> assets<?= $min > 1 || $cat ? ' matching these settings' : '' ?>.
    Also <a href="<?= e($list(['assigned' => 'location'])) ?>" class="text-decoration-none"><?= number_format($toLocations) ?> checked out to a <?= e(strtolower($loc)) ?></a>
    and <a href="<?= e($list(['assigned' => 'import'])) ?>" class="text-decoration-none"><?= number_format($noAccount) ?> checked out in Snipe-IT to someone with no account here</a>.
</p>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr><th>Person</th><th><?= e($loc) ?></th><th class="text-end">Assets</th><th>Tags</th></tr></thead>
            <tbody>
                <?php if (!$people): ?><tr><td colspan="4" class="text-muted text-center py-4">Nobody matches.</td></tr><?php endif; ?>
                <?php foreach ($people as $p): ?>
                <tr>
                    <td><a href="/admin/users/<?= $p['uid'] ?>" class="text-decoration-none"><?= e($p['person']) ?></a><div class="small text-muted"><?= e($p['email']) ?></div></td>
                    <td class="text-muted"><?= e($p['location'] ?: '—') ?></td>
                    <td class="text-end fw-semibold"><?= count($p['assets']) ?></td>
                    <td><?php foreach ($p['assets'] as $a): ?><a href="/inventory/<?= (int) $a['id'] ?>" class="badge text-bg-light border text-decoration-none font-monospace me-1 mb-1" title="<?= e($a['category_name'] . ' — ' . $a['model_name']) ?>"><?= e($a['asset_tag']) ?></a><?php endforeach; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'activity'): ?>
<form method="GET" action="/inventory/reports" class="d-flex align-items-center gap-2 flex-wrap mb-3">
    <input type="hidden" name="tab" value="activity">
    <label for="repFrom" class="small text-muted">From</label>
    <input type="date" id="repFrom" name="from" value="<?= e($from) ?>" class="form-control form-control-sm w-auto">
    <label for="repTo" class="small text-muted">to</label>
    <input type="date" id="repTo" name="to" value="<?= e($to) ?>" class="form-control form-control-sm w-auto">
    <label for="repAction" class="small text-muted">Action</label>
    <select id="repAction" name="action" class="form-select form-select-sm w-auto">
        <option value="">Any</option>
        <?php foreach (array_unique(array_merge(['checkout', 'checkin', 'audit', 'update', 'create', 'delete', 'restore'], array_keys($byAction))) as $k): ?>
        <option value="<?= e($k) ?>" <?= $k === $action ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $k))) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button>
    <a href="<?= $csvUrl ?>" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-download me-1"></i>CSV</a>
</form>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($byAction as $k => $n): ?>
    <a href="/inventory/reports?<?= e(http_build_query(['tab' => 'activity', 'from' => $from, 'to' => $to, 'action' => $k === $action ? '' : $k])) ?>"
       class="badge rounded-pill text-decoration-none <?= $k === $action ? 'text-bg-primary' : 'text-bg-light border' ?>"><i class="bi <?= Assets::historyIcon((string) $k) ?> me-1"></i><?= e(ucfirst(str_replace('_', ' ', (string) $k))) ?> <?= number_format((int) $n) ?></a>
    <?php endforeach; ?>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr><th>When</th><th>Asset</th><th>What</th><th>By</th></tr></thead>
            <tbody>
                <?php if (!$history): ?><tr><td colspan="4" class="text-muted text-center py-4">No activity in this range.</td></tr><?php endif; ?>
                <?php foreach ($history as $h): $d = $h['details'] ? (json_decode((string) $h['details'], true) ?: []) : []; ?>
                <tr>
                    <td class="text-nowrap small text-muted"><?= e(date('M j, Y g:i a', strtotime($h['created_at']))) ?></td>
                    <td><a href="/inventory/<?= (int) $h['asset_id'] ?>?tab=history" class="text-decoration-none font-monospace"><?= e($h['asset_tag']) ?></a><div class="small text-muted"><?= e($h['model_name']) ?></div></td>
                    <td class="small">
                        <i class="bi <?= Assets::historyIcon((string) $h['action']) ?> text-muted me-1"></i><?= Assets::historyText((string) $h['action'], $d) ?>
                        <?php if ($fields = inventoryHistoryFields($d)): ?><span class="text-muted">: <?= e(str_replace('_', ' ', implode(', ', $fields))) ?></span><?php endif; ?>
                        <?php if (!empty($d['note'])): ?><div class="text-muted fst-italic"><?= e((string) $d['note']) ?></div><?php endif; ?>
                    </td>
                    <td class="small"><?= e($h['user_name'] ?: ($h['actor_name'] ?: '—')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="small text-muted"><?= number_format(($page - 1) * 100 + 1) ?>–<?= number_format(min($page * 100, $total)) ?> of <?= number_format($total) ?></span>
        <nav aria-label="Activity pages"><ul class="pagination pagination-sm mb-0">
            <?php foreach (['«' => $page - 1, '»' => $page + 1] as $txt => $pg): ?>
            <li class="page-item <?= $pg < 1 || $pg > $pages ? 'disabled' : '' ?>"><a class="page-link" href="/inventory/reports?<?= e(http_build_query(['tab' => 'activity', 'from' => $from, 'to' => $to, 'action' => $action, 'page' => $pg])) ?>"><?= $txt ?></a></li>
            <?php endforeach; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'quality'): $c = $counts; ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Records to tidy up <span class="text-muted fw-normal small">of <?= number_format((int) $c['total']) ?> assets</span></div>
            <ul class="list-group list-group-flush">
                <?php foreach ([
                    ['No serial number', $c['no_serial'], ['missing' => 'serial'], ''],
                    ['No ' . strtolower($loc), $c['no_location'], ['location' => ['none']], ''],
                    ['Model has no manufacturer', $c['no_manufacturer'], ['manufacturer' => ['none']], 'Warranty lookup needs the manufacturer set on the model.'],
                    ['No purchase date', $c['no_purchase_date'], ['missing' => 'purchase_date'], 'Age falls back to the warranty start.'],
                    ['Checked out in Snipe-IT to someone with no account here', $c['no_account'], ['assigned' => 'import'], ''],
                    ['Warranty lookup failed', $c['lookup_failed'], ['warranty' => 'error'], ''],
                ] as [$label, $n, $f, $hint]): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0"><?= e($label) ?><?php if ($hint): ?><div class="small text-muted"><?= e($hint) ?></div><?php endif; ?></div>
                    <span class="fw-semibold"><?= $cell($n, $list($f)) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Duplicate serial numbers</span>
                <?php if ($dupes): ?><a href="<?= $csvUrl ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>CSV</a><?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" data-no-resize>
                    <thead class="table-light"><tr><th>Serial</th><th class="text-end">Assets</th><th>Tags</th></tr></thead>
                    <tbody>
                        <?php if (!$dupes): ?><tr><td colspan="3" class="text-muted text-center py-3">No serial number is on two assets.</td></tr><?php endif; ?>
                        <?php foreach ($dupes as $group): ?>
                        <tr>
                            <td class="font-monospace small"><?= e($group[0]['serial']) ?></td>
                            <td class="text-end fw-semibold"><?= count($group) ?></td>
                            <td><?php foreach ($group as $r): ?><a href="/inventory/<?= (int) $r['id'] ?>" class="badge text-bg-light border text-decoration-none font-monospace me-1 mb-1" title="<?= e($r['model_name']) ?>"><?= e($r['asset_tag']) ?></a><?php endforeach; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Dell / Lenovo serials that cannot be looked up</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" data-no-resize>
                    <thead class="table-light"><tr><th>Tag</th><th>Model</th><th>Serial</th></tr></thead>
                    <tbody>
                        <?php if (!$badSerial): ?><tr><td colspan="3" class="text-muted text-center py-3">Every Dell and Lenovo serial is in a shape the manufacturer accepts.</td></tr><?php endif; ?>
                        <?php foreach ($badSerial as $r): ?>
                        <tr>
                            <td class="font-monospace"><a href="/inventory/<?= (int) $r['id'] ?>" class="text-decoration-none"><?= e($r['asset_tag']) ?></a></td>
                            <td><?= e($r['model_name']) ?> <span class="text-muted small"><?= e($r['mf']) ?></span></td>
                            <td class="font-monospace small"><?= e($r['serial']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">A Dell service tag is 7 letters and digits. A Lenovo serial needs at least 4; longer label scans are cut to their last 8. Spaces and hyphens are ignored.</div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'digest'): $s = $digest; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-envelope-paper me-1"></i>Scheduled expiry digest</span>
                <span class="badge <?= $s['enabled'] ? 'bg-success' : 'bg-secondary' ?>"><?= $s['enabled'] ? 'On' : 'Off' ?></span>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small">
                    One email listing warranties about to end, warranties that ended since the last digest, end-of-life dates coming up
                    and how many audits are overdue. Nothing is sent on a scheduled day with nothing to report.
                </p>
                <form method="POST" action="/inventory/reports/digest">
                    <?= csrfField() ?>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="dgEnabled" name="enabled" <?= $s['enabled'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="dgEnabled">Send the digest on a schedule</label>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label small fw-semibold mb-1" for="dgFreq">How often</label>
                            <select class="form-select form-select-sm" name="frequency" id="dgFreq">
                                <option value="monthly" <?= $s['frequency'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="weekly" <?= $s['frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            </select>
                        </div>
                        <div class="col-sm-4" data-dg="monthly">
                            <label class="form-label small fw-semibold mb-1" for="dgMonthday">Day of the month</label>
                            <select class="form-select form-select-sm" name="monthday" id="dgMonthday">
                                <?php for ($i = 1; $i <= 28; $i++): ?><option value="<?= $i ?>" <?= $s['frequency'] === 'monthly' && $s['day'] === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-sm-4" data-dg="weekly">
                            <label class="form-label small fw-semibold mb-1" for="dgWeekday">Day of the week</label>
                            <select class="form-select form-select-sm" name="weekday" id="dgWeekday">
                                <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $i => $name): ?>
                                <option value="<?= $i + 1 ?>" <?= $s['frequency'] === 'weekly' && $s['day'] === $i + 1 ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small fw-semibold mb-1" for="dgDays">Look ahead</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" name="days" id="dgDays" min="1" max="730" value="<?= (int) $s['days'] ?>">
                                <span class="input-group-text">days</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-label small fw-semibold mb-1">Send to</div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dgAdmins" name="admins" <?= $s['admins'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="dgAdmins">All administrators</label>
                        </div>
                        <label class="form-label small text-muted mt-2 mb-1" for="dgRecipients">Also send to (email addresses, separated by commas)</label>
                        <input type="text" class="form-control form-control-sm" name="recipients" id="dgRecipients" value="<?= e($s['recipients']) ?>" placeholder="it-team@example.org, manager@example.org">
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);"><i class="bi bi-check-lg me-1"></i>Save schedule</button>
                        <button type="submit" name="action" value="test" class="btn btn-sm btn-outline-secondary" formnovalidate title="Uses the saved settings and does not move the schedule"><i class="bi bi-send me-1"></i>Send me a test now</button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-white small text-muted">
                <?php if ($next): ?>Next digest: <strong><?= $next === $today ? 'at the next run (7:30 am)' : e($day($next)) ?></strong>.<?php else: ?>Switched off — nothing will be sent.<?php endif; ?>
                <?php if ($s['last_sent']): ?> Last <?= $s['enabled'] ? 'sent or started' : 'sent' ?> <?= e($day($s['last_sent'])) ?>.<?php endif; ?>
                Sent by the <a href="/admin/settings/cron-jobs">Inventory Digest</a> job, which must be in the server's crontab.
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">If it were sent today</div>
            <ul class="list-group list-group-flush">
                <?php foreach ([
                    ["Warranties ending within {$preview['days']} days", count($preview['ending']), $list(['warranty' => 'soon', 'warranty_days' => $preview['days'], 'sort' => 'warranty_end'])],
                    ['Warranties ended since ' . $day($preview['since']), count($preview['expired']), $list(['warranty' => 'expired', 'sort' => 'warranty_end', 'dir' => 'desc'])],
                    ["End-of-life dates within {$preview['days']} days", count($preview['eol']), null],
                    ['Audits overdue', $preview['audit_overdue'], $list(['audit' => 'overdue'])],
                ] as [$label, $n, $url]): ?>
                <li class="list-group-item d-flex justify-content-between gap-2"><span class="min-w-0"><?= e($label) ?></span><span class="fw-semibold"><?= $cell($n, $url) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Goes to <span class="text-muted fw-normal small">(<?= count($recipients) ?>)</span></div>
            <ul class="list-group list-group-flush small">
                <?php if (!$recipients): ?><li class="list-group-item text-muted">Nobody yet.</li><?php endif; ?>
                <?php foreach ($recipients as [$email, $name]): ?>
                <li class="list-group-item"><?= e($name !== '' ? $name . ' — ' : '') ?><span class="text-muted"><?= e($email) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<script>
(function () {
    var f = document.getElementById('dgFreq');
    function show() { document.querySelectorAll('[data-dg]').forEach(function (el) { el.classList.toggle('d-none', el.getAttribute('data-dg') !== f.value); }); }
    f.addEventListener('change', show);
    show();
})();
</script>
<?php endif; ?>
