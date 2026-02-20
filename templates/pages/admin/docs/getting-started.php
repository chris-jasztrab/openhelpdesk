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
<p class="text-muted mb-4">Follow these steps to configure LocalDesk after a fresh installation.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-1-circle-fill text-primary me-2"></i>Configure Email (SMTP)</h5>
<p class="text-muted mb-2">LocalDesk sends notifications when tickets are created, updated or merged. Without SMTP configured, no emails will be sent.</p>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/settings"><strong>Admin → Settings → Email / SMTP</strong></a>.</li>
    <li>Enter your SMTP host, port, encryption (TLS recommended), username and password.</li>
    <li>Set the <strong>From Address</strong> and <strong>From Name</strong> that recipients will see.</li>
    <li>Click <strong>Save Settings</strong> and use the <strong>Send Test Email</strong> button to verify.</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Common providers: Gmail uses <code>smtp.gmail.com:587 TLS</code>. Microsoft 365 uses <code>smtp.office365.com:587 TLS</code>.
    App-specific passwords are required when 2FA is enabled on the sending account.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-2-circle-fill text-primary me-2"></i>Add Locations (Optional)</h5>
<p class="text-muted mb-2">Locations let you categorise tickets and users by physical site or department (e.g. Main Branch, Downtown Office).</p>
<p class="text-muted mb-0">Go to <a href="/admin/locations"><strong>Admin → Settings → Locations</strong></a> to add your sites. Users can then be associated with a location, and tickets can be tagged with one.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-3-circle-fill text-primary me-2"></i>Create Agent Accounts</h5>
<p class="text-muted mb-2">Agents are the staff members who handle tickets. Admins can also manage tickets.</p>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/users/create"><strong>Admin → Users → Create User</strong></a>.</li>
    <li>Set the role to <strong>Agent</strong>.</li>
    <li>Optionally assign them a location.</li>
    <li>The agent receives a welcome email if SMTP is configured.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-4-circle-fill text-primary me-2"></i>Set Up SLA Policies</h5>
<p class="text-muted mb-2">SLA (Service Level Agreement) policies define how quickly your team must respond to and resolve tickets.</p>
<p class="text-muted mb-0">See the <a href="/admin/docs/sla"><strong>SLA Policies</strong></a> documentation for full details. At minimum, create one default policy and assign it to a ticket priority.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-5-circle-fill text-primary me-2"></i>Customise Branding</h5>
<p class="text-muted mb-2">Change the application name, logo and colour scheme to match your organisation.</p>
<p class="text-muted mb-0">Go to <a href="/admin/settings/branding"><strong>Admin → Settings → Branding</strong></a>. You can also customise the colours used for internal notes and system events in the ticket timeline.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-6-circle-fill text-primary me-2"></i>Share the Portal URL</h5>
<p class="text-muted mb-2">End users submit and track tickets via the portal. Direct them to:</p>
<div class="bg-light rounded px-3 py-2 font-monospace small mb-0"><strong><?= e(env('APP_URL', 'http://your-site.com')) ?>/portal</strong></div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
