<?php
/**
 * Migration 080 — SLA notice block on the ticket forms
 *
 * Adds the `sla_notice` system field to every ticket type that already has a
 * form layout, placed directly after Priority and shown by default. It is a
 * read-only block: the form fills it with the response/resolution targets for
 * the chosen type + priority, and admins drag it anywhere on the form (or hide
 * it) from the Form Builder like any other system field.
 *
 * Types with no layout rows yet pick it up from systemFieldDefaults() the
 * first time the Form Builder opens them. A type whose Priority row was
 * removed from the form gets no notice row — there is nothing for it to
 * describe. Idempotent (INSERT IGNORE against the primary key).
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "INSERT IGNORE INTO `ticket_type_form_layout`
            (`type_id`, `field_kind`, `field_key`, `sort_order`, `visibility`)
         SELECT p.`type_id`, 'system', 'sla_notice', p.`sort_order` + 1, 'optional'
         FROM `ticket_type_form_layout` p
         WHERE p.`field_kind` = 'system' AND p.`field_key` = 'priority'"
    );
};
