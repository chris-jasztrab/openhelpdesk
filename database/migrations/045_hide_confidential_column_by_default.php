<?php
/**
 * Migration 045 — drop the "Confidential" ticket-list column from existing users
 *
 * The Confidential column is hidden by default (see ticketColumnsHiddenByDefault()),
 * so any user who has never touched the Columns picker already doesn't see it.
 * But users who saved a column preference back when Confidential was on-by-default
 * have it baked into their stored `ticket_columns:{userId}` setting. This strips
 * `confidential` out of every such saved preference so the column is hidden for
 * everyone — existing and new — leaving it opt-in via the Columns picker.
 *
 * Only the `confidential` entry is removed; every other column the user chose is
 * preserved in its original order.
 *
 * Idempotent: re-running simply finds nothing left to strip.
 */
return static function (PDO $pdo): void {
    $rows = $pdo->query(
        "SELECT setting_key, setting_value FROM settings
         WHERE setting_key LIKE 'ticket_columns:%'"
    )->fetchAll(PDO::FETCH_ASSOC);

    $update = $pdo->prepare(
        'UPDATE settings SET setting_value = ? WHERE setting_key = ?'
    );

    foreach ($rows as $row) {
        $cols = json_decode($row['setting_value'], true);
        if (!is_array($cols) || !in_array('confidential', $cols, true)) {
            continue; // malformed or already clean — leave it alone
        }
        $cleaned = array_values(array_filter($cols, static fn ($c) => $c !== 'confidential'));
        $update->execute([json_encode($cleaned), $row['setting_key']]);
    }
};
