<?php
/**
 * OpenHelpDesk — Database Migration Runner
 *
 * Applies any pending migrations from database/migrations/ in order.
 * Each migration is recorded in `schema_migrations` so it only ever runs once.
 *
 * Usage (CLI):  php database/migrate.php
 * Programmatic: require this file with $pdo already set, or let it build its own.
 */

declare(strict_types=1);

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}

// ── Bootstrap enough to get a DB connection ───────────────────────────────
if (!function_exists('loadEnv')) {
    require_once ROOT_DIR . '/src/helpers.php';
}
if (!getenv('DB_HOST')) {
    loadEnv(ROOT_DIR . '/.env');
}

$_migHost   = env('DB_HOST', '127.0.0.1');
$_migPort   = env('DB_PORT', '3306');
$_migDbname = env('DB_NAME', 'localdesk');
$_migDbuser = env('DB_USER', 'root');
$_migDbpass = env('DB_PASS', '');

$migrationPdo = new PDO(
    "mysql:host={$_migHost};port={$_migPort};dbname={$_migDbname};charset=utf8mb4",
    $_migDbuser,
    $_migDbpass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
unset($_migHost, $_migPort, $_migDbname, $_migDbuser, $_migDbpass);

// ── Ensure the tracking table exists ─────────────────────────────────────
$migrationPdo->exec(
    "CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `migration`  VARCHAR(255) NOT NULL UNIQUE,
        `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

// ── Discover migration files ──────────────────────────────────────────────
$migrationDir = ROOT_DIR . '/database/migrations';
$files = glob($migrationDir . '/*.php');
if ($files === false) {
    $files = [];
}
sort($files);   // guarantees numeric order (001, 002, 003 …)

// ── Find already-applied migrations ──────────────────────────────────────
$applied = $migrationPdo
    ->query("SELECT migration FROM schema_migrations")
    ->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$isCli = PHP_SAPI === 'cli';
$ran   = 0;

// Fast path: nothing pending → return without taking the lock. This runs on
// every web request (see src/bootstrap.php), so the common case must be cheap.
$pending = array_filter($files, static fn(string $f): bool => !isset($applied[basename($f)]));

if (!empty($pending)) {
    // Serialize concurrent migrators with a MySQL advisory lock. Without it,
    // two simultaneous web requests can both see a migration as pending and run
    // the same DDL at once — the second throws (e.g. "column already exists")
    // and 500s that request. The lock is on this dedicated connection only.
    $gotLock = (int) $migrationPdo->query("SELECT GET_LOCK('openhelpdesk_migrate', 10)")->fetchColumn();

    try {
        // Re-read the applied set: another request may have applied migrations
        // while we waited for the lock, so we don't re-run them.
        if ($gotLock === 1) {
            $applied = array_flip(
                $migrationPdo->query("SELECT migration FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN)
            );

            foreach ($files as $file) {
                $name = basename($file);

                if (isset($applied[$name])) {
                    continue;   // already applied
                }

                if ($isCli) {
                    echo "  → Running {$name} … ";
                }

                $up = require $file;   // each file returns a callable(PDO $pdo): void
                $up($migrationPdo);

                $migrationPdo
                    ->prepare("INSERT INTO schema_migrations (migration) VALUES (?)")
                    ->execute([$name]);

                if ($isCli) {
                    echo "done\n";
                }
                $ran++;
            }
        } elseif ($isCli) {
            echo "  Could not acquire migration lock; another process is applying migrations.\n";
        }
    } finally {
        if ($gotLock === 1) {
            $migrationPdo->query("SELECT RELEASE_LOCK('openhelpdesk_migrate')");
        }
    }
}

if ($isCli) {
    if ($ran === 0) {
        echo "  No pending migrations.\n";
    } else {
        echo "  {$ran} migration(s) applied.\n";
    }
}

unset($migrationPdo);
