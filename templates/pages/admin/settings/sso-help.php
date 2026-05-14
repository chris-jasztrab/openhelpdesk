<?php
$layout       = 'app';
$pageTitle    = 'SSO Setup Guide – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'SSO / Microsoft 365', 'url' => '/admin/settings/sso'],
    ['label' => 'Setup Guide'],
];
$redirectUri = rtrim(env('APP_URL', ''), '/') . '/auth/microsoft/callback';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2"></i>Microsoft 365 SSO — Setup Guide</h2>
    <a href="/admin/settings/sso" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to SSO Settings
    </a>
</div>

<div class="row justify-content-center">
<div class="col-lg-9">

<!-- Prerequisites -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-check2-square me-1"></i>Prerequisites
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>You must be a <strong>Global Administrator</strong> or <strong>Application Administrator</strong> in your Microsoft 365 tenant.</li>
            <li>Your OpenHelpDesk install must be accessible over HTTPS. Microsoft does not allow plain HTTP redirect URIs in production.</li>
            <li>The redirect URI for your install is: <br>
                <div class="input-group mt-2" style="max-width:560px;">
                    <input type="text" class="form-control form-control-sm font-monospace bg-light" readonly
                           id="prereqUri" value="<?= e($redirectUri) ?>">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="navigator.clipboard.writeText(document.getElementById('prereqUri').value);this.innerHTML='<i class=\'bi bi-check-lg\'></i> Copied';">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
            </li>
        </ul>
    </div>
</div>

<!-- Step 1 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 1</span>
        <span class="fw-semibold">Open the Azure Portal</span>
    </div>
    <div class="card-body">
        <ol class="mb-0">
            <li>Navigate to <a href="https://portal.azure.com" target="_blank" rel="noopener">portal.azure.com</a> and sign in with your admin account.</li>
            <li>In the top search bar, type <strong>Azure Active Directory</strong> and click it (it may also appear as <strong>Microsoft Entra ID</strong> in newer tenants).</li>
        </ol>
    </div>
</div>

<!-- Step 2 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 2</span>
        <span class="fw-semibold">Register a New Application</span>
    </div>
    <div class="card-body">
        <ol class="mb-0">
            <li>In the left sidebar, click <strong>App registrations</strong>.</li>
            <li>Click <strong>+ New registration</strong> at the top.</li>
            <li>Fill in the form:
                <ul class="mt-2">
                    <li><strong>Name:</strong> Enter a recognisable name, e.g. <em>OpenHelpDesk SSO</em>.</li>
                    <li><strong>Supported account types:</strong> Choose <em>"Accounts in this organizational directory only (Single tenant)"</em> — this restricts login to your organisation's accounts only.</li>
                    <li><strong>Redirect URI:</strong> Select <em>Web</em> from the platform dropdown and paste your redirect URI:
                        <div class="input-group mt-1" style="max-width:560px;">
                            <input type="text" class="form-control form-control-sm font-monospace bg-light" readonly
                                   id="step2Uri" value="<?= e($redirectUri) ?>">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    onclick="navigator.clipboard.writeText(document.getElementById('step2Uri').value);this.innerHTML='<i class=\'bi bi-check-lg\'></i> Copied';">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </li>
                </ul>
            </li>
            <li>Click <strong>Register</strong>.</li>
        </ol>
    </div>
</div>

<!-- Step 3 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 3</span>
        <span class="fw-semibold">Copy the Tenant ID and Client ID</span>
    </div>
    <div class="card-body">
        <ol class="mb-2">
            <li>After registering, you are taken to the app's <strong>Overview</strong> page.</li>
            <li>Copy the following values — you will paste them into OpenHelpDesk:
                <table class="table table-sm table-bordered mt-2 mb-0">
                    <thead class="table-light">
                        <tr><th>Azure Field</th><th>OpenHelpDesk Field</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Application (client) ID</strong></td><td>Application (Client) ID</td></tr>
                        <tr><td><strong>Directory (tenant) ID</strong></td><td>Directory (Tenant) ID</td></tr>
                    </tbody>
                </table>
            </li>
        </ol>
        <div class="alert alert-warning small py-2 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Keep this tab open — you will need the app page again in the next steps.
        </div>
    </div>
</div>

