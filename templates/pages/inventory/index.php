<?php
$layout       = 'app';
$pageTitle    = label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory')],
];
$hasFilters       = !empty($filterParams);
$panelFilterCount = count(array_diff_key($filterParams, ['q' => null, 'deleted' => null, 'warranty_days' => null]));
$canCheckout = !empty($users); // only handed over for assets.manage on the live (not deleted) list
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
        <a href="/inventory/reports" class="btn btn-sm btn-outline-secondary" title="Warranty, audit, age, assignment and activity reports"><i class="bi bi-bar-chart me-1"></i>Reports</a>
        <?php if (Assets::canManage()): ?>
        <a href="/inventory/audit" class="btn btn-sm btn-outline-secondary" title="Scan tags to record a physical inventory"><i class="bi bi-upc-scan me-1"></i>Audit mode</a>
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
                    <label class="filter-check-item" title="Checked out to a person, <?= strtolower(label('location.singular')) ?> or asset (shown in place of Ready to Deploy)">
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
                <label class="form-label small fw-semibold mb-1">Manufacturer</label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="manufacturer[]" value="none" <?= in_array('none', $mfFilter, true) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic">No manufacturer</span>
                    </label>
                    <?php foreach ($manufacturers as $mf): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="manufacturer[]" value="<?= (int) $mf['id'] ?>" <?= in_array((string) $mf['id'], $mfFilter, true) ? 'checked' : '' ?>>
                        <span><?= e($mf['name']) ?> <span class="text-muted">(<?= (int) $mf['n'] ?>)</span></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($modelFilter): ?>
            <div class="mb-3">
                <label class="filter-check-item">
                    <input type="checkbox" name="model" value="<?= (int) $modelFilter['id'] ?>" checked>
                    <span>Model: <?= e($modelFilter['name']) ?></span>
                </label>
            </div>
            <?php endif; ?>
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
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1" for="invAuditBefore">Not audited since</label>
                <input type="date" class="form-control form-control-sm" name="audit_before" id="invAuditBefore" value="<?= e($auditBefore) ?>">
                <div class="form-text">Includes assets never audited.</div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1" for="invAuditAfter">Audited since</label>
                <input type="date" class="form-control form-control-sm" name="audit_after" id="invAuditAfter" value="<?= e($auditAfter) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1" for="invAudit">Audit</label>
                <select class="form-select form-select-sm" name="audit" id="invAudit">
                    <option value="">Any</option>
                    <option value="never" <?= $audit === 'never' ? 'selected' : '' ?>>Never audited</option>
                    <option value="overdue" <?= $audit === 'overdue' ? 'selected' : '' ?>>Next audit date has passed</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1" for="invWarranty">Warranty</label>
                <select class="form-select form-select-sm" name="warranty" id="invWarranty">
                    <option value="">Any</option>
                    <?php foreach (['expired' => 'Expired', 'soon' => 'Ends within…', 'active' => 'In warranty', 'unknown' => 'Unknown', 'error' => 'Lookup failed'] as $k => $t): ?>
                    <option value="<?= $k ?>" <?= $warranty === $k ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="input-group input-group-sm mt-1">
                    <input type="number" class="form-control" name="warranty_days" min="1" max="3650" value="<?= (int) $wDays ?>" aria-label="Ends within this many days">
                    <span class="input-group-text">days (for “Ends within”)</span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1" for="invAge">Age</label>
                <select class="form-select form-select-sm" name="age" id="invAge">
                    <option value="">Any</option>
                    <?php foreach (ASSET_AGE_BUCKETS as $k => [$t]): ?>
                    <option value="<?= $k ?>" <?= $age === $k ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">From the purchase date, else the manufacturer's warranty start.</div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1" for="invMissing">Missing</label>
                <select class="form-select form-select-sm" name="missing" id="invMissing">
                    <option value="">—</option>
                    <option value="serial" <?= $missing === 'serial' ? 'selected' : '' ?>>Serial number</option>
                    <option value="purchase_date" <?= $missing === 'purchase_date' ? 'selected' : '' ?>>Purchase date</option>
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
                    <?php if ($showCols['warranty']): ?><th><?= $th('warranty_end', 'Warranty ends') ?></th><?php endif; ?>
                    <?php if ($showCols['audit']): ?><th><?= $th('last_audit', 'Last audit') ?></th><?php endif; ?>
                    <?php if ($showCols['acquired']): ?><th><?= $th('acquired', 'Acquired') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assets)): ?>
                <tr><td colspan="<?= 8 + count(array_filter($showCols)) ?>" class="text-center py-4 text-muted">No assets found.</td></tr>
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
                            <?= Assets::statusBadgeHtml($a) ?>
                        </td>
                        <td>
                            <?= Assets::assigneeHtml($a) ?>
                            <?php if ($canCheckout && $a['assigned_type'] === null): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 ms-1" data-bs-toggle="modal" data-bs-target="#coModal" data-id="<?= (int) $a['id'] ?>" data-tag="<?= e($a['asset_tag']) ?>" onclick="event.stopPropagation()" title="Check out <?= e($a['asset_tag']) ?>" aria-label="Check out <?= e($a['asset_tag']) ?>"><i class="bi bi-box-arrow-right"></i></button>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e($a['location_name'] ?: '—') ?></td>
                        <?php if ($showCols['warranty']): ?>
                        <td class="text-nowrap small">
                            <?php if ($a['warranty_end']): ?>
                            <span class="<?= $a['warranty_end'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(date('M j, Y', strtotime($a['warranty_end']))) ?></span>
                            <?php elseif ($a['warranty_error']): ?>
                            <span class="text-warning-emphasis" title="<?= e($a['warranty_error']) ?>"><i class="bi bi-exclamation-triangle me-1"></i>Lookup failed</span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php if ($showCols['audit']): ?>
                        <td class="text-nowrap small text-muted"><?= $a['last_audit_at'] ? e(date('M j, Y', strtotime($a['last_audit_at']))) : 'Never' ?></td>
                        <?php endif; ?>
                        <?php if ($showCols['acquired']): ?>
                        <?php $acq = $a['purchase_date'] ?: $a['warranty_start']; ?>
                        <td class="text-nowrap small text-muted" title="<?= $a['purchase_date'] ? 'Purchase date' : 'Warranty start' ?>"><?= $acq ? e(date('M j, Y', strtotime($acq))) : '—' ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > 0): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="GET" action="/inventory" class="d-flex align-items-center gap-2">
            <?php foreach ($filterParams + ['sort' => $sort, 'dir' => $dir] as $k => $v): ?>
                <?php foreach ((array) $v as $vv): ?>
                <input type="hidden" name="<?= e($k) ?><?= is_array($v) ? '[]' : '' ?>" value="<?= e((string) $vv) ?>">
                <?php endforeach; ?>
            <?php endforeach; ?>
            <span class="text-muted small">Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= number_format($total) ?></span>
            <select name="per_page" class="form-select form-select-sm w-auto" aria-label="Assets per page" onchange="this.form.submit()">
                <?php foreach (inventoryPerPageOptions() as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?> per page</option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($totalPages > 1): ?>
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
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($canCheckout): ?>
<div class="modal fade" id="coModal" tabindex="-1" aria-labelledby="coModalTitle">
    <div class="modal-dialog">
        <form method="POST" id="coModalForm" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '/inventory') ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="coModalTitle">Check out <span class="font-monospace" id="coModalTag"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="coModalType">Check out to</label>
                    <select class="form-select" name="assigned_type" id="coModalType" onchange="document.querySelectorAll('#coModal [data-co]').forEach(function(el){el.classList.toggle('d-none', el.getAttribute('data-co')!==this.value);}.bind(this));">
                        <option value="user">A person</option>
                        <option value="location">A <?= label('location.singular') ?></option>
                    </select>
                </div>
                <div class="mb-3" data-co="user">
                    <label class="form-label fw-semibold" for="coModalUser">Person</label>
                    <select class="form-select" name="user_id" id="coModalUser">
                        <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?> — <?= e($u['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3 d-none" data-co="location">
                    <label class="form-label fw-semibold" for="coModalLoc"><?= label('location.singular') ?></label>
                    <select class="form-select" name="location_id" id="coModalLoc">
                        <?php foreach ($locations as $l): ?>
                        <option value="<?= (int) $l['id'] ?>"><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold" for="coModalNote">Note <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" class="form-control" name="note" id="coModalNote">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('coModal').addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget;
    document.getElementById('coModalForm').action = '/inventory/' + b.getAttribute('data-id') + '/checkout';
    document.getElementById('coModalTag').textContent = b.getAttribute('data-tag');
});
</script>
<?php endif; ?>

<script>
(function () {
    function open()  { document.getElementById('filterPanel').classList.add('open');    document.getElementById('filterPanelBackdrop').classList.add('open'); }
    function close() { document.getElementById('filterPanel').classList.remove('open'); document.getElementById('filterPanelBackdrop').classList.remove('open'); }
    window.filterPanelToggle = function () { document.getElementById('filterPanel').classList.contains('open') ? close() : open(); };
    window.filterPanelClose = close;
})();
</script>
