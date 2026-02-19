<?php
$sidebarItems = adminSidebar('workflows');
$layout       = 'app';

// Field type metadata
$fieldTypeMeta = [
    'text'      => ['label' => 'Text',             'icon' => 'bi-input-cursor-text'],
    'textarea'  => ['label' => 'Multi-line Text',  'icon' => 'bi-textarea-t'],
    'checkbox'  => ['label' => 'Checkbox',         'icon' => 'bi-check2-square'],
    'dropdown'  => ['label' => 'Dropdown',         'icon' => 'bi-chevron-expand'],
    'date'      => ['label' => 'Date',             'icon' => 'bi-calendar'],
    'number'    => ['label' => 'Number',           'icon' => 'bi-123'],
    'decimal'   => ['label' => 'Decimal',          'icon' => 'bi-0-circle'],
    'dependent' => ['label' => 'Dependent Field',  'icon' => 'bi-diagram-3'],
];
?>
<style>
    .field-palette-card {
        cursor: pointer;
        border: 1px solid #e2e8f0;
        border-radius: .5rem;
        padding: .6rem .75rem;
        margin-bottom: .4rem;
        background: #fff;
        transition: background .12s, border-color .12s;
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .875rem;
        user-select: none;
    }
    .field-palette-card:hover {
        background: #eef2ff;
        border-color: var(--ld-primary);
    }
    .field-palette-card i { font-size: 1rem; color: var(--ld-primary); }

    .field-canvas-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .5rem;
        padding: .65rem 1rem;
        margin-bottom: .5rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        cursor: default;
    }
    .field-canvas-card .drag-handle {
        cursor: grab;
        color: #94a3b8;
        font-size: 1.1rem;
    }
    .field-canvas-card .drag-handle:active { cursor: grabbing; }
    .field-canvas-label { flex: 1; font-size: .9rem; font-weight: 500; }
    .canvas-empty {
        border: 2px dashed #cbd5e1;
        border-radius: .5rem;
        padding: 2rem;
        text-align: center;
        color: #94a3b8;
        font-size: .875rem;
    }
    #fieldModal .modal-body .field-section { display: none; }
    #fieldModal .modal-body .field-section.active { display: block; }

    /* Dropdown option pills */
    .opt-pill {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: .2rem .6rem;
        font-size: .8rem;
        margin: .15rem;
    }
    .opt-pill .remove-opt {
        cursor: pointer;
        color: #94a3b8;
        line-height: 1;
        padding: 0 2px;
    }
    .opt-pill .remove-opt:hover { color: #ef4444; }

    /* Dependent tree preview */
    .dep-tree { font-size: .825rem; }
    .dep-tree ul { list-style: none; padding-left: 1.2rem; }
    .dep-tree > ul { padding-left: 0; }
    .dep-tree li { padding: .1rem 0; }
    .dep-tree li::before { content: "▸ "; color: var(--ld-primary); font-size: .7rem; }

    [data-bs-theme="dark"] .field-palette-card { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .field-palette-card:hover { background: #2b3035; }
    [data-bs-theme="dark"] .field-canvas-card { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .opt-pill { background: #2b3035; border-color: #495057; }
    [data-bs-theme="dark"] .canvas-empty { border-color: #495057; }
    #fieldCanvas.canvas-drag-over {
        outline: 2px dashed var(--ld-primary);
        border-radius: .375rem;
        background: rgba(79, 70, 229, .04);
    }
    .field-palette-card { cursor: grab; touch-action: none; }
    .field-palette-card:active { cursor: grabbing; }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Ticket Fields</h4>
        <p class="text-muted mb-0 small">Build the custom fields shown on the ticket creation form.</p>
    </div>
</div>

<div class="row g-4">
    <!-- ── Field Palette ── -->
    <div class="col-md-3">
        <div class="card shadow-sm sticky-top" style="top: calc(var(--ld-navbar-height) + 1rem);">
            <div class="card-header bg-white border-bottom py-2 px-3">
                <span class="fw-semibold small text-uppercase" style="letter-spacing:.05em;font-size:.72rem;">Add Field</span>
            </div>
            <div class="card-body p-2" id="fieldPalette">
                <?php foreach ($fieldTypeMeta as $type => $meta): ?>
                <div class="field-palette-card" data-type="<?= e($type) ?>" role="button" title="Drag onto canvas or click to add <?= e($meta['label']) ?>">
                    <i class="bi <?= e($meta['icon']) ?>"></i>
                    <span><?= e($meta['label']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── Form Canvas ── -->
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-2 px-3">
                <span class="fw-semibold small">Form Fields</span>
                <span class="text-muted small" id="fieldCountBadge">
                    <?= count($fields) ?> field<?= count($fields) !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="card-body p-3">
                <div id="fieldCanvas">
                    <?php if (empty($fields)): ?>
                    <div class="canvas-empty" id="emptyState">
                        <i class="bi bi-layout-text-window-reverse fs-2 d-block mb-2 text-muted"></i>
                        No fields added yet. Click a field type on the left to add it to the form.
                    </div>
                    <?php else: ?>
                    <?php foreach ($fields as $field): ?>
                    <?php
                        $meta = $fieldTypeMeta[$field['field_type']] ?? ['label' => $field['field_type'], 'icon' => 'bi-question'];
                    ?>
                    <div class="field-canvas-card" data-field-id="<?= (int) $field['id'] ?>" data-field-type="<?= e($field['field_type']) ?>">
                        <i class="bi bi-grip-vertical drag-handle"></i>
                        <span class="badge bg-secondary" style="font-size:.68rem;"><?= e($meta['label']) ?></span>
                        <span class="field-canvas-label"><?= e($field['label']) ?></span>
                        <?php if ($field['is_required']): ?>
                        <span class="badge bg-danger" style="font-size:.65rem;">Required</span>
                        <?php endif; ?>
                        <?php if (!$field['is_visible']): ?>
                        <i class="bi bi-eye-slash text-muted" title="Hidden from portal users"></i>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 edit-field-btn" data-field-id="<?= (int) $field['id'] ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 delete-field-btn" data-field-id="<?= (int) $field['id'] ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Field Properties Modal ── -->
<div class="modal fade" id="fieldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fieldModalTitle">Edit Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalFieldId">
                <input type="hidden" id="modalFieldType">

                <!-- Common fields -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-medium">Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalLabel" placeholder="Field label shown to users">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Placeholder</label>
                        <input type="text" class="form-control" id="modalPlaceholder" placeholder="Optional hint text">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-auto">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="modalRequired">
                            <label class="form-check-label" for="modalRequired">Required field</label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="modalVisible" checked>
                            <label class="form-check-label" for="modalVisible">Visible to portal users</label>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Dropdown options -->
                <div id="sectionDropdown" class="field-section">
                    <label class="form-label fw-medium">Options</label>
                    <div id="dropdownPills" class="mb-2" style="min-height:2rem;"></div>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm" id="newOptInput" placeholder="Type an option and press Enter or Add">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addOptBtn">Add</button>
                    </div>
                    <div class="form-text">Each option will appear in the dropdown. Press Enter or click Add.</div>
                </div>

                <!-- Dependent field options -->
                <div id="sectionDependent" class="field-section">
                    <div class="row g-3 mb-3">
                        <div class="col-auto">
                            <label class="form-label fw-medium d-block">Number of levels</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="depLevels" id="dep2" value="2">
                                <label class="form-check-label" for="dep2">2 levels</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="depLevels" id="dep3" value="3" checked>
                                <label class="form-check-label" for="dep3">3 levels</label>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Level 1 Label</label>
                            <input type="text" class="form-control form-control-sm" id="depL1Label" placeholder="e.g. Category">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Level 2 Label</label>
                            <input type="text" class="form-control form-control-sm" id="depL2Label" placeholder="e.g. Subcategory">
                        </div>
                        <div class="col-md-4" id="depL3Wrap">
                            <label class="form-label small fw-medium">Level 3 Label</label>
                            <input type="text" class="form-control form-control-sm" id="depL3Label" placeholder="e.g. Item">
                        </div>
                    </div>

                    <label class="form-label fw-medium">Hierarchy</label>
                    <p class="text-muted small mb-1">
                        Paste your hierarchy below. Use <strong>no indent</strong> for Level 1, <strong>one tab</strong> (or 2 spaces) for Level 2, <strong>two tabs</strong> (or 4 spaces) for Level 3.
                    </p>
                    <textarea class="form-control font-monospace" id="depHierarchy" rows="10" placeholder="Category A&#10;&#9;Subcategory 1&#10;&#9;&#9;Item X&#10;&#9;&#9;Item Y&#10;&#9;Subcategory 2&#10;Category B&#10;&#9;Subcategory 3"></textarea>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="previewDepBtn">
                        <i class="bi bi-eye me-1"></i>Preview hierarchy
                    </button>
                    <div id="depPreview" class="dep-tree mt-2" style="display:none;max-height:200px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:.375rem;padding:.5rem;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFieldBtn">Save Field</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function () {
    var canvas      = document.getElementById('fieldCanvas');
    var countBadge  = document.getElementById('fieldCountBadge');
    var modal       = new bootstrap.Modal(document.getElementById('fieldModal'));
    var modalEl     = document.getElementById('fieldModal');

    var fieldTypeMeta = <?= json_encode(array_map(fn($m) => ['label' => $m['label'], 'icon' => $m['icon']], $fieldTypeMeta)) ?>;

    /* ─── Sortable canvas (internal reorder only) ─── */
    var sortable = Sortable.create(canvas, {
        handle:    '.drag-handle',
        animation: 150,
        filter:    '.canvas-empty',
        onEnd:     function () { saveOrder(); }
    });

    /* ─── Shared add-field function ─── */
    function doAddField(type) {
        fetch('/admin/workflows/ticket-fields/add', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     {'Content-Type': 'application/x-www-form-urlencoded'},
            body:        'field_type=' + encodeURIComponent(type)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert(data.error || 'Error adding field.'); return; }
            var card = buildCard(data.field);
            canvas.appendChild(card);
            updateCount();
            openModal(data.field);
        });
    }

    /* ─── Pointer-capture drag: palette → canvas ──────────────────────────
       Uses the Pointer Events API + setPointerCapture so the tile receives
       ALL pointer events even when the cursor leaves it.  This bypasses both
       the HTML5 Drag API and any SortableJS interference.
       Click (no movement) and drag are both handled here; the separate
       click listener below is removed.
    ──────────────────────────────────────────────────────────────────── */
    document.querySelectorAll('.field-palette-card').forEach(function (tile) {
        var tileType  = tile.dataset.type;
        var ghost     = null;
        var startX    = 0;
        var startY    = 0;
        var dragging  = false;

        tile.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            e.preventDefault();                 // prevent text-select / browser drag
            tile.setPointerCapture(e.pointerId); // all future events routed here
            startX   = e.clientX;
            startY   = e.clientY;
            dragging = false;
            ghost    = null;
        });

        tile.addEventListener('pointermove', function (e) {
            if (!tile.hasPointerCapture(e.pointerId)) return;
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;

            if (!dragging && Math.sqrt(dx * dx + dy * dy) > 6) {
                dragging = true;
                ghost = document.createElement('div');
                ghost.className = 'field-palette-card';
                ghost.style.cssText =
                    'position:fixed;z-index:9999;pointer-events:none;opacity:.85;' +
                    'min-width:140px;box-shadow:0 4px 16px rgba(0,0,0,.18);margin:0;';
                ghost.innerHTML = tile.innerHTML;
                document.body.appendChild(ghost);
            }

            if (ghost) {
                ghost.style.left = (e.clientX - 70) + 'px';
                ghost.style.top  = (e.clientY - 18) + 'px';
                var r = canvas.getBoundingClientRect();
                canvas.classList.toggle('canvas-drag-over',
                    e.clientX >= r.left && e.clientX <= r.right &&
                    e.clientY >= r.top  && e.clientY <= r.bottom);
            }
        });

        function finishDrag(e) {
            if (!tile.hasPointerCapture(e.pointerId)) return;
            if (ghost) { ghost.remove(); ghost = null; }
            canvas.classList.remove('canvas-drag-over');

            if (dragging) {
                // Drag: add only if released over canvas
                var r = canvas.getBoundingClientRect();
                if (e.clientX >= r.left && e.clientX <= r.right &&
                    e.clientY >= r.top  && e.clientY <= r.bottom) {
                    doAddField(tileType);
                }
            } else {
                // No movement = click
                doAddField(tileType);
            }
            dragging = false;
        }

        tile.addEventListener('pointerup',     finishDrag);
        tile.addEventListener('pointercancel', function (e) {
            if (!tile.hasPointerCapture(e.pointerId)) return;
            if (ghost) { ghost.remove(); ghost = null; }
            canvas.classList.remove('canvas-drag-over');
            dragging = false;
        });
    });

    function saveOrder() {
        var cards = canvas.querySelectorAll('.field-canvas-card');
        var order = Array.from(cards).map(function (c) { return c.dataset.fieldId; });
        fetch('/admin/workflows/ticket-fields/reorder', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({order: order})
        });
    }

    /* ─── Update count badge ─── */
    function updateCount() {
        var n = canvas.querySelectorAll('.field-canvas-card').length;
        countBadge.textContent = n + ' field' + (n !== 1 ? 's' : '');
        var empty = document.getElementById('emptyState');
        if (n === 0 && !empty) {
            var div = document.createElement('div');
            div.className = 'canvas-empty';
            div.id = 'emptyState';
            div.innerHTML = '<i class="bi bi-layout-text-window-reverse fs-2 d-block mb-2 text-muted"></i>No fields added yet. Click a field type on the left to add it to the form.';
            canvas.appendChild(div);
        } else if (n > 0 && empty) {
            empty.remove();
        }
    }

    /* ─── Build card HTML ─── */
    function buildCard(field) {
        var meta   = fieldTypeMeta[field.field_type] || {label: field.field_type, icon: 'bi-question'};
        var reqBadge  = field.is_required  ? '<span class="badge bg-danger" style="font-size:.65rem;">Required</span>' : '';
        var eyeIcon   = !parseInt(field.is_visible) ? '<i class="bi bi-eye-slash text-muted" title="Hidden from portal users"></i>' : '';
        var card = document.createElement('div');
        card.className = 'field-canvas-card';
        card.dataset.fieldId   = field.id;
        card.dataset.fieldType = field.field_type;
        card.innerHTML =
            '<i class="bi bi-grip-vertical drag-handle"></i>' +
            '<span class="badge bg-secondary" style="font-size:.68rem;">' + esc(meta.label) + '</span>' +
            '<span class="field-canvas-label">' + esc(field.label) + '</span>' +
            reqBadge + eyeIcon +
            '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 edit-field-btn" data-field-id="' + field.id + '">' +
            '<i class="bi bi-pencil"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 delete-field-btn" data-field-id="' + field.id + '">' +
            '<i class="bi bi-trash"></i></button>';
        return card;
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    /* ─── Delete field ─── */
    canvas.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-field-btn');
        if (!btn) return;
        if (!confirm('Delete this field? Any stored values will be removed.')) return;
        var id   = btn.dataset.fieldId;
        var card = canvas.querySelector('[data-field-id="' + id + '"]');
        fetch('/admin/workflows/ticket-fields/' + id + '/delete', {
            method: 'POST',
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) { card.remove(); updateCount(); }
        });
    });

    /* ─── Open Edit modal ─── */
    canvas.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-field-btn');
        if (!btn) return;
        var id   = btn.dataset.fieldId;
        var card = canvas.querySelector('[data-field-id="' + id + '"]');
        var type = card.dataset.fieldType;

        // Build a stub field object from card data for immediate display
        var label = card.querySelector('.field-canvas-label').textContent.trim();
        openModal({
            id: id,
            field_type: type,
            label: label,
            placeholder: '',
            is_required: card.querySelector('.badge.bg-danger') ? 1 : 0,
            is_visible:  card.querySelector('.bi-eye-slash')    ? 0 : 1,
            config: null,
        });
    });

    /* ─── Modal logic ─── */
    var dropdownPills  = document.getElementById('dropdownPills');
    var newOptInput    = document.getElementById('newOptInput');
    var addOptBtn      = document.getElementById('addOptBtn');
    var depHierarchy   = document.getElementById('depHierarchy');
    var previewDepBtn  = document.getElementById('previewDepBtn');
    var depPreview     = document.getElementById('depPreview');
    var depL3Wrap      = document.getElementById('depL3Wrap');
    var currentOptions = []; // for dropdown
    var fetchedConfig  = null;

    function openModal(field) {
        document.getElementById('modalFieldId').value   = field.id;
        document.getElementById('modalFieldType').value = field.field_type;
        document.getElementById('fieldModalTitle').textContent = 'Edit Field — ' + (fieldTypeMeta[field.field_type] || {label: field.field_type}).label;
        document.getElementById('modalLabel').value       = field.label || '';
        document.getElementById('modalPlaceholder').value = field.placeholder || '';
        document.getElementById('modalRequired').checked  = !!parseInt(field.is_required);
        document.getElementById('modalVisible').checked   = field.is_visible === undefined ? true : !!parseInt(field.is_visible);

        // Show/hide type-specific sections
        document.querySelectorAll('.field-section').forEach(function (s) { s.classList.remove('active'); });

        if (field.field_type === 'dropdown') {
            document.getElementById('sectionDropdown').classList.add('active');
            currentOptions = [];
            dropdownPills.innerHTML = '';
            depHierarchy.value = '';
            // Fetch current options
            fetch('/admin/workflows/ticket-fields/' + field.id + '/options', {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (opts) {
                opts.forEach(function (o) { addDropdownPill(o.label); });
            });
        } else if (field.field_type === 'dependent') {
            document.getElementById('sectionDependent').classList.add('active');
            depPreview.style.display = 'none';
            depHierarchy.value = '';
            fetchedConfig = field.config ? (typeof field.config === 'string' ? JSON.parse(field.config) : field.config) : null;
            if (fetchedConfig) {
                document.querySelector('input[name="depLevels"][value="' + (fetchedConfig.levels || 3) + '"]').checked = true;
                document.getElementById('depL1Label').value = fetchedConfig.l1_label || '';
                document.getElementById('depL2Label').value = fetchedConfig.l2_label || '';
                document.getElementById('depL3Label').value = fetchedConfig.l3_label || '';
                depL3Wrap.style.display = (fetchedConfig.levels || 3) < 3 ? 'none' : '';
            } else {
                document.getElementById('dep3').checked = true;
                document.getElementById('depL1Label').value = 'Category';
                document.getElementById('depL2Label').value = 'Subcategory';
                document.getElementById('depL3Label').value = 'Item';
                depL3Wrap.style.display = '';
            }
            // Fetch existing options and reconstruct textarea
            fetch('/admin/workflows/ticket-fields/' + field.id + '/options', {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (opts) {
                depHierarchy.value = optionsToHierarchyText(opts);
            });
        }

        modal.show();
    }

    // Rebuild hierarchy textarea from flat DB rows
    function optionsToHierarchyText(opts) {
        var byId = {};
        opts.forEach(function (o) { byId[o.id] = o; });
        var lines = [];
        // Level 1 (no parent)
        opts.filter(function (o) { return !o.parent_option_id; }).forEach(function (l1) {
            lines.push(l1.label);
            opts.filter(function (o) { return parseInt(o.parent_option_id) === parseInt(l1.id); }).forEach(function (l2) {
                lines.push('\t' + l2.label);
                opts.filter(function (o) { return parseInt(o.parent_option_id) === parseInt(l2.id); }).forEach(function (l3) {
                    lines.push('\t\t' + l3.label);
                });
            });
        });
        return lines.join('\n');
    }

    // Dropdown pill management
    function addDropdownPill(label) {
        label = label.trim();
        if (!label) return;
        if (currentOptions.indexOf(label) !== -1) return; // no dups
        currentOptions.push(label);
        var pill = document.createElement('span');
        pill.className = 'opt-pill';
        pill.innerHTML = esc(label) + '<span class="remove-opt" data-label="' + esc(label) + '">&times;</span>';
        dropdownPills.appendChild(pill);
    }

    dropdownPills.addEventListener('click', function (e) {
        var rm = e.target.closest('.remove-opt');
        if (!rm) return;
        var lbl = rm.dataset.label;
        currentOptions = currentOptions.filter(function (o) { return o !== lbl; });
        rm.parentElement.remove();
    });

    function addOptFromInput() {
        var v = newOptInput.value;
        addDropdownPill(v);
        newOptInput.value = '';
    }
    addOptBtn.addEventListener('click', addOptFromInput);
    newOptInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); addOptFromInput(); }
    });

    // Dependent levels radio toggle
    document.querySelectorAll('input[name="depLevels"]').forEach(function (r) {
        r.addEventListener('change', function () {
            depL3Wrap.style.display = r.value === '2' ? 'none' : '';
        });
    });

    // Hierarchy parse helpers
    function indentLevel(line) {
        var match = line.match(/^(\t+)/);
        if (match) return match[1].length;
        var spMatch = line.match(/^( +)/);
        if (spMatch) return Math.floor(spMatch[1].length / 2);
        return 0;
    }

    function parseHierarchy(text) {
        var lines = text.split('\n').map(function (l) { return l.trimEnd(); }).filter(function (l) { return l.trim() !== ''; });
        var root = [];
        var stack = [root]; // stack[depth] = current parent children array
        lines.forEach(function (line) {
            var depth = indentLevel(line);
            var label = line.trim();
            var node  = {label: label, children: []};
            if (!stack[depth]) { depth = stack.length - 1; }
            stack[depth].push(node);
            stack[depth + 1] = node.children;
            stack.length = depth + 2;
        });
        return root;
    }

    function buildTreeHtml(nodes) {
        if (!nodes || !nodes.length) return '';
        var html = '<ul>';
        nodes.forEach(function (n) {
            html += '<li>' + esc(n.label) + buildTreeHtml(n.children) + '</li>';
        });
        html += '</ul>';
        return html;
    }

    previewDepBtn.addEventListener('click', function () {
        var tree = parseHierarchy(depHierarchy.value);
        depPreview.innerHTML = buildTreeHtml(tree);
        depPreview.style.display = 'block';
    });

    /* ─── Save field ─── */
    document.getElementById('saveFieldBtn').addEventListener('click', function () {
        var id        = document.getElementById('modalFieldId').value;
        var type      = document.getElementById('modalFieldType').value;
        var label     = document.getElementById('modalLabel').value.trim();
        var placeholder = document.getElementById('modalPlaceholder').value.trim();
        var isRequired  = document.getElementById('modalRequired').checked;
        var isVisible   = document.getElementById('modalVisible').checked;

        if (!label) { document.getElementById('modalLabel').focus(); return; }

        var payload = {
            label:       label,
            placeholder: placeholder,
            is_required: isRequired,
            is_visible:  isVisible,
        };

        if (type === 'dropdown') {
            payload.options = currentOptions.map(function (o, i) { return {label: o, sort_order: i}; });
        }

        if (type === 'dependent') {
            var levels = parseInt(document.querySelector('input[name="depLevels"]:checked').value);
            payload.config = {
                levels:   levels,
                l1_label: document.getElementById('depL1Label').value.trim() || 'Category',
                l2_label: document.getElementById('depL2Label').value.trim() || 'Subcategory',
                l3_label: document.getElementById('depL3Label').value.trim() || 'Item',
            };
            payload.options = parseHierarchy(depHierarchy.value);
        }

        fetch('/admin/workflows/ticket-fields/' + id + '/update', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert(data.error || 'Error saving field.'); return; }

            // Update card in canvas
            var card = canvas.querySelector('[data-field-id="' + id + '"]');
            if (card) {
                card.querySelector('.field-canvas-label').textContent = label;
                // Required badge
                var reqBadge = card.querySelector('.badge.bg-danger');
                if (isRequired && !reqBadge) {
                    var b = document.createElement('span');
                    b.className = 'badge bg-danger';
                    b.style.fontSize = '.65rem';
                    b.textContent = 'Required';
                    card.querySelector('.field-canvas-label').after(b);
                } else if (!isRequired && reqBadge) {
                    reqBadge.remove();
                }
                // Eye-slash icon
                var eyeIcon = card.querySelector('.bi-eye-slash');
                if (!isVisible && !eyeIcon) {
                    var i = document.createElement('i');
                    i.className = 'bi bi-eye-slash text-muted';
                    i.title = 'Hidden from portal users';
                    card.querySelector('.edit-field-btn').before(i);
                } else if (isVisible && eyeIcon) {
                    eyeIcon.remove();
                }
            }

            modal.hide();
        });
    });

    // Initialise count
    updateCount();
})();
</script>
