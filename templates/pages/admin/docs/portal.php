<?php
$layout       = 'app';
$pageTitle    = 'Docs: User Portal';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'User Portal']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">User Portal</h2>
<p class="text-muted mb-4">The portal is the public-facing interface where end users submit and track their support tickets.</p>

<div class="alert alert-info small mb-4"><i class="bi bi-translate me-2"></i>
    <strong>Plain-language vocabulary:</strong> The portal uses non-technical wording so library staff and patrons aren't confronted with IT jargon. "Tickets" appear as <strong>Help Requests</strong>, statuses read as <em>Submitted</em>, <em>We're working on it</em>, <em>Waiting on you</em>, <em>We're waiting on someone else</em>, and <em>Done</em>, the priority field asks <em>"How urgent is this?"</em> with a "let our team decide" default, and the navbar entry is <strong>Help</strong>. Agent and admin views are unchanged — staff who handle the queue still see "Ticket". The full set of editable strings (and their defaults) lives at <a href="/admin/settings/labels"><strong>Admin → Settings → Labels</strong></a>, with download / upload / reset controls if you want to fork your own copy of <code>config/labels.default.json</code>.
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-globe text-primary me-2"></i>Portal URL</h5>
<p class="text-muted mb-2">Share the following URL with your end users:</p>
<div class="bg-light rounded px-3 py-2 font-monospace small mb-2"><strong><?= e(env('APP_URL', 'http://your-site.com')) ?>/portal</strong></div>
<p class="text-muted mb-0">Users can register, log in, submit new tickets, and track the status of their existing tickets.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-plus text-primary me-2"></i>User Registration</h5>
<p class="text-muted mb-2">End users register by clicking <strong>Register</strong> on the portal login page. They provide:</p>
<ul class="text-muted mb-3">
    <li>First name, last name</li>
    <li>Email address (used as login)</li>
    <li>Password</li>
</ul>
<p class="text-muted mb-0">After registration, a verification email is sent. Users must click the link in the email to verify their address before they can log in. If SMTP is not configured, accounts are activated immediately without email verification.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send text-primary me-2"></i>Submitting a Ticket</h5>
<p class="text-muted mb-2">Once logged in, users click <strong>New Ticket</strong> to submit a support request. The form includes:</p>
<ul class="text-muted mb-3">
    <li><strong>Subject</strong> — a brief summary of the issue.</li>
    <li><strong>Description</strong> — full details of the request.</li>
    <li><strong>Location</strong> — visible only if locations are configured in Admin → Settings → Locations.</li>
    <li><strong>Attachments</strong> — users can attach files (images, PDFs, documents) up to the configured upload size limit.</li>
</ul>
<p class="text-muted mb-0">On submission, the user receives a confirmation email (if SMTP is configured) and the ticket appears in their portal dashboard.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-left-dots text-primary me-2"></i>Ticket Communication</h5>
<p class="text-muted mb-2">Users can view the full conversation history on their ticket and post follow-up replies directly from the portal. Each reply:</p>
<ul class="text-muted mb-0">
    <li>Is visible to the assigned agent and all admins.</li>
    <li>Triggers an email notification to the agent (if SMTP is configured).</li>
    <li>Appears in the ticket timeline with a timestamp.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil-square text-primary me-2"></i>Editing &amp; Closing Your Own Ticket</h5>
<p class="text-muted mb-2">Portal users have limited self-service options on their own open tickets:</p>
<ul class="text-muted mb-3">
    <li><strong>Edit subject &amp; description</strong> — a user can update the subject line and description of a ticket they submitted, as long as it has not yet been closed.</li>
    <li><strong>Close ticket</strong> — a user can close one of their own open tickets if the issue has been resolved or the request is no longer needed.</li>
</ul>
<p class="text-muted mb-0">Both actions are available from the ticket detail view in the portal. Internal notes and system events are never shown to portal users.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-up-right-circle text-danger me-2"></i>Requester Escalation</h5>
<p class="text-muted mb-2">If a requester feels their request has stalled, they can click <strong>Escalate</strong> on their own ticket from the portal. This walks the ticket up the <a href="/admin/docs/automations#escalation-paths">escalation path</a> configured for its ticket type — exactly the same flow agents use, but limited by an owner check (only the user who created the ticket can escalate it).</p>
<p class="text-muted mb-0">If no escalation path is configured for the ticket's type, the button is hidden. To enable requester escalation for a type, define its chain at <a href="/admin/settings/escalation-paths"><strong>Admin → Settings → Escalation Paths</strong></a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye text-primary me-2"></i>Escalation Visibility for Requesters</h5>
<p class="text-muted mb-2">When a ticket has been escalated (manually or via a time-based rule), portal users see clear visual indicators rather than only timeline events:</p>
<ul class="text-muted mb-0">
    <li>An <strong>Escalated L#</strong> badge on the row in the portal request list.</li>
    <li>An <strong>Escalated — Level N</strong> badge at the top of the ticket detail view.</li>
    <li>A new <strong>Escalation Level</strong> row in the Details sidebar.</li>
    <li>An <code>escalated</code> entry in the timeline they can already see.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>"What happens next?" Callout</h5>
