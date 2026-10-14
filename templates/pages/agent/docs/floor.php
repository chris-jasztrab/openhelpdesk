<?php
$layout       = 'app';
$pageTitle    = 'Help: Floor Mode';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Floor Mode']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Floor Mode</h2>
<p class="text-muted mb-4">A tablet- and phone-friendly view of the queue for staff working away from a desk &mdash; large touch targets, big type, photos straight from the camera, and one-tap claim/resolve actions.</p>

<div class="alert alert-light border d-flex gap-3 mb-4">
    <i class="bi bi-info-circle fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        Open it from the <i class="bi bi-grid-1x2"></i> <strong>Floor mode</strong> icon in the left sidebar. The icon only appears on touch screens such as tablets and phones; on a computer you can go straight to <a href="/agent/floor">Floor mode</a>. It shows the same tickets as the regular list &mdash; just sized for thumbs.
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>The Queue</h5>
<p class="text-muted mb-2">The main page is a card grid of up to 100 open tickets you can see. Each card shows the ticket ID, priority (as a coloured left rail and pill), subject, current status, type, location, and either the assigned agent or an <strong>Unassigned</strong> badge. Tap a card to open the floor ticket detail view.</p>
<h6 class="fw-semibold mb-2 mt-3">Tabs</h6>
<p class="text-muted mb-0">Three pill tabs at the top filter the queue:</p>
<ul class="text-muted mb-0">
    <li><strong>All open</strong> &mdash; every ticket in an open status (anything not resolved or closed).</li>
    <li><strong>Mine</strong> &mdash; tickets currently assigned to you.</li>
    <li><strong>Unassigned</strong> &mdash; open tickets nobody has picked up yet.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-circle text-success me-2"></i>Quick-Create (the <i class="bi bi-plus-lg"></i> button)</h5>
<p class="text-muted mb-2">The big floating <i class="bi bi-plus-lg"></i> button in the bottom-right opens a bottom-sheet form for capturing a ticket while you're walking the building &mdash; printer jam in the makerspace, frozen public PC, lock at the self-checkout. It's intentionally minimal: subject, type, location, photo. Everything else (priority, group, SLA timer, AI routing, auto-assign) runs the same post-create hooks as a regular ticket once you submit.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:30%;">Field</th><th>Notes</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>What happened?</strong></td><td>Subject line. Tap the <i class="bi bi-mic-fill text-primary"></i> microphone to dictate (uses the browser's speech recognition &mdash; Chrome on Android works best).</td></tr>
        <tr><td><strong>Type</strong></td><td>Required. Confidential types aren&rsquo;t offered here; use the regular <a href="/agent/help/creating-tickets">New Ticket</a> form for those.</td></tr>
        <tr><td><strong>Location</strong></td><td>Leave it on <em>My branch</em> to use your own branch, or pick another.</td></tr>
        <tr><td><strong>Take photo</strong></td><td>Opens the device camera (rear-facing) and attaches the snap to the new ticket. You can take several.</td></tr>
        <tr><td><strong>Scan barcode</strong></td><td>Uses the device camera + <code>BarcodeDetector</code> (Chrome on Android) to read an asset code; the value is appended to the subject like <code>[Asset 12345]</code>. If the browser doesn't support it you'll see a hint to type the code manually.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-3">Tap <strong>Create ticket</strong>. If similar open tickets already exist, you'll see them first with a <strong>Create anyway &mdash; This is a Different Issue</strong> button. After creating, you're taken straight to the floor view of the new ticket.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ticket-detailed text-primary me-2"></i>The Floor Ticket Detail View</h5>
<p class="text-muted mb-2">Tapping a card opens the floor view of that ticket &mdash; a stripped-down, touch-first version of the regular ticket page &mdash; designed for the three or four things you actually do while standing next to a frozen PC.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Section</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Subject card</strong></td><td>Big subject line with status, priority, type, location, and group pills.</td></tr>
        <tr><td><strong>Reported by / Assigned to</strong></td><td>Two cards showing the requester and the current owner. <em>Unassigned</em> tickets show in red.</td></tr>
        <tr><td><strong>Details</strong></td><td>The ticket description. Long text auto-collapses with a <strong>Show all</strong> toggle so the action buttons stay above the fold.</td></tr>
        <tr><td><strong>Quick actions</strong></td><td>One-tap buttons: <strong>Claim</strong> (or <strong>Release</strong> if it's already yours), <strong>In progress</strong>, <strong>Pending</strong> and <strong>Resolve</strong>. On a resolved or closed ticket, <strong>Reopen</strong> replaces Resolve. The button for the current status is hidden. Each works exactly like changing it on the regular ticket page.</td></tr>
        <tr><td><strong>Recent activity</strong></td><td>The last 8 timeline entries, with internal notes marked <em>internal</em>. Useful for catching up on what someone else has already tried.</td></tr>
        <tr><td><strong>Add a note</strong></td><td>A plain-text message with optional <strong>Photo</strong> and an <strong>Internal only</strong> checkbox, then <strong>Send</strong>. Unticked, it's a public reply &mdash; and like on the regular page, a public reply on an unassigned ticket assigns it to you.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-ul text-primary me-2"></i>Need Something the Simple View Doesn't Have?</h5>
<p class="text-muted mb-2">For things floor mode intentionally hides &mdash; merging, splitting, escalating, custom fields, CC management, group/type changes &mdash; tap the <strong>Full ticket details</strong> link at the bottom of the floor detail page. It opens the regular ticket page with the top bar, sidebar and breadcrumbs hidden, and a round <strong>&times;</strong> button in the top-right corner that takes you straight back to the floor view.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Permissions &amp; Visibility</h5>
<ul class="text-muted mb-0">
    <li>Floor mode is available to every staff member and shows the same tickets you can see in the regular ticket list (see <a href="/agent/help/ticket-list">Which Tickets You See</a>).</li>
    <li>Confidential tickets follow the same rules as on the regular page.</li>
    <li>Requesters have their own floor mode in the portal, limited to their own requests.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>Tips</h5>
<ul class="text-muted mb-0">
    <li>Add the floor mode page to your tablet's home screen (Add to Home Screen / Install app) for a one-tap launcher and a fullscreen, no-browser-chrome experience.</li>
    <li>If voice dictate or barcode scan don't appear, the browser doesn't support them. Chrome on Android has the best coverage; iOS Safari supports voice but not barcode scanning.</li>
    <li>The card grid auto-collapses to a single column under 768px wide, so it works on phones too.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
