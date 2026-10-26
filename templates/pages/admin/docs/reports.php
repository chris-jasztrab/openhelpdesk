<?php
$layout       = 'app';
$pageTitle    = 'Docs: Reports';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Reports']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Reports</h2>
<p class="text-muted mb-4">Where to see how the helpdesk is doing: the Reports &amp; Analytics area, the admin dashboard, the live wallboard, and report summaries emailed on a schedule.</p>

<div class="card border-0 shadow-sm mb-4" id="overview">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bar-chart text-primary me-2"></i>Reports &amp; Analytics</h5>
<p class="text-muted mb-2">Open it at <a href="/admin/reports"><strong>Settings → Reports</strong></a>. Anyone whose permission level includes <strong>View reports</strong> can open every report; admins always can. The page starts with eight headline figures for the chosen period:</p>
<ul class="text-muted mb-3">
    <li><strong>Tickets Created</strong>, <strong>Tickets Resolved</strong>, <strong>Avg First Response</strong>, <strong>Avg Resolution Time</strong>, <strong>SLA Compliance</strong> and <strong>SLA Breached</strong> cover tickets created in the period.</li>
    <li><strong>Unresolved</strong> and <strong>Unassigned Tickets</strong> are live counts of everything still open, whatever its age.</li>
</ul>
<p class="text-muted mb-2">Tickets closed with <em>Close as duplicate</em>, <em>Close as won't fix</em> or <em>Close as no response</em> are closed but not counted as resolved. They are left out of <strong>Tickets Resolved</strong> here and out of the <em>Resolved today</em> and <em>Created vs resolved</em> figures on the wallboard.</p>
<p class="text-muted mb-0">Below the figures, <strong>Detailed Reports</strong> has a card for each report.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="date-range">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-calendar3 text-primary me-2"></i>Choosing the Period</h5>
<p class="text-muted mb-2">Date-scoped reports have a preset dropdown beside the <em>from</em> and <em>to</em> boxes: Today, Yesterday, Last 7 / 30 / 90 days, This week, Last week, This month, Last month, This quarter, Last quarter, Year to date, Last 12 months, Last year, and Custom range. Picking a preset applies it straight away. Editing a date switches the dropdown to <em>Custom range</em>.</p>
<p class="text-muted mb-0">With no period chosen, reports open on the <strong>last 30 days</strong>. The URL carries the period, so you can bookmark or share a report with its range.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="report-list">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-columns-reverse text-primary me-2"></i>The Reports</h5>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:180px;">Report</th><th>What it shows</th><th class="text-nowrap">CSV</th><th class="text-nowrap">Schedule</th></tr></thead>
    <tbody class="text-muted">
        <tr><td class="fw-semibold">Agent Performance</td><td>Tickets handled, response times and resolution rates per agent.</td><td>Yes</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">Response Times</td><td>Average first-response and resolution times by priority.</td><td>&ndash;</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">SLA Compliance</td><td>SLA met vs breached, by priority, with the breached tickets. Shows a notice instead when SLA tracking is switched off.</td><td>&ndash;</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">SLA Violations</td><td>Every ticket that breached its first-response or resolution target in the period: which target, how overdue, assignee, status and priority. Also catches tickets that are overdue but not yet re-checked by the SLA job.</td><td>Yes</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">Unresolved Tickets</td><td>Every ticket not yet closed. Click the <em>By Status</em> and <em>By Age</em> tiles (under 1 day, 1&ndash;3, 3&ndash;7, 7&ndash;14, over 14 days) to filter. Filters combine, and the table pages without reloading.</td><td>Yes</td><td>Email only</td></tr>
        <tr><td class="fw-semibold">Ticket Volume</td><td>Tickets created over time, by priority, type and location.</td><td>&ndash;</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">Ticket Lifecycle</td><td>Average time tickets spend in each status, overall and by priority. Custom statuses appear automatically.</td><td>&ndash;</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">By Location</td><td>Volume and resolution rates compared across locations. The name follows your Location label.</td><td>Yes</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">Satisfaction</td><td>Survey results: sent, responses, response rate, average rating, distribution and comments. See <a href="/admin/docs/csat#reports">Satisfaction Surveys</a>.</td><td>&ndash;</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">Time Spent</td><td>Time logged on tickets. See <a href="/admin/docs/tickets#time-tracking">Tickets → Time Tracking</a>.</td><td>Yes</td><td>&ndash;</td></tr>
        <tr><td class="fw-semibold">Agent Time</td><td>Time each agent logged, with a per-agent timesheet. See <a href="/admin/docs/tickets#time-tracking">Tickets → Time Tracking</a>.</td><td>Yes</td><td>&ndash;</td></tr>
        <tr><td class="fw-semibold">Agent Workload</td><td>Heatmap of open tickets per agent, broken down by status and SLA state.</td><td>&ndash;</td><td>Email only</td></tr>
        <tr><td class="fw-semibold">Ticket Trends</td><td>Multi-line volume trend over time, split by ticket type or by location.</td><td>&ndash;</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">FCR Rate</td><td>First-contact resolution: the share of resolved tickets closed after no more than one reply, overall, by agent and by ticket type.</td><td>&ndash;</td><td>Yes</td></tr>
        <tr><td class="fw-semibold">Group Coverage</td><td>Each ticket type, its default group, and that group's members with their role and Manager flag. Flags types with no default group and groups with no members. Has a Print button.</td><td>Yes</td><td>&ndash;</td></tr>
        <tr><td class="fw-semibold">AI Token Usage</td><td>AI tokens used and an estimated cost in Canadian dollars. See <a href="#ai-usage">below</a>.</td><td>&ndash;</td><td>&ndash;</td></tr>
        <tr><td class="fw-semibold">Custom Builder</td><td>Pick a metric (ticket count, average first response, average resolution, SLA compliance %) and a grouping (agent, ticket type, location, priority, status) for the period.</td><td>Yes</td><td>&ndash;</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mt-3 mb-0"><strong>CSV</strong>: the report has an <strong>Export CSV</strong> button. <strong>Schedule</strong>: the report has a <strong>Schedule</strong> button. <em>Email only</em>: there is no button on the page, but you can pick the report on the Scheduled Reports form.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="exports">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-download text-primary me-2"></i>Exporting to CSV</h5>
