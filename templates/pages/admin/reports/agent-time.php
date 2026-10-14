<?php
$layout       = 'app';
$pageTitle    = 'Agent Time – Reports';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Agent Time'],
];
$ticketBase = Auth::isAdmin() ? '/admin/tickets/' : '/agent/tickets/';
// Link to one agent's timesheet, keeping the current date range.
$agentUrl = static function (int $id): string {
    $q = $_GET;
    unset($q['export']);
    $q['agent'] = $id;
    return '/admin/reports/agent-time?' . http_build_query($q);
};
$agentName = '';
foreach ($staff as $s) {
    if ((int) $s['id'] === $agentId) {
        $agentName = $s['name'];
    }
}
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Agent Time</p>
</div>

<!-- Filters -->
<form class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <?php require ROOT_DIR . '/templates/partials/report-date-range.php'; ?>
    <select name="agent" class="form-select form-select-sm" style="width:auto;" aria-label="Agent">
        <option value="0">All agents</option>
        <?php foreach ($staff as $s): ?>
        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $agentId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
    <?php require ROOT_DIR . '/templates/partials/report-export-csv.php'; ?>
</form>

<?php if (!$agents): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>No time was logged in this period.
    Time tracking is switched on per ticket type under <strong>Admin &rarr; Ticket Types</strong> (&ldquo;Track time spent on these tickets&rdquo;).
</div>
<?php else: ?>

<!-- Chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2"></i>Hours Logged per Agent</h5>
    </div>
    <div class="card-body">
        <div style="position:relative; height:<?= max(160, 36 * count($agents) + 60) ?>px;">
            <canvas id="agentTimeChart"></canvas>
        </div>
    </div>
</div>

<!-- Per agent -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2"></i>Time by Agent</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Agent</th>
                    <th class="text-end">Total</th>
                    <th style="min-width:140px;">Share</th>
                    <th class="text-center">Tickets</th>
                    <th class="text-center">Entries</th>
                    <th class="text-end">Avg / Ticket</th>
                    <th class="text-center">Days Logged</th>
                    <th class="text-end">Avg / Day</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $a):
                    $secs  = (int) $a['total'];
                    $share = $grandTotal > 0 ? round($secs / $grandTotal * 100) : 0;
                ?>
                <tr>
                    <td class="fw-semibold"><a href="<?= e($agentUrl((int) $a['user_id'])) ?>" class="text-decoration-none" title="Show timesheet"><?= e($a['agent_name']) ?></a></td>
                    <td class="text-end fw-semibold text-nowrap"><?= e(formatTrackedTime($secs)) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px;" role="progressbar" aria-label="Share of total time" aria-valuenow="<?= $share ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width:<?= $share ?>%; background:var(--ld-primary);"></div>
                            </div>
                            <span class="small text-muted"><?= $share ?>%</span>
                        </div>
                    </td>
                    <td class="text-center"><?= (int) $a['tickets'] ?></td>
                    <td class="text-center"><?= (int) $a['entries'] ?></td>
                    <td class="text-end text-muted"><?= e(formatTrackedTime(intdiv($secs, max(1, (int) $a['tickets'])))) ?></td>
                    <td class="text-center"><?= (int) $a['days'] ?></td>
                    <td class="text-end text-muted"><?= e(formatTrackedTime(intdiv($secs, max(1, (int) $a['days'])))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Agent × ticket type -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-grid-3x3 me-2"></i>Agent &times; Ticket Type</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Agent</th>
                    <?php foreach (array_keys($typeTotals) as $typeName): ?>
                    <th class="text-end text-nowrap"><?= e($typeName) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $a): ?>
                <tr>
                    <td class="fw-semibold text-nowrap"><?= e($a['agent_name']) ?></td>
                    <?php foreach (array_keys($typeTotals) as $typeName):
                        $cell = $grid[(int) $a['user_id']][$typeName] ?? 0; ?>
                    <td class="text-end text-nowrap<?= $cell ? '' : ' text-muted' ?>"><?= $cell ? e(formatTrackedTime($cell)) : '&ndash;' ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th>Total</th>
                    <?php foreach ($typeTotals as $typeTotal): ?>
                    <th class="text-end text-nowrap"><?= e(formatTrackedTime((int) $typeTotal)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($agentId): ?>
<!-- One agent's timesheet -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-journal-text me-2"></i>Timesheet &mdash; <?= e($agentName ?: 'Unknown agent') ?></h5>
        <span class="fw-semibold"><?= e(formatTrackedTime((int) array_sum(array_column($entries, 'seconds')))) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Ticket</th>
                    <th>Type</th>
                    <th>Note</th>
                    <th class="text-end">Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$entries): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No time logged by this agent in this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($entries as $en): ?>
                <tr>
                    <td class="text-nowrap"><?= e(date('D, M j, Y', strtotime($en['work_date']))) ?></td>
                    <td>
                        <a href="<?= $ticketBase . (int) $en['ticket_id'] ?>" class="fw-semibold text-decoration-none">#<?= (int) $en['ticket_id'] ?></a>
                        <span class="ms-1"><?= e($en['subject']) ?></span>
                    </td>
                    <td class="text-muted small"><?= e($en['type_name']) ?></td>
                    <td class="small text-break"><?= e($en['note']) ?></td>
                    <td class="text-end fw-semibold text-nowrap"><?= e(formatTrackedTime((int) $en['seconds'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<p class="text-muted small mb-0">
    Pick an agent (or click a name) to see their timesheet. Export CSV gives every entry in the period, for the chosen agent or everyone.
    Time counts on the date the work was done; a running timer is counted once it is stopped.
</p>

<?php if ($agents): ?>
<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('agentTimeChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($agents, 'agent_name')) ?>,
        datasets: [{
            label: 'Hours',
            data: <?= json_encode(array_map(static fn($a) => round($a['total'] / 3600, 2), $agents)) ?>,
            backgroundColor: 'rgba(79,70,229,0.7)',
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, title: { display: true, text: 'Hours' } } }
    }
});
</script>
<?php endif; ?>
