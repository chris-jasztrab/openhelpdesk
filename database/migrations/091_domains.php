<?php
/**
 * Migration 091 — domain tracking (Admin → Settings → Domains).
 *
 *   domains             — one root domain; registrar / expires_at are filled by the
 *                         registry lookup (RDAP, then WHOIS) and stay editable by hand
 *                         for TLDs that publish neither. check_data is the JSON result
 *                         of the last Domains::check() run.
 *   domain_alerts_sent  — one row per expiry alert already emailed, keyed on the expiry
 *                         date so a renewal re-arms the alert without any cleanup.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `domains` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(253) NOT NULL,
            `registrar` VARCHAR(255) DEFAULT NULL,
            `expires_at` DATE DEFAULT NULL,
            `extra_hosts` TEXT DEFAULT NULL,
            `dkim_selectors` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `check_data` MEDIUMTEXT DEFAULT NULL,
            `checked_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_domains_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `domain_alerts_sent` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `alert_key` VARCHAR(190) NOT NULL,
            `expires_on` DATE NOT NULL,
            `tier` VARCHAR(16) NOT NULL,
            `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_domain_alert` (`alert_key`, `expires_on`, `tier`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec("INSERT IGNORE INTO `settings` (setting_key, setting_value) VALUES
        ('domain_alert_days', '30'),
        ('domain_alerts_email', '1'),
        ('domain_dns_resolver', '1.1.1.1')");
};
