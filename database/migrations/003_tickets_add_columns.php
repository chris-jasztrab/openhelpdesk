<?php
/**
 * Migration 003 — Add columns to `tickets` that were added after v1.0.0
 *
 * Also extends the `status` ENUM to include the two new waiting statuses.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $columnExists = static function (string $table, string $column) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$db, $table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    // Ticket merge reference
    if (!$columnExists('tickets', 'merged_into_ticket_id')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `merged_into_ticket_id` INT UNSIGNED DEFAULT NULL,
             ADD FOREIGN KEY (`merged_into_ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL"
        );
    }

    // Legacy ID (used during CSV/data imports)
    if (!$columnExists('tickets', 'legacy_id')) {
        $pdo->exec("ALTER TABLE `tickets` ADD COLUMN `legacy_id` VARCHAR(50) DEFAULT NULL AFTER `id`");
    }

    // SLA pause tracking
    if (!$columnExists('tickets', 'sla_paused_at')) {
        $pdo->exec("ALTER TABLE `tickets` ADD COLUMN `sla_paused_at` DATETIME DEFAULT NULL");
    }

    // Extend status ENUM to include waiting statuses if not already present
    $stmt = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'status'"
    );
    $stmt->execute([$db]);
    $enumDef = (string) $stmt->fetchColumn();

    if ($enumDef !== '' && strpos($enumDef, 'waiting_on_customer') === false) {
        $pdo->exec(
            "ALTER TABLE `tickets` MODIFY COLUMN `status`
             ENUM('open','in_progress','pending','waiting_on_customer','waiting_on_third_party','resolved','closed')
             NOT NULL DEFAULT 'open'"
        );
    }
};
