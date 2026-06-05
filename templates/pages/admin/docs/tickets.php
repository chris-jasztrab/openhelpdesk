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
<p class="text-muted mb-2">Tickets can be created in two ways:</p>
<ul class="text-muted mb-3">
    <li><strong>Portal:</strong> End users submit tickets through the public portal at <code>/portal</code>. They provide a subject, description, location (if enabled) and optionally attach files.</li>
    <li><strong>Admin / Agent:</strong> Staff can create tickets from <a href="/admin/tickets/create"><strong>Admin → Tickets → Create Ticket</strong></a> or <a href="/agent/tickets/create"><strong>Agent → Tickets → Create Ticket</strong></a>. Provide a subject, description and (optionally) a ticket type, priority, location, group, assignee, due date and tags.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    When a ticket is created, a confirmation email is sent to the requester (if SMTP is configured) and, if assigned, a notification is sent to the agent.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="on-behalf-of">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-telephone-forward text-primary me-2"></i>Filing a Ticket On Behalf Of Someone Else</h5>
<p class="text-muted mb-2">When a staff member phones the helpdesk — or stops you in the hall — you can file the ticket <em>for them</em> from the staff <em>Create Ticket</em> page so the right person owns it from the start. The picker is shown to admins, agents and power users on both <a href="/admin/tickets/create"><strong>Admin → Tickets → Create Ticket</strong></a> and <a href="/agent/tickets/create"><strong>Agent → Tickets → Create Ticket</strong></a>.</p>

<p class="text-muted mb-2"><strong>How to use it</strong></p>
<ol class="text-muted mb-3">
    <li>Open the <em>Create Ticket</em> page.</li>
    <li>Scroll to the <em>Assignment</em> card and find the <strong>On Behalf Of</strong> field.</li>
    <li>Type at least two characters of the caller's name or email — matching staff appear in a dropdown. Click to pick.</li>
    <li>The picker swaps for a coloured pill showing the chosen requester. Use <strong>Clear</strong> to undo.</li>
    <li>Fill the rest of the form as normal and submit.</li>
</ol>

<p class="text-muted mb-2"><strong>What happens behind the scenes</strong></p>
<ul class="text-muted mb-3">
    <li>The picked person becomes the ticket's <strong>requester</strong> (<code>created_by</code>). They own it: it shows up in <em>their</em> portal, the confirmation email goes to <em>their</em> inbox, CSAT is sent to <em>them</em>, and they can reply or close it like any self-submitted ticket.</li>
    <li>The staff member who clicked <em>Create</em> is recorded in a separate <strong>submitter</strong> column (<code>submitted_by</code>) so the audit trail isn't lost. The ticket-detail sidebar shows a small <em>"Filed by [staff] on their behalf"</em> hint under <em>Created By</em>, and the timeline's first <em>Created</em> entry reads <em>"Ticket filed by [staff] on behalf of [requester]."</em></li>
    <li>If you leave the picker blank, the ticket is created as you would any other — you are both the submitter and the requester, exactly like before.</li>
    <li>Picking yourself is treated the same as leaving the picker blank — no spurious "filed on behalf" hint.</li>
</ul>

<p class="text-muted mb-2"><strong>The requester must already have a user account.</strong> If the caller is new, create their account first at <a href="/admin/users/create"><strong>Admin → Settings → Users &amp; Access → Users → Add User</strong></a>, then come back to file the ticket.</p>

