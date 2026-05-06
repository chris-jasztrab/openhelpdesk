<?php
/**
 * Migration 032 — `tickets.submitted_by` for on-behalf-of audit trail.
 *
 * Pre-2.29 the on-behalf-of feature reused `tickets.created_by` to mean
 * "the requester" — when an admin filed a ticket on behalf of a portal
 * user, `created_by` was overwritten with the requester's id and the
 * agent's identity was lost. Email routing, CSAT and "my tickets" all
 * rely on `created_by` meaning the requester, so that overwrite was
 * correct — but it left no record of who actually clicked submit.
 *
 * This adds a sibling column that captures the actual submitter
 * whenever it differs from the requester. NULL means "self-submission"
 * (the common case) so existing tickets need no back-fill.
 *
 * ON DELETE SET NULL — losing the agent later shouldn't take the
 * ticket with it; same pattern as `assigned_to`.
 *
 * Idempotent — guarded by a column-existence check so re-running this
 * migration on a database that already has the column is a no-op.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name   = 'tickets'
           AND column_name  = 'submitted_by'"
    )->fetchColumn();

    if ((int) $exists > 0) {
        return;
    }

    $pdo->exec(
        "ALTER TABLE `tickets`
            ADD COLUMN `submitted_by` INT UNSIGNED NULL DEFAULT NULL
                AFTER `created_by`,
            ADD INDEX `idx_tickets_submitted_by` (`submitted_by`),
            ADD CONSTRAINT `fk_tickets_submitted_by`
                FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`)
                ON DELETE SET NULL"
    );
};
