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
    Set everything up at <a href="/admin/settings/teams"><strong>Settings &rarr; Integrations &rarr; Microsoft Teams</strong></a>. The page is admin-only and can't be granted to other permission levels.
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
    <li><a href="#bot"><strong>Helpdesk bot</strong></a> &mdash; open, check, reply to and close tickets from inside Teams</li>
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
    <li><strong>SLA breached</strong> &mdash; when a ticket moves into Breached during SLA recalculation (the SLA cron job or <strong>Recalculate All</strong>).</li>
</ul>
<p class="text-muted mb-2">Each card carries the ticket number and subject, the key facts (status, priority, type, group, location, requester, assignee) and an <strong>Open ticket</strong> button that links straight back to the ticket in the helpdesk.</p>
<div class="alert alert-warning small mb-0"><i class="bi bi-shield-lock me-2"></i>
    Cards are posted for every ticket type, <strong>including confidential ones</strong>, and show the subject and requester to everyone in the channel. Route confidential types to a private channel, or leave the default webhook blank so only the types you route are posted.
</div>
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
    <li>Tick <strong>Enable Teams notifications</strong>, then <strong>Save settings</strong>. Enabling needs a default webhook or at least one per-type webhook.</li>
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

<div class="card border-0 shadow-sm mb-4" id="bot">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-robot text-primary me-2"></i>Helpdesk bot &mdash; work tickets from inside Teams</h5>
<p class="text-muted mb-3">Separate from the one-way channel notifications above, the helpdesk can also run as a <strong>bot</strong> that staff add to any chat or channel. Talk to it in plain commands &mdash; in a channel, @mention it first:</p>
<div class="table-responsive mb-3">
<table class="table table-sm align-middle small">
<thead><tr class="text-muted"><th style="width:260px;">Say</th><th>What happens</th></tr></thead>
<tbody>
<tr><td><code>new Printer on 2F jammed | paper light flashing</code></td><td>Opens a ticket. Text before the <code>|</code> (or on the first line) is the subject, the rest is the description. The ticket gets the default priority, your location and the default group, plus AI routing / auto-assign if those are on. The reply is the ticket card.</td></tr>
<tr><td><code>my tickets</code></td><td>Your open tickets. Agents also see tickets assigned to them.</td></tr>
<tr><td><code>status 123</code> &nbsp;or&nbsp; <code>#123</code></td><td>A card with the ticket's status, priority, group, assignee, description and latest reply, with <em>Send reply</em>, <em>Close</em>&nbsp;/&nbsp;<em>Reopen</em> and <em>Open in helpdesk</em> buttons.</td></tr>
<tr><td><code>comment 123 Still broken after a restart</code></td><td>Adds a public reply &mdash; the same notifications fire as when replying on the web.</td></tr>
<tr><td><code>close 123 Fixed by swapping the cable</code></td><td>Closes the ticket, recording the note as a reply first. <code>reopen 123</code> reopens it; agents can also <code>resolve 123</code>.</td></tr>
<tr><td><code>help</code></td><td>The command list. The bot also posts it when it is first added to a chat or channel.</td></tr>
</tbody></table></div>

<h6 class="fw-semibold">Who can do what</h6>
<ul class="text-muted small mb-3">
    <li><strong>Identity.</strong> People are matched to helpdesk accounts by their Microsoft identity &mdash; the same id SSO stores. Someone who has never signed in is linked by email, or created as a portal user, when the message comes from your own tenant; otherwise the bot asks them to sign in to the helpdesk once.</li>
    <li><strong>Access.</strong> Requesters see only tickets they opened; agents see what they could see in the agent interface. <strong>Confidential tickets are never shown</strong> &mdash; the bot points to the helpdesk instead, because a channel is a shared surface.</li>
    <li><strong>Visibility.</strong> Whatever the bot posts is visible to everyone in that chat or channel. Use a personal chat with the bot for anything you would not post to the channel.</li>
</ul>

<h6 class="fw-semibold">Setup (once, by an admin)</h6>
<ol class="text-muted small mb-0">
    <li>In the Azure portal create an <strong>Azure Bot</strong> resource. Choose <em>Single Tenant</em>, and either let it create a Microsoft App ID or pick <em>Use existing app registration</em> with your SSO app.</li>
    <li>Create a client secret on that app registration (Entra ID &rarr; App registrations &rarr; Certificates &amp; secrets).</li>
    <li>On <a href="/admin/settings/teams#bot"><strong>Settings &rarr; Microsoft Teams &rarr; Helpdesk bot</strong></a> enter the App ID, tenant ID and secret (leave all blank to reuse the SSO app), enable the bot, save, then <strong>Check saved credentials</strong>.</li>
    <li>Copy the <strong>Messaging endpoint</strong> shown there into the Azure Bot's Configuration, and add the <strong>Microsoft Teams</strong> channel under Channels. The helpdesk must be reachable from the internet over HTTPS.</li>
    <li><strong>Download the Teams app package</strong> and upload it in Teams (Apps &rarr; Manage your apps &rarr; Upload an app), or have a Teams admin publish it to the organisation's app catalogue so anyone can add it to a chat or channel.</li>
</ol>
<div class="alert alert-light border small mt-3 mb-0">
    <i class="bi bi-shield-check me-2"></i><strong>Security.</strong> Every message the bot receives carries a token signed by Microsoft's Bot Framework for your App ID; the helpdesk checks the signature, issuer, audience, expiry and the reply address before reading anything, so a request from anywhere else is refused with 401.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="notes">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Good to know &amp; troubleshooting</h5>
<ul class="text-muted mb-0">
    <li><strong>It never breaks tickets.</strong> If a webhook is wrong, unreachable, or slow, the helpdesk logs the problem and carries on &mdash; creating, assigning and updating tickets always succeeds. A misconfigured webhook can add a few seconds of delay to the action that triggered it, so fix or clear a dead URL when you notice one.</li>
    <li><strong>Channel notifications are one-way.</strong> They post <em>into</em> Teams; replies under a notification card are not read. To act on tickets from Teams, set up the <a href="#bot">helpdesk bot</a>, or use the <strong>Open ticket</strong> button on each card.</li>
    <li><strong>&ldquo;Couldn't connect&rdquo; on test</strong> usually means the URL was copied incompletely, or your server can't reach <code>*.logic.azure.com</code> outbound. Confirm the full URL and that outbound HTTPS is allowed.</li>
    <li><strong>HTTP 4xx on test</strong> usually means the workflow was deleted or disabled in Teams &mdash; recreate it (Step&nbsp;1) and paste the new URL.</li>
    <li><strong>Accepted URLs.</strong> Webhooks must be <code>https://</code> addresses on a Microsoft host ending in <code>.webhook.office.com</code>, <code>.logic.azure.com</code> or <code>.office.com</code>. Plain <code>http</code>, other hosts and bare IP addresses are refused, and the Test button says why.</li>
    <li><strong>SLA-breach posts</strong> depend on SLA recalculation running. Make sure the SLA Recalculation cron job is scheduled (see <a href="/admin/settings/cron-jobs">Cron Jobs</a>).</li></ul>
</div>
</div>

</div>
</div>
