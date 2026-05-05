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

        // 5. Placeholder image for the `image` field. We render a tiny
        //    PNG via GD so the migration is self-contained — no asset
        //    files to copy. If GD isn't installed (very rare in PHP
        //    deployments), the field is left without an image_path and
        //    the renderer simply shows nothing for it; an admin can
        //    upload one through Admin → Workflows → Ticket Fields.
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/field-images/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        // Note: is_writable() is unreliable on Windows under OneDrive — rely on
        // imagepng()'s return value to detect actual write failures.
        if (function_exists('imagecreatetruecolor') && is_dir($uploadDir)) {
            $w = 480;
            $h = 140;
            $img  = imagecreatetruecolor($w, $h);
            $bg   = imagecolorallocate($img, 240, 235, 250); // light purple
            $fg   = imagecolorallocate($img, 111, 66, 193);  // brand purple
            $line = imagecolorallocate($img, 200, 190, 220);
            imagefilledrectangle($img, 0, 0, $w, $h, $bg);
            imagerectangle($img, 0, 0, $w - 1, $h - 1, $line);
            $text1 = 'Example image field';
            $text2 = '(uploaded via Admin > Ticket Fields)';
            $tw1 = imagefontwidth(5) * strlen($text1);
            $tw2 = imagefontwidth(3) * strlen($text2);
            imagestring($img, 5, (int) (($w - $tw1) / 2), 45, $text1, $fg);
            imagestring($img, 3, (int) (($w - $tw2) / 2), 80, $text2, $fg);

            $filename = 'field_' . $fieldIds['image'] . '_example.png';
            $dest = $uploadDir . $filename;
            if (imagepng($img, $dest)) {
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
