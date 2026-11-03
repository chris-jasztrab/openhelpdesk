<?php

declare(strict_types=1);

/**
 * Snipe-IT → asset inventory mapper.
 *
 * Reads a Snipe-IT database (the backup's SQL dump, loaded into a staging
 * schema by scripts/import-snipeit.php) and upserts it into the asset_* tables.
 * Every write is keyed on `snipeit_id` (or, for the catalog, the name) so a
 * re-run only fills gaps and refreshes changed fields — it never duplicates.
 *
 * Mapping rules that are not one-to-one:
 *   - Snipe users have no emails (LDAP import); a user is matched as
 *     `<username>@<--user-domain>`, or via --alias-user. Unmatched assignees
 *     leave the asset unassigned with the Snipe username in `import_assignee`.
 *   - A user-assigned asset takes the matched user's helpdesk location when
 *     they have one, because Snipe parked every LDAP user at head office.
 *   - Snipe locations match local ones by name (case-insensitive) or via
 *     --alias-location; soft-deleted Snipe locations are skipped.
 *   - "Deployed" is not a status here: it is a deployable status plus an
 *     assignee, exactly as Snipe computes it.
 *
 * Runs inside the caller's transaction; the caller decides commit/rollback.
 */
class SnipeItImport
{
    private const ACTIONS = [
        'checkin from' => 'checkin',
        'uploaded'     => 'file_uploaded',
    ];

    /** @var array<string,int> */
    public array $counts = [];
    /** @var list<string> */
    public array $warnings = [];
    /** @var array<string,int> Snipe username => assets left unassigned */
    public array $unmatchedUsers = [];
    /** @var array<string,int> local location name => assets placed there */
    public array $byLocation = [];
    /** @var list<array{0:string,1:string}> [from, to] files to copy (copied immediately when $apply) */
    public array $files = [];

    private array $statusMap = [];
    private array $categoryMap = [];
    private array $manufacturerMap = [];
    private array $supplierMap = [];
    private array $modelMap = [];
    private array $locationMap = [];   // snipe location id => local id|null
    private array $locationName = [];  // local id => name
    private array $snipeLocationName = []; // snipe id => name
    private array $userMap = [];       // snipe user id => local user id|null
    private array $userName = [];      // snipe user id => username
    private array $userLocation = [];  // local user id => location_id|null
    private array $assetMap = [];      // snipe asset id => local id

    /**
     * @param array{user_domain?:string, alias_location?:array<string,string>, alias_user?:array<string,string>, source_dir?:string, apply?:bool} $opt
     */
    public function __construct(private PDO $db, private PDO $src, private array $opt = [])
    {
    }

    public function run(): void
    {
        $this->statuses();
        $this->catalog();
        $this->locations();
        $this->users();
        $this->assets();
        $this->history();
        $this->tagSettings();
    }

    // ── Catalog ──────────────────────────────────────────────────────────────

    private function statuses(): void
    {
        foreach ($this->src->query('SELECT * FROM status_labels WHERE deleted_at IS NULL ORDER BY id') as $s) {
            $kind = $s['deployable'] ? 'deployable' : ($s['pending'] ? 'pending' : ($s['archived'] ? 'archived' : 'undeployable'));
            $color = $s['color'] ?: ['deployable' => '#198754', 'pending' => '#fd7e14', 'archived' => '#6c757d', 'undeployable' => '#dc3545'][$kind];
            $this->statusMap[(int) $s['id']] = $this->upsertByName('asset_statuses', (int) $s['id'], (string) $s['name'], [
                'kind' => $kind, 'color' => $color, 'notes' => $s['notes'], 'sort_order' => (int) $s['id'],
            ]);
        }
    }

