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
<p class="text-muted mb-4">Let the helpdesk act on tickets for you. Everything here is under <strong>Settings &rarr; Automation</strong>:</p>
<ul class="text-muted mb-4">
    <li><a href="#automation-rules"><strong>Automation Rules</strong></a>: "when a ticket is created or updated and matches X, do Y".</li>
    <li><a href="#group-auto-assign"><strong>Group Auto-Assignment</strong></a>: which member of a group picks up a new ticket.</li>
    <li><a href="#escalation-rules"><strong>Escalation Rules</strong></a>: time-based reminders and reassignments, run by cron.</li>
    <li><a href="#escalation-paths"><strong>Escalation Paths</strong></a>: the per-type chain behind the <strong>Escalate</strong> button.</li>
    <li><a href="#ticket-reminders"><strong>Ticket Reminders</strong></a>: due dates that follow the SLA, and owner emails for overdue or quiet tickets.</li>
    <li><a href="#oof-coverage"><strong>Out of Office</strong></a>: cover tickets of agents who are away, read from Outlook.</li>
    <li><a href="#recurring-tickets"><strong>Recurring Tickets</strong></a> and <a href="#scheduled-reports"><strong>Scheduled Reports</strong></a>.</li>
    <li><a href="#cron-jobs"><strong>Cron Jobs</strong></a>: the background jobs that power all of the above.</li>
</ul>

<h3 id="automation-rules" class="fw-bold mt-5 mb-3">Automation Rules</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>How Automations Work</h5>
<p class="text-muted mb-2">Manage rules at <a href="/admin/settings/automations"><strong>Settings &rarr; Automation &rarr; Automations</strong></a> (needs <strong>Manage automations &amp; escalations</strong>). Click <strong>Add Automation</strong> and give the rule a <strong>Name</strong>, a <strong>Trigger</strong>, conditions and actions, then tick <strong>Enabled</strong> and save.</p>
<ul class="text-muted mb-0">
    <li><strong>Ticket Created</strong> runs on every new ticket from any channel. It runs after AI routing and classification and before auto-assignment, so a rule that sets the group decides who gets auto-assigned.</li>
    <li><strong>Ticket Updated</strong> runs when a ticket's properties change: the Update Ticket panel, list quick-edits (status, priority, type, group, assignee) and floor-mode actions. Replies don't trigger it.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-funnel text-primary me-2"></i>Conditions</h5>
<p class="text-muted mb-2">Conditions sit in one or more <strong>groups</strong>. The rule fires if <strong>any</strong> group matches, and each group is set to match <strong>ALL (AND)</strong> or <strong>ANY (OR)</strong> of its conditions, so you can build "(A and B) or C".</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Field</th><th>Operators</th></tr></thead>
    <tbody class="text-muted">
        <tr><td>Ticket Type, Priority, Status, <?= e(label('location.singular', 'Location')) ?>, Group, Assigned To</td><td>is, is not, is empty, is not empty</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-play-circle text-primary me-2"></i>Actions</h5>
<ul class="text-muted mb-2">
    <li><strong>Assign to group</strong> and <strong>Assign to agent</strong>.</li>
    <li><strong>Set ticket type</strong>: e.g. match on Group to stamp a type on tickets that AI routed from a "No Wrong Door" type, so type-based rules, reports and SLA apply. SLA deadlines are recalculated.</li>
    <li><strong>Set priority</strong> and <strong>Set status</strong>.</li>
    <li><strong>Add tag</strong>.</li>
    <li><strong>CC user</strong>: adds a person as a CC; they get the "you were added as a CC" email.</li>
</ul>
<p class="text-muted mb-0">Every action writes a timeline entry on the ticket.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sort-numeric-up text-primary me-2"></i>Order, Run Now &amp; Safety</h5>
<ul class="text-muted mb-0">
    <li>All matching rules run, top to bottom; each rule sees the changes made by the rules above it. Drag rules by the grip to reorder.</li>
    <li>Toggle a rule on or off from the list without editing it.</li>
    <li><strong>Run Now</strong> applies a rule to every existing open ticket that matches it now. Use it carefully: it can change many tickets at once.</li>
    <li>If a rule points at something that was later deleted (a type, group, agent or <?= e(strtolower(label('location.singular', 'location'))) ?>), the editor flags that dropdown red with "previous choice was deleted, pick again" and won't save until you choose. Until it's fixed, the broken action is skipped and noted on the ticket timeline; the rest of the rule still runs.</li>
