<?php

/**
 * Inventory › Import: add (or update) assets from a CSV file in three steps —
 * upload, match the file's columns to fields, preview, confirm. Same shape as
 * Settings › Import Users. The uploaded rows and the chosen mapping wait in
 * the session between steps; AssetImport::plan() is re-run at confirm so the
 * import always matches the database as it is then.
 *
 * Required before routes/inventory.php, whose /inventory/{id} would otherwise
 * take "import" for an asset id.
 */

require_once ROOT_DIR . '/src/AssetImport.php';

/** The upload waiting in the session, or back to step 1. */
function assetImportPending(bool $needMapping = false): array
{
    $p = $_SESSION['asset_import'] ?? null;
    if (!is_array($p) || empty($p['rows']) || ($needMapping && empty($p['map']))) {
        flash('error', 'No import in progress — upload a CSV file first.');
        redirect('/inventory/import');
    }
    return $p;
}

$router->get('/inventory/import', function () {
    Auth::requirePermission('assets.manage');
    render('inventory/import', ['fields' => AssetImport::fields()]);
});

$router->post('/inventory/import', function () {
    Auth::requirePermission('assets.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/inventory/import');
    }
    $f = $_FILES['csv_file'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Choose a CSV file to upload.');
        redirect('/inventory/import');
    }
    if ((int) $f['size'] > 10 * 1024 * 1024) {
        flash('error', 'File is too large. Maximum 10 MB.');
        redirect('/inventory/import');
    }
    $parsed = AssetImport::parse($f['tmp_name']);
    if (is_string($parsed)) {
        flash('error', $parsed);
        redirect('/inventory/import');
    }
    $_SESSION['asset_import'] = ['file' => (string) $f['name'], 'headers' => $parsed[0], 'rows' => $parsed[1]];
    redirect('/inventory/import/map');
});

$router->get('/inventory/import/map', function () {
    Auth::requirePermission('assets.manage');
    $p  = assetImportPending();
    $db = Database::connect();
    // First offer for the default status: the first "ready to deploy" kind, not whatever sorts first.
    $deployable = (int) $db->query("SELECT id FROM asset_statuses ORDER BY kind = 'deployable' DESC, sort_order, name LIMIT 1")->fetchColumn();
    render('inventory/import-map', [
        'file'       => $p['file'],
        'headers'    => $p['headers'],
        'rowCount'   => count($p['rows']),
        'fields'     => AssetImport::fields(),
        'mapping'    => $p['map'] ?? AssetImport::autoMap($p['headers']),
        'sampleRows' => array_slice($p['rows'], 0, 3),
        'statuses'   => $db->query('SELECT id, name, kind FROM asset_statuses ORDER BY sort_order, name')->fetchAll(),
        'opts'       => $p['opts'] ?? ['default_status_id' => $deployable, 'existing' => 'skip'],
    ]);
});

$router->post('/inventory/import/map', function () {
    Auth::requirePermission('assets.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/inventory/import');
    }
    $p   = assetImportPending();
    $map = [];
    foreach (array_keys(AssetImport::fields()) as $key) {
        $col       = (string) (((array) ($_POST['mapping'] ?? []))[$key] ?? '');
        $map[$key] = in_array($col, $p['headers'], true) ? $col : '';
    }
    $opts = [
        'default_status_id' => (int) ($_POST['default_status_id'] ?? 0),
        'existing'          => ($_POST['existing'] ?? '') === 'update' ? 'update' : 'skip',
    ];
    $_SESSION['asset_import']['map']  = $map;
    $_SESSION['asset_import']['opts'] = $opts;

    $s = Database::connect()->prepare('SELECT id FROM asset_statuses WHERE id = ?');
    $s->execute([$opts['default_status_id']]);
    if ($s->fetchColumn() === false) {
        flash('error', 'Choose the status new assets get when the file does not say.');
        redirect('/inventory/import/map');
    }
    if ($map['model'] === '' && $map['model_number'] === '' && !($opts['existing'] === 'update' && $map['asset_tag'] !== '')) {
        flash('error', 'Model is required — match it to a column in your file.');
        redirect('/inventory/import/map');
    }
    redirect('/inventory/import/preview');
});

$router->get('/inventory/import/preview', function () {
    Auth::requirePermission('assets.manage');
    $p    = assetImportPending(true);
    $plan = AssetImport::plan(Database::connect(), $p['rows'], $p['map'], $p['opts']);
    render('inventory/import-preview', [
        'file'     => $p['file'],
        'plan'     => $plan,
        'opts'     => $p['opts'],
        'problems' => array_values(array_filter($plan['rows'], static fn($r) => $r['action'] === 'error' || ($r['warn'] !== [] && $r['action'] !== 'skip'))),
    ]);
});

$router->post('/inventory/import/confirm', function () {
    Auth::requirePermission('assets.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/inventory/import');
    }
    $p  = assetImportPending(true);
    $db = Database::connect();
    @set_time_limit(300);
    $plan = AssetImport::plan($db, $p['rows'], $p['map'], $p['opts']);
    $db->beginTransaction();
    try {
        [$created, $updated] = AssetImport::apply($db, $plan, Auth::id());
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        flash('error', 'Import failed and nothing was changed: ' . $e->getMessage());
        redirect('/inventory/import/preview');
    }
    unset($_SESSION['asset_import']);
    logAudit('asset.import_confirmed', null, 'asset', 'file=' . $p['file'] . '; created=' . $created . '; updated=' . $updated
        . '; skipped=' . $plan['counts']['skip'] . '; errors=' . $plan['counts']['error']);
    $left = $plan['counts']['skip'] + $plan['counts']['error'];
    flash('success', "Import done: {$created} created, {$updated} updated" . ($left > 0 ? ", {$left} left out" : '') . '.');
    // Land on what was just added: newest first.
    redirect('/inventory?sort=created_at&dir=desc');
});
