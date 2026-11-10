<?php
/**
 * Migration 090 — default audit interval for Inventory › Audit mode.
 *
 * Scanning an asset in audit mode stamps last_audit_at and sets
 * next_audit_date to today + this many months unless the auditor typed a
 * date. 0 leaves next_audit_date untouched.
 */
return static function (PDO $pdo): void {
    $pdo->exec("INSERT IGNORE INTO `settings` (setting_key, setting_value) VALUES ('asset_audit_interval_months', '12')");
};
