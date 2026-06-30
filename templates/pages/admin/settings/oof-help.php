<?php
$layout       = 'app';
$pageTitle    = 'Out of Office Setup Guide – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Out of Office', 'url' => '/admin/settings/oof'],
    ['label' => 'Setup Guide'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><i class="bi bi-person-x me-2"></i>Out of Office Coverage — Setup Guide</h2>
    <a href="/admin/settings/oof" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Settings
    </a>
</div>

<div class="row justify-content-center">
<div class="col-lg-9">

<!-- How it works -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-lightbulb me-1"></i>How it works
    </div>
    <div class="card-body">
        <p>Every 15 minutes the coverage job:</p>
        <ol class="mb-0">
            <li>Reads each group member's Outlook <strong>automatic-replies (out-of-office)</strong> state from Microsoft 365.</li>
            <li>For each active ticket whose responsible agent is out of office, it tries to <strong>reassign</strong> the ticket to an available member of the same group.</li>
            <li>When there is <strong>nobody to reassign to</strong> — a single-person group, or every member away — it <strong>auto-replies</strong> the requester once with the agent's out-of-office message, and leaves the ticket open.</li>
        </ol>
    </div>
</div>

<!-- Prerequisites -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-check2-square me-1"></i>Prerequisites
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>Microsoft Graph must be configured (Tenant ID, Client ID, Client Secret) under
                <a href="/admin/settings">Email / SMTP</a> — the <strong>same Azure app registration</strong>
                used for inbound email. No second app is needed.</li>
            <li>Agents set their out-of-office in Outlook as normal (<em>File → Automatic Replies</em>).
                Use the <strong>"Outside my organization"</strong> message — that's the text patrons receive.</li>
        </ul>
    </div>
</div>

<!-- Step 1 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 1</span>
        <span class="fw-semibold">Grant the MailboxSettings.Read permission</span>
    </div>
    <div class="card-body">
        <ol class="mb-0">
            <li>In the <a href="https://portal.azure.com" target="_blank" rel="noopener">Azure Portal</a>,
                open your existing app registration (the one used for inbound email).</li>
            <li>Go to <strong>API permissions → Add a permission → Microsoft Graph → Application permissions</strong>.</li>
            <li>Search for and add <code>MailboxSettings.Read</code>.</li>
            <li>Click <strong>Grant admin consent</strong> for your tenant. The status must show a green check.</li>
        </ol>
        <div class="alert alert-info small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            <code>MailboxSettings.Read</code> is read-only — it lets the app see automatic-reply settings.
            It does not grant access to message contents.
        </div>
    </div>
</div>

<!-- Step 2 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 2</span>
        <span class="fw-semibold">Schedule the cron job</span>
    </div>
    <div class="card-body">
        <p class="mb-2">Add the <strong>OOF Coverage</strong> job from the
            <a href="/admin/settings/cron-jobs">Cron Jobs</a> page (recommended: every 15 minutes).
            Until it runs, no status is collected and no action is taken.</p>
        <p class="text-muted small mb-0">
            The job is missed-tick safe — it acts on current state each run and never auto-replies the same
            ticket twice.
        </p>
    </div>
</div>

<!-- Step 3 -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <span class="badge text-white me-2" style="background:var(--ld-primary);">Step 3</span>
        <span class="fw-semibold">Choose behaviour and enable</span>
    </div>
    <div class="card-body">
        <p class="mb-0">On the <a href="/admin/settings/oof">Out of Office</a> settings page, pick how to handle
            away agents (reassign, reply, or both), choose the ticket scope, optionally customise the auto-reply
            message, then switch the feature on.</p>
    </div>
</div>

</div>
</div>
