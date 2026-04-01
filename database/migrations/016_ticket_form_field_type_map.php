<?php
/**
 * Migration 016 — Create ticket_form_field_type_map join table.
 *
 * Allows custom form fields to be associated with specific ticket types.
 * Fields with no rows in this table are "global" (shown for all types).
 */
return static function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ticket_form_field_type_map` (
            `field_id` INT UNSIGNED NOT NULL,
            `type_id`  INT UNSIGNED NOT NULL,
            PRIMARY KEY (`field_id`, `type_id`),
            FOREIGN KEY (`field_id`) REFERENCES `ticket_form_fields`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`type_id`)  REFERENCES `ticket_types`(`id`)      ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
};
