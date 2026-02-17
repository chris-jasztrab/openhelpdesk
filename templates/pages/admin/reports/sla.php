<?php
$layout       = 'app';
$pageTitle    = 'SLA Compliance – Reports';
$sidebarItems = adminSidebar('reports');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'SLA Compliance'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">SLA Compliance</p>
</div>

<?php require ROOT_DIR . '/templates/partials/reports-nav.php'; ?>

<form class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;">
    <span class="text-muted">to</span>
    <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;">
    <button type="submit" class="btn btn-sm text-white" style="background:#4f46e5;">Apply</button>
</form>

<!-- Overall SLA -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-shield-check"></i></div>
                <div>
                    <div class="text-muted small">Overall Compliance</div>
                    <div class="fs-4 fw-bold"><?= $overallCompliance ?>%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-reply"></i></div>
                <div>
                    <div class="text-muted small">First Response SLA</div>
                    <div class="fs-4 fw-bold"><?= $firstResponseCompliance ?>%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-check2-all"></i></div>
                <div>
                    <div class="text-muted small">Resolution SLA</div>
                    <div class="fs-4 fw-bold"><?= $resolutionCompliance ?>%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="text-muted small">Total Breached</div>
                    <div class="fs-4 fw-bold"><?= $totalBreached ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Doughnut chart -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">SLA Status</h6>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="slaDonut" width="220" height="220"></canvas>
            </div>
        </div>
    </div>
    <!-- By priority -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">By Priority</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Priority</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Response Met</th>
                            <th class="text-center">Response Breached</th>
                            <th class="text-center">Resolution Met</th>
                            <th class="text-center">Resolution Breached</th>
                            <th class="text-center">Compliance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($byPriority)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No SLA data for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($byPriority as $row): ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background:<?= e($row['priority_color']) ?>;"><?= e($row['priority_name']) ?></span>
                                </td>
                                <td class="text-center"><?= $row['total'] ?></td>
                                <td class="text-center text-success"><?= $row['response_met'] ?></td>
                                <td class="text-center text-danger"><?= $row['response_breached'] ?></td>
                                <td class="text-center text-success"><?= $row['resolution_met'] ?></td>
                                <td class="text-center text-danger"><?= $row['resolution_breached'] ?></td>
                                <td class="text-center">
                                    <?php $c = $row['compliance']; $clr = $c >= 90 ? 'success' : ($c >= 70 ? 'warning' : 'danger'); ?>
                                    <span class="badge bg-<?= $clr ?> bg-opacity-10 text-<?= $clr ?>"><?= $c ?>%</span>
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

<!-- Breached tickets list -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-exclamation-octagon me-2 text-danger"></i>Breached Tickets</h5>
        <span class="badge bg-danger"><?= count($breachedTickets) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Priority</th>
                    <th>Agent</th>
                    <th>Breach Type</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($breachedTickets)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No breached tickets in this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($breachedTickets as $t): ?>
                    <tr>
                        <td><a href="/admin/tickets/<?= $t['id'] ?>" class="fw-semibold">#<?= $t['id'] ?></a></td>
                        <td class="text-truncate" style="max-width:250px;"><?= e($t['subject']) ?></td>
                        <td><span class="badge" style="background:<?= e($t['priority_color'] ?? '#6c757d') ?>;"><?= e($t['priority_name'] ?? 'None') ?></span></td>
                        <td><?= e($t['agent_name'] ?? 'Unassigned') ?></td>
                        <td>
                            <?php if (!empty($t['response_breached'])): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger me-1">Response</span>
                            <?php endif; ?>
                            <?php if (!empty($t['resolution_breached'])): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger">Resolution</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('M j, Y g:ia', strtotime($t['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('slaDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Met', 'Breached'],
        datasets: [{
            data: [<?= $totalMet ?>, <?= $totalBreached ?>],
            backgroundColor: ['#10b981', '#ef4444'],
        }]
    },
    options: {
        responsive: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
