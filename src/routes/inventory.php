<?php

/**
 * Inventory (asset management) — read-only pages.
 *
 * /inventory is deliberately not under /assets, which is the static-files
 * folder. Everything here is gated on assets.view (or assets.manage, which
 * implies it); admins hold every permission.
 */

/** Columns + joins shared by the list, the detail page and search. */
function assetSelect(): string
{
    return 'SELECT a.*, m.name AS model_name, m.model_number, m.image AS model_image, m.eol_months,
                   c.id AS category_id, c.name AS category_name, mf.name AS manufacturer_name,
                   s.name AS status_name, s.kind AS status_kind, s.color AS status_color,
                   l.name AS location_name, dl.name AS default_location_name, sp.name AS supplier_name,
                   CONCAT(u.first_name, " ", u.last_name) AS assigned_user_name, u.email AS assigned_user_email,
                   al.name AS assigned_location_name, pa.asset_tag AS assigned_asset_tag, pa.name AS assigned_asset_name
            FROM assets a
            JOIN asset_models m        ON m.id = a.model_id
            JOIN asset_categories c    ON c.id = m.category_id
            JOIN asset_statuses s      ON s.id = a.status_id
            LEFT JOIN asset_manufacturers mf ON mf.id = m.manufacturer_id
            LEFT JOIN asset_suppliers sp ON sp.id = a.supplier_id
            LEFT JOIN locations l      ON l.id = a.location_id
            LEFT JOIN locations dl     ON dl.id = a.default_location_id
            LEFT JOIN users u          ON a.assigned_type = "user" AND u.id = a.assigned_id
            LEFT JOIN locations al     ON a.assigned_type = "location" AND al.id = a.assigned_id
            LEFT JOIN assets pa        ON a.assigned_type = "asset" AND pa.id = a.assigned_id';
}

