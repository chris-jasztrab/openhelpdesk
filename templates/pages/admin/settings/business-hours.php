<?php
$layout       = 'app';
$pageTitle    = 'Business Hours – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Business Hours'],
];

$days = [
    'mon' => 'Monday',
    'tue' => 'Tuesday',
    'wed' => 'Wednesday',
    'thu' => 'Thursday',
    'fri' => 'Friday',
    'sat' => 'Saturday',
    'sun' => 'Sunday',
];

$commonTimezones = [
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Toronto',
    'America/Vancouver',
    'America/Winnipeg',
    'America/Halifax',
    'America/St_Johns',
    'America/Edmonton',
    'America/Regina',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Amsterdam',
    'Australia/Sydney',
    'Australia/Melbourne',
    'Asia/Tokyo',
    'Asia/Shanghai',
    'Asia/Kolkata',
    'Pacific/Auckland',
    'UTC',
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm" style="max-width:700px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-1">Business Hours</h5>
        <p class="text-muted mb-4">Configure your organization's working hours. SLA timers only count time during business hours.</p>

        <form method="POST" action="/admin/settings/business-hours">
            <?= csrfField() ?>

            <div class="mb-4">
                <label for="timezone" class="form-label fw-semibold">Timezone</label>
                <select class="form-select" name="timezone" id="timezone">
                    <option value="">Select timezone...</option>
                    <?php foreach ($commonTimezones as $tz): ?>
                    <option value="<?= e($tz) ?>" <?= $timezone === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Weekly Schedule</label>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:140px">Day</th>
                                <th style="width:60px">Active</th>
                                <th>Start</th>
                                <th>End</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $key => $label): ?>
                            <?php
                            $dayData = $schedule[$key] ?? null;
                            $isActive = is_array($dayData) && count($dayData) >= 2;
                            $startVal = $isActive ? $dayData[0] : '09:00';
                            $endVal = $isActive ? $dayData[1] : '17:00';
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= e($label) ?></td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input day-toggle" type="checkbox"
                                               name="days[<?= $key ?>][active]" value="1"
                                               id="day_<?= $key ?>" <?= $isActive ? 'checked' : '' ?>
                                               data-day="<?= $key ?>">
                                    </div>
                                </td>
                                <td>
                                    <input type="time" class="form-control form-control-sm"
                                           name="days[<?= $key ?>][start]" value="<?= e($startVal) ?>"
                                           id="start_<?= $key ?>" <?= !$isActive ? 'disabled' : '' ?>>
                                </td>
                                <td>
                                    <input type="time" class="form-control form-control-sm"
                                           name="days[<?= $key ?>][end]" value="<?= e($endVal) ?>"
                                           id="end_<?= $key ?>" <?= !$isActive ? 'disabled' : '' ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-4 text-muted small">
                <i class="bi bi-calendar-x me-1"></i>
                Need to mark specific days as closed?
                <a href="/admin/settings/holidays" class="text-decoration-none fw-semibold">Configure Holidays</a>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Business Hours
            </button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.day-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        var day = this.dataset.day;
        document.getElementById('start_' + day).disabled = !this.checked;
        document.getElementById('end_' + day).disabled = !this.checked;
    });
});
</script>
