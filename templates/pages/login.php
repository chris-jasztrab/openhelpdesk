<?php
$layout    = 'auth';
$pageTitle = 'Sign In';
?>
<div style="max-width:420px;width:100%;">
    <div class="text-center mb-4">
        <h1 class="text-white fw-bold"><i class="bi <?= e(getSetting('branding_navbar_icon', 'bi-headset')) ?>"></i> <?= e(getSetting('branding_app_name', 'LocalDesk')) ?></h1>
        <p class="text-white-50">Sign in to your account</p>
    </div>

    <div class="card shadow-lg border-0" style="border-radius:1rem;">
        <div class="card-body p-4">
            <?php if (!empty($_GET['setup'])): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <span>Setup complete! Sign in with your new admin account.</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span><?= e($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="/login">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= e($email ?? '') ?>" placeholder="you@example.com"
                               required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn w-100 py-2 fw-semibold text-white"
                        style="background-color:var(--ld-primary);border-color:var(--ld-primary);">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-white-50 mt-3 small">
        <?= e(getSetting('branding_app_name', 'LocalDesk')) ?> &copy; <?= date('Y') ?>
    </p>
</div>
