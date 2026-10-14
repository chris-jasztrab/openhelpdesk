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
<p class="text-muted mb-4">The portal is where requesters (users with the End User level) submit help requests, follow them, reply, and search the knowledge base.</p>

<div class="alert alert-info small mb-4"><i class="bi bi-translate me-2"></i>
    <strong>Plain-language wording.</strong> The portal avoids help-desk jargon. Tickets are <strong>Help Requests</strong>. The list is <strong>My Requests</strong>. Built-in statuses read <em>Submitted</em>, <em>We're working on it</em>, <em>We're waiting on someone else</em>, <em>Waiting on you</em>, <em>Done</em> and <em>Closed</em>. The priority question is <em>"How urgent is this?"</em>, and if priority isn't required it defaults to <em>Let our team decide</em>. Staff still see the real names. To change any of this wording, see <a href="/admin/docs/branding#labels">Labels</a>.
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-globe text-primary me-2"></i>Portal URL</h5>
<p class="text-muted mb-2">Share this address with your requesters:</p>
<div class="bg-light rounded px-3 py-2 font-monospace small mb-2"><strong><?= e(env('APP_URL', 'http://your-site.com')) ?>/portal</strong></div>
<p class="text-muted mb-0">Anyone who isn't signed in is sent to the sign-in page first, then on to the page they asked for.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="accounts">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-plus text-primary me-2"></i>How Requesters Get an Account</h5>
<p class="text-muted mb-2">There is no self-registration page. A requester account is created when:</p>
<ul class="text-muted mb-3">
    <li>you add the person at <a href="/admin/users/create">Settings → Users &amp; Access → Users → Add User</a> with the <strong>End User</strong> level, or import a list at <a href="/admin/settings/import-users">Settings → Data → Import Users</a>;</li>
    <li>they sign in with <strong>Microsoft 365</strong> for the first time, if <a href="/admin/docs/sso">Single Sign-On</a> is set up;</li>
    <li>they email the helpdesk mailbox and <strong>Auto-create user accounts</strong> is on in the Email-to-Ticket settings (see <a href="/admin/docs/email">Email &amp; Notifications</a>);</li>
    <li>staff forward a ticket to an outside address, which creates an <em>external</em> contact.</li>
</ul>
<p class="text-muted mb-0">Accounts created from email or a forward have no usable password. The person clicks <strong>Forgot password?</strong> on the sign-in page to set one: the emailed link works once and expires after 60 minutes. Microsoft 365 users just sign in with Microsoft.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-house text-primary me-2"></i>What Requesters See</h5>
<ul class="text-muted mb-0">
    <li><strong>Dashboard</strong>: a welcome, the <strong>New Help Request</strong> button and <em>Your Active Requests</em>.</li>
    <li><strong>Left rail</strong>: Dashboard, My Requests, Knowledge Base and Help. A <strong>Floor mode</strong> entry also appears on touch-screen devices.</li>
    <li><strong>Status banners</strong> you post appear at the top of every page. Portal users see global banners and banners for their own location. See <a href="/admin/docs/system#status-banners">Status Banners</a>.</li>
    <li><strong>My Requests</strong>: search, filters and sorting. It starts on their own open requests.</li>
    <li>Each requester controls their own emails, light or dark mode, and timeline order on <strong>My Profile</strong>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send text-primary me-2"></i>Submitting a Request</h5>
<p class="text-muted mb-2"><strong>New Help Request</strong> opens a form built from the chosen ticket type's layout. You design each type's form in <a href="/admin/workflows/ticket-fields">Settings → Organization → Ticket Forms</a>, so the fields depend on your setup. See <a href="/admin/docs/tickets#form-builder">Tickets → Form Builder</a>. Typically:</p>
<ul class="text-muted mb-3">
    <li><strong>Ticket type</strong>: which form to fill in. Fields that only explain things stay hidden until a type is picked.</li>
    <li><strong>Subject</strong>: matching knowledge-base articles are suggested while they type.</li>
    <li><strong>Description</strong>, <strong>How urgent is this?</strong>, <strong>Location</strong> (pre-filled with theirs), <strong>Tags</strong> (if tags are on), <strong>Attachments</strong>, <strong>CC</strong> colleagues, and any custom fields you added.</li>
    <li>An optional <strong>SLA notice</strong> showing the response and resolution targets for the chosen type and priority.</li>
    <li><strong>Start from a template</strong> appears when you have shared ticket templates.</li>
</ul>
<ul class="text-muted mb-0">
    <li>Drafts save automatically, so a half-written request survives a closed tab.</li>
    <li>If the type has AI duplicate checking on, a likely duplicate is shown before the request is created. See <a href="/admin/docs/ai#duplicate-detection">AI → Duplicate Detection</a>.</li>
    <li>With <a href="/admin/settings/undo-send">Undo Send</a> on, there's a short countdown in which the requester can cancel.</li>
    <li>After submitting, the requester gets a confirmation email. If SLA tracking is on and a policy matches, a <strong>What happens next</strong> pop-up shows the actual response and resolution due times.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-left-dots text-primary me-2"></i>On a Request</h5>
