<?php
$layout       = 'app';
$pageTitle    = 'Docs: Single Sign-On';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Single Sign-On']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Single Sign-On (Microsoft 365)</h2>
<p class="text-muted mb-4">Let staff and requesters sign in with their Microsoft 365 / Entra ID work account instead of a separate helpdesk password.</p>

<div class="alert alert-info small mb-4"><i class="bi bi-info-circle me-2"></i>
    Configure it at <a href="/admin/settings/sso"><strong>Settings &rarr; Security &rarr; SSO / Microsoft 365</strong></a>. For the Azure side (app registration, client secret, API permissions) follow the step-by-step <a href="/admin/settings/sso/help"><strong>SSO Setup Guide</strong></a>.
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-microsoft text-primary me-2"></i>What SSO Does</h5>
<p class="text-muted mb-2">When SSO is enabled, the login page shows a <strong>Sign in with Microsoft 365</strong> button below the normal email/password form. The user is sent to Microsoft to sign in, and comes back to the helpdesk already logged in.</p>
<ul class="text-muted mb-0">
    <li><strong>Password login stays available</strong> alongside SSO, so accounts without a Microsoft identity (and your break-glass admin) can still sign in.</li>
    <li>The helpdesk asks Microsoft only for the user's basic profile (<code>openid</code>, <code>email</code>, <code>profile</code>, <code>User.Read</code>): name, email address and account ID.</li>
    <li>SSO uses the web address in <code>APP_URL</code> (server <code>.env</code>) to build its redirect URI. Set it to the exact <code>https://</code> address users visit, and register that same redirect URI in Azure.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-check text-primary me-2"></i>How Accounts Are Matched</h5>
<p class="text-muted mb-2">Each Microsoft sign-in is matched to a helpdesk account in this order:</p>
<ol class="text-muted mb-3">
    <li><strong>Linked account</strong> &mdash; an account already linked to that Microsoft identity (by its permanent object ID, not the email address).</li>
    <li><strong>Same email</strong> &mdash; an existing account with the same email address. It is linked on that first sign-in, so later renames of the mailbox don't break the match.</li>
    <li><strong>New account</strong> &mdash; if nothing matches, a new account is created at the <strong>End User</strong> level with no helpdesk password.</li>
</ol>
<div class="alert alert-warning small mb-2"><i class="bi bi-exclamation-triangle me-2"></i>
    Auto-created accounts are always End Users (portal only). To give a new staff member agent access, create them at <a href="/admin/users/create" class="alert-link">Settings &rarr; Users &amp; Access &rarr; Users &rarr; Add User</a> first, or change their <strong>Permission Level</strong> after their first sign-in.
</div>
<div class="alert alert-info small mb-0"><i class="bi bi-shield-lock me-2"></i>
    <strong>Use your tenant ID, not <code>common</code>.</strong> With a specific Directory (Tenant) ID, only accounts from your tenant are accepted. If the tenant field is set to <code>common</code>, <code>organizations</code> or <code>consumers</code>, the helpdesk refuses email matching and auto-creation (anyone in any tenant could otherwise claim a matching account), so only already-linked accounts can sign in.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-geo-alt text-primary me-2"></i>Choosing a <?= e(label('location.singular', 'Location')) ?> After Sign-In</h5>
<p class="text-muted mb-2">Microsoft doesn't know which <?= e(strtolower(label('location.singular', 'location'))) ?> someone works at, so the helpdesk can ask. A user who has no <?= e(strtolower(label('location.singular', 'location'))) ?> is shown a <strong>Choose your <?= e(label('location.singular', 'Location')) ?></strong> page after signing in. The <strong><?= e(label('location.singular', 'Location')) ?> Prompt</strong> setting controls who sees it:</p>
<ul class="text-muted mb-2">
    <li><strong>SSO-created accounts only</strong> (default) &mdash; only brand-new accounts created by a Microsoft sign-in are asked.</li>
    <li><strong>Any user with no <?= e(strtolower(label('location.singular', 'location'))) ?> assigned</strong> &mdash; every non-admin who signs in (SSO or password) without a <?= e(strtolower(label('location.singular', 'location'))) ?> is asked.</li>
