<?php
$layout       = 'app';
$pageTitle    = 'Help: Dashboard';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Dashboard']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Dashboard</h2>
<p class="text-muted mb-4">Your personal at-a-glance view of the queue and your workload.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-1x2 text-primary me-2"></i>Stat Cards</h5>
<p class="text-muted mb-2">The four cards at the top of the dashboard give you a live count of:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Card</th><th>What it shows</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Unassigned</strong></td><td>Open tickets that have no agent assigned. Click to jump to the filtered ticket list.</td></tr>
        <tr><td><strong>My Tickets</strong></td><td>All open tickets currently assigned to you. Click to jump to your personal queue.</td></tr>
        <tr><td><strong>Pending</strong></td><td>Tickets in <em>Pending</em>, <em>Waiting on Customer</em>, or <em>Waiting on Third Party</em> status.</td></tr>
        <tr><td><strong>Resolved Today</strong></td><td>Tickets you resolved or closed today. Resets at midnight.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-table text-primary me-2"></i>Recent Tickets Widget</h5>
<p class="text-muted mb-2">Below the stat cards is a table of your most recently updated tickets. You can work on tickets directly from this widget without going to the full ticket list:</p>
<ul class="text-muted mb-3">
    <li>Click any row to open the ticket.</li>
    <li>Hover a cell in the <strong>Agent</strong>, <strong>Type</strong>, or <strong>Group</strong> column to reveal a chevron <i class="bi bi-chevron-down"></i>. Click the chevron to change that field inline.</li>
    <li>The <strong>Agent</strong> dropdown is filtered to members of the ticket's assigned group (if any).</li>
</ul>
<h6 class="fw-semibold mb-2">Column Picker</h6>
<p class="text-muted mb-0">Click the <strong><i class="bi bi-layout-three-columns"></i> Columns</strong> button at the top-right of the widget to choose which columns are shown. Your selection is saved automatically.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
