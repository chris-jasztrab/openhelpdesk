<?php
/**
 * Migration 081 — Outbound mail queue
 *
 * Any email that sendMail() cannot deliver — the MAIL_ENABLED kill switch is
 * off, SMTP is not configured, or the SMTP server rejected it — now lands here
 * instead of being dropped. Admin → Settings → Email Queue lists the rows and
 * offers "Send all now" (delivers and deletes on success) and "Flush" (deletes
 * without sending). Nothing drains this table automatically, so turning
 * MAIL_ENABLED back on can never blast a backlog by itself. Idempotent.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `mail_queue` (
            `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `to_email`        VARCHAR(255) NOT NULL,
            `to_name`         VARCHAR(255) NOT NULL DEFAULT '',
            `subject`         VARCHAR(998) NOT NULL,
            `html_body`       MEDIUMTEXT NOT NULL,
            `text_body`       MEDIUMTEXT NULL,
            `ticket_id`       INT UNSIGNED NULL,
            `attachments`     TEXT NULL,
            `transactional`   TINYINT(1) NOT NULL DEFAULT 0,
            `reason`          VARCHAR(255) NOT NULL,
            `attempts`        INT UNSIGNED NOT NULL DEFAULT 0,
            `last_error`      TEXT NULL,
            `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_attempt_at` DATETIME NULL,
            KEY `idx_mail_queue_created` (`created_at`),
            KEY `idx_mail_queue_ticket` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
