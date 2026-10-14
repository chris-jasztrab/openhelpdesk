<?php
/**
 * Migration 083 — Close split-button options on the ticket page
 *
 * `tickets.close_reason` records WHY a ticket was closed when it wasn't
 * actually resolved ('duplicate', 'wont_fix', 'no_response'), so the resolved
 * counts can leave those out. `duplicate_of_ticket_id` links a duplicate to
 * its original. `reopen_on_reply_until` is the "Close and reopen on reply"
 * window: a requester reply before then puts the ticket back to open. All
 * three describe one close and are cleared on the next status change (see
 * Sla::onStatusChanged).
 *
 * `ticket_followups` holds "Close and schedule follow-up" reminders; the SLA
 * cron turns due rows into an in-app notification for the agent who set them.
 * `due_at` / `sent_at` are written by PHP, never NOW(), because local dev MySQL
 * runs on a different clock than PHP. Idempotent.
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

    if (!$hasColumn('tickets', 'close_reason')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `close_reason` VARCHAR(32) NULL DEFAULT NULL AFTER `solution_timeline_id`"
        );
    }
    if (!$hasColumn('tickets', 'duplicate_of_ticket_id')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `duplicate_of_ticket_id` INT UNSIGNED NULL DEFAULT NULL AFTER `close_reason`,
             ADD KEY `idx_tickets_duplicate_of` (`duplicate_of_ticket_id`),
             ADD CONSTRAINT `fk_tickets_duplicate_of` FOREIGN KEY (`duplicate_of_ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL"
        );
    }
    if (!$hasColumn('tickets', 'reopen_on_reply_until')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `reopen_on_reply_until` DATETIME NULL DEFAULT NULL AFTER `duplicate_of_ticket_id`"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ticket_followups` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id`  INT UNSIGNED NOT NULL,
            `user_id`    INT UNSIGNED NOT NULL,
            `due_at`     DATETIME NOT NULL,
            `note`       VARCHAR(500) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL,
            `sent_at`    DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_followups_due` (`sent_at`, `due_at`),
            KEY `idx_followups_ticket` (`ticket_id`),
            CONSTRAINT `fk_followups_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_followups_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
