<?php

declare(strict_types=1);

require_once __DIR__ . '/Assets.php';

/**
 * CSV import for the inventory (Inventory › Import): read the file, guess
 * which column feeds which field, work out what each row would do (plan),
 * then do it (apply). plan() writes nothing, so the preview page and the
 * confirm step run the very same code against the current database.
 */
final class AssetImport
{
    public const MAX_ROWS = 5000;

    /** Field key => [label, hint, header names that auto-match (lowercase)]. */
    public static function fields(): array
    {
        return [
            'asset_tag'       => ['Asset tag', 'Blank = the next automatic tag', ['asset tag', 'tag', 'asset_tag', 'asset #', 'asset number']],
            'name'            => ['Name', 'Device or host name', ['name', 'asset name', 'device name', 'hostname', 'host name', 'computer name']],
            'serial'          => ['Serial number', '', ['serial', 'serial number', 'serial_number', 'serial no', 'serial no.', 'service tag']],
            'model'           => ['Model', 'Matched by name or model number; created if new', ['model', 'model name']],
            'model_number'    => ['Model number', '', ['model no.', 'model no', 'model number', 'model_number']],
            'category'        => ['Category', 'Needed only to create a new model', ['category']],
            'manufacturer'    => ['Manufacturer', 'Used only when creating a new model', ['manufacturer', 'make', 'brand']],
            'status'          => ['Status', 'Matched by name, else the default status', ['status']],
            'location'        => [label('location.singular'), 'Matched by name', ['location', 'branch', 'site', strtolower(label('location.singular'))]],
            'supplier'        => ['Supplier', 'Created if new', ['supplier', 'vendor']],
            'order_number'    => ['Order number', '', ['order number', 'order_number', 'order no', 'order #', 'po', 'po number']],
            'purchase_date'   => ['Purchase date', 'YYYY-MM-DD is safest', ['purchase date', 'purchase_date', 'purchased', 'date purchased']],
            'purchase_cost'   => ['Purchase cost', '', ['cost', 'purchase cost', 'purchase_cost', 'price']],
            'warranty_months' => ['Warranty (months)', '', ['warranty (months)', 'warranty months', 'warranty_months', 'warranty']],
            'eol_date'        => ['End of life date', '', ['eol', 'eol date', 'eol_date', 'end of life']],
            'notes'           => ['Notes', '', ['notes', 'note', 'comments']],
        ];
    }