$router->get('/inventory', function () {
    Auth::requirePermission('assets.view', 'assets.manage');
    $db = Database::connect();

    if (isset($_GET['reset'])) {
        redirect('/inventory');
    }

    $q          = trim(requestScalar('q'));
    $catFilter  = array_values(array_filter(array_map('intval', (array) ($_GET['category'] ?? []))));
    $statRaw    = (array) ($_GET['status'] ?? []);
    $statFilter = array_values(array_filter(array_map('intval', $statRaw)));
    $deployed   = in_array('deployed', $statRaw, true); // virtual status: deployable kind + an assignee
    $locFilter  = array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? []))));
    $assigned   = requestScalar('assigned');
    $deleted    = requestScalar('deleted') === '1';
    $auditBefore = requestScalar('audit_before');
    if ($auditBefore !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $auditBefore)) {
        $auditBefore = '';
    }

    $where  = [$deleted ? 'a.deleted_at IS NOT NULL' : 'a.deleted_at IS NULL'];
    $params = [];
    if ($q !== '') {
        $where[] = '(a.asset_tag LIKE ? OR a.name LIKE ? OR a.serial LIKE ? OR m.name LIKE ? OR m.model_number LIKE ?)';
        array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
    }
    if ($catFilter !== []) {
        $where[] = 'c.id IN (' . implode(',', array_fill(0, count($catFilter), '?')) . ')';
        $params  = array_merge($params, $catFilter);
    }
    $statParts = [];
    if ($statFilter !== []) {
        $statParts[] = 's.id IN (' . implode(',', array_fill(0, count($statFilter), '?')) . ')';
        $params      = array_merge($params, $statFilter);
    }
    if ($deployed) {
        $statParts[] = "(s.kind = 'deployable' AND a.assigned_type IS NOT NULL)";
    }
    if ($statParts !== []) {
        $where[] = '(' . implode(' OR ', $statParts) . ')';
    }
    $locNone = in_array('none', $locFilter, true);
    $locIds  = array_values(array_filter(array_map('intval', $locFilter)));
    if ($locNone && $locIds !== []) {
        $where[] = '(a.location_id IS NULL OR a.location_id IN (' . implode(',', array_fill(0, count($locIds), '?')) . '))';
        $params  = array_merge($params, $locIds);
    } elseif ($locNone) {
        $where[] = 'a.location_id IS NULL';
    } elseif ($locIds !== []) {
        $where[] = 'a.location_id IN (' . implode(',', array_fill(0, count($locIds), '?')) . ')';
        $params  = array_merge($params, $locIds);
    }
    if (in_array($assigned, ['user', 'location', 'asset'], true)) {
        $where[]  = 'a.assigned_type = ?';
        $params[] = $assigned;
    } elseif ($assigned === 'unassigned') {
        $where[] = 'a.assigned_type IS NULL';
    } elseif ($assigned === 'import') {
        $where[] = 'a.import_assignee IS NOT NULL';
    }
    if ($auditBefore !== '') {
        $where[]  = '(a.last_audit_at IS NULL OR a.last_audit_at < ?)';
        $params[] = $auditBefore;
    }
    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $sortable = [
        'tag' => 'a.asset_tag', 'name' => 'a.name', 'serial' => 'a.serial', 'model' => 'm.name',
        'category' => 'c.name', 'status' => 's.name', 'location' => 'l.name',
        'assigned' => 'COALESCE(u.last_name, al.name, pa.asset_tag, a.import_assignee)',
        'purchase_date' => 'a.purchase_date', 'created_at' => 'a.created_at',
    ];
    $sort     = requestScalar('sort', 'tag');
    $dir      = strtolower(requestScalar('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
    $orderCol = $sortable[$sort] ?? 'a.asset_tag';
    $orderSql = " ORDER BY {$orderCol} " . strtoupper($dir) . ', a.asset_tag ASC';

    $filterParams = [];
    if ($q !== '')          $filterParams['q']        = $q;
    if ($catFilter !== [])  $filterParams['category'] = $catFilter;
    if ($statFilter !== [] || $deployed) $filterParams['status'] = array_merge($statFilter, $deployed ? ['deployed'] : []);
    if ($locFilter !== [])  $filterParams['location'] = $locFilter;
    if ($assigned !== '')   $filterParams['assigned'] = $assigned;
    if ($deleted)           $filterParams['deleted']  = '1';
    if ($auditBefore !== '') $filterParams['audit_before'] = $auditBefore;

    if (requestScalar('export') === 'csv') {
        $stmt = $db->prepare(assetSelect() . $whereSql . $orderSql);
        $stmt->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['Tag', 'Name', 'Serial', 'Model', 'Model No.', 'Category', 'Manufacturer', 'Status', 'Location', 'Assigned To', 'Purchase Date', 'Cost', 'Warranty (months)', 'EOL', 'Last Audit', 'Next Audit', 'Notes']);
        foreach ($stmt as $a) {
            fputcsv($out, [
                $a['asset_tag'], $a['name'], $a['serial'], $a['model_name'], $a['model_number'], $a['category_name'],
                $a['manufacturer_name'], $a['status_name'], $a['location_name'], Assets::assigneeLabel($a),
                $a['purchase_date'], $a['purchase_cost'], $a['warranty_months'], $a['eol_date'], $a['last_audit_at'], $a['next_audit_date'], $a['notes'],
            ]);
        }
        fclose($out);
        exit;
    }

    $countStmt = $db->prepare(
        'SELECT COUNT(*) FROM assets a JOIN asset_models m ON m.id = a.model_id JOIN asset_categories c ON c.id = m.category_id
         JOIN asset_statuses s ON s.id = a.status_id LEFT JOIN locations l ON l.id = a.location_id' . $whereSql
    );
    $countStmt->execute($params);
    $total      = (int) $countStmt->fetchColumn();
    $perPage    = 50;
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = max(1, min($totalPages, (int) requestScalar('page', '1')));

    $stmt = $db->prepare(assetSelect() . $whereSql . $orderSql . " LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage));
    $stmt->execute($params);

    render('inventory/index', [
        'assets'       => $stmt->fetchAll(),
        'total'        => $total,
        'page'         => $page,
        'totalPages'   => $totalPages,
        'sort'         => $sort,
        'dir'          => $dir,
        'filterParams' => $filterParams,
        'qFilter'      => $q,
        'catFilter'    => $catFilter,
        'statFilter'   => $statFilter,
        'deployed'     => $deployed,
        'deployedCount' => (int) $db->query("SELECT COUNT(*) FROM assets a JOIN asset_statuses s ON s.id = a.status_id WHERE s.kind = 'deployable' AND a.assigned_type IS NOT NULL AND a.deleted_at IS NULL")->fetchColumn(),
        'locFilter'    => $locFilter,
        'assigned'     => $assigned,
        'deleted'      => $deleted,
        'auditBefore'  => $auditBefore,
        'categories'   => $db->query('SELECT c.id, c.name, COUNT(a.id) AS n FROM asset_categories c LEFT JOIN asset_models m ON m.category_id = c.id LEFT JOIN assets a ON a.model_id = m.id AND a.deleted_at IS NULL GROUP BY c.id ORDER BY c.name')->fetchAll(),
        'statuses'     => $db->query('SELECT s.*, (SELECT COUNT(*) FROM assets a WHERE a.status_id = s.id AND a.deleted_at IS NULL) AS n FROM asset_statuses s ORDER BY s.sort_order, s.name')->fetchAll(),
        'locations'    => $db->query('SELECT id, name FROM locations ORDER BY name')->fetchAll(),
        'deletedCount' => (int) $db->query('SELECT COUNT(*) FROM assets WHERE deleted_at IS NOT NULL')->fetchColumn(),
    ]);
});

/**
 * Legacy Snipe-IT QR codes encode https://inventory.wpl.ca/hardware/{snipe id}.
 * With inventory.* pointed at this app, send them to the asset on the
 * canonical host so the login cookie applies.
 */
$router->get('/hardware/{id}', function (array $p) {
    $db = Database::connect();
    $s  = $db->prepare('SELECT id FROM assets WHERE snipeit_id = ?');
    $s->execute([(int) $p['id']]);
    $id = $s->fetchColumn();
    if ($id === false) {
        http_response_code(404);
        render('errors/404', ['layout' => 'app', 'pageTitle' => 'Asset not found']);
    }
    redirect(appUrl() . '/inventory/' . (int) $id, 301);
});

/** Audit mode: scan tag after tag; each scan stamps last_audit_at. */
$router->get('/inventory/audit', function () {
    Auth::requirePermission('assets.manage');
    $db     = Database::connect();
    $months = (int) getSetting('asset_audit_interval_months', '12');
    render('inventory/audit', [
        'locations'      => $db->query('SELECT id, name FROM locations ORDER BY name')->fetchAll(),
        'myLocationId'   => (int) (Auth::user()['location_id'] ?? 0),
        'defaultNext'    => $months > 0 ? date('Y-m-d', strtotime("+{$months} months")) : '',
        'intervalMonths' => $months,
    ]);
});

