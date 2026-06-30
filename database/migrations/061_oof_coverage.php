<?php
/**
 * Migration 061 — Out-of-office (OOF) coverage
 *
 * Backs the "auto-reassign / auto-reply when an agent is out of office"
 * feature. When the sole member of a group goes on vacation, their unanswered
 * tickets would otherwise sit untouched until they return. A cron job
 * (scripts/process-oof-coverage.php) polls each staff member's Outlook
 * automatic-replies (OOF) state via the Microsoft Graph API, then either
 * reassigns the ticket to an available group member or — when there is nobody
 * to hand it to (single-person groups, or everyone away) — auto-replies the
 * requester with the agent's Outlook out-of-office message.
 *
 *   agent_oof_status            — per-user cache of the last Graph
 *                                 mailboxSettings/automaticRepliesSetting read.
 *                                 Refreshed by the cron job; never queried live
 *                                 per-ticket. `is_oof` is the *effective* flag
 *                                 (alwaysEnabled, or scheduled and within the
 *                                 window) computed at refresh time.
 *
 *   tickets.oof_autoreply_at    — stamp set the first (and only) time an OOF
 *                                 auto-reply is sent for a ticket, so the
 *                                 requester is never auto-replied twice for the
 *                                 same absence.
 *
 * Idempotent — guards the column add and uses CREATE TABLE IF NOT EXISTS.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $hasColumn = static function (string $table, string $column) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$db, $table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `agent_oof_status` (
            `user_id`          INT UNSIGNED NOT NULL,
            `status`           VARCHAR(20)  NOT NULL DEFAULT 'disabled',
            `scheduled_start`  DATETIME     DEFAULT NULL,
            `scheduled_end`    DATETIME     DEFAULT NULL,
            `external_message` MEDIUMTEXT   DEFAULT NULL,
            `is_oof`           TINYINT(1)   NOT NULL DEFAULT 0,
            `checked_at`       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`user_id`),
            KEY `idx_agent_oof_is_oof` (`is_oof`),
            CONSTRAINT `fk_agent_oof_user`
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if (!$hasColumn('tickets', 'oof_autoreply_at')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `oof_autoreply_at` DATETIME NULL DEFAULT NULL"
        );
    }
};
