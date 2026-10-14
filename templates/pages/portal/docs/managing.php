<?php
$layout       = 'app';
$pageTitle    = 'Help: Replying, Editing & Closing';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Replying, Editing & Closing']];
$closeLabel   = label('portal.action.close', 'Close this request');
$doneLabel    = label('portal.status.resolved', 'Done');
$closedLabel  = label('portal.status.closed', 'Closed');
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Replying, Editing &amp; Closing</h2>
<p class="text-muted mb-4">A request isn't a one-way message &mdash; it's a conversation. Here's how to reply to the team, keep colleagues in the loop, fix a mistake, wrap things up, and pick things back up if the problem returns.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-dots text-primary me-2"></i>Add a Comment</h5>
<p class="text-muted mb-2">Open a request and find the <strong>Add Comment</strong> box under the timeline. Use it to answer a question from the team, add information you forgot, or attach another file or photo, then click <strong>Post Comment</strong>. Your comment is added to the timeline and the team is notified.</p>
<p class="text-muted small mb-0">If a request is marked <em><?= e(label('portal.status.waiting_on_customer', 'Waiting on you')) ?></em>, the team is blocked until you reply &mdash; a comment gets things moving again. If your helpdesk has <em>undo</em> turned on, a short countdown with an <strong>Undo</strong> button appears before your comment is posted.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-arrow-up text-success me-2"></i>Reply by Email &mdash; No Login Needed</h5>
<p class="text-muted mb-2">Even easier: whenever you get an email about one of your requests, <strong>just reply to it</strong>. Your reply is added to the request as a comment &mdash; no need to sign in or find the page.</p>
<ul class="text-muted small mb-0">
    <li>Reply from the email address on your account, or your reply can't be matched to you.</li>
    <li>Files attached to an email reply aren't added to the request. To share a file, attach it on the request page instead.</li>
    <li>Replies to a request that's <strong><?= e($closedLabel) ?></strong> aren't added, unless the team set it to reopen when you reply (see <a href="#reopening">Reopening a Request</a>).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>CC &mdash; Keep Colleagues in the Loop</h5>
<p class="text-muted mb-0">If the request form has a CC field, you can copy colleagues in when you submit (see <a href="/portal/help/submitting">Submitting a Request</a>). People on CC get an email when they're added and whenever someone replies, and they can reply to those emails to add a comment. Want someone added later? Ask the team in a comment.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Your Request</h5>
<p class="text-muted mb-0">Forgot a detail or made a typo? Click <strong>Edit</strong> at the top of your request to change its subject or description, then click <strong>Save Changes</strong>. You can edit your own requests until they're <?= e($closedLabel) ?>. It's for fixing and clarifying &mdash; for a brand-new, unrelated problem, submit a fresh request instead.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-circle text-success me-2"></i>Close It Yourself</h5>
<p class="text-muted mb-0">Problem sorted itself out, or the answer worked? Click <strong><?= e($closeLabel) ?></strong> at the top of any of <strong>your own</strong> requests and confirm &mdash; no need to wait for the team. The team is told you closed it. If the problem comes back, just submit a new request.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-check text-primary me-2"></i>When the Team Closes Your Request</h5>
<p class="text-muted mb-2">When the team marks your request <strong><?= e($doneLabel) ?></strong> or <strong><?= e($closedLabel) ?></strong>, you'll usually get an email saying so. Sometimes it includes a short message explaining why &mdash; for example, that it's the same problem as another request, or that we closed it because we didn't hear back. That message also appears on the request's timeline.</p>
<p class="text-muted mb-2">You may also get a <strong>satisfaction survey</strong> &mdash; one per request, and always optional:</p>
<ul class="text-muted mb-0">
    <li><strong>Yes, it was resolved!</strong> (or <strong>Rate your experience</strong>) opens a page where you pick a face from <em>Terrible</em> to <em>Excellent</em>, add a comment if you like, and click <strong>Submit My Rating</strong>.</li>
    <li><strong>No, please reopen it</strong> asks you to confirm with <strong>Yes, reopen it</strong>, then sends your request back to the team.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="reopening">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-counterclockwise text-primary me-2"></i>Reopening a Request</h5>
<p class="text-muted mb-2">Problem back, or not really fixed? Here's how to get it moving again:</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:32%;">If the request is&hellip;</th><th>What to do</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong><?= e($doneLabel) ?></strong></td><td>Add a comment or reply to the email. The team sees your reply.</td></tr>
        <tr><td><strong>Set to reopen on reply</strong></td><td>Sometimes the team closes a request so that it <strong>reopens automatically if you reply</strong> within a set time. Post a comment or reply to one of its emails before then, and it goes back to <em><?= e(label('portal.status.open', 'Submitted')) ?></em>. You'll see <em>Your ticket has been reopened.</em></td></tr>
        <tr><td><strong>Surveyed</strong></td><td>Click <strong>No, please reopen it</strong> in the survey email (this only works if you haven't rated it yet).</td></tr>
        <tr><td><strong><?= e($closedLabel) ?></strong></td><td>Submit a new request and mention the old request number so the team can look back at it.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Escalating &mdash; and What an SLA Is</h5>
<p class="text-muted mb-2">Behind the scenes, many requests have <strong>response-time targets</strong> &mdash; sometimes called SLAs (service-level agreements). The clock only runs during business hours and pauses while we're waiting on you or on someone else.</p>
<p class="text-muted mb-2">If your request type has escalation contacts, an <strong>Escalate</strong> button may appear on your own requests &mdash; sometimes only <em>after</em> a response-time target has been missed. Clicking it opens <strong>Escalate this request</strong>, which shows exactly who it will go to. Add an optional reason, then click <strong>Escalate</strong>. Your request gets an <strong>Escalated &mdash; Level</strong> badge.</p>
<p class="text-muted small mb-0">The button is greyed out once a request is <?= e($doneLabel) ?> (and gone once it's <?= e($closedLabel) ?>), or when it's already with the last person in the chain. Escalating doesn't restart anything; it just raises a hand to make sure the request gets a closer look.</p>
</div>
</div>

<div class="alert alert-light border d-flex gap-3 mb-0">
    <i class="bi bi-floppy fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Started a comment but got pulled away?</strong> It's saved as a draft automatically. You'll see a <strong>&#9999; Draft</strong> badge on the request in your list, and <em>Restored your unsent comment</em> when you come back. Click <strong>Discard</strong> to throw it away.
    </div>
</div>

</div><!-- col -->
</div><!-- row -->
