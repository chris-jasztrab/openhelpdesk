<?php
$isEdit      = !empty($editing);
$layout      = 'app';
$pageTitle   = $isEdit ? 'Edit User' : 'Add User';
$sidebarItems = adminSidebar('users');
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Users', 'url' => '/admin/users'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/users/{$editing['id']}/edit" : '/admin/users/create';
// A non-admin editor may not change the permission level, email or password of
// a user who outranks them. The server enforces this too — these flags only
// keep the form honest.
$targetOutranksEditor = $isEdit && !Auth::isAdmin()
    && !roleAssignableBy(Auth::role(), $editing['role'] ?? 'user');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit User' : 'Add User' ?></h2>
    <a href="/admin/users" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data">
            <?= csrfField() ?>

            <div class="row g-3">
                <!-- First Name -->
                <div class="col-md-6">
                    <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                           value="<?= e(old('first_name', $editing['first_name'] ?? '')) ?>" required>
                </div>

                <!-- Last Name -->
                <div class="col-md-6">
                    <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                           value="<?= e(old('last_name', $editing['last_name'] ?? '')) ?>" required>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e(old('email', $editing['email'] ?? '')) ?>" required
                           <?= $targetOutranksEditor ? 'readonly' : '' ?>>
                    <?php if ($targetOutranksEditor): ?>
                        <div class="form-text">Only an admin can change this user's email.</div>
                    <?php endif; ?>
                </div>

                <!-- Password -->
                <div class="col-md-6">
                    <label for="password" class="form-label fw-semibold">
                        Password <?= $isEdit ? '<span class="text-muted fw-normal">(leave blank to keep current)</span>' : '<span class="text-danger">*</span>' ?>
                    </label>
                    <input type="password" class="form-control" id="password" name="password"
                           <?= $isEdit ? '' : 'required' ?> <?= $targetOutranksEditor ? 'disabled' : '' ?>>
                    <?php if ($targetOutranksEditor): ?>
                        <div class="form-text">Only an admin can reset this user's password.</div>
                    <?php endif; ?>
                </div>

                <!-- Role -->
                <div class="col-md-4">
                    <label for="role" class="form-label fw-semibold">Permission Level <span class="text-danger">*</span></label>
                    <?php
                    $currentRole = old('role', $editing['role'] ?? 'user');
                    // Non-admins may only assign levels at or below their own, and
                    // may not change the level of a user who already outranks them.
                    $roleChoicesForActor = Auth::isAdmin() ? roleChoices() : assignableRoleChoices(Auth::role());
                    ?>
                    <?php if ($targetOutranksEditor): ?>
                        <input type="hidden" name="role" value="<?= e($editing['role']) ?>">
                        <select class="form-select" id="role" disabled>
                            <option selected><?= e(roleLabel($editing['role'])) ?></option>
                        </select>
                        <div class="form-text">This level is above your own, so you can't change it.</div>
                    <?php else: ?>
                        <select class="form-select" id="role" name="role" required>
                            <?php foreach ($roleChoicesForActor as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $currentRole === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Work Phone -->
                <div class="col-md-4">
                    <label for="work_phone" class="form-label fw-semibold">Work Phone</label>
                    <input type="tel" class="form-control" id="work_phone" name="work_phone"
                           value="<?= e(old('work_phone', $editing['work_phone'] ?? '')) ?>">
                </div>

                <!-- Location -->
                <div class="col-md-4">
                    <label for="location_id" class="form-label fw-semibold">Assigned <?= label('location.singular') ?></label>
                    <select class="form-select" id="location_id" name="location_id">
                        <option value="">— None —</option>
                        <?php
                        $currentLoc = old('location_id', (string) ($editing['location_id'] ?? ''));
                        foreach ($locations as $loc):
                        ?>
                        <option value="<?= $loc['id'] ?>" <?= $currentLoc == $loc['id'] ? 'selected' : '' ?>>
                            <?= e($loc['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- <?= label('location.singular') ?> Ticket Visibility -->
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="can_view_location_tickets" name="can_view_location_tickets" value="1"
                               <?= !empty(old('can_view_location_tickets', $editing['can_view_location_tickets'] ?? 0)) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="can_view_location_tickets">
                            <?= label('location.singular') ?> Ticket Visibility
                        </label>
                    </div>
                    <div class="form-text">When enabled, this user can view all tickets assigned to their <?= label('location.singular', 'location') ?> — even if they are not an agent or admin.</div>
                </div>

                <!-- Avatar -->
                <div class="col-md-6">
                    <label for="avatar" class="form-label fw-semibold">Avatar Image</label>
                    <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                    <div class="form-text">JPG, PNG, GIF, or WEBP. Max 2 MB.</div>
                </div>

                <?php if ($isEdit && !empty($editing['avatar'])): ?>
                <div class="col-md-6 d-flex align-items-end gap-3">
                    <img src="/uploads/avatars/<?= e($editing['avatar']) ?>" class="rounded" width="48" height="48" style="object-fit:cover;">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remove_avatar" name="remove_avatar" value="1">
                        <label class="form-check-label" for="remove_avatar">Remove current avatar</label>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php
            // ── Group membership ──────────────────────────────────────────────
            // Editable inline when the editor can also manage groups; otherwise
            // it stays the read-only summary with a pointer to Settings → Groups.
            // On create the block starts collapsed and JS reveals it as soon as a
            // staff permission level is picked.
            $canEditGroups   = !empty($canManageGroups);
            $storedIsStaff   = $isEdit && roleIsStaff($editing['role'] ?? null);
            $selectedGroups  = array_map('intval', oldList('groups', $memberGroupIds ?? []));
            $selectedMgrs    = array_map('intval', oldList('group_managers', $managerGroupIds ?? []));
            ?>
            <?php if ($canEditGroups || $isEdit): ?>
            <hr class="my-4">
            <div id="groupMembership" class="<?= $storedIsStaff ? '' : 'd-none' ?>">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                    <label class="form-label fw-semibold mb-0">
                        <i class="bi bi-people me-1"></i>Group Membership
                        <span class="badge rounded-pill text-bg-light border ms-1" id="groupCountBadge"></span>
                    </label>
                    <?php if ($canEditGroups && !empty($allGroups)): ?>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (count($allGroups) > 6): ?>
                        <input type="search" class="form-control form-control-sm" id="groupFilter"
                               placeholder="Filter groups…" style="max-width:190px;" autocomplete="off">
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="groupClearBtn">Clear</button>
                        <a href="/admin/groups" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                            <i class="bi bi-gear me-1"></i>All groups
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mb-2">
                    Group membership decides which tickets this user can see and which auto-assignment
                    rotations they take part in.
                    <?php if ($canEditGroups): ?>Tick <strong>Manager</strong> to let them manage the group's skills without admin access — see <a href="/admin/docs/users#group-managers" target="_blank" rel="noopener">Group Managers</a>.<?php endif; ?>
                </p>

                <?php if ($canEditGroups && !empty($allGroups)): ?>
                    <input type="hidden" name="_groups_present" value="1">

                    <div id="groupConfidentialWarning" class="alert alert-warning py-2 small d-none">
                        <i class="bi bi-shield-lock-fill me-1"></i>
                        <strong>Confidential group selected.</strong>
                        On save, every existing member of that group is emailed the addition along with
                        your name, email, IP and the timestamp. The attempt is recorded in the audit log.
                    </div>

                    <div class="row g-2">
                        <?php foreach ($allGroups as $g): ?>
                        <?php
                        $gid       = (int) $g['id'];
                        $isMember  = in_array($gid, $selectedGroups, true);
                        $isManager = in_array($gid, $selectedMgrs, true);
                        ?>
                        <div class="col-md-6 col-lg-4 group-option"
                             data-search="<?= e(mb_strtolower($g['name'] . ' ' . ($g['description'] ?? ''))) ?>">
                            <div class="border rounded p-2 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-1">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input group-cb" type="checkbox" name="groups[]"
                                               value="<?= $gid ?>" id="group_<?= $gid ?>"
                                               data-gid="<?= $gid ?>"
                                               data-name="<?= e($g['name']) ?>"
                                               data-confidential="<?= !empty($g['is_confidential']) ? '1' : '0' ?>"
                                               <?= $isMember ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="group_<?= $gid ?>">
                                            <span class="fw-semibold"><?= e($g['name']) ?></span>
                                            <?php if (!empty($g['is_confidential'])): ?>
                                                <i class="bi bi-shield-lock-fill text-warning ms-1"
                                                   title="Confidential group"></i>
                                            <?php endif; ?>
                                            <span class="d-block text-muted" style="font-size:.75rem;">
                                                <?= (int) $g['member_count'] ?> member<?= (int) $g['member_count'] === 1 ? '' : 's' ?>
                                                <?php if (!empty($g['description'])): ?>
                                                    · <?= e(mb_strimwidth($g['description'], 0, 48, '…')) ?>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    </div>
                                    <a href="/admin/groups/<?= $gid ?>/edit" target="_blank" rel="noopener"
                                       class="text-muted" title="Open group settings">
                                        <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                </div>
                                <div class="form-check ms-4 mt-1">
                                    <input class="form-check-input group-manager-cb" type="checkbox" name="group_managers[]"
                                           value="<?= $gid ?>" id="group_manager_<?= $gid ?>"
                                           data-gid="<?= $gid ?>"
                                           <?= $isManager ? 'checked' : '' ?>
                                           <?= $isMember ? '' : 'disabled' ?>>
                                    <label class="form-check-label small text-muted" for="group_manager_<?= $gid ?>">
                                        <i class="bi bi-stars" aria-hidden="true"></i> Manager
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="small mt-2 d-none" id="groupNoneWarning"></div>
                    <div class="text-muted small mt-2 d-none" id="groupFilterEmpty">No groups match that filter.</div>
                <?php elseif ($canEditGroups): ?>
                    <div class="alert alert-info mb-0 py-2 small">
                        <i class="bi bi-info-circle me-1"></i>No groups exist yet.
                        <a href="/admin/groups/create">Create one</a> to start scoping ticket visibility.
                    </div>
                <?php elseif (!empty($userGroups ?? [])): ?>
                    <div class="d-flex flex-wrap gap-2 mb-1">
                        <?php foreach ($userGroups as $g): ?>
                            <span class="badge" style="background:var(--ld-primary);font-size:.8rem;">
                                <i class="bi bi-people me-1"></i><?= e($g['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">You need the “Manage groups” permission to change this.</div>
                <?php else: ?>
                    <div class="text-muted small">Not a member of any groups.</div>
                    <div class="form-text">You need the “Manage groups” permission to change this.</div>
                <?php endif; ?>
            </div>

            <div id="groupDemotionNote" class="alert alert-warning py-2 small mt-3 d-none">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                This user still belongs to <strong><span id="groupDemotionCount">0</span></strong> group(s).
                Permission levels below agent don't use group membership, and saving here won't remove those
                rows — clear them from <a href="/admin/groups">Settings → Groups</a> if they should be gone.
            </div>
            <?php endif; ?>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update User' : 'Create User' ?>
                </button>
                <a href="/admin/users" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
// Per-role visibility capabilities, so the confirm dialogs below can warn the
// editor about the security/usefulness implications of the chosen level.
$roleCaps = [];
foreach (array_keys($roleChoicesForActor) as $capSlug) {
    $roleCaps[(string) $capSlug] = [
        'seesAll' => roleIsAdmin($capSlug) || roleCan($capSlug, 'tickets.view_all'),
        'isStaff' => roleIsStaff($capSlug),
    ];
}
// The edited user's own level may not be in the actor's assignable list (they
// outrank the editor, so the select is locked). Include it anyway or the JS has
// no capabilities to read and would wrongly hide the group picker.
$storedSlug = $isEdit ? (string) ($editing['role'] ?? '') : '';
if ($storedSlug !== '' && !isset($roleCaps[$storedSlug])) {
    $roleCaps[$storedSlug] = [
        'seesAll' => roleIsAdmin($storedSlug) || roleCan($storedSlug, 'tickets.view_all'),
        'isStaff' => roleIsStaff($storedSlug),
    ];
}
?>
<!-- No-group confirmation: a staff user with no group can't see any tickets -->
<div class="modal fade" id="noGroupModal" tabindex="-1" aria-labelledby="noGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#92400e,#b45309); color:#fff;">
                <h5 class="modal-title fw-bold" id="noGroupModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>No Group Selected
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    <span id="noGroupWho">This user</span> is being <span id="noGroupVerb">created</span> with the
                    <strong><span id="noGroupLevel">staff</span></strong> permission level but no group membership.
                </p>
                <p class="mb-2 text-muted small">Group membership is what scopes ticket visibility, so without one:</p>
                <ul class="mb-3">
                    <li>They will see <strong>no tickets at all</strong> — empty ticket lists and an empty dashboard.</li>
                    <li>They can't be picked by any group's auto-assignment rotation.</li>
                    <li>They won't receive new-ticket notifications for any group.</li>
                </ul>
                <div class="alert alert-info mb-0 py-2 small">
                    <i class="bi bi-info-circle me-1"></i>
                    They can still log in, and you can add groups at any time from this page or from
                    <strong>Settings → Groups</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn text-white" style="background:var(--ld-primary);" data-bs-dismiss="modal">
                    <i class="bi bi-people me-1"></i>Pick a group
                </button>
                <button type="button" class="btn btn-outline-warning" id="confirmNoGroupBtn">
                    <span id="noGroupConfirmLabel">Create without a group</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm-add-to-confidential-group modal (mirrors the group edit page) -->
<div class="modal fade" id="userConfidentialAddModal" tabindex="-1" aria-labelledby="userConfidentialAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#92400e,#b45309); color:#fff;">
                <h5 class="modal-title fw-bold" id="userConfidentialAddModalLabel">
                    <i class="bi bi-shield-lock-fill me-2"></i>Confidential Group — Confirm Addition
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">You are about to add this user to <strong><span id="userConfidentialAddCount">0</span></strong> confidential group(s):</p>
                <ul id="userConfidentialAddList" class="mb-3"></ul>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    All <strong>current members</strong> of those groups will immediately receive an email alert
                    naming this user, your name and email, your IP address, and the timestamp. The action and the
                    attempt itself are recorded in the audit log.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-white" id="userConfirmConfidentialAddBtn">
                    <i class="bi bi-check-lg me-1"></i>Confirm & Notify Members
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const caps = <?= json_encode($roleCaps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const form = document.querySelector('form[action="<?= e($action) ?>"]');
    if (!form) return;

    // ── Group membership picker ───────────────────────────────────────────────
    const section     = document.getElementById('groupMembership');
    const memberCbs   = Array.from(form.querySelectorAll('.group-cb'));
    const totalGroups = memberCbs.length;
    // Membership as saved on the server, so we can tell a *new* confidential
    // addition apart from one that was already there.
    const savedIds    = <?= json_encode(array_map('intval', $memberGroupIds ?? [])) ?>;
    const savedCount  = savedIds.length;

    const countBadge   = document.getElementById('groupCountBadge');
    const noneWarning  = document.getElementById('groupNoneWarning');
    const confWarning  = document.getElementById('groupConfidentialWarning');
    const demotionNote = document.getElementById('groupDemotionNote');

    function currentRole() {
        const el = form.elements['role'];
        return el ? el.value : null;
    }
    function selectedIds() {
        return memberCbs.filter(cb => cb.checked).map(cb => parseInt(cb.dataset.gid, 10));
    }
    function newConfidentialGroups() {
        return memberCbs
            .filter(cb => cb.checked
                && cb.dataset.confidential === '1'
                && savedIds.indexOf(parseInt(cb.dataset.gid, 10)) === -1)
            .map(cb => cb.dataset.name);
    }

    // Manager only means something for an actual member — mirror the group form.
    memberCbs.forEach(function (cb) {
        const mgr = document.getElementById('group_manager_' + cb.dataset.gid);
        cb.addEventListener('change', function () {
            if (mgr) {
                mgr.disabled = !cb.checked;
                if (!cb.checked) mgr.checked = false;
            }
            refresh();
        });
    });

    function refresh() {
        const slug     = currentRole();
        const cap      = slug && caps[slug] ? caps[slug] : null;
        const isStaff  = cap ? cap.isStaff : false;
        const seesAll  = cap ? cap.seesAll : false;
        const selected = selectedIds();

        // Only staff levels use group membership — hide the whole block otherwise,
        // and point out any memberships that will be left dangling.
        if (section) {
            section.classList.toggle('d-none', !isStaff);
        }
        if (demotionNote) {
            const show = !isStaff && savedCount > 0;
            demotionNote.classList.toggle('d-none', !show);
            const c = document.getElementById('groupDemotionCount');
            if (c) c.textContent = savedCount;
        }
        if (!totalGroups) return;

        if (countBadge) {
            countBadge.textContent = selected.length + ' of ' + totalGroups + ' selected';
        }
        if (confWarning) {
            confWarning.classList.toggle('d-none', newConfidentialGroups().length === 0);
        }
        if (noneWarning) {
            const locVis    = form.querySelector('#can_view_location_tickets');
            const hasLocVis = locVis && locVis.checked;
            if (selected.length > 0 || !isStaff) {
                noneWarning.classList.add('d-none');
            } else if (seesAll) {
                noneWarning.className = 'small mt-2 text-muted';
                noneWarning.innerHTML = '<i class="bi bi-info-circle me-1"></i>No groups selected — this permission level can already see <strong>all tickets</strong>.';
            } else if (hasLocVis) {
                noneWarning.className = 'small mt-2 text-muted';
                noneWarning.innerHTML = '<i class="bi bi-info-circle me-1"></i>No groups selected — this user will only see tickets for their assigned <?= e(label('location.singular', 'location')) ?>.';
            } else {
                noneWarning.className = 'small mt-2 text-danger';
                noneWarning.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>No groups selected — this user will <strong>not be able to see any tickets</strong>.';
            }
        }
    }

    const roleEl = form.elements['role'];
    if (roleEl) roleEl.addEventListener('change', refresh);
    const locVisEl = form.querySelector('#can_view_location_tickets');
    if (locVisEl) locVisEl.addEventListener('change', refresh);

    const clearBtn = document.getElementById('groupClearBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            memberCbs.forEach(function (cb) {
                cb.checked = false;
                const mgr = document.getElementById('group_manager_' + cb.dataset.gid);
                if (mgr) { mgr.checked = false; mgr.disabled = true; }
            });
            refresh();
        });
    }

    const filterInput = document.getElementById('groupFilter');
    if (filterInput) {
        const empty = document.getElementById('groupFilterEmpty');
        filterInput.addEventListener('input', function () {
            const q = filterInput.value.trim().toLowerCase();
            let shown = 0;
            form.querySelectorAll('.group-option').forEach(function (opt) {
                const hit = q === '' || (opt.dataset.search || '').indexOf(q) !== -1;
                opt.classList.toggle('d-none', !hit);
                if (hit) shown++;
            });
            if (empty) empty.classList.toggle('d-none', shown !== 0);
        });
    }

    refresh();

    // ── Submit guards ─────────────────────────────────────────────────────────
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;

    let confidentialConfirmed = false;
    const modalEl = document.getElementById('userConfidentialAddModal');
    let modalInstance = null;

    let noGroupConfirmed = false;
    const noGroupEl = document.getElementById('noGroupModal');
    let noGroupModal = null;

    function showNoGroupModal(slug) {
        const fn    = (form.querySelector('#first_name') || {}).value || '';
        const ln    = (form.querySelector('#last_name') || {}).value || '';
        const name  = (fn + ' ' + ln).trim();
        const roleEl2 = form.querySelector('#role');
        const level = roleEl2 && roleEl2.selectedOptions && roleEl2.selectedOptions.length
            ? roleEl2.selectedOptions[0].textContent.trim()
            : slug;

        document.getElementById('noGroupWho').textContent   = name !== '' ? name : 'This user';
        document.getElementById('noGroupVerb').textContent  = isEdit ? 'saved' : 'created';
        document.getElementById('noGroupLevel').textContent = level;
        document.getElementById('noGroupConfirmLabel').textContent =
            isEdit ? 'Save without a group' : 'Create without a group';

        if (!noGroupModal) noGroupModal = new bootstrap.Modal(noGroupEl);
        noGroupModal.show();
    }

    const noGroupBtn = document.getElementById('confirmNoGroupBtn');
    if (noGroupBtn) {
        noGroupBtn.addEventListener('click', function () {
            noGroupConfirmed = true;
            if (noGroupModal) noGroupModal.hide();
            form.submit();
        });
    }

    // Picking a group after being warned re-arms the guard for the next attempt.
    memberCbs.forEach(function (cb) {
        cb.addEventListener('change', function () { noGroupConfirmed = false; });
    });

    form.addEventListener('submit', function (e) {
        const slug = currentRole();
        const cap  = slug && caps[slug] ? caps[slug] : null;

        if (cap) {
            // Warn when granting all-ticket visibility (a privacy-sensitive level).
            if (cap.seesAll) {
                if (!confirm('This permission level can see ALL tickets in the system, across every group (confidential tickets excluded). Continue?')) {
                    e.preventDefault();
                    return;
                }
            } else if (!noGroupConfirmed) {
                // A staff level with no group sees nothing at all — make the
                // consequences explicit, but let them go ahead if they mean it.
                const locVis     = form.querySelector('#can_view_location_tickets');
                const hasLocVis  = locVis && locVis.checked;
                const groupCount = totalGroups ? selectedIds().length : savedCount;
                if (cap.isStaff && groupCount === 0 && !hasLocVis) {
                    // Only offer the modal when there is a picker to send them
                    // back to; otherwise fall back to a plain confirm.
                    if (totalGroups > 0 && noGroupEl && window.bootstrap) {
                        e.preventDefault();
                        showNoGroupModal(slug);
                        return;
                    }
                    if (!confirm('This user has a staff permission level but belongs to no group and has no “view all” access. They will be able to log in but will not see any tickets. Continue?')) {
                        e.preventDefault();
                        return;
                    }
                }
            }
        }

        // Adding someone to a confidential group emails every existing member —
        // make that explicit before it happens.
        if (!confidentialConfirmed && cap && cap.isStaff && modalEl) {
            const added = newConfidentialGroups();
            if (added.length > 0) {
                e.preventDefault();
                document.getElementById('userConfidentialAddCount').textContent = added.length;
                const list = document.getElementById('userConfidentialAddList');
                list.innerHTML = '';
                added.forEach(function (name) {
                    const li = document.createElement('li');
                    li.textContent = name;
                    list.appendChild(li);
                });
                if (!modalInstance) modalInstance = new bootstrap.Modal(modalEl);
                modalInstance.show();
            }
        }
    });

    const confirmBtn = document.getElementById('userConfirmConfidentialAddBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            confidentialConfirmed = true;
            if (modalInstance) modalInstance.hide();
            form.submit();
        });
    }
})();
</script>
