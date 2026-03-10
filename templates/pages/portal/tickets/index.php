<?php
$layout       = 'app';
$pageTitle    = 'My Tickets';
$sidebarItems = portalSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Portal', 'url' => '/portal'],
    ['label' => 'My Tickets'],
];
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'waiting_on_customer' => 'warning', 'waiting_on_third_party' => 'dark', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'waiting_on_customer' => 'Waiting on Customer', 'waiting_on_third_party' => 'Waiting on Third Party', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$isDefault  = $filters['status'] === 'open' && $filters['priority'] === '' && $filters['q'] === '' && $filters['scope'] === 'mine';
$hasFilters = !$isDefault;
$sortParams = array_filter($filters, fn($v) => $v !== '' && $v !== 'mine');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">My Tickets</h2>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-secondary fs-6"><?= $totalTickets ?><?= $hasFilters ? ' filtered' : ' total' ?></span>
        <a href="/portal/tickets/create" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-circle me-1"></i>New Ticket
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3" id="tour-portal-filter-bar">
    <div class="card-body py-3">
        <form method="GET" action="/portal/tickets" class="row g-2 align-items-end">
            <?php if ($canViewLocation): ?>
            <input type="hidden" name="scope" value="<?= e($filters['scope']) ?>">
            <?php endif; ?>
            <div class="col-md">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" class="form-control form-control-sm" name="q"
                       value="<?= e($filters['q']) ?>" placeholder="Search subject...">
            </div>
            <div class="col-md-auto" style="min-width:140px;">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($statusLabels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto" style="min-width:130px;">
                <label class="form-label small text-muted mb-1">Priority</label>
                <select class="form-select form-select-sm" name="priority">
                    <option value="">All Priorities</option>
                    <?php foreach ($priorities as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filters['priority'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($hasFilters): ?>
                <a href="/portal/tickets" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php if ($canViewLocation): ?>
            <div class="col-12 pt-1">
                <div class="btn-group btn-group-sm" role="group">
                    <a href="?<?= http_build_query(array_merge(array_filter($filters, fn($v) => $v !== '' && $v !== 'mine'), ['scope' => 'mine'])) ?>"
                       class="btn <?= $filters['scope'] !== 'location' ? 'text-white' : 'btn-outline-secondary' ?>"
                       <?= $filters['scope'] !== 'location' ? 'style="background:var(--ld-primary);"' : '' ?>>
                        <i class="bi bi-person me-1"></i>My Tickets
                    </a>
                    <a href="?<?= http_build_query(array_merge(array_filter($filters, fn($v) => $v !== '' && $v !== 'mine'), ['scope' => 'location'])) ?>"
                       class="btn <?= $filters['scope'] === 'location' ? 'text-white' : 'btn-outline-secondary' ?>"
                       <?= $filters['scope'] === 'location' ? 'style="background:var(--ld-primary);"' : '' ?>>
                        <i class="bi bi-building me-1"></i>My Location
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" id="tour-portal-ticket-table">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px"><a href="<?= sortUrl('id', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark"># <?= sortIcon('id', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('subject', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark">Subject <?= sortIcon('subject', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('status', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark">Status <?= sortIcon('status', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('priority', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark">Priority <?= sortIcon('priority', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('type', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark">Type <?= sortIcon('type', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('agent', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark">Assigned To <?= sortIcon('agent', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('created_at', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark">Created <?= sortIcon('created_at', $sort, $dir) ?></a></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                    <?php if ($userHasAnyTickets): ?>
                    Change your filters to see your tickets, or <a href="/portal/tickets/create">create a new ticket</a>.
                    <?php else: ?>
                    No tickets yet. <a href="/portal/tickets/create">Create your first ticket</a>.
                    <?php endif; ?>
                </td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
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
                        <td class="text-muted"><?= e($t['type_name'] ?: 'Not Set') ?></td>
                        <td><?= e($t['agent_name'] ?: 'Unassigned') ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<?php
    $pagerParams = array_filter($filters, fn($v) => $v !== '');
    if ($sort !== 'created_at' || $dir !== 'desc') {
        $pagerParams['sort'] = $sort;
        $pagerParams['dir']  = $dir;
    }
    $pagerBase   = '/portal/tickets';
?>
<nav class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted small">
        Showing <?= (($page - 1) * 30) + 1 ?>–<?= min($page * 30, $totalTickets) ?> of <?= $totalTickets ?>
    </span>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $page - 1]))) ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $p]))) ?>"
               <?= $p === $page ? 'style="background:var(--ld-primary);border-color:var(--ld-primary);"' : '' ?>><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $page + 1]))) ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>
