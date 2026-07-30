<?php
/**
 * Migration 072 — Stale ticket notification for group managers
 *
 * Seeds the new toggle that lets the stale-ticket processor also email the
 * manager(s) of the group behind a stale ticket (group_user_map.is_manager = 1
 * on the ticket's group, falling back to the group configured on the ticket's
 * type).
 *
 *   settings.email_notify:ticket_stale_manager
 *
 * Seeded to '0' on purpose. Every other email_notify:* key defaults to ON when
 * absent (see emailNotifyEnabled()), which for a brand-new recipient class
 * would mean managers start getting mail the moment this deploys. Writing an
 * explicit '0' makes the opt-in deliberate.
 *
 * Idempotent — INSERT IGNORE never stomps an existing choice.
 */
return static function (PDO $pdo): void {
    $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)')
        ->execute(['email_notify:ticket_stale_manager', '0']);
};
