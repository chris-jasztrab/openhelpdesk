<?php
/**
 * Migration 038 — Per-ticket-type form layout
 *
 * Replaces the global field list + per-field type filter with one
 * authoritative table per (ticket_type × field): `ticket_type_form_layout`.
 * Each row says "for this ticket type, show this field at this position
 * with this required/optional/hidden state and (optionally) this label."
 *
 *   type_id        — FK ticket_types(id), ON DELETE CASCADE
 *   field_kind     — 'system' | 'custom'
 *   field_key      — for system: 'subject','description','ticket_type',
 *                                'location','priority','tags','attachments'
 *                    for custom: stringified ticket_form_fields.id
 *   sort_order     — INT, ordering within the type's form
 *   visibility     — 'required' | 'optional' | 'hidden'
 *   label_override — VARCHAR(255) NULL — overrides default label per type
 *
 * Migration steps:
 *   1. Create `ticket_type_form_layout`.
 *   2. For each ticket_type, seed system field rows from the old
 *      sys_field_* settings + priority_visibility column.
 *   3. For each ticket_type, seed custom field rows from
 *      ticket_form_fields + ticket_form_field_type_map.
 *   4. Drop the now-redundant pivot table `ticket_form_field_type_map`.
 *   5. Drop the now-redundant `ticket_types.priority_visibility` column
 *      (added in migration 037, never used outside that brief window).
 *   6. Drop the now-redundant ticket_form_fields columns: is_required,
 *      is_visible, sort_order — these are per-type now, not per-field.
 *   7. Delete the now-redundant settings rows.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $hasColumn = static function (string $table, string $column) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$db, $table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $hasTable = static function (string $table) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([$db, $table]);
        return (int) $stmt->fetchColumn() > 0;
    };

    // ── 1. Create the new layout table ────────────────────────────────
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `ticket_type_form_layout` (
            `type_id`        INT UNSIGNED NOT NULL,
            `field_kind`     ENUM('system','custom') NOT NULL,
            `field_key`      VARCHAR(64) NOT NULL,
            `sort_order`     INT NOT NULL DEFAULT 0,
            `visibility`     ENUM('required','optional','hidden') NOT NULL DEFAULT 'optional',
            `label_override` VARCHAR(255) DEFAULT NULL,
            `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`type_id`, `field_kind`, `field_key`),
            KEY `idx_type_sort` (`type_id`, `sort_order`),
            CONSTRAINT `fk_layout_type` FOREIGN KEY (`type_id`)
                REFERENCES `ticket_types`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // If the table already had data (re-run safety), bail out early.
    if ((int) $pdo->query('SELECT COUNT(*) FROM ticket_type_form_layout')->fetchColumn() > 0) {
        return;
    }

    // ── 2 + 3. Seed layout rows for every ticket type ─────────────────
    $globalReqPriority = $pdo->query(
        "SELECT setting_value FROM settings WHERE setting_key = 'sys_field_required_priority' LIMIT 1"
    )->fetchColumn();
    $globalReqTags = $pdo->query(
        "SELECT setting_value FROM settings WHERE setting_key = 'sys_field_required_tags' LIMIT 1"
    )->fetchColumn();
    $tagsEnabledRaw = $pdo->query(
        "SELECT setting_value FROM settings WHERE setting_key = 'tags_enabled' LIMIT 1"
    )->fetchColumn();
    $tagsEnabled = $tagsEnabledRaw === false ? true : ((string) $tagsEnabledRaw === '1');

    $priorityRequiredDefault = (string) $globalReqPriority === '1' ? 'required' : 'optional';
    $tagsVisibilityDefault   = !$tagsEnabled
        ? 'hidden'
        : ((string) $globalReqTags === '1' ? 'required' : 'optional');

    // System-field defaults
    $systemDefaults = [
        // [field_key, sort_order, visibility, label_override]
        ['subject',     0,   'required', null],
        ['description', 50,  'required', null],
        ['ticket_type', 100, 'required', null],
        ['location',    200, 'optional', null],
        ['priority',    300, $priorityRequiredDefault, null],
        ['tags',        400, $tagsVisibilityDefault,   null],
        ['attachments', 900, 'optional', null],
    ];

    $typeRows = $pdo->query('SELECT id, priority_visibility FROM ticket_types')->fetchAll();
    $hasPriVisCol = $hasColumn('ticket_types', 'priority_visibility');

    $insertLayout = $pdo->prepare(
        'INSERT INTO ticket_type_form_layout (type_id, field_kind, field_key, sort_order, visibility, label_override)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    // Pull custom field rows + their type maps once. These columns and the
    // type_map table only exist on installs that pre-date this migration —
    // a fresh install (schema.sql is already the new shape) won't have them.
    $hasOldCols = $hasColumn('ticket_form_fields', 'is_required')
               && $hasColumn('ticket_form_fields', 'is_visible')
               && $hasColumn('ticket_form_fields', 'sort_order');
    if ($hasOldCols) {
        $customFields = $pdo->query(
            'SELECT id, sort_order, is_required, is_visible
             FROM ticket_form_fields
             WHERE deleted_at IS NULL
             ORDER BY sort_order, id'
        )->fetchAll();
    } else {
        $customFields = $pdo->query(
            'SELECT id, 0 AS sort_order, 0 AS is_required, 1 AS is_visible
             FROM ticket_form_fields
             WHERE deleted_at IS NULL
             ORDER BY id'
        )->fetchAll();
    }

    $typeMapByField = [];   // [field_id => [type_id, ...]]
    if ($hasTable('ticket_form_field_type_map')) {
        $mapRows = $pdo->query('SELECT field_id, type_id FROM ticket_form_field_type_map')->fetchAll();
        foreach ($mapRows as $r) {
            $typeMapByField[(int) $r['field_id']][] = (int) $r['type_id'];
        }
    }

    foreach ($typeRows as $tr) {
        $typeId = (int) $tr['id'];

        // System fields: resolve this type's priority visibility from
        // the column we added in migration 037 (now retired).
        $thisTypePriVis = $priorityRequiredDefault;
        if ($hasPriVisCol) {
            $colVal = (string) ($tr['priority_visibility'] ?? 'inherit');
            if (in_array($colVal, ['required','optional','hidden'], true)) {
                $thisTypePriVis = $colVal;
            }
        }

        foreach ($systemDefaults as $sd) {
            [$key, $sort, $vis, $label] = $sd;
            if ($key === 'priority') {
                $vis = $thisTypePriVis;
            }
            $insertLayout->execute([$typeId, 'system', $key, $sort, $vis, $label]);
        }

        // Custom fields: include if field had no type map (global) OR
        // if this type appears in the field's map.
        foreach ($customFields as $cf) {
            $fid = (int) $cf['id'];
            $mapTypes = $typeMapByField[$fid] ?? [];
            $appearsHere = empty($mapTypes) || in_array($typeId, $mapTypes, true);
            if (!$appearsHere) continue;

            $vis = (int) $cf['is_required'] === 1
                ? 'required'
                : ((int) $cf['is_visible'] === 1 ? 'optional' : 'hidden');
            $insertLayout->execute([
                $typeId,
                'custom',
                (string) $fid,
                (int) $cf['sort_order'],
                $vis,
                null,
            ]);
        }
    }

    // ── 4. Drop the pivot table ───────────────────────────────────────
    if ($hasTable('ticket_form_field_type_map')) {
        $pdo->exec('DROP TABLE `ticket_form_field_type_map`');
    }

    // ── 5. Drop the priority_visibility column from ticket_types ──────
    if ($hasColumn('ticket_types', 'priority_visibility')) {
        $pdo->exec('ALTER TABLE `ticket_types` DROP COLUMN `priority_visibility`');
    }

    // ── 6. Drop now-per-type columns from ticket_form_fields ──────────
    foreach (['is_required', 'is_visible', 'sort_order'] as $col) {
        if ($hasColumn('ticket_form_fields', $col)) {
            $pdo->exec("ALTER TABLE `ticket_form_fields` DROP COLUMN `{$col}`");
        }
    }

    // ── 7. Delete redundant settings rows ─────────────────────────────
    $pdo->exec(
        "DELETE FROM settings
         WHERE setting_key IN ('sys_field_required_priority', 'sys_field_required_tags')
            OR setting_key LIKE 'sys_field_sort_order_%'"
    );
};
