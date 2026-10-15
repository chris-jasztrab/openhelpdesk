<?php
$layout       = 'app';
$pageTitle    = 'Docs: Getting Started';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Getting Started']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Getting Started</h2>
<p class="text-muted mb-4">The order to set up a new OpenHelpDesk install, from first sign-in to opening the portal to requesters.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-compass text-primary me-2"></i>Finding Your Way Around</h5>
<p class="text-muted mb-2">The left rail holds only <strong>Dashboard</strong>, <strong>Tickets</strong>, <strong>Settings</strong> and <strong>Docs</strong>. Everything you configure is on the <strong>Settings</strong> page, whose left menu is grouped as follows: <em>Operations</em> (status banners), <em>Email</em>, <em>Scheduling</em>, <em>Users &amp; Access</em>, <em>Organization</em>, <em>Knowledge Base</em>, <em>Security</em>, <em>Integrations</em>, <em>Customization</em>, <em>Automation</em>, <em>Reports</em>, <em>Data</em> and <em>System</em>. These docs write menu paths as <em>Settings → group → page</em>.</p>
<ul class="text-muted mb-2">
    <li>The search box at the top of the Settings menu finds pages and individual settings, such as "smtp host" or "primary color". Press <kbd>/</kbd> or <kbd>Ctrl</kbd>+<kbd>K</kbd> to jump to it.</li>
    <li>On a phone, the Settings menu folds behind an <strong>All settings</strong> button.</li>
    <li>Your name menu (top right) has <strong>Restart Tour</strong>, <strong>Wallboard</strong>, <strong>My Profile</strong> and Sign Out. The guided tour starts automatically the first time an admin signs in after installation.</li>
</ul>
<p class="text-muted mb-0">Staff who aren't admins also get a <strong>Settings</strong> link, but their menu lists only the pages their <a href="/admin/docs/users#permission-levels">permission level</a> can open.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hdd-network text-primary me-2"></i>Before You Start: the Server</h5>
<ul class="text-muted mb-0">
    <li><strong><code>APP_URL</code> in <code>.env</code></strong> must be the address people actually use, such as <code>https://help.example.org</code>. Every link in an email, the survey links and the satisfaction-survey webhook address are built from it.</li>
    <li><strong>Background jobs.</strong> SLA timers and ticket reminders, inbound email, escalations, recurring tickets, scheduled reports and out-of-office coverage all run from cron (Task Scheduler on Windows). <a href="/admin/settings/cron-jobs"><strong>Settings → Automation → Cron Jobs</strong></a> lists the exact command for each job, and shows whether each one is running. See <a href="/admin/docs/automations">Automations</a>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-1-circle-fill text-primary me-2"></i>Configure Outgoing Email</h5>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/settings"><strong>Settings → Email → Email / SMTP</strong></a>.</li>
    <li>Enter the SMTP host, port, encryption (TLS recommended), username and password, plus the <strong>From Address</strong> and <strong>From Name</strong> recipients will see. Click <strong>Save Settings</strong>.</li>
    <li>In the <strong>Test Email</strong> card, enter an address and click <strong>Send</strong>.</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Microsoft 365 uses <code>smtp.office365.com:587</code> with TLS, and Gmail uses <code>smtp.gmail.com:587</code> with TLS. If outbound mail is switched off on the server (<code>MAIL_ENABLED=false</code> in <code>.env</code>), messages are held in the Email Queue instead of being sent. Inbound email, templates and notifications are covered in <a href="/admin/docs/email">Email &amp; Notifications</a>.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-2-circle-fill text-primary me-2"></i>Describe Your Organization</h5>
<ul class="text-muted mb-0">
    <li><a href="/admin/settings/organization"><strong>Settings → Organization → Organization Type</strong></a>: pick your sector (public library, school, municipality and so on). <em>Suggest with AI</em> on Agent Skills uses it as context.</li>
    <li><a href="/admin/settings/branding"><strong>Settings → Customization → Branding</strong></a>: application name, logo and colours.</li>
    <li><a href="/admin/settings/labels"><strong>Settings → Customization → Labels</strong></a>: rename terms such as <em>Location</em> to <em>Branch</em>, and reword what the portal shows requesters.</li>
