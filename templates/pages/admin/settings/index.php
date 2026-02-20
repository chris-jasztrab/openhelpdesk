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
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-send me-2"></i>Test Email</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">Send a test email to verify your SMTP configuration.</p>
        <form method="POST" action="/admin/settings/test-email" class="d-flex gap-2 align-items-start" style="max-width:420px;">
            <?= csrfField() ?>
            <input type="email" name="to_email" class="form-control" required
                   value="<?= e(Auth::user()['email'] ?? '') ?>"
                   placeholder="recipient@example.com">
            <button type="submit" class="btn btn-outline-primary text-nowrap">
                <i class="bi bi-envelope-arrow-up me-1"></i>Send
            </button>
        </form>
    </div>
</div>

<!-- Inbound Mail / Reply Processing -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope-arrow-down me-2"></i>Inbound Mail — Reply Processing</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            When enabled, users can reply directly to ticket notification emails and their reply will be
            added as a comment on the ticket. You need a dedicated mailbox that accepts these replies,
            and a cron job to poll it.
        </p>

        <?php if (!extension_loaded('imap')): ?>
        <div class="alert alert-warning mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>PHP IMAP extension not loaded.</strong>
            Enable <code>extension=imap</code> in your <code>php.ini</code> and restart your web server before using this feature.
        </div>
        <?php endif; ?>

        <div class="alert alert-info mb-4" style="font-size:.875rem;">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Office 365 note:</strong> Microsoft has restricted Basic Authentication for IMAP.
            Use an <strong>App Password</strong> (requires MFA to be enabled on the account), or ask your
            Microsoft 365 admin to re-enable Basic Auth for IMAP via the Exchange Admin Center.
            Host: <code>outlook.office365.com</code> &bull; Port: <code>993</code> &bull; Encryption: <code>SSL</code>.
        </div>

        <form method="POST" action="/admin/settings/imap">
            <?= csrfField() ?>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="imap_enabled" name="imap_enabled" value="1"
                           <?= $settings['imap_enabled'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="imap_enabled">Enable reply-by-email</label>
                </div>
                <div class="form-text">When enabled, outgoing ticket emails will have a Reply-To set to the inbox below.</div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label for="imap_reply_to" class="form-label fw-semibold">Reply-To Address</label>
                <input type="email" class="form-control" id="imap_reply_to" name="imap_reply_to"
                       value="<?= e($settings['imap_reply_to']) ?>"
                       placeholder="tickets@yourdomain.com" style="max-width:360px;">
                <div class="form-text">The email address users reply to. This mailbox must match the IMAP credentials below.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label for="imap_host" class="form-label fw-semibold">IMAP Host</label>
                    <input type="text" class="form-control" id="imap_host" name="imap_host"
                           value="<?= e($settings['imap_host']) ?>"
                           placeholder="outlook.office365.com">
                </div>
                <div class="col-md-3">
                    <label for="imap_port" class="form-label fw-semibold">Port</label>
                    <input type="number" class="form-control" id="imap_port" name="imap_port"
                           value="<?= e($settings['imap_port'] ?: '993') ?>"
                           placeholder="993">
                </div>
                <div class="col-md-2">
                    <label for="imap_encryption" class="form-label fw-semibold">Encryption</label>
                    <select class="form-select" id="imap_encryption" name="imap_encryption">
                        <option value="ssl" <?= ($settings['imap_encryption'] ?: 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="tls" <?= $settings['imap_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="none" <?= $settings['imap_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="imap_username" class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control" id="imap_username" name="imap_username"
                           value="<?= e($settings['imap_username']) ?>"
                           placeholder="tickets@yourdomain.com" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label for="imap_password" class="form-label fw-semibold">Password / App Password</label>
                    <input type="password" class="form-control" id="imap_password" name="imap_password"
                           value="" placeholder="<?= $settings['imap_password'] !== '' ? '••••••••' : '' ?>"
                           autocomplete="new-password">
                    <?php if ($settings['imap_password'] !== ''): ?>
                        <div class="form-text">Leave blank to keep the current password.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-4">
                <label for="imap_folder" class="form-label fw-semibold">Mailbox Folder</label>
                <input type="text" class="form-control" id="imap_folder" name="imap_folder"
                       value="<?= e($settings['imap_folder'] ?: 'INBOX') ?>"
                       placeholder="INBOX" style="max-width:200px;">
            </div>

            <hr class="my-4">

            <div class="mb-4">
                <h6 class="fw-semibold mb-1">Cron Job</h6>
                <p class="text-muted small mb-2">Add this to your server's crontab to poll for new replies every 5 minutes:</p>
                <code class="d-block bg-light border rounded p-2 small user-select-all">
                    */5 * * * * php <?= e(ROOT_DIR) ?>/scripts/process-replies.php &gt;&gt; <?= e(ROOT_DIR) ?>/storage/logs/imap.log 2&gt;&amp;1
                </code>
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Inbound Settings
            </button>
        </form>
    </div>
</div>
