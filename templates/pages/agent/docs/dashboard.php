<?php
$layout       = 'app';
$pageTitle    = 'Help: Dashboard & Getting Around';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Dashboard & Getting Around']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Dashboard &amp; Getting Around</h2>
<p class="text-muted mb-4">Your at-a-glance view of the queue, plus where everything lives in the app.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-1x2 text-primary me-2"></i>Stat Cards</h5>
<p class="text-muted mb-2">The cards at the top of the <a href="/agent">Agent Dashboard</a> count only tickets you're allowed to see. Click any card to open the ticket list already filtered to those tickets.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Card</th><th>What it counts</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Unassigned</strong></td><td>Open tickets with nobody assigned.</td></tr>
        <tr><td><strong>My Tickets</strong></td><td>Open tickets assigned to you.</td></tr>
        <tr><td><strong>Pending</strong></td><td>Tickets in the <em>Pending</em> status.</td></tr>
        <tr><td><strong>Resolved Today</strong></td><td>Tickets moved to <em>Resolved</em> today &mdash; by anyone, not just you.</td></tr>
        <tr><td><strong>Escalated to Me</strong></td><td>Open tickets that were escalated to you. This red card only appears when you have at least one.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-table text-primary me-2"></i>Recent Tickets</h5>
<p class="text-muted mb-2">Below the cards is a table of up to 10 open tickets, newest first. You can work on them without leaving the dashboard:</p>
<ul class="text-muted mb-3">
    <li>Click a ticket number or subject to open the ticket.</li>
    <li>Click a column heading to sort by it; click again to reverse.</li>
    <li>Click a <strong>Status</strong>, <strong>Priority</strong>, <strong>Type</strong>, <strong>Assigned To</strong> or <strong>Group</strong> value (or the small <i class="bi bi-chevron-down"></i> beside it) to change it on the spot. The assignee list shows the members of the ticket's group.</li>
</ul>
<p class="text-muted mb-0">Click <strong><i class="bi bi-layout-three-columns"></i> Columns</strong> at the top-right of the table, tick the columns you want, then click <strong>Apply</strong>. The same column choice is used on your ticket list.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-signpost-split text-primary me-2"></i>Getting Around</h5>
<p class="text-muted mb-2"><strong>The left sidebar</strong> holds your day-to-day work:</p>
<ul class="text-muted mb-3">
    <li><strong><i class="bi bi-speedometer2"></i> Dashboard</strong> and <strong><i class="bi bi-ticket-detailed"></i> Tickets</strong>.</li>
    <li><strong><i class="bi bi-grid-1x2"></i> Floor mode</strong> &mdash; only shown on touch screens such as tablets and phones. See <a href="/agent/help/floor">Floor Mode</a>.</li>
    <li><strong><i class="bi bi-book"></i> Knowledge Base</strong> &mdash; see <a href="/agent/help/knowledge-base">Knowledge Base</a>.</li>
    <li><strong><i class="bi bi-sliders"></i> Settings</strong> &mdash; Status Banners, plus any management areas your permission level allows.</li>
    <li><strong><i class="bi bi-question-circle"></i> Help</strong> &mdash; these pages.</li>
</ul>
<p class="text-muted mb-2"><strong>The top bar</strong> has:</p>
<ul class="text-muted mb-3">
    <li><strong>Search</strong> &mdash; press <kbd>/</kbd> anywhere (when you're not typing in a box) to jump into it. Results are grouped into <em>Everything</em>, <em>Tickets</em>, <em>Contacts</em> and <em>Knowledge Base Articles</em>. Press <kbd>Esc</kbd> to close it.</li>
    <li><strong><i class="bi bi-bell"></i> Notifications</strong> &mdash; the bell shows how many unread alerts you have. See <a href="/agent/help/profile">Profile &amp; Notifications</a>.</li>
    <li><strong>Your name</strong> &mdash; opens your account menu: <strong>Restart Tour</strong>, <strong>Manage My Team</strong> (group managers only), <strong>Wallboard</strong>, <strong>My Profile</strong> and <strong>Sign Out</strong>.</li>
</ul>
<p class="text-muted mb-0">On a phone the sidebar is hidden; tap the <i class="bi bi-list"></i> menu button in the top bar to reach the same links.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-play-circle text-primary me-2"></i>The Guided Tour</h5>
<p class="text-muted mb-0">The first time you sign in, a short tour walks you through the dashboard, the ticket list and a ticket, and finishes with tips on replying by email. Close it at any time. To watch it again, open your account menu (your name, top-right) and choose <strong>Restart Tour</strong>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-phone text-primary me-2"></i>Installing the Helpdesk as an App</h5>
<p class="text-muted mb-0">On Chrome, Edge or Android an <strong>Install</strong> banner may appear at the bottom of the screen &mdash; tap it to add the helpdesk to your home screen or desktop as its own app window. On an iPhone or iPad, tap <strong>Share</strong>, then <strong>Add to Home Screen</strong>. If you dismiss the banner it stays hidden for two weeks.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
