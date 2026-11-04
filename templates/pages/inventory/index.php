<?php
$layout       = 'app';
$pageTitle    = label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory')],
];
$hasFilters       = !empty($filterParams);
$panelFilterCount = count(array_diff_key($filterParams, ['q' => null, 'deleted' => null]));
$th = static fn(string $col, string $text): string =>
    '<a href="' . sortUrl($col, $sort, $dir, $filterParams, '/inventory') . '" class="text-decoration-none text-dark">' . $text . ' ' . sortIcon($col, $sort, $dir) . '</a>';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0 min-w-0">
        <i class="bi bi-pc-display me-2 text-muted"></i><?= e(label('nav.inventory', 'Inventory')) ?>
        <?php if ($deleted): ?><span class="badge bg-secondary fs-6 align-middle">Deleted</span><?php endif; ?>
    </h2>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <span class="badge bg-secondary fs-6"><?= number_format($total) ?><?= $hasFilters ? ' filtered' : ' total' ?></span>
        <form method="GET" action="/inventory" class="position-relative">
            <?php foreach ($filterParams as $k => $v): if ($k === 'q') continue; ?>
                <?php foreach ((array) $v as $vv): ?>
                <input type="hidden" name="<?= e($k) ?><?= is_array($v) ? '[]' : '' ?>" value="<?= e((string) $vv) ?>">
                <?php endforeach; ?>
            <?php endforeach; ?>
            <i class="bi bi-search position-absolute text-muted" style="left:.6rem;top:50%;transform:translateY(-50%);font-size:.8rem;pointer-events:none;"></i>
            <input type="search" class="form-control form-control-sm" name="q" value="<?= e($qFilter) ?>"
                   placeholder="Tag, name, serial, model…" aria-label="Search assets" style="width:230px;padding-left:1.9rem;">
        </form>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="filterPanelBtn" onclick="filterPanelToggle()">
            <i class="bi bi-funnel me-1"></i>Filters
            <?php if ($panelFilterCount): ?><span class="badge bg-primary rounded-pill ms-1"><?= $panelFilterCount ?></span><?php endif; ?>
        </button>
        <a href="<?= e('/inventory?' . http_build_query(array_merge($filterParams, ['export' => 'csv']))) ?>" class="btn btn-sm btn-outline-secondary" title="Download the filtered list as CSV">
            <i class="bi bi-download me-1"></i>CSV
        </a>
        <?php if (Assets::canManage()): ?>
        <a href="/inventory/create" class="btn btn-sm text-white" style="background:var(--ld-primary);"><i class="bi bi-plus-lg me-1"></i>Add asset</a>
        <?php endif; ?>
    </div>
</div>

