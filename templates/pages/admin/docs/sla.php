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
    <li>Set the <strong>First Response Time</strong> in hours.</li>
    <li>Set the <strong>Resolution Time</strong> in hours.</li>
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

</div><!-- col -->
</div><!-- row -->
