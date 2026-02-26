-- Migration: Scheduled Reports v2
-- Run this against your existing database to add daily scheduling and date-range support.
-- Safe to run multiple times (uses IF NOT EXISTS / MODIFY patterns).

-- 1. Change report_type from ENUM to VARCHAR so any report page can be scheduled
ALTER TABLE `scheduled_reports`
    MODIFY COLUMN `report_type` VARCHAR(50) NOT NULL DEFAULT 'overview';

-- 2. Add 'daily' to the frequency enum
ALTER TABLE `scheduled_reports`
    MODIFY COLUMN `frequency` ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly';

-- 3. Make send_day nullable (not needed for daily schedules)
ALTER TABLE `scheduled_reports`
    MODIFY COLUMN `send_day` TINYINT UNSIGNED NULL DEFAULT NULL;

-- 4. Add date_range_days column (how many previous days the emailed report covers)
ALTER TABLE `scheduled_reports`
    ADD COLUMN IF NOT EXISTS `date_range_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30
    AFTER `send_day`;
