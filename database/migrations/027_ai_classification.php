<?php
/**
 * Migration 027 — AI ticket classification
 *
 * Adds storage for AI-driven classification and the `ai_skill_based`
 * group-assignment strategy. Each classified ticket gets ONE row in
 * `ai_classifications` capturing the provider, model, requested skills,
 * confidence, sentiment, raw provider output, and any subsequent admin
 * override. Tickets carry a pointer to the latest classification plus
 * a denormalised sentiment string for fast filtering / reporting.
 *
 * The `groups.assign_strategy` ENUM is widened (not replaced) so the
 * existing five strategies keep working unchanged.
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

    // ── ai_classifications ───────────────────────────────────────────
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ai_classifications` (
            `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ticket_id`          INT UNSIGNED NOT NULL,
            `provider`           VARCHAR(32)  NOT NULL,
            `model`              VARCHAR(128) NOT NULL,
            `suggested_skill_ids` JSON         NOT NULL,
            `confidence`         DECIMAL(4,3) NOT NULL DEFAULT 0.000,
            `sentiment`          VARCHAR(32)      DEFAULT NULL,
            `reasoning`          TEXT             DEFAULT NULL,
            `raw_output`         JSON             DEFAULT NULL,
            `latency_ms`         INT UNSIGNED     DEFAULT NULL,
            `prompt_tokens`      INT UNSIGNED     DEFAULT NULL,
            `output_tokens`      INT UNSIGNED     DEFAULT NULL,
            `overridden_skill_ids` JSON           DEFAULT NULL,
            `overridden_by`      INT UNSIGNED     DEFAULT NULL,
            `overridden_at`      TIMESTAMP NULL   DEFAULT NULL,
            `override_reason`    TEXT             DEFAULT NULL,
            `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_ticket`     (`ticket_id`),
            KEY `idx_created`    (`created_at`),
            FOREIGN KEY (`ticket_id`)    REFERENCES `tickets`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`overridden_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // ── tickets pointer / sentiment ─────────────────────────────────
    if (!$hasColumn('tickets', 'ai_classification_id')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `ai_classification_id` INT UNSIGNED NULL DEFAULT NULL,
             ADD CONSTRAINT `fk_tickets_ai_classification`
                 FOREIGN KEY (`ai_classification_id`)
                 REFERENCES `ai_classifications`(`id`) ON DELETE SET NULL"
        );
    }
    if (!$hasColumn('tickets', 'ai_sentiment')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `ai_sentiment` VARCHAR(32) NULL DEFAULT NULL,
             ADD INDEX `idx_ai_sentiment` (`ai_sentiment`)"
        );
    }

    // ── widen groups.assign_strategy to include 'ai_skill_based' ───
    // Skip if already widened (the ENUM definition contains the value).
    $colStmt = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'groups' AND COLUMN_NAME = 'assign_strategy'"
    );
    $colStmt->execute([$db]);
    $colType = (string) ($colStmt->fetchColumn() ?: '');
    if (!str_contains($colType, "'ai_skill_based'")) {
        $pdo->exec(
            "ALTER TABLE `groups`
             MODIFY COLUMN `assign_strategy`
                 ENUM('manual','round_robin','load_based','skill_based','first_available','ai_skill_based')
                 NOT NULL DEFAULT 'manual'"
        );
    }

    // ── seed default settings keys ───────────────────────────────────
    $seed = [
        'ai_enabled'                  => '0',
        'ai_provider'                 => 'anthropic',
        'ai_anthropic_api_key'        => '',
        'ai_anthropic_model'          => 'claude-haiku-4-5',
        'ai_anthropic_models_cache'   => '[]',
        'ai_anthropic_models_cached_at' => '',
        'ai_openai_api_key'           => '',
        'ai_openai_model'             => 'gpt-4o-mini',
        'ai_openai_models_cache'      => '[]',
        'ai_openai_models_cached_at'  => '',
        'ai_confidence_threshold'     => '0.7',
        'ai_max_tokens'               => '500',
        'ai_timeout_seconds'          => '5',
        'ai_sentiment_priority_bump'  => '1',
        'ai_classify_inbound_email'   => '1',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($seed as $k => $v) {
        $stmt->execute([$k, $v]);
    }
};
