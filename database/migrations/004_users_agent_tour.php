<?php
/**
 * Migration 004 — Add show_agent_tour flag to users
 *
 * Controls whether the Driver.js onboarding tour auto-plays for agents.
 * Defaults to 1 so all existing agents see the tour on their next login.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'users', 'show_agent_tour']);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `users`
             ADD COLUMN `show_agent_tour` TINYINT(1) NOT NULL DEFAULT 1
             AFTER `totp_enabled`"
        );
    }
};
