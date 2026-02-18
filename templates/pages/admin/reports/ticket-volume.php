<?php
$layout       = 'app';
$pageTitle    = 'Ticket Volume – Reports';
$sidebarItems = adminSidebar('reports');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Ticket Volume'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Ticket Creation &amp; Volume</p>
</div>

<?php require ROOT_DIR . '/templates/partials/reports-nav.php'; ?>

<form class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;">
    <span class="text-muted">to</span>
    <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;">
    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
</form>

<!-- Volume over time chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-graph-up me-2"></i>Tickets Created Over Time</h5>
    </div>
    <div class="card-body">
        <canvas id="volumeChart" height="80"></canvas>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- By Priority -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0 fw-semibold">By Priority</h6></div>
            <div class="card-body">
                <?php if (empty($byPriority)): ?>
                    <p class="text-muted text-center py-3 mb-0">No data.</p>
                <?php else: ?>
                    <?php foreach ($byPriority as $row): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge" style="background:<?= e($row['color']) ?>;"><?= e($row['name']) ?></span>
                        <span class="fw-bold"><?= $row['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- By Type -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0 fw-semibold">By Type</h6></div>
            <div class="card-body">
                <?php if (empty($byType)): ?>
                    <p class="text-muted text-center py-3 mb-0">No data.</p>
                <?php else: ?>
                    <?php foreach ($byType as $row): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted"><?= e($row['name']) ?></span>
                        <span class="fw-bold"><?= $row['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- By Location -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0 fw-semibold">By Location</h6></div>
            <div class="card-body">
                <?php if (empty($byLocation)): ?>
                    <p class="text-muted text-center py-3 mb-0">No data.</p>
                <?php else: ?>
                    <?php foreach ($byLocation as $row): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted"><?= e($row['name']) ?></span>
                        <span class="fw-bold"><?= $row['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- By Location bar chart -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2"></i>Tickets by Location</h5>
    </div>
    <div class="card-body">
        <canvas id="locationChart" height="80"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('volumeChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($dailyVolume, 'date_label')) ?>,
        datasets: [{
            label: 'Tickets Created',
            data: <?= json_encode(array_map('intval', array_column($dailyVolume, 'count'))) ?>,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79,70,229,0.1)',
            fill: true,
            tension: 0.3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

new Chart(document.getElementById('locationChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($byLocation, 'name')) ?>,
        datasets: [{
            label: 'Tickets',
            data: <?= json_encode(array_map('intval', array_column($byLocation, 'count'))) ?>,
            backgroundColor: 'rgba(79,70,229,0.7)',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
