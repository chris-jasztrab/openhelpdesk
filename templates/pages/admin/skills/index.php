<?php
$layout       = 'app';
$pageTitle    = 'Agent Skills – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Agent Skills'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Agent Skills</h5>
    <div class="d-flex gap-2">
        <a href="/admin/skills/suggest" class="btn btn-outline-primary" title="Use AI to suggest skills based on your ticket types, groups, and organization type">
            <i class="bi bi-magic me-1"></i>Suggest with AI
        </a>
        <a href="/admin/skills/create" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-mortarboard me-1"></i>Add Skill
        </a>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Skills back the <strong>Skill-Based</strong> auto-assign strategy. Tag agents with the skills they hold, then mark which skills each Ticket Type requires. New tickets routed to a Skill-Based group will only be auto-assigned to members whose skill set covers every required skill.
    <br><br>
    <strong>Scope</strong> — leave a skill <em>Global</em> to keep it admin-only and visible everywhere. Pick a <em>group</em> to delegate ownership: managers of that group can edit the skill and assign it to their team without admin involvement. See <a href="/admin/docs/users#group-managers" class="alert-link">Group Managers</a>.
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
                    <th>Sort</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($skills)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No skills defined yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($skills as $s): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-mortarboard text-muted me-1"></i><?= e($s['name']) ?>
                        </td>
                        <td>
                            <?php if (empty($s['group_id'])): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-globe me-1"></i>Global</span>
                            <?php else: ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-people me-1"></i><?= e($s['group_name'] ?? 'Group') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:300px;"><?= e($s['description'] ? mb_strimwidth($s['description'], 0, 80, '...') : '—') ?></td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary"><?= (int) $s['agent_count'] ?> agent<?= (int) $s['agent_count'] !== 1 ? 's' : '' ?></span>
                        </td>
                        <td>
                            <span class="badge bg-info bg-opacity-10 text-info"><?= (int) $s['type_count'] ?> type<?= (int) $s['type_count'] !== 1 ? 's' : '' ?></span>
                        </td>
                        <td class="text-muted"><?= (int) $s['sort_order'] ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/skills/<?= $s['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteSkillModal"
                                        data-id="<?= $s['id'] ?>"
                                        data-name="<?= e($s['name']) ?>">
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

<div class="modal fade" id="deleteSkillModal" tabindex="-1" aria-labelledby="deleteSkillModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteSkillModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Skill
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete skill <strong id="deleteSkillName"></strong>? Agents will lose this skill and ticket types will lose this requirement.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteSkillForm" action="">
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
document.getElementById('deleteSkillModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteSkillName').textContent = btn.dataset.name;
    document.getElementById('deleteSkillForm').action = '/admin/skills/' + btn.dataset.id + '/delete';
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
