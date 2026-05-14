<?php
$layout       = 'app';
$pageTitle    = 'Email Reply Setup Guide';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Email Reply Setup Guide'],
];
?>
<div class="mb-4 d-flex align-items-center justify-content-between">
    <h2 class="fw-bold mb-0">Email Reply Setup Guide</h2>
    <a href="/admin/settings#inbound-mail" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Settings
    </a>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle me-2"></i>
    This guide walks you through registering an application in <strong>Microsoft Entra ID (Azure AD)</strong>
    so OpenHelpDesk can read your Microsoft 365 mailbox without requiring an App Password or interactive login.
    This uses the <strong>OAuth 2.0 Client Credentials</strong> flow — app-only access with no user sign-in.
</div>

<!-- Step 1 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <span class="badge rounded-pill text-white me-2" style="background:var(--ld-primary);">1</span>
            Sign in to the Azure Portal
        </h5>
    </div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li class="mb-2">Go to <strong>portal.azure.com</strong> and sign in with a <strong>Global Administrator</strong> or <strong>Application Administrator</strong> account.</li>
            <li>In the top search bar, search for <strong>Microsoft Entra ID</strong> and open it.<br>
                <span class="text-muted small">(Previously called "Azure Active Directory")</span>
            </li>
        </ol>
    </div>
</div>

<!-- Step 2 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <span class="badge rounded-pill text-white me-2" style="background:var(--ld-primary);">2</span>
            Create a New App Registration
        </h5>
    </div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li class="mb-2">In the left sidebar, click <strong>App registrations</strong>.</li>
            <li class="mb-2">Click <strong>+ New registration</strong>.</li>
            <li class="mb-2">Fill in the form:
                <ul class="mt-2">
                    <li><strong>Name:</strong> <code>OpenHelpDesk Mail Reader</code> (or any name you prefer)</li>
                    <li><strong>Supported account types:</strong> <em>Accounts in this organizational directory only (Single tenant)</em></li>
                    <li><strong>Redirect URI:</strong> Leave blank</li>
                </ul>
            </li>
            <li class="mb-2">Click <strong>Register</strong>.</li>
            <li>
                On the Overview page that appears, copy these two values — you will need them in OpenHelpDesk:
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div class="bg-light border rounded p-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-clipboard me-1"></i>Application (client) ID</div>
                            <div class="text-muted small">Listed as "Application (client) ID" on the Overview page.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light border rounded p-3">
                            <div class="fw-semibold mb-1"><i class="bi bi-clipboard me-1"></i>Directory (tenant) ID</div>
                            <div class="text-muted small">Listed as "Directory (tenant) ID" on the Overview page.</div>
                        </div>
                    </div>
                </div>
            </li>
        </ol>
    </div>
</div>

<!-- Step 3 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <span class="badge rounded-pill text-white me-2" style="background:var(--ld-primary);">3</span>
            Add API Permissions
        </h5>
    </div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li class="mb-2">In the left sidebar of your App Registration, click <strong>API permissions</strong>.</li>
            <li class="mb-2">Click <strong>+ Add a permission</strong>.</li>
            <li class="mb-2">Select <strong>Microsoft Graph</strong>.</li>
            <li class="mb-2">Select <strong>Application permissions</strong> (not Delegated — this is important for a cron job with no logged-in user).</li>
            <li class="mb-2">
                Search for and add each of the following permissions:
                <table class="table table-sm table-bordered mt-2 mb-0" style="max-width:500px;">
                    <thead class="table-light">
                        <tr><th>Permission</th><th>Purpose</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><code>Mail.Read</code></td><td>Read messages in the mailbox</td></tr>
                        <tr><td><code>Mail.ReadWrite</code></td><td>Mark messages as read after processing</td></tr>
                    </tbody>
                </table>
            </li>
            <li class="mb-2">Click <strong>Add permissions</strong>.</li>
            <li>
                <div class="d-flex align-items-start gap-2">
                    <div>
                        <strong class="text-danger">Important:</strong> Click the <strong>Grant admin consent for [your organization]</strong> button.
                        This is required for application permissions and can only be done by a Global Administrator.
                        The status column should show a green checkmark — <span class="text-success fw-semibold">Granted for [org]</span>.
                    </div>
                </div>
            </li>
        </ol>
    </div>
</div>

