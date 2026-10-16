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

<div class="card border-0 shadow-sm mb-4" id="creating-tickets">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Creating Tickets</h5>
<p class="text-muted mb-2">Tickets arrive in several ways:</p>
<ul class="text-muted mb-3">
    <li><strong>Portal:</strong> requesters submit a request at <code>/portal</code> using the form for the ticket type they pick (see <a href="#form-builder">The Ticket Form Builder</a>).</li>
    <li><strong>Email:</strong> messages sent to the monitored mailbox become tickets (see <a href="/admin/docs/email">Email &amp; Notifications</a>).</li>
    <li><strong>Staff:</strong> click <strong>New Ticket</strong> on the ticket list (<a href="/admin/tickets/create"><code>/admin/tickets/create</code></a>; agents use <code>/agent/tickets/create</code>).</li>
    <li><strong>Recurring schedules and the REST API</strong> also create tickets (see <a href="/admin/docs/automations">Automations</a>).</li>
</ul>
<p class="text-muted mb-2">The staff <strong>New Ticket</strong> form has a <strong>Template&hellip;</strong> picker at the top, then <em>Subject</em> and <em>Description</em>, <em>Type</em>, <em>Status</em> and <em>Due Date</em>, followed by the chosen type's own fields (Location, Priority, SLA notice, Tags, Attachments and custom fields, in the order set in the Form Builder). The <strong>Assignment</strong> card holds <em>Assign To</em>, <em>Group</em> and <em>On Behalf Of</em>, and the <strong>CC</strong> card adds people who should get email updates.</p>
<ul class="text-muted mb-3">
    <li>Picking a <strong>Type</strong> switches the form to that type's layout. Text blocks and images appear only once a type is chosen.</li>
    <li><strong>Assign To</strong> narrows to members of the type's default group. Choosing a <strong>Group</strong> narrows it to that group instead.</li>
    <li><strong>Priority</strong> lists only the priorities the type offers (see <a href="#ticket-types">Ticket Types</a>).</li>
    <li>If you leave Group blank, the ticket goes to the type's default group, then the system Default Group (see <a href="/admin/docs/flows">Assignment Flows</a>).</li>
    <li>Types with <em>Check for duplicates on submit</em> turned on may warn you before the ticket is created (see <a href="/admin/docs/ai#duplicate-detection">AI duplicate detection</a>).</li>
    <li>The form saves itself as a draft while you type (see <a href="#drafts">Drafts &amp; Undo Send</a>).</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    On creation, the requester gets a confirmation email, an assigned agent is notified, and group members who receive new-ticket alerts are told. Automations and group auto-assignment run, and SLA timers start if the ticket has a priority with a matching SLA policy.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="on-behalf-of">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-telephone-forward text-primary me-2"></i>Filing a Ticket On Behalf Of Someone Else</h5>
<p class="text-muted mb-2">When someone phones the helpdesk or stops you in the hall, you can file the ticket <em>for them</em> from the staff <strong>New Ticket</strong> form so the right person owns it from the start. Any staff member can use it, on both <a href="/admin/tickets/create"><code>/admin/tickets/create</code></a> and <code>/agent/tickets/create</code>.</p>

<p class="text-muted mb-2"><strong>How to use it</strong></p>
<ol class="text-muted mb-3">
    <li>Open the <strong>New Ticket</strong> form.</li>
    <li>In the <strong>Assignment</strong> card, find the <strong>On Behalf Of</strong> field.</li>
    <li>Type at least two characters of the person's name or email and pick them from the dropdown. External contacts and shared accounts are not listed.</li>
    <li>The search box is replaced by a badge showing the chosen requester. Use <strong>Clear</strong> to undo.</li>
    <li>Fill the rest of the form as normal and click <strong>Create Ticket</strong>.</li>
</ol>

<p class="text-muted mb-2"><strong>What happens behind the scenes</strong></p>
<ul class="text-muted mb-3">
    <li>The picked person becomes the ticket's <strong>requester</strong>. They own it: it shows up in <em>their</em> portal, the confirmation email and satisfaction survey go to <em>them</em>, and they can reply or close it like any ticket they submitted.</li>
    <li>You are recorded separately as the <strong>submitter</strong>, so the audit trail isn't lost. The first timeline entry reads <em>"Ticket filed by [staff] on behalf of [requester]."</em>, and the agent ticket view shows <em>"Filed by [staff] on their behalf"</em> under <em>Created By</em>.</li>
    <li>Leaving the picker blank, or picking yourself, files the ticket as your own.</li>
</ul>

<p class="text-muted mb-2"><strong>The requester must already have a user account.</strong> If the caller is new, create their account first at <a href="/admin/users/create"><strong>Settings → Users &amp; Access → Users → Add User</strong></a>, then come back to file the ticket.</p>

<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    The submitter is only visible to staff. In the portal, the requester sees the ticket as theirs, with no reference to who filed it.
</div>
</div>
</div>

<h3 id="working-tickets" class="fw-bold mt-5 mb-3">Working a Ticket</h3>

<div class="card border-0 shadow-sm mb-4" id="ticket-page">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-layout-three-columns text-primary me-2"></i>The Ticket Page</h5>
<p class="text-muted mb-2">The header shows the subject, status, priority and SLA badges, and stays pinned below the navbar as you scroll. Closed tickets also show why they were closed (see <a href="#closing">Closing a Ticket</a>). The action buttons sit at the top right:</p>
<ul class="text-muted mb-3">
    <li><strong>Watch</strong> &mdash; get email updates on the ticket; the button then reads <strong>Watching</strong>. Your watched tickets are one click away with the <em>My Watched Tickets</em> filter. Not available on confidential tickets.</li>
    <li><strong>Escalate</strong> &mdash; on the agent ticket view only (see <a href="#escalating">Escalating a Ticket</a>).</li>
    <li><strong>Split</strong> and <strong>Merge</strong> &mdash; see <a href="#splitting">Splitting</a> and <a href="#merging">Merging</a>.</li>
    <li><strong>Close</strong> &mdash; a split button with ten ways to close (see <a href="#closing">Closing a Ticket</a>).</li>
    <li><strong>Back</strong> &mdash; returns to the page you came from (a notification, the dashboard, a filtered list). The <em>Tickets</em> breadcrumb always returns to the list with your filters intact.</li>
