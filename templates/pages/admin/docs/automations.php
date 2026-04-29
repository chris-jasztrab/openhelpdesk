<?php
$layout       = 'app';
$pageTitle    = 'Docs: Automations';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Automations']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Automations &amp; Escalations</h2>
<p class="text-muted mb-4">Reduce manual work by letting LocalDesk act on tickets for you. This page covers four related-but-distinct systems:</p>
<ul class="text-muted mb-4">
    <li><a href="#automation-rules"><strong>Automation Rules</strong></a> — event-based "when X happens, do Y" rules.</li>
    <li><a href="#group-auto-assign"><strong>Group Auto-Assignment</strong></a> — which member of a group picks up a new ticket.</li>
    <li><a href="#escalation-rules"><strong>Escalation Rules</strong></a> — time-based reminders and reassignments via cron.</li>
    <li><a href="#escalation-paths"><strong>Manual Escalation Paths</strong></a> — the per-type chain used by the agent &amp; portal <strong>Escalate</strong> button.</li>
    <li><a href="#stale-tickets"><strong>Stale Ticket Notifications</strong></a> — automatic nag emails when a ticket has been ignored.</li>
</ul>

<h3 id="automation-rules" class="fw-bold mt-5 mb-3">Automation Rules</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>How Automations Work</h5>
<p class="text-muted mb-2">Each automation rule has three parts:</p>
<ol class="text-muted mb-0">
    <li><strong>Trigger</strong> — the event that causes the rule to be evaluated (e.g. ticket created, ticket updated).</li>
    <li><strong>Conditions</strong> — criteria that must be met for the rule to fire (e.g. priority is High, subject contains "urgent").</li>
    <li><strong>Actions</strong> — what happens when conditions are met (e.g. assign to agent, set priority, add tag).</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Creating a Rule</h5>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/automations"><strong>Admin → Automations</strong></a>.</li>
    <li>Click <strong>Add Rule</strong>.</li>
    <li>Give the rule a descriptive name.</li>
    <li>Choose the <strong>trigger</strong> event.</li>
    <li>Add one or more <strong>conditions</strong> using the condition builder. Choose whether <strong>all</strong> or <strong>any</strong> conditions must match.</li>
    <li>Add one or more <strong>actions</strong> to perform.</li>
    <li>Toggle the rule <strong>Active</strong> and click <strong>Save</strong>.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightning text-primary me-2"></i>Available Triggers</h5>
<ul class="text-muted mb-0">
    <li><strong>Ticket Created</strong> — fires when a new ticket is submitted (via portal or admin).</li>
    <li><strong>Ticket Updated</strong> — fires when any field on a ticket is changed.</li>
    <li><strong>Reply Added</strong> — fires when a public reply is posted on a ticket.</li>
    <li><strong>Status Changed</strong> — fires specifically when the ticket status changes.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Available Conditions</h5>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Field</th><th>Operators</th></tr></thead>
    <tbody class="text-muted">
        <tr><td>Subject</td><td>contains, does not contain, equals</td></tr>
        <tr><td>Description</td><td>contains, does not contain</td></tr>
        <tr><td>Status</td><td>is, is not</td></tr>
        <tr><td>Priority</td><td>is, is not</td></tr>
        <tr><td>Assigned To</td><td>is, is not, is unassigned</td></tr>
        <tr><td>Location</td><td>is, is not</td></tr>
        <tr><td>Tag</td><td>includes, does not include</td></tr>
        <tr><td>Requester Email</td><td>contains, equals</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-play-circle text-primary me-2"></i>Available Actions</h5>
<ul class="text-muted mb-0">
    <li><strong>Assign to agent</strong> — assigns the ticket to a specific agent.</li>
    <li><strong>Set priority</strong> — changes the ticket's priority.</li>
    <li><strong>Set status</strong> — changes the ticket's status.</li>
    <li><strong>Add tag</strong> — adds a tag to the ticket.</li>
    <li><strong>Set location</strong> — assigns or changes the ticket's location.</li>
    <li><strong>Add internal note</strong> — posts an internal note with specified text.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sort-numeric-up text-primary me-2"></i>Rule Order &amp; Priority</h5>
