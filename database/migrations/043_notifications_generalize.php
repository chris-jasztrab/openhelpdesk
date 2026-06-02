<?php
/**
 * Migration 043 — Generalize the `notifications` table beyond @mentions.
 *
 * The in-app notifications feed originally only carried @mention rows, so the
 * table hard-required `timeline_id` and `mentioned_by` (both NOT NULL, both FK)
 * and the feed query INNER-JOINed them. To surface the other agent-facing
 * notification types (new assignments, ticket/status updates, SLA warnings &
 * breaches, new tickets, customer replies, notes) we make the table generic:
 *
 *   - `type`         names the notification kind ('mention', 'assignment',
 *                    'ticket_update', 'sla_warning', 'sla_breach',
 *                    'new_ticket', 'customer_reply', 'note_added', …).
 *                    Existing rows are all mentions → default 'mention'.
 *   - `body`         denormalized headline/excerpt so the feed no longer needs
 *                    a `ticket_timeline` row to render a message. NULL for
 *                    legacy mention rows, which still fall back to the timeline.
 *   - `timeline_id`  made NULLable — only mentions/notes point at a timeline row.
 *   - `mentioned_by` made NULLable — system-generated notifications (SLA, etc.)
 *                    have no human actor. Renamed in intent to "actor"; column
 *                    name kept for backward compatibility with existing inserts.
 *
 * Idempotent — every step is guarded by an information_schema lookup so
 * re-running on an already-migrated database is a no-op.
 */
return static function (PDO $pdo): void {
    $colExists = static function (PDO $pdo, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute(['notifications', $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$colExists($pdo, 'type')) {
        $pdo->exec(
            "ALTER TABLE `notifications`
             ADD COLUMN `type` VARCHAR(40) NOT NULL DEFAULT 'mention' AFTER `ticket_id`"
        );
    }

    if (!$colExists($pdo, 'body')) {
        $pdo->exec(
            "ALTER TABLE `notifications`
             ADD COLUMN `body` TEXT NULL AFTER `mentioned_by`"
        );
    }

    // Relax the NOT NULL constraints. MODIFY keeps the existing FK in place;
    // a nullable FK column is valid in MySQL/MariaDB.
    $isNullable = static function (PDO $pdo, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute(['notifications', $column]);
        return strtoupper((string) $stmt->fetchColumn()) === 'YES';
    };

    if (!$isNullable($pdo, 'timeline_id')) {
        $pdo->exec('ALTER TABLE `notifications` MODIFY `timeline_id` INT(10) UNSIGNED NULL');
    }
    if (!$isNullable($pdo, 'mentioned_by')) {
        $pdo->exec('ALTER TABLE `notifications` MODIFY `mentioned_by` INT(10) UNSIGNED NULL');
    }

    // Index the new type column for the feed's per-type filtering.
    $idxExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $idxExists->execute(['notifications', 'idx_notifications_user_type']);
    if ((int) $idxExists->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `notifications` ADD INDEX `idx_notifications_user_type` (`user_id`, `type`)');
    }
};
