<?php
$layout       = 'app';
$pageTitle    = 'Location Report – Reports';
$sidebarItems = adminSidebar('reports');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'By Location'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">By Location</p>
</div>

<?php require ROOT_DIR . '/templates/partials/reports-nav.php'; ?>

<form class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;">
    <span class="text-muted">to</span>
    <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;">
    <button type="submit" class="btn btn-sm text-white" style="background:#4f46e5;">Apply</button>
</form>

<!-- Chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2"></i>Tickets by Location</h5>
    </div>
    <div class="card-body">
        <canvas id="locChart" height="80"></canvas>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-table me-2"></i>Location Breakdown</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Location</th>
                    <th class="text-center">Total Tickets</th>
                    <th class="text-center">Open</th>
                    <th class="text-center">Resolved</th>
                    <th class="text-center">Resolution Rate</th>
                    <th class="text-center">Avg Resolution Time</th>
                    <th class="text-center">SLA Compliance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($locations)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No location data for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td class="fw-semibold"><i class="bi bi-geo-alt text-muted me-1"></i><?= e($loc['location_name']) ?></td>
                        <td class="text-center"><?= $loc['total'] ?></td>
                        <td class="text-center">
                            <span class="badge bg-warning bg-opacity-10 text-warning"><?= $loc['open_count'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success bg-opacity-10 text-success"><?= $loc['resolved'] ?></span>
                        </td>
                        <td class="text-center">
                            <?php $r = $loc['resolution_rate']; $c = $r >= 80 ? 'success' : ($r >= 50 ? 'warning' : 'danger'); ?>
                            <span class="badge bg-<?= $c ?> bg-opacity-10 text-<?= $c ?>"><?= $r ?>%</span>
                        </td>
                        <td class="text-center"><?= e($loc['avg_resolution']) ?></td>
                        <td class="text-center">
                            <?php $s = $loc['sla_compliance']; $sc = $s >= 90 ? 'success' : ($s >= 70 ? 'warning' : 'danger'); ?>
                            <span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?>"><?= $s ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('locChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($locations, 'location_name')) ?>,
        datasets: [
            {
                label: 'Resolved',
                data: <?= json_encode(array_map('intval', array_column($locations, 'resolved'))) ?>,
                backgroundColor: '#10b981',
            },
            {
                label: 'Open',
                data: <?= json_encode(array_map('intval', array_column($locations, 'open_count'))) ?>,
                backgroundColor: '#f59e0b',
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
