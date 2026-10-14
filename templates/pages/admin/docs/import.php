<?php
$layout       = 'app';
$pageTitle    = 'Docs: Importing Data';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Importing Data']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Importing Data</h2>
<p class="text-muted mb-4">Bring existing tickets, users and knowledge base articles into OpenHelpDesk from CSV files.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-cloud-upload text-primary me-2"></i>The Three Importers</h5>
<p class="text-muted mb-2">All three live under <strong>Settings → Data</strong>. Each takes a CSV file with a header row (UTF-8), shows a preview, and changes nothing until you confirm.</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:170px">Importer</th><th>Steps</th><th style="width:230px">Permission needed</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><a href="/admin/settings/import"><strong>Import Tickets</strong></a></td><td>Upload, map columns, preview, confirm</td><td><em>Import data &amp; manage backups</em></td></tr>
        <tr><td><a href="/admin/settings/import-users"><strong>Import Users</strong></a></td><td>Upload, map columns, preview, confirm</td><td><em>Manage users</em></td></tr>
        <tr><td><a href="/admin/settings/import-kb"><strong>Import KB</strong></a></td><td>Upload, preview, confirm (see <a href="/admin/docs/kb#import">Knowledge Base</a>)</td><td><em>Import data &amp; manage backups</em></td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<h3 id="tickets" class="fw-bold mt-5 mb-3">Importing Tickets</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-card-checklist text-primary me-2"></i>Import Process</h5>
<ol class="text-muted mb-0">
    <li class="mb-2"><strong>Upload</strong> &mdash; at <a href="/admin/settings/import"><strong>Settings → Data → Import Tickets</strong></a>, choose your CSV and click <strong>Upload &amp; Preview</strong>. Comma, tab, semicolon and pipe separators are detected automatically. Files up to 64 MB are accepted, subject to the server's PHP upload limit.</li>
    <li class="mb-2"><strong>Map CSV Columns</strong> &mdash; for each OpenHelpDesk field, pick the CSV column that holds it, with sample values shown alongside. Columns with familiar names (including common helpdesk exports such as <em>Created time</em>, <em>Due by Time</em> or <em>Type of ticket</em>) are pre-selected; leave a field on <em>— Not mapped —</em> to ignore it. Choose the <strong>SLA handling</strong> here too (see below), then click <strong>Apply &amp; Preview</strong>.</li>
    <li class="mb-2"><strong>Import Preview</strong> &mdash; shows the number of tickets, the first 15 rows as they will be imported, and every location, agent and user that will be created.</li>
    <li><strong>Confirm Import</strong> &mdash; the import runs as one transaction: if anything fails, nothing is imported. The result message reports how many tickets were created, how many rows were skipped, and how SLA was applied.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-columns text-primary me-2"></i>Fields You Can Map</h5>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:170px">Field</th><th>Required</th><th>How it is used</th></tr></thead>
    <tbody class="text-muted">
        <tr><td class="fw-semibold">Subject</td><td><span class="badge bg-danger">Required</span></td><td>The ticket title.</td></tr>
        <tr><td class="fw-semibold">Submitter Email</td><td><span class="badge bg-danger">Required</span></td><td>Matched to an existing user; otherwise a new <strong>User</strong> account is created (see below).</td></tr>
        <tr><td class="fw-semibold">Description</td><td><span class="badge bg-secondary">Optional</span></td><td>The ticket body. Blank becomes "(Imported from legacy system)".</td></tr>
        <tr><td class="fw-semibold">Ticket ID (Legacy)</td><td><span class="badge bg-secondary">Optional</span></td><td>The ticket's number in the old system, kept as a legacy reference. New tickets still get OpenHelpDesk numbers.</td></tr>
        <tr><td class="fw-semibold">Submitter Name</td><td><span class="badge bg-secondary">Optional</span></td><td>Used when a new requester account is created.</td></tr>
        <tr><td class="fw-semibold">Status</td><td><span class="badge bg-secondary">Optional</span></td><td>Recognises <code>open</code>/<code>new</code>, <code>in progress</code>, <code>pending</code>, <code>waiting</code>/<code>waiting on customer</code>, <code>waiting on third party</code>, <code>resolved</code> and <code>closed</code> (any case). Anything else imports as Open.</td></tr>
        <tr><td class="fw-semibold">Priority</td><td><span class="badge bg-secondary">Optional</span></td><td>Matched to an existing priority by name. No match means no priority (and so no SLA clock).</td></tr>
        <tr><td class="fw-semibold">Assigned Agent</td><td><span class="badge bg-secondary">Optional</span></td><td>Matched to an existing user by <strong>full name</strong>. An unknown name creates a new <strong>Agent</strong> account with a placeholder email (<code>first.last@imported.local</code>). <code>No Agent</code> is ignored.</td></tr>
        <tr><td class="fw-semibold">Group</td><td><span class="badge bg-secondary">Optional</span></td><td>Matched by name. No match falls back to the type's default group, then the system Default Group. Groups are never created.</td></tr>
        <tr><td class="fw-semibold">Ticket Type</td><td><span class="badge bg-secondary">Optional</span></td><td>Matched by name; unknown types are created.</td></tr>
        <tr><td class="fw-semibold">Location</td><td><span class="badge bg-secondary">Optional</span></td><td>Matched by name; unknown locations are created.</td></tr>
        <tr><td class="fw-semibold">Created Date</td><td><span class="badge bg-secondary">Optional</span></td><td>Preserves the original creation time. Blank uses the time of import.</td></tr>
        <tr><td class="fw-semibold">Due Date</td><td><span class="badge bg-secondary">Optional</span></td><td>The date part is kept.</td></tr>
        <tr><td class="fw-semibold">Last Updated</td><td><span class="badge bg-secondary">Optional</span></td><td>Defaults to the created date.</td></tr>
        <tr><td class="fw-semibold">First Response Date</td><td><span class="badge bg-secondary">Optional</span></td><td>Recorded as the ticket's first response, so historical SLA can count it.</td></tr>
        <tr><td class="fw-semibold">Tags</td><td><span class="badge bg-secondary">Optional</span></td><td>Comma-separated; new tags are created.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mt-3 mb-0">Dates and times without a timezone are read as local time for the ticket's location; ISO 8601 values that include an offset (e.g. <code>2022-08-22T12:31:35-04:00</code>) are used exactly as written.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-stopwatch text-primary me-2"></i>SLA Handling</h5>
