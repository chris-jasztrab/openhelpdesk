<?php
/**
 * Migration 059 — Add counted_days to sla_policies.
 *
 * Lets each policy choose which weekdays its SLA timer counts (e.g. exclude
 * Sundays). Stored as a CSV of day keys (mon,tue,wed,thu,fri,sat,sun).
 * NULL means "count every business-open day" — the prior behaviour — so
 * existing policies are unchanged.
 */
return static function (PDO $pdo): void {
    $pdo->exec("
        ALTER TABLE `sla_policies`
        ADD COLUMN `counted_days` VARCHAR(27) NULL DEFAULT NULL AFTER `resolution_minutes`
    ");
};
