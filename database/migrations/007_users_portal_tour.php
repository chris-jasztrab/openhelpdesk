<?php
/**
 * Migration 007 — Add show_portal_tour flag to users
 *
 * Controls whether the Driver.js onboarding tour auto-plays for portal users.
 * Defaults to 1 so all existing users see the tour on their next portal visit.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'users', 'show_portal_tour']);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `users`
             ADD COLUMN `show_portal_tour` TINYINT(1) NOT NULL DEFAULT 1
             AFTER `show_agent_tour`"
        );
    }
};
