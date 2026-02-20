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
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/kb/categories"><strong>Admin → Knowledge Base → Categories</strong></a>.</li>
    <li>Click <strong>Add Category</strong>.</li>
    <li>Enter a name and an optional description.</li>
    <li>Click <strong>Save</strong>.</li>
</ol>
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

</div><!-- col -->
</div><!-- row -->
