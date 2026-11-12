<?php
/**
 * Migration 095 — warranty looked up from the manufacturer (Dell, Lenovo).
 *
 *   warranty_start / warranty_end — earliest start and latest end across the
 *                                   device's warranties and contracts
 *   warranty_service              — coverage names, e.g. "3Y Premier Support"
 *   warranty_checked_at           — last answer from the vendor (re-asked after 30 days)
 *   warranty_error                — why the last lookup failed, if it did
 *
 * The hand-entered warranty_months column is left alone.
 */
return static function (PDO $pdo): void {
    $cols = [
        'warranty_start'      => 'DATE DEFAULT NULL AFTER `warranty_months`',
        'warranty_end'        => 'DATE DEFAULT NULL AFTER `warranty_start`',
        'warranty_service'    => 'VARCHAR(255) DEFAULT NULL AFTER `warranty_end`',
        'warranty_checked_at' => 'DATETIME DEFAULT NULL AFTER `warranty_service`',
        'warranty_error'      => 'VARCHAR(255) DEFAULT NULL AFTER `warranty_checked_at`',
    ];
    foreach ($cols as $col => $def) {
        if (!$pdo->query("SHOW COLUMNS FROM `assets` LIKE '{$col}'")->fetch()) {
            $pdo->exec("ALTER TABLE `assets` ADD COLUMN `{$col}` {$def}");
        }
    }
    if (!$pdo->query("SHOW INDEX FROM `assets` WHERE Key_name = 'idx_assets_warranty_end'")->fetch()) {
        $pdo->exec('ALTER TABLE `assets` ADD KEY `idx_assets_warranty_end` (`warranty_end`)');
    }
};
