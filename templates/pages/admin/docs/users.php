<?php
$layout       = 'app';
$pageTitle    = 'Docs: Users & Agents';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Users & Agents']];
$locLabel     = label('location.singular', 'Location');
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Users &amp; Agents</h2>
<p class="text-muted mb-4">Account levels, permissions, groups, ticket visibility, and how to manage staff and end-user accounts.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-badge text-primary me-2"></i>Roles Overview</h5>
<p class="text-muted mb-2">Every account has a <strong>permission level</strong>. Four levels are built in; admins can also create their own (see <a href="#permission-levels">Permission Levels</a> below). Out of the box:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Level</th><th>Access</th></tr></thead>
    <tbody>
        <tr>
            <td><span class="badge bg-danger">Admin</span></td>
            <td class="text-muted">Everything: all tickets, all settings, users and permission levels. Bypasses every permission check. Confidential tickets still need a password re-check (see <a href="#confidential-groups">Confidential Groups</a>).</td>
        </tr>
        <tr>
            <td><span class="badge" style="background:#7c3aed;">Power User</span></td>
            <td class="text-muted">Everything an Agent has, plus <strong>View reports</strong> and <strong>View all tickets</strong> (every non-confidential ticket, whatever its group).</td>
        </tr>
        <tr>
            <td><span class="badge bg-primary">Agent</span></td>
            <td class="text-muted">Works tickets in their groups: replies, internal notes, status, priority and assignment. Also granted by default: ticket templates, recurring tickets, forwarding (internal and external), and the six non-destructive bulk actions.</td>
        </tr>
        <tr>
            <td><span class="badge bg-secondary">End User</span></td>
            <td class="text-muted">Portal only: submits and tracks their own requests. Cannot sign in to the staff interface.</td>
        </tr>
    </tbody>
</table>
</div>
<p class="text-muted small mt-3 mb-0">Admin, Power User, Agent and every custom level are <strong>staff</strong> levels (they sign in to the agent interface). A non-admin staff member sees a single <strong>Settings</strong> link, and the Settings menu lists only the areas their level can open. Opening a page you aren't allowed to see returns you to your dashboard with a "You do not have permission to access that page" banner.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="permission-levels">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Permission Levels &amp; Granular Permissions</h5>
<p class="text-muted mb-2">Create your own levels, or change what the built-in Agent and Power User levels can do, at <a href="/admin/roles"><strong>Settings &rarr; Users &amp; Access &rarr; Permission Levels</strong></a>.</p>
<ol class="text-muted mb-3">
    <li>Click <strong>Add Permission Level</strong> (or edit an existing one).</li>
    <li>Give it a display name (e.g. "KB Editor", "Branch Supervisor") and an optional description.</li>
    <li>Tick the permissions it should hold, then save. The level becomes selectable on the user form, the users-list filter and CSV user import.</li>
</ol>
<h6 class="fw-semibold mt-3 mb-2">Grantable permissions</h6>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:22%;">Category</th><th>Permissions</th></tr></thead>
    <tbody class="text-muted">
        <tr><td>Reports</td><td>View reports</td></tr>
        <tr><td>Knowledge Base</td><td>Create &amp; delete KB articles (also needed to edit them); Manage KB categories &amp; folders</td></tr>
        <tr><td>Tickets</td><td>Forward tickets to internal contacts; Forward tickets to external contacts; Manage ticket templates; View all tickets; Manage recurring tickets; Manage ticket types &amp; forms; Manage priorities; Manage SLA policies</td></tr>
        <tr><td>People</td><td>Manage users; Manage groups; Manage agent skills</td></tr>
        <tr><td>Organization</td><td>Manage locations</td></tr>
        <tr><td>Automation</td><td>Manage automations &amp; escalations; View out-of-office status; Manage CSAT surveys; Manage AI classification</td></tr>
        <tr><td>Data</td><td>Import data &amp; manage backups</td></tr>
        <tr><td>System</td><td>View the audit log</td></tr>
        <tr><td>Settings</td><td>Manage general settings (email, templates, branding, labels and the other general pages)</td></tr>
        <tr><td>Bulk Actions</td><td>Bulk assign; Bulk close; Bulk merge; Bulk change status; Bulk change priority; Bulk change group; Bulk delete (off for every level by default)</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-3"><strong>View all tickets</strong> lets a level see every non-confidential ticket regardless of group; the editor asks you to confirm before granting it, and users holding it carry a <span class="badge bg-warning text-dark">Sees all tickets</span> badge in the user list.</p>