</ul>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-2"></i>
    Need something to happen after a period of time? Use <a href="#escalation-rules" class="alert-link">Escalation Rules</a>. For "past its due date" or "the customer hasn't heard from us in N days" reminders, use <a href="#ticket-reminders" class="alert-link">Ticket Reminders</a>.
</div>
</div>
</div>

<h3 id="group-auto-assign" class="fw-bold mt-5 mb-3">Group Auto-Assignment</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-fill-gear text-primary me-2"></i>How It Works</h5>
<p class="text-muted mb-2">When a new ticket has a <strong>group</strong> but no assignee, the helpdesk can pick a member automatically. Each group chooses an <strong>Auto-Assignment Strategy</strong> on its edit form at <a href="/admin/groups"><strong>Settings &rarr; Organization &rarr; Groups</strong></a>.</p>
<p class="text-muted mb-0">It runs on every creation path (portal, staff New Ticket, email-to-ticket, API, floor mode, splits and recurring tickets). The order is AI group routing &rarr; AI skill classification &rarr; Ticket Created automations &rarr; default-group safety net &rarr; auto-assignment. A ticket that already has an assignee is never reassigned.</p>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-diagram-3 me-2"></i>
    Diagrams of every strategy and of the full routing flow are in <a href="/admin/docs/flows" class="alert-link"><strong>Docs &rarr; Assignment Flows</strong></a>.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>The Strategies</h5>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:160px;">Strategy</th><th>Behaviour</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><strong>Manual</strong> <span class="badge bg-secondary ms-1">default</span></td>
            <td>Leave the ticket unassigned for an agent to claim.</td>
        </tr>
        <tr>
            <td><strong>Round Robin</strong></td>
            <td>Rotate through the group's members in turn, so ticket counts even out.</td>
        </tr>
        <tr>
            <td><strong>Load-Based</strong></td>
            <td>Pick the member with the fewest open tickets. Best when work varies in size.</td>
        </tr>
        <tr>
            <td><strong>Skill-Based</strong></td>
            <td>Pick a member whose skills cover <strong>every</strong> skill the ticket type requires (least-loaded if several qualify).</td>
        </tr>
        <tr>
            <td><strong>AI Skill-Based</strong></td>
            <td>Like Skill-Based, but the AI reads the ticket and decides which skills it needs. Falls back when AI is off or not confident. See <a href="/admin/docs/ai">AI Classification</a>.</td>
        </tr>
        <tr>
            <td><strong>First Available</strong></td>
            <td>Pick a member who has the app open right now (least-loaded among them). Background tabs and minimised windows count. See <a href="/admin/docs/users">Online Presence</a> and <a href="/admin/users/online">Who's Online</a>.</td>
        </tr>
    </tbody>
</table>
</div>
<p class="text-muted mt-3 mb-0"><strong>If no agent matches</strong>: when Skill-Based, AI Skill-Based or First Available find nobody, the group's fallback decides: <strong>Load-Based</strong> (default), <strong>Round Robin</strong> or <strong>Leave unassigned</strong>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-tags text-primary me-2"></i>Skills &amp; Groups to Set Up</h5>
<ul class="text-muted mb-2">
    <li><strong>Agent Skills</strong> at <a href="/admin/skills">Settings &rarr; Organization &rarr; Agent Skills</a>, global or owned by a group (whose <a href="/admin/docs/users#group-managers">managers</a> can maintain them).</li>
    <li><strong>Required skills</strong> on each <a href="/admin/types">ticket type</a>.</li>
    <li>A <strong>Default Group</strong> on each ticket type: new tickets of that type land in that group.</li>
</ul>
<p class="text-muted mb-0">Each auto-assignment adds the timeline entry <code>Auto-assigned to NAME via STRATEGY</code>, and the normal "ticket assigned" emails go to the agent and the requester (subject to notification settings).</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-check text-success me-2"></i>The "No Group" Safety Net</h5>
<p class="text-muted mb-2">No ticket is left without a group:</p>
<ol class="text-muted mb-2">
    <li>At creation the group is the one chosen, else the ticket type's Default Group, else the system <strong>Default Group</strong>, else the first group.</li>
    <li>If AI and automations still leave it without one, it goes to the system Default Group with an internal timeline note.</li>
    <li>The SLA Recalculation job (every 5 minutes) moves any remaining ungrouped open tickets into the Default Group, adds a timeline note and logs a warning.</li>