$router->post('/inventory/audit/scan', function () {
    Auth::requirePermission('assets.manage');
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Session expired — reload the page.']);
        exit;
    }
    $db   = Database::connect();
    $post = static fn (string $k): string => is_string($_POST[$k] ?? null) ? (string) $_POST[$k] : '';
    $code = trim($post('code'));
    $at   = (int) $post('location_id') ?: null;
    $next = $post('next_audit_date');
    if ($next !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next)) {
        $next = '';
    }
    $id = Assets::resolveScan($db, $code);
    if ($id === null) {
        echo json_encode(['found' => false, 'code' => $code]);
        exit;
    }
    $stmt = $db->prepare(assetSelect() . ' WHERE a.id = ?');
    $stmt->execute([$id]);
    $a    = $stmt->fetch();
    $prev = $a['last_audit_at'];
    $d    = Assets::audit($db, $id, $at, $next ?: null, Auth::id());
    echo json_encode([
        'found'      => true,
        'code'       => $code,
        'id'         => $id,
        'tag'        => $a['asset_tag'],
        'name'       => $a['name'],
        'model'      => $a['model_name'],
        'location'   => $a['location_name'],
        'mismatch'   => !empty($d['mismatch']),
        'assigned'   => $a['assigned_type'] === 'user' ? $a['assigned_user_name'] : ($a['assigned_type'] === null ? ($a['import_assignee'] ?? '') : Assets::assigneeLabel($a)),
        'status'     => $a['status_name'],
        'prev_audit' => $prev ? date('M j, Y', strtotime($prev)) : null,
        'next_audit' => $d['next'] ?? null,
    ]);
    exit;
});

/** Typeahead for the ticket-side picker: live assets matching q, the requester's own first. */
$router->get('/inventory/search', function () {
    Auth::requirePermission('assets.view', 'assets.manage');
    header('Content-Type: application/json');
    $db  = Database::connect();
    $q   = trim(requestScalar('q'));
    $uid = (int) requestScalar('user_id', '0');
    if ($q === '' && $uid === 0) {
        echo json_encode([]);
        exit;
    }
    $where  = ['a.deleted_at IS NULL'];
    $params = [];
    if ($q !== '') {
        $where[] = '(a.asset_tag LIKE ? OR a.name LIKE ? OR a.serial LIKE ? OR m.name LIKE ?)';
        array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
    } else {
        $where[] = 'a.assigned_type = "user" AND a.assigned_id = ?';
        $params[] = $uid;
    }
    $stmt = $db->prepare(
        assetSelect() . ' WHERE ' . implode(' AND ', $where)
        . ' ORDER BY (a.assigned_type = "user" AND a.assigned_id = ?) DESC, a.asset_tag LIMIT 10'
    );
    $stmt->execute([...$params, $uid]);
    $out = [];
    foreach ($stmt as $a) {
        $out[] = [
            'id'       => (int) $a['id'],
            'tag'      => $a['asset_tag'],
            'name'     => $a['name'],
            'model'    => $a['model_name'],
            'status'   => $a['status_name'],
            'color'    => $a['status_color'],
            'assigned' => Assets::assigneeLabel($a),
            'mine'     => $a['assigned_type'] === 'user' && (int) $a['assigned_id'] === $uid,
        ];
    }
    echo json_encode($out);
    exit;
});

$router->get('/inventory/models/{id}/image', function (array $p) {
    Auth::requirePermission('assets.view', 'assets.manage');
    $stmt = Database::connect()->prepare('SELECT image FROM asset_models WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    assetServeFile('models/' . basename((string) $stmt->fetchColumn()), null);
});

$router->get('/inventory/{id}/image', function (array $p) {
    Auth::requirePermission('assets.view', 'assets.manage');
    $stmt = Database::connect()->prepare('SELECT image FROM assets WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    assetServeFile('images/' . basename((string) $stmt->fetchColumn()), null);
});

$router->get('/inventory/{id}/files/{fid}', function (array $p) {
    Auth::requirePermission('assets.view', 'assets.manage');
    $stmt = Database::connect()->prepare('SELECT stored_name, original_name FROM asset_files WHERE id = ? AND asset_id = ?');
    $stmt->execute([(int) $p['fid'], (int) $p['id']]);
    $f = $stmt->fetch();
    if (!$f) {
        http_response_code(404);
        exit('Not found');
    }
    assetServeFile('files/' . basename($f['stored_name']), $f['original_name']);
});

/* ── Ticket ⇄ asset links (shared by the agent and admin ticket pages) ── */

/** Load a ticket the current staff user may act on, or redirect back with an error. */
function assetLinkTicket(PDO $db, int $ticketId): array
{
    if (!Auth::isStaff()) {
        Auth::requireStaff();
    }
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket || !userCanAccessTicket($db, Auth::user(), $ticket)) {
        flash('error', 'Ticket not found.');
        redirect(Auth::isAdmin() ? '/admin/tickets' : '/agent/tickets');
    }
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect(staffTicketUrl($ticketId));
    }
    return $ticket;
}

