<?php
/**
 * Migration 052 — split `tickets.forward` into internal / external
 *
 * Forwarding is now governed by two separate permissions:
 *   tickets.forward.internal — forward to a contact who already exists in the
 *                              system as a real (non-external) user.
 *   tickets.forward.external — forward to a brand-new email address, or to a
 *                              previously auto-provisioned external contact
 *                              (users.is_external = 1).
 *
 * Every role that currently holds the umbrella `tickets.forward` permission is
 * granted BOTH new permissions, so existing capability is preserved exactly.
 * The old umbrella permission and its grants are then removed.
 *
 * Idempotent: INSERT IGNORE on the catalog rows + grants, and the umbrella
 * cleanup is a plain DELETE, so re-runs are harmless (schema_migrations also
 * prevents them).
 */
return static function (PDO $pdo): void {
    // ── 1. Seed the two new catalog rows ─────────────────────────────────────
    $permInsert = $pdo->prepare(
        "INSERT IGNORE INTO `permissions`
            (perm_key, label, category, description, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    );
    $permInsert->execute([
        'tickets.forward.internal',
        'Forward tickets to internal contacts',
        'Tickets',
        'Forward a ticket to a contact who already exists in the system (an internal user). '
            . 'Confidential tickets can never be forwarded.',
        34,
    ]);
    $permInsert->execute([
        'tickets.forward.external',
        'Forward tickets to external contacts',
        'Tickets',
        'Forward a ticket to an external email address that is not an internal user. '
            . 'The recipient is added as an external contact. Confidential tickets can never be forwarded.',
        35,
    ]);

    // ── 2. Grant both to every role that has the umbrella permission ─────────
    $roleIds = $pdo->query(
        "SELECT role_id FROM role_permissions WHERE perm_key = 'tickets.forward'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $grantInsert = $pdo->prepare(
        'INSERT IGNORE INTO `role_permissions` (role_id, perm_key) VALUES (?, ?)'
    );
    foreach ($roleIds as $roleId) {
        $grantInsert->execute([(int) $roleId, 'tickets.forward.internal']);
        $grantInsert->execute([(int) $roleId, 'tickets.forward.external']);
    }

    // ── 3. Remove the umbrella permission and its grants ─────────────────────
    $pdo->exec("DELETE FROM `role_permissions` WHERE perm_key = 'tickets.forward'");
    $pdo->exec("DELETE FROM `permissions` WHERE perm_key = 'tickets.forward'");
};
