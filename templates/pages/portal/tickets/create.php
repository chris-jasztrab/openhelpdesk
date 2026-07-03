<?php
$layout       = 'app';
$pageTitle    = label('portal.action.new', 'New Help Request');
$sidebarItems = portalSidebar('tickets');
$breadcrumbs  = [
    ['label' => label('portal.nav.help', 'Help'), 'url' => '/portal'],
    ['label' => label('portal.request.my_plural', 'My Requests'), 'url' => '/portal/tickets'],
    ['label' => label('portal.action.new', 'New Help Request')],
];
?>
<link rel="stylesheet" href="/assets/vendor/ckeditor5/ckeditor5.css">
<script type="importmap">
{"imports":{"ckeditor5":"/assets/vendor/ckeditor5/ckeditor5.js","ckeditor5/":"/assets/vendor/ckeditor5/"}}
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
<?php if (!empty($sharedTemplates)):
$tplData = [];
foreach ($sharedTemplates as $t) {
    $tplData[$t['id']] = [
        'subject'     => $t['subject'],
        'body'        => $t['body'],
        'type_id'     => $t['type_id'],
        'priority_id' => $t['priority_id'],
    ];
}
endif; ?>
<?php if (!empty($embedMode)): ?>
<div class="alert alert-info py-2 px-3 mb-3 small d-flex align-items-center gap-2">
    <i class="bi bi-eye-fill"></i>
    <span><strong>Preview mode</strong> — this is a live render of the ticket form for the form-builder. Submission is disabled.</span>
