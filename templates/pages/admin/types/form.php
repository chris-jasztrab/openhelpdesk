<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Ticket Type' : 'Add Ticket Type';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Ticket Types', 'url' => '/admin/types'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/types/{$editing['id']}/edit" : '/admin/types/create';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Ticket Type' : 'Add Ticket Type' ?></h2>
    <a href="/admin/types" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm" style="max-width:600px;">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Type Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required
                       placeholder="e.g. IT, Marketing, Facilities">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="color" class="form-label fw-semibold">Color</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="color" class="form-control form-control-color" id="color" name="color"
                               value="<?= e(old('color', $editing['color'] ?? '#6c757d')) ?>">
                        <span class="badge" id="colorPreview" style="background:<?= e(old('color', $editing['color'] ?? '#6c757d')) ?>;">
                            <?= e(old('name', $editing['name'] ?? 'Preview')) ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order"
                       value="<?= e(old('sort_order', (string) ($editing['sort_order'] ?? '0'))) ?>" min="0">
                    <div class="form-text">Lower numbers appear first.</div>
                </div>
            </div>

            <div class="mb-3">
                <label for="group_id" class="form-label fw-semibold">Default Group</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">— No group —</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= (int) old('group_id', (string) ($editing['group_id'] ?? '')) === (int) $g['id'] ? 'selected' : '' ?>>
                        <?= e($g['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">When assigning agents to tickets of this type, only agents in this group will be shown.</div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="is_confidential" name="is_confidential" value="1"
                           <?= (int) old('is_confidential', (string) ($editing['is_confidential'] ?? '0')) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="is_confidential">
                        <i class="bi bi-shield-lock me-1"></i>Confidential
                    </label>
                </div>
                <div class="form-text">Only members of the assigned group can view these tickets. Admins outside the group must re-authenticate to access them, and all access is logged and notified to group members.</div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="ai_route_group" name="ai_route_group" value="1"
                           <?= (int) old('ai_route_group', (string) ($editing['ai_route_group'] ?? '0')) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="ai_route_group">
                        <i class="bi bi-signpost-split me-1"></i>Let AI route this to the best group ("No Wrong Door")
                    </label>
                </div>
                <div class="form-text">
                    On submit, AI reads the ticket and picks the most appropriate group from all non-confidential groups, using each group's <em>description</em> to decide. If AI isn't confident, the ticket stays in the <strong>Default Group</strong> selected above (so make that your fallback queue — e.g. a "No Wrong Door" team that handles anything AI couldn't route). Requires AI to be enabled at <a href="/admin/settings/ai">Admin → Settings → AI Classification</a>; routing quality depends on each group having a clear description at <a href="/admin/groups">Admin → Groups</a>.
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="ai_dup_check_enabled" name="ai_dup_check_enabled" value="1"
                           <?= (int) old('ai_dup_check_enabled', (string) ($editing['ai_dup_check_enabled'] ?? '0')) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="ai_dup_check_enabled">
                        <i class="bi bi-files me-1"></i>Check for duplicates on submit
                    </label>
                </div>
                <div class="form-text">
                    When the requester clicks Submit, AI scans recent open non-confidential tickets at the same branch and warns if any look like the same issue. Common at multi-shift branches where staff don't see what's already in the queue.
                    Confidential ticket types are NEVER scanned and never appear as candidates. Requires AI to be enabled at <a href="/admin/settings/ai">Admin → Settings → AI Classification</a>.
                </div>
                <div class="mt-2 row g-2 align-items-center" id="dup_threshold_row" style="<?= (int) old('ai_dup_check_enabled', (string) ($editing['ai_dup_check_enabled'] ?? '0')) ? '' : 'display:none;' ?>">
                    <div class="col-auto">
                        <label for="ai_dup_threshold" class="form-label fw-semibold mb-0 small">
                            Match confidence threshold
                        </label>
                    </div>
                    <div class="col-auto">
                        <input type="number" min="0.50" max="0.99" step="0.05" class="form-control form-control-sm"
                               id="ai_dup_threshold" name="ai_dup_threshold" style="width:90px;"
                               value="<?= e(old('ai_dup_threshold', (string) ($editing['ai_dup_threshold'] ?? '0.75'))) ?>">
                    </div>
                    <div class="col">
                        <small class="text-muted">0.50 = looser (more matches, more false positives) · 0.95 = strict.</small>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="show_to_location_visibility" name="show_to_location_visibility" value="1"
                           <?= (int) old('show_to_location_visibility', (string) ($editing['show_to_location_visibility'] ?? '1')) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="show_to_location_visibility">
                        <i class="bi bi-people me-1"></i>Visible to Location Ticket Visibility users
                    </label>
                </div>
                <div class="form-text">
                    When checked (the default), tickets of this type appear to portal users at the same location who have <strong>Location Ticket Visibility</strong> enabled on their account.
                    Uncheck for types that should stay restricted to agents — e.g. <em>Collections</em>, <em>Human Resources</em>, or other sensitive categories where broad visibility is inappropriate.
                    Requesters always see their own ticket regardless of this setting.
                </div>
            </div>

            <?php if ($isEdit): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    <i class="bi bi-card-list me-1"></i>Form Fields
                </label>
                <div class="form-text mt-0 mb-2">
                    Configure which fields appear on this type's New Ticket form, their order, and whether each is required, optional, or hidden.
                </div>
                <a class="btn btn-outline-primary btn-sm" href="/admin/workflows/ticket-fields?type=<?= (int) $editing['id'] ?>">
                    <i class="bi bi-pencil-square me-1"></i>Edit this type's form
                </a>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="stale_threshold_hours" class="form-label fw-semibold">
                    <i class="bi bi-hourglass-split me-1"></i>Stale Threshold (hours)
                </label>
                <input type="number" min="0" class="form-control" id="stale_threshold_hours" name="stale_threshold_hours"
                       value="<?= e(old('stale_threshold_hours', (string) ($editing['stale_threshold_hours'] ?? ''))) ?>"
                       placeholder="Uses global setting">
                <div class="form-text">
                    Override the global stale threshold for this ticket type. Leave blank to use the global setting (Admin → Settings → Stale Tickets).
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">
                    <i class="bi bi-mortarboard me-1"></i>Required Skills
                </label>
                <?php if (empty($skills)): ?>
                    <div class="alert alert-info py-2 mb-0 small">
                        No skills defined yet. <a href="/admin/skills/create">Create a skill</a> if you want to use Skill-Based routing for this ticket type.
                    </div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($skills as $sk): ?>
                        <div class="col-md-6">
                            <div class="form-check border rounded p-2 ps-4">
                                <input class="form-check-input" type="checkbox" name="required_skills[]"
                                       value="<?= $sk['id'] ?>" id="rskill_<?= $sk['id'] ?>"
                                       <?= in_array((int) $sk['id'], $requiredSkillIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="rskill_<?= $sk['id'] ?>"><?= e($sk['name']) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">
                        Used by groups whose auto-assign strategy is <strong>Skill-Based</strong>. A new ticket of this type will only be auto-assigned to a group member who has every checked skill. Leave all unchecked to let any group member receive these tickets under skill-based routing (the strategy then falls back to the group's configured fallback).
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Type' : 'Create Type' ?>
                </button>
                <a href="/admin/types" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('color').addEventListener('input', function() {
    document.getElementById('colorPreview').style.background = this.value;
});
document.getElementById('name').addEventListener('input', function() {
    document.getElementById('colorPreview').textContent = this.value || 'Preview';
});
// Disable confidential checkbox when no group is selected
(function() {
    var groupSel = document.getElementById('group_id');
    var confCb   = document.getElementById('is_confidential');
    function toggle() {
        if (!groupSel.value) { confCb.checked = false; confCb.disabled = true; }
        else { confCb.disabled = false; }
    }
    groupSel.addEventListener('change', toggle);
    toggle();
})();
// AI group routing is incompatible with Confidential — bodies of confidential
// tickets are never sent to a third-party provider.
(function() {
    var confCb = document.getElementById('is_confidential');
    var aiCb   = document.getElementById('ai_route_group');
    if (!confCb || !aiCb) return;
    function toggle() {
        if (confCb.checked) { aiCb.checked = false; aiCb.disabled = true; }
        else { aiCb.disabled = false; }
    }
    confCb.addEventListener('change', toggle);
    toggle();
})();
// AI dup-check is also incompatible with Confidential, and the threshold
// row only makes sense when the toggle is on.
(function() {
    var confCb  = document.getElementById('is_confidential');
    var dupCb   = document.getElementById('ai_dup_check_enabled');
    var dupRow  = document.getElementById('dup_threshold_row');
    if (!dupCb || !dupRow) return;
    function toggle() {
        if (confCb && confCb.checked) { dupCb.checked = false; dupCb.disabled = true; }
        else if (dupCb.disabled) { dupCb.disabled = false; }
        dupRow.style.display = dupCb.checked ? '' : 'none';
    }
    dupCb.addEventListener('change', toggle);
    if (confCb) confCb.addEventListener('change', toggle);
    toggle();
})();
</script>
