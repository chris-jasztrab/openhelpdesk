<?php
/**
 * Migration 044 — `tickets.view_all` permission + confidential watcher purge
 *
 * Ticket visibility for staff is moving to fail-closed: an agent sees only the
 * tickets in their group(s) unless they hold the new `tickets.view_all`
 * permission (which sees every group's tickets EXCEPT confidential ones).
 * Previously a groupless staff account implicitly saw everything; that
 * accidental "see-all" is being removed.
 *
 * To preserve current behaviour for intentional power users on deploy, the new
 * permission is granted to the built-in `power_user` role here. Admins bypass
 * every permission check, so they are unaffected.
 *
 * Confidential tickets are also being locked down so nothing but the creator
 * and the confidential group can see them. Watchers are no longer permitted on
 * confidential tickets (one fewer accidental-leak path), so any pre-existing
 * watcher rows on confidential-type tickets are purged here.
 *
 * Idempotent: INSERT IGNORE on the catalog/grant rows and a plain DELETE for
 * the purge make re-runs harmless (schema_migrations prevents them anyway).
 */
return static function (PDO $pdo): void {
    // ── 1. Seed the permission catalog row ───────────────────────────────────
    $pdo->prepare(
        "INSERT IGNORE INTO `permissions`
            (perm_key, label, category, description, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        'tickets.view_all',
        'View all tickets',
        'Tickets',
        'See tickets across every group, even groups the user does not belong to. '
            . 'Never includes confidential ticket types — those stay visible only to '
            . 'the confidential group.',
        45, // sits just after "Manage ticket templates" (40) in the matrix
    ]);

    // ── 2. Grant it to the built-in power_user role ──────────────────────────
    $roleIdStmt = $pdo->prepare('SELECT id FROM `roles` WHERE slug = ?');
    $roleIdStmt->execute(['power_user']);
    $powerUserId = (int) $roleIdStmt->fetchColumn();
    if ($powerUserId > 0) {
        $pdo->prepare(
            'INSERT IGNORE INTO `role_permissions` (role_id, perm_key) VALUES (?, ?)'
        )->execute([$powerUserId, 'tickets.view_all']);
    }

    // ── 3. Purge watchers on confidential tickets ────────────────────────────
    // Confidential tickets may not have watchers. Remove any that exist on a
    // ticket whose type is flagged confidential. Guarded so it is a no-op if the
    // ticket_watchers table was never created.
    $hasWatchers = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $hasWatchers->execute(['ticket_watchers']);
    if ((int) $hasWatchers->fetchColumn() > 0) {
        $pdo->exec(
            "DELETE tw FROM ticket_watchers tw
             JOIN tickets t       ON tw.ticket_id = t.id
             JOIN ticket_types tt ON t.type_id    = tt.id
             WHERE COALESCE(tt.is_confidential, 0) = 1"
        );
    }
};
