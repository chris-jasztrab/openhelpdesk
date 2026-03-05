<?php
/**
 * Migration 011 — Add timezone column to locations table
 *
 * Adds a nullable `timezone` VARCHAR(64) column to locations so each
 * location can store its own PHP timezone identifier (e.g. 'America/Toronto').
 *
 * The location_timezone_mode and location_timezone_shared settings are
 * used by the application to determine whether to use a single shared
 * timezone or a per-location timezone.
 *
 * Future: timezone can be populated automatically via a geo API using
 * the location's address. The getLocationTimezone() helper centralises
 * all timezone resolution logic to make that upgrade straightforward.
 *
 * Safe to run multiple times — skips ALTER if column already exists.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'timezone'"
    );
    $stmt->execute([$db]);

    if ((int) $stmt->fetchColumn() > 0) {
        return; // Already applied
    }

    $pdo->exec(
        "ALTER TABLE `locations` ADD COLUMN `timezone` VARCHAR(64) NULL DEFAULT NULL AFTER `description`"
    );
};
