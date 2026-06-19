<?php
/**
 * Migration 055 — Composite index `ticket_timeline (ticket_id, action)`
 *
 * The admin reports (resolution-time, SLA-attainment, aging) carry correlated
 * subqueries of the shape:
 *
 *   WHERE tl.ticket_id = t.id
 *     AND tl.action    = 'status_changed'
 *     AND tl.details LIKE '%→ Resolved%'
 *
 * once per ticket in the report's date range. `ticket_timeline` indexed only
 * `ticket_id` (the FK), so each subquery used that index to gather *all* of a
 * ticket's timeline rows, then filtered `action` (and the unindexable leading-
 * wildcard `details LIKE`) row by row. Timeline grows faster than tickets, so
 * this is the report path that degrades first at volume.
 *
 * `(ticket_id, action)` lets the same correlated lookup narrow on both columns
 * up front, handing the `details LIKE` only the few `status_changed` rows for
 * that ticket instead of its whole history. Column order is ticket_id-first on
 * purpose: every hot query filters ticket_id first (it's correlated to the
 * outer ticket), so this extends the existing access path rather than replacing
 * it.
 *
 * The standalone `ticket_id` index is intentionally left in place: it backs the
 * `ticket_timeline_ibfk_1` foreign key, and dropping an FK-backing index is
 * fiddly across MySQL/MariaDB for no meaningful gain on a table whose writes
 * aren't a hot path.
 *
 * Idempotent: guarded by an information_schema presence check (MySQL 8 has no
 * `ADD INDEX IF NOT EXISTS`).
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

    if (!$indexExists('ticket_timeline', 'idx_timeline_ticket_action')) {
        $pdo->exec(
            'ALTER TABLE `ticket_timeline` ADD INDEX `idx_timeline_ticket_action` (`ticket_id`, `action`)'
        );
    }
};
