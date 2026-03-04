<?php
/**
 * Migration 009 — Add per-user notification preference columns
 *
 * Agent columns (default 1 = subscribed):
 *   notify_assigned_to_me    — email agent when a ticket is assigned directly to them
 *   notify_assigned_to_group — email agent when a ticket is assigned to one of their groups
 *   notify_requester_replied — email agent when the requester adds a comment
 *   notify_note_added        — email agent when an internal note is added to their ticket
 *
 * Requester columns (default 1 = subscribed):
 *   notify_ticket_solved     — email requester when their ticket is resolved
 *   notify_ticket_closed     — email requester when their ticket is closed
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $columns = [
        'notify_assigned_to_me'    => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_group_new_ticket`",
        'notify_assigned_to_group' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_assigned_to_me`",
        'notify_requester_replied' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_assigned_to_group`",
        'notify_note_added'        => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_requester_replied`",
        'notify_ticket_solved'     => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_note_added`",
        'notify_ticket_closed'     => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_ticket_solved`",
    ];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );

    foreach ($columns as $col => $definition) {
        $stmt->execute([$db, 'users', $col]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$col}` {$definition}");
        }
    }
};
