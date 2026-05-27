<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Migration 041 — verifies the ticket_statuses lookup table exists,
 * is seeded with the 7 original statuses, and that tickets.status was
 * converted from ENUM to VARCHAR with an index.
 *
 * These tests run against the live local DB. They never modify data —
 * just inspect schema and seed rows.
 */
class MigrationStatusesTest extends TestCase
{
    private static \PDO $pdo;
    private static string $dbName;

    public static function setUpBeforeClass(): void
    {
        $host   = (string) (getenv('DB_HOST') ?: '127.0.0.1');
        $port   = (string) (getenv('DB_PORT') ?: '3306');
        $dbname = (string) (getenv('DB_NAME') ?: 'localdesk');
        $user   = (string) (getenv('DB_USER') ?: 'root');
        $pass   = (string) (getenv('DB_PASS') ?: '');

        self::$pdo = new \PDO(
            "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        self::$dbName = $dbname;
    }

    // ── Table existence + shape ───────────────────────────────────────────────

    public function test_ticket_statuses_table_exists(): void
    {
        $stmt = self::$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([self::$dbName, 'ticket_statuses']);
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'ticket_statuses table should exist');
    }

    public function test_ticket_statuses_has_expected_columns(): void
    {
        $expected = [
            'id', 'slug', 'label', 'bucket', 'pauses_sla', 'sort_order', 'color',
            'is_default_new', 'is_default_resolved', 'is_default_closed',
            'is_system', 'is_active', 'created_at', 'updated_at',
        ];

        $stmt = self::$pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([self::$dbName, 'ticket_statuses']);
        $actual = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($expected as $col) {
            $this->assertContains($col, $actual, "ticket_statuses must have column «{$col}»");
        }
    }

    public function test_slug_has_unique_index(): void
    {
        $stmt = self::$pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'ticket_statuses'
               AND COLUMN_NAME = 'slug' AND NON_UNIQUE = 0"
        );
        $stmt->execute([self::$dbName]);
        $this->assertGreaterThan(0, (int) $stmt->fetchColumn(), 'slug must have a unique index');
    }

    // ── Seed data ─────────────────────────────────────────────────────────────

    public function test_seven_default_statuses_seeded(): void
    {
        $rows = self::$pdo
            ->query("SELECT slug FROM ticket_statuses WHERE is_system = 1 ORDER BY sort_order")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame(
            ['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party', 'resolved', 'closed'],
            $rows,
            'The 7 original statuses must be seeded with is_system=1 in original order'
        );
    }

    public function test_default_flags_are_unique(): void
    {
        foreach (['is_default_new', 'is_default_resolved', 'is_default_closed'] as $col) {
            $count = (int) self::$pdo
                ->query("SELECT COUNT(*) FROM ticket_statuses WHERE {$col} = 1")
                ->fetchColumn();
            $this->assertSame(1, $count, "exactly one row must have {$col}=1");
        }
    }

    public function test_default_flags_point_at_expected_slugs(): void
    {
        $map = [
            'is_default_new'      => 'open',
            'is_default_resolved' => 'resolved',
            'is_default_closed'   => 'closed',
        ];
        foreach ($map as $col => $expectedSlug) {
            $actual = (string) self::$pdo
                ->query("SELECT slug FROM ticket_statuses WHERE {$col} = 1 LIMIT 1")
                ->fetchColumn();
            $this->assertSame($expectedSlug, $actual, "{$col} should point at «{$expectedSlug}»");
        }
    }

    public function test_bucket_assignments_correct(): void
    {
        $rows = self::$pdo
            ->query("SELECT slug, bucket FROM ticket_statuses WHERE is_system = 1")
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        $expected = [
            'open'                   => 'open',
            'in_progress'            => 'open',
            'pending'                => 'open',
            'waiting_on_customer'    => 'open',
            'waiting_on_third_party' => 'open',
            'resolved'               => 'closed',
            'closed'                 => 'closed',
        ];

        foreach ($expected as $slug => $bucket) {
            $this->assertSame($bucket, $rows[$slug] ?? null, "«{$slug}» should be in bucket «{$bucket}»");
        }
    }

    public function test_sla_pausing_flags_correct(): void
    {
        $rows = self::$pdo
            ->query("SELECT slug, pauses_sla FROM ticket_statuses WHERE is_system = 1")
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        $pausing = ['pending', 'waiting_on_customer', 'waiting_on_third_party'];
        foreach ($rows as $slug => $pauses) {
            $expected = in_array($slug, $pausing, true) ? 1 : 0;
            $this->assertSame($expected, (int) $pauses, "«{$slug}» pauses_sla should be {$expected}");
        }
    }

    // ── tickets.status column conversion ──────────────────────────────────────

    public function test_tickets_status_column_is_varchar(): void
    {
        $stmt = self::$pdo->prepare(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'status'"
        );
        $stmt->execute([self::$dbName]);
        $type = strtolower((string) $stmt->fetchColumn());

        $this->assertSame('varchar(64)', $type, 'tickets.status must be VARCHAR(64), not ENUM');
    }

    public function test_tickets_status_index_exists(): void
    {
        $stmt = self::$pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tickets'
               AND COLUMN_NAME = 'status' AND INDEX_NAME = 'idx_tickets_status'"
        );
        $stmt->execute([self::$dbName]);
        $this->assertGreaterThan(0, (int) $stmt->fetchColumn(), 'tickets.status should have idx_tickets_status');
    }

    public function test_every_ticket_status_value_exists_in_lookup(): void
    {
        $orphans = self::$pdo
            ->query(
                "SELECT DISTINCT t.status
                 FROM tickets t
                 LEFT JOIN ticket_statuses s ON s.slug = t.status
                 WHERE s.slug IS NULL"
            )
            ->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame(
            [],
            $orphans,
            'Every distinct value in tickets.status must match a row in ticket_statuses.slug'
        );
    }
}
