<?php
$layout       = 'app';
$pageTitle    = 'Help: Kanban Board';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Kanban Board']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Kanban Board</h2>
<p class="text-muted mb-4">A drag-and-drop board of your tickets &mdash; grouped by status, priority or assignee, or arranged on your own custom board.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-kanban text-primary me-2"></i>Opening the Board</h5>
<p class="text-muted mb-2">On the ticket list, click the <strong><i class="bi bi-kanban"></i> Kanban</strong> button at the far right of the toolbar, next to the Table / Compact / Card buttons. To go back, click one of those layout buttons. (See <a href="/agent/help/ticket-list">Ticket List &amp; Filters</a>.)</p>
<p class="text-muted mb-0">Each ticket is a card in a column. Cards show the same tickets you'd see in the list, and a card whose ticket a colleague has open shows <em>&ldquo;Viewing&rdquo;</em> or <em>&ldquo;Replying&rdquo;</em> with their name. Click a card's subject to open the ticket.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-columns-gap text-primary me-2"></i>Built-in Boards</h5>
<p class="text-muted mb-2">Use the board menu at the top of the page (under <strong>Group by</strong>) to pick one of three built-in boards:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:140px">Board</th><th>Columns &amp; what dragging does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Status</strong></td><td>One column per active status. Drag a card to <strong>change its status</strong>.</td></tr>
        <tr><td><strong>Priority</strong></td><td>One column per priority, plus <em>No priority</em>. Drag a card to <strong>change its priority</strong>.</td></tr>
        <tr><td><strong>Assignee</strong></td><td><em>Unassigned</em> plus a column per person. Drag a card to <strong>assign or reassign</strong> it.</td></tr>
    </tbody>
</table>
</div>
<div class="alert alert-info small mb-0 mt-3"><i class="bi bi-info-circle me-2"></i>
    Dragging on a built-in board makes a <strong>real change</strong> to the ticket &mdash; it's recorded on the timeline, the SLA pauses or resumes, and notifications and automations run, just like a quick edit in the list.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>Who Gets a Column on the Assignee Board</h5>
<p class="text-muted mb-0">You see <strong>Unassigned</strong>, <strong>yourself</strong>, and any colleague who already holds a ticket you can see &mdash; not an empty column for every staff member. Admins see a column for everyone.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>Custom Boards &mdash; Your Own Organiser</h5>
<p class="text-muted mb-2">A custom board lets you sort tickets into your own columns (called <strong>buckets</strong>) &mdash; piles like &ldquo;Look at today&rdquo;, &ldquo;Waiting on parts&rdquo; or &ldquo;This week&rdquo;. Moving a card on a custom board <strong>never changes the ticket</strong>; its status, priority and assignee stay as they are.</p>
<ul class="text-muted mb-3">
    <li><strong>Create one</strong> &mdash; open the board menu and choose <strong>New board&hellip;</strong>. Your boards (and boards colleagues have shared) are listed under <strong>My &amp; shared boards</strong>.</li>
    <li><strong>Add buckets</strong> &mdash; click <strong>Add bucket</strong>, give it a name and colour, then <strong>Add</strong>.</li>
    <li><strong>Unsorted</strong> &mdash; tickets you haven't placed yet sit in the <em>Unsorted</em> column. Drag them into a bucket.</li>
    <li><strong>Change a bucket</strong> &mdash; use the <i class="bi bi-three-dots-vertical"></i> menu on a bucket to rename it, change its colour or delete it. Deleting a bucket sends its cards back to Unsorted.</li>
    <li><strong>Board settings</strong> &mdash; the <i class="bi bi-gear"></i> button offers <strong>Rename board</strong>, <strong>Share with team</strong> and <strong>Delete board</strong>.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    A custom board is <strong>private</strong> until you tick <strong>Share with team</strong>. On a shared board, colleagues see the same placements and can drag cards between buckets, but only you (the owner) can rename, share, delete or change its buckets.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Search &amp; Filters</h5>
<p class="text-muted mb-2">The <strong>Search subject&hellip;</strong> box narrows the cards by subject. Click <strong>Filters</strong> for more options, then <strong>Apply</strong> (or <strong>Clear</strong>). A number on the button shows how many filters are on, and they stay on as you switch boards.</p>
<ul class="text-muted mb-0">
    <li><strong>My team&rsquo;s tickets only</strong> &mdash; only tickets assigned to someone who shares a group with you, or left unassigned. On the Assignee board this also trims the columns to those teammates.</li>
    <li><strong>My groups</strong> &mdash; tick groups to show only their tickets. (Admins can pick any group.)</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Good to Know</h5>
<ul class="text-muted mb-0">
    <li>Dragging works with a mouse or trackpad. A move applies straight away; if it isn't allowed, the card jumps back and you'll see a message.</li>
    <li>The board shows up to the <strong>500 most recent</strong> matching tickets. If more match, a notice says so &mdash; narrow your search or filters to see the rest.</li>
    <li>The board follows the same visibility rules as the ticket list, and confidential tickets are redacted.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
