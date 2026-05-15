<?php
$layout       = 'app';
$pageTitle    = 'Cron Jobs';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Cron Jobs'],
];

// Platform detection — Linux/macOS users get crontab lines; Windows users
// get Task Scheduler (schtasks) commands. Mixing slashes from a Linux-style
// cron line with a Windows ROOT_DIR produces a path that's invalid on either.
$isWindows = stripos(PHP_OS, 'WIN') === 0;
$phpBin    = $isWindows ? str_replace('/', '\\', PHP_BINARY) : 'php';
$rootPath  = $isWindows ? str_replace('/', '\\', ROOT_DIR) : rtrim(ROOT_DIR, '/');
$sep       = $isWindows ? '\\' : '/';

// Build the platform-appropriate scheduler command for a job.
// $cronSchedule is the Linux cron expression (kept as canonical because it's
// terse and unambiguous); $schtasksArgs is the Windows /SC … /MO … pair.
$buildCommand = static function (string $cronSchedule, string $schtasksArgs, string $scriptRel, string $logRel, string $taskName)
    use ($isWindows, $phpBin, $rootPath, $sep): string {
    if ($isWindows) {
        $script = $rootPath . $sep . str_replace('/', $sep, $scriptRel);
        // schtasks /TR wraps program+args in one double-quoted string and
        // accepts single quotes around inner paths — cleaner than the
        // backslash-escape form, and works in both cmd.exe and PowerShell.
        return sprintf(
            "schtasks /Create /TN \"%s\" /TR \"'%s' '%s'\" %s /F",
            $taskName,
            $phpBin,
            $script,
            $schtasksArgs
        );
    }
    $script = $rootPath . '/' . $scriptRel;
    $log    = $rootPath . '/' . $logRel;
    return $cronSchedule . ' ' . $phpBin . ' ' . $script . ' >> ' . $log . ' 2>&1';
};

