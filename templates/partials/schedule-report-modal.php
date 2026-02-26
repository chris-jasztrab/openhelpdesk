<?php
/**
 * Reusable "Schedule this report" modal.
 *
 * Expects two variables set by the including page:
 *   string $scheduleReportType   — slug saved to DB (e.g. 'ticket_volume')
 *   string $scheduleReportTitle  — human label used to pre-fill the name field
 */
?>
<!-- Schedule Report Modal -->
<div class="modal fade" id="scheduleReportModal" tabindex="-1" aria-labelledby="scheduleReportModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold" id="scheduleReportModalLabel">
                    <i class="bi bi-calendar-plus me-2 text-primary"></i>Schedule Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin/settings/scheduled-reports/create" id="scheduleReportForm">
                <?= csrfField() ?>
                <input type="hidden" name="report_type" value="<?= e($scheduleReportType) ?>">
                <div class="modal-body pt-3">

                    <!-- Name -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small" for="schedName">Schedule Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="schedName" class="form-control"
                               value="<?= e($scheduleReportTitle) ?> Report" required>
                    </div>

                    <!-- Frequency -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Frequency</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="frequency"
                                       id="schedFreqDaily" value="daily">
                                <label class="form-check-label" for="schedFreqDaily">Daily</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="frequency"
                                       id="schedFreqWeekly" value="weekly" checked>
                                <label class="form-check-label" for="schedFreqWeekly">Weekly</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="frequency"
                                       id="schedFreqMonthly" value="monthly">
                                <label class="form-check-label" for="schedFreqMonthly">Monthly</label>
                            </div>
                        </div>
                    </div>

                    <!-- Send Day (hidden for daily) -->
                    <div class="mb-3" id="schedSendDayRow">
                        <label class="form-label fw-semibold small" for="schedSendDaySelect">Send Day</label>
                        <select name="send_day" id="schedSendDaySelect" class="form-select form-select-sm" style="width:auto;">
                            <!-- Populated by JS -->
                        </select>
                        <div class="form-text">Day of week (weekly) or day of month (monthly) to send.</div>
                    </div>

                    <!-- Date Range -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Report Date Range</label>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small text-nowrap">Previous</span>
                            <input type="number" name="date_range_days" id="schedDateRangeDays"
                                   class="form-control form-control-sm" style="width:80px;"
                                   min="1" max="365" value="30" required>
                            <span class="text-muted small">days</span>
                        </div>
                        <div class="d-flex gap-1 mt-2">
                            <button type="button" class="btn btn-xs btn-outline-secondary sched-preset py-0 px-2" data-days="7">7d</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary sched-preset py-0 px-2" data-days="14">14d</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary sched-preset py-0 px-2 active" data-days="30">30d</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary sched-preset py-0 px-2" data-days="60">60d</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary sched-preset py-0 px-2" data-days="90">90d</button>
                        </div>
                        <div class="form-text">Email will cover this many days leading up to the send date.</div>
                    </div>

                    <!-- Recipients -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small" for="schedRecipients">Recipients <span class="text-danger">*</span></label>
                        <textarea name="recipients" id="schedRecipients" class="form-control form-control-sm" rows="3"
                                  placeholder="manager@example.com&#10;director@example.com" required></textarea>
                        <div class="form-text">One email address per line.</div>
                    </div>

                    <!-- Enabled -->
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled"
                               id="schedEnabled" value="1" checked>
                        <label class="form-check-label small fw-semibold" for="schedEnabled">Enable immediately</label>
                    </div>

                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-calendar-check me-1"></i>Create Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var weekDays  = [
        {v:1,l:'Monday'},{v:2,l:'Tuesday'},{v:3,l:'Wednesday'},
        {v:4,l:'Thursday'},{v:5,l:'Friday'},{v:6,l:'Saturday'},{v:0,l:'Sunday'}
    ];
    var monthDays = [];
    for (var i = 1; i <= 28; i++) { monthDays.push({v:i, l:'Day '+i}); }

    var sel         = document.getElementById('schedSendDaySelect');
    var sendDayRow  = document.getElementById('schedSendDayRow');
    var daysInput   = document.getElementById('schedDateRangeDays');
    var presetBtns  = document.querySelectorAll('.sched-preset');

    function populate(freq) {
        if (freq === 'daily') {
            sendDayRow.style.display = 'none';
            return;
        }
        sendDayRow.style.display = '';
        var opts = freq === 'weekly' ? weekDays : monthDays;
        sel.innerHTML = '';
        opts.forEach(function (o) {
            var opt = document.createElement('option');
            opt.value       = o.v;
            opt.textContent = o.l;
            sel.appendChild(opt);
        });
        if (freq === 'weekly') sel.value = 1; // Monday default
        if (freq === 'monthly') sel.value = 1; // 1st of month default
    }

    document.querySelectorAll('input[name="frequency"]').forEach(function (r) {
        r.addEventListener('change', function () { populate(r.value); });
    });

    presetBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            daysInput.value = btn.dataset.days;
            presetBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
        });
    });

    daysInput.addEventListener('input', function () {
        presetBtns.forEach(function (b) {
            b.classList.toggle('active', b.dataset.days === daysInput.value);
        });
    });

    // Reset modal state on close
    document.getElementById('scheduleReportModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('scheduleReportForm').reset();
        // Re-check weekly radio manually since reset() clears it
        document.getElementById('schedFreqWeekly').checked = true;
        // Re-set recipients and name to defaults (reset already handles this)
        daysInput.value = '30';
        presetBtns.forEach(function (b) {
            b.classList.toggle('active', b.dataset.days === '30');
        });
        populate('weekly');
    });

    // Init
    populate('weekly');
})();
</script>