<p class="text-muted mb-2">On reports that have it, <strong>Export CSV</strong> downloads exactly what the page currently shows. It uses the same period, filters and permission checks as the page. The Unresolved Tickets export includes the whole filtered set, not just the visible page.</p>
<p class="text-muted mb-0">Ticket lists export separately: <strong>Export CSV</strong> on the admin ticket list honours the active filters. Cells that begin with <code>=</code>, <code>+</code>, <code>-</code> or <code>@</code> are prefixed with a quote so spreadsheets don't run them as formulas.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="ai-usage">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-cpu text-primary me-2"></i>AI Token Usage</h5>
<p class="text-muted mb-2">This report totals the tokens sent to and received from your AI provider by every AI feature: skill classification, "No Wrong Door" group routing, duplicate detection and similar past tickets.</p>
<ul class="text-muted mb-0">
    <li><strong>Bucket</strong> groups the chart by hour, day, week or month.</li>
    <li><strong>Compare to</strong> overlays a second period. By default it is the same length immediately before the one you picked, and you can set your own dates. Each summary card shows the change: a rise is red, a drop green.</li>
    <li>Summary cards show total, input and output tokens, API calls, and <strong>Est. cost (CAD)</strong>. Tables break usage down by feature and by provider and model.</li>
    <li>Cost uses Anthropic's list price per model in US dollars, converted at the Bank of Canada daily USD/CAD rate (fetched automatically and cached). Models with no price on file, such as OpenAI models, show <em>No price on file</em>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="scheduled-reports">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-calendar-check text-primary me-2"></i>Scheduled Reports</h5>
