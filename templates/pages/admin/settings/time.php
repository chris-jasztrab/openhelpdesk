<?php
$layout       = 'app';
$pageTitle    = 'Server Time – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Server Time'],
];

$php    = $diag['php'];
$cli    = $diag['php_cli'];
$mysql  = $diag['mysql'];
$os     = $diag['os'];
$app    = $diag['app'];
$sum    = $diag['summary'];

$statusStyles = [
    'ok'   => ['success',   'bi-check-circle-fill',       'Pass'],
    'warn' => ['warning',   'bi-exclamation-triangle-fill','Check'],
    'fail' => ['danger',    'bi-x-octagon-fill',          'Problem'],
    'info' => ['secondary', 'bi-info-circle-fill',        'Info'],
];

$bannerStyles = [
    'ok'   => ['success', 'bi-check-circle-fill'],
    'warn' => ['warning', 'bi-exclamation-triangle-fill'],
    'fail' => ['danger',  'bi-x-octagon-fill'],
];
[$bannerColour, $bannerIcon] = $bannerStyles[$sum['status']];

$fmt = fn (?int $s) => TimeDiagnostics::formatOffset($s);
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Server Time</h5>
        <a href="/admin/settings/time" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-clockwise me-1"></i>Re-check
        </a>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            Time is configured in several places independently &mdash; the operating system, the
            database, and PHP (which reads a separate setting for the website and for scheduled
            jobs). When they disagree, nothing reports an error; timestamps are simply written
            wrong by a fixed amount. This page reads all of them so you can see at a glance
            whether they line up. Nothing here changes any setting.
        </p>

        <div class="alert alert-<?= e($bannerColour) ?> d-flex align-items-start" role="alert">
            <i class="bi <?= e($bannerIcon) ?> me-2 mt-1 fs-5"></i>
            <div>
                <strong class="fs-6"><?= e($sum['headline']) ?></strong>
                <?php if ($sum['status'] === 'ok'): ?>
                    <div class="small mt-1">Every clock the application depends on reports the same time and timezone.</div>
                <?php else: ?>
                    <div class="small mt-1">See the checks below &mdash; each one explains what it found and how to fix it.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Checks ────────────────────────────────────────────────── -->
        <h6 class="fw-semibold mt-4 mb-3">Checks</h6>
        <div class="list-group mb-4">
            <?php foreach ($diag['checks'] as $check): ?>
                <?php [$colour, $icon, $word] = $statusStyles[$check['status']] ?? $statusStyles['info']; ?>
                <div class="list-group-item">
                    <div class="d-flex align-items-start">
                        <i class="bi <?= e($icon) ?> text-<?= e($colour) ?> me-2 mt-1"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold"><?= e($check['label']) ?></span>
                                <span class="badge bg-<?= e($colour) ?>"><?= e($word) ?></span>
                            </div>
                            <div class="text-muted small mt-1"><?= e($check['detail']) ?></div>
                            <?php if ($check['fix'] !== ''): ?>
                                <div class="small mt-2">
                                    <span class="fw-semibold">How to fix:</span>
                                    <code class="user-select-all"><?= e($check['fix']) ?></code>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Raw readings ──────────────────────────────────────────── -->
        <h6 class="fw-semibold mt-4 mb-3">What each clock reports</h6>
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:210px;">Source</th>
                        <th style="width:190px;">Timezone</th>
                        <th style="width:100px;">UTC offset</th>
                        <th>Current time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="fw-semibold">Website (PHP)</div>
                            <div class="text-muted small">SAPI <?= e($php['sapi']) ?> &middot; PHP <?= e($php['version']) ?></div>
                        </td>
                        <td>
                            <span class="font-monospace"><?= e($php['timezone']) ?></span>
                            <?php if ($php['ini_value'] === ''): ?>
                                <div class="text-warning small">date.timezone not set</div>
                            <?php endif; ?>
                        </td>
                        <td class="font-monospace"><?= e($fmt($php['offset'])) ?></td>
                        <td class="font-monospace"><?= e($php['now']) ?></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-semibold">Scheduled jobs (PHP CLI)</div>
                            <div class="text-muted small">Cron and migrations</div>
                        </td>
                        <?php if ($cli['available']): ?>
                            <td class="font-monospace"><?= e((string) $cli['timezone']) ?></td>
                            <td class="font-monospace"><?= e($fmt($cli['offset'])) ?></td>
                            <td class="text-muted small">Reads its own php.ini</td>
                        <?php else: ?>
                            <td colspan="3" class="text-muted small"><?= e($cli['reason']) ?></td>
                        <?php endif; ?>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-semibold">Database (MySQL)</div>
                            <div class="text-muted small"><?= e($mysql['version']) ?></div>
                        </td>
                        <td>
                            <span class="font-monospace"><?= e($mysql['session_tz'] !== '' ? $mysql['session_tz'] : '—') ?></span>
                            <?php if (strtoupper($mysql['session_tz']) === 'SYSTEM' && $mysql['system_tz'] !== ''): ?>
                                <div class="text-muted small">follows host: <?= e($mysql['system_tz']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="font-monospace"><?= e($fmt($mysql['offset'])) ?></td>
                        <td class="font-monospace"><?= e($mysql['now'] !== '' ? $mysql['now'] : '—') ?></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-semibold">Operating system</div>
                            <div class="text-muted small">
                                <?= $os['available'] ? e($os['source']) : 'Not readable' ?>
                            </div>
                        </td>
                        <?php if ($os['available']): ?>
                            <td class="font-monospace" colspan="3"><?= e((string) $os['timezone']) ?></td>
                        <?php else: ?>
                            <td colspan="3" class="text-muted small">Could not determine the host timezone on this platform.</td>
                        <?php endif; ?>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ── UTC cross-check ───────────────────────────────────────── -->
        <h6 class="fw-semibold mt-4 mb-3">The same moment, in UTC</h6>
        <p class="text-muted small">
            Stripped of timezones, these two should be within a second of each other. If they
            are not, one of the machines has the wrong time &mdash; a clock problem rather than a
            configuration one.
        </p>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered mb-0" style="max-width:520px;">
                <tbody>
                    <tr>
                        <th class="table-light" style="width:220px;">Website (PHP) in UTC</th>
                        <td class="font-monospace"><?= e($php['utc']) ?></td>
                    </tr>
                    <tr>
                        <th class="table-light">Database (MySQL) in UTC</th>
                        <td class="font-monospace"><?= e($mysql['utc'] !== '' ? $mysql['utc'] : '—') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ── Application settings ──────────────────────────────────── -->
        <h6 class="fw-semibold mt-4 mb-3">Timezones the application is configured with</h6>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <tbody>
                    <tr>
                        <th class="table-light" style="width:260px;">Business Hours</th>
                        <td>
                            <span class="font-monospace"><?= e($app['business_hours_timezone'] !== '' ? $app['business_hours_timezone'] : 'Not configured') ?></span>
                            <div class="text-muted small">
                                Decides which hours SLA timers count. Safe to differ from the server &mdash;
                                due dates are stored in server time either way.
                                <a href="/admin/settings/business-hours">Change</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th class="table-light">Imported ticket timestamps</th>
                        <td>
                            <?php if ($app['import_source_timezone'] !== null): ?>
                                <span class="font-monospace"><?= e($app['import_source_timezone']) ?></span>
                            <?php else: ?>
                                <span class="font-monospace">Per <?= e(label('location.singular')) ?></span>
                            <?php endif; ?>
                            <div class="text-muted small">
                                The timezone a CSV import assumes its dates were written in. If your export
                                includes UTC offsets (for example <code>2022-08-22T12:31:35-04:00</code>) this
                                setting is ignored and the offset in the file is used instead.
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
