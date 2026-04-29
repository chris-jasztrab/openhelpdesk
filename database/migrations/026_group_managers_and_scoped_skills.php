<?php
/**
 * Migration 026 — Group managers and group-scoped skills
 *
 * Lets each group designate one or more "managers" who can maintain
 * agent skills for their team members without admin involvement, and
 * lets skills be either globally-owned (admin only) or scoped to a
 * single group (managers of that group can edit them).
 *
 *   group_user_map.is_manager   — flag on the existing membership
 *                                  join table. Default 0 preserves
 *                                  today's behaviour (no managers).
 *   agent_skills.group_id       — NULL = global skill (admin only).
 *                                  Set = skill owned by one group;
 *                                  managers of that group can edit.
 *
 * Idempotent — guards every column add. No data backfill needed.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $hasColumn = static function (string $table, string $column) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$db, $table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasColumn('group_user_map', 'is_manager')) {
        $pdo->exec(
            "ALTER TABLE `group_user_map`
             ADD COLUMN `is_manager` TINYINT(1) NOT NULL DEFAULT 0"
        );
    }

    if (!$hasColumn('agent_skills', 'group_id')) {
        $pdo->exec(
            "ALTER TABLE `agent_skills`
             ADD COLUMN `group_id` INT UNSIGNED NULL DEFAULT NULL,
             ADD CONSTRAINT `fk_agent_skills_group`
                 FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE SET NULL"
        );
    }
};
