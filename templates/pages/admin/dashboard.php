<?php
$layout      = 'app';
$pageTitle   = 'Admin Dashboard';
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Dashboard'],
];
$sidebarItems = adminSidebar('dashboard');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Admin Dashboard</h2>
        <p class="text-muted mb-0">System overview</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-ticket-detailed"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Tickets</div>
                    <div class="fs-4 fw-bold">0</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="text-muted small">Users</div>
                    <div class="fs-4 fw-bold">3</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-headset"></i>
                </div>
                <div>
                    <div class="text-muted small">Agents</div>
                    <div class="fs-4 fw-bold">1</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="text-muted small">Avg. Resolution</div>
                    <div class="fs-4 fw-bold">&mdash;</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-activity me-2"></i>Recent Activity</h5>
            </div>
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                <p class="mb-0">No recent activity to show.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/admin/users/create" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-person-plus me-2"></i>Add User
                    </a>
                    <a href="/admin/locations" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-geo-alt me-2"></i>Manage Locations
                    </a>
                    <a href="/admin/priorities" class="btn btn-outline-secondary btn-sm text-start">
                        <i class="bi bi-flag me-2"></i>Manage Priorities
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
