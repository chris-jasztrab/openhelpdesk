<?php
$layout       = 'app';
$pageTitle    = 'Groups – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Groups'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Groups</h5>
    <a href="/admin/groups/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-people-fill me-1"></i>Add Group
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Members</th>
                    <th>Sort Order</th>
                    <th>Created</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groups)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No groups found.</td></tr>
                <?php else: ?>
                    <?php foreach ($groups as $g): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-people-fill text-muted me-1"></i><?= e($g['name']) ?>
                            <?php if (!empty($g['is_confidential'])): ?>
                            <i class="bi bi-shield-lock text-warning ms-1" title="Confidential — members are alerted when new users are added"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:300px;"><?= e($g['description'] ? mb_strimwidth($g['description'], 0, 80, '...') : '—') ?></td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary"><?= (int) $g['member_count'] ?> member<?= (int) $g['member_count'] !== 1 ? 's' : '' ?></span>
                        </td>
                        <td class="text-muted"><?= (int) $g['sort_order'] ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($g['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/groups/<?= $g['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteGroupModal"
                                        data-id="<?= $g['id'] ?>"
                                        data-name="<?= e($g['name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Group Modal -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteGroupModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Group
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete group <strong id="deleteGroupName"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteGroupForm" action="">
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
document.getElementById('deleteGroupModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteGroupName').textContent = btn.dataset.name;
    document.getElementById('deleteGroupForm').action = '/admin/groups/' + btn.dataset.id + '/delete';
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
