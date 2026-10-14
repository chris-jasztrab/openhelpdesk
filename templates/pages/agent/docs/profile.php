<?php
$layout       = 'app';
$pageTitle    = 'Help: Profile & Notifications';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Profile & Notifications']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Profile &amp; Notifications</h2>
<p class="text-muted mb-4">Your personal settings, how the helpdesk keeps you informed, and working from your inbox.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-gear text-primary me-2"></i>Your Profile</h5>
<p class="text-muted mb-2">Open your account menu (your name, top-right) and choose <a href="/profile"><strong>My Profile</strong></a>. Changes save as soon as you make them &mdash; a green <em>Saved</em> message confirms it. Only the password needs a button.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Card</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Personal Information</strong></td><td>Your first and last name.</td></tr>
        <tr><td><strong>Appearance</strong></td><td>Light or Dark mode, applied straight away.</td></tr>
        <tr><td><strong>Ticket Timeline</strong></td><td>Show ticket timelines <em>Newest first</em> or <em>Oldest first</em>. Clicking the Timeline heading on a ticket changes the same setting.</td></tr>
        <tr><td><strong>Email Notifications</strong></td><td>Which emails you receive (see below).</td></tr>
        <tr><td><strong>Change Password</strong></td><td>Enter your current password and a new one (at least 8 characters) twice, then click <strong>Update Password</strong>.</td></tr>
        <tr><td><strong>Two-Factor Authentication</strong></td><td>Extra sign-in protection (see below).</td></tr>
        <tr><td><strong>Canned Responses</strong></td><td><strong>Manage Canned Responses</strong> &mdash; see <a href="/agent/help/canned-responses">Canned Responses</a>.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope text-primary me-2"></i>Choosing Your Emails</h5>
<p class="text-muted mb-2">The <strong>Email Notifications</strong> card has a switch for each kind of email, in three groups:</p>
<ul class="text-muted mb-2">
    <li><strong>Agent Notifications</strong> &mdash; new ticket in my group, ticket assigned to my group, ticket assigned to me, requester replies and internal notes on tickets assigned to you.</li>
    <li><strong>Other Notifications</strong> &mdash; replies on tickets you're CC'd on, escalation alerts, and when a ticket you submitted is assigned.</li>
    <li><strong>Tickets You Submit</strong> &mdash; the same emails a requester gets for tickets you raise yourself: confirmation, agent replies, merged, resolved, closed and satisfaction surveys.</li>
</ul>
<p class="text-muted mb-0">Turning off an email doesn't hide the matching alert under the bell. Some emails can also be switched off for everyone by your administrator.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bell text-primary me-2"></i>In-App Notifications</h5>
<p class="text-muted mb-2">The <strong><i class="bi bi-bell"></i> bell</strong> in the top bar shows how many unread alerts you have. Click it to open the <a href="/notifications">Notifications</a> page, which updates on its own. Click an alert to open its ticket; use <strong>Mark read</strong> on one alert or <strong>Mark All Read</strong> for all of them.</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Alert</th><th>When you get it</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><i class="bi bi-at text-primary me-1"></i><strong>Mention</strong></td><td>Someone @mentioned you in a reply or note.</td></tr>
        <tr><td><i class="bi bi-person-check text-success me-1"></i><strong>Assignment</strong></td><td>A ticket was assigned to you.</td></tr>
        <tr><td><i class="bi bi-arrow-repeat text-info me-1"></i><strong>Ticket update</strong></td><td>A ticket you're assigned to, watching or CC'd on changed status or got a reply.</td></tr>
        <tr><td><i class="bi bi-plus-circle text-primary me-1"></i><strong>New ticket</strong></td><td>A new ticket arrived in a group that alerts you.</td></tr>
        <tr><td><i class="bi bi-chat-left-text text-info me-1"></i><strong>Customer replied</strong></td><td>The requester replied on a ticket assigned to you.</td></tr>
        <tr><td><i class="bi bi-sticky text-secondary me-1"></i><strong>Note added</strong></td><td>A colleague added an internal note to a ticket assigned to you.</td></tr>
        <tr><td><i class="bi bi-clock-history text-warning me-1"></i><strong>SLA warning</strong> / <i class="bi bi-exclamation-triangle text-danger me-1"></i><strong>SLA breached</strong></td><td>A ticket assigned to you is close to, or past, its deadline. See <a href="/agent/help/sla">SLAs &amp; Response Times</a>.</td></tr>
        <tr><td><i class="bi bi-alarm text-warning me-1"></i><strong>Follow-up reminder</strong></td><td>A follow-up you set with <em>Close and schedule follow-up</em> is due.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-reply-all text-primary me-2"></i>Working From Your Email</h5>
<p class="text-muted mb-2"><strong>Reply to any ticket email</strong> and your message is added to the ticket as a public reply &mdash; no need to sign in. Replies to a ticket that's already <em>Closed</em> are ignored unless you include <code>#open</code>.</p>
<p class="text-muted mb-2"><strong>Email commands.</strong> Put one or more of these on their own line at the end of your reply (before your signature is fine) to update the ticket at the same time. They aren't case-sensitive and the command line is removed from the posted reply.</p>
<div class="table-responsive mb-2">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:45%;">Command</th><th>Does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>#close</code> &nbsp; <code>#resolve</code> &nbsp; <code>#open</code> &nbsp; <code>#pending</code> &nbsp; <code>#inprogress</code></td><td>Sets the status.</td></tr>
        <tr><td><code>#waitingoncustomer</code> &nbsp; <code>#waitingonthirdparty</code></td><td>Sets a waiting status.</td></tr>
        <tr><td><code>#low</code> &nbsp; <code>#medium</code> &nbsp; <code>#high</code> &nbsp; <code>#critical</code></td><td>Sets the priority, if a priority with that name exists.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-0">Example: <em>Thanks, all fixed! #resolve</em>. Commands are honoured from Agent and Admin accounts.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Two-Factor Authentication</h5>
<ol class="text-muted mb-2">
    <li>On your profile, click <strong>Set Up 2FA</strong>.</li>
    <li>Scan the QR code with an authenticator app (such as Google Authenticator or Authy), or type in the key shown.</li>
    <li>Enter the 6-digit code from the app and click <strong>Enable 2FA</strong>.</li>
</ol>
<p class="text-muted mb-0">From then on you'll be asked for a code from the app each time you sign in. After 5 wrong codes you're sent back to the password step. To turn it off, enter a current code on your profile and click <strong>Disable 2FA</strong>. Lost your phone? Ask an administrator to reset your 2FA.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-airplane text-primary me-2"></i>When You're Out of the Office</h5>
<p class="text-muted mb-0">There's no away switch in the helpdesk. If your organisation has turned on out-of-office coverage, it reads the <strong>Outlook automatic reply</strong> on your mailbox: while yours is on, your open tickets can be handed to an available colleague in your group, and if nobody is available the requester is sent an out-of-office reply once. Turn your Outlook automatic reply off when you're back.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
