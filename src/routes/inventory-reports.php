<?php

/**
 * Inventory reports (/inventory/reports) and the batch endpoint behind the
 * Warranty report's "Look up all".
 *
 * Reports are counts. Every count links to the inventory list with the
 * matching filters, and the list shows the rows and exports them as CSV, so
 * there is one row-level view rather than one per report. Each tab's main
 * table also downloads as CSV here (?export=csv).
 *
 * Required before routes/inventory.php, whose /inventory/{id} would otherwise
 * take "reports" for an asset id. Uses assetSelect(), ASSET_AGE_BUCKETS and
 * assetAgeCase() from that file (resolved at request time).
 */

const INVENTORY_REPORT_TABS = [
    'overview' => ['Overview', 'bi-grid'], 'warranty' => ['Warranty', 'bi-shield-check'], 'audit' => ['Audit', 'bi-clipboard-check'],
    'age' => ['Age', 'bi-hourglass-split'], 'people' => ['Checked out', 'bi-person-badge'], 'activity' => ['Activity', 'bi-clock-history'],
    'quality' => ['Data quality', 'bi-exclamation-diamond'],
    'digest' => ['Email digest', 'bi-envelope-paper'], // assets.manage only
];

require_once ROOT_DIR . '/src/AssetDigest.php';

const INVENTORY_REPORT_FROM = ' FROM assets a JOIN asset_models m ON m.id = a.model_id JOIN asset_categories c ON c.id = m.category_id
    JOIN asset_statuses s ON s.id = a.status_id LEFT JOIN asset_manufacturers mf ON mf.id = m.manufacturer_id
    LEFT JOIN locations l ON l.id = a.location_id WHERE a.deleted_at IS NULL';

/** Group-by choices: key => [label, id expression, name expression]. The key doubles as the list filter name. */
function inventoryReportDims(): array
{
    return [
        'category'     => ['Category', 'c.id', 'c.name'],
        'location'     => [label('location.singular'), 'a.location_id', 'l.name'],
        'status'       => ['Status', 's.id', 's.name'],
        'manufacturer' => ['Manufacturer', 'm.manufacturer_id', 'mf.name'],
        'model'        => ['Model', 'm.id', 'm.name'],
    ];
}

/** Inventory list URL for one group row plus extra filters ($id null = "none"). */
function inventoryReportLink(string $dim, mixed $id, array $more = []): string
{
    $f = $dim === 'model' ? ['model' => (int) $id] : [$dim => [$id === null ? 'none' : (string) $id]];
    return '/inventory?' . http_build_query($f + $more);
}

/**
 * COUNT(*) plus one SUM per $sums entry, grouped by $dim, biggest first.
 * $params bind the ?s inside $sums, in order.
 */
