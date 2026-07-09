<?php
$layout       = 'app';
$pageTitle    = 'Help: Submitting a Request';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Submitting a Request']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Submitting a Request</h2>
<p class="text-muted mb-4">Submitting a request is how you reach our team. Start it from the <strong>New Request</strong> button on your Dashboard or the <em>My Requests</em> page. The form adapts as you fill it in, so don't be surprised if fields appear or change.</p>

<div class="alert alert-light border d-flex gap-3 mb-4">
    <i class="bi bi-lightbulb fs-4 text-warning flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Before you submit:</strong> check the coloured banner at the top of the page and try a quick <a href="/portal/help/knowledge-base">Knowledge Base</a> search. Your answer may already be waiting &mdash; and if a banner describes your problem, we already know.
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-file-earmark-text text-primary me-2"></i>Start From a Template (if offered)</h5>
<p class="text-muted mb-0">For common requests there may be a <strong>template</strong> to pick at the top of the form. Choosing one pre-fills the subject, description, and type for you &mdash; you just adjust the details and submit. If you don't see a template list, no templates have been set up, and that's fine.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-type text-primary me-2"></i>Subject &mdash; and Instant Answers</h5>
<p class="text-muted mb-2">Give a short, clear summary of the problem (for example, <em>"Wi-Fi won't connect on my laptop"</em>). As you type, matching <strong>Knowledge Base articles pop up right below the box</strong>. Your answer might already be written &mdash; it's worth a glance before you go further.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-card-text text-primary me-2"></i>Description &mdash; the Most Important Part</h5>
<p class="text-muted mb-2">Tell us <strong>what happened, when it started, and what you've already tried.</strong> The more we know, the faster we can help without a round of follow-up questions.</p>
<p class="text-muted mb-0">This is a full editor: you can format text, add lists and links, and <strong>paste a screenshot straight in</strong>. A picture of an error message is often the fastest way to explain a problem.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Request Type &mdash; the Form Changes With It</h5>
<p class="text-muted mb-2"><strong>Each type of request has its own form.</strong> Picking <em>Hardware</em> vs. <em>Software</em> vs. <em>Account</em> (for example) rearranges the fields below and, behind the scenes, sends your request to the right team.</p>
<div class="alert alert-info border-0 d-flex gap-3 mb-0" style="background:#eff6ff;">
    <i class="bi bi-compass fs-5 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Not sure which type to pick?</strong> Some helpdesks offer a <strong>&ldquo;No Wrong Door&rdquo;</strong> type. Instead of you guessing who handles your issue, our system reads what you wrote and automatically routes it to the team best suited to help &mdash; and if it isn't sure, a real person steps in, so your request never gets lost. If you see a type described this way, it's a safe choice when you're unsure.
    </div>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>The Optional Extras</h5>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:28%;">Field</th><th>What to do</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Priority</strong></td><td>How urgent is it? Pick one if you know &mdash; or leave it on <em>"Let our team decide"</em> and we'll set it for you.</td></tr>
        <tr><td><strong>Location</strong></td><td>Usually pre-filled from your profile. Change it if you're reporting something at a different branch.</td></tr>
        <tr><td><strong>Tags</strong></td><td>Optional keywords to help categorize the request &mdash; type a word and press Enter.</td></tr>
        <tr><td><strong>Attachments</strong></td><td>Attach photos, screenshots, PDFs, or documents &mdash; anything that shows the problem.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Some of these only appear for certain request types, so you may not see all of them.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send-check text-success me-2"></i>Submit &mdash; With a Duplicate Check</h5>
<p class="text-muted mb-2">When you press <strong>Submit</strong>, we first quietly compare your request with open requests from your branch. If it looks like the same problem someone already reported, we show you a <strong>duplicate warning</strong> &mdash; with how similar it is and a peek at the existing request &mdash; <em>before</em> creating anything.</p>
<p class="text-muted mb-0">You can then either <strong>join the existing request</strong> (so you're following the one the team is already working on) or choose <strong>Create anyway</strong> if yours really is different.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-floppy text-primary me-2"></i>Drafts &mdash; Nothing Gets Lost</h5>
<p class="text-muted mb-0">Half-written requests are <strong>saved automatically</strong>. If you close the tab or get interrupted, your text is waiting when you come back. The same goes for comments you start writing on an existing request.</p>
</div>
</div>

<div class="alert alert-success border-0 d-flex gap-3 mb-0" style="background:#f0fdf4;">
    <i class="bi bi-arrow-right-circle fs-4 text-success flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Submitted something?</strong> Learn how to follow it in <a href="/portal/help/tracking">Tracking Your Requests</a>.
    </div>
</div>

</div><!-- col -->
</div><!-- row -->