<p class="text-muted mb-2">Imported tickets carry their original open date, so the column-mapping step asks what that means for SLA timers:</p>
<ul class="text-muted mb-3">
    <li><strong>Exclude from SLA</strong> (the default, recommended) &mdash; no clock, now or later. The tickets stay out of SLA reports and compliance figures, and a later priority or type change can't start one.</li>
    <li><strong>Start SLA clocks now</strong> &mdash; treats the backlog as if it arrived today. Nothing is instantly breached, but SLA figures won't reflect how long these tickets really waited.</li>
    <li><strong>Apply from original open date</strong> &mdash; reproduces each ticket's real historical SLA. Any still-open ticket older than its target imports already breached.</li>
</ul>
<p class="text-muted mb-2">With either option that starts clocks, the preview adds a <strong>Which tickets get an SLA clock</strong> panel. It lists each ticket type in the file with its row count and the range of open dates it covers, with a checkbox per type and an optional <em>only start clocks on tickets opened on or after</em> date. A ticket gets a clock only if its type is ticked <em>and</em> it is newer than the cutoff, so a legacy category that is entirely years old can sit the SLA out while the rest of the file is timed normally. Everything outside your choice imports SLA-exempt.</p>
<p class="text-muted mb-0">The panel isn't offered when the mapped Ticket Type column has more than 60 different values; go back to the mapping step and check the column. Tickets with no priority, or no SLA policy for their type and priority, are always imported SLA-exempt.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Things to Know</h5>
<ul class="text-muted mb-0">
    <li><strong>Requester accounts.</strong> A Submitter Email that matches an existing user links the ticket to them. Otherwise a new <strong>User</strong> account is created with a random password; the person can set one with <em>Forgot password?</em> on the login page, or sign in with Microsoft 365 if SSO is set up.</li>
    <li><strong>No notifications.</strong> Imported tickets send no emails or alerts, and automations, AI routing and auto-assignment don't run on them. Each ticket gets a single <em>Ticket created (imported from legacy system)</em> timeline entry.</li>
    <li><strong>Skipped rows.</strong> Rows missing a subject or submitter email are skipped. After the import, the Import Tickets page offers a <strong>Skipped Rows Available</strong> download listing them with the reason; it can be downloaded once.</li>
    <li><strong>No undo.</strong> The import can't be reversed from the app. Test with a small sample file first, and take a backup before a large import.</li>
    <li>Each import is recorded in the <a href="/admin/audit-log">Audit Log</a> as <code>ticket.import_confirmed</code> with the counts and SLA choices.</li>
