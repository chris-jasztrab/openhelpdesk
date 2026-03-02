<?php
$layout      = 'app';
$pageTitle   = 'Admin Dashboard';
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Dashboard'],
];
$sidebarItems = adminSidebar('dashboard');
$actionIcons = [
    'created'          => 'bi-plus-circle text-success',
    'comment'          => 'bi-chat-dots text-primary',
    'internal_note'    => 'bi-lock text-secondary',
    'status_changed'   => 'bi-arrow-repeat text-warning',
    'priority_changed' => 'bi-flag text-info',
    'assigned'         => 'bi-person-check text-primary',
    'sla_initialized'  => 'bi-stopwatch text-info',
    'sla_paused'       => 'bi-pause-circle text-warning',
    'sla_resumed'      => 'bi-play-circle text-success',
    'first_response'   => 'bi-reply text-success',
];
?>
<?php $autoShowTour = !empty($showOnboarding); ?>
<?php require ROOT_DIR . '/templates/partials/onboarding-tour.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Admin Dashboard</h2>
        <p class="text-muted mb-0">System overview</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-activity me-2"></i>Recent Activity</h5>
            </div>
            <?php if (empty($recentActivity)): ?>
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                <p class="mb-0">No recent activity to show.</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($recentActivity as $a): ?>
                <a href="/admin/tickets/<?= $a['ticket_id'] ?>" class="list-group-item list-group-item-action py-3">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi <?= $actionIcons[$a['action']] ?? 'bi-circle text-muted' ?> fs-5 mt-1"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold"><?= e($a['ticket_subject'] ?? 'Ticket #' . $a['ticket_id']) ?></span>
                                <small class="text-muted"><?= date('M j, g:ia', strtotime($a['created_at'])) ?></small>
                            </div>
                            <div class="text-muted small">
                                <?= e($a['user_name'] ?? 'System') ?> &mdash;
                                <?= e(ucfirst(str_replace('_', ' ', $a['action']))) ?>
                            </div>
                            <?php if ($a['details']): ?>
                            <div class="text-muted small text-truncate" style="max-width:500px;"><?= e($a['details']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/admin/tickets" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-ticket-detailed me-2"></i>View All Tickets
                    </a>
                    <a href="/admin/users/create" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-person-plus me-2"></i>Add User
                    </a>
                    <a href="/admin/locations" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-geo-alt me-2"></i>Manage <?= label('location.plural') ?>
                    </a>
                    <a href="/admin/priorities" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-flag me-2"></i>Manage Priorities
                    </a>
                    <a href="/admin/settings" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-gear me-2"></i>Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
