<?php
$layout       = 'app';
$pageTitle    = 'Help: Submitting a Request';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Submitting a Request']];
$newLabel     = label('portal.action.new', 'New Help Request');
$submitLabel  = label('portal.action.submit', 'Submit Request');
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Submitting a Request</h2>
<p class="text-muted mb-4">Submitting a request is how you reach our team. Start it from the <strong><?= e($newLabel) ?></strong> button on your Dashboard or the <em><?= e(label('portal.nav.my_tickets', 'My Requests')) ?></em> page. The form adapts as you fill it in, so don't be surprised if fields appear or change. Fields marked with a red <span class="text-danger">*</span> are required.</p>

<div class="alert alert-light border d-flex gap-3 mb-4">
    <i class="bi bi-lightbulb fs-4 text-warning flex-shrink-0"></i>
    <div class="small mb-0">
        <strong>Before you submit:</strong> check the coloured banner at the top of the page and try a quick <a href="/portal/help/knowledge-base">Knowledge Base</a> search. Your answer may already be waiting &mdash; and if a banner describes your problem, we already know.
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-file-earmark-text text-primary me-2"></i>Start From a Template (if offered)</h5>
<p class="text-muted mb-0">For common requests there may be a <strong>Template&hellip;</strong> menu at the top-right of the form, next to <strong>Back</strong>. Choosing one fills in the subject, description, request type, and urgency for you &mdash; you just adjust the details and submit. If you don't see the menu, no templates have been set up, and that's fine.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-type text-primary me-2"></i>Subject &mdash; and Instant Answers</h5>
<p class="text-muted mb-0">Give a short, clear summary of the problem (for example, <em>"Wi-Fi won't connect on my laptop"</em>). As you type, a <strong>Related KB articles that may help</strong> box can pop up right below. Your answer might already be written &mdash; it's worth a glance before you go further. Click the <strong>&times;</strong> to hide the box.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-card-text text-primary me-2"></i>Description &mdash; the Most Important Part</h5>
<p class="text-muted mb-2">Tell us <strong>what happened, when it started, and what you've already tried.</strong> The more we know, the faster we can help without a round of follow-up questions.</p>
<p class="text-muted mb-0">This is a full editor: you can format text, add lists and links, and <strong>paste a screenshot straight in</strong>. A picture of an error message is often the fastest way to explain a problem, and pasted pictures are also saved with the request's attachments.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>Request Type &mdash; the Form Changes With It</h5>
<p class="text-muted mb-2"><strong>Each type of request has its own form.</strong> Picking <em>Hardware</em> vs. <em>Software</em> vs. <em>Account</em> (for example) shows that type's own questions and instructions, and sends your request to the right team. Some questions only appear once you've answered an earlier one. If you followed a link from our team, the type may already be picked for you.</p>
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
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>The Rest of the Form</h5>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:28%;">Field</th><th>What to do</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong><?= e(label('location.singular', 'Location')) ?></strong></td><td>Usually pre-filled from your profile. Change it if you're reporting something at a different branch. If your profile doesn't have one yet, please pick one.</td></tr>
        <tr><td><strong><?= e(label('portal.field.priority_label', 'How urgent is this?')) ?></strong></td><td>Pick a level if you know &mdash; or leave it on <em>"Let our team decide"</em> and we'll set it for you. The choices can depend on the request type.</td></tr>
        <tr><td><strong><?= e(getSetting('sys_field_label_sla_notice', 'Service level targets')) ?></strong></td><td>Once you've picked a type and an urgency, a blue note may show how soon to expect a first response and a fix &mdash; for example, <em>"First response within 1 hour and resolution within 4 hours (business hours)"</em>.</td></tr>
        <tr><td><strong><?= e(getSetting('sys_field_label_tags', 'Tags')) ?></strong></td><td>Optional keywords. Type <strong>#</strong> and a word, then press Enter.</td></tr>
        <tr><td><strong><?= e(getSetting('sys_field_label_attachments', 'Attachments')) ?></strong></td><td>Attach photos, screenshots, PDFs, or documents. The size limit and allowed file types are shown under the box.</td></tr>
        <tr><td><strong>Extra questions</strong></td><td>Dropdowns, dates, checkboxes, and so on that this request type needs.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0 mt-2">Some of these only appear for certain request types, so you may not see all of them.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>Adding People: CC and Name Fields</h5>
<p class="text-muted mb-2"><strong>CC field</strong> (if the form has one): type at least two letters of a colleague's name or email in the <em>Search by name or email&hellip;</em> box and pick them from the list. <strong>Enter</strong>, <strong>Tab</strong>, a comma, or a semicolon adds the highlighted match; click the <strong>&times;</strong> on a name to remove it. People you CC get an email now and whenever someone replies.</p>
<p class="text-muted mb-0"><strong>Name fields:</strong> some forms ask for a person, such as who is reporting the problem from a shared desk login. Start typing a name and <strong>pick it from the list</strong> &mdash; text you type without picking a name isn't saved.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send-check text-success me-2"></i>Submit &mdash; With a Duplicate Check</h5>
<p class="text-muted mb-2">For some request types, pressing <strong><?= e($submitLabel) ?></strong> first compares your request with recent open requests &mdash; your own, plus others from your branch if your account can see them. If one looks like the same problem, you'll see a <strong>duplicate warning</strong> <em>before</em> anything is created, with how close a match it is.</p>
<ul class="text-muted mb-0">
    <li><strong>Click here to see this ticket</strong> &mdash; take a peek at the existing request without leaving your form.</li>
    <li><strong>Create anyway &mdash; This is a Different Issue</strong> &mdash; submit yours as a new request.</li>
    <li><strong>Let me edit my request first.</strong> &mdash; go back and change what you wrote.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hourglass-split text-primary me-2"></i>What Happens Next</h5>
<p class="text-muted mb-2">Once it's submitted, you land on your new request, and a confirmation email is on its way (unless you've turned that email off in your profile). If response-time targets apply, a <strong>What happens next</strong> window shows when you can expect a <strong>first response</strong> and a <strong>resolution</strong>. These count business hours only.</p>
<p class="text-muted mb-0"><strong>Changed your mind?</strong> If your helpdesk has turned on <em>undo</em>, a short countdown with an <strong>Undo</strong> button appears after you press <strong><?= e($submitLabel) ?></strong>. Click <strong>Undo</strong> (or press Esc) to get back to your filled-in form. Nothing is sent until the countdown ends.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-floppy text-primary me-2"></i>Drafts &mdash; Nothing Gets Lost</h5>
<p class="text-muted mb-0">Half-written requests are <strong>saved automatically</strong>, even if you switch computers. When you come back you'll see <em>Restored your unsent ticket draft</em>; click <strong>Discard draft</strong> to start fresh instead. The same goes for comments you start writing on an existing request.</p>
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
