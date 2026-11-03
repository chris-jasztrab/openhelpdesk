<?php
/**
 * Migration 088 — asset inventory (Snipe-IT replacement).
 *
 *   asset_categories / asset_manufacturers / asset_suppliers / asset_models / asset_statuses
 *                       — the catalog an asset is described by (category lives on the model,
 *                         as in Snipe-IT, so a chair and a laptop are just different models)
 *   assets              — one physical thing; assigned_type/assigned_id is polymorphic
 *                         (user, location or another asset) like Snipe's assigned_to
 *   asset_history       — checkout / checkin / update / audit / link rows; details is JSON
 *   asset_files         — uploaded documents (receipts, warranty PDFs)
 *   ticket_asset_map    — ticket ⇄ asset links
 *
 * `snipeit_id` columns let the importer upsert without duplicating. Permissions
 * `assets.view` / `assets.manage` are granted to no role here — admins see the
 * module, everyone else waits until a role is granted under Permission Levels.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `asset_categories` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `snipeit_id` INT(10) UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_asset_categories_name` (`name`),
            UNIQUE KEY `uniq_asset_categories_snipeit` (`snipeit_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `asset_manufacturers` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `url` VARCHAR(255) DEFAULT NULL,
            `support_url` VARCHAR(255) DEFAULT NULL,
            `support_phone` VARCHAR(64) DEFAULT NULL,
            `support_email` VARCHAR(255) DEFAULT NULL,
            `snipeit_id` INT(10) UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_asset_manufacturers_name` (`name`),
            UNIQUE KEY `uniq_asset_manufacturers_snipeit` (`snipeit_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `asset_suppliers` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `contact` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(64) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `url` VARCHAR(255) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `snipeit_id` INT(10) UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_asset_suppliers_name` (`name`),
            UNIQUE KEY `uniq_asset_suppliers_snipeit` (`snipeit_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `asset_models` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `model_number` VARCHAR(255) DEFAULT NULL,
            `category_id` INT(10) UNSIGNED NOT NULL,
            `manufacturer_id` INT(10) UNSIGNED DEFAULT NULL,
            `eol_months` INT(10) UNSIGNED DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `snipeit_id` INT(10) UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_asset_models_snipeit` (`snipeit_id`),
            KEY `idx_asset_models_category` (`category_id`),
            KEY `idx_asset_models_manufacturer` (`manufacturer_id`),
            CONSTRAINT `fk_asset_models_category` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
            CONSTRAINT `fk_asset_models_manufacturer` FOREIGN KEY (`manufacturer_id`) REFERENCES `asset_manufacturers` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `asset_statuses` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(64) NOT NULL,
            `kind` ENUM('deployable','pending','undeployable','archived') NOT NULL DEFAULT 'deployable',
            `color` VARCHAR(7) NOT NULL DEFAULT '#6c757d',
            `notes` TEXT DEFAULT NULL,
            `sort_order` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `snipeit_id` INT(10) UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_asset_statuses_name` (`name`),
            UNIQUE KEY `uniq_asset_statuses_snipeit` (`snipeit_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `assets` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `asset_tag` VARCHAR(64) NOT NULL,
            `name` VARCHAR(255) DEFAULT NULL,
            `serial` VARCHAR(255) DEFAULT NULL,
            `model_id` INT(10) UNSIGNED NOT NULL,
            `status_id` INT(10) UNSIGNED NOT NULL,
            `location_id` INT(10) UNSIGNED DEFAULT NULL,
            `default_location_id` INT(10) UNSIGNED DEFAULT NULL,
            `assigned_type` ENUM('user','location','asset') DEFAULT NULL,
            `assigned_id` INT(10) UNSIGNED DEFAULT NULL,
            `import_assignee` VARCHAR(100) DEFAULT NULL,
            `supplier_id` INT(10) UNSIGNED DEFAULT NULL,
            `order_number` VARCHAR(100) DEFAULT NULL,
            `purchase_date` DATE DEFAULT NULL,
            `purchase_cost` DECIMAL(12,2) DEFAULT NULL,
            `warranty_months` INT(10) UNSIGNED DEFAULT NULL,
            `eol_date` DATE DEFAULT NULL,
            `last_checkout_at` DATETIME DEFAULT NULL,
            `last_checkin_at` DATETIME DEFAULT NULL,
            `last_audit_at` DATETIME DEFAULT NULL,
            `next_audit_date` DATE DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_by` INT(10) UNSIGNED DEFAULT NULL,
            `snipeit_id` INT(10) UNSIGNED DEFAULT NULL,
            `deleted_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_assets_tag` (`asset_tag`),
            UNIQUE KEY `uniq_assets_snipeit` (`snipeit_id`),
            KEY `idx_assets_serial` (`serial`),
            KEY `idx_assets_name` (`name`),
            KEY `idx_assets_model` (`model_id`),
            KEY `idx_assets_status` (`status_id`),
            KEY `idx_assets_location` (`location_id`),
            KEY `idx_assets_default_location` (`default_location_id`),
            KEY `idx_assets_supplier` (`supplier_id`),
            KEY `idx_assets_created_by` (`created_by`),
            KEY `idx_assets_assigned` (`assigned_type`,`assigned_id`),
            KEY `idx_assets_deleted` (`deleted_at`),
            CONSTRAINT `fk_assets_model` FOREIGN KEY (`model_id`) REFERENCES `asset_models` (`id`),
            CONSTRAINT `fk_assets_status` FOREIGN KEY (`status_id`) REFERENCES `asset_statuses` (`id`),
            CONSTRAINT `fk_assets_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_assets_default_location` FOREIGN KEY (`default_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_assets_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `asset_suppliers` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_assets_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `asset_history` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `asset_id` INT(10) UNSIGNED NOT NULL,
            `user_id` INT(10) UNSIGNED DEFAULT NULL,
            `actor_name` VARCHAR(100) DEFAULT NULL,
            `action` VARCHAR(50) NOT NULL,
            `details` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_asset_history_asset` (`asset_id`,`created_at`),
            KEY `idx_asset_history_user` (`user_id`),
            CONSTRAINT `fk_asset_history_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_asset_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `asset_files` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `asset_id` INT(10) UNSIGNED NOT NULL,
            `uploaded_by` INT(10) UNSIGNED DEFAULT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `stored_name` VARCHAR(255) NOT NULL,
            `mime_type` VARCHAR(127) NOT NULL,
            `file_size` INT(10) UNSIGNED NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_asset_files_asset` (`asset_id`),
            KEY `idx_asset_files_uploaded_by` (`uploaded_by`),
            CONSTRAINT `fk_asset_files_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_asset_files_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ticket_asset_map` (
            `ticket_id` INT(10) UNSIGNED NOT NULL,
            `asset_id` INT(10) UNSIGNED NOT NULL,
            `linked_by` INT(10) UNSIGNED DEFAULT NULL,
            `linked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`ticket_id`,`asset_id`),
            KEY `idx_ticket_asset_map_asset` (`asset_id`),
            KEY `idx_ticket_asset_map_linked_by` (`linked_by`),
            CONSTRAINT `fk_ticket_asset_map_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ticket_asset_map_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ticket_asset_map_linked_by` FOREIGN KEY (`linked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Default statuses — only on an empty table, so an import (which brings its own) is never duplicated.
    if ((int) $pdo->query('SELECT COUNT(*) FROM asset_statuses')->fetchColumn() === 0) {
        $pdo->exec(
            "INSERT INTO `asset_statuses` (name, kind, color, sort_order) VALUES
                ('Ready to Deploy', 'deployable',   '#198754', 1),
                ('Pending',         'pending',      '#fd7e14', 2),
                ('Broken',          'undeployable', '#dc3545', 3),
                ('Archived',        'archived',     '#6c757d', 4)"
        );
    }

    // Auto-numbering for asset tags: prefix + zero-filled counter.
    $pdo->exec(
        "INSERT IGNORE INTO `settings` (setting_key, setting_value) VALUES
            ('asset_tag_prefix', ''),
            ('asset_tag_zerofill', '5'),
            ('asset_tag_next', '1')"
    );

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['permissions']);
    if ((int) $stmt->fetchColumn() === 0) {
        return; // permissions catalog not present yet (pre-042) — nothing to do
    }

    $perm = $pdo->prepare(
        "INSERT IGNORE INTO `permissions` (perm_key, label, category, description, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    );
    $perm->execute([
        'assets.view',
        'View inventory',
        'Inventory',
        'Open the Inventory pages, search assets, and link assets to tickets. Read-only on the assets themselves.',
        210,
    ]);
    $perm->execute([
        'assets.manage',
        'Manage inventory',
        'Inventory',
        'Create and edit assets, check them out and in, upload files, and maintain the catalog (categories, models, manufacturers, suppliers, statuses).',
        211,
    ]);
};
