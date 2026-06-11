<?php
$layout       = 'app';
$pageTitle    = 'Cron Jobs';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Cron Jobs'],
];

// Detected platform — drives the default-selected tab. Both platforms'
// commands are always rendered; the tab only controls which one is visible.
$isWindows = stripos(PHP_OS, 'WIN') === 0;

// ── Path/binary helpers ─────────────────────────────────────────────
// When we know the install lives on this OS, we emit its real absolute paths
// (copy-and-paste ready). When we're showing the *other* platform's tab, we
// fall back to a conventional placeholder so the command is at least shaped
// correctly — the user replaces the path with their own.
$linuxRoot   = $isWindows ? '/var/www/openhelpdesk'       : rtrim(ROOT_DIR, '/');
$linuxPhp    = 'php';

$windowsRoot = $isWindows ? str_replace('/', '\\', rtrim(ROOT_DIR, '/')) : 'C:\\xampp\\htdocs\\freshwpl';
$windowsPhp  = $isWindows ? str_replace('/', '\\', PHP_BINARY)           : 'C:\\xampp\\php\\php.exe';

// Linux cron line: `*/5 * * * * php /path/script.php >> /path/log 2>&1`
$buildLinux = static function (string $cron, string $scriptRel, string $logRel)
    use ($linuxRoot, $linuxPhp): string {
    $script = $linuxRoot . '/' . $scriptRel;
    $log    = $linuxRoot . '/' . $logRel;
    return $cron . ' ' . $linuxPhp . ' ' . $script . ' >> ' . $log . ' 2>&1';
};

// Windows schtasks command. The `/TR "'php' 'script'"` pattern (single quotes
// inside double quotes) is schtasks' supported way to embed two
// space-containing paths in one /TR value, and it parses identically in
// cmd.exe and PowerShell. /F overwrites an existing task with the same name.
$buildWindows = static function (string $schedArgs, string $scriptRel, string $taskName)
    use ($windowsRoot, $windowsPhp): string {
    $script = $windowsRoot . '\\' . str_replace('/', '\\', $scriptRel);
    return sprintf(
        "schtasks /Create /TN \"%s\" /TR \"'%s' '%s'\" %s /F",
        $taskName,
        $windowsPhp,
        $script,
        $schedArgs
    );
};

