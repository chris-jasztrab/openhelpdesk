<?php
$layout       = 'app';
$pageTitle    = 'SSO / Microsoft 365 – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'SSO / Microsoft 365'],
];
$redirectUri = appUrl() . '/auth/microsoft/callback';
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2"></i>SSO / Microsoft 365</h5>
    <a href="/admin/settings/sso/help" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-question-circle me-1"></i>Setup Guide
    </a>
</div>

<form method="POST" action="/admin/settings/sso">
    <?= csrfField() ?>

    <!-- Enable / Disable -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-toggle-on me-1"></i>Single Sign-On
        </div>
        <div class="card-body">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="sso_enabled" name="sso_enabled" value="1"
                       <?= ($ssoEnabled ?? '0') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="sso_enabled">
                    Enable Microsoft 365 SSO
                </label>
            </div>
            <div class="form-text">
                When enabled, a "Sign in with Microsoft 365" button appears on the login page.
                Password-based login remains available alongside SSO.
            </div>
        </div>
    </div>

    <!-- Azure AD Credentials -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-cloud me-1"></i>Azure AD Credentials
        </div>
        <div class="card-body">
            <div class="alert alert-info small py-2 mb-4">
                <i class="bi bi-info-circle me-1"></i>
                You need an app registered in the <strong>Azure Portal</strong> to use SSO.
                See the <a href="/admin/settings/sso/help" class="alert-link">Setup Guide</a> for step-by-step instructions.
            </div>

            <div class="mb-3">
                <label for="sso_tenant_id" class="form-label fw-semibold">
                    Directory (Tenant) ID <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control font-monospace" id="sso_tenant_id" name="sso_tenant_id"
                       value="<?= e($ssoTenantId ?? '') ?>"
                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                <div class="form-text">Found on your Azure app's Overview page.</div>
            </div>

            <div class="mb-3">
                <label for="sso_client_id" class="form-label fw-semibold">
                    Application (Client) ID <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control font-monospace" id="sso_client_id" name="sso_client_id"
                       value="<?= e($ssoClientId ?? '') ?>"
                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                <div class="form-text">Found on your Azure app's Overview page.</div>
            </div>

            <div class="mb-3">
                <label for="sso_client_secret" class="form-label fw-semibold">
                    Client Secret
                </label>
                <div class="input-group">
                    <input type="password" class="form-control font-monospace" id="sso_client_secret" name="sso_client_secret"
                           value="<?= e($ssoClientSecret ?? '') ?>"
                           placeholder="<?= ($ssoClientSecret ?? '') !== '' ? '••••••••••••••••' : 'Paste secret value here' ?>">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="var f=document.getElementById('sso_client_secret');f.type=f.type==='password'?'text':'password';">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="form-text">
                    The secret <em>value</em> (not the secret ID) from Certificates &amp; Secrets.
                    <?= ($ssoClientSecret ?? '') !== '' ? '<span class="text-success"><i class="bi bi-check-circle me-1"></i>A secret is currently saved. Leave blank to keep it unchanged.</span>' : '' ?>
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label fw-semibold">Redirect URI <span class="text-muted small fw-normal">(read-only — copy into Azure)</span></label>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace bg-light" readonly
                           id="redirectUri" value="<?= e($redirectUri) ?>">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="navigator.clipboard.writeText(document.getElementById('redirectUri').value);this.innerHTML='<i class=\'bi bi-check-lg\'></i>';">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <div class="form-text">Add this exact URL as a Redirect URI in your Azure app registration.</div>
            </div>
        </div>
    </div>

    <!-- Location Prompt Behaviour -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-geo-alt me-1"></i><?= e(label('location.singular', 'Location')) ?> Prompt
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                After a user logs in, they may be asked to choose their
                <?= e(label('location.singular', 'location')) ?> if one isn't assigned.
                Control when this prompt appears:
            </p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="sso_location_prompt"
                       id="lp_sso" value="sso_only"
                       <?= ($ssoLocationPrompt ?? 'sso_only') === 'sso_only' ? 'checked' : '' ?>>
                <label class="form-check-label" for="lp_sso">
                    <strong>SSO-created accounts only</strong>
                    <span class="text-muted small d-block">Only new accounts created via Microsoft SSO are prompted for a <?= e(label('location.singular', 'location')) ?>.</span>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="sso_location_prompt"
                       id="lp_all" value="all"
                       <?= ($ssoLocationPrompt ?? 'sso_only') === 'all' ? 'checked' : '' ?>>
                <label class="form-check-label" for="lp_all">
                    <strong>Any user with no <?= e(label('location.singular', 'location')) ?> assigned</strong>
                    <span class="text-muted small d-block">Any user — SSO or password login — who has no <?= e(label('location.singular', 'location')) ?> is prompted after sign-in.</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Debug Logging -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-bug me-1"></i>Debug Logging
        </div>
        <div class="card-body">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="sso_debug" name="sso_debug" value="1"
                       <?= ($ssoDebug ?? '0') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="sso_debug">
                    Enable SSO Debug Logging
                </label>
            </div>
            <div class="form-text">
                When enabled, detailed SSO login attempts are written to
                <code>storage/logs/sso-debug.log</code>.
                Disable once the issue is resolved — the log includes user email addresses.
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
            <i class="bi bi-check-lg me-1"></i>Save SSO Settings
        </button>
        <a href="/admin/settings" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php if (($ssoDebug ?? '0') === '1'): ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-file-text me-1"></i>SSO Debug Log</span>
        <form method="POST" action="/admin/settings/sso/clear-log" class="d-inline">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    onclick="return confirm('Clear the SSO debug log?')">
                <i class="bi bi-trash me-1"></i>Clear Log
            </button>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($ssoLog)): ?>
            <p class="text-muted small p-3 mb-0">No log entries yet. Attempt a Microsoft SSO login to generate entries.</p>
        <?php else: ?>
            <pre class="mb-0 p-3" style="font-size:.78rem;max-height:500px;overflow-y:auto;background:#f8f9fa;border-radius:0 0 .375rem .375rem;"><?= e($ssoLog) ?></pre>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
