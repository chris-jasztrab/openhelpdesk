<?php
$layout       = 'app';
$pageTitle    = 'Help: Your Profile & Emails';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Your Profile & Emails']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Your Profile &amp; Emails</h2>
<p class="text-muted mb-4">Your profile is where you manage your account and decide exactly which emails you get. A little setup here makes the whole portal feel like yours.</p>

<div class="alert alert-light border d-flex gap-3 mb-4">
    <i class="bi bi-person-circle fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        Get there by clicking <strong>your name in the top-right corner</strong> and choosing <strong>My Profile</strong>. That same menu also has <strong>Restart Tour</strong> and <strong>Sign Out</strong>.
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Your Settings</h5>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Setting</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Name</strong></td><td>How your name appears on your requests.</td></tr>
        <tr><td><strong>Password</strong></td><td>Change your password whenever you like.</td></tr>
        <tr><td><strong>Light / dark mode</strong></td><td>Switch the whole portal between a light and a dark colour scheme, whichever is easier on your eyes.</td></tr>
        <tr><td><strong>Timeline order</strong></td><td>Choose whether a request's updates show newest-first or oldest-first.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Everything here <strong>saves automatically</strong> as you change it &mdash; there's no separate Save button to remember.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-paper text-primary me-2"></i>Email Notifications</h5>
<p class="text-muted mb-2">You're in charge of which emails land in your inbox. Turn off anything you don't need. You can choose to be emailed when:</p>
<ul class="text-muted mb-2">
    <li>you submit a request (a confirmation)</li>
    <li>your request is assigned to someone</li>
    <li>the team replies</li>
    <li>there's an update on a request you're CC'd on</li>
    <li>a request is resolved or closed</li>
    <li>a satisfaction survey is available</li>
</ul>
<p class="text-muted mb-0">Even if you turn most of these off, remember: <strong>any email we do send can be replied to directly</strong> to add a comment to the request.</p>
</div>
</div>

<div class="alert alert-info border-0 d-flex gap-3 mb-0" style="background:#eff6ff;">
    <i class="bi bi-check-circle fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>That's the whole portal.</strong> Submit a request, track it, reply here or by email, close it when it's done, and tune your emails to taste. Anything unclear? The <a href="/portal/help">Help overview</a> has a guide for each part.
    </div>
</div>

</div><!-- col -->
</div><!-- row -->
