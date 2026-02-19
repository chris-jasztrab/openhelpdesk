<?php
$sidebarItems = adminSidebar('workflows');
$layout       = 'app';

$fieldTypeMeta = [
    'text'      => ['label' => 'Text',            'icon' => 'bi-input-cursor-text'],
    'textarea'  => ['label' => 'Multi-line Text', 'icon' => 'bi-textarea-t'],
    'checkbox'  => ['label' => 'Checkbox',        'icon' => 'bi-check2-square'],
    'dropdown'  => ['label' => 'Dropdown',        'icon' => 'bi-chevron-expand'],
    'date'      => ['label' => 'Date',            'icon' => 'bi-calendar'],
    'number'    => ['label' => 'Number',          'icon' => 'bi-123'],
    'decimal'   => ['label' => 'Decimal',         'icon' => 'bi-0-circle'],
    'dependent' => ['label' => 'Dependent',       'icon' => 'bi-diagram-3'],
];

// Built-in (system) fields shown at top and bottom — not editable
$systemFieldsTop = [
    ['label' => 'Subject',      'icon' => 'bi-input-cursor-text', 'badge' => 'Required'],
    ['label' => 'Description',  'icon' => 'bi-textarea-t',        'badge' => 'Required'],
    ['label' => 'Ticket Type',  'icon' => 'bi-tag',               'badge' => 'Optional'],
    ['label' => 'Location',     'icon' => 'bi-geo-alt',           'badge' => 'Auto'],
    ['label' => 'Priority',     'icon' => 'bi-flag',              'badge' => 'Optional'],
    ['label' => 'Tags',         'icon' => 'bi-hash',              'badge' => 'Optional'],
];
$systemFieldsBottom = [
    ['label' => 'Attachments',  'icon' => 'bi-paperclip',         'badge' => 'Optional'],
];
?>
<style>
    .field-row {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .5rem;
        padding: .65rem 1rem;
        margin-bottom: .4rem;
        display: flex;
        align-items: center;
        gap: .75rem;
    }
    .field-row .drag-handle {
        cursor: grab;
        color: #94a3b8;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .field-row .drag-handle:active { cursor: grabbing; }
    .field-row-label { flex: 1; font-size: .9rem; font-weight: 500; }

    /* System rows — slightly muted */
    .field-row.system-row {
        background: #f8fafc;
        border-style: dashed;
        opacity: .85;
    }
    .field-row.system-row .drag-handle { visibility: hidden; }

    /* Section divider */
    .custom-section-label {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
        padding: .5rem 0 .25rem;
    }

    /* Empty state */
    .custom-empty {
        border: 2px dashed #cbd5e1;
        border-radius: .5rem;
        padding: 1.5rem;
        text-align: center;
        color: #94a3b8;
        font-size: .875rem;
        margin-bottom: .4rem;
    }

    /* Dropdown option pills */
    .opt-pill {
        display: inline-flex; align-items: center; gap: .3rem;
        background: #f1f5f9; border: 1px solid #e2e8f0;
        border-radius: 1rem; padding: .2rem .6rem;
        font-size: .8rem; margin: .15rem;
    }
    .opt-pill .remove-opt { cursor: pointer; color: #94a3b8; line-height: 1; padding: 0 2px; }
    .opt-pill .remove-opt:hover { color: #ef4444; }

    /* Dependent tree */
    .dep-tree { font-size: .825rem; }
    .dep-tree ul { list-style: none; padding-left: 1.2rem; }
    .dep-tree > ul { padding-left: 0; }
    .dep-tree li { padding: .1rem 0; }
    .dep-tree li::before { content: "▸ "; color: var(--ld-primary); font-size: .7rem; }

    #fieldModal .modal-body .field-section { display: none; }
    #fieldModal .modal-body .field-section.active { display: block; }

    [data-bs-theme="dark"] .field-row { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .field-row.system-row { background: var(--bs-tertiary-bg); }
    [data-bs-theme="dark"] .opt-pill { background: #2b3035; border-color: #495057; }
    [data-bs-theme="dark"] .custom-empty { border-color: #495057; }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Ticket Form Builder</h4>
        <p class="text-muted mb-0 small">Manage and extend the fields shown on the New Ticket form.</p>
    </div>
    <!-- Add Custom Field dropdown -->
    <div class="dropdown">
        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-plus-lg me-1"></i>Add Custom Field
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <?php foreach ($fieldTypeMeta as $type => $meta): ?>
            <li>
                <button class="dropdown-item add-field-btn" type="button" data-type="<?= e($type) ?>">
                    <i class="bi <?= e($meta['icon']) ?> me-2 text-primary"></i><?= e($meta['label']) ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-2 px-3">
        <span class="fw-semibold small">Form Fields</span>
        <span class="text-muted small" id="fieldCountBadge">
            <?= count($fields) ?> custom field<?= count($fields) !== 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="card-body p-3">

        <!-- Built-in fields (top) -->
        <div class="custom-section-label">
            <i class="bi bi-lock me-1"></i>System Fields
        </div>
        <?php foreach ($systemFieldsTop as $sf): ?>
        <div class="field-row system-row">
            <i class="bi bi-grip-vertical drag-handle"></i>
            <i class="bi <?= e($sf['icon']) ?> text-muted" style="font-size:1rem;flex-shrink:0;"></i>
            <span class="field-row-label"><?= e($sf['label']) ?></span>
            <span class="badge bg-secondary" style="font-size:.65rem;">System</span>
            <?php
                $badgeClass = match($sf['badge']) {
                    'Required' => 'bg-danger',
                    'Auto'     => 'bg-info',
                    default    => 'bg-light text-secondary border'
                };
            ?>
            <span class="badge <?= $badgeClass ?>" style="font-size:.65rem;"><?= e($sf['badge']) ?></span>
        </div>
        <?php endforeach; ?>

        <!-- Custom fields (sortable) -->
        <div class="custom-section-label mt-3">
            <i class="bi bi-sliders me-1"></i>Custom Fields
            <span class="text-muted fw-normal">&nbsp;— drag <i class="bi bi-grip-vertical"></i> to reorder</span>
        </div>
        <div id="customFieldList">
            <?php if (empty($fields)): ?>
            <div class="custom-empty" id="emptyState">
                <i class="bi bi-layout-text-window-reverse fs-2 d-block mb-2 text-muted"></i>
                No custom fields yet. Use <strong>Add Custom Field</strong> above to add fields to the form.
            </div>
            <?php else: ?>
            <?php foreach ($fields as $field):
                $meta = $fieldTypeMeta[$field['field_type']] ?? ['label' => $field['field_type'], 'icon' => 'bi-question'];
            ?>
            <div class="field-row" data-field-id="<?= (int) $field['id'] ?>" data-field-type="<?= e($field['field_type']) ?>">
                <i class="bi bi-grip-vertical drag-handle"></i>
                <span class="badge bg-secondary" style="font-size:.68rem;"><?= e($meta['label']) ?></span>
                <span class="field-row-label"><?= e($field['label']) ?></span>
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

        <!-- Built-in fields (bottom) -->
        <div class="custom-section-label mt-3">
            <i class="bi bi-lock me-1"></i>System Fields (continued)
        </div>
        <?php foreach ($systemFieldsBottom as $sf): ?>
        <div class="field-row system-row">
            <i class="bi bi-grip-vertical drag-handle"></i>
            <i class="bi <?= e($sf['icon']) ?> text-muted" style="font-size:1rem;flex-shrink:0;"></i>
            <span class="field-row-label"><?= e($sf['label']) ?></span>
            <span class="badge bg-secondary" style="font-size:.65rem;">System</span>
            <span class="badge bg-light text-secondary border" style="font-size:.65rem;"><?= e($sf['badge']) ?></span>
        </div>
        <?php endforeach; ?>

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
                        Use <strong>no indent</strong> for Level 1, <strong>one tab</strong> (or 2 spaces) for Level 2, <strong>two tabs</strong> (or 4 spaces) for Level 3.
                    </p>
                    <textarea class="form-control font-monospace" id="depHierarchy" rows="10"
                              placeholder="Category A&#10;&#9;Subcategory 1&#10;&#9;&#9;Item X&#10;&#9;&#9;Item Y&#10;&#9;Subcategory 2&#10;Category B"></textarea>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="previewDepBtn">
                        <i class="bi bi-eye me-1"></i>Preview hierarchy
                    </button>
                    <div id="depPreview" class="dep-tree mt-2"
                         style="display:none;max-height:200px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:.375rem;padding:.5rem;"></div>
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
document.addEventListener('DOMContentLoaded', function () {
    var list       = document.getElementById('customFieldList');
    var countBadge = document.getElementById('fieldCountBadge');
    var modal      = new bootstrap.Modal(document.getElementById('fieldModal'));

    var fieldTypeMeta = <?= json_encode(array_map(fn($m) => ['label' => $m['label'], 'icon' => $m['icon']], $fieldTypeMeta)) ?>;

    /* ─── SortableJS: reorder within custom list only ─── */
    Sortable.create(list, {
        handle:    '.drag-handle',
        animation: 150,
        filter:    '.custom-empty',
        onEnd:     function () { saveOrder(); }
    });

    /* ─── Add field from dropdown menu ─── */
    document.querySelectorAll('.add-field-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            doAddField(btn.dataset.type);
        });
    });

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
            var row = buildRow(data.field);
            // Remove empty state if present
            var empty = document.getElementById('emptyState');
            if (empty) empty.remove();
            list.appendChild(row);
            updateCount();
            openModal(data.field);
        });
    }

    function saveOrder() {
        var rows  = list.querySelectorAll('.field-row[data-field-id]');
        var order = Array.from(rows).map(function (r) { return r.dataset.fieldId; });
        fetch('/admin/workflows/ticket-fields/reorder', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     {'Content-Type': 'application/json'},
            body:        JSON.stringify({order: order})
        });
    }

    function updateCount() {
        var n = list.querySelectorAll('.field-row[data-field-id]').length;
        countBadge.textContent = n + ' custom field' + (n !== 1 ? 's' : '');
        if (n === 0 && !document.getElementById('emptyState')) {
            var div = document.createElement('div');
            div.className = 'custom-empty';
            div.id = 'emptyState';
            div.innerHTML = '<i class="bi bi-layout-text-window-reverse fs-2 d-block mb-2 text-muted"></i>' +
                'No custom fields yet. Use <strong>Add Custom Field</strong> above to add fields to the form.';
            list.appendChild(div);
        }
    }

    function buildRow(field) {
        var meta = fieldTypeMeta[field.field_type] || {label: field.field_type, icon: 'bi-question'};
        var reqBadge = field.is_required  ? '<span class="badge bg-danger" style="font-size:.65rem;">Required</span>' : '';
        var eyeIcon  = !parseInt(field.is_visible) ? '<i class="bi bi-eye-slash text-muted" title="Hidden from portal users"></i>' : '';
        var row = document.createElement('div');
        row.className = 'field-row';
        row.dataset.fieldId   = field.id;
        row.dataset.fieldType = field.field_type;
        row.innerHTML =
            '<i class="bi bi-grip-vertical drag-handle"></i>' +
            '<span class="badge bg-secondary" style="font-size:.68rem;">' + esc(meta.label) + '</span>' +
            '<span class="field-row-label">' + esc(field.label) + '</span>' +
            reqBadge + eyeIcon +
            '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 edit-field-btn" data-field-id="' + field.id + '">' +
            '<i class="bi bi-pencil"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 delete-field-btn" data-field-id="' + field.id + '">' +
            '<i class="bi bi-trash"></i></button>';
        return row;
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    /* ─── Delete ─── */
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-field-btn');
        if (!btn) return;
        if (!confirm('Delete this field? Any stored values will be removed.')) return;
        var id  = btn.dataset.fieldId;
        var row = list.querySelector('[data-field-id="' + id + '"]');
        fetch('/admin/workflows/ticket-fields/' + id + '/delete', {
            method: 'POST', credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) { row.remove(); updateCount(); }
        });
    });

    /* ─── Edit ─── */
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-field-btn');
        if (!btn) return;
        var id  = btn.dataset.fieldId;
        var row = list.querySelector('[data-field-id="' + id + '"]');
        openModal({
            id:          id,
            field_type:  row.dataset.fieldType,
            label:       row.querySelector('.field-row-label').textContent.trim(),
            placeholder: '',
            is_required: row.querySelector('.badge.bg-danger') ? 1 : 0,
            is_visible:  row.querySelector('.bi-eye-slash')    ? 0 : 1,
            config:      null,
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
    var currentOptions = [];

    function openModal(field) {
        document.getElementById('modalFieldId').value      = field.id;
        document.getElementById('modalFieldType').value    = field.field_type;
        document.getElementById('fieldModalTitle').textContent =
            'Edit Field — ' + (fieldTypeMeta[field.field_type] || {label: field.field_type}).label;
        document.getElementById('modalLabel').value        = field.label || '';
        document.getElementById('modalPlaceholder').value  = field.placeholder || '';
        document.getElementById('modalRequired').checked   = !!parseInt(field.is_required);
        document.getElementById('modalVisible').checked    =
            field.is_visible === undefined ? true : !!parseInt(field.is_visible);

        document.querySelectorAll('.field-section').forEach(function (s) { s.classList.remove('active'); });

        if (field.field_type === 'dropdown') {
            document.getElementById('sectionDropdown').classList.add('active');
            currentOptions = [];
            dropdownPills.innerHTML = '';
            fetch('/admin/workflows/ticket-fields/' + field.id + '/options', {credentials: 'same-origin'})
                .then(function (r) { return r.json(); })
                .then(function (opts) { opts.forEach(function (o) { addDropdownPill(o.label); }); });

        } else if (field.field_type === 'dependent') {
            document.getElementById('sectionDependent').classList.add('active');
            depPreview.style.display = 'none';
            depHierarchy.value = '';
            var cfg = field.config
                ? (typeof field.config === 'string' ? JSON.parse(field.config) : field.config)
                : null;
            if (cfg) {
                document.querySelector('input[name="depLevels"][value="' + (cfg.levels || 3) + '"]').checked = true;
                document.getElementById('depL1Label').value = cfg.l1_label || '';
                document.getElementById('depL2Label').value = cfg.l2_label || '';
                document.getElementById('depL3Label').value = cfg.l3_label || '';
                depL3Wrap.style.display = (cfg.levels || 3) < 3 ? 'none' : '';
            } else {
                document.getElementById('dep3').checked = true;
                document.getElementById('depL1Label').value = 'Category';
                document.getElementById('depL2Label').value = 'Subcategory';
                document.getElementById('depL3Label').value = 'Item';
                depL3Wrap.style.display = '';
            }
            fetch('/admin/workflows/ticket-fields/' + field.id + '/options', {credentials: 'same-origin'})
                .then(function (r) { return r.json(); })
                .then(function (opts) { depHierarchy.value = optionsToText(opts); });
        }

        modal.show();
    }

    function optionsToText(opts) {
        var lines = [];
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

    function addDropdownPill(label) {
        label = label.trim();
        if (!label || currentOptions.indexOf(label) !== -1) return;
        currentOptions.push(label);
        var pill = document.createElement('span');
        pill.className = 'opt-pill';
        pill.innerHTML = esc(label) + '<span class="remove-opt" data-label="' + esc(label) + '">&times;</span>';
        dropdownPills.appendChild(pill);
    }

    dropdownPills.addEventListener('click', function (e) {
        var rm = e.target.closest('.remove-opt');
        if (!rm) return;
        currentOptions = currentOptions.filter(function (o) { return o !== rm.dataset.label; });
        rm.parentElement.remove();
    });

    function addOptFromInput() {
        addDropdownPill(newOptInput.value);
        newOptInput.value = '';
    }
    addOptBtn.addEventListener('click', addOptFromInput);
    newOptInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); addOptFromInput(); }
    });

    document.querySelectorAll('input[name="depLevels"]').forEach(function (r) {
        r.addEventListener('change', function () {
            depL3Wrap.style.display = r.value === '2' ? 'none' : '';
        });
    });

    function indentLevel(line) {
        var m = line.match(/^(\t+)/);
        if (m) return m[1].length;
        var s = line.match(/^( +)/);
        if (s) return Math.floor(s[1].length / 2);
        return 0;
    }

    function parseHierarchy(text) {
        var lines = text.split('\n').map(function (l) { return l.trimEnd(); })
                        .filter(function (l) { return l.trim() !== ''; });
        var root  = [];
        var stack = [root];
        lines.forEach(function (line) {
            var depth = indentLevel(line);
            var node  = {label: line.trim(), children: []};
            if (!stack[depth]) depth = stack.length - 1;
            stack[depth].push(node);
            stack[depth + 1] = node.children;
            stack.length = depth + 2;
        });
        return root;
    }

    function buildTreeHtml(nodes) {
        if (!nodes || !nodes.length) return '';
        return '<ul>' + nodes.map(function (n) {
            return '<li>' + esc(n.label) + buildTreeHtml(n.children) + '</li>';
        }).join('') + '</ul>';
    }

    previewDepBtn.addEventListener('click', function () {
        depPreview.innerHTML = buildTreeHtml(parseHierarchy(depHierarchy.value));
        depPreview.style.display = 'block';
    });

    /* ─── Save field ─── */
    document.getElementById('saveFieldBtn').addEventListener('click', function () {
        var id          = document.getElementById('modalFieldId').value;
        var type        = document.getElementById('modalFieldType').value;
        var label       = document.getElementById('modalLabel').value.trim();
        var placeholder = document.getElementById('modalPlaceholder').value.trim();
        var isRequired  = document.getElementById('modalRequired').checked;
        var isVisible   = document.getElementById('modalVisible').checked;

        if (!label) { document.getElementById('modalLabel').focus(); return; }

        var payload = {label: label, placeholder: placeholder, is_required: isRequired, is_visible: isVisible};

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
            method:      'POST',
            credentials: 'same-origin',
            headers:     {'Content-Type': 'application/json'},
            body:        JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert(data.error || 'Error saving field.'); return; }

            var row = list.querySelector('[data-field-id="' + id + '"]');
            if (row) {
                row.querySelector('.field-row-label').textContent = label;
                var reqBadge = row.querySelector('.badge.bg-danger');
                if (isRequired && !reqBadge) {
                    var b = document.createElement('span');
                    b.className = 'badge bg-danger'; b.style.fontSize = '.65rem'; b.textContent = 'Required';
                    row.querySelector('.field-row-label').after(b);
                } else if (!isRequired && reqBadge) { reqBadge.remove(); }
                var eyeIcon = row.querySelector('.bi-eye-slash');
                if (!isVisible && !eyeIcon) {
                    var i = document.createElement('i');
                    i.className = 'bi bi-eye-slash text-muted'; i.title = 'Hidden from portal users';
                    row.querySelector('.edit-field-btn').before(i);
                } else if (isVisible && eyeIcon) { eyeIcon.remove(); }
            }
            modal.hide();
        });
    });

    updateCount();
});
</script>