    private function catalog(): void
    {
        $live = 'deleted_at IS NULL OR id IN (%s)';
        $q = sprintf("SELECT * FROM categories WHERE $live ORDER BY id", 'SELECT category_id FROM models WHERE id IN (SELECT model_id FROM assets)');
        foreach ($this->src->query($q) as $c) {
            $this->categoryMap[(int) $c['id']] = $this->upsertByName('asset_categories', (int) $c['id'], (string) $c['name'], []);
        }
        $q = sprintf("SELECT * FROM manufacturers WHERE $live ORDER BY id", 'SELECT manufacturer_id FROM models WHERE id IN (SELECT model_id FROM assets)');
        foreach ($this->src->query($q) as $m) {
            $this->manufacturerMap[(int) $m['id']] = $this->upsertByName('asset_manufacturers', (int) $m['id'], (string) $m['name'], [
                'url' => $m['url'], 'support_url' => $m['support_url'], 'support_phone' => $m['support_phone'], 'support_email' => $m['support_email'],
            ]);
        }
        $q = sprintf("SELECT * FROM suppliers WHERE $live ORDER BY id", 'SELECT supplier_id FROM assets WHERE supplier_id IS NOT NULL');
        foreach ($this->src->query($q) as $s) {
            $address = implode(', ', array_filter([$s['address'], $s['address2'], $s['city'], $s['state'], $s['zip'], $s['country']]));
            $this->supplierMap[(int) $s['id']] = $this->upsertByName('asset_suppliers', (int) $s['id'], (string) $s['name'], [
                'contact' => $s['contact'], 'phone' => $s['phone'], 'email' => $s['email'], 'url' => $s['url'],
                'address' => $address ?: null, 'notes' => $s['notes'],
            ]);
        }
        $q = sprintf("SELECT * FROM models WHERE $live ORDER BY id", 'SELECT model_id FROM assets');
        foreach ($this->src->query($q) as $m) {
            $catId = $this->categoryMap[(int) $m['category_id']] ?? null;
            if ($catId === null) {
                $this->warnings[] = "Model {$m['name']} (#{$m['id']}) has no category — skipped, and so are its assets";
                continue;
            }
            $image = $m['image'] ? $this->stageFile('public/uploads/models/' . $m['image'], 'models/' . $m['image']) : null;
            $this->modelMap[(int) $m['id']] = $this->upsert('asset_models', 'snipeit_id', (int) $m['id'], [
                'name' => $m['name'], 'model_number' => $m['model_number'], 'category_id' => $catId,
                'manufacturer_id' => $this->manufacturerMap[(int) $m['manufacturer_id']] ?? null,
                'eol_months' => (int) $m['eol'] > 0 ? (int) $m['eol'] : null,
                'image' => $image, 'notes' => $m['notes'], 'snipeit_id' => (int) $m['id'],
            ]);
        }
    }

    // ── Locations & users ────────────────────────────────────────────────────

    private function locations(): void
    {
        $local = [];
        foreach ($this->db->query('SELECT id, name FROM locations') as $l) {
            $local[self::key((string) $l['name'])] = (int) $l['id'];
            $this->locationName[(int) $l['id']] = (string) $l['name'];
        }
        $aliases = [];
        foreach ($this->opt['alias_location'] ?? [] as $from => $to) {
            $aliases[self::key($from)] = self::key($to);
        }
        foreach ($this->src->query('SELECT id, name, deleted_at FROM locations ORDER BY id') as $l) {
            $id = (int) $l['id'];
            $this->snipeLocationName[$id] = (string) $l['name'];
            if ($l['deleted_at'] !== null) {
                $this->locationMap[$id] = null;
                $this->counts['locations_deleted_skipped'] = ($this->counts['locations_deleted_skipped'] ?? 0) + 1;
                continue;
            }
            $k = self::key((string) $l['name']);
            $k = $aliases[$k] ?? $k;
            $this->locationMap[$id] = $local[$k] ?? null;
            if ($this->locationMap[$id] === null) {
                $this->warnings[] = "Snipe location \"{$l['name']}\" has no match here — pass --alias-location=\"{$l['name']}=<local name>\"; its assets get no location";
            }
        }
    }

