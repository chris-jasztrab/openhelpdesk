<?php
/**
 * Migration 021 — Manual escalation paths
 *
 * Adds a per-ticket-type ordered escalation chain plus per-ticket
 * escalation state and a historical log. Distinct from the existing
 * time-driven `escalation_rules` engine (which fires from cron).
 *
 *   ticket_escalation_steps — ordered chain of agents per ticket_type
 *   ticket_escalations      — audit row per manual escalation event
 *   tickets.escalation_level — 0 = never escalated, N = currently at step N
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    // ticket_escalation_steps
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ticket_escalation_steps` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ticket_type_id` INT UNSIGNED NOT NULL,
            `step_order`     INT UNSIGNED NOT NULL,
            `user_id`        INT UNSIGNED NOT NULL,
            `label`          VARCHAR(100) DEFAULT NULL,
            `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_type_order` (`ticket_type_id`, `step_order`),
            KEY `idx_type` (`ticket_type_id`),
            KEY `idx_user` (`user_id`),
            FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`)        REFERENCES `users`(`id`)        ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // ticket_escalations (audit trail)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ticket_escalations` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ticket_id`      INT UNSIGNED NOT NULL,
            `from_user_id`   INT UNSIGNED DEFAULT NULL,
            `to_user_id`     INT UNSIGNED NOT NULL,
            `step_order`     INT UNSIGNED NOT NULL,
            `reason`         TEXT DEFAULT NULL,
            `escalated_by`   INT UNSIGNED NOT NULL,
            `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_ticket` (`ticket_id`),
            KEY `idx_to_user` (`to_user_id`),
            FOREIGN KEY (`ticket_id`)    REFERENCES `tickets`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`)   ON DELETE SET NULL,
            FOREIGN KEY (`to_user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
            FOREIGN KEY (`escalated_by`) REFERENCES `users`(`id`)   ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // tickets.escalation_level
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $check->execute([$db, 'tickets', 'escalation_level']);
    if ((int) $check->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `escalation_level` TINYINT UNSIGNED NOT NULL DEFAULT 0
             AFTER `assigned_to`"
        );
    }
};
