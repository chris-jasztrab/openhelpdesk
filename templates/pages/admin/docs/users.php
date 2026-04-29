<?php
$layout       = 'app';
$pageTitle    = 'Docs: Users & Agents';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Users & Agents']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Users &amp; Agents</h2>
<p class="text-muted mb-4">Understand the different account roles and how to manage staff and end-user accounts.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-badge text-primary me-2"></i>Roles Overview</h5>
<p class="text-muted mb-2">Every account in LocalDesk has one of three roles:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Role</th><th>Access</th></tr></thead>
    <tbody>
        <tr>
            <td><span class="badge bg-danger">Admin</span></td>
            <td class="text-muted">Full access to everything — all tickets, all settings, all users, reporting, and system configuration.</td>
        </tr>
        <tr>
            <td><span class="badge bg-primary">Agent</span></td>
            <td class="text-muted">Can view and work on tickets, leave replies and internal notes, update status/priority/assignment. Cannot access admin settings or user management.</td>
        </tr>
        <tr>
            <td><span class="badge bg-secondary">User</span></td>
            <td class="text-muted">End user — can only access the portal to submit and track their own tickets. Cannot log in to the admin interface.</td>
        </tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-plus text-primary me-2"></i>Creating Accounts</h5>
<p class="text-muted mb-2">Admins can create accounts for any role from <a href="/admin/users/create"><strong>Admin → Users → Create User</strong></a>.</p>
<ol class="text-muted mb-0">
    <li>Enter the user's first name, last name, and email address.</li>
    <li>Select the appropriate role (User, Agent, or Admin).</li>
    <li>Optionally assign a location.</li>
    <li>A temporary password is set; the user receives a welcome email (if SMTP is configured) with login instructions.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-check text-primary me-2"></i>Self-Registration</h5>
<p class="text-muted mb-2">End users can register themselves via the portal login page. New self-registered accounts are given the <strong>User</strong> role by default and must verify their email address before logging in.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Self-registered accounts always get the User role. To grant Agent or Admin access, an existing admin must edit the account and change the role.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil text-primary me-2"></i>Editing Users</h5>
<p class="text-muted mb-2">From <a href="/admin/users"><strong>Admin → Users</strong></a>, click on any user to edit their details:</p>
<ul class="text-muted mb-0">
    <li>Update name, email, role or location.</li>
    <li>Reset their password (sends a reset email, or set directly if SMTP is not configured).</li>
    <li>Deactivate an account to prevent login without deleting history.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-geo-alt text-primary me-2"></i>Locations</h5>
<p class="text-muted mb-2">Locations are optional organisational units (e.g. Main Branch, IT Department). They can be assigned to both users and tickets.</p>
<ul class="text-muted mb-0">
    <li>Manage locations at <a href="/admin/locations"><strong>Admin → Settings → Locations</strong></a>.</li>
    <li>When a user has a location, new tickets they submit are pre-tagged with that location.</li>
    <li>The ticket list can be filtered by location for focused queue management.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye text-primary me-2"></i>Location Ticket Visibility</h5>
<p class="text-muted mb-2">By default, portal users can only view tickets they personally submitted. The <strong>Location Ticket Visibility</strong> flag extends this access without granting agent or admin privileges.</p>
<p class="text-muted mb-2">When enabled on a user account, that user can view <em>all</em> tickets assigned to the same location as their account — even ones they did not submit themselves. This is useful for:</p>
<ul class="text-muted mb-3">
    <li>Branch supervisors who need visibility into their site's ticket queue.</li>
    <li>Designated liaisons who coordinate on behalf of a group of end users.</li>
</ul>
<p class="text-muted mb-2">To enable the flag:</p>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/users"><strong>Admin → Users</strong></a> and open the user's account.</li>
    <li>Ensure the user has an <strong>Assigned Location</strong> set.</li>
    <li>Enable the <strong>Location Ticket Visibility</strong> toggle and save.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>Groups &amp; Agent Ticket Visibility</h5>
