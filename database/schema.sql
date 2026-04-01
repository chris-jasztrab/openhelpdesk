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
    `role`        ENUM('admin', 'agent', 'power_user', 'user') NOT NULL DEFAULT 'user',
    `avatar`      VARCHAR(255) DEFAULT NULL,
    `azure_oid`   VARCHAR(128) NULL DEFAULT NULL,
    `work_phone`              VARCHAR(50)  DEFAULT NULL,
    `location_id`             INT UNSIGNED DEFAULT NULL,
    `notify_ticket_created`   TINYINT(1)   NOT NULL DEFAULT 1,
    `notify_ticket_updated`   TINYINT(1)   NOT NULL DEFAULT 1,
    `notify_ticket_cc`        TINYINT(1)   NOT NULL DEFAULT 1,
    `notify_ticket_merged`    TINYINT(1)   NOT NULL DEFAULT 1,
    `notify_escalation`       TINYINT(1)   NOT NULL DEFAULT 1,
    `notify_csat`             TINYINT(1)   NOT NULL DEFAULT 1,
    `totp_secret`             VARCHAR(64)  NULL     DEFAULT NULL,
    `totp_enabled`            TINYINT(1)   NOT NULL DEFAULT 0,
    `show_agent_tour`         TINYINT(1)   NOT NULL DEFAULT 1,
    `can_view_location_tickets` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uq_azure_oid` (`azure_oid`)
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
    `legacy_id`    VARCHAR(50)  DEFAULT NULL,
    `browser_info` VARCHAR(500) DEFAULT NULL,
    `os_info`      VARCHAR(500) DEFAULT NULL,
    `created_by`   INT UNSIGNED NOT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `due_date`     DATE         DEFAULT NULL,
    `type_id`      INT UNSIGNED DEFAULT NULL,
    `location_id`  INT UNSIGNED DEFAULT NULL,
    `status`       ENUM('open','in_progress','pending','waiting_on_customer','waiting_on_third_party','resolved','closed') NOT NULL DEFAULT 'open',
    `priority_id`  INT UNSIGNED DEFAULT NULL,
    `assigned_to`  INT UNSIGNED DEFAULT NULL,
    `group_id`     INT UNSIGNED DEFAULT NULL,
    `first_response_due_at` DATETIME DEFAULT NULL,
    `resolution_due_at`     DATETIME DEFAULT NULL,
    `first_responded_at`    DATETIME DEFAULT NULL,
    `sla_state`    ENUM('on_track','warning','breached') DEFAULT NULL,
    `sla_paused_at` DATETIME DEFAULT NULL,
    `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `merged_into_ticket_id` INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`),
    FOREIGN KEY (`type_id`)     REFERENCES `ticket_types`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`group_id`)    REFERENCES `groups`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`merged_into_ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket presence (concurrent viewer tracking)