</div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= e(label('portal.action.new', 'New Help Request')) ?></h2>
    <?php if (empty($embedMode)): ?>
    <div class="d-flex align-items-center gap-2">
        <?php if (!empty($sharedTemplates)): ?>
        <select id="portalTemplateSelect" class="form-select form-select-sm" style="width:auto;max-width:200px;" title="Start from a template">
            <option value="">Template…</option>
            <?php foreach ($sharedTemplates as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <a href="/portal/tickets" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="/portal/tickets/create" enctype="multipart/form-data" id="portal-ticket-form">
            <?= csrfField() ?>

            <div id="ticketDraftNote" class="alert alert-info d-flex align-items-center justify-content-between py-2 px-3 mb-3 small d-none">
                <span><i class="bi bi-arrow-counterclockwise me-1"></i>Restored your unsent ticket draft.</span>
                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" id="ticketDraftDiscard">Discard draft</button>
            </div>

            <div class="mb-3" id="tour-portal-subject">
                <label for="subject" class="form-label fw-semibold"><?= e(getSetting('sys_field_label_subject', 'Subject')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="subject" name="subject"
                       value="<?= e(old('subject')) ?>" required
                       placeholder="Brief summary of your issue" autocomplete="off">
                <!-- KB suggestions -->
                <div id="kb-suggestions" class="mt-2" style="display:none;">
                    <div class="card border-info">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="fw-semibold text-info"><i class="bi bi-lightbulb me-1"></i>Related KB articles that may help:</small>
                                <button type="button" class="btn-close btn-close-sm" id="kb-dismiss" aria-label="Close" style="font-size:.6rem;"></button>
                            </div>
                            <div id="kb-suggestion-list"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3" id="tour-portal-description">
                <label class="form-label fw-semibold"><?= e(getSetting('sys_field_label_description', 'Description')) ?> <span class="text-danger">*</span></label>
                <div id="portal-ticket-editor"></div>
                <input type="hidden" id="description" name="description" value="<?= e(old('description')) ?>">
                <div id="portal-ticket-editor-error" class="text-danger small mt-1" style="display:none;">Description is required.</div>
            </div>

            <!-- Duplicate-ticket warning (filled by JS when AI finds matches) -->
            <div id="dup-warning" class="mb-3" style="display:none;"></div>
            <?php
            $dupPreviewEndpoint = '/portal/tickets/dup-preview';
            $dupViewBase        = '/portal/tickets';
            include ROOT_DIR . '/templates/partials/dup-preview-modal.php';
            include ROOT_DIR . '/templates/partials/ticket-submit-progress.php';
            ?>

            <?php
            $portalMode = true;

            // Resolve the initial ticket type (deep-link, query string, or
            // repopulation after a failed POST).
            $initialTypeIdRaw = old('type_id');
            if ($initialTypeIdRaw === '' && !empty($preselectedTypeId)) {
                $initialTypeIdRaw = (string) $preselectedTypeId;
            }
            $initialTypeId = ctype_digit((string) $initialTypeIdRaw) ? (int) $initialTypeIdRaw : 0;
            $initialLayout = $formLayouts[$initialTypeId] ?? [];

            // Initial visibility lookup: 'system|priority' => 'required', etc.
            $initialVis = [];
            foreach ($initialLayout as $r) {
                $initialVis[$r['kind'] . '|' . $r['key']] = $r['visibility'];
            }
            $visOf = function(string $kind, string $key) use ($initialVis, $initialTypeId): string {
                // Field not in this type's layout → 'absent' (hidden + skipped on submit)
                if (!$initialTypeId) return 'optional';   // no type chosen yet → preview as optional
                return $initialVis[$kind . '|' . $key] ?? 'absent';
            };

            // ─── Ticket type picker (always rendered at the top, not draggable) ───
            $selectedTypeId = (string) $initialTypeId ?: '';
            ?>
            <div class="row g-3 mb-3" id="tour-portal-type">
                <div class="col-md-6">
                    <label for="type_id" class="form-label fw-semibold"><?= e(getSetting('sys_field_label_ticket_type', 'Ticket Type')) ?> <span class="text-danger">*</span></label>
                    <select class="form-select" id="type_id" name="type_id" required>
                        <option value="">— Select type —</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $selectedTypeId == $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php
            // ─── Dynamic fields: rendered once each, reordered + toggled by JS ───
            // The order matters only for the no-type-selected state; per-type
            // ordering is applied by the script below.
            $dynamicSystemOrder = ['location', 'priority', 'tags', 'attachments'];

            // Union of every custom field used by any ticket type, deduped.
            $renderedCustomIds = [];
            foreach ($customFields as $cf) {
                $renderedCustomIds[(int) $cf['id']] = $cf;
            }
            ?>

            <div id="dynamic-fields">
                <?php foreach ($dynamicSystemOrder as $sysKey):
                    $v = $visOf('system', $sysKey);
                    $isRequired = $v === 'required';
                    $isAbsent   = $v === 'absent' || $v === 'hidden';
                    $wrapStyle  = $isAbsent ? 'style="display:none;"' : '';
                ?>
                <?php if ($sysKey === 'location'): ?>
                <div class="row g-3 mb-3 dynamic-field-wrap" data-field-kind="system" data-field-key="location" <?= $wrapStyle ?>>
                    <div class="col-md-6">
                        <label for="location_id" class="form-label fw-semibold">
                            <?= label('location.singular') ?>
                            <span class="text-danger field-required-star" <?= $isRequired || $userHasNoLocation ? '' : 'style="display:none;"' ?>>*</span>
                        </label>
                        <select class="form-select" id="location_id" name="location_id">
                            <option value="">— Select a <?= label('location.singular') ?> —</option>
                            <?php foreach ($locations as $loc):
                                $sel = (old('location_id') != '')
                                    ? (old('location_id') == $loc['id'])
                                    : ((int) $loc['id'] === (int) $userLocationId);
                            ?>
                            <option value="<?= (int) $loc['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                <?= e($loc['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php elseif ($sysKey === 'priority'): ?>
                <div class="row g-3 mb-3 dynamic-field-wrap" data-field-kind="system" data-field-key="priority" <?= $wrapStyle ?>>
                    <div class="col-md-6">
                        <label for="priority_id" class="form-label fw-semibold">
                            <?= e(label('portal.field.priority_label', 'How urgent is this?')) ?>
                            <span class="text-danger field-required-star" <?= $isRequired ? '' : 'style="display:none;"' ?>>*</span>
                        </label>
                        <select class="form-select" id="priority_id" name="priority_id" <?= $isRequired ? 'required' : '' ?>>
                            <option value="">— Let our team decide —</option>
                            <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= old('priority_id') == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text field-help-text" <?= $isRequired ? 'style="display:none;"' : '' ?>>
                            <?= e(label('portal.field.priority_help', 'Pick a level if you know — otherwise leave blank and our team will set it.')) ?>
                        </div>
                    </div>
                </div>
                <?php elseif ($sysKey === 'tags'): ?>
                <?php if (getSetting('tags_enabled', '1') === '1'): ?>
                <div class="mb-3 dynamic-field-wrap" data-field-kind="system" data-field-key="tags" <?= $wrapStyle ?>>
                    <label class="form-label fw-semibold">
                        <?= e(getSetting('sys_field_label_tags', 'Tags')) ?>
                        <span class="text-danger field-required-star" <?= $isRequired ? '' : 'style="display:none;"' ?>>*</span>
                    </label>
                    <div id="tagContainer" class="d-flex flex-wrap gap-1 align-items-center form-control" style="min-height:38px;cursor:text;" onclick="document.getElementById('tagInput').focus();">
                        <input type="text" id="tagInput" class="border-0 flex-grow-1" style="outline:none;min-width:120px;background:transparent;"
                               placeholder="Type #tag and press Enter">
                    </div>
                    <div class="form-text">Type <strong>#</strong> followed by a tag name, then press Enter to add it.</div>
                </div>
                <?php endif; ?>
                <?php elseif ($sysKey === 'attachments'): ?>
                <div class="mb-3 dynamic-field-wrap" data-field-kind="system" data-field-key="attachments" <?= $wrapStyle ?>>
                    <label for="attachments" class="form-label fw-semibold"><?= e(getSetting('sys_field_label_attachments', 'Attachments')) ?></label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                    <div class="form-text">
                        Max <?= UPLOAD_MAX_SIZE / 1024 / 1024 ?>MB per file. Allowed: PDF, images, Office documents, text, ZIP.
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>

                <?php foreach ($renderedCustomIds as $cf):
                    $cfKey  = 'field_' . $cf['id'];
                    $cfOpts = $fieldOptions[$cf['id']] ?? [];
                    $v = $visOf('custom', (string) $cf['id']);
                    $isRequired = $v === 'required';
                    $isAbsent   = $v === 'absent' || $v === 'hidden';
                ?>
                <div class="dynamic-field-wrap custom-field-wrap"
                     data-field-kind="custom"
                     data-field-key="<?= (int) $cf['id'] ?>"
                     data-field-id="<?= (int) $cf['id'] ?>"
                     <?= $isAbsent ? 'style="display:none;"' : '' ?>>
                    <?php
                    // The shared custom-field partial expects $cf, $cfKey, $cfOpts.
                    // Pass current required state via $cfRequired so the asterisk
                    // and `required` attribute are correct on first paint.
                    $cfRequired = $isRequired;
                    include ROOT_DIR . '/templates/partials/custom-field-input.php';
                    ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Hidden fields for browser/OS auto-detection -->
            <input type="hidden" id="browser_info" name="browser_info" value="">
            <input type="hidden" id="os_info" name="os_info" value="">
            <input type="hidden" id="dup_matched_ids" name="_dup_matched_ids" value="">

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white"
                        style="background:var(--ld-primary);<?= !empty($embedMode) ? 'opacity:.55;cursor:not-allowed;' : '' ?>"
                        <?= !empty($embedMode) ? 'disabled aria-disabled="true" title="Submission disabled in preview"' : '' ?>>
                    <i class="bi bi-send me-1"></i><?= e(label('portal.action.submit', 'Submit Request')) ?>
                </button>
                <?php if (empty($embedMode)): ?>
                <a href="/portal/tickets" class="btn btn-outline-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if (!empty($embedMode)): ?>
        <script>
            // Block any keyboard / programmatic submit in preview mode
            document.getElementById('portal-ticket-form').addEventListener('submit', function (e) {
                e.preventDefault(); e.stopPropagation();
            });
        </script>
        <?php endif; ?>
    </div>
</div>

<script>
// ── Per-type form layout: reorder + show/hide + required toggling ──
(function() {
    var formLayouts = <?= json_encode($formLayouts) ?>;
    var typeSelect  = document.getElementById('type_id');
    var dynRoot     = document.getElementById('dynamic-fields');
    if (!typeSelect || !dynRoot) return;

    function setRequired(wrap, required) {
        // Toggle the visible asterisk
        wrap.querySelectorAll('.field-required-star').forEach(function(el) {
            el.style.display = required ? '' : 'none';
        });
        // Hide the optional-priority help line when required
        wrap.querySelectorAll('.field-help-text').forEach(function(el) {
            el.style.display = required ? 'none' : '';
        });
        // Toggle the HTML `required` attribute on all inputs/selects/textareas
        wrap.querySelectorAll('input, select, textarea').forEach(function(inp) {
            if (inp.type === 'hidden' || inp.type === 'file') return;
            if (required) inp.setAttribute('required', '');
            else          inp.removeAttribute('required');
        });
        // CC field uses data-required, not the HTML attribute
        wrap.querySelectorAll('.cc-field-input').forEach(function(inp) {
            if (required) inp.dataset.required = '1';
            else          delete inp.dataset.required;
        });
    }

    function applyLayout() {
        var selectedType = parseInt(typeSelect.value) || 0;
        var layout = formLayouts[selectedType] || [];

        // Build a map: 'kind|key' → visibility for the selected type
        var visByKey = {};
        layout.forEach(function(row) { visByKey[row.kind + '|' + row.key] = row.visibility; });

        // Pass 1: show/hide each wrap + toggle required
        var wraps = dynRoot.querySelectorAll('.dynamic-field-wrap');
        wraps.forEach(function(wrap) {
            var kind = wrap.dataset.fieldKind;
            var key  = wrap.dataset.fieldKey;
            var v    = visByKey[kind + '|' + key];
            if (!selectedType) {
                // No type chosen yet — show everything as optional preview
                wrap.style.display = '';
                setRequired(wrap, false);
                return;
            }
            if (v === undefined || v === 'hidden') {
                wrap.style.display = 'none';
                setRequired(wrap, false);  // unblock form submit
                return;
            }
            wrap.style.display = '';
            setRequired(wrap, v === 'required');
        });

        // Pass 2: reorder by layout sort_order (skip if no type selected)
        if (!selectedType) return;
        layout
            .slice()
            .sort(function(a, b) { return a.sort_order - b.sort_order; })
            .forEach(function(row) {
                var sel = '.dynamic-field-wrap[data-field-kind="' + row.kind + '"][data-field-key="' + row.key + '"]';
                var el = dynRoot.querySelector(sel);
                if (el) dynRoot.appendChild(el);  // appendChild moves, doesn't clone
            });
    }

    typeSelect.addEventListener('change', applyLayout);
    applyLayout();
})();

<?php if (!empty($sharedTemplates)): ?>
// Template picker
(function() {
    const PORTAL_TEMPLATES = <?= json_encode($tplData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.getElementById('portalTemplateSelect').addEventListener('change', function () {
        const tpl = PORTAL_TEMPLATES[this.value];
        if (!tpl) return;
        if (tpl.subject)     document.getElementById('subject').value     = tpl.subject;
        if (tpl.body && window._portalTicketEditor) window._portalTicketEditor.setData(tpl.body);
        if (tpl.type_id) {
            document.getElementById('type_id').value = tpl.type_id;
            document.getElementById('type_id').dispatchEvent(new Event('change'));
        }
        if (tpl.priority_id) {
            const priSel = document.getElementById('priority_id');
            if (priSel) priSel.value = tpl.priority_id;
        }
        document.getElementById('subject').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
})();
<?php endif; ?>
// Auto-detect browser and OS info
(function() {
    var ua = navigator.userAgent;
    document.getElementById('browser_info').value = ua;

    var os = 'Unknown';
    if (ua.indexOf('Win') !== -1) os = 'Windows';
    else if (ua.indexOf('Mac') !== -1) os = 'macOS';
    else if (ua.indexOf('Linux') !== -1) os = 'Linux';
    else if (ua.indexOf('Android') !== -1) os = 'Android';
    else if (ua.indexOf('iPhone') !== -1 || ua.indexOf('iPad') !== -1) os = 'iOS';
    document.getElementById('os_info').value = os + ' (' + navigator.platform + ')';
})();

// KB article suggestions on subject input
(function() {
    var subjectInput   = document.getElementById('subject');
    var suggestionsBox = document.getElementById('kb-suggestions');
    var suggestionList = document.getElementById('kb-suggestion-list');
    var dismissBtn     = document.getElementById('kb-dismiss');
    var timer          = null;
    var dismissed      = false;

    subjectInput.addEventListener('input', function() {
        if (dismissed) return;
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 3) {
            suggestionsBox.style.display = 'none';
            return;
        }
        timer = setTimeout(function() {
            fetch('/portal/kb/search?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (dismissed) return;
                    if (data.length === 0) {
                        suggestionsBox.style.display = 'none';
                        return;
                    }
                    var html = '';
                    data.slice(0, 5).forEach(function(a) {
                        html += '<a href="/portal/kb/articles/' + encodeURIComponent(a.slug)
                            + '" target="_blank" class="d-block text-decoration-none py-1">'
                            + '<small><i class="bi bi-file-text me-1"></i>' + escHtml(a.title) + '</small>'
                            + '</a>';
                    });
                    suggestionList.innerHTML = html;
                    suggestionsBox.style.display = '';
                });
        }, 400);
    });

    dismissBtn.addEventListener('click', function() {
        dismissed = true;
        suggestionsBox.style.display = 'none';
    });

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();

<?php if (getSetting('tags_enabled', '1') === '1'): ?>
// Tag input
(function() {
    var input = document.getElementById('tagInput');
    var container = document.getElementById('tagContainer');
    var tags = [];

    function addTag(name) {
        name = name.replace(/^#+/, '').replace(/[^a-zA-Z0-9_\-\s]/g, '').trim().toLowerCase();
        if (!name || tags.indexOf(name) !== -1) return;
        tags.push(name);

        var badge = document.createElement('span');
        badge.className = 'badge bg-light text-dark border d-flex align-items-center gap-1';
        badge.innerHTML = '<i class="bi bi-hash"></i>' + escTag(name)
            + '<input type="hidden" name="tags[]" value="' + escTag(name) + '">'
            + '<button type="button" class="btn-close" style="font-size:.5rem;" aria-label="Remove"></button>';
        badge.querySelector('.btn-close').addEventListener('click', function() {
            tags.splice(tags.indexOf(name), 1);
            badge.remove();
        });
        container.insertBefore(badge, input);
    }

    function escTag(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    input.addEventListener('keydown', function(ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            var val = this.value.trim();
            if (val) {
                addTag(val);
                this.value = '';
            }
        }
        if (ev.key === 'Backspace' && this.value === '' && tags.length > 0) {
            var last = tags[tags.length - 1];
            tags.pop();
            var badges = container.querySelectorAll('.badge');
            if (badges.length) badges[badges.length - 1].remove();
        }
    });

    // Draft autosave hooks: tag state lives in this closure, so the draft
    // glue reads/rebuilds it through these.
    window._tagDraftGet = function () { return tags.slice(); };
    window._tagDraftAdd = addTag;
})();
<?php endif; ?>

<?php
$ccFields = array_filter($customFields, fn($f) => $f['field_type'] === 'cc');
?>
<?php if (!empty($ccFields)): ?>
// CC field autocomplete
(function() {
    function initCcField(fieldId, isRequired) {
        var input  = document.getElementById('cc_input_' + fieldId);
        var drop   = document.getElementById('cc_drop_' + fieldId);
        var badges = document.getElementById('cc_badges_' + fieldId);
        var hidden = document.getElementById('cc_hidden_' + fieldId);
        var ccSet  = {};
        var timer  = null;
        var results = [];
        var active  = -1;

        function escH(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function renderBadges() {
            badges.innerHTML = '';
            Object.values(ccSet).forEach(function(u) {
                var b = document.createElement('span');
                b.className = 'badge bg-secondary d-inline-flex align-items-center gap-1 py-1 px-2';
                b.innerHTML = escH(u.first_name + ' ' + u.last_name)
                            + ' <span class="opacity-75 small">&lt;' + escH(u.email) + '&gt;</span>'
                            + ' <button type="button" class="btn-close btn-close-white ms-1" style="font-size:.55rem;" aria-label="Remove" data-uid="' + u.id + '"></button>';
                b.querySelector('.btn-close').addEventListener('click', function() {
                    delete ccSet[u.id]; renderBadges(); renderHidden();
                });
                badges.appendChild(b);
            });
            // Required validation: if required and no users selected, mark input invalid
            if (isRequired) {
                input.required = Object.keys(ccSet).length === 0;
            }
        }

        function renderHidden() {
            hidden.innerHTML = '';
            Object.keys(ccSet).forEach(function(id) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'cc_field_' + fieldId + '[]';
                inp.value = id;
                hidden.appendChild(inp);
            });
        }

        function addUser(u) {
            if (ccSet[u.id]) { close(); input.value = ''; return; }
            ccSet[u.id] = u; renderBadges(); renderHidden();
            input.value = ''; close();
        }

        function close() {
            drop.style.display = 'none'; drop.innerHTML = ''; active = -1; results = [];
        }

        function renderDrop(data) {
            results = data; active = -1;
            if (!data.length) { close(); return; }
            var html = '';
            data.forEach(function(u, i) {
                html += '<div class="mention-item" data-index="' + i + '">'
                      + '<span class="mention-name">' + escH(u.first_name + ' ' + u.last_name) + '</span> '
                      + '<span class="text-muted" style="font-size:.75rem;">' + escH(u.email) + '</span>'
                      + '</div>';
            });
            drop.innerHTML = html; drop.style.display = 'block';
            drop.querySelectorAll('.mention-item').forEach(function(el) {
                el.addEventListener('mousedown', function(ev) {
                    ev.preventDefault(); addUser(data[parseInt(this.dataset.index)]);
                });
            });
        }

        function setActive(idx) {
            active = idx;
            drop.querySelectorAll('.mention-item').forEach(function(el, i) {
                el.classList.toggle('active', i === idx);
            });
        }

        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 2) { close(); return; }
            timer = setTimeout(function() {
                fetch('/api/cc-search?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); }).then(renderDrop);
            }, 250);
        });

        input.addEventListener('keydown', function(ev) {
            if (ev.key === 'ArrowDown') { ev.preventDefault(); setActive(Math.min(active + 1, results.length - 1)); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); setActive(Math.max(active - 1, 0)); }
            else if (ev.key === 'Enter') { ev.preventDefault(); if (active >= 0 && results[active]) addUser(results[active]); }
            else if (ev.key === 'Escape') { close(); }
        });

        document.addEventListener('click', function(ev) {
            if (!input.contains(ev.target) && !drop.contains(ev.target)) close();
        });

        if (isRequired) { input.required = true; }

        // Draft autosave hooks: per-field CC state lives in this closure, so
        // the draft glue reads/rebuilds it through this registry.
        window._ccFieldDraftHooks = window._ccFieldDraftHooks || {};
        window._ccFieldDraftHooks[fieldId] = {
            get: function () {
                return Object.values(ccSet).map(function(u) {
                    return { id: u.id, first_name: u.first_name, last_name: u.last_name, email: u.email };
                });
            },
            add: addUser,
        };
    }

    <?php foreach ($ccFields as $ccf): ?>
    // Required state is now per-type and managed by the layout driver above
    // (data-required attribute), so this init no longer takes a required arg.
    initCcField(<?= (int) $ccf['id'] ?>, false);
    <?php endforeach; ?>
})();
<?php endif; ?>

<?php if (!empty($customFields)): ?>
// Dependent field cascading dropdowns

(function() {
    var allOptions = <?= json_encode(array_map(function($id) use ($fieldOptions) {
        return $fieldOptions[$id] ?? [];
    }, array_combine(
        array_column(array_filter($customFields, fn($f) => $f['field_type'] === 'dependent'), 'id'),
        array_column(array_filter($customFields, fn($f) => $f['field_type'] === 'dependent'), 'id')
    ))) ?>;

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
</script>

<script src="/assets/js/ticket-draft.js"></script>
<script src="/assets/js/undo-send.js"></script>
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

ClassicEditor.create(document.querySelector('#portal-ticket-editor'), {
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
    window._portalTicketEditor = editor;

    const form     = document.getElementById('portal-ticket-form');
    const dupBox   = document.getElementById('dup-warning');
    const csrfTok  = '<?= e(csrfToken()) ?>';

    // Undo send: every real submit funnels through here so the countdown
    // toast can hold it. The expiry send is the native form.submit(), which
    // bypasses the submit listener — so the duplicate check never re-runs.
    const UNDO_SECONDS = <?= undoSendSeconds() ?>;
    function submitWithUndo() {
        if (!(window.UndoSend && UNDO_SECONDS > 0)) { form.submit(); return; }
        // The countdown toast replaces the cycling progress phrases.
        const btn = form.querySelector('button[type="submit"]');
        if (btn && btn._submitProgressStop) btn._submitProgressStop();
        UndoSend.hold(form, { seconds: UNDO_SECONDS, label: 'Submitting request' });
    }
    // For paths that submit via the browser's default action.
    function holdDefaultSubmit(e) {
        if (e.defaultPrevented || !(window.UndoSend && UNDO_SECONDS > 0)) return;
        e.preventDefault();
        submitWithUndo();
    }

    function escH(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

    function renderDupMatches(matches) {
        const allIds = matches.map(m => m.ticket_id);
        const headline = matches.length === 1
            ? 'Oops! It looks like someone else might have already submitted a ticket for this issue.'
            : 'Oops! It looks like someone else might have already submitted a ticket for this issue. We found ' + matches.length + ' open requests at your branch that look similar.';

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
          +   '<p class="small mb-2">Please take a moment to review the existing ticket before you submit a new one. If your issue is the same, you can leave it to the team that is already working on it.</p>'
          +   items
          +   '<div class="d-flex gap-2 flex-wrap mt-2">'
          +     '<button type="button" id="dup-submit-anyway" class="btn btn-sm btn-warning">Create anyway &mdash; This is a Different Issue</button>'
          +     '<button type="button" id="dup-edit" class="btn btn-sm btn-link">Let me edit my request first.</button>'
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
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) window.startTicketSubmitProgress(submitBtn);
            submitWithUndo();
        });
        document.getElementById('dup-edit').addEventListener('click', () => {
            dupBox.style.display = 'none';
            document.getElementById('subject').focus();
        });
    }

    form.addEventListener('submit', async function (e) {
        const data  = editor.getData();
        const text  = data.replace(/<[^>]*>/g, '').trim();
        const errEl = document.getElementById('portal-ticket-editor-error');

        if (!text) {
            e.preventDefault();
            errEl.style.display = '';
            editor.editing.view.focus();
            return;
        }

        errEl.style.display = 'none';
        document.getElementById('description').value = data;

        // User already chose "submit anyway" — let it through.
        if (form.dataset.dupOverride === '1') { holdDefaultSubmit(e); return; }

        const typeSel = document.getElementById('type_id');
        const typeId  = typeSel ? parseInt(typeSel.value, 10) : 0;
        if (!typeId) { holdDefaultSubmit(e); return; } // type is required by the form anyway

        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const progress  = submitBtn ? window.startTicketSubmitProgress(submitBtn) : { stop: function () {} };

        let proceed = true;
        try {
            const fd = new FormData();
            fd.append('subject',     document.getElementById('subject').value);
            fd.append('description', data);
            fd.append('type_id',     String(typeId));
            const locSel = document.getElementById('location_id');
            if (locSel && locSel.value) fd.append('location_id', locSel.value);

            const res = await fetch('/portal/tickets/check-duplicates', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfTok },
            });
            const json = await res.json();
            if (json && json.ok && Array.isArray(json.matches) && json.matches.length) {
                progress.stop();
                renderDupMatches(json.matches);
                proceed = false;
            }
        } catch (err) {
            // Non-blocking: if the dup check fails, let the user submit anyway.
        }

        if (proceed) {
            // Keep the cycling running while the synchronous form post happens
            // — the page will navigate away and naturally clear it. Don't
            // call progress.stop() here or the user sees the original label
            // for a flash before navigation.
            form.dataset.dupOverride = '1';
            submitWithUndo();
        }
    });

    <?php if (empty($embedMode)): ?>
    // ── Draft autosave (server-side) ──────────────────────────────────────
    // Persist the half-written ticket to ticket_drafts so an accidental
    // close (or "I'll finish this later") comes back on the next visit.
    // The create handler deletes the draft once the ticket is submitted.
    // (Skipped in the form-builder's embed preview — typing there is not a
    // real ticket and must not touch the previewer's saved draft.)
    const subjectEl = document.getElementById('subject');

    function draftExtras() {
        const extras = {};
        if (window._tagDraftGet) {
            const tags = window._tagDraftGet();
            if (tags.length) extras.tags = tags;
        }
        if (window._ccFieldDraftHooks) {
            const cc = {};
            Object.keys(window._ccFieldDraftHooks).forEach(fid => {
                const users = window._ccFieldDraftHooks[fid].get();
                if (users.length) cc[fid] = users;
            });
            if (Object.keys(cc).length) extras.ccFields = cc;
        }
        return Object.keys(extras).length ? extras : null;
    }

    function applyExtras(extras) {
        if (extras.tags && window._tagDraftAdd) extras.tags.forEach(t => window._tagDraftAdd(t));
        if (extras.ccFields && window._ccFieldDraftHooks) {
            Object.keys(extras.ccFields).forEach(fid => {
                const hook = window._ccFieldDraftHooks[fid];
                if (hook) extras.ccFields[fid].forEach(u => hook.add(u));
            });
        }
    }

    const ticketDraft = TicketDraft.init({
        context:   'portal_create',
        form:      form,
        exclude:   ['description', 'tags[]', 'browser_info', 'os_info'<?php
            foreach ($ccFields as $ccf) { echo ", 'cc_field_" . (int) $ccf['id'] . "[]'"; }
        ?>],
        getHtml:   () => editor.getData(),
        setHtml:   html => editor.setData(html),
        getExtras: draftExtras,
        setExtras: applyExtras,
        isEmpty:   () => subjectEl.value.trim() === '' && !TicketDraft.textOf(editor.getData()),
        noteEl:      document.getElementById('ticketDraftNote'),
        discardBtn:  document.getElementById('ticketDraftDiscard'),
        statusAnchor: document.getElementById('description'),
        // Restored tag/CC badges and cascaded fields are easiest to unwind
        // by reloading — the draft row is already deleted (keepalive).
        onDiscarded: () => window.location.reload(),
    });
    ticketDraft.watchEditor(editor);
    <?php endif; ?>
}).catch(console.error);
</script>
