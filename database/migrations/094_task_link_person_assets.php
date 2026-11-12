<?php
/**
 * Migration 094 — task tickets that link the person's devices.
 *
 * A task row with link_person_assets = 1 links every device checked out to the
 * person picked in one of the task's person (user picker) fields to the new
 * task ticket, e.g. Employee Offboarding → Offboarding - IT gets the leaver's
 * laptop and phone so IT knows what to collect.
 */
return static function (PDO $pdo): void {
    $has = $pdo->query("SHOW COLUMNS FROM `ticket_type_tasks` LIKE 'link_person_assets'")->fetch();
    if (!$has) {
        $pdo->exec("ALTER TABLE `ticket_type_tasks` ADD COLUMN `link_person_assets` TINYINT(1) NOT NULL DEFAULT 0 AFTER `field_ids`");
    }
};
