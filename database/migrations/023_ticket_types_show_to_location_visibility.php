<?php
/**
 * Migration 023 — Add show_to_location_visibility column to ticket_types
 *
 * Controls whether tickets of a given type surface to users who have the
 * "location ticket visibility" permission (can_view_location_tickets).
 * Default 1 (show) preserves existing behaviour. Admins can uncheck this
 * for types like Collections or HR that should stay limited to agents
 * without needing the heavier is_confidential group-restriction flow.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_types', 'show_to_location_visibility']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `show_to_location_visibility` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_confidential`"
        );
    }
};
