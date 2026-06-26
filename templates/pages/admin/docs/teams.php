<?php
$layout       = 'app';
$pageTitle    = 'Docs: Microsoft Teams';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Microsoft Teams']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Microsoft Teams integration</h2>
<p class="text-muted mb-4">Post ticket activity into a Microsoft Teams channel as it happens. Choose which events are announced, and &mdash; if you want &mdash; route different ticket types to different channels (e.g. IT tickets to the IT channel, Lifelong Learning tickets to theirs).</p>

<div class="alert alert-info small mb-4"><i class="bi bi-info-circle me-2"></i>
    Set everything up at <a href="/admin/settings/teams"><strong>Admin &rarr; Settings &rarr; Integrations &rarr; Microsoft Teams</strong></a>. This page is admin-only.
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-signpost-2 text-primary me-2"></i>On this page</h5>
<ul class="text-muted mb-0">
    <li><a href="#what">What gets posted</a></li>
    <li><a href="#webhook">Step 1 &mdash; create a channel webhook in Teams</a></li>
    <li><a href="#connect">Step 2 &mdash; connect it in the helpdesk</a></li>
    <li><a href="#events">Step 3 &mdash; choose which events to post</a></li>
    <li><a href="#routing"><strong>Routing by ticket type</strong></a> &mdash; send different types to different channels</li>
    <li><a href="#test">Testing</a></li>
    <li><a href="#notes">Good to know &amp; troubleshooting</a></li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="what">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-left-dots text-primary me-2"></i>What gets posted</h5>
<p class="text-muted mb-2">When enabled, the helpdesk sends a card to your Teams channel for any of these events you switch on:</p>
<ul class="text-muted mb-3">
    <li><strong>New ticket created</strong> &mdash; whenever a ticket is opened through any channel (agent, portal, email-to-ticket, or floor mode).</li>
    <li><strong>Ticket assigned</strong> &mdash; when a ticket is assigned or reassigned to an agent.</li>
    <li><strong>Status changed</strong> &mdash; when a ticket moves between statuses; the card shows the <em>old&nbsp;&rarr;&nbsp;new</em> status.</li>
    <li><strong>SLA breached</strong> &mdash; the moment a ticket breaches its SLA (detected during SLA recalculation, including the scheduled cron run).</li>
</ul>
<p class="text-muted mb-0">Each card carries the ticket number and subject, the key facts (status, priority, type, group, location, requester, assignee) and an <strong>Open ticket</strong> button that links straight back to the ticket in the helpdesk.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="webhook">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-1-circle text-primary me-2"></i>Step 1 &mdash; create a channel webhook in Teams</h5>
<p class="text-muted mb-2">Microsoft has retired the old &ldquo;Office&nbsp;365 connector&rdquo; webhooks in favour of <strong>Workflows</strong> (Power Automate). Creating one takes about a minute and needs no admin approval in most tenants:</p>
<ol class="text-muted mb-3">
    <li>In Teams, find the channel you want notifications in. Click the <strong>&ctdot;</strong> (more options) next to the channel name &rarr; <strong>Workflows</strong>.</li>
    <li>In the search box, type <code>webhook</code> and pick the template <strong>&ldquo;Send webhook alerts to a channel&rdquo;</strong>. <span class="text-muted">(Microsoft renamed this &mdash; it used to be called &ldquo;Post to a channel when a webhook request is received&rdquo;. Avoid the &ldquo;from specific people / from an org&rdquo; and &ldquo;to a chat&rdquo; variants.)</span></li>
    <li>Confirm the team &amp; channel, then click <strong>Add workflow</strong> (or <strong>Create</strong>).</li>
    <li>Teams shows a generated <strong>HTTP POST URL</strong>. Copy it &mdash; that's your webhook.</li>
</ol>
<div class="alert alert-warning small mb-0"><i class="bi bi-shield-lock me-2"></i>
    Treat the webhook URL like a password &mdash; anyone who has it can post to that channel. The helpdesk stores it for admins only and keeps it out of the audit log. To revoke it, delete the workflow in Teams.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="connect">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-2-circle text-primary me-2"></i>Step 2 &mdash; connect it in the helpdesk</h5>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/settings/teams"><strong>Settings &rarr; Integrations &rarr; Microsoft Teams</strong></a>.</li>
    <li>Paste the URL into <strong>Default channel webhook</strong>.</li>
    <li>Click <strong>Test</strong> next to it &mdash; a confirmation card should appear in the channel within a few seconds.</li>
    <li>Tick <strong>Enable Teams notifications</strong>, then <strong>Save settings</strong>.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="events">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-3-circle text-primary me-2"></i>Step 3 &mdash; choose which events to post</h5>
