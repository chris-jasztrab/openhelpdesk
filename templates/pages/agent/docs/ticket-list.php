<?php
$layout       = 'app';
$pageTitle    = 'Help: Ticket List & Filters';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Ticket List & Filters']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Ticket List &amp; Filters</h2>
<p class="text-muted mb-4">Find, filter and act on tickets without leaving the list view.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-layout-text-sidebar text-primary me-2"></i>Choosing a List Layout</h5>
<p class="text-muted mb-2">The ticket list comes in three layouts. Pick the one you like under <strong>My Profile &rarr; Ticket List View</strong> (top-right avatar menu &rarr; <strong>My Profile</strong>); your choice applies to every ticket list you open. Filtering, sorting, bulk-select checkboxes and the bulk-action bar work the same in all three.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:120px">Layout</th><th>What it looks like</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong><i class="bi bi-table me-1"></i>Table</strong></td><td>The classic resizable grid with a configurable set of columns (see below). The default, and the only layout that shows every column at once.</td></tr>
        <tr><td><strong><i class="bi bi-inbox me-1"></i>Inbox</strong></td><td>An email-style list showing just who the ticket is <strong>From</strong> and the <strong>Subject</strong>. Hovering the <strong>Subject</strong> pops up a detail card with the requester, when they submitted, a snippet of the message, and the status, priority, type and assignee &mdash; with <strong>Reply</strong>, <strong>Forward</strong> and <strong>Add Note</strong> buttons that deep-link straight into the matching composer on the ticket. The card anchors in place so you can move into it. Hovering the <strong>From</strong> name shows a <em>person</em> card instead &mdash; their name, email, and an <strong>Open tickets &amp; mentions</strong> button (see below).</td></tr>
        <tr><td><strong><i class="bi bi-grid-1x2 me-1"></i>Card</strong></td><td>A roomier list where each ticket is a horizontal card: a colour-keyed requester avatar, a &ldquo;New&rdquo; flag for tickets still awaiting a first reply, the type badge and subject up top; the location/group, ticket age and SLA underneath; and priority, assignee and status down the right edge.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Confidential tickets stay redacted in every layout.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-lines-fill text-primary me-2"></i>A User&rsquo;s Open Tickets &amp; Mentions</h5>
<p class="text-muted mb-2">From the <em>sender</em> hover card in <strong>Inbox</strong> layout, click <strong>Open tickets &amp; mentions</strong> to open a page listing every open ticket that person submitted, <em>plus</em> any open ticket where they were <strong>@mentioned</strong>. The mentioned ones are badged so it&rsquo;s obvious they&rsquo;re not the requester.</p>
<p class="text-muted mb-0">The list is scoped to what you&rsquo;re allowed to see &mdash; you&rsquo;ll never be shown a ticket your permissions or confidential-type rules would otherwise hide.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-table text-primary me-2"></i>Ticket List Columns</h5>
<p class="text-muted mb-2">The ticket list shows a configurable set of columns. Click the <strong><i class="bi bi-layout-three-columns"></i> Columns</strong> button at the top-right to open the column picker and toggle columns on or off. Your preferences are saved automatically.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Column</th><th>Description</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Status</strong></td><td>Colour-coded badge: Open, In Progress, Pending, Waiting on Customer, Waiting on Third Party, Resolved, Closed. Click the badge to change the status inline.</td></tr>
        <tr><td><strong>Priority</strong></td><td>Ticket priority with its custom colour. Click the badge to change the priority inline.</td></tr>
        <tr><td><strong>Type</strong></td><td>Ticket type. Hover the cell and click the chevron to change inline.</td></tr>
        <tr><td><strong>Agent</strong></td><td>Assigned agent (or Unassigned). Hover and click the chevron to quick-assign inline.</td></tr>
        <tr><td><strong>Group</strong></td><td>Assigned group. Hover and click the chevron to change inline.</td></tr>
        <tr><td><strong>Created By</strong></td><td>The user who submitted the ticket.</td></tr>
        <tr><td><strong>Location</strong></td><td>Branch or department location associated with the ticket.</td></tr>
        <tr><td><strong>SLA</strong></td><td>Current SLA state: On Track, Warning, or Breached.</td></tr>
        <tr><td><strong>Created</strong></td><td>Date the ticket was submitted.</td></tr>
        <tr><td><strong>Due</strong></td><td>Due date, highlighted in red when overdue.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightning text-primary me-2"></i>Inline Actions</h5>
<p class="text-muted mb-2">You can update key fields directly from the ticket list without opening the ticket. Click a <strong>Status</strong> or <strong>Priority</strong> badge, or hover a cell in the <strong>Agent</strong>, <strong>Type</strong>, or <strong>Group</strong> column to reveal a chevron <i class="bi bi-chevron-down"></i> — then pick from the dropdown to make the change immediately.</p>
<ul class="text-muted mb-0">
    <li><strong>Status column</strong> — sets a new status (active statuses only). Records a timeline entry, notifies the requester when the status is in the closed bucket, triggers CSAT, and pauses or resumes the SLA timer — exactly like changing it on the ticket itself.</li>
    <li><strong>Priority column</strong> — sets a new priority or clears it, recalculating the SLA.</li>
    <li><strong>Agent column</strong> — assigns or reassigns the ticket. If the ticket has a group set, only that group's members are shown.</li>
    <li><strong>Type column</strong> — changes the ticket type.</li>
    <li><strong>Group column</strong> — moves the ticket to a different group.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Filtering Tickets</h5>