</ol>
<p class="text-muted mb-0">Set the Default Group at <a href="/admin/settings/ticket-routing"><strong>Settings &rarr; Organization &rarr; Ticket Routing</strong></a>. A good choice is a Triage / Service Desk group with Load-Based or First Available assignment, so the catch-all queue still reaches a person.</p>
</div>
</div>

<h3 id="escalation-rules" class="fw-bold mt-5 mb-3">Escalation Rules</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-alarm text-danger me-2"></i>Time-Based Escalation Rules</h5>
<p class="text-muted mb-2">Escalation Rules act on open tickets that have been in a state for too long, e.g. "no update for 48 hours" or "SLA breached". Manage them at <a href="/admin/settings/escalations"><strong>Settings &rarr; Automation &rarr; Escalation Rules</strong></a> with <strong>Add Rule</strong>.</p>
<ul class="text-muted mb-3">
    <li>The <strong>Escalation Rules</strong> cron job evaluates every enabled rule, in order, every 15 minutes against tickets in Open, In Progress, Pending, Waiting on Customer or Waiting on Third Party (custom statuses aren't checked). <strong>Run Now</strong> on the page runs it immediately.</li>
    <li>All conditions must match (AND). A rule with no conditions matches every one of those tickets.</li>
    <li><strong>Re-fire cooldown</strong>: <code>0</code> fires once per ticket ever; a duration such as <code>12h</code> or <code>2d</code> lets it fire again after that gap (a bare number means hours).</li>
</ul>
<div class="row g-3 mb-3">
<div class="col-md-6">
<h6 class="fw-semibold mb-2">Conditions</h6>
<ul class="text-muted small mb-0">
    <li><strong>SLA State</strong>: equals / not equals On Track, Warning, Breached</li>
    <li><strong>Hours open (since created)</strong>: greater than N</li>
    <li><strong>Hours since last update</strong>: greater than N</li>
    <li><strong>Hours in current status</strong>: greater than N</li>
    <li><strong>Is assigned</strong>: Yes / No</li>
    <li><strong>Priority</strong>, <strong>Status</strong>: equals / not equals</li>
    <li><strong>Group</strong>: equals / not equals / is empty / is not empty</li>
</ul>
</div>
<div class="col-md-6">
<h6 class="fw-semibold mb-2">Actions (run in order)</h6>
<ul class="text-muted small mb-0">
    <li><strong>Set priority</strong>, <strong>Set group</strong>, <strong>Set status</strong></li>
    <li><strong>Assign to</strong> (or leave empty to unassign)</li>
    <li><strong>Notify specific user</strong> / <strong>Notify assigned agent</strong>: sends the Escalation Alert email</li>
    <li><strong>Send reminder email to customer</strong>: sends the Customer Reminder email to the requester</li>
    <li><strong>Add internal note</strong></li>
</ul>
</div>
</div>
<h6 class="fw-semibold mt-3 mb-2">Example: 3-day customer follow-up</h6>
<ol class="text-muted mb-3">
    <li>Add a rule named "3-Day Customer Follow-Up".</li>
    <li>Conditions: <strong>Status</strong> equals <em>Waiting on Customer</em>, and <strong>Hours in current status</strong> greater than <em>72</em>.</li>
    <li>Action: <strong>Send reminder email to customer</strong>. Set the cooldown to <code>3d</code> to repeat every three days, or <code>0</code> for a single reminder.</li>
</ol>
<p class="text-muted mb-2">Reword the emails on the <strong>Escalation Alert</strong> and <strong>Customer Reminder</strong> tabs of <a href="/admin/settings/email-templates">Email Templates</a>.</p>
<p class="text-muted mb-2"><strong>Notify</strong> actions also add the notified staff member as a watcher (except on confidential tickets), so they can open the ticket from the email even if it isn't in their group. Recipients who opted out of <em>Escalation alerts</em> aren't emailed.</p>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    A new rule acts on <em>every</em> open ticket that already matches, on its very next run. Before enabling a notify rule (especially one on SLA State), check how many tickets match, or use <a href="#cron-jobs" class="alert-link">Dry run</a> on the Cron Jobs page.
</div>
</div>
</div>

<h3 id="escalation-paths" class="fw-bold mt-5 mb-3">Escalation Paths</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-up-right-circle text-danger me-2"></i>What They Are</h5>
<p class="text-muted mb-2">An <strong>Escalation Path</strong> is an ordered chain of people for a ticket type (e.g. Tier 1 &rarr; Tier 2 &rarr; Supervisor). It drives the red <strong>Escalate</strong> button: a person decides to escalate. Escalation Rules, by contrast, escalate automatically after time passes.</p>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/settings/escalation-paths"><strong>Settings &rarr; Automation &rarr; Escalation Paths</strong></a> and pick a ticket type.</li>
    <li>Add people in order (top row = Level 1), each with an optional label such as "Branch Supervisor". Drag rows to reorder.</li>
    <li>Save. The change is audit-logged.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye-slash text-primary me-2"></i>Who Sees the Escalate Button, and When</h5>
<ul class="text-muted mb-2">
    <li><strong>Staff</strong> see it on tickets they can access. <strong>Requesters</strong> see it on tickets they submitted themselves (not on tickets they see through <?= e(label('location.singular', 'Location')) ?> Ticket Visibility).</li>
    <li>It is hidden on tickets whose type has no path, and greyed out on closed or merged tickets and at the top of the chain.</li>
    <li><strong>Escalate Button Visibility</strong> (top of the Escalation Paths page): <strong>Always show</strong> (default) or <strong>Only after SLA breach</strong>. The breach-only mode falls back to always showing the button when SLA tracking is off.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bell text-primary me-2"></i>What Happens on Escalate</h5>
<ul class="text-muted mb-0">
    <li>A confirmation shows exactly who the ticket will go to (name, level and label) and asks for an optional reason.</li>
    <li>The ticket is reassigned to the next person. If the current assignee is already on the path, it skips to the step after them, so a ticket never loops back down.</li>
    <li>The previous assignee becomes a watcher, keeping access even if the ticket leaves their group.</li>
    <li>The new assignee gets the <strong>Ticket Escalated</strong> email. The ticket shows its escalation level and an Escalation History card, and requesters see an <strong>Escalated &mdash; Level N</strong> badge.</li>
    <li>Agents find tickets escalated to them via the <strong>Escalated to Me</strong> dashboard card and list filter.</li>
</ul>
</div>
</div>

<h3 id="ticket-reminders" class="fw-bold mt-5 mb-3">Ticket Reminders</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-alarm text-warning me-2"></i>What It Does</h5>
<p class="text-muted mb-2">Ticket Reminders keep customers informed by nudging the <strong>ticket owner</strong> to contact them; nothing here emails the customer automatically. Configure it at <a href="/admin/settings/ticket-reminders"><strong>Settings &rarr; Automation &rarr; Ticket Reminders</strong></a> (needs <strong>Manage automations &amp; escalations</strong>). It replaces the old Stale Tickets page.</p>
<ul class="text-muted mb-0">
    <li>Tickets in <strong>Waiting on Customer</strong> are skipped.</li>
    <li>Owners who turned off "a ticket is assigned to me" emails on their profile don't get these reminders.</li>
    <li>Each reminder adds an internal <strong>Overdue Reminder Sent</strong> or <strong>Update Reminder Sent</strong> entry to the timeline, which is also how duplicates are prevented.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-calendar-event text-primary me-2"></i>Due Dates</h5>
<ul class="text-muted mb-0">
    <li><strong>Due date follows the SLA</strong> (on by default): a ticket's due date is set to its <a href="/admin/docs/sla">SLA</a> resolution date, and moves with it when the SLA moves (a pause ends, the ticket is reopened, or its priority or type changes). A due date someone set by hand is never overwritten.</li>
    <li><strong>Ask for a customer reply when a due date moves past the SLA date</strong> (on by default): the <strong>Update Ticket</strong> panel on the ticket page has a <strong>Due Date</strong> field with the <em>SLA target</em> shown under it. Saving a later date opens <em>New due date is past the SLA target</em>, with a reply to the customer already drafted. <strong>Send reply &amp; save</strong> posts it as a normal public reply (to the requester, CCs and watchers) and saves the date; <strong>Save without replying</strong> just saves the date; <strong>Cancel</strong> changes nothing.</li>
    <li>A due date changed on the ticket page is logged as an internal <strong>Due Date Changed</strong> timeline entry. Moving the due date never changes the SLA itself, so SLA reports stay accurate.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-exclamation text-primary me-2"></i>Owner Reminder Emails</h5>
<ul class="text-muted mb-3">
    <li><strong>Email the owner when a ticket passes its due date</strong> (off by default): sent once, the day after the due date, if the ticket is still open. Moving the due date later sets up a new reminder for the new date.</li>
    <li><strong>Email the owner when the customer hasn't heard from us in N days</strong> (off by default; N is 1&ndash;365, default 15): counted from the last public reply by staff, or from when the ticket was opened if nobody has replied. Any public reply resets it. It repeats every N days while the silence continues.</li>
    <li><strong>If a ticket has no owner, email its group's manager(s) instead</strong> (off by default): managers are the group members ticked <strong>Manager</strong> under <a href="/admin/groups">Groups</a>. With this off, unassigned tickets get no reminder.</li>
</ul>
<p class="text-muted mb-0">Reword the emails on the <strong>Overdue Reminder</strong> and <strong>Update Due Reminder</strong> tabs of <a href="/admin/settings/email-templates">Email Templates</a>. Both offer <code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{user_name}}</code> (the recipient), <code>{{ticket_id}}</code>, <code>{{subject}}</code>, <code>{{requester}}</code>, <code>{{due_date}}</code>, <code>{{days_since_contact}}</code> and <code>{{group}}</code>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-terminal text-primary me-2"></i>Coverage &amp; Running It</h5>
<ul class="text-muted mb-2">
    <li><strong>New tickets only</strong> (default): only tickets created after you save this setting get reminders; the backlog is left alone. Until the page has been saved once, no reminders go out. Saving again keeps the original start date.</li>
    <li><strong>All open tickets</strong>: includes older tickets, so the first run can email about every open ticket that is already overdue or quiet.</li>