<div class="filter-panel-backdrop" id="filterPanelBackdrop" onclick="filterPanelClose()"></div>
<div class="filter-panel" id="filterPanel">
    <div class="filter-panel-header">
        <span class="fw-semibold"><i class="bi bi-funnel me-1"></i>Filters</span>
        <button type="button" class="btn-close" onclick="filterPanelClose()" aria-label="Close"></button>
    </div>
    <div class="filter-panel-body">
        <form method="GET" action="/inventory">
            <input type="hidden" name="q" value="<?= e($qFilter) ?>">
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Category</label>
                <div class="filter-checklist">
                    <?php foreach ($categories as $c): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="category[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $catFilter, true) ? 'checked' : '' ?>>
                        <span><?= e($c['name']) ?> <span class="text-muted">(<?= (int) $c['n'] ?>)</span></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <div class="filter-checklist">
                    <?php foreach ($statuses as $s): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="status[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $statFilter, true) ? 'checked' : '' ?>>
                        <span><span class="d-inline-block rounded-circle me-1" style="width:.6rem;height:.6rem;background:<?= e($s['color']) ?>;"></span><?= e($s['name']) ?> <span class="text-muted">(<?= (int) $s['n'] ?>)</span></span>
                    </label>
                    <?php endforeach; ?>
                    <label class="filter-check-item" title="Ready-to-deploy status and checked out to a person, <?= strtolower(label('location.singular')) ?> or asset">
                        <input type="checkbox" name="status[]" value="deployed" <?= $deployed ? 'checked' : '' ?>>
                        <span><span class="badge bg-primary-subtle text-primary border">Deployed</span> <span class="text-muted">(<?= (int) $deployedCount ?>)</span></span>
                    </label>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1"><?= label('location.singular') ?></label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="location[]" value="none" <?= in_array('none', $locFilter, true) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic">No <?= label('location.singular') ?></span>
                    </label>
                    <?php foreach ($locations as $loc): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="location[]" value="<?= (int) $loc['id'] ?>" <?= in_array((string) $loc['id'], $locFilter, true) ? 'checked' : '' ?>>
                        <span><?= e($loc['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1" for="invAssigned">Assigned</label>
                <select class="form-select form-select-sm" name="assigned" id="invAssigned">
                    <option value="">Any</option>
                    <option value="user" <?= $assigned === 'user' ? 'selected' : '' ?>>To a person</option>
                    <option value="location" <?= $assigned === 'location' ? 'selected' : '' ?>>To a <?= label('location.singular') ?></option>
                    <option value="asset" <?= $assigned === 'asset' ? 'selected' : '' ?>>To another asset</option>
                    <option value="unassigned" <?= $assigned === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                    <option value="import" <?= $assigned === 'import' ? 'selected' : '' ?>>Snipe-IT assignee with no account</option>
                </select>
            </div>
            <?php if ($deletedCount > 0): ?>
            <div class="mb-3">
                <label class="filter-check-item">
                    <input type="checkbox" name="deleted" value="1" <?= $deleted ? 'checked' : '' ?>>
                    <span>Show deleted assets only <span class="text-muted">(<?= $deletedCount ?>)</span></span>
                </label>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm text-white flex-grow-1" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="/inventory?reset=1" class="btn btn-sm btn-outline-secondary" title="Clear all filters">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-resize">
            <thead class="table-light">
                <tr>
                    <th><?= $th('tag', 'Tag') ?></th>
                    <th><?= $th('name', 'Name') ?></th>
                    <th><?= $th('model', 'Model') ?></th>
                    <th><?= $th('category', 'Category') ?></th>
                    <th><?= $th('serial', 'Serial') ?></th>
                    <th><?= $th('status', 'Status') ?></th>
                    <th><?= $th('assigned', 'Assigned to') ?></th>
                    <th><?= $th('location', label('location.singular')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assets)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No assets found.</td></tr>
                <?php else: ?>
                    <?php foreach ($assets as $a): ?>
                    <tr style="cursor:pointer;" onclick="window.location='/inventory/<?= (int) $a['id'] ?>'">
                        <td class="fw-semibold text-nowrap">
                            <a href="/inventory/<?= (int) $a['id'] ?>" class="text-decoration-none text-dark font-monospace"><?= e($a['asset_tag']) ?></a>
                        </td>
                        <td><?= e($a['name'] ?: '—') ?></td>
                        <td>
                            <?= e($a['model_name']) ?>
                            <?php if ($a['manufacturer_name']): ?><div class="text-muted small"><?= e($a['manufacturer_name']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e($a['category_name']) ?></td>
                        <td class="text-muted small font-monospace"><?= e($a['serial'] ?: '—') ?></td>
                        <td>
                            <span class="badge" style="background:<?= e($a['status_color']) ?>;color:<?= ticketStatusTextColor($a['status_color']) ?>;"><?= e($a['status_name']) ?></span>
                            <?php if ($a['status_kind'] === 'deployable' && $a['assigned_type'] !== null): ?>
                            <span class="badge bg-primary-subtle text-primary border">Deployed</span>
                            <?php endif; ?>
                        </td>
                        <td><?= Assets::assigneeHtml($a) ?></td>
                        <td class="text-muted"><?= e($a['location_name'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted small">Page <?= $page ?> of <?= $totalPages ?></span>
        <nav aria-label="Inventory pages">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e('/inventory?' . http_build_query(array_merge($filterParams, ['sort' => $sort, 'dir' => $dir, 'page' => $page - 1]))) ?>">&laquo;</a>
                </li>
                <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e('/inventory?' . http_build_query(array_merge($filterParams, ['sort' => $sort, 'dir' => $dir, 'page' => $p]))) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e('/inventory?' . http_build_query(array_merge($filterParams, ['sort' => $sort, 'dir' => $dir, 'page' => $page + 1]))) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    function open()  { document.getElementById('filterPanel').classList.add('open');    document.getElementById('filterPanelBackdrop').classList.add('open'); }
    function close() { document.getElementById('filterPanel').classList.remove('open'); document.getElementById('filterPanelBackdrop').classList.remove('open'); }
    window.filterPanelToggle = function () { document.getElementById('filterPanel').classList.contains('open') ? close() : open(); };
    window.filterPanelClose = close;
})();
</script>
