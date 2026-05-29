<?php
$layout              = 'app';
$pageTitle           = 'Agent Performance – Reports';
$scheduleReportType  = 'agent_performance';
$scheduleReportTitle = 'Agent Performance';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Agent Performance'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Agent Performance</p>
</div>


<!-- Date range filter -->
<form class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;">
    <span class="text-muted">to</span>
    <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;">
    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scheduleReportModal">
        <i class="bi bi-calendar-plus me-1"></i>Schedule
    </button>
</form>
<?php require ROOT_DIR . '/templates/partials/schedule-report-modal.php'; ?>

<!-- Chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2"></i>Tickets Resolved per Agent</h5>
    </div>
    <div class="card-body">
        <canvas id="agentChart" height="80"></canvas>
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
                    <th class="text-center">Assigned</th>
                    <th class="text-center">Resolved</th>
                    <th class="text-center">Open</th>
                    <th class="text-center">Avg First Response</th>
                    <th class="text-center">Avg Resolution</th>
                    <th class="text-center">SLA Compliance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agents)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No agent data for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($agents as $a): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($a['agent_name']) ?></td>
                        <td class="text-center"><?= $a['assigned'] ?></td>
                        <td class="text-center">
                            <span class="badge bg-success bg-opacity-10 text-success"><?= $a['resolved'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-warning bg-opacity-10 text-warning"><?= $a['open_count'] ?></span>
                        </td>
                        <td class="text-center"><?= $a['avg_first_response'] ?></td>
                        <td class="text-center"><?= $a['avg_resolution'] ?></td>
                        <td class="text-center">
                            <?php
                            $pct = $a['sla_compliance'];
                            $color = $pct >= 90 ? 'success' : ($pct >= 70 ? 'warning' : 'danger');
                            ?>
                            <span class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>"><?= $pct ?>%</span>
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
new Chart(document.getElementById('agentChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($agents, 'agent_name')) ?>,
        datasets: [
            {
                label: 'Resolved',
                data: <?= json_encode(array_map('intval', array_column($agents, 'resolved'))) ?>,
                backgroundColor: 'rgba(79,70,229,0.7)',
            },
            {
                label: 'Assigned',
                data: <?= json_encode(array_map('intval', array_column($agents, 'assigned'))) ?>,
                backgroundColor: 'rgba(79,70,229,0.2)',
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
