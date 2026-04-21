<?php
$layout       = 'app';
$pageTitle    = 'Escalation Path — ' . ($type['name'] ?? '');
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',             'url' => '/admin'],
    ['label' => 'Settings',          'url' => '/admin/settings'],
    ['label' => 'Escalation Paths',  'url' => '/admin/settings/escalation-paths'],
    ['label' => $type['name']],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="fw-semibold mb-1">
            <i class="bi bi-signpost-split me-2"></i>Escalation Path:
            <span class="badge" style="background:<?= e($type['color'] ?: '#6c757d') ?>;"><?= e($type['name']) ?></span>
        </h5>
        <p class="text-muted mb-0" style="font-size:.875rem;">
            Ordered list of agents. Clicking <strong>Escalate</strong> on a ticket of this type moves it to the next agent down the list.
        </p>
    </div>
    <a href="/admin/settings/escalation-paths" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<form method="POST" action="/admin/settings/escalation-paths/<?= (int) $type['id'] ?>" id="escalationPathForm">
    <?= csrfField() ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <p class="text-muted small mb-3">
                Drag rows by the <i class="bi bi-grip-vertical"></i> handle to reorder. The topmost row is Level 1.
                Labels (optional) help agents understand who they're escalating to — e.g. "Branch Supervisor", "Service Manager", "Director".
            </p>

            <div id="stepsList">
                <?php foreach ($steps as $i => $s): ?>
                <div class="step-row d-flex align-items-center gap-2 mb-2 p-2 border rounded" draggable="true">
                    <span class="drag-handle text-muted" style="cursor:grab;"><i class="bi bi-grip-vertical"></i></span>
                    <span class="step-number badge bg-secondary" style="min-width:56px;">Level <?= $i + 1 ?></span>
                    <select name="user_id[]" class="form-select form-select-sm" style="max-width:320px;" required>
                        <option value="">Select an agent…</option>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === (int) $s['user_id'] ? 'selected' : '' ?>>
                            <?= e($a['name']) ?> <span class="text-muted">(<?= e($a['role']) ?>)</span>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="label[]" class="form-control form-control-sm" style="max-width:260px;"
                           placeholder="Label (optional) — e.g. Branch Manager"
                           value="<?= e($s['label'] ?? '') ?>" maxlength="100">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-step ms-auto" title="Remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" id="addStepBtn" class="btn btn-sm btn-outline-secondary mt-2">
                <i class="bi bi-plus-lg me-1"></i>Add step
            </button>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-save me-1"></i>Save Path
        </button>
        <a href="/admin/settings/escalation-paths" class="btn btn-outline-secondary">Cancel</a>
    </div>

    <!-- Hidden template for new rows -->
    <template id="stepRowTemplate">
        <div class="step-row d-flex align-items-center gap-2 mb-2 p-2 border rounded" draggable="true">
            <span class="drag-handle text-muted" style="cursor:grab;"><i class="bi bi-grip-vertical"></i></span>
            <span class="step-number badge bg-secondary" style="min-width:56px;">Level ?</span>
            <select name="user_id[]" class="form-select form-select-sm" style="max-width:320px;" required>
                <option value="">Select an agent…</option>
                <?php foreach ($agents as $a): ?>
                <option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?> (<?= e($a['role']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="label[]" class="form-control form-control-sm" style="max-width:260px;"
                   placeholder="Label (optional) — e.g. Branch Manager" maxlength="100">
            <button type="button" class="btn btn-sm btn-outline-danger remove-step ms-auto" title="Remove">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </template>
</form>

<script>
(function () {
    var list     = document.getElementById('stepsList');
    var addBtn   = document.getElementById('addStepBtn');
    var tplNode  = document.getElementById('stepRowTemplate');

    function renumber() {
        list.querySelectorAll('.step-row').forEach(function (row, idx) {
            var badge = row.querySelector('.step-number');
            if (badge) { badge.textContent = 'Level ' + (idx + 1); }
        });
    }

    addBtn.addEventListener('click', function () {
        var frag  = tplNode.content.cloneNode(true);
        list.appendChild(frag);
        renumber();
    });

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-step');
        if (btn) {
            var row = btn.closest('.step-row');
            if (row) {
                row.remove();
                renumber();
            }
        }
    });

    // Drag-to-reorder (HTML5 DnD)
    var dragEl = null;
    list.addEventListener('dragstart', function (e) {
        var row = e.target.closest('.step-row');
        if (!row) return;
        dragEl = row;
        row.classList.add('opacity-50');
        e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', function () {
        if (dragEl) { dragEl.classList.remove('opacity-50'); dragEl = null; }
    });
    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        var target = e.target.closest('.step-row');
        if (!target || target === dragEl) return;
        var rect = target.getBoundingClientRect();
        var after = (e.clientY - rect.top) / rect.height > 0.5;
        list.insertBefore(dragEl, after ? target.nextSibling : target);
    });
    list.addEventListener('drop', function () { renumber(); });

    // Warn if duplicate agents selected — the server deduplicates, but surface it early.
    document.getElementById('escalationPathForm').addEventListener('submit', function (e) {
        var seen = {};
        var dupes = [];
        list.querySelectorAll('select[name="user_id[]"]').forEach(function (sel) {
            var v = sel.value;
            if (!v) return;
            if (seen[v]) { dupes.push(sel.options[sel.selectedIndex].text.trim()); }
            else { seen[v] = true; }
        });
        if (dupes.length) {
            if (!confirm('These agents appear more than once in the path:\n\n' + dupes.join('\n') + '\n\nDuplicate rows will be ignored. Continue?')) {
                e.preventDefault();
            }
        }
    });
})();
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