$cronJobs = [
    [
        'title'            => 'SLA Recalculation',
        'icon'             => 'bi-stopwatch',
        'description'      => 'Recalculates SLA status (breached / at-risk) for all active tickets. Should run frequently so SLA breaches are detected promptly.',
        'frequency'        => 'Every 5 minutes',
        'interval_seconds' => 300,
        'command'          => $buildCommand('*/5 * * * *', '/SC MINUTE /MO 5', 'public/sla-cron.php', 'storage/logs/sla-cron.log', 'OpenHelpDesk SLA Recalculation'),
        'log'              => $rootPath . $sep . 'storage' . $sep . 'logs' . $sep . 'sla-cron.log',
        'required'         => true,
        'note'             => 'Can also be triggered via HTTP: <code>GET /sla-cron.php?token=YOUR_SECRET_TOKEN</code>. Set <code>SLA_CRON_TOKEN</code> in your <code>.env</code> file when using HTTP mode.',
    ],
    [
        'title'            => 'Inbound Email Replies',
        'icon'             => 'bi-envelope-arrow-down',
        'description'      => 'Polls the configured Microsoft 365 mailbox via the Graph API for new replies and appends them to the matching ticket timeline.',
        'frequency'        => 'Every 5 minutes',
        'interval_seconds' => 300,
        'command'          => $buildCommand('*/5 * * * *', '/SC MINUTE /MO 5', 'scripts/process-replies.php', 'storage/logs/graph-mail.log', 'OpenHelpDesk Inbound Email'),
        'log'              => $rootPath . $sep . 'storage' . $sep . 'logs' . $sep . 'graph-mail.log',
        'required'         => false,
        'note'             => 'Only required if you have Microsoft Graph / inbound email configured in Admin → Settings → Email / SMTP.',
    ],
    [
        'title'            => 'Escalation Rules',
        'icon'             => 'bi-alarm',
        'description'      => 'Evaluates all enabled escalation rules against open tickets and fires any configured actions (reassign, notify, change priority, etc.).',
        'frequency'        => 'Every 15 minutes',
        'interval_seconds' => 900,
        'command'          => $buildCommand('*/15 * * * *', '/SC MINUTE /MO 15', 'scripts/process-escalations.php', 'storage/logs/escalations.log', 'OpenHelpDesk Escalations'),
        'log'              => $rootPath . $sep . 'storage' . $sep . 'logs' . $sep . 'escalations.log',
        'required'         => false,
        'note'             => 'Only required if you have escalation rules configured in Admin → Settings → Escalations.',
    ],
    [
        'title'            => 'Scheduled Reports',
        'icon'             => 'bi-envelope-paper',
        'description'      => 'Checks for any scheduled reports that are due and emails summaries to the configured recipients.',
        'frequency'        => 'Every 30 minutes',
        'interval_seconds' => 1800,
        'command'          => $buildCommand('*/30 * * * *', '/SC MINUTE /MO 30', 'scripts/process-scheduled-reports.php', 'storage/logs/scheduled-reports.log', 'OpenHelpDesk Scheduled Reports'),
        'log'              => $rootPath . $sep . 'storage' . $sep . 'logs' . $sep . 'scheduled-reports.log',
        'required'         => false,
        'note'             => 'Only required if you have scheduled reports configured in Admin → Reports → Scheduled Reports.',
    ],
    [
        'title'            => 'Recurring / Preventive-Maintenance Tickets',
        'icon'             => 'bi-arrow-repeat',
        'description'      => 'Mints tickets from active recurring schedules whose <code>next_run_at</code> has passed (e.g. monthly toner audit, quarterly HVAC, annual fire inspection), then advances each schedule to its next firing slot.',
        'frequency'        => 'Every 15 minutes',
        'interval_seconds' => 900,
        'command'          => $buildCommand('*/15 * * * *', '/SC MINUTE /MO 15', 'scripts/process-recurring-tickets.php', 'storage/logs/recurring-tickets.log', 'OpenHelpDesk Recurring Tickets'),
        'log'              => $rootPath . $sep . 'storage' . $sep . 'logs' . $sep . 'recurring-tickets.log',
        'required'         => false,
        'note'             => 'Only required if you have recurring schedules configured in Admin → Recurring Tickets. Missed-tick safe — does not back-fill if cron pauses.',
    ],
    [
        'title'            => 'Stale Ticket Notifications',
        'icon'             => 'bi-hourglass-split',
        'description'      => 'Finds active tickets that have had no activity for longer than the configured stale threshold and emails both the assigned agent and the requester. Skips resolved, closed, and waiting-on-customer/third-party statuses.',
        'frequency'        => 'Every hour',
        'interval_seconds' => 3600,
        'command'          => $buildCommand('0 * * * *', '/SC HOURLY', 'scripts/process-stale-tickets.php', 'storage/logs/stale-tickets.log', 'OpenHelpDesk Stale Tickets'),
        'log'              => $rootPath . $sep . 'storage' . $sep . 'logs' . $sep . 'stale-tickets.log',
        'required'         => false,
        'note'             => 'Configure the threshold and per-type overrides in Admin → Settings → Stale Tickets.',
    ],
    [
        'title'            => 'App Secret Expiry Reminders',
        'icon'             => 'bi-key',
        'description'      => 'Sends email reminders to all administrators when the Microsoft Graph app secret is approaching its expiry date. Reminds at 30 days, 7 days, and on the day of expiry.',
        'frequency'        => 'Once daily',
        'interval_seconds' => 86400,
        'command'          => $buildCommand('0 8 * * *', '/SC DAILY /ST 08:00', 'scripts/process-secret-reminders.php', 'storage/logs/secret-reminders.log', 'OpenHelpDesk Secret Reminders'),
        'log'              => $rootPath . $sep . 'storage' . $sep . 'logs' . $sep . 'secret-reminders.log',
        'required'         => false,
        'note'             => 'Only required if you have a Microsoft Graph app secret expiry date configured in Admin → Settings → Email / SMTP.',
    ],
];

// ── Helpers for the "last run" status badge ─────────────────────────
$fmtAgo = static function (int $seconds): string {
    if ($seconds < 60)    return $seconds . 's ago';
    if ($seconds < 3600)  return (int) floor($seconds / 60)   . 'm ago';
    if ($seconds < 86400) return (int) floor($seconds / 3600) . 'h ago';
    return (int) floor($seconds / 86400) . 'd ago';
};

// Returns ['status' => ok|stale|missing, 'age' => int seconds|null, 'mtime' => int|null]
// A job is considered "running" if its log was touched within 2x its expected interval.
$cronStatus = static function (array $job): array {
    $path = $job['log'] ?? '';
    if ($path === '' || !is_file($path)) {
        return ['status' => 'missing', 'age' => null, 'mtime' => null];
    }
    $mtime = @filemtime($path);
    if ($mtime === false) {
        return ['status' => 'missing', 'age' => null, 'mtime' => null];
    }
    $age       = max(0, time() - $mtime);
    $threshold = (int) (($job['interval_seconds'] ?? 3600) * 2);
    return [
        'status' => $age <= $threshold ? 'ok' : 'stale',
        'age'    => $age,
        'mtime'  => $mtime,
    ];
};
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-clock me-2"></i>Cron Jobs</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        These background scripts must be scheduled on your server for certain features to function automatically.
        <?php if ($isWindows): ?>
        Run each <code>schtasks</code> command below in an <strong>elevated</strong> PowerShell or Command Prompt
        (Run as Administrator) to register it with Windows Task Scheduler.
        <?php else: ?>
        Add them to your server's crontab using <code>crontab -e</code>.
        <?php endif; ?>
    </p>
