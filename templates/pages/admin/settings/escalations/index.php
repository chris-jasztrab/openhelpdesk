<?php
$layout       = 'app';
$pageTitle    = 'Escalation Rules';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Escalation Rules'],
];

$runOutput = $_SESSION['_escalation_run'] ?? null;
unset($_SESSION['_escalation_run']);
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="fw-semibold mb-1"><i class="bi bi-alarm me-2"></i>Escalation Rules</h5>
        <p class="text-muted mb-0" style="font-size:.875rem;">
            Time-driven rules that automatically act on stale or deteriorating tickets.
            Evaluated by the cron processor — unlike automations, these fire based on elapsed time, not ticket events.
        </p>
    </div>
    <a href="/admin/settings/escalations/create" class="btn text-white btn-sm" style="background:var(--ld-primary);">
        <i class="bi bi-plus-lg me-1"></i>Add Rule
    </a>
</div>

<?php if (empty($rules)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-alarm fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-3">No escalation rules yet.</p>
        <a href="/admin/settings/escalations/create" class="btn text-white btn-sm" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>Create your first rule
        </a>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle"
               data-sortable-list data-reorder-url="/admin/settings/escalations/reorder">
            <thead class="table-light">
                <tr>
                    <th data-sort-col="name">Name</th>
                    <th>Conditions</th>
                    <th>Actions</th>
                    <th style="width:90px;" data-sort-col="cooldown">Cooldown</th>
                    <th style="width:80px;" data-sort-col="enabled">Enabled</th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rules as $rule):
                $conditions = json_decode($rule['conditions'], true) ?: [];
                $actions    = json_decode($rule['actions'],    true) ?: [];
                $condLabels = [
                    'sla_state'          => 'SLA state',
                    'hours_open'         => 'Hours open',
                    'hours_since_update' => 'Hours since update',
                    'hours_in_status'    => 'Hours in status',
                    'is_assigned'        => 'Is assigned',
                    'priority_id'        => 'Priority',
                    'priority'           => 'Priority',
                    'status'             => 'Status',
                    'group_id'           => 'Group',
                    'assigned_to'        => 'Assigned to',
                    'age_minutes'        => 'Age (minutes)',
                    'type_id'            => 'Type',
                    'location_id'        => 'Location',
                ];
                $opLabels = [
                    'eq'      => '=',
                    'neq'     => '≠',
                    'in'      => 'in',
                    'not_in'  => 'not in',
                    'gte'     => '≥',
                    'lte'     => '≤',
                    'gt'      => '>',
                    'lt'      => '<',
                    'empty'   => 'is empty',
                    'not_empty' => 'is not empty',
                    'is_empty'     => 'is empty',
                    'is_not_empty' => 'is not empty',
                ];
                $actionLabels = [
                    'set_priority'          => 'Set priority',
                    'set_assigned_to'       => 'Assign to',
                    'set_group'             => 'Set group',
                    'set_status'            => 'Set status',
                    'notify_user'           => 'Notify user',
                    'notify_assigned_agent' => 'Notify assigned agent',
                    'notify_assigned'       => 'Notify assigned agent',
                    'notify_admin'          => 'Notify admin',
                    'add_internal_note'     => 'Add note',
                ];
            ?>
            <tr data-id="<?= (int) $rule['id'] ?>" class="<?= $rule['is_enabled'] ? '' : 'opacity-50' ?>">
                <td class="fw-semibold" data-sort-value="<?= e($rule['name']) ?>"><?= e($rule['name']) ?></td>
                <td style="font-size:.8rem;">
                    <?php foreach ($conditions as $c): ?>
                        <?php
                            $op  = $c['op'] ?? $c['operator'] ?? '';
                            $val = $c['value'] ?? '';
                            $valStr = is_array($val) ? implode(', ', $val) : (string) $val;
                            $hideValue = in_array($op, ['empty','not_empty','is_empty','is_not_empty'], true);
                        ?>
                        <span class="badge bg-light text-dark border me-1 mb-1">
                            <?= e($condLabels[$c['field']] ?? $c['field']) ?>
                            <?= e($opLabels[$op] ?? $op) ?>
                            <?php if (!$hideValue && $valStr !== ''): ?>
                                <strong><?= e($valStr) ?></strong>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (empty($conditions)): ?><span class="text-muted">None</span><?php endif; ?>
                </td>
                <td style="font-size:.8rem;">
                    <?php foreach ($actions as $a): ?>
                        <?php $aType = $a['type'] ?? $a['action'] ?? ''; ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary me-1 mb-1">
                            <?= e($actionLabels[$aType] ?? $aType) ?>
                        </span>
                    <?php endforeach; ?>
                </td>
                <td class="text-muted small" data-sort-value="<?= (int) $rule['cooldown_minutes'] ?>">
                    <?= (int) $rule['cooldown_minutes'] > 0 ? e(formatDuration((int) $rule['cooldown_minutes'])) : 'Once' ?>
                </td>
                <td data-sort-value="<?= $rule['is_enabled'] ? '1' : '0' ?>">
                    <form method="POST" action="/admin/settings/escalations/<?= (int) $rule['id'] ?>/toggle">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-sm <?= $rule['is_enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>" style="min-width:62px;">
                            <?= $rule['is_enabled'] ? 'On' : 'Off' ?>
                        </button>
                    </form>
                </td>
                <td class="text-end">
                    <a href="/admin/settings/escalations/<?= (int) $rule['id'] ?>/edit"
                       class="btn btn-sm btn-outline-secondary me-1">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#deleteEscalationModal"
                            data-id="<?= (int) $rule['id'] ?>"
                            data-name="<?= e($rule['name']) ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Cron + Run Now -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock me-2"></i>Cron Setup</h6>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-2">
            Add this to your server's crontab to evaluate escalation rules every 15 minutes:
        </p>
        <code class="d-block bg-light border rounded p-2 small user-select-all mb-4">
            */15 * * * * php <?= e(ROOT_DIR) ?>/scripts/process-escalations.php &gt;&gt; <?= e(ROOT_DIR) ?>/storage/logs/escalations.log 2&gt;&amp;1
        </code>

        <h6 class="fw-semibold mb-1">Run Now</h6>
        <p class="text-muted small mb-3">Execute the processor immediately for testing.</p>
        <form method="POST" action="/admin/settings/escalations/run-now">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-play-circle me-1"></i>Run Now
            </button>
        </form>

        <?php if (!empty($runOutput)): ?>
        <div class="mt-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="small fw-semibold text-muted">Last run: <?= e($runOutput['time']) ?></span>
                <?php if ($runOutput['code'] === 0): ?>
                    <span class="badge bg-success">Exit 0 — OK</span>
                <?php else: ?>
                    <span class="badge bg-danger">Exit <?= (int) $runOutput['code'] ?> — Error</span>
                <?php endif; ?>
            </div>
            <pre class="bg-dark text-light rounded p-3 small mb-0" style="max-height:300px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;"><?= e(implode("\n", $runOutput['lines'])) ?></pre>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Escalation Rule Modal -->
<div class="modal fade" id="deleteEscalationModal" tabindex="-1" aria-labelledby="deleteEscalationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteEscalationModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Escalation Rule
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete rule <strong id="deleteEscalationName"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteEscalationForm" action="">
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
document.getElementById('deleteEscalationModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteEscalationName').textContent = btn.dataset.name;
    document.getElementById('deleteEscalationForm').action = '/admin/settings/escalations/' + btn.dataset.id + '/delete';
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
<?php require ROOT_DIR . '/templates/partials/sortable-list.php'; ?>