<!-- Step 4 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <span class="badge rounded-pill text-white me-2" style="background:var(--ld-primary);">4</span>
            Create a Client Secret
        </h5>
    </div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li class="mb-2">In the left sidebar, click <strong>Certificates &amp; secrets</strong>.</li>
            <li class="mb-2">Under the <strong>Client secrets</strong> tab, click <strong>+ New client secret</strong>.</li>
            <li class="mb-2">Enter a description (e.g. <code>OpenHelpDesk</code>) and choose an expiry duration.</li>
            <li class="mb-2">Click <strong>Add</strong>.</li>
            <li>
                <strong class="text-danger">Copy the secret value immediately</strong> — it is only shown once.
                Paste it into the <strong>Client Secret</strong> field in OpenHelpDesk Settings.
                <div class="alert alert-warning mt-2 mb-0 py-2" style="font-size:.875rem;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Copy the <strong>Value</strong> column, not the "Secret ID". If you navigate away before copying, you will need to create a new secret.
                </div>
            </li>
        </ol>
    </div>
</div>

<!-- Step 5 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <span class="badge rounded-pill text-white me-2" style="background:var(--ld-primary);">5</span>
            Enter Credentials in OpenHelpDesk
        </h5>
    </div>
    <div class="card-body p-4">
        <p>Go to <a href="/admin/settings">Admin → Settings</a> and scroll to <strong>Inbound Mail — Reply Processing</strong>. Fill in:</p>
        <table class="table table-sm table-bordered mb-0" style="max-width:600px;">
            <thead class="table-light">
                <tr><th>Field</th><th>Where to find it</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Reply-To Address</strong></td>
                    <td>The email address you want users to reply to (same as the mailbox below)</td>
                </tr>
                <tr>
                    <td><strong>Mailbox Address</strong></td>
                    <td>The Microsoft 365 email address of the shared/dedicated mailbox (e.g. <code>tickets@yourorg.com</code>)</td>
                </tr>
                <tr>
                    <td><strong>Tenant ID</strong></td>
                    <td>Directory (tenant) ID from App Registration → Overview</td>
                </tr>
                <tr>
                    <td><strong>Client ID</strong></td>
                    <td>Application (client) ID from App Registration → Overview</td>
                </tr>
                <tr>
                    <td><strong>Client Secret</strong></td>
                    <td>The secret value you copied in Step 4</td>
                </tr>
            </tbody>
        </table>
        <p class="mt-3 mb-0">Enable the <strong>Enable reply-by-email</strong> toggle and click <strong>Save Inbound Settings</strong>.</p>
    </div>
</div>

<!-- Step 6 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <span class="badge rounded-pill text-white me-2" style="background:var(--ld-primary);">6</span>
            Set Up the Cron Job
        </h5>
    </div>
    <div class="card-body p-4">
        <p>Add the following entry to your server's crontab (<code>crontab -e</code>) to poll for new replies every 5 minutes:</p>
        <code class="d-block bg-light border rounded p-3 user-select-all" style="word-break:break-all;">* /5 * * * * php <?= e(ROOT_DIR) ?>/scripts/process-replies.php &gt;&gt; <?= e(ROOT_DIR) ?>/storage/logs/graph-mail.log 2&gt;&amp;1</code>
        <p class="text-muted small mt-2 mb-0">
            <i class="bi bi-info-circle me-1"></i>Remove the space between <code>*</code> and <code>/5</code> when pasting into crontab.
            The space is shown here to avoid a display issue.
        </p>
        <p class="mt-3 mb-0">The log file will be created automatically at <code><?= e(ROOT_DIR) ?>/storage/logs/graph-mail.log</code>.</p>
    </div>
</div>

<!-- Troubleshooting -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-tools me-2"></i>Troubleshooting</h5>
    </div>
    <div class="card-body p-4">
        <dl class="mb-0">
            <dt class="mb-1">Failed to obtain access token</dt>
            <dd class="mb-3">Double-check the Tenant ID, Client ID, and Client Secret. Make sure the secret hasn't expired (check Certificates &amp; secrets in Azure).</dd>

            <dt class="mb-1">Graph API returns HTTP 403</dt>
            <dd class="mb-3">Admin consent was not granted. Go back to API permissions and click <strong>Grant admin consent</strong>. The status must show a green checkmark.</dd>

            <dt class="mb-1">Graph API returns HTTP 404 for messages</dt>
            <dd class="mb-3">The mailbox address doesn't match a valid Microsoft 365 user or shared mailbox. Verify the address in your Microsoft 365 Admin Center.</dd>

            <dt class="mb-1">Sender not a registered user</dt>
            <dd class="mb-3">Only users with accounts in OpenHelpDesk can post replies via email. The email address in OpenHelpDesk must match the From address of the reply exactly.</dd>

            <dt class="mb-1">Reply body is empty after stripping quoted text</dt>
            <dd class="mb-0">The reply processor strips the quoted original email from the reply body. Make sure the user is replying <em>above</em> the quoted text, not below or replacing it.</dd>
        </dl>
    </div>
</div>

<div class="text-end">
    <a href="/admin/settings" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-arrow-left me-1"></i>Back to Settings
    </a>
</div>
