<?php
/**
 * "Export CSV" button for report pages.
 *
 * Re-requests the page's own URL with export=csv appended, so the download is
 * always exactly what the filters on screen resolved to — no separate export
 * route to keep in sync, and no second permission gate to get wrong.
 *
 * Only include this on routes that actually handle ?export=csv (grep
 * reportCsvOut in src/routes/admin.php); elsewhere it would 200 with the HTML
 * page and hand the user a .csv full of markup.
 */
?>
<a href="<?= e(reportExportCsvUrl()) ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-download me-1"></i>Export CSV
</a>