</ul>
<p class="text-muted mb-2">Reminders run inside the <strong>SLA Recalculation</strong> cron job every 5 minutes, so there is no separate cron line. Its log line reports, for example, <code>Ticket reminders: 0 overdue, 0 update due.</code> If your crontab still has the old hourly <code>scripts/process-stale-tickets.php</code> line, remove it; that script no longer exists.</p>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    Before choosing <em>All open tickets</em> on an established helpdesk, use <strong>Dry run</strong> on <a href="/admin/settings/cron-jobs" class="alert-link">Cron Jobs</a> &rarr; SLA Recalculation to see how many emails it would send.
</div>
</div>
</div>

<h3 id="oof-coverage" class="fw-bold mt-5 mb-3">Out-of-Office Coverage</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-x text-primary me-2"></i>What It Does</h5>
<p class="text-muted mb-2">When an agent turns on their Outlook automatic replies, their tickets shouldn't sit untouched. A background job reads each group member's out-of-office state through Microsoft Graph. For each active ticket whose agent is away, it either:</p>
<ul class="text-muted mb-2">
    <li><strong>Reassigns</strong> the ticket to an available member of the same group; or</li>
    <li><strong>Auto-replies to the requester</strong> once, with the agent's out-of-office message, when there is nobody to reassign to (a one-person group, or everyone away). The ticket stays open.</li>
