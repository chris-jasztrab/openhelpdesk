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
<h5 class="fw-semibold mb-3"><i class="bi bi-eye text-primary me-2"></i>Which Tickets You See</h5>
<p class="text-muted mb-2">Open <a href="/agent/tickets">Tickets</a> from the left sidebar. The list (and the dashboard, board, wallboard and search) only shows tickets you're allowed to see:</p>
<ul class="text-muted mb-2">
    <li>tickets in any group you belong to, and tickets with no group;</li>
    <li>tickets assigned to you, that you submitted, that you watch, or that you're CC'd on.</li>
</ul>
<p class="text-muted mb-0">If your permission level includes <em>view all tickets</em>, you see every ticket except confidential ones. A blue note at the top &mdash; <em>&ldquo;Showing tickets assigned to your groups&rdquo;</em> &mdash; names the groups you're in. Confidential tickets follow stricter rules; see <a href="/agent/help/working-tickets#confidential">Confidential Tickets</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-tools text-primary me-2"></i>The Toolbar</h5>
<ul class="text-muted mb-0">
    <li><strong>Count badge</strong> &mdash; how many tickets match, e.g. <em>&ldquo;12 filtered of 340 total&rdquo;</em>.</li>
    <li><strong><i class="bi bi-funnel"></i> Filters</strong> &mdash; opens the filter panel (see below). A number on the button shows how many filters are on.</li>
    <li><strong><i class="bi bi-layout-three-columns"></i> Columns</strong> &mdash; choose the visible columns (Table layout only).</li>
    <li><strong><i class="bi bi-download"></i> Export CSV</strong> &mdash; downloads exactly the tickets that match your current filters and sort, as a spreadsheet.</li>
    <li><strong><i class="bi bi-collection"></i> Templates</strong> &mdash; manage ticket templates (only if your permission level allows it).</li>
    <li><strong><i class="bi bi-plus-lg"></i> New Ticket</strong> &mdash; see <a href="/agent/help/creating-tickets">Creating Tickets</a>.</li>
    <li><strong>Layout switcher</strong> &mdash; the icon buttons on the far right (see the next section).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-layout-text-sidebar text-primary me-2"></i>Choosing a List Layout</h5>
<p class="text-muted mb-2">Pick a layout with the <strong>Table / Compact / Card</strong> buttons on the far right of the toolbar. Your choice is saved and used every time you open the list. Filtering, sorting, the checkboxes and the bulk-action bar work the same in all three. The fourth button, <strong><i class="bi bi-kanban"></i> Kanban</strong>, opens the drag-and-drop <a href="/agent/help/kanban">board view</a>.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:120px">Layout</th><th>What it looks like</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong><i class="bi bi-table me-1"></i>Table</strong></td><td>A grid with the columns you choose. The only layout with the <strong>Columns</strong> picker and quick-edit cells.</td></tr>
        <tr><td><strong><i class="bi bi-inbox me-1"></i>Compact</strong></td><td>An email-style list of <strong>From</strong> and <strong>Subject</strong>. Hover the subject for a card with the requester, when they wrote, a snippet, the status, priority, type and assignee, and <strong>Reply</strong>, <strong>Forward</strong> and <strong>Add Note</strong> buttons that open the ticket straight into that box. Hover the <strong>From</strong> name for the person's email and an <strong>Open tickets &amp; mentions</strong> button.</td></tr>
        <tr><td><strong><i class="bi bi-grid-1x2 me-1"></i>Card</strong></td><td>A roomier card per ticket: requester avatar, a &ldquo;New&rdquo; flag if nobody has replied yet, type and subject, location/group, age and SLA, then priority, assignee and status on the right.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Confidential tickets stay redacted in every layout.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-badge text-primary me-2"></i>Hints Next to the Subject</h5>
<ul class="text-muted mb-0">
    <li><strong style="color:#b45309;">Amber subject</strong> with <em>&ldquo;Opened by Jane Smith&rdquo;</em> or <em>&ldquo;Being replied to by Jane Smith&rdquo;</em> &mdash; a colleague has that ticket open right now. The hint updates on its own every few seconds, so you can avoid two people answering the same ticket.</li>
    <li><span class="badge bg-warning bg-opacity-25 text-dark"><i class="bi bi-pencil-square me-1"></i>Draft</span> &mdash; you have an unsent reply saved on that ticket. It disappears once you send or discard it.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-lines-fill text-primary me-2"></i>A Person&rsquo;s Open Tickets &amp; Mentions</h5>