</ul>
<p class="text-muted mb-0">Users can pick <strong>Skip for now</strong>; in the "any user" mode they are asked again on a later sign-in until a <?= e(strtolower(label('location.singular', 'location'))) ?> is set. Admins can set it for them on the user's account.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Settings Reference</h5>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Setting</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Enable Microsoft 365 SSO</strong></td><td>Shows the Microsoft button on the login page. Requires the Tenant ID and Client ID to be filled in.</td></tr>
        <tr><td><strong>Directory (Tenant) ID</strong></td><td>From the Azure app's Overview page. Use your tenant's GUID (see above).</td></tr>
        <tr><td><strong>Application (Client) ID</strong></td><td>From the Azure app's Overview page.</td></tr>
        <tr><td><strong>Client Secret</strong></td><td>The secret <em>value</em> from Certificates &amp; secrets. Never shown again after saving; leave the box blank to keep the saved secret.</td></tr>
        <tr><td><strong>Redirect URI</strong></td><td>Read-only. Copy it into the Azure app registration exactly as shown.</td></tr>
        <tr><td><strong><?= e(label('location.singular', 'Location')) ?> Prompt</strong></td><td>Who is asked to choose a <?= e(strtolower(label('location.singular', 'location'))) ?> after signing in (see above).</td></tr>
        <tr><td><strong>Enable SSO Debug Logging</strong></td><td>Writes each sign-in attempt to <code>storage/logs/sso-debug.log</code> and shows the log (with a <strong>Clear Log</strong> button) at the bottom of the settings page. The log contains email addresses, so turn it off once the problem is solved.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mt-3 mb-0">Saving records an <code>sso.settings_changed</code> entry in the <a href="/admin/docs/users#audit-log">audit log</a>; a new client secret appears only as <em>(rotated)</em>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Good to Know</h5>
<ul class="text-muted mb-0">
    <li><strong>Two-factor authentication.</strong> A Microsoft sign-in does not ask for the helpdesk's own 2FA code. Enforce multi-factor authentication on the Microsoft side (Entra ID security defaults or Conditional Access).</li>
    <li><strong>No helpdesk password.</strong> Accounts created by SSO have no password, so <em>Forgot password?</em> does nothing for them. An admin can set a password on the account if password login is also needed.</li>
    <li><strong>Secret expiry.</strong> Azure client secrets expire. When yours does, sign-in fails at the callback step: create a new secret in Azure and paste it into <strong>Client Secret</strong>.</li>
    <li><strong>Removing access.</strong> Permission changes and deletions take effect on the user's next page load. A deleted user who signs in with Microsoft again gets a new End User account, so block them in Entra ID if they should not have portal access either.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Troubleshooting</h5>
<p class="text-muted mb-2">Sign-in failures show a message under the login form. Turn on debug logging for the detail behind it.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:38%;">Message</th><th>Likely cause</th></tr></thead>
    <tbody class="text-muted">
        <tr><td>SSO is not currently enabled.</td><td>The enable switch is off. Turn it on and save.</td></tr>
        <tr><td>Sign-in session expired. Please try again.</td><td>The round trip to Microsoft took too long or the browser lost its session cookie. Try again in one tab.</td></tr>
        <tr><td>Could not complete sign-in with Microsoft.</td><td>Wrong or expired client secret, or the redirect URI in Azure doesn't match <code>APP_URL</code>. The <a href="/admin/settings/sso/help">Setup Guide</a> lists the matching Azure error codes.</td></tr>
        <tr><td>Could not retrieve your account details from Microsoft.</td><td><code>User.Read</code> permission or admin consent is missing, or the account has no email address.</td></tr>
        <tr><td>Your organization is not authorized for single sign-on here.</td><td>The account belongs to a different tenant, or the tenant field is <code>common</code> and the account isn't linked yet.</td></tr>
        <tr><td>Sign-in was cancelled or access was denied.</td><td>The user cancelled, or consent was refused in Microsoft.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
