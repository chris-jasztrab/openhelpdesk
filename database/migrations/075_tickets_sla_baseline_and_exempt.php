<?php
/**
 * Migration 075 — SLA baseline + exemption for imported tickets
 *
 * Two problems this fixes, both surfaced by the CSV ticket importer:
 *
 * 1. Imported tickets carry a legacy `created_at` (often years old). Every SLA
 *    recompute path — Sla::onPriorityChanged(), Sla::onTypeChanged() — measured
 *    from `created_at`, so the first time anyone changed a priority or type on an
 *    imported ticket its due dates landed in the past and it flipped straight to
 *    'breached'. A bulk priority change or an automation with a set_priority
 *    action could do that to hundreds of tickets in one click.
 *
 * 2. The importer never set the SLA columns at all, so the whole imported
 *    backlog was silently absent from every SLA metric — but nothing recorded
 *    that the exclusion was deliberate, so any later edit undid it.
 *
 * `sla_started_at` is the moment the SLA clock started for this ticket, which is
 * NOT always `created_at`: an imported backlog can be put under SLA from the
 * import moment rather than from its original open date. All SLA arithmetic now
 * measures from this column, falling back to `created_at` when it is NULL.
 *
 * `sla_exempt` marks a ticket as permanently outside SLA. Unlike "has no due
 * dates yet", it survives a priority or type change — the recompute paths bail
 * out on it instead of retroactively inventing a breach.
 *
 * Backfill:
 *   - Tickets that already have an SLA clock get `sla_started_at = created_at`,
 *     preserving their current due-date arithmetic exactly.
 *   - Previously imported tickets with no SLA clock are marked exempt, making
 *     today's de-facto behaviour explicit and permanent.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $hasColumn = static function (string $column) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$db, 'tickets', $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasColumn('sla_started_at')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `sla_started_at` DATETIME NULL DEFAULT NULL
             AFTER `group_id`"
        );
        // Existing SLA'd tickets kept their old baseline: created_at.
        $pdo->exec(
            "UPDATE `tickets`
                SET `sla_started_at` = `created_at`
              WHERE `first_response_due_at` IS NOT NULL
                 OR `resolution_due_at` IS NOT NULL"
        );
    }

    if (!$hasColumn('sla_exempt')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `sla_exempt` TINYINT(1) NOT NULL DEFAULT 0
             AFTER `sla_paused_at`"
        );
        // Previously imported tickets never had an SLA clock. Make that explicit
        // so a later priority/type edit can't retroactively breach them.
        $pdo->exec(
            "UPDATE `tickets`
                SET `sla_exempt` = 1
              WHERE `legacy_id` IS NOT NULL
                AND `first_response_due_at` IS NULL
                AND `resolution_due_at` IS NULL"
        );
    }

    // Lets recalculateAll() skip exempt rows without a filesort over the table.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$db, 'tickets', 'idx_tickets_sla_exempt']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `tickets` ADD KEY `idx_tickets_sla_exempt` (`sla_exempt`)");
    }
};
