<?php
$layout       = 'app';
$pageTitle    = 'Unresolved Tickets – Reports';
$sidebarItems = Auth::role() === 'power_user' ? powerUserSidebar('reports') : adminSidebar('reports');
$breadcrumbs  = [
    Auth::role() === 'power_user' ? ['label' => 'Agent', 'url' => '/agent'] : ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Unresolved Tickets'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Unresolved Tickets</p>
</div>


<!-- Summary cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="text-muted small">Total Unresolved</div>
                    <div class="fs-4 fw-bold"><?= $totalUnresolved ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-person-x"></i></div>
                <div>
                    <div class="text-muted small">Unassigned</div>
                    <div class="fs-4 fw-bold"><?= $unassigned ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-stopwatch"></i></div>
                <div>
                    <div class="text-muted small">SLA Breached</div>
                    <div class="fs-4 fw-bold"><?= $breachedCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-clock"></i></div>
                <div>
                    <div class="text-muted small">Avg Age</div>
                    <div class="fs-4 fw-bold"><?= $avgAge ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Aging breakdown -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0 fw-semibold">By Status</h6></div>
            <div class="card-body">
                <?php foreach ($byStatus as $s): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?></span>
                    <span class="fw-bold"><?= $s['count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0 fw-semibold">By Age</h6></div>
            <div class="card-body">
                <?php
                $ageLabels = ['< 1 day', '1–3 days', '3–7 days', '7–14 days', '> 14 days'];
                $ageColors = ['success', 'info', 'warning', 'danger', 'danger'];
                foreach ($agingBuckets as $i => $count): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted"><?= $ageLabels[$i] ?></span>
                    <span class="badge bg-<?= $ageColors[$i] ?> bg-opacity-10 text-<?= $ageColors[$i] ?>"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Ticket table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>All Unresolved Tickets</h5>
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
                    <th>SLA</th>
                    <th>Age</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No unresolved tickets.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><a href="/admin/tickets/<?= $t['id'] ?>" class="fw-semibold">#<?= $t['id'] ?></a></td>
                        <td class="text-truncate" style="max-width:220px;"><?= e($t['subject']) ?></td>
                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?></span></td>
                        <td>
                            <?php if ($t['priority_name']): ?>
                            <span class="badge" style="background:<?= e($t['priority_color']) ?>;"><?= e($t['priority_name']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($t['agent_name'] ?? 'Unassigned') ?></td>
                        <td>
                            <?php if ($t['sla_state']): ?>
                                <?php
                                $slaColor = match($t['sla_state']) {
                                    'on_track' => 'success',
                                    'warning' => 'warning',
                                    'breached' => 'danger',
                                    default => 'secondary',
                                };
                                ?>
                                <span class="badge bg-<?= $slaColor ?> bg-opacity-10 text-<?= $slaColor ?>"><?= e(ucfirst(str_replace('_', ' ', $t['sla_state']))) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= $t['age_display'] ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