$router->post('/tickets/{id}/assets', function (array $p) {
    Auth::requirePermission('assets.view', 'assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    assetLinkTicket($db, $id);
    $assetId = (int) ($_POST['asset_id'] ?? 0);
    $stmt = $db->prepare('SELECT id FROM assets WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$assetId]);
    if ($stmt->fetchColumn() === false) {
        flash('error', 'Asset not found.');
    } elseif (Assets::link($db, $id, $assetId, Auth::id())) {
        flash('success', 'Asset ' . Assets::describe($db, $assetId) . ' linked.');
    }
    redirect(staffTicketUrl($id) . '#ticketAssetsCard');
});

$router->post('/tickets/{id}/assets/{assetId}/remove', function (array $p) {
    Auth::requirePermission('assets.view', 'assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    assetLinkTicket($db, $id);
    if (Assets::unlink($db, $id, (int) $p['assetId'], Auth::id())) {
        flash('success', 'Asset unlinked.');
    }
    redirect(staffTicketUrl($id) . '#ticketAssetsCard');
});

/** Stream a file from storage/attachments/assets/; a download name makes it an attachment. */
function assetServeFile(string $rel, ?string $downloadName): never
{
    $path = ROOT_DIR . '/storage/attachments/assets/' . $rel;
    if (basename($rel) === '' || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    session_write_close();
    header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=86400');
    if ($downloadName !== null) {
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
    }
    readfile($path);
    exit;
}

/* ── Create / edit / check out / check in / delete / files (assets.manage) ── */

/** Columns the create/edit form posts, in the order the history diff reports them. */
const ASSET_FORM_FIELDS = ['asset_tag', 'name', 'serial', 'model_id', 'status_id', 'location_id', 'default_location_id', 'supplier_id', 'order_number', 'purchase_date', 'purchase_cost', 'warranty_months', 'eol_date', 'next_audit_date', 'notes'];

/** Catalog lists the form needs. */
function assetFormData(PDO $db): array
{
    return [
        'models'    => $db->query('SELECT m.id, m.name, c.name AS category_name, mf.name AS manufacturer_name FROM asset_models m JOIN asset_categories c ON c.id = m.category_id LEFT JOIN asset_manufacturers mf ON mf.id = m.manufacturer_id ORDER BY c.name, m.name')->fetchAll(),
        'statuses'  => $db->query('SELECT id, name FROM asset_statuses ORDER BY sort_order, name')->fetchAll(),
        'locations' => $db->query('SELECT id, name FROM locations ORDER BY name')->fetchAll(),
        'suppliers' => $db->query('SELECT id, name FROM asset_suppliers ORDER BY name')->fetchAll(),
        'nextTag'   => assetNextTagPreview($db),
    ];
}

/** What nextTag() would hand out, without advancing the counter. */
function assetNextTagPreview(PDO $db): string
{
    return getSetting('asset_tag_prefix', '') . str_pad(getSetting('asset_tag_next', '1'), (int) getSetting('asset_tag_zerofill', '5'), '0', STR_PAD_LEFT);
}

/** Validate + normalise the posted form; returns [row, error]. */
function assetFormRow(PDO $db, ?int $editingId): array
{
    $int  = static fn(string $k): ?int => ($_POST[$k] ?? '') === '' ? null : (int) $_POST[$k];
    $str  = static fn(string $k): ?string => trim((string) ($_POST[$k] ?? '')) === '' ? null : trim((string) $_POST[$k]);
    $row = [
        'asset_tag' => trim((string) ($_POST['asset_tag'] ?? '')), 'name' => $str('name'), 'serial' => $str('serial'),
        'model_id' => $int('model_id'), 'status_id' => $int('status_id'),
        'location_id' => $int('location_id'), 'default_location_id' => $int('default_location_id'), 'supplier_id' => $int('supplier_id'),
        'order_number' => $str('order_number'), 'purchase_date' => $str('purchase_date'),
        'purchase_cost' => ($_POST['purchase_cost'] ?? '') === '' ? null : round((float) $_POST['purchase_cost'], 2),
        'warranty_months' => $int('warranty_months'), 'eol_date' => $str('eol_date'), 'next_audit_date' => $str('next_audit_date'),
        'notes' => $str('notes'),
    ];
    foreach (['purchase_date', 'eol_date', 'next_audit_date'] as $d) {
        if ($row[$d] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row[$d])) {
            return [$row, 'Dates must be YYYY-MM-DD.'];
        }
    }
    if ($row['model_id'] === null || $row['status_id'] === null) {
        return [$row, 'Model and status are required.'];
    }
    if ($row['asset_tag'] !== '') {
        $s = $db->prepare('SELECT id FROM assets WHERE asset_tag = ? AND id <> ?');
        $s->execute([$row['asset_tag'], $editingId ?? 0]);
        if ($s->fetchColumn() !== false) {
            return [$row, 'Asset tag ' . $row['asset_tag'] . ' is already in use.'];
        }
    }
    return [$row, null];
}

/** Store an uploaded asset photo under images/; returns the stored name or null. */
function assetStoreImage(string $field, int $assetId): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $mime = (string) mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset(Assets::IMAGE_TYPES[$mime]) || (int) $_FILES[$field]['size'] > UPLOAD_MAX_SIZE) {
        flash('error', 'Photo must be a PNG, JPEG, GIF or WebP under ' . (UPLOAD_MAX_SIZE / 1048576) . ' MB.');
        return null;
    }
    $dir = ROOT_DIR . '/storage/attachments/assets/images/';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'asset_' . $assetId . '_' . bin2hex(random_bytes(4)) . '.' . Assets::IMAGE_TYPES[$mime];
    return move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $name) ? $name : null;
}

$router->get('/inventory/create', function () {
    Auth::requirePermission('assets.manage');
    render('inventory/form', ['editing' => null] + assetFormData(Database::connect()));
});

$router->post('/inventory/create', function () {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/inventory/create');
    }
    [$row, $err] = assetFormRow($db, null);
    if ($err !== null) {
        flashInput($_POST);
        flash('error', $err);
        redirect('/inventory/create');
    }
    if ($row['asset_tag'] === '') {
        $row['asset_tag'] = Assets::nextTag($db);
    }
    $row['created_by'] = Auth::id();
    $cols = array_keys($row);
    $db->prepare('INSERT INTO assets (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
       ->execute(array_values($row));
    $id = (int) $db->lastInsertId();
    if ($img = assetStoreImage('image', $id)) {
        $db->prepare('UPDATE assets SET image = ? WHERE id = ?')->execute([$img, $id]);
    }
    Assets::logHistory($db, $id, 'create', [], Auth::id());
    logAudit('asset.created', $id, 'asset', 'tag=' . $row['asset_tag']);
    flash('success', 'Asset ' . $row['asset_tag'] . ' created.');
    redirect('/inventory/' . $id);
});

