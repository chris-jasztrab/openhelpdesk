<?php
$layout              = 'app';
$pageTitle           = 'Response Times – Reports';
$scheduleReportType  = 'response_times';
$scheduleReportTitle = 'Response Times';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Response Times'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Resolution &amp; Response Times</p>
</div>


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

<!-- Overall averages -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-reply"></i></div>
                <div>
                    <div class="text-muted small">Avg First Response</div>
                    <div class="fs-4 fw-bold"><?= e($overallFirstResponse) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="text-muted small">Avg Resolution Time</div>
                    <div class="fs-4 fw-bold"><?= e($overallResolution) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-ticket-detailed"></i></div>
                <div>
                    <div class="text-muted small">Tickets Measured</div>
                    <div class="fs-4 fw-bold"><?= $ticketsMeasured ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Trend chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-graph-up me-2"></i>Weekly Trend</h5>
    </div>
    <div class="card-body">
        <canvas id="trendChart" height="80"></canvas>
    </div>
</div>

<!-- By priority -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-table me-2"></i>By Priority</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Priority</th>
                    <th class="text-center">Tickets</th>
                    <th class="text-center">Avg First Response</th>
                    <th class="text-center">Avg Resolution Time</th>
                    <th class="text-center">Fastest Resolution</th>
                    <th class="text-center">Slowest Resolution</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($byPriority)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No data for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($byPriority as $row): ?>
                    <tr>
                        <td>
                            <span class="badge" style="background:<?= e($row['priority_color']) ?>;">
                                <?= e($row['priority_name']) ?>
                            </span>
                        </td>
                        <td class="text-center"><?= $row['ticket_count'] ?></td>
                        <td class="text-center"><?= $row['avg_first_response'] ?></td>
                        <td class="text-center"><?= $row['avg_resolution'] ?></td>
                        <td class="text-center"><?= $row['fastest'] ?></td>
                        <td class="text-center"><?= $row['slowest'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($weeklyTrend, 'week_label')) ?>,
        datasets: [
            {
                label: 'Avg First Response (hrs)',
                data: <?= json_encode(array_column($weeklyTrend, 'avg_response_hrs')) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79,70,229,0.1)',
                fill: true,
                tension: 0.3,
            },
            {
                label: 'Avg Resolution (hrs)',
                data: <?= json_encode(array_column($weeklyTrend, 'avg_resolution_hrs')) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                fill: true,
                tension: 0.3,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Hours' } } }
    }
});
</script>
