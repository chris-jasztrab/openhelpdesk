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
        Open it from the <i class="bi bi-grid-1x2"></i> <strong>Floor mode</strong> icon in the left sidebar, or go straight to <code>/agent/floor</code>. Floor mode shows the same tickets as the regular list (subject to your group restrictions) &mdash; just rendered for thumbs.
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>The Queue</h5>
<p class="text-muted mb-2">The main page (<code>/agent/floor</code>) is a card grid of all open tickets you can act on. Each card shows the ticket ID, priority (as a coloured left rail and pill), subject, current status, type, location, and either the assigned agent or an <strong>Unassigned</strong> badge. Tap a card to open the floor ticket detail view.</p>
<h6 class="fw-semibold mb-2 mt-3">Tabs</h6>
<p class="text-muted mb-0">Three pill tabs at the top filter the queue:</p>
<ul class="text-muted mb-0">
    <li><strong>All open</strong> &mdash; everything in <em>Open</em>, <em>In Progress</em>, or <em>Pending</em>.</li>
    <li><strong>Mine</strong> &mdash; tickets currently assigned to you.</li>
    <li><strong>Unassigned</strong> &mdash; tickets in your group(s) waiting to be picked up.</li>
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
        <tr><td><strong>Type</strong></td><td>Required. Confidential types are hidden from quick-create; use the regular <code>/agent/tickets/create</code> form for those.</td></tr>
        <tr><td><strong>Location</strong></td><td>Defaults to your profile's home branch if left blank.</td></tr>
        <tr><td><strong>Take photo</strong></td><td>Opens the device camera (rear-facing) and attaches the snap to the new ticket. You can take several.</td></tr>
        <tr><td><strong>Scan barcode</strong></td><td>Uses the device camera + <code>BarcodeDetector</code> (Chrome on Android) to read an asset code; the value is appended to the subject like <code>[Asset 12345]</code>. If the browser doesn't support it you'll see a hint to type the code manually.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-3">After submit you're taken straight to the floor ticket detail page for the new ticket.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ticket-detailed text-primary me-2"></i>The Floor Ticket Detail View</h5>
<p class="text-muted mb-2">Tapping a card takes you to <code>/agent/floor/tickets/{id}</code>. This is a stripped-down, touch-first version of the regular ticket page &mdash; designed for the three or four things you actually do while standing next to a frozen PC.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Section</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Subject card</strong></td><td>Big subject line with status, priority, type, location, and group pills.</td></tr>
        <tr><td><strong>Reported by / Assigned to</strong></td><td>Two cards showing the requester and the current owner. <em>Unassigned</em> tickets show in red.</td></tr>
        <tr><td><strong>Details</strong></td><td>The ticket description. Long text auto-collapses with a <strong>Show all</strong> toggle so the action buttons stay above the fold.</td></tr>
        <tr><td><strong>Quick actions</strong></td><td>One-tap forms for <strong>Claim</strong> / <strong>Release</strong>, <strong>In progress</strong>, <strong>Pending</strong>, <strong>Resolve</strong>, and <strong>Reopen</strong> (shown when the ticket is already resolved/closed). Each runs the same SLA timer and notification logic as the regular detail page.</td></tr>
        <tr><td><strong>Recent activity</strong></td><td>Last 8 timeline entries &mdash; comments, status changes, assignments, internal notes (highlighted in yellow). Useful for catching up on what someone else has already tried.</td></tr>
        <tr><td><strong>Add a note</strong></td><td>Plain-text reply with an optional photo (rear camera) and an <strong>Internal only</strong> checkbox. Posting a public reply also auto-assigns the ticket to you if it was unassigned &mdash; same behaviour as on the regular page.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-ul text-primary me-2"></i>Need Something the Simple View Doesn't Have?</h5>
<p class="text-muted mb-2">For things floor mode intentionally hides &mdash; merging, splitting, escalating, custom fields, CC management, group/type changes &mdash; tap the <strong>Full ticket details</strong> link at the bottom of the floor detail page. It opens the regular ticket page but with the navbar, sidebar, and breadcrumbs hidden, and a floating <strong>&times;</strong> close button in the top right that takes you straight back to the simple floor view. You don't have to navigate back through the full app to return to floor mode.</p>
<p class="text-muted small mb-0">Behind the scenes this is just <code>/agent/tickets/{id}?from=floor</code> &mdash; the <code>?from=floor</code> flag is what triggers the chrome-stripping and the <strong>&times;</strong>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Permissions &amp; Visibility</h5>
<ul class="text-muted mb-0">
    <li>Floor mode is available to <strong>agents</strong>, <strong>power users</strong>, and <strong>admins</strong> &mdash; same as the regular ticket list.</li>
    <li>Group restrictions apply: agents and power users only see tickets in their assigned groups, plus tickets with no group set.</li>
    <li>Confidential ticket types behave the same way as on the regular page &mdash; access is gated and re-authentication may be required for admins.</li>
    <li>Patrons get their own version at <code>/portal/floor</code> with the same touch-first layout, but limited to their own requests and read/reply/close.</li>
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
