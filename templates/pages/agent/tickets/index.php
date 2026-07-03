<?php
$layout       = 'app';
$pageTitle    = 'Tickets';
$sidebarItems = agentSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Tickets'],
];
$statusLabels = ticketStatusLabelMap();
$slaStateColors = ['on_track' => 'success', 'warning' => 'warning', 'breached' => 'danger'];
$hasFilters = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
$sortParams = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
if ($perPage !== 25) $sortParams['per_page'] = $perPage;

// Applied-filter pills: build label + a "remove this one filter" URL for each active filter.
$arrayFilterKeys = ['status', 'priority', 'type', 'location', 'agent', 'group'];
$boolFilterKeys  = ['watched', 'resolved_today', 'escalated_to_me'];
$buildRemoveUrl = function (string $removeKey, $removeVal = null) use ($filters, $perPage, $arrayFilterKeys, $boolFilterKeys) {
    $params = [];
    foreach ($arrayFilterKeys as $k) {
        $vals = array_map('strval', (array) ($filters[$k] ?? []));
        if ($k === $removeKey && $removeVal !== null) {
            $vals = array_values(array_filter($vals, fn($v) => $v !== (string) $removeVal));
        }
        if (!empty($vals)) $params[$k] = $vals;
    }
    if ($removeKey !== 'q' && ($filters['q'] ?? '') !== '') $params['q'] = $filters['q'];
    foreach ($boolFilterKeys as $k) {
        if ($k !== $removeKey && !empty($filters[$k])) $params[$k] = 1;
    }
    if ($perPage !== 25) $params['per_page'] = $perPage;
    $qs = http_build_query($params);
    return '/agent/tickets' . ($qs ? '?' . $qs : '');
};
$prioMap = []; foreach ($priorities as $p) $prioMap[(string) $p['id']] = $p['name'];
$typeMap = []; foreach ($types as $tp) $typeMap[(string) $tp['id']] = $tp['name'];
$locMap  = []; foreach ($locations as $l) $locMap[(string) $l['id']] = $l['name'];
$agentMap = ['mine' => 'My Tickets', 'unassigned' => 'Unassigned'];
foreach ($agents as $a) $agentMap[(string) $a['id']] = trim($a['first_name'] . ' ' . $a['last_name']);
$groupMap = ['none' => 'No Group'];
foreach ($groups as $g) $groupMap[(string) $g['id']] = $g['name'];
$appliedPills = [];
foreach ((array) ($filters['status'] ?? []) as $v)   $appliedPills[] = ['label' => 'Status: '   . ($statusLabels[$v] ?? $v), 'url' => $buildRemoveUrl('status', $v)];
foreach ((array) ($filters['priority'] ?? []) as $v) $appliedPills[] = ['label' => 'Priority: ' . ($prioMap[(string) $v] ?? $v), 'url' => $buildRemoveUrl('priority', $v)];
foreach ((array) ($filters['type'] ?? []) as $v)     $appliedPills[] = ['label' => 'Type: '     . ($typeMap[(string) $v] ?? $v), 'url' => $buildRemoveUrl('type', $v)];
foreach ((array) ($filters['location'] ?? []) as $v) $appliedPills[] = ['label' => label('location.singular') . ': ' . ($locMap[(string) $v] ?? $v), 'url' => $buildRemoveUrl('location', $v)];
foreach ((array) ($filters['agent'] ?? []) as $v)    $appliedPills[] = ['label' => 'Assigned To: '    . ($agentMap[(string) $v] ?? $v), 'url' => $buildRemoveUrl('agent', $v)];
foreach ((array) ($filters['group'] ?? []) as $v)    $appliedPills[] = ['label' => 'Group: '    . ($groupMap[(string) $v] ?? $v), 'url' => $buildRemoveUrl('group', $v)];
if (($filters['q'] ?? '') !== '')          $appliedPills[] = ['label' => 'Search: "' . $filters['q'] . '"', 'url' => $buildRemoveUrl('q')];
if (!empty($filters['watched']))           $appliedPills[] = ['label' => 'My Watched Tickets', 'url' => $buildRemoveUrl('watched')];
if (!empty($filters['resolved_today']))    $appliedPills[] = ['label' => 'Resolved Today', 'url' => $buildRemoveUrl('resolved_today')];
if (!empty($filters['escalated_to_me']))   $appliedPills[] = ['label' => 'Escalated to Me', 'url' => $buildRemoveUrl('escalated_to_me')];
$allColumns = ticketColumnDefinitions();
if (!slaEnabled()) { unset($allColumns['sla']); }
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
        <span class="badge bg-secondary fs-6" id="ticketCount"><?php if ($hasFilters): ?><?= $totalTickets ?> filtered of <?= $allTickets ?> total<?php else: ?><?= $totalTickets ?> total<?php endif; ?></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="filterPanelBtn" onclick="filterPanelToggle()">
            <i class="bi bi-funnel me-1"></i>Filters
            <span id="filterCount"><?php if ($hasFilters): ?><span class="badge bg-primary rounded-pill ms-1"><?= count($hasFilters) ?></span><?php endif; ?></span>
        </button>
        <?php if (($ticketView ?? 'table') === 'table'): // Columns only apply to the table layout ?>
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
        <?php endif; ?>
        <?php if (Auth::can('ticket_templates.manage')): ?>
        <a id="tour-templates-link" href="/admin/ticket-templates" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-collection me-1"></i>Templates
        </a>
        <?php endif; ?>
        <a id="tour-new-ticket-btn" href="/agent/tickets/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>New Ticket
        </a>
        <?php $tvBoardUrl = '/agent/tickets/board'; require ROOT_DIR . '/templates/partials/ticket-view-switcher.php'; ?>
    </div>
