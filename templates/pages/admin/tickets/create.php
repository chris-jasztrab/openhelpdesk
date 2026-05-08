<?php
$isAgent      = $isAgent ?? false;
$layout       = 'app';
$pageTitle    = 'New Ticket';
$sidebarItems = $isAgent ? agentSidebar('tickets') : adminSidebar('tickets');
$breadcrumbs  = [
    ['label' => $isAgent ? 'Agent' : 'Admin', 'url' => $isAgent ? '/agent' : '/admin'],
    ['label' => 'Tickets', 'url' => $isAgent ? '/agent/tickets' : '/admin/tickets'],
    ['label' => 'New Ticket'],
];
$formAction   = $formAction ?? '/admin/tickets/create';

// Build template data for JS auto-fill
$templateData = [];
foreach ($templates as $tpl) {
    $templateData[$tpl['id']] = [
        'subject'     => $tpl['subject'],
        'body'        => $tpl['body'],
        'type_id'     => $tpl['type_id'],
        'priority_id' => $tpl['priority_id'],
    ];
}

$statusOptions = [
    'open'                   => 'Open',
    'in_progress'            => 'In Progress',
    'pending'                => 'Pending',
    'waiting_on_customer'    => 'Waiting on Customer',
    'waiting_on_third_party' => 'Waiting on Third Party',
    'resolved'               => 'Resolved',
    'closed'                 => 'Closed',
];
?>
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
<script type="importmap">
{"imports":{"ckeditor5":"https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.js","ckeditor5/":"https://cdn.ckeditor.com/ckeditor5/43.3.1/"}}
</script>
<style>
.ck.ck-editor__editable { min-height: 200px; }
.ck.ck-toolbar { border-radius: .375rem .375rem 0 0 !important; border-color: #dee2e6 !important; }
.ck.ck-editor__editable { border-radius: 0 0 .375rem .375rem !important; border-color: #dee2e6 !important; }

/* Dark mode */
[data-bs-theme="dark"] .ck.ck-toolbar,
[data-bs-theme="dark"] .ck.ck-toolbar__separator { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-button:not(.ck-disabled):hover,
[data-bs-theme="dark"] .ck.ck-button.ck-on { background: #373b3e !important; }
[data-bs-theme="dark"] .ck.ck-button { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-icon { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-editor__editable { background: #212529 !important; color: #dee2e6 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-editor__editable:not(.ck-focused) { border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-list { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-list__item .ck-button:hover { background: #373b3e !important; }
[data-bs-theme="dark"] .ck.ck-dropdown__panel { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-label,
[data-bs-theme="dark"] .ck.ck-heading_paragraph,
[data-bs-theme="dark"] .ck.ck-list__item .ck-button .ck-button__label { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-input { background: #212529 !important; color: #dee2e6 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-balloon-panel { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-color-grid__tile:hover { border-color: #fff !important; }
</style>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">New Ticket</h2>
    <div class="d-flex align-items-center gap-2">
        <?php if (!empty($templates)): ?>
        <select id="templateSelect" class="form-select form-select-sm" style="width:auto;max-width:200px;" title="Start from a template">
            <option value="">Template…</option>
            <?php foreach ($templates as $tpl): ?>
                <option value="<?= (int)$tpl['id'] ?>"><?= e($tpl['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <a href="<?= $isAgent ? '/agent/tickets' : '/admin/tickets' ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <form method="POST" action="<?= e($formAction) ?>" id="admin-ticket-form">
            <?= csrfField() ?>
            <input type="hidden" id="dup_matched_ids" name="_dup_matched_ids" value="">

            <!-- Subject & Description -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-ticket-detailed me-1"></i>Ticket Details
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="subject" class="form-label fw-semibold">
                            Subject <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="subject" name="subject"
                               value="<?= e(old('subject', '')) ?>"
                               placeholder="Brief summary of the issue" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">
                            Description <span class="text-danger">*</span>
                        </label>
                        <div id="admin-ticket-editor"></div>
                        <input type="hidden" id="description" name="description" value="<?= e(old('description', '')) ?>">
                        <div id="admin-ticket-editor-error" class="text-danger small mt-1" style="display:none;">Description is required.</div>
                    </div>
                    <div id="dup-warning" class="mt-3" style="display:none;"></div>
                    <?php
                    $dupPreviewEndpoint = '/agent/tickets/dup-preview';
                    $dupViewBase        = '/agent/tickets';
                    include ROOT_DIR . '/templates/partials/dup-preview-modal.php';
                    ?>
                </div>
            </div>

            <!-- Classification & Custom Fields (unified order) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-tags me-1"></i>Classification & Details
                </div>
                <div class="card-body">
                    <div class="row g-3">
                    <?php
                    $portalMode = false;
                    foreach ($unifiedFields as $uf):
                        if ($uf['kind'] === 'system'):
                    ?>
                        <?php if ($uf['key'] === 'ticket_type'): ?>
                        <div class="col-md-6 col-lg-4">
                            <label for="type_id" class="form-label fw-semibold">Type</label>
                            <select class="form-select" id="type_id" name="type_id">
                                <option value="">— Unclassified —</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>"
                                        <?= old('type_id', '') == $t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label for="status" class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="status" name="status">
                                <?php foreach ($statusOptions as $val => $label): ?>
                                    <option value="<?= $val ?>"
                                        <?= old('status', 'open') === $val ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php elseif ($uf['key'] === 'priority'): ?>
                        <div class="col-md-6 col-lg-4">
                            <label for="priority_id" class="form-label fw-semibold">Priority</label>
                            <select class="form-select" id="priority_id" name="priority_id">
                                <option value="">— None —</option>
                                <?php foreach ($priorities as $pri): ?>
                                    <option value="<?= $pri['id'] ?>"
                                        <?= old('priority_id', '') == $pri['id'] ? 'selected' : '' ?>>
                                        <?= e($pri['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php elseif ($uf['key'] === 'location'): ?>
                        <div class="col-md-6 col-lg-4">
                            <label for="location_id" class="form-label fw-semibold"><?= label('location.singular') ?></label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">— None —</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['id'] ?>"
                                        <?= old('location_id', '') == $loc['id'] ? 'selected' : '' ?>>
                                        <?= e($loc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label for="due_date" class="form-label fw-semibold">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date"
                                   value="<?= e(old('due_date', '')) ?>">
                        </div>
                        <?php elseif ($uf['key'] === 'tags'): ?>
                        <?php if (getSetting('tags_enabled', '1') === '1'): ?>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Tags</label>
                            <div class="d-flex flex-wrap gap-2 mb-2" id="tagBadges"></div>
                            <div class="input-group" style="max-width:320px;">
                                <span class="input-group-text text-muted">#</span>
                                <input type="text" class="form-control" id="tagInput"
                                       placeholder="Add a tag and press Enter"
                                       autocomplete="off">
                            </div>
                            <div id="tagHiddenFields"></div>
                            <div class="form-text mt-1">Press Enter or comma to add a tag.</div>
                        </div>
                        <?php endif; ?>
                        <?php elseif ($uf['key'] === 'attachments'): ?>
                        <!-- Attachments not shown on admin create form -->
                        <?php endif; ?>
                    <?php else:
                        $cf    = $uf['field'];
                        $cfKey = 'field_' . $cf['id'];
                        $cfOpts = $fieldOptions[$cf['id']] ?? [];
                    ?>
                        <div class="col-12 custom-field-col" data-field-id="<?= (int) $cf['id'] ?>">
                            <?php include ROOT_DIR . '/templates/partials/custom-field-input.php'; ?>
                        </div>
                    <?php endif;
                    endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Assignment -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-person-check me-1"></i>Assignment
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="assigned_to" class="form-label fw-semibold">Assign To</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($agents as $ag): ?>
                                    <option value="<?= $ag['id'] ?>"
                                        <?= old('assigned_to', '') == $ag['id'] ? 'selected' : '' ?>>
                                        <?= e($ag['first_name'] . ' ' . $ag['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="group_id" class="form-label fw-semibold">Group</label>
                            <select class="form-select" id="group_id" name="group_id">
                                <option value="">— None —</option>
                                <?php foreach ($groups as $grp): ?>
                                    <option value="<?= $grp['id'] ?>"
                                        <?= old('group_id', '') == $grp['id'] ? 'selected' : '' ?>>
                                        <?= e($grp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-telephone me-1"></i>On Behalf Of
                            </label>
                            <div class="form-text mb-1">
                                Leave blank to submit as yourself. To file a ticket for someone who phoned in, search for them by name or email — the requester will receive the confirmation email and own the ticket; you'll be recorded as the submitter for audit.
                            </div>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="onBehalfSearch"
                                       placeholder="Search by name or email…"
                                       autocomplete="off">
                                <div id="onBehalfResults" class="dropdown-menu w-100 shadow-sm"
                                     style="max-height:200px;overflow-y:auto;display:none;"></div>
                            </div>
                            <input type="hidden" id="on_behalf_of_id" name="on_behalf_of_id"
                                   value="<?= e(old('on_behalf_of_id', '')) ?>">
                            <div id="onBehalfSelected" class="mt-2" style="display:none;">
                                <span class="badge bg-primary py-2 px-3" id="onBehalfBadge"></span>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2"
                                        id="onBehalfClear">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CC -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-people me-1"></i>CC
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">Add users to CC on this ticket. They will receive email updates.</p>
                    <div id="ccBadges" class="d-flex flex-wrap gap-2 mb-2"></div>
                    <div id="ccHiddenFields"></div>
                    <div class="position-relative" style="max-width:360px;">
                        <input type="text" class="form-control" id="ccSearchInput"
                               placeholder="Search by name or email…" autocomplete="off">
                        <div id="ccDropdown" class="mention-dropdown" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                    <i class="bi bi-plus-lg me-1"></i>Create Ticket
                </button>
                <a href="<?= $isAgent ? '/agent/tickets' : '/admin/tickets' ?>"
                   class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// ── Custom field type filtering ────────────────────────────────
<?php if (!empty($customFields)): ?>
(function() {
    var fieldTypeMap = <?= json_encode($fieldTypeMap ?: new stdClass()) ?>;
    var typeSelect   = document.getElementById('type_id');

    function filterFieldsByType() {
        var selectedType = parseInt(typeSelect.value) || 0;
        document.querySelectorAll('.custom-field-col').forEach(function(col) {
            var fieldId = col.dataset.fieldId;
            var types   = fieldTypeMap[fieldId] || [];
            var show    = selectedType > 0 && (types.length === 0 || types.indexOf(selectedType) !== -1);
            col.style.display = show ? '' : 'none';
        });
    }

    typeSelect.addEventListener('change', filterFieldsByType);
    filterFieldsByType();
})();

// ── Dependent field cascading dropdowns ────────────────────────
<?php
$depFields = array_filter($customFields, fn($f) => $f['field_type'] === 'dependent');
if (!empty($depFields)):
    $depIds = array_column($depFields, 'id');
    $allDepOpts = [];
    foreach ($depIds as $did) {
        $allDepOpts[$did] = $fieldOptions[$did] ?? [];
    }
?>
(function() {
    var allOptions = <?= json_encode($allDepOpts) ?>;

    document.querySelectorAll('.dep-l1').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var fid    = this.dataset.field;
            var l1Val  = this.value;
            var opts   = allOptions[fid] || [];
            var l2Wrap = document.getElementById('dep_l2_wrap_' + fid);
            var l2Sel  = l2Wrap ? l2Wrap.querySelector('.dep-l2') : null;
            var l3Wrap = document.getElementById('dep_l3_wrap_' + fid);
            var l3Sel  = l3Wrap ? l3Wrap.querySelector('.dep-l3') : null;

            if (l2Sel) {
                l2Sel.innerHTML = '<option value="">— Select —</option>';
                if (l1Val) {
                    var children = opts.filter(function(o) { return String(o.parent_option_id) === String(l1Val); });
                    children.forEach(function(o) {
                        var opt = document.createElement('option');
                        opt.value = o.id;
                        opt.textContent = o.label;
                        l2Sel.appendChild(opt);
                    });
                    l2Wrap.style.display = children.length ? '' : 'none';
                } else {
                    l2Wrap.style.display = 'none';
                }
            }
            if (l3Sel) {
                l3Sel.innerHTML = '<option value="">— Select —</option>';
                if (l3Wrap) l3Wrap.style.display = 'none';
            }
        });
    });

    document.querySelectorAll('.dep-l2').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var fid    = this.dataset.field;
            var l2Val  = this.value;
            var opts   = allOptions[fid] || [];
            var l3Wrap = document.getElementById('dep_l3_wrap_' + fid);
            var l3Sel  = l3Wrap ? l3Wrap.querySelector('.dep-l3') : null;

            if (l3Sel) {
                l3Sel.innerHTML = '<option value="">— Select —</option>';
                if (l2Val) {
                    var children = opts.filter(function(o) { return String(o.parent_option_id) === String(l2Val); });
                    children.forEach(function(o) {
                        var opt = document.createElement('option');
                        opt.value = o.id;
                        opt.textContent = o.label;
                        l3Sel.appendChild(opt);
                    });
                    l3Wrap.style.display = children.length ? '' : 'none';
                } else {
                    l3Wrap.style.display = 'none';
                }
            }
        });
    });
})();
<?php endif; ?>
<?php endif; ?>

// ── Template auto-fill ──────────────────────────────────────────
const TEMPLATES = <?= json_encode($templateData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

document.getElementById('templateSelect')?.addEventListener('change', function () {
    const tpl = TEMPLATES[this.value];
    if (!tpl) return;
    if (tpl.subject)     document.getElementById('subject').value      = tpl.subject;
    if (tpl.body && window._adminTicketEditor) window._adminTicketEditor.setData(tpl.body);
    if (tpl.type_id) {
        document.getElementById('type_id').value = tpl.type_id;
        document.getElementById('type_id').dispatchEvent(new Event('change'));
    }
    if (tpl.priority_id) document.getElementById('priority_id').value  = tpl.priority_id;
});

<?php if (getSetting('tags_enabled', '1') === '1'): ?>
// ── Tag management ──────────────────────────────────────────────
const tagBadges      = document.getElementById('tagBadges');
const tagHidden      = document.getElementById('tagHiddenFields');
const tagInput       = document.getElementById('tagInput');
const tagSet         = new Set();

function addTag(raw) {
    const name = raw.replace(/[^a-zA-Z0-9_\-\s]/g, '').trim().toLowerCase();
    if (!name || tagSet.has(name)) return;
    tagSet.add(name);

    const badge = document.createElement('span');
    badge.className = 'badge bg-secondary d-inline-flex align-items-center gap-1 py-2 px-2';
    badge.innerHTML = `#${name} <button type="button" class="btn-close btn-close-white btn-sm ms-1" style="font-size:.6rem;" aria-label="Remove"></button>`;
    badge.querySelector('.btn-close').addEventListener('click', () => {
        tagSet.delete(name);
        badge.remove();
        tagHidden.querySelector(`input[value="${CSS.escape(name)}"]`)?.remove();
    });
    tagBadges.appendChild(badge);

    const hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = 'tags[]';
    hidden.value = name;
    tagHidden.appendChild(hidden);
}

tagInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(tagInput.value);
        tagInput.value = '';
    }
});
<?php endif; ?>

// ── "On behalf of" live search ─────────────────────────────────
const obSearch   = document.getElementById('onBehalfSearch');
const obResults  = document.getElementById('onBehalfResults');
const obHidden   = document.getElementById('on_behalf_of_id');
const obSelected = document.getElementById('onBehalfSelected');
const obBadge    = document.getElementById('onBehalfBadge');
const obClear    = document.getElementById('onBehalfClear');
let obTimer;

obSearch.addEventListener('input', () => {
    clearTimeout(obTimer);
    const q = obSearch.value.trim();
    if (q.length < 2) { obResults.style.display = 'none'; return; }
    obTimer = setTimeout(() => {
        fetch('/api/user-search?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                obResults.innerHTML = '';
                if (!data.length) {
                    obResults.innerHTML = '<div class="dropdown-item text-muted small">No users found</div>';
                } else {
                    data.forEach(u => {
                        const name = u.first_name + ' ' + u.last_name;
                        const item = document.createElement('a');
                        item.className = 'dropdown-item small';
                        item.href = '#';
                        item.textContent = name + ' — ' + u.email;
                        item.addEventListener('click', e => {
                            e.preventDefault();
                            obHidden.value      = u.id;
                            obBadge.textContent = name + ' (' + u.email + ')';
                            obSelected.style.display = '';
                            obSearch.style.display   = 'none';
                            obResults.style.display  = 'none';
                        });
                        obResults.appendChild(item);
                    });
                }
                obResults.style.display = 'block';
            });
    }, 250);
});

obClear.addEventListener('click', () => {
    obHidden.value           = '';
    obSearch.value           = '';
    obSearch.style.display   = '';
    obSelected.style.display = 'none';
});

document.addEventListener('click', e => {
    if (!obSearch.contains(e.target) && !obResults.contains(e.target)) {
        obResults.style.display = 'none';
    }
});

// ── CC autocomplete ─────────────────────────────────────────────
(function() {
    var ccInput   = document.getElementById('ccSearchInput');
    var ccDrop    = document.getElementById('ccDropdown');
    var ccBadges  = document.getElementById('ccBadges');
    var ccHidden  = document.getElementById('ccHiddenFields');
    var ccSet     = {};
    var ccTimer   = null;
    var ccResults = [];
    var ccActive  = -1;

    function escH(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function renderCcBadges() {
        ccBadges.innerHTML = '';
        Object.values(ccSet).forEach(function(u) {
            var b = document.createElement('span');
            b.className = 'badge bg-secondary d-inline-flex align-items-center gap-1 py-1 px-2';
            b.innerHTML = escH(u.first_name + ' ' + u.last_name)
                        + ' <span class="opacity-75 small">&lt;' + escH(u.email) + '&gt;</span>'
                        + ' <button type="button" class="btn-close btn-close-white ms-1" style="font-size:.55rem;" aria-label="Remove" data-uid="' + u.id + '"></button>';
            b.querySelector('.btn-close').addEventListener('click', function() {
                delete ccSet[u.id]; renderCcBadges(); renderCcHidden();
            });
            ccBadges.appendChild(b);
        });
    }

    function renderCcHidden() {
        ccHidden.innerHTML = '';
        Object.keys(ccSet).forEach(function(id) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'cc_user_ids[]'; inp.value = id;
            ccHidden.appendChild(inp);
        });
    }

    function ccAddUser(u) {
        if (ccSet[u.id]) { ccClose(); ccInput.value = ''; return; }
        ccSet[u.id] = u; renderCcBadges(); renderCcHidden();
        ccInput.value = ''; ccClose();
    }

    function ccClose() {
        ccDrop.style.display = 'none'; ccDrop.innerHTML = ''; ccActive = -1; ccResults = [];
    }

    function ccRenderDrop(data) {
        ccResults = data; ccActive = -1;
        if (!data.length) { ccClose(); return; }
        var html = '';
        data.forEach(function(u, i) {
            var rb = u.role === 'admin' ? '<span class="badge bg-danger" style="font-size:.6rem;">Admin</span>'
                   : u.role === 'agent' ? '<span class="badge bg-primary" style="font-size:.6rem;">Agent</span>'
                   : '<span class="badge bg-secondary" style="font-size:.6rem;">User</span>';
            html += '<div class="mention-item" data-index="' + i + '">'
                  + '<span class="mention-name">' + escH(u.first_name + ' ' + u.last_name) + '</span> '
                  + '<span class="text-muted" style="font-size:.75rem;">' + escH(u.email) + '</span> ' + rb
                  + '</div>';
        });
        ccDrop.innerHTML = html; ccDrop.style.display = 'block';
        ccDrop.querySelectorAll('.mention-item').forEach(function(el) {
            el.addEventListener('mousedown', function(ev) {
                ev.preventDefault(); ccAddUser(data[parseInt(this.dataset.index)]);
            });
        });
    }

    function ccSetActive(idx) {
        ccActive = idx;
        ccDrop.querySelectorAll('.mention-item').forEach(function(el, i) {
            el.classList.toggle('active', i === idx);
        });
    }

    ccInput.addEventListener('input', function() {
        clearTimeout(ccTimer);
        var q = this.value.trim();
        if (q.length < 2) { ccClose(); return; }
        ccTimer = setTimeout(function() {
            fetch('/api/user-search?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); }).then(ccRenderDrop);
        }, 250);
    });

    ccInput.addEventListener('keydown', function(ev) {
        if (ev.key === 'ArrowDown') { ev.preventDefault(); ccSetActive(Math.min(ccActive + 1, ccResults.length - 1)); }
        else if (ev.key === 'ArrowUp') { ev.preventDefault(); ccSetActive(Math.max(ccActive - 1, 0)); }
        else if (ev.key === 'Enter') { ev.preventDefault(); if (ccActive >= 0 && ccResults[ccActive]) ccAddUser(ccResults[ccActive]); }
        else if (ev.key === 'Escape') { ccClose(); }
    });

    document.addEventListener('click', function(ev) {
        if (!ccInput.contains(ev.target) && !ccDrop.contains(ev.target)) ccClose();
    });
})();
</script>

<script type="module">
import {
    ClassicEditor,
    Essentials,
    Heading,
    Bold, Italic, Underline, Strikethrough,
    FontColor, FontBackgroundColor, FontSize,
    Alignment,
    List, ListProperties,
    Link, AutoLink,
    Image, ImageUpload, Base64UploadAdapter,
    ImageCaption, ImageStyle, ImageToolbar, ImageResize,
    Table, TableToolbar, TableProperties, TableCellProperties,
    BlockQuote,
    Code, CodeBlock,
    HorizontalLine,
    Indent, IndentBlock,
    FindAndReplace,
    RemoveFormat
} from 'ckeditor5';

ClassicEditor.create(document.querySelector('#admin-ticket-editor'), {
    plugins: [
        Essentials,
        Heading,
        Bold, Italic, Underline, Strikethrough,
        FontColor, FontBackgroundColor, FontSize,
        Alignment,
        List, ListProperties,
        Link, AutoLink,
        Image, ImageUpload, Base64UploadAdapter,
        ImageCaption, ImageStyle, ImageToolbar, ImageResize,
        Table, TableToolbar, TableProperties, TableCellProperties,
        BlockQuote,
        Code, CodeBlock,
        HorizontalLine,
        Indent, IndentBlock,
        FindAndReplace,
        RemoveFormat
    ],
    toolbar: {
        items: [
            'heading', '|',
            'fontSize', 'fontColor', 'fontBackgroundColor', '|',
            'bold', 'italic', 'underline', 'strikethrough', 'removeFormat', '|',
            'alignment', '|',
            'bulletedList', 'numberedList', 'outdent', 'indent', '|',
            'link', 'insertImage', 'insertTable', 'blockQuote', 'codeBlock', 'horizontalLine', '|',
            'findAndReplace', 'undo', 'redo'
        ],
        shouldNotGroupWhenFull: true
    },
    heading: {
        options: [
            { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
            { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
            { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
            { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
        ]
    },
    image: {
        toolbar: [
            'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|',
            'toggleImageCaption', 'imageTextAlternative', '|',
            'resizeImage'
        ]
    },
    table: {
        contentToolbar: [
            'tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties'
        ]
    },
    initialData: document.getElementById('description').value
}).then(editor => {
    window._adminTicketEditor = editor;

    const form    = document.getElementById('admin-ticket-form');
    const dupBox  = document.getElementById('dup-warning');
    const csrfTok = '<?= e(csrfToken()) ?>';

    function escH(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

    function renderDupMatches(matches) {
        const allIds = matches.map(m => m.ticket_id);
        const headline = matches.length === 1
            ? 'Oops! Someone may have already submitted a ticket for this issue.'
            : 'Oops! There are ' + matches.length + ' open tickets at this branch that look similar to the one you are creating.';

        const items = matches.map(m => {
            const conf = Math.round((m.confidence || 0) * 100);
            const when = m.created_at ? new Date(m.created_at.replace(' ', 'T')).toLocaleString() : '';
            const who  = m.requester ? ' &middot; Reported by ' + escH(m.requester) : '';
            const reason = m.reasoning ? '<div class="small text-muted mt-1">' + escH(m.reasoning) + '</div>' : '';
            return '<div class="border rounded p-2 mb-2 bg-white">'
                 +   '<div class="d-flex justify-content-between align-items-start gap-2">'
                 +     '<div>'
                 +       '<div class="fw-semibold">#' + m.ticket_id + ' &mdash; ' + escH(m.subject) + '</div>'
                 +       '<div class="small text-muted">Status: ' + escH(m.status) + (when ? ' &middot; Opened ' + escH(when) : '') + who + '</div>'
                 +       reason
                 +     '</div>'
                 +     '<span class="badge bg-warning text-dark align-self-start">' + conf + '% match</span>'
                 +   '</div>'
                 +   '<div class="mt-2 d-flex gap-2 flex-wrap">'
                 +     '<button type="button" class="btn btn-sm btn-primary dup-view-details" data-ticket-id="' + m.ticket_id + '">'
                 +       '<i class="bi bi-eye me-1"></i>Click here to see this ticket'
                 +     '</button>'
                 +   '</div>'
                 + '</div>';
        }).join('');

        dupBox.innerHTML =
            '<div class="alert alert-warning border-warning">'
          +   '<div class="d-flex align-items-center gap-2 mb-2">'
          +     '<i class="bi bi-exclamation-triangle-fill"></i>'
          +     '<strong>' + escH(headline) + '</strong>'
          +   '</div>'
          +   '<p class="small mb-2">Please take a moment to review the existing ticket before you create a new one. If it covers the same issue, you can let the assigned team continue with that ticket.</p>'
          +   items
          +   '<div class="d-flex gap-2 flex-wrap mt-2">'
          +     '<button type="button" id="dup-submit-anyway" class="btn btn-sm btn-warning">Create anyway &mdash; This is a Different Issue</button>'
          +     '<button type="button" id="dup-edit" class="btn btn-sm btn-link">Let me edit this ticket first.</button>'
          +   '</div>'
          + '</div>';
        dupBox.style.display = '';
        dupBox.scrollIntoView({ behavior: 'smooth', block: 'center' });

        dupBox.querySelectorAll('.dup-view-details').forEach(btn => {
            btn.addEventListener('click', () => {
                const tid = parseInt(btn.dataset.ticketId, 10) || 0;
                if (tid && typeof window.openDupPreviewModal === 'function') {
                    window.openDupPreviewModal(tid, allIds);
                }
            });
        });

        document.getElementById('dup-submit-anyway').addEventListener('click', () => {
            form.dataset.dupOverride = '1';
            const idsField = document.getElementById('dup_matched_ids');
            if (idsField) idsField.value = allIds.join(',');
            dupBox.style.display = 'none';
            form.submit();
        });
        document.getElementById('dup-edit').addEventListener('click', () => {
            dupBox.style.display = 'none';
            document.getElementById('subject').focus();
        });
    }

    form.addEventListener('submit', async function (e) {
        const data  = editor.getData();
        const text  = data.replace(/<[^>]*>/g, '').trim();
        const errEl = document.getElementById('admin-ticket-editor-error');

        if (!text) {
            e.preventDefault();
            errEl.style.display = '';
            editor.editing.view.focus();
            return;
        }

        errEl.style.display = 'none';
        document.getElementById('description').value = data;

        if (form.dataset.dupOverride === '1') return;

        const typeSel = document.getElementById('type_id');
        const typeId  = typeSel ? parseInt(typeSel.value, 10) : 0;
        if (!typeId) return;

        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        let origLabel = '';
        if (submitBtn) {
            origLabel = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Checking for duplicates&hellip;';
        }

        let proceed = true;
        try {
            const fd = new FormData();
            fd.append('subject',     document.getElementById('subject').value);
            fd.append('description', data);
            fd.append('type_id',     String(typeId));
            const locSel = document.getElementById('location_id');
            if (locSel && locSel.value) fd.append('location_id', locSel.value);

            const res = await fetch('/agent/tickets/check-duplicates', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfTok },
            });
            const json = await res.json();
            if (json && json.ok && Array.isArray(json.matches) && json.matches.length) {
                renderDupMatches(json.matches);
                proceed = false;
            }
        } catch (err) {
            // Non-blocking — let the create go through.
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origLabel;
            }
        }

        if (proceed) {
            form.dataset.dupOverride = '1';
            form.submit();
        }
    });
}).catch(console.error);
</script>