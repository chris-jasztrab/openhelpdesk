<?php
$layout       = 'app';
$pageTitle    = 'Docs: Branding & Labels';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Branding & Labels']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Branding &amp; Labels</h2>
<p class="text-muted mb-4">Make OpenHelpDesk look like your organisation and use your own words. All three pages live under <strong>Settings → Customization</strong> and need the <strong>Manage general settings</strong> permission. Every save is recorded in the audit log.</p>

<div class="card border-0 shadow-sm mb-4" id="branding">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-palette text-primary me-2"></i>Branding Settings</h5>
<p class="text-muted mb-2">Open <a href="/admin/settings/branding"><strong>Settings → Customization → Branding</strong></a>. A <strong>Live Preview</strong> beside the form shows the navbar, buttons and sign-in background as you change them. Nothing applies until you click <strong>Save Branding</strong>.</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:190px;">Setting</th><th>Description</th></tr></thead>
    <tbody class="text-muted">
        <tr><td class="fw-semibold">Logo</td><td>JPG, PNG, GIF or WEBP, up to 2 MB. It appears in the navbar, so aim for 32&ndash;40px tall. SVG files are rejected for security. <strong>Remove logo</strong> goes back to the fallback icon.</td></tr>
        <tr><td class="fw-semibold">Fallback Icon</td><td>The <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icon</a> shown beside the name when there is no logo, for example <code>bi-building</code>.</td></tr>
        <tr><td class="fw-semibold">App Name</td><td>Shown in the navbar, the browser tab, the sign-in page and outgoing email. When the app is installed on a phone, the icon is labelled with it.</td></tr>
        <tr><td class="fw-semibold">Primary Color</td><td>Buttons, links, active menu items and highlights.</td></tr>
        <tr><td class="fw-semibold">Primary Hover Color</td><td>Button hover and focus states.</td></tr>
        <tr><td class="fw-semibold">Navbar Gradient Start / End</td><td>The two ends of the top-bar gradient. The start colour is also the theme colour of the installed app.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mt-3 mb-0"><strong>Reset to Defaults</strong> restores the default colours and name in the form, ready for you to save. Your logo also becomes the installed-app icon: see <a href="/admin/docs/portal#install-app">Portal → Install as an App</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-card-list text-primary me-2"></i>Timeline Colours</h5>
<p class="text-muted mb-2">Also on the Branding page. The ticket timeline marks two kinds of staff-only entries with their own colours:</p>
<ul class="text-muted mb-3">
    <li><strong>Internal Note Background / Accent</strong>: notes agents write for each other. The accent colours the left border and lock icon. Default <code>#fefce8</code> / <code>#ca8a04</code> (warm yellow).</li>
    <li><strong>System Entry Background / Accent</strong>: automated entries such as SLA changes and auto-assignments. Default <code>#eff6ff</code> / <code>#3b82f6</code> (soft blue).</li>
</ul>
<p class="text-muted mb-0">Replies the requester can see are not tinted. <strong>Reset Timeline to Defaults</strong> restores the four timeline colours. Light or dark mode is each person's own choice on My Profile, not a branding setting.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="labels">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-translate text-primary me-2"></i>Labels: Renaming On-Screen Terms</h5>
<p class="text-muted mb-2"><a href="/admin/settings/labels"><strong>Settings → Customization → Labels</strong></a> changes the wording people see, for example <em>Location</em> to <em>Branch</em>, or the plain-English status names on the portal. The <strong>Current Label Values</strong> list is grouped by where each label appears:</p>
<ul class="text-muted mb-3">
    <li><strong>Organisation</strong>: what you call a location, singular and plural. Used throughout ticket lists, forms, reports, emails and the portal.</li>
    <li><strong>Staff navigation</strong> and <strong>Portal navigation</strong>: names on the left rails and in the Settings menu.</li>
    <li><strong>Portal status wording</strong>: what requesters see for each built-in status, such as <em>Submitted</em>, <em>We're working on it</em> or <em>Waiting on you</em>. Staff see the status names set at <a href="/admin/settings/ticket-statuses">Settings → Organization → Ticket Statuses</a>, so the two can differ. Custom statuses you add show their own name on the portal.</li>
    <li><strong>Portal requests &amp; actions</strong> and <strong>Portal request page</strong>: buttons, headings, the priority question, the "What happens next?" panel and the green answer banner.</li>
</ul>
<p class="text-muted mb-2"><strong>Editing:</strong></p>
<ul class="text-muted mb-3">
    <li>Click any value to edit it in place. Press Enter or click away to save, or press Escape to cancel. Edited labels get a <strong>custom</strong> badge, and the header counts how many you've customised.</li>
    <li>To restore the built-in wording, clear the box or type the default back in. Values can be up to 255 characters.</li>
    <li>The search box filters by key or value. Type several words to narrow it down, and press Escape to clear.</li>
    <li>For bulk changes, <strong>Download labels.json</strong>, edit the values (never the keys), then <strong>Upload &amp; Apply</strong>. Every key is checked before anything is applied. <strong>Reset to Defaults</strong> removes every customisation.</li>
</ul>
<p class="text-muted mb-0">Keys that the app doesn't read yet sit in a collapsed <em>Not wired up yet</em> section at the bottom. Renaming those has no visible effect.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="tags">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hash text-primary me-2"></i>Tags</h5>
<p class="text-muted mb-0"><a href="/admin/settings/tags"><strong>Settings → Customization → Tags</strong></a> has one switch, <strong>Enable tags on tickets</strong>. When it's on, ticket forms show a tag input so agents and requesters can add hashtag labels. When it's off, the tag input is hidden from every ticket form and view.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
