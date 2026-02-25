<?php
$isEdit  = !empty($editing);
$action  = $isEdit
    ? '/admin/settings/escalations/' . (int) $editing['id'] . '/edit'
    : '/admin/settings/escalations/create';

$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Escalation Rule' : 'New Escalation Rule';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Escalation Rules', 'url' => '/admin/settings/escalations'],
    ['label' => $isEdit ? 'Edit' : 'New'],
];

/* Build option arrays for JS */
$priorityOpts = array_map(fn($r) => ['id' => (string)$r['id'], 'label' => $r['name']], $priorities);
$groupOpts    = array_map(fn($r) => ['id' => (string)$r['id'], 'label' => $r['name']], $groups);
$agentOpts    = array_map(fn($r) => ['id' => (string)$r['id'], 'label' => $r['first_name'].' '.$r['last_name']], $agents);
$statusOpts   = [
    ['id' => 'open',                   'label' => 'Open'],
    ['id' => 'in_progress',            'label' => 'In Progress'],
    ['id' => 'pending',                'label' => 'Pending'],
    ['id' => 'waiting_on_customer',    'label' => 'Waiting on Customer'],
    ['id' => 'waiting_on_third_party', 'label' => 'Waiting on Third Party'],
    ['id' => 'resolved',               'label' => 'Resolved'],
    ['id' => 'closed',                 'label' => 'Closed'],
];
$slaStateOpts = [
    ['id' => 'on_track', 'label' => 'On Track'],
    ['id' => 'warning',  'label' => 'Warning'],
    ['id' => 'breached', 'label' => 'Breached'],
];