$router->get('/inventory/{id}/edit', function (array $p) {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM assets WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Asset not found.');
        redirect('/inventory');
    }
    render('inventory/form', ['editing' => $editing] + assetFormData($db));
});

$router->post('/inventory/{id}/edit', function (array $p) {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/inventory/{$id}/edit");
    }
    $stmt = $db->prepare('SELECT * FROM assets WHERE id = ?');
    $stmt->execute([$id]);
    $before = $stmt->fetch();
    if (!$before) {
        flash('error', 'Asset not found.');
        redirect('/inventory');
    }
    [$row, $err] = assetFormRow($db, $id);
    if ($err !== null || $row['asset_tag'] === '') {
        flashInput($_POST);
        flash('error', $err ?? 'Asset tag is required.');
        redirect("/inventory/{$id}/edit");
    }
    if ($img = assetStoreImage('image', $id)) {
        $row['image'] = $img;
    }
    $sets = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($row)));
    $db->prepare("UPDATE assets SET {$sets} WHERE id = ?")->execute([...array_values($row), $id]);
    $changes = Assets::changes($before, $row, ASSET_FORM_FIELDS);
    if ($changes !== [] || isset($row['image'])) {
        Assets::logHistory($db, $id, 'update', $changes === [] ? [] : ['changes' => $changes], Auth::id());
        logAudit('asset.updated', $id, 'asset', 'tag=' . $row['asset_tag'] . ' fields=' . implode(',', array_keys($changes)));
    }
    flash('success', 'Asset updated.');
    redirect('/inventory/' . $id);
});

$router->post('/inventory/{id}/checkout', function (array $p) {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/inventory/{$id}");
    }
    $type = (string) ($_POST['assigned_type'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));
    $target = null;
    $location = null;
    if ($type === 'user') {
        $s = $db->prepare('SELECT id, CONCAT(first_name, " ", last_name) AS name, location_id FROM users WHERE id = ?');
        $s->execute([(int) ($_POST['user_id'] ?? 0)]);
        if ($target = $s->fetch()) {
            $location = $target['location_id'] !== null ? (int) $target['location_id'] : null;
        }
    } elseif ($type === 'location') {
        $s = $db->prepare('SELECT id, name FROM locations WHERE id = ?');
        $s->execute([(int) ($_POST['location_id'] ?? 0)]);
        if ($target = $s->fetch()) {
            $location = (int) $target['id'];
        }
    } elseif ($type === 'asset') {
        $s = $db->prepare('SELECT id, asset_tag AS name, location_id FROM assets WHERE asset_tag = ? AND id <> ? AND deleted_at IS NULL');
        $s->execute([trim((string) ($_POST['asset_tag'] ?? '')), $id]);
        if ($target = $s->fetch()) {
            $location = $target['location_id'] !== null ? (int) $target['location_id'] : null;
        }
    }
    if (!$target) {
        flash('error', 'Choose who or where to check this asset out to.');
        redirect("/inventory/{$id}?tab=details");
    }
    $db->prepare('UPDATE assets SET assigned_type = ?, assigned_id = ?, import_assignee = NULL, location_id = COALESCE(?, location_id), last_checkout_at = NOW() WHERE id = ? AND deleted_at IS NULL')
       ->execute([$type, (int) $target['id'], $location, $id]);
    Assets::logHistory($db, $id, 'checkout', array_filter(['target' => ['type' => $type, 'id' => (int) $target['id'], 'name' => $target['name']], 'note' => $note ?: null]), Auth::id());
    logAudit('asset.checked_out', $id, 'asset', $type . '=' . $target['name']);
    flash('success', 'Checked out to ' . $target['name'] . '.');
    redirect("/inventory/{$id}");
});

$router->post('/inventory/{id}/checkin', function (array $p) {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/inventory/{$id}");
    }
    $stmt = $db->prepare(assetSelect() . ' WHERE a.id = ?');
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    if (!$a || ($a['assigned_type'] === null && $a['import_assignee'] === null)) {
        flash('error', 'That asset is not checked out.');
        redirect("/inventory/{$id}");
    }
    $from = Assets::assigneeLabel($a);
    $db->prepare('UPDATE assets SET assigned_type = NULL, assigned_id = NULL, import_assignee = NULL, location_id = COALESCE(default_location_id, location_id), last_checkin_at = NOW() WHERE id = ?')
       ->execute([$id]);
    $note = trim((string) ($_POST['note'] ?? ''));
    Assets::logHistory($db, $id, 'checkin', array_filter(['target' => ['type' => $a['assigned_type'] ?? 'user', 'id' => $a['assigned_id'] !== null ? (int) $a['assigned_id'] : null, 'name' => $from], 'note' => $note ?: null]), Auth::id());
    logAudit('asset.checked_in', $id, 'asset', 'from=' . $from);
    flash('success', 'Checked in from ' . $from . '.');
    redirect("/inventory/{$id}");
});

$router->post('/inventory/{id}/delete', function (array $p) {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/inventory/{$id}");
    }
    $db->prepare('UPDATE assets SET deleted_at = NOW(), assigned_type = NULL, assigned_id = NULL WHERE id = ? AND deleted_at IS NULL')->execute([$id]);
    Assets::logHistory($db, $id, 'delete', [], Auth::id());
    logAudit('asset.deleted', $id, 'asset', null);
    flash('success', 'Asset deleted. It stays in the list under "Show deleted" and can be restored.');
    redirect("/inventory/{$id}");
});

