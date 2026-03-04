<?php
/**
 * Migration 010 — Add power_user role to users.role ENUM
 *
 * Ensures the role column includes all four valid values:
 * admin, agent, power_user, user
 *
 * Safe to run multiple times — only alters the column if power_user
 * is not already present in the ENUM definition.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'"
    );
    $stmt->execute([$db]);
    $type = (string) $stmt->fetchColumn();

    if (str_contains($type, 'power_user')) {
        return; // Already applied
    }

    $pdo->exec(
        "ALTER TABLE `users` MODIFY `role` ENUM('admin','agent','power_user','user') NOT NULL DEFAULT 'user'"
    );
};