<p class="text-muted mb-2">Rules are evaluated in the order they appear in the list. Multiple rules can fire on the same event — all matching rules run in sequence.</p>
<p class="text-muted mb-0">Drag rules in the list to reorder them. Place more specific rules above general ones to ensure correct behaviour when multiple rules could match.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightbulb text-primary me-2"></i>Examples</h5>
<ul class="text-muted mb-0">
    <li><strong>Auto-assign urgent tickets:</strong> Trigger = Ticket Created, Condition = Priority is Critical, Action = Assign to [senior agent].</li>
    <li><strong>Tag IT requests:</strong> Trigger = Ticket Created, Condition = Subject contains "computer" or "laptop", Action = Add tag "hardware".</li>
    <li><strong>Flag unassigned tickets:</strong> Trigger = Ticket Updated, Condition = Status is Open AND assigned to is unassigned, Action = Add internal note "Ticket is unassigned and still open."</li>
</ul>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-2"></i>
    Looking for <em>time-based</em> rules (fire after X hours)? See <a href="#escalation-rules" class="alert-link">Escalation Rules</a> below. Looking for "send a reminder when no-one has touched this ticket in N hours"? See <a href="#stale-tickets" class="alert-link">Stale Ticket Notifications</a>.
</div>
</div>
</div>

<h3 id="group-auto-assign" class="fw-bold mt-5 mb-3">Group Auto-Assignment</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-fill-gear text-primary me-2"></i>How It Works</h5>
<p class="text-muted mb-2">When a new ticket arrives with a <strong>Group</strong> set but no assignee, LocalDesk can pick a member of that group automatically. Each group chooses its own strategy at <a href="/admin/groups"><strong>Admin → Settings → Groups → Edit</strong></a>.</p>
<p class="text-muted mb-0">Auto-assignment runs on portal submissions, the public REST API, email-to-ticket, and the admin "Split Ticket" flow whenever the assignee is left blank but a group is set. Direct manual assignment is never overridden.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>The Five Strategies</h5>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:160px;">Strategy</th><th>Behaviour</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><strong>Manual</strong> <span class="badge bg-secondary ms-1">default</span></td>
            <td>Do nothing — leave the ticket unassigned for an agent to claim. Preserves pre-2.12 behaviour.</td>
        </tr>
        <tr>
            <td><strong>Round Robin</strong></td>
            <td>Rotate sequentially through group members. The next pick is always the agent <em>after</em> whoever was last auto-assigned (tracked on the group, not per-ticket-type). Distribution is even by ticket count.</td>
        </tr>
        <tr>
            <td><strong>Load-Based</strong></td>
            <td>Pick the member with the fewest <em>open</em> (non-resolved, non-closed) tickets. Best when work items vary in length. Ties are broken by user ID for stability.</td>
        </tr>
        <tr>
            <td><strong>Skill-Based</strong></td>
            <td>Pick a member whose declared skills cover <strong>every</strong> skill required by the ticket type. If multiple members qualify, the least-loaded among them wins. Falls back to the configured fallback when nobody qualifies.</td>
        </tr>
        <tr>
            <td><strong>First Available</strong></td>
            <td>Pick a member who has flipped the <em>"I'm available for new tickets"</em> switch on their profile (least-loaded among them). Good for shift / follow-the-sun coverage. Falls back when nobody is available.</td>
        </tr>
        <tr>
            <td><strong>AI Skill-Based</strong> <span class="badge bg-primary ms-1">new</span></td>
            <td>Same as Skill-Based, but instead of reading the ticket type's required-skills, an LLM reads the ticket's actual subject + body and infers which skills it needs. See <a href="/admin/docs/ai">AI Classification</a> for setup. Confidential ticket types are never sent to the AI provider.</td>
        </tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-down-circle text-primary me-2"></i>Fallback Behaviour</h5>
