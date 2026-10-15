<?php
/**
 * Migration 084 — Ticket Reminders replace Stale Tickets
 *
 * Seeds the Settings → Ticket Reminders keys. Both reminder emails are seeded
 * OFF ('0'), because an absent email_notify:* key means ON. Coverage starts as
 * "new tickets only" with no cutoff, so nothing goes out until an admin saves
 * the page, which stamps the cutoff.
 *
 * Backfills due_date from the SLA resolution date on open tickets that have an
 * SLA but no due date, matching what Sla::syncDueDate() now does going forward.
 * A due date someone already set is left alone.
 *
 * Retires the stale-ticket feature: its settings, its email-template
 * overrides, and ticket_types.stale_threshold_minutes. Old
 * `stale_notification_sent` timeline rows are kept as history. Idempotent.
 */
return static function (PDO $pdo): void {
    $seed = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ([
        'due_date_follows_sla'           => '1',
        'due_date_extension_prompt'      => '1',
        'email_notify:ticket_overdue'    => '0',
        'email_notify:ticket_update_due' => '0',
        'ticket_update_due_days'         => '15',
        'ticket_reminders_managers'      => '0',
        'ticket_reminders_scope'         => 'new',
        'ticket_reminders_scope_cutoff'  => '',
    ] as $key => $value) {
        $seed->execute([$key, $value]);
    }

    $pdo->exec(
        "UPDATE tickets SET due_date = DATE(resolution_due_at)
          WHERE due_date IS NULL AND resolution_due_at IS NOT NULL
            AND status IN (SELECT slug FROM ticket_statuses WHERE bucket = 'open')"
    );

    $pdo->exec(
        "DELETE FROM settings
          WHERE setting_key IN ('stale_threshold_minutes', 'stale_recheck_minutes', 'stale_scope', 'stale_scope_cutoff',
                                'email_notify:ticket_stale_agent', 'email_notify:ticket_stale_manager',
                                'email_notify:ticket_stale_requester')
             OR setting_key LIKE 'email\\_%\\_ticket\\_stale\\_%'"
    );

    $hasColumn = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $hasColumn->execute(['ticket_types', 'stale_threshold_minutes']);
    if ((int) $hasColumn->fetchColumn() > 0) {
        $pdo->exec('ALTER TABLE `ticket_types` DROP COLUMN `stale_threshold_minutes`');
    }

    $pdo->prepare('UPDATE permissions SET description = ? WHERE perm_key = ?')->execute([
        'Configure automation rules, escalations, ticket reminders and scheduled-report jobs.',
        'automations.manage',
    ]);
};
