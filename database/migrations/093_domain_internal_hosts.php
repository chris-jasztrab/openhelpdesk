<?php
/**
 * Migration 093 — per-domain hosts checked through internal DNS first.
 *
 * The SSL check reads each host's certificate from its public address first
 * and falls back to the name as this server resolves it. On a split-horizon
 * network some names exist in both views and the public address, reached from
 * inside, lands on the wrong box (often the firewall). Hosts listed here, one
 * per line, are read through internal DNS first, with the public address as
 * the fallback.
 */
return static function (PDO $pdo): void {
    $has = $pdo->query("SHOW COLUMNS FROM `domains` LIKE 'internal_hosts'")->fetch();
    if (!$has) {
        $pdo->exec("ALTER TABLE `domains` ADD COLUMN `internal_hosts` TEXT DEFAULT NULL AFTER `ignored_hosts`");
    }
};