</ul>
</div>
</div>

<h3 id="users" class="fw-bold mt-5 mb-3">Importing Users</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-plus text-primary me-2"></i>Import Users from CSV</h5>
<p class="text-muted mb-2">At <a href="/admin/settings/import-users"><strong>Settings → Data → Import Users</strong></a>, choose a CSV of up to 10 MB and click <strong>Upload &amp; Preview</strong>. <strong>Download sample-users.csv</strong> on that page shows the expected layout. Then map the columns:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:170px">Field</th><th>Required</th><th>How it is used</th></tr></thead>
    <tbody class="text-muted">
        <tr><td class="fw-semibold">Email Address</td><td><span class="badge bg-danger">Required</span></td><td>Rows without one are skipped.</td></tr>
        <tr><td class="fw-semibold">First Name / Last Name</td><td><span class="badge bg-secondary">Optional</span></td><td>&nbsp;</td></tr>
        <tr><td class="fw-semibold">Full Name</td><td><span class="badge bg-secondary">Optional</span></td><td>Split into first and last name when those aren't mapped.</td></tr>
        <tr><td class="fw-semibold">Role</td><td><span class="badge bg-secondary">Optional</span></td><td>A permission level's name as stored (e.g. <code>user</code>, <code>agent</code>, <code>power_user</code>, <code>admin</code>, or a custom level). Unknown values, and levels you aren't allowed to assign, become <strong>User</strong>.</td></tr>
        <tr><td class="fw-semibold">Work Phone</td><td><span class="badge bg-secondary">Optional</span></td><td>&nbsp;</td></tr>
        <tr><td class="fw-semibold">Location</td><td><span class="badge bg-secondary">Optional</span></td><td>Matched by name; unknown locations are created.</td></tr>
    </tbody>
</table>
</div>
<ul class="text-muted mb-0">
    <li>The preview counts total rows, new users, existing emails and new locations, lists the emails that already exist, and shows the first 15 rows.</li>
    <li>Existing accounts (matched by email) are <strong>skipped</strong>, never overwritten.</li>
    <li>New accounts get a random password; users set their own with <em>Forgot password?</em> or sign in with SSO. No welcome email is sent.</li>
    <li>New staff accounts aren't added to any group, so they see no tickets until you add them to one (see <a href="/admin/docs/users">Users &amp; Roles</a>).</li>
    <li>The import is all-or-nothing and is recorded in the Audit Log as <code>user.import_confirmed</code>.</li>
</ul>
</div>
</div>

<h3 id="kb" class="fw-bold mt-5 mb-3">Importing Knowledge Base Articles</h3>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-book text-primary me-2"></i>Import KB</h5>
<p class="text-muted mb-0"><a href="/admin/settings/import-kb"><strong>Settings → Data → Import KB</strong></a> imports articles from a CSV with <code>title</code> and <code>body_markdown</code> columns, plus optional <code>category</code> and <code>status</code>. The file format, preview and the <em>Publish all articles on import</em> option are described in <a href="/admin/docs/kb#import">Knowledge Base → Importing &amp; Exporting Articles</a>.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
