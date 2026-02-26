<?php
$layout      = 'app';
$pageTitle   = 'Users';
$sidebarItems = adminSidebar('users');
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Users'],
];
$filterParams = [];
if ($roleFilter !== '') $filterParams['role']     = $roleFilter;
if ($locFilter !== '')  $filterParams['location'] = $locFilter;
if ($qFilter !== '')    $filterParams['q']        = $qFilter;
$hasFilters = !empty($filterParams);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Users</h2>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-secondary fs-6"><?= count($users) ?><?= $hasFilters ? ' filtered' : ' total' ?></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="filterPanelBtn" onclick="filterPanelToggle()">
            <i class="bi bi-funnel me-1"></i>Filters
            <?php if ($hasFilters): ?><span class="badge bg-primary rounded-pill ms-1"><?= count($filterParams) ?></span><?php endif; ?>
        </button>
        <a href="/admin/users/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-person-plus me-1"></i>Add User
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
        <form method="GET" action="/admin/users">
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <input type="text" class="form-control form-control-sm" name="q"
                       value="<?= e($qFilter) ?>" placeholder="Name or email…">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Role</label>
                <select class="form-select form-select-sm" name="role">
                    <option value="">All Roles</option>
                    <option value="admin"  <?= $roleFilter === 'admin'  ? 'selected' : '' ?>>Admin</option>
                    <option value="agent"  <?= $roleFilter === 'agent'  ? 'selected' : '' ?>>Agent</option>
                    <option value="user"   <?= $roleFilter === 'user'   ? 'selected' : '' ?>>User</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1"><?= label('location.singular') ?></label>
                <select class="form-select form-select-sm" name="location">
                    <option value=""><?= 'All ' . label('location.plural') ?></option>
                    <option value="none" <?= $locFilter === 'none' ? 'selected' : '' ?>>No <?= label('location.singular') ?></option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $locFilter == $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm text-white flex-grow-1" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="/admin/users?reset=1" class="btn btn-sm btn-outline-secondary" title="Clear all filters">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px"></th>
                    <th><a href="<?= sortUrl('name', $sort, $dir, $filterParams, '/admin/users') ?>" class="text-decoration-none text-dark">Name <?= sortIcon('name', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('email', $sort, $dir, $filterParams, '/admin/users') ?>" class="text-decoration-none text-dark">Email <?= sortIcon('email', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('role', $sort, $dir, $filterParams, '/admin/users') ?>" class="text-decoration-none text-dark">Role <?= sortIcon('role', $sort, $dir) ?></a></th>
                    <th>Phone</th>
                    <th><a href="<?= sortUrl('location', $sort, $dir, $filterParams, '/admin/users') ?>" class="text-decoration-none text-dark"><?= label('location.singular') ?> <?= sortIcon('location', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('created_at', $sort, $dir, $filterParams, '/admin/users') ?>" class="text-decoration-none text-dark">Created <?= sortIcon('created_at', $sort, $dir) ?></a></th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr style="cursor:pointer;" onclick="window.location='/admin/users/<?= $u['id'] ?>'">
                        <td>
                            <?php if ($u['avatar']): ?>
                                <img src="/uploads/avatars/<?= e($u['avatar']) ?>" class="rounded-circle" width="36" height="36" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;font-size:.8rem;">
                                    <?= strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold">
                            <a href="/admin/users/<?= $u['id'] ?>" class="text-decoration-none text-dark">
                                <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                            </a>
                        </td>
                        <td><span class="text-muted"><?= e($u['email']) ?></span></td>
                        <td>
                            <?php
                            $badgeColors = ['admin' => 'danger', 'agent' => 'primary', 'user' => 'secondary'];
                            $bc = $badgeColors[$u['role']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $bc ?>"><?= e(ucfirst($u['role'])) ?></span>
                        </td>
                        <td><?= e($u['work_phone'] ?? '—') ?></td>
                        <td><?= e($u['location_name'] ?? '—') ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/users/<?= $u['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($u['id'] !== Auth::id()): ?>
                                <a href="/admin/users/<?= $u['id'] ?>?delete=1"
                                   class="btn btn-sm btn-outline-danger" title="Delete"
                                   onclick="event.stopPropagation()">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    function filterPanelOpen() {
        document.getElementById('filterPanel').classList.add('open');
        document.getElementById('filterPanelBackdrop').classList.add('open');
        sessionStorage.setItem('adminUserFilterPanelOpen', '1');
    }
    function filterPanelClose() {
        document.getElementById('filterPanel').classList.remove('open');
        document.getElementById('filterPanelBackdrop').classList.remove('open');
        sessionStorage.setItem('adminUserFilterPanelOpen', '0');
    }
    window.filterPanelToggle = function () {
        document.getElementById('filterPanel').classList.contains('open') ? filterPanelClose() : filterPanelOpen();
    };
    window.filterPanelClose = filterPanelClose;
    if (sessionStorage.getItem('adminUserFilterPanelOpen') === '1') filterPanelOpen();
})();
</script>