</ul>
<p class="text-muted small mt-2 mb-0">See <a href="/admin/docs/branding">Branding &amp; Labels</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-3-circle-fill text-primary me-2"></i>Add Locations</h5>
<p class="text-muted mb-0">At <a href="/admin/locations"><strong>Settings → Organization → Locations</strong></a>, add your branches, sites or departments. Users get a home location, tickets record where the problem is, status banners can target a location, and reports compare locations. If you renamed the Location label, the menu item uses your new name.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-4-circle-fill text-primary me-2"></i>Set Hours and Service Targets</h5>
<p class="text-muted mb-0">Under <strong>Settings → Scheduling</strong>, set <a href="/admin/settings/business-hours">Business Hours</a> and <a href="/admin/settings/holidays">Holidays</a>, then define response and resolution targets in <a href="/admin/settings/sla-policies">SLA Policies</a>. SLA tracking can be switched off entirely if you don't use targets. See <a href="/admin/docs/sla">SLA Policies</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-5-circle-fill text-primary me-2"></i>Shape Your Tickets</h5>
<p class="text-muted mb-0">Under <strong>Settings → Organization</strong>, review <a href="/admin/settings/ticket-statuses">Ticket Statuses</a> and <a href="/admin/priorities">Ticket Priorities</a>. Then create your <a href="/admin/types">Ticket Types</a> (each with a default group) and lay out each type's form in <a href="/admin/workflows/ticket-fields">Ticket Forms</a>. <a href="/admin/types/matrix">Type Settings Matrix</a> shows every type's options side by side. See <a href="/admin/docs/tickets">Tickets</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-6-circle-fill text-primary me-2"></i>Create Groups and Routing</h5>
<p class="text-muted mb-0">Create teams at <a href="/admin/groups"><strong>Settings → Organization → Groups</strong></a> and give each a clear description. Choose the catch-all <strong>Default Group</strong> at <a href="/admin/settings/ticket-routing">Ticket Routing</a>, and add <a href="/admin/skills">Agent Skills</a> if you plan to route by skill. Each group can auto-assign new tickets to its members. See <a href="/admin/docs/users">Users &amp; Roles</a>, <a href="/admin/docs/automations#group-auto-assign">Group Auto-Assignment</a> and <a href="/admin/docs/flows">Assignment Flows</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-7-circle-fill text-primary me-2"></i>Add Your Staff</h5>
<ol class="text-muted mb-2">
    <li>Go to <a href="/admin/users/create"><strong>Settings → Users &amp; Access → Users → Add User</strong></a>.</li>
    <li>Pick a <strong>Permission Level</strong> (Agent, Power User, Admin, or a level you create at <a href="/admin/roles">Permission Levels</a>) and tick the groups they work in.</li>
    <li>Optionally set their location, work phone and avatar.</li>
</ol>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    A staff member who isn't in any group sees no tickets, unless their permission level can view all tickets. No welcome email is sent. Give new staff their password yourself, have them use <strong>Forgot password?</strong> on the sign-in page, or let them sign in with <a href="/admin/docs/sso">Microsoft 365</a>. To add many people at once, use <a href="/admin/settings/import-users">Settings → Data → Import Users</a>.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-8-circle-fill text-primary me-2"></i>Turn On What You Need</h5>
<ul class="text-muted mb-0">
    <li><strong>Email-to-ticket and reply-by-email</strong>: <a href="/admin/docs/email">Email &amp; Notifications</a></li>
    <li><strong>Microsoft 365 sign-in</strong>: <a href="/admin/docs/sso">Single Sign-On</a></li>
    <li><strong>Teams channel alerts</strong>: <a href="/admin/docs/teams">Microsoft Teams</a></li>
    <li><strong>Automation rules, escalations and ticket reminders</strong>: <a href="/admin/docs/automations">Automations</a></li>
    <li><strong>AI routing, duplicate detection and similar past tickets</strong>: <a href="/admin/docs/ai">AI Classification</a></li>
    <li><strong>Satisfaction surveys</strong>: <a href="/admin/docs/csat">Satisfaction Surveys</a></li>
    <li><strong>Knowledge base articles</strong>: <a href="/admin/docs/kb">Knowledge Base</a></li>
    <li><strong>Existing tickets from another system</strong>: <a href="/admin/docs/import">Importing Tickets</a></li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-9-circle-fill text-primary me-2"></i>Open the Portal</h5>
<p class="text-muted mb-2">Requesters submit and track their requests at:</p>
<div class="bg-light rounded px-3 py-2 font-monospace small mb-2"><strong><?= e(env('APP_URL', 'http://your-site.com')) ?>/portal</strong></div>
<p class="text-muted mb-0">There is no self-registration. Requesters get an account when you create or import them, when they first sign in with Microsoft 365, or when they email the helpdesk (if auto-create is on). You can also link people straight to one ticket type's form. See <a href="/admin/docs/portal">Portal</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-counterclockwise text-primary me-2"></i>Starting Over</h5>
<p class="text-muted mb-0">After an evaluation, <strong>Reset Everything</strong> in the Danger Zone wipes all data and sends you back to the Setup Wizard. See <a href="/admin/docs/system#danger-zone">System &amp; Maintenance</a>, and take a <a href="/admin/docs/system#backup">backup</a> first.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
