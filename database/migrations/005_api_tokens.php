<?php
/**
 * Migration 005 — Create api_tokens table
 *
 * Stores Bearer tokens for the mobile REST API (/api/v1/).
 * Each row represents one active device session.
 */
return static function (PDO $pdo): void {
    $db   = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([$db, 'api_tokens']);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "CREATE TABLE `api_tokens` (
                `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id`      INT UNSIGNED NOT NULL,
                `token`        VARCHAR(64)  NOT NULL,
                `device_name`  VARCHAR(255) NULL DEFAULT NULL,
                `last_used_at` DATETIME     NULL DEFAULT NULL,
                `expires_at`   DATETIME     NULL DEFAULT NULL,
                `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_token`  (`token`),
                INDEX  `idx_user`      (`user_id`),
                CONSTRAINT `fk_api_tokens_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
