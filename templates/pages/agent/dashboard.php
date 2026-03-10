<?php
$layout      = 'app';
$pageTitle   = 'Agent Dashboard';
$breadcrumbs = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Dashboard'],
];
$sidebarItems = agentSidebar('dashboard');
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed'];
?>
<div class=" mb-4">
    <div>
        <h2 class="fw-bold mb-1">Agent Dashboard</h2>
        <p class="text-muted mb-0">Welcome back, <?= e($user['first_name'] ?? 'Agent') ?></p>
    </div>
</div>

<div class="row g-4 mb-4" id="tour-stat-cards">
    <div class="col-md-3">
        <a href="/agent/tickets?agent%5B%5D=unassigned&status%5B%5D=open&status%5B%5D=in_progress&status%5B%5D=pending" class="text-decoration-none">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Unassigned</div>
                    <div class="fs-4 fw-bold"><?= $unassigned ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/agent/tickets?agent%5B%5D=mine&status%5B%5D=open&status%5B%5D=in_progress&status%5B%5D=pending" class="text-decoration-none">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-person-check"></i>
                </div>
                <div>
                    <div class="text-muted small">My Tickets</div>
                    <div class="fs-4 fw-bold"><?= $myTickets ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/agent/tickets?status%5B%5D=pending" class="text-decoration-none">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="text-muted small">Pending</div>
                    <div class="fs-4 fw-bold"><?= $pending ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/agent/tickets?resolved_today=1" class="text-decoration-none">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Resolved Today</div>
                    <div class="fs-4 fw-bold"><?= $resolvedToday ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm" id="tour-recent-tickets">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-ticket-detailed me-2"></i>Recent Tickets</h5>
    </div>
    <?php if (empty($recentTickets)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        <p class="mb-0">No tickets in the queue.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">#</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Created By</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTickets as $t): ?>
                <tr style="cursor:pointer;" onclick="window.location='/agent/tickets/<?= $t['id'] ?>'">
                    <td class="text-muted fw-bold"><?= $t['id'] ?></td>
                    <td>
                        <a href="/agent/tickets/<?= $t['id'] ?>" class="text-decoration-none fw-semibold text-dark">
                            <?= e($t['subject']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?>">
                            <?= e($statusLabels[$t['status']] ?? $t['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($t['priority_name']): ?>
                        <span class="badge" style="background:<?= e($t['priority_color']) ?>;">
                            <?= e($t['priority_name']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= e($t['creator_name'] ?? '—') ?></td>
                    <td><?= e($t['agent_name'] ?: '— Unassigned —') ?></td>
                    <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
