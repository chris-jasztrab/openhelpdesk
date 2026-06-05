<?php
/**
 * Migration 046 — Ticket-list performance indexes
 *
 * The agent/admin ticket list is the hottest read path in the app. Two indexes
 * cover its dominant query shapes; this migration adds them and retires the
 * single-column status index they supersede.
 *
 *   1. The DEFAULT landing view — no status filter, `ORDER BY created_at DESC
 *      LIMIT n`. For an admin (unrestricted visibility) this meant a full table
 *      scan + filesort on every page load — no index covered it. Fixed by
 *      `idx_tickets_created_at`.
 *
 *   2. The STATUS-FILTERED views — `WHERE status IN (...) ... ORDER BY
 *      created_at DESC`. Migration 041 already added `idx_tickets_status`
 *      (status alone), but that index narrows the filter and then leaves the
 *      sort to a filesort. The composite `idx_tickets_status_created
 *      (status, created_at)` covers both, and — sharing the same leading
 *      column — fully supersedes `idx_tickets_status`, which is dropped here to
 *      avoid maintaining two overlapping indexes.
 *
 * Two separate indexes are required because their leading columns differ: a
 * `(status, created_at)` composite cannot serve an unfiltered `ORDER BY
 * created_at`, and a plain `(created_at)` index cannot narrow a status filter.
 * Both indexed columns are cheap to maintain — `created_at` never changes after
 * insert, and `status` changes only on the occasional state transition.
 *
 * Idempotent: each step is guarded by an information_schema presence check, so
 * re-runs are harmless. MySQL 8 has no `ADD/DROP INDEX IF [NOT] EXISTS`, hence
 * the manual guards.
 */
return static function (PDO $pdo): void {
    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = ?
               AND INDEX_NAME   = ?'
        );
        $stmt->execute([$table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$indexExists('tickets', 'idx_tickets_status_created')) {
        $pdo->exec('ALTER TABLE `tickets` ADD INDEX `idx_tickets_status_created` (`status`, `created_at`)');
    }

    if (!$indexExists('tickets', 'idx_tickets_created_at')) {
        $pdo->exec('ALTER TABLE `tickets` ADD INDEX `idx_tickets_created_at` (`created_at`)');
    }

    // Retire the now-redundant single-column status index from migration 041.
    if ($indexExists('tickets', 'idx_tickets_status')) {
        $pdo->exec('ALTER TABLE `tickets` DROP INDEX `idx_tickets_status`');
    }
};
