<?php
/**
 * Migration 067 — AI "similar past tickets" agent assist
 *
 * Two parts:
 *
 *   1. A FULLTEXT index on tickets(subject, description). This powers the
 *      retrieval step — a natural-language MATCH...AGAINST query narrows the
 *      whole ticket archive down to a handful of lexically-similar candidates
 *      before the LLM reranks them. InnoDB has supported FULLTEXT since
 *      MySQL 5.6 / MariaDB 10, so no engine change is needed.
 *
 *   2. An ai_similarity_classifications audit table recording each rerank call
 *      (candidates in, matches out, tokens, latency). Mirrors
 *      ai_duplicate_classifications. Doubles as the per-ticket cache: the
 *      newest row for a ticket is reused so the LLM is called once per ticket,
 *      not once per page view.
 *
 * The feature is gated behind the global `ai_similar_enabled` setting
 * (default off) plus the existing `ai_enabled` master switch — no schema
 * row is needed for those, they default via getSetting().
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $hasIndex = static function (string $table, string $index) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([$db, $table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasIndex('tickets', 'ft_tickets_subject_description')) {
        $pdo->exec(
            'ALTER TABLE `tickets`
             ADD FULLTEXT INDEX `ft_tickets_subject_description` (`subject`, `description`)'
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ai_similarity_classifications` (
            `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ticket_id`           INT UNSIGNED NOT NULL,
            `provider`            VARCHAR(32)  NOT NULL,
            `model`               VARCHAR(128) NOT NULL,
            `candidate_ticket_ids` JSON        NOT NULL,
            `matches`             JSON         NOT NULL,
            `raw_output`          JSON             DEFAULT NULL,
            `latency_ms`          INT UNSIGNED     DEFAULT NULL,
            `prompt_tokens`       INT UNSIGNED     DEFAULT NULL,
            `output_tokens`       INT UNSIGNED     DEFAULT NULL,
            `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_ticket`      (`ticket_id`),
            KEY `idx_created`     (`created_at`),
            FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
