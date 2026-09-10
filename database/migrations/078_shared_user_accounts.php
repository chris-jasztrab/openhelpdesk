<?php
/**
 * Migration 078 — flag a user account as a shared account
 *
 * Some accounts are not a person: a service-desk login, a branch circulation
 * account, a staff-room workstation signed in to Office 365 and never signed
 * out. Whoever sits down next inherits that session, and any ticket they raise
 * is attributed to the shared account rather than to them. There is currently
 * no way for the system to know an account is like that, so there is no way for
 * anything downstream to react to it.
 *
 * `users.is_shared_account` is that marker, and nothing more — it grants and
 * removes no access on its own. Migration 079 adds the conditional-field
 * machinery that consumes it, so an admin can put a "who are you?" field on the
 * ticket form that appears for shared logins only.
 *
 * Defaults to 0, so every existing account is treated as belonging to a person
 * until someone says otherwise.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'users', 'is_shared_account']);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `users`
             ADD COLUMN `is_shared_account` TINYINT(1) NOT NULL DEFAULT 0
             AFTER `is_external`"
        );
    }
};
