<?php
$sidebarItems = adminSidebar('workflows');
$layout       = 'app';

$fieldTypeMeta = [
    'text'       => ['label' => 'Text',            'icon' => 'bi-input-cursor-text'],
    'textarea'   => ['label' => 'Multi-line Text', 'icon' => 'bi-textarea-t'],
    'checkbox'   => ['label' => 'Checkbox',        'icon' => 'bi-check2-square'],
    'dropdown'   => ['label' => 'Dropdown',        'icon' => 'bi-chevron-expand'],
    'date'       => ['label' => 'Date',            'icon' => 'bi-calendar'],
    'number'     => ['label' => 'Number',          'icon' => 'bi-123'],
    'decimal'    => ['label' => 'Decimal',         'icon' => 'bi-0-circle'],
    'dependent'  => ['label' => 'Dependent',       'icon' => 'bi-diagram-3'],
    'text_block' => ['label' => 'Text Block',      'icon' => 'bi-text-paragraph'],
    'image'      => ['label' => 'Image',           'icon' => 'bi-image'],
    'cc'         => ['label' => 'CC',              'icon' => 'bi-people'],
    'date_range' => ['label' => 'Date Range',      'icon' => 'bi-calendar-range'],
];

$systemFieldMeta = [
    'subject'     => ['label' => 'Subject',     'icon' => 'bi-input-cursor-text'],
    'description' => ['label' => 'Description', 'icon' => 'bi-textarea-t'],
    'ticket_type' => ['label' => 'Ticket Type', 'icon' => 'bi-tag'],
    'location'    => ['label' => 'Location',    'icon' => 'bi-geo-alt'],
    'priority'    => ['label' => 'Priority',    'icon' => 'bi-flag'],
    'tags'        => ['label' => 'Tags',        'icon' => 'bi-hash'],
    'attachments' => ['label' => 'Attachments', 'icon' => 'bi-paperclip'],
];