<ul class="text-muted mb-0">
    <li><strong>Replies</strong>: requesters comment on the page or simply reply to any notification email. Internal notes and system entries are never shown to them.</li>
    <li><strong>Edit</strong>: the requester can change the subject and description until the request is closed.</li>
    <li><strong>Close this request</strong>: the requester can close their own request when it's no longer needed.</li>
    <li><strong>CC'd colleagues</strong> are listed on the request. Requesters add CCs on the form when they submit, and after that only staff can add more.</li>
    <li>When staff mark a reply as the solution, a green <strong>Answer available</strong> banner links to it.</li>
    <li>While a request is still <em>Submitted</em>, a <strong>"What happens next?"</strong> panel tells the requester it's queued and that they'll get an email when there's an update. The wording is editable under <a href="/admin/docs/branding#labels">Labels</a>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-up-right-circle text-danger me-2"></i>Requester Escalation</h5>
<p class="text-muted mb-2">The person who opened a request can click <strong>Escalate</strong>. A confirmation shows who it will go to, and they can give a reason. The request then moves up the <a href="/admin/docs/automations#escalation-paths">escalation path</a> for its ticket type, the same as when an agent escalates. Other people who can see the request can't escalate it.</p>
<ul class="text-muted mb-2">
    <li>The button is hidden when the ticket type has no escalation path. Set paths up at <a href="/admin/settings/escalation-paths"><strong>Settings → Automation → Escalation Paths</strong></a>.</li>
    <li>On the same page, <strong>Escalate Button Visibility</strong> can hide the button until the request's SLA has been breached.</li>
</ul>
<p class="text-muted mb-0">Escalated requests show an <strong>Escalated L#</strong> badge in My Requests, an <strong>Escalated: Level N</strong> badge and Escalation row on the request, and an entry in the timeline.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-geo-alt text-primary me-2"></i>Seeing a Whole Location's Requests</h5>
<p class="text-muted mb-0">Requesters with <strong>Location Ticket Visibility</strong> on their account get a <strong>My Location</strong> toggle on My Requests. The toggle follows your Location label: with <em>Branch</em> it reads <em>My Branch</em>. It shows other people's requests at their location, except confidential types and types you've hidden from location visibility. Before commenting on a request they didn't open, they're asked to confirm. See <a href="/admin/docs/users">Users &amp; Roles</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-check text-primary me-2"></i>Requester Emails</h5>
<p class="text-muted mb-2">Requesters are emailed when their request is received (from any channel), assigned, updated by staff, and resolved or closed. They also get a satisfaction survey if <a href="/admin/docs/csat">surveys</a> are on.</p>
<p class="text-muted mb-0">Each email can be switched off for everyone in the <strong>Ticket Requester Notifications</strong> section of <a href="/admin/settings/email-notifications">Settings → Email → Email Notifications</a>, and each person can opt out on My Profile. Wording lives in <a href="/admin/settings/email-templates">Email Templates</a>. See <a href="/admin/docs/email">Email &amp; Notifications</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-life-preserver text-primary me-2"></i>Help Pages and the Guided Tour</h5>
<ul class="text-muted mb-0">
    <li>Requesters have their own help section at <a href="/portal/help">/portal/help</a>, linked from <strong>Help</strong> on the rail. Its articles are Getting Started, Submitting a Request, Tracking Your Requests, Replying, Editing &amp; Closing, Finding Answers Yourself, Your Profile &amp; Emails, and Floor Mode.</li>
    <li>On their first visit, each requester is offered a guided tour of the portal, including a practice request. It skips anything their account doesn't have. They can replay it from their name menu → <strong>Restart Tour</strong>.</li>
    <li>Published <a href="/admin/docs/kb">knowledge base</a> articles appear under <strong>Knowledge Base</strong> on the rail, and public articles can be read without signing in.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="floor-mode">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-1x2 text-primary me-2"></i>Floor Mode</h5>
