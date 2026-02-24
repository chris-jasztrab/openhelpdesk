<?php
$layout       = 'app';
$pageTitle    = 'First Contact Resolution – Reports';
$sidebarItems = adminSidebar('reports');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'FCR Rate'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">First Contact Resolution (FCR) Rate</p>
</div>


<!-- Date range filter -->
<form class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;">
    <span class="text-muted">to</span>
    <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;">
    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
</form>

<div class="alert alert-light border small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    <strong>FCR definition:</strong> A ticket counts as first-contact resolved if it was resolved/closed with
    at most <strong>1 agent reply</strong> — i.e., the issue was handled without extended back-and-forth.
</div>

<!-- KPI card -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-4">
                <div class="display-4 fw-bold" style="color:var(--ld-primary);"><?= $overallFcr['pct'] ?>%</div>
                <div class="text-muted small mt-1">Overall FCR Rate</div>
                <div class="progress mt-3" style="height:8px;">
                    <div class="progress-bar" style="width:<?= $overallFcr['pct'] ?>%; background:var(--ld-primary);"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-4">
                <div class="display-4 fw-bold text-success"><?= $overallFcr['fcr'] ?></div>
                <div class="text-muted small mt-1">FCR Tickets</div>
                <div class="text-muted" style="font-size:.8rem;">resolved in 1 interaction</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-4">
                <div class="display-4 fw-bold text-secondary"><?= $overallFcr['total'] ?></div>
                <div class="text-muted small mt-1">Total Resolved</div>
                <div class="text-muted" style="font-size:.8rem;">in the selected period</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- FCR by Agent -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">FCR by Agent</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Agent</th>
                            <th class="text-center">Resolved</th>
                            <th class="text-center">FCR</th>
                            <th class="text-center">FCR %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fcrByAgent)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No data.</td></tr>
                        <?php else: ?>
                            <?php foreach ($fcrByAgent as $a): ?>
                            <tr>
                                <td><?= e($a['agent_name']) ?></td>
                                <td class="text-center"><?= (int)$a['total_resolved'] ?></td>
                                <td class="text-center text-success fw-semibold"><?= (int)$a['fcr_count'] ?></td>
                                <td class="text-center">
                                    <?php $c = $a['fcr_pct'] >= 70 ? 'success' : ($a['fcr_pct'] >= 40 ? 'warning' : 'danger'); ?>
                                    <span class="badge bg-<?= $c ?> bg-opacity-15 text-<?= $c ?>"><?= $a['fcr_pct'] ?>%</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FCR by Type -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">FCR by Ticket Type</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th class="text-center">Resolved</th>
                            <th class="text-center">FCR</th>
                            <th class="text-center">FCR %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fcrByType)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No data.</td></tr>
                        <?php else: ?>
                            <?php foreach ($fcrByType as $r): ?>
                            <tr>
                                <td><?= e($r['type_name']) ?></td>
                                <td class="text-center"><?= (int)$r['total_resolved'] ?></td>
                                <td class="text-center text-success fw-semibold"><?= (int)$r['fcr_count'] ?></td>
                                <td class="text-center">
                                    <?php $c = $r['fcr_pct'] >= 70 ? 'success' : ($r['fcr_pct'] >= 40 ? 'warning' : 'danger'); ?>
                                    <span class="badge bg-<?= $c ?> bg-opacity-15 text-<?= $c ?>"><?= $r['fcr_pct'] ?>%</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Weekly trend -->
<?php if (!empty($weeklyFcr)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-graph-up me-2"></i>Weekly FCR Trend</h5>
    </div>
    <div class="card-body">
        <canvas id="fcrTrendChart" height="80"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('fcrTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($weeklyFcr, 'week_label')) ?>,
        datasets: [{
            label: 'FCR %',
            data: <?= json_encode(array_map(fn($r) => (int)$r['fcr_pct'], $weeklyFcr)) ?>,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79,70,229,0.1)',
            fill: true,
            tension: 0.3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } }
        }
    }
});
</script>
<?php endif; ?>
