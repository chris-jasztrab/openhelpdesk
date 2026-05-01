<?php
/**
 * Migration 028 — Global user presence
 *
 * Adds a `user_presence` table that tracks who currently has the app open in
 * a browser. The base layout pings `POST /api/presence` every 30s for any
 * authenticated user; this migration just sets up the storage.
 *
 *   user_id    — PK; one row per logged-in user. Old rows are upserted, not
 *                duplicated.
 *   last_seen  — DATETIME of the most recent heartbeat. The "Who's Online"
 *                admin page and the First Available auto-assign strategy both
 *                treat anyone with last_seen within the last 60 seconds as
 *                currently online.
 *   ip_address — VARCHAR(45) so it fits IPv6 in canonical form. Optional;
 *                shown on the admin page so support can tell remote vs
 *                in-building sessions apart.
 *   user_agent — VARCHAR(255) trimmed UA string. Optional; helps spot
 *                Chrome / Edge / mobile sessions on the admin page.
 *
 * Idempotent — guarded with CREATE TABLE IF NOT EXISTS.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `user_presence` (
            `user_id`    INT UNSIGNED NOT NULL,
            `last_seen`  DATETIME NOT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`user_id`),
            KEY `idx_user_presence_last_seen` (`last_seen`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
