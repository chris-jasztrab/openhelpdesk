<?php
$layout      = 'app';
$pageTitle   = 'My Profile';
$breadcrumbs = [
    ['label' => 'My Profile'],
];
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center"
                 style="width:56px;height:56px;font-size:1.25rem;font-weight:600;border:2px solid var(--ld-primary);">
                <?= Auth::initials() ?>
            </div>
            <div>
                <h2 class="fw-bold mb-0">My Profile</h2>
                <span class="text-muted small"><?= e($user['email']) ?></span>
                <span class="badge bg-primary ms-1"><?= e(ucfirst($user['role'])) ?></span>
            </div>
        </div>

        <form method="POST" action="/profile">
            <?= csrfField() ?>

            <!-- Name -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-person me-1"></i>Personal Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name"
                                   value="<?= e(old('first_name', $user['first_name'])) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name"
                                   value="<?= e(old('last_name', $user['last_name'])) ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-shield-lock me-1"></i>Change Password
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Leave blank to keep your current password.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" name="current_password"
                                   autocomplete="current-password">
                        </div>
                        <div class="col-md-6">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password"
                                   minlength="8" autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password"
                                   autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
        </form>
    </div>
</div>
