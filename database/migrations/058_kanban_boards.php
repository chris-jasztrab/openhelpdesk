<?php
/**
 * Migration 058 — Kanban board ticket view
 *
 * Adds the storage for the agent/admin Kanban board (a fourth ticket view
 * alongside table / inbox / card). Two kinds of board exist:
 *
 *   - Built-in boards (group by Status / Priority / Assignee) are *virtual*:
 *     their columns are derived live from ticket_statuses / ticket_priorities /
 *     staff users, and dragging a card reuses the existing
 *     /api/tickets/{id}/set-status|set-priority|assign endpoints. No rows here.
 *
 *   - Custom boards are a personal organizer: an agent defines their own
 *     buckets (columns) and drags tickets into them. Placement is stored, not
 *     a shared ticket field — moving a card changes nothing on the ticket.
 *     A board is private to its owner unless is_shared = 1, in which case the
 *     whole team sees the same board + placements (e.g. a shared sprint board).
 *
 * Tables:
 *   kanban_boards           one row per custom board (owner + share flag)
 *   kanban_buckets          columns within a custom board
 *   kanban_card_placements  which ticket sits in which bucket
 *
 * Idempotent: each table is created only if absent, so re-runs and fresh
 * schema installs are safe.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$tableExists('kanban_boards')) {
        $pdo->exec(
            "CREATE TABLE `kanban_boards` (
                `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                `user_id`    int(10) unsigned NOT NULL,
                `name`       varchar(100) NOT NULL,
                `is_shared`  tinyint(1) NOT NULL DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_owner` (`user_id`),
                KEY `idx_shared` (`is_shared`),
                CONSTRAINT `fk_kanban_boards_user` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('kanban_buckets')) {
        $pdo->exec(
            "CREATE TABLE `kanban_buckets` (
                `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                `board_id`   int(10) unsigned NOT NULL,
                `name`       varchar(100) NOT NULL,
                `color`      varchar(7) NOT NULL DEFAULT '#6c757d',
                `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_board` (`board_id`, `sort_order`),
                CONSTRAINT `fk_kanban_buckets_board` FOREIGN KEY (`board_id`)
                    REFERENCES `kanban_boards` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('kanban_card_placements')) {
        $pdo->exec(
            "CREATE TABLE `kanban_card_placements` (
                `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                `bucket_id`  int(10) unsigned NOT NULL,
                `ticket_id`  int(10) unsigned NOT NULL,
                `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_bucket_ticket` (`bucket_id`, `ticket_id`),
                KEY `idx_ticket` (`ticket_id`),
                CONSTRAINT `fk_kanban_place_bucket` FOREIGN KEY (`bucket_id`)
                    REFERENCES `kanban_buckets` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_kanban_place_ticket` FOREIGN KEY (`ticket_id`)
                    REFERENCES `tickets` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
};