<p class="text-muted mb-2">Groups are optional teams that agents can belong to (e.g. IT Support, Facilities, HR). They serve two purposes: routing tickets to the right team, and controlling which tickets each agent can see.</p>

<h6 class="fw-semibold mt-3 mb-2">How visibility works</h6>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Agent's group membership</th><th>Tickets visible</th></tr></thead>
    <tbody>
        <tr>
            <td class="text-muted">Belongs to one or more groups</td>
            <td class="text-muted">Only tickets assigned to those groups</td>
        </tr>
        <tr>
            <td class="text-muted">Belongs to no groups</td>
            <td class="text-muted">All tickets (unrestricted)</td>
        </tr>
        <tr>
            <td class="text-muted">Admin (any group membership)</td>
            <td class="text-muted">Always all tickets</td>
        </tr>
    </tbody>
</table>
</div>

<div class="alert alert-info small mb-3"><i class="bi bi-info-circle me-2"></i>
    This is automatic — there is no separate toggle to enable or disable it. An agent's visibility is determined entirely by whether they belong to any groups.
</div>

<h6 class="fw-semibold mb-2">Managing groups</h6>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/groups"><strong>Admin → Settings → Groups</strong></a>.</li>
    <li>Create a group and add agents as members.</li>
    <li>When creating or editing a ticket, assign it to a group so the right agents can see it.</li>
    <li>To give an agent unrestricted access to all tickets without making them an admin, remove them from all groups.</li>
</ol>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-2"></i>
    Each group also has an <strong>Auto-Assignment Strategy</strong> for routing new tickets to a specific member (Round Robin, Load-Based, Skill-Based, First Available). See <a href="/admin/docs/automations#group-auto-assign" class="alert-link">Group Auto-Assignment</a> for the full breakdown.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-mortarboard text-primary me-2"></i>Agent Skills</h5>
<p class="text-muted mb-2"><strong>Skills</strong> are admin-managed labels (e.g. "Billing", "Network", "French", "Cataloguing") that agents can hold. They drive the <a href="/admin/docs/automations#group-auto-assign">Skill-Based</a> auto-assignment strategy: when a new ticket arrives in a group whose strategy is Skill-Based, only members whose skills cover every skill required by the ticket type are eligible.</p>
<p class="text-muted mb-2"><strong>Manage the catalogue</strong> at <a href="/admin/skills"><strong>Admin → Settings → Agent Skills</strong></a>. Each skill has a name, optional description, and sort order. From the skill editor you can also tick which agents hold the skill.</p>
<p class="text-muted mb-2"><strong>Required skills per ticket type</strong> are configured on the Ticket Type form. A ticket type can require zero, one, or several skills.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Today, only admins can create skills and decide which agents hold them. A future release will let group managers maintain skills for their own team without admin involvement.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-toggle-on text-primary me-2"></i>Agent Availability</h5>
<p class="text-muted mb-2">Each agent / power user / admin has an <strong>"I'm available for new tickets"</strong> toggle on their My Profile page. It defaults to on and is read by the <a href="/admin/docs/automations#group-auto-assign">First Available</a> auto-assignment strategy only.</p>
<ul class="text-muted mb-0">
    <li>Flipping yourself off-duty stops First Available from routing new tickets to you. The other strategies (Round Robin, Load-Based, Skill-Based) ignore the flag.</li>
    <li>Direct manual assignment is never blocked by the flag — a colleague can still assign you a ticket if they choose.</li>
    <li>Use it for shift changes, breaks, vacation cover, or follow-the-sun rotations. There is no scheduled-on/scheduled-off; the flag is a manual switch.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-warning me-2"></i>Confidential Groups</h5>
<p class="text-muted mb-2">Any group can be marked <strong>Confidential</strong> to enable a suite of security measures that protect sensitive membership and ticket data. When a group is confidential, every significant action is authenticated, logged, and reported to group members via email.</p>

