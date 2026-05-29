<?php
$layout       = 'app';
$pageTitle    = 'Team Skills · ' . ($group['name'] ?? '');
$breadcrumbs  = [
    ['label' => 'Manage My Team', 'url' => '/manager'],
    ['label' => $group['name'] ?? 'Group'],
];
$sidebarItems = Auth::isAdmin()
    ? adminSidebar('')
    : (Auth::role() === 'power_user' ? powerUserSidebar('') : agentSidebar(''));

$globalSkills = array_values(array_filter($skills ?? [], static fn($s) => empty($s['group_id'])));
$groupSkills  = array_values(array_filter($skills ?? [], static fn($s) => !empty($s['group_id'])));
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-person-check me-2"></i>Team Skills</h2>
        <p class="text-muted mb-0">Group: <strong><?= e($group['name']) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/manager/groups/<?= (int) $group['id'] ?>/skills" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-mortarboard me-1"></i>Curate group skills
        </a>
        <a href="/manager" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<?php if (empty($members)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-5 text-center text-muted">
        <i class="bi bi-people" style="font-size:3rem;"></i>
        <p class="mt-3 mb-0">This group has no agent / power-user / admin members yet. Ask an admin to add team members at <a href="/admin/groups/<?= (int) $group['id'] ?>/edit">Settings → Groups</a>.</p>
    </div>
</div>
<?php elseif (empty($skills)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-5 text-center text-muted">
        <i class="bi bi-mortarboard" style="font-size:3rem;"></i>
        <p class="mt-3 mb-2">No skills defined yet.</p>
        <a href="/manager/groups/<?= (int) $group['id'] ?>/skills/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-circle me-1"></i>Create your first skill
        </a>
    </div>
</div>
<?php else: ?>

<form method="POST" action="/manager/groups/<?= (int) $group['id'] ?>/team">
    <?= csrfField() ?>

    <div class="alert alert-info small mb-3">
        <i class="bi bi-info-circle me-2"></i>
        Tick a box to grant a skill, untick to revoke. <strong>Global</strong> skills (admin-curated) and <strong><?= e($group['name']) ?></strong>-owned skills are shown. Skills owned by other groups are hidden — they aren't yours to manage.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:220px;">Member</th>
                        <?php foreach ($groupSkills as $s): ?>
                        <th class="text-center" style="min-width:90px;" title="<?= e($s['description'] ?? '') ?>">
                            <span class="badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-people me-1"></i><?= e($group['name']) ?></span>
                            <div class="small fw-semibold text-body mt-1"><?= e($s['name']) ?></div>
                        </th>
                        <?php endforeach; ?>
                        <?php foreach ($globalSkills as $s): ?>
                        <th class="text-center" style="min-width:90px;" title="<?= e($s['description'] ?? '') ?>">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-globe me-1"></i>Global</span>
                            <div class="small fw-semibold text-body mt-1"><?= e($s['name']) ?></div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></div>
                            <div class="text-muted small"><?= e($m['email']) ?> · <?= e(ucfirst($m['role'])) ?></div>
                        </td>
                        <?php foreach (array_merge($groupSkills, $globalSkills) as $s): ?>
                        <td class="text-center">
                            <input type="checkbox"
                                   name="skills[<?= (int) $m['id'] ?>][]"
                                   value="<?= (int) $s['id'] ?>"
                                   class="form-check-input"
                                   <?= !empty($holdings[(int) $m['id']][(int) $s['id']]) ? 'checked' : '' ?>>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-check-lg me-1"></i>Save assignments
        </button>
        <a href="/manager/groups/<?= (int) $group['id'] ?>/team" class="btn btn-outline-secondary">Discard changes</a>
    </div>
</form>

<?php endif; ?>
