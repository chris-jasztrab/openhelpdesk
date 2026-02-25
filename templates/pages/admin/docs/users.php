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
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Profile &amp; Password</h5>
<p class="text-muted mb-2">Any logged-in user can update their own profile and password from the profile menu (top-right avatar). Agents and admins can also update their display name and email from their profile page.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Changing an email address on a self-registered account will require the new address to be verified before it takes effect.
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
