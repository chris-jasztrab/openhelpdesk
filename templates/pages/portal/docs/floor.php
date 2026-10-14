<?php
$layout       = 'app';
$pageTitle    = 'Help: Floor Mode';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Floor Mode']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Floor Mode</h2>
<p class="text-muted mb-4">A simple, phone-friendly version of your help requests. Designed for checking on something while you're already standing in front of it.</p>

<div class="alert alert-light border d-flex gap-3 mb-4">
    <i class="bi bi-info-circle fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        Open it from the <i class="bi bi-grid-1x2"></i> <strong>Floor mode</strong> icon in the left sidebar (it appears on touch-screen devices; on a phone, it's in the <strong>&#9776;</strong> menu), or go to <code>/portal/floor</code>. It shows the requests you submitted &mdash; just with bigger touch targets and less clutter.
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>Your Requests</h5>
<p class="text-muted mb-0">The main page, <strong>My help requests</strong>, is a card grid of up to 50 of the requests you submitted &mdash; open ones first, most recently updated at the top, then finished ones so you can confirm something was handled. Each card shows the request number, how long ago you submitted it, the subject, status, type, and location; the coloured edge shows its priority. Tap a card to open it.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-circle text-success me-2"></i>Submit a New Request</h5>
<p class="text-muted mb-0">The big <strong>New help request</strong> card at the top opens the regular submission form (see <a href="/portal/help/submitting">Submitting a Request</a>). Use your camera to attach a photo of whatever's broken &mdash; that's usually the fastest way to explain the problem.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-ticket-detailed text-primary me-2"></i>The Request Detail View</h5>
<p class="text-muted mb-2">Tapping a card opens the request. Here's what you can do:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">Section</th><th>What it does</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Status pill</strong></td><td>Plain-English status: <em><?= e(label('portal.status.open', 'Submitted')) ?></em>, <em><?= e(label('portal.status.in_progress', "We're working on it")) ?></em>, <em><?= e(label('portal.status.waiting_on_customer', 'Waiting on you')) ?></em>, <em><?= e(label('portal.status.resolved', 'Done')) ?></em>, and so on.</td></tr>
        <tr><td><strong>Helping you</strong></td><td>Shows the staff member assigned to the request, once one's been picked.</td></tr>
        <tr><td><strong>What you reported</strong></td><td>The original description, with a <strong>Show all</strong> toggle if it's long.</td></tr>
        <tr><td><strong>Updates</strong></td><td>The latest replies and status changes. Internal staff-only notes are not shown here.</td></tr>
        <tr><td><strong>Reply</strong></td><td>Add more information, answer a question from staff, or share a follow-up photo. Tap <strong>Photo</strong> to use the camera, then <strong>Send</strong>. Not available once a request is <?= e(label('portal.status.closed', 'Closed')) ?>.</td></tr>
        <tr><td><strong>Close this request</strong></td><td>Closes the request once it's sorted out (you'll be asked to confirm). Only shown on requests you submitted, and only while they're still open. You can always submit a new one if the problem comes back.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-ul text-primary me-2"></i>Full Request Details</h5>
<p class="text-muted mb-0">If you need to edit the request, see attachments, or view the full history, tap <strong>Full request details</strong> at the bottom of the page. You'll get the regular detail view with a floating <strong>&times;</strong> in the top right that takes you straight back to the simple view &mdash; you don't have to navigate back through menus. <strong>My requests</strong> at the top of the detail view takes you back to your card grid.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>Tips</h5>
<ul class="text-muted mb-0">
    <li>Install the portal on your phone's home screen for a one-tap launcher &mdash; see <a href="/portal/help/getting-started#install">Install It Like an App</a>.</li>
    <li>Photos help a lot &mdash; even a quick snap of an error message or a broken thing makes the request faster to resolve.</li>
    <li>If your request is set to <em><?= e(label('portal.status.waiting_on_customer', 'Waiting on you')) ?></em>, staff are blocked until you reply. Replying lets them keep working on it.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
