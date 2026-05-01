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

            <div class="mb-3">
                <label for="group_id" class="form-label fw-semibold">Scope</label>
                <select class="form-select" id="group_id" name="group_id" style="max-width:400px;">
                    <?php $currentGroup = old('group_id', (string) ($editing['group_id'] ?? '')); ?>
                    <option value="" <?= $currentGroup === '' ? 'selected' : '' ?>>Global — admin-only (default)</option>
                    <?php foreach (($groups ?? []) as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= (string) $currentGroup === (string) $g['id'] ? 'selected' : '' ?>>Owned by group: <?= e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Global skills are managed by admins only. A group-scoped skill can be edited by managers of that group, who can also assign it to their team via the Manage My Team page.</div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label class="form-label fw-semibold">Agents with this skill</label>
                <p class="text-muted small mb-2" id="membersHelp">
                    Select every agent / admin who has this skill. Skill-Based routing will only auto-assign tickets requiring this skill to a member of the destination group who is also checked here.
                </p>

                <?php if (empty($users)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-1"></i>No agents or admins found. <a href="/admin/users/create">Create a user</a> first.
                    </div>
                <?php else: ?>
                    <div class="row g-2" id="membersGrid">
                        <?php foreach ($users as $u): ?>
                            <?php $gids = $userGroups[(int) $u['id']] ?? []; ?>
                        <div class="col-md-6 col-lg-4 js-member-card" data-group-ids="<?= e(implode(',', $gids)) ?>">
                            <div class="form-check border rounded p-2 ps-4">
                                <input class="form-check-input js-member-checkbox" type="checkbox" name="members[]"
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
                    <div id="membersEmpty" class="alert alert-warning mt-2 mb-0 d-none">
                        <i class="bi bi-info-circle me-1"></i>No agents are members of the selected group. Add members to the group first under <a href="/admin/groups">Admin → Settings → Groups</a>, then come back here to grant them this skill.
                    </div>
                <?php endif; ?>
            </div>

            <script>
            /* Filter the "Agents with this skill" list to only members of the
               selected owning group. When scope is "Global" we show every
               agent / admin (admins manage global skills directly). When a
               group is picked, we hide and uncheck users who don't belong to
               that group — so saving the form can't accidentally retain a
               skill for someone outside the group's membership. */
            (function () {
                var scopeSelect = document.getElementById('group_id');
                var grid = document.getElementById('membersGrid');
                if (!scopeSelect || !grid) return;
                var emptyMsg = document.getElementById('membersEmpty');
                var helpText = document.getElementById('membersHelp');
                var helpDefault = helpText ? helpText.textContent : '';

                function applyFilter() {
                    var groupId = scopeSelect.value; // '' === Global
                    var cards = grid.querySelectorAll('.js-member-card');
                    var visible = 0;
                    cards.forEach(function (card) {
                        var raw = card.getAttribute('data-group-ids') || '';
                        var ids = raw === '' ? [] : raw.split(',');
                        var show = (groupId === '') || (ids.indexOf(groupId) !== -1);
                        card.classList.toggle('d-none', !show);
                        if (!show) {
                            var cb = card.querySelector('.js-member-checkbox');
                            if (cb && cb.checked) cb.checked = false;
                        } else {
                            visible++;
                        }
                    });
                    if (emptyMsg) emptyMsg.classList.toggle('d-none', visible !== 0);
                    if (helpText) {
                        if (groupId === '') {
                            helpText.textContent = helpDefault;
                        } else {
                            var groupLabel = scopeSelect.options[scopeSelect.selectedIndex].textContent.replace(/^Owned by group:\s*/, '');
                            helpText.textContent = 'Showing only members of "' + groupLabel + '". Skill-Based routing will only auto-assign tickets requiring this skill to checked members of this group.';
                        }
                    }
                }

                scopeSelect.addEventListener('change', applyFilter);
                applyFilter();
            })();
            </script>

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
