<?php
$layout      = 'app';
$pageTitle   = 'Set Up Two-Factor Authentication';
$breadcrumbs = [
    ['label' => 'My Profile', 'url' => '/profile'],
    ['label' => 'Set Up 2FA'],
];
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                 style="width:48px;height:48px;">
                <i class="bi bi-shield-lock text-primary" style="font-size:1.25rem;"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0">Set Up Two-Factor Authentication</h2>
                <p class="text-muted small mb-0">Protect your account with an authenticator app.</p>
            </div>
        </div>

        <!-- Step 1 -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <span class="badge bg-primary me-2">1</span>Scan the QR Code
            </div>
            <div class="card-body text-center py-4">
                <p class="text-muted small mb-3">
                    Open <strong>Google Authenticator</strong>, <strong>Authy</strong>, or any TOTP app and scan this code.
                </p>
                <img src="<?= e($qrUrl) ?>" alt="QR Code" width="200" height="200"
                     class="border rounded p-2 bg-white mb-3">
                <div>
                    <p class="text-muted small mb-1">Or enter this key manually:</p>
                    <code class="d-block bg-light rounded px-3 py-2 user-select-all fw-bold"
                          style="letter-spacing:.15em;font-size:1rem;">
                        <?= e(wordwrap($secret, 4, ' ', true)) ?>
                    </code>
                    <p class="text-muted" style="font-size:.7rem;">Time-based · SHA1 · 6 digits · 30 second period</p>
                </div>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <span class="badge bg-primary me-2">2</span>Verify Your Setup
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Enter the 6-digit code shown in your authenticator app to confirm setup.
                </p>
                <form method="POST" action="/profile/2fa/setup">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label for="code" class="form-label fw-semibold">Verification Code</label>
                        <input type="text" class="form-control text-center fw-bold"
                               id="code" name="code"
                               placeholder="000000"
                               maxlength="6"
                               inputmode="numeric"
                               pattern="\d{6}"
                               autocomplete="one-time-code"
                               required autofocus
                               style="letter-spacing:.4em;font-size:1.25rem;max-width:180px;">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                            <i class="bi bi-shield-check me-1"></i>Enable 2FA
                        </button>
                        <a href="/profile" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="alert alert-info d-flex gap-2" role="alert">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div class="small">
                <strong>Important:</strong> If you lose access to your authenticator app, contact an administrator to reset your 2FA.
                Store your authenticator backup codes in a safe place.
            </div>
        </div>
    </div>
</div>
