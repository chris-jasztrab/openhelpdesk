<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Skill' : 'Add Skill';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Agent Skills', 'url' => '/admin/skills'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/skills/{$editing['id']}/edit" : '/admin/skills/create';
$roleColors = ['admin' => 'danger', 'agent' => 'primary'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Skill' : 'Add Skill' ?></h2>
    <a href="/admin/skills" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Skill Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required
                       placeholder="e.g. Billing, Network, French" style="max-width:400px;">
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description</label>
                <textarea class="form-control" id="description" name="description" rows="2"
                          placeholder="Optional — what this skill represents"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order"
                       value="<?= e(old('sort_order', (string) ($editing['sort_order'] ?? 0))) ?>" min="0" style="max-width:120px;">
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label class="form-label fw-semibold">Agents with this skill</label>
                <p class="text-muted small mb-2">Select every agent / admin who has this skill. Skill-Based routing will only auto-assign tickets requiring this skill to a member of the destination group who is also checked here.</p>

                <?php if (empty($users)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-1"></i>No agents or admins found. <a href="/admin/users/create">Create a user</a> first.
                    </div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($users as $u): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check border rounded p-2 ps-4">
                                <input class="form-check-input" type="checkbox" name="members[]"
                                       value="<?= $u['id'] ?>" id="member_<?= $u['id'] ?>"
                                       <?= in_array($u['id'], $memberIds) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="member_<?= $u['id'] ?>">
                                    <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                                    <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?> bg-opacity-10 text-<?= $roleColors[$u['role']] ?? 'secondary' ?> ms-1"><?= e(ucfirst($u['role'])) ?></span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Skill' : 'Create Skill' ?>
                </button>
                <a href="/admin/skills" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
