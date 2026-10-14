<?php
/**
 * Migration 082 — Agent time tracking on tickets
 *
 * `ticket_types.time_tracking_enabled` switches the Time Tracking card on for
 * tickets of that type (off by default, so nothing changes until an admin opts
 * a type in).
 *
 * `ticket_time_entries` holds one row per block of work. `seconds` is the
 * stored total; a non-NULL `timer_started_at` means the live timer is running
 * and the elapsed time since then is added to `seconds` when it is stopped.
 * Both timestamps are written by PHP, never NOW(), because local dev MySQL runs
 * on a different clock than PHP. `work_date` is the day the work was done —
 * reports filter on it, so a manual entry for yesterday lands on yesterday.
 *
 * No FK on user_id: deleting a staff account must not erase the time history
 * the reports are built on. Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_types', 'time_tracking_enabled']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `time_tracking_enabled` TINYINT(1) NOT NULL DEFAULT 0
             AFTER `require_resolution_on_close`"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ticket_time_entries` (
            `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ticket_id`        INT UNSIGNED NOT NULL,
            `user_id`          INT UNSIGNED NOT NULL,
            `seconds`          INT UNSIGNED NOT NULL DEFAULT 0,
            `timer_started_at` DATETIME NULL DEFAULT NULL,
            `work_date`        DATE NOT NULL,
            `note`             VARCHAR(500) NOT NULL DEFAULT '',
            `created_at`       DATETIME NOT NULL,
            `updated_at`       DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_time_entries_ticket` (`ticket_id`),
            KEY `idx_time_entries_user_date` (`user_id`, `work_date`),
            KEY `idx_time_entries_work_date` (`work_date`),
            CONSTRAINT `fk_time_entries_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
