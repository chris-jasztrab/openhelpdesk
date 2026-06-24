<?php
/**
 * One-time JS for the report date-range presets. Included by
 * report-date-range.php and any report form that embeds a `.js-report-range`
 * <select> directly. Guarded so it prints only once per request.
 */
if (!empty($GLOBALS['__ldReportRangeScript'])) return;
$GLOBALS['__ldReportRangeScript'] = true;
?>
<script>
(function () {
    // Event-delegated so it covers every report form regardless of include order.
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.form) return;

        // Picking a preset (anything but "Custom") defines from/to server-side.
        if (t.classList && t.classList.contains('js-report-range')) {
            if (t.value !== 'custom') { t.form.submit(); }
            return;
        }

        // Manually editing a date means the user wants a custom window.
        if (t.matches && t.matches('input[type="date"]')) {
            var sel = t.form.querySelector('.js-report-range');
            if (sel && sel !== t) { sel.value = 'custom'; }
        }
    });
})();
</script>