$router->post('/inventory/{id}/restore', function (array $p) {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/inventory/{$id}");
    }
    $db->prepare('UPDATE assets SET deleted_at = NULL WHERE id = ?')->execute([$id]);
    Assets::logHistory($db, $id, 'restore', [], Auth::id());
    logAudit('asset.restored', $id, 'asset', null);
    flash('success', 'Asset restored.');
    redirect("/inventory/{$id}");
});

$router->post('/inventory/{id}/files', function (array $p) {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/inventory/{$id}?tab=files");
    }
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Choose a file to upload.');
        redirect("/inventory/{$id}?tab=files");
    }
    $mime = (string) mime_content_type($f['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED_TYPES, true) || (int) $f['size'] > UPLOAD_MAX_SIZE) {
        flash('error', 'That file type or size is not allowed (see Settings › Attachments).');
        redirect("/inventory/{$id}?tab=files");
    }
    $dir = ROOT_DIR . '/storage/attachments/assets/files/';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stored = 'asset_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . safeUploadExtension($mime, (string) $f['name']);
    if (!move_uploaded_file($f['tmp_name'], $dir . $stored)) {
        flash('error', 'Upload failed.');
        redirect("/inventory/{$id}?tab=files");
    }
    $db->prepare('INSERT INTO asset_files (asset_id, uploaded_by, original_name, stored_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)')
       ->execute([$id, Auth::id(), (string) $f['name'], $stored, $mime, (int) $f['size']]);
    Assets::logHistory($db, $id, 'file_uploaded', ['filename' => (string) $f['name']], Auth::id());
    flash('success', 'File uploaded.');
    redirect("/inventory/{$id}?tab=files");
});

$router->post('/inventory/{id}/files/{fid}/delete', function (array $p) {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/inventory/{$id}?tab=files");
    }
    $stmt = $db->prepare('SELECT stored_name, original_name FROM asset_files WHERE id = ? AND asset_id = ?');
    $stmt->execute([(int) $p['fid'], $id]);
    if ($f = $stmt->fetch()) {
        $db->prepare('DELETE FROM asset_files WHERE id = ?')->execute([(int) $p['fid']]);
        $path = ROOT_DIR . '/storage/attachments/assets/files/' . basename($f['stored_name']);
        if (is_file($path)) {
            unlink($path);
        }
        Assets::logHistory($db, $id, 'file_deleted', ['filename' => $f['original_name']], Auth::id());
        flash('success', 'File removed.');
    }
    redirect("/inventory/{$id}?tab=files");
});

/* ── Settings › Inventory: tag numbering + catalog (assets.manage) ── */

/** Field specs per catalog kind: [name, label, type, required?, default]. */
function assetCatalogSpec(string $kind): ?array
{
    $kinds = ['deployable' => 'Deployable', 'pending' => 'Pending', 'undeployable' => 'Undeployable', 'archived' => 'Archived'];
    return [
        'categories' => ['title' => 'Categories', 'singular' => 'Category', 'table' => 'asset_categories', 'order' => 'name',
            'fields' => [['name', 'Name', 'text', true]],
            'columns' => [['name', 'Name']],
            'in_use' => 'SELECT COUNT(*) FROM asset_models WHERE category_id = t.id'],
        'manufacturers' => ['title' => 'Manufacturers', 'singular' => 'Manufacturer', 'table' => 'asset_manufacturers', 'order' => 'name',
            'fields' => [['name', 'Name', 'text', true], ['url', 'Website', 'url'], ['support_url', 'Support URL', 'url'], ['support_phone', 'Support phone', 'text'], ['support_email', 'Support email', 'email']],
            'columns' => [['name', 'Name'], ['url', 'Website'], ['support_phone', 'Support phone']],
            'in_use' => 'SELECT COUNT(*) FROM asset_models WHERE manufacturer_id = t.id'],
        'suppliers' => ['title' => 'Suppliers', 'singular' => 'Supplier', 'table' => 'asset_suppliers', 'order' => 'name',
            'fields' => [['name', 'Name', 'text', true], ['contact', 'Contact', 'text'], ['phone', 'Phone', 'text'], ['email', 'Email', 'email'], ['url', 'Website', 'url'], ['address', 'Address', 'textarea'], ['notes', 'Notes', 'textarea']],
            'columns' => [['name', 'Name'], ['contact', 'Contact'], ['phone', 'Phone'], ['email', 'Email']],
            'in_use' => 'SELECT COUNT(*) FROM assets WHERE supplier_id = t.id'],
        'statuses' => ['title' => 'Statuses', 'singular' => 'Status', 'table' => 'asset_statuses', 'order' => 'sort_order, name',
            'fields' => [['name', 'Name', 'text', true], ['kind', 'Kind', 'select', true, 'deployable'], ['color', 'Colour', 'color', false, '#6c757d'], ['sort_order', 'Sort order', 'number', false, '0'], ['notes', 'Notes', 'textarea']],
            'columns' => [['color', 'Status'], ['kind', 'Kind'], ['sort_order', 'Order']],
            'options' => ['kind' => $kinds],
            'in_use' => 'SELECT COUNT(*) FROM assets WHERE status_id = t.id'],
        'models' => ['title' => 'Models', 'singular' => 'Model', 'table' => 'asset_models', 'order' => 'name',
            'fields' => [['name', 'Name', 'text', true], ['model_number', 'Model number', 'text'], ['category_id', 'Category', 'select', true], ['manufacturer_id', 'Manufacturer', 'select'], ['eol_months', 'End of life (months)', 'number'], ['notes', 'Notes', 'textarea']],
            'columns' => [['name', 'Name'], ['model_number', 'Model no.'], ['category_name', 'Category'], ['manufacturer_name', 'Manufacturer'], ['eol_months', 'EOL (months)']],
            'in_use' => 'SELECT COUNT(*) FROM assets WHERE model_id = t.id'],
    ][$kind] ?? null;
}

