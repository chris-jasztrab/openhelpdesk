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
    $statFilter = array_values(array_filter(array_map('intval', (array) ($_GET['status'] ?? []))));
    $locFilter  = array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? []))));
    $assigned   = requestScalar('assigned');
    $deleted    = requestScalar('deleted') === '1';

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
    if ($statFilter !== []) {
        $where[] = 's.id IN (' . implode(',', array_fill(0, count($statFilter), '?')) . ')';
        $params  = array_merge($params, $statFilter);
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
    if ($statFilter !== []) $filterParams['status']   = $statFilter;
    if ($locFilter !== [])  $filterParams['location'] = $locFilter;
    if ($assigned !== '')   $filterParams['assigned'] = $assigned;
    if ($deleted)           $filterParams['deleted']  = '1';

    if (requestScalar('export') === 'csv') {
        $stmt = $db->prepare(assetSelect() . $whereSql . $orderSql);
        $stmt->execute($params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['Tag', 'Name', 'Serial', 'Model', 'Model No.', 'Category', 'Manufacturer', 'Status', 'Location', 'Assigned To', 'Purchase Date', 'Cost', 'Warranty (months)', 'EOL', 'Notes']);
        foreach ($stmt as $a) {
            fputcsv($out, [
                $a['asset_tag'], $a['name'], $a['serial'], $a['model_name'], $a['model_number'], $a['category_name'],
                $a['manufacturer_name'], $a['status_name'], $a['location_name'], Assets::assigneeLabel($a),
                $a['purchase_date'], $a['purchase_cost'], $a['warranty_months'], $a['eol_date'], $a['notes'],
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
        'locFilter'    => $locFilter,
        'assigned'     => $assigned,
        'deleted'      => $deleted,
        'categories'   => $db->query('SELECT c.id, c.name, COUNT(a.id) AS n FROM asset_categories c LEFT JOIN asset_models m ON m.category_id = c.id LEFT JOIN assets a ON a.model_id = m.id AND a.deleted_at IS NULL GROUP BY c.id ORDER BY c.name')->fetchAll(),
        'statuses'     => $db->query('SELECT s.*, (SELECT COUNT(*) FROM assets a WHERE a.status_id = s.id AND a.deleted_at IS NULL) AS n FROM asset_statuses s ORDER BY s.sort_order, s.name')->fetchAll(),
        'locations'    => $db->query('SELECT id, name FROM locations ORDER BY name')->fetchAll(),
        'deletedCount' => (int) $db->query('SELECT COUNT(*) FROM assets WHERE deleted_at IS NOT NULL')->fetchColumn(),
    ]);
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
    ]);
});
