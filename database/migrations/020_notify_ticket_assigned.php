<?php
/**
 * Migration 020 — Requester assignment notification preference
 *
 * Adds a per-user opt-out for the new "your ticket was assigned" email sent
 * to the requester when an agent is assigned.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $check->execute([$db, 'users', 'notify_ticket_assigned']);

    if ((int) $check->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `users`
             ADD COLUMN `notify_ticket_assigned` TINYINT(1) NOT NULL DEFAULT 1
             AFTER `notify_ticket_closed`"
        );
    }
};
