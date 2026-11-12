<?php
/**
 * Migration 092 — per-domain ignored hosts.
 *
 * A zone lists hosts that will never serve a certificate in our name
 * (Microsoft enrolment CNAMEs, Google verification records, retired names).
 * Hosts listed here, one per line, are still checked but are left out of the
 * hosts table, the SSL verdict counts and every expiry alert.
 */
return static function (PDO $pdo): void {
    $has = $pdo->query("SHOW COLUMNS FROM `domains` LIKE 'ignored_hosts'")->fetch();
    if (!$has) {
        $pdo->exec("ALTER TABLE `domains` ADD COLUMN `ignored_hosts` TEXT DEFAULT NULL AFTER `extra_hosts`");
    }
};