<p class="text-muted mb-2">Skill-Based and First Available can both come up empty (no member has the required skills, or nobody is on duty). When that happens, the group's <strong>Fallback</strong> setting decides what to do next:</p>
<ul class="text-muted mb-0">
    <li><strong>Load-Based</strong> <span class="badge bg-secondary ms-1">default</span> — assign the least-loaded member of the group regardless of skill / availability.</li>
    <li><strong>Round Robin</strong> — rotate through the group ignoring skill / availability.</li>
    <li><strong>Leave Unassigned</strong> — fall through and let an agent pick it up manually.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-tags text-primary me-2"></i>Skills &amp; Availability</h5>
<p class="text-muted mb-2">Two pieces feed Skill-Based and First Available:</p>
<ul class="text-muted mb-3">
    <li><strong>Agent Skills</strong> are managed at <a href="/admin/skills"><strong>Admin → Settings → Agent Skills</strong></a>. Each skill has a name, description, and a <strong>scope</strong> — Global (admin-curated) or owned by a specific group. <a href="/admin/docs/users#group-managers">Group managers</a> can edit skills their group owns and assign them to teammates without admin involvement.</li>
    <li><strong>Required skills per ticket type</strong> are configured on the <a href="/admin/types">Ticket Types</a> form. A ticket type can require zero, one, or several skills — Skill-Based assignment matches members who hold them <em>all</em>.</li>
    <li><strong>"Available for new tickets"</strong> is a per-user toggle on the My Profile page (agent / admin / power user only). Defaults to on. Only First Available reads it; the other strategies ignore it.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Auto-assignment only fires when the ticket already has a group. Portal-created tickets inherit the group from <a href="/admin/types">Ticket Types → Default Group</a>, so make sure types you want auto-routed have a default group set.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Audit &amp; Notifications</h5>