$existingConditions = $isEdit ? ($editing['conditions_decoded'] ?? []) : [];
$existingActions    = $isEdit ? ($editing['actions_decoded']    ?? []) : [];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/settings/escalations" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-semibold mb-0"><?= $isEdit ? 'Edit Escalation Rule' : 'New Escalation Rule' ?></h5>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <!-- Name + Sort + Enabled -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="name" class="form-label fw-semibold">Rule Name</label>
                    <input type="text" class="form-control" id="name" name="name" required maxlength="255"
                           value="<?= e($isEdit ? $editing['name'] : '') ?>"
                           placeholder="e.g. Escalate breached critical tickets">
                </div>
                <div class="col-md-3">
                    <label for="cooldown_hours" class="form-label fw-semibold">Re-fire cooldown (hours)</label>
                    <input type="number" class="form-control" id="cooldown_hours" name="cooldown_hours"
                           min="0" max="720" value="<?= $isEdit ? (int)$editing['cooldown_hours'] : 0 ?>">
                    <div class="form-text">0 = fire once per ticket ever</div>
                </div>
                <div class="col-md-2">
                    <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order"
                           min="0" value="<?= $isEdit ? (int)$editing['sort_order'] : 0 ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end pb-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_enabled" name="is_enabled" value="1"
                               <?= (!$isEdit || $editing['is_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_enabled">On</label>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <!-- Conditions -->
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <h6 class="fw-semibold mb-0">Conditions</h6>
                        <div class="form-text mt-0">All conditions must match (AND logic). Leave empty to match every active ticket.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addCondBtn">
                        <i class="bi bi-plus-lg me-1"></i>Add Condition
                    </button>
                </div>
                <div id="conditionsContainer"></div>
                <p id="noConditions" class="text-muted small fst-italic mb-0" style="display:none;">
                    No conditions — rule applies to all active tickets.
                </p>
            </div>

            <hr class="my-4">

            <!-- Actions -->
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <h6 class="fw-semibold mb-0">Actions</h6>
                        <div class="form-text mt-0">Actions are executed in order when conditions match.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addActBtn">
                        <i class="bi bi-plus-lg me-1"></i>Add Action
                    </button>
                </div>
                <div id="actionsContainer"></div>
                <p id="noActions" class="text-muted small fst-italic mb-0">No actions defined.</p>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Rule' ?>
                </button>
                <a href="/admin/settings/escalations" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    /* ── Reference data ──────────────────────────────── */
    var priorityOpts  = <?= json_encode($priorityOpts) ?>;
    var groupOpts     = <?= json_encode($groupOpts) ?>;
    var agentOpts     = <?= json_encode($agentOpts) ?>;
    var statusOpts    = <?= json_encode($statusOpts) ?>;
    var slaStateOpts  = <?= json_encode($slaStateOpts) ?>;
    var allUsers      = <?= json_encode(array_map(fn($u) => ['id'=>(string)$u['id'],'label'=>$u['first_name'].' '.$u['last_name'].' ('.$u['email'].')'], $allUsers)) ?>;

    /* field → {operators, valueType, options?} */
    var fieldMeta = {
        'sla_state':          { ops: ['equals','not_equals'],  valueType: 'select', opts: slaStateOpts },
        'hours_open':         { ops: ['greater_than'],         valueType: 'number' },
        'hours_since_update': { ops: ['greater_than'],         valueType: 'number' },
        'hours_in_status':    { ops: ['greater_than'],         valueType: 'number' },
        'is_assigned':        { ops: ['equals'],               valueType: 'select', opts: [{id:'yes',label:'Yes'},{id:'no',label:'No'}] },
        'priority_id':        { ops: ['equals','not_equals'],  valueType: 'select', opts: priorityOpts },
        'status':             { ops: ['equals','not_equals'],  valueType: 'select', opts: statusOpts },
        'group_id':           { ops: ['equals','not_equals','is_empty','is_not_empty'], valueType: 'select', opts: groupOpts },
    };
    var fieldLabels = {
        'sla_state':          'SLA State',
        'hours_open':         'Hours open (since created)',
        'hours_since_update': 'Hours since last update',
        'hours_in_status':    'Hours in current status',
        'is_assigned':        'Is assigned',
        'priority_id':        'Priority',
        'status':             'Status',
        'group_id':           'Group',
    };
    var actionMeta = {
        'set_priority':          { label: 'Set priority',           valueType: 'select',      opts: priorityOpts },
        'set_assigned_to':       { label: 'Assign to (empty=unassign)', valueType: 'select_or_empty', opts: agentOpts },
        'set_group':             { label: 'Set group',              valueType: 'select',      opts: groupOpts },
        'set_status':            { label: 'Set status',             valueType: 'select',      opts: statusOpts },
        'notify_user':           { label: 'Notify specific user',          valueType: 'user_search', opts: allUsers },
        'notify_assigned_agent': { label: 'Notify assigned agent',         valueType: 'none' },
        'notify_ticket_creator': { label: 'Send reminder email to customer', valueType: 'none' },
        'add_internal_note':     { label: 'Add internal note',              valueType: 'textarea' },
    };
    var opLabels = {
        'equals':        'equals',
        'not_equals':    'not equals',
        'greater_than':  'greater than',
        'is_empty':      'is empty',
        'is_not_empty':  'is not empty',
    };

    /* ── Helpers ─────────────────────────────────────── */
    function makeSelect(name, opts, selected) {
        var s = '<select name="' + name + '" class="form-select form-select-sm">';
        opts.forEach(function(o) {
            s += '<option value="' + esc(o.id) + '"' + (o.id === selected ? ' selected' : '') + '>' + esc(o.label) + '</option>';
        });
        s += '</select>';
        return s;
    }
    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function buildValueCell(name, meta, opVal, currentVal) {
        if (opVal === 'is_empty' || opVal === 'is_not_empty') {
            return '<input type="hidden" name="' + name + '" value="">';
        }
        if (meta.valueType === 'number') {
            return '<input type="number" name="' + name + '" class="form-control form-control-sm" min="0" value="' + esc(currentVal) + '" placeholder="hours" style="max-width:100px;">';
        }
        if (meta.valueType === 'select' || meta.valueType === 'select_or_empty') {
            var prefix = meta.valueType === 'select_or_empty'
                ? '<option value="">— Unassign —</option>'
                : '';
            var s = '<select name="' + name + '" class="form-select form-select-sm">' + prefix;
            (meta.opts || []).forEach(function(o) {
                s += '<option value="' + esc(o.id) + '"' + (o.id === String(currentVal) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
            });
            s += '</select>';
            return s;
        }
        if (meta.valueType === 'user_search') {
            var label = '';
            (meta.opts || []).forEach(function(o) { if (o.id === String(currentVal)) label = o.label; });
            return '<div class="position-relative user-search-wrap">' +
                '<input type="hidden" name="' + name + '" class="user-id-val" value="' + esc(currentVal) + '">' +
                '<input type="text" class="form-control form-control-sm user-search-input" placeholder="Search users…" value="' + esc(label) + '" autocomplete="off">' +
                '<div class="user-search-dropdown dropdown-menu shadow-sm p-1" style="min-width:280px;display:none;position:absolute;top:100%;left:0;z-index:9999;max-height:180px;overflow-y:auto;"></div>' +
                '</div>';
        }
        if (meta.valueType === 'textarea') {
            return '<textarea name="' + name + '" class="form-control form-control-sm" rows="2" placeholder="Note text…" style="resize:vertical;">' + esc(currentVal) + '</textarea>';
        }
        return '<input type="hidden" name="' + name + '" value="">';
    }

    /* ── Conditions ──────────────────────────────────── */
    var condContainer  = document.getElementById('conditionsContainer');
    var noCondEl       = document.getElementById('noConditions');

    function updateNoCondMsg() {
        noCondEl.style.display = condContainer.children.length === 0 ? '' : 'none';
    }

    function addConditionRow(field, operator, value) {
        field    = field    || 'sla_state';
        operator = operator || 'equals';
        value    = value    || '';

        var meta   = fieldMeta[field] || fieldMeta['sla_state'];
        var opOpts = meta.ops.map(function(o){ return {id:o,label:opLabels[o]||o}; });

        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-start cond-row';
        row.innerHTML =
            '<div class="col-md-3">' +
                '<select name="cond_field[]" class="form-select form-select-sm cond-field-sel">' +
                    Object.keys(fieldLabels).map(function(k){
                        return '<option value="'+esc(k)+'"'+(k===field?' selected':'')+'>'+esc(fieldLabels[k])+'</option>';
                    }).join('') +
                '</select>' +
            '</div>' +
            '<div class="col-md-3">' +
                makeSelect('cond_operator[]', opOpts, operator) +
            '</div>' +
            '<div class="col-md-5 cond-value">' +
                buildValueCell('cond_value[]', meta, operator, value) +
            '</div>' +
            '<div class="col-md-1">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x-lg"></i></button>' +
            '</div>';

        condContainer.appendChild(row);
        updateNoCondMsg();

        // Field change → update operator list and value
        row.querySelector('.cond-field-sel').addEventListener('change', function() {
            var newField = this.value;
            var newMeta  = fieldMeta[newField] || fieldMeta['sla_state'];
            var opOpts2  = newMeta.ops.map(function(o){ return {id:o,label:opLabels[o]||o}; });
            row.querySelector('[name="cond_operator[]"]').outerHTML = makeSelect('cond_operator[]', opOpts2, newMeta.ops[0]);
            // Re-bind operator change
            bindOperatorChange(row, newMeta, newMeta.ops[0], '');
            row.querySelector('.cond-value').innerHTML = buildValueCell('cond_value[]', newMeta, newMeta.ops[0], '');
            bindUserSearch(row);
        });

        // Operator change → update value input
        bindOperatorChange(row, meta, operator, value);
        row.querySelector('.remove-row').addEventListener('click', function() {
            row.remove(); updateNoCondMsg();
        });
        bindUserSearch(row);
    }

    function bindOperatorChange(row, meta, currentOp, currentVal) {
        var opSel = row.querySelector('[name="cond_operator[]"]');
        if (!opSel) return;
        opSel.addEventListener('change', function() {
            row.querySelector('.cond-value').innerHTML = buildValueCell('cond_value[]', meta, this.value, currentVal);
            bindUserSearch(row);
        });
    }

    document.getElementById('addCondBtn').addEventListener('click', function() { addConditionRow(); });
    updateNoCondMsg();

    /* ── Actions ─────────────────────────────────────── */
    var actContainer = document.getElementById('actionsContainer');
    var noActEl      = document.getElementById('noActions');

    function updateNoActMsg() {
        noActEl.style.display = actContainer.children.length === 0 ? '' : 'none';
    }

    function addActionRow(actionType, value) {
        actionType = actionType || 'set_priority';
        value      = value      || '';

        var meta = actionMeta[actionType] || actionMeta['set_priority'];

        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-start act-row';
        row.innerHTML =
            '<div class="col-md-4">' +
                '<select name="act_type[]" class="form-select form-select-sm act-type-sel">' +
                    Object.keys(actionMeta).map(function(k){
                        return '<option value="'+esc(k)+'"'+(k===actionType?' selected':'')+'>'+esc(actionMeta[k].label)+'</option>';
                    }).join('') +
                '</select>' +
            '</div>' +
            '<div class="col-md-7 act-value">' +
                buildValueCell('act_value[]', meta, null, value) +
            '</div>' +
            '<div class="col-md-1">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x-lg"></i></button>' +
            '</div>';

        actContainer.appendChild(row);
        updateNoActMsg();

        row.querySelector('.act-type-sel').addEventListener('change', function() {
            var newMeta = actionMeta[this.value] || actionMeta['set_priority'];
            row.querySelector('.act-value').innerHTML = buildValueCell('act_value[]', newMeta, null, '');
            bindUserSearch(row);
        });
        row.querySelector('.remove-row').addEventListener('click', function() {
            row.remove(); updateNoActMsg();
        });
        bindUserSearch(row);
    }

    document.getElementById('addActBtn').addEventListener('click', function() { addActionRow(); });
    updateNoActMsg();

    /* ── User search widget ──────────────────────────── */
    function bindUserSearch(container) {
        var wrap = container.querySelector('.user-search-wrap');
        if (!wrap) return;
        var input    = wrap.querySelector('.user-search-input');
        var hidden   = wrap.querySelector('.user-id-val');
        var dropdown = wrap.querySelector('.user-search-dropdown');

        input.addEventListener('input', function() {
            var q = this.value.toLowerCase();
            hidden.value = '';
            dropdown.innerHTML = '';
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            var matches = allUsers.filter(function(u){ return u.label.toLowerCase().includes(q); }).slice(0,8);
            if (!matches.length) { dropdown.style.display = 'none'; return; }
            matches.forEach(function(u) {
                var item = document.createElement('a');
                item.href = '#';
                item.className = 'dropdown-item small py-1';
                item.textContent = u.label;
                item.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    hidden.value  = u.id;
                    input.value   = u.label;
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });
            dropdown.style.display = '';
        });
        input.addEventListener('blur', function() {
            setTimeout(function(){ dropdown.style.display = 'none'; }, 150);
        });
    }

    /* ── Populate existing rows on edit ─────────────── */
    var existing = <?= json_encode(['conditions' => $existingConditions, 'actions' => $existingActions]) ?>;
    existing.conditions.forEach(function(c) {
        addConditionRow(c.field || '', c.operator || 'equals', c.value || '');
    });
    existing.actions.forEach(function(a) {
        addActionRow(a.action || '', a.value || '');
    });

})();
</script>
