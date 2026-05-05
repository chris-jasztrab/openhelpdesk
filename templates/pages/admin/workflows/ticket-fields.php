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
    'date_range' => ['label' => 'Date Range',     'icon' => 'bi-calendar-range'],
];

// Pinned fields — always at the very top, not draggable
$pinnedFields = [
    ['key' => 'subject',     'label' => $sysFs['label_subject'],     'icon' => 'bi-input-cursor-text', 'badge' => 'Required', 'editable' => true,  'has_required' => false],
    ['key' => 'description', 'label' => $sysFs['label_description'], 'icon' => 'bi-textarea-t',        'badge' => 'Required', 'editable' => true,  'has_required' => false],
];

// System fields that participate in unified ordering
$orderableSystemMeta = [
    'ticket_type' => ['label' => $sysFs['label_ticket_type'], 'icon' => 'bi-tag',       'badge' => 'Required', 'editable' => true,  'has_required' => false],
    'location'    => ['label' => label('location.singular'),   'icon' => 'bi-geo-alt',    'badge' => 'Auto',     'editable' => false, 'has_required' => false],
    'priority'    => ['label' => $sysFs['label_priority'],     'icon' => 'bi-flag',       'badge' => $sysFs['required_priority'] === '1' ? 'Required' : 'Optional', 'editable' => true, 'has_required' => true],
    'tags'        => ['label' => $sysFs['label_tags'],         'icon' => 'bi-hash',       'badge' => $sysFs['required_tags'] === '1' ? 'Required' : 'Optional',     'editable' => true, 'has_required' => true],
    'attachments' => ['label' => $sysFs['label_attachments'],  'icon' => 'bi-paperclip',  'badge' => 'Optional', 'editable' => true,  'has_required' => false],
];

