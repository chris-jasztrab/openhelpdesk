<?php
/**
 * Migration 057 — `api_rate_limits` fixed-window counters for the mobile API
 *
 * Per-user request counters used by _apiRateLimit() to cap how many `/api/v1`
 * calls a token holder can make per minute. One row per (bucket, window):
 * `bucket` is e.g. "user:123", `window_start` is the unix epoch second the
 * 60-second window began, `count` is the running tally. Stale windows are pruned
 * opportunistically by the limiter, so the table stays tiny.
 *
 * Idempotent: created only if absent, so re-runs and schema installs are safe.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $exists->execute(['api_rate_limits']);
    if ((int) $exists->fetchColumn() === 0) {
        $pdo->exec(
            "CREATE TABLE `api_rate_limits` (
                `bucket`       varchar(64) NOT NULL,
                `window_start` int(10) unsigned NOT NULL,
                `count`        int(10) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`bucket`, `window_start`),
                KEY `idx_window_start` (`window_start`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
