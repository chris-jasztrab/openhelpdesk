<?php
/**
 * Migration 096 — show the new "Updated" ticket-list column to everyone.
 *
 * "Updated" is on by default, but anyone who has ever used the Columns picker
 * has their choice stored in `ticket_columns:{userId}` and would never see it.
 * This adds `updated` to every saved preference, right after `created_at`
 * (Opened) when that is shown, otherwise at the end. Still opt-out via the
 * Columns picker afterwards.
 *
 * Idempotent: preferences that already list `updated` are left alone. The
 * migration runner records it as applied, so unticking the column later sticks.
 */
return static function (PDO $pdo): void {
    $rows = $pdo->query(
        "SELECT setting_key, setting_value FROM settings
         WHERE setting_key LIKE 'ticket_columns:%'"
    )->fetchAll(PDO::FETCH_ASSOC);

    $update = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');

    foreach ($rows as $row) {
        $cols = json_decode($row['setting_value'], true);
        if (!is_array($cols) || in_array('updated', $cols, true)) {
            continue; // malformed or already there — leave it alone
        }
        $cols = array_values($cols);
        $at   = array_search('created_at', $cols, true);
        array_splice($cols, $at === false ? count($cols) : $at + 1, 0, ['updated']);
        $update->execute([json_encode($cols), $row['setting_key']]);
    }
};