</ul>
<p class="text-muted mb-2">Below the header the page has three columns:</p>
<ul class="text-muted mb-3">
    <li><strong>Left:</strong> the description, then the <strong>Reply</strong>, <strong>Forward</strong> and <strong>Add Note</strong> buttons, AI <em>Similar past tickets</em> (when <a href="/admin/docs/ai#similar-tickets">turned on</a>), attachments, tags and the timeline, with a second set of the same three buttons below it.</li>
    <li><strong>Middle:</strong> <strong>Details</strong>, the AI routing and classification cards, <strong>Custom Fields</strong> (only fields on the type's form or with a saved value; empty ones are hidden) and the <a href="/admin/docs/csat#on-ticket">Satisfaction Survey</a> panel.</li>
    <li><strong>Right:</strong> <strong>CC</strong>, <strong>Update Ticket</strong> (Status, Priority, Assigned To, Group, Type), <a href="#time-tracking">Time Tracking</a> for types that track time, and the <strong>SLA</strong> card.</li>
</ul>
<p class="text-muted mb-0"><strong>Update Ticket</strong> stays disabled until you change a field. If someone else changed the status since you opened the page, your status change is refused with a notice and your other changes still save. Changing the <strong>Type</strong> moves the ticket into the new type's default group; changing the <strong>Group</strong> to one that doesn't match the type clears the type (see <a href="#agents-assign-any-group">below</a>). Staff who are assigned, CC'd or watching can always open a ticket, even when it belongs to a group they're not in (confidential tickets excepted).</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="closing">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-circle text-success me-2"></i>Closing a Ticket</h5>
<p class="text-muted mb-2">The green <strong>Close</strong> button closes the ticket and sends the requester the standard "ticket closed" email, exactly like setting the status to Closed in <em>Update Ticket</em>. The arrow beside it opens more options:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:230px">Option</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Close without notifying</strong></td><td>Closes with no email to the requester.</td></tr>
        <tr><td><strong>Close and notify</strong></td><td>Same as the Close button.</td></tr>
        <tr><td><strong>Close and request feedback</strong></td><td>Closes and sends the satisfaction survey, which serves as the requester's notice. Greyed out when surveys are turned off.</td></tr>
        <tr><td><strong>Close as duplicate&hellip;</strong></td><td>Pick the original ticket by number or subject. The ticket is linked to it, and both tickets get an internal note.</td></tr>
        <tr><td><strong>Close as won't fix&hellip;</strong></td><td>A reason is required.</td></tr>
        <tr><td><strong>Close as no response&hellip;</strong></td><td>For a requester who stopped replying.</td></tr>
        <tr><td><strong>Close and merge&hellip;</strong></td><td>Opens the Merge dialog (see <a href="#merging">Merging</a>).</td></tr>
        <tr><td><strong>Close and create KB article</strong></td><td>Closes with the standard email, then opens a new draft KB article prefilled with the subject, the problem (the description) and the resolution (the marked solution, else the latest public staff reply, else the latest staff note). Shown only to staff who can create KB articles.</td></tr>
        <tr><td><strong>Close and reopen on reply&hellip;</strong></td><td>If the requester replies within 1&ndash;90 days (default 7), the ticket reopens on its own.</td></tr>
        <tr><td><strong>Close and schedule follow-up&hellip;</strong></td><td>Pick a date and time (default a week out, 9:00 AM) and an optional note. When it's due you get an in-app notification on the ticket.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-2">Options marked &hellip; open a dialog with a message box and an <strong>Email the requester</strong> checkbox. Ticked, your message is sent as a public reply, or if you leave it blank the standard "ticket closed" email goes out instead. Unticked, the message is saved as an internal note and nothing is emailed. Duplicate, no-response and reopen-on-reply start with a suggested message you can edit.</p>
<p class="text-muted mb-2">Closed tickets show a badge for how they were closed: <em>Duplicate of #N</em>, <em>Won't fix</em>, <em>No response</em>, or <em>Reopens on reply until [date]</em>. Tickets closed as duplicate, won't fix or no response don't count as resolved in reports and dashboard totals. The Close button is hidden on tickets that are already Closed or merged; a Resolved ticket can still be closed.</p>
<h6 class="fw-semibold mt-3 mb-2">Reopen on reply</h6>
<p class="text-muted mb-2">A reply from the ticket's requester inside the window, whether from the portal, by email or through the API, moves the ticket back to the default status for new tickets and notifies the assigned agent. Replies after the window, or from anyone else, don't reopen it. By email, such a reply to a Closed ticket opens a new ticket with the message instead (see <a href="/admin/docs/email">Email</a>). What happens to SLA timers on reopen follows the <em>When a closed ticket is reopened</em> setting (see <a href="/admin/docs/sla">SLA Policies</a>).</p>
<h6 class="fw-semibold mt-3 mb-2">Follow-up reminders</h6>
<p class="text-muted mb-2">Follow-ups are delivered by the SLA cron job, so they arrive within about five minutes of the time you chose. The reminder goes to the person who set it.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Every option runs through the same path as an <em>Update Ticket</em> close, so the <a href="#resolution-note">resolution-note prompt</a>, the <a href="#time-tracking">no-time-logged reminder</a>, SLA and automations all apply.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="replies">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-dots text-primary me-2"></i>Replies, Internal Notes &amp; Forwarding</h5>
<p class="text-muted mb-2">The <strong>Reply</strong>, <strong>Forward</strong> and <strong>Add Note</strong> buttons sit directly under the ticket description, and a second set sits below the last timeline entry. The reply box opens under whichever set you click, and moving it keeps any unsent text:</p>
<ul class="text-muted mb-3">
    <li><strong>Reply</strong> &mdash; a public message. The requester and CC'd people are emailed.</li>
    <li><strong>Add Note</strong> &mdash; an internal note (highlighted). Only staff see it; it is never sent to the requester. Type <code>@</code> to mention a colleague.</li>
    <li><strong>Forward</strong> &mdash; sends the ticket details, the full public conversation and all attachments to one or more email addresses, with an optional note. Recipients are added as contacts and CC'd on the ticket, and their email replies thread back into it with an <em>External</em> badge. Forwarding needs the <em>Forward tickets to internal contacts</em> or <em>external contacts</em> permission, and is disabled on confidential tickets.</li>
</ul>
<p class="text-muted mb-2">The <strong>Canned</strong> button in the reply box inserts a saved response. The arrow beside <strong>Send Reply</strong> offers <strong>Send &amp; change status to</strong> Resolved (while that status is active), Closed, Pending, Waiting on Customer or Waiting on Third Party in one step.</p>
<p class="text-muted mb-2">Replying publicly to an unassigned ticket assigns it to you. If another staff member posts a public reply while you're writing yours, <strong>Send Reply</strong> stops and shows their reply, with <strong>Review their reply</strong>, <strong>Cancel</strong> and <strong>Post anyway</strong>.</p>
<p class="text-muted mb-0">Replies and notes can carry attachments, and images pasted into the editor are saved as attachments too (see <a href="#attachments">Attachments</a>).</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="drafts">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil-square text-primary me-2"></i>Drafts &amp; Undo Send</h5>
<p class="text-muted mb-2"><strong>Drafts.</strong> The staff New Ticket form, the portal's new-request form and every reply box save a draft to the server shortly after you stop typing; a <em>Draft saved</em> time appears under the editor. Reopen the form, on any computer, and a <em>Restored your unsent draft</em> banner offers <strong>Discard</strong>. A ticket where you have an unsent reply shows an amber <strong>Draft</strong> badge in your ticket lists. Drafts are personal, are deleted when you send, and expire after 90 days unused.</p>
<p class="text-muted mb-0"><strong>Undo send.</strong> At <a href="/admin/settings/undo-send"><strong>Settings → Customization → Undo Send</strong></a>, turn on <em>Enable undo send</em> and set the <em>Undo window</em> (3&ndash;120 seconds, default 10). Submitting a new ticket, reply, note or forward then shows a countdown with an <strong>Undo</strong> button (or press Escape). Nothing is saved or emailed until the countdown ends. It applies to staff and portal forms and is off by default.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="assigning">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-check text-primary me-2"></i>Assigning Tickets</h5>
<p class="text-muted mb-2">Tickets can be assigned to any staff member. Assignment can happen:</p>
<ul class="text-muted mb-3">
    <li>At creation time, with <strong>Assign To</strong> on the New Ticket form.</li>
    <li>From the ticket page, with <strong>Assigned To</strong> in the <strong>Update Ticket</strong> panel.</li>
    <li>From the ticket list, with the <strong>chevron <i class="bi bi-chevron-down"></i></strong> in the Assigned To column, or the bulk <strong>Assign</strong> action.</li>
    <li>By replying publicly to an unassigned ticket, which assigns it to you.</li>
    <li>Automatically, through the group's auto-assignment strategy or an automation (see <a href="/admin/docs/flows">Assignment Flows</a> and <a href="/admin/docs/automations">Automations</a>).</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    When a ticket has a <strong>Group</strong>, the agent picker on the ticket page and in the list is filtered to that group's members. In <strong>Update Ticket</strong>, changing the <strong>Group</strong> re-filters <strong>Assigned To</strong> straight away; pick <em>None</em> to choose from all staff. The current assignee stays selected if they belong to the new group, otherwise the field resets to Unassigned.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="agents-assign-any-group">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Letting Agents Assign Tickets to Any Group</h5>
<p class="text-muted mb-2">By default, the <strong>Group</strong> picker on the agent ticket list is <strong>scoped to the groups each agent belongs to</strong>. An agent can move a ticket within their own queues, but can't push it into a group they aren't a member of. This keeps work from being silently routed into queues nobody on that team is watching. Admins are <strong>never</strong> restricted; they always see every group.</p>
<p class="text-muted mb-2">The <strong>Allow agents to assign tickets to groups they're not part of</strong> setting lifts that restriction. Turn it on and every agent's group picker lists <strong>all</strong> groups.</p>

<h6 class="fw-semibold mt-4 mb-2">When to turn it on</h6>
<ul class="text-muted mb-3">
    <li><strong>Turn it on</strong> when a small triage team fields incoming tickets and routes them across the whole organisation, e.g. a front-desk agent who hands work off to IT, Facilities or HR queues they don't sit in.</li>
    <li><strong>Leave it off</strong> (the default) when each team owns its own queue. This is the safer choice for larger or more siloed teams.</li>
</ul>

<h6 class="fw-semibold mt-4 mb-2">What it affects</h6>
<ul class="text-muted mb-3">
    <li><strong>Only agents.</strong> It changes the group list non-admin agents see in the inline <strong>Group</strong> dropdown on the ticket list and on the agent dashboard's <strong>Recent Tickets</strong> widget.</li>
    <li><strong>Only the picker.</strong> It grants no new permission; an agent still needs access to the ticket itself.</li>
    <li><strong>It is audit-logged</strong> as <code>settings.agents_assign_any_group_changed</code> in the <a href="/admin/audit-log">Audit Log</a>.</li>
</ul>

<h6 class="fw-semibold mt-4 mb-2">How to use it</h6>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/settings/ticket-routing"><strong>Settings → Organization → Ticket Routing</strong></a>.</li>
    <li>Flip the <strong>Allow agents to assign tickets to groups they're not part of</strong> switch.</li>
    <li>Click <strong>Save</strong>. The change takes effect immediately.</li>
</ol>

<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    <strong>Types and groups stay in step.</strong> Each ticket type can have a default group. Moving a ticket into a group that doesn't match its type's default group clears the <strong>Type</strong> back to <em>Not Set</em>, and changing a ticket's type moves it into the new type's default group. Both changes are recorded on the timeline. Types with no default group can sit in any group.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="merging">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-intersect text-primary me-2"></i>Merging Tickets</h5>
<p class="text-muted mb-2">When the same issue arrives as several tickets, merge them into one. Merging <strong>moves the conversation</strong>: the secondary ticket's replies, internal notes, forwards and attachments, plus its original request, move into the primary and slot into its timeline by date. System events stay behind on the secondary.</p>
<ol class="text-muted mb-3">
    <li>Open one of the tickets and click <strong>Merge</strong> (or <strong>Close and merge&hellip;</strong> from the Close menu).</li>
    <li>Search for the other ticket by number or subject and pick it.</li>
    <li>Choose which ticket should be the <strong>primary</strong>: the one you picked, or the one you're on.</li>
    <li>Click <strong>Merge Tickets</strong>.</li>
</ol>
<ul class="text-muted mb-3">
    <li>The secondary is closed, shows a <strong>Merged</strong> badge and links to the primary.</li>
    <li>CC'd people and tags are copied to the primary.</li>
    <li>The primary's priority is raised to the highest priority among the merged tickets, with a timeline entry.</li>
    <li>Public replies from the secondary become visible to the primary's requester. The secondary's requester and CC'd people are notified.</li>
    <li>Confidential tickets can only be merged with tickets of the same type.</li>
</ul>
<p class="text-muted mb-0">To stop duplicates being filed in the first place, see <a href="/admin/docs/ai#duplicate-detection">AI duplicate-ticket detection</a>. To merge several at once, use the bulk <strong>Merge</strong> action on the ticket list.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="splitting">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-scissors text-warning me-2"></i>Splitting a Ticket</h5>
<p class="text-muted mb-2">When one ticket turns out to hold two separate issues, click <strong>Split</strong>. The <em>Split Ticket</em> page lists the ticket's public comments (with <strong>Select All</strong> and <strong>Clear</strong>); tick the ones that belong to the new issue, then fill in the new ticket's Subject, Description, Type, Priority, Assign To and Group.</p>
<p class="text-muted mb-0">Click <strong>Split &amp; Create New Ticket</strong>. The ticked comments (and their attachments) move to the new ticket, which keeps the original's location and starts as Open. Both tickets get a timeline entry linking them. Splitting a confidential ticket requires re-authentication first.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="escalating">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-up-right-circle text-danger me-2"></i>Escalating a Ticket</h5>
<p class="text-muted mb-2">A red <strong>Escalate</strong> button on the agent ticket view (<code>/agent/tickets/{id}</code>) and, for requesters, on the portal reassigns the ticket to the next person in its ticket type's escalation path. The previous assignee stays on as a watcher. The admin ticket view doesn't show the button.</p>
<ul class="text-muted mb-3">
    <li><strong>Where to configure</strong> &mdash; <a href="/admin/settings/escalation-paths"><strong>Settings → Automation → Escalation Paths</strong></a>. Each ticket type has its own ordered chain.</li>
    <li><strong>When it shows</strong> &mdash; only on types that have a path. It is disabled on resolved or closed tickets and at the top of the chain. The <em>Escalate Button Visibility</em> setting on the same page can hide it until the ticket's SLA has breached.</li>
    <li><strong>Who can escalate</strong> &mdash; staff on any ticket they can access; portal users on their own tickets.</li>
    <li><strong>Skip-current-assignee logic</strong> &mdash; if the current assignee already appears in the path, Escalate goes to the step after them.</li>
    <li><strong>Tracking</strong> &mdash; escalated tickets show on the agent dashboard's "Escalated to Me" card and in a ticket-list filter.</li>
</ul>
<p class="text-muted mb-0">See the <a href="/admin/docs/automations#escalation-paths">Automations doc</a> for full configuration guidance and how this differs from the time-based <a href="/admin/docs/automations#escalation-rules">Escalation Rules</a>.</p>
</div>
</div>

<h3 id="ticket-settings" class="fw-bold mt-5 mb-3">Statuses, Priorities &amp; Ticket Types</h3>

<div class="card border-0 shadow-sm mb-4" id="ticket-statuses">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>Ticket Statuses</h5>
<p class="text-muted mb-2">Each ticket moves through a lifecycle managed by statuses. Statuses are <strong>fully configurable</strong> &mdash; add, remove, rename, recolour, and reorder them from <a href="/admin/settings/ticket-statuses"><strong>Settings &rarr; Organization &rarr; Ticket Statuses</strong></a>. The seven statuses installed by default are:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Status</th><th>Meaning</th></tr></thead>
    <tbody>
        <tr><td><span class="badge bg-warning text-dark">Open</span></td><td class="text-muted">Newly submitted; awaiting agent action. The default status for new tickets.</td></tr>
        <tr><td><span class="badge bg-primary">In Progress</span></td><td class="text-muted">An agent is actively working on the ticket.</td></tr>
        <tr><td><span class="badge bg-info text-dark">Pending</span></td><td class="text-muted">Waiting on a response from the requester or a third party.</td></tr>
        <tr><td><span class="badge" style="background:#7c3aed;">Waiting on Customer</span></td><td class="text-muted">A reply has been sent; waiting for the requester to respond. SLA timer pauses. Can trigger automated reminder emails via Escalation Rules.</td></tr>
        <tr><td><span class="badge" style="background:#0369a1;">Waiting on Third Party</span></td><td class="text-muted">Work is blocked pending an external vendor or team. SLA timer pauses while in this state.</td></tr>
        <tr><td><span class="badge bg-success">Resolved</span></td><td class="text-muted">The issue has been addressed. The requester is notified, and (if CSAT is enabled) a satisfaction survey is sent.</td></tr>
        <tr><td><span class="badge bg-secondary">Closed</span></td><td class="text-muted">Fully closed. No further action expected.</td></tr>
    </tbody>
</table>
</div>
<h6 class="fw-semibold mt-4 mb-2">Configuring statuses</h6>
<p class="text-muted mb-2">On the <a href="/admin/settings/ticket-statuses">Ticket Statuses</a> page each status has four levers admins can pull:</p>
<ul class="text-muted mb-2">
    <li><strong>Bucket</strong> &mdash; <em>Open</em> or <em>Closed</em>. Drives business logic across the app: <em>open</em>-bucket statuses count toward "open" dashboard tiles and SLA tracking; <em>closed</em>-bucket statuses are treated as done and unlock the resolved/closed email + CSAT flows when used as a default for those kinds.</li>
    <li><strong>Pauses SLA</strong> &mdash; a per-status toggle. Any status with this on pauses the SLA timer (default policy: <em>Pending</em>, <em>Waiting on Customer</em>, <em>Waiting on Third Party</em>). The timer resumes the moment a ticket moves to a status that does not pause SLA.</li>
    <li><strong>Colour</strong> &mdash; the badge colour everywhere the status appears (lists, dashboards, emails). Text contrast picks itself.</li>
    <li><strong>Active</strong> &mdash; click the green checkmark in the Active column to hide a status from every dropdown without losing the historical tickets that still hold it. Existing tickets keep their stored status either way.</li>
</ul>
<p class="text-muted mb-2"><strong>Defaults.</strong> Three slots determine which status is used in three system-driven flows:</p>
<ul class="text-muted mb-2">
    <li><strong>Default for new tickets</strong> &mdash; what every newly-submitted ticket lands on, and where a reopened ticket goes. Must be in the <em>open</em> bucket.</li>
    <li><strong>Default for resolved</strong> &mdash; the status that triggers the "ticket resolved" email + CSAT survey. Must be in the <em>closed</em> bucket.</li>
    <li><strong>Default for closed</strong> &mdash; the status that triggers the "ticket closed" email, and the status the Close button sets. Must be in the <em>closed</em> bucket.</li>
</ul>
<p class="text-muted mb-2">To change a default, open the <i class="bi bi-gear"></i> <em>set</em> menu in the Defaults column on the target row and pick the slot to claim. The previous holder is cleared in the same transaction so the slot is always occupied.</p>
<p class="text-muted mb-2"><strong>Adding a custom status.</strong> Click <strong>Add Status</strong> in the top-right. Supply a label, a slug (lowercase letters / digits / underscores &mdash; auto-suggested from the label), a bucket, and a colour. <strong>Slugs are permanent</strong> after create &mdash; existing tickets, automations, and integrations reference the slug, so changing it later would break things. Labels, colours, and everything else are editable any time.</p>
<p class="text-muted mb-2"><strong>Reordering.</strong> Drag the grip handle <i class="bi bi-grip-vertical"></i> on any row to set the order &mdash; the new order drives every status dropdown across the app and saves immediately.</p>
<h6 class="fw-semibold mt-4 mb-2">Guardrails</h6>
<p class="text-muted mb-2">The settings page refuses destructive changes that would break the rest of the app:</p>
<ul class="text-muted mb-0">
    <li>Built-in statuses (the seven seeded above) can be relabeled, recoloured, or deactivated but <strong>not deleted</strong>.</li>
    <li>A status holding any of the three default slots cannot be deleted or deactivated until another status is promoted to that slot.</li>
    <li>The last active status in a bucket cannot be deactivated or deleted &mdash; that would leave the bucket empty and break new-ticket creation and dashboard counters.</li>
    <li>Statuses referenced by an automation, escalation rule, or the CSAT trigger setting cannot be deleted until those references are edited to point elsewhere. The error message lists exactly which rules block the delete.</li>
    <li>If tickets currently hold a status you're trying to delete, the delete modal shows a <strong>Reassign tickets to:</strong> dropdown so you can move them to a different active status in the same transaction before the row is removed.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="priorities">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-flag text-primary me-2"></i>Priorities</h5>
<p class="text-muted mb-2">Priorities set urgency and drive SLA timers. Low, Medium, High and Critical are created at installation. Add, rename, recolour and reorder them at <a href="/admin/priorities"><strong>Settings → Organization → Ticket Priorities</strong></a>. The order matters: the first priority is the default used when a type hides the Priority field, and merging tickets raises the primary to whichever of their priorities sits lowest in the list (Critical, by default).</p>
<p class="text-muted mb-0">Response and resolution targets are set per priority (and optionally per ticket type) at <a href="/admin/settings/sla-policies"><strong>Settings → Scheduling → SLA Policies</strong></a>. Each ticket type can also limit which priorities it offers (see <a href="#ticket-types">Ticket Types</a>).</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="ticket-types">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-tags text-primary me-2"></i>Ticket Types</h5>
<p class="text-muted mb-2">Ticket types (e.g. IT, Facilities, Collections) categorise tickets and carry most per-category behaviour. Manage them at <a href="/admin/types"><strong>Settings → Organization → Ticket Types</strong></a>; each type's edit form has these settings:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:260px">Setting</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Type Name</strong>, <strong>Color</strong>, <strong>Sort Order</strong></td><td>How the type appears in lists and dropdowns.</td></tr>
        <tr><td><strong>Default Group</strong></td><td>Where new tickets of this type land, and the agents offered for assignment. Required for Confidential.</td></tr>
        <tr><td><strong>Confidential</strong></td><td>Locks tickets to the default group (see <a href="#confidential">Confidential Ticket Types</a>).</td></tr>
        <tr><td><strong>Let AI route this to the best group ("No Wrong Door")</strong></td><td>AI picks the group; see <a href="/admin/docs/ai#no-wrong-door">AI docs</a>.</td></tr>
        <tr><td><strong>Check for duplicates on submit</strong></td><td>AI warns about likely duplicates, with a <em>Match confidence threshold</em> (0.50&ndash;0.99, default 0.75); see <a href="/admin/docs/ai#duplicate-detection">AI docs</a>.</td></tr>
        <tr><td><strong>Show similar past tickets to agents</strong></td><td>On by default; needs the global switch too. See <a href="/admin/docs/ai#similar-tickets">AI docs</a>.</td></tr>
        <tr><td><strong>Visible to Location Ticket Visibility users</strong></td><td>See <a href="#location-visibility">below</a>.</td></tr>
        <tr><td><strong>Prompt for a resolution note before the owner closes</strong></td><td>See <a href="#resolution-note">below</a>.</td></tr>
        <tr><td><strong>Track time spent on these tickets</strong></td><td>See <a href="#time-tracking">Time Tracking</a>.</td></tr>
        <tr><td><strong>Form Fields</strong></td><td><strong>Edit this type's form</strong> opens the type in the <a href="#form-builder">Form Builder</a>.</td></tr>
        <tr><td><strong>SLA on Reopen</strong></td><td>Overrides the site-wide <em>When a closed ticket is reopened</em> setting for this type (see <a href="/admin/docs/sla">SLA Policies</a>).</td></tr>
        <tr><td><strong>Required Skills</strong></td><td>Used by Skill-Based auto-assignment (see <a href="/admin/docs/flows#d4">Assignment Flows</a>).</td></tr>
        <tr><td><strong>Available Priorities</strong></td><td>Which priorities requesters and agents can pick for this type. All ticked (the default) offers every priority, including ones added later. Enforced on every form, quick-edit and bulk change; existing tickets keep their priority.</td></tr>
    </tbody>
</table>
</div>
<ul class="text-muted mb-3">
    <li>If you turn on an AI option while AI is off or not responding, saving shows <em>AI isn't ready for this ticket type</em> with <strong>Save anyway</strong> or <strong>Close and edit</strong>. Until AI works, those tickets simply land in the Default Group.</li>
    <li>The list's <strong>Direct Link</strong> column gives a link that opens the portal form with the type pre-selected (see <a href="#direct-links">Direct Links</a>). A signpost icon marks AI-routed types.</li>
    <li>Deleting a type that still has tickets lets you <strong>Bulk Reassign</strong> them to another type, <strong>Delete All Affected Tickets</strong>, or edit them individually first.</li>
</ul>
<p class="text-muted mb-0"><strong>Type Settings Matrix.</strong> <a href="/admin/types/matrix"><strong>Settings → Organization → Type Settings Matrix</strong></a> shows every type against its optional settings (group assigned, confidential, AI routing, AI duplicate check, AI similar tickets, location visibility, time tracking, custom duplicate threshold, SLA on reopen) in one grid. <strong>Print</strong> fits it on one landscape page.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="location-visibility">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye-slash text-primary me-2"></i>Hiding a Type from Location-Visibility Users</h5>
<p class="text-muted mb-2">Each ticket type has a <strong>Visible to Location Ticket Visibility users</strong> checkbox, on by default. Unchecking it hides every ticket of that type from portal users who have the <a href="/admin/docs/users">Location Ticket Visibility</a> permission, while agents and admins work the queue normally. Requesters always see their own tickets.</p>
<p class="text-muted mb-2">This is the lighter alternative to <strong>Confidential</strong> ticket types (described below). Use it for routine-but-sensitive types like Collections, HR, or Payroll where you simply don't want supervisors / branch leads to see the contents &mdash; without invoking the heavier confidential flow (group lock, re-authentication, audit log, email alerts).</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Flag</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><strong>Visible to Location Ticket Visibility</strong> <span class="badge bg-secondary ms-1">light</span></td>
            <td>Hides the type from portal users with the location-visibility permission. No effect on agents / admins. No re-auth or email notification overhead.</td>
        </tr>
        <tr>
            <td><strong>Confidential</strong> <span class="badge bg-warning text-dark ms-1">strict</span></td>
            <td>Locks tickets to a specific group, redacts them for non-group admins, requires password re-auth + audit log + email notification when an outside admin views one. See below.</td>
        </tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="resolution-note">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-journal-check text-primary me-2"></i>Prompt for a Resolution Note on Close</h5>
<p class="text-muted mb-2">Agents often close their own tickets without recording what actually fixed the issue &mdash; so the next person who hits the same problem starts from scratch. Each ticket type has a <strong>Prompt for a resolution note before the owner closes</strong> checkbox to nudge that knowledge out before the ticket disappears into the closed pile.</p>
<p class="text-muted mb-2">When it's on and the ticket's <strong>assigned owner</strong> moves it to a closed-bucket status from the ticket page (the <em>Update Ticket</em> panel or the <strong>Close</strong> button and its options) without having added any comment, a modal asks what resolved it. The note box is pre-filled with a suggested framework (<em>Action Taken &rarr; Verification &rarr; Next Steps</em>), and <strong>Add resolution &amp; close</strong> saves it as an <strong>internal note</strong> (staff-only &mdash; the requester is not emailed). Agents who genuinely have nothing to record can click <strong>Close without a note</strong> and pick a reason (Duplicate, Spam, No response needed, Resolved elsewhere, Other), which is logged to the timeline.</p>
<p class="text-muted mb-0"><strong>It's a nudge, not a wall.</strong> It only fires for the <em>assigned owner</em>, only <em>from the ticket page</em>, and only when <em>no comment exists yet</em> &mdash; so replying with a resolution before closing skips it, as do list quick-edits and bulk status changes. It does not apply when someone other than the assignee closes the ticket, nor to automated closes.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="time-tracking">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hourglass-split text-primary me-2"></i>Time Tracking</h5>
<p class="text-muted mb-2">Each ticket type has a <strong>Track time spent on these tickets</strong> checkbox (off by default). When it's on, tickets of that type get a <strong>Time Tracking</strong> card on the ticket page, under Update Ticket, showing the ticket's total and every entry with who logged it. Agents can:</p>
<ul class="text-muted mb-2">
    <li><strong>Start timer / Stop timer</strong>: the timer runs on the server, so it keeps counting through page reloads. Each agent has one live timer; starting a timer on another ticket stops the one already running. Add a short note when stopping.</li>
    <li><strong>Log time</strong>: enter hours and minutes, the date the work was done, and an optional note, for work done away from the ticket page.</li>
    <li><strong>Edit / Delete</strong> their own entries. Admins can edit or delete anyone's.</li>
</ul>
<p class="text-muted mb-2">The card saves in place, so a half-written reply isn't lost. If an agent closes one of these tickets from the ticket page (the Update form, the Close button, or the reply box's <em>Send &amp; change status</em>) with no time logged, a reminder asks them to log time first. Choosing <strong>Log time</strong> turns the card's button into <strong>Save &amp; close ticket</strong>, so saving the entry finishes the close they started. They can still choose <strong>Close without logging</strong>. List quick-edits, bulk changes, and automations close without asking.</p>
<p class="text-muted mb-0">Logged time feeds two reports under <a href="/admin/reports"><strong>Reports</strong></a>. <strong>Time Spent</strong> shows totals, a daily or monthly trend, time by ticket type and group, and the tickets that took longest. <strong>Agent Time</strong> shows each agent's total, an agent &times; ticket type grid, and a per-agent timesheet. Both export to CSV, count time on the <em>date the work was done</em>, leave out timers that are still running, and hide the subject and note of confidential tickets. Turning tracking off for a type later keeps time already logged visible on its tickets.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="confidential">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Confidential Ticket Types</h5>
<p class="text-muted mb-2">Ticket types can be marked as <strong>Confidential</strong> to restrict access to sensitive tickets (e.g., HR, Legal). This feature ensures that only authorised group members can view the ticket details.</p>

