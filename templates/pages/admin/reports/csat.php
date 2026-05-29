<?php
$layout              = 'app';
$pageTitle           = 'Satisfaction Report';
$scheduleReportType  = 'csat';
$scheduleReportTitle = 'CSAT / Satisfaction';
$sidebarItems        = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Satisfaction'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Customer satisfaction survey results</p>
</div>

<?php
$csatMode      = getSetting('csat_mode', 'internal');
$csatExtDash   = trim(getSetting('csat_external_dashboard_url', ''));
?>
<?php if ($csatMode === 'external'): ?>
<div class="alert alert-info d-flex align-items-center justify-content-between">
    <div>
        <i class="bi bi-box-arrow-up-right me-2"></i>
        <strong>External survey mode is active.</strong>
        Ratings and comments are collected by the external survey service, not stored locally.
        The numbers below count surveys <em>sent</em> from this app only.
    </div>
    <?php if ($csatExtDash !== ''): ?>
    <a href="<?= e($csatExtDash) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary text-white ms-3">
        <i class="bi bi-box-arrow-up-right me-1"></i>Open external dashboard
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>


<!-- Date range filter -->
<div class="d-flex align-items-center gap-2 mb-4 text-muted small">
    <i class="bi bi-calendar3"></i>
    Showing data from <strong><?= e($from) ?></strong> to <strong><?= e($to) ?></strong>
    <form class="d-inline-flex align-items-center gap-2 ms-3">
        <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;">
        <span>to</span>
        <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;">
        <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">Apply</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scheduleReportModal">
            <i class="bi bi-calendar-plus me-1"></i>Schedule
        </button>
    </form>
</div>
<?php require ROOT_DIR . '/templates/partials/schedule-report-modal.php'; ?>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-envelope-check"></i>
                </div>
                <div>
                    <div class="text-muted small">Surveys Sent</div>
                    <div class="fs-4 fw-bold"><?= $totalSent ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Responses</div>
                    <div class="fs-4 fw-bold"><?= $totalResponded ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-graph-up"></i>
                </div>
                <div>
                    <div class="text-muted small">Response Rate</div>
                    <div class="fs-4 fw-bold"><?= $responseRate ?>%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div>
                    <div class="text-muted small">Avg Rating</div>
                    <div class="fs-4 fw-bold"><?= $totalResponded > 0 ? $avgRating . ' / 5' : '—' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rating distribution -->
<?php if ($totalResponded > 0): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart me-2"></i>Rating Distribution</h6>
    </div>
    <div class="card-body p-4">
        <?php
        $labels     = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Great', 5 => 'Excellent'];
        $barColors  = [1 => '#ef4444', 2 => '#f97316', 3 => '#eab308', 4 => '#22c55e', 5 => '#10b981'];
        $maxCount   = max($distribution) ?: 1;
        ?>
        <div class="d-flex flex-column gap-2" style="max-width:480px;">
            <?php for ($star = 5; $star >= 1; $star--): ?>
            <div class="d-flex align-items-center gap-3">
                <span style="width:68px; font-size:.8rem; text-align:right; color:#64748b;">
                    <?= $star ?>★ <?= $labels[$star] ?>
                </span>
                <div class="flex-fill bg-light rounded" style="height:22px; position:relative;">
                    <?php $pct = round($distribution[$star] / $maxCount * 100); ?>
                    <div style="width:<?= $pct ?>%; height:100%; background:<?= $barColors[$star] ?>; border-radius:4px; transition:width .3s;"></div>
                </div>
                <span style="width:32px; font-size:.875rem; color:#475569; font-weight:600;">
                    <?= $distribution[$star] ?>
                </span>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Responses table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>Recent Responses</h6>
        <span class="badge bg-secondary"><?= $totalResponded ?></span>
    </div>
    <?php if (empty($responses)): ?>
    <div class="card-body p-4 text-center text-muted">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
        No survey responses in this date range.
        <?php if (getSetting('csat_enabled', '0') !== '1'): ?>
        <div class="mt-2"><a href="/admin/settings/csat">Enable CSAT surveys</a> to start collecting feedback.</div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:80px;">Ticket</th>
                    <th>Subject</th>
                    <th>User</th>
                    <th style="width:100px;">Rating</th>
                    <th>Comment</th>
                    <th style="width:140px;">Responded</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($responses as $r):
                    $stars = (int) $r['rating'];
                    $starColors = [1 => 'text-danger', 2 => 'text-warning', 3 => 'text-warning', 4 => 'text-success', 5 => 'text-success'];
                ?>
                <tr>
                    <td>
                        <a href="/agent/tickets/<?= (int) $r['ticket_id'] ?>" class="text-decoration-none fw-semibold">
                            #<?= (int) $r['ticket_id'] ?>
                        </a>
                    </td>
                    <td class="text-truncate" style="max-width:200px;"><?= e($r['subject']) ?></td>
                    <td><?= e($r['user_name']) ?></td>
                    <td>
                        <span class="<?= $starColors[$stars] ?> fw-semibold">
                            <?= str_repeat('★', $stars) ?><span class="text-muted"><?= str_repeat('★', 5 - $stars) ?></span>
                        </span>
                        <span class="text-muted small ms-1"><?= $stars ?>/5</span>
                    </td>
                    <td class="text-truncate" style="max-width:220px; font-size:.875rem;">
                        <?= $r['comment'] ? e($r['comment']) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="text-muted small"><?= e(date('M j, Y', strtotime($r['responded_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
