<?php
/**
 * Migration 048 — Per-action bulk ticket permissions
 *
 * Until now every staff member could run all the list-view bulk actions
 * (assign, close, merge, change status/priority/group) — they were gated only
 * by Auth::requireStaff() — while bulk DELETE was admin-only and hidden from
 * agents entirely.
 *
 * This splits each bulk action into its own grantable permission so an admin
 * can decide, per role, exactly which bulk actions an agent may use — including
 * the brand-new ability to let agents bulk-delete tickets.
 *
 * Backward compatibility: the six non-destructive bulk permissions are granted
 * to EVERY existing non-admin staff role (built-in agent / power_user and any
 * custom staff levels), so deploying this changes nothing about what staff can
 * already do today. `tickets.bulk_delete` is granted to NO role — admins bypass
 * every permission check, so they keep it; everyone else starts without it until
 * an admin ticks the box.
 *
 * Idempotent: INSERT IGNORE on every catalog and grant row makes re-runs
 * harmless (schema_migrations prevents them anyway).
 */
return static function (PDO $pdo): void {
    // ── 1. Seed the permission catalog rows (new "Bulk Actions" category) ────
    // [perm_key, label, category, description, sort_order]
    $permSeeds = [
        ['tickets.bulk_assign',   'Bulk assign tickets',          'Bulk Actions', 'Assign or unassign several selected tickets at once from the ticket list.',       200],
        ['tickets.bulk_close',    'Bulk close tickets',           'Bulk Actions', 'Close several selected tickets at once from the ticket list.',                     201],
        ['tickets.bulk_merge',    'Bulk merge tickets',           'Bulk Actions', 'Merge several selected tickets into one from the ticket list.',                    202],
        ['tickets.bulk_status',   'Bulk change status',           'Bulk Actions', 'Change the status of several selected tickets at once.',                           203],
        ['tickets.bulk_priority', 'Bulk change priority',         'Bulk Actions', 'Change the priority of several selected tickets at once.',                         204],
        ['tickets.bulk_group',    'Bulk change group',            'Bulk Actions', 'Change the group of several selected tickets at once.',                            205],
        ['tickets.bulk_delete',   'Bulk delete tickets',          'Bulk Actions', 'Permanently delete several selected tickets at once. Destructive and irreversible.', 206],
    ];
    $permInsert = $pdo->prepare(
        "INSERT IGNORE INTO `permissions`
            (perm_key, label, category, description, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($permSeeds as $p) {
        $permInsert->execute($p);
    }

    // ── 2. Preserve today's behaviour: grant the six non-destructive bulk
    //       permissions to every existing non-admin staff role. ───────────────
    $preserve = [
        'tickets.bulk_assign',
        'tickets.bulk_close',
        'tickets.bulk_merge',
        'tickets.bulk_status',
        'tickets.bulk_priority',
        'tickets.bulk_group',
    ];
    $grantToStaff = $pdo->prepare(
        "INSERT IGNORE INTO `role_permissions` (role_id, perm_key)
         SELECT id, ? FROM `roles` WHERE is_staff = 1 AND is_admin = 0"
    );
    foreach ($preserve as $key) {
        $grantToStaff->execute([$key]);
    }

    // tickets.bulk_delete is intentionally granted to no role here.
};
