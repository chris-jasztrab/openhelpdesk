<?php
/**
 * Migration 047 — Index `ticket_presence.last_seen`
 *
 * The ticket-presence read path (`GET /api/tickets/{id}/presence`) used to run
 * a `DELETE ... WHERE last_seen < <cutoff>` on every poll from every viewer.
 * `ticket_presence` had no index on `last_seen`, so that delete — and the new
 * filter-on-read predicate that replaces most of it — scanned the whole table
 * each time. `user_presence` already indexes `last_seen`; this brings
 * `ticket_presence` in line so the (now occasional) GC and the read-time
 * staleness filter use a range scan instead.
 *
 * Idempotent: guarded by an information_schema presence check (MySQL 8 has no
 * `ADD INDEX IF NOT EXISTS`).
 */
return static function (PDO $pdo): void {
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = ?
           AND INDEX_NAME   = ?'
    );
    $exists->execute(['ticket_presence', 'idx_ticket_presence_last_seen']);
    if ((int) $exists->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `ticket_presence` ADD INDEX `idx_ticket_presence_last_seen` (`last_seen`)');
    }
};
