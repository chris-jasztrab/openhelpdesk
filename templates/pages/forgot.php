<?php
$layout    = 'auth';
$pageTitle = 'Reset Password';
?>
<div style="max-width:420px;width:100%;">
    <div class="text-center mb-4">
        <h1 class="text-white fw-bold"><i class="bi <?= e(getSetting('branding_navbar_icon', 'bi-headset')) ?>" aria-hidden="true"></i> <?= e(getSetting('branding_app_name', 'OpenHelpDesk')) ?></h1>
        <p class="text-white-50">Forgot your password?</p>
    </div>

    <div class="card shadow-lg border-0" style="border-radius:1rem;">
        <div class="card-body p-4">

            <?php if (!empty($sent)): ?>
                <div class="alert alert-success d-flex align-items-start" role="status" aria-live="polite">
                    <i class="bi bi-envelope-check-fill me-2 mt-1" aria-hidden="true"></i>
                    <div>
                        <strong>Check your email.</strong><br>
                        If an account exists for <strong><?= e($email ?? '') ?></strong>, a password reset link is on its way. It will expire in 60 minutes.
                    </div>
                </div>
                <a href="/login" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to sign in
                </a>
            <?php else: ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert" aria-live="assertive">
                        <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <p class="text-muted small mb-3">
                    Enter the email address associated with your account and we&rsquo;ll send you a link to reset your password.
                </p>

                <form method="POST" action="/forgot">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= e($email ?? '') ?>" placeholder="you@example.com"
                                   required autofocus autocomplete="email">
                        </div>
                    </div>

                    <button type="submit" class="btn w-100 py-2 fw-semibold text-white"
                            style="background-color:var(--ld-primary);border-color:var(--ld-primary);">
                        <i class="bi bi-send me-2" aria-hidden="true"></i>Send reset link
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="/login" class="small text-decoration-none">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to sign in
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-center text-white-50 mt-3 small">
        <?= e(getSetting('branding_app_name', 'OpenHelpDesk')) ?> &copy; <?= date('Y') ?>
    </p>
</div>
