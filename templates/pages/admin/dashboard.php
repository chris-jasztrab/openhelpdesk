<?php
$layout      = 'app';
$pageTitle   = 'Admin Dashboard';
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Dashboard'],
];
$sidebarItems = adminSidebar('dashboard');
$statusLabels = ticketStatusLabelMap();
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
                <h5 class="mb-0 fw-semibold"><i class="bi bi-ticket-detailed me-2"></i>Recent Tickets</h5>
            </div>
            <?php if (empty($recentTickets)): ?>
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                <p class="mb-0">No recent tickets to show.</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($recentTickets as $t): ?>
                <a href="/admin/tickets/<?= $t['id'] ?>" class="list-group-item list-group-item-action py-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="fw-semibold text-truncate">
                                    <span class="text-muted fw-normal me-1">#<?= $t['id'] ?></span>
                                    <?= e($t['subject']) ?>
                                </span>
                                <small class="text-muted flex-shrink-0"><?= date('M j, g:ia', strtotime($t['updated_at'])) ?></small>
                            </div>
                            <div class="small mt-1 d-flex flex-wrap gap-2 align-items-center">
                                <?= ticketStatusBadgeHtml($t['status']) ?>
                                <?php if (!empty($t['type_name'])): ?>
                                <span class="badge" style="background:<?= e($t['type_color'] ?? '#6c757d') ?>;">
                                    <?= e($t['type_name']) ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($t['priority_name'])): ?>
                                <span class="badge" style="background:<?= e($t['priority_color'] ?? '#6c757d') ?>;">
                                    <?= e($t['priority_name']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
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
