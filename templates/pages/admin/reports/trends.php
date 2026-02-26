<?php
$layout              = 'app';
$pageTitle           = 'Ticket Trends – Reports';
$scheduleReportType  = 'trends';
$scheduleReportTitle = 'Ticket Trends';
$sidebarItems        = adminSidebar('reports');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Ticket Trends'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Ticket Trends</p>
</div>


<!-- Filters -->
<form class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <i class="bi bi-calendar3 text-muted"></i>
    <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;">
    <span class="text-muted">to</span>
    <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;">
    <input type="hidden" name="group_by" value="<?= e($groupBy) ?>">
    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scheduleReportModal">
        <i class="bi bi-calendar-plus me-1"></i>Schedule
    </button>
    <div class="ms-2 d-flex gap-1">
        <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&group_by=type"
           class="btn btn-sm <?= $groupBy === 'type' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            By Type
        </a>
        <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&group_by=location"
           class="btn btn-sm <?= $groupBy === 'location' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            By Location
        </a>
    </div>
</form>

<?php if (empty($labels)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-graph-up fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-0">No ticket data for this period.</p>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-graph-up me-2"></i>
            Tickets by <?= $groupBy === 'location' ? 'Location' : 'Type' ?> Over Time
        </h5>
        <span class="badge bg-light text-muted border"><?= $from ?> – <?= $to ?></span>
    </div>
    <div class="card-body">
        <canvas id="trendsChart" height="100"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('trendsChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: <?= json_encode($datasets) ?>,
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
<?php endif; ?>
<?php require ROOT_DIR . '/templates/partials/schedule-report-modal.php'; ?>
