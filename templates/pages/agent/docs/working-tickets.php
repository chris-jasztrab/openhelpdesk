<?php
$layout       = 'app';
$pageTitle    = 'Help: Working on Tickets';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Working on Tickets']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Working on Tickets</h2>
<p class="text-muted mb-4">Everything you can do once you have a ticket open.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-layout-three-columns text-primary me-2"></i>Ticket Layout</h5>
<p class="text-muted mb-2">The ticket detail view is divided into three columns:</p>
<ul class="text-muted mb-0">
    <li><strong>Left — Conversation</strong>: The ticket description, timeline of replies/notes/events, and the reply composer.</li>
    <li><strong>Middle — Details</strong>: Status, priority, type, assigned agent, group, requester, location, created date, due date, and any custom fields.</li>
    <li><strong>Right — Actions</strong>: CC management, the Update Ticket form, and SLA information.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-dots text-primary me-2"></i>Replies, Notes &amp; Forwarding</h5>
<p class="text-muted mb-2">Three action buttons below the timeline open the reply composer:</p>
<ul class="text-muted mb-3">
    <li><strong>Reply</strong> — sends a public message to the requester. The message appears in their portal view and triggers an email notification (if SMTP is configured).</li>
    <li><strong>Add Note</strong> — adds a private internal note visible only to agents and admins. Notes are never sent to the requester or shown in the portal.</li>
    <li><strong>Forward</strong> — sends a copy of the ticket details to an external email address.</li>
</ul>
<p class="text-muted mb-0">The reply composer uses a rich text editor (CKEditor 5). You can apply bold, italic, bullet lists, numbered lists, and hyperlinks. Attachments can be uploaded directly in the composer.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send-check text-primary me-2"></i>Send &amp; Set Status</h5>
<p class="text-muted mb-2">The <strong>Send</strong> button has a dropdown arrow on its right side. Click the arrow to choose a status to apply at the same time as sending. The dropdown lists every active status other than the ticket's current one, so the menu always reflects the statuses your admins have configured.</p>
<p class="text-muted mb-3">On a fresh install the choices are <strong>Send &amp; Resolve</strong>, <strong>Send &amp; Close</strong>, <strong>Send &amp; Set as Pending</strong>, <strong>Send &amp; Set as Waiting on Customer</strong>, and <strong>Send &amp; Set as Waiting on Third Party</strong> &mdash; but admins can add, rename, or hide any of these from <em>Settings &rarr; Ticket Statuses</em>.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Using <strong>Send &amp; Set Status</strong> is the fastest way to close out a ticket — you reply and change the status without a second click.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-square-text text-primary me-2"></i>Canned Responses</h5>
<p class="text-muted mb-2">While the reply composer is open, click <strong>Canned Response</strong> to pick a saved reply template. The template content is inserted at the cursor position and tokens (e.g. <code>{{customer_first_name}}</code>) are replaced with the actual values for this ticket.</p>
<p class="text-muted mb-0">You can create and manage your own canned responses under <a href="/agent/canned-responses"><strong>Canned Responses</strong></a> in the sidebar.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Updating Ticket Fields</h5>
<p class="text-muted mb-2">Use the <strong>Update Ticket</strong> panel in the right column to change:</p>
<ul class="text-muted mb-3">
    <li><strong>Status</strong> — pick any active status. The list reflects whatever your admins have configured in <em>Settings &rarr; Ticket Statuses</em>; out of the box it offers Open, In Progress, Pending, Waiting on Customer, Waiting on Third Party, Resolved, and Closed.</li>
    <li><strong>Priority</strong> — changes the urgency and may change the SLA deadline.</li>
    <li><strong>Assigned To</strong> — assign to yourself or another agent. If the ticket has a group set, only that group's members are shown.</li>
    <li><strong>Group</strong> — move the ticket to a different team group.</li>
</ul>
<p class="text-muted mb-0">Click <strong>Update</strong> to save changes. A system event is recorded in the timeline for each change.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>CC (Carbon Copy)</h5>
<p class="text-muted mb-2">The <strong>CC</strong> panel in the right column lets you add other users as watchers who receive email notifications for every public reply on this ticket.</p>
<ul class="text-muted mb-0">
    <li>Type a name or email in the search box to find a user and add them.</li>
    <li>Added CC recipients appear as chips — click the <strong>&times;</strong> to remove them.</li>
    <li>CC recipients receive notification emails but cannot reply via the portal unless they are registered users.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye text-primary me-2"></i>Watching Tickets</h5>
