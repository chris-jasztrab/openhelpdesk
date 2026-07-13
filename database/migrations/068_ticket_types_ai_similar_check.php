<?php
/**
 * Migration 068 — Add ai_similar_check_enabled column to ticket_types
 *
 * Per-type on/off switch for the "similar past tickets" agent assist added in
 * migration 067. The feature is still gated behind the global
 * `ai_similar_enabled` setting plus the `ai_enabled` master switch; this column
 * lets an admin exclude specific ticket types even when the feature is on
 * globally (and confidential types are always excluded regardless).
 *
 * Default 1 (on) preserves existing behaviour: before this column, turning the
 * feature on globally showed the panel for every non-confidential type, so an
 * install that already enabled it keeps that exact behaviour after upgrade.
 * Admins switch individual types off as needed.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_types', 'ai_similar_check_enabled']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `ai_similar_check_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `ai_dup_threshold`"
        );
    }
};
