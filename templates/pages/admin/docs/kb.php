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
<p class="text-muted mb-2">The Knowledge Base is a collection of articles that users can browse and search. Well-maintained knowledge bases reduce ticket volume by giving users answers to common questions.</p>
<p class="text-muted mb-2">It is organised in three levels: <strong>categories</strong> contain <strong>folders</strong>, and every article lives in a folder. Articles are either <strong>Draft</strong> or <strong>Published</strong>; only published articles are shown to readers.</p>
<ul class="text-muted mb-0">
    <li>Portal users read the knowledge base in the portal; staff read it at <code>/agent/kb</code>; categories marked public are also open to anyone at <a href="/kb"><code>/kb</code></a>.</li>
    <li>Articles are managed at <a href="/admin/kb/articles"><strong>Settings → Knowledge Base → Manage Articles</strong></a>, which needs the <em>Create &amp; delete KB articles</em> permission.</li>
    <li>Categories and folders are managed at <a href="/admin/kb/categories"><strong>Settings → Knowledge Base → Categories &amp; Folders</strong></a>, which needs <em>Manage KB categories &amp; folders</em>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-folder-plus text-primary me-2"></i>Categories &amp; Folders</h5>
<p class="text-muted mb-2">Categories group your articles into broad areas (e.g. "Getting Started", "Account Management", "Troubleshooting"); folders divide a category further.</p>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/kb/categories"><strong>Settings → Knowledge Base → Categories &amp; Folders</strong></a> and click <strong>Add Category</strong>.</li>
    <li>Enter a <strong>Category Name</strong> and an optional description.</li>
    <li>Optionally turn on <strong>Public</strong> to let anyone browse the category without signing in (see <a href="#public-kb">Public Knowledge Base</a>), then save.</li>
    <li>Click <strong>Folders</strong>, then <strong>Add Folder</strong>: give it a <strong>Folder Name</strong> and choose its <strong>Category</strong>. A category needs at least one folder before articles can go in it.</li>
</ol>
<ul class="text-muted mb-0">
    <li>Drag the grip handle <i class="bi bi-grip-vertical"></i> to set the order categories and folders appear in.</li>
    <li>Deleting a category deletes all its folders and articles; deleting a folder deletes all its articles. Neither can be undone.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-file-earmark-plus text-primary me-2"></i>Creating Articles</h5>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/kb/articles"><strong>Settings → Knowledge Base → Manage Articles</strong></a>.</li>
    <li>Click <strong>Add Article</strong>.</li>
    <li>Enter a <strong>Title</strong> &mdash; this becomes the article heading and its web address.</li>
    <li>Pick a <strong>Folder</strong> (listed as <em>Category / Folder</em>).</li>
    <li>Write the <strong>Body</strong> in the rich text editor: headings, lists, bold/italic, font and background colours, tables, images, links and code.</li>
    <li>Set <strong>Status</strong> to <strong>Published</strong> to make it visible, or leave it as <strong>Draft</strong>.</li>
    <li>Click <strong>Create Article</strong>.</li>
</ol>
<p class="text-muted mb-0"><strong>From a ticket.</strong> On a ticket, the Close menu's <strong>Close and create KB article</strong> closes the ticket and opens a new draft article prefilled with the ticket's subject, the problem (its description) and the resolution (the marked solution or the latest staff reply). Pick a folder, tidy the text and save. See <a href="/admin/docs/tickets#closing">Closing a Ticket</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil text-primary me-2"></i>Editing &amp; Publishing Articles</h5>
<p class="text-muted mb-2"><a href="/admin/kb/articles"><strong>Manage Articles</strong></a> lists every article with its folder, author, status and last update. Each row has <strong>Preview</strong>, <strong>Edit</strong>, <strong>History</strong> and <strong>Delete</strong> buttons. When staff read an article in the portal or on the public knowledge base, an <strong>Edit Article</strong> button opens it in the editor (editing still needs the permission above).</p>
<ul class="text-muted mb-0">
    <li>Changes take effect immediately for published articles.</li>
    <li>Publish or unpublish an article by switching its <strong>Status</strong> between <strong>Draft</strong> and <strong>Published</strong>.</li>
    <li>Deleting an article cannot be undone.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="history">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Article Version History</h5>
<p class="text-muted mb-2">Every time an article is saved, the version it replaces is kept as a revision.</p>
<ol class="text-muted mb-3">
    <li>On <a href="/admin/kb/articles"><strong>Manage Articles</strong></a>, click the <strong>History</strong> (clock) button on an article.</li>
    <li>The revision list shows each saved version's title, who saved over it, and when.</li>
    <li>Click <strong>View Diff</strong> to compare that revision with the current version line by line (very long articles are shown side by side instead).</li>
