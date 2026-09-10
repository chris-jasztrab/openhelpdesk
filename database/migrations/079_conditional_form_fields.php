<?php
/**
 * Migration 079 — conditional form fields, and a directory-backed person picker
 *
 * Two structural changes, both additive and both inert until an admin uses them.
 *
 * ── ticket_type_form_layout.condition_json ───────────────────────────────────
 *
 * Until now a field's presence on a ticket type's form was a single fixed
 * choice: required, optional or hidden. This adds a second gate in front of
 * that one — a condition evaluated per submission, per user.
 *
 * NULL means "no condition", which is every existing row, so nothing about any
 * current form changes. A non-NULL value is a JSON object:
 *
 *   {"type":"shared_account"}
 *       true when the person filling in the form is signed in to an account
 *       flagged `users.is_shared_account` (migration 078)
 *
 *   {"type":"field","field":"42","op":"equals","value":"7"}
 *       true when custom field 42's submitted value satisfies the operator.
 *       Operators: equals, not_equals, filled, empty. `value` is ignored by
 *       the last two.
 *
 * LONGTEXT rather than the `JSON` type, and deliberately WITHOUT a
 * `CHECK (json_valid(...))` constraint: that constraint is MariaDB-flavoured
 * and this schema already has to install on MySQL 8. The application validates
 * the shape on write and treats anything unparseable as "no condition", which
 * fails open — a field with a corrupt condition shows up rather than silently
 * vanishing from the form.
 *
 * The condition narrows, never widens. A field that is `hidden` stays hidden
 * whatever its condition says; a field whose condition is false is treated as
 * absent — not rendered, not required, and its value not saved even if one is
 * posted. Conditions that reference each other in a loop are not rejected and
 * do not need to be: evaluation is a single non-recursive pass over the
 * submitted values, so a cycle just resolves against whatever was posted.
 *
 * ── ticket_form_fields.field_type += 'user_picker' ───────────────────────────
 *
 * A text input that autocompletes against the user directory and stores the
 * chosen user's id, not the typed string. The point is correlation: a free-text
 * name gives you "sarah", "S. Chen" and "asfd", none of which join to anything,
 * whereas an id resolves to a current name and a working email address every
 * time it is displayed. Values live in `ticket_field_values.value` as the plain
 * integer id, so no new table and no change to how field values are read.
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

    if (!$hasColumn('ticket_type_form_layout', 'condition_json')) {
        $pdo->exec(
            "ALTER TABLE `ticket_type_form_layout`
             ADD COLUMN `condition_json` LONGTEXT NULL DEFAULT NULL
             AFTER `visibility`"
        );
    }

    // Add 'user_picker' to the field_type enum, preserving the existing members.
    // Read the current definition rather than hard-coding it, so an install that
    // has picked up a newer type from elsewhere doesn't lose it here.
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$db, 'ticket_form_fields', 'field_type']);
    $columnType = (string) $stmt->fetchColumn();

    if ($columnType !== '' && !str_contains($columnType, "'user_picker'")) {
        // COLUMN_TYPE looks like: enum('text','textarea',...)
        $inner = substr($columnType, strlen('enum('), -1);
        $pdo->exec(
            "ALTER TABLE `ticket_form_fields`
             MODIFY COLUMN `field_type` ENUM($inner,'user_picker') NOT NULL"
        );
    }
};
