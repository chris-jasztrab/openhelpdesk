<?php
/**
 * Migration 024 — Login attempt log
 *
 * Backs the API login rate limiter. We record one row per credential check
 * (success or failure) keyed by email + IP, then count recent failures in a
 * sliding window to decide whether to short-circuit further attempts.
 *
 * Rows are kept indefinitely so they double as an audit trail; a follow-up
 * job can prune anything older than ~30 days if the table grows.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email`        VARCHAR(255) NOT NULL,
            `ip`           VARCHAR(45)  NOT NULL,
            `succeeded`    TINYINT(1)   NOT NULL DEFAULT 0,
            `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_login_attempts_email_time` (`email`, `attempted_at`),
            INDEX `idx_login_attempts_ip_time`    (`ip`,    `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
