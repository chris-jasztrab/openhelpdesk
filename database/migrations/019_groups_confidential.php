<?php
/**
 * Migration 019 — Add is_confidential column to groups
 *
 * When a group is marked confidential, adding new members (after the first)
 * triggers an email alert to all current members.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'groups', 'is_confidential']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `groups` ADD COLUMN `is_confidential` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notify_new_ticket`");
    }
};
