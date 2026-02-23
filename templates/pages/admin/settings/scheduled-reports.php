<?php
$layout       = 'app';
$pageTitle    = 'Scheduled Reports';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Scheduled Reports'],
];
$typeLabels = [
    'overview'           => 'Overview',
    'agent_performance'  => 'Agent Performance',
    'ticket_volume'      => 'Ticket Volume',
    'fcr'                => 'FCR Rate',
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="fw-semibold mb-1"><i class="bi bi-envelope-paper me-2"></i>Scheduled Reports</h5>
        <p class="text-muted mb-0" style="font-size:.875rem;">
            Automatically email report summaries to managers on a weekly or monthly cadence.
        </p>
    </div>
    <a href="/admin/settings/scheduled-reports/create" class="btn text-white btn-sm" style="background:var(--ld-primary);">
        <i class="bi bi-plus-lg me-1"></i>Add Schedule
    </a>
</div>

<?php if (empty($reports)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-envelope-paper fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-3">No scheduled reports yet.</p>
        <a href="/admin/settings/scheduled-reports/create" class="btn text-white btn-sm" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>Create your first schedule
        </a>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Report Type</th>
                    <th>Recipients</th>
                    <th>Frequency</th>
                    <th>Send Day</th>
                    <th>Last Sent</th>
                    <th style="width:80px;">Enabled</th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $report):
                $recipients = json_decode($report['recipients'], true) ?: [];
                $dayLabels  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                $sendDayDisplay = $report['frequency'] === 'weekly'
                    ? ($dayLabels[(int)$report['send_day']] ?? $report['send_day'])
                    : 'Day ' . $report['send_day'];
            ?>
            <tr class="<?= $report['is_enabled'] ? '' : 'opacity-50' ?>">
                <td class="fw-semibold"><?= e($report['name']) ?></td>
                <td><span class="badge bg-light text-dark border"><?= e($typeLabels[$report['report_type']] ?? $report['report_type']) ?></span></td>
                <td class="small text-muted" style="max-width:200px;">
                    <?php foreach (array_slice($recipients, 0, 2) as $email): ?>
                        <div><?= e($email) ?></div>
                    <?php endforeach; ?>
                    <?php if (count($recipients) > 2): ?>
                        <div class="text-muted">+<?= count($recipients) - 2 ?> more</div>
                    <?php endif; ?>
                </td>
                <td class="text-capitalize"><?= e($report['frequency']) ?></td>
                <td><?= e($sendDayDisplay) ?></td>
                <td class="text-muted small">
                    <?= $report['last_sent_at'] ? date('M j, Y', strtotime($report['last_sent_at'])) : '—' ?>
                </td>
                <td>
                    <form method="POST" action="/admin/settings/scheduled-reports/<?= (int)$report['id'] ?>/toggle">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-sm <?= $report['is_enabled'] ? 'btn-success' : 'btn-outline-secondary' ?>" style="min-width:62px;">
                            <?= $report['is_enabled'] ? 'On' : 'Off' ?>
                        </button>
                    </form>
                </td>
                <td class="text-end">
                    <a href="/admin/settings/scheduled-reports/<?= (int)$report['id'] ?>/edit"
                       class="btn btn-sm btn-outline-secondary me-1">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="/admin/settings/scheduled-reports/<?= (int)$report['id'] ?>/delete"
                          class="d-inline"
                          onsubmit="return confirm('Delete \'<?= e(addslashes($report['name'])) ?>\'?')">
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

<!-- Cron setup -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock me-2"></i>Cron Setup</h6>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-2">
            Add this to your server's crontab to check for due reports every 30 minutes:
        </p>
        <code class="d-block bg-light border rounded p-2 small user-select-all">
            */30 * * * * php <?= e(ROOT_DIR) ?>/scripts/process-scheduled-reports.php &gt;&gt; <?= e(ROOT_DIR) ?>/storage/logs/scheduled-reports.log 2&gt;&amp;1
        </code>
        <p class="text-muted small mt-3 mb-0">
            Reports are sent on the configured day of week (weekly) or day of month (monthly).
            The script is idempotent — running it multiple times on the same day will not re-send.
        </p>
    </div>
</div>
