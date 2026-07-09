<?php
$layout       = 'app';
$pageTitle    = 'Help: Replying, Editing & Closing';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Replying, Editing & Closing']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Replying, Editing &amp; Closing</h2>
<p class="text-muted mb-4">A request isn't a one-way message &mdash; it's a conversation. Here's how to reply to the team, keep colleagues in the loop, fix a mistake, and wrap things up when you're done.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-dots text-primary me-2"></i>Add a Comment</h5>
<p class="text-muted mb-2">Open a request and scroll to the reply box at the bottom. Use it to answer a question from the team, add information you forgot, or attach another file or photo. Your comment is added to the timeline and the team is notified.</p>
<p class="text-muted small mb-0">If a request is marked <em>Waiting on you</em>, the team is blocked until you reply &mdash; a comment gets things moving again.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-arrow-up text-success me-2"></i>Reply by Email &mdash; No Login Needed</h5>
<p class="text-muted mb-0">Even easier: whenever you get an email about one of your requests, <strong>just reply to it</strong>. Your reply is automatically added to the request as a comment &mdash; no need to sign in or find the page. Attachments in your email come along too.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>CC &mdash; Keep Colleagues in the Loop</h5>
<p class="text-muted mb-0">People CC'd on a request can see it and get email updates as it progresses. It's a good way to keep a manager or a colleague informed without them having to ask you for updates.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Your Request</h5>
<p class="text-muted mb-0">Forgot a detail or made a typo? The <strong>Edit</strong> button lets you update the subject or description of your own requests while they're still open. It's for fixing and clarifying &mdash; for a brand-new, unrelated problem, submit a fresh request instead.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-circle text-success me-2"></i>Close It Yourself</h5>
<p class="text-muted mb-0">Problem sorted itself out, or the answer worked? You can close any of <strong>your own</strong> requests with the <strong>Close</strong> button &mdash; no need to wait for the team. If the problem comes back, just submit a new request. After closing you may get a short <strong>satisfaction survey</strong> by email; it's optional and helps us improve.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Escalating &mdash; and What an SLA Is</h5>
<p class="text-muted mb-2">Behind the scenes, every request has <strong>response-time targets</strong> &mdash; sometimes called SLAs (service-level agreements). The clock only runs during business hours and pauses while a request is <em>Waiting on you</em>.</p>
<p class="text-muted mb-0">If your request type has an escalation contact, an <strong>Escalate</strong> button may appear so you can flag it for extra attention &mdash; often only <em>after</em> a response-time target has been missed. Escalating doesn't restart anything; it just raises a hand to make sure the request gets a closer look.</p>
</div>
</div>

<div class="alert alert-light border d-flex gap-3 mb-0">
    <i class="bi bi-floppy fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Started a comment but got pulled away?</strong> It's saved as a draft automatically. You'll see a <strong>&#9999; Draft</strong> badge on the request in your list, and your text is waiting when you come back.
    </div>
</div>

</div><!-- col -->
</div><!-- row -->
