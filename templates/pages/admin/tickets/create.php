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

        <form method="POST" action="<?= e($formAction) ?>">
            <?= csrfField() ?>

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
                        <label for="description" class="form-label fw-semibold">
                            Description <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="8" required
                                  placeholder="Describe the issue in detail..."><?= e(old('description', '')) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Classification -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-tags me-1"></i>Classification
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
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
                        <div class="col-md-6">
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
                        <div class="col-md-6">
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
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <label for="due_date" class="form-label fw-semibold">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date"
                                   value="<?= e(old('due_date', '')) ?>">
                        </div>
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

                        <?php if (!$isAgent): ?>
                        <div class="col-12">
                            <label class="form-label fw-semibold">On Behalf Of</label>
                            <div class="form-text mb-1">
                                Leave blank to submit as yourself. Search for a portal user to create this ticket for them.
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-tag me-1"></i>Tags
                </div>
                <div class="card-body">
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
// ── Template auto-fill ──────────────────────────────────────────
const TEMPLATES = <?= json_encode($templateData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

document.getElementById('templateSelect')?.addEventListener('change', function () {
    const tpl = TEMPLATES[this.value];
    if (!tpl) return;
    if (tpl.subject)     document.getElementById('subject').value      = tpl.subject;
    if (tpl.body)        document.getElementById('description').value  = tpl.body;
    if (tpl.type_id)     document.getElementById('type_id').value      = tpl.type_id;
    if (tpl.priority_id) document.getElementById('priority_id').value  = tpl.priority_id;
});

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

<?php if (!$isAgent): ?>
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
<?php endif; ?>
</script>
