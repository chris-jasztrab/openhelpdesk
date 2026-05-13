<?php
/**
 * Migration 037 — Per-ticket-type priority field visibility
 *
 * Adds one column to ticket_types so admins can override how the
 * Priority field behaves on a per-type basis:
 *
 *   priority_visibility ENUM('inherit','required','optional','hidden')
 *                       NOT NULL DEFAULT 'inherit'
 *
 *   - inherit  : fall back to the global sys_field_required_priority setting
 *   - required : show the picker; user must choose a priority
 *   - optional : show the picker; user may leave it blank
 *   - hidden   : do not render the picker; server applies the system default
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_types', 'priority_visibility']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `priority_visibility`
                 ENUM('inherit','required','optional','hidden')
                 NOT NULL DEFAULT 'inherit'
             AFTER `stale_threshold_hours`"
        );
    }
};
