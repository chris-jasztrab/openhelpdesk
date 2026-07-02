<?php
$layout       = 'app';
$pageTitle    = 'Agent Workload – Reports';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Agent Workload'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Agent Workload Heatmap</p>
</div>


<p class="text-muted small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    Showing current open tickets (excludes resolved &amp; closed). Not filtered by date.
</p>

<?php if (empty($agents)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-people fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-0">No open tickets at this time.</p>
    </div>
</div>
<?php else: ?>

<!-- Horizontal stacked bar chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-steps me-2"></i>Open Tickets by Agent</h5>
    </div>
    <div class="card-body">
        <canvas id="workloadChart" height="<?= max(60, count($agents) * 28) ?>"></canvas>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-table me-2"></i>Agent Breakdown</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Agent</th>
                    <th class="text-center">Total Open</th>
                    <th class="text-center">In Progress</th>
                    <th class="text-center">Pending</th>
                    <th class="text-center">Waiting</th>
                    <th class="text-center">SLA Breached</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $a): ?>
                <tr>
                    <td class="fw-semibold"><?= e($a['agent_name']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary bg-opacity-15 text-dark"><?= (int)$a['open_total'] ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-primary bg-opacity-10 text-primary"><?= (int)$a['in_progress_count'] ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-warning bg-opacity-10 text-warning"><?= (int)$a['pending_count'] ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-info bg-opacity-10 text-info"><?= (int)$a['waiting_count'] ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($a['breached_count'] > 0): ?>
                            <span class="badge bg-danger"><?= (int)$a['breached_count'] ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('workloadChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($agents, 'agent_name')) ?>,
        datasets: [
            {
                label: 'In Progress',
                data: <?= json_encode(array_map('intval', array_column($agents, 'in_progress_count'))) ?>,
                backgroundColor: 'rgba(79,70,229,0.7)',
            },
            {
                label: 'Pending',
                data: <?= json_encode(array_map('intval', array_column($agents, 'pending_count'))) ?>,
                backgroundColor: 'rgba(245,158,11,0.7)',
            },
            {
                label: 'Waiting',
                data: <?= json_encode(array_map('intval', array_column($agents, 'waiting_count'))) ?>,
                backgroundColor: 'rgba(6,182,212,0.7)',
            },
            {
                label: 'Open',
                data: <?= json_encode(array_map('intval', array_column($agents, 'open_count'))) ?>,
                backgroundColor: 'rgba(100,116,139,0.4)',
            },
            {
                label: 'SLA Breached',
                data: <?= json_encode(array_map('intval', array_column($agents, 'breached_count'))) ?>,
                backgroundColor: 'rgba(239,68,68,0.8)',
            },
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            x: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } },
            y: { stacked: true }
        }
    }
});
</script>

<?php endif; ?>
