<?php
$layout       = 'app';
$pageTitle    = 'Help: Status Banners';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Status Banners']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Status Banners</h2>
<p class="text-muted mb-4">When something is down or slow, pin a notice to the top of every page so people stop opening duplicate tickets about it.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-megaphone text-primary me-2"></i>Posting a Banner</h5>
<p class="text-muted mb-2">Any staff member can post a banner. Click <strong><i class="bi bi-sliders"></i> Settings</strong> in the left sidebar, then <strong>Operations &rarr; <a href="/agent/banners">Status Banners</a></strong>, and click <strong>Post Incident</strong>.</p>
<div class="table-responsive">
<table class="table table-sm mb-2">
    <thead class="table-light"><tr><th style="width:28%;">Field</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Title</strong> (optional)</td><td>Shown in bold above the message.</td></tr>
        <tr><td><strong>Severity</strong></td><td><em>Info</em> (FYI or planned maintenance), <em>Warning</em> (active issue, workarounds available) or <em>Critical</em> (major outage, no workaround). Sets the colour and icon.</td></tr>
        <tr><td><strong>Message</strong></td><td>Required. Formatting and links are allowed.</td></tr>
        <tr><td><strong>Branches</strong></td><td>Tick one or more branches to show the banner only to requesters at those branches, or leave it on <em>All branches</em>.</td></tr>
        <tr><td><strong>Show from</strong> (optional)</td><td>Schedule it for later. Blank shows it straight away.</td></tr>
        <tr><td><strong>Auto-hide at</strong> (optional)</td><td>Hide it automatically at this time. Blank keeps it up until someone clears it.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-0">Click <strong>Post Banner</strong>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-window-stack text-primary me-2"></i>Who Sees It</h5>
<ul class="text-muted mb-0">
    <li>Banners appear at the top of every page while they're live.</li>
    <li>Requesters see banners for all branches plus those for their own branch. Staff see every live banner.</li>
    <li>Staff also see who posted it and when, with <strong>Edit</strong> and <strong>Clear</strong> buttons right on the banner.</li>
    <li>Anyone can hide a banner for themselves with its &times;. If the banner is edited later, it shows again.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-check text-primary me-2"></i>Managing Banners</h5>
<p class="text-muted mb-2">The Status Banners page lists every banner with its status &mdash; <span class="badge bg-success">Live</span>, <span class="badge bg-info text-dark">Scheduled</span>, <span class="badge bg-secondary">Expired</span> or <span class="badge bg-secondary">Cleared</span> &mdash; and these actions:</p>
<ul class="text-muted mb-0">
    <li><strong>Edit</strong> &mdash; change anything. Untick <em>Active</em> to clear the incident without deleting it.</li>
    <li><strong>Clear</strong> &mdash; take a live banner down when the problem is fixed.</li>
    <li><strong>Re-post</strong> &mdash; put a cleared banner back up.</li>
    <li><strong>Delete</strong> &mdash; remove it permanently.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