<!-- Step 4 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 4</span>
        <span class="fw-semibold">Create a Client Secret</span>
    </div>
    <div class="card-body">
        <ol class="mb-2">
            <li>In the left sidebar of your app, click <strong>Certificates &amp; secrets</strong>.</li>
            <li>Under the <strong>Client secrets</strong> tab, click <strong>+ New client secret</strong>.</li>
            <li>Enter a description (e.g. <em>OpenHelpDesk</em>) and choose an expiry period. Click <strong>Add</strong>.</li>
            <li>The secret is displayed <strong>once only</strong>. Copy the <strong>Value</strong> (not the Secret ID) immediately and paste it into the <em>Client Secret</em> field in OpenHelpDesk.</li>
        </ol>
        <div class="alert alert-danger small py-2 mb-0">
            <i class="bi bi-shield-exclamation me-1"></i>
            <strong>Important:</strong> The secret value is only visible at this moment. If you navigate away, you will need to create a new secret. Treat it like a password — do not share it.
        </div>
    </div>
</div>

<!-- Step 5 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 5</span>
        <span class="fw-semibold">Configure API Permissions</span>
    </div>
    <div class="card-body">
        <ol class="mb-2">
            <li>In the left sidebar, click <strong>API permissions</strong>.</li>
            <li>Click <strong>+ Add a permission</strong>.</li>
            <li>Select <strong>Microsoft Graph</strong> → <strong>Delegated permissions</strong>.</li>
            <li>Search for and tick the following permissions:
                <ul class="mt-1">
                    <li><code>openid</code></li>
                    <li><code>email</code></li>
                    <li><code>profile</code></li>
                    <li><code>User.Read</code></li>
                </ul>
            </li>
            <li>Click <strong>Add permissions</strong>.</li>
            <li>Click <strong>Grant admin consent for [your organisation]</strong> and confirm. The status column should show green checkmarks.</li>
        </ol>
        <div class="alert alert-info small py-2 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            <code>User.Read</code> allows OpenHelpDesk to read the signed-in user's name and email address from Microsoft Graph. No other data is accessed.
        </div>
    </div>
</div>

<!-- Step 6 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 6</span>
        <span class="fw-semibold">Enter Credentials in OpenHelpDesk and Enable SSO</span>
    </div>
    <div class="card-body">
        <ol class="mb-0">
            <li>Return to <a href="/admin/settings/sso">Admin → Settings → SSO / Microsoft 365</a>.</li>
            <li>Paste the <strong>Directory (Tenant) ID</strong>, <strong>Application (Client) ID</strong>, and <strong>Client Secret</strong> into the corresponding fields.</li>
            <li>Tick <strong>Enable Microsoft 365 SSO</strong>.</li>
            <li>Click <strong>Save SSO Settings</strong>.</li>
            <li>Open a private/incognito browser window and visit your login page. You should see a <em>Sign in with Microsoft 365</em> button.</li>
        </ol>
    </div>
</div>

<!-- Troubleshooting -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-wrench me-1"></i>Troubleshooting
    </div>
    <div class="card-body">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>Problem</th><th>Likely Cause &amp; Fix</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>AADSTS50011: Redirect URI mismatch</td>
                    <td>The Redirect URI in Azure does not exactly match the one shown in OpenHelpDesk. Check for trailing slashes, HTTP vs HTTPS, or wrong domain.</td>
                </tr>
                <tr>
                    <td>AADSTS700016: Application not found</td>
                    <td>The Client ID or Tenant ID is incorrect. Double-check the values from the Azure app Overview page.</td>
                </tr>
                <tr>
                    <td>AADSTS70011: Invalid scope</td>
                    <td>The required API permissions were not added or admin consent was not granted. Repeat Step 5.</td>
                </tr>
                <tr>
                    <td>Authentication error on callback</td>
                    <td>The Client Secret may have expired. Create a new secret in Azure and update it in OpenHelpDesk SSO settings.</td>
                </tr>
                <tr>
                    <td>"SSO is not enabled" on login page</td>
                    <td>The <em>Enable Microsoft 365 SSO</em> toggle is off. Enable it and save settings.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="text-center mb-4">
    <a href="/admin/settings/sso" class="btn text-white px-5" style="background:var(--ld-primary);">
        <i class="bi bi-arrow-left me-1"></i>Go to SSO Settings
    </a>
</div>

</div><!-- /col -->
</div><!-- /row -->