    private function users(): void
    {
        $domain = strtolower(trim((string) ($this->opt['user_domain'] ?? '')));
        $aliases = array_change_key_case($this->opt['alias_user'] ?? [], CASE_LOWER);
        $find = $this->db->prepare('SELECT id, location_id FROM users WHERE LOWER(email) = ? LIMIT 1');
        foreach ($this->src->query('SELECT id, username, email, deleted_at FROM users ORDER BY id') as $u) {
            $username = strtolower(trim((string) $u['username']));
            $this->userName[(int) $u['id']] = $username;
            $email = $aliases[$username] ?? ($domain !== '' && $username !== '' ? "$username@$domain" : strtolower(trim((string) $u['email'])));
            $this->userMap[(int) $u['id']] = null;
            if ($email === '') {
                continue;
            }
            $find->execute([$email]);
            $row = $find->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->userMap[(int) $u['id']] = (int) $row['id'];
                $this->userLocation[(int) $row['id']] = $row['location_id'] !== null ? (int) $row['location_id'] : null;
            }
        }
    }

    // ── Assets ───────────────────────────────────────────────────────────────

    private function assets(): void
    {
        $tagOwner = $this->db->prepare('SELECT id, snipeit_id FROM assets WHERE asset_tag = ?');
        $assignedAssets = [];

        foreach ($this->src->query('SELECT * FROM assets ORDER BY id') as $a) {
            $sid = (int) $a['id'];
            $modelId = $this->modelMap[(int) $a['model_id']] ?? null;
            $statusId = $this->statusMap[(int) $a['status_id']] ?? null;
            if ($modelId === null || $statusId === null) {
                $this->warnings[] = "Asset {$a['asset_tag']} (#$sid) references a missing model or status — skipped";
                $this->counts['assets_skipped'] = ($this->counts['assets_skipped'] ?? 0) + 1;
                continue;
            }
            $tagOwner->execute([$a['asset_tag']]);
            $owner = $tagOwner->fetch(PDO::FETCH_ASSOC);
            if ($owner && (int) $owner['snipeit_id'] !== $sid) {
                $this->warnings[] = "Asset tag {$a['asset_tag']} already belongs to a non-imported asset #{$owner['id']} — Snipe asset #$sid skipped";
                $this->counts['assets_skipped'] = ($this->counts['assets_skipped'] ?? 0) + 1;
                continue;
            }

            $location = $this->locationMap[(int) $a['location_id']] ?? null;
            $assignedType = null;
            $assignedId = null;
            $importAssignee = null;
            $type = substr((string) $a['assigned_type'], strrpos((string) $a['assigned_type'], '\\') + 1);
            if ($a['assigned_to'] !== null && $type === 'User') {
                $uid = $this->userMap[(int) $a['assigned_to']] ?? null;
                if ($uid !== null) {
                    $assignedType = 'user';
                    $assignedId = $uid;
                    $location = $this->userLocation[$uid] ?? $location;
                } else {
                    $importAssignee = $this->userName[(int) $a['assigned_to']] ?? ('snipe-user-' . $a['assigned_to']);
                    $this->unmatchedUsers[$importAssignee] = ($this->unmatchedUsers[$importAssignee] ?? 0) + 1;
                }
            } elseif ($a['assigned_to'] !== null && $type === 'Location') {
                $lid = $this->locationMap[(int) $a['assigned_to']] ?? null;
                if ($lid !== null) {
                    $assignedType = 'location';
                    $assignedId = $lid;
                    $location = $lid;
                } else {
                    $importAssignee = 'location: ' . ($this->snipeLocationName[(int) $a['assigned_to']] ?? ('#' . $a['assigned_to']));
                    $this->unmatchedUsers[$importAssignee] = ($this->unmatchedUsers[$importAssignee] ?? 0) + 1;
                }
            } elseif ($a['assigned_to'] !== null && $type === 'Asset') {
                $assignedAssets[$sid] = (int) $a['assigned_to']; // resolved after every asset has an id
            }

            $image = $a['image'] ? $this->stageFile('public/uploads/assets/' . $a['image'], 'images/' . $a['image']) : null;
            $row = [
                'asset_tag' => $a['asset_tag'], 'name' => $a['name'] ?: null, 'serial' => $a['serial'] ?: null,
                'model_id' => $modelId, 'status_id' => $statusId,
                'location_id' => $location, 'default_location_id' => $this->locationMap[(int) $a['rtd_location_id']] ?? null,
                'assigned_type' => $assignedType, 'assigned_id' => $assignedId, 'import_assignee' => $importAssignee,
                'supplier_id' => $this->supplierMap[(int) $a['supplier_id']] ?? null,
                'order_number' => $a['order_number'] ?: null, 'purchase_date' => $a['purchase_date'] ?: null,
                'purchase_cost' => $a['purchase_cost'] !== null ? (float) $a['purchase_cost'] : null,
                'warranty_months' => $a['warranty_months'] !== null ? (int) $a['warranty_months'] : null,
                'eol_date' => $a['asset_eol_date'] ?: null,
                'last_checkout_at' => $a['last_checkout'] ?: null, 'last_checkin_at' => $a['last_checkin'] ?: null,
                'last_audit_at' => $a['last_audit_date'] ?: null, 'next_audit_date' => $a['next_audit_date'] ?: null,
                'image' => $image, 'notes' => $a['notes'] ?: null,
                'created_by' => $this->userMap[(int) $a['user_id']] ?? null,
                'snipeit_id' => $sid, 'deleted_at' => $a['deleted_at'], 'created_at' => $a['created_at'] ?? null,
            ];
            $this->assetMap[$sid] = $this->upsert('assets', 'snipeit_id', $sid, $row);
            $bucket = $a['deleted_at'] === null ? 'assets' : 'assets_deleted';
            $this->counts[$bucket] = ($this->counts[$bucket] ?? 0) + 1;
            if ($a['deleted_at'] === null) {
                $name = $location !== null ? $this->locationName[$location] : '(none)';
                $this->byLocation[$name] = ($this->byLocation[$name] ?? 0) + 1;
            }
        }

        $upd = $this->db->prepare("UPDATE assets SET assigned_type = 'asset', assigned_id = ? WHERE id = ?");
        foreach ($assignedAssets as $sid => $parentSid) {
            if (isset($this->assetMap[$sid], $this->assetMap[$parentSid])) {
                $upd->execute([$this->assetMap[$parentSid], $this->assetMap[$sid]]);
            }
        }
        $this->counts['assets_assigned_to_users']     = (int) $this->db->query("SELECT COUNT(*) FROM assets WHERE snipeit_id IS NOT NULL AND assigned_type = 'user'")->fetchColumn();
        $this->counts['assets_assigned_to_locations'] = (int) $this->db->query("SELECT COUNT(*) FROM assets WHERE snipeit_id IS NOT NULL AND assigned_type = 'location'")->fetchColumn();
        $this->counts['assets_with_import_assignee']  = (int) $this->db->query('SELECT COUNT(*) FROM assets WHERE snipeit_id IS NOT NULL AND import_assignee IS NOT NULL')->fetchColumn();
    }

    // ── History & files ──────────────────────────────────────────────────────

    private function history(): void
    {
        $done = [];
        foreach ($this->db->query("SELECT details FROM asset_history WHERE details LIKE '%\"snipe_log_id\":%'") as $r) {
            $d = json_decode((string) $r['details'], true);
            if (isset($d['snipe_log_id'])) {
                $done[(int) $d['snipe_log_id']] = true;
            }
        }
        $fileExists = $this->db->prepare('SELECT 1 FROM asset_files WHERE stored_name = ?');
        $q = "SELECT * FROM action_logs WHERE item_type LIKE '%Asset' AND deleted_at IS NULL ORDER BY COALESCE(action_date, created_at), id";
        foreach ($this->src->query($q) as $l) {
            $assetId = $this->assetMap[(int) $l['item_id']] ?? null;
            if ($assetId === null) {
                continue;
            }
            if (isset($done[(int) $l['id']])) {
                $this->counts['history_already_present'] = ($this->counts['history_already_present'] ?? 0) + 1;
                continue;
            }
            $actorId = $this->userMap[(int) $l['user_id']] ?? null;
            $actorName = $actorId === null ? ($this->userName[(int) $l['user_id']] ?? null) : null;
            $details = ['snipe_log_id' => (int) $l['id']];
            if ($l['note'] !== null && trim((string) $l['note']) !== '') {
                $details['note'] = trim((string) $l['note']);
            }
            $targetType = substr((string) $l['target_type'], strrpos((string) $l['target_type'], '\\') + 1);
            if ($l['target_id'] !== null && $targetType === 'User') {
                $details['target'] = ['type' => 'user', 'id' => $this->userMap[(int) $l['target_id']] ?? null, 'name' => $this->userName[(int) $l['target_id']] ?? null];
            } elseif ($l['target_id'] !== null && $targetType === 'Location') {
                $lid = $this->locationMap[(int) $l['target_id']] ?? null;
                $details['target'] = ['type' => 'location', 'id' => $lid, 'name' => $lid !== null ? $this->locationName[$lid] : null];
            }
            $meta = $l['log_meta'] ? json_decode((string) $l['log_meta'], true) : null;
            if (is_array($meta) && $meta !== []) {
                $details['changes'] = $meta;
            }
            if ($l['filename']) {
                $details['filename'] = $l['filename'];
            }
            $when = $l['action_date'] ?: $l['created_at'];
            Assets::logHistory($this->db, $assetId, self::ACTIONS[$l['action_type']] ?? (string) $l['action_type'], $details, $actorId, $actorName, $when);
            $this->counts['history'] = ($this->counts['history'] ?? 0) + 1;

            if ($l['action_type'] === 'uploaded' && $l['filename']) {
                $stored = (string) $l['filename'];
                $fileExists->execute([$stored]);
                if ($fileExists->fetchColumn() !== false) {
                    continue;
                }
                $from = ($this->opt['source_dir'] ?? '') . '/storage/private_uploads/assets/' . $stored;
                if (!is_file($from)) {
                    $this->warnings[] = "Uploaded file $stored is not in the backup — history row kept, file skipped";
                    continue;
                }
                $this->stageFile('storage/private_uploads/assets/' . $stored, 'files/' . $stored);
                $this->db->prepare(
                    'INSERT INTO asset_files (asset_id, uploaded_by, original_name, stored_name, mime_type, file_size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $assetId, $actorId, self::originalName($stored), $stored,
                    (string) (mime_content_type($from) ?: 'application/octet-stream'), (int) filesize($from), $when,
                ]);
                $this->counts['files'] = ($this->counts['files'] ?? 0) + 1;
            }
        }
    }

    private function tagSettings(): void
    {
        $tags = $this->src->query("SELECT asset_tag FROM assets WHERE asset_tag REGEXP '^[0-9]+$'")->fetchAll(PDO::FETCH_COLUMN);
        $s = self::tagSettingsFor($tags);
        if ($s === null) {
            return;
        }
        $cur = (int) ($this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'asset_tag_next'")->fetchColumn() ?: 1);
        $set = $this->db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $set->execute(['asset_tag_next', (string) max($cur, $s['next'])]);
        $set->execute(['asset_tag_zerofill', (string) $s['zerofill']]);
        $this->counts['asset_tag_next'] = max($cur, $s['next']);
    }

    // ── Pure helpers (unit-tested) ───────────────────────────────────────────

    /** Case/space-insensitive key for name matching. */
    public static function key(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }

    /** Next counter + zero-fill width from the numeric tags in use; null when there are none. */
    public static function tagSettingsFor(array $numericTags): ?array
    {
        $numericTags = array_values(array_filter($numericTags, static fn($t) => preg_match('/^\d+$/', (string) $t) === 1));
        if ($numericTags === []) {
            return null;
        }
        $widths = array_count_values(array_map('strlen', $numericTags));
        arsort($widths);
        return ['next' => max(array_map('intval', $numericTags)) + 1, 'zerofill' => (int) array_key_first($widths)];
    }

    /** "hardware-735-dv1Dnsjb-applecare.pdf" → "applecare.pdf" (Snipe's stored-name prefix). */
    public static function originalName(string $stored): string
    {
        return preg_replace('/^hardware-\d+-[A-Za-z0-9]+-/', '', $stored) ?: $stored;
    }

    // ── Write helpers ────────────────────────────────────────────────────────

    /** Catalog rows match an existing local row by snipeit_id first, then by name; returns the local id. */
    private function upsertByName(string $table, int $snipeId, string $name, array $fields): int
    {
        $s = $this->db->prepare("SELECT id FROM `$table` WHERE snipeit_id = ? OR LOWER(name) = ? ORDER BY snipeit_id = ? DESC LIMIT 1");
        $s->execute([$snipeId, self::key($name), $snipeId]);
        $id = $s->fetchColumn();
        $fields = ['name' => $name, 'snipeit_id' => $snipeId] + $fields;
        if ($id !== false) {
            $this->set($table, (int) $id, $fields);
            return (int) $id;
        }
        return $this->insert($table, $fields);
    }

    private function upsert(string $table, string $keyCol, int $keyVal, array $fields): int
    {
        $s = $this->db->prepare("SELECT id FROM `$table` WHERE `$keyCol` = ?");
        $s->execute([$keyVal]);
        $id = $s->fetchColumn();
        if ($id !== false) {
            $this->set($table, (int) $id, $fields);
            $this->counts["{$table}_updated"] = ($this->counts["{$table}_updated"] ?? 0) + 1;
            return (int) $id;
        }
        $this->counts["{$table}_created"] = ($this->counts["{$table}_created"] ?? 0) + 1;
        return $this->insert($table, $fields);
    }

    private function insert(string $table, array $fields): int
    {
        $cols = array_keys($fields);
        $this->db->prepare(
            "INSERT INTO `$table` (`" . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')'
        )->execute(array_values($fields));
        return (int) $this->db->lastInsertId();
    }

    private function set(string $table, int $id, array $fields): void
    {
        $sets = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", array_keys($fields)));
        $this->db->prepare("UPDATE `$table` SET $sets WHERE id = ?")->execute([...array_values($fields), $id]);
    }

    /**
     * Register a backup file for copying into storage/attachments/assets/<dest>.
     * Copies right away on --apply; a dry run only records it. Returns the
     * stored name (the basename) or null when the backup lacks the file.
     */
    private function stageFile(string $relSource, string $dest): ?string
    {
        $from = ($this->opt['source_dir'] ?? '') . '/' . $relSource;
        if (!is_file($from)) {
            $this->warnings[] = "File $relSource is not in the backup — reference dropped";
            return null;
        }
        $to = ROOT_DIR . '/storage/attachments/assets/' . $dest;
        $this->files[] = [$from, $to];
        if (!empty($this->opt['apply'])) {
            if (!is_dir(dirname($to))) {
                mkdir(dirname($to), 0775, true);
            }
            if (!is_file($to) && !copy($from, $to)) {
                $this->warnings[] = "Could not copy $relSource to $to";
                return null;
            }
        }
        return basename($dest);
    }
}
