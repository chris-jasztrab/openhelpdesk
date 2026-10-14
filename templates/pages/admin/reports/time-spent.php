<?php
$layout       = 'app';
$pageTitle    = 'Time Spent – Reports';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Time Spent'],
];
$ticketBase = Auth::isAdmin() ? '/admin/tickets/' : '/agent/tickets/';
$total      = (int) $kpi['total'];
$avgTicket  = (int) $kpi['tickets'] > 0 ? intdiv($total, (int) $kpi['tickets']) : 0;
$kpiCards   = [
    ['icon' => 'bi-hourglass-split', 'color' => 'primary', 'label' => 'Total Time Logged', 'value' => formatTrackedTime($total)],
    ['icon' => 'bi-ticket-detailed', 'color' => 'success', 'label' => 'Tickets Worked',    'value' => (int) $kpi['tickets']],
    ['icon' => 'bi-stopwatch',       'color' => 'info',    'label' => 'Avg per Ticket',    'value' => formatTrackedTime($avgTicket)],
    ['icon' => 'bi-people',          'color' => 'warning', 'label' => 'Agents Logging',    'value' => (int) $kpi['agents']],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Time Spent on Tickets</p>
</div>

<!-- Date range filter -->
<form class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <?php require ROOT_DIR . '/templates/partials/report-date-range.php'; ?>
    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
    <?php require ROOT_DIR . '/templates/partials/report-export-csv.php'; ?>
</form>

<?php if ($total === 0): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>No time was logged in this period.
    Time tracking is switched on per ticket type under <strong>Admin &rarr; Ticket Types</strong> (&ldquo;Track time spent on these tickets&rdquo;).
</div>
<?php endif; ?>

<!-- KPI cards -->
<div class="row g-4 mb-4">
    <?php foreach ($kpiCards as $k): ?>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-<?= $k['color'] ?> bg-opacity-10 text-<?= $k['color'] ?>">
                    <i class="bi <?= $k['icon'] ?>"></i>
                </div>
                <div>
                    <div class="text-muted small"><?= e($k['label']) ?></div>
                    <div class="fs-4 fw-bold"><?= e((string) $k['value']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Trend -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2"></i>Hours Logged per <?= $byMonth ? 'Month' : 'Day' ?></h5>
    </div>
    <div class="card-body">
        <canvas id="timeTrendChart" height="80"></canvas>
    </div>
</div>

<!-- By type / by group -->
<div class="row g-4 mb-4">
    <?php foreach (['Ticket Type' => [$byType, 'bi-tags'], 'Group' => [$byGroup, 'bi-diagram-3']] as $title => [$rows, $icon]): ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi <?= $icon ?> me-2"></i>By <?= $title ?></h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= $title ?></th>
                            <th class="text-center">Tickets</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Avg / Ticket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No time logged.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['name']) ?></td>
                            <td class="text-center"><?= (int) $r['tickets'] ?></td>
                            <td class="text-end"><?= e(formatTrackedTime((int) $r['total'])) ?></td>
                            <td class="text-end text-muted"><?= e(formatTrackedTime(intdiv((int) $r['total'], max(1, (int) $r['tickets'])))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Top tickets -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ol me-2"></i>Tickets with the Most Time</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ticket</th>
                    <th>Type</th>
                    <th>Agents</th>
                    <th class="text-center">Entries</th>
                    <th class="text-end">Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tickets): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No time logged.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>
                        <a href="<?= $ticketBase . (int) $t['id'] ?>" class="fw-semibold text-decoration-none">#<?= (int) $t['id'] ?></a>
                        <span class="ms-1"><?= e($t['subject']) ?></span>
                    </td>
                    <td class="text-muted small"><?= e($t['type_name']) ?></td>
                    <td class="small"><?= e($t['agents'] ?? '') ?></td>
                    <td class="text-center"><?= (int) $t['entries'] ?></td>
                    <td class="text-end fw-semibold text-nowrap"><?= e(formatTrackedTime((int) $t['total'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted small mb-0">
    Top 25 shown; Export CSV lists every ticket. Time counts on the date the work was done. A running timer is counted once it is stopped.
</p>

<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('timeTrendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($trend)) ?>,
        datasets: [{
            label: 'Hours',
            data: <?= json_encode(array_values($trend)) ?>,
            backgroundColor: 'rgba(79,70,229,0.7)',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Hours' } } }
    }
});
</script>
