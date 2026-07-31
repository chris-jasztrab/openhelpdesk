<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * `database/schema.sql` must describe the same database the migration chain
 * produces.
 *
 * Two paths build an OpenHelpDesk database and they are maintained by hand in
 * parallel: a fresh install imports `schema.sql`, while an existing install
 * upgrades by running `database/migrations/`. The file header spells out the
 * rule — add a table or column, and you write *both* the guarded migration and
 * the schema.sql entry — but nothing enforced it, so the two drifted silently
 * and repeatedly. Notable escapes: six whole tables absent from schema.sql
 * (`ticket_statuses`, `ticket_drafts`, `agent_oof_status`, and all three
 * `kanban_*` tables), `tickets.status` left as the ENUM that configurable
 * statuses replaced in v2.60.0, and the ticket-list indexes from v2.65.0.
 *
 * Drift here is nasty because it is invisible on the developer's own machine —
 * their database came from migrations and is correct. It only surfaces for
 * someone doing a fresh install, as a 500 on whichever page touches the missing
 * object.
 *
 * This test closes the loop by building a scratch database from schema.sql and
 * diffing its shape against the live (migration-built) one: tables, then
 * columns, then indexes. It compares structure only and never reads or writes
 * application rows.
 *
 * Requires a MySQL user that can CREATE/DROP DATABASE. Where that is not
 * available the test skips rather than fails, so a restricted CI environment
 * does not report a false problem.
 */
class SchemaParityTest extends TestCase
{
    /** Scratch database built from schema.sql; dropped in tearDownAfterClass. */
    private const SCRATCH_DB = 'localdesk_schema_parity_test';

    private static \PDO $live;
    private static \PDO $scratch;
    private static string $liveDb;
    private static bool $built = false;

    /**
     * Objects that legitimately exist in only one of the two databases.
     *
     * `schema_migrations` is created by the migration runner itself
     * (database/migrate.php) rather than by schema.sql, so it is present in a
     * migrated database and absent from a freshly imported one by design.
     */
    private const IGNORED_TABLES = ['schema_migrations'];

    public static function setUpBeforeClass(): void
    {
        $host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
        $port = (string) (getenv('DB_PORT') ?: '3306');
        $user = (string) (getenv('DB_USER') ?: 'root');
        $pass = (string) (getenv('DB_PASS') ?: '');

        self::$liveDb = (string) (getenv('DB_NAME') ?: 'localdesk');

        $opts = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

        self::$live = new \PDO(
            "mysql:host={$host};port={$port};dbname=" . self::$liveDb . ';charset=utf8mb4',
            $user,
            $pass,
            $opts
        );

        // Build the scratch database from schema.sql alone.
        try {
            $server = new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, $opts);
            $server->exec('DROP DATABASE IF EXISTS `' . self::SCRATCH_DB . '`');
            $server->exec(
                'CREATE DATABASE `' . self::SCRATCH_DB . '` '
                . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (\PDOException $e) {
            return; // no privilege — every test self-skips via requireBuilt()
        }

        self::$scratch = new \PDO(
            "mysql:host={$host};port={$port};dbname=" . self::SCRATCH_DB . ';charset=utf8mb4',
            $user,
            $pass,
            $opts
        );

        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        self::$scratch->exec($sql);

        self::$built = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$built) {
            self::$scratch->exec('DROP DATABASE IF EXISTS `' . self::SCRATCH_DB . '`');
        }
    }

    private function requireBuilt(): void
    {
        if (!self::$built) {
            $this->markTestSkipped(
                'Needs a MySQL user that can CREATE/DROP DATABASE to build a scratch schema.'
            );
        }
    }

    // ── Tables ────────────────────────────────────────────────────────────────

    public function test_schema_sql_declares_every_table_the_migrations_create(): void
    {
        $this->requireBuilt();

        $missing = array_diff($this->tables(self::$live, self::$liveDb), $this->tables(self::$scratch, self::SCRATCH_DB));

        $this->assertSame(
            [],
            array_values($missing),
            "database/schema.sql is missing these tables, so a fresh install will 500 on any page that "
            . "touches them. Add a matching CREATE TABLE IF NOT EXISTS block: " . implode(', ', $missing)
        );
    }

