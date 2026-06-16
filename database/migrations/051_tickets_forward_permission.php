<?php
/**
 * Migration 051 — `tickets.forward` permission
 *
 * Forwarding a ticket to external third parties / contacts (shipped in 2.93.0)
 * is now a grantable capability instead of being available to every staff role.
 *
 * To preserve the behaviour 2.93.0 introduced — where any staff member could
 * forward — the permission is granted here to the built-in `agent` and
 * `power_user` roles. Admins bypass every permission check, so they keep it
 * automatically. Custom roles can be granted it from the role permission matrix.
 *
 * Idempotent: INSERT IGNORE on the catalog row and the grants make re-runs
 * harmless (schema_migrations prevents them anyway).
 */
return static function (PDO $pdo): void {
    // ── 1. Seed the permission catalog row ───────────────────────────────────
    $pdo->prepare(
        "INSERT IGNORE INTO `permissions`
            (perm_key, label, category, description, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        'tickets.forward',
        'Forward tickets',
        'Tickets',
        'Forward a ticket — its details, conversation and attachments — to external '
            . 'third parties or other contacts by email. Confidential tickets can never be forwarded.',
        35, // sits just before "Manage ticket templates" (40) in the matrix
    ]);

    // ── 2. Grant it to the built-in agent and power_user roles ───────────────
    $roleIdStmt  = $pdo->prepare('SELECT id FROM `roles` WHERE slug = ?');
    $grantInsert = $pdo->prepare(
        'INSERT IGNORE INTO `role_permissions` (role_id, perm_key) VALUES (?, ?)'
    );
    foreach (['agent', 'power_user'] as $slug) {
        $roleIdStmt->execute([$slug]);
        $roleId = (int) $roleIdStmt->fetchColumn();
        if ($roleId > 0) {
            $grantInsert->execute([$roleId, 'tickets.forward']);
        }
    }
};
