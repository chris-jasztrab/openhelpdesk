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

<h3 id="form-builder" class="fw-bold mt-5 mb-3">The Ticket Form Builder</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ui-checks-grid text-primary me-2"></i>What the Form Builder is for</h5>
<p class="text-muted mb-2">The <strong>Ticket Form Builder</strong> at <a href="/admin/workflows/ticket-fields"><strong>Admin → Settings → Custom Fields</strong></a> controls everything that appears on the <em>New Ticket</em> form — both on the public portal and inside the admin/agent "Create Ticket" view. Anything you change here takes effect immediately for the next person who opens the form. There is no "publish" step.</p>
<p class="text-muted mb-2">The page shows three kinds of rows in a single, drag-to-reorder list:</p>
<ul class="text-muted mb-3">
    <li><strong>Pinned Fields</strong> — <em>Subject</em> and <em>Description</em>. Always at the very top of the form. Their labels can be renamed (pencil icon), but they can't be moved or removed.</li>
    <li><strong>System Fields</strong> — <em>Ticket Type</em>, <em>Location</em>, <em>Priority</em>, <em>Tags</em>, and <em>Attachments</em>. Built-in fields the application needs in order to function. You can rename them, toggle <em>required</em> on the ones that support it, and drag them up or down relative to the custom fields. They can't be deleted.</li>
    <li><strong>Custom Fields</strong> — anything you add via the <strong>+ Add Custom Field</strong> dropdown. Twelve field types are supported (see the table below). Custom fields can be edited, deleted, reordered, marked required, hidden from portal users, and scoped to specific ticket types.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    The order of rows on this page <em>is</em> the order users see on the form. Pinned fields are locked at the top; everything else flows in whatever order you arrange. There is no separate "form layout" screen.
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
<p class="text-muted small mt-3 mb-0"><strong>About requirement and visibility:</strong> Most types let you mark them <em>Required</em> (red badge) and let you <em>Hide from portal users</em> (eye-slash icon). Hidden fields are still shown on the admin <em>Create Ticket</em> view — useful for admin-only metadata you don't want patrons filling in. <em>Text Block</em> and <em>Image</em> are display-only, so they don't have these toggles.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-tag-fill text-primary me-2"></i>Field Scope: Global vs Specific to Ticket Type</h5>
<p class="text-muted mb-2">Every custom field has a <strong>scope</strong> that decides which ticket types' forms it appears on. Scope is shown as a coloured pill in the field's row on the builder list:</p>
<ul class="text-muted mb-3">
    <li><span class="scope-pill scope-global" style="display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;padding:.2rem .55rem;border-radius:999px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;"><i class="bi bi-globe2"></i>Global</span> &nbsp; The field appears on the form regardless of which ticket type the user picks. Use this for things you always want to ask — <em>"Have you tried turning it off and on again?"</em>, <em>"Where can we reach you?"</em>.</li>
    <li><span class="scope-pill scope-specific" style="display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;padding:.2rem .55rem;border-radius:999px;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;"><i class="bi bi-tag-fill"></i>2 types</span> &nbsp; The field <em>only</em> appears when the user picks one of the listed ticket types. Hover the pill to see the names. Use this for type-specific data — a "printer model" field that only makes sense on <em>Printer Issue</em> tickets, an "outage start time" that only makes sense on <em>Outage Report</em>.</li>
</ul>
<p class="text-muted mb-2">Setting the scope happens inside the field's edit dialog (pencil icon). The first thing in the dialog is a segmented switch:</p>
<div class="bg-light rounded p-3 mb-3">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;background:#f1f5f9;border-radius:.55rem;padding:.25rem;">
        <div style="text-align:center;padding:.55rem .5rem;border-radius:.4rem;background:#fff;color:#4f46e5;font-size:.85rem;font-weight:500;box-shadow:0 1px 2px rgba(15,23,42,.06);"><i class="bi bi-globe2 me-1"></i>All ticket types</div>
        <div style="text-align:center;padding:.55rem .5rem;border-radius:.4rem;color:#64748b;font-size:.85rem;font-weight:500;"><i class="bi bi-tag-fill me-1"></i>Only specific types</div>
    </div>
