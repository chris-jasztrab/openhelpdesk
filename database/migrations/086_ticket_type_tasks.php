<?php
/**
 * Migration 086 — task tickets (e.g. New Employee onboarding).
 *
 *   tickets.parent_ticket_id              — a task ticket points at the ticket that spawned it
 *   ticket_types.restrict_create_to_group — only members of the type's group may create it
 *   ticket_type_tasks                     — per type: which task tickets to spawn on create,
 *                                           and which custom-field values each one carries
 */
return static function (PDO $pdo): void {
    $col = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );

    $col->execute(['tickets', 'parent_ticket_id']);
    if ((int) $col->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `tickets`
                ADD COLUMN `parent_ticket_id` INT(10) UNSIGNED DEFAULT NULL AFTER `duplicate_of_ticket_id`,
                ADD KEY `idx_tickets_parent` (`parent_ticket_id`),
                ADD CONSTRAINT `fk_tickets_parent` FOREIGN KEY (`parent_ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL"
        );
    }

    $col->execute(['ticket_types', 'restrict_create_to_group']);
    if ((int) $col->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `ticket_types` ADD COLUMN `restrict_create_to_group` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_confidential`");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ticket_type_tasks` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `type_id` INT(10) UNSIGNED NOT NULL,
            `child_type_id` INT(10) UNSIGNED NOT NULL,
            `field_ids` TEXT DEFAULT NULL,
            `sort_order` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_ttt_type` (`type_id`),
            KEY `idx_ttt_child_type` (`child_type_id`),
            CONSTRAINT `fk_ttt_type` FOREIGN KEY (`type_id`) REFERENCES `ticket_types` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ttt_child_type` FOREIGN KEY (`child_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
