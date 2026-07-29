<?php
/**
 * Migration 071 — Scheduled reports belong to whoever created them
 *
 * Scheduling used to be an admin-only, shared list: every schedule was visible
 * to everyone who could reach the page, and nothing recorded who made it. Now
 * that report viewers can schedule too (see 042's `reports.view`), a schedule
 * is personal — you see and manage your own, admins see everyone's.
 *
 * `created_by` NULL means "no identifiable owner" and is treated as
 * admin-only by the app, which is also where a schedule lands if its creator's
 * account is later deleted (ON DELETE SET NULL) — the schedule keeps running
 * instead of silently disappearing with the person who left.
 *
 * Pre-ownership rows are deleted rather than assigned a guessed owner: their
 * recipient lists were written under the old "anyone can edit anyone's"
 * assumption, so handing them to one person would give that person a schedule
 * mailing addresses they never chose. Confirmed with the site owner before
 * writing this. Fresh installs have no rows to lose.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'scheduled_reports', 'created_by']);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $orphans = (int) $pdo->query('SELECT COUNT(*) FROM `scheduled_reports`')->fetchColumn();
    if ($orphans > 0) {
        $pdo->exec('DELETE FROM `scheduled_reports`');
        echo "  → cleared {$orphans} pre-ownership schedule(s); they must be recreated by their owners\n";
    }

    $pdo->exec(
        "ALTER TABLE `scheduled_reports`
         ADD COLUMN `created_by` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
         ADD KEY `idx_scheduled_reports_created_by` (`created_by`),
         ADD CONSTRAINT `fk_scheduled_reports_created_by`
             FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL"
    );
};
