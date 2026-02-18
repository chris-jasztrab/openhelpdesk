<?php
$layout       = 'app';
$pageTitle    = 'Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<!-- Email / SMTP Configuration -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope me-2"></i>Email / SMTP Configuration</h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="/admin/settings">
            <?= csrfField() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label for="smtp_host" class="form-label fw-semibold">SMTP Host</label>
                    <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                           value="<?= e($settings['smtp_host']) ?>"
                           placeholder="e.g. smtp.gmail.com">
                </div>
                <div class="col-md-4">
                    <label for="smtp_port" class="form-label fw-semibold">Port</label>
                    <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                           value="<?= e($settings['smtp_port'] ?: '587') ?>"
                           placeholder="587">
                </div>
            </div>

            <div class="mb-3">
                <label for="smtp_encryption" class="form-label fw-semibold">Encryption</label>
                <select class="form-select" id="smtp_encryption" name="smtp_encryption" style="max-width:200px;">
                    <option value="tls" <?= ($settings['smtp_encryption'] ?: 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= $settings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="none" <?= $settings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="smtp_username" class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                           value="<?= e($settings['smtp_username']) ?>"
                           placeholder="e.g. user@example.com" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label for="smtp_password" class="form-label fw-semibold">Password</label>
                    <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                           value="" placeholder="<?= $settings['smtp_password'] !== '' ? '••••••••' : '' ?>"
                           autocomplete="new-password">
                    <?php if ($settings['smtp_password'] !== ''): ?>
                        <div class="form-text">Leave blank to keep the current password.</div>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="mail_from_address" class="form-label fw-semibold">From Address</label>
                    <input type="email" class="form-control" id="mail_from_address" name="mail_from_address"
                           value="<?= e($settings['mail_from_address']) ?>"
                           placeholder="e.g. noreply@example.com">
                </div>
                <div class="col-md-6">
                    <label for="mail_from_name" class="form-label fw-semibold">From Name</label>
                    <input type="text" class="form-control" id="mail_from_name" name="mail_from_name"
                           value="<?= e($settings['mail_from_name']) ?>"
                           placeholder="e.g. LocalDesk">
                </div>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
        </form>
    </div>
</div>

<!-- Test Email -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-send me-2"></i>Test Email</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            Send a test email to <strong><?= e(Auth::user()['email'] ?? '') ?></strong> to verify your SMTP configuration.
        </p>
        <form method="POST" action="/admin/settings/test-email">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-envelope-arrow-up me-1"></i>Send Test Email
            </button>
        </form>
    </div>
</div>
