<?php
$layout       = 'app';
$pageTitle    = 'Tickets';
$sidebarItems = agentSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Tickets'],
];
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'waiting_on_customer' => 'warning', 'waiting_on_third_party' => 'dark', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'waiting_on_customer' => 'Waiting on Customer', 'waiting_on_third_party' => 'Waiting on Third Party', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$slaStateColors = ['on_track' => 'success', 'warning' => 'warning', 'breached' => 'danger'];
$hasFilters = array_filter($filters, fn($v) => $v !== '');
$sortParams = array_filter($filters, fn($v) => $v !== '');
$allColumns = ticketColumnDefinitions();
$colCount = 2 + count($visibleColumns);
$currentUrl = '/agent/tickets' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Tickets</h2>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-secondary fs-6"><?= $totalTickets ?><?= $hasFilters ? ' filtered' : ' total' ?></span>
        <a href="/admin/ticket-templates" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-collection me-1"></i>Templates
        </a>
        <a href="/agent/tickets/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>New Ticket
        </a>
    </div>
</div>

<?php if (!empty($groupRestricted)): ?>
<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-people me-1"></i>
    Showing tickets assigned to your group<?= count($groups) !== 1 ? 's' : '' ?>:
    <strong><?= implode(', ', array_column($groups, 'name')) ?></strong>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="/agent/tickets" class="row g-2 align-items-end">
            <div class="col-md">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" class="form-control form-control-sm" name="q"
                       value="<?= e($filters['q']) ?>" placeholder="Search subject...">
            </div>
            <div class="col-md-auto" style="min-width:130px;">
                <label class="form-label small text-muted mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($statusLabels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto" style="min-width:120px;">
                <label class="form-label small text-muted mb-1">Priority</label>
                <select class="form-select form-select-sm" name="priority">
                    <option value="">All Priorities</option>
                    <?php foreach ($priorities as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filters['priority'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto" style="min-width:130px;">
                <label class="form-label small text-muted mb-1">Type</label>
                <select class="form-select form-select-sm" name="type">
                    <option value="">All Types</option>
                    <?php foreach ($types as $tp): ?>
                    <option value="<?= $tp['id'] ?>" <?= $filters['type'] == $tp['id'] ? 'selected' : '' ?>><?= e($tp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto" style="min-width:130px;">
                <label class="form-label small text-muted mb-1">Location</label>
                <select class="form-select form-select-sm" name="location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $filters['location'] == $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto" style="min-width:140px;">
                <label class="form-label small text-muted mb-1">Agent</label>
                <select class="form-select form-select-sm" name="agent">
                    <option value="">All Agents</option>
                    <option value="mine" <?= $filters['agent'] === 'mine' ? 'selected' : '' ?>>My Tickets</option>
                    <option value="unassigned" <?= $filters['agent'] === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($agents as $ag): ?>
                    <option value="<?= $ag['id'] ?>" <?= $filters['agent'] == $ag['id'] ? 'selected' : '' ?>><?= e($ag['first_name'] . ' ' . $ag['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto" style="min-width:130px;">
                <label class="form-label small text-muted mb-1">Group</label>
                <select class="form-select form-select-sm" name="group">
                    <option value="">All Groups</option>
                    <option value="none" <?= $filters['group'] === 'none' ? 'selected' : '' ?>>No Group</option>
                    <?php foreach ($groups as $grp): ?>
                    <option value="<?= $grp['id'] ?>" <?= $filters['group'] == $grp['id'] ? 'selected' : '' ?>><?= e($grp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($hasFilters): ?>
                <a href="/agent/tickets?reset=1" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Saved Filters & Column Chooser -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <span class="text-muted small"><i class="bi bi-bookmark me-1"></i>Saved:</span>
    <?php foreach ($savedFilters as $sf):
        $sfData  = json_decode($sf['filters'], true) ?: [];
        $sfUrl   = '/agent/tickets' . ($sfData ? '?' . http_build_query($sfData) : '');
        $isOwner = ((int) $sf['user_id'] === Auth::id());
        $isActive = true;
        foreach (['status','priority','type','location','agent','group','q'] as $fk) {
            $saved   = (string) ($sfData[$fk] ?? '');
            $current = (string) ($filters[$fk] ?? '');
            if ($saved !== $current) { $isActive = false; break; }
        }
    ?>
    <div class="btn-group btn-group-sm">
        <a href="<?= e($sfUrl) ?>"
           class="btn <?= $isActive ? 'text-white' : 'btn-outline-secondary' ?>"
           <?= $isActive ? 'style="background:var(--ld-primary);"' : '' ?>
           title="<?= $isOwner ? '' : 'Shared by ' . e($sf['owner_name']) ?>">
            <?php if ($sf['is_default'] && $isOwner): ?>
                <i class="bi bi-star-fill text-warning me-1" title="Default filter"></i>
            <?php endif; ?>
            <?php if ($sf['is_shared'] && !$isOwner): ?>
                <i class="bi bi-people-fill me-1" title="Shared by <?= e($sf['owner_name']) ?>"></i>
            <?php elseif ($sf['is_shared'] && $isOwner): ?>
                <i class="bi bi-share me-1" title="Shared"></i>
            <?php endif; ?>
            <?= e($sf['name']) ?>
        </a>
        <?php if ($isOwner): ?>
        <button type="button" class="btn <?= $isActive ? 'text-white border-start' : 'btn-outline-secondary' ?> dropdown-toggle dropdown-toggle-split"
                <?= $isActive ? 'style="background:#4338ca;"' : '' ?>
                data-bs-toggle="dropdown" aria-expanded="false">
            <span class="visually-hidden">Options</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <form method="POST" action="/agent/tickets/filters/<?= $sf['id'] ?>/toggle-default" class="d-inline">
                    <?= csrfField() ?>
                    <button type="submit" class="dropdown-item">
                        <i class="bi bi-star<?= $sf['is_default'] ? '-fill text-warning' : '' ?> me-2"></i>
                        <?= $sf['is_default'] ? 'Remove Default' : 'Set as Default' ?>
                    </button>
                </form>
            </li>
            <li>
                <form method="POST" action="/agent/tickets/filters/<?= $sf['id'] ?>/toggle-share" class="d-inline">
                    <?= csrfField() ?>
                    <button type="submit" class="dropdown-item">
                        <i class="bi bi-<?= $sf['is_shared'] ? 'lock-fill' : 'people' ?> me-2"></i>
                        <?= $sf['is_shared'] ? 'Make Private' : 'Share with Team' ?>
                    </button>
                </form>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="/agent/tickets/filters/<?= $sf['id'] ?>/delete"
                      onsubmit="return confirm('Delete this saved filter?')">
                    <?= csrfField() ?>
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bi bi-trash me-2"></i>Delete
                    </button>
                </form>
            </li>
        </ul>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($hasFilters): ?>
    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#saveFilterModal">
        <i class="bi bi-bookmark-plus me-1"></i>Save Current Filter
    </button>
    <?php endif; ?>

    <!-- Column Chooser -->
    <div class="dropdown ms-auto">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            <i class="bi bi-layout-three-columns me-1"></i>Columns
        </button>
        <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:200px;">
            <form method="POST" action="/agent/tickets/columns">
                <?= csrfField() ?>
                <input type="hidden" name="_redirect" value="<?= e($currentUrl) ?>">
                <h6 class="dropdown-header px-0 pt-0">Visible Columns</h6>
                <?php foreach ($allColumns as $colKey => $colLabel): ?>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="columns[]"
                           value="<?= $colKey ?>" id="col_<?= $colKey ?>"
                           <?= in_array($colKey, $visibleColumns) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="col_<?= $colKey ?>"><?= e($colLabel) ?></label>
                </div>
                <?php endforeach; ?>
                <hr class="my-2">
                <button type="submit" class="btn btn-sm text-white w-100" style="background:var(--ld-primary);">Apply</button>
            </form>
        </div>
    </div>
</div>

<!-- Save Filter Modal -->
<div class="modal fade" id="saveFilterModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="/agent/tickets/filters/save">
                <?= csrfField() ?>
                <?php foreach ($filters as $fk => $fv): ?>
                    <?php if ($fv !== ''): ?>
                    <input type="hidden" name="<?= $fk === 'q' ? 'q' : e($fk) ?>" value="<?= e($fv) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Save Filter</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small text-muted">Filter Name</label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. My Open Tickets" required autofocus>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px"><a href="<?= sortUrl('id', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark"># <?= sortIcon('id', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('subject', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Subject <?= sortIcon('subject', $sort, $dir) ?></a></th>
                    <?php if (in_array('status', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('status', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Status <?= sortIcon('status', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('priority', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('priority', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Priority <?= sortIcon('priority', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('type', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('type', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Type <?= sortIcon('type', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('agent', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('agent', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Assigned To <?= sortIcon('agent', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('group', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('group', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Group <?= sortIcon('group', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('creator', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('creator', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Created By <?= sortIcon('creator', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('location', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('location', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Location <?= sortIcon('location', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('sla', $visibleColumns)): ?>
                    <th>SLA</th>
                    <?php endif; ?>
                    <?php if (in_array('created_at', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('created_at', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Created <?= sortIcon('created_at', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('due_date', $visibleColumns)): ?>
                    <th><a href="<?= sortUrl('due_date', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Due <?= sortIcon('due_date', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="<?= $colCount ?>" class="text-center py-4 text-muted">No tickets found.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                    <?php $isAssignedToMe = ($t['assigned_to'] == Auth::id()); ?>
                    <tr style="cursor:pointer;<?= $isAssignedToMe ? 'background:#eef2ff;' : '' ?>"
                        onclick="window.location='/agent/tickets/<?= $t['id'] ?>'">
                        <td class="text-muted fw-bold"><?= $t['id'] ?></td>
                        <td>
                            <a href="/agent/tickets/<?= $t['id'] ?>" class="text-decoration-none fw-semibold text-dark">
                                <?= e($t['subject']) ?>
                            </a>
                            <?php if ($isAssignedToMe): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Mine</span>
                            <?php endif; ?>
                        </td>
                        <?php if (in_array('status', $visibleColumns)): ?>
                        <td>
                            <span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?>">
                                <?= e($statusLabels[$t['status']] ?? $t['status']) ?>
                            </span>
                            <?php if ($t['merged_into_ticket_id']): ?>
                            <a href="/agent/tickets/<?= (int) $t['merged_into_ticket_id'] ?>" class="badge bg-secondary text-decoration-none ms-1" title="Merged into #<?= (int) $t['merged_into_ticket_id'] ?>">
                                <i class="bi bi-arrow-right-circle"></i> Merged
                            </a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('priority', $visibleColumns)): ?>
                        <td>
                            <?php if ($t['priority_name']): ?>
                            <span class="badge" style="background:<?= e($t['priority_color']) ?>;">
                                <?= e($t['priority_name']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('type', $visibleColumns)): ?>
                        <td class="text-muted"><?= e($t['type_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('agent', $visibleColumns)): ?>
                        <td><?= e($t['agent_name'] ?: '— Unassigned —') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('group', $visibleColumns)): ?>
                        <td class="text-muted"><?= e($t['group_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('creator', $visibleColumns)): ?>
                        <td class="text-muted"><?= e($t['creator_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('location', $visibleColumns)): ?>
                        <td class="text-muted"><?= e($t['location_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('sla', $visibleColumns)): ?>
                        <td>
                            <?php if ($t['sla_state']): ?>
                            <span class="badge bg-<?= $slaStateColors[$t['sla_state']] ?? 'secondary' ?>" title="<?= e(ucfirst(str_replace('_', ' ', $t['sla_state']))) ?>">
                                <i class="bi bi-stopwatch"></i>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('created_at', $visibleColumns)): ?>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                        <?php endif; ?>
                        <?php if (in_array('due_date', $visibleColumns)): ?>
                        <td class="text-muted small">
                            <?php if ($t['due_date']): ?>
                                <?php
                                $due = strtotime($t['due_date']);
                                $overdue = $due < time() && !in_array($t['status'], ['resolved', 'closed']);
                                ?>
                                <span class="<?= $overdue ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('M j, Y', $due) ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>sessionStorage.setItem('agentTicketListUrl', window.location.href);</script>

<?php if ($totalPages > 1): ?>
<?php
    $pagerParams = array_filter($filters, fn($v) => $v !== '');
    if ($sort !== 'created_at' || $dir !== 'desc') {
        $pagerParams['sort'] = $sort;
        $pagerParams['dir']  = $dir;
    }
    $pagerBase   = '/agent/tickets';
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
