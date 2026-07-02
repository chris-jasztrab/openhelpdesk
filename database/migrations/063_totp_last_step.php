<?php
/**
 * Migration 063 — TOTP replay protection
 *
 * Adds users.totp_last_step: the last TOTP time-step counter a user
 * successfully authenticated with. totpVerifyOnce() rejects any code whose
 * matched step is <= this value, so a captured/shoulder-surfed 6-digit code
 * can't be replayed within its ~90s validity window to open a second session.
 *
 * Nullable (never used before first 2FA login). Idempotent: skips if present.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'users', 'totp_last_step']);
    if ((int) $stmt->fetchColumn() > 0) {
        return; // already applied
    }

    $pdo->exec('ALTER TABLE `users` ADD COLUMN `totp_last_step` BIGINT NULL DEFAULT NULL AFTER `totp_secret`');
};
