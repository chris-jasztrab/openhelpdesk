<?php
$layout       = 'app';
$pageTitle    = 'Group Coverage – Reports';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Group Coverage'],
];

// Group flat result rows by ticket type so each type renders as a section.
$byType = [];
foreach ($rows as $r) {
    $tid = (int) $r['type_id'];
    if (!isset($byType[$tid])) {
        $byType[$tid] = [
            'type_name'  => $r['type_name'],
            'type_color' => $r['type_color'],
            'group_id'   => $r['group_id'] !== null ? (int) $r['group_id'] : null,
            'group_name' => $r['group_name'],
            'members'    => [],
        ];
    }
    if ($r['user_id'] !== null) {
        $byType[$tid]['members'][] = [
            'name'       => $r['member_name'],
            'email'      => $r['member_email'],
            'role'       => $r['user_role'],
            'is_manager' => (int) $r['is_manager'] === 1,
        ];
    }
}

$roleBadge = static function (string $role): string {
    return match ($role) {
        'admin'      => 'danger',
        'power_user' => 'warning',
        'agent'      => 'primary',
        default      => 'secondary',
    };
};
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Group Coverage &mdash; ticket type &rarr; default group &rarr; group members</p>
</div>

<div class="alert alert-light border d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-info-circle text-muted mt-1"></i>
    <div class="small text-muted">
        Each ticket type has a <strong>default group</strong> set in <a href="/admin/types">Admin &rarr; Ticket Types</a>. New tickets of that type are routed to the group, and only members of that group can be assigned. This report shows every type, the group it routes to, and who is currently in that group. People may appear under more than one type if the same group is reused, or if they belong to multiple groups used by different types.
    </div>
</div>

<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

<style>
@media print {
    .ld-sidebar, .ld-topnav, nav, .breadcrumb, button { display: none !important; }
    .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ccc !important; }
}
.gc-type-swatch {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 3px;
    vertical-align: middle;
    margin-right: .35rem;
    border: 1px solid rgba(0,0,0,.1);
}
</style>

<?php if (empty($byType)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center text-muted py-5">
        No ticket types have been configured yet. Add one at <a href="/admin/types">Admin &rarr; Ticket Types</a>.
    </div>
</div>
<?php else: ?>
    <?php foreach ($byType as $type): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">
                <span class="gc-type-swatch" style="background:<?= e($type['type_color']) ?>;"></span>
                <?= e($type['type_name']) ?>
                <?php if ($type['group_name'] !== null): ?>
                    <span class="text-muted fw-normal mx-2">&rarr;</span>
                    <a href="/admin/groups/<?= (int) $type['group_id'] ?>/edit" class="text-decoration-none">
                        <i class="bi bi-people me-1"></i><?= e($type['group_name']) ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted fw-normal mx-2">&rarr;</span>
                    <span class="badge bg-warning bg-opacity-10 text-warning">No default group assigned</span>
                <?php endif; ?>
            </h5>
            <span class="text-muted small">
                <?= count($type['members']) ?> member<?= count($type['members']) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if ($type['group_name'] === null): ?>
            <div class="card-body text-muted small">
                Tickets of this type are not auto-routed to any group. Set a default group at
                <a href="/admin/types">Admin &rarr; Ticket Types</a>.
            </div>
        <?php elseif (empty($type['members'])): ?>
            <div class="card-body text-muted small">
                The <strong><?= e($type['group_name']) ?></strong> group has no members.
                Add members at <a href="/admin/groups/<?= (int) $type['group_id'] ?>/edit">Admin &rarr; Groups</a>.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Member</th>
                            <th>Email</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Manager</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($type['members'] as $m): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($m['name']) ?></td>
                            <td><a href="mailto:<?= e($m['email']) ?>" class="text-decoration-none"><?= e($m['email']) ?></a></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $roleBadge($m['role']) ?> bg-opacity-10 text-<?= $roleBadge($m['role']) ?>">
                                    <?= e(roleLabel($m['role'])) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($m['is_manager']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-star-fill me-1"></i>Manager</span>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