<h6 class="fw-semibold mt-3">How to Enable</h6>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/types"><strong>Settings &rarr; Organization &rarr; Ticket Types</strong></a>.</li>
    <li>Edit or create a ticket type.</li>
    <li>Select a <strong>Default Group</strong> (required for confidentiality).</li>
    <li>Check the <strong>Confidential</strong> checkbox and save.</li>
</ol>

<h6 class="fw-semibold mt-3">What Happens</h6>
<ul class="text-muted mb-3">
    <li><strong>Group members</strong> and the ticket's requester can see confidential tickets; group members work on them normally.</li>
    <li><strong>Other staff</strong> cannot see confidential tickets at all &mdash; not even if they are assigned, watching, or hold the <em>View all tickets</em> permission. The tickets are hidden from lists and search.</li>
    <li><strong>Admins not in the group</strong> can see confidential tickets in the listing, but the subject is replaced with <em>[Confidential]</em> and other details are hidden.</li>
</ul>

<h6 class="fw-semibold mt-3">Admin Re-Authentication</h6>
<p class="text-muted mb-2">When an admin who is <strong>not</strong> a member of the confidential type's group clicks on a confidential ticket, they are presented with a re-authentication screen. Before viewing the ticket they must:</p>
<ul class="text-muted mb-3">
    <li>Re-enter their password.</li>
    <li>Acknowledge that the access will be recorded in the <a href="/admin/audit-log"><strong>Audit Log</strong></a>.</li>
    <li>Acknowledge that all members of the assigned group will be notified via email.</li>
