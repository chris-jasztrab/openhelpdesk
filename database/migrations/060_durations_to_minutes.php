<?php
/**
 * Migration 060 — Store all editable durations in minutes.
 *
 * The stale-ticket and escalation duration fields were stored in whole hours,
 * which made them inconsistent with the SLA fields (already minutes) and meant
 * sub-hour values couldn't be expressed. They now all store minutes so the
 * admin can type any of d/h/m into any duration field.
 *
 * Renames + converts (×60), guarded so it's safe to re-run and a no-op on
 * fresh installs where the columns/keys are already in minutes:
 *
 *   ticket_types.stale_threshold_hours   → stale_threshold_minutes
 *   escalation_rules.cooldown_hours       → cooldown_minutes
 *   settings.stale_threshold_hours        → stale_threshold_minutes  (value ×60)
 *   settings.stale_recheck_hours          → stale_recheck_minutes    (value ×60)
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $columnExists = static function (string $table, string $column) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$db, $table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    // ── ticket_types.stale_threshold_hours → stale_threshold_minutes ──
    if ($columnExists('ticket_types', 'stale_threshold_hours')
        && !$columnExists('ticket_types', 'stale_threshold_minutes')) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             CHANGE `stale_threshold_hours` `stale_threshold_minutes` INT UNSIGNED NULL DEFAULT NULL"
        );
        $pdo->exec(
            "UPDATE `ticket_types`
             SET `stale_threshold_minutes` = `stale_threshold_minutes` * 60
             WHERE `stale_threshold_minutes` IS NOT NULL"
        );
    }

    // ── escalation_rules.cooldown_hours → cooldown_minutes ──
    if ($columnExists('escalation_rules', 'cooldown_hours')
        && !$columnExists('escalation_rules', 'cooldown_minutes')) {
        $pdo->exec(
            "ALTER TABLE `escalation_rules`
             CHANGE `cooldown_hours` `cooldown_minutes` INT UNSIGNED NOT NULL DEFAULT 0"
        );
        $pdo->exec("UPDATE `escalation_rules` SET `cooldown_minutes` = `cooldown_minutes` * 60");
    }

    // ── settings keys: rename + convert hours → minutes ──
    $renameSetting = static function (string $oldKey, string $newKey) use ($pdo): void {
        $sel = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $sel->execute([$oldKey]);
        $val = $sel->fetchColumn();
        if ($val === false) {
            return; // old key absent — nothing to migrate
        }
        $minutes = (int) $val * 60;
        // Only create the new key if it doesn't already exist, then drop the old.
        $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)')
            ->execute([$newKey, (string) $minutes]);
        $pdo->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$oldKey]);
    };

    $renameSetting('stale_threshold_hours', 'stale_threshold_minutes');
    $renameSetting('stale_recheck_hours',   'stale_recheck_minutes');
};
