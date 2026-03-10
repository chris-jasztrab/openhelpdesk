<?php
$layout       = 'app';
$pageTitle    = 'Ticket Types – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Ticket Types'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Ticket Types</h5>
    <a href="/admin/types/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-plus-lg me-1"></i>Add Type
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Sort Order</th>
                    <th>Created</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($types)): ?>
                <tr><td colspan="4" class="text-center py-4 text-muted">No ticket types found.</td></tr>
                <?php else: ?>
                    <?php foreach ($types as $t): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($t['name']) ?></td>
                        <td class="text-muted"><?= (int) $t['sort_order'] ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/types/<?= $t['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/types/<?= $t['id'] ?>/delete" class="d-inline">
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
