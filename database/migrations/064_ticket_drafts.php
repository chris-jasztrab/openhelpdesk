<?php
/**
 * Migration 064 — `ticket_drafts` for server-side draft autosave
 *
 * Holds one in-progress draft per user per form: the staff/portal "new ticket"
 * forms (`ticket_id` = 0) and the per-ticket reply boxes (`ticket_id` = the
 * ticket). Drafts are stored server-side rather than in localStorage so they
 * follow the user across devices and never leak to the next person on a
 * shared machine. `payload` is a JSON blob of the form's field values; it may
 * carry pasted Base64 images inside CKEditor HTML, hence MEDIUMTEXT.
 *
 * No FK on ticket_id: create-form drafts use 0, and stale reply drafts for
 * deleted tickets are swept by the 90-day purge in the drafts routes.
 *
 * Idempotent: the table is only created if it does not already exist.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $exists->execute(['ticket_drafts']);
    if ((int) $exists->fetchColumn() === 0) {
        $pdo->exec(
            "CREATE TABLE `ticket_drafts` (
                `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                `user_id`    int(10) unsigned NOT NULL,
                `context`    varchar(32) NOT NULL,
                `ticket_id`  int(10) unsigned NOT NULL DEFAULT 0,
                `payload`    mediumtext NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ticket_drafts` (`user_id`, `context`, `ticket_id`),
                KEY `idx_ticket_drafts_updated` (`updated_at`),
                CONSTRAINT `fk_ticket_drafts_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
