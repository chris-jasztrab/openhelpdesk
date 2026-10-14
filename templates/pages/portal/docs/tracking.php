<?php
$layout       = 'app';
$pageTitle    = 'Help: Tracking Your Requests';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Tracking Your Requests']];
$myRequests   = label('portal.nav.my_tickets', 'My Requests');
$locLabel     = label('location.singular', 'Location');
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Tracking Your Requests</h2>
<p class="text-muted mb-4">Once you've submitted a request, everything about it lives in one place. Here's how to find it, read where things stand, and follow along as the team works on it.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ticket-detailed text-primary me-2"></i>The "<?= e($myRequests) ?>" Page</h5>
<p class="text-muted mb-2">Click <strong><?= e($myRequests) ?></strong> in the sidebar to see your requests. Each row shows the request number, subject, status, priority, type, who it's assigned to, and when you submitted it. Click any column heading to sort, or click a row to open the request. Long lists are split into pages, with page numbers at the bottom.</p>
<div class="alert alert-warning border-0 small d-flex gap-2 mb-2" style="background:#fffbeb;">
    <i class="bi bi-funnel flex-shrink-0"></i>
    <div>When the page first opens, it shows only requests that are <strong><?= e(label('portal.status.open', 'Submitted')) ?></strong>. To see everything &mdash; including requests the team is working on and finished ones &mdash; set <strong>Status</strong> to <strong>All Statuses</strong> and click <strong>Filter</strong>.</div>
</div>
<p class="text-muted small mb-0">Two badges you might spot next to a subject: <strong>Escalated L1</strong> (or L2, L3&hellip;) means it's been flagged for extra attention, and <strong>&#9999; Draft</strong> means you have an unsent comment saved on that request.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Search &amp; Filter</h5>
<p class="text-muted mb-2">Type a keyword in <strong>Search</strong> to find a request by its subject. Narrow the list with the <strong>Status</strong> and <strong>Priority</strong> dropdowns, or tick <strong>Has attachment</strong> to show only requests with files on them. Then click <strong>Filter</strong>.</p>
<p class="text-muted small mb-0">Click the <strong>&times;</strong> button beside <strong>Filter</strong> to clear everything and go back to the starting view. The badge at the top shows how many requests match.</p>
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
        <tr><td><strong><?= e(label('portal.status.open', 'Submitted')) ?></strong></td><td>We've received it and it's in the queue.</td></tr>
        <tr><td><strong><?= e(label('portal.status.in_progress', "We're working on it")) ?></strong></td><td>Someone has picked it up and is on the case.</td></tr>
        <tr><td><strong><?= e(label('portal.status.pending', "We're waiting on someone else")) ?></strong></td><td>We're waiting on another team, a supplier, or a part before we can continue.</td></tr>
        <tr><td><strong><?= e(label('portal.status.waiting_on_customer', 'Waiting on you')) ?></strong></td><td>We need something from you before we can continue &mdash; reply to unblock it.</td></tr>
        <tr><td><strong><?= e(label('portal.status.resolved', 'Done')) ?></strong></td><td>We think it's sorted. If it isn't, add a comment or reply to the email.</td></tr>
        <tr><td><strong><?= e(label('portal.status.closed', 'Closed')) ?></strong></td><td>It's finished. If you need more help, submit a new request.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Your helpdesk may use a few statuses of its own, but the idea is the same. A coloured badge also shows the priority.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-building text-primary me-2"></i>Requests From Your Whole Branch</h5>
<p class="text-muted mb-2">Some accounts can also see requests submitted by anyone at their branch. If you have this, two buttons appear on the <em><?= e($myRequests) ?></em> page: <strong><?= e($myRequests) ?></strong> and <strong>My <?= e($locLabel) ?></strong>. It's handy for checking whether a colleague already reported the problem you're about to submit, or for following an issue someone else opened.</p>
<p class="text-muted mb-2">You can open those requests, download their files, and comment on them. Only the person who submitted a request can edit, close, or escalate it. Before you comment on someone else's request, a <strong>This isn't your ticket</strong> window reminds you that comments from many people get confusing &mdash; if you have your own issue, submit a new request instead. Click <strong>I want to add a comment anyway</strong> to carry on.</p>
<p class="text-muted small mb-0">Requests in confidential or private categories never appear here.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrows-collapse text-primary me-2"></i>Merged and CC'd Requests</h5>
<p class="text-muted mb-2"><strong>Merged:</strong> if your request is about the same problem as another one, the team may combine them. You'll usually get a <em>Your ticket has been merged</em> email. The conversation moves to the combined request, which shows up in your <em><?= e($myRequests) ?></em> list, and opening your old request takes you straight there. Keep the conversation going on the combined request.</p>
<p class="text-muted mb-0"><strong>CC'd:</strong> requests that someone else submitted with you on CC aren't listed on your <em><?= e($myRequests) ?></em> page. You follow them by email &mdash; and you can reply to those emails to add a comment.</p>
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
        <tr><td><strong>Status &amp; priority badges</strong></td><td>Where things stand, right under the title, with the request number. A red <strong>Escalated &mdash; Level</strong> badge shows if it's been escalated.</td></tr>
        <tr><td><strong><?= e(label('portal.what_next.title', 'What happens next?')) ?></strong></td><td>While your request is <?= e(label('portal.status.open', 'Submitted')) ?>, a short box confirms it's in the queue and that you'll get an email when there's an update.</td></tr>
        <tr><td><strong>Answer banner</strong></td><td>When the team marks one of their replies as <em>the answer</em>, a green <strong><?= e(label('portal.solution.available', 'Answer available')) ?></strong> banner appears at the top. Click <strong><?= e(label('portal.solution.go', 'Go to answer')) ?></strong> to jump to that reply.</td></tr>
        <tr><td><strong>Description</strong></td><td>What you wrote when you submitted, screenshots and all.</td></tr>
        <tr><td><strong>Additional Details</strong></td><td>Your answers to the request type's extra questions.</td></tr>
        <tr><td><strong>Attachments</strong></td><td>Every file on the request &mdash; yours and the team's &mdash; in one spot. Pictures may show a small preview; click any file to download it.</td></tr>
        <tr><td><strong>Timeline</strong></td><td>The full history: replies, status changes, and assignments. Ten entries show at first; the rest are tucked behind a <strong>Show &hellip; updates</strong> link at the bottom. Click the <strong>Timeline</strong> heading to flip between newest-first and oldest-first.</td></tr>
        <tr><td><strong>Add Comment</strong></td><td>Where you reply to the team. See <a href="/portal/help/managing">Replying, Editing &amp; Closing</a>.</td></tr>
        <tr><td><strong>Details panel</strong></td><td>A quick summary: status, priority, type, <?= e(strtolower($locLabel)) ?>, <strong>Assigned To</strong>, and when it was created. "Unassigned" just means it's still in the queue.</td></tr>
        <tr><td><strong>CC panel</strong></td><td>Who else is copied on the request, if anyone.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="alert alert-success border-0 d-flex gap-3 mb-0" style="background:#f0fdf4;">
    <i class="bi bi-arrow-right-circle fs-4 text-success flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Need to reply, edit, close, or reopen a request?</strong> See <a href="/portal/help/managing">Replying, Editing &amp; Closing</a>.
    </div>
</div>

</div><!-- col -->
</div><!-- row -->