</ol>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    There is no one-click restore. To roll back, copy the text you need from the revision into the editor and save; that save is itself recorded as a new revision, so nothing is lost.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="import">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-upload text-primary me-2"></i>Importing &amp; Exporting Articles (CSV)</h5>
<p class="text-muted mb-2"><strong>Export CSV</strong> on <a href="/admin/kb/articles"><strong>Manage Articles</strong></a> downloads every article with the columns <code>title</code>, <code>body_markdown</code>, <code>category</code>, <code>status</code> and <code>tags</code> (always empty).</p>
<p class="text-muted mb-2">To bulk-import, click <strong>Import CSV</strong> (or go to <a href="/admin/settings/import-kb"><strong>Settings → Data → Import KB</strong></a>), choose a CSV file of up to 10 MB and click <strong>Upload &amp; Preview</strong>. The file needs a header row with at least these columns:</p>
<ul class="text-muted mb-3">
    <li><strong>title</strong> and <strong>body_markdown</strong> (the article body; HTML is fine). Rows missing either are skipped.</li>
    <li><strong>category</strong> (optional) &mdash; matched by name and created if it doesn't exist. Blank means <em>General</em>.</li>
    <li><strong>status</strong> (optional) &mdash; <code>published</code> or <code>draft</code>. Anything else imports as draft.</li>
    <li>A <code>tags</code> column is accepted but ignored; articles have no tags.</li>
</ul>
<p class="text-muted mb-2">The preview shows how many articles, drafts and published articles were found, which categories will be created, and the first 15 rows. Tick <strong>Publish all articles on import</strong> to publish everything regardless of the status column, then confirm.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Imported articles go into a folder named <em>General</em> in their category (created if needed); move them to other folders afterwards by editing them. The import is all-or-nothing: if any row fails, nothing is imported. Importing the same file twice creates a second copy of each article.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-search text-primary me-2"></i>Searching the Knowledge Base</h5>
<p class="text-muted mb-0">Portal users and staff can search published articles from the Knowledge Base page; the search matches words in article titles and bodies. On the public <code>/kb</code> site, search covers only articles in public categories. Encourage users to search before submitting a ticket by keeping your knowledge base up to date.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="public-kb">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-globe text-primary me-2"></i>Public Knowledge Base</h5>
<p class="text-muted mb-2">Categories and their articles can be made publicly accessible at <strong><a href="/kb">/kb</a></strong> — no login required. This is useful for sharing common how-to guides with visitors or new staff before they have an account.</p>
<p class="text-muted mb-2">To make a category public:</p>
<ol class="text-muted mb-3">
    <li>Go to <a href="/admin/kb/categories"><strong>Settings → Knowledge Base → Categories &amp; Folders</strong></a>.</li>
    <li>Edit the category and turn on <strong>Public (visible without login at /kb/&hellip;)</strong>.</li>
    <li>Save. The category and all its published articles appear on <code>/kb</code> straight away.</li>
</ol>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    Only <strong>published</strong> articles within a public category are shown publicly. Draft articles remain hidden even if their category is public. Categories that are not public are still visible to signed-in portal users and staff.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hand-thumbs-up text-primary me-2"></i>Article Feedback</h5>
<p class="text-muted mb-2">Every published article ends with <strong>Was this article helpful?</strong> and <strong>Yes</strong> / <strong>No</strong> buttons, each showing its running count. This appears in the portal, on the staff knowledge base and on the public <code>/kb</code> site.</p>
<ul class="text-muted mb-0">
    <li>Each signed-in user gets one vote per article; visitors to the public site get one vote per browser session.</li>
    <li>The counts are shown on the article itself; there is no separate feedback report.</li>
    <li>Use a high "No" count as a signal to review and update the article.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-lightbulb text-primary me-2"></i>Article Suggestions When Creating a Ticket</h5>
<p class="text-muted mb-2">While a portal user types the subject of a new request, the form searches the Knowledge Base and shows matching published articles under <em>Related KB articles that may help</em>.</p>
<p class="text-muted mb-2">This gives users a chance to self-serve before submitting — if a relevant article answers their question, they can read it without creating a ticket at all.</p>
<ul class="text-muted mb-0">
    <li>Suggestions appear after the user has typed three or more characters and paused briefly; up to five are shown.</li>
    <li>Only <strong>published</strong> articles are searched.</li>
    <li>Clicking a suggestion opens the article in a new tab. The &times; hides the suggestions for the rest of the form.</li>
    <li>To maximise the usefulness of suggestions, use clear and specific article titles that match the language users are likely to type.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
