<?php
/**
 * Report date-range picker: a preset dropdown (Today, Last 7 days, This month,
 * Year to date, …) alongside from/to inputs that show the resolved window.
 *
 * Drop inside an existing GET <form>. Expects $from, $to, $range in scope.
 * Picking a preset auto-submits; editing a date flips the dropdown to "Custom".
 * The enclosing form keeps its own Apply / Schedule buttons.
 */
$rangeValue = $range ?? 'custom';
?>
<select name="range" class="form-select form-select-sm js-report-range" style="width:auto;" aria-label="Date range preset">
    <?php foreach (reportRangePresets() as $key => $label): ?>
    <option value="<?= e($key) ?>" <?= $rangeValue === $key ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
</select>
<input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:auto;" aria-label="From date">
<span class="text-muted">to</span>
<input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" style="width:auto;" aria-label="To date">
<?php include __DIR__ . '/report-range-script.php'; ?>
