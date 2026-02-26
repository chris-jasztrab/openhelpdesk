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

$existingConditions = $isEdit ? ($editing['conditions'] ?? []) : [];
$existingActions    = $isEdit ? ($editing['actions'] ?? []) : [];
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-funnel me-1"></i>Conditions <span class="text-muted fw-normal small">(all must match)</span></h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addCondition">
                        <i class="bi bi-plus-lg me-1"></i>Add Condition
                    </button>
                </div>
                <div id="conditionsContainer">
                    <!-- Dynamic rows inserted here -->
                </div>
                <div id="noConditions" class="text-muted small" style="display:none;">
                    No conditions — this automation will match <strong>all</strong> tickets.
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
                <div id="actionsContainer">
                    <!-- Dynamic rows inserted here -->
                </div>
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

    var condContainer = document.getElementById('conditionsContainer');
    var actContainer  = document.getElementById('actionsContainer');
    var noCond = document.getElementById('noConditions');
    var noAct  = document.getElementById('noActions');

    function updateEmptyMessages() {
        noCond.style.display = condContainer.children.length === 0 ? '' : 'none';
        noAct.style.display  = actContainer.children.length === 0 ? '' : 'none';
    }

    // Build a <select> from an array of {id, label} or an object of labels
    function buildSelect(name, options, selectedVal) {
        var sel = document.createElement('select');
        sel.name = name;
        sel.className = 'form-select form-select-sm';
        if (typeof options === 'object' && !Array.isArray(options)) {
            // key-value object
            for (var k in options) {
                var opt = document.createElement('option');
                opt.value = k;
                opt.textContent = options[k];
                if (k === selectedVal) opt.selected = true;
                sel.appendChild(opt);
            }
        } else {
            // array of {id, label}
            options.forEach(function(o) {
                var opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = o.label;
                if (o.id === selectedVal) opt.selected = true;
                sel.appendChild(opt);
            });
        }
        return sel;
    }

    var userLookup = <?= json_encode($userLookup, JSON_HEX_TAG) ?>;

    function buildUserSearchWidget(name, selectedVal) {
        var wrap = document.createElement('div');
        wrap.style.position = 'relative';

        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = selectedVal || '';

        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'form-control form-control-sm';
        inp.placeholder = 'Type a name or email...';
        inp.autocomplete = 'off';
        // Pre-populate display text if editing
        if (selectedVal && userLookup[selectedVal]) {
            inp.value = userLookup[selectedVal];
        }

        var dropdown = document.createElement('div');
        dropdown.className = 'list-group shadow-sm';
        dropdown.style.cssText = 'position:absolute;top:100%;left:0;right:0;z-index:1050;max-height:200px;overflow-y:auto;display:none;';

        var debounce = null;

        inp.addEventListener('input', function() {
            // Clear selection when user types
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
                            var roleBadge = u.role === 'admin' ? ' <span class="badge bg-danger" style="font-size:.6rem;">Admin</span>'
                                          : u.role === 'agent' ? ' <span class="badge bg-primary" style="font-size:.6rem;">Agent</span>'
                                          : ' <span class="badge bg-secondary" style="font-size:.6rem;">User</span>';
                            item.innerHTML = '<strong>' + escHtml(u.first_name + ' ' + u.last_name) + '</strong> '
                                           + '<span class="text-muted">' + escHtml(u.email) + '</span>' + roleBadge;
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

        function escHtml(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        wrap.appendChild(hidden);
        wrap.appendChild(inp);
        wrap.appendChild(dropdown);
        return wrap;
    }

    function buildValueInput(name, fieldOrAction, selectedVal, isCondition) {
        var opts;
        if (isCondition) {
            opts = fieldOptions[fieldOrAction] || [];
        } else {
            opts = actionOptions[fieldOrAction];
        }

        if (opts === 'user_search') {
            return buildUserSearchWidget(name, selectedVal);
        }

        if (opts === 'text') {
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.name = name;
            inp.className = 'form-control form-control-sm';
            inp.placeholder = 'Tag name (e.g. critical)';
            inp.value = selectedVal || '';
            return inp;
        }

        if (Array.isArray(opts) && opts.length > 0) {
            return buildSelect(name, opts, selectedVal);
        }

        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = '';
        return hidden;
    }

    // --- Conditions ---
    function addConditionRow(field, operator, value) {
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-center condition-row';

        // Field select
        var col1 = document.createElement('div');
        col1.className = 'col-md-3';
        var fieldSel = buildSelect('cond_field[]', fieldLabels, field || '');
        fieldSel.addEventListener('change', function() {
            updateCondValueCell(row, this.value, '');
        });
        col1.appendChild(fieldSel);

        // Operator select
        var col2 = document.createElement('div');
        col2.className = 'col-md-3';
        var opSel = buildSelect('cond_operator[]', operatorLabels, operator || 'equals');
        opSel.addEventListener('change', function() {
            var valCell = row.querySelector('.cond-value');
            if (this.value === 'is_empty' || this.value === 'is_not_empty') {
                valCell.innerHTML = '';
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = 'cond_value[]'; h.value = '';
                valCell.appendChild(h);
            } else {
                updateCondValueCell(row, fieldSel.value, '');
            }
        });
        col2.appendChild(opSel);

        // Value
        var col3 = document.createElement('div');
        col3.className = 'col-md-5 cond-value';
        var isNoValue = (operator === 'is_empty' || operator === 'is_not_empty');
        if (isNoValue) {
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = 'cond_value[]'; h.value = '';
            col3.appendChild(h);
        } else {
            col3.appendChild(buildValueInput('cond_value[]', field || 'type_id', value || '', true));
        }

        // Remove button
        var col4 = document.createElement('div');
        col4.className = 'col-md-1';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-danger';
        btn.title = 'Remove';
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        btn.addEventListener('click', function() {
            row.remove();
            updateEmptyMessages();
        });
        col4.appendChild(btn);

        row.appendChild(col1);
        row.appendChild(col2);
        row.appendChild(col3);
        row.appendChild(col4);
        condContainer.appendChild(row);
        updateEmptyMessages();
    }

    function updateCondValueCell(row, field, value) {
        var cell = row.querySelector('.cond-value');
        cell.innerHTML = '';
        cell.appendChild(buildValueInput('cond_value[]', field, value, true));
    }

    // --- Actions ---
    function addActionRow(actionType, value) {
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-center action-row';

        // Action type select
        var col1 = document.createElement('div');
        col1.className = 'col-md-4';
        var actSel = buildSelect('act_type[]', actionLabels, actionType || '');
        actSel.addEventListener('change', function() {
            updateActValueCell(row, this.value, '');
        });
        col1.appendChild(actSel);

        // Value
        var col2 = document.createElement('div');
        col2.className = 'col-md-7 act-value';
        col2.appendChild(buildValueInput('act_value[]', actionType || 'set_group', value || '', false));

        // Remove button
        var col3 = document.createElement('div');
        col3.className = 'col-md-1';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-danger';
        btn.title = 'Remove';
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        btn.addEventListener('click', function() {
            row.remove();
            updateEmptyMessages();
        });
        col3.appendChild(btn);

        row.appendChild(col1);
        row.appendChild(col2);
        row.appendChild(col3);
        actContainer.appendChild(row);
        updateEmptyMessages();
    }

    function updateActValueCell(row, actionType, value) {
        var cell = row.querySelector('.act-value');
        cell.innerHTML = '';
        cell.appendChild(buildValueInput('act_value[]', actionType, value, false));
    }

    // Add buttons
    document.getElementById('addCondition').addEventListener('click', function() {
        addConditionRow('', 'equals', '');
    });
    document.getElementById('addAction').addEventListener('click', function() {
        addActionRow('', '');
    });

    // Load existing conditions/actions on edit
    var existing = <?= json_encode(['conditions' => $existingConditions, 'actions' => $existingActions], JSON_HEX_TAG) ?>;
    existing.conditions.forEach(function(c) {
        addConditionRow(c.field || '', c.operator || 'equals', c.value || '');
    });
    existing.actions.forEach(function(a) {
        addActionRow(a.action || '', a.value || '');
    });

    updateEmptyMessages();
})();
</script>
