<?php
$layout       = 'app';
$pageTitle    = 'Docs: Knowledge Base';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Knowledge Base']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Knowledge Base</h2>
<p class="text-muted mb-4">Publish self-service articles to help users resolve common issues without submitting a ticket.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>Overview</h5>
<p class="text-muted mb-2">The Knowledge Base is a collection of articles that portal users can browse and search. Well-maintained knowledge bases reduce ticket volume by giving users answers to common questions.</p>
<p class="text-muted mb-0">Articles are organised into categories and can be published or kept as drafts. Only <strong>published</strong> articles are visible to portal users.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-folder-plus text-primary me-2"></i>Creating Categories</h5>
<p class="text-muted mb-2">Categories organise your articles into logical groups (e.g. "Getting Started", "Account Management", "Troubleshooting").</p>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/kb/categories"><strong>Admin → Knowledge Base → Categories</strong></a>.</li>
    <li>Click <strong>Add Category</strong>.</li>
    <li>Enter a name and an optional description.</li>
    <li>Optionally check <strong>Make Public</strong> to allow anyone to browse the category without logging in (see <em>Public Knowledge Base</em> below).</li>
    <li>Click <strong>Save</strong>.</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Only categories marked as public appear on the unauthenticated <code>/kb</code> page. Categories that are not public remain visible only to logged-in portal users.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-file-earmark-plus text-primary me-2"></i>Creating Articles</h5>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/kb"><strong>Admin → Knowledge Base</strong></a>.</li>
    <li>Click <strong>New Article</strong>.</li>
    <li>Enter a <strong>title</strong> — this becomes the article heading and the URL slug.</li>
    <li>Select a <strong>category</strong>.</li>
    <li>Write the article body using the rich text editor. You can add headings, lists, bold/italic text, code blocks, and links.</li>
    <li>Set the status to <strong>Published</strong> to make it visible in the portal, or leave as <strong>Draft</strong> to save without publishing.</li>
    <li>Click <strong>Save</strong>.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil text-primary me-2"></i>Editing &amp; Publishing Articles</h5>
<p class="text-muted mb-2">From <a href="/admin/kb"><strong>Admin → Knowledge Base</strong></a>, click any article title to edit it. You can:</p>
<ul class="text-muted mb-0">
    <li>Update the content at any time — changes take effect immediately for published articles.</li>
    <li>Toggle an article between <strong>Draft</strong> and <strong>Published</strong> using the status selector.</li>
    <li>Delete an article — this cannot be undone.</li>
    <li>Use <strong>Publish All</strong> to publish every draft article at once.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-upload text-primary me-2"></i>Importing Articles from CSV</h5>
<p class="text-muted mb-2">Bulk-import articles from a CSV file at <a href="/admin/kb/import"><strong>Admin → Knowledge Base → Import</strong></a>. The CSV must have at minimum a <strong>title</strong> and <strong>body</strong> column.</p>
<p class="text-muted mb-2">Optional columns:</p>
<ul class="text-muted mb-3">
    <li><strong>category</strong> — category name; creates the category if it does not exist.</li>
    <li><strong>status</strong> — <code>published</code> or <code>draft</code>. Defaults to draft if omitted.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    After import, review the articles and publish them individually or use <strong>Publish All</strong> once you have verified the content.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-search text-primary me-2"></i>Portal Search</h5>
<p class="text-muted mb-0">Portal users can search across all published articles using the search bar in the Knowledge Base section. The search covers both article titles and body content, returning ranked results. Encourage users to search before submitting a ticket by keeping your knowledge base up to date.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-globe text-primary me-2"></i>Public Knowledge Base</h5>
<p class="text-muted mb-2">Categories and their articles can be made publicly accessible at <strong><a href="/kb">/kb</a></strong> — no login required. This is useful for sharing common how-to guides with visitors or new staff before they have an account.</p>
<p class="text-muted mb-2">To make a category public:</p>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/kb/categories"><strong>Admin → Knowledge Base → Categories</strong></a>.</li>
    <li>Edit the category and check <strong>Make Public</strong>.</li>
    <li>Click <strong>Save</strong>. The category and all its published articles will immediately appear on the public <code>/kb</code> page.</li>
</ol>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    Only <strong>published</strong> articles within a public category are shown publicly. Draft articles remain hidden even if their category is public.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hand-thumbs-up text-primary me-2"></i>Article Feedback</h5>
<p class="text-muted mb-2">Portal users can rate any published KB article as <strong>helpful</strong> or <strong>not helpful</strong> using the thumbs up / thumbs down buttons at the bottom of every article page.</p>
<p class="text-muted mb-2">Feedback helps you identify which articles are working well and which may need improvement:</p>
<ul class="text-muted mb-0">
    <li>Each user can submit one rating per article (they can change it later).</li>
    <li>The helpful/not-helpful counts are recorded in the database for future reporting.</li>
    <li>Use low helpful-ratings as a signal to review and update article content.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Article Version History</h5>
<p class="text-muted mb-2">Every time an article is saved, a revision is automatically created and stored. Admins can view the complete revision history for any article and restore any prior version.</p>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/kb/articles"><strong>Admin → Knowledge Base → Articles</strong></a>.</li>
    <li>Click the <strong>History</strong> (clock) button on any article row.</li>
    <li>The revision list shows the date, editor, and a preview of each saved version.</li>
    <li>Click <strong>Restore</strong> on any revision to revert the article body to that version. A new revision is created so the current content is not lost.</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Revisions accumulate over time. Restoring a version does not delete intermediate revisions — the full history is always preserved.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightbulb text-primary me-2"></i>Article Suggestions When Creating a Ticket</h5>
<p class="text-muted mb-2">When a portal user is writing the subject of a new ticket, OpenHelpDesk automatically searches the Knowledge Base and displays matching published articles as suggestions below the subject field.</p>
<p class="text-muted mb-2">This gives users a chance to self-serve before submitting — if a relevant article answers their question, they can read it without creating a ticket at all.</p>
<ul class="text-muted mb-0">
    <li>Suggestions appear after a short pause while the user types.</li>
    <li>Only <strong>published</strong> articles are searched.</li>
    <li>Clicking a suggestion opens the article in a new tab.</li>
    <li>To maximise the usefulness of suggestions, use clear and specific article titles that match the language users are likely to type.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
