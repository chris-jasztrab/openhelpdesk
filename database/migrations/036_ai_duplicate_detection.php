<?php
/**
 * Migration 036 — AI duplicate-ticket detection
 *
 * Adds two columns to ticket_types so admins can opt this feature in
 * per type and tune the confidence threshold:
 *
 *   ai_dup_check_enabled  TINYINT(1)   default 0 — off until enabled
 *   ai_dup_threshold      DECIMAL(3,2) default 0.75 — match confidence floor
 *
 * Plus an ai_duplicate_classifications audit table that records each
 * AI call so we can tune thresholds and prove the feature isn't
 * over-suggesting. Mirrors ai_classifications / ai_group_classifications.
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

    if (!$hasColumn('ticket_types', 'ai_dup_check_enabled')) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `ai_dup_check_enabled` TINYINT(1) NOT NULL DEFAULT 0
             AFTER `ai_route_group`"
        );
    }

    if (!$hasColumn('ticket_types', 'ai_dup_threshold')) {
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `ai_dup_threshold` DECIMAL(3,2) NOT NULL DEFAULT 0.75
             AFTER `ai_dup_check_enabled`"
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ai_duplicate_classifications` (
            `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id`             INT UNSIGNED NOT NULL,
            `type_id`             INT UNSIGNED     DEFAULT NULL,
            `location_id`         INT UNSIGNED     DEFAULT NULL,
            `provider`            VARCHAR(32)  NOT NULL,
            `model`               VARCHAR(128) NOT NULL,
            `subject`             VARCHAR(255) NOT NULL,
            `candidate_ticket_ids` JSON        NOT NULL,
            `matches`             JSON         NOT NULL,
            `threshold`           DECIMAL(3,2) NOT NULL DEFAULT 0.75,
            `decision`            ENUM('no_match','suggested','suppressed') NOT NULL DEFAULT 'no_match',
            `chosen_ticket_id`    INT UNSIGNED     DEFAULT NULL,
            `raw_output`          JSON             DEFAULT NULL,
            `latency_ms`          INT UNSIGNED     DEFAULT NULL,
            `prompt_tokens`       INT UNSIGNED     DEFAULT NULL,
            `output_tokens`       INT UNSIGNED     DEFAULT NULL,
            `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_user`        (`user_id`),
            KEY `idx_created`     (`created_at`),
            KEY `idx_type`        (`type_id`),
            FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`)         ON DELETE CASCADE,
            FOREIGN KEY (`type_id`)          REFERENCES `ticket_types`(`id`)  ON DELETE SET NULL,
            FOREIGN KEY (`location_id`)      REFERENCES `locations`(`id`)     ON DELETE SET NULL,
            FOREIGN KEY (`chosen_ticket_id`) REFERENCES `tickets`(`id`)       ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
