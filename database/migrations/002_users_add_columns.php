<?php
/**
 * Migration 002 — Add columns to `users` that were added after v1.0.0
 *
 * Checks information_schema before each ALTER so this is safe to run
 * against a database that already has some or all of these columns.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $columnExists = static function (string $table, string $column) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$db, $table, $column]);
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

    // Azure AD / M365 SSO
    if (!$columnExists('users', 'azure_oid')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `azure_oid` VARCHAR(128) NULL DEFAULT NULL AFTER `password`");
    }
    if (!$indexExists('users', 'uq_azure_oid')) {
        $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uq_azure_oid` (`azure_oid`)");
    }

    // Per-user email notification preferences
    foreach ([
        'notify_ticket_created' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `location_id`",
        'notify_ticket_updated' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_ticket_created`",
        'notify_ticket_cc'      => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_ticket_updated`",
        'notify_ticket_merged'  => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_ticket_cc`",
        'notify_escalation'     => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_ticket_merged`",
        'notify_csat'           => "TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_escalation`",
    ] as $col => $def) {
        if (!$columnExists('users', $col)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$col}` {$def}");
        }
    }

    // TOTP / 2FA
    if (!$columnExists('users', 'totp_secret')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_secret` VARCHAR(64) NULL DEFAULT NULL");
    }
    if (!$columnExists('users', 'totp_enabled')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Location-scoped ticket visibility
    if (!$columnExists('users', 'can_view_location_tickets')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `can_view_location_tickets` TINYINT(1) NOT NULL DEFAULT 0");
    }
};
