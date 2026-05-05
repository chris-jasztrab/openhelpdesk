<?php
/**
 * Migration 031 — Backfill the Example Ticket Form image
 *
 * Migration 030's first revision relied on the GD extension to draw
 * the placeholder PNG for the showcase `image` field. Production PHP
 * doesn't ship php-gd so the image step silently no-op'd, leaving
 * the image field with NULL config and nothing to render.
 *
 * 030 has since been rewritten to embed a pre-rendered PNG as base64
 * and write it via file_put_contents (no GD required) — but that fix
 * only helps fresh installs because 030 is already recorded as
 * applied on existing deployments. This migration repairs them.
 *
 * Idempotent — only writes when the field's config is empty.
 * Safe to run on fresh installs too: 030's new code already populates
 * the image, so this finds nothing to fix and no-ops.
 */
return static function (PDO $pdo): void {

    // Find the showcase image field. Match on the label suffix that
    // 030 stamps on every showcase field — survives renames better
    // than matching the type-name + type-map join.
    $row = $pdo->query(
        "SELECT id, config
         FROM ticket_form_fields
         WHERE field_type = 'image'
           AND label LIKE '%(example form)%'
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return; // 030 wasn't applied (or its showcase fields were deleted) — nothing to repair
    }

    $cfg = $row['config'] ? json_decode($row['config'], true) : [];
    if (!empty($cfg['image_path'])) {
        return; // already populated — fresh install or previously repaired
    }

    $uploadDir = dirname(__DIR__, 2) . '/public/uploads/field-images/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    if (!is_dir($uploadDir)) {
        return; // can't create the dir — skip silently, admin can upload later
    }

    // Same pre-rendered 480×140 light-purple PNG as 030's rewrite.
    $pngB64 = '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';

    $filename = 'field_' . (int) $row['id'] . '_example.png';
    if (@file_put_contents($uploadDir . $filename, base64_decode($pngB64)) === false) {
        return; // can't write — admin can upload later, no point failing the migration
    }

    $pdo->prepare(
        'UPDATE ticket_form_fields SET config = ?, updated_at = NOW() WHERE id = ?'
    )->execute([json_encode(['image_path' => $filename]), (int) $row['id']]);
};
