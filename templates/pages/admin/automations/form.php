<?php
$layout       = 'app';
$isEdit       = !empty($editing);
$pageTitle    = ($isEdit ? 'Edit' : 'Create') . ' Automation – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Automations', 'url' => '/admin/settings/automations'],
    ['label' => $isEdit ? 'Edit' : 'Create'],
];

$action = $isEdit
    ? "/admin/settings/automations/{$editing['id']}/edit"
    : '/admin/settings/automations/create';

$statusOptions = [
    'open'                   => 'Open',
    'in_progress'            => 'In Progress',
    'pending'                => 'Pending',
    'waiting_on_customer'    => 'Waiting on Customer',
    'waiting_on_third_party' => 'Waiting on Third Party',
    'resolved'               => 'Resolved',
    'closed'                 => 'Closed',
];

// Build JSON maps for JavaScript to populate value dropdowns
$fieldOptions = [
    'type_id'     => array_map(fn($t) => ['id' => (string) $t['id'], 'label' => $t['name']], $types),
    'priority_id' => array_map(fn($p) => ['id' => (string) $p['id'], 'label' => $p['name']], $priorities),
    'status'      => array_map(fn($k, $v) => ['id' => $k, 'label' => $v], array_keys($statusOptions), array_values($statusOptions)),
    'location_id' => array_map(fn($l) => ['id' => (string) $l['id'], 'label' => $l['name']], $locations),
    'group_id'    => array_map(fn($g) => ['id' => (string) $g['id'], 'label' => $g['name']], $groups),
    'assigned_to' => array_map(fn($a) => ['id' => (string) $a['id'], 'label' => $a['first_name'] . ' ' . $a['last_name']], $agents),
];

$actionOptions = [
    'set_group'       => $fieldOptions['group_id'],
    'set_assigned_to' => $fieldOptions['assigned_to'],
    'set_priority'    => $fieldOptions['priority_id'],
    'set_status'      => $fieldOptions['status'],
    'add_tag'         => 'text',
    'add_cc'          => 'user_search',
];

// Build a lookup map of user id -> display label for pre-populating edit form
$userLookup = [];
foreach ($allUsers as $u) {
    $userLookup[(string) $u['id']] = $u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['email'] . ')';
}

// Normalise existing conditions to v2 group format for the JS loader
$existingConditions = $isEdit ? ($editing['conditions'] ?? []) : [];
$existingActions    = $isEdit ? ($editing['actions'] ?? []) : [];