<p class="text-muted mb-0">When a requester opens a help request that's still in the Submitted (open) state, the portal shows a reassuring callout explaining that the request is in the queue, someone will review it, and they'll get an email when there's an update — so there's no need to phone or email to confirm. The text comes from the <code>portal.what_next.*</code> label keys and can be edited at <a href="/admin/settings/labels">Admin → Settings → Labels</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-check text-primary me-2"></i>Requester Email Notifications</h5>
<p class="text-muted mb-2">Two notifications keep requesters in the loop without any portal action on their part:</p>
<ul class="text-muted mb-0">
    <li><strong>Submission acknowledgement</strong> — fires from every creation path (portal, admin, API, email-to-ticket) so the requester always gets a "we got it, here's the request number" email.</li>
    <li><strong>Assignment notification</strong> — when an agent picks up the request, the requester is told who is handling it.</li>
</ul>
<p class="text-muted small mb-0 mt-2">Both notifications honour the global <a href="/admin/settings/email-templates">email template toggles</a> and individual users' <a href="/admin/docs/users">notification preferences</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-book text-primary me-2"></i>Knowledge Base Access</h5>
<p class="text-muted mb-2">Published knowledge base articles are accessible to portal users from the <strong>Knowledge Base</strong> link in the portal navigation. Users can browse by category and search for articles before submitting a ticket.</p>
<p class="text-muted mb-0">See the <a href="/admin/docs/kb"><strong>Knowledge Base</strong></a> documentation for how to create and publish articles.</p>
</div>
</div>

<h3 id="direct-links" class="fw-bold mt-5 mb-3">Direct Links</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-link-45deg text-primary me-2"></i>Linking Straight to a Specific Type's Form</h5>
<p class="text-muted mb-2">Most users land on the portal, click <em>New Ticket</em>, and pick a ticket type from a dropdown. But there are plenty of situations where you want to skip the dropdown and send someone directly to the right form — a "Report a printer problem" link on the staff intranet, a QR code on a public-access PC, a button in a Teams channel, an email signature.</p>
<p class="text-muted mb-2">The new-ticket page accepts two query parameters that pre-select the ticket type for the visitor:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:40%">URL pattern</th><th>Behaviour</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><code>/portal/tickets/create?type_id=<em>N</em></code></td>
            <td>Numeric ticket-type ID (you can find it in the URL when you edit a type). <strong>Stable across renames</strong> — the link keeps working even if you change the type's display name later. Recommended for shareable links.</td>
        </tr>
        <tr>
            <td><code>/portal/tickets/create?type=<em>name</em></code></td>
            <td>Human-readable name, case-insensitive. Hyphens and underscores are treated as spaces, so <code>?type=hardware-issue</code> resolves the same as <code>?type=Hardware%20Issue</code>. Nicer to read in a URL bar but <strong>breaks silently if the type is later renamed</strong>.</td>
        </tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-2">The numeric form wins if both are supplied. Unknown values are ignored — a stale link still loads the form, just without the pre-selection (the dropdown stays at "&mdash; Select type &mdash;"). And anything the user has typed before submitting (a half-finished POST that came back with an error) takes precedence over the URL parameter, so re-submitting after a validation failure won't lose their pick.</p>
