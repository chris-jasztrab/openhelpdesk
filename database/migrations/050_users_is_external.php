<?php
/**
 * Migration 050 — `users.is_external` flag for forwarded third-party contacts
 *
 * The ticket Forward feature can send a ticket's details to people who are not
 * staff and may not be in the system at all (external vendors, other agencies).
 * Those external recipients are auto-provisioned as `role = 'user'` contacts so
 * their replies thread back into the ticket via the inbound mail processor.
 *
 * This column marks those auto-provisioned contacts so admins can filter them
 * out of (or down to) in the user directory — they are not real portal users.
 *
 * Idempotent: the column is added only if it does not already exist, so re-runs
 * (and installs from a schema that already has it) are harmless.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $exists->execute(['users', 'is_external']);
    if ((int) $exists->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `users`
                ADD COLUMN `is_external` TINYINT(1) NOT NULL DEFAULT 0
                AFTER `role`"
        );
    }
};