</div>
<p class="text-muted mb-2">Pick <strong>All ticket types</strong> for a global field. Pick <strong>Only specific types</strong> to reveal a checkbox grid of every ticket type — tick the ones the field should appear on. The form refuses to save a "specific" field with zero boxes ticked, so you can't accidentally orphan a field by switching modes and not picking anything.</p>
<p class="text-muted mb-0">Switching back from <em>Specific</em> to <em>All</em> automatically clears the checkbox state, so a stale list can never be saved by accident.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye-fill text-primary me-2"></i>"Preview as" — Filter the List by Ticket Type</h5>
<p class="text-muted mb-2">Once you start scoping fields to specific types, the field list quickly becomes hard to read at a glance — you can't easily answer <em>"what does the form actually look like for a Hardware ticket?"</em> just by scanning the rows. The <strong>Preview as</strong> chip strip at the top of the Form Fields card solves that.</p>
<p class="text-muted mb-2">The strip is a horizontal scrolling row of chips — one for each ticket type, plus an <em>All types</em> chip on the left. Each chip carries a count of how many fields would land on that type's form (Pinned + System + every Global custom field + every Specific-to-this-type custom field).</p>
<p class="text-muted mb-2">Click any chip and the field list filters in place to show <em>exactly</em> the fields a user filling in that ticket type would see, in the order they'd see them. Custom fields that don't apply to the chosen type are hidden. The visible custom rows pick up a subtle left-border accent so you can see scope at a glance:</p>
<ul class="text-muted mb-3">
    <li><strong>Grey accent</strong> — Global field (would appear on every type, including this one).</li>
    <li><strong>Indigo accent</strong> — Specific to the currently-filtered type.</li>
</ul>
<p class="text-muted mb-2">An indigo banner appears at the top of the card while a filter is active, naming the ticket type and the visible field count. <strong>Drag-to-reorder is disabled while a filter is on</strong> — ordering is global, not per-type, and the banner spells that out: <em>"Reordering applies to all forms; switch to All types to reorder."</em> Click the <em>Clear</em> button on the banner (or the <em>All types</em> chip) to leave the filter view.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Quality-of-life: when a type filter is active and you click <strong>+ Add Custom Field</strong>, the new field's edit dialog opens pre-scoped to that type. So if you're previewing the <em>Hardware</em> form and add a "Printer model" field, it lands on the <em>Hardware</em> form by default — no need to remember to flip the scope after creating it.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-display text-primary me-2"></i>Live Preview Pane</h5>
<p class="text-muted mb-2">The chip strip filters the <em>builder list</em>, but the builder list is still a list of admin rows — labels, badges, edit buttons. To see what the form genuinely <em>looks like</em> to a user (with the actual input controls, dropdown options, dependent cascades, image fields, text blocks, all rendered for real), click the <strong>Live Preview</strong> button next to <em>+ Add Custom Field</em>.</p>
<p class="text-muted mb-2">The page splits into two columns:</p>
<ul class="text-muted mb-3">
    <li><strong>Left column</strong> — the field-builder list as before. Filtering with the <em>Preview as</em> chip strip still works.</li>
    <li><strong>Right column</strong> — a pinned preview pane that iframes the real <code>/portal/tickets/create</code> page in a chrome-less <em>preview mode</em> (no navbar, no sidebar, no tour overlays, the <em>Submit</em> button is disabled). The preview is the actual portal renderer — there is no parallel "preview engine" that could ever drift out of sync with what users see.</li>
</ul>
<p class="text-muted mb-2">Switching chips on the left swaps the preview's <code>type_id</code> deep-link, so the iframe always reflects the form for the currently-filtered ticket type — including dynamic show/hide of type-scoped fields, dependent cascades, image fields, and text blocks. Adding, editing, deleting, or reordering a field automatically reloads the iframe, and the pane has its own controls in its header:</p>
<ul class="text-muted mb-3">
    <li><strong>Reload</strong> (circular arrow) — manually refresh the preview, e.g. after editing something in another tab.</li>
    <li><strong>Open in new tab</strong> (square-arrow icon) — opens the live (non-preview) form in a new tab with the type pre-selected, so you can submit a real test ticket if you want to.</li>
    <li><strong>Close</strong> — collapses the preview pane back to a single-column builder.</li>
</ul>
<p class="text-muted small mb-0">On screens narrower than 1200&nbsp;px the preview drops below the field list rather than next to it, so the form remains readable on laptops and tablets.</p>
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
