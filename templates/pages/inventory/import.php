<?php
$layout       = 'app';
$pageTitle    = 'Import – ' . label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory'), 'url' => '/inventory'],
    ['label' => 'Import'],
];
$loc = label('location.singular');
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0 min-w-0"><i class="bi bi-upload me-2 text-muted"></i>Import assets from CSV</h2>
    <a href="/inventory" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i>Asset list</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            Upload a spreadsheet saved as CSV with one asset per row. Any column names work: on the next step you
            match your file's columns to the fields here, then see exactly what will happen before anything is saved.
        </p>

        <div class="alert alert-info d-flex align-items-start" role="alert">
            <i class="bi bi-info-circle-fill me-2 mt-1"></i>
            <div>
                <strong>How rows are handled</strong>
                <ul class="mb-0 mt-1">
                    <li><strong>Model</strong> is the only required column. It is matched by name or model number; a model that is not in the catalog is created when the row also has a <strong>Category</strong> (and its Manufacturer, if given).</li>
                    <li>A blank <strong>Asset tag</strong> gets the next automatic tag.</li>
                    <li>A tag that already exists is <strong>skipped</strong>, or — if you choose it on the next step — that asset is <strong>updated</strong> with the non-blank cells. Export the list as CSV, fill in the gaps, and import it back to fix many assets at once.</li>
                    <li><strong>Status</strong> and <strong><?= e($loc) ?></strong> are matched by name. An unknown status falls back to a default you pick; an unknown <?= e(strtolower($loc)) ?> is left blank and flagged.</li>
                    <li>New suppliers are created. Nothing is checked out by an import.</li>
                </ul>
            </div>
        </div>

        <div class="mb-4 p-3 bg-light rounded border">
            <div class="fw-semibold mb-1"><i class="bi bi-download me-1"></i>Example file</div>
            <p class="text-muted small mb-2">
                Download the example to see the columns and how each kind of row is filled in, then replace its rows with yours.
                The CSV button on the asset list produces the same column names.
            </p>
            <a href="/samples/import-assets-sample.csv" download class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download import-assets-sample.csv
            </a>
            <div class="table-responsive mt-3">
                <table class="table table-sm table-bordered bg-white small mb-0" data-no-resize>
                    <thead class="table-light"><tr><th>Column</th><th>What goes in it</th></tr></thead>
                    <tbody>
                        <?php foreach ([
                            'Asset Tag' => 'Your tag number, or blank for the next automatic one',
                            'Name' => 'Device or host name (optional)',
                            'Serial' => 'Serial number or Dell service tag — needed for warranty lookups',
                            'Model' => 'Required. e.g. ThinkPad T14 Gen 5',
                            'Model No.' => 'Manufacturer part / type number (optional)',
                            'Category' => 'Laptops, Desktops, Monitors… — needed only for a model that is new',
                            'Manufacturer' => 'Lenovo, Dell… — used only for a model that is new',
                            'Status' => 'A status name exactly as it appears in the list filters',
                            $loc => 'A ' . strtolower($loc) . ' name exactly as it appears in the app',
                            'Supplier' => 'Who it was bought from (created if new)',
                            'Order Number' => 'PO or order number',
                            'Purchase Date' => 'YYYY-MM-DD, e.g. 2026-03-15',
                            'Cost' => 'Price of one unit, e.g. 1489.00',
                            'Warranty (months)' => 'e.g. 36',
                            'EOL' => 'Planned end-of-life date, YYYY-MM-DD',
                            'Notes' => 'Anything else',
                        ] as $col => $what): ?>
                        <tr><td class="font-monospace text-nowrap"><?= e($col) ?></td><td><?= e($what) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="/inventory/import" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="mb-4">
                <label for="csv_file" class="form-label fw-semibold">CSV file</label>
                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                <div class="form-text">Up to 10 MB and <?= number_format(AssetImport::MAX_ROWS) ?> rows, with a header row. In Excel: File › Save As › CSV UTF-8.</div>
            </div>
            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-arrow-right me-1"></i>Upload &amp; match columns
            </button>
        </form>
    </div>
</div>
