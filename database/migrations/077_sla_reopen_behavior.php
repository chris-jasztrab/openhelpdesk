<?php
/**
 * Migration 077 — configurable SLA behaviour when a closed ticket is reopened
 *
 * Until now, reopening a closed ticket did nothing at all to its SLA. The
 * status-change handlers only pause and resume around the statuses flagged
 * `pauses_sla`, and a closed-bucket status is not one of them — closed tickets
 * drop out of SLA scope because `recalculateAll()` only scans the open bucket,
 * not because anything was paused. So a reopen put the ticket straight back
 * under its ORIGINAL due dates with no credit for the time it spent closed: a
 * ticket closed six months ago, reopened today, breaches on the next cron run
 * five minutes later.
 *
 * That is a defensible policy, but it was never a choice — and it is the wrong
 * one for a service desk where reopening is routine. This makes it a per-type
 * setting.
 *
 * `ticket_types.sla_reopen_behavior` is NULL by default, meaning "inherit the
 * global `sla_reopen_behavior` setting", matching how `stale_threshold_minutes`
 * and `business_hours_schedule` already work on this table. The global default
 * is 'keep', which is exactly today's behaviour — so this migration changes
 * nothing about how any existing install behaves until someone picks otherwise.
 *
 * The four values:
 *   keep                — reopen against the original due dates (today's behaviour)
 *   resume              — extend both due dates by the business minutes the
 *                         ticket spent closed, the same accounting Sla::resume()
 *                         already applies to a pause
 *   restart             — fresh clock from the reopen moment, and clear
 *                         first_responded_at so a new first response is required
 *   restart_resolution  — fresh clock from the reopen moment, but leave
 *                         first_responded_at intact so the response leg stays met
 *
 * `tickets.sla_closed_at` records when the ticket entered a closed status, which
 * is what 'resume' measures the credit from. There is no closed-at timestamp
 * anywhere else in the schema — `updated_at` moves on every edit, and the
 * timeline only holds a formatted 'X → Resolved' string — so this column is the
 * only reliable source. It is deliberately NOT backfilled: for tickets closed
 * before this migration the closed-at moment is genuinely unknown, and inventing
 * one from `updated_at` would credit back a wrong (often wildly wrong) amount of
 * time. 'resume' falls back to 'keep' when the column is NULL.
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

    if (!$hasColumn('tickets', 'sla_closed_at')) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `sla_closed_at` DATETIME NULL DEFAULT NULL
             AFTER `sla_paused_at`"
        );
    }

    if (!$hasColumn('ticket_types', 'sla_reopen_behavior')) {
        // VARCHAR rather than ENUM: adding a fifth behaviour later is then a code
        // change, not an ALTER on a table every ticket joins against. The
        // application validates the value on write and falls back to 'keep' on
        // anything it does not recognise.
        $pdo->exec(
            "ALTER TABLE `ticket_types`
             ADD COLUMN `sla_reopen_behavior` VARCHAR(24) NULL DEFAULT NULL
             AFTER `business_hours_schedule`"
        );
    }

    // Seed the global default explicitly rather than relying on getSetting()'s
    // fallback, so the value is visible (and editable) on the settings page from
    // the first page load instead of appearing only after someone saves.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `settings` WHERE `setting_key` = ?');
    $stmt->execute(['sla_reopen_behavior']);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)')
            ->execute(['sla_reopen_behavior', 'keep']);
    }
};
