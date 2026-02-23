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
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Name</th>
                    <th>Conditions</th>
                    <th>Actions</th>
                    <th style="width:90px;">Cooldown</th>
                    <th style="width:80px;">Enabled</th>
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
                    'status'             => 'Status',
                    'group_id'           => 'Group',
                ];
                $actionLabels = [
                    'set_priority'          => 'Set priority',
                    'set_assigned_to'       => 'Assign to',
                    'set_group'             => 'Set group',
                    'set_status'            => 'Set status',
                    'notify_user'           => 'Notify user',
                    'notify_assigned_agent' => 'Notify assigned agent',
                    'add_internal_note'     => 'Add note',
                ];
            ?>
            <tr class="<?= $rule['is_enabled'] ? '' : 'opacity-50' ?>">
                <td class="text-muted small"><?= (int) $rule['sort_order'] ?></td>
                <td class="fw-semibold"><?= e($rule['name']) ?></td>
                <td style="font-size:.8rem;">
                    <?php foreach ($conditions as $c): ?>
                        <span class="badge bg-light text-dark border me-1 mb-1">
                            <?= e($condLabels[$c['field']] ?? $c['field']) ?>
                            <?= e($c['operator']) ?>
                            <?php if (!in_array($c['operator'], ['is_empty','is_not_empty'], true)): ?>
                                <strong><?= e($c['value']) ?></strong>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (empty($conditions)): ?><span class="text-muted">None</span><?php endif; ?>
                </td>
                <td style="font-size:.8rem;">
                    <?php foreach ($actions as $a): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary me-1 mb-1">
                            <?= e($actionLabels[$a['action']] ?? $a['action']) ?>
                        </span>
                    <?php endforeach; ?>
                </td>
                <td class="text-muted small">
                    <?= $rule['cooldown_hours'] > 0 ? $rule['cooldown_hours'] . 'h' : 'Once' ?>
                </td>
                <td>
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
                    <form method="POST" action="/admin/settings/escalations/<?= (int) $rule['id'] ?>/delete"
                          class="d-inline"
                          onsubmit="return confirm('Delete rule \'<?= e(addslashes($rule['name'])) ?>\'?')">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
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
