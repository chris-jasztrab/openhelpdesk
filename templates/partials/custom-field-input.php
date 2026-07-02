<?php
/**
 * Shared custom field rendering partial.
 *
 * Expected variables in scope:
 *   $cf         — the ticket_form_fields row (definition only — no per-type flags)
 *   $cfKey      — 'field_<id>'
 *   $cfOpts     — options array (for dropdown/dependent)
 *   $cfRequired — bool: required *for the currently selected ticket type*
 *                 (the form-create page sets this from the layout table)
 *   $portalMode — (optional) bool, true if rendering on the portal form
 */
$portalMode = $portalMode ?? false;
$cfRequired = $cfRequired ?? false;
$requiredAttr = ($portalMode && $cfRequired) ? 'required' : '';
?>
<?php if ($cf['field_type'] === 'text_block'):
    $tbCfg = $cf['config'] ? (is_string($cf['config']) ? json_decode($cf['config'], true) : $cf['config']) : [];
?>
<div class="mb-3 custom-field-wrap" data-field-id="<?= (int) $cf['id'] ?>">
    <?php if (!empty($cf['label'])): ?>
    <p class="fw-semibold mb-1"><?= e($cf['label']) ?></p>
    <?php endif; ?>
    <?php
        $tbContent = (string) ($tbCfg['content'] ?? '');
        // Content authored in CKEditor is HTML (starts with a tag). Legacy blocks
        // are plain text — escape those and preserve their line breaks.
        $tbIsHtml = $tbContent !== '' && ltrim($tbContent)[0] === '<';
    ?>
    <div class="border rounded p-3 bg-light text-secondary small ld-text-block">
        <?php if ($tbIsHtml): ?>
            <?= sanitizeRichHtml($tbContent) ?>
        <?php else: ?>
            <span style="white-space:pre-wrap;"><?= e($tbContent) ?></span>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($cf['field_type'] === 'image'):
    $imgCfg = $cf['config'] ? (is_string($cf['config']) ? json_decode($cf['config'], true) : $cf['config']) : [];
