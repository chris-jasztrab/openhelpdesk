<?php
/**
 * Migration 053 — Add survey_url to csat_surveys
 *
 * Stores the exact survey link sent to the requester so the ticket view can
 * deep-link to it: the public /survey/<token> page for built-in surveys, or
 * the substituted third-party URL for external surveys.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'csat_surveys', 'survey_url']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `csat_surveys` ADD COLUMN `survey_url` VARCHAR(2048) NULL DEFAULT NULL AFTER `comment`");
    }
};