// v1 backward compat: convert flat array to v2 group format for the form JS
if (!empty($existingConditions) && isset($existingConditions[0]['field'])) {
    $existingConditions = ['v' => 2, 'groups' => [['match' => 'all', 'conditions' => $existingConditions]]];
}
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><?= $isEdit ? 'Edit' : 'Create' ?> Automation</h5>
    <a href="/admin/settings/automations" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>" id="automationForm">
            <?= csrfField() ?>
            <input type="hidden" name="conditions_json" id="conditions_json">

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= e(old('name', $editing['name'] ?? '')) ?>" required
                           placeholder="e.g. Assign IT tickets to IT Group">
                </div>
                <div class="col-md-3">
                    <label for="trigger_event" class="form-label fw-semibold">Trigger <span class="text-danger">*</span></label>
                    <select class="form-select" id="trigger_event" name="trigger_event" required>
                        <option value="ticket_created" <?= old('trigger_event', $editing['trigger_event'] ?? '') === 'ticket_created' ? 'selected' : '' ?>>Ticket Created</option>
                        <option value="ticket_updated" <?= old('trigger_event', $editing['trigger_event'] ?? '') === 'ticket_updated' ? 'selected' : '' ?>>Ticket Updated</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order"
                           value="<?= e(old('sort_order', (string) ($editing['sort_order'] ?? 0))) ?>" min="0">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="is_enabled"
                               <?= old('is_enabled', $isEdit ? ($editing['is_enabled'] ? '1' : '') : '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_enabled">Enabled</label>
                    </div>
                </div>
            </div>

            <hr>

            <!-- Conditions -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="fw-bold mb-0"><i class="bi bi-funnel me-1"></i>Conditions</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addGroup">
                        <i class="bi bi-plus-lg me-1"></i>Add Group
                    </button>
                </div>
                <p class="text-muted small mb-3">The automation fires if <strong>any</strong> group below matches. Within each group you choose whether <strong>all</strong> or <strong>any</strong> conditions must pass.</p>
                <div id="groupsContainer"></div>
                <div id="noGroups" class="text-muted small py-2" style="display:none;">
                    No conditions — this automation will run for <strong>all</strong> tickets.
                </div>
            </div>

            <hr>

            <!-- Actions -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-gear me-1"></i>Actions <span class="text-danger">*</span></h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addAction">
                        <i class="bi bi-plus-lg me-1"></i>Add Action
                    </button>
                </div>
                <div id="actionsContainer"></div>
                <div id="noActions" class="text-muted small" style="display:none;">
                    No actions defined. Add at least one action.
                </div>
            </div>

            <hr>

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update' : 'Create' ?> Automation
                </button>
                <a href="/admin/settings/automations" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var fieldOptions = <?= json_encode($fieldOptions, JSON_HEX_TAG) ?>;
    var actionOptions = <?= json_encode($actionOptions, JSON_HEX_TAG) ?>;
    var userLookup    = <?= json_encode($userLookup, JSON_HEX_TAG) ?>;

    var fieldLabels = {
        type_id: 'Ticket Type', priority_id: 'Priority', status: 'Status',
        location_id: '<?= label("location.singular") ?>', group_id: 'Group', assigned_to: 'Assigned To'
    };
    var operatorLabels = {
        equals: 'is', not_equals: 'is not', is_empty: 'is empty', is_not_empty: 'is not empty'
    };
    var actionLabels = {
        set_group: 'Assign to group', set_assigned_to: 'Assign to agent',
        set_priority: 'Set priority', set_status: 'Set status', add_tag: 'Add tag', add_cc: 'CC user'
    };

    var groupsContainer = document.getElementById('groupsContainer');
    var actContainer    = document.getElementById('actionsContainer');
    var noGroups = document.getElementById('noGroups');
    var noAct    = document.getElementById('noActions');
    var groupCounter = 0;

    function updateEmptyMessages() {
        noGroups.style.display = groupsContainer.children.length === 0 ? '' : 'none';
        noAct.style.display    = actContainer.children.length === 0 ? '' : 'none';
    }

    // ── Shared widget builders (unchanged from before) ───────────────────────

    function buildSelect(name, options, selectedVal) {
        var sel = document.createElement('select');
        sel.name = name;
        sel.className = 'form-select form-select-sm';
        if (typeof options === 'object' && !Array.isArray(options)) {
            for (var k in options) {
                var opt = document.createElement('option');
                opt.value = k; opt.textContent = options[k];
                if (k === selectedVal) opt.selected = true;
                sel.appendChild(opt);
            }
        } else {
            options.forEach(function(o) {
                var opt = document.createElement('option');
                opt.value = o.id; opt.textContent = o.label;
                if (o.id === selectedVal) opt.selected = true;
                sel.appendChild(opt);
            });
        }
        return sel;
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function buildUserSearchWidget(name, selectedVal) {
        var wrap = document.createElement('div');
        wrap.style.position = 'relative';

        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = name; hidden.value = selectedVal || '';

        var inp = document.createElement('input');
        inp.type = 'text'; inp.className = 'form-control form-control-sm';
        inp.placeholder = 'Type a name or email…'; inp.autocomplete = 'off';
        if (selectedVal && userLookup[selectedVal]) inp.value = userLookup[selectedVal];

        var dropdown = document.createElement('div');
        dropdown.className = 'list-group shadow-sm';
        dropdown.style.cssText = 'position:absolute;top:100%;left:0;right:0;z-index:1050;max-height:200px;overflow-y:auto;display:none;';

        var debounce = null;
        inp.addEventListener('input', function() {
            hidden.value = '';
            clearTimeout(debounce);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            debounce = setTimeout(function() {
                fetch('/api/user-search?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(users) {
                        if (!users.length) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        users.forEach(function(u) {
                            var item = document.createElement('a');
                            item.href = '#';
                            item.className = 'list-group-item list-group-item-action py-1 px-2 small';
                            var badge = u.role === 'admin'
                                ? ' <span class="badge bg-danger" style="font-size:.6rem;">Admin</span>'
                                : u.role === 'agent'
                                ? ' <span class="badge bg-primary" style="font-size:.6rem;">Agent</span>'
                                : ' <span class="badge bg-secondary" style="font-size:.6rem;">User</span>';
                            item.innerHTML = '<strong>' + escHtml(u.first_name + ' ' + u.last_name) + '</strong> '
                                           + '<span class="text-muted">' + escHtml(u.email) + '</span>' + badge;
                            item.addEventListener('mousedown', function(ev) {
                                ev.preventDefault();
                                hidden.value = u.id;
                                inp.value = u.first_name + ' ' + u.last_name + ' (' + u.email + ')';
                                dropdown.style.display = 'none';
                            });
                            dropdown.appendChild(item);
                        });
                        dropdown.style.display = '';
                    });
            }, 250);
        });
        inp.addEventListener('blur', function() {
            setTimeout(function() { dropdown.style.display = 'none'; }, 200);
        });

        wrap.appendChild(hidden); wrap.appendChild(inp); wrap.appendChild(dropdown);
        return wrap;
    }

    function buildValueInput(name, fieldOrAction, selectedVal, isCondition) {
        var opts = isCondition ? (fieldOptions[fieldOrAction] || []) : actionOptions[fieldOrAction];
        if (opts === 'user_search') return buildUserSearchWidget(name, selectedVal);
        if (opts === 'text') {
            var inp = document.createElement('input');
            inp.type = 'text'; inp.name = name; inp.className = 'form-control form-control-sm';
            inp.placeholder = 'Tag name (e.g. critical)'; inp.value = selectedVal || '';
            return inp;
        }
        if (Array.isArray(opts) && opts.length > 0) return buildSelect(name, opts, selectedVal);
        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = name; hidden.value = '';
        return hidden;
    }

    // ── Condition row (scoped inside a group) ────────────────────────────────

    function addConditionRow(groupBody, field, operator, value) {
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-center condition-row';

        // Field select
        var col1 = document.createElement('div'); col1.className = 'col-md-3';
        var fieldSel = buildSelect('', fieldLabels, field || 'type_id');
        fieldSel.dataset.role = 'field';
        fieldSel.addEventListener('change', function() {
            updateCondValueCell(row, this.value, '');
        });
        col1.appendChild(fieldSel);

        // Operator select
        var col2 = document.createElement('div'); col2.className = 'col-md-3';
        var opSel = buildSelect('', operatorLabels, operator || 'equals');
        opSel.dataset.role = 'operator';
        opSel.addEventListener('change', function() {
            if (this.value === 'is_empty' || this.value === 'is_not_empty') {
                row.querySelector('.cond-value').innerHTML = '';
                var h = document.createElement('input');
                h.type = 'hidden'; h.dataset.role = 'value'; h.value = '';
                row.querySelector('.cond-value').appendChild(h);
            } else {
                updateCondValueCell(row, fieldSel.value, '');
            }
        });
        col2.appendChild(opSel);

        // Value cell
        var col3 = document.createElement('div'); col3.className = 'col-md-5 cond-value';
        if (operator === 'is_empty' || operator === 'is_not_empty') {
            var h = document.createElement('input');
            h.type = 'hidden'; h.dataset.role = 'value'; h.value = '';
            col3.appendChild(h);
        } else {
            var vi = buildValueInput('', field || 'type_id', value || '', true);
            vi.dataset = vi.dataset || {};
            // Mark the actual value element(s) so we can read them
            markValueEl(vi);
            col3.appendChild(vi);
        }

        // Remove button
        var col4 = document.createElement('div'); col4.className = 'col-md-1';
        var btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'btn btn-sm btn-outline-danger'; btn.title = 'Remove condition';
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        btn.addEventListener('click', function() { row.remove(); });
        col4.appendChild(btn);

        row.appendChild(col1); row.appendChild(col2); row.appendChild(col3); row.appendChild(col4);
        groupBody.appendChild(row);
    }

    function markValueEl(el) {
        // Finds the input/select inside a value widget and tags it with data-role="value"
        if (el.tagName === 'INPUT' || el.tagName === 'SELECT') {
            el.dataset.role = 'value';
        } else {
            // user-search widget: the hidden input carries the id
            var hidden = el.querySelector('input[type="hidden"]');
            if (hidden) hidden.dataset.role = 'value';
        }
    }

    function updateCondValueCell(row, field, value) {
        var cell = row.querySelector('.cond-value');
        cell.innerHTML = '';
        var vi = buildValueInput('', field, value, true);
        markValueEl(vi);
        cell.appendChild(vi);
    }

    // ── Condition group block ────────────────────────────────────────────────

    function addGroupBlock(matchMode, conditionsData) {
        groupCounter++;
        var gIdx = groupCounter;

        var card = document.createElement('div');
        card.className = 'card border mb-3 condition-group';
        card.style.cssText = 'border-color:#dee2e6!important;';

        // Header
        var header = document.createElement('div');
        header.className = 'd-flex align-items-center gap-2 px-3 py-2 rounded-top';
        header.style.cssText = 'background:#f8f9fa;border-bottom:1px solid #dee2e6;';

        var gLabel = document.createElement('span');
        gLabel.className = 'fw-semibold small text-muted';
        gLabel.textContent = 'Group ' + gIdx + ' — Match';

        var matchSel = document.createElement('select');
        matchSel.className = 'form-select form-select-sm w-auto group-match-select';
        matchSel.style.cssText = 'min-width:80px;';
        [['all','ALL (AND)'],['any','ANY (OR)']].forEach(function(opt) {
            var o = document.createElement('option');
            o.value = opt[0]; o.textContent = opt[1];
            if (opt[0] === matchMode) o.selected = true;
            matchSel.appendChild(o);
        });

        var gLabel2 = document.createElement('span');
        gLabel2.className = 'fw-semibold small text-muted';
        gLabel2.textContent = 'of these conditions';

        var spacer = document.createElement('div');
        spacer.className = 'ms-auto';

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger';
        removeBtn.title = 'Remove group';
        removeBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Remove Group';
        removeBtn.addEventListener('click', function() {
            card.remove();
            updateEmptyMessages();
        });

        header.appendChild(gLabel);
        header.appendChild(matchSel);
        header.appendChild(gLabel2);
        header.appendChild(spacer);
        header.appendChild(removeBtn);

        // Body (condition rows)
        var body = document.createElement('div');
        body.className = 'p-3 pb-0 condition-rows';

        // Footer (add condition button)
        var footer = document.createElement('div');
        footer.className = 'px-3 pb-3 pt-1';
        var addCondBtn = document.createElement('button');
        addCondBtn.type = 'button';
        addCondBtn.className = 'btn btn-sm btn-outline-secondary';
        addCondBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>Add Condition';
        addCondBtn.addEventListener('click', function() {
            addConditionRow(body, 'type_id', 'equals', '');
        });
        footer.appendChild(addCondBtn);

        card.appendChild(header);
        card.appendChild(body);
        card.appendChild(footer);
        groupsContainer.appendChild(card);

        // Restore existing conditions into this group
        if (conditionsData && conditionsData.length) {
            conditionsData.forEach(function(c) {
                addConditionRow(body, c.field || 'type_id', c.operator || 'equals', c.value || '');
            });
        } else {
            // New group gets one blank condition row
            addConditionRow(body, 'type_id', 'equals', '');
        }

        updateEmptyMessages();
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    function addActionRow(actionType, value) {
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-center action-row';

        var col1 = document.createElement('div'); col1.className = 'col-md-4';
        var actSel = buildSelect('act_type[]', actionLabels, actionType || '');
        actSel.addEventListener('change', function() { updateActValueCell(row, this.value, ''); });
        col1.appendChild(actSel);

        var col2 = document.createElement('div'); col2.className = 'col-md-7 act-value';
        col2.appendChild(buildValueInput('act_value[]', actionType || 'set_group', value || '', false));

        var col3 = document.createElement('div'); col3.className = 'col-md-1';
        var btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'btn btn-sm btn-outline-danger'; btn.title = 'Remove';
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        btn.addEventListener('click', function() { row.remove(); updateEmptyMessages(); });
        col3.appendChild(btn);

        row.appendChild(col1); row.appendChild(col2); row.appendChild(col3);
        actContainer.appendChild(row);
        updateEmptyMessages();
    }

    function updateActValueCell(row, actionType, value) {
        var cell = row.querySelector('.act-value');
        cell.innerHTML = '';
        cell.appendChild(buildValueInput('act_value[]', actionType, value, false));
    }

    // ── Serialise conditions to JSON on submit ───────────────────────────────

    document.getElementById('automationForm').addEventListener('submit', function() {
        var groups = [];
        document.querySelectorAll('#groupsContainer .condition-group').forEach(function(card) {
            var matchSel = card.querySelector('.group-match-select');
            var matchVal = matchSel ? matchSel.value : 'all';
            var conds = [];
            card.querySelectorAll('.condition-row').forEach(function(row) {
                var fieldEl = row.querySelector('[data-role="field"]');
                var opEl    = row.querySelector('[data-role="operator"]');
                var valEl   = row.querySelector('[data-role="value"]');
                if (!fieldEl) return;
                var field = fieldEl.value;
                var op    = opEl ? opEl.value : 'equals';
                var val   = valEl ? valEl.value : '';
                if (field) conds.push({field: field, operator: op, value: val});
            });
            if (conds.length) groups.push({match: matchVal, conditions: conds});
        });
        document.getElementById('conditions_json').value = JSON.stringify({v: 2, groups: groups});
    });

    // ── Wire up top-level buttons ────────────────────────────────────────────

    document.getElementById('addGroup').addEventListener('click', function() {
        addGroupBlock('all', []);
    });
    document.getElementById('addAction').addEventListener('click', function() {
        addActionRow('', '');
    });

    // ── Load existing data on edit ───────────────────────────────────────────

    var existing = <?= json_encode(['conditions' => $existingConditions, 'actions' => $existingActions], JSON_HEX_TAG) ?>;

    var condData = existing.conditions;
    if (condData && condData.groups && condData.groups.length) {
        condData.groups.forEach(function(g) {
            addGroupBlock(g.match || 'all', g.conditions || []);
        });
    } else if (!condData || !condData.groups || condData.groups.length === 0) {
        // New automation — start with one empty group
        addGroupBlock('all', []);
    }

    existing.actions.forEach(function(a) {
        addActionRow(a.action || '', a.value || '');
    });

    updateEmptyMessages();
})();
</script>
