<?php
$layout       = 'app';
$pageTitle    = 'Help: SLAs & Response Times';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'SLAs & Response Times']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">SLAs &amp; Response Times</h2>
<p class="text-muted mb-4">A plain-English guide to the clocks you see on tickets: what they mean, how they run, and how to stay on track.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>What is an SLA?</h5>
<p class="text-muted mb-2">An <strong>SLA</strong> &mdash; Service Level Agreement &mdash; is a promise about <em>how quickly</em> a ticket gets attention. Instead of "we'll get to it eventually," an SLA puts a clock on two moments:</p>
<ul class="text-muted mb-2">
    <li><strong>First Response</strong> &mdash; how long after a ticket arrives before an agent sends the requester their first public reply.</li>
    <li><strong>Resolution</strong> &mdash; how long after a ticket arrives before it must be resolved (or closed).</li>
</ul>
<p class="text-muted mb-0">Each ticket can have one or both of these clocks. You don't set them &mdash; they're attached automatically based on the ticket's priority (and sometimes its type). Your job is simply to reply and resolve before the clock runs out.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bullseye text-primary me-2"></i>Why they exist</h5>
<p class="text-muted mb-2">SLAs keep the queue fair and predictable:</p>
<ul class="text-muted mb-0">
    <li><strong>Requesters know what to expect.</strong> An urgent outage and a "nice to have" request get different, agreed-upon response windows.</li>
    <li><strong>Nothing quietly rots.</strong> A ticket that's approaching its deadline lights up so it can't be forgotten at the bottom of the list.</li>
    <li><strong>The team can measure itself.</strong> Managers can see how often the desk hits its targets and where the pressure points are (the <a href="/admin/reports/sla">SLA Compliance report</a>, if you have access).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hourglass-split text-primary me-2"></i>How the clock is measured</h5>
<p class="text-muted mb-2">SLA deadlines are counted from the moment the ticket was <strong>created</strong> &mdash; but they don't tick 24/7. The clock only advances during your organisation's <strong>business hours</strong>, so a "4 hour first response" target set at 4:55&nbsp;PM doesn't quietly expire overnight.</p>
<p class="text-muted mb-2">Time is <strong>not</strong> counted when:</p>
<ul class="text-muted mb-2">
    <li>The desk is closed (outside business hours, or on a weekend / non-working day).</li>
    <li>The day is a holiday that's been marked as excluded from SLA.</li>
    <li>The policy is set to skip that weekday entirely (for example, a low-priority policy that never burns its clock on weekends).</li>
    <li>The ticket is <strong>paused</strong> &mdash; see <a href="#pausing">Pausing &amp; resuming</a> below.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    This is why a ticket that's been "open for two days" on the calendar might only be a few business hours into its SLA. The countdown you see on the ticket already accounts for all of this &mdash; trust the timer, not the calendar age.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-traffic-light text-primary me-2"></i>Reading the SLA badge</h5>
<p class="text-muted mb-2">Every ticket with an SLA shows a coloured state, both on the ticket itself and in the <strong>SLA</strong> column of your ticket lists. There are three states:</p>
<ul class="text-muted mb-2">
    <li><span class="badge bg-success">On track</span> &mdash; comfortably ahead of the deadline. Nothing to do beyond normal work.</li>
    <li><span class="badge bg-warning text-dark">Warning</span> &mdash; you've used up about 80% of the allotted time (roughly the last 20% remaining). This ticket should jump the queue.</li>
    <li><span class="badge bg-danger">Breached</span> &mdash; the deadline has passed. The promise was missed; resolve it as soon as possible.</li>
</ul>
<p class="text-muted mb-0">On the ticket's <strong>SLA card</strong> (right-hand column) the same colours apply to each countdown: it turns <strong class="text-warning">amber</strong> as the deadline nears and <strong class="text-danger">red</strong> once it's breached.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-circle text-primary me-2"></i>What stops each clock</h5>
<ul class="text-muted mb-0">
    <li>The <strong>First Response</strong> clock stops the instant an agent posts the ticket's first <strong>public reply</strong>. Internal notes don't count &mdash; the requester has to actually hear from you.</li>
    <li>The <strong>Resolution</strong> clock stops when the ticket is marked <strong>Resolved</strong> or <strong>Closed</strong>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightning-charge text-primary me-2"></i>How an SLA gets attached &amp; updated</h5>
