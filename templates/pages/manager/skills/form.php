<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = ($isEdit ? 'Edit Skill' : 'Add Skill') . ' · ' . ($group['name'] ?? '');
$breadcrumbs  = [
    ['label' => 'Manage My Team',  'url' => '/manager'],
    ['label' => $group['name'],    'url' => '/manager/groups/' . (int) $group['id'] . '/team'],
    ['label' => 'Skills',          'url' => '/manager/groups/' . (int) $group['id'] . '/skills'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$sidebarItems = Auth::role() === 'admin'
    ? adminSidebar('')
    : (Auth::role() === 'power_user' ? powerUserSidebar('') : agentSidebar(''));
$action = $isEdit
    ? "/manager/groups/" . (int) $group['id'] . "/skills/" . (int) $editing['id'] . "/edit"
    : "/manager/groups/" . (int) $group['id'] . "/skills/create";
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Skill' : 'Add Skill' ?></h2>
    <a href="/manager/groups/<?= (int) $group['id'] ?>/skills" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle me-2"></i>
    This skill will be owned by <strong><?= e($group['name']) ?></strong>. Other groups won't see it; only you and other managers of this group can edit it.
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Skill Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required
                       placeholder="e.g. Cataloguing, French, ILL" style="max-width:400px;">
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

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Skill' : 'Create Skill' ?>
                </button>
                <a href="/manager/groups/<?= (int) $group['id'] ?>/skills" class="btn btn-outline-secondary">Cancel</a>
            </div>

            <p class="text-muted small mb-0 mt-3">
                <i class="bi bi-info-circle me-1"></i>To assign this skill to your team members, use the <a href="/manager/groups/<?= (int) $group['id'] ?>/team">Team Skills</a> grid.
            </p>
        </form>
    </div>
</div>
