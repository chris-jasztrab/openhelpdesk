<?php
$layout       = 'app';
$pageTitle    = 'Docs: System & Maintenance';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'System & Maintenance']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">System &amp; Maintenance</h2>
<p class="text-muted mb-4">Status banners for outages, the server clock check, backups, the Danger Zone, the REST API, and where to find log files.</p>

<div class="card border-0 shadow-sm mb-4" id="status-banners">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-megaphone text-primary me-2"></i>Status Banners</h5>
<p class="text-muted mb-2">A status banner is a notice pinned to the top of every signed-in page, for example "Wi-Fi is down at Main Branch, we're on it". It heads off a wave of duplicate tickets. Manage banners at <a href="/agent/banners"><strong>Settings → Operations → Status Banners</strong></a>. Every staff member can post, edit, clear and delete banners, not only admins.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:150px;">Field</th><th>Notes</th></tr></thead>
    <tbody class="text-muted">
        <tr><td class="fw-semibold">Title</td><td>Optional. Shown in bold above the message.</td></tr>
        <tr><td class="fw-semibold">Severity</td><td><em>Info</em> (FYI or planned maintenance), <em>Warning</em> (active issue, workarounds available) or <em>Critical</em> (major outage). Sets the colour and icon. Critical banners are listed first.</td></tr>
        <tr><td class="fw-semibold">Message</td><td>Required. Rich text: bold, links and lists. Scripts and unsafe links are stripped.</td></tr>
        <tr><td class="fw-semibold">Branches</td><td>Leave on <em>All branches (global)</em>, or tick one or more locations. Location rows named "System Wide" or "All Branches" are hidden here because <em>All branches</em> already covers them.</td></tr>
        <tr><td class="fw-semibold">Show from / Auto-hide at</td><td>Both optional. Blank means show now, until someone clears it.</td></tr>
    </tbody>
</table>
</div>
<ul class="text-muted mb-0">
    <li><strong>Who sees it:</strong> staff see every live banner. Portal users see global banners plus banners for their own location.</li>
    <li>Banners don't appear inside embedded previews.</li>
    <li>Staff see a <em>Posted by</em> line and inline <strong>Edit</strong> and <strong>Clear</strong> buttons on the banner itself.</li>
    <li>Anyone can dismiss a banner. It stays dismissed for the rest of their session, across tabs. Editing a banner brings it back for everyone.</li>
    <li>The list shows each banner as <em>Live</em>, <em>Scheduled</em>, <em>Expired</em> or <em>Cleared</em>. <strong>Re-post</strong> brings a cleared banner back. <strong>Delete</strong> removes it permanently.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="server-time">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Server Time Check</h5>