<p class="text-muted mb-0">In the <strong>Compact</strong> layout, hover a name in the <strong>From</strong> column and click <strong>Open tickets &amp; mentions</strong>. The page lists every open ticket that person submitted plus open tickets where they were <strong>@mentioned</strong> (badged so you can tell them apart). It only shows tickets you're allowed to see.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-table text-primary me-2"></i>Table Columns</h5>
<p class="text-muted mb-2">Click <strong><i class="bi bi-layout-three-columns"></i> Columns</strong>, tick the columns you want, then click <strong>Apply</strong>. The ticket number and subject are always shown.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Column</th><th>Description</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Status</strong></td><td>Colour-coded status badge. Click to change it.</td></tr>
        <tr><td><strong>Priority</strong></td><td>Priority badge. Click to change it.</td></tr>
        <tr><td><strong>Type</strong></td><td>Ticket type. Click to change it.</td></tr>
        <tr><td><strong>Assigned To</strong></td><td>The assignee, or Unassigned. Click to reassign.</td></tr>
        <tr><td><strong>Group</strong></td><td>The ticket's group. Click to change it.</td></tr>
        <tr><td><strong>Created By</strong></td><td>The person who submitted the ticket.</td></tr>
        <tr><td><strong>Location</strong></td><td>The branch or site on the ticket.</td></tr>
        <tr><td><strong>SLA</strong></td><td>On Track, Warning or Breached (only when SLA tracking is on).</td></tr>
        <tr><td><strong>Created</strong></td><td>When the ticket was submitted.</td></tr>
        <tr><td><strong>Due</strong></td><td>Due date, in red when overdue.</td></tr>
        <tr><td><strong>Confidential</strong></td><td>A lock on confidential tickets. Hidden until you turn it on.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightning text-primary me-2"></i>Quick Edits From the List</h5>
<p class="text-muted mb-2">In the Table layout, click a value in the <strong>Status</strong>, <strong>Priority</strong>, <strong>Type</strong>, <strong>Assigned To</strong> or <strong>Group</strong> column (or the small <i class="bi bi-chevron-down"></i> beside it) and pick a new value. The change saves immediately and behaves exactly like changing it on the ticket: it's recorded on the timeline, notifications and automations run, and the SLA pauses, resumes or recalculates as needed.</p>
<ul class="text-muted mb-0">
    <li><strong>Status</strong> &mdash; only active statuses are offered. If someone else changed the status a moment before you, you'll see their value and a message instead of overwriting it.</li>
    <li><strong>Priority</strong> &mdash; only the priorities the ticket's type allows are listed.</li>
    <li><strong>Assigned To</strong> &mdash; lists the members of the ticket's group when it has one.</li>
    <li><strong>Type</strong> &mdash; changing the type also moves the ticket into that type's default group, if it has one.</li>
    <li><strong>Group</strong> &mdash; moving a ticket to a group that doesn't match its type clears the type to &ldquo;Not Set&rdquo;.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Filtering Tickets</h5>
<p class="text-muted mb-2">Click <strong>Filters</strong> to slide the filter panel in from the left. Ticking a box applies it straight away &mdash; the list refreshes without reloading the page. The <strong>Search</strong> box is the exception: type, then press Enter or click <strong>Apply</strong>.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Filter</th><th>Options</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Search</strong></td><td>Text in the ticket subject.</td></tr>
        <tr><td><strong>Status</strong></td><td>Any of your statuses.</td></tr>
        <tr><td><strong>Priority</strong></td><td>Any of your priorities.</td></tr>
        <tr><td><strong>Type</strong></td><td>Any ticket type.</td></tr>
        <tr><td><strong>Location</strong></td><td>Any branch or site. Ticking a location marked <em>(any)</em>, such as System Wide, means &ldquo;any location&rdquo;.</td></tr>
        <tr><td><strong>Assigned To</strong></td><td><em>My Tickets</em>, <em>Unassigned</em>, or particular people.</td></tr>
        <tr><td><strong>Group</strong></td><td><em>No Group</em>, or particular groups.</td></tr>
        <tr><td><strong>Requester</strong></td><td><em>Requested by Me</em> &mdash; tickets you opened yourself, for example asking IT for help with your own computer.</td></tr>
        <tr><td><strong>Watching</strong></td><td><em>My Watched Tickets</em>.</td></tr>
        <tr><td><strong>Attachments</strong></td><td><em>Has Attachment</em>.</td></tr>
        <tr><td><strong>Date</strong></td><td><em>Resolved Today</em>.</td></tr>
        <tr><td><strong>Escalation</strong></td><td><em>Escalated to Me</em>.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-0">The list remembers your filters for the rest of the day, even if you leave and come back. Click <strong>Clear</strong> (or <strong>Clear All</strong>) to start fresh.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel-fill text-primary me-2"></i>Applied Filters</h5>
