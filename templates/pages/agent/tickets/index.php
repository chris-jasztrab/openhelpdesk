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
$hasFilters = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
$sortParams = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
if ($perPage !== 25) $sortParams['per_page'] = $perPage;
$allColumns = ticketColumnDefinitions();
$colCount = 3 + count($visibleColumns); // checkbox + id + subject + visible toggleable columns
$currentUrl = '/agent/tickets' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
?>
<script>
(function () {
    var KEY = 'ticketFilter_agent';
    var DEFAULT_URL = <?= json_encode($defaultFilterUrl) ?>;
    var today = new Date().toISOString().slice(0, 10);
    var search = window.location.search;
    var params = new URLSearchParams(search);

    if (params.get('reset') === '1') {
        // User explicitly cleared filters — remember cleared state for today
        try { localStorage.setItem(KEY, JSON.stringify({ date: today, query: '', cleared: true })); } catch (e) {}
        return;
    }

    if (search) {
        // Page loaded with filter params — remember them for today
        try { localStorage.setItem(KEY, JSON.stringify({ date: today, query: search, cleared: false })); } catch (e) {}
        return;
    }

    // Bare URL — decide what to show
    try {
        var stored = JSON.parse(localStorage.getItem(KEY));
        if (stored && stored.date === today) {
            if (!stored.cleared && stored.query) {
                window.location.replace(window.location.pathname + stored.query);
            }
            // else: user cleared filters today — show bare/no-filter page
            return;
        }
    } catch (e) {}

    // New day or no history — apply default filter if one exists
    if (DEFAULT_URL) {
        window.location.replace(DEFAULT_URL);
    }
})();
</script>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Tickets</h2>
    <div class="d-flex align-items-center gap-2">
        <?php if ($hasFilters): ?><span class="badge bg-secondary fs-6"><?= $totalTickets ?> filtered of <?= $allTickets ?> total</span><?php else: ?><span class="badge bg-secondary fs-6"><?= $totalTickets ?> total</span><?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="filterPanelBtn" onclick="filterPanelToggle()">
            <i class="bi bi-funnel me-1"></i>Filters
            <?php if ($hasFilters): ?><span class="badge bg-primary rounded-pill ms-1"><?= count($hasFilters) ?></span><?php endif; ?>
        </button>
        <div class="dropdown">
            <button id="tour-columns-btn" class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
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
        <a id="tour-templates-link" href="/admin/ticket-templates" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-collection me-1"></i>Templates
        </a>
        <a id="tour-new-ticket-btn" href="/agent/tickets/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
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

<!-- Filter Panel Backdrop -->
<div class="filter-panel-backdrop" id="filterPanelBackdrop" onclick="filterPanelClose()"></div>