<h6 class="fw-semibold mt-3 mb-2">Enabling confidential mode</h6>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/groups"><strong>Admin → Settings → Groups</strong></a> and create or edit a group.</li>
    <li>Check the <strong><i class="bi bi-shield-lock me-1"></i>Confidential</strong> checkbox and save.</li>
</ol>

<h6 class="fw-semibold mt-3 mb-2">Security measures in effect</h6>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:30%;">Protection</th><th>Details</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><strong>Membership change alerts</strong></td>
            <td>When members are added to a confidential group, all <em>existing</em> members receive an email alert listing the new member(s), the admin who made the change, their email, IP address, and timestamp. The first member added to an empty group is silent — alerts begin once the group already has at least one member.</td>
        </tr>
        <tr>
            <td><strong>Membership change audit log</strong></td>
            <td>Every attempt to add or remove members from a confidential group is recorded in the <a href="/admin/audit-log">audit log</a> <em>before</em> the database write, so the attempt is preserved even if the update later fails.</td>
        </tr>
        <tr>
            <td><strong>Add-member confirmation dialog</strong></td>
            <td>When adding new members to a confidential group in the edit form, a modal confirmation dialog warns the admin that existing members will be notified and the action will be logged. The admin must explicitly confirm before the form is submitted.</td>
        </tr>
        <tr>
            <td><strong>CSRF failure logging</strong></td>
            <td>If a form submission to edit a confidential group has an invalid CSRF token (possible attack indicator), the attempt is logged in the audit log with the admin's identity.</td>
        </tr>
    </tbody>
</table>
</div>

<h6 class="fw-semibold mt-3 mb-2">Confidential ticket types</h6>
<p class="text-muted mb-2">When a ticket type is linked to a group and marked <strong>Confidential</strong>, additional protections apply to tickets of that type:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:30%;">Protection</th><th>Details</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><strong>Ticket redaction</strong></td>
            <td>Admins who are <em>not</em> members of the ticket type's group see the ticket redacted (subject and details hidden) in all ticket listings.</td>
        </tr>
        <tr>
            <td><strong>Re-authentication gate</strong></td>
            <td>Admins outside the group who attempt to view a confidential ticket must re-enter their password. Access is granted for a 5-minute window per ticket.</td>
        </tr>
        <tr>
            <td><strong>Access notification</strong></td>
            <td>When an admin outside the group views a confidential ticket (after re-authentication), every member of the group receives an email alert with the admin's name, email, IP address, and timestamp.</td>
        </tr>
        <tr>
            <td><strong>Access audit log</strong></td>
            <td>Each confidential ticket access is recorded in the audit log and as an internal timeline entry on the ticket itself.</td>
        </tr>
        <tr>
            <td><strong>Agent access control</strong></td>
            <td>Agents and power users who are not members of the ticket type's group cannot view confidential tickets at all — they are fully hidden from their ticket list.</td>
        </tr>
    </tbody>
</table>
</div>

<h6 class="fw-semibold mt-3 mb-2">Tamper protection</h6>
<p class="text-muted mb-2">To prevent security bypass, confidential groups and ticket types have additional safeguards against removal or deletion of the confidential flag:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:30%;">Action</th><th>Required</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><strong>Remove confidential flag from a group</strong></td>
            <td>Password re-authentication, audit log entry, and email alert to all group members.</td>
        </tr>
        <tr>
            <td><strong>Remove confidential flag from a ticket type</strong></td>
            <td>Password re-authentication, audit log entry, and email alert to all members of the linked group.</td>
        </tr>
        <tr>
            <td><strong>Delete a confidential group</strong></td>
            <td>Password re-authentication, audit log entry, and email alert to all group members before the group is removed.</td>
        </tr>
        <tr>
            <td><strong>Delete a confidential ticket type</strong></td>
            <td>Password re-authentication, audit log entry, and email alert to all members of the linked group before the type is removed.</td>
        </tr>
    </tbody>
</table>
</div>

<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>
    All confidential security events — access, membership changes, flag removal, and deletion — are permanently recorded in the <a href="/admin/audit-log">audit log</a> and cannot be erased through the admin interface.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Profile &amp; Password</h5>