function inventoryGroupCounts(PDO $db, string $dim, array $sums, array $params = []): array
{
    [, $idExpr, $nameExpr] = inventoryReportDims()[$dim];
    $cols = '';
    foreach ($sums as $k => $sql) {
        $cols .= ", SUM({$sql}) AS `{$k}`";
    }
    $s = $db->prepare("SELECT {$idExpr} AS gid, {$nameExpr} AS gname, COUNT(*) AS total{$cols}" . INVENTORY_REPORT_FROM . ' GROUP BY gid, gname ORDER BY total DESC, gname');
    $s->execute($params);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

/** Column totals across group rows. */
function inventoryReportTotals(array $rows): array
{
    $t = [];
    foreach ($rows as $r) {
        foreach ($r as $k => $v) {
            if ($k !== 'gid' && $k !== 'gname') {
                $t[$k] = ($t[$k] ?? 0) + (int) $v;
            }
        }
    }
    return $t;
}

/** A YYYY-MM-DD query value, or $default. */
function inventoryReportDate(string $key, string $default): string
{
    $v = requestScalar($key);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $default;
}

/** Fields an 'update' history row changed: the edit form's diff, or a warranty lookup's new end date. */
function inventoryHistoryFields(array $d): array
{
    return is_array($d['changes'] ?? null) ? array_keys($d['changes']) : (isset($d['warranty_end']) ? ['warranty_end'] : []);
}

function inventoryReportCsv(string $name, array $head, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory-' . $name . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, $head);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

$router->get('/inventory/reports', function () {
    Auth::requirePermission('assets.view', 'assets.manage');
    $db    = Database::connect();
    $tab   = isset(INVENTORY_REPORT_TABS[requestScalar('tab')]) ? requestScalar('tab') : 'overview';
    if ($tab === 'digest' && !Assets::canManage()) {
        $tab = 'overview';
    }
    $dims  = inventoryReportDims();
    $by    = isset($dims[requestScalar('by')]) ? requestScalar('by') : ($tab === 'audit' ? 'location' : 'category');
    $today = date('Y-m-d');
    $csv   = requestScalar('export') === 'csv';
    $months = (int) getSetting('asset_audit_interval_months', '12') ?: 12;
    $data  = ['tab' => $tab, 'by' => $by, 'dims' => $dims, 'today' => $today];

    if ($tab === 'overview') {
        $cut  = date('Y-m-d', strtotime("-{$months} months"));
        $rows = inventoryGroupCounts($db, $by, [
            'deployed'    => "s.kind = 'deployable' AND a.assigned_type IS NOT NULL",
            'w_active'    => 'a.warranty_end >= ?',
            'w_expired'   => 'a.warranty_end < ?',
            'w_unknown'   => 'a.warranty_end IS NULL',
            'audited'     => 'a.last_audit_at >= ?',
            'not_audited' => 'a.last_audit_at IS NULL OR a.last_audit_at < ?',
        ], [$today, $today, $cut, $cut]);
        if ($csv) {
            inventoryReportCsv('overview-by-' . $by, [$dims[$by][0], 'Assets', 'Deployed', 'In warranty', 'Warranty expired', 'Warranty unknown', "Audited in last {$months} months", 'Not audited'],
                array_map(static fn($r) => [$r['gname'] ?? '(none)', $r['total'], $r['deployed'], $r['w_active'], $r['w_expired'], $r['w_unknown'], $r['audited'], $r['not_audited']], $rows));
        }
        $data += ['rows' => $rows, 'totals' => inventoryReportTotals($rows), 'cut' => $cut, 'months' => $months];
    }

    if ($tab === 'warranty') {
        $days  = max(1, min(3650, (int) requestScalar('days', '90')));
        $until = date('Y-m-d', strtotime("+{$days} days"));
        $rows  = inventoryGroupCounts($db, $by, [
            'w_active'  => 'a.warranty_end > ?',
            'w_soon'    => 'a.warranty_end BETWEEN ? AND ?',
            'w_expired' => 'a.warranty_end < ?',
            'w_unknown' => 'a.warranty_end IS NULL',
            'w_lookup'  => "a.warranty_end IS NULL AND (mf.name LIKE '%dell%' OR mf.name LIKE '%lenovo%')",
            'w_error'   => 'a.warranty_error IS NOT NULL',
        ], [$until, $today, $until, $today]);
        if ($csv) {
            inventoryReportCsv('warranty-by-' . $by, [$dims[$by][0], 'Assets', "In warranty past {$days} days", "Ends within {$days} days", 'Expired', 'Unknown', 'Unknown (Dell/Lenovo)', 'Lookup failed'],
                array_map(static fn($r) => [$r['gname'] ?? '(none)', $r['total'], $r['w_active'], $r['w_soon'], $r['w_expired'], $r['w_unknown'], $r['w_lookup'], $r['w_error']], $rows));
        }
        $ending = $db->prepare(assetSelect() . ' WHERE a.deleted_at IS NULL AND a.warranty_end BETWEEN ? AND ? ORDER BY a.warranty_end, a.asset_tag LIMIT 25');
        $ending->execute([$today, $until]);
        $data += [
            'rows' => $rows, 'totals' => inventoryReportTotals($rows), 'days' => $days, 'until' => $until,
            'ending' => $ending->fetchAll(),
            'errors' => $db->query('SELECT warranty_error, COUNT(*) AS n FROM assets WHERE deleted_at IS NULL AND warranty_error IS NOT NULL GROUP BY warranty_error ORDER BY n DESC LIMIT 15')->fetchAll(),
            'lookupMf' => array_map('strval', $db->query("SELECT id FROM asset_manufacturers WHERE name LIKE '%dell%' OR name LIKE '%lenovo%'")->fetchAll(PDO::FETCH_COLUMN)),
            'dellReady' => Warranty::dellConfigured(),
        ];
        if (Assets::canManage()) {
            $data['scan'] = ['all' => Warranty::scanIds($db, false), 'due' => Warranty::scanIds($db, true)];
            $data['lastLookup'] = $db->query('SELECT MAX(warranty_checked_at) FROM assets')->fetchColumn() ?: null;
        }
    }

    if ($tab === 'audit') {
        $since = inventoryReportDate('since', date('Y-m-d', strtotime("-{$months} months")));
        $rows  = inventoryGroupCounts($db, $by, [
            'audited'     => 'a.last_audit_at >= ?',
            'not_audited' => 'a.last_audit_at IS NULL OR a.last_audit_at < ?',
            'never'       => 'a.last_audit_at IS NULL',
            'overdue'     => 'a.next_audit_date < ?',
        ], [$since, $since, $today]);
        if ($csv) {
            inventoryReportCsv('audit-by-' . $by, [$dims[$by][0], 'Assets', "Audited since {$since}", 'Not audited since', 'Never audited', 'Next audit date passed'],
                array_map(static fn($r) => [$r['gname'] ?? '(none)', $r['total'], $r['audited'], $r['not_audited'], $r['never'], $r['overdue']], $rows));
        }
        $data += ['rows' => $rows, 'totals' => inventoryReportTotals($rows), 'since' => $since, 'months' => $months];
    }

    if ($tab === 'age') {
        [, $idExpr, $nameExpr] = $dims[$by];
        $grid = [];
        foreach ($db->query("SELECT {$idExpr} AS gid, {$nameExpr} AS gname, " . assetAgeCase() . ' AS bucket, COUNT(*) AS n' . INVENTORY_REPORT_FROM . ' GROUP BY gid, gname, bucket') as $r) {
            $k = (string) $r['gid'];
            $grid[$k] ??= ['gid' => $r['gid'], 'gname' => $r['gname'], 'total' => 0];
            $grid[$k][$r['bucket']] = (int) $r['n'];
            $grid[$k]['total'] += (int) $r['n'];
        }
        usort($grid, static fn($x, $y) => [$y['total'], (string) $x['gname']] <=> [$x['total'], (string) $y['gname']]);
        if ($csv) {
            inventoryReportCsv('age-by-' . $by, [$dims[$by][0], 'Assets', ...array_column(ASSET_AGE_BUCKETS, 0)],
                array_map(static fn($r) => [$r['gname'] ?? '(none)', $r['total'], ...array_map(static fn($k) => $r[$k] ?? 0, array_keys(ASSET_AGE_BUCKETS))], $grid));
        }
        $data += [
            'rows' => $grid, 'totals' => inventoryReportTotals($grid),
            'source' => $db->query('SELECT SUM(a.purchase_date IS NOT NULL) AS purchased, SUM(a.purchase_date IS NULL AND a.warranty_start IS NOT NULL) AS warranty FROM assets a WHERE a.deleted_at IS NULL')->fetch(),
        ];
    }

    if ($tab === 'people') {
        $min = max(1, (int) requestScalar('min', '1'));
        $cat = (int) requestScalar('category', '0');
        $s   = $db->prepare(
            "SELECT a.id, a.asset_tag, m.name AS model_name, c.name AS category_name, u.id AS uid,
                    CONCAT(u.first_name, ' ', u.last_name) AS person, u.email, ul.name AS person_location
             FROM assets a JOIN users u ON a.assigned_type = 'user' AND u.id = a.assigned_id
             JOIN asset_models m ON m.id = a.model_id JOIN asset_categories c ON c.id = m.category_id
             LEFT JOIN locations ul ON ul.id = u.location_id
             WHERE a.deleted_at IS NULL" . ($cat > 0 ? ' AND c.id = ?' : '') . ' ORDER BY c.name, a.asset_tag'
        );
        $s->execute($cat > 0 ? [$cat] : []);
        $people = [];
        foreach ($s as $r) {
            $people[$r['uid']] ??= ['uid' => (int) $r['uid'], 'person' => $r['person'], 'email' => $r['email'], 'location' => $r['person_location'], 'assets' => []];
            $people[$r['uid']]['assets'][] = $r;
        }
        $people = array_filter($people, static fn($p) => count($p['assets']) >= $min);
        usort($people, static fn($x, $y) => [count($y['assets']), $x['person']] <=> [count($x['assets']), $y['person']]);
        if ($csv) {
            inventoryReportCsv('checked-out', ['Person', 'Email', label('location.singular'), 'Assets', 'Tags'],
                array_map(static fn($p) => [$p['person'], $p['email'], $p['location'], count($p['assets']),
                    implode(', ', array_map(static fn($a) => $a['asset_tag'] . ' (' . $a['model_name'] . ')', $p['assets']))], $people));
        }
        $data += [
            'people' => $people, 'min' => $min, 'cat' => $cat,
            'categories' => $db->query('SELECT id, name FROM asset_categories ORDER BY name')->fetchAll(),
            'toLocations' => (int) $db->query("SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL AND assigned_type = 'location'")->fetchColumn(),
            'noAccount' => (int) $db->query('SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL AND assigned_type IS NULL AND import_assignee IS NOT NULL')->fetchColumn(),
        ];
    }

    if ($tab === 'activity') {
        $from   = inventoryReportDate('from', date('Y-m-d', strtotime('-30 days')));
        $to     = inventoryReportDate('to', $today);
        $action = preg_match('/^[a-z_]{1,50}$/', requestScalar('action')) ? requestScalar('action') : '';
        $where  = 'h.created_at >= ? AND h.created_at < ? + INTERVAL 1 DAY' . ($action !== '' ? ' AND h.action = ?' : '');
        $params = $action !== '' ? [$from, $to, $action] : [$from, $to];
        $sql    = "SELECT h.*, a.asset_tag, m.name AS model_name, CONCAT(u.first_name, ' ', u.last_name) AS user_name
                   FROM asset_history h JOIN assets a ON a.id = h.asset_id JOIN asset_models m ON m.id = a.model_id
                   LEFT JOIN users u ON u.id = h.user_id WHERE {$where} ORDER BY h.created_at DESC, h.id DESC";
        if ($csv) {
            $s = $db->prepare($sql);
            $s->execute($params);
            $out = [];
            foreach ($s as $h) {
                $d     = $h['details'] ? (json_decode((string) $h['details'], true) ?: []) : [];
                $out[] = [$h['created_at'], $h['asset_tag'], $h['model_name'], $h['action'],
                    html_entity_decode(strip_tags(Assets::historyText((string) $h['action'], $d)), ENT_QUOTES),
                    implode(', ', inventoryHistoryFields($d)), $h['user_name'] ?: $h['actor_name'], $d['note'] ?? ''];
            }
            inventoryReportCsv("activity-{$from}-to-{$to}", ['When', 'Tag', 'Model', 'Action', 'What', 'Fields changed', 'By', 'Note'], $out);
        }
        $count = $db->prepare('SELECT h.action, COUNT(*) AS n FROM asset_history h WHERE h.created_at >= ? AND h.created_at < ? + INTERVAL 1 DAY GROUP BY h.action ORDER BY n DESC');
        $count->execute([$from, $to]);
        $byAction = $count->fetchAll(PDO::FETCH_KEY_PAIR);
        $total    = $action !== '' ? (int) ($byAction[$action] ?? 0) : array_sum($byAction);
        $perPage  = 100;
        $pages    = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($pages, (int) requestScalar('page', '1')));
        $s = $db->prepare($sql . " LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage));
        $s->execute($params);
        $data += ['history' => $s->fetchAll(), 'byAction' => $byAction, 'from' => $from, 'to' => $to, 'action' => $action, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    if ($tab === 'quality') {
        $dupeRows = $db->query(
            "SELECT a.id, a.asset_tag, a.serial, m.name AS model_name, UPPER(TRIM(a.serial)) AS k
             FROM assets a JOIN asset_models m ON m.id = a.model_id
             WHERE a.deleted_at IS NULL AND UPPER(TRIM(a.serial)) IN (
                 SELECT k FROM (SELECT UPPER(TRIM(serial)) AS k FROM assets WHERE deleted_at IS NULL AND TRIM(COALESCE(serial, '')) <> ''
                                GROUP BY k HAVING COUNT(*) > 1) d)
             ORDER BY k, a.asset_tag"
        )->fetchAll();
        $dupes = []; // serial => its assets, biggest group first (placeholders like XXXXX float up)
        foreach ($dupeRows as $r) {
            $dupes[$r['k']][] = $r;
        }
        uasort($dupes, static fn($x, $y) => count($y) <=> count($x));
        $badSerial = [];
        foreach ($db->query("SELECT a.id, a.asset_tag, a.serial, m.name AS model_name, mf.name AS mf FROM assets a
                             JOIN asset_models m ON m.id = a.model_id JOIN asset_manufacturers mf ON mf.id = m.manufacturer_id
                             WHERE a.deleted_at IS NULL AND TRIM(COALESCE(a.serial, '')) <> '' AND (mf.name LIKE '%dell%' OR mf.name LIKE '%lenovo%')
                             ORDER BY a.asset_tag") as $r) {
            if (Warranty::serial(Warranty::vendor($r['mf']), $r['serial']) === null) {
                $badSerial[] = $r;
            }
        }
        $c = $db->query(
            "SELECT SUM(TRIM(COALESCE(a.serial, '')) = '') AS no_serial, SUM(a.location_id IS NULL) AS no_location,
                    SUM(m.manufacturer_id IS NULL) AS no_manufacturer, SUM(a.purchase_date IS NULL) AS no_purchase_date,
                    SUM(a.assigned_type IS NULL AND a.import_assignee IS NOT NULL) AS no_account, SUM(a.warranty_error IS NOT NULL) AS lookup_failed,
                    COUNT(*) AS total
             FROM assets a JOIN asset_models m ON m.id = a.model_id WHERE a.deleted_at IS NULL"
        )->fetch();
        if ($csv) {
            inventoryReportCsv('duplicate-serials', ['Serial', 'Tag', 'Model'], array_map(static fn($r) => [$r['serial'], $r['asset_tag'], $r['model_name']], $dupeRows));
        }
        $data += ['dupes' => $dupes, 'badSerial' => $badSerial, 'counts' => $c];
    }

    if ($tab === 'digest') {
        $s = AssetDigest::settings();
        $data += [
            'digest'     => $s,
            'preview'    => AssetDigest::data($db, $s['days'], $s['last_sent'] !== '' ? $s['last_sent'] : date('Y-m-d', strtotime('-30 days')), $today),
            'recipients' => AssetDigest::recipients($db, $s),
            'next'       => !$s['enabled'] ? null : (AssetDigest::isDue($s, $today) ? $today : AssetDigest::nextSlot($s, $today)),
        ];
    }

    render('inventory/reports', $data);
});

/** Save the digest schedule, or (action=test) email the digest to yourself now. */
$router->post('/inventory/reports/digest', function () {
    Auth::requirePermission('assets.manage');
    $back = '/inventory/reports?tab=digest';
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect($back);
    }
    $db    = Database::connect();
    $today = date('Y-m-d');
    if (($_POST['action'] ?? '') === 'test') {
        $me = Auth::user();
        $r  = AssetDigest::send($db, AssetDigest::settings(), $today, [[(string) $me['email'], trim($me['first_name'] . ' ' . $me['last_name'])]]);
        flash($r['sent'] ? 'success' : 'error', $r['sent']
            ? 'Test digest sent to ' . $me['email'] . ' using the saved settings. The schedule is unchanged.'
            : 'The test digest could not be sent — check Settings › Email.');
        redirect($back);
    }
    $before  = AssetDigest::settings();
    $enabled = isset($_POST['enabled']);
    $weekly  = ($_POST['frequency'] ?? '') === 'weekly';
    $extra   = preg_split('/[\s,;]+/', (string) ($_POST['recipients'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    $bad     = array_filter($extra, static fn($e) => !filter_var($e, FILTER_VALIDATE_EMAIL));
    $after   = [
        'asset_digest_enabled'    => $enabled ? '1' : '0',
        'asset_digest_frequency'  => $weekly ? 'weekly' : 'monthly',
        'asset_digest_day'        => (string) ($weekly ? max(1, min(7, (int) ($_POST['weekday'] ?? 1))) : max(1, min(28, (int) ($_POST['monthday'] ?? 1)))),
        'asset_digest_days'       => (string) max(1, min(730, (int) ($_POST['days'] ?? 90))),
        'asset_digest_admins'     => isset($_POST['admins']) ? '1' : '0',
        'asset_digest_recipients' => implode(', ', array_diff($extra, $bad)),
    ];
    foreach ($after as $k => $v) {
        setSetting($k, $v);
    }
    // Switching it on starts the clock today, so the first digest is the next scheduled day — not tomorrow morning.
    if ($enabled && (!$before['enabled'] || $before['last_sent'] === '')) {
        setSetting('asset_digest_last_sent', $today);
    }
    logAudit('asset.digest_settings_changed', null, null, http_build_query($after, '', '; '));
    if ($bad) {
        flash('error', 'Saved, but these are not email addresses and were left out: ' . implode(', ', $bad));
    } elseif ($enabled && AssetDigest::recipients($db, AssetDigest::settings()) === []) {
        flash('error', 'Saved, but nobody is set to receive the digest — tick administrators or add an address.');
    } else {
        flash('success', $enabled ? 'Digest schedule saved.' : 'Digest saved and switched off.');
    }
    redirect($back);
});

/**
 * One batch of the Warranty report's "Look up all": the browser posts ids in
 * slices of 10 and shows progress. Ids that are not live Dell/Lenovo assets
 * with a serial are ignored by Warranty::refreshIds().
 */
$router->post('/inventory/warranty/scan', function () {
    Auth::requirePermission('assets.manage');
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Session expired — reload the page.']);
        exit;
    }
    $userId = Auth::id();
    session_write_close(); // a batch takes ~20 s; don't freeze the user's other tabs
    @set_time_limit(300);
    $lines = [];
    $n = Warranty::refreshIds(Database::connect(), array_slice((array) ($_POST['ids'] ?? []), 0, 25), $userId,
        static function (string $m) use (&$lines): void { $lines[] = trim($m); });
    echo json_encode($n + ['lines' => $lines]);
    exit;
});