    /**
     * Read a CSV file: [headers, rows keyed by header] or a string saying why not.
     * Blank lines are dropped; a UTF-8 BOM on the first header is ignored.
     */
    public static function parse(string $path): array|string
    {
        $h = @fopen($path, 'r');
        if (!$h) {
            return 'Could not read the uploaded file.';
        }
        $headers = fgetcsv($h, null, ',', '"', '');
        if (!$headers || trim(implode('', array_map('strval', $headers))) === '') {
            fclose($h);
            return 'The file is empty or has no header row.';
        }
        $headers = array_map(static fn($x) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $x)), $headers);
        $rows = [];
        while (($r = fgetcsv($h, null, ',', '"', '')) !== false) {
            if (trim(implode('', array_map('strval', $r))) === '') {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $col) {
                if ($col !== '') {
                    $row[$col] = trim((string) ($r[$i] ?? ''));
                }
            }
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS) {
                fclose($h);
                return 'The file has more than ' . number_format(self::MAX_ROWS) . ' rows. Split it and import each part.';
            }
        }
        fclose($h);
        return $rows === [] ? 'No data rows found under the header row.' : [array_values(array_filter($headers, static fn($c) => $c !== '')), $rows];
    }

    /** Field key => the CSV header that looks like it, or null. */
    public static function autoMap(array $headers): array
    {
        $lower = array_map(static fn($x) => strtolower(trim((string) $x)), $headers);
        $map   = [];
        foreach (self::fields() as $key => [, , $aliases]) {
            $map[$key] = null;
            foreach ($aliases as $alias) {
                if (($i = array_search($alias, $lower, true)) !== false) {
                    $map[$key] = $headers[$i];
                    break;
                }
            }
        }
        return $map;
    }

    /** A date in any form strtotime() reads → YYYY-MM-DD, or null. */
    private static function date(string $v): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return $v;
        }
        $t = strtotime($v);
        return $t === false ? null : date('Y-m-d', $t);
    }

    /**
     * Work out what every row would do, without writing anything.
     *
     * $map  field key => CSV header ('' / missing = not mapped)
     * $opts default_status_id (int), existing ('skip'|'update': rows whose tag is already in use)
     *
     * Returns rows [{line, action: create|update|skip|error, why, tag, id, show{}, set{}, model, supplier, warn[]}],
     * counts per action, and the catalog entries a confirm would create.
     * `model` / `supplier` are an id, or for new ones the key into new[models] / the supplier name.
     */
    public static function plan(PDO $db, array $rows, array $map, array $opts): array
    {
        $lc  = static fn(string $s): string => mb_strtolower(trim($s));
        $ids = static function (string $sql) use ($db, $lc): array {
            $out = [];
            foreach ($db->query($sql, PDO::FETCH_NUM) as $r) {
                $out[$lc((string) $r[1])] ??= (int) $r[0];
            }
            return $out;
        };
        $statuses  = $ids('SELECT id, name FROM asset_statuses ORDER BY id');
        $locations = $ids('SELECT id, name FROM locations ORDER BY id');
        $suppliers = $ids('SELECT id, name FROM asset_suppliers ORDER BY id');
        $models    = []; // lowercase name or model number => [[id, lowercase category]]
        foreach ($db->query('SELECT m.id, m.name, m.model_number, c.name AS category FROM asset_models m JOIN asset_categories c ON c.id = m.category_id ORDER BY m.id') as $m) {
            foreach (array_unique(array_filter([$lc((string) $m['name']), $lc((string) $m['model_number'])])) as $k) {
                $models[$k][] = [(int) $m['id'], $lc((string) $m['category'])];
            }
        }
        $tags = []; // lowercase tag => [id, deleted?]
        foreach ($db->query('SELECT id, asset_tag, deleted_at FROM assets') as $a) {
            $tags[$lc((string) $a['asset_tag'])] = [(int) $a['id'], $a['deleted_at'] !== null];
        }
        $serials = []; // lowercase serial => asset id (live only)
        foreach ($db->query("SELECT id, serial FROM assets WHERE deleted_at IS NULL AND TRIM(COALESCE(serial, '')) <> ''") as $a) {
            $serials[$lc((string) $a['serial'])] = (int) $a['id'];
        }

        $updateExisting = ($opts['existing'] ?? 'skip') === 'update';
        $defaultStatus  = (int) ($opts['default_status_id'] ?? 0);
        $labels  = self::fields();
        $out     = [];
        $counts  = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];
        $new     = ['models' => [], 'suppliers' => []];
        $seenTag = [];

        foreach ($rows as $i => $raw) {
            $get = static function (string $key) use ($raw, $map): string {
                $col = (string) ($map[$key] ?? '');
                return $col !== '' ? trim((string) ($raw[$col] ?? '')) : '';
            };
            $row = ['line' => $i + 2, 'action' => 'create', 'why' => '', 'tag' => $get('asset_tag'), 'id' => null,
                    'show' => [], 'set' => [], 'model' => null, 'supplier' => null, 'warn' => []];
            $fail = static function (string $why) use (&$row): void {
                $row['action'] = 'error';
                $row['why']    = $why;
            };

            $tagKey = $lc($row['tag']);
            if ($tagKey !== '') {
                if (isset($seenTag[$tagKey])) {
                    $fail('Tag ' . $row['tag'] . ' is also on line ' . $seenTag[$tagKey] . ' of this file');
                } elseif (isset($tags[$tagKey])) {
                    [$row['id'], $isDeleted] = $tags[$tagKey];
                    if ($isDeleted) {
                        $fail('Tag ' . $row['tag'] . ' belongs to a deleted asset — restore it or use another tag');
                    } elseif ($updateExisting) {
                        $row['action'] = 'update';
                    } else {
                        $row['action'] = 'skip';
                        $row['why']    = 'Tag already exists';
                    }
                }
                $seenTag[$tagKey] ??= $row['line'];
            }
            $isUpdate = $row['action'] === 'update';

            // Plain values. On an update a blank cell leaves the asset's value alone.
            foreach (['name' => 255, 'serial' => 255, 'order_number' => 100, 'notes' => 65000] as $f => $max) {
                if (($v = $get($f)) !== '') {
                    $row['set'][$f] = mb_substr($v, 0, $max);
                }
            }
            foreach (['purchase_date', 'eol_date'] as $f) {
                if (($v = $get($f)) !== '') {
                    $d = self::date($v);
                    if ($d === null) {
                        $row['warn'][] = $labels[$f][0] . ' "' . $v . '" is not a date — left blank';
                    } else {
                        $row['set'][$f] = $d;
                    }
                }
            }
            if (($v = $get('purchase_cost')) !== '') {
                $num = preg_replace('/[^0-9.\-]/', '', $v);
                if (is_numeric($num)) {
                    $row['set']['purchase_cost'] = number_format((float) $num, 2, '.', '');
                } else {
                    $row['warn'][] = 'Cost "' . $v . '" is not a number — left blank';
                }
            }
            if (($v = $get('warranty_months')) !== '') {
                if (preg_match('/\d+/', $v, $m)) {
                    $row['set']['warranty_months'] = (int) $m[0];
                } else {
                    $row['warn'][] = 'Warranty "' . $v . '" is not a number of months — left blank';
                }
            }
            if (isset($row['set']['serial'], $serials[$lc($row['set']['serial'])]) && $serials[$lc($row['set']['serial'])] !== $row['id']) {
                $row['warn'][] = 'Serial ' . $row['set']['serial'] . ' is already on another asset';
            }

            // Status and location: an id, else the default (new assets) or left alone (updates).
            $status = $get('status');
            if ($status !== '' && isset($statuses[$lc($status)])) {
                $row['set']['status_id'] = $statuses[$lc($status)];
            } else {
                if ($status !== '') {
                    $row['warn'][] = 'Status "' . $status . '" does not exist — ' . ($isUpdate ? 'left as it is' : 'using the default');
                }
                if (!$isUpdate) {
                    $row['set']['status_id'] = $defaultStatus;
                }
            }
            $location = $get('location');
            if ($location !== '') {
                if (isset($locations[$lc($location)])) {
                    $row['set']['location_id'] = $locations[$lc($location)];
                    if (!$isUpdate) {
                        $row['set']['default_location_id'] = $locations[$lc($location)];
                    }
                } else {
                    $row['warn'][] = $labels['location'][0] . ' "' . $location . '" does not exist — left blank';
                }
            }
            $supplier = $get('supplier');
            if ($supplier !== '') {
                $row['supplier'] = $suppliers[$lc($supplier)] ?? $supplier;
            }

            // Model: by name or model number (same category wins when the name is shared), else a new one.
            $model = $get('model') !== '' ? $get('model') : $get('model_number');
            if ($model !== '') {
                $cat  = $lc($get('category'));
                $hits = $models[$lc($model)] ?? ($models[$lc($get('model_number'))] ?? []);
                if ($hits !== []) {
                    $row['model'] = $hits[0][0];
                    foreach ($hits as [$mid, $mcat]) {
                        if ($cat !== '' && $mcat === $cat) {
                            $row['model'] = $mid;
                            break;
                        }
                    }
                } elseif ($cat === '') {
                    $fail('Model "' . $model . '" is not in the catalog, and the row has no category to create it under');
                } else {
                    $key = $lc($model) . '|' . $cat;
                    $new['models'][$key] ??= ['name' => $model, 'model_number' => $get('model') !== '' ? $get('model_number') : '',
                                              'category' => $get('category'), 'manufacturer' => $get('manufacturer')];
                    $row['model'] = $key;
                }
            } elseif ($row['action'] === 'create') {
                $fail('No model');
            }

            $row['show'] = ['name' => $get('name'), 'serial' => $get('serial'), 'model' => $model, 'status' => $status, 'location' => $location];
            $counts[$row['action']]++;
            $out[] = $row;
        }

        // Only catalog entries that a created/updated row actually uses get made.
        $usedModels = [];
        foreach ($out as $r) {
            if ($r['action'] !== 'create' && $r['action'] !== 'update') {
                continue;
            }
            if (is_string($r['model'])) {
                $usedModels[$r['model']] = true;
            }
            if (is_string($r['supplier'])) {
                $new['suppliers'][$lc($r['supplier'])] = $r['supplier'];
            }
        }
        $new['models'] = array_intersect_key($new['models'], $usedModels);

        return ['rows' => $out, 'counts' => $counts, 'new' => $new,
                'warnings' => count(array_filter($out, static fn($r) => $r['warn'] !== [] && $r['action'] !== 'error' && $r['action'] !== 'skip'))];
    }

    /**
     * Carry out a plan(). The caller owns the transaction (so a failure part
     * way rolls everything back). Returns [created, updated].
     */
    public static function apply(PDO $db, array $plan, ?int $userId): array
    {
        $findOrCreate = static function (string $table, string $name) use ($db): int {
            $s = $db->prepare("SELECT id FROM `{$table}` WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $s->execute([$name]);
            if (($id = $s->fetchColumn()) !== false) {
                return (int) $id;
            }
            $db->prepare("INSERT INTO `{$table}` (name) VALUES (?)")->execute([$name]);
            return (int) $db->lastInsertId();
        };
        $created  = $updated = 0;
        $modelIds = [];
        foreach ($plan['new']['models'] as $key => $m) {
            $db->prepare('INSERT INTO asset_models (name, model_number, category_id, manufacturer_id) VALUES (?, ?, ?, ?)')->execute([
                $m['name'], $m['model_number'] !== '' ? $m['model_number'] : null, $findOrCreate('asset_categories', $m['category']),
                $m['manufacturer'] !== '' ? $findOrCreate('asset_manufacturers', $m['manufacturer']) : null,
            ]);
            $modelIds[$key] = (int) $db->lastInsertId();
        }
        // Rows that bring their own tag go first, so an automatic tag can never take one the file uses further down.
        $rows = $plan['rows'];
        usort($rows, static fn($x, $y) => [$x['tag'] === '', $x['line']] <=> [$y['tag'] === '', $y['line']]);
        foreach ($rows as $r) {
            if ($r['action'] !== 'create' && $r['action'] !== 'update') {
                continue;
            }
            $set = $r['set'];
            if ($r['model'] !== null) {
                $set['model_id'] = is_int($r['model']) ? $r['model'] : $modelIds[$r['model']];
            }
            if ($r['supplier'] !== null) {
                $set['supplier_id'] = is_int($r['supplier']) ? $r['supplier'] : $findOrCreate('asset_suppliers', $r['supplier']);
            }
            if ($r['action'] === 'update') {
                $updated += Assets::bulkUpdate($db, [(int) $r['id']], $set, $userId);
                continue;
            }
            $set['asset_tag']  = $r['tag'] !== '' ? $r['tag'] : Assets::nextTag($db);
            $set['created_by'] = $userId;
            $cols = array_keys($set);
            $db->prepare('INSERT INTO assets (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
               ->execute(array_values($set));
            Assets::logHistory($db, (int) $db->lastInsertId(), 'create', ['import' => 'csv'], $userId);
            $created++;
        }
        return [$created, $updated];
    }
}