<p class="text-muted mb-0">Every auto-assignment posts an internal timeline entry on the ticket reading <code>Auto-assigned to NAME via STRATEGY</code>. The standard "ticket assigned" emails go out to the chosen agent and to the requester (subject to each user's notification preferences).</p>
</div>
</div>

<h3 id="escalation-rules" class="fw-bold mt-5 mb-3">Escalation Rules</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-header bg-white py-3">
    <h5 class="fw-semibold mb-0"><i class="bi bi-alarm text-danger me-2"></i>Time-Based Escalation Rules</h5>
</div>
<div class="card-body p-4">
<p class="text-muted mb-3">Escalation Rules are <strong>time-based</strong> rules that fire when a ticket has been in a particular state for too long — for example, when a customer hasn't replied for several days. They complement event-based Automations.</p>
<p class="text-muted mb-2">Manage escalation rules at <a href="/admin/settings/escalations"><strong>Admin → Settings → Escalation Rules</strong></a>.</p>

<h6 class="fw-semibold mt-3 mb-2">How Escalation Rules Work</h6>
<ol class="text-muted mb-3">
    <li>A background cron job runs <code>scripts/process-escalations.php</code> periodically (e.g. every hour).</li>
    <li>Each active rule is evaluated against every open ticket.</li>
    <li>If the ticket's conditions are met <em>and</em> the rule has not already fired for that ticket, the defined actions are executed.</li>
    <li>A log entry is created so the same rule doesn't fire again for the same ticket.</li>
</ol>

<h6 class="fw-semibold mt-3 mb-2">Available Conditions</h6>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Condition</th><th>Description</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Hours in status</strong></td><td>The ticket has been in the specified status for at least N hours.</td></tr>
        <tr><td><strong>Status is</strong></td><td>The ticket's current status matches a specific value (e.g. Waiting on Customer).</td></tr>
        <tr><td><strong>Priority is</strong></td><td>Matches tickets of a given priority.</td></tr>
        <tr><td><strong>Assigned to</strong></td><td>Matches tickets assigned to a specific agent (or unassigned).</td></tr>
    </tbody>
</table>
</div>

<h6 class="fw-semibold mt-3 mb-2">Available Actions</h6>
<ul class="text-muted mb-3">
    <li><strong>Notify assigned agent</strong> — sends the assigned agent an escalation alert email with a link to the ticket.</li>
    <li><strong>Notify ticket creator</strong> — sends the requester a customisable reminder email. This is the "Customer Reminder" template found under <a href="/admin/settings/email-templates"><strong>Admin → Settings → Email Templates</strong></a>.</li>
    <li><strong>Set status</strong> — changes the ticket's status automatically.</li>
    <li><strong>Set priority</strong> — bumps or lowers the priority.</li>
    <li><strong>Assign to agent</strong> — reassigns the ticket to a specific agent.</li>
    <li><strong>Add internal note</strong> — posts an internal note on the ticket documenting the escalation.</li>
</ul>

<h6 class="fw-semibold mt-3 mb-2">Customer Reminder Example</h6>
<p class="text-muted mb-0">To send an automatic follow-up when a customer hasn't replied for 3 days:</p>
<ol class="text-muted mb-0">
    <li>Create a new escalation rule and give it a descriptive name (e.g. "3-Day Customer Follow-Up").</li>
    <li>Add condition: <strong>Status is</strong> → <em>Waiting on Customer</em>.</li>
    <li>Add condition: <strong>Hours in status ≥</strong> <em>72</em>.</li>
    <li>Add action: <strong>Notify ticket creator</strong>.</li>
    <li>Save and activate the rule.</li>
</ol>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-2"></i>
    The reminder email content can be customised at <a href="/admin/settings/email-templates" class="alert-link"><strong>Admin → Settings → Email Templates → Customer Reminder</strong></a>. Available tokens include <code>{{first_name}}</code>, <code>{{ticket_id}}</code>, and <code>{{subject}}</code>.
</div>

<h6 class="fw-semibold mt-4 mb-2">Watcher access for off-group recipients</h6>
<p class="text-muted mb-0">When an escalation rule's <strong>Notify user</strong> or <strong>Notify assigned agent</strong> action targets someone outside the ticket's group, that user is automatically added to the ticket's watchers list. They can open the ticket from the email link without hitting a 403 — the same behaviour as the manual <a href="#escalation-paths">escalation paths</a>. Portal users are skipped (portal access uses a separate path).</p>
</div>
</div>

<h3 id="escalation-paths" class="fw-bold mt-5 mb-3">Manual Escalation Paths</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-up-right-circle text-danger me-2"></i>What They Are</h5>
<p class="text-muted mb-2"><strong>Escalation Paths</strong> drive the red <strong>Escalate</strong> button on the ticket view. Each ticket type has its own ordered chain of agents (e.g. Tier 1 → Tier 2 → Manager). When someone clicks Escalate, the ticket reassigns to the next agent in the chain, the previous assignee stays on as a watcher, and the event is logged.</p>
<p class="text-muted mb-0">This is distinct from the time-based <a href="#escalation-rules">Escalation Rules</a> above — paths fire on a button press, rules fire from cron. Use paths when you want a person to <em>decide</em> to escalate; use rules when you want the system to escalate automatically after time elapses.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-ol text-primary me-2"></i>Configuring a Path</h5>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/settings/escalation-paths"><strong>Admin → Settings → Escalation Paths</strong></a>.</li>
    <li>Pick a ticket type from the list.</li>
    <li>Add agents to the chain in order. Each step is a single user (agent, power user, or admin) plus an optional label like "Tier 2 Lead".</li>
    <li>Drag rows to reorder.</li>
    <li>Save — the change is recorded in the audit log.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-play-fill text-primary me-2"></i>Who Can Click Escalate</h5>
<ul class="text-muted mb-0">
    <li><strong>Agents, power users, admins</strong> — see the Escalate button on every ticket they can access. Confirms in a modal before reassigning.</li>
    <li><strong>Portal users (requesters)</strong> — see the Escalate button on tickets they themselves submitted. Useful when they feel the response has stalled. Owner check is enforced server-side; non-owners cannot escalate someone else's ticket.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-skip-forward text-primary me-2"></i>Skipping the Current Assignee</h5>
<p class="text-muted mb-0">If the current assignee already appears in the escalation path (say they <em>are</em> Tier 2), clicking Escalate skips ahead to the step <em>after</em> them rather than re-routing back to an earlier level. This prevents a ticket from looping inside the same tier.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bell text-primary me-2"></i>What Happens On Escalate</h5>
<ul class="text-muted mb-0">
    <li>Ticket is reassigned to the next agent in the chain.</li>
    <li>The previous assignee is added as a <strong>watcher</strong> so they keep email visibility and can still open the ticket even if it leaves their group.</li>
    <li>An entry is added to the <code>ticket_escalations</code> audit table with the from/to agents, step number, optional reason, and who clicked the button.</li>
    <li>The new assignee receives the dedicated <strong>Ticket Escalated</strong> email template.</li>
    <li>An <code>escalated</code> timeline entry shows on the ticket. The portal also shows an <strong>Escalated — Level N</strong> badge to the requester (see the <a href="/admin/docs/portal">Portal docs</a>).</li>
    <li>The "Escalated to Me" agent dashboard card and ticket-list filter surface tickets currently at an escalation level.</li>
</ul>
</div>
</div>

<h3 id="stale-tickets" class="fw-bold mt-5 mb-3">Stale Ticket Notifications</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hourglass-split text-warning me-2"></i>What It Does</h5>
<p class="text-muted mb-2">When a ticket has had <em>no</em> activity for longer than the configured threshold and is in a status that's still waiting on your team, LocalDesk emails the assigned agent (or all group members if unassigned) and reassures the requester that they haven't been forgotten.</p>
<p class="text-muted mb-0">Tickets in <strong>Waiting on Customer</strong>, <strong>Waiting on Third Party</strong>, <strong>Resolved</strong>, or <strong>Closed</strong> are intentionally ignored — the clock only runs when the ball is in your court.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Configuration</h5>
<p class="text-muted mb-2">Settings live at <a href="/admin/settings/stale-tickets"><strong>Admin → Settings → Stale Tickets</strong></a>:</p>
<ul class="text-muted mb-0">
    <li><strong>Stale threshold (hours)</strong> — how long a ticket can sit without an update before it counts as stale. Default <code>72</code> (3 days). Set to <code>0</code> to disable the feature globally.</li>
    <li><strong>Re-notify after (hours)</strong> — minimum gap between repeat stale notifications on the same ticket, so the same nag doesn't fire on every cron run. Default <code>24</code>.</li>
    <li><strong>Notify the assigned agent</strong> — toggle on/off. When the ticket is unassigned, the email goes to every member of the ticket's group instead.</li>
    <li><strong>Notify the requester</strong> — toggle on/off for the "we haven't forgotten you" email.</li>
    <li><strong>Per-type override</strong> — each ticket type can override the threshold under <a href="/admin/types">Ticket Types</a>. Leave the override blank to inherit the global value.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-terminal text-primary me-2"></i>Cron Setup</h5>
<p class="text-muted mb-2">The stale notifier runs on the same cron pattern as the other background jobs. Add this to your server's crontab so it runs hourly:</p>
<code class="d-block bg-light border rounded p-2 small user-select-all mb-3">0 * * * * php /path/to/app/scripts/process-stale-tickets.php &gt;&gt; /path/to/app/storage/logs/stale-tickets.log 2&gt;&amp;1</code>
<p class="text-muted mb-0">A <strong>Run Now</strong> button on the settings page executes the processor immediately — useful when you change the threshold and want to see the effect without waiting an hour. Each run streams its log lines to <code>storage/logs/stale-tickets.log</code> so you can verify it ran.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-check text-primary me-2"></i>De-Duplication</h5>
<p class="text-muted mb-0">Each notification posts an internal <code>stale_notification_sent</code> timeline entry. The processor checks the timeline before sending: if a stale notification was already sent within the re-notify window, the ticket is skipped this run. Replying to or updating the ticket clears its staleness automatically (the threshold is measured from <code>updated_at</code>).</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
