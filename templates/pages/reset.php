<?php
$layout    = 'auth';
$pageTitle = 'Choose a New Password';
?>
<div style="max-width:420px;width:100%;">
    <div class="text-center mb-4">
        <h1 class="text-white fw-bold"><i class="bi <?= e(getSetting('branding_navbar_icon', 'bi-headset')) ?>" aria-hidden="true"></i> <?= e(getSetting('branding_app_name', 'OpenHelpDesk')) ?></h1>
        <p class="text-white-50">Choose a new password</p>
    </div>

    <div class="card shadow-lg border-0" style="border-radius:1rem;">
        <div class="card-body p-4">

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert" aria-live="assertive">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($token)): ?>
                <a href="/forgot" class="btn w-100 py-2 fw-semibold text-white"
                   style="background-color:var(--ld-primary);border-color:var(--ld-primary);">
                    <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>Request a new link
                </a>
                <div class="text-center mt-3">
                    <a href="/login" class="small text-decoration-none">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to sign in
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($email)): ?>
                    <p class="text-muted small mb-3">
                        Resetting password for <strong><?= e($email) ?></strong>.
                    </p>
                <?php endif; ?>

                <form method="POST" action="/reset">
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="At least 8 characters" required minlength="8"
                                   autofocus autocomplete="new-password">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirm" class="form-label fw-semibold">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                                   placeholder="Re-enter your new password" required minlength="8"
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn w-100 py-2 fw-semibold text-white"
                            style="background-color:var(--ld-primary);border-color:var(--ld-primary);">
                        <i class="bi bi-check-circle me-2" aria-hidden="true"></i>Reset password
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-center text-white-50 mt-3 small">
        <?= e(getSetting('branding_app_name', 'OpenHelpDesk')) ?> &copy; <?= date('Y') ?>
    </p>
</div>
