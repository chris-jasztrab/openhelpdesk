<?php
$layout       = 'app';
$pageTitle    = 'Cron Jobs';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Cron Jobs'],
];

// The job list, the crontab/schtasks commands and the status check all live in
// src/CronJobs.php — the run endpoint needs the same data, and two copies of it
// would drift apart.
$cronJobs = CronJobs::all();

// Detected platform — drives the default-selected tab. Both platforms'
// commands are always rendered; the tab only controls which one is visible.
$isWindows       = stripos(PHP_OS, 'WIN') === 0;
$defaultPlatform = $isWindows ? 'windows' : 'linux';

// Can this server spawn a job at all? Drives whether the run buttons are live
// or disabled-with-an-explanation.
$runnable = CronJobs::runnability();

// Worth saying out loud on the confirmation dialogs: a live run of a
// mail-sending job won't actually mail anyone while the kill switch is off.
$mailDisabled = env('MAIL_ENABLED', 'true') === 'false';

$fmtAgo = static function (int $seconds): string {
    if ($seconds < 60)    return $seconds . 's ago';
    if ($seconds < 3600)  return (int) floor($seconds / 60)   . 'm ago';
    if ($seconds < 86400) return (int) floor($seconds / 3600) . 'h ago';
    return (int) floor($seconds / 86400) . 'd ago';
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
        Choose your platform below to see the matching commands &mdash; <strong>Linux/macOS</strong> uses
        <code>crontab</code> and <strong>Windows</strong> uses <code>schtasks</code> (Task Scheduler).
        You can also run any job right now with the buttons on each card.
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
    $summary[CronJobs::status($j)['status']]++;
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

<div class="alert alert-secondary d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-play-circle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div class="small">
        <strong>Running a job by hand.</strong>
        <strong>Dry run</strong> executes the job for real but throws the result away: every database change is rolled
        back when it finishes and every email is listed instead of sent. It's safe to use at any time, and it's the
        way to see what a job <em>would</em> do before letting it near real recipients.
        <strong>Run for real</strong> is the genuine thing &mdash; it sends email, creates tickets, and reassigns work
        exactly as the scheduled run would.
        Either way a job can't run twice at once: if the scheduled copy is mid-flight, your run steps aside and says so.
        <?php if (!$runnable['ok']): ?>
        <div class="mt-2 text-danger">
            <i class="bi bi-x-circle me-1"></i><strong>Not available on this server:</strong> <?= e($runnable['reason']) ?>
        </div>
        <?php elseif ($mailDisabled): ?>
        <div class="mt-2">
            <i class="bi bi-envelope-slash me-1"></i>Outbound mail is currently switched off on this server
            (<code>MAIL_ENABLED=false</code>), so even a real run won't deliver email.
        </div>
        <?php endif; ?>
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

<?php foreach ($cronJobs as $key => $job): ?>
    <?php
    $st     = CronJobs::status($job);
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
            <div class="d-flex flex-column gap-1">
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

            <!-- Run controls. Dry run is the plain click; the real thing is one
                 level down in the dropdown and always confirms first. -->
            <div class="btn-group btn-group-sm flex-shrink-0" role="group"
                 <?= $runnable['ok'] ? '' : 'title="' . e($runnable['reason']) . '"' ?>>
                <button type="button"
                        class="btn btn-outline-primary cron-run-btn"
                        data-key="<?= e($key) ?>"
                        data-mode="dry"
                        <?= $runnable['ok'] ? '' : 'disabled' ?>>
                    <i class="bi bi-eyeglasses me-1"></i>Dry run
                </button>
                <button type="button"
                        class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        <?= $runnable['ok'] ? '' : 'disabled' ?>>
                    <span class="visually-hidden">More run options</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <button type="button" class="dropdown-item text-danger cron-confirm-btn"
                                data-key="<?= e($key) ?>"
                                data-title="<?= e($job['title']) ?>"
                                data-mail="<?= $job['sends_mail'] ? '1' : '0' ?>">
                            <i class="bi bi-exclamation-triangle me-1"></i>Run for real&hellip;
                        </button>
                    </li>
                </ul>
            </div>
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

<!-- Confirmation before a live run -->
<div class="modal fade" id="cronConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Run this job for real?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    <strong id="cronConfirmTitle"></strong> will run immediately with full effect &mdash; the same as its
                    scheduled run. Database changes are kept.
                </p>
                <div id="cronConfirmMail" class="d-none">
                    <div class="alert alert-danger small">
                        <i class="bi bi-envelope-exclamation me-1"></i>
                        <strong>This job sends email to real people.</strong>
                        <?php if ($mailDisabled): ?>
                            Outbound mail is currently switched off on this server, so nothing will actually be
                            delivered &mdash; but that safety net disappears the moment mail is re-enabled.
                        <?php else: ?>
                            Outbound mail is <strong>enabled</strong> on this server. Recipients will receive it.
                        <?php endif; ?>
                        If you haven't dry-run it yet, cancel and do that first.
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="cronConfirmAck">
                        <label class="form-check-label small" for="cronConfirmAck">
                            I understand this may email real recipients.
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="cronConfirmGo">
                    <i class="bi bi-play-fill me-1"></i>Run it for real
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Run output -->
<div class="modal fade" id="cronOutputModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cronOutputTitle">Run output</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cronOutputStatus" class="mb-3"></div>
                <pre id="cronOutputBody" class="bg-light border rounded p-3 small mb-0"
                     style="white-space:pre-wrap;word-break:break-word;max-height:50vh;overflow:auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary d-none" id="cronOutputReload">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh page
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
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

/* ── Run a job now ────────────────────────────────────────────────────
   Dry run posts straight through. A live run goes via the confirm modal,
   which for mail-sending jobs also needs the acknowledgement ticked.

   Waits for DOMContentLoaded: the layout loads bootstrap.bundle.min.js at the
   end of <body>, after this block, so `bootstrap` isn't defined yet at parse
   time and constructing the modals here would throw. */
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken   = document.querySelector('meta[name="csrf-token"]').content;
    const confirmEl   = document.getElementById('cronConfirmModal');
    const outputEl    = document.getElementById('cronOutputModal');
    const confirmModal = new bootstrap.Modal(confirmEl);
    const outputModal  = new bootstrap.Modal(outputEl);

    const ackWrap   = document.getElementById('cronConfirmMail');
    const ackBox    = document.getElementById('cronConfirmAck');
    const goBtn     = document.getElementById('cronConfirmGo');
    const reloadBtn = document.getElementById('cronOutputReload');

    let pending = null; // { key, button }

    reloadBtn.addEventListener('click', function () { window.location.reload(); });

    document.querySelectorAll('.cron-run-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { run(btn.dataset.key, 'dry', btn); });
    });

    document.querySelectorAll('.cron-confirm-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const sendsMail = btn.dataset.mail === '1';
            pending = { key: btn.dataset.key, button: btn };
            document.getElementById('cronConfirmTitle').textContent = btn.dataset.title;
            ackWrap.classList.toggle('d-none', !sendsMail);
            ackBox.checked = false;
            goBtn.disabled = sendsMail;
            confirmModal.show();
        });
    });

    ackBox.addEventListener('change', function () { goBtn.disabled = !ackBox.checked; });

    goBtn.addEventListener('click', function () {
        if (!pending) return;
        confirmModal.hide();
        run(pending.key, 'live', pending.button);
        pending = null;
    });

    function run(key, mode, btn) {
        // The request blocks for as long as the job takes, so lock the button
        // and say what's happening rather than leaving the page looking idle.
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Running…';

        fetch('/admin/settings/cron-jobs/run', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ key: key, mode: mode })
        })
            .then(function (r) { return r.json(); })
            .then(function (j) { showResult(j, mode); })
            .catch(function () {
                showResult({ success: false, error: 'The request failed before the job reported back. Check the job\'s log file on the server.' }, mode);
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = original;
            });
    }

    function showResult(j, mode) {
        const isLive = mode === 'live';
        document.getElementById('cronOutputTitle').textContent =
            (j.title ? j.title + ' — ' : '') + (isLive ? 'real run' : 'dry run');

        let status;
        if (j.error) {
            status = '<div class="alert alert-danger mb-0 small"><i class="bi bi-x-circle me-1"></i>' + escapeHtml(j.error) + '</div>';
        } else if (j.already_running) {
            status = '<div class="alert alert-warning mb-0 small"><i class="bi bi-hourglass-split me-1"></i>'
                   + 'This job is already running (most likely its scheduled copy). Nothing was run — try again in a moment.</div>';
        } else if (j.timed_out) {
            status = '<div class="alert alert-danger mb-0 small"><i class="bi bi-stopwatch me-1"></i>'
                   + 'The job exceeded its time limit after ' + j.duration + 's and was stopped. '
                   + (isLive ? 'It may have completed part of its work — check the log file.' : 'Nothing was saved.')
                   + '</div>';
        } else if (j.success) {
            status = '<div class="alert alert-success mb-0 small"><i class="bi bi-check-circle me-1"></i>'
                   + 'Finished in ' + j.duration + 's. '
                   + (isLive ? 'Changes were saved.' : 'Nothing was saved and no email was sent.')
                   + '</div>';
        } else {
            status = '<div class="alert alert-warning mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i>'
                   + 'The job exited with code ' + j.exit_code + ' after ' + j.duration + 's. See the output below.</div>';
        }

        if (j.truncated) {
            status += '<div class="text-muted small mt-2">Output was long and has been truncated — see the log file for the rest.</div>';
        }

        document.getElementById('cronOutputStatus').innerHTML = status;
        document.getElementById('cronOutputBody').textContent = (j.output && j.output.trim() !== '')
            ? j.output
            : '(the job produced no output)';

        // Only a real run changes the badges/last-run times on this page.
        reloadBtn.classList.toggle('d-none', !(isLive && !j.error));

        outputModal.show();
    }

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