</div>

<?php
$summary = ['ok' => 0, 'stale' => 0, 'missing' => 0];
foreach ($cronJobs as $j) {
    $summary[$cronStatus($j)['status']]++;
}
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:42px;height:42px;background:rgba(25,135,84,.1);">
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 lh-1"><?= (int) $summary['ok'] ?></div>
                    <div class="text-muted small">Running</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:42px;height:42px;background:rgba(255,193,7,.15);">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 lh-1"><?= (int) $summary['stale'] ?></div>
                    <div class="text-muted small">Stale (overdue)</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:42px;height:42px;background:rgba(108,117,125,.1);">
                    <i class="bi bi-dash-circle-fill text-secondary fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 lh-1"><?= (int) $summary['missing'] ?></div>
                    <div class="text-muted small">Not configured</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-info-circle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div class="small">
        <?php if ($isWindows): ?>
        <strong>How to register a task:</strong> Open an <strong>elevated</strong> PowerShell or Command Prompt,
        paste the <code>schtasks /Create …</code> command, and press Enter. Replace existing entries with <code>/F</code>
        already included. Click any command to copy it to the clipboard.
        <?php else: ?>
        <strong>How to edit your crontab:</strong> Run <code>crontab -e</code> on your server, paste the desired
        cron lines, save, and exit. All paths below are absolute paths for your installation.
        Click any command to copy it to the clipboard.
        <?php endif; ?>
        <br>
        <strong>Status detection:</strong> Each job is checked by the modified time of its log file.
        A job shows <span class="badge bg-success">Running</span> if its log was written within 2× its expected interval,
        <span class="badge bg-warning text-dark">Stale</span> if it's older than that,
        or <span class="badge bg-light text-muted border">Not configured</span> if no log file exists.
        A manual "Run Now" counts as a run for detection purposes.
    </div>
</div>

<?php foreach ($cronJobs as $job): ?>
    <?php
    $st     = $cronStatus($job);
    $ageStr = $st['age'] !== null ? $fmtAgo((int) $st['age']) : '';
    $mtStr  = $st['mtime'] !== null ? date('Y-m-d H:i:s', (int) $st['mtime']) : '';
    ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center gap-3">
        <i class="bi <?= e($job['icon']) ?> fs-5 text-primary"></i>
        <div class="flex-grow-1">
            <h6 class="mb-0 fw-semibold"><?= e($job['title']) ?></h6>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <?php if ($st['status'] === 'ok'): ?>
                <span class="badge bg-success" title="Log last written <?= e($mtStr) ?>">
                    <i class="bi bi-check-circle me-1"></i>Running · <?= e($ageStr) ?>
                </span>
            <?php elseif ($st['status'] === 'stale'): ?>
                <span class="badge bg-warning text-dark" title="Log last written <?= e($mtStr) ?>; exceeds 2× expected interval">
                    <i class="bi bi-exclamation-triangle me-1"></i>Stale · <?= e($ageStr) ?>
                </span>
            <?php else: ?>
                <span class="badge bg-light text-muted border" title="No log file yet — job has never written output">
                    <i class="bi bi-dash-circle me-1"></i>Not configured
                </span>
            <?php endif; ?>
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

        <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;"><?= $isWindows ? 'Task Scheduler command' : 'Crontab entry' ?></label>
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

        <div class="mt-3 pt-3 border-top d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <span class="text-muted small"><i class="bi bi-file-text me-1"></i>Log file: <code><?= e($job['log']) ?></code></span>
            <?php if ($st['mtime'] !== null): ?>
            <span class="text-muted small"><i class="bi bi-clock-history me-1"></i>Last run: <?= e($mtStr) ?></span>
            <?php else: ?>
            <span class="text-muted small fst-italic"><i class="bi bi-clock-history me-1"></i>No runs recorded</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Combined block -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-terminal me-2"></i><?= $isWindows ? 'Combined Task Scheduler Commands' : 'Combined Crontab Block' ?></h6>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-3">
            <?= $isWindows
                ? 'Run all of these in an <strong>elevated</strong> PowerShell or Command Prompt:'
                : 'Copy all entries at once and paste into your crontab:' ?>
        </p>
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

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