<div class="alert alert-warning small mb-2"><i class="bi bi-shield-lock me-2"></i>
    <strong>Admin-only areas.</strong> Permission Levels, Microsoft Teams, the Danger Zone and these Docs can't be granted to any other level, so no level can raise its own (or anyone else's) power.
</div>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Built-in levels can't be deleted. A custom level still assigned to users can't be deleted until those users are moved to another level.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="user-management-limits">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-warning me-2"></i>What a Level Can Change About Other Users</h5>
<p class="text-muted mb-2">The <strong>Manage users</strong> permission lets a non-admin level create, edit and delete accounts, but never in a way that hands out more power than it holds. These rules are enforced on the server for create, edit, delete and CSV import:</p>
<ul class="text-muted mb-2">
    <li><strong>Can't assign a level above its own.</strong> A non-admin can only assign levels whose permissions are a subset of their own, and never Admin. The Permission Level list shows only the levels they may assign.</li>
    <li><strong>Can't touch a user who outranks them.</strong> For such a user the Permission Level, Email and Password fields are locked, and the Delete control is hidden.</li>
</ul>
<div class="alert alert-warning small mb-0"><i class="bi bi-shield-lock me-2"></i>
    Admins are never restricted by these rules.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="ticket-visibility">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye text-primary me-2"></i>Which Tickets Staff Can See</h5>
<p class="text-muted mb-2">Ticket visibility comes from group membership and permissions. It applies everywhere: ticket lists, dashboards, the wallboard, Kanban, floor mode, search, exports and the API.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:38%;">Staff member</th><th>Tickets visible</th></tr></thead>
    <tbody class="text-muted">
        <tr><td>In one or more groups</td><td>Tickets in those groups, plus tickets with no group.</td></tr>
        <tr><td>In no group</td><td>Nothing from the queue, only tickets below.</td></tr>
        <tr><td>Anyone (on top of the above)</td><td>Tickets assigned to them, created by them, watched by them, or that they are CC'd on.</td></tr>
        <tr><td>Level with <strong>View all tickets</strong></td><td>Every non-confidential ticket.</td></tr>
        <tr><td>Admin</td><td>Every ticket (confidential ones redacted until a password re-check).</td></tr>
    </tbody>
