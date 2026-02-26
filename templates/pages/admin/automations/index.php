<?php
$layout       = 'app';
$pageTitle    = 'Automations – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Automations'],
];

$triggerLabels = [
    'ticket_created' => 'Ticket Created',
    'ticket_updated' => 'Ticket Updated',
];

// Build lookup maps for readable condition/action summaries
$fieldLabels = [
    'type_id'     => 'Type',
    'priority_id' => 'Priority',
    'status'      => 'Status',
    'location_id' => label('location.singular'),
    'group_id'    => 'Group',
    'assigned_to' => 'Assigned To',
];
$operatorLabels = [
    'equals'       => 'is',
    'not_equals'   => 'is not',
    'is_empty'     => 'is empty',
    'is_not_empty' => 'is not empty',
];
$actionLabels = [
    'set_group'       => 'Set group to',
    'set_assigned_to' => 'Assign to',
    'set_priority'    => 'Set priority to',
    'set_status'      => 'Set status to',
    'add_tag'         => 'Add tag',
    'add_cc'          => 'CC user',
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Automations</h5>
    <a href="/admin/settings/automations/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-lightning me-1"></i>Add Automation
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Trigger</th>
                    <th>Conditions</th>
                    <th>Actions</th>
                    <th>Enabled</th>
                    <th style="width:140px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($automations)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No automations found. Create one to get started.</td></tr>
                <?php else: ?>
                    <?php foreach ($automations as $auto):
                        $conditions = json_decode($auto['conditions'], true) ?: [];
                        $actions    = json_decode($auto['actions'], true) ?: [];
                    ?>
                    <tr class="<?= !$auto['is_enabled'] ? 'opacity-50' : '' ?>">
                        <td class="fw-semibold">
                            <i class="bi bi-lightning text-warning me-1"></i><?= e($auto['name']) ?>
                        </td>
                        <td>
                            <span class="badge bg-info bg-opacity-10 text-info">
                                <?= e($triggerLabels[$auto['trigger_event']] ?? $auto['trigger_event']) ?>
                            </span>
                        </td>
                        <td class="small text-muted" style="max-width:250px;">
                            <?php if (empty($conditions)): ?>
                                <em>Always</em>
                            <?php else: ?>
                                <?php foreach ($conditions as $c): ?>
                                    <div><?= e($fieldLabels[$c['field']] ?? $c['field']) ?> <?= e($operatorLabels[$c['operator']] ?? $c['operator']) ?><?= !in_array($c['operator'], ['is_empty', 'is_not_empty']) ? ' ' . e($c['value']) : '' ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td class="small" style="max-width:250px;">
                            <?php foreach ($actions as $a): ?>
                                <div><?= e($actionLabels[$a['action']] ?? $a['action']) ?> <strong><?= e($a['value']) ?></strong></div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <form method="POST" action="/admin/settings/automations/<?= $auto['id'] ?>/toggle" class="d-inline">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-sm <?= $auto['is_enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>" title="Toggle">
                                    <i class="bi <?= $auto['is_enabled'] ? 'bi-check-circle' : 'bi-circle' ?>"></i>
                                    <?= $auto['is_enabled'] ? 'On' : 'Off' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/settings/automations/<?= $auto['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/settings/automations/<?= $auto['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('Delete this automation?')">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
