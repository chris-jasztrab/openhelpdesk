<?php
$layout       = 'app';
$pageTitle    = 'Reports';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports'],
];
?>
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
        <p class="text-muted mb-0">Overview of key metrics for the current period</p>
    </div>
    <?php if (Auth::isAdmin()): ?>
    <a href="/admin/settings/scheduled-reports" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-envelope-paper me-1"></i>Scheduled Reports
    </a>
    <?php endif; ?>
</div>

<!-- Date range info -->
<div class="d-flex align-items-center gap-2 mb-4 text-muted small">
    <i class="bi bi-calendar3"></i>
    Showing data from <strong><?= e($from) ?></strong> to <strong><?= e($to) ?></strong>
    <form class="d-inline-flex align-items-center gap-2 ms-3">
        <?php require ROOT_DIR . '/templates/partials/report-date-range.php'; ?>
        <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
    </form>
</div>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-ticket-detailed"></i>
                </div>
                <div>
                    <div class="text-muted small">Tickets Created</div>
                    <div class="fs-4 fw-bold"><?= $ticketsCreated ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Tickets Resolved</div>
                    <div class="fs-4 fw-bold"><?= $ticketsResolved ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="text-muted small">Unresolved</div>
                    <div class="fs-4 fw-bold"><?= $unresolvedCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="text-muted small">Avg First Response</div>
                    <div class="fs-4 fw-bold"><?= $avgFirstResponse ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="text-muted small">Avg Resolution Time</div>
                    <div class="fs-4 fw-bold"><?= $avgResolution ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <div class="text-muted small">SLA Compliance</div>
                    <div class="fs-4 fw-bold"><?= $slaCompliance ?>%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                    <i class="bi bi-person-x"></i>
                </div>
                <div>
                    <div class="text-muted small">Unassigned Tickets</div>
                    <div class="fs-4 fw-bold"><?= $unassignedCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-stopwatch"></i>
                </div>
                <div>
                    <div class="text-muted small">SLA Breached</div>
                    <div class="fs-4 fw-bold"><?= $slaBreached ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick-nav report cards -->
<h5 class="fw-bold mb-3">Detailed Reports</h5>
<div class="row g-4">
    <?php
    $reportCards = [
        ['icon' => 'bi-person-badge',        'title' => 'Agent Performance',  'desc' => 'Tickets handled, response times, and resolution rates per agent.',       'url' => '/admin/reports/agent-performance'],
        ['icon' => 'bi-clock-history',        'title' => 'Response Times',     'desc' => 'Average first-response and resolution times by priority.',               'url' => '/admin/reports/response-times'],
        ['icon' => 'bi-stopwatch',            'title' => 'SLA Compliance',     'desc' => 'SLA met vs breached rates and breached ticket details.',                 'url' => '/admin/reports/sla'],
        ['icon' => 'bi-exclamation-triangle', 'title' => 'Unresolved Tickets', 'desc' => 'All open, in-progress, and pending tickets with aging breakdown.',       'url' => '/admin/reports/unresolved'],
        ['icon' => 'bi-graph-up',             'title' => 'Ticket Volume',      'desc' => 'Ticket creation trends over time by priority, type, and location.',      'url' => '/admin/reports/ticket-volume'],
        ['icon' => 'bi-arrow-repeat',         'title' => 'Ticket Lifecycle',   'desc' => 'Average time spent in each status stage and transition patterns.',       'url' => '/admin/reports/lifecycle'],
        ['icon' => 'bi-geo-alt',              'title' => 'By Location',        'desc' => 'Ticket volume and resolution rates compared across locations.',          'url' => '/admin/reports/location'],
        ['icon' => 'bi-star',                 'title' => 'Satisfaction',       'desc' => 'CSAT survey results — response rates, average ratings, and feedback.',     'url' => '/admin/reports/csat'],
        ['icon' => 'bi-people',               'title' => 'Agent Workload',     'desc' => 'Visual heatmap of open tickets per agent, broken down by status and SLA.',  'url' => '/admin/reports/workload'],
        ['icon' => 'bi-graph-up-arrow',       'title' => 'Ticket Trends',      'desc' => 'Multi-line volume trend over time, drilled down by type or location.',      'url' => '/admin/reports/trends'],
        ['icon' => 'bi-patch-check',          'title' => 'FCR Rate',           'desc' => 'First-contact resolution rate — tickets resolved without back-and-forth.',   'url' => '/admin/reports/fcr'],
        ['icon' => 'bi-diagram-3',            'title' => 'Group Coverage',     'desc' => 'Each ticket type, its default group, and the members of that group.',        'url' => '/admin/reports/group-coverage'],
        ['icon' => 'bi-sliders',              'title' => 'Custom Builder',     'desc' => 'Pick any metric and group-by combination to build a custom report.',         'url' => '/admin/reports/custom'],
    ];
    foreach ($reportCards as $rc): ?>
    <div class="col-md-4">
        <a href="<?= $rc['url'] ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="transition:transform .15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi <?= $rc['icon'] ?>"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-dark"><?= e($rc['title']) ?></h6>
                    </div>
                    <p class="text-muted small mb-0"><?= e($rc['desc']) ?></p>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
