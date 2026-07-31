<?php
/**
 * Migration 074 — Indexes for the remaining ticket-list filters
 *
 * Migration 046 indexed the default landing view and the status filter. Three
 * other filters on the same list were left doing a full scan plus filesort on
 * every page load:
 *
 *   1. `due_date` — the "due today" filter (`t.due_date = CURDATE()`) and a
 *      sortable column in all three ticket lists (agent, admin, and the admin
 *      export). Indexed on its own: it serves the equality filter and the sort
 *      equally, and there is no single dominant secondary sort to pair it with.
 *
 *   2. `(sla_state, created_at)` — the SLA filter (`t.sla_state = ?`) followed
 *      by the list's default `ORDER BY created_at DESC`. Deliberately composite
 *      rather than `sla_state` alone: the column holds only
 *      on_track/warning/breached/NULL, so on its own it is too low-cardinality
 *      for the optimiser to prefer over a scan. Adding `created_at` lets the
 *      one index both narrow the filter and supply the sort order, which is the
 *      same reasoning migration 046 applied to `(status, created_at)`.
 *
 *   3. `(status, updated_at)` — the "resolved today" filter, which is
 *      `status = 'resolved'` AND a same-day bound on `updated_at`. Leading with
 *      `status` matches the equality half first, then ranges on `updated_at`.
 *
 * No index is added for `updated_at` alone: it is not offered as a sort column
 * anywhere, so a standalone index would carry write cost for no read.
 *
 * Guarded by information_schema — MySQL 8 has no ADD INDEX IF NOT EXISTS — so
 * re-runs are harmless.
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

    if (!$indexExists('tickets', 'idx_tickets_due_date')) {
        $pdo->exec('ALTER TABLE `tickets` ADD INDEX `idx_tickets_due_date` (`due_date`)');
    }

    if (!$indexExists('tickets', 'idx_tickets_sla_state_created')) {
        $pdo->exec('ALTER TABLE `tickets` ADD INDEX `idx_tickets_sla_state_created` (`sla_state`, `created_at`)');
    }

    if (!$indexExists('tickets', 'idx_tickets_status_updated')) {
        $pdo->exec('ALTER TABLE `tickets` ADD INDEX `idx_tickets_status_updated` (`status`, `updated_at`)');
    }
};
