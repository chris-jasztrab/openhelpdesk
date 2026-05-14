<?php
$layout       = 'app';
$pageTitle    = 'Docs: Branding';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Branding']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Branding</h2>
<p class="text-muted mb-4">Customise the look and feel of OpenHelpDesk to match your organisation's identity.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-palette text-primary me-2"></i>Branding Settings</h5>
<p class="text-muted mb-2">All branding options are found at <a href="/admin/settings/branding"><strong>Admin → Settings → Branding</strong></a>.</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Setting</th><th>Description</th></tr></thead>
    <tbody class="text-muted">
        <tr><td class="fw-semibold">Application Name</td><td>The name shown in the browser title bar, emails, and throughout the interface.</td></tr>
        <tr><td class="fw-semibold">Logo</td><td>Upload a PNG, JPG, or SVG logo. Displayed in the top-left navigation bar. Recommended height: 40px.</td></tr>
        <tr><td class="fw-semibold">Primary Colour</td><td>The main accent colour used for buttons, links, active sidebar items, and highlights across the interface.</td></tr>
        <tr><td class="fw-semibold">Portal Welcome Title</td><td>Heading text shown on the portal homepage above the ticket submission form.</td></tr>
        <tr><td class="fw-semibold">Portal Welcome Message</td><td>Supporting paragraph shown on the portal homepage.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-card-list text-primary me-2"></i>Timeline Colours</h5>
<p class="text-muted mb-2">The ticket timeline distinguishes three types of entries with different background colours:</p>
<ul class="text-muted mb-3">
    <li><strong>Internal Notes</strong> — agent-to-agent private notes. Customise the background and left-border accent colour.</li>
    <li><strong>System Events</strong> — automated events like status changes, SLA updates, and assignments. Customise background and left-border accent colour.</li>
    <li><strong>Replies &amp; Comments</strong> — customer-visible entries. Always white — not customisable.</li>
</ul>
<p class="text-muted mb-2">Default colours:</p>
<ul class="text-muted mb-0">
    <li>Notes: background <code>#fefce8</code>, accent <code>#ca8a04</code> (warm yellow)</li>
    <li>System: background <code>#eff6ff</code>, accent <code>#3b82f6</code> (soft blue)</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-image text-primary me-2"></i>Logo Tips</h5>
<ul class="text-muted mb-0">
    <li>Use a transparent-background PNG or SVG for best results on both light and dark nav bars.</li>
    <li>Recommended aspect ratio: landscape (wider than tall). Square logos also work well.</li>
    <li>Keep the file size under 1 MB for fast page loads.</li>
    <li>If no logo is uploaded, the Application Name text is shown instead.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-droplet text-primary me-2"></i>Primary Colour</h5>
<p class="text-muted mb-2">The primary colour is applied as a CSS custom property <code>--ld-primary</code> throughout the interface. It affects:</p>
<ul class="text-muted mb-0">
    <li>Navigation sidebar active item highlight</li>
    <li>Primary action buttons (Create, Save, etc.)</li>
    <li>Links and focus rings</li>
    <li>Stat card icons on the dashboard</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
