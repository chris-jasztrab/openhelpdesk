<?php
$layout       = 'app';
$pageTitle    = 'Permission Levels – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Permission Levels'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="fw-bold mb-0">Permission Levels</h5>
    <a href="/admin/roles/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-shield-plus me-1"></i>Add Permission Level
    </a>
</div>
<p class="text-muted small mb-4" style="max-width:60ch;">
    Permission levels decide what each user can do. Create your own staff levels and
    grant exactly the capabilities they need. Admins always have full access.
</p>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th style="width:90px">Users</th>
                    <th style="width:120px">Capabilities</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($roles)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No permission levels found.</td></tr>
                <?php else: ?>
                    <?php foreach ($roles as $r): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-shield-lock text-muted me-1"></i><?= e($r['name']) ?>
                            <?php if (!empty($r['is_admin'])): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger ms-1">Full access</span>
                            <?php elseif (empty($r['is_staff'])): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">Portal</span>
                            <?php else: ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Staff</span>
                            <?php endif; ?>
                            <?php if (!empty($r['is_system'])): ?>
                                <span class="badge bg-light text-muted border ms-1">Built-in</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:320px;"><?= e($r['description'] ? mb_strimwidth($r['description'], 0, 90, '…') : '—') ?></td>
                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= (int) $r['user_count'] ?></span></td>
                        <td>
                            <?php if (!empty($r['is_admin'])): ?>
                                <span class="text-muted small">Everything</span>
                            <?php else: ?>
                                <span class="badge bg-info bg-opacity-10 text-info"><?= (int) $r['perm_count'] ?> granted</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/roles/<?= (int) $r['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if (empty($r['is_system'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteRoleModal"
                                        data-id="<?= (int) $r['id'] ?>" data-name="<?= e($r['name']) ?>"
                                        data-users="<?= (int) $r['user_count'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Built-in levels can't be deleted">
                                    <i class="bi bi-lock"></i>
                                </button>
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

<!-- Delete Role Modal -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteRoleModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Permission Level
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete <strong id="deleteRoleName"></strong>? This cannot be undone.</p>
                <p class="text-danger small mb-0 mt-2 d-none" id="deleteRoleWarn">
                    This level is still assigned to users. Reassign them first, or the delete will be blocked.
                </p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteRoleForm" action="">
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
document.getElementById('deleteRoleModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteRoleName').textContent = btn.dataset.name;
    document.getElementById('deleteRoleForm').action = '/admin/roles/' + btn.dataset.id + '/delete';
    document.getElementById('deleteRoleWarn').classList.toggle('d-none', parseInt(btn.dataset.users || '0', 10) === 0);
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
