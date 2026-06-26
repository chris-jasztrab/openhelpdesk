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
<p class="text-muted mb-4">A drag-and-drop board view of your tickets — group them by status, priority or assignee, or build your own personal organiser.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-kanban text-primary me-2"></i>Opening the Board</h5>
<p class="text-muted mb-2">The board is a fourth way to view your tickets, alongside <strong>Table</strong>, <strong>Compact</strong> and <strong>Card</strong>. Open it from the <strong>view switcher</strong> on the far right of the ticket-list toolbar — click the <strong><i class="bi bi-kanban"></i> Kanban</strong> button. (See <a href="/agent/help/ticket-list">Ticket List &amp; Filters</a> for the other layouts.)</p>
<p class="text-muted mb-0">Each ticket shows as a card in a column. Drag a card between columns to update it. The board shows the same tickets you'd see in the list — confidential tickets you can't access are never shown, and a ticket you're not permitted to see can never appear on a card.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-columns-gap text-primary me-2"></i>Built-in Boards</h5>
<p class="text-muted mb-2">Three built-in boards group your tickets by a real ticket field. Switch between them with the board selector at the top of the page:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:140px">Board</th><th>Columns &amp; what dragging does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong><i class="bi bi-list-task me-1"></i>Status</strong></td><td>One column per active ticket status. Drag a card to a new column to <strong>change its status</strong> — exactly as if you'd changed it on the ticket.</td></tr>
        <tr><td><strong><i class="bi bi-flag me-1"></i>Priority</strong></td><td>One column per priority. Drag a card to <strong>change its priority</strong> (and recalculate the SLA).</td></tr>
        <tr><td><strong><i class="bi bi-person me-1"></i>Assignee</strong></td><td>A column for <strong>Unassigned</strong> plus one per agent. Drag a card to <strong>assign or reassign</strong> the ticket.</td></tr>
    </tbody>
</table>
</div>
<div class="alert alert-info small mb-0 mt-3"><i class="bi bi-info-circle me-2"></i>
    Dragging a card on a built-in board makes a <strong>real change</strong> to the ticket. It records a timeline entry, pauses or resumes the SLA timer, and fires automations and notifications — identical to using the quick-edit pickers in the list. The columns are built live from your configured statuses, priorities and staff, so they always match your setup.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>Scoped Assignee Columns</h5>
<p class="text-muted mb-2">On the <strong>Assignee</strong> board you won't see an empty column for every staff member in the system. As an agent, the board shows <strong>Unassigned</strong>, <strong>yourself</strong>, and any colleagues who already hold a ticket you can see. Admins still see a column for every staff member.</p>
<p class="text-muted mb-0">This keeps the board focused on the people actually working your queue instead of listing the whole roster.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>Custom Boards — Your Personal Organiser</h5>
<p class="text-muted mb-2">A <strong>custom board</strong> lets you arrange tickets however <em>you</em> want, without changing anything about the tickets themselves. You define your own columns (called <strong>buckets</strong>) and drag tickets into them.</p>
<ul class="text-muted mb-3">
    <li><strong>Create a board</strong> from the board selector, give it a name, and add as many buckets as you like.</li>
    <li><strong>Manage buckets</strong> — the board owner can add, rename, recolour and delete buckets at any time.</li>
    <li><strong>Drag a card into a bucket</strong> and that placement is remembered for you. <strong>No ticket field changes</strong> — the ticket keeps its real status, priority and assignee. It's purely a personal layout, ideal for triage piles like "Look at today", "Waiting on parts", or "This week".</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Custom boards are <strong>private to you</strong> unless you share them with the team. A shared board is visible to your colleagues, but only its owner can rename or restructure the buckets.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Board Filters</h5>
<p class="text-muted mb-2">Click <strong>Filters</strong> on any board to narrow what's shown. Active filters show a count badge, stick as you switch between boards or search, and have a <strong>Clear</strong> action.</p>
<ul class="text-muted mb-0">
    <li><strong>My team's tickets only</strong> — limits the board to tickets assigned to a teammate (someone who shares a group with you) or left unassigned. On the Assignee board this also trims the columns down to just those teammates.</li>
    <li><strong>Group</strong> — a checklist of groups to filter by. As an agent you can filter within your own groups; admins can filter within any group.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrows-move text-primary me-2"></i>Dragging Cards</h5>
<ul class="text-muted mb-0">
    <li>Drag-and-drop uses your browser's native support — there's nothing to install and it works with a mouse or trackpad.</li>
    <li>A move applies <strong>immediately</strong>; if the server rejects it (for example you lack permission to make that change), the card snaps back to where it was and you'll see an error.</li>
    <li>Click a card's subject to open the full ticket in the usual ticket view.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>What the Board Shows</h5>
<ul class="text-muted mb-0">
    <li>The board displays up to the <strong>500 most recent</strong> matching tickets. If your filters match more than that, an on-screen notice tells you — narrow the filters or the search to see the rest.</li>
    <li>The search box at the top filters cards by subject, just like the list.</li>
    <li>Confidential tickets are redacted and out-of-scope tickets are never shown — the board obeys exactly the same visibility rules as every other ticket view.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
