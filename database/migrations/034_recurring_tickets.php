<?php
/**
 * Migration 034 — `recurring_tickets` table for preventive-maintenance /
 * recurring ticket schedules.
 *
 * Examples this is meant to capture: monthly toner audit, quarterly HVAC,
 * annual fire inspection, weekly website-content sweep. Today these live
 * in someone's Outlook calendar; with this table a cron processor mints
 * the ticket on schedule, routes it via the same group/auto-assign path
 * as a hand-filed ticket, and records the link back so reporting can ask
 * "how many of last year's quarterly HVAC tickets actually closed?"
 *
 * Cadence model — flexible by design:
 *   - `frequency`     daily / weekly / monthly / yearly / custom
 *   - `interval_value` how many of the unit between runs (every 1 month,
 *                     every 3 months for quarterly, every 2 weeks…)
 *   - `day_of_week`   0=Sun … 6=Sat — anchor for weekly/custom-week runs
 *   - `day_of_month`  1–31 — anchor for monthly/yearly (clamped to month
 *                     length at fire-time so Feb 30 → Feb 28/29)
 *   - `month_of_year` 1–12 — anchor for yearly (e.g. annual fire
 *                     inspection in October)
 *   - `start_date`    first run anchor; `next_run_at` is advanced from
 *                     this without drift
 *   - `next_run_at`   pre-computed UTC datetime the cron looks at — the
 *                     index `idx_recurring_due` makes due-checks cheap
 *   - `last_run_at` / `last_ticket_id` — audit + traceability so the
 *                     timeline on the new ticket can link back to the
 *                     schedule that spawned it
 *
 * Ticket-mint defaults — same shape as `tickets` so the cron can just
 * copy-paste fields:
 *   - `requester_id`     who the ticket is filed *for* (defaults to the
 *                        creator of the schedule); FK ON DELETE SET NULL
 *                        with a row-level guard in the processor — a
 *                        schedule with no requester is auto-disabled
 *                        rather than silently failing.
 *   - `submitted_by`     populated automatically on the minted ticket as
 *                        "the recurring schedule" — we use NULL plus a
 *                        timeline entry pointing at the schedule.
 *   - `due_date_offset_days` how many days after firing the ticket's
 *                        `due_date` should land (e.g. 14 = HVAC tech
 *                        has two weeks to complete the inspection).
 *                        NULL = no due date.
 *
 * Idempotent — guarded by an information_schema lookup so re-running on
 * a database that already has the table is a no-op.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name   = 'recurring_tickets'"
    )->fetchColumn();

    if ((int) $exists > 0) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE `recurring_tickets` (
            `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name`                 VARCHAR(255) NOT NULL,
            `description_internal` TEXT NULL,

            -- Ticket-mint payload (copied onto every ticket the schedule fires)
            `subject`              VARCHAR(255) NOT NULL,
            `body`                 MEDIUMTEXT NOT NULL,
            `type_id`              INT UNSIGNED NULL,
            `priority_id`          INT UNSIGNED NULL,
            `location_id`          INT UNSIGNED NULL,
            `assigned_to`          INT UNSIGNED NULL,
            `group_id`             INT UNSIGNED NULL,
            `requester_id`         INT UNSIGNED NULL,
            `due_date_offset_days` SMALLINT UNSIGNED NULL,

            -- Cadence
            `frequency`            ENUM('daily','weekly','monthly','yearly','custom') NOT NULL DEFAULT 'monthly',
            `interval_value`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            `day_of_week`          TINYINT UNSIGNED NULL,
            `day_of_month`         TINYINT UNSIGNED NULL,
            `month_of_year`        TINYINT UNSIGNED NULL,
            `start_date`           DATE NOT NULL,
            `next_run_at`          DATETIME NOT NULL,
            `last_run_at`          DATETIME NULL,
            `last_ticket_id`       INT UNSIGNED NULL,
            `run_count`            INT UNSIGNED NOT NULL DEFAULT 0,

            -- Lifecycle
            `is_active`            TINYINT(1) NOT NULL DEFAULT 1,
            `created_by`           INT UNSIGNED NULL,
            `updated_by`           INT UNSIGNED NULL,
            `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX `idx_recurring_due`        (`is_active`, `next_run_at`),
            INDEX `idx_recurring_type`       (`type_id`),
            INDEX `idx_recurring_group`      (`group_id`),
            INDEX `idx_recurring_requester`  (`requester_id`),
            INDEX `idx_recurring_last_ticket`(`last_ticket_id`),

            CONSTRAINT `fk_recurring_type`
                FOREIGN KEY (`type_id`) REFERENCES `ticket_types`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_recurring_priority`
                FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_recurring_location`
                FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_recurring_assigned`
                FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_recurring_group`
                FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_recurring_requester`
                FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_recurring_last_ticket`
                FOREIGN KEY (`last_ticket_id`) REFERENCES `tickets`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_recurring_created_by`
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
                ON DELETE SET NULL,
            CONSTRAINT `fk_recurring_updated_by`
                FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
