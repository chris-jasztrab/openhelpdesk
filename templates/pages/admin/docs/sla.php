<?php
$layout       = 'app';
$pageTitle    = 'Docs: SLA Policies';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'SLA Policies']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">SLA Policies</h2>
<p class="text-muted mb-4">Define response and resolution time targets to ensure your team meets service commitments.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>What is an SLA?</h5>
<p class="text-muted mb-2">A Service Level Agreement (SLA) policy defines two time targets for a ticket:</p>
<ul class="text-muted mb-0">
    <li><strong>First Response Time</strong> — how long after ticket creation an agent must send their first public reply.</li>
    <li><strong>Resolution Time</strong> — how long after ticket creation the ticket must be resolved.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-toggle-on text-primary me-2"></i>Turning SLA Tracking On or Off</h5>
<p class="text-muted mb-2">SLA tracking is a site-wide feature you can switch off entirely. The <strong>Enable SLA tracking</strong> switch at the top of <a href="/admin/settings/sla-policies"><strong>Admin → Settings → SLA Policies</strong></a> controls it for the whole helpdesk.</p>
<p class="text-muted mb-2">While SLA tracking is <strong>disabled</strong>:</p>
<ul class="text-muted mb-2">
    <li>New tickets get no first-response or resolution due dates, and priority/type changes and pending pauses no longer touch SLA.</li>
    <li>The SLA state badge and the SLA card disappear from agent and admin ticket views.</li>
    <li>The <strong>SLA</strong> column drops out of every ticket list (and its column picker) and the Unresolved Tickets report.</li>
    <li>The SLA Compliance report shows a "disabled" banner instead of figures.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Switching SLA tracking off leaves your existing policies and all historical SLA data intact — nothing is deleted. Turn the switch back on and tracking resumes for tickets created from that point. SLA tracking is <strong>enabled by default</strong>.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Creating an SLA Policy</h5>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/settings/sla-policies"><strong>Admin → Settings → SLA Policies</strong></a>.</li>
    <li>Click <strong>Add Policy</strong>.</li>
    <li>Enter a name (e.g. "Standard", "Critical Response").</li>
    <li>Set the <strong>First Response Time</strong> — type a duration such as <code>4h</code>, <code>3d</code>, or a combination like <code>1d 2h 30m</code> (see <a href="#duration-units">Typing Durations</a> below).</li>
    <li>Set the <strong>Resolution Time</strong> the same way.</li>
    <li>Optionally mark the policy as the <strong>default</strong> — this policy applies when no priority-specific policy is set.</li>
    <li>Click <strong>Save</strong>.</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    You can set either target to <code>0</code> to disable that particular timer without affecting the other.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-link text-primary me-2"></i>Linking SLAs to Priorities</h5>
<p class="text-muted mb-2">SLA policies are applied based on a ticket's priority. Link a policy to a priority at <a href="/admin/priorities"><strong>Admin → Settings → Priorities</strong></a> by editing any priority and selecting the desired SLA policy.</p>
<p class="text-muted mb-0">When a ticket is created or its priority changes, OpenHelpDesk automatically assigns the matching SLA policy and (re)calculates the deadlines.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-stopwatch text-primary me-2"></i>How Timers Work</h5>
<p class="text-muted mb-2">Once an SLA is initialised on a ticket:</p>
<ul class="text-muted mb-3">
    <li>Deadlines are calculated from the ticket's creation time.</li>
    <li>The <strong>first response timer</strong> stops when an agent adds a public reply.</li>
    <li>The <strong>resolution timer</strong> stops when the ticket is marked <em>Resolved</em> or <em>Closed</em>.</li>
</ul>
<p class="text-muted mb-0">The ticket view shows a live countdown for both timers. Timers turn <span class="text-warning fw-semibold">amber</span> when within 20% of the deadline and <span class="text-danger fw-semibold">red</span> when breached.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pause-circle text-primary me-2"></i>Pausing &amp; Resuming</h5>
<p class="text-muted mb-2">SLA timers can be paused when a ticket is in <strong>Pending</strong> status (waiting on the requester). When the ticket moves back to <strong>Open</strong> or <strong>In Progress</strong>, the timer automatically resumes.</p>
<p class="text-muted mb-0">Pause and resume events are recorded in the ticket timeline with timestamps so you have a full audit trail.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>SLA Re-initialisation</h5>
<p class="text-muted mb-0">If a ticket's priority is changed after creation, the SLA policy is re-evaluated. If a different policy applies to the new priority, the SLA is re-initialised with new deadlines calculated from the current time. The old SLA record is replaced and a <em>SLA Set</em> event is added to the timeline.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="counted-days">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-calendar-week text-primary me-2"></i>Which Days the SLA Counts</h5>
<p class="text-muted mb-2">Each policy row (per ticket type and priority) has an <strong>"SLA counts on"</strong> control — a Mon&ndash;Sun toggle. Deselect a day and that policy's first-response and resolution timers <strong>freeze on that day</strong>, even if your organisation is open then, so the deadline rolls forward to the next counted day.</p>
<p class="text-muted mb-2">This is finer-grained than the global <strong>Business Hours</strong> schedule, which is shared by every policy and answers a different question ("are we open?"). Counted days answer "should this policy's SLA tick today?" &mdash; useful when, say, a low-priority policy shouldn't burn its clock over the weekend even though the desk is staffed.</p>
<ul class="text-muted mb-0">
    <li>Leaving all seven days selected preserves the previous behaviour, so existing policies are unchanged.</li>
    <li>The chosen days are also honoured when an SLA is paused and resumed, so a never-counted day is never credited back as paused time.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="duration-units">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock text-primary me-2"></i>Typing Durations</h5>
<p class="text-muted mb-2">Every editable duration field in OpenHelpDesk &mdash; the SLA first-response and resolution targets here, plus the <a href="/admin/docs/automations#stale-tickets">stale-ticket thresholds</a>, the stale re-notify gap, and the escalation re-fire cooldown &mdash; accepts a unit suffix:</p>
<ul class="text-muted mb-2">
    <li>Type <code>72h</code>, <code>3d</code> or <code>4320m</code> and they all mean the same thing.</li>
    <li>Combine units too: <code>1d 2h 30m</code>.</li>
    <li>A bare number (no suffix) keeps that field's previous unit, so existing habits still work &mdash; type the unit explicitly when you want to be unambiguous.</li>
</ul>
<p class="text-muted mb-0">Saved values are stored as minutes internally and displayed rolled up into the largest units that fit &mdash; <code>60m</code> shows as <code>1h</code>, <code>80m</code> as <code>1h 20m</code>, and <code>24h</code> as <code>1d</code> (the roll-up is calendar-based: <code>1d</code> = 24h everywhere).</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