<p class="text-muted mb-0">Each of the four events (created, assigned, status changed, SLA breached) has its own switch. Turn off any that would be too noisy for your team &mdash; for example, you might want only <em>new ticket</em> and <em>SLA breached</em>. Note that auto-assigning a brand-new ticket fires both a <em>created</em> and an <em>assigned</em> card; disable <em>Ticket assigned</em> if you'd rather see just one card per new ticket.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="routing">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Routing by ticket type</h5>
<p class="text-muted mb-2">By default every notification goes to the <strong>Default channel webhook</strong>. To split traffic across teams, give individual ticket types their own channel:</p>
<ol class="text-muted mb-3">
    <li>Create a separate webhook in <em>each</em> destination channel (Step&nbsp;1) &mdash; e.g. one in your <strong>IT</strong> channel and one in your <strong>Lifelong Learning</strong> channel.</li>
    <li>On the Teams settings page, find the <strong>Route by ticket type</strong> table. Paste the IT channel's webhook next to the <strong>IT</strong> type, and the Lifelong Learning channel's webhook next to the <strong>Lifelong Learning</strong> type.</li>
    <li>Use the <strong>Test</strong> button on each row to confirm each one lands in the right channel.</li>
    <li>Save. From now on, an IT ticket's events post to the IT channel and a Lifelong Learning ticket's events post to theirs.</li>
</ol>
<p class="text-muted mb-2"><strong>How the routing decision is made, per ticket:</strong></p>
<ul class="text-muted mb-3">
    <li>If the ticket's type has its own channel webhook &rarr; it goes <strong>there</strong>.</li>
    <li>Otherwise &rarr; it falls back to the <strong>Default channel webhook</strong>.</li>
    <li>If the type has no webhook <em>and</em> the default is blank &rarr; nothing is posted for that ticket (no error).</li>
</ul>
<div class="alert alert-light border small mb-0">
    <i class="bi bi-lightbulb me-2"></i><strong>Tips.</strong>
    Leave the default blank if you only want to notify the specific types you've routed.
    Several types can point at the same channel webhook if you want them grouped together.
    The event switches (created / assigned / status / SLA) apply to <em>all</em> channels &mdash; routing only changes <em>where</em> a notification goes, not <em>which</em> events fire.
</div>
<div class="alert alert-warning small mb-0 mt-3">
    <i class="bi bi-exclamation-triangle me-2"></i><strong>Routing a type does not enable Teams on its own.</strong>
    The master <strong>Enable Teams notifications</strong> switch at the top of the settings page must be on (and saved) for <em>any</em> card to post &mdash; including per-type ones. The per-row <strong>Test</strong> button works regardless of the master switch, so a passing test but no tickets posting almost always means the master switch is off.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="test">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send-check text-primary me-2"></i>Testing</h5>
<p class="text-muted mb-0">Every webhook field has a <strong>Test</strong> button that posts a sample card to that exact URL &mdash; even before you've saved. A green message means the channel accepted it (check Teams to see the card); a red message shows the reason it failed (bad URL, network error, or an HTTP error from Teams). Use it whenever you add or change a webhook.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="notes">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Good to know &amp; troubleshooting</h5>
<ul class="text-muted mb-0">
    <li><strong>It never breaks tickets.</strong> If a webhook is wrong, unreachable, or slow, the helpdesk logs the problem and carries on &mdash; creating, assigning and updating tickets always succeeds. A misconfigured webhook can add a few seconds of delay to the action that triggered it, so fix or clear a dead URL when you notice one.</li>
    <li><strong>Notifications are one-way.</strong> The integration posts <em>into</em> Teams; it doesn't read replies or let you act on tickets from Teams. Use the <strong>Open ticket</strong> button on each card to jump back to the helpdesk.</li>
    <li><strong>&ldquo;Couldn't connect&rdquo; on test</strong> usually means the URL was copied incompletely, or your server can't reach <code>*.logic.azure.com</code> outbound. Confirm the full URL and that outbound HTTPS is allowed.</li>
    <li><strong>HTTP 4xx on test</strong> usually means the workflow was deleted or disabled in Teams &mdash; recreate it (Step&nbsp;1) and paste the new URL.</li>
    <li><strong>Webhooks must be <code>https://</code>.</strong> Plain <code>http</code> URLs are rejected.</li>
    <li><strong>SLA-breach posts</strong> depend on SLA recalculation running. Make sure the SLA cron job is scheduled (see <a href="/admin/settings/cron-jobs">Cron Jobs</a>); breaches are also re-evaluated on normal ticket activity.</li>
</ul>
</div>
</div>

</div>
</div>
