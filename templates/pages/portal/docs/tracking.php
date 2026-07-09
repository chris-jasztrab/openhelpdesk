<?php
$layout       = 'app';
$pageTitle    = 'Help: Tracking Your Requests';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Tracking Your Requests']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Tracking Your Requests</h2>
<p class="text-muted mb-4">Once you've submitted a request, everything about it lives in one place. Here's how to find it, read where things stand, and follow along as the team works on it.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ticket-detailed text-primary me-2"></i>The "My Requests" Page</h5>
<p class="text-muted mb-2">Click <strong>My Requests</strong> in the sidebar to see <strong>every request you've ever submitted</strong> &mdash; open ones and finished ones. Each row shows the request number, subject, status, priority, type, who's handling it, and when you submitted it. Click any column heading to sort, or click a row to open the request.</p>
<p class="text-muted small mb-0">Two badges you might spot next to a subject: <strong>Escalated</strong> means it's been flagged for extra attention, and <strong>&#9999; Draft</strong> means you have an unsent comment saved on that request.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Search &amp; Filter</h5>
<p class="text-muted mb-0">Type a keyword in <strong>Search</strong> to find a request by its subject. Narrow the list with the <strong>Status</strong> dropdown or by <strong>Priority</strong>, then click <strong>Filter</strong>. This is the quickest way to find one request among many.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-flag text-primary me-2"></i>What the Statuses Mean</h5>
<p class="text-muted mb-2">We use plain words, not jargon, so you always know where things stand:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:34%;">Status</th><th>What it means</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Submitted</strong></td><td>We've received it and it's in the queue.</td></tr>
        <tr><td><strong>We're working on it</strong></td><td>Someone has picked it up and is on the case.</td></tr>
        <tr><td><strong>Waiting on you</strong></td><td>We need something from you before we can continue &mdash; reply to unblock it.</td></tr>
        <tr><td><strong>Done</strong></td><td>It's been resolved or closed.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Your helpdesk may use slightly different wording, but the idea is the same. A coloured badge also shows the priority, and a red <strong>Escalated</strong> badge appears if a request has been escalated.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-building text-primary me-2"></i>Requests From Your Whole Branch</h5>
<p class="text-muted mb-0">Some accounts can also see requests submitted by anyone at their branch. If you have this, a <strong>My Location</strong> option appears on the <em>My Requests</em> page. It's handy for checking whether a colleague already reported the problem you're about to submit, or for following an issue someone else opened &mdash; you can open those requests and even comment on them. <span class="text-muted small">(Requests in confidential categories never appear here.)</span></p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-card-list text-primary me-2"></i>Inside a Request</h5>
<p class="text-muted mb-2">Click a request to open it. Here's what you'll see:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Section</th><th>What it shows</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Status &amp; priority badges</strong></td><td>Where things stand, right under the title, in plain words.</td></tr>
        <tr><td><strong>What happens next</strong></td><td>Brand-new requests show a short box confirming it's in the queue and that you'll get an email when there's an update.</td></tr>
        <tr><td><strong>Answer banner</strong></td><td>When the team marks one of their replies as <em>the answer</em>, a green banner appears at the top. Click it to jump straight to that reply.</td></tr>
        <tr><td><strong>Your description</strong></td><td>What you wrote when you submitted, screenshots and all.</td></tr>
        <tr><td><strong>Attachments</strong></td><td>Every file on the request &mdash; yours and the team's &mdash; collected in one spot.</td></tr>
        <tr><td><strong>Timeline</strong></td><td>The full history: replies, status changes, and assignments, in order. Click the <strong>Timeline</strong> heading to flip between newest-first and oldest-first.</td></tr>
        <tr><td><strong>Details panel</strong></td><td>A quick summary: status, priority, type, branch, and <strong>Assigned To</strong>. "Unassigned" just means it's still in the queue.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="alert alert-success border-0 d-flex gap-3 mb-0" style="background:#f0fdf4;">
    <i class="bi bi-arrow-right-circle fs-4 text-success flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Need to reply, edit, or close a request?</strong> See <a href="/portal/help/managing">Replying, Editing &amp; Closing</a>.
    </div>
</div>

</div><!-- col -->
</div><!-- row -->
