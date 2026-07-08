<?php
/**
 * Migration 065 — Re-show the portal onboarding tour for everyone
 *
 * The portal walkthrough was rewritten in 2.142.0 to cover the features added
 * since it first shipped (per-type ticket forms, AI duplicate detection,
 * status banners, My Location visibility, escalation & SLAs, email replies,
 * drafts, and a practice-ticket section). Reset the per-user dismissal flag
 * so every user is offered the updated tour once; dismissing it again sets
 * the flag back to 0 as usual.
 *
 * Safe for staff rows too: the portal tour only ever auto-plays for
 * non-staff users on /portal, so a stale flag on agents/admins is inert.
 */
return static function (PDO $pdo): void {
    // Guard: only run if the column exists (it was added in migration 007).
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'users', 'show_portal_tour']);

    if ((int) $stmt->fetchColumn() === 1) {
        $pdo->exec('UPDATE `users` SET `show_portal_tour` = 1');
    }
};