</div>

<?php if (!empty($groupRestricted)): ?>
<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-people me-1"></i>
    Showing tickets assigned to your group<?= count($myGroups) !== 1 ? 's' : '' ?>:
    <strong><?= implode(', ', array_column($myGroups, 'name')) ?></strong>
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
        <div id="filterPanelDynamic">
        <!-- Applied Filters -->
        <?php if ($appliedPills): ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-semibold"><i class="bi bi-funnel-fill me-1"></i>Applied Filters</span>
                <a href="/agent/tickets?reset=1" class="small text-decoration-none">Clear All</a>
            </div>
            <div class="applied-filters">
                <?php foreach ($appliedPills as $pill): ?>
                <span class="applied-filter-pill">
                    <span class="pill-label"><?= e($pill['label']) ?></span>
                    <a href="<?= e($pill['url']) ?>" class="pill-remove" title="Remove filter" aria-label="Remove filter <?= e($pill['label']) ?>"><i class="bi bi-x-lg"></i></a>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <hr class="my-3">
        <?php endif; ?>
        <!-- Saved Filters -->
        <div class="small fw-semibold mb-2"><i class="bi bi-bookmark me-1"></i>Saved Filters</div>
        <?php if (empty($savedFilters)): ?>
        <p class="text-muted small">No saved filters yet.</p>
        <?php else: ?>
        <div class="d-flex flex-column gap-1 mb-2 saved-filter-list">
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
                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
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
                        <button type="button" class="dropdown-item text-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteFilterModal"
                                data-id="<?= $sf['id'] ?>"
                                data-name="<?= e($sf['name']) ?>"
                                data-url="/agent/tickets/filters/<?= $sf['id'] ?>/delete">
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
        </div>
        <hr class="my-3">
        <form method="GET" action="/agent/tickets" id="filterForm">
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
                        <span><?= e($loc['name']) ?><?= isMetaLocationName((string) $loc['name']) ? ' <span class="text-muted fst-italic">(any)</span>' : '' ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Assigned To</label>
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
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Escalation</label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="escalated_to_me" value="1" <?= !empty($filters['escalated_to_me']) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic"><i class="bi bi-arrow-up-circle me-1"></i>Escalated to Me</span>
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
                <!-- Rebuilt from the live URL on open (ticket-list-ajax.php) -->
                <div id="saveFilterParams">
                <?php foreach ($filters as $fk => $fv): ?>
                    <?php if (is_array($fv)): ?>
                        <?php foreach ($fv as $v): ?>
                        <input type="hidden" name="<?= e($fk) ?>[]" value="<?= e($v) ?>">
                        <?php endforeach; ?>
                    <?php elseif ($fv !== ''): ?>
                    <input type="hidden" name="<?= e($fk) ?>" value="<?= e($fv) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>
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
        <?php if (Auth::can('tickets.bulk_assign')): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('assign')">
            <i class="bi bi-person-check me-1"></i>Assign
        </button>
        <?php endif; ?>
        <?php if (Auth::can('tickets.bulk_close')): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('close')">
            <i class="bi bi-check-circle me-1"></i>Close
        </button>
        <?php endif; ?>
        <?php if (Auth::can('tickets.bulk_merge')): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('merge')">
            <i class="bi bi-diagram-2 me-1"></i>Merge
        </button>
        <?php endif; ?>
        <?php if (Auth::can('tickets.bulk_status')): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('status')">
            <i class="bi bi-flag me-1"></i>Status
        </button>
        <?php endif; ?>
        <?php if (Auth::can('tickets.bulk_priority')): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('priority')">
            <i class="bi bi-exclamation-triangle me-1"></i>Priority
        </button>
        <?php endif; ?>
        <?php if (Auth::can('tickets.bulk_group')): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('group')">
            <i class="bi bi-people me-1"></i>Group
        </button>
        <?php endif; ?>
        <?php if (Auth::can('tickets.bulk_delete')): ?>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkAction('delete')">
            <i class="bi bi-trash me-1"></i>Delete
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-link text-muted ms-auto p-0" onclick="clearSelection()" title="Clear selection">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div id="ticketResults">
    <?php if (($ticketView ?? 'table') === 'inbox'): ?>
    <?php $inboxBase = '/agent/tickets'; require ROOT_DIR . '/templates/partials/ticket-inbox.php'; ?>
    <?php elseif (($ticketView ?? 'table') === 'card'): ?>
    <?php $cardBase = '/agent/tickets'; require ROOT_DIR . '/templates/partials/ticket-cards.php'; ?>
    <?php else: ?>
    <style>
    /* The ticket list fills its container without horizontal scroll: Subject (the
       flex column) keeps a comfortable width, and when space is tight the Type,
       Group, then Location columns shed width and truncate — in that order. */
    #ticketTable td.type-col .quick-type-wrap,
    #ticketTable td.group-col .quick-group-wrap { max-width: 100%; }
    #ticketTable td.type-col .quick-type-badge { min-width: 0; overflow: hidden; }
    #ticketTable td.type-col .quick-type-badge .badge {
        display: inline-block; max-width: 100%; overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap; vertical-align: middle;
    }
    #ticketTable td.group-col .quick-group-name {
        min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    #ticketTable td.type-col .quick-type-btn,
    #ticketTable td.group-col .quick-group-btn { flex: 0 0 auto; }
    #ticketTable td.location-col { overflow: hidden; text-overflow: ellipsis; }
    </style>
    <div style="overflow-x:auto;overflow-y:auto;max-height:calc(100vh - 260px);">
        <table class="table table-hover align-middle mb-0" id="ticketTable" style="width:100%;visibility:hidden;">
            <thead class="table-light" style="position:sticky;top:0;z-index:5;box-shadow:0 1px 2px rgba(0,0,0,.06);">
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="selectAll" class="form-check-input" title="Select all"></th>
                    <th style="width:72px;min-width:72px;"><a href="<?= sortUrl('id', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark"># <?= sortIcon('id', $sort, $dir) ?></a></th>
                    <th class="subject-col" data-flex-col style="min-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><a href="<?= sortUrl('subject', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Subject <?= sortIcon('subject', $sort, $dir) ?></a></th>
                    <?php if (in_array('status', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('status', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Status <?= sortIcon('status', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('priority', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('priority', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Priority <?= sortIcon('priority', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('type', $visibleColumns)): ?>
                    <th class="type-col" style="white-space:nowrap;"><a href="<?= sortUrl('type', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Type <?= sortIcon('type', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('agent', $visibleColumns)): ?>
                    <th style="white-space:nowrap;text-align:right;"><a href="<?= sortUrl('agent', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Assigned To <?= sortIcon('agent', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('group', $visibleColumns)): ?>
                    <th class="group-col" style="white-space:nowrap;"><a href="<?= sortUrl('group', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Group <?= sortIcon('group', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('creator', $visibleColumns)): ?>
                    <th style="white-space:nowrap;"><a href="<?= sortUrl('creator', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark">Created By <?= sortIcon('creator', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if (in_array('location', $visibleColumns)): ?>
                    <th class="location-col" style="white-space:nowrap;"><a href="<?= sortUrl('location', $sort, $dir, $sortParams, '/agent/tickets') ?>" class="text-decoration-none text-dark"><?= label('location.singular') ?> <?= sortIcon('location', $sort, $dir) ?></a></th>
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
                    <?php
                        $isAssignedToMe = ($t['assigned_to'] == Auth::id());
                        $isRedacted = isTicketRedactedForUser($t, $confidentialTypeIds ?? [], $adminGroupIds ?? []);
                    ?>
                    <tr style="cursor:pointer;<?= $isRedacted ? 'opacity:0.75;' : ($isAssignedToMe ? 'background:#eef2ff;' : '') ?>"
                        onclick="window.location='/agent/tickets/<?= $t['id'] ?>'">
                        <td onclick="event.stopPropagation()">
                            <input type="checkbox" class="ticket-cb form-check-input" value="<?= $t['id'] ?>"
                                   data-subject="<?= $isRedacted ? 'Confidential' : e($t['subject']) ?>"
                                   <?= $isRedacted ? 'data-confidential="1"' : '' ?>>
                        </td>
                        <td class="text-muted fw-bold" style="white-space:nowrap;"><?= $t['id'] ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= $isRedacted ? 'Confidential' : e($t['subject']) ?>">
                            <?php if ($isRedacted): ?>
                            <span class="text-muted fst-italic">
                                <i class="bi bi-shield-lock me-1"></i>[Confidential]
                            </span>
                            <?php else: ?>
                            <?php $presence = $ticketPresence[$t['id']] ?? null; ?>
                            <span class="ld-subject-cell" data-ticket-id="<?= (int) $t['id'] ?>">
                                <a href="/agent/tickets/<?= $t['id'] ?>" class="ld-subject-link text-decoration-none fw-semibold text-dark"<?= $presence ? ' style="color:#b45309;"' : '' ?>>
                                    <?= e($t['subject']) ?>
                                </a>
                                <?php if ($isAssignedToMe): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Mine</span>
                                <?php endif; ?>
                                <?php if (!empty($draftTicketIds[$t['id']])): ?>
                                <span class="badge bg-warning bg-opacity-25 text-dark ms-1" title="You have an unsent reply draft on this ticket"><i class="bi bi-pencil-square me-1"></i>Draft</span>
                                <?php endif; ?>
                                <span class="ld-presence-hint d-block small fst-italic" style="color:#b45309;<?= $presence ? '' : 'display:none;' ?>" title="Another staff member currently has this ticket open">
                                    <?php if ($presence): ?>
                                    <i class="bi <?= $presence['replying'] ? 'bi-pencil-fill' : 'bi-eye-fill' ?> me-1"></i><?= $presence['replying'] ? 'Being replied to by ' : 'Opened by ' ?><?= e($presence['name']) ?>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <?php endif; ?>
                        </td>
                        <?php if (in_array('status', $visibleColumns)): ?>
                        <td style="white-space:nowrap;overflow:hidden;cursor:default;" onclick="event.stopPropagation()">
                            <span class="d-inline-flex align-items-center gap-1 quick-status-wrap" data-ticket-id="<?= (int)$t['id'] ?>" data-current-status="<?= e($t['status']) ?>" style="cursor:pointer;">
                                <span class="quick-status-badge"><?= ticketStatusBadgeHtml($t['status']) ?></span>
                                <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-status-btn" type="button" title="Change status" style="line-height:1;"><i class="bi bi-chevron-down" style="font-size:0.65rem;"></i></button>
                            </span>
                            <?php if ($t['merged_into_ticket_id']): ?>
                            <a href="/agent/tickets/<?= (int) $t['merged_into_ticket_id'] ?>" class="badge bg-secondary text-decoration-none ms-1" title="Merged into #<?= (int) $t['merged_into_ticket_id'] ?>">
                                <i class="bi bi-arrow-right-circle"></i> Merged
                            </a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('priority', $visibleColumns)): ?>
                        <td style="white-space:nowrap;overflow:hidden;cursor:default;" onclick="event.stopPropagation()">
                            <span class="d-inline-flex align-items-center gap-1 quick-priority-wrap" data-ticket-id="<?= (int)$t['id'] ?>" style="cursor:pointer;">
                                <span class="quick-priority-badge"><?php if ($t['priority_name']): ?><span class="badge" style="background:<?= e($t['priority_color']) ?>;"><?= e($t['priority_name']) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></span>
                                <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-priority-btn" type="button" title="Change priority" style="line-height:1;"><i class="bi bi-chevron-down" style="font-size:0.65rem;"></i></button>
                            </span>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('type', $visibleColumns)): ?>
                        <td class="type-col" style="white-space:nowrap;overflow:hidden;cursor:default;" onclick="event.stopPropagation()">
                            <span class="d-inline-flex align-items-center gap-1 quick-type-wrap" data-ticket-id="<?= (int)$t['id'] ?>" style="cursor:pointer;">
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
                        <td style="white-space:nowrap;overflow:hidden;text-align:right;cursor:default;" onclick="event.stopPropagation()">
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
                        <td class="group-col" style="white-space:nowrap;overflow:hidden;cursor:default;" onclick="event.stopPropagation()">
                            <span class="d-inline-flex align-items-center gap-1 quick-group-wrap" data-ticket-id="<?= (int)$t['id'] ?>" style="cursor:pointer;">
                                <span class="quick-group-name<?= $t['group_name'] ? '' : ' text-muted' ?>"><?= e($t['group_name'] ?: '—') ?></span>
                                <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-group-btn" type="button" title="Change group" style="line-height:1;"><i class="bi bi-chevron-down" style="font-size:0.65rem;"></i></button>
                            </span>
                        </td>
                        <?php endif; ?>
                        <?php if (in_array('creator', $visibleColumns)): ?>
                        <td class="text-muted" style="white-space:nowrap;overflow:hidden;"><?= $isRedacted ? '—' : e($t['creator_name'] ?? '—') ?></td>
                        <?php endif; ?>
                        <?php if (in_array('location', $visibleColumns)): ?>
                        <td class="text-muted location-col" style="white-space:nowrap;overflow:hidden;"><?= e($t['location_name'] ?? '—') ?></td>
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
    <?php endif; ?>
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

<!-- Bulk Status Modal -->
<div class="modal fade" id="bulkStatusModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Change Status</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold mb-1">Set status to</label>
                <select id="bulkStatusSelect" class="form-select form-select-sm">
                    <?php foreach (ticketActiveStatuses() as $s): ?>
                    <option value="<?= e($s['slug']) ?>"><?= e($s['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" onclick="submitBulkStatus()">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Priority Modal -->
<div class="modal fade" id="bulkPriorityModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Change Priority</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold mb-1">Set priority to</label>
                <select id="bulkPrioritySelect" class="form-select form-select-sm">
                    <option value="">None</option>
                    <?php foreach ($priorities as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>"><?= e($pr['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" onclick="submitBulkPriority()">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Group Modal -->
<div class="modal fade" id="bulkGroupModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Change Group</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold mb-1">Set group to</label>
                <select id="bulkGroupSelect" class="form-select form-select-sm">
                    <option value="">None</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" onclick="submitBulkGroup()">Apply</button>
            </div>
        </div>
    </div>
</div>

<?php if (Auth::can('tickets.bulk_delete')): ?>
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
<?php endif; ?>
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

    // Filter auto-apply is ajax now — see templates/partials/ticket-list-ajax.php.

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

    // Delegated so the bindings survive ajax swaps of the list (#ticketResults).
    document.addEventListener('change', function (e) {
        if (e.target.id === 'selectAll') {
            var checked = e.target.checked;
            document.querySelectorAll('.ticket-cb').forEach(function (cb) {
                cb.checked = checked;
                if (checked) { selectedIds.add(cb.value); } else { selectedIds.delete(cb.value); }
            });
            updateBulkBar();
            return;
        }
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
        if (action === 'status') {
            new bootstrap.Modal(document.getElementById('bulkStatusModal')).show();
            return;
        }
        if (action === 'priority') {
            new bootstrap.Modal(document.getElementById('bulkPriorityModal')).show();
            return;
        }
        if (action === 'group') {
            new bootstrap.Modal(document.getElementById('bulkGroupModal')).show();
            return;
        }
        submitBulk(action, null);
    };

    window.submitBulkStatus = function () {
        var v = document.getElementById('bulkStatusSelect').value;
        var modal = bootstrap.Modal.getInstance(document.getElementById('bulkStatusModal'));
        if (modal) modal.hide();
        submitBulk('status', null, undefined, { status: v });
    };

    window.submitBulkPriority = function () {
        var v = document.getElementById('bulkPrioritySelect').value;
        var modal = bootstrap.Modal.getInstance(document.getElementById('bulkPriorityModal'));
        if (modal) modal.hide();
        submitBulk('priority', null, undefined, { priority_id: v });
    };

    window.submitBulkGroup = function () {
        var v = document.getElementById('bulkGroupSelect').value;
        var modal = bootstrap.Modal.getInstance(document.getElementById('bulkGroupModal'));
        if (modal) modal.hide();
        submitBulk('group', null, undefined, { group_id: v });
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

    function submitBulk(action, agentId, primaryId, extras) {
        var form = document.getElementById('bulkForm');
        document.getElementById('bulkActionInput').value = action;
        form.querySelectorAll('input[name="ticket_ids[]"], input[data-bulk-extra]').forEach(function (el) { el.remove(); });
        selectedIds.forEach(function (id) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'ticket_ids[]'; inp.value = id;
            form.appendChild(inp);
        });
        if (extras) {
            Object.keys(extras).forEach(function (k) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = k; inp.value = extras[k];
                inp.setAttribute('data-bulk-extra', '1');
                form.appendChild(inp);
            });
        }
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

    var bulkDeleteConfirmBtn = document.getElementById('bulkDeleteConfirmBtn');
    if (bulkDeleteConfirmBtn) {
        bulkDeleteConfirmBtn.addEventListener('click', function () {
            bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal')).hide();
            submitBulk('delete', null);
        });
    }
})();

    // Size the columns then switch to fixed layout. Subject is the flex column
    // (it absorbs the leftover width); the Type, Group, then Location columns
    // shed width in that order so Subject keeps a comfortable share and the
    // table fits without horizontal scroll. Re-run after each ajax swap.
    window.ldMeasureTicketTable = function () {
        var tbl = document.getElementById("ticketTable");
        if (!tbl) return;
        var thead = tbl.tHead;
        if (!thead || !thead.rows.length) return;
        var ths = Array.prototype.slice.call(thead.rows[0].cells);
        var subjectIdx = ths.findIndex(function (th) { return th.classList.contains("subject-col"); });
        var container = tbl.parentElement ? tbl.parentElement.clientWidth : tbl.offsetWidth;

        // Measure each column's natural content width: let the table shrink-wrap
        // so columns aren't stretched to fill 100%.
        tbl.style.tableLayout = "auto";
        tbl.style.width = "auto";
        ths.forEach(function (th) {
            if (!th.classList.contains("subject-col") && !th.hasAttribute("data-ld-resized")) th.style.width = "";
        });
        var natural = ths.map(function (th) { return th.offsetWidth; });
        tbl.style.width = "100%";

        // Guarantee Subject a comfortable share, then shed any overflow from the
        // squeeze columns (Type → Group → Location) down to a usable floor.
        var fixedSum = 0;
        ths.forEach(function (th, i) { if (i !== subjectIdx) fixedSum += natural[i]; });
        var subjectNatural = subjectIdx >= 0 ? natural[subjectIdx] : 0;
        var subjectTarget  = Math.min(subjectNatural, Math.max(260, container * 0.3));
        var deficit = (fixedSum + subjectTarget) - container;
        var MIN = 110; // floor: badge/text + inline-edit chevron stay readable
        if (deficit > 0) {
            ["type-col", "group-col", "location-col"].forEach(function (cls) {
                if (deficit <= 0) return;
                var idx = ths.findIndex(function (th) { return th.classList.contains(cls); });
                if (idx < 0 || idx === subjectIdx) return;
                var room = natural[idx] - MIN;
                if (room <= 0) return;
                var take = Math.min(room, deficit);
                natural[idx] -= take;
                deficit -= take;
            });
        }

        // Pin every column except Subject; Subject (flex) takes the remainder.
        ths.forEach(function (th, i) {
            if (i === subjectIdx) { th.style.width = ""; return; }
            if (th.hasAttribute("data-ld-resized")) return;
            th.style.width = natural[i] + "px";
        });
        tbl.style.tableLayout = "fixed";
        tbl.style.visibility = "";
    };
    window.ldMeasureTicketTable();

    // Quick-change: type, group, agent dropdowns
    (function () {
        var quickTypes = <?= json_encode(array_values(array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name'], 'color' => $t['color'] ?: '#6c757d', 'group_id' => $t['group_id'] ? (int)$t['group_id'] : null], $types))) ?>;
        var quickGroups = <?= json_encode(array_values(array_map(fn($g) => ['id' => (int)$g['id'], 'name' => $g['name']], $groups))) ?>;
        var quickGroupAgents = <?= json_encode(array_map(fn($agents) => array_values($agents), $groupAgents)) ?>;
        var quickAllAgents = <?= json_encode(array_values(array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['name']], $allAgentsForAssign))) ?>;
        var quickStatuses = <?= json_encode(array_values(array_map(fn($s) => ['slug' => $s['slug'], 'label' => $s['label'], 'color' => $s['color'] ?: '#6c757d', 'text_color' => ticketStatusTextColor($s['color'] ?: '#6c757d')], ticketActiveStatuses()))) ?>;
        var quickPriorities = <?= json_encode(array_values(array_map(fn($pr) => ['id' => (int)$pr['id'], 'name' => $pr['name'], 'color' => $pr['color'] ?: '#6c757d'], $priorities))) ?>;
        var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var activeMenu = null;
        var activeBtn  = null;
        var esc = function (s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

        function closeMenu() {
            if (activeMenu) { activeMenu.remove(); activeMenu = null; activeBtn = null; }
        }

        function resizeColumns() {
            // Re-fit with the shared priority-shrink logic; it leaves manually
            // resized columns (data-ld-resized) alone and re-flexes Subject.
            if (window.ldMeasureTicketTable) window.ldMeasureTicketTable();
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
            } else if (kind === 'status') {
                quickStatuses.forEach(function (st) {
                    html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="status" data-val="' + esc(st.slug) + '" data-ticket-id="' + esc(ticketId) + '"><span class="badge" style="background-color:' + esc(st.color) + ';color:' + esc(st.text_color) + ';">' + esc(st.label) + '</span></a></li>';
                });
            } else if (kind === 'priority') {
                html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="priority" data-val="" data-ticket-id="' + esc(ticketId) + '"><span class="text-muted">None</span></a></li>';
                if (quickPriorities.length) {
                    html += '<li><hr class="dropdown-divider"></li>';
                    quickPriorities.forEach(function (pr) {
                        html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="priority" data-val="' + esc(pr.id) + '" data-ticket-id="' + esc(ticketId) + '"><span class="badge" style="background:' + esc(pr.color) + ';">' + esc(pr.name) + '</span></a></li>';
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

        // Bound per element (cell-level stopPropagation keeps these clicks from
        // ever reaching document, so delegation can't work). Exposed so the
        // ajax list swap can rebind the fresh elements.
        window.ldBindQuickEdit = function () {
        document.querySelectorAll('.quick-assign-btn').forEach(function (b) { bindBtn(b, 'agent'); });
        document.querySelectorAll('.quick-type-btn').forEach(function (b) { bindBtn(b, 'type'); });
        document.querySelectorAll('.quick-group-btn').forEach(function (b) { bindBtn(b, 'group'); });
        document.querySelectorAll('.quick-status-btn').forEach(function (b) { bindBtn(b, 'status'); });
        document.querySelectorAll('.quick-priority-btn').forEach(function (b) { bindBtn(b, 'priority'); });

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
        document.querySelectorAll('.quick-type-wrap').forEach(function (wrap) {
            wrap.addEventListener('click', function (e) {
                if (e.target.closest('.quick-type-btn')) return;
                var btn = wrap.querySelector('.quick-type-btn');
                if (btn) { e.stopPropagation(); if (activeBtn === btn) { closeMenu(); } else { openMenu(btn, 'type'); } }
            });
        });
        document.querySelectorAll('.quick-status-wrap').forEach(function (wrap) {
            wrap.addEventListener('click', function (e) {
                if (e.target.closest('.quick-status-btn')) return;
                var btn = wrap.querySelector('.quick-status-btn');
                if (btn) { e.stopPropagation(); if (activeBtn === btn) { closeMenu(); } else { openMenu(btn, 'status'); } }
            });
        });
        document.querySelectorAll('.quick-priority-wrap').forEach(function (wrap) {
            wrap.addEventListener('click', function (e) {
                if (e.target.closest('.quick-priority-btn')) return;
                var btn = wrap.querySelector('.quick-priority-btn');
                if (btn) { e.stopPropagation(); if (activeBtn === btn) { closeMenu(); } else { openMenu(btn, 'priority'); } }
            });
        });
        };
        window.ldBindQuickEdit();

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
                            // Server cleared a now-mismatched type (type maps 1:1 to a default group).
                            if (data.type_cleared) {
                                var typeBadge = document.querySelector('.quick-type-wrap[data-ticket-id="' + ticketId + '"] .quick-type-badge');
                                if (typeBadge) { typeBadge.innerHTML = '<span class="text-muted small">Not Set</span>'; }
                            }
                            resizeColumns();
                        }
                    }).catch(function () {});
                } else if (kind === 'status') {
                    var statusWrap = document.querySelector('.quick-status-wrap[data-ticket-id="' + ticketId + '"]');
                    if (!statusWrap) return;
                    var statusBadge = statusWrap.querySelector('.quick-status-badge');
                    fetch('/api/tickets/' + ticketId + '/set-status', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                        body: JSON.stringify({status: val, expected_status: statusWrap.dataset.currentStatus || ''})
                    }).then(function (r) {
                        return r.json().then(function (data) { return {ok: r.ok, code: r.status, data: data}; });
                    }).then(function (res) {
                        if (res.ok && res.data.status_html) {
                            statusBadge.innerHTML = res.data.status_html;
                            statusWrap.dataset.currentStatus = val;
                            resizeColumns();
                        } else if (res.code === 409 && res.data.status_html) {
                            // Another agent changed it first: show their value, don't apply ours.
                            statusBadge.innerHTML = res.data.status_html;
                            statusWrap.dataset.currentStatus = res.data.current_status || '';
                            resizeColumns();
                            if (res.data.message) { alert(res.data.message); }
                        }
                    }).catch(function () {});
                } else if (kind === 'priority') {
                    var priorityWrap = document.querySelector('.quick-priority-wrap[data-ticket-id="' + ticketId + '"]');
                    if (!priorityWrap) return;
                    var priorityBadge = priorityWrap.querySelector('.quick-priority-badge');
                    fetch('/api/tickets/' + ticketId + '/set-priority', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                        body: JSON.stringify({priority_id: val === '' ? null : parseInt(val, 10)})
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data.success) {
                            if (data.priority_name) {
                                priorityBadge.innerHTML = '<span class="badge" style="background:' + esc(data.priority_color || '#6c757d') + ';">' + esc(data.priority_name) + '</span>';
                            } else {
                                priorityBadge.innerHTML = '<span class="text-muted">—</span>';
                            }
                            resizeColumns();
                        }
                    }).catch(function () {});
                }
                return;
            }
            if (!e.target.closest('.quick-assign-wrap') && !e.target.closest('.quick-type-wrap') && !e.target.closest('.quick-group-wrap') && !e.target.closest('.quick-status-wrap') && !e.target.closest('.quick-priority-wrap') && !(activeMenu && activeMenu.contains(e.target))) {
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

<?php
$pagerParams = array_filter($filters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
if ($sort !== 'created_at' || $dir !== 'desc') {
    $pagerParams['sort'] = $sort;
    $pagerParams['dir']  = $dir;
}
if ($perPage !== 25) $pagerParams['per_page'] = $perPage;
$pagerBase = '/agent/tickets';
?>
<div id="ticketPager">
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
                    aria-label="Tickets per page">
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
document.getElementById('deleteFilterModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteFilterName').textContent = btn.dataset.name;
    document.getElementById('deleteFilterForm').action = btn.dataset.url;
});
</script>
<?php $ticketListScope = 'agent'; require ROOT_DIR . '/templates/partials/ticket-list-ajax.php'; ?>
