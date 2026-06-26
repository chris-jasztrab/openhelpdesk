<?php
$layout       = 'app';
$pageTitle    = 'Help: Live Wallboard';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label'=>'Help','url'=>'/agent/help'],['label'=>'Live Wallboard']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Live Wallboard</h2>
<p class="text-muted mb-4">A real-time operations board you can put on a screen or keep open on your desk. It refreshes on its own, shows only the data you're allowed to see, and you choose which widgets appear and in what order. Open it from <strong><i class="bi bi-display"></i> Wallboard</strong> in the left sidebar, or go to <a href="/agent/wallboard">/agent/wallboard</a>.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-signpost-2 text-primary me-2"></i>On this page</h5>
<ul class="text-muted mb-0">
    <li><a href="#realtime">Real-time refresh &amp; controls</a></li>
    <li><a href="#filters">Filters</a></li>
    <li><a href="#customize">Customising your widgets</a></li>
    <li><a href="#widgets">The widgets</a></li>
    <li><a href="#visibility">What data you see</a></li>
    <li><a href="#tv">Putting it on a wall-mounted screen</a></li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="realtime">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>Real-time refresh &amp; controls</h5>
<p class="text-muted mb-2">The board polls for fresh numbers automatically. The pill next to the title shows a green dot and the time of the last successful update (it turns red if a refresh fails). The toolbar (top-right) gives you:</p>
<ul class="text-muted mb-0">
    <li><strong>Interval</strong> &mdash; how often it refreshes: 10s, 15s, 30s (default), 1m or 2m.</li>
    <li><strong>Pause / Resume</strong> &mdash; stop auto-refresh (handy while you read a list); the button turns amber while paused.</li>
    <li><strong>Refresh now</strong> &mdash; force an immediate update.</li>
    <li><strong>Fullscreen</strong> &mdash; expand the board to fill the screen for wall display.</li>
</ul>
<p class="text-muted mb-0 mt-2">To save resources, the board <strong>pauses while its browser tab is in the background</strong> and catches up the instant you return to it.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="filters">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Filters</h5>
<p class="text-muted mb-2">The filter bar narrows the whole board at once by <strong>location</strong>, <strong>group</strong>, <strong>type</strong>, <strong>priority</strong>, and a <strong>time range</strong> (7 / 14 / 30 / 90 days or the last year). The time range drives the trend, CSAT and first-response widgets; the others apply to every widget that counts tickets.</p>
<p class="text-muted mb-0"><strong>Clear</strong> resets every filter. Your filter choices are remembered for next time, alongside your widget layout.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="customize">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-1x2 text-primary me-2"></i>Customising your widgets</h5>
<p class="text-muted mb-2"><strong>Click <i class="bi bi-grid-1x2"></i> Customize</strong> (top-right) to enter customise mode. The widgets start to wobble &mdash; like rearranging app icons on a phone &mdash; and you can:</p>
<ul class="text-muted mb-2">
    <li><strong>Move a widget</strong> &mdash; grab it <em>anywhere</em> and drag. The other widgets slide out of the way and the one you're holding drops into the new spot. Put the lists, charts or numbers you care about most right at the top. Works with a mouse or by touch.</li>
    <li><strong>Remove a widget</strong> &mdash; click the red <i class="bi bi-x-circle text-danger"></i> badge on its corner.</li>
    <li><strong>Add widgets</strong> &mdash; click <strong>Add widget</strong> to open the list of every available widget and switch on the ones you want.</li>
</ul>
<p class="text-muted mb-0">Click <strong>Done</strong> when you're finished. There's nothing to save manually &mdash; every change is saved as you make it. Your widget selection, order, refresh interval and filters are all stored <strong>to your account</strong>, so they're personal to you and follow you to any device you log in from. Every agent gets a sensible default set the first time they open the board.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="widgets">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-1x2 text-primary me-2"></i>The widgets</h5>
<p class="text-muted mb-2"><strong>Number cards (KPIs):</strong></p>
<ul class="text-muted mb-3">
    <li><strong>Open tickets</strong>, <strong>Unassigned</strong>, <strong>Due today</strong>, <strong>Created today</strong>, <strong>Resolved today</strong>.</li>
    <li><strong>SLA breached</strong>, <strong>SLA at risk</strong>, <strong>SLA compliance %</strong> &mdash; shown only when SLA tracking is enabled.</li>
    <li><strong>Avg first response</strong> and <strong>CSAT</strong> &mdash; over the selected time range.</li>
</ul>
<p class="text-muted mb-2"><strong>Charts:</strong> open tickets broken down by <strong>status</strong>, <strong>priority</strong>, <strong>type</strong>, <strong>group</strong> or <strong>location</strong>, plus a <strong>Created vs Resolved</strong> trend line over the time range.</p>
<p class="text-muted mb-0"><strong>Live lists:</strong> <strong>Agent workload</strong> (open tickets per agent, with an SLA-breach flag), the <strong>Unassigned queue</strong>, and <strong>Recently updated</strong> tickets. Items in the lists link straight to the ticket.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="visibility">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-check text-primary me-2"></i>What data you see</h5>
<p class="text-muted mb-0">The wallboard follows the <strong>same visibility rules as your ticket list</strong>. Every number, chart and list only counts tickets you're allowed to open &mdash; tickets in your groups, assigned to you, that you created or watch, plus confidential tickets only if you're permitted. Admins (and anyone with &ldquo;view all tickets&rdquo;) see everything. Applying a filter can only <em>narrow</em> what you already see; it can never reveal tickets outside your access.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="tv">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-tv text-primary me-2"></i>Putting it on a wall-mounted screen</h5>
<p class="text-muted mb-0">Set the widgets and filters you want, pick a comfortable refresh interval, then click <strong>Fullscreen</strong>. The board keeps refreshing on its own with no interaction needed. Because the layout is saved to the account that's logged in, it's worth using a dedicated display account whose wallboard is tuned for the room it's in.</p>
</div>
</div>

</div>
</div>
