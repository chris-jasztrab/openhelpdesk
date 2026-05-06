<?php
/**
 * Migration 033 — `status_banners` table for the public status page /
 * incident banner feature.
 *
 * Branches occasionally hit infrastructure issues (Wi-Fi down, ILS slow,
 * printer offline, etc.) that send a wave of duplicate tickets to the
 * helpdesk. A pinned banner on the portal — editable by any agent — lets
 * staff see "Network issue at Eastside, ETA 2pm" the moment they open
 * the help page, so we stop fielding the same ticket twelve times.
 *
 * - `body_html`     stores rich HTML from CKEditor (same trust model as
 *                   KB articles).
 * - `severity`      drives the banner colour / icon (info / warning /
 *                   critical).
 * - `location_id`   optional FK to `locations` — when set, the banner is
 *                   only shown to portal users whose `users.location_id`
 *                   matches; agents and admins always see every active
 *                   banner regardless of branch. NULL = all branches.
 * - `starts_at` /
 *   `expires_at`    optional time window; both NULL means "show now,
 *                   forever, until cleared".
 * - `is_active`     soft-delete flag; "Clear" sets this to 0 so the
 *                   incident drops off the portal but the row is kept
 *                   for audit / history.
 *
 * FK ON DELETE SET NULL — losing a location row or the agent who posted
 * the banner shouldn't take the row with it.
 *
 * Idempotent — guarded by an information_schema lookup so re-running on
 * a database that already has the table is a no-op.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name   = 'status_banners'"
    )->fetchColumn();

    if ((int) $exists > 0) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE `status_banners` (
            `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title`       VARCHAR(255) NULL,
            `body_html`   MEDIUMTEXT NOT NULL,
            `severity`    ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
            `location_id` INT UNSIGNED NULL,
            `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
            `starts_at`   DATETIME NULL,
            `expires_at`  DATETIME NULL,
            `created_by`  INT UNSIGNED NULL,
            `updated_by`  INT UNSIGNED NULL,
            `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_status_banners_active`   (`is_active`, `expires_at`, `starts_at`),
            INDEX `idx_status_banners_location` (`location_id`),
            CONSTRAINT `fk_status_banners_location`
                FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_status_banners_created_by`
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_status_banners_updated_by`
                FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