<ul class="text-muted mb-2">
    <li><strong>On creation</strong> &mdash; when a ticket is created, the system looks at its priority (and type) and applies the matching policy, calculating both deadlines automatically.</li>
    <li><strong>When priority or type changes</strong> &mdash; if you change a ticket's <strong>priority</strong> or <strong>type</strong>, a different policy may apply. The deadlines are recalculated from the ticket's original creation time to match the new policy.</li>
    <li><strong>Continuously</strong> &mdash; a background job re-checks open tickets so the badge flips to <em>Warning</em> or <em>Breached</em> on its own, even if nobody has the ticket open.</li>
</ul>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    Bumping a ticket's priority is the normal way to give something a tighter SLA &mdash; but be aware it resets the deadlines against the new target, so a raised priority can immediately show <em>Warning</em> or <em>Breached</em> if the ticket is already old.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bell text-primary me-2"></i>How you get warned</h5>
<p class="text-muted mb-2">You don't have to babysit the timer. If a ticket is <strong>assigned to you</strong>, you'll get an in-app notification the moment it crosses into:</p>
<ul class="text-muted mb-0">
    <li><strong>Warning</strong> &mdash; "SLA deadline approaching" appears in your notifications feed.</li>
    <li><strong>Breached</strong> &mdash; "SLA breached" appears, and (if Microsoft Teams alerts are configured) a message is posted to the team channel.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="pausing">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pause-circle text-primary me-2"></i>Pausing &amp; resuming (waiting on the requester)</h5>
<p class="text-muted mb-2">It's not fair for your clock to keep running while you're waiting on someone else. So the SLA timer automatically <strong>pauses</strong> whenever you move a ticket to a status flagged as "Pauses SLA" &mdash; out of the box that's <em>Pending</em>, <em>Waiting on Customer</em>, and <em>Waiting on Third Party</em>.</p>
<p class="text-muted mb-0">The moment the ticket moves back to an active status (such as <em>Open</em> or <em>In Progress</em>), the clock resumes and the deadline is pushed out by however long it was paused. Every pause and resume is stamped into the ticket timeline, so there's a full record of where the time went.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-geo text-primary me-2"></i>Where you'll see SLAs</h5>
<ul class="text-muted mb-0">
    <li><strong>Ticket lists</strong> &mdash; the <strong>SLA</strong> column shows each ticket's state at a glance; sort or filter by it to work the most at-risk tickets first.</li>
    <li><strong>The ticket view</strong> &mdash; the SLA card in the right column shows both countdowns live. See <a href="/agent/help/working-tickets">Working on Tickets</a>.</li>
    <li><strong>The wallboard</strong> &mdash; "SLA breached" is one of the live KPI tiles you can put on the <a href="/agent/help/wallboard">Live Wallboard</a>.</li>
    <li><strong>Your notifications</strong> &mdash; warning and breach alerts for tickets assigned to you.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightbulb text-primary me-2"></i>Tips to stay on track</h5>
<ul class="text-muted mb-0">
    <li><strong>Sort by SLA.</strong> Work amber and red tickets before green ones, regardless of when they arrived.</li>
    <li><strong>A quick reply beats silence.</strong> Even an acknowledgement stops the First Response clock and buys goodwill &mdash; you don't need the full answer yet.</li>
    <li><strong>Use the waiting statuses.</strong> If the ball is in the requester's court, set the ticket to a pausing status so your clock isn't punished for their delay.</li>
    <li><strong>Check priority.</strong> If a ticket is genuinely more (or less) urgent than it looks, adjusting its priority applies the right SLA.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-gear text-primary me-2"></i>Who sets the targets?</h5>
<p class="text-muted mb-0">SLA policies, business hours, holidays, and which priorities get which targets are all configured by administrators. If you think a target is wrong for the kind of work you handle, raise it with an admin &mdash; the full configuration guide lives at <a href="/admin/docs/sla">Admin &rarr; Docs &rarr; SLA Policies</a> (visible if you have admin access).</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
