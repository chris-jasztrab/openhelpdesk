<?php
// Partial: the users table. Rendered inline by index.php and again on its own
// for the toolbar search box's ?ajax=1 fetches, so the search-as-you-type
// results come out of the same query (and honour the same role/location
// filters and sort) as a full page load.
// Required vars: $users, $sort, $dir, $filterParams
?>
<div class="card border-0 shadow-sm" data-user-count="<?= count($users) ?>">
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
                            <?php if (!empty($u['is_external'])): ?>
                            <span class="badge bg-secondary-subtle text-secondary border" title="Auto-created when a ticket was forwarded to this address. Not a portal user.">External</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="text-muted"><?= e($u['email']) ?></span></td>
                        <td>
                            <?php
                            $badgeColors = ['admin' => 'danger', 'agent' => 'primary', 'power_user' => 'info', 'user' => 'secondary'];
                            $bc = $badgeColors[$u['role']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $bc ?>"><?= e(roleLabel($u['role'])) ?></span>
                            <?php if (!roleIsAdmin($u['role']) && roleCan($u['role'], 'tickets.view_all')): ?>
                            <span class="badge bg-warning text-dark" title="This permission level can see all tickets across every group (confidential excluded).">
                                <i class="bi bi-eye-fill me-1"></i>Sees all tickets
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($u['work_phone'] ?? '—') ?></td>
                        <td><?= e($u['location_name'] ?? '—') ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/users/<?= $u['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($u['id'] !== Auth::id() && roleAssignableBy(Auth::role(), $u['role'])): ?>
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
