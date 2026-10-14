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
        <tr><td><strong>Personal Information</strong></td><td>Your first and last name, as they appear on your requests.</td></tr>
        <tr><td><strong>Appearance</strong></td><td>Switch the whole portal between <em>Light Mode</em> and <em>Dark Mode</em>, whichever is easier on your eyes.</td></tr>
        <tr><td><strong>Ticket Timeline</strong></td><td>Choose whether a request's updates show <em>Newest first</em> or <em>Oldest first</em>.</td></tr>
        <tr><td><strong>Change Password</strong></td><td>Enter your current password, then the new one twice (at least 8 characters), and click <strong>Update Password</strong>.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Everything except the password <strong>saves automatically</strong> as you change it &mdash; there's no separate Save button to remember. Your email address is shown at the top of the page; if it needs changing, ask your administrator.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-paper text-primary me-2"></i>Email Notifications</h5>
<p class="text-muted mb-2">You're in charge of which emails land in your inbox. Each switch under <strong>Email Notifications</strong> turns one kind of email on or off:</p>
<ul class="text-muted mb-2">
    <li><strong>Ticket submitted</strong> &mdash; confirmation when you submit a request</li>
    <li><strong>Ticket assigned</strong> &mdash; when someone is handling your request</li>
    <li><strong>Agent replies</strong> &mdash; when the team replies, plus reminders and check-ins about your request</li>
    <li><strong>CC updates</strong> &mdash; when you're added as a CC, and replies on requests you're CC'd on</li>
    <li><strong>Ticket merged</strong> &mdash; when your request is combined with another one</li>
    <li><strong>Ticket resolved</strong> and <strong>Ticket closed</strong> &mdash; when the team marks your request done or closed</li>
    <li><strong>Satisfaction surveys</strong> &mdash; a short survey after a request is resolved</li>
</ul>
<p class="text-muted mb-0">Even if you turn most of these off, remember: <strong>any email we do send about a request can be replied to directly</strong> to add a comment. Your administrator may also switch some emails off for everyone.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-open text-primary me-2"></i>What Each Email Means</h5>
<p class="text-muted mb-2">Every email about a request includes its number in the subject, such as <em>#123</em>. Here's what the most common ones are telling you:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:34%;">Subject usually says&hellip;</th><th>What it means</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>[Ticket #123]</strong> and your subject</td><td>We got your request. Nothing else to do.</td></tr>
        <tr><td><strong>Assigned:</strong></td><td>A named person is now handling your request.</td></tr>
        <tr><td><strong>Update:</strong></td><td>The team replied. Reply to the email to answer.</td></tr>
        <tr><td><strong>Following up on your ticket</strong></td><td>We're waiting on you. Reply with the information we asked for.</td></tr>
        <tr><td><strong>Status update:</strong></td><td>No news yet, but you haven't been forgotten &mdash; your request is still in the queue.</td></tr>
        <tr><td><strong>You were added as a CC</strong></td><td>Someone copied you on a request. You'll hear about its replies too.</td></tr>
        <tr><td><strong>Your ticket has been merged</strong></td><td>Your request was combined with another one about the same problem. Follow the combined request from now on.</td></tr>
        <tr><td><strong>Resolved:</strong></td><td>We think it's sorted. Still need help? Reply to the email.</td></tr>
        <tr><td><strong>Closed:</strong></td><td>It's finished. If you need more help, submit a new request.</td></tr>
        <tr><td><strong>How did we do?</strong></td><td>An optional satisfaction survey. See <a href="/portal/help/managing">Replying, Editing &amp; Closing</a>.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Your helpdesk may word these a little differently. If the person handling your request is away and no one else can take it, you may also get one automatic note with their away message.</p>
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
