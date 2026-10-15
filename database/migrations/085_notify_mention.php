<?php
/**
 * Migration 085 — per-user opt-out for @mention emails.
 *
 *   notify_mention — email a staff member when someone @mentions them in a
 *                    ticket reply or note (default 1 = subscribed)
 */
return static function (PDO $pdo): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['users', 'notify_mention']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `notify_mention` TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_note_added`");
    }
};
