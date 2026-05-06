<?php
/**
 * Migration 035 — AI group routing ("No Wrong Door")
 *
 * Adds the ai_route_group flag on ticket_types and an
 * ai_group_classifications table that records the AI's pick-a-group
 * decisions for the audit trail.
 *
 * Why: certain ticket types (e.g. a portal "I don't know who handles
 * this" entry point) should be routed to the right group by AI rather
 * than pinned to a single default group. Existing AI infra picks a
 * skill within a group; this migration adds the layer above it.
 *
 * Idempotent.
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

    // ── ticket_types.ai_route_group ─────────────────────────────────
    if (!$hasColumn('ticket_types', 'ai_route_group')) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `ai_route_group` TINYINT(1) NOT NULL DEFAULT 0
             AFTER `is_confidential`"
        );
    }

    // ── ai_group_classifications ────────────────────────────────────
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ai_group_classifications` (
            `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ticket_id`           INT UNSIGNED NOT NULL,
            `provider`            VARCHAR(32)  NOT NULL,
            `model`               VARCHAR(128) NOT NULL,
            `candidate_group_ids` JSON         NOT NULL,
            `suggested_group_id`  INT UNSIGNED     DEFAULT NULL,
            `applied_group_id`    INT UNSIGNED     DEFAULT NULL,
            `confidence`          DECIMAL(4,3) NOT NULL DEFAULT 0.000,
            `reasoning`           TEXT             DEFAULT NULL,
            `raw_output`          JSON             DEFAULT NULL,
            `latency_ms`          INT UNSIGNED     DEFAULT NULL,
            `prompt_tokens`       INT UNSIGNED     DEFAULT NULL,
            `output_tokens`       INT UNSIGNED     DEFAULT NULL,
            `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_ticket`      (`ticket_id`),
            KEY `idx_created`     (`created_at`),
            FOREIGN KEY (`ticket_id`)          REFERENCES `tickets`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`suggested_group_id`) REFERENCES `groups`(`id`)  ON DELETE SET NULL,
            FOREIGN KEY (`applied_group_id`)   REFERENCES `groups`(`id`)  ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // ── tickets pointer ─────────────────────────────────────────────
    if (!$hasColumn('tickets', 'ai_group_classification_id')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `ai_group_classification_id` INT UNSIGNED NULL DEFAULT NULL,
             ADD CONSTRAINT `fk_tickets_ai_group_classification`
                 FOREIGN KEY (`ai_group_classification_id`)
                 REFERENCES `ai_group_classifications`(`id`) ON DELETE SET NULL"
        );
    }
};