</table>
</div>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    A staff user with no group sees almost nothing and is never auto-assigned or sent new-ticket alerts. Saving a staff user with no group shows a confirmation explaining this (it's skipped for levels that see all tickets and for users with <?= e($locLabel) ?> Ticket Visibility).
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-plus text-primary me-2"></i>Adding Users</h5>
<p class="text-muted mb-2">Go to <a href="/admin/users/create"><strong>Settings &rarr; Users &amp; Access &rarr; Users &rarr; Add User</strong></a>:</p>
<ul class="text-muted mb-2">
    <li><strong>First Name, Last Name, Email, Password</strong> (all required) and <strong>Permission Level</strong>.</li>
    <li>Optional <strong>Work Phone</strong>, <strong>Assigned <?= e($locLabel) ?></strong> and <strong>Avatar Image</strong> (JPG, PNG, GIF or WEBP, max 2 MB).</li>
    <li><strong><?= e($locLabel) ?> Ticket Visibility</strong> and <strong>Shared account</strong> switches (both described below).</li>
    <li><strong>Group Membership</strong>, which appears as soon as you pick a staff level (see <a href="#group-membership">Group Membership</a>).</li>
</ul>
<p class="text-muted mb-0">No welcome email is sent. Give the user their password yourself, or let them set one with <em>Forgot password?</em> on the login page. To add many people at once, use <a href="/admin/settings/import-users">Settings &rarr; Data &rarr; Import Users</a>. People who sign in with <a href="/admin/docs/sso">Microsoft 365 SSO</a> get an End User account automatically on first sign-in.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>The Users List</h5>
<p class="text-muted mb-2"><a href="/admin/users"><strong>Settings &rarr; Users &amp; Access &rarr; Users</strong></a> lists every account.</p>
<ul class="text-muted mb-0">
    <li><strong>Search users&hellip;</strong> filters the table as you type and keeps any active filters. <strong>Filters</strong> narrows by permission level, <?= e(strtolower($locLabel)) ?> and "External contacts only". Ticking <em>System Wide</em> in the <?= e(strtolower($locLabel)) ?> filter means "any <?= e(strtolower($locLabel)) ?>".</li>
    <li>Badges show the level, <span class="badge bg-warning text-dark">Sees all tickets</span>, <span class="badge bg-secondary">External</span>, and an eye icon for users with <?= e($locLabel) ?> Ticket Visibility.</li>
    <li>Tick rows (or the header box to select everyone in view) to <strong>Enable</strong> or <strong>Disable</strong> <?= e($locLabel) ?> Ticket Visibility in bulk. Each run is written to the audit log.</li>
    <li>The toolbar also has <strong>Who's Online</strong>, <strong>Merge Users</strong> and <strong>Add User</strong>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil text-primary me-2"></i>Editing Users</h5>
<p class="text-muted mb-2">Click a user to open their profile page, then <strong>Edit User</strong>:</p>
<ul class="text-muted mb-2">
    <li>Change name, email, permission level, <?= e(strtolower($locLabel)) ?>, phone, avatar, flags and group membership.</li>
    <li>Type a new <strong>Password</strong> to reset it (leave blank to keep the current one).</li>
    <li>Use <strong>Reset 2FA</strong> on the profile page if the user has lost their authenticator.</li>
</ul>
<p class="text-muted mb-0">There is no "deactivate" switch. To stop someone signing in, change their password or <a href="#deleting-users">delete</a> the account. Level changes and deletions apply on the user's next page load, without waiting for them to sign out.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-lines-fill text-primary me-2"></i>User Profile Page</h5>
<p class="text-muted mb-2">Each user's profile page (<code>/admin/users/{id}</code>) shows their details and level, plus their open tickets. A <strong>Filters</strong> panel switches the list to other statuses and criteria.</p>
<p class="text-muted mb-0">Buttons: <strong>Edit User</strong>, <strong>Reset 2FA</strong> (only when 2FA is on), <strong>Merge Into Another</strong> and <strong>Delete User</strong>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-box-arrow-up-right text-primary me-2"></i>External Contacts</h5>
<p class="text-muted mb-2">When an agent forwards a ticket to an email address that isn't already a user, the helpdesk creates a lightweight <strong>external contact</strong> account so that person's email replies thread back onto the ticket.</p>
<ul class="text-muted mb-0">
    <li>They carry an <span class="badge bg-secondary">External</span> badge, and the <strong>External contacts only</strong> filter lists just them.</li>
    <li>They are not staff, and they are excluded from Person-field directory lookups.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pc-display text-primary me-2"></i>Shared Accounts</h5>
<p class="text-muted mb-0">Tick <strong>Shared account</strong> on a login used by more than one person (a service desk, a branch workstation). On its own it grants or removes nothing. It lets ticket forms show extra fields only to people signed in this way, typically a Person field asking who is actually raising the ticket (set up in the <a href="/admin/workflows/ticket-fields">form builder</a>). Shared accounts are excluded from Person-field lookups.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-geo-alt text-primary me-2"></i><?= e(label('location.plural', 'Locations')) ?></h5>
<p class="text-muted mb-2"><?= e(label('location.plural', 'Locations')) ?> (branches, sites, departments) can be set on users and tickets. Manage them at <a href="/admin/locations"><strong>Settings &rarr; Organization &rarr; <?= e(label('location.plural', 'Locations')) ?></strong></a>.</p>
<ul class="text-muted mb-0">
    <li>A user's <?= e(strtolower($locLabel)) ?> pre-fills new tickets they submit.</li>
    <li>Ticket lists, reports and the users list can be filtered by <?= e(strtolower($locLabel)) ?>.</li>
    <li>Deleting a <?= e(strtolower($locLabel)) ?> that still has tickets asks you to move those tickets to another one first.</li>
    <li>Rename the word "<?= e($locLabel) ?>" (e.g. to "Branch") on the Labels page.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="location-visibility">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-eye text-primary me-2"></i><?= e($locLabel) ?> Ticket Visibility</h5>
<p class="text-muted mb-2">Portal users normally see only the requests they submitted. <strong><?= e($locLabel) ?> Ticket Visibility</strong> lets a user also see every ticket at their own <?= e(strtolower($locLabel)) ?>, without making them staff. It suits branch supervisors and liaisons; in the portal they get a <strong>My <?= e($locLabel) ?></strong> toggle on My Requests.</p>
<ol class="text-muted mb-2">
    <li>Make sure the user has an <strong>Assigned <?= e($locLabel) ?></strong>.</li>
    <li>Turn on <strong><?= e($locLabel) ?> Ticket Visibility</strong> on their account, or use the bulk Enable/Disable bar on the users list.</li>
</ol>
<ul class="text-muted mb-0">
    <li>Confidential ticket types are never shown this way.</li>
    <li>Untick <strong>Visible to Location Ticket Visibility users</strong> on a <a href="/admin/types">ticket type</a> to hide that type too (e.g. HR or payroll requests) without making it confidential.</li>
    <li>Requesters always see their own tickets.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="groups">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people-fill text-primary me-2"></i>Groups</h5>
<p class="text-muted mb-2">Groups are teams (IT, Facilities, Circulation&hellip;). They decide which tickets staff can see, where new tickets are routed, and who is auto-assigned. Manage them at <a href="/admin/groups"><strong>Settings &rarr; Organization &rarr; Groups</strong></a>. Each group has:</p>
<ul class="text-muted mb-0">
    <li><strong>Group Name</strong> and <strong>Description</strong>. AI group routing reads the description, so describe what the team handles.</li>
    <li><strong>Members</strong>, each with a <strong>Manager</strong> sub-checkbox (see <a href="#group-managers">Group Managers</a>).</li>
    <li><strong>Notify members when a new ticket is created</strong>. Members can still opt out on their own profile.</li>
    <li><strong>Confidential</strong> (see <a href="#confidential-groups">Confidential Groups</a>).</li>
    <li><strong>Auto-Assignment Strategy</strong> and <strong>If no agent matches</strong> fallback (see <a href="/admin/docs/automations#group-auto-assign">Group Auto-Assignment</a>).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="group-membership">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>Group Membership</h5>
<p class="text-muted mb-2">Membership can be edited from either side, and both write the same records:</p>
<ul class="text-muted mb-2">
    <li><strong>One group, many people:</strong> <a href="/admin/groups">Groups</a> &rarr; edit the group &rarr; Members.</li>
    <li><strong>One person, many groups:</strong> <a href="/admin/users">Users</a> &rarr; edit the user (or <strong>Add User</strong>) &rarr; <strong>Group Membership</strong>.</li>
</ul>
<p class="text-muted mb-2">On the user form the picker appears only for staff levels. It shows each group's member count, flags <i class="bi bi-shield-lock-fill text-warning"></i> confidential groups, has a filter box when there are more than six groups, and warns live if a staff user would end up in no group.</p>
<p class="text-muted mb-0">Editing membership from the user form needs <strong>Manage groups</strong> on top of <strong>Manage users</strong>; without it the list is read-only. Changes are logged as <code>group.members_changed</code> / <code>group.managers_changed</code>, and adding someone to a confidential group asks for confirmation and emails the existing members.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-mortarboard text-primary me-2"></i>Agent Skills</h5>
<p class="text-muted mb-2"><strong>Skills</strong> (e.g. "Billing", "Network", "French") drive the <a href="/admin/docs/automations#group-auto-assign">Skill-Based</a> auto-assignment strategy: only members holding every skill the ticket type requires are eligible.</p>
<p class="text-muted mb-2">Manage them at <a href="/admin/skills"><strong>Settings &rarr; Organization &rarr; Agent Skills</strong></a>. Each skill has a name, description, the agents who hold it, and a <strong>Scope</strong>:</p>
<ul class="text-muted mb-2">
    <li><strong>Global</strong>: admin-managed, usable by any group.</li>
    <li><strong>Owned by a group</strong>: its <a href="#group-managers">managers</a> can edit it and assign it to their team. The agent checklist shows only that group's members.</li>
</ul>
<p class="text-muted mb-0">Required skills are set per <a href="/admin/types">ticket type</a> (admin-only). <strong>Suggest with AI</strong> on the skills page proposes a starter list using the configured AI provider.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="group-managers">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-stars text-primary me-2"></i>Group Managers</h5>
<p class="text-muted mb-2">A <strong>group manager</strong> is a member with extra rights over their team's skills, and the person who receives stale-ticket manager alerts. Tick <strong>Manager</strong> next to a member on the group form (or on the user's Group Membership picker). A group can have several managers; unticking Member clears Manager too.</p>
<h6 class="fw-semibold mt-3 mb-2">The Manage My Team area</h6>
<p class="text-muted mb-2">Managers get <strong><i class="bi bi-stars"></i> Manage My Team</strong> in the account menu (top-right), which opens <code>/manager</code> with the groups they manage:</p>
<ul class="text-muted mb-3">
    <li><strong>Team skills</strong>: a grid of members &times; skills (the group's own plus global). Tick to grant, untick to revoke, then save.</li>
    <li><strong>Group skills</strong>: create, edit and delete skills owned by the group. Global skills are listed read-only.</li>
</ul>
<h6 class="fw-semibold mt-3 mb-2">What a manager cannot do</h6>
<ul class="text-muted mb-3">
    <li>Edit global skills, or skills owned by other groups.</li>
    <li>Change which skills a ticket type requires.</li>
    <li>Add or remove group members, or promote/demote managers.</li>
</ul>
<p class="text-muted mb-0">Manager actions are audited as <code>manager.skill_assignments_changed</code>, <code>manager.skill_created</code>, <code>manager.skill_updated</code> and <code>manager.skill_deleted</code>; changes to who manages a group are logged as <code>group.managers_changed</code>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-circle-fill text-success me-2"></i>Online Presence</h5>
<p class="text-muted mb-2">While someone has the app open, their browser sends a heartbeat every 30 seconds. Anyone heard from in the last 2 minutes counts as <strong>online</strong> and appears on <a href="/admin/users/online"><strong>Who's Online</strong></a> (name, level, last seen, IP and browser, refreshing every 15 seconds). The same data drives the <a href="/admin/docs/automations#group-auto-assign">First Available</a> strategy.</p>
<ul class="text-muted mb-0">
    <li>Background tabs and minimised windows still count as online. Closing the tab or signing out takes the user offline straight away.</li>
    <li>A sleeping computer stops the heartbeat; the user reappears within 30 seconds of waking.</li>
    <li>There is no manual "available" switch, and presence never blocks manual assignment.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="confidential-groups">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-warning me-2"></i>Confidential Groups</h5>
<p class="text-muted mb-2">Mark a group <strong><i class="bi bi-shield-lock me-1"></i>Confidential</strong> on its edit form to protect sensitive membership and tickets.</p>
<h6 class="fw-semibold mt-3 mb-2">Group protections</h6>
<ul class="text-muted mb-3">
    <li>Adding members asks for confirmation and emails every existing member (who was added, by whom, their email, IP and time). The first member of an empty group is added silently.</li>
    <li>Every membership change is written to the audit log before it is saved, and an invalid-token submission against a confidential group is logged too.</li>
</ul>
<h6 class="fw-semibold mt-3 mb-2">Confidential ticket types</h6>
<p class="text-muted mb-2">A <a href="/admin/types">ticket type</a> linked to a group and marked <strong>Confidential</strong> gets these protections:</p>
<ul class="text-muted mb-3">
    <li>Only members of that group and the ticket's creator can see its tickets. Being assigned, watching, being CC'd or holding <strong>View all tickets</strong> doesn't grant access, and watchers can't be added.</li>
    <li>Admins outside the group see <em>[Confidential]</em> in lists. Opening one asks for their password (access lasts 5 minutes per ticket), is logged on the ticket timeline and in the audit log, and emails every group member.</li>
    <li>Group notification emails go only to that group, with the subject redacted to <em>[Confidential ticket]</em>.</li>
    <li>These tickets are never sent to the AI provider, can't be forwarded, and can only be merged with tickets of the same type.</li>
</ul>
<h6 class="fw-semibold mt-3 mb-2">Tamper protection</h6>
<p class="text-muted mb-2">Removing the Confidential flag from a group or ticket type, or deleting a confidential group or type, requires the admin's password. The attempt is audit-logged (even if cancelled) and every affected group member gets a security alert email.</p>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>
    Confidential access, membership changes, flag removal and deletion are all recorded in the <a href="#audit-log">audit log</a>.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-gear text-primary me-2"></i>My Profile</h5>
<p class="text-muted mb-2">Everyone manages their own settings at <a href="/profile"><strong>My Profile</strong></a> (account menu, top-right). Changes save instantly, with a brief "Saved" toast. Only Change Password has its own button.</p>
<ul class="text-muted mb-0">
    <li><strong>Personal Information</strong>: first and last name. Only an admin (or someone with Manage users) can change an email address.</li>
    <li><strong>Appearance</strong>: Light or Dark mode, applied immediately and remembered on the account.</li>
    <li><strong>Ticket Timeline</strong>: newest-first or oldest-first.</li>
    <li><strong>System Timeline Notes</strong> / <strong>AI Timeline Notes</strong>: show or hide automated and AI entries on ticket timelines.</li>
    <li><strong>Email Notifications</strong> (see <a href="#notification-preferences">below</a>).</li>
    <li><strong>Change Password</strong>: current password, then a new one of at least 8 characters. Changing it signs out the user's mobile/API tokens.</li>
    <li><strong>Two-Factor Authentication</strong> and <strong>Canned Responses</strong> (staff only).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-key text-primary me-2"></i>Passwords &amp; Sign-In Protection</h5>
<p class="text-muted mb-2">Users can reset a forgotten password themselves with <strong>Forgot password?</strong> on the login page:</p>
<ol class="text-muted mb-2">
    <li>They enter their email address.</li>
    <li>If an account with a password exists, a one-time link is emailed. It works once and expires after 60 minutes.</li>
    <li>They choose a new password (at least 8 characters) and sign in.</li>
</ol>
<ul class="text-muted mb-0">
    <li>The confirmation message is the same whether or not the address exists, and requests are limited to 5 per email per 15 minutes. Both steps are audit-logged.</li>
    <li>Accounts that sign in only through <a href="/admin/docs/sso">Microsoft 365 SSO</a> have no password to reset.</li>
    <li>Sign-in is throttled after 5 failed attempts per email address, or 10 per IP address, within 15 minutes.</li>
    <li>If outbound mail is switched off on the server, reset emails still go out when <code>MAIL_TRANSACTIONAL_ENABLED=true</code> (see <a href="/admin/docs/email">Email</a>).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Two-Factor Authentication (2FA)</h5>
<p class="text-muted mb-2">Any staff level can turn on app-based (TOTP) 2FA. End users can't.</p>
<ol class="text-muted mb-2">
    <li>Open <strong>My Profile</strong> and click <strong>Set Up 2FA</strong> in the Two-Factor Authentication card.</li>
    <li>Scan the QR code with an authenticator app (Microsoft Authenticator, Google Authenticator, 1Password&hellip;) or type the key.</li>
    <li>Confirm with a code from the app.</li>
</ol>
<p class="text-muted mb-0">After that, every password sign-in asks for a 6-digit code. A code can't be reused, and 5 wrong codes cancel the sign-in. To turn 2FA off, the user enters a current code and clicks <strong>Disable 2FA</strong>. If they lose their device, an admin clicks <strong>Reset 2FA</strong> on their user profile page. Microsoft SSO sign-ins don't use this 2FA; enforce MFA in Entra ID instead.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="notification-preferences">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bell text-primary me-2"></i>Email Notification Preferences</h5>
<p class="text-muted mb-2">Each user chooses which emails they receive under <strong>My Profile &rarr; Email Notifications</strong>. An email goes out only when the site-wide switch on <a href="/admin/settings/email-notifications">Email Notifications</a> is on <em>and</em> the user hasn't opted out. In-app notifications (the bell) ignore these preferences.</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:26%;">Section</th><th>Options</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Agent Notifications</strong> (staff)</td><td>New ticket in my group; Ticket assigned to my group; Ticket assigned to me; Requester replies; Internal note added</td></tr>
        <tr><td><strong>Other Notifications</strong> (staff)</td><td>CC updates; Escalation alerts; My ticket assigned</td></tr>
        <tr><td><strong>Tickets You Submit</strong> (staff)</td><td>Ticket submitted; Agent replies; Ticket merged; Ticket resolved; Ticket closed; Satisfaction surveys</td></tr>
        <tr><td>End users</td><td>Ticket submitted; Ticket assigned; Agent replies; CC updates; Ticket merged; Ticket resolved; Ticket closed; Satisfaction surveys</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mt-3 mb-0">@mentions notify only in-app, never by email.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="deleting-users">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-x text-primary me-2"></i>Deleting Users</h5>
<p class="text-muted mb-2">Use <strong>Delete User</strong> on the user's profile page (or the trash icon in the list). You can't delete your own account. If the user has tickets or KB articles, the dialog shows clickable counts so you can review them first, and you must choose:</p>
<ul class="text-muted mb-2">
    <li><strong>Transfer records</strong>: move their submitted tickets, assigned tickets and KB articles to another user you pick.</li>
    <li><strong>Delete all records</strong>: permanently delete the tickets they submitted (with their conversations and attachments), their KB articles and their ticket templates. Tickets assigned to them become unassigned.</li>
</ul>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>
    Deletion can't be undone. Non-admins with Manage users can't delete someone who outranks them.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-left-right text-primary me-2"></i>Merging Duplicate Accounts</h5>
<p class="text-muted mb-0">When one person has two accounts (e.g. one created by email-to-ticket and one by SSO), use <a href="/admin/users/merge"><strong>Merge Users</strong></a> (or <strong>Merge Into Another</strong> on a profile). Pick the <strong>Account to Remove</strong> and the <strong>Account to Keep</strong>, then <strong>Preview Merge</strong> to compare them. Confirming moves all tickets, comments, CC and watcher entries and group memberships to the kept account and deletes the other. It is logged as <code>user.merged</code> and can't be undone.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="audit-log">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-journal-text text-primary me-2"></i>Audit Log</h5>
<p class="text-muted mb-2">Significant actions are recorded at <a href="/admin/audit-log"><strong>Settings &rarr; System &rarr; Audit Log</strong></a> (needs the <strong>View the audit log</strong> permission). Each entry shows when, who, the action, its target, details and, where captured, the IP address.</p>
<h6 class="fw-semibold mt-3 mb-2">What gets logged</h6>
<ul class="text-muted mb-3">
    <li><strong>Sign-in</strong>: logins, failed logins, logouts, closed tabs and timed-out sessions, password changes and resets, 2FA changes, API logins and tokens.</li>
    <li><strong>Configuration</strong>: create/update/delete across settings, including users, groups, types, priorities, SLA, automations, escalation rules, templates, KB and integration settings. Updates show <code>field: old &rarr; new</code>; secrets appear only as <em>(rotated)</em> / <em>(unchanged)</em>.</li>
    <li><strong>Bulk operations</strong>: bulk ticket and user actions, CSV imports, backups and the Danger Zone reset, with counts.</li>
    <li><strong>Confidential access</strong>: every view of, and change to, a confidential group or ticket type.</li>
</ul>
<h6 class="fw-semibold mt-3 mb-2">Filtering &amp; ticket history</h6>
<ul class="text-muted mb-3">
    <li>Filter by <strong>Actor</strong>, <strong>Source</strong>, <strong>Action</strong> and <strong>From</strong>/<strong>To</strong> dates, or click an actor's name to see only their entries.</li>
    <li>The log also folds in ticket-timeline events (status, priority, assignment, group, type, merge, escalation, creation). <strong>Source</strong> switches between all sources, audit log only and ticket history only; rows carry a grey <em>Audit</em> or blue <em>Timeline</em> badge, and ticket references link to the ticket.</li>
</ul>
<h6 class="fw-semibold mt-3 mb-2">Export &amp; pruning</h6>
<p class="text-muted mb-2"><strong>Export to Excel</strong> downloads every entry matching the current filters (not just the page of 50) as a CSV with columns When, Source, Actor, Action, Target Type, Target ID, Detail, IP Address. The export itself is logged.</p>
<p class="text-muted mb-2">Entries are kept forever unless pruned. <strong>Prune older entries</strong> deletes audit entries older than a date you choose and logs the prune with its cut-off and count.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Pruning removes only audit-log rows. Ticket-history (blue <em>Timeline</em>) rows belong to each ticket and stay in the list after a prune.
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
