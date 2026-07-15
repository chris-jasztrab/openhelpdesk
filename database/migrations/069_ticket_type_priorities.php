<?php
/**
 * Migration 069 — Per-ticket-type available priorities
 *
 * Some ticket types (i.e. departments) don't use the full priority scale — a
 * team might only ever triage as Low / Medium / High and never Critical. This
 * join table restricts which priorities a type offers on its New Ticket form
 * and priority pickers.
 *
 * Semantics: a type with NO rows here is UNRESTRICTED — every priority is
 * available (the pre-migration behaviour, so upgrades are unaffected). A type
 * with rows offers ONLY the listed priorities.
 *
 * Both FKs cascade on delete: dropping a type or a priority prunes its rows.
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_type_priorities']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(
            "CREATE TABLE `ticket_type_priorities` (
                `type_id` INT UNSIGNED NOT NULL,
                `priority_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`type_id`, `priority_id`),
                KEY `idx_ttp_priority` (`priority_id`),
                CONSTRAINT `fk_ttp_type` FOREIGN KEY (`type_id`)
                    REFERENCES `ticket_types` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_ttp_priority` FOREIGN KEY (`priority_id`)
                    REFERENCES `ticket_priorities` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