</ul>
<p class="text-muted mb-2">After authentication, the admin can view the ticket for 5 minutes before needing to re-authenticate again.</p>

<h6 class="fw-semibold mt-3">Audit &amp; Notification</h6>
<ul class="text-muted mb-3">
    <li>Every admin access to a confidential ticket is recorded in the <strong>Audit Log</strong> with action <code>ticket.confidential_viewed</code>.</li>
    <li>A timeline entry is added to the ticket itself (internal only).</li>
    <li>All members of the assigned group receive an email notification containing the admin's name, email, IP address, and timestamp.</li>
</ul>

<h6 class="fw-semibold mt-3">Restrictions</h6>
<ul class="text-muted mb-0">
    <li>Confidential tickets are excluded from the <strong>REST API</strong> for non-group members. A 403 response is returned with a message to use the web interface.</li>
    <li><strong>Bulk actions</strong> automatically exclude confidential tickets the user cannot access.</li>
    <li><strong>Merge and Split</strong> are blocked if the user is not in the group, and confidential tickets can only be merged with tickets of the same type.</li>
    <li><strong>Watching</strong> and <strong>forwarding</strong> are not available on confidential tickets.</li>
    <li>Confidential tickets are never sent to AI, and new-ticket group emails go only to the type's group, with the subject redacted.</li>
    <li><strong>CSV exports</strong> replace the subject and identifying fields with "[Confidential]" for tickets the admin is not authorised to view.</li>
    <li><strong>Search/typeahead</strong> results redact confidential ticket subjects.</li>
