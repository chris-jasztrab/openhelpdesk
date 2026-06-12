<?php
/**
 * Migration 049 — multi-branch targeting for status banners.
 *
 * Banners used to carry a single nullable `location_id` (NULL = every
 * branch). Real incidents often hit *some* branches — "Wi-Fi down at
 * Eastside and McCormick" — so targeting moves to a join table:
 *
 * - `status_banner_locations` (banner_id, location_id) — one row per
 *   targeted branch. A banner with NO rows is global (all branches),
 *   replacing the old NULL convention.
 * - Existing single-branch banners are backfilled into the join table,
 *   then the legacy `status_banners.location_id` column (and its FK +
 *   index) is dropped so there's a single source of truth.
 *
 * FK ON DELETE CASCADE both ways: deleting a banner removes its
 * targeting rows; deleting a branch removes just that branch from any
 * banner targeting it (a banner left with zero rows becomes global —
 * same end state as the old ON DELETE SET NULL).
 *
 * Idempotent — every step is guarded by an information_schema lookup.
 */
return static function (PDO $pdo): void {
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name   = 'status_banner_locations'"
    )->fetchColumn();

    if (!$tableExists) {
        $pdo->exec(
            "CREATE TABLE `status_banner_locations` (
                `banner_id`   INT UNSIGNED NOT NULL,
                `location_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`banner_id`, `location_id`),
                INDEX `idx_sbl_location` (`location_id`),
                CONSTRAINT `fk_sbl_banner`
                    FOREIGN KEY (`banner_id`) REFERENCES `status_banners`(`id`)
                    ON DELETE CASCADE,
                CONSTRAINT `fk_sbl_location`
                    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $colExists = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name   = 'status_banners'
           AND column_name  = 'location_id'"
    )->fetchColumn();

    if (!$colExists) {
        return; // fresh install from schema.sql — nothing to backfill
    }

    $pdo->exec(
        "INSERT IGNORE INTO status_banner_locations (banner_id, location_id)
         SELECT id, location_id FROM status_banners WHERE location_id IS NOT NULL"
    );

    $fkExists = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.table_constraints
         WHERE table_schema    = DATABASE()
           AND table_name      = 'status_banners'
           AND constraint_name = 'fk_status_banners_location'
           AND constraint_type = 'FOREIGN KEY'"
    )->fetchColumn();
    if ($fkExists) {
        $pdo->exec('ALTER TABLE `status_banners` DROP FOREIGN KEY `fk_status_banners_location`');
    }

    $idxExists = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name   = 'status_banners'
           AND index_name   = 'idx_status_banners_location'"
    )->fetchColumn();
    if ($idxExists) {
        $pdo->exec('ALTER TABLE `status_banners` DROP INDEX `idx_status_banners_location`');
    }

    $pdo->exec('ALTER TABLE `status_banners` DROP COLUMN `location_id`');
};
