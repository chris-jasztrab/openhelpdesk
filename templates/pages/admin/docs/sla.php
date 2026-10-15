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
<p class="text-muted mb-4">Set response and resolution targets, and track whether each ticket meets them. The SLA pages are under <strong>Settings &rarr; Scheduling</strong>.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>What is an SLA?</h5>
<p class="text-muted mb-2">An SLA (service level agreement) gives a ticket up to two deadlines, counted in <strong>business hours</strong>:</p>
<ul class="text-muted mb-0">
    <li><strong>First Response</strong>: how long staff have to send the first public reply.</li>
    <li><strong>Resolution</strong>: how long staff have to close or resolve the ticket.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="business-hours">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock text-primary me-2"></i>Business Hours &amp; Holidays</h5>
<p class="text-muted mb-2">SLA clocks run only during business hours, so set these first. <strong>Without business hours, no ticket gets SLA deadlines.</strong></p>
<ul class="text-muted mb-2">
    <li><a href="/admin/settings/business-hours"><strong>Business Hours</strong></a>: the <strong>Timezone</strong> and a <strong>Weekly Schedule</strong> (open/close time for each day). This timezone is the only one the SLA uses; the timezone options on the <?= e(label('location.plural', 'Locations')) ?> page don't affect it.</li>
    <li><a href="/admin/settings/holidays"><strong>Holidays</strong></a>: add closed days by date and name. With <strong>Exclude from SLA</strong> on, the clock doesn't run that day; switched off, the day is listed for reference only. Each holiday's switch can be flipped from the list. <strong>Auto-Populate Year</strong> adds a country's public holidays for a chosen year.</li>
    <li><a href="/admin/settings/time"><strong>Server Time</strong></a>: a read-only check that the operating system, database and PHP clocks agree. It gives one overall verdict and, for each mismatch, the command that fixes it. A Business Hours timezone that differs from the server's is fine; the clocks disagreeing with each other is not.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-toggle-on text-primary me-2"></i>Turning SLA Tracking On or Off</h5>
<p class="text-muted mb-2">The <strong>Enable SLA tracking</strong> switch at the top of <a href="/admin/settings/sla-policies"><strong>SLA Policies</strong></a> controls SLA for the whole helpdesk. It is on by default. While it is off:</p>
<ul class="text-muted mb-2">
    <li>New tickets get no deadlines, priority/type/status changes don't touch SLA, and the SLA cron job does nothing.</li>
    <li>SLA badges, the SLA card and the SLA column disappear from ticket views, ticket lists and the Unresolved Tickets report. The SLA Compliance report shows a "disabled" banner.</li>
    <li>The SLA email tokens are empty, and the "Only after SLA breach" Escalate-button mode falls back to always showing the button.</li>
</ul>
<p class="text-muted mb-0">Policies and past SLA data are kept; turn the switch back on at any time.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="targets">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-stopwatch text-primary me-2"></i>Setting Targets</h5>
<p class="text-muted mb-2">Targets are set per <strong>priority</strong>, optionally per <strong>ticket type</strong>, on <a href="/admin/settings/sla-policies"><strong>Settings &rarr; Scheduling &rarr; SLA Policies</strong></a>:</p>
<ul class="text-muted mb-2">
    <li>The <strong>Default</strong> tab has one row per priority, with a <strong>First Response</strong> and a <strong>Resolution</strong> box. These apply to every ticket type that doesn't override them.</li>
    <li>Each ticket type has its own tab. A box left empty there <strong>inherits</strong> the Default value, so the type follows the Default whenever you change it. Fill a box only to override it.</li>
    <li>On the Default tab, an empty box means <strong>no target</strong>. A priority can have just one target (e.g. resolution only); a priority with neither gets no SLA.</li>
    <li>A type's tab lists only the priorities that type offers (its <em>Available Priorities</em> setting).</li>
    <li>Type durations such as <code>90m</code>, <code>8h</code> or <code>2d</code> (see <a href="#duration-units">Typing Durations</a>). The <strong>Rolled up</strong> column shows how each value will be stored.</li>
</ul>
<p class="text-muted mb-0">Click <strong>Save Policies</strong>. Changing a target affects new tickets and tickets whose priority or type changes later; existing deadlines aren't moved.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="counted-days">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-calendar-week text-primary me-2"></i>Which Days the SLA Counts</h5>
<p class="text-muted mb-2">Each priority row has an <strong>SLA counts on</strong> Mon&ndash;Sun toggle. Deselect a day and that row's timers <strong>freeze on that day</strong>, even if you're open, so deadlines roll to the next counted day. Business Hours answers "are we open?"; counted days answer "should this target tick today?" (e.g. keep Low priority off weekends while the desk is staffed).</p>
<ul class="text-muted mb-0">
    <li>All seven days selected means no extra restriction.</li>
    <li>Changing only the days on a type tab saves on its own; you don't need to fill in the times.</li>
    <li>Counted days are honoured when a paused ticket resumes.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="type-hours">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Custom Business Hours for a Ticket Type</h5>