</ul>
</div>
</div>

<h3 id="ticket-list" class="fw-bold mt-5 mb-3">The Ticket List</h3>

<div class="card border-0 shadow-sm mb-4" id="list-layouts">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-layout-text-sidebar text-primary me-2"></i>Ticket List Layouts (Table / Compact / Card)</h5>
<p class="text-muted mb-2">Each staff member can choose how their ticket lists are laid out with the <strong>Table / Compact / Card</strong> view switcher at the right of the ticket-list toolbar. The choice is saved per-user and applies to both the admin and agent ticket lists. Filtering, sorting, bulk-select and the bulk-action bar behave identically in all three.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:120px">Layout</th><th>What it looks like</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong><i class="bi bi-table me-1"></i>Table</strong></td><td>The classic resizable grid. The default, and the only layout with the <strong>Columns</strong> picker (Status, Priority, Type, Assigned To, Group, Created By, Location, SLA, Created, Due, Confidential). The <strong>Confidential</strong> column is hidden by default.</td></tr>
        <tr><td><strong><i class="bi bi-inbox me-1"></i>Compact</strong></td><td>An email-style two-column list showing only <strong>From</strong> and <strong>Subject</strong>. Hovering the <strong>Subject</strong> opens a detail card (requester, submitted time, message snippet, status/priority/type/assignee) with <strong>Reply</strong>, <strong>Forward</strong> and <strong>Add Note</strong> buttons that deep-link into the ticket composer. Hovering the <strong>From</strong> name opens a person card with their email and an <strong>Open tickets &amp; mentions</strong> button.</td></tr>
        <tr><td><strong><i class="bi bi-grid-1x2 me-1"></i>Card</strong></td><td>A roomier stacked layout where each ticket is a horizontal card &mdash; colour-keyed requester avatar, a &ldquo;New&rdquo; flag for tickets awaiting a first reply, type badge and subject, the location/group, age and SLA, and priority/assignee/status down the right edge.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mt-2 mb-2">The <strong>Kanban</strong> button at the end of the switcher opens the board view (<a href="/admin/tickets/board"><code>/admin/tickets/board</code></a>), which groups tickets by status, priority or assignee (see <a href="/agent/help/kanban">Agent Help &rarr; Kanban Board</a>).</p>
