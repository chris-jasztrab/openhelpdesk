<?php
/**
 * Migration 030 — "Example Ticket Form" showcase ticket type
 *
 * Creates a demo ticket type that, when selected on the new-ticket form,
 * exposes one custom field of every supported field_type so admins can
 * see what each type looks like end-to-end. Fields are scoped to the
 * new type via `ticket_form_field_type_map` so they only appear on the
 * showcase form, not on every ticket.
 *
 * Idempotent — keyed off the ticket type's name. If a type called
 * "Example Ticket Form" already exists the migration is a no-op.
 *
 * Removal — delete the ticket type row; the FK CASCADE on the type-map
 * and the option-tree will leave the custom fields un-scoped (i.e.
 * global). To remove the fields too, also DELETE FROM ticket_form_fields
 * WHERE label LIKE '%(example form)%'.
 */
return static function (PDO $pdo): void {

    // Bail if the showcase already exists (idempotency).
    $existing = $pdo->prepare('SELECT id FROM ticket_types WHERE name = ? LIMIT 1');
    $existing->execute(['Example Ticket Form']);
    if ($existing->fetchColumn()) {
        return;
    }

    $pdo->beginTransaction();

    try {
        // 1. Create the showcase ticket type. Distinct purple colour so
        //    it stands out in lists; sort_order pushed to the end so it
        //    sits at the bottom of the type picker.
        $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM ticket_types')->fetchColumn();
        $pdo->prepare(
            "INSERT INTO ticket_types (name, color, sort_order) VALUES (?, ?, ?)"
        )->execute(['Example Ticket Form', '#6f42c1', $maxOrder + 1]);
        $typeId = (int) $pdo->lastInsertId();

        // 2. Field roster — one of every supported type. The label
        //    suffix "(example form)" makes the rows easy to find later
        //    if the user wants to delete just the showcase fields.
        //    Order matches what the user sees on the form.
        $fields = [
            ['text_block', 'Welcome to the Example Ticket Form (example form)', null, 0, 1, json_encode([
                'content' =>
                    "This form showcases every custom field type the helpdesk supports.\n\n" .
                    "Each field below is a different type — text, dropdowns, dates, " .
                    "cascading selects, image displays, etc. Use this as a reference when " .
                    "building real ticket forms in Admin → Workflows → Ticket Fields.\n\n" .
                    "(This intro itself is a 'text_block' field — read-only display text.)",
            ])],
            ['text',       'Single-line Text (example form)',                   'e.g. short answer goes here',  0, 1, null],
            ['textarea',   'Multi-line Textarea (example form)',                'e.g. longer free-form notes',  0, 1, null],
            ['number',     'Whole Number (example form)',                       'e.g. 42',                      0, 1, null],
            ['decimal',    'Decimal Number (example form)',                     'e.g. 19.99',                   0, 1, null],
            ['date',       'Single Date (example form)',                        null,                           0, 1, null],
            ['date_range', 'Date Range (example form)',                         null,                           0, 1, null],
            ['checkbox',   'Yes / No Checkbox (example form)',                  null,                           0, 1, null],
            ['dropdown',   'Single-select Dropdown (example form)',             null,                           0, 1, null],
            ['dependent',  'Cascading 3-level Dropdown (example form)',         null,                           0, 1, json_encode([
                'levels'   => 3,
                'l1_label' => 'Region',
                'l2_label' => 'Country',
                'l3_label' => 'City',
            ])],
            ['cc',         'CC Additional Users (example form)',                null,                           0, 1, null],
            ['image',      'Image Display (example form)',                      null,                           0, 1, null], // image_path filled in below
        ];

        $insertField = $pdo->prepare(
            "INSERT INTO ticket_form_fields
                (field_type, label, placeholder, is_required, is_visible, sort_order, config)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $mapField = $pdo->prepare(
            "INSERT INTO ticket_form_field_type_map (field_id, type_id) VALUES (?, ?)"
        );

        $fieldIds = []; // field_type => id
        foreach ($fields as $i => $f) {
            [$type, $label, $placeholder, $required, $visible, $config] = $f;
            $insertField->execute([$type, $label, $placeholder, $required, $visible, $i + 1, $config]);
            $id = (int) $pdo->lastInsertId();
            $fieldIds[$type] = $id;
            $mapField->execute([$id, $typeId]);
        }

        // 3. Dropdown options — three sample choices.
        $insertOpt = $pdo->prepare(
            "INSERT INTO ticket_form_field_options (field_id, parent_option_id, label, sort_order)
             VALUES (?, ?, ?, ?)"
        );
        foreach (['Option A', 'Option B', 'Option C'] as $i => $opt) {
            $insertOpt->execute([$fieldIds['dropdown'], null, $opt, $i + 1]);
        }

        // 4. Dependent field hierarchy — Region → Country → City.
        $depId = $fieldIds['dependent'];
        $hierarchy = [
            'North America' => [
                'USA'    => ['New York',  'San Francisco'],
                'Canada' => ['Toronto',   'Vancouver'],
            ],
            'Europe' => [
                'UK'      => ['London', 'Manchester'],
                'Germany' => ['Berlin', 'Munich'],
            ],
        ];
        $l1Order = 0;
        foreach ($hierarchy as $regionLabel => $countries) {
            $l1Order++;
            $insertOpt->execute([$depId, null, $regionLabel, $l1Order]);
            $regionId = (int) $pdo->lastInsertId();

            $l2Order = 0;
            foreach ($countries as $countryLabel => $cities) {
                $l2Order++;
                $insertOpt->execute([$depId, $regionId, $countryLabel, $l2Order]);
                $countryId = (int) $pdo->lastInsertId();

                $l3Order = 0;
                foreach ($cities as $cityLabel) {
                    $l3Order++;
                    $insertOpt->execute([$depId, $countryId, $cityLabel, $l3Order]);
                }
            }
        }

        // 5. Placeholder image for the `image` field. The PNG is embedded
        //    as base64 below — pre-rendered at migration-authoring time
        //    so we don't depend on the GD extension at runtime (production
        //    PHP doesn't ship php-gd by default). 480×140 light-purple
        //    panel reading "Example image field". An admin can replace
        //    it from Admin → Workflows → Ticket Fields.
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/field-images/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $pngB64 = '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';
        if (is_dir($uploadDir)) {
            $filename = 'field_' . $fieldIds['image'] . '_example.png';
            if (@file_put_contents($uploadDir . $filename, base64_decode($pngB64)) !== false) {
                $pdo->prepare(
                    'UPDATE ticket_form_fields SET config = ?, updated_at = NOW() WHERE id = ?'
                )->execute([json_encode(['image_path' => $filename]), $fieldIds['image']]);
            }
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
};
