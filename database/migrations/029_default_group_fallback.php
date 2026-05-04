<?php
/**
 * Migration 029 — Default-group fallback for unrouted tickets
 *
 * Adds a system-wide `default_group_id` setting (the catch-all queue used
 * by every ticket-creation path when nothing else picks a group) and
 * back-fills any existing tickets that are already sitting with
 * `group_id IS NULL` into it.
 *
 * Why: pre-2.23, six creation paths could each leave a ticket with
 * `group_id = NULL`, and `autoAssignTicket()` short-circuits on null
 * groups, so those tickets sat invisibly in a "no group" queue forever.
 * 2.23 plumbs `default_group_id` through every path so the safety net
 * always catches them — this migration provides the safety net's value
 * for the first time (lowest-id existing group) and cleans up any
 * historical strays.
 *
 * Idempotent — guarded by inspecting `settings` and only mutating
 * NULL-group tickets.
 */
return static function (PDO $pdo): void {
    // 1. Seed `default_group_id` if missing. Pick the lowest-id existing
    //    group as a sensible default — admins can change it any time at
    //    Admin → Settings → Ticket Routing Defaults.
    $exists = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'default_group_id'");
    $exists->execute();
    $current = $exists->fetchColumn();

    $needsSeed = ($current === false) || ($current === null) || ((string) $current === '');

    if ($needsSeed) {
        $firstGroupId = $pdo->query('SELECT id FROM `groups` ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($firstGroupId) {
            $pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value)
                 VALUES ('default_group_id', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            )->execute([(string) (int) $firstGroupId]);
        }
        // If there are zero groups yet (fresh install), the setting stays
        // unset; it'll be auto-seeded the next time this migration runs OR
        // an admin saves the new picker after creating a group.
    }

    // 2. Back-fill any NULL-group tickets. Pull the now-current default
    //    (which we may have just seeded) and update — but only if there
    //    actually IS a default to point them at.
    $defaultId = (int) ($pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'default_group_id'")->fetchColumn() ?: 0);
    if ($defaultId > 0) {
        // Make sure the default still references a real group before we
        // copy it onto every NULL-group ticket — guards against a stale
        // setting pointing at a group that was deleted.
        $defaultExists = (int) $pdo->query("SELECT COUNT(*) FROM `groups` WHERE id = {$defaultId}")->fetchColumn();
        if ($defaultExists > 0) {
            $pdo->prepare('UPDATE tickets SET group_id = ? WHERE group_id IS NULL')
                ->execute([$defaultId]);
        }
    }
};
