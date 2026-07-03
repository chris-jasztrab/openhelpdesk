<?php
$layout       = 'app';
$pageTitle    = label('portal.request.my_plural', 'My Requests');
$sidebarItems = portalSidebar('tickets');
$breadcrumbs  = [
    ['label' => label('portal.nav.help', 'Help'), 'url' => '/portal'],
    ['label' => label('portal.request.my_plural', 'My Requests')],
];
$portalLabelOverrides = [
    'open'                   => label('portal.status.open', 'Submitted'),
    'in_progress'            => label('portal.status.in_progress', "We're working on it"),
    'pending'                => label('portal.status.pending', "We're waiting on someone else"),
    'waiting_on_customer'    => label('portal.status.waiting_on_customer', 'Waiting on you'),
    'waiting_on_third_party' => label('portal.status.waiting_on_third_party', "We're waiting on someone else"),
    'resolved'               => label('portal.status.resolved', 'Done'),
    'closed'                 => label('portal.status.closed', 'Closed'),
];
$statusLabels = [];
foreach (ticketActiveStatuses() as $__s) {
    $statusLabels[$__s['slug']] = $portalLabelOverrides[$__s['slug']] ?? $__s['label'];
}
unset($__s);
$isDefault  = $filters['status'] === 'open' && $filters['priority'] === '' && $filters['q'] === '' && $filters['scope'] === 'mine';
$hasFilters = !$isDefault;
$sortParams = array_filter($filters, fn($v) => $v !== '' && $v !== 'mine');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= e(label('portal.request.my_plural', 'My Requests')) ?></h2>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($hasFilters): ?><span class="badge bg-secondary fs-6"><?= $totalTickets ?> filtered of <?= $allTickets ?> total</span><?php else: ?><span class="badge bg-secondary fs-6"><?= $totalTickets ?> total</span><?php endif; ?>
        <a href="/portal/tickets/create" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-circle me-1"></i><?= e(label('portal.action.new', 'New Help Request')) ?>
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
                        <i class="bi bi-person me-1"></i><?= e(label('portal.request.my_plural', 'My Requests')) ?>
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
                    <th style="width:60px;min-width:60px;white-space:nowrap;"><a href="<?= sortUrl('id', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark"># <?= sortIcon('id', $sort, $dir) ?></a></th>
                    <th style="min-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><a href="<?= sortUrl('subject', $sort, $dir, $sortParams, '/portal/tickets') ?>" class="text-decoration-none text-dark">Subject <?= sortIcon('subject', $sort, $dir) ?></a></th>
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
                    Change your filters to see your requests, or <a href="/portal/tickets/create">start a new help request</a>.
                    <?php else: ?>
                    No help requests yet. <a href="/portal/tickets/create">Submit your first one</a>.
                    <?php endif; ?>
                </td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                    <tr style="cursor:pointer;" onclick="window.location='/portal/tickets/<?= $t['id'] ?>'">
                        <td class="text-muted fw-bold" style="white-space:nowrap;"><?= $t['id'] ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:1px;" title="<?= e($t['subject']) ?>">
                            <a href="/portal/tickets/<?= $t['id'] ?>" class="text-decoration-none fw-semibold text-dark">
                                <?= e($t['subject']) ?>
                            </a>
                            <?php if ((int) ($t['escalation_level'] ?? 0) > 0): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1"
                                  title="This ticket has been escalated to Level <?= (int) $t['escalation_level'] ?>.">
                                <i class="bi bi-arrow-up-circle me-1"></i>Escalated L<?= (int) $t['escalation_level'] ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($draftTicketIds[$t['id']])): ?>
                            <span class="badge bg-warning bg-opacity-25 text-dark ms-1" title="You have an unsent comment draft on this request"><i class="bi bi-pencil-square me-1"></i>Draft</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge" style="<?= ticketStatusBadgeStyle($t['status']) ?>">
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
                        <td><?php if ($t['type_name']): ?><span class="badge" style="background:<?= e($t['type_color'] ?: '#6c757d') ?>;"><?= e($t['type_name']) ?></span><?php else: ?><span class="text-muted small">Not Set</span><?php endif; ?></td>
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
        <?php $winStart = max(1, $page - 2); $winEnd = min($totalPages, $page + 2); ?>
        <?php if ($winStart > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => 1]))) ?>">1</a>
        </li>
        <?php if ($winStart > 2): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $winStart; $p <= $winEnd; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $p]))) ?>"
               <?= $p === $page ? 'style="background:var(--ld-primary);border-color:var(--ld-primary);"' : '' ?>><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($winEnd < $totalPages): ?>
        <?php if ($winEnd < $totalPages - 1): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>
        <li class="page-item">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $totalPages]))) ?>"><?= $totalPages ?></a>
        </li>
        <?php endif; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $page + 1]))) ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>