function assetCatalogCounts(PDO $db): array
{
    return [
        'models' => (int) $db->query('SELECT COUNT(*) FROM asset_models')->fetchColumn(),
        'categories' => (int) $db->query('SELECT COUNT(*) FROM asset_categories')->fetchColumn(),
        'manufacturers' => (int) $db->query('SELECT COUNT(*) FROM asset_manufacturers')->fetchColumn(),
        'suppliers' => (int) $db->query('SELECT COUNT(*) FROM asset_suppliers')->fetchColumn(),
        'statuses' => (int) $db->query('SELECT COUNT(*) FROM asset_statuses')->fetchColumn(),
    ];
}

$router->get('/admin/settings/inventory', function () {
    Auth::requirePermission('assets.manage');
    $db = Database::connect();
    render('admin/settings/inventory', [
        'kind' => null, 'spec' => null, 'counts' => assetCatalogCounts($db),
        'tagPrefix' => getSetting('asset_tag_prefix', ''), 'tagZerofill' => getSetting('asset_tag_zerofill', '5'),
        'tagNext' => getSetting('asset_tag_next', '1'), 'nextTagPreview' => assetNextTagPreview($db),
        'auditInterval' => getSetting('asset_audit_interval_months', '12'),
    ]);
});

$router->post('/admin/settings/inventory', function () {
    Auth::requirePermission('assets.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/inventory');
    }
    setSetting('asset_tag_prefix', trim((string) ($_POST['asset_tag_prefix'] ?? '')));
    setSetting('asset_tag_zerofill', (string) max(0, min(12, (int) ($_POST['asset_tag_zerofill'] ?? 5))));
    setSetting('asset_tag_next', (string) max(1, (int) ($_POST['asset_tag_next'] ?? 1)));
    setSetting('asset_audit_interval_months', (string) max(0, min(120, (int) ($_POST['asset_audit_interval_months'] ?? 12))));
    logAudit('asset.tag_settings_changed', null, null, 'prefix=' . trim((string) ($_POST['asset_tag_prefix'] ?? '')) . ' next=' . (int) ($_POST['asset_tag_next'] ?? 1));
    flash('success', 'Inventory settings saved.');
    redirect('/admin/settings/inventory');
});

$router->get('/admin/settings/inventory/{kind}', function (array $p) {
    Auth::requirePermission('assets.manage');
    $spec = assetCatalogSpec($p['kind']);
    if ($spec === null) {
        redirect('/admin/settings/inventory');
    }
    $db = Database::connect();
    $extra = $p['kind'] === 'models' ? ', (SELECT name FROM asset_categories c WHERE c.id = t.category_id) AS category_name, (SELECT name FROM asset_manufacturers m WHERE m.id = t.manufacturer_id) AS manufacturer_name' : '';
    $rows = $db->query("SELECT t.*, ({$spec['in_use']}) AS in_use{$extra} FROM `{$spec['table']}` t ORDER BY {$spec['order']}")->fetchAll();
    $editing = null;
    if (($eid = (int) requestScalar('edit', '0')) > 0) {
        foreach ($rows as $r) {
            if ((int) $r['id'] === $eid) {
                $editing = $r;
            }
        }
    }
    $options = $spec['options'] ?? [];
    if ($p['kind'] === 'models') {
        $options['category_id']     = $db->query('SELECT id, name FROM asset_categories ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
        $options['manufacturer_id'] = $db->query('SELECT id, name FROM asset_manufacturers ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    render('admin/settings/inventory', [
        'kind' => $p['kind'], 'spec' => $spec, 'rows' => $rows, 'editing' => $editing, 'options' => $options,
        'counts' => assetCatalogCounts($db),
    ]);
});

$router->post('/admin/settings/inventory/{kind}', function (array $p) {
    Auth::requirePermission('assets.manage');
    $spec = assetCatalogSpec($p['kind']);
    if ($spec === null) {
        redirect('/admin/settings/inventory');
    }
    $back = '/admin/settings/inventory/' . $p['kind'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect($back);
    }
    $db  = Database::connect();
    $id  = (int) ($_POST['id'] ?? 0);
    $row = [];
    foreach ($spec['fields'] as $f) {
        [$name, $label, $type] = $f;
        $val = trim((string) ($_POST[$name] ?? ''));
        if ($val === '' && !empty($f[3])) {
            flashInput($_POST);
            flash('error', $label . ' is required.');
            redirect($back . ($id ? "?edit={$id}" : ''));
        }
        if ($type === 'select' && isset($spec['options'][$name]) && $val !== '' && !isset($spec['options'][$name][$val])) {
            $val = array_key_first($spec['options'][$name]);
        }
        $row[$name] = $val === '' ? null : (in_array($type, ['number'], true) || str_ends_with($name, '_id') ? (int) $val : $val);
    }
    $dup = $db->prepare("SELECT id FROM `{$spec['table']}` WHERE LOWER(name) = LOWER(?) AND id <> ?" . ($p['kind'] === 'models' ? ' AND category_id = ?' : ''));
    $dup->execute($p['kind'] === 'models' ? [$row['name'], $id, $row['category_id']] : [$row['name'], $id]);
    if ($dup->fetchColumn() !== false) {
        flashInput($_POST);
        flash('error', $spec['singular'] . ' "' . $row['name'] . '" already exists.');
        redirect($back . ($id ? "?edit={$id}" : ''));
    }
    if ($id > 0) {
        $sets = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($row)));
        $db->prepare("UPDATE `{$spec['table']}` SET {$sets} WHERE id = ?")->execute([...array_values($row), $id]);
        logAudit('asset_catalog.updated', $id, $p['kind'], 'name=' . $row['name']);
        flash('success', $spec['singular'] . ' updated.');
    } else {
        $cols = array_keys($row);
        $db->prepare("INSERT INTO `{$spec['table']}` (`" . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
        logAudit('asset_catalog.created', (int) $db->lastInsertId(), $p['kind'], 'name=' . $row['name']);
        flash('success', $spec['singular'] . ' added.');
    }
    redirect($back);
});