<!-- Filter Panel -->
<div class="filter-panel" id="filterPanel">
    <div class="filter-panel-header">
        <span class="fw-semibold"><i class="bi bi-funnel me-1"></i>Filters</span>
        <button type="button" class="btn-close" onclick="filterPanelClose()" aria-label="Close"></button>
    </div>
    <div class="filter-panel-body">
        <!-- Saved Filters -->
        <div class="small fw-semibold mb-2"><i class="bi bi-bookmark me-1"></i>Saved Filters</div>
        <?php if (empty($savedFilters)): ?>
        <p class="text-muted small">No saved filters yet.</p>
        <?php else: ?>
        <div class="d-flex flex-column gap-1 mb-2">
            <?php foreach ($savedFilters as $sf):
                $sfData  = json_decode($sf['filters'], true) ?: [];
                $sfUrl   = '/agent/tickets' . ($sfData ? '?' . http_build_query($sfData) : '');
                $isOwner = ((int) $sf['user_id'] === Auth::id());
                $isActive = true;
                foreach (['q', 'watched'] as $fk) {
                    if (($sfData[$fk] ?? '') !== ($filters[$fk] ?? '')) { $isActive = false; break; }
                }
                if ($isActive) {
                    foreach (['status','priority','type','location','agent','group'] as $fk) {
                        $saved   = array_map('strval', (array) ($sfData[$fk] ?? []));
                        $current = array_map('strval', (array) ($filters[$fk] ?? []));
                        sort($saved); sort($current);
                        if ($saved !== $current) { $isActive = false; break; }
                    }
                }
            ?>
            <div class="btn-group btn-group-sm">
                <a href="<?= e($sfUrl) ?>"
                   class="btn text-start <?= $isActive ? 'text-white' : 'btn-outline-secondary' ?>"
                   style="<?= $isActive ? 'background:var(--ld-primary);' : '' ?>overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                   title="<?= $isOwner ? '' : 'Shared by ' . e($sf['owner_name']) ?>">
                    <?php if ($sf['is_default'] && $isOwner): ?>
                        <i class="bi bi-star-fill text-warning me-1"></i>
                    <?php endif; ?>
                    <?php if ($sf['is_shared'] && !$isOwner): ?>
                        <i class="bi bi-people-fill me-1"></i>
                    <?php elseif ($sf['is_shared'] && $isOwner): ?>
                        <i class="bi bi-share me-1"></i>
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
        </div>
        <?php endif; ?>
        <?php if ($hasFilters): ?>
        <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-1" data-bs-toggle="modal" data-bs-target="#saveFilterModal">
            <i class="bi bi-bookmark-plus me-1"></i>Save Current Filter
        </button>
        <?php endif; ?>
        <hr class="my-3">
        <form method="GET" action="/agent/tickets">
            <?php if ($perPage !== 25): ?><input type="hidden" name="per_page" value="<?= $perPage ?>"><?php endif; ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <input type="text" class="form-control form-control-sm" name="q"
                       value="<?= e($filters['q']) ?>" placeholder="Search subject...">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <div class="filter-checklist">
                    <?php foreach ($statusLabels as $val => $lbl): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="status[]" value="<?= $val ?>" <?= in_array($val, $filters['status'], true) ? 'checked' : '' ?>>
                        <span><?= e($lbl) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Priority</label>
                <div class="filter-checklist">
                    <?php foreach ($priorities as $p): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="priority[]" value="<?= $p['id'] ?>" <?= in_array((string)$p['id'], $filters['priority'], true) ? 'checked' : '' ?>>
                        <span><?= e($p['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Type</label>
                <div class="filter-checklist">
                    <?php foreach ($types as $tp): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="type[]" value="<?= $tp['id'] ?>" <?= in_array((string)$tp['id'], $filters['type'], true) ? 'checked' : '' ?>>
                        <span><?= e($tp['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1"><?= label('location.singular') ?></label>
                <div class="filter-checklist">
                    <?php foreach ($locations as $loc): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="location[]" value="<?= $loc['id'] ?>" <?= in_array((string)$loc['id'], $filters['location'], true) ? 'checked' : '' ?>>
                        <span><?= e($loc['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Agent</label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="agent[]" value="mine" <?= in_array('mine', $filters['agent'], true) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic">My Tickets</span>
                    </label>
                    <label class="filter-check-item">
                        <input type="checkbox" name="agent[]" value="unassigned" <?= in_array('unassigned', $filters['agent'], true) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic">Unassigned</span>
                    </label>
                    <?php foreach ($agents as $ag): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="agent[]" value="<?= $ag['id'] ?>" <?= in_array((string)$ag['id'], $filters['agent'], true) ? 'checked' : '' ?>>
                        <span><?= e($ag['first_name'] . ' ' . $ag['last_name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Group</label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="group[]" value="none" <?= in_array('none', $filters['group'], true) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic">No Group</span>
                    </label>
                    <?php foreach ($groups as $grp): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="group[]" value="<?= $grp['id'] ?>" <?= in_array((string)$grp['id'], $filters['group'], true) ? 'checked' : '' ?>>
                        <span><?= e($grp['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Watching</label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="watched" value="1" <?= !empty($filters['watched']) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic"><i class="bi bi-eye me-1"></i>My Watched Tickets</span>
                    </label>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Date</label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="resolved_today" value="1" <?= !empty($filters['resolved_today']) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic"><i class="bi bi-check-circle me-1"></i>Resolved Today</span>
                    </label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm text-white flex-grow-1" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="/agent/tickets?reset=1" class="btn btn-sm btn-outline-secondary" title="Clear all filters">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </a>
            </div>
        </form>

    </div>
</div>

<!-- Save Filter Modal -->
<div class="modal fade" id="saveFilterModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="/agent/tickets/filters/save">
                <?= csrfField() ?>
                <?php foreach ($filters as $fk => $fv): ?>
                    <?php if (is_array($fv)): ?>
                        <?php foreach ($fv as $v): ?>
                        <input type="hidden" name="<?= e($fk) ?>[]" value="<?= e($v) ?>">
                        <?php endforeach; ?>
                    <?php elseif ($fv !== ''): ?>
                    <input type="hidden" name="<?= e($fk) ?>" value="<?= e($fv) ?>">
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
    <!-- Bulk Action Bar (shown when tickets are selected) -->
    <div id="bulkBar" style="display:none;background:#eef2ff;border-bottom:1px solid #d1d9f0;padding:.5rem .75rem;align-items:center;gap:.5rem;">
        <span id="bulkCount" class="text-muted small fw-semibold me-1">0 selected</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('assign')">
            <i class="bi bi-person-check me-1"></i>Assign
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('close')">
            <i class="bi bi-check-circle me-1"></i>Close
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('merge')">
            <i class="bi bi-diagram-2 me-1"></i>Merge
        </button>
        <button type="button" class="btn btn-sm btn-link text-muted ms-auto p-0" onclick="clearSelection()" title="Clear selection">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div style="overflow-x:hidden;overflow-y:auto;max-height:calc(100vh - 260px);">
        <table class="table table-hover align-middle mb-0" id="ticketTable" style="width:100%;visibility:hidden;">
            <thead class="table-light" style="position:sticky;top:0;z-index:5;box-shadow:0 1px 2px rgba(0,0,0,.06);">
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="selectAll" class="form-check-input" title="Select all"></th>
                    <th style="width:60px"><a href="<?= sortUrl('id', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark"># <?= sortIcon('id', $sort, $dir) ?></a></th>
                    <th class="subject-col" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><a href="<?= sortUrl('subject', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Subject <?= sortIcon('subject', $sort, $dir) ?></a></th>
                    <?php if (in_array('status', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('status', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Status <?= sortIcon('status', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('priority', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('priority', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Priority <?= sortIcon('priority', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('type', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('type', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Type <?= sortIcon('type', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('agent', $visibleColumns)): ?>
                    <th style="white-space:nowrap;text-align:right;"><a href="<?= sortUrl('agent', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Assigned To <?= sortIcon('agent', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('group', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('group', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Group <?= sortIcon('group', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('creator', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('creator', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Created By <?= sortIcon('creator', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('location', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('location', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark"><?= label('location.singular') ?> <?= sortIcon('location', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('sla', $visibleColumns)): ?>
                    <th style="white-space:nowrap;">SLA</th>
                    <?php endif; ?>
                    <?php if (in_array('created_at', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('created_at', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Created <?= sortIcon('created_at', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('due_date', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('due_date', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Due <?= sortIcon('due_date', $sort, $dir) ?></a></th>
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
                        <td onclick="event.stopPropagation()">
                            <input type="checkbox" class="ticket-cb form-check-input" value="<?= $t['id'] ?>" data-subject="<?= e($t['subject']) ?>">
                        </td>
                        <td class="text-muted fw-bold" style="white-space:nowrap;"><?= $t['id'] ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <a href="/agent/tickets/<?= $t['id'] ?>" class="text-decoration-none fw-semibold text-dark">
                                <?= e($t['subject']) ?>
                            </a>
                            <?php if ($isAssignedToMe): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Mine</span>
                            <?php endif; ?>
                        </td>
                        <?php if (in_array('status', $visibleColumns)): ?>
                        <td style="white-space:nowrap;">
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
                        <td style="white-space:nowrap;">
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
                        <td style="white-space:nowrap;overflow:hidden;"><?php if ($t['type_name']): ?><span class="badge" style="background:<?= e($t['type_color'] ?: '#6c757d') ?>;"><?= e($t['type_name']) ?></span><?php else: ?><span class="text-muted small">Not Set</span><?php endif; ?></td>
                        <?php endif; ?>
                        <?php if (in_array('agent', $visibleColumns)): ?>
                        <?php $qaAgents = $t['group_id'] ? ($groupAgents[(int)$t['group_id']] ?? []) : $allAgentsForAssign; ?>
                        <td style="white-space:nowrap;overflow:hidden;text-align:right;">
                            <span class="d-inline-flex align-items-center gap-1 quick-assign-wrap" data-ticket-id="<?= (int)$t['id'] ?>">
                                <span class="quick-assign-name"><?= e($t['agent_name'] ?: '— Unassigned —') ?></span>
                                <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-assign-btn"
                                        type="button"
                                        data-agents="<?= e(json_encode($qaAgents)) ?>"
                                        title="Change assignee"
                                        style="line-height:1;">
                                    <i class="bi bi-chevron-down" style="font-size:0.65rem;"></i>
                                </button>
                            </span>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('group', $visibleColumns)): ?>
                        <td class="text-muted" style="white-space:nowrap;overflow:hidden;"><?= e($t['group_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('creator', $visibleColumns)): ?>
                        <td class="text-muted" style="white-space:nowrap;overflow:hidden;"><?= e($t['creator_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('location', $visibleColumns)): ?>
                        <td class="text-muted" style="white-space:nowrap;overflow:hidden;"><?= e($t['location_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('sla', $visibleColumns)): ?>
                        <td style="white-space:nowrap;">
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
                        <td class="text-muted small" style="white-space:nowrap;"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                        <?php endif; ?>
                        <?php if (in_array('due_date', $visibleColumns)): ?>
                        <td class="text-muted small" style="white-space:nowrap;">
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

<!-- Bulk Actions Form (submitted programmatically) -->
<form id="bulkForm" method="POST" action="/agent/tickets/bulk" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" id="bulkActionInput">
</form>

<!-- Bulk Assign Modal -->
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Assign Tickets</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold mb-1">Assign to</label>
                <select id="bulkAssignAgent" class="form-select form-select-sm">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($agents as $ag): ?>
                    <option value="<?= $ag['id'] ?>"><?= e($ag['first_name'] . ' ' . $ag['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" onclick="submitBulkAssign()">Assign</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Merge Modal -->
<div class="modal fade" id="bulkMergeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-diagram-2 me-2"></i>Merge Tickets</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Select which ticket should be the <strong>primary</strong>. All others will be closed and linked to it.</p>
                <div id="bulkMergeList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" onclick="submitBulkMerge()">
                    <i class="bi bi-diagram-2 me-1"></i>Merge Tickets
                </button>
            </div>
        </div>
    </div>
</div>
<script>
sessionStorage.setItem('agentTicketListUrl', window.location.href);
(function () {
    // ── Filter panel ─────────────────────────────────────────────────────────
    function filterPanelOpen() {
        document.getElementById('filterPanel').classList.add('open');
        document.getElementById('filterPanelBackdrop').classList.add('open');
        sessionStorage.setItem('agentFilterPanelOpen', '1');
    }
    function filterPanelClose() {
        document.getElementById('filterPanel').classList.remove('open');
        document.getElementById('filterPanelBackdrop').classList.remove('open');
        sessionStorage.setItem('agentFilterPanelOpen', '0');
    }
    window.filterPanelToggle = function () {
        document.getElementById('filterPanel').classList.contains('open') ? filterPanelClose() : filterPanelOpen();
    };
    window.filterPanelClose = filterPanelClose;
    if (sessionStorage.getItem('agentFilterPanelOpen') === '1') filterPanelOpen();

    // ── Bulk selection ────────────────────────────────────────────────────────
    var selectedIds = new Set();

    function updateBulkBar() {
        var bar = document.getElementById('bulkBar');
        var cnt = document.getElementById('bulkCount');
        if (selectedIds.size > 0) {
            bar.style.display = 'flex';
            cnt.textContent = selectedIds.size + ' selected';
        } else {
            bar.style.display = 'none';
        }
    }

    document.getElementById('selectAll').addEventListener('change', function () {
        var checked = this.checked;
        document.querySelectorAll('.ticket-cb').forEach(function (cb) {
            cb.checked = checked;
            if (checked) { selectedIds.add(cb.value); } else { selectedIds.delete(cb.value); }
        });
        updateBulkBar();
    });

    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('ticket-cb')) return;
        if (e.target.checked) { selectedIds.add(e.target.value); } else { selectedIds.delete(e.target.value); }
        var all     = document.querySelectorAll('.ticket-cb');
        var numChk  = document.querySelectorAll('.ticket-cb:checked').length;
        var sa      = document.getElementById('selectAll');
        sa.checked       = all.length > 0 && numChk === all.length;
        sa.indeterminate = numChk > 0 && numChk < all.length;
        updateBulkBar();
    });

    window.bulkAction = function (action) {
        if (selectedIds.size === 0) return;
        if (action === 'assign') {
            new bootstrap.Modal(document.getElementById('bulkAssignModal')).show();
            return;
        }
        if (action === 'merge') {
            if (selectedIds.size < 2) { alert('Select at least 2 tickets to merge.'); return; }
            var list = document.getElementById('bulkMergeList');
            list.innerHTML = '';
            var first = true;
            selectedIds.forEach(function(id) {
                var cb = document.querySelector('.ticket-cb[value="' + id + '"]');
                var subject = cb ? cb.dataset.subject : '';
                var div = document.createElement('div');
                div.className = 'form-check mb-1';
                div.innerHTML = '<input class="form-check-input" type="radio" name="bulkPrimary" id="bp' + id + '" value="' + id + '"'
                    + (first ? ' checked' : '') + '> '
                    + '<label class="form-check-label small" for="bp' + id + '"><strong>#' + id + '</strong>'
                    + (subject ? ' \u2014 ' + subject.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '') + '</label>';
                list.appendChild(div);
                first = false;
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkMergeModal')).show();
            return;
        }
        submitBulk(action, null);
    };

    window.submitBulkMerge = function () {
        var checked = document.querySelector('input[name="bulkPrimary"]:checked');
        if (!checked) { alert('Please select a primary ticket.'); return; }
        var modal = bootstrap.Modal.getInstance(document.getElementById('bulkMergeModal'));
        if (modal) modal.hide();
        submitBulk('merge', null, checked.value);
    };

    window.submitBulkAssign = function () {
        var agentId = document.getElementById('bulkAssignAgent').value;
        var modal   = bootstrap.Modal.getInstance(document.getElementById('bulkAssignModal'));
        if (modal) modal.hide();
        submitBulk('assign', agentId);
    };

    function submitBulk(action, agentId, primaryId) {
        var form = document.getElementById('bulkForm');
        document.getElementById('bulkActionInput').value = action;
        form.querySelectorAll('input[name="ticket_ids[]"]').forEach(function (el) { el.remove(); });
        selectedIds.forEach(function (id) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'ticket_ids[]'; inp.value = id;
            form.appendChild(inp);
        });
        var pi = form.querySelector('input[name="primary_ticket_id"]');
        if (primaryId !== undefined && primaryId !== null) {
            if (!pi) { pi = document.createElement('input'); pi.type = 'hidden'; pi.name = 'primary_ticket_id'; form.appendChild(pi); }
            pi.value = primaryId;
        } else if (pi) { pi.remove(); }
        var ag = form.querySelector('input[name="assign_to"]');
        if (agentId !== null) {
            if (!ag) { ag = document.createElement('input'); ag.type = 'hidden'; ag.name = 'assign_to'; form.appendChild(ag); }
            ag.value = agentId;
        } else if (ag) {
            ag.remove();
        }
        form.submit();
    }

    window.clearSelection = function () {
        selectedIds.clear();
        document.querySelectorAll('.ticket-cb').forEach(function (cb) { cb.checked = false; });
        var sa = document.getElementById('selectAll');
        sa.checked = false; sa.indeterminate = false;
        updateBulkBar();
    };
})();

    // Measure natural column widths then switch to fixed layout so subject truncates
    (function () {
        var tbl = document.getElementById("ticketTable");
        if (!tbl) return;
        tbl.querySelectorAll("thead th:not(.subject-col)").forEach(function (th) {
            th.style.width = th.offsetWidth + "px";
        });
        tbl.style.tableLayout = "fixed";
        tbl.style.visibility = "";
    })();

    // Quick-assign: custom fixed-position dropdown
    (function () {
        var activeMenu = null;
        var activeBtn  = null;
        var esc = function (s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

        function closeMenu() {
            if (activeMenu) { activeMenu.remove(); activeMenu = null; activeBtn = null; }
        }

        function openMenu(btn) {
            closeMenu();
            var wrap = btn.closest('.quick-assign-wrap');
            var ticketId = wrap ? wrap.dataset.ticketId : '';
            var agents = [];
            try { agents = JSON.parse(btn.dataset.agents || '[]'); } catch (_) {}
            var html = '<ul class="dropdown-menu show shadow-sm" style="position:fixed;z-index:1060;min-width:180px;max-height:260px;overflow-y:auto;font-size:0.85rem;">';
            html += '<li><a class="dropdown-item quick-assign-floating-item" href="#" data-agent-id="" data-ticket-id="' + esc(ticketId) + '">— Unassigned —</a></li>';
            if (agents.length) {
                html += '<li><hr class="dropdown-divider"></li>';
                agents.forEach(function (a) {
                    html += '<li><a class="dropdown-item quick-assign-floating-item" href="#" data-agent-id="' + esc(a.id) + '" data-ticket-id="' + esc(ticketId) + '">' + esc(a.name) + '</a></li>';
                });
            }
            html += '</ul>';
            var div = document.createElement('div');
            div.innerHTML = html;
            var menu = div.firstChild;
            document.body.appendChild(menu);
            var rect = btn.getBoundingClientRect();
            menu.style.top = (rect.bottom + 2) + 'px';
            menu.style.left = rect.left + 'px';
            activeMenu = menu;
            activeBtn  = btn;
        }

        // Attach directly to each button so stopPropagation only blocks tr navigation
        document.querySelectorAll('.quick-assign-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (activeBtn === btn) { closeMenu(); return; }
                openMenu(btn);
            });
        });

        // Handle floating menu item clicks and outside-close
        document.addEventListener('click', function (e) {
            var item = e.target.closest('.quick-assign-floating-item');
            if (item) {
                e.preventDefault();
                var agentId  = item.dataset.agentId;
                var ticketId = item.dataset.ticketId;
                var wrap = document.querySelector('.quick-assign-wrap[data-ticket-id="' + ticketId + '"]');
                closeMenu();
                if (!wrap) return;
                var nameSpan = wrap.querySelector('.quick-assign-name');
                var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                fetch('/api/tickets/' + ticketId + '/assign', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                    body: JSON.stringify({assigned_to: agentId === '' ? null : parseInt(agentId, 10)})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.success) { nameSpan.textContent = data.agent_name || '— Unassigned —'; }
                }).catch(function () {});
                return;
            }
            if (!e.target.closest('.quick-assign-btn')) { closeMenu(); }
        });

        window.addEventListener('scroll', closeMenu, true);
        window.addEventListener('resize', closeMenu);
    })();
</script>

<?php
$pagerParams = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
if ($sort !== 'created_at' || $dir !== 'desc') {
    $pagerParams['sort'] = $sort;
    $pagerParams['dir']  = $dir;
}
if ($perPage !== 25) $pagerParams['per_page'] = $perPage;
$pagerBase = '/agent/tickets';
?>
<?php if ($totalTickets > 0): ?>
<nav class="d-flex justify-content-between align-items-center mt-3">
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">
            Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $totalTickets) ?> of <?= $totalTickets ?>
        </span>
        <form method="GET" action="/agent/tickets" class="d-inline">
            <?php foreach ($pagerParams as $pk => $pv): ?>
                <?php if (is_array($pv)): foreach ($pv as $pvi): ?>
                <input type="hidden" name="<?= e($pk) ?>[]" value="<?= e($pvi) ?>">
                <?php endforeach; else: ?>
                <input type="hidden" name="<?= e($pk) ?>" value="<?= e($pv) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <select name="per_page" class="form-select form-select-sm d-inline-block w-auto"
                    onchange="this.form.submit()" aria-label="Tickets per page">
                <?php foreach ([25, 50, 100, 200] as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?> per page</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if ($totalPages > 1): ?>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $page - 1]))) ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $p]))) ?>"
               <?= $p === $page ? 'style="background:var(--ld-primary);border-color:var(--ld-primary);"'  : '' ?>><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($pagerBase . '?' . http_build_query(array_merge($pagerParams, ['page' => $page + 1]))) ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
    <?php endif; ?>
</nav>
<?php endif; ?>
