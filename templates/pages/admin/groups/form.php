<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Group' : 'Add Group';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Groups', 'url' => '/admin/groups'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/groups/{$editing['id']}/edit" : '/admin/groups/create';
$roleColors = ['admin' => 'danger', 'agent' => 'primary'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Group' : 'Add Group' ?></h2>
    <a href="/admin/groups" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Group Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order"
                       value="<?= e(old('sort_order', (string) ($editing['sort_order'] ?? 0))) ?>" min="0" style="max-width:120px;">
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label class="form-label fw-semibold">Members</label>
                <p class="text-muted small mb-2">Select agents and admins to include in this group.</p>

                <div id="confidentialWarning" class="alert alert-warning d-none mb-3">
                    <i class="bi bi-shield-lock-fill me-1"></i>
                    <strong>Confidential group.</strong>
                    Every current member will receive an email alert listing any new members you add and your identity (name, email, IP, timestamp). Every attempt is recorded in the audit log. The first member added to an empty group is silent.
                </div>

                <?php if (empty($users)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-1"></i>No agents or admins found. <a href="/admin/users/create">Create a user</a> first.
                    </div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($users as $u): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check border rounded p-2 ps-4">
                                <input class="form-check-input" type="checkbox" name="members[]"
                                       value="<?= $u['id'] ?>" id="member_<?= $u['id'] ?>"
                                       <?= in_array($u['id'], $memberIds) ? 'checked' : '' ?>>
                                <label class="form-check-label w-100" for="member_<?= $u['id'] ?>">
                                    <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                                    <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?> bg-opacity-10 text-<?= $roleColors[$u['role']] ?? 'secondary' ?> ms-1"><?= e(ucfirst($u['role'])) ?></span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label class="form-label fw-semibold">Notifications</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="notify_new_ticket" value="1"
                           id="notify_new_ticket"
                           <?= !empty($editing['notify_new_ticket']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_new_ticket">
                        Notify members when a new ticket is created
                    </label>
                </div>
                <div class="form-text">Members can opt out individually via their profile Email Notifications settings.</div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="is_confidential" name="is_confidential" value="1"
                           <?= (int) old('is_confidential', (string) ($editing['is_confidential'] ?? '0')) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="is_confidential">
                        <i class="bi bi-shield-lock me-1"></i>Confidential
                    </label>
                </div>
                <div class="form-text">When enabled, every current member of the group will receive an email alert whenever new members are added. The first member added is silent — alerts begin once the group has at least one existing member.</div>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Group' : 'Create Group' ?>
                </button>
                <a href="/admin/groups" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Confirm-add-to-confidential-group modal -->
<div class="modal fade" id="confidentialAddModal" tabindex="-1" aria-labelledby="confidentialAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#92400e,#b45309); color:#fff;">
                <h5 class="modal-title fw-bold" id="confidentialAddModalLabel">
                    <i class="bi bi-shield-lock-fill me-2"></i>Confidential Group — Confirm Member Addition
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">You are about to add <strong><span id="confidentialAddCount">0</span></strong> new member(s) to a confidential group:</p>
                <ul id="confidentialAddList" class="mb-3"></ul>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    All <strong>current members</strong> of this group will immediately receive an email alert naming the new member(s), your name and email, your IP address, and the timestamp. This action and the attempt itself are recorded in the audit log.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-white" id="confirmConfidentialAddBtn">
                    <i class="bi bi-check-lg me-1"></i>Confirm & Notify Members
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var confCb        = document.getElementById('is_confidential');
    var warningBanner = document.getElementById('confidentialWarning');
    var form          = confCb ? confCb.closest('form') : null;
    if (!confCb || !warningBanner || !form) return;

    // Existing member IDs at page load (server-side state, before any UI changes)
    var existingMemberIds = <?= json_encode(array_map('intval', $memberIds ?? [])) ?>;

    function toggleBanner() {
        if (confCb.checked) warningBanner.classList.remove('d-none');
        else warningBanner.classList.add('d-none');
    }
    confCb.addEventListener('change', toggleBanner);
    toggleBanner();

    // Compute which checked members are NEW relative to the saved state
    function getNewlyAddedMembers() {
        var added = [];
        var checkboxes = form.querySelectorAll('input[name="members[]"]');
        checkboxes.forEach(function (cb) {
            if (cb.checked && existingMemberIds.indexOf(parseInt(cb.value, 10)) === -1) {
                var label = form.querySelector('label[for="' + cb.id + '"]');
                added.push({
                    id: cb.value,
                    name: label ? label.textContent.trim().replace(/\s+/g, ' ') : ('User #' + cb.value)
                });
            }
        });
        return added;
    }

    var modalEl       = document.getElementById('confidentialAddModal');
    var modalInstance = null;
    var confirmed     = false;

    form.addEventListener('submit', function (e) {
        if (confirmed) return;             // already passed the confirm step
        if (!confCb.checked) return;       // not confidential — no warning needed
        var added = getNewlyAddedMembers();
        if (added.length === 0) return;    // no new additions — no warning needed

        // Intercept and show the modal
        e.preventDefault();
        document.getElementById('confidentialAddCount').textContent = added.length;
        var listEl = document.getElementById('confidentialAddList');
        listEl.innerHTML = '';
        added.forEach(function (u) {
            var li = document.createElement('li');
            li.textContent = u.name;
            listEl.appendChild(li);
        });
        if (!modalInstance) modalInstance = new bootstrap.Modal(modalEl);
        modalInstance.show();
    });

    document.getElementById('confirmConfidentialAddBtn').addEventListener('click', function () {
        confirmed = true;
        if (modalInstance) modalInstance.hide();
        form.submit();
    });
})();
</script>
