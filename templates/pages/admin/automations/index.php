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

$statusOptions = [
    'open'                   => 'Open',
    'in_progress'            => 'In Progress',
    'pending'                => 'Pending',
    'waiting_on_customer'    => 'Waiting on Customer',
    'waiting_on_third_party' => 'Waiting on Third Party',
    'resolved'               => 'Resolved',
    'closed'                 => 'Closed',
];

// Build value-lookup maps so condition summaries show names, not IDs
$valueLookup = ['status' => $statusOptions];
foreach ($types      as $r) $valueLookup['type_id'][(string)$r['id']]     = $r['name'];
foreach ($priorities as $r) $valueLookup['priority_id'][(string)$r['id']] = $r['name'];
foreach ($locations  as $r) $valueLookup['location_id'][(string)$r['id']] = $r['name'];
foreach ($groups     as $r) $valueLookup['group_id'][(string)$r['id']]    = $r['name'];
foreach ($agents     as $r) $valueLookup['assigned_to'][(string)$r['id']] = $r['first_name'] . ' ' . $r['last_name'];

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

/**
 * Render a single condition as a readable string.
 */
$renderCond = function (array $c) use ($fieldLabels, $operatorLabels, $valueLookup): string {
    $field    = $fieldLabels[$c['field'] ?? '']    ?? ($c['field']    ?? '?');
    $op       = $operatorLabels[$c['operator'] ?? ''] ?? ($c['operator'] ?? '?');
    $rawVal   = (string) ($c['value'] ?? '');
    $val      = $valueLookup[$c['field'] ?? ''][$rawVal] ?? $rawVal;
    $noValue  = in_array($c['operator'] ?? '', ['is_empty', 'is_not_empty'], true);
    return '<span class="fw-semibold">' . e($field) . '</span> '
         . '<span class="text-muted">' . e($op) . '</span>'
         . (!$noValue ? ' <span class="text-body">' . e($val) . '</span>' : '');
};

/**
 * Render the full conditions block — handles v1 (flat array) and v2 (groups).
 */
$renderConditions = function (array $conditions) use ($renderCond): string {
    if (empty($conditions)) {
        return '<em class="text-muted">Always</em>';
    }

    // v1 backward compat: flat indexed array
    if (isset($conditions[0]['field'])) {
        $conditions = ['groups' => [['match' => 'all', 'conditions' => $conditions]]];
    }

    $groups = $conditions['groups'] ?? [];
    if (empty($groups)) {
        return '<em class="text-muted">Always</em>';
    }

    $groupParts = [];
    foreach ($groups as $group) {
        $matchMode = ($group['match'] ?? 'all') === 'any' ? 'any' : 'all';
        $connector = $matchMode === 'any'
            ? ' <span class="badge bg-warning text-dark" style="font-size:.65rem;">OR</span> '
            : ' <span class="badge bg-info text-dark" style="font-size:.65rem;">AND</span> ';

        $condParts = array_map($renderCond, $group['conditions'] ?? []);
        $inner     = implode($connector, $condParts);
        $groupParts[] = count($condParts) > 1 ? '(' . $inner . ')' : $inner;
    }

    $separator = ' <span class="badge bg-danger" style="font-size:.65rem;vertical-align:middle;">OR</span> ';
    return implode($separator, $groupParts);
};
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
                        <td class="small" style="max-width:280px;">
                            <?= $renderConditions($conditions) ?>
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

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