<p class="text-muted mb-0">Floor mode is a touch-friendly view for tablets and phones, made for staff working away from a desk. It appears on the rail only on touch-screen devices, and there's nothing to configure. Requesters get a big <strong>New help request</strong> button, their recent requests, and a quick form with photo capture. Agents get a live queue and quick actions. See <a href="/portal/help/floor">Portal Help → Floor Mode</a> and <a href="/agent/help/floor">Agent Help → Floor Mode</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="install-app">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-phone text-primary me-2"></i>Install as an App</h5>
<p class="text-muted mb-2">OpenHelpDesk can be installed to a phone, tablet or desktop home screen. This works for requesters and staff alike, and there's nothing to switch on.</p>
<ul class="text-muted mb-0">
    <li>In Chrome, Edge and Android browsers a small banner offers <strong>Install</strong>. On iPhone and iPad it explains Share → <em>Add to Home Screen</em>. Dismissing the banner hides it for 14 days, and it never shows once the app is installed.</li>
    <li>The installed app takes its name, theme colour and icon from <a href="/admin/docs/branding">Branding</a>. The icon is made from your logo, or from the first letter of the app name if there's no logo. Generating icons needs the PHP GD extension on the server.</li>
    <li>Long-pressing the icon offers shortcuts to Floor mode, New ticket and My tickets. If the connection drops, a simple offline page offers <em>Try again</em>.</li>
    <li>Browsers only offer installation over HTTPS.</li>
</ul>
</div>
</div>

<h3 id="direct-links" class="fw-bold mt-5 mb-3">Direct Links</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-link-45deg text-primary me-2"></i>Linking Straight to a Specific Type's Form</h5>
<p class="text-muted mb-2">Sometimes you want to skip the type dropdown and send people straight to the right form. For example: a "Report a printer problem" link on the intranet, a QR code on a public computer, a button in a Teams channel, or an email signature.</p>
<p class="text-muted mb-2">The new-request page accepts two parameters that pre-select the ticket type:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:40%">URL pattern</th><th>Behaviour</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><code>/portal/tickets/create?type_id=<em>N</em></code></td>
            <td>The numeric ticket-type ID. It keeps working if you rename the type, so use this for links you share.</td>
        </tr>
        <tr>
            <td><code>/portal/tickets/create?type=<em>name</em></code></td>
            <td>The type name, ignoring case. Hyphens and underscores count as spaces, so <code>?type=hardware-issue</code> matches <em>Hardware Issue</em>. Easier to read, but it <strong>stops pre-selecting if the type is renamed</strong>.</td>
        </tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-0">If both are given, <code>type_id</code> wins. An unknown value is ignored, so the form loads with no type selected. If a submission bounces back with an error, the requester's own choice is kept instead of the link's. Once the type is selected, the form shows that type's layout.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clipboard-check text-primary me-2"></i>Where to Get a Direct Link</h5>
<p class="text-muted mb-2"><a href="/admin/types"><strong>Settings → Organization → Ticket Types</strong></a> has a <strong>Direct Link</strong> column for every type:</p>
<ul class="text-muted mb-2">
    <li><strong>The path box</strong> shows <code>/portal/tickets/create?type_id=<em>N</em></code>. Click it to select the whole path.</li>
    <li><strong>Copy</strong> (clipboard icon) copies the full <code>https://…</code> address and briefly shows a green check.</li>
    <li><strong>Open in new tab</strong> lets you check that the right type and form appear.</li>
</ul>
<p class="text-muted mb-0">For a friendlier link on a poster, swap <code>type_id=<em>N</em></code> for <code>type=<em>your-type-name</em></code> yourself.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-check text-primary me-2"></i>When the Visitor Isn't Signed In</h5>
<p class="text-muted mb-2">Direct links survive the sign-in step. Someone who clicks one while signed out is sent to the sign-in page. After signing in (including two-factor sign-in) they land on the form with the right type selected.</p>
<p class="text-muted mb-2">To send people through sign-in on purpose, put the destination in <code>?next=</code> on the sign-in address. URL-encode it, because it contains its own <code>?</code>:</p>
<div class="bg-light rounded px-3 py-2 font-monospace small mb-3">/login?next=%2Fportal%2Ftickets%2Fcreate%3Ftype_id%3D5</div>
<div class="alert alert-warning small mb-0"><i class="bi bi-shield-lock me-2"></i>
    <strong>Off-site links are blocked.</strong> Only paths on this site that start with a single <code>/</code> are accepted. Full addresses, <code>//other-site</code> and backslash tricks, <code>/login</code> itself, and anything over 2,000 characters are ignored, and the person lands on the home page instead. A direct link can't be used to bounce someone to another website.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-paint-bucket text-primary me-2"></i>Customising the Portal</h5>
<ul class="text-muted mb-0">
    <li><strong>Look</strong>: logo, app name and colours at <a href="/admin/docs/branding">Branding</a>.</li>
    <li><strong>Wording</strong>: status names, buttons, headings and panels at <a href="/admin/docs/branding#labels">Labels</a>.</li>
    <li><strong>Forms</strong>: which types requesters can pick and which fields each form shows, at <a href="/admin/docs/tickets#form-builder">Ticket Forms</a>.</li>
    <li><strong>Announcements</strong>: <a href="/admin/docs/system#status-banners">status banners</a>.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