$sysDefaults = systemFieldDefaults();
?>
<style>
    /* ── Layout: left rail + canvas + preview ── */
    .builder-shell {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 1rem;
        align-items: flex-start;
    }
    .builder-shell.preview-open {
        grid-template-columns: 260px minmax(0, 1fr) 440px;
    }
    @media (max-width: 991px) {
        .builder-shell, .builder-shell.preview-open {
            grid-template-columns: 1fr;
        }
    }

    /* Type rail */
    .type-rail {
        position: sticky;
        top: calc(var(--ld-navbar-height, 56px) + 1rem);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .55rem;
        max-height: calc(100vh - var(--ld-navbar-height, 56px) - 2rem);
        overflow-y: auto;
    }
    .type-rail-header {
        padding: .7rem .85rem;
        border-bottom: 1px solid #e2e8f0;
        font-size: .78rem; font-weight: 700;
        letter-spacing: .06em; text-transform: uppercase;
        color: #64748b;
        background: linear-gradient(180deg, #fafbff 0%, #f5f7ff 100%);
        border-radius: .55rem .55rem 0 0;
    }
    .type-rail-list { padding: .35rem; }
    .type-rail-item {
        display: flex; align-items: center; gap: .5rem;
        padding: .55rem .65rem;
        border-radius: .4rem;
        font-size: .9rem; color: #334155;
        text-decoration: none;
        cursor: pointer;
        margin-bottom: .15rem;
        transition: all .12s ease;
        border: 1px solid transparent;
    }
    .type-rail-item:hover { background: #f1f5f9; color: #1e1b4b; }
    .type-rail-item.active {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #1e1b4b;
        font-weight: 600;
    }
    .type-rail-item .type-color {
        width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0;
    }
    .type-rail-item .type-count {
        margin-left: auto;
        font-size: .72rem; font-weight: 500;
        background: #e2e8f0; color: #475569;
        padding: .1rem .4rem; border-radius: 999px;
    }
    .type-rail-item.active .type-count { background: #c7d2fe; color: #3730a3; }

    /* Canvas */
    .form-canvas {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .55rem;
        min-height: 400px;
    }
    .canvas-header {
        padding: .85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex; align-items: center; gap: .65rem;
        background: linear-gradient(180deg, #fafbff 0%, #f5f7ff 100%);
        border-radius: .55rem .55rem 0 0;
    }
    .canvas-header .type-badge {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .3rem .65rem;
        border-radius: 999px;
        font-size: .78rem; font-weight: 600;
        background: #eef2ff; color: #4338ca;
        border: 1px solid #c7d2fe;
    }
    .canvas-header .type-badge .type-color {
        width: 10px; height: 10px; border-radius: 2px;
    }
    .canvas-body { padding: 1rem; }

    /* Field rows */
    .field-row {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .5rem;
        padding: .65rem .85rem;
        margin-bottom: .45rem;
        display: flex; align-items: center; gap: .65rem;
    }
    .field-row.is-system {
        background: #f8fafc;
    }
    .field-row.is-hidden {
        opacity: .55;
        background: repeating-linear-gradient(
            45deg, #f8fafc, #f8fafc 10px, #f1f5f9 10px, #f1f5f9 20px
        );
    }
    .field-row.is-locked {
        background: #f1f5f9;
        border-style: dashed;
    }
    .field-row .drag-handle {
        cursor: grab; color: #94a3b8; font-size: 1.05rem; flex-shrink: 0;
    }
    .field-row .drag-handle:active { cursor: grabbing; }
    .field-row.is-locked .drag-handle { visibility: hidden; }
    .field-row .field-icon { color: #64748b; flex-shrink: 0; }
    .field-row .field-name { flex: 1; font-size: .92rem; font-weight: 500; }
    .field-row .field-name small { color: #64748b; font-weight: 400; }
    .field-row .badge-kind {
        font-size: .65rem; font-weight: 500;
        padding: .15rem .45rem; border-radius: .3rem;
        background: #e2e8f0; color: #475569;
    }
    .field-row.is-system .badge-kind { background: #dbeafe; color: #1e40af; }

    /* Visibility pill (clickable) */
    .vis-pill {
        font-size: .7rem; font-weight: 600;
        padding: .2rem .55rem; border-radius: 999px;
        cursor: pointer; user-select: none;
        border: 1px solid transparent;
        display: inline-flex; align-items: center; gap: .25rem;
        transition: all .12s ease;
    }
    .vis-pill[data-vis="required"] { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    .vis-pill[data-vis="optional"] { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; }
    .vis-pill[data-vis="hidden"]   { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
    .vis-pill:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(15,23,42,.08); }
    .vis-pill.is-locked { cursor: not-allowed; opacity: .6; }

    /* Action buttons in row */
    .field-row .row-action {
        background: none; border: 1px solid transparent;
        color: #64748b; padding: .15rem .4rem;
        border-radius: .3rem; cursor: pointer; font-size: .9rem;
    }
    .field-row .row-action:hover { background: #f1f5f9; color: #1e1b4b; border-color: #e2e8f0; }
    .field-row .row-action.danger:hover { background: #fee2e2; color: #991b1b; border-color: #fecaca; }

    /* Section separators */
    .section-label {
        font-size: .72rem; font-weight: 700;
        letter-spacing: .08em; text-transform: uppercase;
        color: #64748b;
        padding: .9rem 0 .35rem;
    }

    /* Add-field cards */
    .add-field-actions {
        display: flex; gap: .55rem; flex-wrap: wrap;
        padding: 1rem;
        background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: .5rem;
        margin-top: 1rem;
    }
    .add-field-actions .btn-add {
        background: #fff;
    }

    /* Empty state */
    .canvas-empty {
        padding: 3rem 1rem; text-align: center; color: #64748b;
    }
    .canvas-empty .bi { font-size: 2.5rem; color: #cbd5e1; display: block; margin-bottom: .55rem; }

    /* Preview pane */
    .preview-pane {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .55rem;
        position: sticky;
        top: calc(var(--ld-navbar-height, 56px) + 1rem);
        max-height: calc(100vh - var(--ld-navbar-height, 56px) - 2rem);
        display: none;
        flex-direction: column;
        overflow: hidden;
    }
    .builder-shell.preview-open .preview-pane { display: flex; }
    .preview-head {
        padding: .65rem .85rem;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fafbff 0%, #f5f7ff 100%);
        display: flex; align-items: center; gap: .55rem;
        font-size: .85rem; font-weight: 600; color: #1e1b4b;
    }
    .preview-head .ph-actions { margin-left: auto; display: flex; gap: .25rem; }
    .preview-head .ph-btn {
        background: none; border: 1px solid transparent;
        color: #475569; padding: .15rem .4rem;
        border-radius: .35rem; cursor: pointer; font-size: .85rem; line-height: 1;
    }
    .preview-head .ph-btn:hover { background: #fff; border-color: #e2e8f0; }
    .preview-pane iframe { flex: 1; border: 0; width: 100%; min-height: 540px; background: #fff; }

    @media (max-width: 991px) {
        .preview-pane { position: static; max-height: none; }
        .preview-pane iframe { min-height: 720px; }
    }

    /* Dark mode */
    [data-bs-theme="dark"] .type-rail { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .type-rail-header { background: linear-gradient(180deg, #1a1d21 0%, #15171a 100%); border-bottom-color: #373b3e; color: #cbd5e1; }
    [data-bs-theme="dark"] .type-rail-item { color: #cbd5e1; }
    [data-bs-theme="dark"] .type-rail-item:hover { background: var(--bs-tertiary-bg); color: #e0e7ff; }
    [data-bs-theme="dark"] .type-rail-item.active { background: #1e1b4b; border-color: #4338ca; color: #c7d2fe; }
    [data-bs-theme="dark"] .form-canvas { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .canvas-header { background: linear-gradient(180deg, #1a1d21 0%, #15171a 100%); border-bottom-color: #373b3e; }
    [data-bs-theme="dark"] .field-row { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .field-row.is-system { background: var(--bs-tertiary-bg); }
    [data-bs-theme="dark"] .field-row.is-locked { background: var(--bs-tertiary-bg); }
    [data-bs-theme="dark"] .field-row.is-hidden {
        background: repeating-linear-gradient(45deg, var(--bs-tertiary-bg), var(--bs-tertiary-bg) 10px, var(--bs-secondary-bg) 10px, var(--bs-secondary-bg) 20px);
    }
    [data-bs-theme="dark"] .add-field-actions { background: var(--bs-tertiary-bg); border-color: #495057; }
    [data-bs-theme="dark"] .preview-pane { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .preview-head { background: linear-gradient(180deg, #1a1d21 0%, #15171a 100%); border-bottom-color: #373b3e; color: #e0e7ff; }

    /* Dropdown option pills (inside the field-edit modal) */
    .opt-pill {
        display: inline-flex; align-items: center; gap: .3rem;
        background: #f1f5f9; border: 1px solid #e2e8f0;
        border-radius: 1rem; padding: .2rem .6rem;
        font-size: .8rem; margin: .15rem;
    }
    .opt-pill .remove-opt { cursor: pointer; color: #94a3b8; line-height: 1; padding: 0 2px; }
    .opt-pill .remove-opt:hover { color: #ef4444; }
    .dep-tree { font-size: .825rem; }
    .dep-tree ul { list-style: none; padding-left: 1.2rem; }
    .dep-tree > ul { padding-left: 0; }
    .dep-tree li { padding: .1rem 0; }
    .dep-tree li::before { content: "▸ "; color: var(--ld-primary); font-size: .7rem; }
    #fieldModal .modal-body .field-section { display: none; }
    #fieldModal .modal-body .field-section.active { display: block; }
</style>

<?php if (empty($ticketTypes)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    No ticket types yet. <a href="/admin/types/create" class="alert-link">Create a ticket type</a> first — each type gets its own form layout.
</div>
<?php else: ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Form Builder</h4>
        <p class="text-muted mb-0 small">Pick a ticket type on the left, then drag fields and toggle visibility on the right.</p>
    </div>
    <button class="btn btn-outline-primary" type="button" id="togglePreviewBtn" aria-pressed="false">
        <i class="bi bi-eye me-1"></i>Live Preview
    </button>
</div>

<div class="builder-shell" id="builderShell">

    <!-- Type rail (left) -->
    <aside class="type-rail" aria-label="Ticket types">
        <div class="type-rail-header"><i class="bi bi-list me-1"></i>Ticket Types</div>
        <div class="type-rail-list">
            <?php foreach ($ticketTypes as $tt):
                $tid = (int) $tt['id'];
                $isActive = $selectedType && (int) $selectedType['id'] === $tid;
            ?>
            <a class="type-rail-item <?= $isActive ? 'active' : '' ?>"
               href="/admin/workflows/ticket-fields?type=<?= $tid ?>">
                <span class="type-color" style="background:<?= e($tt['color'] ?? '#6c757d') ?>;"></span>
                <span><?= e($tt['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- Canvas (middle) -->
    <main class="form-canvas">
        <?php if (!$selectedType): ?>
        <div class="canvas-empty">
            <i class="bi bi-tag"></i>
            <p>Select a ticket type on the left to edit its form.</p>
        </div>
        <?php else: ?>
        <div class="canvas-header">
            <span class="type-badge">
                <span class="type-color" style="background:<?= e($selectedType['color'] ?? '#6c757d') ?>;"></span>
                <?= e($selectedType['name']) ?>
            </span>
            <span class="text-muted small">— this is the form requesters see when they pick this type.</span>
            <a href="/admin/types/<?= (int) $selectedType['id'] ?>/edit"
               class="btn btn-sm btn-outline-secondary ms-auto"
               title="Edit other settings for this ticket type">
                <i class="bi bi-gear me-1"></i>Type settings
            </a>
        </div>
        <div class="canvas-body">

            <!-- Pinned (subject + description) -->
            <div class="section-label">
                <i class="bi bi-pin-angle me-1"></i>Pinned (always first, can't be reordered)
            </div>
            <?php foreach (['subject', 'description'] as $pinKey):
                $row = null;
                foreach ($layout_ as $r) { if ($r['kind'] === 'system' && $r['key'] === $pinKey) { $row = $r; break; } }
                if (!$row) continue;
                $meta = $systemFieldMeta[$pinKey];
            ?>
            <div class="field-row is-system is-locked"
                 data-row-kind="system" data-row-key="<?= e($pinKey) ?>">
                <i class="bi bi-grip-vertical drag-handle"></i>
                <i class="bi <?= e($meta['icon']) ?> field-icon"></i>
                <span class="field-name"><?= e($row['label']) ?> <small>· system</small></span>
                <span class="badge-kind">System</span>
                <span class="vis-pill is-locked" data-vis="required" title="Always required">Required</span>
                <button class="row-action edit-row-btn" type="button" title="Rename">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <?php endforeach; ?>

            <!-- Form body (reorderable, droppable) -->
            <div class="section-label">
                <i class="bi bi-arrows-move me-1"></i>Form fields
                <span class="text-muted fw-normal">— drag <i class="bi bi-grip-vertical"></i> to reorder, click the pill to change required/optional/hidden</span>
            </div>
            <div id="formCanvasList">
                <?php
                $bodyRows = array_filter($layout_, fn($r) => !($r['kind'] === 'system' && in_array($r['key'], ['subject', 'description'], true)));
                foreach ($bodyRows as $row):
                    $kind = $row['kind'];
                    $key  = $row['key'];
                    $vis  = $row['visibility'];
                    if ($kind === 'system') {
                        $meta = $systemFieldMeta[$key] ?? ['label' => $key, 'icon' => 'bi-question'];
                        $lockedVis = $sysDefaults[$key]['lockedVisibility'] ?? false;
                        $kindBadge = 'System';
                        $kindLabel = $meta['label'];
                        $icon = $meta['icon'];
                        $typeName = '';
                    } else {
                        $field = $row['field'] ?? null;
                        if (!$field) continue;
                        $meta = $fieldTypeMeta[$field['field_type']] ?? ['label' => $field['field_type'], 'icon' => 'bi-question'];
                        $lockedVis = false;
                        $kindBadge = $meta['label'];
                        $kindLabel = $row['label'];
                        $icon = $meta['icon'];
                        $typeName = '';
                    }
                    $rowClass = 'field-row';
                    if ($kind === 'system') $rowClass .= ' is-system';
                    if ($vis === 'hidden')  $rowClass .= ' is-hidden';
                ?>
                <div class="<?= $rowClass ?>"
                     data-row-kind="<?= e($kind) ?>"
                     data-row-key="<?= e((string) $key) ?>"
                     <?= $kind === 'custom' ? 'data-field-id="' . (int) $row['field']['id'] . '"' : '' ?>>
                    <i class="bi bi-grip-vertical drag-handle"></i>
                    <i class="bi <?= e($icon) ?> field-icon"></i>
                    <span class="field-name">
                        <span class="row-label-text"><?= e($kindLabel) ?></span>
                        <small>· <?= e(strtolower($kindBadge)) ?></small>
                    </span>
                    <span class="badge-kind"><?= e($kindBadge) ?></span>
                    <span class="vis-pill <?= $lockedVis ? 'is-locked' : '' ?>"
                          data-vis="<?= e($vis) ?>"
                          <?= $lockedVis ? 'title="This field can\'t be hidden or made optional."' : 'title="Click to cycle Required → Optional → Hidden"' ?>>
                        <?= ucfirst($vis) ?>
                    </span>
                    <button class="row-action edit-row-btn" type="button" title="Rename / edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php if (!$lockedVis): ?>
                    <button class="row-action danger remove-row-btn" type="button"
                            title="<?= $kind === 'custom' ? 'Remove from this type (definition is preserved)' : 'Remove from this form' ?>">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Add fields -->
            <div class="add-field-actions">
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle btn-add" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-plus-lg me-1"></i>Add field
                    </button>
                    <ul class="dropdown-menu shadow-sm">
                        <?php foreach ($fieldTypeMeta as $type => $meta): ?>
                        <li>
                            <button class="dropdown-item add-field-btn" type="button" data-type="<?= e($type) ?>">
                                <i class="bi <?= e($meta['icon']) ?> me-2 text-primary"></i><?= e($meta['label']) ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (!empty($unusedFields)): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle btn-add" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-arrow-left-right me-1"></i>Add existing field
                    </button>
                    <ul class="dropdown-menu shadow-sm" style="max-height:320px;overflow-y:auto;">
                        <?php foreach ($unusedFields as $uf):
                            $meta = $fieldTypeMeta[$uf['field_type']] ?? ['label' => $uf['field_type'], 'icon' => 'bi-question'];
                        ?>
                        <li>
                            <button class="dropdown-item add-existing-btn" type="button" data-field-id="<?= (int) $uf['id'] ?>">
                                <i class="bi <?= e($meta['icon']) ?> me-2 text-primary"></i><?= e($uf['label']) ?>
                                <small class="text-muted ms-1">· <?= e($meta['label']) ?></small>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Live preview (right, toggled) -->
    <aside class="preview-pane" id="previewPane" aria-hidden="true">
        <div class="preview-head">
            <i class="bi bi-eye-fill text-primary"></i>
            <span>Live Preview</span>
            <div class="ph-actions">
                <button class="ph-btn" id="previewReloadBtn" title="Reload"><i class="bi bi-arrow-clockwise"></i></button>
                <button class="ph-btn" id="previewOpenBtn"   title="Open in new tab"><i class="bi bi-box-arrow-up-right"></i></button>
                <button class="ph-btn" id="previewCloseBtn"  title="Close"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        <?php if ($selectedType): ?>
        <iframe id="previewFrame"
                title="Form preview"
                src="/portal/tickets/create?embed=1&amp;type_id=<?= (int) $selectedType['id'] ?>"
                sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>
        <?php else: ?>
        <div class="canvas-empty p-3"><small>No type selected.</small></div>
        <?php endif; ?>
    </aside>
</div>

<!-- ── Field edit modal ── -->
<div class="modal fade" id="fieldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fieldModalTitle">Edit Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalRowKind">
                <input type="hidden" id="modalRowKey">
                <input type="hidden" id="modalFieldType">
                <input type="hidden" id="modalFieldId">

                <div class="mb-3">
                    <label class="form-label fw-medium">
                        Label on this type's form <span class="text-muted small">— override the default label</span>
                    </label>
                    <input type="text" class="form-control" id="modalLabelOverride" placeholder="(uses default)" maxlength="255">
                    <div class="form-text" id="modalLabelHint">Leave blank to use the default label.</div>
                </div>

                <div class="mb-3" id="modalPlaceholderWrap">
                    <label class="form-label fw-medium">Placeholder</label>
                    <input type="text" class="form-control" id="modalPlaceholder" placeholder="Optional hint text">
                </div>

                <!-- Share-with-types (only for custom fields) -->
                <div class="mb-3" id="modalShareWrap">
                    <label class="form-label fw-medium d-block">
                        <i class="bi bi-tag me-1 text-muted"></i>Also show this field on
                    </label>
                    <div class="border rounded p-2 bg-light" id="modalShareList">
                        <?php foreach ($ticketTypes as $tt): ?>
                        <div class="form-check d-inline-block me-3">
                            <input class="form-check-input modal-share-cb" type="checkbox"
                                   id="modalShare_<?= (int) $tt['id'] ?>" value="<?= (int) $tt['id'] ?>">
                            <label class="form-check-label" for="modalShare_<?= (int) $tt['id'] ?>"><?= e($tt['name']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">Unchecking removes this field from that type's form (preserves the definition and saved values).</div>
                </div>

                <hr id="modalHr">

                <!-- Dropdown options -->
                <div id="sectionDropdown" class="field-section">
                    <label class="form-label fw-medium">Options</label>
                    <div id="dropdownPills" class="mb-2" style="min-height:2rem;"></div>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm" id="newOptInput" placeholder="Type an option and press Enter">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addOptBtn">Add</button>
                    </div>
                </div>

                <!-- Dependent options -->
                <div id="sectionDependent" class="field-section">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Level 1 Label</label>
                            <input type="text" class="form-control form-control-sm" id="depL1Label" placeholder="e.g. Category">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Level 2 Label</label>
                            <input type="text" class="form-control form-control-sm" id="depL2Label" placeholder="e.g. Subcategory">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Level 3 Label</label>
                            <input type="text" class="form-control form-control-sm" id="depL3Label" placeholder="e.g. Item">
                        </div>
                    </div>
                    <label class="form-label fw-medium">Hierarchy</label>
                    <p class="text-muted small mb-1">No indent = Level 1; one tab (or 2 spaces) = Level 2; two tabs = Level 3.</p>
                    <textarea class="form-control font-monospace" id="depHierarchy" rows="8"
                              placeholder="Category A&#10;&#9;Subcategory 1&#10;&#9;&#9;Item X"></textarea>
                </div>

                <!-- Text block -->
                <div id="sectionTextBlock" class="field-section">
                    <label class="form-label fw-medium">Content</label>
                    <textarea class="form-control" id="modalTextBlockContent" rows="6"
                              placeholder="Enter text, instructions, or notes to display on the form..."></textarea>
                </div>

                <!-- Image -->
                <div id="sectionImage" class="field-section">
                    <div id="imageCurrentWrap" class="mb-3" style="display:none;">
                        <label class="form-label fw-medium">Current Image</label><br>
                        <img id="imageCurrentPreview" src="" alt="" class="img-thumbnail" style="max-height:160px;">
                    </div>
                    <label class="form-label fw-medium">Upload Image</label>
                    <input type="file" class="form-control" id="modalImageFile" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">JPEG, PNG, GIF, or WebP. Max 5 MB.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="deleteFieldFromAllBtn" style="display:none;">
                    <i class="bi bi-trash me-1"></i>Delete field entirely
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFieldBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Remove confirm modal ── -->
<div class="modal fade" id="removeRowModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-lg me-2 text-danger"></i>Remove field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Remove <strong id="removeRowName">this field</strong> from <strong><?= $selectedType ? e($selectedType['name']) : '' ?></strong>'s form?</p>
                <p class="text-muted small mb-0" id="removeRowNote">The field definition stays available — you can re-add it later from <em>Add existing field</em>. Tickets that already use it keep their saved values.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="removeRowConfirmBtn">Remove</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// ── Preview toggle: bound first, outside the main IIFE, so it works even if
//    the builder IIFE bails on a later error (Sortable load, etc.). ──
(function() {
    var shell      = document.getElementById('builderShell');
    var previewBtn = document.getElementById('togglePreviewBtn');
    var iframe     = document.getElementById('previewFrame');
    if (!shell || !previewBtn) return;

    function setOpen(open) {
        shell.classList.toggle('preview-open', open);
        previewBtn.classList.toggle('active', open);
        previewBtn.setAttribute('aria-pressed', open ? 'true' : 'false');
        try { localStorage.setItem('formBuilder_previewOpen', open ? '1' : '0'); } catch (e) {}
    }
    function reload() {
        if (!iframe) return;
        var s = iframe.getAttribute('src');
        if (s) iframe.setAttribute('src', s); // force reload
    }
    var savedOpen = false;
    try { savedOpen = localStorage.getItem('formBuilder_previewOpen') === '1'; } catch (e) {}
    if (savedOpen) setOpen(true);
    previewBtn.addEventListener('click', function() {
        setOpen(!shell.classList.contains('preview-open'));
    });
    var rBtn = document.getElementById('previewReloadBtn');
    if (rBtn) rBtn.addEventListener('click', reload);
    var cBtn = document.getElementById('previewCloseBtn');
    if (cBtn) cBtn.addEventListener('click', function() { setOpen(false); });
    window.__formBuilderReloadPreview = reload;
})();

(function() {
    var typeId = <?= $selectedType ? (int) $selectedType['id'] : 'null' ?>;
    if (!typeId) return;

    var canvas = document.getElementById('formCanvasList');
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var fieldTypeMeta = <?= json_encode($fieldTypeMeta) ?>;

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(body || {})
        }).then(function(r) { return r.json(); });
    }

    /* ── Drag-and-drop reorder ──
       Guarded: if the Sortable CDN script fails to load (restricted network,
       CDN outage), window.Sortable is undefined. Calling it unguarded would
       throw and abort the rest of this IIFE — killing the edit/remove/add/
       visibility handlers below. Degrade gracefully: skip reordering, keep
       everything else working. (Same pattern as partials/sortable-list.php.) */
    if (window.Sortable) {
        Sortable.create(canvas, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function() {
                var order = Array.from(canvas.querySelectorAll('.field-row')).map(function(row) {
                    return { kind: row.dataset.rowKind, key: row.dataset.rowKey };
                });
                postJson('/admin/forms/' + typeId + '/layout/save', { order: order })
                    .then(reloadPreview);
            }
        });
    } else {
        console.warn('Sortable failed to load — drag-to-reorder disabled, other form-builder actions still work.');
    }

    /* ── Visibility pill: click to cycle required → optional → hidden ── */
    canvas.addEventListener('click', function(e) {
        var pill = e.target.closest('.vis-pill');
        if (!pill || pill.classList.contains('is-locked')) return;
        var row = pill.closest('.field-row');
        var current = pill.dataset.vis;
        var next = current === 'required' ? 'optional' : (current === 'optional' ? 'hidden' : 'required');

        postJson('/admin/forms/' + typeId + '/layout/visibility', {
            kind: row.dataset.rowKind,
            key:  row.dataset.rowKey,
            visibility: next,
        }).then(function(r) {
            if (!r.success) { alert(r.error || 'Could not update visibility'); return; }
            pill.dataset.vis = next;
            pill.textContent = next.charAt(0).toUpperCase() + next.slice(1);
            row.classList.toggle('is-hidden', next === 'hidden');
            reloadPreview();
        });
    });

    /* ── Add new field ── */
    document.querySelectorAll('.add-field-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = btn.dataset.type;
            var defaultLabel = fieldTypeMeta[type] ? fieldTypeMeta[type].label : 'New Field';
            postJson('/admin/forms/' + typeId + '/field/create', { field_type: type, label: defaultLabel })
                .then(function(r) { if (r.success) location.reload(); else alert(r.error || 'Could not add field'); });
        });
    });

    /* ── Add existing field ── */
    document.querySelectorAll('.add-existing-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            postJson('/admin/forms/' + typeId + '/layout/add-existing', { field_id: parseInt(btn.dataset.fieldId) })
                .then(function(r) { if (r.success) location.reload(); else alert(r.error || 'Could not add field'); });
        });
    });

    /* ── Remove row ── */
    var pendingRemove = null;
    var removeModal = new bootstrap.Modal(document.getElementById('removeRowModal'));
    canvas.addEventListener('click', function(e) {
        var btn = e.target.closest('.remove-row-btn');
        if (!btn) return;
        var row = btn.closest('.field-row');
        pendingRemove = { kind: row.dataset.rowKind, key: row.dataset.rowKey, row: row };
        document.getElementById('removeRowName').textContent = row.querySelector('.row-label-text').textContent;
        removeModal.show();
    });
    document.getElementById('removeRowConfirmBtn').addEventListener('click', function() {
        if (!pendingRemove) return;
        postJson('/admin/forms/' + typeId + '/layout/remove', {
            kind: pendingRemove.kind, key: pendingRemove.key,
        }).then(function(r) {
            if (r.success) { pendingRemove.row.remove(); removeModal.hide(); reloadPreview(); }
            else alert(r.error || 'Could not remove');
        });
    });

    /* ── Edit row (rename + per-type label override, plus field definition for custom) ── */
    var fieldModal = new bootstrap.Modal(document.getElementById('fieldModal'));
    function openEditModal(row) {
        var kind = row.dataset.rowKind;
        var key  = row.dataset.rowKey;
        document.getElementById('modalRowKind').value = kind;
        document.getElementById('modalRowKey').value  = key;
        document.getElementById('modalLabelOverride').value = '';
        document.getElementById('modalPlaceholder').value = '';
        document.querySelectorAll('.modal-share-cb').forEach(function(cb) { cb.checked = false; });
        document.querySelectorAll('#fieldModal .field-section').forEach(function(s) { s.classList.remove('active'); });
        document.getElementById('deleteFieldFromAllBtn').style.display = 'none';

        var labelHint = document.getElementById('modalLabelHint');

        if (kind === 'system') {
            document.getElementById('fieldModalTitle').textContent = 'Rename system field on ' + <?= json_encode($selectedType['name'] ?? '') ?>;
            document.getElementById('modalPlaceholderWrap').style.display = 'none';
            document.getElementById('modalShareWrap').style.display = 'none';
            document.getElementById('modalHr').style.display = 'none';
            labelHint.textContent = 'Affects only ' + <?= json_encode($selectedType['name'] ?? '') ?> + '. Leave blank to use the system default label.';
            // Prefill current label override (empty if none)
            var current = row.querySelector('.row-label-text').textContent.trim();
            document.getElementById('modalLabelOverride').value = current;
            fieldModal.show();
            return;
        }

        // Custom field — fetch its definition + share-with-types
        var fieldId = row.dataset.fieldId;
        document.getElementById('modalFieldId').value = fieldId;
        document.getElementById('modalPlaceholderWrap').style.display = '';
        document.getElementById('modalShareWrap').style.display = '';
        document.getElementById('modalHr').style.display = '';
        document.getElementById('deleteFieldFromAllBtn').style.display = '';
        labelHint.textContent = 'Renames the field on every type. Use the per-type override on the row to rename only here.';
        document.getElementById('fieldModalTitle').textContent = 'Edit field';

        fetch('/admin/forms/field/' + fieldId + '/details', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.field) return;
                document.getElementById('modalFieldType').value = data.field.field_type;
                document.getElementById('modalLabelOverride').value = data.field.label || '';
                document.getElementById('modalPlaceholder').value   = data.field.placeholder || '';
                (data.type_ids || []).forEach(function(tid) {
                    var cb = document.querySelector('.modal-share-cb[value="' + tid + '"]');
                    if (cb) cb.checked = true;
                });

                var fieldType = data.field.field_type;
                if (fieldType === 'dropdown')      document.getElementById('sectionDropdown').classList.add('active');
                if (fieldType === 'dependent')     document.getElementById('sectionDependent').classList.add('active');
                if (fieldType === 'text_block')    document.getElementById('sectionTextBlock').classList.add('active');
                if (fieldType === 'image')         document.getElementById('sectionImage').classList.add('active');

                if (fieldType === 'dropdown') {
                    populateDropdownPills((data.options || []).filter(function(o) { return !o.parent_option_id; }));
                }
                if (fieldType === 'dependent') {
                    var cfg = {};
                    try { cfg = JSON.parse(data.field.config || '{}') || {}; } catch (e) {}
                    document.getElementById('depL1Label').value = cfg.l1_label || '';
                    document.getElementById('depL2Label').value = cfg.l2_label || '';
                    document.getElementById('depL3Label').value = cfg.l3_label || '';
                    document.getElementById('depHierarchy').value = depTreeToText(data.options || []);
                }
                if (fieldType === 'text_block') {
                    var tbCfg = {};
                    try { tbCfg = JSON.parse(data.field.config || '{}') || {}; } catch (e) {}
                    document.getElementById('modalTextBlockContent').value = tbCfg.content || '';
                }
                if (fieldType === 'image') {
                    var imgCfg = {};
                    try { imgCfg = JSON.parse(data.field.config || '{}') || {}; } catch (e) {}
                    if (imgCfg.image_path) {
                        document.getElementById('imageCurrentPreview').src = '/uploads/field-images/' + imgCfg.image_path;
                        document.getElementById('imageCurrentWrap').style.display = '';
                    } else {
                        document.getElementById('imageCurrentWrap').style.display = 'none';
                    }
                }
                fieldModal.show();
            });
    }
    canvas.addEventListener('click', function(e) {
        var btn = e.target.closest('.edit-row-btn');
        if (!btn) return;
        openEditModal(btn.closest('.field-row'));
    });

    /* Pinned-row edit (subject/description rename) */
    document.querySelectorAll('.field-row.is-locked .edit-row-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { openEditModal(btn.closest('.field-row')); });
    });

    /* Dropdown pill helpers */
    function populateDropdownPills(opts) {
        var pills = document.getElementById('dropdownPills');
        pills.innerHTML = '';
        opts.forEach(function(o) { addDropdownPill(o.label); });
    }
    function addDropdownPill(label) {
        var pills = document.getElementById('dropdownPills');
        var el = document.createElement('span');
        el.className = 'opt-pill';
        el.dataset.label = label;
        el.innerHTML = '<span class="opt-label"></span><span class="remove-opt">&times;</span>';
        el.querySelector('.opt-label').textContent = label;
        el.querySelector('.remove-opt').addEventListener('click', function() { el.remove(); });
        pills.appendChild(el);
    }
    document.getElementById('addOptBtn').addEventListener('click', function() {
        var input = document.getElementById('newOptInput');
        var val = input.value.trim();
        if (val) { addDropdownPill(val); input.value = ''; }
    });
    document.getElementById('newOptInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('addOptBtn').click(); }
    });

    /* Dep-tree parsing */
    function parseDepTree(text) {
        var lines = text.split(/\r?\n/);
        var l1 = [];
        var lastL1 = null;
        var lastL2 = null;
        lines.forEach(function(line) {
            if (!line.trim()) return;
            var depth = 0;
            if (/^\t\t|^ {4}/.test(line)) depth = 2;
            else if (/^\t| {2}/.test(line)) depth = 1;
            var label = line.replace(/^[\t ]+/, '').trim();
            if (!label) return;
            if (depth === 0) {
                lastL1 = { label: label, children: [] };
                l1.push(lastL1);
                lastL2 = null;
            } else if (depth === 1 && lastL1) {
                lastL2 = { label: label, children: [] };
                lastL1.children.push(lastL2);
            } else if (depth === 2 && lastL2) {
                lastL2.children.push({ label: label });
            }
        });
        return l1;
    }
    function depTreeToText(options) {
        var byParent = {};
        options.forEach(function(o) {
            var p = o.parent_option_id || 0;
            (byParent[p] = byParent[p] || []).push(o);
        });
        function walk(parentId, depth) {
            var out = '';
            (byParent[parentId] || []).forEach(function(o) {
                out += '\t'.repeat(depth) + o.label + '\n';
                out += walk(o.id, depth + 1);
            });
            return out;
        }
        return walk(0, 0).replace(/\n$/, '');
    }

    /* Save (system: label-override only; custom: full update) */
    document.getElementById('saveFieldBtn').addEventListener('click', function() {
        var kind = document.getElementById('modalRowKind').value;
        var key  = document.getElementById('modalRowKey').value;
        var labelOverride = document.getElementById('modalLabelOverride').value.trim();

        if (kind === 'system') {
            postJson('/admin/forms/' + typeId + '/layout/label', {
                kind: 'system', key: key, label_override: labelOverride,
            }).then(function(r) {
                if (!r.success) { alert(r.error || 'Save failed'); return; }
                location.reload();
            });
            return;
        }

        // Custom: send full definition update + share-with-types
        var fieldId = document.getElementById('modalFieldId').value;
        var fieldType = document.getElementById('modalFieldType').value;
        var placeholder = document.getElementById('modalPlaceholder').value.trim();
        var shareWith = Array.from(document.querySelectorAll('.modal-share-cb:checked'))
            .map(function(cb) { return parseInt(cb.value); });

        var body = {
            label: labelOverride,   // for custom fields, this is the field's "default" label
            placeholder: placeholder,
            share_with_types: shareWith,
        };
        if (fieldType === 'dropdown') {
            body.options = Array.from(document.querySelectorAll('#dropdownPills .opt-pill'))
                .map(function(p) { return { label: p.dataset.label }; });
        }
        if (fieldType === 'dependent') {
            body.config = {
                levels: 3,
                l1_label: document.getElementById('depL1Label').value.trim() || 'Category',
                l2_label: document.getElementById('depL2Label').value.trim() || 'Subcategory',
                l3_label: document.getElementById('depL3Label').value.trim() || 'Item',
            };
            body.options = parseDepTree(document.getElementById('depHierarchy').value);
        }
        if (fieldType === 'text_block') {
            body.config = { content: document.getElementById('modalTextBlockContent').value };
        }
        if (fieldType === 'image') {
            var fileInput = document.getElementById('modalImageFile');
            if (fileInput.files && fileInput.files[0]) {
                var fd = new FormData();
                fd.append('image', fileInput.files[0]);
                fetch('/admin/forms/field/' + fieldId + '/upload-image', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': csrfToken },
                    body: fd,
                }).then(function(r) { return r.json(); }).then(function() { afterImageUpload(fieldId, body); });
                return;
            }
        }

        postJson('/admin/forms/field/' + fieldId + '/update', body)
            .then(function(r) {
                if (!r.success) { alert(r.error || 'Save failed'); return; }
                location.reload();
            });
    });
    function afterImageUpload(fieldId, body) {
        postJson('/admin/forms/field/' + fieldId + '/update', body)
            .then(function(r) {
                if (!r.success) { alert(r.error || 'Save failed'); return; }
                location.reload();
            });
    }

    /* Delete field entirely */
    document.getElementById('deleteFieldFromAllBtn').addEventListener('click', function() {
        if (!confirm('Delete this field entirely? It will be removed from every ticket type. Saved values on existing tickets are preserved.')) return;
        var fieldId = document.getElementById('modalFieldId').value;
        postJson('/admin/forms/field/' + fieldId + '/delete', {})
            .then(function(r) {
                if (!r.success) { alert(r.error || 'Delete failed'); return; }
                location.reload();
            });
    });

    /* Live preview toggle is wired in a separate IIFE above so it works even
       if anything in this main IIFE throws. Here we only wire the "open in
       new tab" button and expose a no-op reloadPreview() used by other
       handlers below. */
    var openBtn = document.getElementById('previewOpenBtn');
    if (openBtn) {
        openBtn.addEventListener('click', function() {
            window.open('/portal/tickets/create?type_id=' + typeId, '_blank');
        });
    }
    function reloadPreview() {
        if (typeof window.__formBuilderReloadPreview === 'function') {
            window.__formBuilderReloadPreview();
        }
    }
})();
</script>
<?php endif; ?>
