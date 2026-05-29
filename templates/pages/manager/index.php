<?php
$layout       = 'app';
$pageTitle    = 'Manage My Team';
$breadcrumbs  = [['label' => 'Manage My Team']];
$sidebarItems = Auth::isAdmin()
    ? adminSidebar('')
    : (Auth::role() === 'power_user' ? powerUserSidebar('') : agentSidebar(''));
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1"><i class="bi bi-stars me-2"></i>Manage My Team</h2>
    <p class="text-muted mb-0">Maintain the skills your team holds without filing a ticket with IT — assign existing skills to your members, and (optionally) curate skills owned by your group.</p>
</div>

<?php if (Auth::isAdmin()): ?>
<div class="alert alert-info small mb-4">
    <i class="bi bi-info-circle me-2"></i>You're seeing every group because you're an admin. Group managers see only the groups they were delegated.
</div>
<?php endif; ?>

<?php if (empty($groups)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-5 text-center text-muted">
        <i class="bi bi-people" style="font-size:3rem;"></i>
        <p class="mt-3 mb-0">You aren't flagged as a manager of any group yet. Ask an admin to delegate manager rights from <a href="/admin/groups">Admin → Settings → Groups</a>.</p>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($groups as $g): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="fw-semibold mb-1"><?= e($g['name']) ?></h5>
                <p class="text-muted small mb-3" style="min-height:2.5rem;">
                    <?= e($g['description'] ? mb_strimwidth($g['description'], 0, 110, '…') : 'No description.') ?>
                </p>
                <div class="d-flex gap-2 mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-people me-1"></i><?= (int) $g['member_count'] ?> member<?= (int) $g['member_count'] === 1 ? '' : 's' ?></span>
                    <span class="badge bg-info bg-opacity-10 text-info"><i class="bi bi-mortarboard me-1"></i><?= (int) $g['skill_count'] ?> skill<?= (int) $g['skill_count'] === 1 ? '' : 's' ?></span>
                </div>
                <div class="d-grid gap-2">
                    <a href="/manager/groups/<?= (int) $g['id'] ?>/team" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-person-check me-1"></i>Assign skills to team
                    </a>
                    <a href="/manager/groups/<?= (int) $g['id'] ?>/skills" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-mortarboard me-1"></i>Curate group skills
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
