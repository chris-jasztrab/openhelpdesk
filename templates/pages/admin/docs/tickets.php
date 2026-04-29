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
        <tr><td><span class="badge" style="background:#7c3aed;">Waiting on Customer</span></td><td class="text-muted">A reply has been sent; waiting for the requester to respond. SLA timer pauses. Can trigger automated reminder emails via Escalation Rules.</td></tr>
        <tr><td><span class="badge" style="background:#0369a1;">Waiting on Third Party</span></td><td class="text-muted">Work is blocked pending an external vendor or team. SLA timer pauses while in this state.</td></tr>
        <tr><td><span class="badge bg-success">Resolved</span></td><td class="text-muted">The issue has been addressed. The requester is notified.</td></tr>
        <tr><td><span class="badge bg-secondary">Closed</span></td><td class="text-muted">Fully closed. No further action expected.</td></tr>
    </tbody>
</table>
</div>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-2"></i>
    When a ticket moves to <strong>Waiting on Customer</strong> or <strong>Waiting on Third Party</strong>, the SLA timer automatically pauses and resumes when the status changes to any other active status.
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
<p class="text-muted mb-2">The action buttons near the bottom of a ticket open the reply panel:</p>
<ul class="text-muted mb-3">
    <li><strong>Reply</strong> — opens the reply panel in public mode. The message is visible to the requester and triggers an email notification (if SMTP is configured).</li>
    <li><strong>Forward</strong> — opens the reply panel to forward ticket details to another party.</li>
    <li><strong>Add Note</strong> — opens the reply panel in internal-note mode (highlighted background). The note is visible only to agents and admins and is never sent to the requester.</li>
</ul>
<p class="text-muted mb-2">The <strong>Send</strong> button has a dropdown arrow on the right. Clicking the arrow lets you <strong>Send &amp; set status</strong> in a single action — for example, "Send &amp; Set as Resolved" closes the ticket immediately when the reply is posted. Available status options: Resolved, Closed, Pending, Waiting on Customer, Waiting on Third Party.</p>
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
    When a ticket is assigned to a <strong>Group</strong>, the agent picker (both in the ticket view and the quick-assign dropdown) is automatically filtered to show only members of that group. To assign outside the group, first clear the Group field or change it.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightning text-primary me-2"></i>Inline Ticket List Actions</h5>
<p class="text-muted mb-2">Agents and admins can update key ticket fields directly from the ticket list without opening the ticket. Each row shows chevron icons (<i class="bi bi-chevron-down"></i>) in the <strong>Agent</strong>, <strong>Type</strong>, and <strong>Group</strong> columns. Hovering a cell reveals the chevron; clicking it opens a small dropdown to change that field immediately.</p>
<ul class="text-muted mb-3">
    <li><strong>Agent column</strong> — quick-assign to any agent (filtered to the ticket's group if one is set).</li>
    <li><strong>Type column</strong> — change the ticket type in one click.</li>
    <li><strong>Group column</strong> — reassign to a different group.</li>
</ul>
<p class="text-muted mb-0">The same inline actions are available in the <strong>Recent Tickets</strong> widget on the agent dashboard. A column picker on the dashboard widget lets you choose which columns are displayed.</p>
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
<p class="text-muted mb-0">File attachments are displayed <strong>inline</strong> within the timeline entry they were uploaded with, so agents can preview images and download files without leaving the ticket view.</p>
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
<p class="text-muted mb-2">Templates let you pre-fill a ticket's subject, description, type, and priority to speed up common requests. Managed at <a href="/admin/ticket-templates"><strong>Admin → Ticket Templates</strong></a>.</p>
<ul class="text-muted mb-3">
    <li>Admins can create, edit, and delete any template. Agents can manage their own templates.</li>
    <li>Mark a template as <strong>Shared</strong> to make it available on the portal ticket creation form — users see a "Start from a Template" picker.</li>
    <li>When a template is selected, the subject, body, type, and priority fields are auto-filled immediately.</li>
</ul>
<p class="text-muted mb-0">Staff (agents and admins) can also select templates when creating tickets from the admin interface at <a href="/admin/tickets/create"><strong>Admin → Tickets → Create Ticket</strong></a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ui-checks-grid text-primary me-2"></i>Custom Form Fields (Workflows)</h5>
<p class="text-muted mb-2">LocalDesk supports custom fields on the ticket creation and detail forms. Build and manage fields at <a href="/admin/workflows/ticket-fields"><strong>Admin → Settings → Custom Fields</strong></a>.</p>
<p class="text-muted mb-2">Supported field types:</p>
<ul class="text-muted mb-0">
    <li><strong>Short Text</strong> — single-line text input.</li>
    <li><strong>Long Text</strong> — multi-line textarea.</li>
    <li><strong>Dropdown</strong> — single-select from a defined list of options.</li>
    <li><strong>Multi-Select</strong> — choose multiple values from a list.</li>
    <li><strong>Checkbox</strong> — a true/false toggle.</li>
</ul>
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
