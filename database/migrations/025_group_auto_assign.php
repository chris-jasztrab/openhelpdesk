<?php
/**
 * Migration 025 — Auto-assign tickets to group members
 *
 * Adds the storage that backs the four assignment strategies surfaced on the
 * group edit page (round_robin, load_based, skill_based, first_available):
 *
 *   groups.assign_strategy        — which strategy a group uses for new
 *                                   tickets that arrive with a group set but
 *                                   no assignee. 'manual' (the default) means
 *                                   "do nothing" and matches today's behaviour.
 *   groups.assign_last_user_id    — round-robin pointer; the agent who got
 *                                   the most recent auto-assignment. The next
 *                                   pick rotates to the member after this one.
 *   groups.assign_fallback        — what to do when skill_based or
 *                                   first_available finds no eligible
 *                                   agent (e.g. nobody is online): try
 *                                   round_robin, load_based, or leave
 *                                   the ticket unassigned.
 *
 *   users.is_available            — agent-controlled "online / off duty"
 *                                   flag. Default 1 keeps existing agents
 *                                   eligible for first_available routing.
 *
 *   agent_skills                  — admin-managed list of skills (e.g.
 *                                   "Billing", "Network", "French").
 *   user_skill_map                — which skills each agent holds.
 *   ticket_type_skill_map         — which skills are required to handle a
 *                                   given ticket type. Used by skill_based
 *                                   routing: only group members whose skill
 *                                   set covers every required skill are
 *                                   eligible.
 *
 * Idempotent — guards every column add and every CREATE TABLE.
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

    if (!$hasColumn('groups', 'assign_strategy')) {
        $pdo->exec(
            "ALTER TABLE `groups`
             ADD COLUMN `assign_strategy` ENUM('manual','round_robin','load_based','skill_based','first_available')
                 NOT NULL DEFAULT 'manual' AFTER `is_confidential`"
        );
    }
    if (!$hasColumn('groups', 'assign_last_user_id')) {
        $pdo->exec(
            "ALTER TABLE `groups`
             ADD COLUMN `assign_last_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `assign_strategy`,
             ADD CONSTRAINT `fk_groups_assign_last_user`
                 FOREIGN KEY (`assign_last_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL"
        );
    }
    if (!$hasColumn('groups', 'assign_fallback')) {
        $pdo->exec(
            "ALTER TABLE `groups`
             ADD COLUMN `assign_fallback` ENUM('round_robin','load_based','none')
                 NOT NULL DEFAULT 'load_based' AFTER `assign_last_user_id`"
        );
    }

    if (!$hasColumn('users', 'is_available')) {
        $pdo->exec(
            "ALTER TABLE `users`
             ADD COLUMN `is_available` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_view_location_tickets`"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `agent_skills` (
            `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name`        VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_agent_skill_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `user_skill_map` (
            `user_id`  INT UNSIGNED NOT NULL,
            `skill_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`user_id`, `skill_id`),
            FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)        ON DELETE CASCADE,
            FOREIGN KEY (`skill_id`) REFERENCES `agent_skills`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ticket_type_skill_map` (
            `ticket_type_id` INT UNSIGNED NOT NULL,
            `skill_id`       INT UNSIGNED NOT NULL,
            PRIMARY KEY (`ticket_type_id`, `skill_id`),
            FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`skill_id`)       REFERENCES `agent_skills`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
