<?php
$layout       = 'app';
$pageTitle    = 'Help: Getting Started';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Getting Started']];
$locLabel     = label('location.singular', 'Location');
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Getting Started</h2>
<p class="text-muted mb-4">New here? This is the whole portal in five minutes. It's where you ask our team for help, keep track of what you've asked, and find answers on your own.</p>

<div class="alert alert-light border d-flex gap-3 mb-4">
    <i class="bi bi-signpost-2 fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        Prefer a guided walkthrough? Click <strong>your name in the top-right corner</strong> and choose <strong>Restart Tour</strong>. It points at each part of the screen as you go. You can restart it as many times as you like.
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-box-arrow-in-right text-primary me-2"></i>Signing In</h5>
<p class="text-muted mb-2">Enter your <strong>Email Address</strong> and <strong>Password</strong>, then click <strong>Sign In</strong>. If you see a <strong>Sign in with Microsoft 365</strong> button, you can use your work account instead.</p>
<ul class="text-muted mb-2">
    <li><strong>Forgot your password?</strong> Click <strong>Forgot password?</strong> under the password box and enter your email address. We'll email you a link to choose a new password (at least 8 characters). The link works once and expires after 60 minutes.</li>
    <li><strong>"Too many failed login attempts"?</strong> Wait a few minutes, then try again.</li>
    <li><strong>Asked to choose your <?= e(strtolower($locLabel)) ?>?</strong> If your account doesn't have one yet, you may be asked which <?= e(strtolower($locLabel)) ?> you work from. Pick it and click <strong>Continue</strong>, or choose <strong>Skip for now</strong>.</li>
</ul>
<p class="text-muted small mb-0">If you open a link to a page in the portal (for example, one a staff member sent you) while you're signed out, you'll land on that page as soon as you sign in.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-speedometer2 text-primary me-2"></i>The Dashboard</h5>
<p class="text-muted mb-0">When you sign in you land on your <strong>Dashboard</strong>. The <strong><?= e(label('portal.action.new', 'New Help Request')) ?></strong> button in the top-right starts a new request. Below it, <strong><?= e(label('portal.dashboard.recent_tickets', 'Your Active Requests')) ?></strong> lists your five newest requests that aren't finished yet &mdash; click one to open it, or click <strong>View All</strong> to see the rest. The <strong><?= e(label('portal.nav.dashboard', 'Dashboard')) ?></strong> link in the sidebar always brings you back here.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list text-primary me-2"></i>Finding Your Way Around</h5>
<p class="text-muted mb-2">The <strong>sidebar</strong> on the left gets you everywhere:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Menu item</th><th>What it's for</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><i class="bi bi-speedometer2 me-1"></i><strong><?= e(label('portal.nav.dashboard', 'Dashboard')) ?></strong></td><td>Your home page, with your active requests.</td></tr>
        <tr><td><i class="bi bi-ticket-detailed me-1"></i><strong><?= e(label('portal.nav.my_tickets', 'My Requests')) ?></strong></td><td>Every help request you've submitted, open and finished.</td></tr>
        <tr><td><i class="bi bi-grid-1x2 me-1"></i><strong>Floor mode</strong></td><td>A simple, phone-friendly view (shows on touch-screen devices).</td></tr>
        <tr><td><i class="bi bi-book me-1"></i><strong><?= e(label('portal.nav.knowledge_base', 'Knowledge Base')) ?></strong></td><td>A library of help articles that may already answer your question.</td></tr>
        <tr><td><i class="bi bi-question-circle me-1"></i><strong>Help</strong></td><td>These guides &mdash; how the portal itself works.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2"><strong>On a phone?</strong> The sidebar is tucked away. Tap the <strong>&#9776;</strong> menu button at the top of the screen to reach the same links.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-megaphone text-danger me-2"></i>Status Banners &mdash; Check These First</h5>
<p class="text-muted mb-2">When something is broken that affects lots of people &mdash; like the Wi-Fi being down at a branch &mdash; we post a coloured <strong>banner</strong> at the top of every page. A banner can be for everyone or just for your <?= e(strtolower($locLabel)) ?>.</p>
<p class="text-muted mb-0">If a banner already describes your problem, <strong>we already know about it</strong> and you don't need to submit a request. Click the <strong>&times;</strong> on a banner to hide it for the rest of your visit. If we update the banner, it comes back so you see the news.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-search text-primary me-2"></i>Search</h5>
<p class="text-muted mb-2">The search box in the top bar searches <strong>both</strong> your requests <em>and</em> Knowledge Base articles at once. Use the tabs in the results (<strong>Everything</strong>, <strong>Tickets</strong>, <strong>Knowledge Base Articles</strong>) to narrow it down. Know the request number? Type it, like <code>#123</code>, to jump straight to it.</p>
<p class="text-muted small mb-0"><strong>Shortcut:</strong> press the <code>/</code> key on any page to jump straight into the search box.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-circle text-primary me-2"></i>Your Account Menu</h5>
<p class="text-muted mb-0">Click <strong>your name in the top-right corner</strong> to reach <strong>My Profile</strong> (your name, password, dark mode, and which emails you receive), <strong>Restart Tour</strong>, and <strong>Sign Out</strong>. The <strong>Help</strong> link in the top bar is on every page if you get stuck.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="install">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-phone text-primary me-2"></i>Install It Like an App</h5>
<p class="text-muted mb-2">You can add the portal to your phone, tablet, or computer so it opens with one tap from its own icon.</p>
<ul class="text-muted mb-0">
    <li><strong>Chrome, Edge, or Android:</strong> an <strong>Install</strong> banner may appear at the bottom of the screen. Tap <strong>Install</strong>. Tap <strong>&times;</strong> if you'd rather not; it stays hidden for two weeks.</li>
    <li><strong>iPhone or iPad (Safari):</strong> tap <strong>Share</strong>, then <strong>Add to Home Screen</strong>.</li>
</ul>
</div>
</div>

<div class="alert alert-success border-0 d-flex gap-3 mb-0" style="background:#f0fdf4;">
    <i class="bi bi-arrow-right-circle fs-4 text-success flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Ready to ask for help?</strong> Head to <a href="/portal/help/submitting">Submitting a Request</a> next.
    </div>
</div>

</div><!-- col -->
</div><!-- row -->
