<?php

/**
 * OpenHelpDesk — Snipe-IT backup importer
 *
 * Loads a Snipe-IT backup (the zip Snipe creates, or that zip already
 * unpacked) into the asset inventory: statuses, categories, manufacturers,
 * suppliers, models, assets (live and soft-deleted), the full action log as
 * asset history, and the model images / asset files the backup carries.
 * Licenses, accessories, consumables and components are not imported.
 *
 * Dry run by default — loads the dump into a staging schema, maps everything
 * inside a transaction, prints the report, rolls back:
 *
 *   sudo -u www-data php scripts/import-snipeit.php --source=/path/to/backup[.zip] \
 *       --user-domain=example.org [--verify-csv=/path/to/export.csv] \
 *       [--alias-location="Snipe name=Local name" ...] [--alias-user="username=email" ...] \
 *       [--staging-db=snipeit_staging] [--keep-staging] [--apply]
 *
 * --user-domain turns Snipe usernames (LDAP imports carry no email) into
 * helpdesk emails; --alias-user overrides one. --alias-location maps a Snipe
 * location name onto a local one when the names differ. --verify-csv compares
 * the result against Snipe's own asset export (ID, Asset Tag, Serial, Model,
 * Status, Username, Location) and prints every mismatch.
 *
 * The zip is read entry by entry: only db-dumps/*.sql, public/uploads/{models,
 * assets}/ and storage/private_uploads/assets/ are extracted. The backup's
 * .env, OAuth keys and user password hashes are never read.
 *
 * Safe to re-run: every row is keyed on its Snipe id (catalog rows also by
 * name), history rows remember their Snipe log id, files are skipped when
 * already present. Rows are inserted directly — no emails or notifications.
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Assets.php';
require_once ROOT_DIR . '/src/SnipeItImport.php';

loadEnv(ROOT_DIR . '/.env');

$opt = getopt('', ['source:', 'user-domain:', 'verify-csv:', 'alias-location:', 'alias-user:', 'staging-db:', 'keep-staging', 'apply']);
$source = rtrim((string) ($opt['source'] ?? ''), '/\\');
if ($source === '' || !file_exists($source)) {
    fwrite(STDERR, "Usage: php scripts/import-snipeit.php --source=/path/to/backup[.zip] --user-domain=example.org [--verify-csv=FILE] [--alias-location=A=B] [--alias-user=U=EMAIL] [--staging-db=NAME] [--keep-staging] [--apply]\n");
    exit(1);
}
$apply   = isset($opt['apply']);
$staging = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($opt['staging-db'] ?? 'snipeit_staging'));
$pairs = static function ($v): array {
    $out = [];
    foreach ((array) $v as $pair) {
        [$from, $to] = array_map('trim', explode('=', (string) $pair, 2)) + [1 => ''];
        if ($from !== '' && $to !== '') {
            $out[$from] = $to;
        }
    }
    return $out;
};

function out(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// ── 1. Source: a folder, or a zip we unpack selectively ──────────────────────
$tmpDir = null;
if (is_file($source)) {
    $zip = new ZipArchive();
    if ($zip->open($source) !== true) {
        fwrite(STDERR, "Cannot open zip $source\n");
        exit(1);
    }
    $tmpDir = sys_get_temp_dir() . '/snipeit-import-' . bin2hex(random_bytes(4));
    mkdir($tmpDir, 0700, true);
    $wanted = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if (preg_match('#^(db-dumps/[^/]+\.sql|public/uploads/(models|assets)/[^/]+|storage/private_uploads/assets/[^/]+)$#', $n) && !str_contains($n, '..')) {
            $wanted[] = $n;
        }
    }
    $zip->extractTo($tmpDir, $wanted);
    $zip->close();
    out('Unpacked ' . count($wanted) . " entries from the zip (dump, model/asset images, asset files only)");
    $source = $tmpDir;
}
$dumps = glob($source . '/db-dumps/*.sql') ?: [];
if ($dumps === []) {
    fwrite(STDERR, "No db-dumps/*.sql in $source\n");
    exit(1);
}

// ── 2. Staging schema from the dump ──────────────────────────────────────────
$db = Database::connect();
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
try {
    $db->exec("CREATE DATABASE IF NOT EXISTS `$staging` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot create staging database `$staging` as the app's DB user: {$e->getMessage()}\nCreate it by hand (CREATE DATABASE `$staging`; GRANT ALL ON `$staging`.* TO the app user) and re-run.\n");
    exit(1);
}
$src = new PDO(
    'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306') . ";dbname=$staging;charset=utf8mb4",
    env('DB_USER', 'root'),
    env('DB_PASS', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$src->exec('SET FOREIGN_KEY_CHECKS = 0');
$statements = 0;
$buf = '';
$fh = fopen($dumps[0], 'rb');
while (($line = fgets($fh)) !== false) {
    if ($buf === '' && (str_starts_with($line, '--') || trim($line) === '')) {
        continue;
    }
    $buf .= $line;
    if (str_ends_with(rtrim($line), ';')) {
        $src->exec($buf);
        $statements++;
        $buf = '';
    }
}
fclose($fh);
out("Loaded $statements statements from " . basename($dumps[0]) . " into `$staging`");
foreach (['licenses', 'accessories', 'consumables', 'components', 'asset_maintenances'] as $t) {
    try {
        $n = (int) $src->query("SELECT COUNT(*) FROM `$t` WHERE deleted_at IS NULL")->fetchColumn();
        if ($n > 0) {
            out("Note: $n $t in the backup are NOT imported (assets only)");
        }
    } catch (PDOException) {
        // table absent in this Snipe version — nothing to report
    }
}

// ── 3. Map ───────────────────────────────────────────────────────────────────
$import = new SnipeItImport($db, $src, [
    'user_domain'    => (string) ($opt['user-domain'] ?? ''),
    'alias_location' => $pairs($opt['alias-location'] ?? []),
    'alias_user'     => $pairs($opt['alias-user'] ?? []),
    'source_dir'     => $source,
    'apply'          => $apply,
]);
$db->beginTransaction();
try {
    $import->run();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

// ── 4. Report ────────────────────────────────────────────────────────────────
out($apply ? '=== APPLY ===' : '=== DRY RUN (nothing written) ===');
ksort($import->counts);
foreach ($import->counts as $k => $v) {
    out(sprintf('  %-32s %d', $k, $v));
}
arsort($import->byLocation);
out('Live assets by location:');
foreach ($import->byLocation as $name => $n) {
    out(sprintf('  %-32s %d', $name, $n));
}
if ($import->unmatchedUsers !== []) {
    arsort($import->unmatchedUsers);
    out(count($import->unmatchedUsers) . ' Snipe users have no helpdesk account (assets left unassigned, name kept in import_assignee):');
    foreach ($import->unmatchedUsers as $u => $n) {
        out("  $u ($n)");
    }
}
out(count($import->files) . ' file(s) ' . ($apply ? 'copied' : 'to copy') . ' into storage/attachments/assets/');
foreach (array_unique($import->warnings) as $w) {
    out('WARNING: ' . $w);
}

// ── 5. Verify against the CSV export ─────────────────────────────────────────
if (!empty($opt['verify-csv']) && is_readable((string) $opt['verify-csv'])) {
    $fh = fopen((string) $opt['verify-csv'], 'rb');
    $head = array_map(static fn($h) => trim((string) $h, " \xEF\xBB\xBF"), fgetcsv($fh) ?: []);
    $col = array_flip($head);
    $get = $db->prepare(
        'SELECT a.asset_tag, a.serial, a.import_assignee, a.assigned_type, a.assigned_id, m.name AS model, s.name AS status, l.name AS location, u.email
           FROM assets a JOIN asset_models m ON m.id = a.model_id JOIN asset_statuses s ON s.id = a.status_id
           LEFT JOIN locations l ON l.id = a.location_id
           LEFT JOIN users u ON a.assigned_type = \'user\' AND u.id = a.assigned_id
          WHERE a.snipeit_id = ?'
    );
    $rows = 0;
    $mismatch = 0;
    $missing = 0;
    while (($r = fgetcsv($fh)) !== false) {
        $rows++;
        $get->execute([(int) $r[$col['ID']]]);
        $a = $get->fetch();
        if (!$a) {
            $missing++;
            out("CSV #{$r[$col['ID']]} ({$r[$col['Asset Tag']]}) not imported");
            continue;
        }
        $diff = [];
        foreach (['Asset Tag' => 'asset_tag', 'Serial' => 'serial', 'Model' => 'model'] as $c => $f) {
            // Snipe prefixes a backtick to values Excel would read as a formula.
            if (isset($col[$c]) && trim(ltrim((string) $r[$col[$c]], '`')) !== trim((string) $a[$f])) {
                $diff[] = "$c: csv «{$r[$col[$c]]}» vs «{$a[$f]}»";
            }
        }
        if (isset($col['Status'])) {
            // Snipe exports "Ready to Deploy (deployed)" — the suffix is its derived
            // deployed/deployable flag, which here is "has an assignee".
            $csvStatus = trim((string) $r[$col['Status']]);
            $deployed  = str_ends_with($csvStatus, '(deployed)');
            $csvStatus = trim((string) preg_replace('/\s*\((deployed|deployable|pending|undeployable|archived)\)$/', '', $csvStatus));
            if ($csvStatus !== trim((string) $a['status'])) {
                $diff[] = "Status: csv «{$csvStatus}» vs «{$a['status']}»";
            }
            $oursDeployed = $a['assigned_type'] !== null || $a['import_assignee'] !== null;
            if ($deployed !== $oursDeployed) {
                $diff[] = 'Deployed: csv «' . ($deployed ? 'yes' : 'no') . '» vs «' . ($oursDeployed ? 'yes' : 'no') . '»';
            }
        }
        if (isset($col['Username'])) {
            $csvUser = strtolower(trim((string) $r[$col['Username']]));
            $ours = $a['email'] !== null ? strtolower(strstr((string) $a['email'], '@', true) ?: '') : strtolower((string) $a['import_assignee']);
            if (str_starts_with($ours, 'location: ')) {
                $ours = ''; // a location checkout has no username in the export
            }
            if ($csvUser !== $ours) {
                $diff[] = "Username: csv «{$csvUser}» vs «{$ours}»";
            }
        }
        if ($diff !== []) {
            $mismatch++;
            if ($mismatch <= 25) {
                out("CSV #{$r[$col['ID']]} {$a['asset_tag']}: " . implode('; ', $diff));
            }
        }
    }
    fclose($fh);
    out("CSV verify: $rows rows, $missing not imported, $mismatch with differences (location is not compared — user-assigned assets deliberately take the user's location)");
}

// ── 6. Commit / roll back, clean up ──────────────────────────────────────────
if ($apply) {
    $db->commit();
    out('Committed.');
} else {
    $db->rollBack();
    out('Rolled back — re-run with --apply to write.');
}
if (!isset($opt['keep-staging'])) {
    $db->exec("DROP DATABASE IF EXISTS `$staging`");
    out("Dropped staging database `$staging`");
}
if ($tmpDir !== null) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($tmpDir);
}
