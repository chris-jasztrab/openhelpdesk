<?php
$layout       = 'app';
$pageTitle    = 'Custom Report – Reports';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Custom Builder'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Custom Report Builder</p>
</div>


<!-- Builder form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-sliders me-2"></i>Build Your Report</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-sm-auto">
                <label class="form-label small fw-semibold mb-1">Metric</label>
                <select name="metric" class="form-select form-select-sm">
                    <?php foreach ($metricOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $metric === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label small fw-semibold mb-1">Group By</label>
                <select name="group_by" class="form-select form-select-sm">
                    <?php foreach ($groupByOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $groupBy === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label small fw-semibold mb-1">Quick range</label>
                <select name="range" class="form-select form-select-sm js-report-range">
                    <?php foreach (reportRangePresets() as $rKey => $rLabel): ?>
                    <option value="<?= e($rKey) ?>" <?= ($range ?? 'custom') === $rKey ? 'selected' : '' ?>><?= e($rLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm">
            </div>
            <div class="col-sm-auto">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm">
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-play-fill me-1"></i>Run Report
                </button>
            </div>
        </form>
        <?php require ROOT_DIR . '/templates/partials/report-range-script.php'; ?>
    </div>
</div>

<?php if (!$metric || !$groupBy): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bar-chart-line fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-0">Select a metric and group-by field above, then click <strong>Run Report</strong>.</p>
    </div>
</div>

<?php elseif (empty($rows)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-0">No data found for this combination and date range.</p>
    </div>
</div>

<?php else: ?>
<!-- Chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-bar-chart me-2"></i>
            <?= e($metricLabel) ?> by <?= e($groupByOptions[$groupBy]) ?>
        </h5>
    </div>
    <div class="card-body">
        <canvas id="customChart" height="80"></canvas>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-table me-2"></i>Data Table</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= e($groupByOptions[$groupBy]) ?></th>
                    <th class="text-end"><?= e($metricLabel) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['label']) ?></td>
                    <td class="text-end fw-semibold"><?= e($row['display']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('customChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($rows, 'label')) ?>,
        datasets: [{
            label: <?= json_encode($metricLabel) ?>,
            data: <?= json_encode(array_map(fn($r) => (float)$r['raw'], $rows)) ?>,
            backgroundColor: 'rgba(79,70,229,0.7)',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>
<?php endif; ?>