<p class="text-muted mb-2">Every active filter shows as a pill at the top of the filter panel &mdash; for example <span class="badge rounded-pill bg-light text-dark border">Status: Open</span> or <span class="badge rounded-pill bg-light text-dark border">Assigned To: My Tickets</span>.</p>
<ul class="text-muted mb-0">
    <li>Click the <strong>&times;</strong> on a pill to remove just that filter.</li>
    <li>Click <strong>Clear All</strong> to remove every filter at once.</li>
    <li>Filters that arrive from a dashboard card or a wallboard widget (such as <em>Created Today</em> or <em>Due Today</em>) also show as pills, so you can see why the list is narrowed and remove them.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bookmark-star text-primary me-2"></i>Saved Filters</h5>
<p class="text-muted mb-2">Save a combination of filters so you can bring it back with one click.</p>
<ol class="text-muted mb-3">
    <li>Apply the filters you want.</li>
    <li>Click <strong>Save Current Filter</strong> in the panel.</li>
    <li>Give it a name and click <strong>Save</strong>.</li>
</ol>
<p class="text-muted mb-2">Your saved filters are listed under <strong>Saved Filters</strong> at the top of the panel. Click one to apply it. The arrow next to a filter you own offers:</p>
<ul class="text-muted mb-0">
    <li><strong>Set as Default</strong> (marked <i class="bi bi-star-fill text-warning"></i>) &mdash; applied automatically the first time you open the list each day. Only one filter can be your default.</li>
    <li><strong>Share with Team</strong> / <strong>Make Private</strong> &mdash; shared filters appear in colleagues' lists with a <i class="bi bi-people-fill"></i> icon. They can use them but not change them.</li>
    <li><strong>Delete</strong>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-square text-primary me-2"></i>Bulk Actions</h5>
<p class="text-muted mb-2">Tick the boxes on the left of several tickets (or the box in the header to select the whole page). A bar appears at the top of the list showing how many are selected and these actions:</p>
<ul class="text-muted mb-2">
    <li><strong>Assign</strong> &mdash; assign them all to one person.</li>
    <li><strong>Close</strong> &mdash; set them all to Closed.</li>
    <li><strong>Merge</strong> &mdash; pick which ticket is the primary; the others' conversations move into it and they're closed. The primary takes the highest priority among them.</li>
    <li><strong>Status</strong>, <strong>Priority</strong>, <strong>Group</strong> &mdash; set a value on all of them. A priority that a ticket's type doesn't allow is skipped, and you're told how many were skipped.</li>
    <li><strong>Delete</strong> &mdash; permanently deletes the tickets and their attachments after you confirm. This can't be undone.</li>
</ul>
<p class="text-muted small mb-0">Each bulk action is a separate permission, so your bar may show fewer buttons. <strong>Delete</strong> is off unless your administrator grants it. Bulk actions never touch tickets you aren't allowed to see.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sort-down text-primary me-2"></i>Sorting &amp; Pages</h5>
<p class="text-muted mb-2">Click a column heading to sort by it; click again to reverse. An arrow shows the current sort.</p>
<p class="text-muted mb-0">Use the selector at the bottom to show 25, 50, 100 or 200 tickets per page; the page links always include the first and last page. When you open a ticket, the <strong>Tickets</strong> link in the breadcrumb brings you back to the list with the same filters and sort.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrows text-primary me-2"></i>Resizing Columns</h5>
<p class="text-muted mb-2">Drag the right edge of any column heading to make it wider or narrower.</p>
<ul class="text-muted mb-0">
    <li>The Subject column stretches to fill the space that's left, and long subjects end in &hellip; &mdash; hover for the full text.</li>
    <li>Your widths are remembered in this browser, per page.</li>
    <li>This works on most tables in the app, not just the ticket list.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
