<?php
$layout       = 'app';
$pageTitle    = 'Docs: Satisfaction Surveys';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Satisfaction Surveys']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Satisfaction Surveys (CSAT)</h2>
<p class="text-muted mb-4">Automatically email a rating request when a ticket is resolved or closed. You can use the built-in 1&ndash;5 star page or link recipients to an external survey tool (HappyOrNot, SurveyGizmo, Jotform, Typeform, etc.).</p>

<div class="alert alert-info small mb-4"><i class="bi bi-info-circle me-2"></i>
    Configure everything described here at <a href="/admin/settings/csat"><strong>Admin &rarr; Settings &rarr; CSAT Surveys</strong></a>. View collected results at <a href="/admin/reports/csat"><strong>Admin &rarr; Reports &rarr; Satisfaction</strong></a>.
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send text-primary me-2"></i>How and when a survey is sent</h5>
<p class="text-muted mb-2">When CSAT is enabled, the app watches for tickets reaching the <strong>trigger status</strong> (default <em>Resolved</em>, or you can switch to <em>Closed</em>). The moment a ticket lands on that status &mdash; whether the agent posted a public reply with a status change or used the quick-status modal &mdash; a survey email is queued to the ticket's <strong>requester</strong> (the user in <code>created_by</code>, not whoever submitted the ticket on their behalf).</p>
<p class="text-muted mb-2"><strong>Guards that keep things sane:</strong></p>
<ul class="text-muted mb-0">
    <li>Only <strong>one survey per ticket</strong> &mdash; if a ticket is re-resolved after being reopened, no duplicate email is sent.</li>
    <li>The requester's <strong>"Send me satisfaction surveys"</strong> notification preference is respected. If they've turned it off in their profile, no email goes out.</li>
    <li>If <strong>External</strong> mode is selected but the URL is blank, the send is skipped entirely (and doesn't burn the one-survey-per-ticket slot) so the misconfiguration is fixable.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-toggle2-on text-primary me-2"></i>Survey type: Built-in vs. External</h5>
<p class="text-muted mb-3">Pick one of two modes:</p>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2"><i class="bi bi-star me-1 text-warning"></i>Built-in</div>
            <p class="text-muted small mb-2">The rating button in the email opens a public page hosted by this app (no login required). Recipients pick 1&ndash;5 stars and can leave an optional comment.</p>
            <p class="text-muted small mb-0"><strong>Results live in this app</strong> &mdash; visible on the Satisfaction report with average rating, response rate, distribution, and full per-ticket comments.</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2"><i class="bi bi-box-arrow-up-right me-1 text-primary"></i>External</div>
            <p class="text-muted small mb-2">The rating button links to a URL you provide &mdash; typically a HappyOrNot smiley page, a SurveyGizmo / Alchemer link, a Jotform, a Typeform, or any other web survey.</p>
            <p class="text-muted small mb-0"><strong>Results live in the external service.</strong> This app still tracks <em>that</em> a survey was sent so the "Surveys Sent" count and the one-per-ticket guard keep working &mdash; but ratings and comments are stored wherever you put the URL.</p>
        </div>
    </div>
</div>

<p class="text-muted mb-0 small"><i class="bi bi-lightbulb text-warning me-1"></i> Picking External does not delete any previously collected built-in ratings &mdash; they remain visible on the report under their original dates.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="placeholders">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-braces text-primary me-2"></i>External URL placeholders</h5>
<p class="text-muted mb-2">When you paste an external survey URL, you can include any of these <code>{placeholder}</code> tokens. Each value is URL-encoded at send time, so they're safe to drop straight into a query string.</p>
<div class="table-responsive mb-3">
    <table class="table table-sm mb-0">
        <thead class="table-light">
            <tr><th style="width:170px;">Placeholder</th><th>Replaced with</th></tr>
        </thead>
        <tbody>
            <tr><td><code>{ticket_id}</code></td><td>The numeric ticket ID (e.g. <code>1234</code>).</td></tr>
            <tr><td><code>{user_email}</code></td><td>The requester's email address.</td></tr>
            <tr><td><code>{first_name}</code></td><td>Requester's first name.</td></tr>
            <tr><td><code>{last_name}</code></td><td>Requester's last name.</td></tr>
            <tr><td><code>{user_name}</code></td><td>First + last name combined.</td></tr>
            <tr><td><code>{subject}</code></td><td>The ticket subject line.</td></tr>
        </tbody>
    </table>
</div>
<p class="text-muted mb-2"><strong>Example:</strong></p>
<div class="bg-light rounded px-3 py-2 font-monospace small mb-3" style="word-break:break-all;">
    https://survey.example.com/start?tid={ticket_id}&amp;email={user_email}&amp;subj={subject}
</div>
<p class="text-muted mb-2"><strong>Per-provider notes:</strong></p>
<ul class="text-muted mb-0">
    <li><strong>HappyOrNot, single-link smiley surveys</strong> &mdash; usually one shared URL per kiosk/page. Paste it as-is; you can ignore the placeholders.</li>
    <li><strong>SurveyGizmo / Alchemer, Jotform, Typeform, Microsoft Forms (with pre-fill enabled)</strong> &mdash; use placeholders in the query string to pre-fill hidden fields so each response can be linked back to the originating ticket.</li>
    <li><strong>Unknown placeholders are left intact</strong> in the rendered URL (not stripped or blanked) so a typo like <code>{ticked_id}</code> is visible in the email for debugging.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="reopen-button">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-counterclockwise text-primary me-2"></i>The reopen button</h5>
<p class="text-muted mb-2">There's a separate switch &mdash; <strong>Include &ldquo;No, please reopen it&rdquo; button</strong> &mdash; that controls the email layout. It works the same for both modes:</p>
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Switch ON (default)</div>
            <p class="text-muted small mb-2">The email shows <strong>two buttons</strong>: a red <em>&ldquo;No, please reopen it&rdquo;</em> and a green <em>&ldquo;Yes, it was resolved!&rdquo;</em>.</p>
            <p class="text-muted small mb-0">The reopen button always points back to this app (one click and the ticket flips back to Open with a timeline note). The rate button goes to either the built-in star page or your external URL, depending on the mode.</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Switch OFF</div>
            <p class="text-muted small mb-2">The email collapses to a <strong>single centered button</strong> &mdash; <em>&ldquo;Rate your experience&rdquo;</em> &mdash; styled in your brand colour.</p>
            <p class="text-muted small mb-0">Cleaner for a pure rating flow. Recipients who actually need their ticket reopened will have to reply to the ticket email or visit the portal &mdash; the email no longer offers a one-click reopen.</p>
        </div>
    </div>
</div>
<p class="text-muted mb-0 small"><i class="bi bi-lightbulb text-warning me-1"></i> External surveys can't trigger reopens on their own &mdash; the survey provider has no idea what a "ticket" is. If you want recipients to be able to reopen with one click, keep this switch on even in External mode.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="reports">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bar-chart text-primary me-2"></i>Where the results show up</h5>
<p class="text-muted mb-2">The <a href="/admin/reports/csat"><strong>Reports &rarr; Satisfaction</strong></a> page is the same in both modes, but it shows different things:</p>
<ul class="text-muted mb-3">
    <li><strong>Built-in mode</strong> &mdash; full picture: surveys sent, responses, response rate, average rating, star distribution, and a table of every comment (linked to its ticket).</li>
    <li><strong>External mode</strong> &mdash; only the <em>Surveys Sent</em> count is meaningful (responses, average rating, etc. will show as 0 because ratings live in the external system). A blue banner appears at the top of the report explaining this.</li>
</ul>
<p class="text-muted mb-0">If you fill in the optional <strong>External dashboard URL</strong> setting (e.g. a link to your SurveyGizmo report or HappyOrNot dashboard), the banner gains an <strong>Open external dashboard</strong> button so admins can jump straight to where the ratings actually live.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send-check text-primary me-2"></i>Sending a test survey</h5>
<p class="text-muted mb-2">The <strong>Send a Test Survey</strong> form at the bottom of <a href="/admin/settings/csat">the CSAT settings page</a> uses the same logic as a real send, so you can preview the email end-to-end &mdash; including external URLs with placeholders substituted &mdash; before any real ticket triggers one.</p>
<p class="text-muted mb-0">Test emails reuse the most recent ticket that doesn't already have a survey attached, so the placeholders show plausible values rather than dummy data.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-check text-primary me-2"></i>Settings reference</h5>
<div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead class="table-light">
            <tr><th style="width:240px;">Setting</th><th>What it does</th></tr>
        </thead>
        <tbody>
            <tr><td><strong>Enable CSAT surveys</strong></td><td>Master switch. Off = no survey emails ever go out, regardless of the other settings below.</td></tr>
            <tr><td><strong>Send survey when ticket is</strong></td><td><em>Resolved</em> (default, recommended) or <em>Closed</em>. Picks the status that triggers the email.</td></tr>
            <tr><td><strong>Survey type</strong></td><td><em>Built-in</em> (default) hosts the rating page in this app. <em>External</em> links to a URL you provide.</td></tr>
            <tr><td><strong>External survey URL</strong></td><td>Required when type is External. Supports <code>{ticket_id}</code>, <code>{user_email}</code>, <code>{first_name}</code>, <code>{last_name}</code>, <code>{user_name}</code>, <code>{subject}</code>.</td></tr>
            <tr><td><strong>External dashboard URL</strong></td><td>Optional. Surfaces as an <em>Open external dashboard</em> button on the Satisfaction report.</td></tr>
            <tr><td><strong>Include &ldquo;No, please reopen it&rdquo; button</strong></td><td>On = two buttons (reopen + rate). Off = single centered "Rate your experience" button. Affects both modes.</td></tr>
        </tbody>
    </table>
</div>
</div>
</div>

</div></div>
