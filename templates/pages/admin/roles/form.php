<?php
$isEdit   = !empty($editing);
$isAdmin  = $isEdit && !empty($editing['is_admin']);   // admin role: matrix locked
$isSystem = $isEdit && !empty($editing['is_system']);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Permission Level' : 'New Permission Level';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Permission Levels', 'url' => '/admin/roles'],
    ['label' => $isEdit ? 'Edit' : 'New'],
];
$action = $isEdit ? "/admin/roles/{$editing['id']}/edit" : '/admin/roles/create';
$granted = array_flip($grantedKeys ?? []);
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex align-items-center mb-4">
    <a href="/admin/roles" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h5 class="fw-bold mb-0"><?= $isEdit ? 'Edit' : 'New' ?> Permission Level</h5>
    <?php if ($isSystem): ?><span class="badge bg-light text-muted border ms-2">Built-in</span><?php endif; ?>
</div>

<form method="POST" action="<?= e($action) ?>">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Display name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="64" required
                               value="<?= e(old('name', $editing['name'] ?? '')) ?>"
                               placeholder="e.g. Knowledge Base Editor">
                        <?php if ($isEdit): ?>
                        <div class="form-text">Internal key: <code><?= e($editing['slug']) ?></code> (fixed)</div>
                        <?php else: ?>
                        <div class="form-text">A staff-side level. Members sign in to the agent interface.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="What is this level for?"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="alert alert-danger bg-opacity-10 small mb-0">
                        <i class="bi bi-shield-fill-check me-1"></i>
                        The Admin level always has full, unrestricted access. Its capabilities can't be limited.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Permission Level' ?>
                </button>
                <a href="/admin/roles" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-1">Capabilities</h6>
                    <p class="text-muted small mb-3">
                        Tick the capabilities this level grants. Everyone with a staff level can already
                        work tickets and the knowledge base — these are the extra, sensitive abilities.
                    </p>
                    <?php if (empty($permissions)): ?>
                        <p class="text-muted">No permissions are defined.</p>
                    <?php else: ?>
                        <?php foreach ($permissions as $category => $perms): ?>
                        <div class="mb-3">
                            <div class="text-uppercase fw-semibold text-muted mb-2" style="font-size:.7rem;letter-spacing:.06em;">
                                <?= e($category) ?>
                            </div>
                            <div class="row g-2">
                                <?php foreach ($perms as $perm): ?>
                                <?php $checked = $isAdmin || isset($granted[$perm['perm_key']]); ?>
                                <div class="col-md-6">
                                    <label class="d-flex align-items-start gap-2 p-2 rounded border h-100 <?= $checked ? 'border-primary bg-primary bg-opacity-10' : '' ?>"
                                           style="cursor:<?= $isAdmin ? 'default' : 'pointer' ?>;">
                                        <input class="form-check-input mt-1 flex-shrink-0" type="checkbox"
                                               name="perms[]" value="<?= e($perm['perm_key']) ?>"
                                               <?= $checked ? 'checked' : '' ?> <?= $isAdmin ? 'disabled' : '' ?>>
                                        <span>
                                            <span class="fw-semibold d-block small"><?= e($perm['label']) ?></span>
                                            <span class="text-muted" style="font-size:.78rem;"><?= e($perm['description']) ?></span>
                                        </span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const form = document.querySelector('form[action="<?= e($action) ?>"]');
    if (!form) return;
    const box = form.querySelector('input[name="perms[]"][value="tickets.view_all"]');
    if (!box) return;
    const initiallyGranted = <?= isset($granted['tickets.view_all']) ? 'true' : 'false' ?>;
    form.addEventListener('submit', function (e) {
        if (box.disabled) return; // admin roles: perms not editable here
        if (box.checked && !initiallyGranted) {
            if (!confirm('“View all tickets” lets every user with this role see tickets across ALL groups (confidential tickets excluded). Grant it to this role?')) {
                e.preventDefault();
            }
        }
    });
})();
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
