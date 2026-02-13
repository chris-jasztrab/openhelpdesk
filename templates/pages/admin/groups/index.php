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
    <a href="/admin/groups/create" class="btn text-white" style="background:#4f46e5;">
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
                                <form method="POST" action="/admin/groups/<?= $g['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('Delete this group?')">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
