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
<h5 class="fw-semibold mb-3"><i class="bi bi-book text-primary me-2"></i>Knowledge Base Access</h5>
<p class="text-muted mb-2">Published knowledge base articles are accessible to portal users from the <strong>Knowledge Base</strong> link in the portal navigation. Users can browse by category and search for articles before submitting a ticket.</p>
<p class="text-muted mb-0">See the <a href="/admin/docs/kb"><strong>Knowledge Base</strong></a> documentation for how to create and publish articles.</p>
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
