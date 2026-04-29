<?php
$layout       = 'app';
$pageTitle    = 'Group Skills · ' . ($group['name'] ?? '');
$breadcrumbs  = [
    ['label' => 'Manage My Team',  'url' => '/manager'],
    ['label' => $group['name'],    'url' => '/manager/groups/' . (int) $group['id'] . '/team'],
    ['label' => 'Skills'],
];
$sidebarItems = Auth::role() === 'admin'
    ? adminSidebar('')
    : (Auth::role() === 'power_user' ? powerUserSidebar('') : agentSidebar(''));
?>
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-mortarboard me-2"></i>Group Skills</h2>
        <p class="text-muted mb-0">Catalogue for group <strong><?= e($group['name']) ?></strong>. Global skills are shown here for context but can only be edited by an admin.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/manager/groups/<?= (int) $group['id'] ?>/skills/create" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-circle me-1"></i>Add Skill
        </a>
        <a href="/manager/groups/<?= (int) $group['id'] ?>/team" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to team
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Scope</th>
                    <th>Description</th>
                    <th>Agents</th>
                    <th>Required by Types</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($skills)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No skills available yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($skills as $s): $owned = (int) ($s['group_id'] ?? 0) === (int) $group['id']; ?>
                    <tr>
                        <td class="fw-semibold"><i class="bi bi-mortarboard text-muted me-1"></i><?= e($s['name']) ?></td>
                        <td>
                            <?php if ($owned): ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-people me-1"></i>Owned by <?= e($group['name']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-globe me-1"></i>Global</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:300px;"><?= e($s['description'] ? mb_strimwidth($s['description'], 0, 80, '…') : '—') ?></td>
                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= (int) $s['agent_count'] ?> agent<?= (int) $s['agent_count'] === 1 ? '' : 's' ?></span></td>
                        <td><span class="badge bg-info bg-opacity-10 text-info"><?= (int) $s['type_count'] ?> type<?= (int) $s['type_count'] === 1 ? '' : 's' ?></span></td>
                        <td>
                            <?php if ($owned): ?>
                            <div class="d-flex gap-1">
                                <a href="/manager/groups/<?= (int) $group['id'] ?>/skills/<?= (int) $s['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST"
                                      action="/manager/groups/<?= (int) $group['id'] ?>/skills/<?= (int) $s['id'] ?>/delete"
                                      onsubmit="return confirm('Delete the skill &quot;<?= e($s['name']) ?>&quot;? Agents will lose this skill and any ticket types requiring it will lose the requirement.');">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small"><i class="bi bi-lock me-1"></i>Admin-only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
