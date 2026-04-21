<?php
$layout       = 'app';
$pageTitle    = 'Stale Tickets';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Stale Tickets'],
];

$runOutput = $_SESSION['_stale_run'] ?? null;
unset($_SESSION['_stale_run']);

$thresholdHours = (int) ($settings['stale_threshold_hours'] ?? 72);
$recheckHours   = (int) ($settings['stale_recheck_hours']   ?? 24);
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-hourglass-split me-2"></i>Stale Tickets</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        Automatically notify the assigned agent (and reassure the requester) when a ticket has had no activity for longer than the threshold below.
        Tickets in <strong>Waiting on Customer</strong>, <strong>Waiting on Third Party</strong>, <strong>Resolved</strong>, or <strong>Closed</strong> statuses are ignored — the clock only runs on tickets that are waiting on your team.
    </p>
</div>

<!-- Global settings -->
<form method="POST" action="/admin/settings/stale-tickets">
    <?= csrfField() ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-sliders me-2"></i>Global Thresholds</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="stale_threshold_hours" class="form-label fw-semibold">Stale threshold (hours)</label>
                    <input type="number" min="1" class="form-control" id="stale_threshold_hours"
                           name="stale_threshold_hours" value="<?= (int) $thresholdHours ?>">
                    <div class="form-text">A ticket is considered stale once it has had no update for this many hours. Default: 72 (3 days). Set to <code>0</code> to disable the feature entirely.</div>
                </div>
                <div class="col-md-6">
                    <label for="stale_recheck_hours" class="form-label fw-semibold">Re-notify after (hours)</label>
                    <input type="number" min="1" class="form-control" id="stale_recheck_hours"
                           name="stale_recheck_hours" value="<?= (int) $recheckHours ?>">
                    <div class="form-text">Minimum gap between repeat stale notifications on the same ticket. Default: 24.</div>
                </div>
            </div>

            <hr class="my-4">

            <h6 class="fw-semibold mb-3">Email notifications</h6>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="notify_agent" name="notify_agent"
                       <?= ($settings['email_notify:ticket_stale_agent'] ?? '1') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_agent">
                    Notify the assigned agent (or group members if unassigned)
                </label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="notify_requester" name="notify_requester"
                       <?= ($settings['email_notify:ticket_stale_requester'] ?? '1') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_requester">
                    Send the requester a "we haven't forgotten you" update
                </label>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i>Save Settings
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Per-type overrides -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-tags me-2"></i>Per-Type Overrides</h6>
        <a href="/admin/types" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Manage Ticket Types
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($types)): ?>
            <div class="p-4 text-muted small">No ticket types configured yet.</div>
        <?php else: ?>
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ticket Type</th>
                    <th style="width:240px;">Stale Threshold</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($types as $t): ?>
                <tr>
                    <td>
                        <span class="badge" style="background:<?= e($t['color'] ?? '#6c757d') ?>;">
                            <?= e($t['name']) ?>
                        </span>
                    </td>
                    <td class="text-muted small">
                        <?php if ($t['stale_threshold_hours'] === null || $t['stale_threshold_hours'] === ''): ?>
                            Uses global (<?= (int) $thresholdHours ?>h)
                        <?php else: ?>
                            <strong><?= (int) $t['stale_threshold_hours'] ?>h</strong>
                            <span class="text-muted ms-1">(override)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-white text-muted small">
        To override the threshold for a specific ticket type, edit it from <a href="/admin/types">Ticket Types</a>.
    </div>
</div>

<!-- Cron + Run Now -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock me-2"></i>Cron Setup</h6>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-2">
            Add this to your server's crontab so stale tickets are detected automatically. Runs every hour:
        </p>
        <code class="d-block bg-light border rounded p-2 small user-select-all mb-4">
            0 * * * * php <?= e(ROOT_DIR) ?>/scripts/process-stale-tickets.php &gt;&gt; <?= e(ROOT_DIR) ?>/storage/logs/stale-tickets.log 2&gt;&amp;1
        </code>

        <h6 class="fw-semibold mb-1">Run Now</h6>
        <p class="text-muted small mb-3">Execute the processor immediately for testing.</p>
        <form method="POST" action="/admin/settings/stale-tickets/run-now">
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

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
