<?php
/**
 * Migration 054 — Track what a viewer is *doing* on a ticket
 *
 * `ticket_presence` recorded only that a staff member had the ticket open.
 * To drive the in-header indicator ("X is viewing" vs "X is replying") and
 * the reply-collision warning, each heartbeat now also reports an activity:
 *
 *   activity — 'viewing' (just has it open) or 'replying' (the reply/compose
 *              panel is open). The ping endpoint writes it; the read endpoint
 *              returns it per viewer. Defaults to 'viewing' so any pre-existing
 *              row (and any older client that omits the field) reads sensibly.
 *
 * Idempotent: guarded by an information_schema column check (MySQL 8 has no
 * `ADD COLUMN IF NOT EXISTS`).
 */
return static function (PDO $pdo): void {
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = ?
           AND COLUMN_NAME  = ?'
    );
    $exists->execute(['ticket_presence', 'activity']);
    if ((int) $exists->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `ticket_presence`
             ADD COLUMN `activity` VARCHAR(16) NOT NULL DEFAULT 'viewing' AFTER `last_seen`"
        );
    }
};