<p class="text-muted mb-2">Click <strong>Watch</strong> on a ticket to subscribe to it. You will receive email notifications for any activity on that ticket even if it is not assigned to you.</p>
<p class="text-muted mb-0">Watched tickets appear in the <strong>Watched</strong> filter in the ticket list. Click <strong>Unwatch</strong> on the ticket to stop receiving notifications.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Ticket Timeline</h5>
<p class="text-muted mb-2">The timeline shows all activity on the ticket in chronological order:</p>
<ul class="text-muted mb-3">
    <li><strong>Replies</strong> — public messages visible to the requester (white background).</li>
    <li><strong>Internal Notes</strong> — private agent notes, highlighted in your configured note colour. Never shown to portal users.</li>
    <li><strong>System Events</strong> — status changes, assignments, priority changes, SLA events, merges, highlighted in your configured system colour. Not visible to portal users.</li>
</ul>
<p class="text-muted mb-2">File attachments are shown inline within the entry they were uploaded with. The timeline loads the 10 most recent entries first — click <strong>Load older entries</strong> to see the full history.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Admins see two switches in the Timeline header — <strong>System notes</strong> and <strong>AI notes</strong> — to hide automated entries (SLA events, AI classifications, and similar) and keep the focus on the conversation. The choice sticks to the admin's account across tickets. The notes are only hidden from view, never deleted.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check-circle text-success me-2"></i>Marking a Comment as the Solution</h5>
<p class="text-muted mb-2">When a ticket's answer is buried partway down a long timeline, you can flag the comment that resolved it:</p>
<ul class="text-muted mb-2">
    <li>Every customer-visible reply shows a small <strong>Mark as solution</strong> button next to its timestamp. Click it to flag that comment.</li>
    <li>The ticket then shows a green alert with a <strong>Go to solution</strong> button at the top — for you, for admins, and for the requester on their portal view — that jumps to the marked comment.</li>
    <li>There is one solution per ticket; marking another comment replaces it, and <strong>Unmark</strong> clears it.</li>
</ul>
<p class="text-muted mb-0">Internal notes can't be marked (the requester can't see them). Marking a solution does not change the ticket's status — resolve it separately when you're ready.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-stopwatch text-primary me-2"></i>SLA Information</h5>
<p class="text-muted mb-2">If the ticket has an SLA policy applied (determined by priority), the SLA card in the right column shows:</p>
<ul class="text-muted mb-0">
    <li><strong>First Response Due</strong> — deadline for the first public reply. Met as soon as an agent posts a public reply.</li>
    <li><strong>Resolution Due</strong> — deadline by which the ticket must be resolved.</li>
    <li>Timers turn <strong class="text-warning">amber</strong> when within 20% of the deadline and <strong class="text-danger">red</strong> when the deadline has passed (breached).</li>
    <li>The SLA timer automatically <strong>pauses</strong> while the ticket is on any status flagged "Pauses SLA" in <em>Settings &rarr; Ticket Statuses</em> (out of the box: <em>Pending</em>, <em>Waiting on Customer</em>, <em>Waiting on Third Party</em>) and resumes the moment it moves to a status that does not pause SLA.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-intersect text-primary me-2"></i>Merging Tickets</h5>
<p class="text-muted mb-2">Use <strong>Merge</strong> when a requester has submitted duplicate tickets about the same issue.</p>
<ol class="text-muted mb-3">
    <li>Open the ticket you want to keep as the <strong>primary</strong>.</li>
    <li>Click <strong>Merge</strong> in the ticket actions.</li>
    <li>Enter the ID of the duplicate ticket to merge in.</li>
    <li>Confirm — the duplicate is closed and its history is linked to the primary. The requester is notified by email.</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    The primary ticket's priority is automatically escalated to the highest priority among all merged tickets.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-scissors text-primary me-2"></i>Splitting Tickets</h5>
<p class="text-muted mb-2">Use <strong>Split</strong> when a single ticket contains multiple distinct issues that should be tracked separately.</p>
<ol class="text-muted mb-3">
    <li>Click <strong>Split</strong> on the ticket.</li>
    <li>Select one or more timeline comments to move to the new ticket.</li>
    <li>Fill in the new ticket's subject, description, type, priority, and optionally assign it.</li>
    <li>Click <strong>Create Split Ticket</strong> — the selected comments are moved to the new ticket and a link is added to both tickets' timelines.</li>
</ol>
<p class="text-muted mb-0">The new ticket inherits the original ticket's location. It is created with Open status.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Confidential Tickets</h5>
<p class="text-muted mb-2">Some ticket types may be marked as <strong>Confidential</strong> by an administrator. When a ticket type is confidential:</p>
<ul class="text-muted mb-3">
    <li>Only members of the ticket type's assigned group can view and work on those tickets.</li>
    <li>If you are not a member of the group, confidential tickets will not appear in your ticket list or search results.</li>
    <li>Admins who are not in the group can see confidential tickets listed but must re-authenticate to view the details. Their access is logged and all group members are notified via email.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    If you need access to a confidential ticket type, ask your administrator to add you to the associated group.
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
