<?php
$layout              = 'app';
$pageTitle           = 'SLA Violations – Reports';
$scheduleReportType  = 'sla_violations';
$scheduleReportTitle = 'SLA Violations';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'SLA Violations'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">SLA Violations</p>
</div>

<?php if (!slaEnabled()): ?>
<div class="alert alert-warning d-flex align-items-start">
    <i class="bi bi-exclamation-triangle me-2 mt-1"></i>
    <div>
        <strong>SLA tracking is currently disabled site-wide.</strong>
        New tickets are no longer tracked — the figures below reflect historical data only.
        Re-enable it under <a href="/admin/settings/sla-policies" class="alert-link">Settings &rsaquo; SLA Policies</a>.
    </div>
</div>
<?php endif; ?>

<form class="d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-calendar3 text-muted"></i>
    <?php require ROOT_DIR . '/templates/partials/report-date-range.php'; ?>
    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scheduleReportModal">
        <i class="bi bi-calendar-plus me-1"></i>Schedule
    </button>
</form>
<?php require ROOT_DIR . '/templates/partials/schedule-report-modal.php'; ?>

<!-- Summary -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-octagon"></i></div>
                <div>
                    <div class="text-muted small">Total Violations</div>
                    <div class="fs-4 fw-bold"><?= $totalViolations ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-reply"></i></div>
                <div>
                    <div class="text-muted small">First-Response Breaches</div>
                    <div class="fs-4 fw-bold"><?= $responseCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-check2-all"></i></div>
                <div>
                    <div class="text-muted small">Resolution Breaches</div>
                    <div class="fs-4 fw-bold"><?= $resolutionCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="text-muted small">Still Open</div>
                    <div class="fs-4 fw-bold"><?= $openCount ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Violations list -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-exclamation-octagon me-2 text-danger"></i>Tickets That Violated Their SLA</h5>
        <span class="badge bg-danger"><?= $totalViolations ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Agent</th>
                    <th>Breach</th>
                    <th class="text-end">Overdue By</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($violations)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No SLA violations in this period. 🎉</td></tr>
                <?php else: ?>
                    <?php foreach ($violations as $t): ?>
                    <tr>
                        <td><a href="/admin/tickets/<?= $t['id'] ?>" class="fw-semibold">#<?= $t['id'] ?></a></td>
                        <td class="text-truncate" style="max-width:240px;"><?= e($t['subject']) ?></td>
                        <td>
                            <?php $sclr = ticketStatusColor($t['status']); ?>
                            <span class="badge" style="background:<?= e($sclr) ?>;color:<?= e(ticketStatusTextColor($sclr)) ?>;"><?= e(ticketStatusLabel($t['status'])) ?></span>
                        </td>
                        <td><span class="badge" style="background:<?= e($t['priority_color'] ?? '#6c757d') ?>;"><?= e($t['priority_name'] ?? 'None') ?></span></td>
                        <td><?= e($t['agent_name'] ?? 'Unassigned') ?></td>
                        <td>
                            <?php if ($t['response_breached']): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger me-1">Response</span>
                            <?php endif; ?>
                            <?php if ($t['resolution_breached']): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger">Resolution</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($t['worst_overdue_min'] > 0): ?>
                                <span class="text-danger fw-semibold"><?= e(formatMinutes((float) $t['worst_overdue_min'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
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