<p class="text-muted mb-2">In every layout, a ticket another staff member has open shows an amber subject with <em>Opened by [name]</em> or <em>Being replied to by [name]</em>, updated live. Tickets where you have an unsent reply show a <strong>Draft</strong> badge.</p>
<p class="text-muted mb-0"><strong>Open tickets &amp; mentions page.</strong> The sender card&rsquo;s <strong>Open tickets &amp; mentions</strong> button opens a per-user page listing every open ticket that person submitted plus any open ticket where they were <strong>@mentioned</strong> (mentions are badged). The page is scoped to what the viewing staff member is permitted to see, and confidential tickets stay redacted in every layout.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="inline-actions">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightning text-primary me-2"></i>Inline Ticket List Actions</h5>
<p class="text-muted mb-2">Staff can update key ticket fields directly from the ticket list without opening the ticket. Hovering a cell in one of these columns reveals a chevron (<i class="bi bi-chevron-down"></i>); clicking it opens a small dropdown to change that field immediately, and the badge or cell updates in place.</p>
<ul class="text-muted mb-3">
    <li><strong>Status column</strong> — click the status badge to set a new status. Only <em>active</em> statuses are offered, and the change runs the same logic as the ticket page: timeline entry, requester notification for closed-bucket statuses, CSAT survey trigger, SLA pause/resume, and <code>ticket_updated</code> automations. If someone else changed the status first, you see their value and an alert.</li>
    <li><strong>Priority column</strong> — click the priority badge to pick a new priority or clear it. Only priorities the ticket's type offers are listed. Records a timeline entry, recalculates SLA and runs <code>ticket_updated</code> automations.</li>
    <li><strong>Assigned To column</strong> — quick-assign to any staff member (filtered to the ticket's group if one is set).</li>
    <li><strong>Type column</strong> — change the ticket type in one click. The whole cell is clickable, not just the chevron.</li>
    <li><strong>Group column</strong> — reassign to a different group.</li>
</ul>
<p class="text-muted mb-0">The same inline actions are available in the <strong>Recent Tickets</strong> widget on the agent dashboard. A column picker on the dashboard widget lets you choose which columns are displayed.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="filters">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Filter Panel &amp; Saved Filters</h5>
<p class="text-muted mb-2">Click <strong>Filters</strong> to slide the filter panel out from the left. Whether it is open is remembered as you move between pages. The admin list filters by Search (subject), Status, Priority, Type, Location, Assigned To (including <em>Unassigned</em>), Group (including <em>No Group</em>), a From/To created-date range, <em>Requested by Me</em> (tickets you opened yourself), <em>My Watched Tickets</em> and <em>Has Attachment</em>. A location named <em>System Wide</em> is labelled <em>(any)</em> and matches every location.</p>
<p class="text-muted mb-2">Ticking a checkbox or picking a date <strong>applies the filter immediately</strong> &mdash; the list refreshes without reloading the page, and the URL updates so you can bookmark or share it. (The <strong>Search</strong> box is the exception: type your text and press Enter or click Apply.) Every active filter shows as a labelled pill at the top of the panel &mdash; e.g. <span class="badge rounded-pill bg-light text-dark border">Status: Open</span> or <span class="badge rounded-pill bg-light text-dark border">Requester: Jane Doe</span>. Click the <strong>&times;</strong> on a pill to remove just that filter, or <strong>Clear All</strong> to remove them all.</p>
<p class="text-muted mb-2">You can save any combination of active filters as a named preset:</p>
<ol class="text-muted mb-3">
    <li>Apply the filters you want, then click <strong>Save Current Filter</strong> in the panel and give it a name.</li>
    <li>Saved filters appear under <strong>Saved Filters</strong> and apply with one click.</li>
    <li>The arrow beside a saved filter offers <strong>Set as Default</strong> (applied automatically when you open the list each day), <strong>Share with Team</strong> / <strong>Make Private</strong>, and <strong>Delete</strong>.</li>
</ol>
<p class="text-muted mb-0">The list remembers the filters you used today and brings them back when you return; <strong>Clear All</strong> resets that. Coming back from a ticket via the <em>Tickets</em> breadcrumb restores the exact list, including sort order. The admin list shows 25 tickets per page; the agent list adds a per-page selector (25, 50, 100 or 200) at the bottom.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="bulk-actions">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-square text-primary me-2"></i>Bulk Actions</h5>
<p class="text-muted mb-2">The ticket list includes a checkbox column to select multiple tickets at once. When one or more tickets are selected, a bulk action bar appears at the top of the ticket list.</p>
<p class="text-muted mb-2">Available bulk actions:</p>
<ul class="text-muted mb-0">
    <li><strong>Assign</strong> — open an agent picker and assign all selected tickets to one agent.</li>
    <li><strong>Close</strong> — set all selected tickets to Closed status.</li>
    <li><strong>Merge</strong> — choose which selected ticket is the primary; the others are merged into it, moving their conversations as described in <a href="#merging">Merging Tickets</a>. The primary's priority is raised to the highest among them.</li>
    <li><strong>Status</strong> — set all selected tickets to a chosen status.</li>
    <li><strong>Priority</strong> — set all selected tickets to a chosen priority (or none). Tickets whose type doesn't offer that priority are skipped and counted.</li>
    <li><strong>Group</strong> — assign all selected tickets to a chosen group (or none).</li>
    <li><strong>Delete</strong> — permanently delete selected tickets and their attachments (asks for confirmation first; irreversible).</li>
</ul>
<p class="text-muted small mt-2 mb-0">Each action is a separate permission in the <strong>Bulk Actions</strong> group at <a href="/admin/roles"><strong>Settings &rarr; Users &amp; Access &rarr; Permission Levels</strong></a>; staff only see the buttons they&rsquo;re granted. <strong>Bulk delete tickets</strong> is off for every level except Admin until you grant it. Bulk actions skip tickets the user can't see, and each run is audit-logged.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="export">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-download text-primary me-2"></i>Exporting Tickets (CSV)</h5>
<p class="text-muted mb-2"><strong>Export CSV</strong> on the ticket list downloads every ticket matching the current filters and sort, not just the visible page. On the admin list it is available to admins; other staff export from the agent list, which only includes tickets they can see.</p>
<p class="text-muted mb-0">Columns: ID, Subject, Status, Priority, Type, Location, Group, Assigned To, Created By, Tags, Created, Due Date and SLA State. The file is UTF-8 with a BOM so Excel opens it cleanly, cells that look like spreadsheet formulas are neutralised, and confidential tickets you aren't authorised to view show <em>[Confidential]</em>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="reordering-admin-lists">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grip-vertical text-primary me-2"></i>Reordering &amp; Sorting Admin Lists</h5>
<p class="text-muted mb-2">The order that <strong>Ticket Types</strong>, <strong>Ticket Statuses</strong>, <strong>Groups</strong>, <strong>Priorities</strong>, <strong>Skills</strong>, <strong>Automations</strong>, <strong>Canned Responses</strong>, <strong>Escalation Rules</strong>, <strong>KB Categories</strong>, and <strong>KB Folders</strong> appear in &mdash; both on their settings tables and in every dropdown where those values surface on the agent and portal sides &mdash; can be set directly from each list.</p>
<p class="text-muted mb-2"><strong>Drag to reorder.</strong> Every row in those lists has a grip handle <i class="bi bi-grip-vertical"></i> on the left. Drag a row up or down and the new order saves immediately (an <em>Order saved</em> toast confirms it) &mdash; no integer to juggle.</p>
<p class="text-muted mb-0"><strong>Click a header to sort.</strong> Any sortable column header (Name, Members, Created, etc.) is clickable. Clicking re-sorts the visible rows ascending or descending. Because this overwrites your existing custom order, a yellow toolbar with <strong>Save Order</strong> and <strong>Revert</strong> buttons appears above the table &mdash; the sort is only committed once you click <strong>Save Order</strong>, so a stray click on a header never destroys a curated arrangement.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="resizing-columns">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrows text-primary me-2"></i>Resizing Columns</h5>
<p class="text-muted mb-2">Every column on every list in the app can be widened or narrowed by dragging its right edge. Hover the line between two column headers, the cursor turns into a resize grip, and you can drag to set the width.</p>
<ul class="text-muted mb-0">
    <li>Chosen widths are remembered per page in your browser &mdash; lists come back the way each person leaves them.</li>
    <li>A column can't be dragged narrower than its header text.</li>
    <li>On the ticket lists, <strong>Subject</strong> takes up the spare width and truncates (hover for the full text); widening Subject past the card edge scrolls the table horizontally.</li>
    <li>Works on the ticket lists, user list, KB lists, reports, and every settings table. A handful of tables with merged/multi-row headers are skipped (a single column width can't represent a merged header).</li>
    <li>On the ticket lists, a column you have hand-sized is not touched by the auto-fit that runs after an inline quick-change &mdash; only un-resized columns are re-measured.</li>
</ul>
</div>
</div>

<h3 id="ticket-content" class="fw-bold mt-5 mb-3">Tags, Attachments &amp; the Timeline</h3>

<div class="card border-0 shadow-sm mb-4" id="tags">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-tags text-primary me-2"></i>Tags</h5>
<p class="text-muted mb-2">Tags are free-form labels for categorising tickets. On the ticket page they sit above the timeline: type in the <em>#add tag</em> box and press <kbd>Enter</kbd>, or click the &times; on a tag to remove it. Tags can also be added on the New Ticket form (when the type's form includes Tags) and by the automation <em>Add tag</em> action. Tag names are stored in lowercase.</p>
<p class="text-muted mb-0">Tags are included in the CSV export. If your team doesn't use them, turn off <strong>Enable tags on tickets</strong> at <a href="/admin/settings/tags"><strong>Settings → Customization → Tags</strong></a> to hide the tag input everywhere.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="attachments">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-paperclip text-primary me-2"></i>Attachments</h5>
<ul class="text-muted mb-2">
    <li>Files can be attached on the New Ticket form (when the type's form includes Attachments), on replies, notes and forwards, and by requesters in the portal.</li>
    <li>Images pasted or dropped into the editor appear inline where they were pasted and are also listed with the ticket's attachments. Each one must pass the same type and size checks as an uploaded file; SVG is never accepted.</li>
    <li>Attachments show in the timeline entry they arrived with. Files on an internal note are never shown to the requester.</li>
    <li>With <strong>Show image attachment previews</strong> on (the default) at <a href="/admin/settings/attachments"><strong>Settings → Customization → Attachments</strong></a>, image attachments appear as thumbnails that open full size. Off, every attachment is a plain icon and filename. Pasted inline images are not affected.</li>
    <li>The <em>Has Attachment</em> filter finds tickets with at least one file.</li>
</ul>
<p class="text-muted mb-0">The allowed file types and maximum size are set by the server's <code>UPLOAD_ALLOWED_TYPES</code> and <code>UPLOAD_MAX_SIZE</code> settings. Files are stored in <code>storage/attachments/</code> and served only through the application, with the same access rules as the ticket.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="timeline">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Ticket Timeline</h5>
<p class="text-muted mb-2">Every ticket has a full audit trail in the timeline, showing all events in order:</p>
<ul class="text-muted mb-3">
    <li><strong>Replies</strong> — customer-visible messages (white background).</li>
    <li><strong>Internal Notes</strong> — private staff notes (highlighted with your configured note colour). Never visible to portal users.</li>
    <li><strong>System Events</strong> — status changes, priority changes, assignments, SLA updates, merges (highlighted with your configured system colour). Automated entries with no human author are shown to admins only.</li>
</ul>
<p class="text-muted mb-2">Click the <strong>Timeline</strong> heading to switch between newest-first and oldest-first; your choice is remembered (it is also on <strong>My Profile</strong>). Long timelines show the first 10 entries with a <em>Show N older/newer updates</em> link. New replies, notes and changes made by others appear on their own while you have the ticket open.</p>
<h6 class="fw-semibold mt-3">Hiding system &amp; AI notes</h6>
<p class="text-muted mb-2">A busy timeline can fill with automated entries — SLA timer events, escalation reminders, automatic group assignment, AI classifications. Admins get two switches in the <strong>Timeline</strong> card header to declutter the view:</p>
<ul class="text-muted mb-2">
    <li><strong>System notes</strong> — shows or hides <em>every</em> automated, human-author-less entry.</li>
    <li><strong>AI notes</strong> — shows or hides just the AI-generated entries (group classification, skill suggestions). This switch only appears when the ticket actually has AI notes. AI notes are a subset of system notes, so turning <em>System notes</em> off also hides AI notes.</li>
</ul>
<p class="text-muted mb-0">Flipping a switch takes effect instantly and is remembered for your account across every ticket and session — it sets the same per-user default you can also change at <strong>My Profile → System Timeline Notes / AI Timeline Notes</strong>. The notes are only hidden from view, never deleted. Defaults to showing everything.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="solution">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check-circle text-success me-2"></i>Marking a Comment as the Solution</h5>
<p class="text-muted mb-2">On a long ticket the answer often ends up buried halfway down the timeline. Staff can flag the comment that resolved the issue so everyone can jump straight to it.</p>
<ul class="text-muted mb-2">
    <li>Every customer-visible reply in the timeline shows a small <strong>Mark as solution</strong> button next to its timestamp. Click it to flag that comment.</li>
    <li>Once a comment is marked, a green alert with a <strong>Go to solution</strong> button appears at the top of the ticket — on the agent, admin, <em>and</em> the requester's portal view. It scrolls to the marked comment, which gets a green left border and a <strong>Solution</strong> badge.</li>
    <li>A ticket has one solution at a time. Marking a different comment replaces the previous one; use <strong>Unmark solution</strong> to clear it.</li>
    <li><strong>Close and create KB article</strong> uses the marked solution as the article's resolution.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Internal notes can't be marked — the requester can't see them, so a "Go to solution" link would point at nothing on the portal. Marking a solution does <em>not</em> change the ticket status: you can flag a candidate fix while a ticket is still <em>Waiting on Customer</em> and resolve it separately. On the portal the banner and badge say <strong>Answer</strong> by default — rename them under <a href="/admin/settings/labels">Settings → Customization → Labels</a>.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="sla-on-tickets">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-stopwatch text-primary me-2"></i>SLA on Tickets</h5>
<p class="text-muted mb-2">When SLA tracking is on and a ticket's type and priority match an SLA policy, its timers start automatically. The header shows an <strong>On Track</strong>, <strong>Warning</strong> (80% of the time used) or <strong>Breached</strong> badge, and the <strong>SLA</strong> card on the right shows:</p>
<ul class="text-muted mb-0">
    <li><strong>First Response</strong> — when an agent must post a public reply, or when they did.</li>
    <li><strong>Resolution</strong> — when the ticket must be resolved.</li>
    <li>A <em>Paused</em> badge while the status pauses SLA. Overdue times turn red.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="presence">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye text-primary me-2"></i>Seeing Who Else Is on a Ticket</h5>
<ul class="text-muted mb-0">
    <li>Under the ticket header, a pill shows <em>[name] is viewing this ticket</em> (blue) or <em>[name] is replying to this ticket</em> (amber), updated every few seconds.</li>
    <li>The ticket lists show the same information as an amber subject with <em>Opened by</em> or <em>Being replied to by</em>.</li>
    <li>Changes made by others appear in the timeline on their own. If the page can't refresh itself, a banner reads <em>This ticket was updated since you opened it</em> with a <strong>Refresh</strong> link.</li>
    <li>If someone posts a public reply while you're writing yours, you're warned before yours is sent (see <a href="#replies">Replies</a>).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="ticket-templates">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-file-earmark-text text-primary me-2"></i>Ticket Templates</h5>
<p class="text-muted mb-2">Templates pre-fill a new ticket's subject, description, type and priority to speed up common requests. Manage them at <a href="/admin/ticket-templates"><strong>Settings → Customization → Ticket Templates</strong></a>, or with the <strong>Templates</strong> button on the ticket list.</p>
<ul class="text-muted mb-3">
    <li>Each template has a name, <em>Internal Notes</em>, subject, a rich-text description, ticket type and priority. The priority list follows the type's available priorities.</li>
    <li>Staff with the <em>Manage ticket templates</em> permission can create templates. Admins can edit or delete any template; everyone else only their own.</li>
    <li>Turn on <strong>Share with portal users</strong> to offer the template in the <strong>Template&hellip;</strong> picker on the portal's new-request form.</li>
    <li>The staff New Ticket form's <strong>Template&hellip;</strong> picker lists every template. Picking one fills the fields immediately; everything stays editable.</li>
</ul>
</div>
</div>

<h3 id="form-builder" class="fw-bold mt-5 mb-3">The Ticket Form Builder</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ui-checks-grid text-primary me-2"></i>What the Form Builder is for</h5>
<p class="text-muted mb-2">The <strong>Form Builder</strong> at <a href="/admin/workflows/ticket-fields"><strong>Settings → Organization → Ticket Forms</strong></a> controls everything that appears on the <em>New Ticket</em> form — both on the public portal and on the staff New Ticket form. Anything you change here takes effect immediately for the next person who opens the form. There is no "publish" step.</p>
<p class="text-muted mb-2"><strong>Every ticket type has its own form.</strong> The page is laid out as a left rail of ticket types and a canvas:</p>
<ul class="text-muted mb-3">
    <li><strong>Type rail (left)</strong> — pick the ticket type whose form you want to edit. The canvas swaps to that type's layout; <strong>Type settings</strong> jumps to the type's edit form.</li>
    <li><strong>Canvas (middle)</strong> — the fields on the selected type's form, in the order requesters see them.</li>
    <li><strong>Live Preview (right)</strong> — an optional pane (see below) showing the real portal form for that type.</li>
</ul>
<p class="text-muted mb-2">The canvas shows three kinds of rows:</p>
<ul class="text-muted mb-3">
    <li><strong>Pinned</strong> — <em>Subject</em> and <em>Description</em>. Always at the very top of every type's form, always Required, and can't be moved or removed. Their labels can be renamed (pencil icon).</li>
    <li><strong>System fields</strong> — <em>Ticket Type</em> (always Required), <em>Location</em>, <em>Priority</em>, <em>SLA notice</em>, <em>Tags</em>, and <em>Attachments</em>. You can rename them, drag them to reorder, and set them Required / Optional / Hidden per type. They can't be deleted. <em>SLA notice</em> is a read-only line that shows the first-response and resolution targets once a priority is picked (hidden when no policy matches).</li>
    <li><strong>Custom fields</strong> — anything you add via <strong>Add field</strong>. Thirteen field types are supported (see the table below). Custom fields can be edited, reordered, given a visibility and a condition per type, shared onto other types, removed from a type, or deleted entirely.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Drag, reorder and visibility changes apply to the <em>currently selected ticket type only</em>. The same custom field can be Required on one type, Optional on another, and Hidden on a third — each type carries its own authoritative layout.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-ul text-primary me-2"></i>The 13 Custom Field Types</h5>
<p class="text-muted mb-2">Pick the type that matches the data you want to collect. Each row in the form-builder list shows its field type as a small grey badge so you can scan the list at a glance.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:160px">Type</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Text</strong></td><td>Single-line text input. Optional placeholder hint.</td></tr>
        <tr><td><strong>Multi-line Text</strong></td><td>Tall textarea for longer free-form input — model numbers, error messages, paste-in logs.</td></tr>
        <tr><td><strong>Checkbox</strong></td><td>One true/false toggle. The label <em>is</em> the question (<em>"Is the building unlocked?"</em>).</td></tr>
        <tr><td><strong>Dropdown</strong></td><td>Single-select from a list you maintain inside the field's edit dialog. Options are kept as little pills you can add or remove.</td></tr>
        <tr><td><strong>Date</strong></td><td>Single calendar picker.</td></tr>
        <tr><td><strong>Date Range</strong></td><td>Two calendar pickers — <em>from</em> / <em>to</em>. Useful for "when can someone come?" and outage windows.</td></tr>
        <tr><td><strong>Number</strong></td><td>Whole-number input — copy counts, port numbers, room numbers.</td></tr>
        <tr><td><strong>Decimal</strong></td><td>Number input that accepts a decimal point (1.5 hours, $42.99, 0.25 GB).</td></tr>
        <tr><td><strong>Dependent</strong></td><td>A 2- or 3-level cascading dropdown — pick a Region, then the Country dropdown re-populates, then the City dropdown does the same. The hierarchy is entered as an indented list inside the edit dialog (no indent = level 1, one tab or two spaces = level 2, two tabs = level 3). Each level has its own label (<em>"Category"</em>, <em>"Subcategory"</em>, <em>"Item"</em>).</td></tr>
        <tr><td><strong>Text Block</strong></td><td>A read-only block of rich text (headings, bold, colours, lists, links) rendered on the form itself — instructions, contact details, "please don't enter PII here" warnings. Doesn't accept input and doesn't store anything.</td></tr>
        <tr><td><strong>Image</strong></td><td>A read-only image rendered on the form (logo, diagram, sign-here example). Upload a JPEG/PNG/GIF/WebP up to 5 MB; the image is stored under <code>public/uploads/field-images/</code> and shown to users without a download step.</td></tr>
        <tr><td><strong>CC</strong></td><td>"Copy these people too." Search people by name or email (at least two characters); everyone picked is added to the ticket's CC list and gets its email updates.</td></tr>
        <tr><td><strong>Person</strong></td><td>Picks one person from the user directory and stores their account, not typed text. Staff see the person's name (linked) and email on the ticket. External contacts and shared accounts aren't offered, and text typed without picking a match is saved as blank.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mt-3 mb-0"><strong>About requirement and visibility:</strong> Whether a field is Required, Optional, or Hidden is set <em>per ticket type</em> with the visibility pill on each row — see the next section. <em>Text Block</em> and <em>Image</em> are display-only, so their pill is fixed to Optional (they collect no input).</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye-fill text-primary me-2"></i>Field Visibility: Required, Optional, Hidden</h5>
<p class="text-muted mb-2">Every field row carries a coloured <strong>visibility pill</strong>. Click it to cycle the field through three states — for the <em>currently selected ticket type</em> only:</p>
<ul class="text-muted mb-3">
    <li><span class="badge badge-vivid" style="background:#fee2e2;color:#991b1b;">Required</span> &nbsp; The field shows on the form and must be filled in before the ticket can be submitted.</li>
    <li><span class="badge badge-vivid" style="background:#e0e7ff;color:#3730a3;">Optional</span> &nbsp; The field shows on the form but can be left blank.</li>
    <li><span class="badge badge-vivid" style="background:#f1f5f9;color:#475569;">Hidden</span> &nbsp; The field does not appear on that type's form at all. Hidden rows are drawn with a hatched background in the builder so the state is obvious at a glance.</li>
</ul>
<p class="text-muted mb-2">Because visibility is per-type, the same field can be Required on <em>Hardware Issue</em>, Optional on <em>General Enquiry</em>, and Hidden on <em>Lost &amp; Found</em> — no global setting, no juggling separate screens.</p>
<p class="text-muted mb-2"><strong>Subject</strong>, <strong>Description</strong> and <strong>Ticket Type</strong> are locked to Required. The <strong>Priority</strong> system field is a good Hidden candidate for ticket types where the requester shouldn't be picking severity (e.g. a "Key cut request" type where every ticket lands at the same urgency).</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Visibility is enforced on the server, not just in the browser. A Required field still fails closed if its input is stripped, and when <strong>Priority</strong> is Hidden the ticket is created with the default (first) priority so SLA timers, escalation, and list views keep working — agents can still change it afterwards.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="form-conditions">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-signpost-split text-primary me-2"></i>Showing a Field Only When&hellip;</h5>
<p class="text-muted mb-2">Click the signpost icon on a field row to open <strong>Show [field] when&hellip;</strong> and pick a condition for the selected type:</p>
<ul class="text-muted mb-3">
    <li><strong>Always — no condition</strong> (the default).</li>
    <li><strong>The person is signed in to a shared account</strong> &mdash; shown only to logins ticked as <em>Shared account</em> on their user record, such as a service-desk workstation.</li>
    <li><strong>Another field on this form has a particular answer</strong> &mdash; pick the field and a test: <em>is exactly</em>, <em>is not</em>, <em>has any answer</em> or <em>is blank</em>. For a dropdown the value is the option's numeric id; a checkbox is <code>1</code> when ticked and <code>0</code> when not.</li>
</ul>
<p class="text-muted mb-2">The row then shows a small pill summarising the condition. A condition only ever <em>removes</em> a field: a Hidden field stays hidden, and a field whose condition doesn't hold is not shown, not required, and not saved. This is enforced on the server. On the staff New Ticket form, conditions are checked against the staff member filling it in, not the requester.</p>
<p class="text-muted mb-0"><strong>Typical setup:</strong> tick <em>Shared account</em> on the desk login, add a <strong>Person</strong> field to the relevant ticket types, and set it to show only for shared accounts, so whoever is at the desk says who they are.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-left-right text-primary me-2"></i>Adding &amp; Sharing Fields Across Ticket Types</h5>
<p class="text-muted mb-2">Two buttons at the bottom of the canvas add fields to the selected type's form:</p>
<ul class="text-muted mb-3">
    <li><strong>Add field</strong> — creates a brand-new custom field (pick one of the thirteen types) and places it on this type's form.</li>
    <li><strong>Add existing field</strong> — picks a custom field that already exists on another type and shares it onto this one. The field definition (label, options, etc.) is shared; only the visibility and position are per-type.</li>
</ul>
<p class="text-muted mb-2">To manage which types a field appears on, click the <strong>pencil</strong> icon on its row. The edit dialog has an <strong>"Also show this field on"</strong> checkbox list of every ticket type — tick a type to add the field to its form, untick to remove it. You can also set a per-type <strong>label override</strong> here so the same field can read "Device model" on one type and "Equipment ID" on another.</p>
<p class="text-muted mb-2">Two ways to take a field off a form:</p>
<ul class="text-muted mb-3">
    <li><strong>Remove from this type</strong> (the <strong>&times;</strong> on the row) — drops the field from the selected type's form only. The field definition is preserved and tickets that already used it keep their saved values; re-add it any time via <em>Add existing field</em>.</li>
    <li><strong>Delete field entirely</strong> (red button in the edit dialog) — removes the field from <em>every</em> ticket type. Use this only when the field is genuinely retired.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Removing a field from a type — or deleting it entirely — never destroys data already entered on past tickets. Historical values are always retained and still shown on those tickets.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-display text-primary me-2"></i>Live Preview</h5>
<p class="text-muted mb-2">The canvas is a list of admin rows — labels, badges, pills. To see what the form genuinely <em>looks like</em> to a requester, click the <strong>Live Preview</strong> button at the top right of the page. A third column opens alongside the canvas.</p>
<p class="text-muted mb-2">The preview pane iframes the real <code>/portal/tickets/create</code> page for the selected ticket type, in a chrome-less embed mode (no navbar, no sidebar). It is the actual portal renderer, so the input controls, dropdown options, dependent cascades, image fields, and text blocks all render exactly as a requester will see them — there is no separate "preview engine" to drift out of sync.</p>
<p class="text-muted mb-2">Switching ticket types in the left rail swaps the preview to that type's form. The pane header has three controls:</p>
<ul class="text-muted mb-3">
    <li><strong>Reload</strong> (circular arrow) — refresh the preview after a change.</li>
    <li><strong>Open in new tab</strong> (box-arrow icon) — opens the live form in a new tab with the type pre-selected, so you can submit a real test ticket.</li>
    <li><strong>Close</strong> — collapses the preview back to a two-column builder.</li>
</ul>
<p class="text-muted small mb-0">Whether the preview pane is open is remembered in your browser, so it stays open the next time you visit the builder. On narrow screens the preview drops below the canvas rather than beside it.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="direct-links">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-link-45deg text-primary me-2"></i>Direct Links to a Specific Type's Form</h5>
<p class="text-muted mb-2">Sometimes you want to send a patron straight to the right form rather than asking them to pick a ticket type from a dropdown — for example, a "Report a printer problem" link on the staff intranet. The portal supports two query parameters on <code>/portal/tickets/create</code> for exactly this:</p>
<ul class="text-muted mb-3">
    <li><code>?type_id=<em>N</em></code> — the numeric ID of the ticket type. <strong>Stable across renames</strong> and immune to typos. Less human-readable but the recommended form for shareable links.</li>
    <li><code>?type=<em>name</em></code> — the human-readable name of the type, case-insensitive, with hyphens or underscores treated as spaces (so <code>?type=hardware-issue</code> resolves the same as <code>?type=Hardware%20Issue</code>). Nicer to look at, but the link will silently stop pre-selecting if the type is later renamed.</li>
</ul>
<p class="text-muted mb-2">The numeric form wins when both are supplied. Unknown values are ignored silently — a stale link still loads the form, just without the pre-selection.</p>
<p class="text-muted mb-2">There's a built-in shortcut for grabbing the right URL: every row on <a href="/admin/types"><strong>Settings → Organization → Ticket Types</strong></a> has a <strong>Direct Link</strong> column with a read-only path you can click to select, a <strong>copy</strong> button that puts the absolute URL on your clipboard, and an <strong>open in a new tab</strong> button. The copy button shows a green check briefly so you know it worked — paste it into your intranet, an email signature, a poster QR code, anywhere.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    If the person clicking the link <strong>isn't signed in</strong>, they go through the login page first and are bounced straight to the right form afterwards. See <a href="/admin/docs/portal#direct-links"><strong>Portal → Direct Links</strong></a> for how that flow works end-to-end.
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
