<?php

declare(strict_types=1);

/**
 * Asset inventory — the small set of writes that must stay consistent no
 * matter which page triggers them: tag numbering, the history log, and the
 * ticket ⇄ asset link that is also mirrored into the ticket timeline.
 *
 * Everything here takes an explicit PDO so cron scripts and the importer can
 * use it without bootstrap.php.
 */
class Assets
{
    /**
     * Allocate the next auto-numbered asset tag (prefix + zero-filled counter)
     * and advance the counter. Skips tags that already exist, so a manually
     * typed tag never blocks the sequence.
     */
    public static function nextTag(PDO $db): string
    {
        $get = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $read = static function (string $k, string $d) use ($get): string {
            $get->execute([$k]);
            $v = $get->fetchColumn();
            return $v === false ? $d : (string) $v;
        };
        $prefix = $read('asset_tag_prefix', '');
        $pad    = max(0, (int) $read('asset_tag_zerofill', '5'));
        $next   = max(1, (int) $read('asset_tag_next', '1'));

        $exists = $db->prepare('SELECT 1 FROM assets WHERE asset_tag = ?');
        do {
            $tag = $prefix . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
            $next++;
            $exists->execute([$tag]);
        } while ($exists->fetchColumn() !== false);

        $db->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES ('asset_tag_next', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([(string) $next]);

        return $tag;
    }

    /**
     * Append a row to the asset's history. $details is stored as JSON;
     * $actorName covers imported rows whose actor is not a helpdesk user.
     */
    public static function logHistory(
        PDO $db,
        int $assetId,
        string $action,
        array $details = [],
        ?int $userId = null,
        ?string $actorName = null,
        ?string $createdAt = null
    ): int {
        $db->prepare(
            'INSERT INTO asset_history (asset_id, user_id, actor_name, action, details, created_at)
             VALUES (?, ?, ?, ?, ?, COALESCE(?, NOW()))'
        )->execute([
            $assetId,
            $userId,
            $actorName,
            $action,
            $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $createdAt,
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Link an asset to a ticket. Idempotent — a second call for the same pair
     * is a no-op and returns false. Writes an internal timeline row on the
     * ticket and a history row on the asset.
     */
    public static function link(PDO $db, int $ticketId, int $assetId, ?int $userId): bool
    {
        $ins = $db->prepare('INSERT IGNORE INTO ticket_asset_map (ticket_id, asset_id, linked_by) VALUES (?, ?, ?)');
        $ins->execute([$ticketId, $assetId, $userId]);
        if ($ins->rowCount() === 0) {
            return false;
        }
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 1)'
        )->execute([$ticketId, $userId, 'asset_linked', 'Linked asset ' . self::describe($db, $assetId)]);
        self::logHistory($db, $assetId, 'ticket_linked', ['ticket_id' => $ticketId], $userId);
        return true;
    }

    /** Remove a ticket ⇄ asset link. Returns false when there was nothing to remove. */
    public static function unlink(PDO $db, int $ticketId, int $assetId, ?int $userId): bool
    {
        $del = $db->prepare('DELETE FROM ticket_asset_map WHERE ticket_id = ? AND asset_id = ?');
        $del->execute([$ticketId, $assetId]);
        if ($del->rowCount() === 0) {
            return false;
        }
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 1)'
        )->execute([$ticketId, $userId, 'asset_unlinked', 'Unlinked asset ' . self::describe($db, $assetId)]);
        self::logHistory($db, $assetId, 'ticket_unlinked', ['ticket_id' => $ticketId], $userId);
        return true;
    }

    /** "00123 — Dell Latitude 5540 (HC04)" for timeline text. */
    public static function describe(PDO $db, int $assetId): string
    {
        $s = $db->prepare(
            'SELECT a.asset_tag, a.name, m.name AS model FROM assets a JOIN asset_models m ON m.id = a.model_id WHERE a.id = ?'
        );
        $s->execute([$assetId]);
        $a = $s->fetch(PDO::FETCH_ASSOC);
        if (!$a) {
            return '#' . $assetId;
        }
        $label = $a['asset_tag'] . ' — ' . $a['model'];
        return $a['name'] !== null && $a['name'] !== '' ? $label . ' (' . $a['name'] . ')' : $label;
    }
}