<p class="text-muted mb-2">Email a report summary on a schedule from <a href="/admin/settings/scheduled-reports"><strong>Settings → Automation → Scheduled Reports</strong></a>, or click <strong>Schedule</strong> on a report page to start one for that report. Anyone who can view reports can create schedules.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:180px;">Field</th><th>Notes</th></tr></thead>
    <tbody class="text-muted">
        <tr><td class="fw-semibold">Schedule Name</td><td>Shown in the list and in the email.</td></tr>
        <tr><td class="fw-semibold">Report Type</td><td>Overview, Overview by Group (the overview totals plus a per-group breakdown), Agent Performance, Ticket Volume, Response Times, SLA Compliance, SLA Violations, Unresolved Tickets, Ticket Lifecycle, By Location, CSAT / Satisfaction, Agent Workload, Ticket Trends or FCR Rate.</td></tr>
        <tr><td class="fw-semibold">Frequency &amp; Send Day</td><td>Daily, Weekly (pick a weekday) or Monthly (pick day 1&ndash;28).</td></tr>
        <tr><td class="fw-semibold">Report Date Range</td><td>How many days before the send date the summary covers (1&ndash;365).</td></tr>
        <tr><td class="fw-semibold">Recipients</td><td>Type an address and press Enter, comma, semicolon or space, or paste a whole list. <strong>Add me</strong> adds your own address. Badly formed addresses turn red and block saving.</td></tr>
        <tr><td class="fw-semibold">Enabled</td><td>Untick to pause a schedule without deleting it.</td></tr>
    </tbody>
</table>
</div>
<ul class="text-muted mb-3">
    <li>A schedule belongs to the person who created it. Others see only their own schedules. Admins see everyone's, with a <strong>Created By</strong> column, and can edit, pause or delete any of them.</li>
    <li>Each email says which schedule sent it and who set it up.</li>
    <li>Emails are sent by the <strong>Scheduled Reports</strong> background job, which runs every 30 minutes. A report goes out on the first run of its due day. If the job isn't set up, nothing is sent. Admins see the cron line on the Scheduled Reports page, and the job's status on <a href="/admin/settings/cron-jobs">Settings → Automation → Cron Jobs</a>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="dashboard">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-speedometer2 text-primary me-2"></i>The Admin Dashboard</h5>
<p class="text-muted mb-0"><a href="/admin"><strong>Dashboard</strong></a> on the left rail shows <strong>Recent Tickets</strong>, the 10 most recently updated tickets with status, type, priority and when they changed. Confidential tickets are left out unless you belong to that type's group. <strong>Quick Actions</strong> link to the ticket list, Add User, Locations, Priorities and Settings. Staff who aren't admins land on the agent dashboard instead.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="wallboard">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-display text-primary me-2"></i>Live Wallboard</h5>
<p class="text-muted mb-2">The wallboard is a self-refreshing board for a wall screen or a second monitor. Open it from your name menu (top right) → <strong>Wallboard</strong>. Any staff member can use it. Each person sees only the tickets they're allowed to see, and admins see everything.</p>
<ul class="text-muted mb-2">
    <li>It refreshes every 10s, 15s, 30s, 1 minute or 2 minutes, pauses while the browser tab is hidden, and has Pause, Refresh now and Fullscreen buttons.</li>
    <li>Filters (location, group, type, priority and time range) narrow every widget.</li>
    <li>Widgets include count cards (open, unassigned, SLA breached or at risk, due today, created and resolved today, average first response, CSAT, SLA compliance), breakdown charts, a created-vs-resolved trend, agent workload and the unassigned queue. SLA widgets are hidden when SLA tracking is switched off.</li>
    <li>Click a number, chart slice or workload row to open the matching tickets in the ticket list.</li>
    <li>Layout, widget heights, 2&ndash;4 columns, filters and refresh rate are saved per person.</li>
</ul>
<p class="text-muted mb-0">The full guide is <a href="/agent/help/wallboard">Agent Help → Live Wallboard</a>.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
