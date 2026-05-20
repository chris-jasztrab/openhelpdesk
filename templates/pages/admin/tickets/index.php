<?php
$layout       = 'app';
$pageTitle    = 'Tickets';
$sidebarItems = adminSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Tickets'],
];
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'waiting_on_customer' => 'warning', 'waiting_on_third_party' => 'dark', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'waiting_on_customer' => 'Waiting on Customer', 'waiting_on_third_party' => 'Waiting on Third Party', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$slaStateColors = ['on_track' => 'success', 'warning' => 'warning', 'breached' => 'danger'];
$hasFilters = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
$sortParams = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
$allColumns = ticketColumnDefinitions();
if (!slaEnabled()) { unset($allColumns['sla']); }
$colCount = 3 + count($visibleColumns); // checkbox + id + subject + visible toggleable columns
$currentUrl = '/admin/tickets' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
?>
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
(function () {
    var KEY = 'ticketFilter_admin';
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
    <h2 class="fw-bold mb-0">All Tickets</h2>
    <div class="d-flex align-items-center gap-2">
        <?php if ($hasFilters): ?><span class="badge bg-secondary fs-6"><?= $totalTickets ?> filtered of <?= $allTickets ?> total</span><?php else: ?><span class="badge bg-secondary fs-6"><?= $totalTickets ?> total</span><?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="filterPanelBtn" onclick="filterPanelToggle()">
            <i class="bi bi-funnel me-1"></i>Filters
            <?php if ($hasFilters): ?><span class="badge bg-primary rounded-pill ms-1"><?= count($hasFilters) ?></span><?php endif; ?>
        </button>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-layout-three-columns me-1"></i>Columns
            </button>
            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:200px;">
                <form method="POST" action="/admin/tickets/columns">
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
        <?php $exportParams = array_filter($filters, fn($v) => $v !== ''); if (!empty($sort)) { $exportParams['sort'] = $sort; $exportParams['dir'] = $dir; } ?>
        <a href="/admin/tickets/export<?= !empty($exportParams) ? '?' . http_build_query($exportParams) : '' ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a href="/admin/ticket-templates" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-collection me-1"></i>Templates
        </a>
        <a href="/admin/tickets/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>New Ticket
        </a>
    </div>
</div>

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
                $sfUrl   = '/admin/tickets' . ($sfData ? '?' . http_build_query($sfData) : '');
                $isOwner = ((int) $sf['user_id'] === Auth::id());
                $isActive = true;
                foreach (['q', 'date_from', 'date_to', 'watched'] as $fk) {
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
                   title="<?= $isOwner ? ($sf['is_default'] ? 'Default filter' : '') : 'Shared by ' . e($sf['owner_name']) ?>">
                    <?php if ($sf['is_default'] && $isOwner): ?>
                        <i class="bi bi-star-fill me-1 text-warning"></i>
                    <?php elseif ($sf['is_shared'] && !$isOwner): ?>
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
                        <form method="POST" action="/admin/tickets/filters/<?= $sf['id'] ?>/toggle-default" class="d-inline">
                            <?= csrfField() ?>
                            <button type="submit" class="dropdown-item">
                                <i class="bi bi-star<?= $sf['is_default'] ? '-fill text-warning' : '' ?> me-2"></i>
                                <?= $sf['is_default'] ? 'Remove Default' : 'Set as Default' ?>
                            </button>
                        </form>
                    </li>
                    <li>
                        <form method="POST" action="/admin/tickets/filters/<?= $sf['id'] ?>/toggle-share" class="d-inline">
                            <?= csrfField() ?>
                            <button type="submit" class="dropdown-item">
                                <i class="bi bi-<?= $sf['is_shared'] ? 'lock-fill' : 'people' ?> me-2"></i>
                                <?= $sf['is_shared'] ? 'Make Private' : 'Share with Team' ?>
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteFilterModal"
                                data-id="<?= $sf['id'] ?>"
                                data-name="<?= e($sf['name']) ?>"
                                data-url="/admin/tickets/filters/<?= $sf['id'] ?>/delete">
                            <i class="bi bi-trash me-2"></i>Delete
                        </button>
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
        <form method="GET" action="/admin/tickets">
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
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold mb-1">From</label>
                    <input type="date" class="form-control form-control-sm" name="date_from"
                           value="<?= e($filters['date_from'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold mb-1">To</label>
                    <input type="date" class="form-control form-control-sm" name="date_to"
                           value="<?= e($filters['date_to'] ?? '') ?>">
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
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm text-white flex-grow-1" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="/admin/tickets?reset=1" class="btn btn-sm btn-outline-secondary" title="Clear all filters">
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
            <form method="POST" action="/admin/tickets/filters/save">
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
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkAction('delete')">
            <i class="bi bi-trash me-1"></i>Delete
        </button>
        <button type="button" class="btn btn-sm btn-link text-muted ms-auto p-0" onclick="clearSelection()" title="Clear selection">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div style="overflow-x:auto;overflow-y:auto;max-height:calc(100vh - 260px);">
        <table class="table table-hover align-middle mb-0" id="ticketTable" style="width:100%;visibility:hidden;">
            <thead class="table-light" style="position:sticky;top:0;z-index:5;box-shadow:0 1px 2px rgba(0,0,0,.06);">
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="selectAll" class="form-check-input" title="Select all"></th>
                    <th style="width:60px"><a href="<?= sortUrl('id', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark"># <?= sortIcon('id', $sort, $dir) ?></a></th>
                    <th class="subject-col" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><a href="<?= sortUrl('subject', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Subject <?= sortIcon('subject', $sort, $dir) ?></a></th>
                    <?php if (in_array('status', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('status', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Status <?= sortIcon('status', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('priority', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('priority', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Priority <?= sortIcon('priority', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('type', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('type', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Type <?= sortIcon('type', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('agent', $visibleColumns)): ?>
                    <th style="white-space:nowrap;text-align:right;"><a href="<?= sortUrl('agent', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Assigned To <?= sortIcon('agent', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('group', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('group', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Group <?= sortIcon('group', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('creator', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('creator', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Created By <?= sortIcon('creator', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('location', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('location', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark"><?= label('location.singular') ?> <?= sortIcon('location', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('sla', $visibleColumns)): ?>
                    <th style="white-space:nowrap;">SLA</th>
                    <?php endif; ?>
                    <?php if (in_array('created_at', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('created_at', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Created <?= sortIcon('created_at', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('due_date', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('due_date', $sort, $dir, $sortParams, '/admin/tickets') ?>" class="text-decoration-none text-dark">Due <?= sortIcon('due_date', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('confidential', $visibleColumns)): ?>
                    <th style="white-space:nowrap;text-align:center;">Confidential</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="<?= $colCount ?>" class="text-center py-4 text-muted">No tickets found.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                    <?php $isRedacted = isTicketRedactedForUser($t, $confidentialTypeIds ?? [], $adminGroupIds ?? []); ?>
                    <tr style="cursor:pointer;<?= $isRedacted ? 'opacity:0.75;' : '' ?>" onclick="window.location='/admin/tickets/<?= $t['id'] ?>'">
                        <td onclick="event.stopPropagation()">
                            <input type="checkbox" class="ticket-cb form-check-input" value="<?= $t['id'] ?>"
                                   data-subject="<?= $isRedacted ? 'Confidential' : e($t['subject']) ?>"
                                   <?= $isRedacted ? 'data-confidential="1"' : '' ?>>
                        </td>
                        <td class="text-muted fw-bold" style="white-space:nowrap;"><?= $t['id'] ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?php if ($isRedacted): ?>
                            <span class="text-muted fst-italic">
                                <i class="bi bi-shield-lock me-1"></i>[Confidential]
                            </span>
                            <?php else: ?>
                            <a href="/admin/tickets/<?= $t['id'] ?>" class="text-decoration-none fw-semibold text-dark">
                                <?= e($t['subject']) ?>
                            </a>
                            <?php endif; ?>
                        </td>
                        <?php if (in_array('status', $visibleColumns)): ?>
                        <td style="white-space:nowrap;">
                            <span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?>">
                                <?= e($statusLabels[$t['status']] ?? $t['status']) ?>
                            </span>
                            <?php if ($t['merged_into_ticket_id']): ?>
                            <a href="/admin/tickets/<?= (int) $t['merged_into_ticket_id'] ?>" class="badge bg-secondary text-decoration-none ms-1" title="Merged into #<?= (int) $t['merged_into_ticket_id'] ?>">
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
                        <td style="white-space:nowrap;">
                            <span class="d-inline-flex align-items-center gap-1 quick-type-wrap" data-ticket-id="<?= (int)$t['id'] ?>">
                                <span class="quick-type-badge"><?php if ($t['type_name']): ?><span class="badge" style="background:<?= e($t['type_color'] ?: '#6c757d') ?>;"><?= e($t['type_name']) ?></span><?php else: ?><span class="text-muted small">Not Set</span><?php endif; ?></span>
                                <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-type-btn" type="button" title="Change type" style="line-height:1;"><i class="bi bi-chevron-down" style="font-size:0.65rem;"></i></button>
                            </span>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('agent', $visibleColumns)): ?>
                        <?php
                            $qaGroupId = $t['type_group_id'] ? (int)$t['type_group_id'] : ($t['group_id'] ? (int)$t['group_id'] : null);
                            $qaAgents  = $qaGroupId ? ($groupAgents[$qaGroupId] ?? []) : $allAgentsForAssign;
                            $qaTip     = $qaGroupId ? 'Assign (type group: ' . e($t['type_name'] ?? '') . ')' : 'Assign (all agents)';
                        ?>
                        <td style="white-space:nowrap;text-align:right;cursor:default;" onclick="event.stopPropagation()">
                            <span class="d-inline-flex align-items-center gap-1 quick-assign-wrap" data-ticket-id="<?= (int)$t['id'] ?>" style="cursor:pointer;">
                                <span class="quick-assign-name"><?= e($t['agent_name'] ?: 'Unassigned') ?></span>
                                <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-assign-btn"
                                        type="button"
                                        data-agents="<?= e(json_encode(array_values($qaAgents))) ?>"
                                        title="<?= $qaTip ?>"
                                        style="line-height:1;">
                                    <i class="bi bi-chevron-down" style="font-size:0.65rem;"></i>
                                </button>
                            </span>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('group', $visibleColumns)): ?>
                        <td style="white-space:nowrap;overflow:visible;cursor:default;" onclick="event.stopPropagation()">
                            <span class="d-inline-flex align-items-center gap-1 quick-group-wrap" data-ticket-id="<?= (int)$t['id'] ?>" style="cursor:pointer;">
                                <span class="quick-group-name<?= $t['group_name'] ? '' : ' text-muted' ?>"><?= e($t['group_name'] ?: '—') ?></span>
                                <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-group-btn" type="button" title="Change group" style="line-height:1;"><i class="bi bi-chevron-down" style="font-size:0.65rem;"></i></button>
                            </span>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('creator', $visibleColumns)): ?>
                        <td class="text-muted" style="white-space:nowrap;"><?= $isRedacted ? '—' : e($t['creator_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('location', $visibleColumns)): ?>
                        <td class="text-muted" style="white-space:nowrap;"><?= e($t['location_name'] ?? '—') ?></td>
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
                        <?php if (in_array('confidential', $visibleColumns)): ?>
                        <td style="white-space:nowrap;text-align:center;">
                            <?php if (!empty($t['type_confidential'])): ?>
                            <i class="bi bi-shield-lock text-danger" title="Confidential"></i>
                            <?php else: ?>
                            <span class="text-muted">—</span>
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
<form id="bulkForm" method="POST" action="/admin/tickets/bulk" class="d-none">
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
                    <option value="">Unassigned</option>
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

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="bulkDeleteModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Tickets
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete <strong id="bulkDeleteCount"></strong> ticket(s)? <span class="text-danger fw-semibold">This cannot be undone.</span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="bulkDeleteConfirmBtn">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Saved Filter Modal -->
<div class="modal fade" id="deleteFilterModal" tabindex="-1" aria-labelledby="deleteFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteFilterModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Saved Filter
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete saved filter <strong id="deleteFilterName"></strong>?</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteFilterForm" action="">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
sessionStorage.setItem('adminTicketListUrl', window.location.href);
(function () {
    // ── Filter panel ─────────────────────────────────────────────────────────
    function filterPanelOpen() {
        document.getElementById('filterPanel').classList.add('open');
        document.getElementById('filterPanelBackdrop').classList.add('open');
        sessionStorage.setItem('adminFilterPanelOpen', '1');
    }
    function filterPanelClose() {
        document.getElementById('filterPanel').classList.remove('open');
        document.getElementById('filterPanelBackdrop').classList.remove('open');
        sessionStorage.setItem('adminFilterPanelOpen', '0');
    }
    window.filterPanelToggle = function () {
        document.getElementById('filterPanel').classList.contains('open') ? filterPanelClose() : filterPanelOpen();
    };
    window.filterPanelClose = filterPanelClose;
    if (sessionStorage.getItem('adminFilterPanelOpen') === '1') filterPanelOpen();

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
        if (action === 'delete') {
            document.getElementById('bulkDeleteCount').textContent = selectedIds.size;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkDeleteModal')).show();
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

    document.getElementById('bulkDeleteConfirmBtn').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal')).hide();
        submitBulk('delete', null);
    });
})();

document.getElementById('deleteFilterModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteFilterName').textContent = btn.dataset.name;
    document.getElementById('deleteFilterForm').action = btn.dataset.url;
});

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

    // Quick-change: type, group, agent dropdowns
    (function () {
        var quickTypes = <?= json_encode(array_values(array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name'], 'color' => $t['color'] ?: '#6c757d', 'group_id' => $t['group_id'] ? (int)$t['group_id'] : null], $types))) ?>;
        var quickGroups = <?= json_encode(array_values(array_map(fn($g) => ['id' => (int)$g['id'], 'name' => $g['name']], $groups))) ?>;
        var quickGroupAgents = <?= json_encode(array_map(fn($agents) => array_values($agents), $groupAgents)) ?>;
        var quickAllAgents = <?= json_encode(array_values(array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['name']], $allAgentsForAssign))) ?>;
        var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var activeMenu = null;
        var activeBtn  = null;
        var esc = function (s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

        function closeMenu() {
            if (activeMenu) { activeMenu.remove(); activeMenu = null; activeBtn = null; }
        }

        function resizeColumns() {
            var tbl = document.getElementById("ticketTable");
            if (!tbl) return;
            var ths = tbl.querySelectorAll("thead th:not(.subject-col)");
            ths.forEach(function(th) { th.style.width = ''; });
            tbl.style.tableLayout = 'auto';
            ths.forEach(function(th) { th.style.width = th.offsetWidth + 'px'; });
            tbl.style.tableLayout = 'fixed';
        }

        function openMenu(btn, kind) {
            closeMenu();
            var wrapEl = btn.closest('[data-ticket-id]');
            var ticketId = wrapEl ? wrapEl.dataset.ticketId : '';
            var html = '<ul class="dropdown-menu show shadow-sm" style="position:fixed;z-index:1060;min-width:180px;max-height:260px;overflow-y:auto;font-size:0.85rem;">';
            if (kind === 'agent') {
                var agents = [];
                try { agents = JSON.parse(btn.dataset.agents || '[]'); } catch (_) {}
                html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="agent" data-val="" data-ticket-id="' + esc(ticketId) + '">Unassigned</a></li>';
                if (agents.length) {
                    html += '<li><hr class="dropdown-divider"></li>';
                    agents.forEach(function (a) {
                        html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="agent" data-val="' + esc(a.id) + '" data-ticket-id="' + esc(ticketId) + '">' + esc(a.name) + '</a></li>';
                    });
                }
            } else if (kind === 'type') {
                html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="type" data-val="" data-ticket-id="' + esc(ticketId) + '"><span class="text-muted">Not Set</span></a></li>';
                if (quickTypes.length) {
                    html += '<li><hr class="dropdown-divider"></li>';
                    quickTypes.forEach(function (tp) {
                        html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="type" data-val="' + esc(tp.id) + '" data-ticket-id="' + esc(ticketId) + '"><span class="badge" style="background:' + esc(tp.color) + ';">' + esc(tp.name) + '</span></a></li>';
                    });
                }
            } else if (kind === 'group') {
                html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="group" data-val="" data-ticket-id="' + esc(ticketId) + '"><span class="text-muted">None</span></a></li>';
                if (quickGroups.length) {
                    html += '<li><hr class="dropdown-divider"></li>';
                    quickGroups.forEach(function (g) {
                        html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="group" data-val="' + esc(g.id) + '" data-ticket-id="' + esc(ticketId) + '">' + esc(g.name) + '</a></li>';
                    });
                }
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

        function bindBtn(btn, kind) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (activeBtn === btn) { closeMenu(); return; }
                openMenu(btn, kind);
            });
        }

        document.querySelectorAll('.quick-assign-btn').forEach(function (b) { bindBtn(b, 'agent'); });
        document.querySelectorAll('.quick-type-btn').forEach(function (b) { bindBtn(b, 'type'); });
        document.querySelectorAll('.quick-group-btn').forEach(function (b) { bindBtn(b, 'group'); });

        document.querySelectorAll('.quick-assign-wrap').forEach(function (wrap) {
            wrap.addEventListener('click', function (e) {
                if (e.target.closest('.quick-assign-btn')) return;
                var btn = wrap.querySelector('.quick-assign-btn');
                if (btn) { e.stopPropagation(); if (activeBtn === btn) { closeMenu(); } else { openMenu(btn, 'agent'); } }
            });
        });
        document.querySelectorAll('.quick-group-wrap').forEach(function (wrap) {
            wrap.addEventListener('click', function (e) {
                if (e.target.closest('.quick-group-btn')) return;
                var btn = wrap.querySelector('.quick-group-btn');
                if (btn) { e.stopPropagation(); if (activeBtn === btn) { closeMenu(); } else { openMenu(btn, 'group'); } }
            });
        });

        document.addEventListener('click', function (e) {
            var item = e.target.closest('.quick-menu-item');
            if (item) {
                e.preventDefault();
                var kind     = item.dataset.kind;
                var val      = item.dataset.val;
                var ticketId = item.dataset.ticketId;
                closeMenu();
                if (kind === 'agent') {
                    var assignWrap = document.querySelector('.quick-assign-wrap[data-ticket-id="' + ticketId + '"]');
                    if (!assignWrap) return;
                    var nameSpan = assignWrap.querySelector('.quick-assign-name');
                    fetch('/api/tickets/' + ticketId + '/assign', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                        body: JSON.stringify({assigned_to: val === '' ? null : parseInt(val, 10)})
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.success) { nameSpan.textContent = data.agent_name || 'Unassigned'; resizeColumns(); }
                    }).catch(function () {});
                } else if (kind === 'type') {
                    var typeWrap = document.querySelector('.quick-type-wrap[data-ticket-id="' + ticketId + '"]');
                    if (!typeWrap) return;
                    var badge = typeWrap.querySelector('.quick-type-badge');
                    fetch('/api/tickets/' + ticketId + '/set-type', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                        body: JSON.stringify({type_id: val === '' ? null : parseInt(val, 10)})
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.success) {
                            if (data.type_name) {
                                badge.innerHTML = '<span class="badge" style="background:' + esc(data.type_color || '#6c757d') + ';">' + esc(data.type_name) + '</span>';
                            } else {
                                badge.innerHTML = '<span class="text-muted small">Not Set</span>';
                            }
                            var tp = val === '' ? null : quickTypes.find(function (t) { return t.id === parseInt(val, 10); });
                            var newAgents = (tp && tp.group_id && quickGroupAgents[tp.group_id]) ? quickGroupAgents[tp.group_id] : quickAllAgents;
                            var assignBtn = document.querySelector('.quick-assign-wrap[data-ticket-id="' + ticketId + '"] .quick-assign-btn');
                            if (assignBtn) { assignBtn.dataset.agents = JSON.stringify(newAgents); }
                            resizeColumns();
                        }
                    }).catch(function () {});
                } else if (kind === 'group') {
                    var groupWrap = document.querySelector('.quick-group-wrap[data-ticket-id="' + ticketId + '"]');
                    if (!groupWrap) return;
                    var groupNameSpan = groupWrap.querySelector('.quick-group-name');
                    fetch('/api/tickets/' + ticketId + '/set-group', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                        body: JSON.stringify({group_id: val === '' ? null : parseInt(val, 10)})
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.success) {
                            groupNameSpan.textContent = data.group_name || '—';
                            groupNameSpan.className = 'quick-group-name' + (data.group_name ? '' : ' text-muted');
                            var newAgents = data.agents || quickAllAgents;
                            var assignBtn = document.querySelector('.quick-assign-wrap[data-ticket-id="' + ticketId + '"] .quick-assign-btn');
                            if (assignBtn) { assignBtn.dataset.agents = JSON.stringify(newAgents); }
                            resizeColumns();
                        }
                    }).catch(function () {});
                }
                return;
            }
            if (!e.target.closest('.quick-assign-wrap') && !e.target.closest('.quick-type-btn') && !e.target.closest('.quick-group-wrap') && !(activeMenu && activeMenu.contains(e.target))) {
                closeMenu();
            }
        });

        window.addEventListener('scroll', function (e) {
            if (activeMenu && (activeMenu === e.target || activeMenu.contains(e.target))) return;
            closeMenu();
        }, true);
        window.addEventListener('resize', closeMenu);
    })();
</script>

<?php if ($totalPages > 1): ?>
<?php
    // Build base query string preserving filters and sort
    $pagerParams = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
    if ($sort !== 'created_at' || $dir !== 'desc') {
        $pagerParams['sort'] = $sort;
        $pagerParams['dir']  = $dir;
    }
    $pagerBase   = '/admin/tickets';
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
