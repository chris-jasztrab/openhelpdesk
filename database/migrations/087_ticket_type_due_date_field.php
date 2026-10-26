<?php
/**
 * Migration 087 — ticket_types.due_date_field_id: a date custom field on the
 * type's form that fills the ticket's due date (e.g. New Staff Member → Start
 * Date). The New Ticket form hides its own Due Date input for such a type, and
 * task tickets inherit the due date.
 */
return static function (PDO $pdo): void {
    $col = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $col->execute(['ticket_types', 'due_date_field_id']);
    if ((int) $col->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `ticket_types` ADD COLUMN `due_date_field_id` INT(10) UNSIGNED DEFAULT NULL AFTER `restrict_create_to_group`");
    }
};