// Build unified list for the form builder
$defaults = systemFieldSortDefaults();
$unifiedBuilderList = [];
foreach ($orderableSystemMeta as $key => $meta) {
    if ($key === 'tags' && getSetting('tags_enabled', '1') !== '1') continue;
    $unifiedBuilderList[] = [
        'kind'       => 'system',
        'key'        => $key,
        'sort_order' => (int) getSetting("sys_field_sort_order_{$key}", (string) $defaults[$key]),
        'meta'       => $meta,
    ];
}
foreach ($fields as $f) {
    $unifiedBuilderList[] = [
        'kind'       => 'custom',
        'key'        => (string) $f['id'],
        'sort_order' => (int) $f['sort_order'],
        'field'      => $f,
    ];
}
usort($unifiedBuilderList, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
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

    /* Pinned system rows — slightly muted, not draggable */
    .field-row.system-row {
        background: #f8fafc;
        border-style: dashed;
        opacity: .85;
    }
    .field-row.system-row .drag-handle { visibility: hidden; }

    /* Orderable system rows — draggable, distinctive background */
    .field-row.system-row-orderable {
        background: #f0f4ff;
        border: 1px solid #c7d2fe;
        border-radius: .5rem;
    }

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

    /* ── Type filter strip ── */
    .type-filter-bar {
        display: flex; align-items: center; gap: .5rem;
        padding: .65rem .85rem;
        background: linear-gradient(180deg, #fafbff 0%, #f5f7ff 100%);
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: nowrap; overflow-x: auto;
    }
    .type-filter-bar::-webkit-scrollbar { height: 6px; }
    .type-filter-bar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    .type-filter-label {
        font-size: .72rem; font-weight: 700; letter-spacing: .08em;
        text-transform: uppercase; color: #64748b;
        flex-shrink: 0; padding-right: .25rem;
    }
    .type-chip {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .3rem .7rem;
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 999px; font-size: .8rem; font-weight: 500;
        color: #475569; cursor: pointer;
        transition: all .15s ease;
        flex-shrink: 0; white-space: nowrap;
    }
    .type-chip:hover { border-color: var(--ld-primary); color: var(--ld-primary); }
    .type-chip .chip-count {
        font-size: .7rem; font-weight: 600;
        background: #f1f5f9; color: #64748b;
        padding: 0 .4rem; border-radius: 999px; min-width: 1.25rem; text-align: center;
    }
    .type-chip.active {
        background: var(--ld-primary); border-color: var(--ld-primary);
        color: #fff; box-shadow: 0 1px 3px rgba(79,70,229,.25);
    }
    .type-chip.active .chip-count { background: rgba(255,255,255,.22); color: #fff; }
    .type-chip.all-chip i { font-size: .9rem; }

    /* ── Filter-active banner inside card-body ── */
    .filter-banner {
        display: flex; align-items: center; gap: .55rem;
        padding: .55rem .8rem;
        background: #eef2ff; border: 1px solid #c7d2fe;
        border-radius: .5rem; margin-bottom: .9rem;
        font-size: .825rem; color: #3730a3;
    }
    .filter-banner .fb-strong { font-weight: 600; color: #1e1b4b; }
    .filter-banner .fb-clear {
        margin-left: auto; background: none; border: none;
        color: #4338ca; font-size: .78rem; font-weight: 500;
        cursor: pointer; padding: .15rem .4rem; border-radius: .25rem;
    }
    .filter-banner .fb-clear:hover { background: #e0e7ff; }

    /* Row scope styling under filter */
    .field-row.is-global  { border-left: 3px solid #cbd5e1; }
    .field-row.is-specific { border-left: 3px solid var(--ld-primary); }
    body.filter-active .field-row .drag-handle { opacity: .25; cursor: not-allowed; pointer-events: none; }
    body.filter-active .field-row .drag-handle::after {
        content: "↕ disabled while filtered"; display: none;
    }

    /* Scope pill in row (replaces "N types" bg-info) */
    .scope-pill {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .68rem; font-weight: 500;
        padding: .2rem .55rem; border-radius: 999px;
        line-height: 1;
    }
    .scope-pill.scope-global  { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
    .scope-pill.scope-specific { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; cursor: help; }
    .scope-pill i { font-size: .72rem; }

    /* Modal: segmented scope control */
    .scope-segment {
        display: grid; grid-template-columns: 1fr 1fr; gap: .5rem;
        background: #f1f5f9; border-radius: .55rem; padding: .25rem;
    }
    .scope-segment label {
        text-align: center; cursor: pointer; padding: .55rem .5rem;
        border-radius: .4rem; font-size: .85rem; font-weight: 500;
        color: #64748b; transition: all .15s ease; user-select: none;
        display: flex; align-items: center; justify-content: center; gap: .35rem;
    }
    .scope-segment label:hover { color: #334155; }
    .scope-segment input[type="radio"] { display: none; }
    .scope-segment input[type="radio"]:checked + span {
        background: #fff; color: var(--ld-primary);
        box-shadow: 0 1px 2px rgba(15,23,42,.06);
    }
    .scope-segment label > span {
        display: flex; align-items: center; justify-content: center; gap: .35rem;
        width: 100%; padding: .5rem; border-radius: .4rem;
        transition: all .15s ease;
    }
    .scope-types-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: .4rem .8rem; margin-top: .85rem;
        padding: .75rem; background: #f8fafc;
        border: 1px solid #e2e8f0; border-radius: .5rem;
    }
    .scope-types-grid .form-check { margin: 0; }
    #modalTypeChecksWrap[data-mode="all"] { display: none; }

    [data-bs-theme="dark"] .field-row { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .field-row.system-row { background: var(--bs-tertiary-bg); }
    [data-bs-theme="dark"] .field-row.system-row-orderable { background: #1e293b; border-color: #475569; }
    [data-bs-theme="dark"] .opt-pill { background: #2b3035; border-color: #495057; }
    [data-bs-theme="dark"] .custom-empty { border-color: #495057; }
    [data-bs-theme="dark"] .type-filter-bar { background: linear-gradient(180deg, #1a1d21 0%, #15171a 100%); border-bottom-color: #373b3e; }
    [data-bs-theme="dark"] .type-chip { background: #2b3035; border-color: #495057; color: #cbd5e1; }
    [data-bs-theme="dark"] .type-chip .chip-count { background: #1e293b; color: #94a3b8; }
    [data-bs-theme="dark"] .filter-banner { background: #1e1b4b; border-color: #4338ca; color: #c7d2fe; }
    [data-bs-theme="dark"] .filter-banner .fb-strong { color: #e0e7ff; }
    [data-bs-theme="dark"] .filter-banner .fb-clear { color: #a5b4fc; }
    [data-bs-theme="dark"] .filter-banner .fb-clear:hover { background: #312e81; }
    [data-bs-theme="dark"] .scope-pill.scope-global { background: var(--bs-tertiary-bg); border-color: #495057; color: #94a3b8; }
    [data-bs-theme="dark"] .scope-pill.scope-specific { background: #1e1b4b; border-color: #4338ca; color: #c7d2fe; }
    [data-bs-theme="dark"] .scope-segment { background: var(--bs-tertiary-bg); }
    [data-bs-theme="dark"] .scope-segment input[type="radio"]:checked + span { background: var(--bs-secondary-bg); }
    [data-bs-theme="dark"] .scope-types-grid { background: var(--bs-tertiary-bg); border-color: #495057; }

    /* ── Split layout: builder + live preview pane ── */
    .builder-wrap { display: flex; gap: 1rem; align-items: flex-start; }
    .builder-wrap > .card { flex: 1; min-width: 0; }
    .preview-pane {
        flex: 0 0 480px;
        max-width: 480px;
        position: sticky;
        top: calc(var(--ld-navbar-height, 56px) + 1rem);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: .5rem;
        box-shadow: 0 1px 3px rgba(15,23,42,.06);
        display: none;
        flex-direction: column;
        overflow: hidden;
        max-height: calc(100vh - var(--ld-navbar-height, 56px) - 2rem);
    }
    .preview-pane.is-open { display: flex; }
    .preview-pane .preview-head {
        padding: .65rem .85rem;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #fafbff 0%, #f5f7ff 100%);
        display: flex; align-items: center; gap: .55rem;
        font-size: .85rem; font-weight: 600; color: #1e1b4b;
    }
    .preview-pane .preview-head .ph-type {
        background: #eef2ff; color: #4338ca;
        padding: .15rem .55rem; border-radius: 999px;
        font-size: .72rem; border: 1px solid #c7d2fe;
    }
    .preview-pane .preview-head .ph-actions { margin-left: auto; display: flex; gap: .25rem; }
    .preview-pane .preview-head .ph-btn {
        background: none; border: 1px solid transparent;
        color: #475569; padding: .15rem .4rem;
        border-radius: .35rem; cursor: pointer;
        font-size: .85rem; line-height: 1;
    }
    .preview-pane .preview-head .ph-btn:hover { background: #fff; border-color: #e2e8f0; color: #1e1b4b; }
    .preview-pane iframe {
        flex: 1; border: 0; width: 100%; min-height: 540px;
        background: #fff;
    }
    .preview-pane .preview-loading {
        position: absolute; inset: 38px 0 0 0;
        background: rgba(255,255,255,.85);
        display: flex; align-items: center; justify-content: center;
        gap: .5rem; color: #475569; font-size: .85rem;
        pointer-events: none; opacity: 0;
        transition: opacity .15s ease;
    }
    .preview-pane.is-loading .preview-loading { opacity: 1; }

    @media (max-width: 1199px) {
        .builder-wrap { flex-direction: column; }
        .preview-pane { flex: 1 1 auto; max-width: none; width: 100%; position: static; max-height: none; }
        .preview-pane iframe { min-height: 720px; }
    }

    /* Toggle button — distinct active state */
    #togglePreviewBtn { white-space: nowrap; }
    #togglePreviewBtn.is-active {
        background: var(--ld-primary); color: #fff; border-color: var(--ld-primary);
    }
    #togglePreviewBtn.is-active:hover { background: var(--ld-primary-hover, #4338ca); }

    [data-bs-theme="dark"] .preview-pane { background: var(--bs-secondary-bg); border-color: #373b3e; }
    [data-bs-theme="dark"] .preview-pane .preview-head { background: linear-gradient(180deg, #1a1d21 0%, #15171a 100%); border-bottom-color: #373b3e; color: #e0e7ff; }
    [data-bs-theme="dark"] .preview-pane .preview-head .ph-type { background: #1e1b4b; border-color: #4338ca; color: #c7d2fe; }
    [data-bs-theme="dark"] .preview-pane .preview-head .ph-btn { color: #cbd5e1; }
    [data-bs-theme="dark"] .preview-pane .preview-head .ph-btn:hover { background: var(--bs-secondary-bg); border-color: #495057; color: #fff; }
    [data-bs-theme="dark"] .preview-pane iframe { background: #212529; }
    [data-bs-theme="dark"] .preview-pane .preview-loading { background: rgba(33,37,41,.85); color: #cbd5e1; }
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Ticket Form Builder</h4>
        <p class="text-muted mb-0 small">Manage and extend the fields shown on the New Ticket form.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <!-- Live Preview toggle -->
        <button class="btn btn-outline-primary" type="button" id="togglePreviewBtn"
                aria-pressed="false" title="Show a live preview of the portal ticket form">
            <i class="bi bi-eye me-1"></i>Live Preview
        </button>
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
</div>

<div class="builder-wrap">
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-2 px-3">
        <span class="fw-semibold small">Form Fields</span>
        <span class="text-muted small" id="fieldCountBadge">
            <?= count($fields) ?> custom field<?= count($fields) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <!-- Type filter strip — preview the form for any single ticket type -->
    <div class="type-filter-bar" id="typeFilterBar" role="tablist" aria-label="Filter form fields by ticket type">
        <span class="type-filter-label"><i class="bi bi-eye me-1"></i>Preview as</span>
        <button type="button" class="type-chip all-chip active" data-type-id="all" role="tab" aria-selected="true">
            <i class="bi bi-grid-3x3-gap"></i>All types
            <span class="chip-count" data-count-for="all"><?= count($ticketTypes) ? '—' : '0' ?></span>
        </button>
        <?php foreach ($ticketTypes as $tt): ?>
        <button type="button" class="type-chip" data-type-id="<?= (int) $tt['id'] ?>" role="tab" aria-selected="false">
            <?= e($tt['name']) ?>
            <span class="chip-count" data-count-for="<?= (int) $tt['id'] ?>">—</span>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="card-body p-3">

        <!-- Filter-active banner (shown when a specific type is selected) -->
        <div class="filter-banner" id="filterBanner" style="display:none;" role="status">
            <i class="bi bi-funnel-fill"></i>
            <span>Showing the form for <span class="fb-strong" id="filterBannerType"></span> — <span id="filterBannerCount">0</span> fields visible. Reordering applies to all forms; switch to <em>All types</em> to reorder.</span>
            <button type="button" class="fb-clear" id="filterBannerClear">
                <i class="bi bi-x-lg me-1"></i>Clear
            </button>
        </div>

        <!-- Pinned fields (Subject & Description) — not draggable -->
        <div class="custom-section-label">
            <i class="bi bi-pin-angle me-1"></i>Pinned Fields
            <span class="text-muted fw-normal">&nbsp;— always first on the form</span>
        </div>
        <?php foreach ($pinnedFields as $pf): ?>
        <div class="field-row system-row">
            <i class="bi bi-grip-vertical drag-handle"></i>
            <i class="bi <?= e($pf['icon']) ?> text-muted" style="font-size:1rem;flex-shrink:0;"></i>
            <span class="field-row-label sys-field-label-text" data-key="<?= e($pf['key']) ?>"><?= e($pf['label']) ?></span>
            <span class="badge bg-secondary" style="font-size:.65rem;">Pinned</span>
            <span class="badge bg-danger" style="font-size:.65rem;"><?= e($pf['badge']) ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 edit-sys-field-btn"
                    data-key="<?= e($pf['key']) ?>"
                    data-label="<?= e($pf['label']) ?>"
                    data-has-required="0"
                    data-required="0">
                <i class="bi bi-pencil"></i>
            </button>
        </div>
        <?php endforeach; ?>

        <!-- Unified sortable list — system + custom fields interleaved -->
        <div class="custom-section-label mt-3">
            <i class="bi bi-arrows-move me-1"></i>Form Fields
            <span class="text-muted fw-normal">&nbsp;— drag <i class="bi bi-grip-vertical"></i> to reorder</span>
        </div>
        <div id="unifiedFieldList">
            <?php if (empty($unifiedBuilderList)): ?>
            <div class="custom-empty" id="emptyState">
                <i class="bi bi-layout-text-window-reverse fs-2 d-block mb-2 text-muted"></i>
                No custom fields yet. Use <strong>Add Custom Field</strong> above to add fields to the form.
            </div>
            <?php else: ?>
            <?php foreach ($unifiedBuilderList as $item): ?>
                <?php if ($item['kind'] === 'system'):
                    $sf = $item['meta'];
                    $badgeClass = match($sf['badge']) {
                        'Required' => 'bg-danger',
                        'Auto'     => 'bg-info',
                        default    => 'bg-light text-secondary border'
                    };
                ?>
                <div class="field-row system-row-orderable" data-system-key="<?= e($item['key']) ?>">
                    <i class="bi bi-grip-vertical drag-handle"></i>
                    <i class="bi <?= e($sf['icon']) ?> text-muted" style="font-size:1rem;flex-shrink:0;"></i>
                    <span class="field-row-label sys-field-label-text" data-key="<?= e($item['key']) ?>"><?= e($sf['label']) ?></span>
                    <span class="badge bg-secondary" style="font-size:.65rem;">System</span>
                    <span class="badge <?= $badgeClass ?> sys-field-badge" style="font-size:.65rem;" data-key="<?= e($item['key']) ?>"><?= e($sf['badge']) ?></span>
                    <?php if ($sf['editable']): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 edit-sys-field-btn"
                            data-key="<?= e($item['key']) ?>"
                            data-label="<?= e($sf['label']) ?>"
                            data-has-required="<?= $sf['has_required'] ? '1' : '0' ?>"
                            data-required="<?= isset($sysFs['required_' . $item['key']]) ? $sysFs['required_' . $item['key']] : '0' ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php else:
                    $field = $item['field'];
                    $meta = $fieldTypeMeta[$field['field_type']] ?? ['label' => $field['field_type'], 'icon' => 'bi-question'];
                    $typeIds = $fieldTypeMap[$field['id']] ?? [];
                    $typeNames = [];
                    if ($typeIds) {
                        foreach ($ticketTypes as $tt) {
                            if (in_array((int) $tt['id'], $typeIds, true)) $typeNames[] = $tt['name'];
                        }
                    }
                ?>
                <div class="field-row" data-field-id="<?= (int) $field['id'] ?>" data-field-type="<?= e($field['field_type']) ?>" data-type-ids="<?= e(implode(',', $typeIds)) ?>">
                    <i class="bi bi-grip-vertical drag-handle"></i>
                    <span class="badge bg-secondary" style="font-size:.68rem;"><?= e($meta['label']) ?></span>
                    <span class="field-row-label"><?= e($field['label']) ?></span>
                    <?php if (empty($typeNames)): ?>
                    <span class="scope-pill scope-global" title="Shown on every ticket type">
                        <i class="bi bi-globe2"></i>Global
                    </span>
                    <?php else: ?>
                    <span class="scope-pill scope-specific"
                          data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="<?= e(implode(' · ', $typeNames)) ?>">
                        <i class="bi bi-tag-fill"></i><?= count($typeNames) ?> type<?= count($typeNames) !== 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
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
                <?php endif; ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ── Live Preview pane ── -->
<aside class="preview-pane" id="previewPane" aria-label="Live ticket form preview" aria-hidden="true">
    <div class="preview-head">
        <i class="bi bi-eye-fill text-primary"></i>
        <span>Live Preview</span>
        <span class="ph-type" id="previewHeadType">All types</span>
        <div class="ph-actions">
            <button type="button" class="ph-btn" id="previewReloadBtn" title="Reload preview">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <button type="button" class="ph-btn" id="previewOpenBtn" title="Open in new tab">
                <i class="bi bi-box-arrow-up-right"></i>
            </button>
            <button type="button" class="ph-btn" id="previewCloseBtn" title="Close preview">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
    <iframe id="previewFrame" title="Portal ticket form preview" loading="lazy"
            sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>
    <div class="preview-loading">
        <div class="spinner-border spinner-border-sm text-primary"></div>
        <span>Loading preview…</span>
    </div>
</aside>
</div><!-- /.builder-wrap -->

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

                <div class="row g-3 mb-3" id="modalLabelRow">
                    <div class="col-md-8">
                        <label class="form-label fw-medium" id="modalLabelLabel">Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalLabel" placeholder="Field label shown to users">
                    </div>
                    <div class="col-md-4" id="modalPlaceholderWrap">
                        <label class="form-label fw-medium">Placeholder</label>
                        <input type="text" class="form-control" id="modalPlaceholder" placeholder="Optional hint text">
                    </div>
                </div>
                <div class="row g-3 mb-3" id="modalOptionsRow">
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

                <!-- Ticket type association — segmented "All / Specific" control -->
                <div class="mb-3" id="modalTypeRow">
                    <label class="form-label fw-medium d-block mb-2">
                        <i class="bi bi-tag me-1 text-muted"></i>Show this field on
                    </label>
                    <div class="scope-segment" role="radiogroup" aria-label="Field scope">
                        <label>
                            <input type="radio" name="modalScope" value="all" id="modalScopeAll" checked>
                            <span><i class="bi bi-globe2"></i>All ticket types</span>
                        </label>
                        <label>
                            <input type="radio" name="modalScope" value="specific" id="modalScopeSpecific">
                            <span><i class="bi bi-tag-fill"></i>Only specific types</span>
                        </label>
                    </div>
                    <div id="modalTypeChecksWrap" data-mode="all">
                        <div class="scope-types-grid" id="modalTypeChecks">
                            <?php foreach ($ticketTypes as $tt): ?>
                            <div class="form-check">
                                <input class="form-check-input modal-type-cb" type="checkbox"
                                       id="modalType_<?= (int) $tt['id'] ?>" value="<?= (int) $tt['id'] ?>">
                                <label class="form-check-label" for="modalType_<?= (int) $tt['id'] ?>"><?= e($tt['name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text mt-1">Pick at least one. Switch to <em>All ticket types</em> to make it global.</div>
                    </div>
                </div>

                <hr id="modalHr">

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

                <!-- Text Block content -->
                <div id="sectionTextBlock" class="field-section">
                    <label class="form-label fw-medium">Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="modalTextBlockContent" rows="6"
                              placeholder="Enter the text, instructions, or notes to display on the form…"></textarea>
                    <div class="form-text">This text will be displayed as a read-only block on the ticket submission form.</div>
                </div>

                <!-- Image -->
                <div id="sectionImage" class="field-section">
                    <div id="imageCurrentWrap" class="mb-3" style="display:none;">
                        <label class="form-label fw-medium">Current Image</label><br>
                        <img id="imageCurrentPreview" src="" alt="Current image" class="img-thumbnail" style="max-height:160px;">
                    </div>
                    <label class="form-label fw-medium">Upload Image</label>
                    <input type="file" class="form-control" id="modalImageFile" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">JPEG, PNG, GIF, or WebP. Max 5 MB. The image will be displayed on the ticket submission form.</div>
                    <div id="imageUploadProgress" class="mt-2" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary me-1"></div> Uploading…
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFieldBtn">Save Field</button>
            </div>
        </div>
    </div>
</div>

<!-- System Field Edit Modal -->
<div class="modal fade" id="sysFieldModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-pencil me-2"></i>Edit System Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sysFieldKey">
                <div class="mb-3">
                    <label class="form-label fw-medium">Label <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="sysFieldLabel" maxlength="80"
                           placeholder="Label shown to users">
                    <div class="form-text">This is the wording shown on the ticket form.</div>
                </div>
                <div id="sysFieldRequiredWrap" style="display:none;">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sysFieldRequired">
                        <label class="form-check-label" for="sysFieldRequired">Required field</label>
                    </div>
                    <div class="form-text">When enabled, users must fill this in before submitting.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSysFieldBtn">
                    <i class="bi bi-check-lg me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Field Confirmation Modal -->
<div class="modal fade" id="deleteFieldModal" tabindex="-1" aria-labelledby="deleteFieldModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteFieldModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Remove Field
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Remove this field from the form?</p>
                <p class="text-muted small mb-0">The field will no longer appear on new or existing tickets, but historical values submitted by users will be preserved.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="deleteFieldConfirmBtn">
                    <i class="bi bi-trash me-1"></i>Remove Field
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var list       = document.getElementById('unifiedFieldList');
    var countBadge = document.getElementById('fieldCountBadge');
    var modal      = new bootstrap.Modal(document.getElementById('fieldModal'));

    var fieldTypeMeta = <?= json_encode(array_map(fn($m) => ['label' => $m['label'], 'icon' => $m['icon']], $fieldTypeMeta)) ?>;
    var fieldTypeMap  = <?= json_encode($fieldTypeMap ?: new stdClass()) ?>;
    var ticketTypes   = <?= json_encode($ticketTypes) ?>;

    /* ─── SortableJS: reorder within custom list only ─── */
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var sortableInstance = Sortable.create(list, {
        handle:    '.drag-handle',
        animation: 150,
        filter:    '.custom-empty',
        onEnd:     function () { saveOrder(); if (typeof loadPreview === 'function') loadPreview(); }
    });

    /* ─── Type filter: preview the form for any single ticket type ─── */
    var filterBar       = document.getElementById('typeFilterBar');
    var filterBanner    = document.getElementById('filterBanner');
    var filterBannerType  = document.getElementById('filterBannerType');
    var filterBannerCount = document.getElementById('filterBannerCount');
    var currentFilterTypeId = null;   // null = "all types"

    function rowMatchesType(row, typeId) {
        if (typeId === null) return true;
        // System + pinned rows always show — they appear on every type
        if (row.classList.contains('system-row') || row.classList.contains('system-row-orderable')) return true;
        var ids = (row.dataset.typeIds || '').split(',').filter(Boolean).map(Number);
        return ids.length === 0 || ids.indexOf(typeId) !== -1;
    }

    function applyTypeFilter(typeId) {
        currentFilterTypeId = typeId;
        var pinned = document.querySelectorAll('.field-row.system-row');
        var allRows = Array.from(pinned).concat(Array.from(list.querySelectorAll('.field-row')));
        var visible = 0;

        allRows.forEach(function (row) {
            var match = rowMatchesType(row, typeId);
            row.style.display = match ? '' : 'none';
            row.classList.remove('is-global', 'is-specific');
            if (typeId !== null && match && !row.classList.contains('system-row') && !row.classList.contains('system-row-orderable')) {
                var ids = (row.dataset.typeIds || '').split(',').filter(Boolean);
                row.classList.add(ids.length === 0 ? 'is-global' : 'is-specific');
            }
            if (match) visible++;
        });

        // Active chip styling
        filterBar.querySelectorAll('.type-chip').forEach(function (chip) {
            var match = (typeId === null && chip.dataset.typeId === 'all')
                     || (typeId !== null && parseInt(chip.dataset.typeId) === typeId);
            chip.classList.toggle('active', match);
            chip.setAttribute('aria-selected', match ? 'true' : 'false');
        });

        // Banner + body class for drag-disable styling
        if (typeId === null) {
            filterBanner.style.display = 'none';
            document.body.classList.remove('filter-active');
            sortableInstance.option('disabled', false);
        } else {
            var typeName = '';
            for (var i = 0; i < ticketTypes.length; i++) {
                if (parseInt(ticketTypes[i].id) === typeId) { typeName = ticketTypes[i].name; break; }
            }
            filterBannerType.textContent  = typeName;
            filterBannerCount.textContent = visible;
            filterBanner.style.display = '';
            document.body.classList.add('filter-active');
            sortableInstance.option('disabled', true);
        }
    }

    function countFieldsForType(typeId) {
        // Pinned (subject + description) + every system row + matching custom fields
        var n = document.querySelectorAll('.field-row.system-row').length;
        n += list.querySelectorAll('[data-system-key]').length;
        list.querySelectorAll('.field-row[data-field-id]').forEach(function (row) {
            var ids = (row.dataset.typeIds || '').split(',').filter(Boolean).map(Number);
            if (typeId === null || ids.length === 0 || ids.indexOf(typeId) !== -1) n++;
        });
        return n;
    }

    function refreshChipCounts() {
        filterBar.querySelectorAll('.type-chip').forEach(function (chip) {
            var key = chip.dataset.typeId;
            var countEl = chip.querySelector('.chip-count');
            if (!countEl) return;
            if (key === 'all') {
                countEl.textContent = countFieldsForType(null);
            } else {
                countEl.textContent = countFieldsForType(parseInt(key));
            }
        });
    }

    filterBar.addEventListener('click', function (e) {
        var chip = e.target.closest('.type-chip');
        if (!chip) return;
        var key = chip.dataset.typeId;
        applyTypeFilter(key === 'all' ? null : parseInt(key));
    });

    document.getElementById('filterBannerClear').addEventListener('click', function () {
        applyTypeFilter(null);
    });

    /* ─── Scope segmented control inside the modal ─── */
    document.querySelectorAll('input[name="modalScope"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var mode = document.querySelector('input[name="modalScope"]:checked').value;
            document.getElementById('modalTypeChecksWrap').dataset.mode = mode;
            // When switching to "All", clear any checked types so the user can't accidentally save a stale list
            if (mode === 'all') {
                document.querySelectorAll('.modal-type-cb').forEach(function (cb) { cb.checked = false; });
            }
        });
    });

    /* ─── Initialize Bootstrap tooltips on scope pills ─── */
    function initTooltips(root) {
        (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }
    initTooltips();

    /* ─── Bootstrap chip counts on first paint ─── */
    refreshChipCounts();

    /* ─── Live preview pane ─── */
    var previewPane    = document.getElementById('previewPane');
    var previewFrame   = document.getElementById('previewFrame');
    var previewToggle  = document.getElementById('togglePreviewBtn');
    var previewHeadTy  = document.getElementById('previewHeadType');
    var previewReload  = document.getElementById('previewReloadBtn');
    var previewOpen    = document.getElementById('previewOpenBtn');
    var previewClose   = document.getElementById('previewCloseBtn');

    function buildPreviewUrl() {
        var url = '/portal/tickets/create?embed=1';
        if (currentFilterTypeId !== null) url += '&type_id=' + currentFilterTypeId;
        // Cache-buster guarantees iframe reloads on each filter change AND on field-save
        url += '&_t=' + Date.now();
        return url;
    }

    function previewIsOpen() {
        return previewPane.classList.contains('is-open');
    }

    function loadPreview() {
        if (!previewIsOpen()) return;
        previewPane.classList.add('is-loading');
        previewFrame.src = buildPreviewUrl();
        // Update the type chip in the preview header
        if (currentFilterTypeId === null) {
            previewHeadTy.textContent = 'All types';
        } else {
            for (var i = 0; i < ticketTypes.length; i++) {
                if (parseInt(ticketTypes[i].id) === currentFilterTypeId) {
                    previewHeadTy.textContent = ticketTypes[i].name;
                    return;
                }
            }
        }
    }

    previewFrame.addEventListener('load', function () {
        previewPane.classList.remove('is-loading');
    });

    previewToggle.addEventListener('click', function () {
        var willOpen = !previewIsOpen();
        previewPane.classList.toggle('is-open', willOpen);
        previewToggle.classList.toggle('is-active', willOpen);
        previewToggle.setAttribute('aria-pressed', willOpen ? 'true' : 'false');
        previewPane.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
        if (willOpen) loadPreview();
    });

    previewClose.addEventListener('click', function () {
        previewPane.classList.remove('is-open');
        previewToggle.classList.remove('is-active');
        previewToggle.setAttribute('aria-pressed', 'false');
        previewPane.setAttribute('aria-hidden', 'true');
    });

    previewReload.addEventListener('click', loadPreview);
    previewOpen.addEventListener('click', function () {
        // Open the live (non-embed) form in a new tab, with the active type pre-selected
        var url = '/portal/tickets/create';
        if (currentFilterTypeId !== null) url += '?type_id=' + currentFilterTypeId;
        window.open(url, '_blank', 'noopener');
    });

    // Re-load the preview whenever the chip-strip filter changes
    var origApplyTypeFilter = applyTypeFilter;
    applyTypeFilter = function (typeId) {
        origApplyTypeFilter(typeId);
        loadPreview();
    };

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
            headers:     {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken},
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
            // If a type filter is active, pre-scope the new field to that type
            if (currentFilterTypeId !== null) {
                document.getElementById('modalScopeSpecific').checked = true;
                document.getElementById('modalScopeAll').checked = false;
                document.getElementById('modalTypeChecksWrap').dataset.mode = 'specific';
                var cb = document.getElementById('modalType_' + currentFilterTypeId);
                if (cb) cb.checked = true;
            }
            refreshChipCounts();
            if (typeof loadPreview === 'function') loadPreview();
        });
    }

    function saveOrder() {
        var rows  = list.querySelectorAll('[data-field-id], [data-system-key]');
        var order = Array.from(rows).map(function (r) {
            return r.dataset.systemKey || r.dataset.fieldId;
        });
        fetch('/admin/workflows/ticket-fields/reorder', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
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
        // Newly added fields default to "Global" (no type association)
        var scopePill = '<span class="scope-pill scope-global" title="Shown on every ticket type">' +
                        '<i class="bi bi-globe2"></i>Global</span>';
        var row = document.createElement('div');
        row.className = 'field-row';
        row.dataset.fieldId   = field.id;
        row.dataset.fieldType = field.field_type;
        row.dataset.typeIds   = '';
        row.innerHTML =
            '<i class="bi bi-grip-vertical drag-handle"></i>' +
            '<span class="badge bg-secondary" style="font-size:.68rem;">' + esc(meta.label) + '</span>' +
            '<span class="field-row-label">' + esc(field.label) + '</span>' +
            scopePill +
            reqBadge + eyeIcon +
            '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 edit-field-btn" data-field-id="' + field.id + '">' +
            '<i class="bi bi-pencil"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 delete-field-btn" data-field-id="' + field.id + '">' +
            '<i class="bi bi-trash"></i></button>';
        return row;
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    /* ─── Delete ─── */
    var pendingDeleteId  = null;
    var pendingDeleteRow = null;

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-field-btn');
        if (!btn) return;
        pendingDeleteId  = btn.dataset.fieldId;
        pendingDeleteRow = list.querySelector('[data-field-id="' + pendingDeleteId + '"]');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteFieldModal')).show();
    });

    document.getElementById('deleteFieldConfirmBtn').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('deleteFieldModal')).hide();
        fetch('/admin/workflows/ticket-fields/' + pendingDeleteId + '/delete', {
            method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-Token': csrfToken}
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && pendingDeleteRow) {
                pendingDeleteRow.remove();
                updateCount();
                refreshChipCounts();
                if (currentFilterTypeId !== null) applyTypeFilter(currentFilterTypeId);
                if (typeof loadPreview === 'function') loadPreview();
            }
        });
    });

    /* ─── Edit ─── */
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-field-btn');
        if (!btn) return;
        var id   = btn.dataset.fieldId;
        var row  = list.querySelector('[data-field-id="' + id + '"]');
        var type = row.dataset.fieldType;
        // For types that store config (text_block, image), fetch it server-side
        if (type === 'text_block' || type === 'image') {
            fetch('/admin/workflows/ticket-fields/' + id + '/config', {credentials: 'same-origin'})
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    openModal({
                        id:          id,
                        field_type:  type,
                        label:       row.querySelector('.field-row-label').textContent.trim(),
                        placeholder: '',
                        is_required: 0,
                        is_visible:  row.querySelector('.bi-eye-slash') ? 0 : 1,
                        config:      data.config,
                    });
                });
        } else {
            openModal({
                id:          id,
                field_type:  type,
                label:       row.querySelector('.field-row-label').textContent.trim(),
                placeholder: '',
                is_required: row.querySelector('.badge.bg-danger') ? 1 : 0,
                is_visible:  row.querySelector('.bi-eye-slash')    ? 0 : 1,
                config:      null,
            });
        }
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

    var displayOnlyTypes  = ['text_block', 'image'];
    var noPlaceholderTypes = ['text_block', 'image', 'cc', 'date_range'];

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

        // Populate scope segmented control + type checkboxes
        var assignedTypes = fieldTypeMap[field.id] || [];
        var modeIsSpecific = assignedTypes.length > 0;
        document.getElementById('modalScopeAll').checked      = !modeIsSpecific;
        document.getElementById('modalScopeSpecific').checked = modeIsSpecific;
        document.getElementById('modalTypeChecksWrap').dataset.mode = modeIsSpecific ? 'specific' : 'all';
        document.querySelectorAll('.modal-type-cb').forEach(function (cb) {
            cb.checked = assignedTypes.indexOf(parseInt(cb.value)) !== -1;
        });

        // Hide/show options row and placeholder for display-only types
        var isDisplayOnly  = displayOnlyTypes.indexOf(field.field_type) !== -1;
        var noPlaceholder  = noPlaceholderTypes.indexOf(field.field_type) !== -1;
        document.getElementById('modalOptionsRow').style.display      = isDisplayOnly ? 'none' : '';
        document.getElementById('modalTypeRow').style.display         = '';
        document.getElementById('modalPlaceholderWrap').style.display = noPlaceholder ? 'none' : '';
        document.getElementById('modalHr').style.display              = '';

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

        } else if (field.field_type === 'text_block') {
            document.getElementById('sectionTextBlock').classList.add('active');
            var cfg = field.config
                ? (typeof field.config === 'string' ? JSON.parse(field.config) : field.config)
                : null;
            document.getElementById('modalTextBlockContent').value = (cfg && cfg.content) ? cfg.content : '';

        } else if (field.field_type === 'image') {
            document.getElementById('sectionImage').classList.add('active');
            document.getElementById('modalImageFile').value = '';
            document.getElementById('imageUploadProgress').style.display = 'none';
            var cfg = field.config
                ? (typeof field.config === 'string' ? JSON.parse(field.config) : field.config)
                : null;
            var previewWrap = document.getElementById('imageCurrentWrap');
            var previewImg  = document.getElementById('imageCurrentPreview');
            if (cfg && cfg.image_path) {
                previewImg.src = '/uploads/field-images/' + cfg.image_path;
                previewWrap.style.display = '';
            } else {
                previewWrap.style.display = 'none';
                previewImg.src = '';
            }
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

    /* ─── Image upload (fires immediately on file select) ─── */
    document.getElementById('modalImageFile').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        var id = document.getElementById('modalFieldId').value;
        var progress = document.getElementById('imageUploadProgress');
        progress.style.display = '';

        var fd = new FormData();
        fd.append('image', file);
        fetch('/admin/workflows/ticket-fields/' + id + '/upload-image', {
            method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-Token': csrfToken}, body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            progress.style.display = 'none';
            if (!data.success) { alert(data.error || 'Upload failed.'); return; }
            var previewImg  = document.getElementById('imageCurrentPreview');
            var previewWrap = document.getElementById('imageCurrentWrap');
            previewImg.src = '/uploads/field-images/' + data.image_path + '?t=' + Date.now();
            previewWrap.style.display = '';
        })
        .catch(function () { progress.style.display = 'none'; alert('Upload failed.'); });
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

        // text_block: validate content
        if (type === 'text_block') {
            var content = document.getElementById('modalTextBlockContent').value.trim();
            if (!content) { document.getElementById('modalTextBlockContent').focus(); return; }
        }

        // Collect selected ticket type IDs (only when scope = specific)
        var scopeMode = document.querySelector('input[name="modalScope"]:checked').value;
        var typeIds = [];
        if (scopeMode === 'specific') {
            document.querySelectorAll('.modal-type-cb:checked').forEach(function (cb) {
                typeIds.push(parseInt(cb.value));
            });
            if (typeIds.length === 0) {
                alert('Pick at least one ticket type, or switch to "All ticket types".');
                return;
            }
        }

        var payload = {label: label, placeholder: placeholder, is_required: isRequired, is_visible: isVisible, type_ids: typeIds};

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
        if (type === 'text_block') {
            payload.config = {content: document.getElementById('modalTextBlockContent').value.trim()};
        }

        fetch('/admin/workflows/ticket-fields/' + id + '/update', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
            body:        JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert(data.error || 'Error saving field.'); return; }

            var row = list.querySelector('[data-field-id="' + id + '"]');
            if (row) {
                row.querySelector('.field-row-label').textContent = label;
                var isDisplayOnly = displayOnlyTypes.indexOf(type) !== -1;
                if (!isDisplayOnly) {
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

                // Update scope pill
                var oldScopePill = row.querySelector('.scope-pill');
                if (oldScopePill) oldScopePill.remove();
                var pill = document.createElement('span');
                if (typeIds.length > 0) {
                    var names = typeIds.map(function (tid) {
                        var found = ticketTypes.find(function (t) { return parseInt(t.id) === tid; });
                        return found ? found.name : '';
                    }).filter(Boolean);
                    pill.className = 'scope-pill scope-specific';
                    pill.title = names.join(' · ');
                    pill.innerHTML = '<i class="bi bi-tag-fill"></i>' + typeIds.length + ' type' + (typeIds.length !== 1 ? 's' : '');
                } else {
                    pill.className = 'scope-pill scope-global';
                    pill.title = 'Shown on every ticket type';
                    pill.innerHTML = '<i class="bi bi-globe2"></i>Global';
                }
                row.querySelector('.field-row-label').after(pill);

                // Update row data + JS map
                row.dataset.typeIds = typeIds.join(',');
                if (typeIds.length > 0) {
                    fieldTypeMap[id] = typeIds;
                } else {
                    delete fieldTypeMap[id];
                }
                // Refresh filter chip counts + re-apply current filter
                refreshChipCounts();
                applyTypeFilter(currentFilterTypeId);
            }
            modal.hide();
        });
    });

    updateCount();

    /* ─── System field edit ─── */
    var sysModal       = new bootstrap.Modal(document.getElementById('sysFieldModal'));
    var sysFieldKey    = document.getElementById('sysFieldKey');
    var sysFieldLabel  = document.getElementById('sysFieldLabel');
    var sysFieldReqWrap = document.getElementById('sysFieldRequiredWrap');
    var sysFieldReq    = document.getElementById('sysFieldRequired');

    document.querySelectorAll('.edit-sys-field-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            sysFieldKey.value       = btn.dataset.key;
            sysFieldLabel.value     = btn.dataset.label;
            sysFieldReq.checked     = btn.dataset.required === '1';
            sysFieldReqWrap.style.display = btn.dataset.hasRequired === '1' ? '' : 'none';
            sysModal.show();
        });
    });

    document.getElementById('saveSysFieldBtn').addEventListener('click', function () {
        var key   = sysFieldKey.value;
        var label = sysFieldLabel.value.trim();
        if (!label) { sysFieldLabel.focus(); return; }

        var payload = { field: key, label: label, required: sysFieldReq.checked };

        fetch('/admin/workflows/ticket-fields/system', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body:        JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert(data.error || 'Error saving.'); return; }

            // Update label text in the row
            document.querySelectorAll('.sys-field-label-text[data-key="' + key + '"]').forEach(function (el) {
                el.textContent = label;
            });

            // Update the button's stored label
            document.querySelectorAll('.edit-sys-field-btn[data-key="' + key + '"]').forEach(function (btn) {
                btn.dataset.label    = label;
                btn.dataset.required = sysFieldReq.checked ? '1' : '0';
            });

            // Update required badge for priority / tags
            if (payload.required !== undefined) {
                document.querySelectorAll('.sys-field-badge[data-key="' + key + '"]').forEach(function (badge) {
                    badge.textContent = sysFieldReq.checked ? 'Required' : 'Optional';
                    badge.className = badge.className
                        .replace(/\bbg-danger\b|\bbg-light\b|\btext-secondary\b|\bborder\b/g, '')
                        .trim();
                    if (sysFieldReq.checked) {
                        badge.classList.add('bg-danger');
                    } else {
                        badge.classList.add('bg-light', 'text-secondary', 'border');
                    }
                });
            }

            sysModal.hide();
        });
    });
});
</script>
