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
     * Turn whatever a barcode scanner or camera produced into a live asset id.
     * Accepts, in order: a Snipe-IT URL (.../hardware/{snipeit_id}), one of
     * our URLs (.../inventory/{id}), an exact asset tag, the tag zero-padded
     * to the configured width ("26" → "00026"), then a serial number.
     */
    public static function resolveScan(PDO $db, string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $live = ' AND deleted_at IS NULL';
        if (preg_match('~/hardware/(\d+)(?:[/?#]|$)~i', $code, $m)) {
            $s = $db->prepare('SELECT id FROM assets WHERE snipeit_id = ?' . $live);
            $s->execute([(int) $m[1]]);
            return ($id = $s->fetchColumn()) !== false ? (int) $id : null;
        }
        if (preg_match('~/inventory/(\d+)(?:[/?#]|$)~i', $code, $m)) {
            $s = $db->prepare('SELECT id FROM assets WHERE id = ?' . $live);
            $s->execute([(int) $m[1]]);
            return ($id = $s->fetchColumn()) !== false ? (int) $id : null;
        }
        $candidates = [$code];
        if (ctype_digit($code)) {
            $candidates[] = getSetting('asset_tag_prefix', '') . str_pad($code, (int) getSetting('asset_tag_zerofill', '5'), '0', STR_PAD_LEFT);
        }
        foreach ($candidates as $tag) {
            $s = $db->prepare('SELECT id FROM assets WHERE asset_tag = ?' . $live);
            $s->execute([$tag]);
            if (($id = $s->fetchColumn()) !== false) {
                return (int) $id;
            }
        }
        $s = $db->prepare('SELECT id FROM assets WHERE serial = ?' . $live . ' LIMIT 1');
        $s->execute([$code]);
        return ($id = $s->fetchColumn()) !== false ? (int) $id : null;
    }

    /**
     * Stamp one audit: last_audit_at = now, next_audit_date = the typed date
     * or today + the configured interval (0 months = leave as is). The
     * asset's own location is never changed; a mismatch is only recorded.
     * Returns the history details that were written.
     */
    public static function audit(PDO $db, int $assetId, ?int $atLocationId, ?string $nextDate, ?int $userId): array
    {
        $s = $db->prepare('SELECT a.location_id, l.name AS location_name, al.name AS at_name
                           FROM assets a LEFT JOIN locations l ON l.id = a.location_id LEFT JOIN locations al ON al.id = ?
                           WHERE a.id = ?');
        $s->execute([$atLocationId, $assetId]);
        $a = $s->fetch();
        if ($nextDate === null || $nextDate === '') {
            $months   = (int) getSetting('asset_audit_interval_months', '12');
            $nextDate = $months > 0 ? date('Y-m-d', strtotime("+{$months} months")) : null;
        }
        $db->prepare('UPDATE assets SET last_audit_at = NOW(), next_audit_date = COALESCE(?, next_audit_date) WHERE id = ?')
           ->execute([$nextDate, $assetId]);
        $details = array_filter([
            'at'       => $atLocationId !== null ? ['id' => $atLocationId, 'name' => $a['at_name']] : null,
            'expected' => $a['location_id'] !== null ? ['id' => (int) $a['location_id'], 'name' => $a['location_name']] : null,
            'mismatch' => $atLocationId !== null && $a['location_id'] !== null && (int) $a['location_id'] !== $atLocationId,
            'next'     => $nextDate,
        ], static fn ($v) => $v !== null && $v !== false);
        self::logHistory($db, $assetId, 'audit', $details, $userId);
        return $details;
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

    /**
     * Check an asset out to a user, location or another asset. $key is the
     * user/location id, or the asset tag for type "asset". The asset's location
     * follows the target (a user's home location, the location itself, the
     * parent asset's location) when known. Returns the target's display name,
     * or null when the target does not exist — nothing is written then.
     */
    public static function checkout(PDO $db, int $assetId, string $type, string|int $key, ?int $userId, string $note = ''): ?string
    {
        if ($type === 'user') {
            $s = $db->prepare('SELECT id, CONCAT(first_name, " ", last_name) AS name, location_id FROM users WHERE id = ?');
            $s->execute([(int) $key]);
        } elseif ($type === 'location') {
            $s = $db->prepare('SELECT id, name, id AS location_id FROM locations WHERE id = ?');
            $s->execute([(int) $key]);
        } elseif ($type === 'asset') {
            $s = $db->prepare('SELECT id, asset_tag AS name, location_id FROM assets WHERE asset_tag = ? AND id <> ? AND deleted_at IS NULL');
            $s->execute([trim((string) $key), $assetId]);
        } else {
            return null;
        }
        $target = $s->fetch();
        if (!$target) {
            return null;
        }
        $location = $target['location_id'] !== null ? (int) $target['location_id'] : null;
        $db->prepare('UPDATE assets SET assigned_type = ?, assigned_id = ?, import_assignee = NULL, location_id = COALESCE(?, location_id), last_checkout_at = NOW() WHERE id = ? AND deleted_at IS NULL')
           ->execute([$type, (int) $target['id'], $location, $assetId]);
        self::logHistory($db, $assetId, 'checkout', array_filter(['target' => ['type' => $type, 'id' => (int) $target['id'], 'name' => $target['name']], 'note' => $note ?: null]), $userId);
        logAudit('asset.checked_out', $assetId, 'asset', $type . '=' . $target['name']);
        return (string) $target['name'];
    }

    /**
     * Check an asset in: clears the assignee (including an unmatched Snipe-IT
     * one) and returns it to its default location. Returns who it came back
     * from, or null when it was not checked out — nothing is written then.
     */
    public static function checkin(PDO $db, int $assetId, ?int $userId, string $note = ''): ?string
    {
        $s = $db->prepare('SELECT a.assigned_type, a.assigned_id, a.import_assignee,
                                  CONCAT(u.first_name, " ", u.last_name) AS assigned_user_name, al.name AS assigned_location_name, pa.asset_tag AS assigned_asset_tag
                           FROM assets a
                           LEFT JOIN users u      ON a.assigned_type = "user" AND u.id = a.assigned_id
                           LEFT JOIN locations al ON a.assigned_type = "location" AND al.id = a.assigned_id
                           LEFT JOIN assets pa    ON a.assigned_type = "asset" AND pa.id = a.assigned_id
                           WHERE a.id = ?');
        $s->execute([$assetId]);
        $a = $s->fetch();
        if (!$a || ($a['assigned_type'] === null && $a['import_assignee'] === null)) {
            return null;
        }
        $from = self::assigneeLabel($a);
        $db->prepare('UPDATE assets SET assigned_type = NULL, assigned_id = NULL, import_assignee = NULL, location_id = COALESCE(default_location_id, location_id), last_checkin_at = NOW() WHERE id = ?')
           ->execute([$assetId]);
        self::logHistory($db, $assetId, 'checkin', array_filter(['target' => ['type' => $a['assigned_type'] ?? 'user', 'id' => $a['assigned_id'] !== null ? (int) $a['assigned_id'] : null, 'name' => $from], 'note' => $note ?: null]), $userId);
        logAudit('asset.checked_in', $assetId, 'asset', 'from=' . $from);
        return $from;
    }

    /** A deployable asset that is checked out is "Deployed" — it is no longer ready to deploy. */
    public static function isDeployed(array $a): bool
    {
        return ($a['status_kind'] ?? null) === 'deployable' && ($a['assigned_type'] ?? null) !== null;
    }

    /** Status text for lists, CSV and JSON: "Deployed" overrides the stored status while checked out. */
    public static function statusLabel(array $a): string
    {
        return self::isDeployed($a) ? 'Deployed' : (string) $a['status_name'];
    }

    /** Badge colour matching statusLabel() for JSON consumers that paint their own badge. */
    public static function statusColor(array $a): string
    {
        return self::isDeployed($a) ? '#0d6efd' : (string) $a['status_color'];
    }

    /** Escaped status badge matching statusLabel(). */
    public static function statusBadgeHtml(array $a): string
    {
        if (self::isDeployed($a)) {
            return '<span class="badge bg-primary-subtle text-primary border">Deployed</span>';
        }
        return '<span class="badge" style="background:' . e((string) $a['status_color']) . ';color:' . ticketStatusTextColor((string) $a['status_color']) . ';">' . e((string) $a['status_name']) . '</span>';
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
            'audit'           => 'Audited' . (isset($d['at']['name']) ? ' at ' . e((string) $d['at']['name']) : '')
                . (!empty($d['mismatch']) && isset($d['expected']['name']) ? ' <span class="badge bg-warning text-dark">expected ' . e((string) $d['expected']['name']) . '</span>' : ''),
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
