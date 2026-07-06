<?php
$layout              = 'app';
$pageTitle           = 'AI Token Usage – Reports';
$sidebarItems        = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'AI Token Usage'],
];

$granOptions = ['hour' => 'Hourly', 'day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'];
$hasData     = ($stats[0]['cur'] ?? 0) > 0 || ($stats[0]['prev'] ?? 0) > 0;
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">AI Token Usage</p>
</div>

<!-- Filters -->
<form class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <i class="bi bi-calendar3 text-muted"></i>
    <?php require ROOT_DIR . '/templates/partials/report-date-range.php'; ?>

    <span class="text-muted ms-2">Bucket</span>
    <select name="granularity" class="form-select form-select-sm" style="width:auto;" aria-label="Bucket granularity">
        <?php foreach ($granOptions as $key => $lbl): ?>
        <option value="<?= e($key) ?>" <?= $gran === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
    </select>

    <span class="text-muted ms-2" title="Leave blank to auto-compare against the equal-length window immediately before the selected range.">Compare to</span>
    <input type="date" name="cmp_from" value="<?= e($cmpFrom) ?>" class="form-control form-control-sm" style="width:auto;" aria-label="Compare from date">
    <span class="text-muted">to</span>
    <input type="date" name="cmp_to" value="<?= e($cmpTo) ?>" class="form-control form-control-sm" style="width:auto;" aria-label="Compare to date">

    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
</form>

<?php if (!$hasData): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-cpu fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-1">No AI token usage recorded for this period or the comparison period.</p>
        <p class="mb-0 small">Usage is logged whenever AI skill classification, group routing, or duplicate detection runs.</p>
    </div>
</div>
<?php else: ?>

<!-- Summary stat cards: current window vs comparison window -->
<div class="row g-3 mb-4">
    <?php foreach ($stats as $s):
        $diff = $s['cur'] - $s['prev'];
        if ($s['prev'] == 0) {
            $deltaText  = $s['cur'] > 0 ? 'New' : '—';
            $deltaClass = $s['cur'] > 0 ? 'text-success' : 'text-muted';
            $arrow      = '';
        } else {
            $pct        = round($diff / $s['prev'] * 100, 1);
            // Higher AI consumption = higher cost, so up trends read as "warning".
            $deltaText  = ($diff > 0 ? '+' : '') . $pct . '%';
            $deltaClass = $diff > 0 ? 'text-danger' : ($diff < 0 ? 'text-success' : 'text-muted');
            $arrow      = $diff > 0 ? 'bi-arrow-up' : ($diff < 0 ? 'bi-arrow-down' : '');
        }
    ?>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi <?= e($s['icon']) ?>"></i>
                </div>
                <div>
                    <div class="text-muted small"><?= e($s['label']) ?></div>
                    <div class="fs-4 fw-bold"><?= number_format((int) $s['cur']) ?></div>
                    <div class="small <?= $deltaClass ?>">
                        <?php if ($arrow): ?><i class="bi <?= $arrow ?>"></i> <?php endif; ?>
                        <?= e($deltaText) ?>
                        <span class="text-muted">vs <?= number_format((int) $s['prev']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Comparison chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-graph-up me-2"></i>
            Total tokens by <?= e($cfg['axis']) ?>
        </h5>
        <div class="d-flex gap-1">
            <span class="badge bg-light text-muted border"><?= e($from) ?> – <?= e($to) ?></span>
            <span class="badge bg-light text-muted border">vs <?= e($cmpFrom) ?> – <?= e($cmpTo) ?></span>
        </div>
    </div>
    <div class="card-body">
        <canvas id="aiUsageChart" height="100"></canvas>
    </div>
</div>

<!-- Breakdown tables -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>By feature</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Feature</th>
                            <th class="text-end">Calls</th>
                            <th class="text-end">Input</th>
                            <th class="text-end">Output</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bySource as $r): $tot = (int) $r['in_tok'] + (int) $r['out_tok']; ?>
                        <tr>
                            <td><?= e($r['source']) ?></td>
                            <td class="text-end"><?= number_format((int) $r['calls']) ?></td>
                            <td class="text-end"><?= number_format((int) $r['in_tok']) ?></td>
                            <td class="text-end"><?= number_format((int) $r['out_tok']) ?></td>
                            <td class="text-end fw-semibold"><?= number_format($tot) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bySource)): ?>
                        <tr><td colspan="5" class="text-muted text-center py-3">No usage in this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-robot me-2"></i>By provider &amp; model</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Provider</th>
                            <th>Model</th>
                            <th class="text-end">Calls</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byModel as $r): $tot = (int) $r['in_tok'] + (int) $r['out_tok']; ?>
                        <tr>
                            <td><?= e($r['provider']) ?></td>
                            <td><?= e($r['model']) ?></td>
                            <td class="text-end"><?= number_format((int) $r['calls']) ?></td>
                            <td class="text-end fw-semibold"><?= number_format($tot) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($byModel)): ?>
                        <tr><td colspan="4" class="text-muted text-center py-3">No usage in this period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
(function () {
    var prevLabels = <?= json_encode($prevLabels) ?>;
    new Chart(document.getElementById('aiUsageChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Current period',
                    data: <?= json_encode($curTotal) ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: '#4f46e522',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Comparison period',
                    data: <?= json_encode($prevTotal) ?>,
                    borderColor: '#94a3b8',
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    fill: false,
                    tension: 0.3,
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        afterBody: function (items) {
                            var i = items[0].dataIndex;
                            var pl = prevLabels[i];
                            return pl ? 'Comparison bucket: ' + pl : '';
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Tokens' } }
            }
        }
    });
})();
</script>
<?php endif; ?>
