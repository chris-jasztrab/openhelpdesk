<?php
/**
 * Migration 014 — Add reopened_at column to csat_surveys
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'csat_surveys', 'reopened_at']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `csat_surveys` ADD COLUMN `reopened_at` TIMESTAMP NULL DEFAULT NULL AFTER `responded_at`");
    }
};
