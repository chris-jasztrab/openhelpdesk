-- ============================================================
-- LocalDesk – Full Database Schema
-- ============================================================

-- Locations (must be created before users due to FK)
CREATE TABLE IF NOT EXISTS `locations` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(255) NOT NULL,
    `address`     TEXT,
    `description` TEXT,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users
CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `first_name`  VARCHAR(255) NOT NULL,
    `last_name`   VARCHAR(255) NOT NULL,
    `email`       VARCHAR(255) NOT NULL UNIQUE,
    `password`    VARCHAR(255) NOT NULL,
    `role`        ENUM('admin', 'agent', 'user') NOT NULL DEFAULT 'user',
    `avatar`      VARCHAR(255) DEFAULT NULL,
    `work_phone`  VARCHAR(50)  DEFAULT NULL,
    `location_id` INT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket priorities
CREATE TABLE IF NOT EXISTS `ticket_priorities` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL,
    `color`      VARCHAR(7)   NOT NULL DEFAULT '#6c757d',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket types
CREATE TABLE IF NOT EXISTS `ticket_types` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(255) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tickets
CREATE TABLE IF NOT EXISTS `tickets` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subject`      VARCHAR(255) NOT NULL,
    `description`  TEXT         NOT NULL,
    `browser_info` VARCHAR(500) DEFAULT NULL,
    `os_info`      VARCHAR(500) DEFAULT NULL,
    `created_by`   INT UNSIGNED NOT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `due_date`     DATE         DEFAULT NULL,
    `type_id`      INT UNSIGNED DEFAULT NULL,
    `location_id`  INT UNSIGNED DEFAULT NULL,
    `status`       ENUM('open','in_progress','pending','resolved','closed') NOT NULL DEFAULT 'open',
    `priority_id`  INT UNSIGNED DEFAULT NULL,
    `assigned_to`  INT UNSIGNED DEFAULT NULL,
    `first_response_due_at` DATETIME DEFAULT NULL,
    `resolution_due_at`     DATETIME DEFAULT NULL,
    `first_responded_at`    DATETIME DEFAULT NULL,
    `sla_state`    ENUM('on_track','warning','breached') DEFAULT NULL,
    `sla_paused_at` DATETIME DEFAULT NULL,
    `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`),
    FOREIGN KEY (`type_id`)     REFERENCES `ticket_types`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket tags (hashtags)
CREATE TABLE IF NOT EXISTS `ticket_tags` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket ↔ tag pivot
CREATE TABLE IF NOT EXISTS `ticket_tag_map` (
    `ticket_id` INT UNSIGNED NOT NULL,
    `tag_id`    INT UNSIGNED NOT NULL,
    PRIMARY KEY (`ticket_id`, `tag_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tag_id`)    REFERENCES `ticket_tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket timeline (audit trail of all changes)
CREATE TABLE IF NOT EXISTS `ticket_timeline` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`   INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `details`     TEXT,
    `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket attachments (secure file storage outside webroot)
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`     INT UNSIGNED NOT NULL,
    `timeline_id`   INT UNSIGNED DEFAULT NULL,
    `uploaded_by`   INT UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name`   VARCHAR(255) NOT NULL,
    `mime_type`     VARCHAR(127) NOT NULL,
    `file_size`     INT UNSIGNED NOT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`)   REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`timeline_id`) REFERENCES `ticket_timeline`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Knowledge Base categories
CREATE TABLE IF NOT EXISTS `kb_categories` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(255) NOT NULL,
    `slug`        VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Knowledge Base folders (belong to a category)
CREATE TABLE IF NOT EXISTS `kb_folders` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `slug`        VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `kb_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Knowledge Base articles (belong to a folder)
CREATE TABLE IF NOT EXISTS `kb_articles` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `folder_id`     INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `slug`          VARCHAR(255) NOT NULL UNIQUE,
    `body_markdown` LONGTEXT NOT NULL,
    `status`        ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `published_at`  TIMESTAMP NULL DEFAULT NULL,
    `created_by`    INT UNSIGNED NOT NULL,
    `sort_order`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`folder_id`)   REFERENCES `kb_folders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications (for @mentions)
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT UNSIGNED NOT NULL,
    `ticket_id`    INT UNSIGNED NOT NULL,
    `timeline_id`  INT UNSIGNED NOT NULL,
    `mentioned_by` INT UNSIGNED NOT NULL,
    `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ticket_id`)    REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`timeline_id`)  REFERENCES `ticket_timeline`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`mentioned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SLA policies (per priority)
CREATE TABLE IF NOT EXISTS `sla_policies` (
    `id`                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `priority_id`            INT UNSIGNED NOT NULL UNIQUE,
    `first_response_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
    `resolution_minutes`     INT UNSIGNED NOT NULL DEFAULT 480,
    `created_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups (organizational groups for agents/admins)
CREATE TABLE IF NOT EXISTS `groups` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group ↔ user pivot
CREATE TABLE IF NOT EXISTS `group_user_map` (
    `group_id` INT UNSIGNED NOT NULL,
    `user_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`group_id`, `user_id`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application settings (key-value store)
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
