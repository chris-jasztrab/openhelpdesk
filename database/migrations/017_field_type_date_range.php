<?php
/**
 * Migration 017 — Add 'date_range' to ticket_form_fields.field_type ENUM.
 */
return static function (PDO $pdo): void {
    $pdo->exec("
        ALTER TABLE `ticket_form_fields`
        MODIFY `field_type` ENUM(
            'text','textarea','checkbox','dropdown','date','number',
            'decimal','dependent','text_block','image','cc','date_range'
        ) NOT NULL
    ");
};
