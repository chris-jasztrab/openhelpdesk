<?php
$layout       = 'app';
$pageTitle    = 'Priorities – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Priorities'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Ticket Priorities</h5>
    <a href="/admin/priorities/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-flag me-1"></i>Add Priority
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">Color</th>
                    <th>Name</th>
                    <th>Sort Order</th>
                    <th>Created</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($priorities)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No priorities found.</td></tr>
                <?php else: ?>
                    <?php foreach ($priorities as $p): ?>
                    <tr>
                        <td>
                            <span class="badge rounded-pill" style="background:<?= e($p['color']) ?>;min-width:28px;">&nbsp;</span>
                        </td>
                        <td class="fw-semibold">
                            <span class="badge" style="background:<?= e($p['color']) ?>;"><?= e($p['name']) ?></span>
                        </td>
                        <td class="text-muted"><?= (int) $p['sort_order'] ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/priorities/<?= $p['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deletePriorityModal"
                                        data-id="<?= $p['id'] ?>"
                                        data-name="<?= e($p['name']) ?>">
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

<!-- Delete Priority Modal -->
<div class="modal fade" id="deletePriorityModal" tabindex="-1" aria-labelledby="deletePriorityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deletePriorityModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Priority
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete priority <strong id="deletePriorityName"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deletePriorityForm" action="">
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
document.getElementById('deletePriorityModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deletePriorityName').textContent = btn.dataset.name;
    document.getElementById('deletePriorityForm').action = '/admin/priorities/' + btn.dataset.id + '/delete';
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
