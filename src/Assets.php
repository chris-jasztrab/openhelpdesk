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

    /** May the current user open the inventory at all? (assets.manage implies view; admins hold both.) */
    public static function canView(): bool
    {
        return Auth::can('assets.view') || Auth::can('assets.manage');
    }

    /** May the current user create, edit, check out/in, delete assets and maintain the catalog? */
    public static function canManage(): bool
    {
        return Auth::can('assets.manage');
    }

    /** Image types accepted for asset photos (never SVG — it can carry scripts). */
    public const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    /**
     * {field: {old, new}} for every key in $fields whose value changed —
     * the same shape the importer stores, so the History tab renders both.
     */
    public static function changes(array $before, array $after, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $o = $before[$f] ?? null;
            $n = $after[$f] ?? null;
            if ((string) $o !== (string) $n) {
                $out[$f] = ['old' => $o, 'new' => $n];
            }
        }
        return $out;
    }

    /** Plain-text assignee for a row carrying the assetSelect() join columns. */
    public static function assigneeLabel(array $a): string
    {
        return match ($a['assigned_type'] ?? null) {
            'user'     => (string) ($a['assigned_user_name'] ?? ''),
            'location' => (string) ($a['assigned_location_name'] ?? ''),
            'asset'    => (string) ($a['assigned_asset_tag'] ?? ''),
            default    => $a['import_assignee'] ?? '',
        };
    }

    /** Linked/escaped assignee for templates; unmatched Snipe-IT assignees are flagged. */
    public static function assigneeHtml(array $a): string
    {
        return match ($a['assigned_type'] ?? null) {
            'user'     => '<a href="/admin/users/' . (int) $a['assigned_id'] . '" class="text-decoration-none">' . e((string) $a['assigned_user_name']) . '</a>',
            'location' => '<i class="bi bi-geo-alt text-muted me-1"></i>' . e((string) $a['assigned_location_name']),
            'asset'    => '<a href="/inventory/' . (int) $a['assigned_id'] . '" class="text-decoration-none font-monospace">' . e((string) $a['assigned_asset_tag']) . '</a>',
            default    => $a['import_assignee']
                ? '<span class="text-muted fst-italic" title="Checked out in Snipe-IT to an account that does not exist here">' . e((string) $a['import_assignee']) . ' <i class="bi bi-question-circle"></i></span>'
                : '<span class="text-muted">—</span>',
        };
    }

    public static function historyIcon(string $action): string
    {
        return match ($action) {
            'checkout'        => 'bi-box-arrow-right',
            'checkin'         => 'bi-box-arrow-in-left',
            'update'          => 'bi-pencil',
            'create'          => 'bi-plus-circle',
            'delete'          => 'bi-trash',
            'restore'         => 'bi-arrow-counterclockwise',
            'audit'           => 'bi-clipboard-check',
            'file_uploaded'   => 'bi-paperclip',
            'file_deleted'    => 'bi-paperclip',
            'ticket_linked'   => 'bi-link-45deg',
            'ticket_unlinked' => 'bi-x-circle',
            default           => 'bi-dot',
        };
    }

    /** One escaped HTML sentence for a history row. */
    public static function historyText(string $action, array $d): string
    {
        $target = isset($d['target']['name']) && $d['target']['name'] !== null ? e((string) $d['target']['name']) : null;
        if (isset($d['target']['type'], $d['target']['id']) && $d['target']['id'] !== null && $target !== null) {
            $url = $d['target']['type'] === 'user' ? '/admin/users/' . (int) $d['target']['id'] : null;
            $target = $url ? '<a href="' . $url . '" class="text-decoration-none">' . $target . '</a>' : $target;
        }
        $ticket = isset($d['ticket_id']) ? '<a href="' . e(staffTicketUrl((int) $d['ticket_id'])) . '" class="text-decoration-none">#' . (int) $d['ticket_id'] . '</a>' : null;
        return match ($action) {
            'checkout'        => 'Checked out' . ($target ? ' to ' . $target : ''),
            'checkin'         => 'Checked in' . ($target ? ' from ' . $target : ''),
            'update'          => 'Updated',
            'create'          => 'Created',
            'delete'          => 'Deleted',
            'restore'         => 'Restored',
            'audit'           => 'Audited',
            'file_uploaded'   => 'Uploaded ' . e((string) ($d['filename'] ?? 'a file')),
            'file_deleted'    => 'Removed file ' . e((string) ($d['filename'] ?? '')),
            'ticket_linked'   => 'Linked to ticket ' . ($ticket ?? ''),
            'ticket_unlinked' => 'Unlinked from ticket ' . ($ticket ?? ''),
            default           => e(ucfirst(str_replace('_', ' ', $action))),
        };
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
