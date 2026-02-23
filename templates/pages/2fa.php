<?php
$layout    = 'auth';
$pageTitle = 'Two-Factor Authentication';
?>
<div style="max-width:420px;width:100%;">
    <div class="text-center mb-4">
        <h1 class="text-white fw-bold"><i class="bi bi-headset"></i> LocalDesk</h1>
        <p class="text-white-50">Two-Factor Authentication</p>
    </div>

    <div class="card shadow-lg border-0" style="border-radius:1rem;">
        <div class="card-body p-4">
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span><?= e($error) ?></span>
            </div>
            <?php endif; ?>

            <div class="text-center mb-4">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:56px;height:56px;">
                    <i class="bi bi-shield-lock text-primary" style="font-size:1.5rem;"></i>
                </div>
                <p class="text-muted small mb-0">
                    Enter the 6-digit code from your authenticator app to continue.
                </p>
            </div>

            <form method="POST" action="/2fa">
                <?= csrfField() ?>
                <div class="mb-4">
                    <label for="code" class="form-label fw-semibold">Verification Code</label>
                    <input type="text" class="form-control form-control-lg text-center fw-bold"
                           id="code" name="code"
                           placeholder="000000"
                           maxlength="6"
                           inputmode="numeric"
                           pattern="\d{6}"
                           autocomplete="one-time-code"
                           required autofocus
                           style="letter-spacing:.4em;font-size:1.5rem;">
                </div>

                <button type="submit" class="btn w-100 py-2 fw-semibold text-white"
                        style="background-color:var(--ld-primary);border-color:var(--ld-primary);">
                    <i class="bi bi-check-circle me-2"></i>Verify
                </button>
            </form>
        </div>
    </div>

    <p class="text-center mt-3">
        <a href="/login" class="text-white-50 small">
            <i class="bi bi-arrow-left me-1"></i>Back to sign in
        </a>
    </p>

    <p class="text-center text-white-50 mt-2 small">
        <?= e(getSetting('branding_app_name', 'LocalDesk')) ?> &copy; <?= date('Y') ?>
    </p>
</div>