<p class="text-muted mb-0">Once the type is pre-selected, the form's existing JS automatically renders any custom fields scoped to that type — see <a href="/admin/docs/tickets#form-builder"><strong>Tickets → Form Builder</strong></a> for how field scope is configured.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clipboard-check text-primary me-2"></i>Where to Get a Direct Link</h5>
<p class="text-muted mb-2">Rather than hand-crafting URLs, use the built-in column at <a href="/admin/types"><strong>Admin → Settings → Ticket Types</strong></a>. Each row carries a <strong>Direct Link</strong> column with three controls:</p>
<ul class="text-muted mb-3">
    <li><strong>Read-only path input</strong> — shows the relative path (<code>/portal/tickets/create?type_id=<em>N</em></code>). Click it once to select the whole thing, then <kbd>Ctrl</kbd>+<kbd>C</kbd> to copy. Hovering it shows a tooltip noting that anonymous visitors will be sent through login first.</li>
    <li><strong>Copy button</strong> (clipboard icon) — copies the <em>absolute</em> URL (full <code>https://your-site/...</code>) to your clipboard, and briefly flashes a green check so you know it worked. Uses the modern <code>navigator.clipboard</code> API where available, with a fallback for older browsers and non-HTTPS contexts.</li>
    <li><strong>Open in new tab</strong> (square-arrow icon) — opens the link in a new tab. Useful for sanity-checking that the right type pre-selects and that any type-scoped custom fields show up the way you expect.</li>
</ul>
<p class="text-muted mb-2">The link uses the <code>type_id</code> form deliberately — it's the durable choice. If you'd rather share a name-based URL (it reads more naturally on a printed poster or in an email), copy the path and swap <code>type_id=<em>N</em></code> for <code>type=<em>your-type-name</em></code> by hand.</p>
<p class="text-muted mb-0">Direct-link URLs are always relative paths starting with a single forward slash. They are safe to embed anywhere on the same site (intranet pages, internal Slack/Teams messages, email signatures) and the absolute version on the clipboard is what you'd typically paste into a public-facing place.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-check text-primary me-2"></i>What Happens When the Visitor Isn't Signed In</h5>
<p class="text-muted mb-2">Submitting a ticket requires a logged-in account. Direct links would be much less useful if every anonymous click dumped the visitor on a generic home page after login — they'd be left wondering what they were supposed to do next.</p>
<p class="text-muted mb-2">OpenHelpDesk handles this with a <strong>"remember where you were going"</strong> flow:</p>
<ol class="text-muted mb-3">
    <li>An anonymous visitor clicks the direct link (e.g. <code>/portal/tickets/create?type_id=5</code>).</li>
    <li>The server sees they aren't signed in, <strong>stashes the requested URL in their session</strong>, and redirects them to the login page.</li>
    <li>They sign in (and complete 2FA if their account has it enabled — the stashed URL survives the 2FA round-trip).</li>
    <li>On final success, they're sent <strong>straight to the new-ticket form for the right type</strong>, with type-scoped custom fields already rendered.</li>
</ol>
<p class="text-muted mb-2">Self-registration works the same way: a visitor without an account can register from the login page, click the verification link in their welcome email, sign in, and land on the form they originally clicked. The session-stashed URL persists until either it's consumed on a successful login or the browser session ends.</p>
<p class="text-muted mb-2">If you want to share a "log in first, then go to this form" link explicitly — for example, in an email where you want the recipient to see the login page even if they think they're already signed in elsewhere — you can also pass the destination as a <code>?next=</code> parameter on the login URL itself:</p>
<div class="bg-light rounded px-3 py-2 font-monospace small mb-3">/login?next=%2Fportal%2Ftickets%2Fcreate%3Ftype_id%3D5</div>
<p class="text-muted mb-2">The <code>next</code> value must be URL-encoded because it contains its own <code>?</code>. Once the user signs in, they're forwarded to the decoded path.</p>
<div class="alert alert-warning small mb-0"><i class="bi bi-shield-lock me-2"></i>
    <strong>Open-redirect protection.</strong> Both the auto-stash and the explicit <code>?next=</code> parameter only accept <strong>relative paths starting with a single forward slash</strong>. Absolute URLs (<code>http://&hellip;</code>), protocol-relative URLs (<code>//evil.example.com/&hellip;</code>), backslash-prefix variants that some browsers normalise (<code>/\evil.example.com</code>), the <code>/login</code> page itself, anything longer than 2&nbsp;000 characters, and non-GET requests are all silently rejected — visitors hitting a bad value just land on the home page after login. There is no way to weaponise this flow into bouncing a logged-in user off-site.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-paint-bucket text-primary me-2"></i>Customising the Portal</h5>
<p class="text-muted mb-2">You can personalise what end users see on the portal homepage at <a href="/admin/settings/branding"><strong>Admin → Settings → Branding</strong></a>:</p>
<ul class="text-muted mb-0">
    <li><strong>Portal Welcome Title</strong> — large heading on the portal homepage.</li>
    <li><strong>Portal Welcome Message</strong> — supporting text below the heading.</li>
    <li><strong>Logo</strong> and <strong>Primary Colour</strong> — applied throughout the portal as well as the admin interface.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
