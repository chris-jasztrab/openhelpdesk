<?php
/**
 * Migration 073 — Re-assert the tickets.status conversion and list indexes
 *
 * Migrations 041 and 046 between them are supposed to leave `tickets` in this
 * state:
 *
 *   - `status` is VARCHAR(64), not the original 7-value ENUM (041 step 4), so
 *     admin-defined custom statuses can actually be stored.
 *   - `idx_tickets_status_created (status, created_at)` and
 *     `idx_tickets_created_at (created_at)` exist, and the superseded
 *     single-column `idx_tickets_status` does not (046).
 *
 * On installs whose database was built by importing `database/schema.sql`
 * rather than by running the migration chain, neither is true: schema.sql
 * still declares the ENUM and carries none of these indexes. If such an
 * install also inherits a populated `schema_migrations` table (e.g. the schema
 * was imported alongside a copy of that table, or restored from a partial
 * dump), 041 and 046 are recorded as applied and the runner will never revisit
 * them — leaving the ENUM and no index on the busiest query path in the app.
 *
 * The symptoms are quiet but real: saving a custom status silently truncates to
 * '' on a non-strict server, and every ticket-list query falls back to a full
 * scan plus filesort.
 *
 * This migration re-asserts the intended end state directly instead of trying
 * to rewind history, so it is a no-op on a correctly-migrated database and a
 * repair on a drifted one. Every step is guarded by information_schema, so
 * re-runs are harmless (MySQL 8 has no ADD/DROP INDEX IF [NOT] EXISTS).
 *
 * Ordering note: the ENUM→VARCHAR widening cannot lose data — every existing
 * value is one of the 7 ENUM labels, all of which are seeded into
 * `ticket_statuses` by 041 step 2 — so the orphan check 041 performs before
 * converting is not repeated here.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $indexExists = static function (string $table, string $index) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([$db, $table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    };

    // ── 1. tickets.status: ENUM → VARCHAR(64) ────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'status'"
    );
    $stmt->execute([$db]);
    $colType = (string) $stmt->fetchColumn();

    if (stripos($colType, 'enum') === 0) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             MODIFY COLUMN `status` VARCHAR(64) NOT NULL DEFAULT 'open'"
        );
    }

    // ── 2. Restore the composite list indexes from 046 ───────────────────────
    // (status, created_at) serves the status-filtered views; (created_at) alone
    // serves the default unfiltered landing view, which the composite cannot.
    if (!$indexExists('tickets', 'idx_tickets_status_created')) {
        $pdo->exec('ALTER TABLE `tickets` ADD INDEX `idx_tickets_status_created` (`status`, `created_at`)');
    }

    if (!$indexExists('tickets', 'idx_tickets_created_at')) {
        $pdo->exec('ALTER TABLE `tickets` ADD INDEX `idx_tickets_created_at` (`created_at`)');
    }

    // ── 3. Retire the single-column index the composite supersedes ───────────
    if ($indexExists('tickets', 'idx_tickets_status')) {
        $pdo->exec('ALTER TABLE `tickets` DROP INDEX `idx_tickets_status`');
    }
};