$cronJobs = [
    [
        'title'            => 'SLA Recalculation',
        'icon'             => 'bi-stopwatch',
        'description'      => 'Recalculates SLA status (breached / at-risk) for all active tickets. Should run frequently so SLA breaches are detected promptly.',
        'frequency'        => 'Every 5 minutes',
        'interval_seconds' => 300,
        'cron_linux'       => $buildLinux('*/5 * * * *', 'public/sla-cron.php', 'storage/logs/sla-cron.log'),
        'cron_windows'     => $buildWindows('/SC MINUTE /MO 5', 'public/sla-cron.php', 'OpenHelpDesk SLA Recalculation'),
        'log_linux'        => $linuxRoot . '/storage/logs/sla-cron.log',
        'log_windows'      => $windowsRoot . '\\storage\\logs\\sla-cron.log',
        'required'         => true,
        'note'             => 'Can also be triggered via HTTP: <code>GET /sla-cron.php?token=YOUR_SECRET_TOKEN</code>. Set <code>SLA_CRON_TOKEN</code> in your <code>.env</code> file when using HTTP mode.',
    ],
    [
        'title'            => 'Inbound Email Replies',
        'icon'             => 'bi-envelope-arrow-down',
        'description'      => 'Polls the configured Microsoft 365 mailbox via the Graph API for new replies and appends them to the matching ticket timeline.',
        'frequency'        => 'Every 5 minutes',
        'interval_seconds' => 300,
        'cron_linux'       => $buildLinux('*/5 * * * *', 'scripts/process-replies.php', 'storage/logs/graph-mail.log'),
        'cron_windows'     => $buildWindows('/SC MINUTE /MO 5', 'scripts/process-replies.php', 'OpenHelpDesk Inbound Email'),
        'log_linux'        => $linuxRoot . '/storage/logs/graph-mail.log',
        'log_windows'      => $windowsRoot . '\\storage\\logs\\graph-mail.log',
        'required'         => false,
        'note'             => 'Only required if you have Microsoft Graph / inbound email configured in Admin → Settings → Email / SMTP.',
    ],
    [
        'title'            => 'Escalation Rules',
        'icon'             => 'bi-alarm',
        'description'      => 'Evaluates all enabled escalation rules against open tickets and fires any configured actions (reassign, notify, change priority, etc.).',
        'frequency'        => 'Every 15 minutes',
        'interval_seconds' => 900,
        'cron_linux'       => $buildLinux('*/15 * * * *', 'scripts/process-escalations.php', 'storage/logs/escalations.log'),
        'cron_windows'     => $buildWindows('/SC MINUTE /MO 15', 'scripts/process-escalations.php', 'OpenHelpDesk Escalations'),
        'log_linux'        => $linuxRoot . '/storage/logs/escalations.log',
        'log_windows'      => $windowsRoot . '\\storage\\logs\\escalations.log',
        'required'         => false,
        'note'             => 'Only required if you have escalation rules configured in Admin → Settings → Escalations.',
    ],
    [
        'title'            => 'Scheduled Reports',
        'icon'             => 'bi-envelope-paper',
        'description'      => 'Checks for any scheduled reports that are due and emails summaries to the configured recipients.',
        'frequency'        => 'Every 30 minutes',
        'interval_seconds' => 1800,
        'cron_linux'       => $buildLinux('*/30 * * * *', 'scripts/process-scheduled-reports.php', 'storage/logs/scheduled-reports.log'),
        'cron_windows'     => $buildWindows('/SC MINUTE /MO 30', 'scripts/process-scheduled-reports.php', 'OpenHelpDesk Scheduled Reports'),
        'log_linux'        => $linuxRoot . '/storage/logs/scheduled-reports.log',
        'log_windows'      => $windowsRoot . '\\storage\\logs\\scheduled-reports.log',
        'required'         => false,
        'note'             => 'Only required if you have scheduled reports configured in Admin → Reports → Scheduled Reports.',
    ],
    [
        'title'            => 'Recurring / Preventive-Maintenance Tickets',
        'icon'             => 'bi-arrow-repeat',
        'description'      => 'Mints tickets from active recurring schedules whose <code>next_run_at</code> has passed (e.g. monthly toner audit, quarterly HVAC, annual fire inspection), then advances each schedule to its next firing slot.',
        'frequency'        => 'Every 15 minutes',
        'interval_seconds' => 900,
        'cron_linux'       => $buildLinux('*/15 * * * *', 'scripts/process-recurring-tickets.php', 'storage/logs/recurring-tickets.log'),
        'cron_windows'     => $buildWindows('/SC MINUTE /MO 15', 'scripts/process-recurring-tickets.php', 'OpenHelpDesk Recurring Tickets'),
        'log_linux'        => $linuxRoot . '/storage/logs/recurring-tickets.log',
        'log_windows'      => $windowsRoot . '\\storage\\logs\\recurring-tickets.log',
        'required'         => false,
        'note'             => 'Only required if you have recurring schedules configured in Admin → Recurring Tickets. Missed-tick safe — does not back-fill if cron pauses.',
    ],
    [
        'title'            => 'Stale Ticket Notifications',
        'icon'             => 'bi-hourglass-split',
        'description'      => 'Finds active tickets that have had no activity for longer than the configured stale threshold and emails both the assigned agent and the requester. Skips resolved, closed, and waiting-on-customer/third-party statuses.',
        'frequency'        => 'Every hour',
        'interval_seconds' => 3600,
        'cron_linux'       => $buildLinux('0 * * * *', 'scripts/process-stale-tickets.php', 'storage/logs/stale-tickets.log'),
        'cron_windows'     => $buildWindows('/SC HOURLY', 'scripts/process-stale-tickets.php', 'OpenHelpDesk Stale Tickets'),
        'log_linux'        => $linuxRoot . '/storage/logs/stale-tickets.log',
        'log_windows'      => $windowsRoot . '\\storage\\logs\\stale-tickets.log',
        'required'         => false,
        'note'             => 'Configure the threshold and per-type overrides in Admin → Settings → Stale Tickets.',
    ],
    [
        'title'            => 'App Secret Expiry Reminders',
        'icon'             => 'bi-key',
        'description'      => 'Sends email reminders to all administrators when the Microsoft Graph app secret is approaching its expiry date. Reminds at 30 days, 7 days, and on the day of expiry.',
        'frequency'        => 'Once daily',
        'interval_seconds' => 86400,
        'cron_linux'       => $buildLinux('0 8 * * *', 'scripts/process-secret-reminders.php', 'storage/logs/secret-reminders.log'),
        'cron_windows'     => $buildWindows('/SC DAILY /ST 08:00', 'scripts/process-secret-reminders.php', 'OpenHelpDesk Secret Reminders'),
        'log_linux'        => $linuxRoot . '/storage/logs/secret-reminders.log',
        'log_windows'      => $windowsRoot . '\\storage\\logs\\secret-reminders.log',
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

// Status checking only makes sense for THIS server's log file — i.e. the
// platform we're actually running on. We don't know the remote machine's log
// state if a Linux admin is viewing the Windows tab from a Linux server.
$cronStatus = static function (array $job) use ($isWindows): array {
    $path = $isWindows ? ($job['log_windows'] ?? '') : ($job['log_linux'] ?? '');
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

$defaultPlatform = $isWindows ? 'windows' : 'linux';
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-clock me-2"></i>Cron Jobs</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        These background scripts must be scheduled on your server for certain features to function automatically.
        Choose your platform below to see the matching commands &mdash; <strong>Linux/macOS</strong> uses
        <code>crontab</code> and <strong>Windows</strong> uses <code>schtasks</code> (Task Scheduler).
    </p>
</div>

<!-- Platform toggle — flips visibility of .platform-linux / .platform-windows blocks below. -->
<ul class="nav nav-pills mb-4" id="platformTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link platform-tab <?= $defaultPlatform === 'linux' ? 'active' : '' ?>"
                data-platform="linux" type="button">
            <i class="bi bi-ubuntu me-1"></i>Linux / macOS
            <?php if (!$isWindows): ?><span class="badge bg-light text-dark ms-1">Detected</span><?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link platform-tab <?= $defaultPlatform === 'windows' ? 'active' : '' ?>"
                data-platform="windows" type="button">
            <i class="bi bi-windows me-1"></i>Windows
            <?php if ($isWindows): ?><span class="badge bg-light text-dark ms-1">Detected</span><?php endif; ?>
        </button>
    </li>
</ul>

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

<!-- Linux-only instruction banner -->
<div class="alert alert-info d-flex gap-3 align-items-start mb-4 platform-linux <?= $defaultPlatform === 'linux' ? '' : 'd-none' ?>">
    <i class="bi bi-info-circle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div class="small">
        <strong>How to edit your crontab:</strong> Run <code>crontab -e</code> on your server, paste the desired
        cron lines, save, and exit.
        <?php if ($isWindows): ?>
            The paths below use the conventional Linux web-root <code>/var/www/openhelpdesk</code> as a placeholder &mdash;
            replace it with your actual install path.
        <?php else: ?>
            All paths below are absolute paths for your installation.
        <?php endif; ?>
        Click any command to copy it to the clipboard.
        <br>
        <strong>Status detection:</strong> Each job is checked by the modified time of its log file.
        A job shows <span class="badge bg-success">Running</span> if its log was written within 2&times; its expected interval,
        <span class="badge bg-warning text-dark">Stale</span> if it's older than that,
        or <span class="badge bg-light text-muted border">Not configured</span> if no log file exists.
    </div>
</div>

<!-- Windows-only instruction banner -->
<div class="alert alert-info d-flex gap-3 align-items-start mb-4 platform-windows <?= $defaultPlatform === 'windows' ? '' : 'd-none' ?>">
    <i class="bi bi-info-circle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div class="small">
        <strong>How to register a task:</strong> Open an <strong>elevated</strong> PowerShell or Command Prompt
        (Run as Administrator), paste a <code>schtasks /Create &hellip;</code> command, and press Enter. The
        <code>/F</code> flag overwrites an existing task with the same name, so you can safely re-run a command
        to update its schedule. You can also create tasks via <strong>Task Scheduler &rarr; Create Basic Task</strong>
        in the GUI if you prefer &mdash; use the trigger and program/arguments from each command below.
        <?php if (!$isWindows): ?>
            The paths below use <code>C:\xampp\htdocs\freshwpl</code> and <code>C:\xampp\php\php.exe</code> as
            placeholders (typical for XAMPP) &mdash; replace them with your actual install paths.
        <?php endif; ?>
        Click any command to copy it to the clipboard.
        <br>
        <strong>Status detection:</strong> Each job is checked by the modified time of its log file.
        A job shows <span class="badge bg-success">Running</span> if its log was written within 2&times; its expected interval,
        <span class="badge bg-warning text-dark">Stale</span> if it's older than that,
        or <span class="badge bg-light text-muted border">Not configured</span> if no log file exists.
    </div>
</div>

<?php foreach ($cronJobs as $idx => $job): ?>
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
                    <i class="bi bi-check-circle me-1"></i>Running &middot; <?= e($ageStr) ?>
                </span>
            <?php elseif ($st['status'] === 'stale'): ?>
                <span class="badge bg-warning text-dark" title="Log last written <?= e($mtStr) ?>; exceeds 2&times; expected interval">
                    <i class="bi bi-exclamation-triangle me-1"></i>Stale &middot; <?= e($ageStr) ?>
                </span>
            <?php else: ?>
                <span class="badge bg-light text-muted border" title="No log file yet on this server">
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

        <!-- Linux command -->
        <div class="platform-linux <?= $defaultPlatform === 'linux' ? '' : 'd-none' ?>">
            <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">
                Crontab entry
            </label>
            <div class="position-relative">
                <code class="d-block bg-light border rounded p-3 small user-select-all pe-5 cron-command"
                      style="word-break:break-all;"><?= e($job['cron_linux']) ?></code>
                <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2 copy-btn"
                        data-command="<?= e($job['cron_linux']) ?>"
                        title="Copy to clipboard">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        </div>

        <!-- Windows command -->
        <div class="platform-windows <?= $defaultPlatform === 'windows' ? '' : 'd-none' ?>">
            <label class="form-label small fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">
                Task Scheduler command
            </label>
            <div class="position-relative">
                <code class="d-block bg-light border rounded p-3 small user-select-all pe-5 cron-command"
                      style="word-break:break-all;"><?= e($job['cron_windows']) ?></code>
                <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2 copy-btn"
                        data-command="<?= e($job['cron_windows']) ?>"
                        title="Copy to clipboard">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        </div>

        <?php if (!empty($job['note'])): ?>
        <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i><?= $job['note'] ?></p>
        <?php endif; ?>

        <div class="mt-3 pt-3 border-top d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <span class="text-muted small platform-linux <?= $defaultPlatform === 'linux' ? '' : 'd-none' ?>">
                <i class="bi bi-file-text me-1"></i>Log file: <code><?= e($job['log_linux']) ?></code>
            </span>
            <span class="text-muted small platform-windows <?= $defaultPlatform === 'windows' ? '' : 'd-none' ?>">
                <i class="bi bi-file-text me-1"></i>Log file: <code><?= e($job['log_windows']) ?></code>
            </span>
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
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-terminal me-2"></i>
            <span class="platform-linux <?= $defaultPlatform === 'linux' ? '' : 'd-none' ?>">Combined Crontab Block</span>
            <span class="platform-windows <?= $defaultPlatform === 'windows' ? '' : 'd-none' ?>">Combined Task Scheduler Commands</span>
        </h6>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-3 platform-linux <?= $defaultPlatform === 'linux' ? '' : 'd-none' ?>">
            Copy all entries at once and paste into your crontab (run <code>crontab -e</code>):
        </p>
        <p class="text-muted small mb-3 platform-windows <?= $defaultPlatform === 'windows' ? '' : 'd-none' ?>">
            Run all of these in an <strong>elevated</strong> PowerShell or Command Prompt:
        </p>

        <!-- Linux combined -->
        <div class="position-relative platform-linux <?= $defaultPlatform === 'linux' ? '' : 'd-none' ?>">
            <pre class="bg-light border rounded p-3 small mb-0 user-select-all pe-5"
                 id="combinedLinux"
                 style="white-space:pre-wrap;word-break:break-all;"><?= e(implode("\n", array_column($cronJobs, 'cron_linux'))) ?></pre>
            <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2"
                    data-target="combinedLinux" onclick="copyBlock(this)" title="Copy all">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>

        <!-- Windows combined -->
        <div class="position-relative platform-windows <?= $defaultPlatform === 'windows' ? '' : 'd-none' ?>">
            <pre class="bg-light border rounded p-3 small mb-0 user-select-all pe-5"
                 id="combinedWindows"
                 style="white-space:pre-wrap;word-break:break-all;"><?= e(implode("\n", array_column($cronJobs, 'cron_windows'))) ?></pre>
            <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2"
                    data-target="combinedWindows" onclick="copyBlock(this)" title="Copy all">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Platform toggle — flips d-none on every .platform-linux / .platform-windows
// element on the page. Cheap, no per-element state, no framework needed.
document.querySelectorAll('.platform-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const platform = btn.dataset.platform;
        document.querySelectorAll('.platform-tab').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.platform-linux').forEach(function (el) {
            el.classList.toggle('d-none', platform !== 'linux');
        });
        document.querySelectorAll('.platform-windows').forEach(function (el) {
            el.classList.toggle('d-none', platform !== 'windows');
        });
    });
});

document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(btn.dataset.command).then(function () {
            const icon = btn.querySelector('i');
            icon.className = 'bi bi-check text-success';
            setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
        });
    });
});

function copyBlock(btn) {
    const text = document.getElementById(btn.dataset.target).textContent;
    navigator.clipboard.writeText(text).then(function () {
        const icon = btn.querySelector('i');
        icon.className = 'bi bi-check text-success';
        setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
    });
}
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
