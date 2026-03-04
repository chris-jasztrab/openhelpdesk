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
                           value="<?= e(old('email', $editing['email'] ?? '')) ?>" required>
                </div>

                <!-- Password -->
                <div class="col-md-6">
                    <label for="password" class="form-label fw-semibold">
                        Password <?= $isEdit ? '<span class="text-muted fw-normal">(leave blank to keep current)</span>' : '<span class="text-danger">*</span>' ?>
                    </label>
                    <input type="password" class="form-control" id="password" name="password"
                           <?= $isEdit ? '' : 'required' ?>>
                </div>

                <!-- Role -->
                <div class="col-md-4">
                    <label for="role" class="form-label fw-semibold">Permission Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role" required>
                        <?php
                        $currentRole = old('role', $editing['role'] ?? 'user');
                        foreach (['admin' => 'Admin', 'agent' => 'Agent', 'power_user' => 'Power User', 'user' => 'End User'] as $val => $label):
                        ?>
                        <option value="<?= $val ?>" <?= $currentRole === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
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

            <?php if ($isEdit && in_array($editing['role'] ?? '', ['agent', 'admin', 'power_user'])): ?>
            <hr class="my-4">
            <div>
                <label class="form-label fw-semibold">Group Membership</label>
                <?php if (!empty($userGroups)): ?>
                    <div class="d-flex flex-wrap gap-2 mb-1">
                        <?php foreach ($userGroups as $g): ?>
                            <a href="/admin/groups/<?= $g['id'] ?>/edit" class="badge text-decoration-none"
                               style="background:var(--ld-primary);font-size:.8rem;">
                                <i class="bi bi-people me-1"></i><?= e($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">This <?= $editing['role'] === 'admin' ? 'admin' : 'agent' ?> belongs to the groups shown above. Group membership controls which tickets they can see. Manage membership from <a href="/admin/groups">Admin → Settings → Groups</a>.</div>
                <?php else: ?>
                    <div class="text-muted small">Not a member of any groups — can see all tickets.</div>
                    <div class="form-text">Assign this <?= $editing['role'] === 'admin' ? 'admin' : 'agent' ?> to groups from <a href="/admin/groups">Admin → Settings → Groups</a>.</div>
                <?php endif; ?>
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
