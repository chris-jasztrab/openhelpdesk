<?php
/**
 * Migration 066 — Add require_resolution_on_close column to ticket_types
 *
 * Opt-in, per-type flag. When the ticket's assigned owner moves a ticket of
 * this type to a closed-bucket status from the ticket view WITHOUT having added
 * any comment, the UI prompts them to record what resolved it (with a suggested
 * framework) before closing. They can still close by picking a logged reason —
 * it is a soft nudge, not a hard block. Default 0 (off) preserves existing
 * behaviour for every existing type.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_types', 'require_resolution_on_close']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `require_resolution_on_close` TINYINT(1) NOT NULL DEFAULT 0 AFTER `show_to_location_visibility`"
        );
    }
};