<p class="text-muted mb-0">On a type's tab, tick <strong>Use custom business hours for this type</strong> to give that type its own weekly open/close schedule (useful when one team keeps different hours). The timezone and holidays still come from the global settings. Untick it to go back to the global schedule.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hourglass-split text-primary me-2"></i>How Timers Work</h5>
<ul class="text-muted mb-2">
    <li>When a ticket with a priority is created (from any channel, including email-to-ticket and splits), the matching targets are added to its start time, counting only business hours on counted days and skipping excluded holidays.</li>
    <li>With <strong>Due date follows the SLA</strong> on (the default), the ticket's due date is set to the resolution deadline and moves whenever that deadline moves. A due date set by hand is kept, and moving the due date never changes the SLA. See <a href="/admin/docs/automations#ticket-reminders">Ticket Reminders</a>.</li>
    <li>The <strong>first response</strong> deadline is met by the first public reply from staff. The <strong>resolution</strong> deadline is met when the ticket moves to a closed-bucket status (e.g. Resolved or Closed).</li>
    <li>A ticket is <span class="text-success fw-semibold">On track</span>, then <span class="text-warning fw-semibold">Warning</span> once 80% of a deadline has elapsed, then <span class="text-danger fw-semibold">Breached</span> after it passes.</li>
    <li>Moving into Warning or Breached creates an in-app notification for the assigned agent; a breach can also post to <a href="/admin/docs/teams">Microsoft Teams</a>.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    A breach does not email anyone by itself. To email on breach, create an <a href="/admin/docs/automations#escalation-rules" class="alert-link">Escalation Rule</a> with the condition <strong>SLA State equals Breached</strong> and a notify action, and set a re-fire cooldown. The rule applies to <em>every</em> breached open ticket on its first run, not just new ones.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pause-circle text-primary me-2"></i>Pausing &amp; Resuming</h5>
<p class="text-muted mb-2">Statuses with <strong>Pauses SLA</strong> switched on (on <a href="/admin/settings/ticket-statuses">Settings &rarr; Organization &rarr; Ticket Statuses</a>) stop the clock, typically while you wait on the requester. Out of the box those are Pending, Waiting on Customer and Waiting on Third Party.</p>
<p class="text-muted mb-0">When the ticket leaves a pausing status, both deadlines move out by the business time spent paused. This works the same whether the status is changed on the ticket page, by a list quick-edit, in the portal or floor mode, by an automation, or through the API.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-counterclockwise text-primary me-2"></i>Closing &amp; Reopening</h5>
<p class="text-muted mb-2">Closed and resolved tickets aren't paused; they drop out of SLA tracking. A closed ticket can't newly breach, and its last SLA state is kept, so a ticket that breached before closing still counts as breached in reports.</p>
<p class="text-muted mb-2">What happens on reopen is set by <strong>When a closed ticket is reopened</strong> on the SLA Policies page:</p>
<ul class="text-muted mb-2">
    <li><strong>Keep the original due dates</strong> (default): the old deadlines stand, so a ticket closed long ago is breached at once.</li>
    <li><strong>Resume</strong>: both deadlines move out by the business hours the ticket spent closed.</li>
    <li><strong>Restart both clocks</strong>: a fresh window from the reopen, and a new first response is required.</li>
    <li><strong>Restart the resolution clock only</strong>: a fresh resolution window; the first response already given still counts.</li>
</ul>
<p class="text-muted mb-0">Any <a href="/admin/types">ticket type</a> can override this, and the <a href="/admin/types/matrix">Type Settings Matrix</a> shows each type's setting. Resume needs the time the ticket was closed; tickets closed before that time was recorded keep their original dates.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>Changing Priority or Type</h5>
<p class="text-muted mb-0">When a ticket's priority or type changes, its deadlines are recalculated from the ticket's original SLA start using the targets for the new priority and type. If the new combination has no targets, the ticket's SLA is cleared. A first response already given still counts.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-terminal text-primary me-2"></i>Keeping SLA States Current</h5>
<ul class="text-muted mb-0">
    <li>The <strong>SLA Recalculation</strong> cron job is <strong>required</strong> and runs every 5 minutes; it moves tickets into Warning and Breached as time passes. Get the exact command from <a href="/admin/settings/cron-jobs">Cron Jobs</a>. It can also be triggered over HTTP with a secret token (<code>SLA_CRON_TOKEN</code> in <code>.env</code>). The same job sends <a href="/admin/docs/automations#ticket-reminders">Ticket Reminders</a> (so it counts as an email-sending job) and routes any ticket with no group to the default group.</li>
    <li><strong>Recalculate All</strong> on the SLA Policies page recalculates every active ticket immediately.</li>
    <li>The first time a ticket's state is calculated, no notifications are sent, so putting a backlog under SLA doesn't flood anyone.</li>
    <li>Tickets marked <strong>SLA-exempt</strong> (e.g. imported with "Exclude from SLA") never get a clock, even if they are edited later.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="duration-units">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock text-primary me-2"></i>Typing Durations</h5>
<p class="text-muted mb-2">The SLA targets here, plus the escalation re-fire cooldown, accept unit suffixes:</p>
<ul class="text-muted mb-2">
    <li><code>72h</code>, <code>3d</code> and <code>4320m</code> mean the same thing, and units can be combined: <code>1d 2h 30m</code>.</li>
    <li>A bare number uses the field's own unit: minutes for SLA targets, hours for the escalation cooldown. Add the unit to be unambiguous.</li>
</ul>
<p class="text-muted mb-0">Values are stored in minutes and shown rolled up (<code>60m</code> shows as <code>1h</code>, <code>80m</code> as <code>1h 20m</code>, <code>24h</code> as <code>1d</code>). Here <code>1d</code> always means 24 hours. SLA time only elapses during business hours, so a <code>1d</code> target means 24 <em>business</em> hours.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