<p class="text-muted mb-2">Any logged-in user can update their own profile and password from the profile menu (top-right avatar). Agents and admins can also update their display name and email from their profile page.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Changing an email address on a self-registered account will require the new address to be verified before it takes effect.
</div>
</div>
</div>


<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Two-Factor Authentication (2FA)</h5>
<p class="text-muted mb-2">Admins and agents can enable TOTP-based two-factor authentication from their profile page (<a href="/profile/2fa/setup"><strong>My Profile → Security → Enable 2FA</strong></a>).</p>
<ol class="text-muted mb-3">
    <li>Open <strong>My Profile</strong> from the top-right avatar menu.</li>
    <li>Click <strong>Enable 2FA</strong> in the Security section.</li>
    <li>Scan the QR code with an authenticator app (Google Authenticator, Authy, 1Password, etc.) or enter the key manually.</li>
    <li>Confirm with a code from the app to activate.</li>
</ol>
<p class="text-muted mb-2">Once enabled, a 6-digit code is required at every login. If a user loses access to their authenticator, an admin can reset their 2FA from the user management page.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Portal (end user) accounts do not have access to 2FA — it is available to admin and agent accounts only.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bell text-primary me-2"></i>Email Notification Preferences</h5>
<p class="text-muted mb-2">Each user can control which email notifications they receive from <strong>My Profile → Notification Preferences</strong>. Preferences are checked before every outgoing notification email, so users only receive the emails they want.</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Preference</th><th>Email sent when…</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>New ticket assigned to me</strong></td><td>A ticket is assigned to the user (agent-side).</td></tr>
        <tr><td><strong>Ticket updated</strong></td><td>A public reply is added to a ticket they are involved with.</td></tr>
        <tr><td><strong>@mentioned in a note</strong></td><td>Another agent @mentions them in an internal note or reply.</td></tr>
        <tr><td><strong>My ticket assigned to an agent</strong></td><td>(Requester-side) Their submitted ticket has been picked up — the email tells them who is handling it.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-lines-fill text-primary me-2"></i>Admin User Profile View</h5>
<p class="text-muted mb-2">Admins can view any user's profile page at <code>/admin/users/{id}</code>. The profile page shows the user's details, role, and a list of open tickets they have submitted.</p>
<p class="text-muted mb-0">From this page admins can also edit the user's account or initiate a deletion with the transfer/delete workflow described below.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-x text-primary me-2"></i>Deleting Users</h5>
<p class="text-muted mb-2">When an admin deletes a user who has associated tickets or KB articles, LocalDesk shows a warning listing those records. The ticket count and article count are clickable links that open the filtered list in a new tab so you can review them before deciding.</p>
<p class="text-muted mb-2">You must choose one of two options before the deletion can proceed:</p>
<ul class="text-muted mb-3">
    <li><strong>Transfer records</strong> — search for another user and move all associated tickets and articles to them.</li>
    <li><strong>Delete all records</strong> — permanently remove the user's tickets, notes, KB articles, and all other associated data.</li>
</ul>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>
    Deletion cannot be undone. Use the transfer option if you need to preserve ticket history.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-moon-stars text-primary me-2"></i>Dark Mode</h5>
<p class="text-muted mb-2">Each user can switch between light and dark mode from <strong>My Profile → Appearance</strong>. The preference is saved to their account and applied on every subsequent page load.</p>
<p class="text-muted mb-0">Dark mode uses Bootstrap 5.3's native theming, so all components — sidebar, tables, cards, modals, and dropdowns — adapt automatically.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-journal-text text-primary me-2"></i>Audit Log</h5>
<p class="text-muted mb-2">Every significant admin action is recorded in the audit log, accessible at <a href="/admin/audit-log"><strong>Admin → Audit Log</strong></a>. Each entry shows the actor (who did it), the action taken, the target record, and a timestamp.</p>
<p class="text-muted mb-0">Logged actions include: user creation and deletion, role changes, settings updates, SLA policy changes, automation rule changes, and more.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