?>
<div class="mb-3 custom-field-wrap" data-field-id="<?= (int) $cf['id'] ?>">
    <?php if (!empty($imgCfg['image_path'])): ?>
    <img src="/uploads/field-images/<?= e($imgCfg['image_path']) ?>"
         alt="<?= e($cf['label']) ?>" class="img-fluid rounded" style="max-height:400px;">
    <?php if (!empty($cf['label'])): ?>
    <p class="text-muted small mt-1"><?= e($cf['label']) ?></p>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="mb-3 custom-field-wrap" data-field-id="<?= (int) $cf['id'] ?>">
    <label class="form-label fw-semibold">
        <?= e($cf['label']) ?>
        <span class="text-danger ms-1 field-required-star" <?= $cfRequired ? '' : 'style="display:none;"' ?>>*</span>
    </label>

    <?php if ($cf['field_type'] === 'text'): ?>
    <input type="text" class="form-control" name="<?= e($cfKey) ?>"
           placeholder="<?= e($cf['placeholder'] ?? '') ?>"
           value="<?= e(old($cfKey)) ?>" <?= $requiredAttr ?>>

    <?php elseif ($cf['field_type'] === 'textarea'): ?>
    <textarea class="form-control" name="<?= e($cfKey) ?>" rows="3"
              placeholder="<?= e($cf['placeholder'] ?? '') ?>" <?= $requiredAttr ?>><?= e(old($cfKey)) ?></textarea>

    <?php elseif ($cf['field_type'] === 'checkbox'): ?>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="<?= e($cfKey) ?>" value="1"
               id="<?= e($cfKey) ?>" <?= old($cfKey) ? 'checked' : '' ?>>
        <label class="form-check-label" for="<?= e($cfKey) ?>">Yes</label>
    </div>

    <?php elseif ($cf['field_type'] === 'dropdown'): ?>
    <select class="form-select" name="<?= e($cfKey) ?>" <?= $requiredAttr ?>>
        <option value="">— Select —</option>
        <?php foreach ($cfOpts as $opt): ?>
        <option value="<?= (int) $opt['id'] ?>"
            <?= old($cfKey) == $opt['id'] ? 'selected' : '' ?>>
            <?= e($opt['label']) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <?php elseif ($cf['field_type'] === 'date'): ?>
    <input type="date" class="form-control" name="<?= e($cfKey) ?>"
           value="<?= e(old($cfKey)) ?>" <?= $requiredAttr ?>>

    <?php elseif ($cf['field_type'] === 'date_range'): ?>
    <div class="row g-2">
        <div class="col">
            <label class="form-label small">From</label>
            <input type="date" class="form-control" name="<?= e($cfKey) ?>_from"
                   value="<?= e(old($cfKey . '_from')) ?>" <?= $requiredAttr ?>>
        </div>
        <div class="col">
            <label class="form-label small">To</label>
            <input type="date" class="form-control" name="<?= e($cfKey) ?>_to"
                   value="<?= e(old($cfKey . '_to')) ?>" <?= $requiredAttr ?>>
        </div>
    </div>

    <?php elseif ($cf['field_type'] === 'number'): ?>
    <input type="number" step="1" class="form-control" name="<?= e($cfKey) ?>"
           placeholder="<?= e($cf['placeholder'] ?? '') ?>"
           value="<?= e(old($cfKey)) ?>" <?= $requiredAttr ?>>

    <?php elseif ($cf['field_type'] === 'decimal'): ?>
    <input type="number" step="0.01" class="form-control" name="<?= e($cfKey) ?>"
           placeholder="<?= e($cf['placeholder'] ?? '') ?>"
           value="<?= e(old($cfKey)) ?>" <?= $requiredAttr ?>>

    <?php elseif ($cf['field_type'] === 'cc'): ?>
    <div id="cc_badges_<?= (int) $cf['id'] ?>" class="d-flex flex-wrap gap-2 mb-2"></div>
    <div id="cc_hidden_<?= (int) $cf['id'] ?>"></div>
    <div class="position-relative" style="max-width:400px;">
        <input type="text" class="form-control cc-field-input"
               id="cc_input_<?= (int) $cf['id'] ?>"
               data-field-id="<?= (int) $cf['id'] ?>"
               placeholder="Search by name or email…" autocomplete="off"
               <?= $cfRequired ? 'data-required="1"' : '' ?>>
        <div id="cc_drop_<?= (int) $cf['id'] ?>" class="mention-dropdown" style="display:none;position:absolute;top:100%;left:0;z-index:1050;width:100%;"></div>
    </div>

    <?php elseif ($cf['field_type'] === 'dependent'):
        $config  = $cf['config'] ? (is_string($cf['config']) ? json_decode($cf['config'], true) : $cf['config']) : [];
        $levels  = (int) ($config['levels']   ?? 3);
        $l1Label = $config['l1_label'] ?? 'Category';
        $l2Label = $config['l2_label'] ?? 'Subcategory';
        $l3Label = $config['l3_label'] ?? 'Item';
        $l1Opts  = array_filter($cfOpts, fn($o) => !$o['parent_option_id']);
    ?>
    <div class="row g-2" id="dep_wrap_<?= (int) $cf['id'] ?>">
        <div class="col-md-4">
            <label class="form-label small"><?= e($l1Label) ?></label>
            <select class="form-select form-select-sm dep-l1"
                    name="<?= e($cfKey) ?>_l1"
                    data-field="<?= (int) $cf['id'] ?>" <?= $requiredAttr ?>>
                <option value="">— Select —</option>
                <?php foreach ($l1Opts as $opt): ?>
                <option value="<?= (int) $opt['id'] ?>"><?= e($opt['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4" id="dep_l2_wrap_<?= (int) $cf['id'] ?>" style="display:none;">
            <label class="form-label small"><?= e($l2Label) ?></label>
            <select class="form-select form-select-sm dep-l2"
                    name="<?= e($cfKey) ?>_l2"
                    data-field="<?= (int) $cf['id'] ?>">
                <option value="">— Select —</option>
            </select>
        </div>
        <?php if ($levels >= 3): ?>
        <div class="col-md-4" id="dep_l3_wrap_<?= (int) $cf['id'] ?>" style="display:none;">
            <label class="form-label small"><?= e($l3Label) ?></label>
            <select class="form-select form-select-sm dep-l3"
                    name="<?= e($cfKey) ?>_l3"
                    data-field="<?= (int) $cf['id'] ?>">
                <option value="">— Select —</option>
            </select>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
