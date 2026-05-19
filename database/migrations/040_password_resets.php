<?php
/**
 * Migration 040 — Password reset tokens
 *
 * Backs the "Forgot password?" flow on the login page. One row per
 * outstanding reset link: we store only a SHA-256 hash of the random
 * token (so a DB leak doesn't hand attackers usable reset links) plus
 * the target user, an expiry, and the time the link was consumed.
 *
 * A token is valid when `used_at IS NULL AND expires_at > NOW()`.
 * Rows are kept after use as an audit trail; a follow-up prune can
 * delete anything older than a week.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `password_resets` (
            `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id`    INT UNSIGNED NOT NULL,
            `token_hash` CHAR(64)     NOT NULL,
            `expires_at` DATETIME     NOT NULL,
            `used_at`    DATETIME     NULL DEFAULT NULL,
            `requested_ip` VARCHAR(45) NULL DEFAULT NULL,
            `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_password_resets_token` (`token_hash`),
            KEY `idx_password_resets_user` (`user_id`, `created_at`),
            CONSTRAINT `fk_password_resets_user`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
