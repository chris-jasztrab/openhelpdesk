<?php
/**
 * Migration 008 — Add group new-ticket notification columns
 *
 * notify_new_ticket on groups:  when enabled, all group members are emailed
 *                               whenever any new ticket is submitted.
 * notify_group_new_ticket on users: per-user opt-out (defaults 1 = subscribed).
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );

    $stmt->execute([$db, 'groups', 'notify_new_ticket']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `groups`
             ADD COLUMN `notify_new_ticket` TINYINT(1) NOT NULL DEFAULT 0
             AFTER `sort_order`"
        );
    }

    $stmt->execute([$db, 'users', 'notify_group_new_ticket']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `users`
             ADD COLUMN `notify_group_new_ticket` TINYINT(1) NOT NULL DEFAULT 1
             AFTER `notify_csat`"
        );
    }
};