</ul>
<p class="text-muted mb-0">An unassigned ticket is only picked up when <em>every</em> member of its group is away; otherwise normal <a href="#group-auto-assign">auto-assignment</a> handles it. Configure everything at <a href="/admin/settings/oof"><strong>Settings &rarr; Automation &rarr; Out of Office</strong></a>, and see the <a href="/admin/settings/oof/help">Out of Office Setup Guide</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-key text-primary me-2"></i>Prerequisites</h5>
<ol class="text-muted mb-2">
    <li>Microsoft Graph configured for inbound email (Tenant ID, Client ID, Client Secret). The same app registration is reused.</li>
    <li>The <code>MailboxSettings.Read</code> <strong>application</strong> permission on that app, with admin consent (see the <a href="/admin/settings/email-reply-help">Email Reply Setup Guide</a>).</li>
    <li>The <strong>Out-of-Office Coverage</strong> cron job scheduled every 15 minutes.</li>
    <li>Agents set automatic replies in Outlook as usual. The <strong>"Outside my organization"</strong> message is the one requesters receive.</li>
</ol>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    A missing or unconsented <code>MailboxSettings.Read</code> grant shows as <strong>HTTP 403</strong> for every agent in <code>storage/logs/oof-coverage.log</code>. Agents with no Exchange Online mailbox return <strong>HTTP 404</strong> and are skipped quietly.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Settings</h5>
<ul class="text-muted mb-2">
    <li><strong>Enable out-of-office coverage</strong>: the master switch.</li>
    <li><strong>When an agent is out of office</strong>: <em>Reassign if possible, otherwise auto-reply</em> (recommended), <em>Reassign only</em> (one-person groups are left alone), or <em>Auto-reply only</em> (never moves tickets).</li>
    <li><strong>Which tickets to act on</strong>: <em>Unanswered tickets only</em> (recommended; conversations already under way aren't disturbed) or <em>All active tickets</em>.</li>
    <li><strong>Auto-reply message</strong>: two messages, <em>When the agent has a return date</em> (tokens <code>{requester_name}</code>, <code>{ticket_id}</code>, <code>{agent_name}</code>, <code>{return_date}</code>) and <em>When the agent has no return date</em> (same tokens without <code>{return_date}</code>). Leave blank to use the defaults. The agent's own Outlook message is appended when present.</li>
</ul>
<p class="text-muted mb-2">The auto-reply is posted as a public reply and emailed to the requester <strong>once</strong> per ticket (a later absence won't trigger a second auto-reply on the same ticket). A return date is used only when the agent scheduled their out-of-office with an end time. Replies switched on with no end date count as "away until switched off".</p>
<p class="text-muted mb-0">The <strong>Current agent status</strong> table shows each member's last-read state. Changing settings needs <strong>Manage automations &amp; escalations</strong>; the grantable <strong>View out-of-office status</strong> permission lets other levels (e.g. Power Users) open the page read-only to see who is away.</p>
</div>
</div>

<h3 id="recurring-tickets" class="fw-bold mt-5 mb-3">Recurring Tickets</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-clockwise text-primary me-2"></i>Scheduled Ticket Creation</h5>
<p class="text-muted mb-2">Create tickets on a schedule for preventive maintenance and routine checks (monthly toner audit, quarterly HVAC service, annual fire inspection). Manage schedules at <a href="/admin/recurring-tickets"><strong>Settings &rarr; Automation &rarr; Recurring Tickets</strong></a> (needs <strong>Manage recurring tickets</strong>) with <strong>New Recurring Ticket</strong>:</p>
<ul class="text-muted mb-2">
    <li><strong>Schedule</strong>: an internal name and notes, a <strong>Frequency</strong> (Daily, Weekly, Monthly, Yearly or Custom every N days), an interval (e.g. 3 &times; Monthly = quarterly), a <strong>Start Date</strong>, and optional day-of-week, day-of-month and month anchors. A plain-language summary updates as you type.</li>
    <li><strong>Ticket</strong>: subject, description, type, priority, <?= e(strtolower(label('location.singular', 'location'))) ?>, group (or auto-route), assignee (or auto-assign), a due date set N days after creation, and <strong>Filed As (Requester)</strong>.</li>
</ul>
<ul class="text-muted mb-0">
    <li>Tickets are created at 06:00 on the scheduled day. Days past the end of a month use the last day.</li>
    <li>Created tickets go through the normal steps (AI, automations, auto-assignment, SLA, notifications) and show "Ticket auto-created by recurring schedule".</li>
    <li>If the cron job misses a run, the schedule fires once when it catches up; missed dates aren't back-filled. A schedule whose requester has been deleted switches itself off.</li>
    <li>The list shows next run (red if overdue), last run (linked to the ticket created) and run count, with an On/Off toggle and <strong>Run now</strong>, which creates a ticket immediately without changing the schedule.</li>
    <li>Needs the <strong>Recurring Tickets</strong> cron job (every 15 minutes).</li>
</ul>
</div>
</div>

<h3 id="scheduled-reports" class="fw-bold mt-5 mb-3">Scheduled Reports</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-calendar-check text-primary me-2"></i>Emailing Reports on a Schedule</h5>
<p class="text-muted mb-2"><a href="/admin/settings/scheduled-reports"><strong>Settings &rarr; Automation &rarr; Scheduled Reports</strong></a> emails a report summary to a list of recipients. Anyone who can view reports can schedule them, from this page (<strong>Add Schedule</strong>) or with the <strong>Schedule</strong> button on a report page.</p>
<ul class="text-muted mb-0">
    <li>Each schedule has a name, a <strong>Report Type</strong>, <strong>Enabled</strong>, a <strong>Frequency</strong> (Daily, Weekly or Monthly) with a <strong>Send Day</strong>, and a <strong>Report Date Range</strong> (how many days the email covers).</li>
    <li>Type recipients as chips (Enter, comma or space adds one; <strong>Add me</strong> adds you). Invalid addresses turn red and block saving.</li>
    <li>Schedules belong to their creator. People see and edit only their own; admins see all, with a <strong>Created By</strong> column.</li>
    <li>Each email says which schedule sent it and who set it up. Sending needs the <strong>Scheduled Reports</strong> cron job (every 30 minutes).</li>
</ul>
</div>
</div>

<h3 id="cron-jobs" class="fw-bold mt-5 mb-3">Cron Jobs</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Background Jobs</h5>
<p class="text-muted mb-2">Time-based features run from your server's scheduler (cron on Linux/macOS, Task Scheduler on Windows). <a href="/admin/settings/cron-jobs"><strong>Settings &rarr; Automation &rarr; Cron Jobs</strong></a> shows ready-to-copy commands for this server (with a Linux / Windows toggle) and each job's status: <span class="badge bg-success">Running</span> if its log was written within twice its interval, <span class="badge bg-warning text-dark">Stale</span> if older, <span class="badge bg-light text-muted border">Not configured</span> if it has never run.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Job</th><th>Schedule</th><th>Needed for</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>SLA Recalculation</strong> <span class="badge bg-danger ms-1">Required</span></td><td>Every 5 min</td><td>SLA warnings and breaches, follow-up reminders, <a href="#ticket-reminders">ticket reminders</a>, the no-group sweep</td></tr>
        <tr><td><strong>Inbound Email Replies</strong></td><td>Every 5 min</td><td>Reply by email, email-to-ticket</td></tr>
        <tr><td><strong>Escalation Rules</strong></td><td>Every 15 min</td><td><a href="#escalation-rules">Escalation rules</a></td></tr>
        <tr><td><strong>Recurring / Preventive-Maintenance Tickets</strong></td><td>Every 15 min</td><td><a href="#recurring-tickets">Recurring tickets</a></td></tr>
        <tr><td><strong>Out-of-Office Coverage</strong></td><td>Every 15 min</td><td><a href="#oof-coverage">Out-of-office coverage</a></td></tr>
        <tr><td><strong>Scheduled Reports</strong></td><td>Every 30 min</td><td><a href="#scheduled-reports">Scheduled reports</a></td></tr>
        <tr><td><strong>App Secret Expiry Reminders</strong></td><td>Daily, 08:00</td><td>Graph client-secret expiry emails</td></tr>
    </tbody>
</table>
</div>
<h6 class="fw-semibold mb-2">Running a job by hand</h6>
<ul class="text-muted mb-2">
    <li><strong>Dry run</strong> runs the real job but rolls back every database change and lists the emails it <em>would</em> send (<code>WOULD EMAIL &hellip;</code>). It's safe at any time. For Inbound Email Replies it reads the mailbox without marking anything read.</li>
    <li><strong>Run for real</strong> (in the job's dropdown) always asks for confirmation. Jobs that send email also make you tick an acknowledgement, and the dialog shows whether outbound mail is on.</li>
    <li>Output appears in a pop-up. Every manual run is recorded in <code>storage/logs/cron-manual-runs.log</code> (who, which job, dry or live, result, duration).</li>
    <li>A job never runs twice at once: if the scheduled copy is running, a manual run steps aside and says so.</li>
    <li>If the buttons are disabled, the page says why. Usually no PHP command-line binary was found; set <code>PHP_CLI_BINARY</code> in <code>.env</code> to its full path.</li>
</ul>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    Before scheduling an email-sending job for the first time on a helpdesk with existing tickets, <strong>Dry run</strong> it and read the list of emails. Escalation rules, ticket reminders set to <em>All open tickets</em> and similar jobs act on the whole backlog on their first run.
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
