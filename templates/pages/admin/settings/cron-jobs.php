<?php
$layout       = 'app';
$pageTitle    = 'Cron Jobs';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Cron Jobs'],
];

$cronJobs = [
    [
        'title'       => 'SLA Recalculation',
        'icon'        => 'bi-stopwatch',
        'description' => 'Recalculates SLA status (breached / at-risk) for all active tickets. Should run frequently so SLA breaches are detected promptly.',
        'frequency'   => 'Every 5 minutes',
        'command'     => '*/5 * * * * php ' . ROOT_DIR . '/public/sla-cron.php >> ' . ROOT_DIR . '/storage/logs/sla-cron.log 2>&1',
        'log'         => ROOT_DIR . '/storage/logs/sla-cron.log',
        'required'    => true,
        'note'        => 'Can also be triggered via HTTP: <code>GET /sla-cron.php?token=YOUR_SECRET_TOKEN</code>. Set <code>SLA_CRON_TOKEN</code> in your <code>.env</code> file when using HTTP mode.',
    ],
    [
        'title'       => 'Inbound Email Replies',
        'icon'        => 'bi-envelope-arrow-down',
        'description' => 'Polls the configured Microsoft 365 mailbox via the Graph API for new replies and appends them to the matching ticket timeline.',
        'frequency'   => 'Every 5 minutes',
        'command'     => '*/5 * * * * php ' . ROOT_DIR . '/scripts/process-replies.php >> ' . ROOT_DIR . '/storage/logs/graph-mail.log 2>&1',
        'log'         => ROOT_DIR . '/storage/logs/graph-mail.log',
        'required'    => false,
        'note'        => 'Only required if you have Microsoft Graph / inbound email configured in Admin → Settings → Email / SMTP.',
    ],
    [
        'title'       => 'Escalation Rules',
        'icon'        => 'bi-alarm',
        'description' => 'Evaluates all enabled escalation rules against open tickets and fires any configured actions (reassign, notify, change priority, etc.).',
        'frequency'   => 'Every 15 minutes',
        'command'     => '*/15 * * * * php ' . ROOT_DIR . '/scripts/process-escalations.php >> ' . ROOT_DIR . '/storage/logs/escalations.log 2>&1',
        'log'         => ROOT_DIR . '/storage/logs/escalations.log',
        'required'    => false,
        'note'        => 'Only required if you have escalation rules configured in Admin → Settings → Escalations.',
    ],
    [
        'title'       => 'Scheduled Reports',
        'icon'        => 'bi-envelope-paper',
        'description' => 'Checks for any scheduled reports that are due and emails summaries to the configured recipients.',
        'frequency'   => 'Every 30 minutes',
        'command'     => '*/30 * * * * php ' . ROOT_DIR . '/scripts/process-scheduled-reports.php >> ' . ROOT_DIR . '/storage/logs/scheduled-reports.log 2>&1',
        'log'         => ROOT_DIR . '/storage/logs/scheduled-reports.log',
        'required'    => false,
        'note'        => 'Only required if you have scheduled reports configured in Admin → Reports → Scheduled Reports.',
    ],
    [
        'title'       => 'App Secret Expiry Reminders',
        'icon'        => 'bi-key',
        'description' => 'Sends email reminders to all administrators when the Microsoft Graph app secret is approaching its expiry date. Reminds at 30 days, 7 days, and on the day of expiry.',
        'frequency'   => 'Once daily',
        'command'     => '0 8 * * * php ' . ROOT_DIR . '/scripts/process-secret-reminders.php >> ' . ROOT_DIR . '/storage/logs/secret-reminders.log 2>&1',
        'log'         => ROOT_DIR . '/storage/logs/secret-reminders.log',
        'required'    => false,
        'note'        => 'Only required if you have a Microsoft Graph app secret expiry date configured in Admin → Settings → Email / SMTP.',
    ],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-clock me-2"></i>Cron Jobs</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        These background scripts must be scheduled on your server for certain features to function automatically.
        Add them to your server's crontab using <code>crontab -e</code>.
    </p>
</div>

<div class="alert alert-info d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-info-circle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div class="small">
        <strong>How to edit your crontab:</strong> Run <code>crontab -e</code> on your server, paste the desired
        cron lines, save, and exit. All paths below are absolute paths for your installation.
        Click any command to copy it to the clipboard.
    </div>
</div>

<?php foreach ($cronJobs as $job): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center gap-3">
        <i class="bi <?= e($job['icon']) ?> fs-5 text-primary"></i>
        <div class="flex-grow-1">
            <h6 class="mb-0 fw-semibold"><?= e($job['title']) ?></h6>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark border small">
                <i class="bi bi-repeat me-1"></i><?= e($job['frequency']) ?>
            </span>
            <?php if ($job['required']): ?>
            <span class="badge bg-danger">Required</span>
            <?php else: ?>
            <span class="badge bg-secondary">Optional</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-3"><?= e($job['description']) ?></p>

        <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Crontab entry</label>
        <div class="position-relative">
            <code class="d-block bg-light border rounded p-3 small user-select-all pe-5 cron-command"
                  style="word-break:break-all;"><?= e($job['command']) ?></code>
            <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2 copy-btn"
                    data-command="<?= e($job['command']) ?>"
                    title="Copy to clipboard">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>

        <?php if (!empty($job['note'])): ?>
        <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i><?= $job['note'] ?></p>
        <?php endif; ?>

        <div class="mt-3 pt-3 border-top">
            <span class="text-muted small"><i class="bi bi-file-text me-1"></i>Log file: <code><?= e($job['log']) ?></code></span>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Combined crontab block -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-terminal me-2"></i>Combined Crontab Block</h6>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-3">Copy all entries at once and paste into your crontab:</p>
        <div class="position-relative">
            <pre class="bg-light border rounded p-3 small mb-0 user-select-all pe-5" id="combinedBlock" style="white-space:pre-wrap;word-break:break-all;"><?php
$lines = array_map(fn($j) => $j['command'], $cronJobs);
echo e(implode("\n", $lines));
?></pre>
            <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2"
                    onclick="copyBlock()" title="Copy all">
                <i class="bi bi-clipboard" id="copyBlockIcon"></i>
            </button>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(btn.dataset.command).then(function () {
            const icon = btn.querySelector('i');
            icon.className = 'bi bi-check text-success';
            setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
        });
    });
});

function copyBlock() {
    const text = document.getElementById('combinedBlock').textContent;
    navigator.clipboard.writeText(text).then(function () {
        const icon = document.getElementById('copyBlockIcon');
        icon.className = 'bi bi-check text-success';
        setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
    });
}
</script>
