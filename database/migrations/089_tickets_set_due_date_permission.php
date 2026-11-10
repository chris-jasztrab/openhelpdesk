<?php
/**
 * Migration 089 — `tickets.set_due_date` permission.
 *
 * The Due Date field on the New Ticket form and the ticket page's Update form
 * is now shown only to roles holding this permission (admins bypass every
 * check). Granted to no role here: grant it from Admin → Roles to the roles
 * that should set due dates. The timeline, lists and reminders still show
 * the date to everyone.
 */
return static function (PDO $pdo): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['permissions']);
    if ((int) $stmt->fetchColumn() === 0) {
        return; // permissions catalog not present yet (pre-042)
    }
    $pdo->prepare(
        "INSERT IGNORE INTO `permissions` (perm_key, label, category, description, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        'tickets.set_due_date',
        'Set due dates',
        'Tickets',
        'Show the Due Date field on the New Ticket form and the ticket page, and allow changing it. Without it the date is read-only.',
        46, // right after "View all tickets" (45)
    ]);
};
