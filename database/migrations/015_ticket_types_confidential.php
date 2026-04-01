<?php
/**
 * Migration 015 — Add is_confidential column to ticket_types
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_types', 'is_confidential']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `ticket_types` ADD COLUMN `is_confidential` TINYINT(1) NOT NULL DEFAULT 0 AFTER `group_id`");
    }
};
