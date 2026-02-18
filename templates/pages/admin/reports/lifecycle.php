<?php
$layout       = 'app';
$pageTitle    = 'Ticket Lifecycle – Reports';
$sidebarItems = adminSidebar('reports');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Ticket Lifecycle'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Ticket Lifecycle</p>
</div>

<?php require ROOT_DIR . '/templates/partials/reports-nav.php'; ?>

<form class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;">
    <span class="text-muted">to</span>
    <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;">
    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
</form>

<!-- Average time per status -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-hourglass-split me-2"></i>Average Time in Each Status</h5>
    </div>
    <div class="card-body">
        <canvas id="lifecycleChart" height="80"></canvas>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0 fw-semibold">Time per Status (Overall)</h6></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th class="text-center">Avg Duration</th>
                            <th class="text-center">Transitions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statusDurations as $s): ?>
                        <tr>
                            <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?></span></td>
                            <td class="text-center fw-semibold"><?= e($s['avg_duration']) ?></td>
                            <td class="text-center text-muted"><?= $s['transitions'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0 fw-semibold">Time per Status by Priority</h6></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Priority</th>
                            <th class="text-center">Avg to First Response</th>
                            <th class="text-center">Avg to Resolution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($byPriority)): ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">No data.</td></tr>
                        <?php else: ?>
                            <?php foreach ($byPriority as $row): ?>
                            <tr>
                                <td><span class="badge" style="background:<?= e($row['priority_color']) ?>;"><?= e($row['priority_name']) ?></span></td>
                                <td class="text-center"><?= e($row['avg_to_first_response']) ?></td>
                                <td class="text-center"><?= e($row['avg_to_resolution']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Status transitions -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Status Transitions</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>From</th>
                    <th>To</th>
                    <th class="text-center">Count</th>
                    <th class="text-center">Avg Time Between</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transitions)): ?>
                <tr><td colspan="4" class="text-center py-4 text-muted">No transition data.</td></tr>
                <?php else: ?>
                    <?php foreach ($transitions as $t): ?>
                    <tr>
                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e(ucfirst(str_replace('_', ' ', $t['from_status']))) ?></span></td>
                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= e(ucfirst(str_replace('_', ' ', $t['to_status']))) ?></span></td>
                        <td class="text-center fw-semibold"><?= $t['count'] ?></td>
                        <td class="text-center text-muted"><?= e($t['avg_duration']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('lifecycleChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($s) => ucfirst(str_replace('_', ' ', $s['status'])), $statusDurations)) ?>,
        datasets: [{
            label: 'Avg Hours',
            data: <?= json_encode(array_column($statusDurations, 'avg_hours')) ?>,
            backgroundColor: ['#4f46e5', '#f59e0b', '#8b5cf6', '#10b981', '#6b7280'],
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Hours' } } }
    }
});
</script>