<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    The submitter pill is only visible to agents and admins. End users in the portal see the ticket as theirs, with no reference to who filed it for them.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="ticket-statuses">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>Ticket Statuses</h5>
<p class="text-muted mb-2">Each ticket moves through a lifecycle managed by statuses. Statuses are <strong>fully configurable</strong> &mdash; add, remove, rename, recolour, and reorder them from <a href="/admin/settings/ticket-statuses"><strong>Settings &rarr; Ticket Statuses</strong></a>. The seven statuses installed by default are:</p>
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
    <li><strong>Default for new tickets</strong> &mdash; what every newly-submitted ticket lands on. Must be in the <em>open</em> bucket.</li>
    <li><strong>Default for resolved</strong> &mdash; the status that triggers the "ticket resolved" email + CSAT survey. Must be in the <em>closed</em> bucket.</li>
    <li><strong>Default for closed</strong> &mdash; the status that triggers the "ticket closed" email. Must be in the <em>closed</em> bucket.</li>
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

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-flag text-primary me-2"></i>Priorities</h5>
<p class="text-muted mb-2">Priorities determine urgency and drive SLA timers. Default priorities are created during installation (Low, Medium, High, Critical). You can rename, recolour and adjust them at <a href="/admin/priorities"><strong>Admin → Settings → Priorities</strong></a>.</p>
<p class="text-muted mb-0">Each priority can be linked to an SLA policy so that response and resolution deadlines are automatically applied when a ticket is created.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-dots text-primary me-2"></i>Replies &amp; Internal Notes</h5>
<p class="text-muted mb-2">The action buttons near the bottom of a ticket open the reply panel:</p>
<ul class="text-muted mb-3">
    <li><strong>Reply</strong> — opens the reply panel in public mode. The message is visible to the requester and triggers an email notification (if SMTP is configured).</li>
    <li><strong>Forward</strong> — opens the reply panel to forward ticket details to another party.</li>
    <li><strong>Add Note</strong> — opens the reply panel in internal-note mode (highlighted background). The note is visible only to agents and admins and is never sent to the requester.</li>
</ul>
<p class="text-muted mb-2">The <strong>Send</strong> button has a dropdown arrow on the right. Clicking the arrow lets you <strong>Send &amp; set status</strong> in a single action — for example, "Send &amp; Set as Resolved" closes the ticket immediately when the reply is posted. The dropdown lists every active status other than the ticket's current one, so the menu always reflects whatever statuses you've configured in <a href="/admin/settings/ticket-statuses">Settings &rarr; Ticket Statuses</a>.</p>
<p class="text-muted mb-0">Both replies and notes support file attachments. Attachments are stored in <code>storage/attachments/</code> and served securely through the application.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-check text-primary me-2"></i>Assigning Tickets</h5>
<p class="text-muted mb-2">Tickets can be assigned to any agent or admin. Assignment can happen:</p>
<ul class="text-muted mb-3">
    <li>At creation time (by an admin or agent creating the ticket).</li>
    <li>From the ticket view — use the <strong>Assigned To</strong> dropdown in the details panel on the right.</li>
    <li>From the ticket list — click the <strong>agent chevron <i class="bi bi-chevron-down"></i></strong> in the Agent column to open a quick-assign dropdown without leaving the list.</li>
    <li>Via automations (see the <a href="/admin/docs/automations">Automations</a> doc).</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    When a ticket is assigned to a <strong>Group</strong>, the agent picker (both in the ticket view and the quick-assign dropdown) is automatically filtered to show only members of that group. In the ticket's <strong>Update Ticket</strong> panel this now happens <strong>live</strong> — change the <strong>Group</strong> dropdown and the <strong>Assigned To</strong> list immediately re-filters to that group's members (no need to save the group change first); pick <em>None</em> to choose from all staff. The current assignee is kept selected if they belong to the newly chosen group, otherwise it resets to Unassigned.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightning text-primary me-2"></i>Inline Ticket List Actions</h5>
