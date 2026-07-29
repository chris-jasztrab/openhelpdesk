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
    'overview'          => 'Overview',
    'agent_performance' => 'Agent Performance',
    'ticket_volume'     => 'Ticket Volume',
    'response_times'    => 'Response Times',
    'sla'               => 'SLA Compliance',
    'unresolved'        => 'Unresolved Tickets',
    'lifecycle'         => 'Ticket Lifecycle',
    'location'          => 'By ' . label('location.singular'),
    'csat'              => 'CSAT / Satisfaction',
    'workload'          => 'Agent Workload',
    'trends'            => 'Ticket Trends',
    'fcr'               => 'FCR Rate',
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
            <?php if ($seesAll): ?>
                Automatically email report summaries on a daily, weekly, or monthly cadence.
                As an admin you see every schedule, whoever created it.
            <?php else: ?>
                Automatically email report summaries on a daily, weekly, or monthly cadence.
                You see and manage the schedules you created.
            <?php endif; ?>
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
        <p class="mb-3"><?= $seesAll ? 'No scheduled reports yet.' : "You haven't scheduled any reports yet." ?></p>
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
                    <th>Report</th>
                    <th>Cadence</th>
                    <th>Created By</th>
                    <th>Recipients</th>
                    <th>Last Sent</th>
                    <th style="width:80px;">Enabled</th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $report):
                $recipients = json_decode($report['recipients'], true) ?: [];
                $dayLabels  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                // One plain-English cadence line beats three columns of parts.
                if ($report['frequency'] === 'daily') {
                    $cadence = 'Every day';
                } elseif ($report['frequency'] === 'weekly') {
                    $cadence = 'Every ' . ($dayLabels[(int)$report['send_day']] ?? $report['send_day']);
                } else {
                    $cadence = 'Monthly on day ' . (int)$report['send_day'];
                }
                $dateRangeDays = (int)($report['date_range_days'] ?? 30);
                $isMine        = (int)($report['created_by'] ?? 0) === (int)Auth::id();
                $creatorName   = trim((string)($report['creator_name'] ?? ''));
            ?>
            <tr class="<?= $report['is_enabled'] ? '' : 'opacity-50' ?>">
                <td>
                    <div class="fw-semibold"><?= e($report['name']) ?></div>
                    <span class="badge bg-light text-dark border fw-normal"><?= e($typeLabels[$report['report_type']] ?? $report['report_type']) ?></span>
                </td>
                <td>
                    <div><?= e($cadence) ?></div>
                    <div class="text-muted small">Covers previous <?= $dateRangeDays ?> days</div>
                </td>
                <td class="small">
                    <?php if ($isMine): ?>
                        <span class="badge rounded-pill text-bg-secondary fw-normal">You</span>
                    <?php elseif ($creatorName !== ''): ?>
                        <div><?= e($creatorName) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= e((string)($report['creator_email'] ?? '')) ?></div>
                    <?php else: ?>
                        <span class="text-muted" title="The account that created this schedule no longer exists">No owner</span>
                    <?php endif; ?>
                </td>
                <td class="small text-muted" style="max-width:200px;">
                    <?php foreach (array_slice($recipients, 0, 2) as $email): ?>
                        <div><?= e($email) ?></div>
                    <?php endforeach; ?>
                    <?php if (count($recipients) > 2): ?>
                        <div class="text-muted">+<?= count($recipients) - 2 ?> more</div>
                    <?php endif; ?>
                </td>
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
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal" data-bs-target="#deleteReportModal"
                            data-id="<?= (int)$report['id'] ?>"
                            data-name="<?= e($report['name']) ?>">
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

<!-- Cron setup — server-side wiring, so only admins can act on it -->
<?php if (Auth::isAdmin()): ?>
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
            Daily reports run once per day, weekly on the configured day of week, monthly on the configured day of month.
            The script is idempotent — running it multiple times on the same day will not re-send a daily report.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Delete Scheduled Report Modal -->
<div class="modal fade" id="deleteReportModal" tabindex="-1" aria-labelledby="deleteReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteReportModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Scheduled Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete <strong id="deleteReportName"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteReportForm" action="">
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
document.getElementById('deleteReportModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteReportName').textContent = btn.dataset.name;
    document.getElementById('deleteReportForm').action = '/admin/settings/scheduled-reports/' + btn.dataset.id + '/delete';
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