$router->post('/admin/settings/inventory/{kind}/{id}/delete', function (array $p) {
    Auth::requirePermission('assets.manage');
    $spec = assetCatalogSpec($p['kind']);
    if ($spec === null) {
        redirect('/admin/settings/inventory');
    }
    $back = '/admin/settings/inventory/' . $p['kind'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect($back);
    }
    $db = Database::connect();
    $id = (int) $p['id'];
    $s = $db->prepare("SELECT name, ({$spec['in_use']}) AS in_use FROM `{$spec['table']}` t WHERE id = ?");
    $s->execute([$id]);
    $r = $s->fetch();
    if (!$r) {
        redirect($back);
    }
    if ((int) $r['in_use'] > 0) {
        flash('error', $spec['singular'] . ' "' . $r['name'] . '" is in use by ' . (int) $r['in_use'] . ' record(s) and cannot be deleted.');
        redirect($back);
    }
    $db->prepare("DELETE FROM `{$spec['table']}` WHERE id = ?")->execute([$id]);
    logAudit('asset_catalog.deleted', $id, $p['kind'], 'name=' . $r['name']);
    flash('success', $spec['singular'] . ' deleted.');
    redirect($back);
});

$router->get('/inventory/{id}', function (array $p) {
    Auth::requirePermission('assets.view', 'assets.manage');
    $db = Database::connect();
    $id = (int) $p['id'];

    $stmt = $db->prepare(assetSelect() . ' WHERE a.id = ?');
    $stmt->execute([$id]);
    $asset = $stmt->fetch();
    if (!$asset) {
        http_response_code(404);
        render('errors/404', ['layout' => 'app', 'pageTitle' => 'Asset not found']);
    }

    // Tickets linked to this asset, each one checked against the viewer's
    // access (confidential types/groups) — hidden ones are counted, not shown.
    $stmt = $db->prepare(
        'SELECT t.*, tam.linked_at, CONCAT(r.first_name, " ", r.last_name) AS requester_name,
                CONCAT(ag.first_name, " ", ag.last_name) AS agent_name, tt.name AS type_name
         FROM ticket_asset_map tam
         JOIN tickets t ON t.id = tam.ticket_id
         LEFT JOIN users r  ON r.id = t.created_by
         LEFT JOIN users ag ON ag.id = t.assigned_to
         LEFT JOIN ticket_types tt ON tt.id = t.type_id
         WHERE tam.asset_id = ?
         ORDER BY t.created_at DESC'
    );
    $stmt->execute([$id]);
    $tickets = [];
    $hiddenTickets = 0;
    $viewer = Auth::user();
    foreach ($stmt as $t) {
        if (userCanAccessTicket($db, $viewer, $t)) {
            $tickets[] = $t;
        } else {
            $hiddenTickets++;
        }
    }

    $stmt = $db->prepare(
        'SELECT h.*, CONCAT(u.first_name, " ", u.last_name) AS user_name
         FROM asset_history h LEFT JOIN users u ON u.id = h.user_id
         WHERE h.asset_id = ? ORDER BY h.created_at DESC, h.id DESC'
    );
    $stmt->execute([$id]);
    $history = $stmt->fetchAll();

    $stmt = $db->prepare(
        'SELECT f.*, CONCAT(u.first_name, " ", u.last_name) AS uploader_name
         FROM asset_files f LEFT JOIN users u ON u.id = f.uploaded_by WHERE f.asset_id = ? ORDER BY f.created_at DESC'
    );
    $stmt->execute([$id]);
    $files = $stmt->fetchAll();

    $stmt = $db->prepare(
        'SELECT a.id, a.asset_tag, a.name, m.name AS model_name, s.name AS status_name, s.color AS status_color
         FROM assets a JOIN asset_models m ON m.id = a.model_id JOIN asset_statuses s ON s.id = a.status_id
         WHERE a.assigned_type = "asset" AND a.assigned_id = ? AND a.deleted_at IS NULL ORDER BY a.asset_tag'
    );
    $stmt->execute([$id]);
    $children = $stmt->fetchAll();

    render('inventory/view', [
        'asset'         => $asset,
        'tickets'       => $tickets,
        'hiddenTickets' => $hiddenTickets,
        'history'       => $history,
        'files'         => $files,
        'children'      => $children,
        'tab'           => requestScalar('tab', 'tickets'),
        'users'         => Assets::canManage() ? $db->query('SELECT id, first_name, last_name, email FROM users WHERE is_external = 0 ORDER BY first_name, last_name')->fetchAll() : [],
        'locations'     => Assets::canManage() ? $db->query('SELECT id, name FROM locations ORDER BY name')->fetchAll() : [],
    ]);
});
