<?php
/**
 * Migration 062 — "View out-of-office status" permission
 *
 * Adds a grantable, read-only permission (`oof.view`) to the catalog so admins
 * can let non-managers (e.g. Power Users) open Admin → Settings → Out of Office
 * and see which agents are currently away — without being able to change any
 * coverage settings (that stays behind `automations.manage`).
 *
 * Catalog-only: it grants the permission to NO role here, so it appears in the
 * role permission matrix (Admin → Permission Levels) for an admin to assign.
 * Idempotent via INSERT IGNORE.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([$db, 'permissions']);
    if ((int) $stmt->fetchColumn() === 0) {
        return; // permissions catalog not present yet (pre-042) — nothing to do
    }

    $pdo->prepare(
        "INSERT IGNORE INTO `permissions` (perm_key, label, category, description, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        'oof.view',
        'View out-of-office status',
        'Automation',
        'Open the Out-of-Office page to see which agents are currently away. Read-only — cannot change coverage settings (that needs "Manage automations & escalations").',
        135,
    ]);
};