<p class="text-muted mb-2">Click the <strong>Filters</strong> button to open the filter panel on the right. The panel state is remembered as you navigate. Ticking a checkbox or picking a date <strong>applies the filter immediately</strong> &mdash; the list refreshes on click, with no need to press Apply. (The <strong>Search</strong> box is the exception: type your text and press Enter or click Apply, so the list doesn&rsquo;t reload on every keystroke.) Available filters:</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Filter</th><th>Options</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Status</strong></td><td>Multi-select from all ticket statuses.</td></tr>
        <tr><td><strong>Priority</strong></td><td>Multi-select from configured priorities.</td></tr>
        <tr><td><strong>Type</strong></td><td>Multi-select from configured ticket types.</td></tr>
        <tr><td><strong>Location</strong></td><td>Multi-select from configured locations.</td></tr>
        <tr><td><strong>Agent</strong></td><td>Unassigned only, My Tickets, or a specific agent.</td></tr>
        <tr><td><strong>Group</strong></td><td>No group, or a specific group.</td></tr>
        <tr><td><strong>Search</strong></td><td>Free-text search on the ticket subject.</td></tr>
        <tr><td><strong>Watched</strong></td><td>Show only tickets you are watching.</td></tr>
        <tr><td><strong>Resolved Today</strong></td><td>Show only tickets resolved or closed today.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel-fill text-primary me-2"></i>Applied Filters</h5>
<p class="text-muted mb-2">Every filter you have active shows as a labelled pill at the top of the filter panel &mdash; for example <span class="badge rounded-pill bg-light text-dark border">Status: Open</span> or <span class="badge rounded-pill bg-light text-dark border">Priority: High</span> &mdash; so you can see what&rsquo;s applied at a glance without scrolling the whole list.</p>
<ul class="text-muted mb-0">
    <li>Click the <strong>&times;</strong> on any pill to remove just that one filter; the list refreshes immediately.</li>
    <li>Click <strong>Clear All</strong> next to the pills to remove every filter at once.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bookmark-star text-primary me-2"></i>Saved Filters</h5>
<p class="text-muted mb-2">Save any combination of active filters as a named preset so you can apply them with a single click later.</p>
<ol class="text-muted mb-3">
    <li>Apply the filters you want, then click <strong>Save Filter</strong> in the panel.</li>
    <li>Give the preset a name.</li>
    <li>Optionally enable <strong>Share with team</strong> to make it visible to all agents.</li>
    <li>Saved filters appear at the top of the filter panel under <em>Saved Filters</em>.</li>
</ol>
<p class="text-muted mb-0">Mark any saved filter as your <strong>Default</strong> — it will be applied automatically when you open the ticket list. Only one filter can be the default at a time.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-square text-primary me-2"></i>Bulk Actions</h5>
<p class="text-muted mb-2">Use the checkbox column on the left to select multiple tickets at once. When one or more tickets are selected, a bulk action bar appears at the bottom of the screen.</p>
<ul class="text-muted mb-0">
    <li><strong>Assign</strong> — assign all selected tickets to one agent.</li>
    <li><strong>Change Status</strong> — set the same status on all selected tickets.</li>
    <li><strong>Change Priority</strong> — set the same priority on all selected tickets.</li>
    <li><strong>Change Group</strong> — move all selected tickets to a group.</li>
    <li><strong>Merge</strong> — merge all selected tickets; the lowest-numbered ticket becomes the primary.</li>
    <li><strong>Close</strong> — set all selected tickets to Closed status.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sort-down text-primary me-2"></i>Sorting &amp; Pagination</h5>
<p class="text-muted mb-2">Click any column header to sort by that column. Click again to reverse the sort order. An arrow indicator shows the current sort direction.</p>
<p class="text-muted mb-0">Use the <strong>per page</strong> selector at the bottom-right to show 25, 50, 100, or 200 tickets per page. When you navigate from a ticket back to the list, the previous URL (including all filters and sort order) is automatically restored via the breadcrumb link.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrows text-primary me-2"></i>Resizing Columns</h5>
<p class="text-muted mb-2">Every column can be widened or narrowed by dragging its right edge. Hover the line between two column headers and the cursor turns into a resize grip <i class="bi bi-arrows"></i>; drag left or right to set the width.</p>
<ul class="text-muted mb-0">
    <li>Widening past the edge of the card scrolls the table horizontally rather than overflowing.</li>
    <li>Your widths are remembered per page in your browser, so the list comes back the way you left it next time.</li>
    <li>This works on every list in the app &mdash; tickets, users, KB, reports, settings tables &mdash; not just the ticket list.</li>
    <li>A manual width sticks even after a quick-change in an inline cell; the auto-fit only adjusts columns you have not hand-sized.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
