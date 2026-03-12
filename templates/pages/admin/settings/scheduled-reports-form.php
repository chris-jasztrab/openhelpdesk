<?php
$layout       = 'app';
$isEdit       = $report !== null;
$pageTitle    = $isEdit ? 'Edit Scheduled Report' : 'New Scheduled Report';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',              'url' => '/admin'],
    ['label' => 'Settings',           'url' => '/admin/settings'],
    ['label' => 'Scheduled Reports',  'url' => '/admin/settings/scheduled-reports'],
    ['label' => $isEdit ? 'Edit' : 'New'],
];
$action = $isEdit
    ? '/admin/settings/scheduled-reports/' . (int)$report['id'] . '/edit'
    : '/admin/settings/scheduled-reports/create';

$recipients      = $isEdit ? implode("\n", json_decode($report['recipients'], true) ?: []) : '';
$dateRangeDays   = (int) ($report['date_range_days'] ?? 30);
$allReportTypes  = [
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

<div class="mb-4">
    <h5 class="fw-semibold mb-0">
        <i class="bi bi-envelope-paper me-2"></i><?= $pageTitle ?>
    </h5>
</div>

<form method="POST" action="<?= e($action) ?>">
    <?= csrfField() ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <!-- Name -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Schedule Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= e($report['name'] ?? '') ?>"
                           placeholder="e.g. Weekly Manager Summary" required>
                </div>

                <!-- Report Type -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Report Type</label>
                    <select name="report_type" class="form-select">
                        <?php foreach ($allReportTypes as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($report['report_type'] ?? 'overview') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Which report's summary data to include in the email.</div>
                </div>

                <!-- Enabled -->
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="is_enabled" id="isEnabled" value="1"
                               <?= ($report['is_enabled'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="isEnabled">Enabled</label>
                    </div>
                </div>

                <!-- Frequency -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Frequency</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="frequency" id="freqDaily"
                                   value="daily" <?= ($report['frequency'] ?? '') === 'daily' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="freqDaily">Daily</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="frequency" id="freqWeekly"
                                   value="weekly" <?= ($report['frequency'] ?? 'weekly') === 'weekly' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="freqWeekly">Weekly</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="frequency" id="freqMonthly"
                                   value="monthly" <?= ($report['frequency'] ?? '') === 'monthly' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="freqMonthly">Monthly</label>
                        </div>
                    </div>
                </div>

                <!-- Send Day (hidden for daily) -->
                <div class="col-md-6" id="sendDayCol">
                    <label class="form-label fw-semibold" for="sendDaySelect">Send Day</label>
                    <select name="send_day" id="sendDaySelect" class="form-select">
                        <!-- Options populated/swapped by JS based on frequency -->
                    </select>
                    <div class="form-text">Day of week (weekly) or day of month (monthly) to send.</div>
                </div>

                <!-- Date Range -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Report Date Range</label>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small text-nowrap">Previous</span>
                        <input type="number" name="date_range_days" id="dateRangeDays"
                               class="form-control form-control-sm" style="width:90px;"
                               min="1" max="365" value="<?= $dateRangeDays ?>" required>
                        <span class="text-muted small">days</span>
                    </div>
                    <div class="d-flex gap-1 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary day-preset" data-days="7">7 days</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary day-preset" data-days="14">14 days</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary day-preset" data-days="30">30 days</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary day-preset" data-days="60">60 days</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary day-preset" data-days="90">90 days</button>
                    </div>
                    <div class="form-text">The email will summarize data from this many days leading up to the send date.</div>
                </div>

                <!-- Recipients -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Recipients <span class="text-danger">*</span></label>
                    <textarea name="recipients" class="form-control" rows="4"
                              placeholder="one@example.com&#10;two@example.com"
                              required><?= e($recipients) ?></textarea>
                    <div class="form-text">Enter one email address per line.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
            <?= $isEdit ? 'Save Changes' : 'Create Schedule' ?>
        </button>
        <a href="/admin/settings/scheduled-reports" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
(function () {
    const weekDays  = [{v:1,l:'Monday'},{v:2,l:'Tuesday'},{v:3,l:'Wednesday'},
                       {v:4,l:'Thursday'},{v:5,l:'Friday'},{v:6,l:'Saturday'},{v:0,l:'Sunday'}];
    const monthDays = Array.from({length:28}, (_,i) => ({v:i+1, l:'Day '+(i+1)}));
    const sel        = document.getElementById('sendDaySelect');
    const sendDayCol = document.getElementById('sendDayCol');
    const savedDay   = <?= (int)($report['send_day'] ?? 1) ?>;
    const savedFreq  = <?= json_encode($report['frequency'] ?? 'weekly') ?>;

    function populate(freq) {
        if (freq === 'daily') {
            sendDayCol.style.display = 'none';
            return;
        }
        sendDayCol.style.display = '';
        const opts = freq === 'weekly' ? weekDays : monthDays;
        sel.innerHTML = '';
        opts.forEach(o => {
            const opt = document.createElement('option');
            opt.value = o.v;
            opt.textContent = o.l;
            if (o.v === savedDay && freq === savedFreq) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    document.querySelectorAll('input[name="frequency"]').forEach(r => {
        r.addEventListener('change', () => populate(r.value));
    });

    // Date range preset buttons
    const daysInput = document.getElementById('dateRangeDays');
    document.querySelectorAll('.day-preset').forEach(btn => {
        if (parseInt(btn.dataset.days) === <?= $dateRangeDays ?>) btn.classList.add('active');
        btn.addEventListener('click', () => {
            daysInput.value = btn.dataset.days;
            document.querySelectorAll('.day-preset').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });
    daysInput.addEventListener('input', () => {
        document.querySelectorAll('.day-preset').forEach(b => {
            b.classList.toggle('active', b.dataset.days === daysInput.value);
        });
    });

    // Init
    populate(savedFreq);
})();
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
