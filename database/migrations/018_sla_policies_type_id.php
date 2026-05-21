<?php
/**
 * Migration 018 — Add type_id to sla_policies for per-type SLA policies.
 *
 * Allows different SLA targets per (ticket_type, priority) combination.
 * Rows with type_id = NULL serve as the default/fallback policy.
 */
return static function (PDO $pdo): void {
    // Add type_id column
    $pdo->exec("
        ALTER TABLE `sla_policies`
        ADD COLUMN `type_id` INT UNSIGNED NULL AFTER `id`
    ");

    // Add generated column for NULL-safe uniqueness (MySQL treats NULL != NULL in UNIQUE)
    $pdo->exec("
        ALTER TABLE `sla_policies`
        ADD COLUMN `type_id_norm` INT UNSIGNED GENERATED ALWAYS AS (COALESCE(type_id, 0)) STORED AFTER `type_id`
    ");

    // Must drop the FK on priority_id before we can drop its unique index
    // Find and drop the FK constraint referencing priority_id
    $fks = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'sla_policies'
          AND COLUMN_NAME = 'priority_id'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($fks as $fk) {
        $pdo->exec("ALTER TABLE `sla_policies` DROP FOREIGN KEY `{$fk}`");
    }

    // Now drop the old unique index on priority_id
    $pdo->exec("ALTER TABLE `sla_policies` DROP INDEX `priority_id`");

    // Re-add the FK on priority_id, plus the new composite unique and type_id FK.
    // The type_id FK must be ON DELETE RESTRICT (not CASCADE): type_id is the base
    // column of the `type_id_norm` generated column, and MySQL 8 rejects a
    // cascading FK on such a column (errno 1215). MariaDB allows it, but keeping
    // RESTRICT here makes the migration portable across both engines.
    $pdo->exec("
        ALTER TABLE `sla_policies`
        ADD UNIQUE KEY `uniq_type_priority` (`type_id_norm`, `priority_id`),
        ADD FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities`(`id`) ON DELETE CASCADE,
        ADD FOREIGN KEY (`type_id`) REFERENCES `ticket_types`(`id`) ON DELETE RESTRICT
    ");
};
