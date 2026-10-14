<?php
$layout       = 'app';
$pageTitle    = 'Help: Creating Tickets';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Creating Tickets']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Creating Tickets</h2>
<p class="text-muted mb-4">Log a ticket yourself &mdash; for your own issue, or for someone who phoned or walked up to the desk.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-square text-primary me-2"></i>Opening the Form</h5>
<p class="text-muted mb-2">Click <strong><i class="bi bi-plus-lg"></i> New Ticket</strong> on the <a href="/agent/tickets">ticket list</a>, or go straight to the <a href="/agent/tickets/create">New Ticket</a> form. Fill in the cards from top to bottom and click <strong>Create Ticket</strong>.</p>
<p class="text-muted mb-0">On a tablet or phone you can also use the quick <i class="bi bi-plus-lg"></i> button in <a href="/agent/help/floor">Floor Mode</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ui-checks text-primary me-2"></i>The Form</h5>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:30%;">Section</th><th>What to fill in</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Template&hellip;</strong> (top-right)</td><td>If any ticket templates exist, pick one to fill in the subject, description, type and priority for you. Edit anything before you submit.</td></tr>
        <tr><td><strong>Ticket Details</strong></td><td>Subject and description. You can paste or drop images straight into the description.</td></tr>
        <tr><td><strong>Classification &amp; Details</strong></td><td>Type, status, due date, location, priority, tags, attachments and any extra fields. <strong>Pick the Type first</strong> &mdash; it decides which fields appear, which are required, and which priorities you can choose.</td></tr>
        <tr><td><strong>Service level targets</strong></td><td>Once a type and priority are chosen, this line shows the response and resolution targets that will apply (if your organisation uses SLAs).</td></tr>
        <tr><td><strong>Assignment</strong></td><td><strong>Assign To</strong> and <strong>Group</strong>. Picking a type narrows <em>Assign To</em> to that type's group; choosing a group yourself takes priority. Leave them blank and the ticket is routed automatically.</td></tr>
        <tr><td><strong>On Behalf Of</strong></td><td>See below.</td></tr>
        <tr><td><strong>CC</strong></td><td>Type two or more letters of a name or email, then click a match or press Enter, Tab, comma or semicolon. Click the &times; on a name to remove it. CC'd people get email updates on the ticket.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-telephone text-primary me-2"></i>Filing a Ticket On Behalf Of Someone</h5>
<p class="text-muted mb-2">Leave <strong>On Behalf Of</strong> blank to submit the ticket as yourself. To log a ticket for someone else, search for them by name or email and pick them from the list.</p>
<ul class="text-muted mb-0">
    <li>They become the requester: they own the ticket and get the confirmation email, replies and any satisfaction survey.</li>
    <li>You're recorded as the person who filed it. The ticket's details show <em>&ldquo;Filed by <em>you</em> on their behalf&rdquo;</em> to staff; the requester simply sees their own ticket.</li>
    <li>The person needs an existing account. If you can't find them, ask an administrator to add them.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-files text-primary me-2"></i>Duplicate Warning</h5>
<p class="text-muted mb-2">For some ticket types, the helpdesk checks for similar open tickets when you click <strong>Create Ticket</strong>. If it finds any, it shows them with a <strong>Click here to see this ticket</strong> button so you can check without leaving the form. Then choose:</p>
<ul class="text-muted mb-0">
    <li><strong>Create anyway &mdash; This is a Different Issue</strong> &mdash; submit your ticket as normal.</li>
    <li><strong>Let me edit this ticket first.</strong> &mdash; go back to the form.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-floppy text-primary me-2"></i>Drafts</h5>
<p class="text-muted mb-0">The form saves a draft as you type &mdash; including the type, fields, tags, CC and On Behalf Of. If you leave and come back (even on another computer), you'll see <em>&ldquo;Restored your unsent ticket draft&rdquo;</em> with a <strong>Discard draft</strong> link. The draft is removed when you create the ticket. Unused drafts are cleared after 90 days.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send-check text-primary me-2"></i>After You Click Create Ticket</h5>
<ul class="text-muted mb-0">
    <li><strong>Undo</strong> &mdash; if your organisation has turned on undo send, a small notice counts down (<em>&ldquo;Creating ticket in 10s&hellip; Undo&rdquo;</em>). Click <strong>Undo</strong> or press <kbd>Esc</kbd> to stop and go back to the form. Nothing is sent until the countdown ends.</li>
    <li><strong>Routing</strong> &mdash; the ticket gets its group, assignee and SLA automatically if you left those to the system.</li>
    <li><strong>What happens next</strong> &mdash; if SLA targets apply, a box shows when the first response and resolution are due.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
