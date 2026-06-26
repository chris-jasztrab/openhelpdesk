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
    <li><strong>Forward</strong> — sends the full ticket out to one or more other people by email (see <a href="#forwarding">Forwarding a Ticket</a> below).</li>
</ul>
<p class="text-muted mb-0">The reply composer uses a rich text editor (CKEditor 5). You can apply bold, italic, bullet lists, numbered lists, and hyperlinks. Attachments can be uploaded directly in the composer.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="forwarding">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-forward text-primary me-2"></i>Forwarding a Ticket</h5>
<p class="text-muted mb-2"><strong>Forward</strong> sends the ticket's details, the full public conversation, and <strong>all</strong> of its attachments out to one or more email addresses — for example to loop in a vendor or a colleague in another department. You can add an optional note on top and attach extra files in the forward box.</p>
<ul class="text-muted mb-3">
    <li><strong>Replies thread back in.</strong> Each recipient is added to the ticket's CC list, so when they reply by email their message lands straight on the ticket timeline. Addresses that aren't already users are auto-provisioned as <strong>external contacts</strong> so their replies can be attributed.</li>
    <li><strong>External replies are flagged.</strong> When a forwarded outside contact replies, their timeline entry carries an <span class="badge bg-secondary">External</span> badge, so you can tell at a glance it came from a third party rather than the requester or a colleague.</li>
</ul>
<p class="text-muted mb-2">Forwarding is split into <strong>two permissions</strong>, and which one applies depends on the recipient:</p>
<ul class="text-muted mb-3">
    <li><strong>Forward to internal contacts</strong> — addresses that already belong to a real in-system user.</li>
    <li><strong>Forward to external contacts</strong> — a brand-new address, or a previously auto-provisioned external contact.</li>
</ul>
<p class="text-muted mb-2">You'll see the <strong>Forward</strong> button if you hold <em>either</em> permission, and the recipients-field hint adapts to what you're allowed to do. If you try to send to an address you're not permitted to reach, the send is rejected and the disallowed addresses are named so you can remove them.</p>
<div class="alert alert-warning small mb-0"><i class="bi bi-shield-lock me-2"></i>
    <strong>Confidential tickets can't be forwarded.</strong> For confidential ticket types the Forward action is disabled — if you need to share something, copy the specific details into a separate email by hand.
</div>
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
<p class="text-muted mb-0">You can create and manage your own canned responses from your <a href="/agent/canned-responses"><strong>profile page</strong></a> (top-right avatar menu &rarr; <strong>My Profile</strong> &rarr; <strong>Manage Canned Responses</strong>).</p>
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
<h6 class="fw-semibold mt-3 mb-2">Sorting the Timeline</h6>
<p class="text-muted mb-2">Click the <strong>Timeline</strong> heading to flip the order between <strong>newest-first</strong> and <strong>oldest-first</strong>. Your choice is remembered as your default across every ticket and session — and a matching <strong>Ticket Timeline</strong> option on your <a href="/profile">profile page</a> (Newest first / Oldest first) sets the same default. In either direction the entries you read first sit at the top, and the "show older / newer updates" collapser sits at the bottom; the <strong>Go to solution</strong> anchor keeps working both ways.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Admins see two switches in the Timeline header — <strong>System notes</strong> and <strong>AI notes</strong> — to hide automated entries (SLA events, AI classifications, and similar) and keep the focus on the conversation. The choice sticks to the admin's account across tickets. The notes are only hidden from view, never deleted.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-broadcast text-primary me-2"></i>Live Updates &amp; Avoiding Collisions</h5>
<p class="text-muted mb-2">While you have a ticket open, the page keeps itself current without a refresh:</p>
<ul class="text-muted mb-3">
    <li><strong>The timeline updates on its own.</strong> Replies, internal notes, status/priority/assignment changes, and inbound emails from other people slot in automatically — with a brief highlight on each new entry — while your in-progress reply draft is left untouched.</li>
    <li><strong>You can see who else is here.</strong> A coloured pill in the pinned header shows when a colleague has the ticket open: <span class="badge" style="background:#0d6efd;">blue</span> while they're only viewing, <span class="badge" style="background:#b45309;">amber</span> while they're replying. It appears and clears live as people come and go. (Because the header stays pinned just below the navbar as you scroll, this warning never scrolls off-screen.)</li>
</ul>
<h6 class="fw-semibold mb-2">Reply-collision warning</h6>
<p class="text-muted mb-2">If another agent posts a public reply while you're drafting yours, clicking <strong>Send Reply</strong> pauses and shows a warning naming who replied, when, and a short excerpt of what they said. You can:</p>
<ul class="text-muted mb-3">
    <li><strong>Review their reply</strong> — opens the up-to-date ticket in a new tab (your draft is safe).</li>
    <li><strong>Cancel</strong> — go back and adjust your reply.</li>
    <li><strong>Post anyway</strong> — send your reply as written.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    <strong>Your reply draft is autosaved as you type.</strong> It survives a refresh, an accidental navigation, or going off to read a colliding reply — when you return the reply box reopens with <em>"Restored your unsent draft"</em> and a <strong>Discard</strong> link. The draft is cleared the moment the reply is actually sent. (Only public replies collide — internal notes and forwards never trigger the warning.)
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-star text-warning me-2"></i>Satisfaction Survey Results</h5>
<p class="text-muted mb-2">When a requester rates their experience through a CSAT survey, a <strong>Satisfaction Survey</strong> panel appears on the ticket showing:</p>
<ul class="text-muted mb-2">
    <li>The <strong>star rating</strong> and the requester's <strong>comment</strong>.</li>
    <li>The <strong>response time</strong>, and a <span class="badge bg-warning text-dark">Reopened by requester</span> badge if they reopened the ticket from the survey.</li>
    <li>While a survey is still outstanding, the panel shows an <em>"awaiting response"</em> state instead.</li>
</ul>
<p class="text-muted mb-0">An <strong>Open survey</strong> button links to the exact survey that was emailed — the public rating page for built-in surveys, or the third-party form for external ones. When a rating lands it's also written to the timeline as an internal <strong>Satisfaction Rating</strong> entry.</p>
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
