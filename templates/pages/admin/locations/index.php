<?php
$layout       = 'app';
$pageTitle    = 'Locations – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Locations'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Locations</h5>
    <a href="/admin/locations/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-geo-alt me-1"></i>Add Location
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Description</th>
                    <th>Created</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($locations)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No locations found.</td></tr>
                <?php else: ?>
                    <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-geo-alt text-muted me-1"></i><?= e($loc['name']) ?>
                        </td>
                        <td class="text-muted"><?= e($loc['address'] ?: '—') ?></td>
                        <td class="text-muted small" style="max-width:300px;"><?= e($loc['description'] ? mb_strimwidth($loc['description'], 0, 80, '...') : '—') ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($loc['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/locations/<?= $loc['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/locations/<?= $loc['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('Delete this location?')">
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
