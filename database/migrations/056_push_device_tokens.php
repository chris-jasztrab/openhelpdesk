<?php
/**
 * Migration 056 — `push_device_tokens` for native mobile push notifications
 *
 * The mobile apps register the APNs/FCM token issued to them by the OS so the
 * server can deliver an in-app notification to the device as a native push.
 * One row per device token; `token` is unique so a device re-registering (or a
 * token the OS reassigns to a different signed-in user) updates the row in place
 * rather than accumulating duplicates.
 *
 * Idempotent: the table is only created if it does not already exist, so re-runs
 * (and installs from a schema that already ships it) are harmless.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $exists->execute(['push_device_tokens']);
    if ((int) $exists->fetchColumn() === 0) {
        $pdo->exec(
            "CREATE TABLE `push_device_tokens` (
                `id`          int(10) unsigned NOT NULL AUTO_INCREMENT,
                `user_id`     int(10) unsigned NOT NULL,
                `platform`    varchar(10) NOT NULL,
                `token`       varchar(512) NOT NULL,
                `device_name` varchar(255) DEFAULT NULL,
                `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
                `last_used_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_push_token` (`token`),
                KEY `idx_push_user` (`user_id`),
                CONSTRAINT `fk_push_tokens_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