<p class="text-muted mb-2"><a href="/admin/settings/time"><strong>Settings → Scheduling → Server Time</strong></a> is a read-only check. It compares four clocks: the operating system, MySQL, PHP for the website, and PHP for scheduled jobs. When they disagree, timestamps are written wrong by a fixed amount and nothing else reports an error.</p>
<p class="text-muted mb-0">You get one verdict (all clocks agree, or how many don't), then a card per check with the fix command, the raw readings, and a UTC cross-check. It changes nothing. Click <strong>Re-check</strong> after fixing the server. For how business-hours timezones affect SLA timers, see <a href="/admin/docs/sla">SLA Policies</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="backup">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-archive text-primary me-2"></i>Backups</h5>
<p class="text-muted mb-2"><a href="/admin/settings/backup"><strong>Settings → Data → Backup</strong></a> needs the <strong>Import data &amp; manage backups</strong> permission (admins always have it). Click <strong>Create Backup Now</strong> to build a <code>.zip</code> in <code>storage/backups/</code> containing:</p>
<ul class="text-muted mb-2">
    <li><code>database.sql</code>: a full dump of every table.</li>
    <li><code>website/</code>: the whole application folder, including source, templates, <code>config/</code>, <code>.env</code>, <code>vendor/</code>, scripts, attachments, branding, avatars and logs. Only <code>storage/backups/</code> itself is left out.</li>
</ul>
<p class="text-muted mb-2">Stored backups are listed with their size, a <strong>Download</strong> button and a delete button. Creating and deleting backups is recorded in the audit log. A large site can take several minutes. The PHP <code>zip</code> extension is required, and the page warns you if it's missing.</p>
<p class="text-muted mb-2"><strong>To restore:</strong> extract the zip, import <code>database.sql</code> into MySQL, copy <code>website/</code> back to the site folder, give the web server user ownership of the files again, and check that <code>.env</code> suits the server you're restoring to.</p>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    A backup contains <code>.env</code>, which holds your database password, and every attachment. Treat the file as sensitive and keep a copy off the server. A backup that only exists in <code>storage/backups/</code> is lost along with the server.
</div>
</div>
</div>

<div class="card border-danger border-opacity-50 shadow-sm mb-4" id="danger-zone">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Danger Zone</h5>
<p class="text-muted mb-2"><a href="/admin/settings/danger-zone"><strong>Settings → System → Danger Zone</strong></a> is admin-only. Neither action can be undone, so take a backup first.</p>
<ul class="text-muted mb-2">
    <li><strong>Delete All Tickets</strong> removes every ticket with its timeline, attachment files and notifications. Type <code>DELETE</code> to confirm. Users, settings and configuration are kept.</li>
    <li><strong>Reset Everything</strong> empties every database table: users, tickets, KB articles, automations, SLA policies, settings, API tokens and the audit log. Type <code>RESET</code> to confirm. You're signed out and sent to the <strong>Setup Wizard</strong>, which asks for a helpdesk name and a new admin account.</li>
</ul>
<p class="text-muted mb-0">Reset Everything clears the database only. Files already on disk, such as attachments, the branding logo, avatars and stored backups, are not deleted.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="api">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plug text-primary me-2"></i>REST API</h5>
<p class="text-muted mb-2">OpenHelpDesk has a token-authenticated REST API under <code>/api/v1</code> for mobile apps and integrations. It covers tickets, replies, attachments, the knowledge base, notifications, canned responses and lookup lists. The full reference is <code>docs/API.md</code> in the application folder, with a machine-readable OpenAPI spec in <code>openapi.json</code> at the top of the folder.</p>
<ul class="text-muted mb-3">
    <li><strong>Always on, no admin keys.</strong> There is no switch to enable it and no key screen. A user signs in with <code>POST /api/v1/auth/login</code> using their own email and password, plus their 2FA code if they use two-factor sign-in. They receive a bearer token valid for 90 days. <code>POST /api/v1/auth/rotate</code> swaps it for a fresh one, and <code>POST /api/v1/auth/logout</code> revokes it. Accounts that only sign in with Microsoft 365 have no password and can't use the API.</li>
    <li><strong>Same access as the website.</strong> The API applies the user's permission level and ticket visibility: groups, confidential types, and own tickets only for portal users.</li>
    <li><strong>Revoking access.</strong> When a user resets their password with <em>Forgot password?</em> or changes it on My Profile, all their API tokens are revoked.</li>
    <li><strong>Limits.</strong> Failed API sign-ins are throttled: 5 per email or 10 per IP address in 15 minutes. Each user is capped at <code>API_RATE_LIMIT_PER_MIN</code> requests per minute, set in <code>.env</code> (default 120, <code>0</code> turns the cap off). Over the cap, requests get HTTP 429 with a <code>Retry-After</code> header.</li>
    <li><strong>Audit trail.</strong> API sign-ins, failed sign-ins, token creation and rotation appear in the <a href="/admin/audit-log">Audit Log</a>.</li>
</ul>
<p class="text-muted small mb-0">The <code>/api/...</code> routes without <code>v1</code> belong to the web interface and won't accept a bearer token. The satisfaction-survey webhook (<code>/api/csat/webhook</code>) is separate and is covered in <a href="/admin/docs/csat#webhook">Satisfaction Surveys</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="logs">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-journal-text text-primary me-2"></i>Log Files</h5>
<p class="text-muted mb-2">When something misbehaves, look in <code>storage/logs/</code> on the server:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:240px;">File</th><th>Contains</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>php-error.log</code></td><td>PHP errors and warnings from the website and background jobs. Errors only appear on screen when <code>APP_DEBUG=true</code> in <code>.env</code>, which should stay <code>false</code> on a live site.</td></tr>
        <tr><td><code>smtp.log</code></td><td>Outgoing mail sessions, plus skipped sends while outbound mail is switched off. See <a href="/admin/docs/email">Email &amp; Notifications</a>.</td></tr>
        <tr><td><code>sso-debug.log</code></td><td>Microsoft 365 sign-in steps. See <a href="/admin/docs/sso">Single Sign-On</a>.</td></tr>
        <tr><td>One log per background job</td><td>For example <code>scheduled-reports.log</code>, <code>stale-tickets.log</code> and <code>escalations.log</code>. The Cron Jobs page uses these to show when each job last ran. See <a href="/admin/docs/automations">Automations</a>.</td></tr>
        <tr><td><code>cron-manual-runs.log</code></td><td>Who ran a background job by hand from the Cron Jobs page, dry run or live, and how it finished.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
