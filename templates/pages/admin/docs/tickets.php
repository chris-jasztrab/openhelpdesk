<?php
$layout       = 'app';
$pageTitle    = 'Docs: Tickets';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Tickets']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Tickets</h2>
<p class="text-muted mb-4">Everything you need to know about creating, managing and resolving tickets.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Creating Tickets</h5>
<p class="text-muted mb-2">Tickets can be created in two ways:</p>
<ul class="text-muted mb-3">
    <li><strong>Portal:</strong> End users submit tickets through the public portal at <code>/portal</code>. They provide a subject, description, location (if enabled) and optionally attach files.</li>
    <li><strong>Admin / Agent:</strong> Staff can create tickets on behalf of users from <a href="/admin/tickets/create"><strong>Admin → Tickets → Create Ticket</strong></a>. You must select or create a requester, choose a subject, priority, and optionally assign it immediately.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    When a ticket is created, a confirmation email is sent to the requester (if SMTP is configured) and, if assigned, a notification is sent to the agent.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>Ticket Statuses</h5>
<p class="text-muted mb-2">Each ticket moves through a lifecycle managed by statuses:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Status</th><th>Meaning</th></tr></thead>
    <tbody>
        <tr><td><span class="badge bg-warning text-dark">Open</span></td><td class="text-muted">Newly submitted; awaiting agent action.</td></tr>
        <tr><td><span class="badge bg-primary">In Progress</span></td><td class="text-muted">An agent is actively working on the ticket.</td></tr>
        <tr><td><span class="badge bg-info text-dark">Pending</span></td><td class="text-muted">Waiting on a response from the requester or a third party.</td></tr>
        <tr><td><span class="badge bg-success">Resolved</span></td><td class="text-muted">The issue has been addressed. The requester is notified.</td></tr>
        <tr><td><span class="badge bg-secondary">Closed</span></td><td class="text-muted">Fully closed. No further action expected.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-flag text-primary me-2"></i>Priorities</h5>
<p class="text-muted mb-2">Priorities determine urgency and drive SLA timers. Default priorities are created during installation (Low, Medium, High, Critical). You can rename, recolour and adjust them at <a href="/admin/priorities"><strong>Admin → Priorities</strong></a>.</p>
<p class="text-muted mb-0">Each priority can be linked to an SLA policy so that response and resolution deadlines are automatically applied when a ticket is created.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-dots text-primary me-2"></i>Replies &amp; Internal Notes</h5>
<p class="text-muted mb-2">Within a ticket you can add two types of entries:</p>
<ul class="text-muted mb-3">
    <li><strong>Reply:</strong> Visible to the requester. Sends an email notification to the requester (if SMTP configured). Use this for all customer-facing communication.</li>
    <li><strong>Internal Note:</strong> Visible only to agents and admins. Never sent to the requester. Use this for internal coordination, escalation notes, or context that should stay private.</li>
</ul>
<p class="text-muted mb-0">Both types support file attachments. Attachments are stored in <code>storage/attachments/</code> and are served securely through the application.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-check text-primary me-2"></i>Assigning Tickets</h5>
<p class="text-muted mb-2">Tickets can be assigned to any agent or admin. Assignment can happen:</p>
<ul class="text-muted mb-0">
    <li>At creation time (by an admin or agent creating the ticket).</li>
    <li>From the ticket view — use the <strong>Assigned To</strong> dropdown in the details panel on the right.</li>
    <li>Via automations (see the <a href="/admin/docs/automations">Automations</a> doc).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-tags text-primary me-2"></i>Tags</h5>
<p class="text-muted mb-2">Tags are free-form labels you can attach to any ticket for categorisation or filtering. Type a tag name in the tag field on the ticket detail panel and press <kbd>Enter</kbd>. Tags are auto-suggested from previously used tags.</p>
<p class="text-muted mb-0">Tags can be used to filter the ticket list — use the search filters above the ticket table.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-intersect text-primary me-2"></i>Merging Tickets</h5>
<p class="text-muted mb-2">When a requester submits duplicate tickets about the same issue, you can merge them. The secondary ticket is closed and its history is linked to the primary ticket.</p>
<ol class="text-muted mb-0">
    <li>Open the ticket you want to keep as the <strong>primary</strong>.</li>
    <li>Click <strong>Merge</strong> in the ticket actions panel.</li>
    <li>Enter the ID of the duplicate ticket to merge in.</li>
    <li>Confirm the merge — the duplicate is marked closed and a merge event is recorded in the primary ticket's timeline.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Ticket Timeline</h5>
<p class="text-muted mb-2">Every ticket has a full audit trail in the timeline tab, showing all events in chronological order:</p>
<ul class="text-muted mb-0">
    <li><strong>Replies</strong> — customer-visible messages (white background)</li>
    <li><strong>Internal Notes</strong> — private agent notes (highlighted with your configured note colour)</li>
    <li><strong>System Events</strong> — status changes, priority changes, assignments, SLA updates, merges (highlighted with your configured system colour)</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-stopwatch text-primary me-2"></i>SLA on Tickets</h5>
<p class="text-muted mb-2">When a ticket matches an SLA policy (via priority), the SLA timer is initialised automatically. The ticket view shows:</p>
<ul class="text-muted mb-0">
    <li><strong>First Response Due</strong> — time by which an agent must add a public reply.</li>
    <li><strong>Resolution Due</strong> — time by which the ticket must be resolved.</li>
    <li>Timers turn amber when within 20% of the deadline and red when breached.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
