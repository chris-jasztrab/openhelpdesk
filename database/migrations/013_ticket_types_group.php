<?php
/**
 * Migration 013 — Add group_id column to ticket_types
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_types', 'group_id']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `ticket_types` ADD COLUMN `group_id` INT UNSIGNED DEFAULT NULL AFTER `color`,
                    ADD CONSTRAINT `fk_ticket_types_group` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE SET NULL");
    }
};
