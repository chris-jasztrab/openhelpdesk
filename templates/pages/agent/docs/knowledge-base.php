<?php
$layout       = 'app';
$pageTitle    = 'Help: Knowledge Base';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Knowledge Base']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Knowledge Base</h2>
<p class="text-muted mb-4">Find answers to send to requesters, and turn solved tickets into articles.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-book text-primary me-2"></i>Browsing &amp; Searching</h5>
<p class="text-muted mb-2">Click <strong><i class="bi bi-book"></i> Knowledge Base</strong> in the left sidebar. Articles are organised into <strong>categories</strong>, then <strong>folders</strong>. Click your way down to an article, and use <strong>Back</strong> to go up a level.</p>
<ul class="text-muted mb-0">
    <li><strong>Search articles&hellip;</strong> at the top of the <a href="/agent/kb">Knowledge Base</a> page searches the titles and text of published articles as you type.</li>
    <li>The search box in the top bar also finds articles &mdash; switch to its <em>Knowledge Base Articles</em> tab to see only those.</li>
    <li>Only published articles are shown here; drafts are only visible to the people who manage articles.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hand-thumbs-up text-primary me-2"></i>Rating an Article</h5>
<p class="text-muted mb-0">At the bottom of each article, answer <em>&ldquo;Was this article helpful?&rdquo;</em> with <strong>Yes</strong> or <strong>No</strong>. The counts update straight away. You get one vote per article; clicking the other button changes it.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-journal-plus text-primary me-2"></i>Writing &amp; Editing Articles</h5>
<p class="text-muted mb-2">Creating and editing articles needs the <em>manage KB articles</em> permission. If your level has it:</p>
<ul class="text-muted mb-0">
    <li><strong>Edit Article</strong> at the top of an article opens it in the editor.</li>
    <li><strong>Settings &rarr; Knowledge Base &rarr; Manage Articles</strong> lists every article, including drafts, and lets you add new ones. <em>Categories &amp; Folders</em> appears there too if you're allowed to organise them.</li>
    <li>When you've solved a ticket worth sharing, use <strong>Close and create KB article</strong> from the ticket's Close menu. It closes the ticket and opens a draft article with the ticket's subject as the title, the description as the <em>Problem</em> and the solution (or latest staff reply) as the <em>Resolution</em>. Tidy it up, remove anything personal, and publish. See <a href="/agent/help/working-tickets#closing">Closing a Ticket</a>.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->
