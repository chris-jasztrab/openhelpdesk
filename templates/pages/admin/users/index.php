<?php
$layout      = 'app';
$pageTitle   = 'Users';
$sidebarItems = adminSidebar('users');
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Users'],
];
$filterParams = [];
if ($roleFilter !== '') $filterParams['role'] = $roleFilter;
if ($locFilter !== '')  $filterParams['location'] = $locFilter;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Users</h2>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-secondary fs-6"><?= count($users) ?><?= (!empty($filterParams)) ? ' filtered' : ' total' ?></span>
        <a href="/admin/users/create" class="btn text-white" style="background:#4f46e5;">
            <i class="bi bi-person-plus me-1"></i>Add User
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-auto">
                <label class="form-label small text-muted mb-1">Role</label>
                <div class="d-flex gap-1">
                    <a href="<?= e('/admin/users' . ($locFilter !== '' ? '?location=' . urlencode($locFilter) : '')) ?>"
                       class="btn btn-sm <?= empty($roleFilter) ? 'text-white' : 'btn-outline-secondary' ?>" <?= empty($roleFilter) ? 'style="background:#4f46e5;"' : '' ?>>All</a>
                    <?php
                    $roleButtons = ['admin' => ['Admins', 'danger'], 'agent' => ['Agents', 'primary'], 'user' => ['Users', 'secondary']];
                    foreach ($roleButtons as $rKey => [$rLabel, $rColor]):
                        $rParams = $locFilter !== '' ? ['role' => $rKey, 'location' => $locFilter] : ['role' => $rKey];
                    ?>
                    <a href="<?= e('/admin/users?' . http_build_query($rParams)) ?>"
                       class="btn btn-sm <?= $roleFilter === $rKey ? "btn-{$rColor}" : "btn-outline-{$rColor}" ?>"><?= $rLabel ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-auto" style="min-width:160px;">
                <label class="form-label small text-muted mb-1">Location</label>
                <select class="form-select form-select-sm" onchange="var p=new URLSearchParams(window.location.search);if(this.value)p.set('location',this.value);else p.delete('location');p.delete('page');window.location='/admin/users?'+p.toString();">
                    <option value="">All Locations</option>
                    <option value="none" <?= $locFilter === 'none' ? 'selected' : '' ?>>No Location</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $locFilter == $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($filterParams)): ?>
            <div class="col-md-auto">
                <a href="/admin/users" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </a>
            </div>
            <?php endif; ?>
        </div>
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
                    <th><a href="<?= sortUrl('location', $sort, $dir, $filterParams, '/admin/users') ?>" class="text-decoration-none text-dark">Location <?= sortIcon('location', $sort, $dir) ?></a></th>
                    <th><a href="<?= sortUrl('created_at', $sort, $dir, $filterParams, '/admin/users') ?>" class="text-decoration-none text-dark">Created <?= sortIcon('created_at', $sort, $dir) ?></a></th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <?php if ($u['avatar']): ?>
                                <img src="/uploads/avatars/<?= e($u['avatar']) ?>" class="rounded-circle" width="36" height="36" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;font-size:.8rem;">
                                    <?= strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
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
                                <form method="POST" action="/admin/users/<?= $u['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('Delete this user?')">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
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
