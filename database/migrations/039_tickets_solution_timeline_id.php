<?php
/**
 * Migration 039 — Mark a ticket comment as the solution
 *
 * Adds one column to `tickets`:
 *
 *   solution_timeline_id INT UNSIGNED NULL
 *     FK → ticket_timeline.id ON DELETE SET NULL
 *
 * Lets agents/admins flag a single ticket_timeline row as the
 * accepted answer for a ticket, so the ticket-detail views can
 * render a green "Go to solution" anchor near the top that jumps
 * down to the marked comment.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $colStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $colStmt->execute([$db, 'tickets', 'solution_timeline_id']);
    if ((int) $colStmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD COLUMN `solution_timeline_id` INT UNSIGNED DEFAULT NULL
             AFTER `merged_into_ticket_id`"
        );
    }

    $idxStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $idxStmt->execute([$db, 'tickets', 'fk_tickets_solution_timeline']);
    if ((int) $idxStmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD KEY `fk_tickets_solution_timeline` (`solution_timeline_id`)"
        );
    }

    $fkStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
    $fkStmt->execute([$db, 'tickets', 'fk_tickets_solution_timeline']);
    if ((int) $fkStmt->fetchColumn() === 0) {
        $pdo->exec(
            "ALTER TABLE `tickets`
             ADD CONSTRAINT `fk_tickets_solution_timeline`
                 FOREIGN KEY (`solution_timeline_id`)
                 REFERENCES `ticket_timeline` (`id`)
                 ON DELETE SET NULL"
        );
    }
};