<p class="text-muted mb-2">Agents and admins can update key ticket fields directly from the ticket list without opening the ticket. Hovering a cell in one of these columns reveals a chevron (<i class="bi bi-chevron-down"></i>); clicking it opens a small dropdown to change that field immediately, and the badge or cell updates in place.</p>
<ul class="text-muted mb-3">
    <li><strong>Status column</strong> — click the status badge to set a new status. Only <em>active</em> statuses are offered, and the change runs the same logic as the ticket detail view: timeline entry, requester notification for closed-bucket statuses, CSAT survey trigger, SLA pause/resume, and <code>ticket_updated</code> automations.</li>
    <li><strong>Priority column</strong> — click the priority badge to pick a new priority or clear it. Mirrors the ticket-detail priority logic — timeline entry, SLA recalculation, and <code>ticket_updated</code> automations.</li>
    <li><strong>Agent column</strong> — quick-assign to any agent (filtered to the ticket's group if one is set).</li>
    <li><strong>Type column</strong> — change the ticket type in one click. The whole cell is clickable, not just the chevron.</li>
    <li><strong>Group column</strong> — reassign to a different group.</li>
</ul>
<p class="text-muted mb-0">The same inline actions are available in the <strong>Recent Tickets</strong> widget on the agent dashboard. A column picker on the dashboard widget lets you choose which columns are displayed.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="list-layouts">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-layout-text-sidebar text-primary me-2"></i>Ticket List Layouts (Table / Compact / Card)</h5>
<p class="text-muted mb-2">Each staff member can choose how their ticket lists are laid out under <strong>My Profile &rarr; Ticket List View</strong>. The setting is per-user and applies to both the admin and agent ticket lists. Filtering, sorting, bulk-select and the bulk-action bar behave identically in all three.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:120px">Layout</th><th>What it looks like</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong><i class="bi bi-table me-1"></i>Table</strong></td><td>The classic resizable grid with a configurable set of columns. The default. The <strong>Confidential</strong> column is hidden by default and can be re-enabled from the column picker.</td></tr>
        <tr><td><strong><i class="bi bi-inbox me-1"></i>Compact</strong></td><td>An email-style two-column list showing only <strong>From</strong> and <strong>Subject</strong>. Hovering the <strong>Subject</strong> opens a detail card (requester, submitted time, message snippet, status/priority/type/assignee) with <strong>Reply</strong>, <strong>Forward</strong> and <strong>Add Note</strong> buttons that deep-link into the ticket composer. Hovering the <strong>From</strong> name opens a person card with their email and an <strong>Open tickets &amp; mentions</strong> button.</td></tr>
        <tr><td><strong><i class="bi bi-grid-1x2 me-1"></i>Card</strong></td><td>A roomier stacked layout where each ticket is a horizontal card &mdash; colour-keyed requester avatar, a &ldquo;New&rdquo; flag for tickets awaiting a first reply, type badge and subject, the location/group, age and SLA, and priority/assignee/status down the right edge.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-0 mt-2"><strong>Open tickets &amp; mentions page.</strong> The sender card&rsquo;s <strong>Open tickets &amp; mentions</strong> button opens a per-user page listing every open ticket that person submitted plus any open ticket where they were <strong>@mentioned</strong> (mentions are badged). The page is scoped to what the viewing staff member is permitted to see, and confidential tickets stay redacted in every layout.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="reordering-admin-lists">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grip-vertical text-primary me-2"></i>Reordering &amp; Sorting Admin Lists</h5>
<p class="text-muted mb-2">The order that <strong>Ticket Types</strong>, <strong>Groups</strong>, <strong>Priorities</strong>, <strong>Skills</strong>, <strong>Automations</strong>, <strong>Canned Responses</strong>, <strong>Escalation Rules</strong>, <strong>KB Categories</strong>, and <strong>KB Folders</strong> appear in &mdash; both on their settings tables and in every dropdown where those values surface on the agent and portal sides &mdash; can be set directly from each list.</p>
<p class="text-muted mb-2"><strong>Drag to reorder.</strong> Every row in those lists has a grip handle <i class="bi bi-grip-vertical"></i> on the left. Drag a row up or down and the new order saves immediately &mdash; no edit form, no integer to juggle.</p>
<p class="text-muted mb-2"><strong>Click a header to sort.</strong> Any sortable column header (Name, Members, Created, etc.) is clickable. Clicking re-sorts the visible rows ascending or descending. Because this overwrites your existing custom order, a yellow toolbar with <strong>Save Order</strong> and <strong>Revert</strong> buttons appears above the table &mdash; the sort is only committed once you click <strong>Save Order</strong>, so a stray click on a header never destroys a curated arrangement.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    The old raw "Sort Order" integer column has been retired from every list &mdash; the row position now communicates it directly. Existing custom orders are preserved.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="resizing-columns">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrows text-primary me-2"></i>Resizing Columns</h5>
<p class="text-muted mb-2">Every column on every list in the app can be widened or narrowed by dragging its right edge. Hover the line between two column headers, the cursor turns into a resize grip, and you can drag to set the width.</p>
<ul class="text-muted mb-0">
    <li>Chosen widths are remembered per page in your browser &mdash; lists come back the way each person leaves them.</li>
    <li>Widening past the card edge scrolls the table horizontally rather than overflowing.</li>
    <li>Works on the ticket lists, user list, KB lists, reports, and every settings table. A handful of tables with merged/multi-row headers are skipped (a single column width can't represent a merged header).</li>
    <li>On the ticket lists, a column you have hand-sized is not touched by the existing auto-fit when an inline quick-change is made &mdash; only un-resized columns are re-measured.</li>
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
<p class="text-muted mb-2">To stop duplicates from being filed in the first place, see <a href="/admin/docs/ai#duplicate-detection">AI duplicate-ticket detection</a> — an optional check that warns the submitter when their new ticket looks like one that already exists.</p>
<ol class="text-muted mb-3">
    <li>Open the ticket you want to keep as the <strong>primary</strong>.</li>
    <li>Click <strong>Merge</strong> in the ticket actions panel.</li>
    <li>Enter the ID of the duplicate ticket to merge in.</li>
    <li>Confirm the merge — the duplicate is marked closed and a merge event is recorded in the primary ticket's timeline.</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    <strong>Priority escalation:</strong> When tickets are merged, the primary ticket's priority is automatically updated to the highest priority among all tickets being merged. For example, if the primary ticket is Low priority and a merged ticket is High priority, the primary ticket will be escalated to High. A timeline event is recorded if the priority changes.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Ticket Timeline</h5>
<p class="text-muted mb-2">Every ticket has a full audit trail in the timeline tab, showing all events in chronological order:</p>
<ul class="text-muted mb-3">
    <li><strong>Replies</strong> — customer-visible messages (white background).</li>
    <li><strong>Internal Notes</strong> — private agent notes (highlighted with your configured note colour). Never visible to portal users.</li>
    <li><strong>System Events</strong> — status changes, priority changes, assignments, SLA updates, merges (highlighted with your configured system colour). Visible to agents and admins only — portal users do not see system events.</li>
</ul>
<p class="text-muted mb-2">File attachments are displayed <strong>inline</strong> within the timeline entry they were uploaded with, so agents can preview images and download files without leaving the ticket view.</p>
<h6 class="fw-semibold mt-3">Hiding system &amp; AI notes</h6>
<p class="text-muted mb-2">A busy timeline can fill with automated entries — SLA timer events, escalation reminders, automatic group assignment, AI classifications. Admins get two switches in the <strong>Timeline</strong> card header to declutter the view:</p>
<ul class="text-muted mb-2">
    <li><strong>System notes</strong> — shows or hides <em>every</em> automated, human-author-less entry.</li>
    <li><strong>AI notes</strong> — shows or hides just the AI-generated entries (group classification, skill suggestions). This switch only appears when the ticket actually has AI notes. AI notes are a subset of system notes, so turning <em>System notes</em> off also hides AI notes.</li>
</ul>
<p class="text-muted mb-0">Flipping a switch takes effect instantly and is remembered for your account across every ticket and session — it sets the same per-user default you can also change at <strong>My Profile → System Timeline Notes / AI Timeline Notes</strong>. The notes are only hidden from view, never deleted, and the switches are admin-only; agents and portal users are unaffected. Defaults to showing everything.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="solution">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check-circle text-success me-2"></i>Marking a Comment as the Solution</h5>
<p class="text-muted mb-2">On a long ticket the answer often ends up buried halfway down the timeline. Agents and admins can flag the comment that resolved the issue so everyone can jump straight to it.</p>
<ul class="text-muted mb-2">
    <li>Every customer-visible reply in the timeline shows a small <strong>Mark as solution</strong> button next to its timestamp. Click it to flag that comment.</li>
    <li>Once a comment is marked, a green alert with a <strong>Go to solution</strong> button appears at the top of the ticket — on the agent, admin, <em>and</em> the requester's portal view. It scrolls to the marked comment, which gets a green left border and a <strong>Solution</strong> badge.</li>
    <li>A ticket has one solution at a time. Marking a different comment replaces the previous one; use <strong>Unmark</strong> to clear it.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Internal notes can't be marked — the requester can't see them, so a "Go to solution" link would point at nothing on the portal. Marking a solution does <em>not</em> change the ticket status: you can flag a candidate fix while a ticket is still <em>Waiting on Customer</em> and resolve it separately. On the portal the button is labelled <strong>Answer</strong> by default — rename it under <a href="/admin/settings/labels">Settings → Application name &amp; labels</a>.
</div>
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


<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-square text-primary me-2"></i>Bulk Actions</h5>
<p class="text-muted mb-2">The ticket list includes a checkbox column to select multiple tickets at once. When one or more tickets are selected, a bulk action bar appears at the bottom of the screen.</p>
<p class="text-muted mb-2">Available bulk actions:</p>
<ul class="text-muted mb-0">
    <li><strong>Assign</strong> — open an agent picker and assign all selected tickets to one agent.</li>
    <li><strong>Close</strong> — set all selected tickets to Closed status.</li>
    <li><strong>Merge</strong> — merge all selected tickets; the lowest-numbered ticket becomes the primary. The primary's priority is automatically escalated to the highest priority among all tickets being merged.</li>
    <li><strong>Delete</strong> — permanently delete selected tickets (admin only).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Filter Panel &amp; Saved Filters</h5>
<p class="text-muted mb-2">Ticket list filters are in a slide-out panel on the right side of the screen. Click the <strong>Filters</strong> button to open it. The panel state (open or closed) is remembered across page navigations.</p>
<p class="text-muted mb-2">Ticking a checkbox or picking a date <strong>applies the filter immediately</strong> &mdash; the list refreshes on click, with no need to press Apply. (The <strong>Search</strong> box is the exception: type your text and press Enter or click Apply.) Every active filter shows as a labelled pill at the top of the panel &mdash; e.g. <span class="badge rounded-pill bg-light text-dark border">Status: Open</span> or <span class="badge rounded-pill bg-light text-dark border">Requester: Jane Doe</span>. Click the <strong>&times;</strong> on a pill to remove just that filter, or <strong>Clear All</strong> to remove them all.</p>
<p class="text-muted mb-2">You can save any combination of active filters as a named preset:</p>
<ol class="text-muted mb-3">
    <li>Apply the filters you want, then click <strong>Save Filter</strong> in the panel.</li>
    <li>Give the preset a name and optionally share it with the rest of the team.</li>
    <li>Saved filters appear in the panel and can be applied with a single click.</li>
    <li>Mark any saved filter as your <strong>Default</strong> — it will be applied automatically when you open the ticket list.</li>
</ol>
<p class="text-muted mb-0">When you navigate from a ticket detail view back to the list, the previous URL (including all active filters and sort order) is automatically restored via the breadcrumb link.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-file-earmark-text text-primary me-2"></i>Ticket Templates</h5>
<p class="text-muted mb-2">Templates let you pre-fill a ticket's subject, description, type, and priority to speed up common requests. Managed at <a href="/admin/ticket-templates"><strong>Admin → Settings → Ticket Templates</strong></a>.</p>
<ul class="text-muted mb-3">
    <li>Admins can create, edit, and delete any template. Agents can manage their own templates.</li>
    <li>Mark a template as <strong>Shared</strong> to make it available on the portal ticket creation form — users see a "Start from a Template" picker.</li>
    <li>When a template is selected, the subject, body, type, and priority fields are auto-filled immediately.</li>
</ul>
<p class="text-muted mb-0">Staff (agents and admins) can also select templates when creating tickets from the admin interface at <a href="/admin/tickets/create"><strong>Admin → Tickets → Create Ticket</strong></a>.</p>
</div>
</div>

<h3 id="form-builder" class="fw-bold mt-5 mb-3">The Ticket Form Builder</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ui-checks-grid text-primary me-2"></i>What the Form Builder is for</h5>
<p class="text-muted mb-2">The <strong>Form Builder</strong> at <a href="/admin/workflows/ticket-fields"><strong>Admin → Settings → Ticket Forms</strong></a> controls everything that appears on the <em>New Ticket</em> form — both on the public portal and inside the admin/agent "Create Ticket" view. Anything you change here takes effect immediately for the next person who opens the form. There is no "publish" step.</p>
<p class="text-muted mb-2"><strong>Every ticket type has its own form.</strong> The page is laid out as a left rail of ticket types and a canvas:</p>
<ul class="text-muted mb-3">
    <li><strong>Type rail (left)</strong> — pick the ticket type whose form you want to edit. The canvas swaps to that type's layout.</li>
    <li><strong>Canvas (middle)</strong> — the fields on the selected type's form, in the order requesters see them.</li>
    <li><strong>Live Preview (right)</strong> — an optional pane (see below) showing the real portal form for that type.</li>
</ul>
<p class="text-muted mb-2">The canvas shows three kinds of rows:</p>
<ul class="text-muted mb-3">
    <li><strong>Pinned Fields</strong> — <em>Subject</em> and <em>Description</em>. Always at the very top of every type's form, always Required, and can't be moved or removed. Their labels can be renamed (pencil icon).</li>
    <li><strong>System Fields</strong> — <em>Ticket Type</em>, <em>Location</em>, <em>Priority</em>, <em>Tags</em>, and <em>Attachments</em>. Built-in fields the application needs in order to function. You can rename them, drag them to reorder, and change their visibility (Required / Optional / Hidden) per type. They can't be deleted.</li>
    <li><strong>Custom Fields</strong> — anything you add via <strong>Add field</strong>. Twelve field types are supported (see the table below). Custom fields can be edited, reordered, given a visibility per type, shared onto other types, removed from a type, or deleted entirely.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Drag, reorder and visibility changes apply to the <em>currently selected ticket type only</em>. The same custom field can be Required on one type, Optional on another, and Hidden on a third — each type carries its own authoritative layout.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-ul text-primary me-2"></i>The 12 Custom Field Types</h5>
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
        <tr><td><strong>Dependent</strong></td><td>A 2- or 3-level cascading dropdown — pick a Region, then the Country dropdown re-populates, then the City dropdown does the same. The hierarchy is entered as an indented list (one tab per level) inside the edit dialog, with a "Preview hierarchy" button so you can sanity-check the tree before saving. Each level has its own label (<em>"Category"</em>, <em>"Subcategory"</em>, <em>"Item"</em>).</td></tr>
        <tr><td><strong>Text Block</strong></td><td>A read-only paragraph rendered on the form itself — instructions, contact details, "please don't enter PII here" warnings. Doesn't accept input and doesn't store anything.</td></tr>
        <tr><td><strong>Image</strong></td><td>A read-only image rendered on the form (logo, diagram, sign-here example). Upload a JPEG/PNG/GIF/WebP up to 5 MB; the image is stored under <code>public/uploads/field-images/</code> and shown to users without a download step.</td></tr>
        <tr><td><strong>CC</strong></td><td>An "email me a copy" / "copy these people too" field. Adds the listed addresses to the requester acknowledgement and any future agent replies on the ticket.</td></tr>
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
<p class="text-muted mb-2"><strong>Subject</strong> and <strong>Description</strong> are pinned and locked to Required. The <strong>Priority</strong> system field is a good Hidden candidate for ticket types where the requester shouldn't be picking severity (e.g. a "Key cut request" type where every ticket lands at the same urgency).</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Visibility is enforced on the server, not just in the browser. A Required field still fails closed if its input is stripped, and when <strong>Priority</strong> is Hidden the ticket is created with the system default priority so SLA timers, escalation, and list views keep working — agents can still change it afterwards.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-left-right text-primary me-2"></i>Adding &amp; Sharing Fields Across Ticket Types</h5>
<p class="text-muted mb-2">Two buttons at the bottom of the canvas add fields to the selected type's form:</p>
<ul class="text-muted mb-3">
    <li><strong>Add field</strong> — creates a brand-new custom field (pick one of the twelve types) and places it on this type's form.</li>
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

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-link-45deg text-primary me-2"></i>Direct Links to a Specific Type's Form</h5>
<p class="text-muted mb-2">Sometimes you want to send a patron straight to the right form rather than asking them to pick a ticket type from a dropdown — for example, a "Report a printer problem" link on the staff intranet. The portal supports two query parameters on <code>/portal/tickets/create</code> for exactly this:</p>
<ul class="text-muted mb-3">
    <li><code>?type_id=<em>N</em></code> — the numeric ID of the ticket type. <strong>Stable across renames</strong> and immune to typos. Less human-readable but the recommended form for shareable links.</li>
    <li><code>?type=<em>name</em></code> — the human-readable name of the type, case-insensitive, with hyphens or underscores treated as spaces (so <code>?type=hardware-issue</code> resolves the same as <code>?type=Hardware%20Issue</code>). Nicer to look at, but the link will silently stop pre-selecting if the type is later renamed.</li>
</ul>
<p class="text-muted mb-2">The numeric form wins when both are supplied. Unknown values are ignored silently — a stale link still loads the form, just without the pre-selection.</p>
<p class="text-muted mb-2">There's a built-in shortcut for grabbing the right URL: every row on <a href="/admin/types"><strong>Admin → Settings → Ticket Types</strong></a> now has a <strong>Direct Link</strong> column with a read-only path you can click to select, a <strong>copy</strong> button that puts the absolute URL on your clipboard, and an <strong>open in a new tab</strong> button. The copy button shows a green check briefly so you know it worked — paste it into your intranet, an email signature, a poster QR code, anywhere.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    If the person clicking the link <strong>isn't signed in</strong>, they go through the login page first and are bounced straight to the right form afterwards. See <a href="/admin/docs/portal#direct-links"><strong>Portal → Direct Links</strong></a> for how that flow works end-to-end.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-download text-primary me-2"></i>Exporting Tickets (CSV)</h5>
<p class="text-muted mb-2">Export the current filtered ticket list to CSV from the ticket list page. The export respects all active filters (status, priority, type, location, agent, group, search query, date range, requester).</p>
<p class="text-muted mb-2">Exported columns include: ID, subject, status, priority, type, location, group, assigned agent, creator, tags, created date, due date, and SLA state. The file is UTF-8 encoded with a BOM for seamless Excel compatibility.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-up-right-circle text-danger me-2"></i>Escalating a Ticket</h5>
<p class="text-muted mb-2">A red <strong>Escalate</strong> button on the ticket view reassigns the ticket to the next agent in its ticket type's escalation path (the previous assignee stays on as a watcher). The button is hidden for ticket types that don't have a path configured.</p>
<ul class="text-muted mb-3">
    <li><strong>Where to configure</strong> — <a href="/admin/settings/escalation-paths"><strong>Admin → Settings → Escalation Paths</strong></a>. Each ticket type has its own ordered chain.</li>
    <li><strong>Who can escalate</strong> — agents, power users, and admins on any ticket they can access; portal users on their own tickets.</li>
    <li><strong>Skip-current-assignee logic</strong> — if the current assignee already appears in the path (they <em>are</em> Tier 2), Escalate jumps to the step after them rather than re-routing to an earlier level.</li>
    <li><strong>Tracking</strong> — escalated tickets show on the agent dashboard's "Escalated to Me" card and on a dedicated ticket-list filter.</li>
</ul>
<p class="text-muted mb-0">See the <a href="/admin/docs/automations#escalation-paths">Automations doc</a> for full configuration guidance and how this differs from the time-based <a href="/admin/docs/automations#escalation-rules">Escalation Rules</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye-slash text-primary me-2"></i>Hiding a Type from Location-Visibility Users</h5>
<p class="text-muted mb-2">Each ticket type has a <strong>Visible to Location Ticket Visibility users</strong> checkbox. By default it's on, matching pre-2.x behaviour. Unchecking it hides every ticket of that type from portal users who have the <a href="/admin/docs/users">Location Ticket Visibility</a> permission, while still letting agents and admins work on the queue normally.</p>
<p class="text-muted mb-2">This is the lighter alternative to <strong>Confidential</strong> ticket types (described below). Use it for routine-but-sensitive types like Collections, HR, or Payroll where you simply don't want supervisors / branch leads to see the contents — without invoking the heavier confidential flow (group lock, re-authentication, audit log, email alerts).</p>
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

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hourglass-split text-warning me-2"></i>Per-Type Stale Threshold</h5>
<p class="text-muted mb-0">Each ticket type can override the global stale-notification threshold. Leave the override blank to inherit the value set under <a href="/admin/settings/stale-tickets">Stale Tickets</a>; set a number to use that instead for tickets of this type. Useful when, say, "Critical Outage" tickets should nag after 4 hours but "Suggestion Box" tickets shouldn't nag at all (set to a high value or disable globally). See the <a href="/admin/docs/automations#stale-tickets">Stale Ticket Notifications</a> section for the full picture.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Confidential Ticket Types</h5>
<p class="text-muted mb-2">Ticket types can be marked as <strong>Confidential</strong> to restrict access to sensitive tickets (e.g., HR, Legal). This feature ensures that only authorised group members can view the ticket details.</p>

<h6 class="fw-semibold mt-3">How to Enable</h6>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/types"><strong>Admin &rarr; Settings &rarr; Ticket Types</strong></a>.</li>
    <li>Edit or create a ticket type.</li>
    <li>Select a <strong>Default Group</strong> (required for confidentiality).</li>
    <li>Check the <strong>Confidential</strong> checkbox and save.</li>
</ol>

<h6 class="fw-semibold mt-3">What Happens</h6>
<ul class="text-muted mb-3">
    <li><strong>Group members</strong> can see and work on confidential tickets normally.</li>
    <li><strong>Agents not in the group</strong> cannot see confidential tickets at all &mdash; they are completely hidden from ticket lists and search results.</li>
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
    <li>Every admin access to a confidential ticket is recorded in the <strong>Audit Log</strong> with action <code>confidential_ticket_viewed</code>.</li>
    <li>A timeline entry is added to the ticket itself (internal only).</li>
    <li>All members of the assigned group receive an email notification containing the admin's name, email, IP address, and timestamp.</li>
</ul>

<h6 class="fw-semibold mt-3">Restrictions</h6>
<ul class="text-muted mb-0">
    <li>Confidential tickets are excluded from the <strong>REST API</strong> for non-group members. A 403 response is returned with a message to use the web interface.</li>
    <li><strong>Bulk actions</strong> automatically exclude confidential tickets the user cannot access.</li>
    <li><strong>Merge and Split</strong> operations are blocked if either ticket is confidential and the user is not in the group.</li>
    <li><strong>CSV exports</strong> replace the subject and identifying fields with "[Confidential]" for tickets the admin is not authorised to view.</li>
    <li><strong>Search/typeahead</strong> results redact confidential ticket subjects.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye text-primary me-2"></i>Concurrent Viewer Warning</h5>
<p class="text-muted mb-2">When two or more agents open the same ticket at the same time, a dismissible warning banner is shown to alert them that someone else is also viewing the ticket. This helps avoid duplicate work or conflicting replies.</p>
<p class="text-muted mb-0">Presence is tracked per ticket and automatically cleared when a user navigates away.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-ol text-primary me-2"></i>Per-Page Row Count</h5>
<p class="text-muted mb-0">Use the <strong>per page</strong> selector at the bottom-right of the ticket list to control how many tickets are shown per page. Options are 25, 50, 100, or 200. Your selection is applied immediately and included in any export or saved filter URL.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
