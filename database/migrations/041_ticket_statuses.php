<?php
/**
 * Migration 041 — Configurable ticket statuses
 *
 * Replaces the hardcoded `tickets.status` ENUM with a `ticket_statuses`
 * lookup table so admins can add, remove, rename, recolor, and re-order
 * status options through a settings page.
 *
 * Slugs of the original 7 statuses are preserved verbatim so existing
 * rows and external API integrations keep working without translation.
 *
 * Steps:
 *   1. Create `ticket_statuses` (slug, label, bucket, sort, color, defaults, flags).
 *   2. Seed the 7 existing statuses with is_system=1 (so they're protected from deletion).
 *   3. Verify every distinct value in tickets.status is also in the lookup; abort otherwise.
 *   4. ALTER tickets.status from ENUM to VARCHAR(64).
 *   5. Add an index on tickets.status (none today).
 *
 * Idempotent: schema_migrations prevents re-runs, but each step also guards
 * itself with information_schema / INSERT IGNORE so a half-applied run is recoverable.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $tableExists = static function (string $table) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([$db, $table]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $indexExists = static function (string $table, string $index) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([$db, $table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    };

    // ── 1. Create the lookup table ───────────────────────────────────────────
    if (!$tableExists('ticket_statuses')) {
        $pdo->exec(
            "CREATE TABLE `ticket_statuses` (
                `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `slug`                VARCHAR(64)  NOT NULL,
                `label`               VARCHAR(64)  NOT NULL,
                `bucket`              ENUM('open','closed') NOT NULL DEFAULT 'open',
                `pauses_sla`          TINYINT(1)   NOT NULL DEFAULT 0,
                `sort_order`          INT UNSIGNED NOT NULL DEFAULT 0,
                `color`               VARCHAR(7)   NOT NULL DEFAULT '#6c757d',
                `is_default_new`      TINYINT(1)   NOT NULL DEFAULT 0,
                `is_default_resolved` TINYINT(1)   NOT NULL DEFAULT 0,
                `is_default_closed`   TINYINT(1)   NOT NULL DEFAULT 0,
                `is_system`           TINYINT(1)   NOT NULL DEFAULT 0,
                `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
                `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_ticket_statuses_slug` (`slug`),
                KEY `idx_ticket_statuses_bucket_active` (`bucket`, `is_active`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ── 2. Seed the 7 existing statuses (idempotent via INSERT IGNORE) ───────
    //
    // sort_order matches the original ENUM declaration order. Defaults flagged:
    //   open      → is_default_new      (new tickets land here)
    //   resolved  → is_default_resolved (fires the resolved email template)
    //   closed    → is_default_closed   (fires the closed email template)
    //
    // pauses_sla is set on the three "waiting"-style statuses, matching the
    // hardcoded $pausingStatuses array at src/helpers.php:3518 / :4798.
    $seeds = [
        // [slug, label, bucket, pauses_sla, sort, color, def_new, def_res, def_closed]
        ['open',                   'Open',                    'open',   0,  1, '#0d6efd', 1, 0, 0],
        ['in_progress',            'In Progress',             'open',   0,  2, '#0dcaf0', 0, 0, 0],
        ['pending',                'Pending',                 'open',   1,  3, '#ffc107', 0, 0, 0],
        ['waiting_on_customer',    'Waiting on Customer',     'open',   1,  4, '#fd7e14', 0, 0, 0],
        ['waiting_on_third_party', 'Waiting on Third Party',  'open',   1,  5, '#6f42c1', 0, 0, 0],
        ['resolved',               'Resolved',                'closed', 0,  6, '#198754', 0, 1, 0],
        ['closed',                 'Closed',                  'closed', 0,  7, '#6c757d', 0, 0, 1],
    ];

    $insert = $pdo->prepare(
        "INSERT IGNORE INTO `ticket_statuses`
            (slug, label, bucket, pauses_sla, sort_order, color,
             is_default_new, is_default_resolved, is_default_closed, is_system, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
    );
    foreach ($seeds as $s) {
        $insert->execute($s);
    }

    // ── 3. Verify no orphan status values in tickets ─────────────────────────
    $orphans = $pdo->query(
        "SELECT DISTINCT t.status
         FROM tickets t
         LEFT JOIN ticket_statuses s ON s.slug = t.status
         WHERE s.slug IS NULL"
    )->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($orphans)) {
        throw new RuntimeException(
            'Migration 041 aborted: tickets contain status values not in the lookup table: '
            . implode(', ', array_map(static fn($v) => "'{$v}'", $orphans))
            . '. Add these slugs to the seed before re-running.'
        );
    }

    // ── 4. ALTER tickets.status from ENUM → VARCHAR(64) ──────────────────────
    // Existing string values are preserved bit-for-bit.
    $stmt = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'status'"
    );
    $stmt->execute([$db]);
    $colType = (string) $stmt->fetchColumn();

    if (stripos($colType, 'enum') === 0) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             MODIFY COLUMN `status` VARCHAR(64) NOT NULL DEFAULT 'open'"
        );
    }

    // ── 5. Add an index on tickets.status (none today) ───────────────────────
    if (!$indexExists('tickets', 'idx_tickets_status')) {
        $pdo->exec("ALTER TABLE `tickets` ADD INDEX `idx_tickets_status` (`status`)");
    }
};
