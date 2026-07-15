<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Recurring Ticket' : 'New Recurring Ticket';
$sidebarItems = adminSidebar('recurring-tickets');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Recurring Tickets', 'url' => '/admin/recurring-tickets'],
    ['label' => $isEdit ? 'Edit' : 'New'],
];
$action = $isEdit
    ? "/admin/recurring-tickets/{$editing['id']}/edit"
    : '/admin/recurring-tickets/create';

$pickedFreq      = old('frequency',      (string) ($editing['frequency']      ?? 'monthly'));
$pickedInterval  = old('interval_value', (string) ($editing['interval_value'] ?? '1'));
$pickedDow       = old('day_of_week',    (string) ($editing['day_of_week']    ?? ''));
$pickedDom       = old('day_of_month',   (string) ($editing['day_of_month']   ?? '1'));
$pickedMoy       = old('month_of_year',  (string) ($editing['month_of_year']  ?? ''));
$pickedStart     = old('start_date',     (string) ($editing['start_date']     ?? date('Y-m-d')));
$pickedActive    = $isEdit
    ? (int) ($editing['is_active'] ?? 1)
    : (isset($_POST['is_active']) ? (int) !empty($_POST['is_active']) : 1);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Recurring Ticket' : 'New Recurring Ticket' ?></h2>
    <a href="/admin/recurring-tickets" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <!-- Identity -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-arrow-clockwise me-1"></i>Schedule Details
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">
                            Schedule Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= e(old('name', $editing['name'] ?? '')) ?>"
                               placeholder="e.g. Monthly Toner Audit, Quarterly HVAC Inspection" required>
                        <div class="form-text">An internal label shown only on this page — not on the ticket.</div>
                    </div>
                    <div class="mb-0">
                        <label for="description_internal" class="form-label fw-semibold">Internal Notes</label>
                        <textarea class="form-control" id="description_internal" name="description_internal" rows="2"
                                  placeholder="What is this schedule for? Any vendor / SLA context for staff?"><?= e(old('description_internal', $editing['description_internal'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Cadence -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-calendar-event me-1"></i>How often should this ticket be created?
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="frequency" class="form-label fw-semibold">Frequency</label>
                            <select class="form-select" id="frequency" name="frequency">
                                <?php
                                $freqOptions = [
                                    'daily'   => 'Daily',
                                    'weekly'  => 'Weekly',
                                    'monthly' => 'Monthly',
                                    'yearly'  => 'Yearly',
                                    'custom'  => 'Custom (every N days)',
                                ];
                                foreach ($freqOptions as $val => $lbl):
                                ?>
                                    <option value="<?= e($val) ?>" <?= $pickedFreq === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3" id="intervalWrap">
                            <label for="interval_value" class="form-label fw-semibold">
                                Every <span id="intervalUnitLabel">month(s)</span>
                            </label>
                            <input type="number" class="form-control" id="interval_value" name="interval_value"
                                   min="1" max="365" value="<?= e($pickedInterval) ?>">
                            <div class="form-text">e.g. <code>3</code> with <em>Monthly</em> = quarterly.</div>
                        </div>
                        <div class="col-md-5">
                            <label for="start_date" class="form-label fw-semibold">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date"
                                   value="<?= e($pickedStart) ?>">
                            <div class="form-text">First firing happens on/after this date.</div>
                        </div>
                    </div>

                    <!-- Frequency-specific anchors -->
                    <div class="row g-3 mt-1" id="anchorRow">
                        <div class="col-md-4 d-none" id="dowWrap">
                            <label for="day_of_week" class="form-label fw-semibold">Day of Week</label>
                            <select class="form-select" id="day_of_week" name="day_of_week">
                                <option value="">— Any —</option>
                                <?php
                                $dows = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                                foreach ($dows as $i => $name):
                                ?>
                                    <option value="<?= $i ?>" <?= (string) $pickedDow === (string) $i ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-none" id="domWrap">
                            <label for="day_of_month" class="form-label fw-semibold">Day of Month</label>
                            <input type="number" class="form-control" id="day_of_month" name="day_of_month"
                                   min="1" max="31" value="<?= e($pickedDom) ?>">
                            <div class="form-text">Days past month-end clamp to last day (Feb 30 → Feb 28/29).</div>
                        </div>
                        <div class="col-md-4 d-none" id="moyWrap">
                            <label for="month_of_year" class="form-label fw-semibold">Month</label>
                            <select class="form-select" id="month_of_year" name="month_of_year">
                                <option value="">— Any —</option>
                                <?php
                                $months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
                                for ($m = 1; $m <= 12; $m++):
                                ?>
                                    <option value="<?= $m ?>" <?= (string) $pickedMoy === (string) $m ? 'selected' : '' ?>><?= $months[$m] ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-light border mt-3 mb-0" id="cadenceSummary">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        <span id="cadenceSummaryText">…</span>
                    </div>
                </div>
            </div>

            <!-- Ticket payload -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-ticket-detailed me-1"></i>Ticket That Will Be Created
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="subject" class="form-label fw-semibold">
                            Ticket Subject <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="subject" name="subject"
                               value="<?= e(old('subject', $editing['subject'] ?? '')) ?>"
                               placeholder="e.g. Quarterly HVAC inspection — Main Branch" required>
                    </div>
                    <div class="mb-3">
                        <label for="body" class="form-label fw-semibold">
                            Ticket Description <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="body" name="body" rows="6"
                                  placeholder="Checklist or instructions for the assignee. Same body fires on every run." required><?= e(old('body', $editing['body'] ?? '')) ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <label for="type_id" class="form-label fw-semibold">Type</label>
                            <select class="form-select" id="type_id" name="type_id">
                                <option value="">— None —</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>"
                                        <?= (int) old('type_id', (string) ($editing['type_id'] ?? '')) === (int) $t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label for="priority_id" class="form-label fw-semibold">Priority</label>
                            <select class="form-select" id="priority_id" name="priority_id">
                                <option value="">— None —</option>
                                <?php foreach ($priorities as $pri): ?>
                                    <option value="<?= (int) $pri['id'] ?>"
                                        <?= (int) old('priority_id', (string) ($editing['priority_id'] ?? '')) === (int) $pri['id'] ? 'selected' : '' ?>>
                                        <?= e($pri['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label for="location_id" class="form-label fw-semibold">Location</label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">— None —</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= (int) $loc['id'] ?>"
                                        <?= (int) old('location_id', (string) ($editing['location_id'] ?? '')) === (int) $loc['id'] ? 'selected' : '' ?>>
                                        <?= e($loc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label for="group_id" class="form-label fw-semibold">Group</label>
                            <select class="form-select" id="group_id" name="group_id">
                                <option value="">— Auto-route —</option>
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?= (int) $g['id'] ?>"
                                        <?= (int) old('group_id', (string) ($editing['group_id'] ?? '')) === (int) $g['id'] ? 'selected' : '' ?>>
                                        <?= e($g['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Tip: pick <em>Facilities</em> for HVAC, fire-inspection, etc.</div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label for="assigned_to" class="form-label fw-semibold">Assignee</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">— Unassigned (auto-assign) —</option>
                                <?php foreach ($agents as $ag): ?>
                                    <option value="<?= (int) $ag['id'] ?>"
                                        <?= (int) old('assigned_to', (string) ($editing['assigned_to'] ?? '')) === (int) $ag['id'] ? 'selected' : '' ?>>
                                        <?= e($ag['first_name'] . ' ' . $ag['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label for="due_date_offset_days" class="form-label fw-semibold">Due Date</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="due_date_offset_days" name="due_date_offset_days"
                                       min="0" max="365"
                                       value="<?= e(old('due_date_offset_days', (string) ($editing['due_date_offset_days'] ?? ''))) ?>"
                                       placeholder="—">
                                <span class="input-group-text">days after firing</span>
                            </div>
                            <div class="form-text">Leave blank for no due date.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="requester_id" class="form-label fw-semibold">Filed As (Requester)</label>
                            <select class="form-select" id="requester_id" name="requester_id">
                                <option value="">— You (<?= e(Auth::fullName()) ?>) —</option>
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"
                                        <?= (int) old('requester_id', (string) ($editing['requester_id'] ?? '')) === (int) $u['id'] ? 'selected' : '' ?>>
                                        <?= e($u['first_name'] . ' ' . $u['last_name']) ?> &lt;<?= e($u['email']) ?>&gt;
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Who the auto-created ticket appears to be from. Defaults to you.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active toggle -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                               value="1" <?= $pickedActive ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="isActive">
                            Active
                        </label>
                    </div>
                    <div class="form-text mt-1">
                        When off, the schedule is paused — no tickets fire on cadence. You can still <em>Run now</em> from the list.
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Schedule' : 'Create Schedule' ?>
                </button>
                <a href="/admin/recurring-tickets" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var freq    = document.getElementById('frequency');
    var dowWrap = document.getElementById('dowWrap');
    var domWrap = document.getElementById('domWrap');
    var moyWrap = document.getElementById('moyWrap');
    var unitLbl = document.getElementById('intervalUnitLabel');
    var interval = document.getElementById('interval_value');
    var dow     = document.getElementById('day_of_week');
    var dom     = document.getElementById('day_of_month');
    var moy     = document.getElementById('month_of_year');
    var summary = document.getElementById('cadenceSummaryText');

    var monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
    var dowNames   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    function ord(n) {
        n = parseInt(n, 10);
        if (n >= 11 && n <= 13) return n + 'th';
        switch (n % 10) { case 1: return n + 'st'; case 2: return n + 'nd'; case 3: return n + 'rd'; default: return n + 'th'; }
    }

    function refresh() {
        var f = freq.value;
        dowWrap.classList.toggle('d-none', f !== 'weekly');
        domWrap.classList.toggle('d-none', !(f === 'monthly' || f === 'yearly'));
        moyWrap.classList.toggle('d-none', f !== 'yearly');

        var unitMap = { daily: 'day(s)', weekly: 'week(s)', monthly: 'month(s)', yearly: 'year(s)', custom: 'day(s)' };
        unitLbl.textContent = unitMap[f] || 'unit(s)';

        var n = parseInt(interval.value, 10) || 1;
        var unit = unitMap[f] || 'unit(s)';
        var pretty = (n === 1)
            ? ({ daily: 'Every day', weekly: 'Every week', monthly: 'Every month', yearly: 'Every year', custom: 'Every day' }[f])
            : ('Every ' + n + ' ' + unit.replace('(s)', 's'));

        var extra = '';
        if (f === 'weekly' && dow.value !== '') {
            extra = ' on ' + dowNames[parseInt(dow.value, 10)];
        } else if (f === 'monthly' && dom.value !== '') {
            extra = ' on the ' + ord(dom.value);
        } else if (f === 'yearly') {
            if (moy.value !== '' && dom.value !== '') {
                extra = ' on ' + monthNames[parseInt(moy.value, 10)] + ' ' + ord(dom.value);
            } else if (moy.value !== '') {
                extra = ' in ' + monthNames[parseInt(moy.value, 10)];
            }
        }
        summary.textContent = pretty + extra + '.';
    }

    [freq, interval, dow, dom, moy].forEach(function (el) {
        el.addEventListener('change', refresh);
        el.addEventListener('input',  refresh);
    });
    refresh();
})();
</script>

<?php require ROOT_DIR . '/templates/partials/type-priority-filter.php'; ?>
