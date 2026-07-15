<?php
/**
 * Migration 070 — Per-ticket-type business-hours override (for SLA)
 *
 * Some ticket types map to departments that don't keep the same hours as the
 * branch — e.g. a back-office team that only works 9AM–5PM Mon–Fri while the
 * library floor is open evenings and weekends. SLA timers for those types
 * should count only the department's hours.
 *
 * This column stores an optional weekly schedule as JSON in the same shape as
 * the global `business_hours_schedule` setting:
 *
 *   {"mon":["09:00","17:00"], ... , "sat":null, "sun":null}
 *
 * NULL (the default) means "inherit the global business hours". The timezone is
 * always inherited from the global setting — a per-type override changes the
 * open/close hours only, not the timezone.
 *
 * Stored as TEXT (not JSON type) to match the MariaDB dump the schema is built
 * from; validated/decoded in PHP. Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_types', 'business_hours_schedule']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `business_hours_schedule` TEXT NULL DEFAULT NULL
             AFTER `stale_threshold_minutes`"
        );
    }
};
