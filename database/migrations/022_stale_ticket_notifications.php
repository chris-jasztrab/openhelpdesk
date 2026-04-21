<?php
/**
 * Migration 022 — Stale ticket notifications
 *
 * Adds per-type override for the stale threshold and seeds sensible
 * defaults for the new settings keys the feature relies on.
 *
 *   ticket_types.stale_threshold_hours — NULL = use global setting
 *   settings.stale_threshold_hours     — global threshold (default 72h)
 *   settings.stale_recheck_hours       — minimum gap between repeat nags
 *   settings.email_notify:ticket_stale_agent
 *   settings.email_notify:ticket_stale_requester
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    // ticket_types.stale_threshold_hours
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $check->execute([$db, 'ticket_types', 'stale_threshold_hours']);
    if ((int) $check->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `stale_threshold_hours` INT UNSIGNED NULL DEFAULT NULL"
        );
    }

    // Seed default settings (INSERT IGNORE — don't stomp on existing values)
    $seed = [
        'stale_threshold_hours'              => '72',
        'stale_recheck_hours'                => '24',
        'email_notify:ticket_stale_agent'     => '1',
        'email_notify:ticket_stale_requester' => '1',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($seed as $k => $v) {
        $stmt->execute([$k, $v]);
    }
};