CREATE TABLE IF NOT EXISTS `ticket_presence` (
    `ticket_id` INT UNSIGNED NOT NULL,
    `user_id`   INT UNSIGNED NOT NULL,
    `last_seen` DATETIME NOT NULL,
    PRIMARY KEY (`ticket_id`, `user_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE
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

-- Ticket CC (users who receive copies of correspondence)
CREATE TABLE IF NOT EXISTS `ticket_cc` (
    `ticket_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `added_by`   INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ticket_id`, `user_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`added_by`)  REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket watchers (agents/admins who subscribe to ticket updates)
CREATE TABLE IF NOT EXISTS `ticket_watchers` (
    `ticket_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ticket_id`, `user_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Canned responses (saved reply snippets; user_id NULL = global admin-managed)
CREATE TABLE IF NOT EXISTS `canned_responses` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED DEFAULT NULL COMMENT 'NULL = global; non-null = personal to that agent',
    `title`      VARCHAR(255) NOT NULL,
    `body`       TEXT NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
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
    `is_public`   TINYINT(1)   NOT NULL DEFAULT 0,
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

-- Holidays / closed days (optional SLA exclusion per date)
CREATE TABLE IF NOT EXISTS `holidays` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `holiday_date`     DATE NOT NULL,
    `name`             VARCHAR(255) NOT NULL DEFAULT '',
    `exclude_from_sla` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_holiday_date` (`holiday_date`),
    KEY `idx_holiday_date` (`holiday_date`)
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

-- Saved ticket filters (per-user, optionally shared)
CREATE TABLE IF NOT EXISTS `saved_filters` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `name`       VARCHAR(255) NOT NULL,
    `filters`    JSON NOT NULL,
    `is_shared`  TINYINT(1) NOT NULL DEFAULT 0,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application settings (key-value store)
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Automations (rule-based ticket automation)
CREATE TABLE IF NOT EXISTS `automations` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(255) NOT NULL,
    `trigger_event` VARCHAR(50)  NOT NULL,
    `conditions`    JSON         NOT NULL,
    `actions`       JSON         NOT NULL,
    `is_enabled`    TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Custom ticket form fields (Workflows > Ticket Fields builder)
CREATE TABLE IF NOT EXISTS `ticket_form_fields` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `field_type`  ENUM('text','textarea','checkbox','dropdown','date','number','decimal','dependent','text_block','image','cc','date_range') NOT NULL,
    `label`       VARCHAR(255) NOT NULL,
    `placeholder` VARCHAR(255) DEFAULT NULL,
    `config`      JSON NULL,
    `is_required` TINYINT(1)   NOT NULL DEFAULT 0,
    `is_visible`  TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Options for dropdown and dependent fields (self-referential for hierarchy)
CREATE TABLE IF NOT EXISTS `ticket_form_field_options` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `field_id`         INT UNSIGNED NOT NULL,
    `parent_option_id` INT UNSIGNED DEFAULT NULL,
    `label`            VARCHAR(255) NOT NULL,
    `sort_order`       INT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (`field_id`)         REFERENCES `ticket_form_fields`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_option_id`) REFERENCES `ticket_form_field_options`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Custom field values stored per ticket
CREATE TABLE IF NOT EXISTS `ticket_field_values` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`  INT UNSIGNED NOT NULL,
    `field_id`   INT UNSIGNED NOT NULL,
    `value`      TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ticket_field` (`ticket_id`, `field_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`field_id`)  REFERENCES `ticket_form_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Map custom fields to specific ticket types (many-to-many).
-- Fields with no rows here are "global" and shown for all types.
CREATE TABLE IF NOT EXISTS `ticket_form_field_type_map` (
    `field_id` INT UNSIGNED NOT NULL,
    `type_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`field_id`, `type_id`),
    FOREIGN KEY (`field_id`) REFERENCES `ticket_form_fields`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`type_id`)  REFERENCES `ticket_types`(`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Escalation rules — time-driven, evaluated by cron script
CREATE TABLE IF NOT EXISTS `escalation_rules` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`           VARCHAR(255) NOT NULL,
    `conditions`     JSON         NOT NULL,
    `actions`        JSON         NOT NULL,
    `cooldown_hours` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_enabled`     TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tracks which escalation rules have already fired per ticket (dedup / cooldown)
CREATE TABLE IF NOT EXISTS `escalation_log` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rule_id`   INT UNSIGNED NOT NULL,
    `ticket_id` INT UNSIGNED NOT NULL,
    `fired_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_rule_ticket` (`rule_id`, `ticket_id`),
    FOREIGN KEY (`rule_id`)   REFERENCES `escalation_rules`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Satisfaction (CSAT) surveys — one per ticket, sent on resolution
CREATE TABLE IF NOT EXISTS `csat_surveys` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`    INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NOT NULL,
    `token`        VARCHAR(64)  NOT NULL,
    `rating`       TINYINT UNSIGNED DEFAULT NULL,
    `comment`      TEXT NULL,
    `sent_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `responded_at` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `uq_csat_ticket` (`ticket_id`),
    UNIQUE KEY `uq_csat_token`  (`token`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled report emails — sent on a daily/weekly/monthly cadence
CREATE TABLE IF NOT EXISTS `scheduled_reports` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`             VARCHAR(255) NOT NULL,
    `report_type`      VARCHAR(50) NOT NULL DEFAULT 'overview',
    `recipients`       JSON NOT NULL,
    `frequency`        ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
    `send_day`         TINYINT UNSIGNED NULL DEFAULT NULL,
    `date_range_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `last_sent_at`     TIMESTAMP NULL DEFAULT NULL,
    `is_enabled`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB article ratings (thumbs up/down feedback per article)
CREATE TABLE IF NOT EXISTS `kb_article_ratings` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `article_id` INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NULL DEFAULT NULL,
    `session_id` VARCHAR(64)  NULL DEFAULT NULL,
    `rating`     TINYINT(1)   NOT NULL COMMENT '1=helpful, -1=not helpful',
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_rating_user`    (`article_id`, `user_id`),
    KEY        `idx_rating_session`(`article_id`, `session_id`),
    FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KB article version history (snapshot before each edit)
CREATE TABLE IF NOT EXISTS `kb_article_revisions` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `article_id`    INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `body_markdown` LONGTEXT     NOT NULL,
    `edited_by`     INT UNSIGNED NOT NULL,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_rev_article` (`article_id`),
    FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`edited_by`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin audit log
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NULL DEFAULT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(50)  NULL DEFAULT NULL,
    `target_id`   INT UNSIGNED NULL DEFAULT NULL,
    `detail`      TEXT         NULL,
    `ip_address`  VARCHAR(45)  NULL DEFAULT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_audit_user`   (`user_id`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_created`(`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket templates (pre-filled forms for common request types)
CREATE TABLE IF NOT EXISTS `ticket_templates` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT         NULL,
    `subject`     VARCHAR(255) NOT NULL DEFAULT '',
    `body`        TEXT         NOT NULL,
    `type_id`     INT UNSIGNED NULL DEFAULT NULL,
    `priority_id` INT UNSIGNED NULL DEFAULT NULL,
    `is_shared`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_tpl_shared` (`is_shared`),
    FOREIGN KEY (`type_id`)     REFERENCES `ticket_types`(`id`)       ON DELETE SET NULL,
    FOREIGN KEY (`priority_id`) REFERENCES `ticket_priorities`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
