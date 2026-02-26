<?php
$layout       = 'app';
$pageTitle    = 'New Ticket';
$sidebarItems = portalSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Portal', 'url' => '/portal'],
    ['label' => 'My Tickets', 'url' => '/portal/tickets'],
    ['label' => 'New Ticket'],
];
?>
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
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">New Ticket</h2>
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
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="/portal/tickets/create" enctype="multipart/form-data">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="subject" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
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

            <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                <textarea class="form-control" id="description" name="description" rows="5" required
                          placeholder="Please describe your issue in detail..."><?= e(old('description')) ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="type_id" class="form-label fw-semibold">Ticket Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="type_id" name="type_id" required>
                        <option value="">— Select type —</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= old('type_id') == $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold"><?= label('location.singular') ?></label>
                    <input type="text" class="form-control" value="<?= e($userLocationName ?: 'Not set') ?>" readonly disabled>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="priority_id" class="form-label fw-semibold">Priority</label>
                    <select class="form-select" id="priority_id" name="priority_id">
                        <option value="">— Select priority —</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= old('priority_id') == $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Tags</label>
                <div id="tagContainer" class="d-flex flex-wrap gap-1 align-items-center form-control" style="min-height:38px;cursor:text;" onclick="document.getElementById('tagInput').focus();">
                    <input type="text" id="tagInput" class="border-0 flex-grow-1" style="outline:none;min-width:120px;background:transparent;"
                           placeholder="Type #tag and press Enter">
                </div>
                <div class="form-text">Type <strong>#</strong> followed by a tag name, then press Enter to add it.</div>
            </div>

            <?php if (!empty($customFields)): ?>
            <hr class="my-3">
            <?php foreach ($customFields as $cf):
                $cfKey = 'field_' . $cf['id'];
                $cfOpts = $fieldOptions[$cf['id']] ?? [];
            ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    <?= e($cf['label']) ?>
                    <?php if ($cf['is_required']): ?><span class="text-danger ms-1">*</span><?php endif; ?>
                </label>

                <?php if ($cf['field_type'] === 'text'): ?>
                <input type="text" class="form-control" name="<?= e($cfKey) ?>"
                       placeholder="<?= e($cf['placeholder'] ?? '') ?>"
                       value="<?= e(old($cfKey)) ?>"
                       <?= $cf['is_required'] ? 'required' : '' ?>>

                <?php elseif ($cf['field_type'] === 'textarea'): ?>
                <textarea class="form-control" name="<?= e($cfKey) ?>" rows="3"
                          placeholder="<?= e($cf['placeholder'] ?? '') ?>"
                          <?= $cf['is_required'] ? 'required' : '' ?>><?= e(old($cfKey)) ?></textarea>

                <?php elseif ($cf['field_type'] === 'checkbox'): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="<?= e($cfKey) ?>" value="1"
                           id="<?= e($cfKey) ?>"
                           <?= old($cfKey) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($cfKey) ?>">Yes</label>
                </div>

                <?php elseif ($cf['field_type'] === 'dropdown'): ?>
                <select class="form-select" name="<?= e($cfKey) ?>"
                        <?= $cf['is_required'] ? 'required' : '' ?>>
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
                       value="<?= e(old($cfKey)) ?>"
                       <?= $cf['is_required'] ? 'required' : '' ?>>

                <?php elseif ($cf['field_type'] === 'number'): ?>
                <input type="number" step="1" class="form-control" name="<?= e($cfKey) ?>"
                       placeholder="<?= e($cf['placeholder'] ?? '') ?>"
                       value="<?= e(old($cfKey)) ?>"
                       <?= $cf['is_required'] ? 'required' : '' ?>>

                <?php elseif ($cf['field_type'] === 'decimal'): ?>
                <input type="number" step="0.01" class="form-control" name="<?= e($cfKey) ?>"
                       placeholder="<?= e($cf['placeholder'] ?? '') ?>"
                       value="<?= e(old($cfKey)) ?>"
                       <?= $cf['is_required'] ? 'required' : '' ?>>

                <?php elseif ($cf['field_type'] === 'dependent'):
                    $config  = $cf['config'] ? (is_string($cf['config']) ? json_decode($cf['config'], true) : $cf['config']) : [];
                    $levels  = (int) ($config['levels']   ?? 3);
                    $l1Label = $config['l1_label'] ?? 'Category';
                    $l2Label = $config['l2_label'] ?? 'Subcategory';
                    $l3Label = $config['l3_label'] ?? 'Item';
                    // Build hierarchy: level1 items (no parent), keyed by id
                    $l1Opts = array_filter($cfOpts, fn($o) => !$o['parent_option_id']);
                ?>
                <div class="row g-2" id="dep_wrap_<?= (int) $cf['id'] ?>">
                    <div class="col-md-4">
                        <label class="form-label small"><?= e($l1Label) ?></label>
                        <select class="form-select form-select-sm dep-l1"
                                name="<?= e($cfKey) ?>_l1"
                                data-field="<?= (int) $cf['id'] ?>"
                                <?= $cf['is_required'] ? 'required' : '' ?>>
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
            <?php endforeach; ?>
            <?php endif; ?>

            <div class="mb-3">
                <label for="attachments" class="form-label fw-semibold">Attachments</label>
                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                <div class="form-text">
                    Max <?= UPLOAD_MAX_SIZE / 1024 / 1024 ?>MB per file. Allowed: PDF, images, Office documents, text, ZIP.
                </div>
            </div>

            <!-- Hidden fields for browser/OS auto-detection -->
            <input type="hidden" id="browser_info" name="browser_info" value="">
            <input type="hidden" id="os_info" name="os_info" value="">

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-send me-1"></i>Submit Ticket
                </button>
                <a href="/portal/tickets" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
<?php if (!empty($sharedTemplates)): ?>
// Template picker
(function() {
    const PORTAL_TEMPLATES = <?= json_encode($tplData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.getElementById('portalTemplateSelect').addEventListener('change', function () {
        const tpl = PORTAL_TEMPLATES[this.value];
        if (!tpl) return;
        if (tpl.subject)     document.getElementById('subject').value     = tpl.subject;
        if (tpl.body)        document.getElementById('description').value = tpl.body;
        if (tpl.type_id)     document.getElementById('type_id').value     = tpl.type_id;
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
})();

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