    public function test_schema_sql_declares_no_table_the_migrations_do_not(): void
    {
        $this->requireBuilt();

        $extra = array_diff($this->tables(self::$scratch, self::SCRATCH_DB), $this->tables(self::$live, self::$liveDb));

        $this->assertSame(
            [],
            array_values($extra),
            'database/schema.sql declares tables that no migration creates — either a migration is '
            . 'missing or these are dead: ' . implode(', ', $extra)
        );
    }

    // ── Columns ───────────────────────────────────────────────────────────────

    public function test_columns_match_for_every_shared_table(): void
    {
        $this->requireBuilt();

        $shared = array_intersect(
            $this->tables(self::$live, self::$liveDb),
            $this->tables(self::$scratch, self::SCRATCH_DB)
        );

        $problems = [];

        foreach ($shared as $table) {
            $live    = $this->columns(self::$live, self::$liveDb, $table);
            $scratch = $this->columns(self::$scratch, self::SCRATCH_DB, $table);

            foreach (array_diff_key($live, $scratch) as $col => $_) {
                $problems[] = "{$table}.{$col} — in the migrated DB, missing from schema.sql";
            }
            foreach (array_diff_key($scratch, $live) as $col => $_) {
                $problems[] = "{$table}.{$col} — in schema.sql, missing from the migrated DB";
            }
            // Same column on both sides, different declared type.
            foreach (array_intersect_key($live, $scratch) as $col => $type) {
                if ($type !== $scratch[$col]) {
                    $problems[] = "{$table}.{$col} — migrated is «{$type}», schema.sql says «{$scratch[$col]}»";
                }
            }
        }

        $this->assertSame(
            [],
            $problems,
            "database/schema.sql and the migration chain disagree about columns:\n  - "
            . implode("\n  - ", $problems)
        );
    }

    // ── Indexes ───────────────────────────────────────────────────────────────

    public function test_indexes_match_for_every_shared_table(): void
    {
        $this->requireBuilt();

        $shared = array_intersect(
            $this->tables(self::$live, self::$liveDb),
            $this->tables(self::$scratch, self::SCRATCH_DB)
        );

        $problems = [];

        foreach ($shared as $table) {
            $live    = $this->indexes(self::$live, self::$liveDb, $table);
            $scratch = $this->indexes(self::$scratch, self::SCRATCH_DB, $table);

            foreach (array_diff_key($live, $scratch) as $name => $cols) {
                $problems[] = "{$table}.{$name} ({$cols}) — in the migrated DB, missing from schema.sql";
            }
            foreach (array_diff_key($scratch, $live) as $name => $cols) {
                $problems[] = "{$table}.{$name} ({$cols}) — in schema.sql, missing from the migrated DB";
            }
            foreach (array_intersect_key($live, $scratch) as $name => $cols) {
                if ($cols !== $scratch[$name]) {
                    $problems[] = "{$table}.{$name} — migrated covers ({$cols}), schema.sql covers ({$scratch[$name]})";
                }
            }
        }

        $this->assertSame(
            [],
            $problems,
            "database/schema.sql and the migration chain disagree about indexes. A missing index is a "
            . "silent performance cliff on a fresh install, not an error:\n  - " . implode("\n  - ", $problems)
        );
    }

    // ── information_schema helpers ────────────────────────────────────────────

    /** @return list<string> sorted base-table names, minus IGNORED_TABLES */
    private function tables(\PDO $pdo, string $db): array
    {
        $stmt = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME"
        );
        $stmt->execute([$db]);

        return array_values(
            array_diff($stmt->fetchAll(\PDO::FETCH_COLUMN), self::IGNORED_TABLES)
        );
    }

    /**
     * Column name => declared type. COLUMN_TYPE carries length/signedness/ENUM
     * members, which is exactly the granularity the ENUM-vs-VARCHAR(64) drift
     * on tickets.status escaped at.
     *
     * @return array<string, string>
     */
    private function columns(\PDO $pdo, string $db, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([$db, $table]);

        return array_map('strtolower', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    /**
     * Index name => ordered comma-joined column list, so a composite index
     * whose columns are in the wrong order is caught rather than counted equal.
     *
     * @return array<string, string>
     */
    private function indexes(\PDO $pdo, string $db, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        $stmt->execute([$db, $table]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
        }

        return array_map(static fn(array $cols): string => implode(',', $cols), $out);
    }
}
