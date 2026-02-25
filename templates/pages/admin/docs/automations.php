<?php
$layout       = 'app';
$pageTitle    = 'Docs: Automations';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Automations']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Automations</h2>
<p class="text-muted mb-4">Create rules that automatically act on tickets when conditions are met — reducing manual work for your team.</p>

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
    <li><strong>Escalate stale tickets:</strong> Trigger = Ticket Updated, Condition = Status is Open AND assigned to is unassigned, Action = Add internal note "Ticket is unassigned and still open."</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-header bg-white py-3">
    <h5 class="fw-semibold mb-0"><i class="bi bi-alarm text-danger me-2"></i>Escalation Rules</h5>
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
</div>
</div>

</div><!-- col -->
</div><!-- row -->
