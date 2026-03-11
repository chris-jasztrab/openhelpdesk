<?php
$layout       = 'app';
$pageTitle    = 'Portal';
$sidebarItems = portalSidebar('dashboard');
$breadcrumbs  = [
    ['label' => 'Portal'],
];
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'waiting_on_customer' => 'warning', 'waiting_on_third_party' => 'dark', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'waiting_on_customer' => 'Waiting on Customer', 'waiting_on_third_party' => 'Waiting on Third Party', 'resolved' => 'Resolved', 'closed' => 'Closed'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Welcome, <?= e($user['first_name'] ?? 'User') ?></h2>
        <p class="text-muted mb-0">How can we help you today?</p>
    </div>
    <a href="/portal/tickets/create" id="tour-portal-new-ticket" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-plus-circle me-1"></i>New Ticket
    </a>
</div>

<div class="card border-0 shadow-sm mt-4" id="tour-portal-recent">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-ticket-detailed me-2"></i>Open Tickets</h5>
        <a href="/portal/tickets" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <?php if (empty($recentTickets)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        <p class="mb-0">You have no open tickets. Click "New Ticket" to get started.</p>
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
                    <th>Assigned To</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTickets as $t): ?>
                <tr style="cursor:pointer;" onclick="window.location='/portal/tickets/<?= $t['id'] ?>'">
                    <td class="text-muted fw-bold"><?= $t['id'] ?></td>
                    <td>
                        <a href="/portal/tickets/<?= $t['id'] ?>" class="text-decoration-none fw-semibold text-dark">
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
                    <td><?= e($t['agent_name'] ?: 'Unassigned') ?></td>
                    <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
