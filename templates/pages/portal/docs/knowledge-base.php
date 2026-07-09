<?php
$layout       = 'app';
$pageTitle    = 'Help: Finding Answers Yourself';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/portal/help'], ['label' => 'Finding Answers Yourself']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/portal-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Finding Answers Yourself</h2>
<p class="text-muted mb-4">The <strong>Knowledge Base</strong> is a library of how-to articles and answers to common questions. It's the fastest way to fix something &mdash; often quicker than submitting a request and waiting for a reply.</p>

<div class="alert alert-light border d-flex gap-3 mb-4">
    <i class="bi bi-book fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        Open it from the <i class="bi bi-book"></i> <strong>Knowledge Base</strong> link in the left sidebar.
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-grid text-primary me-2"></i>Browsing</h5>
<p class="text-muted mb-0">Articles are grouped into <strong>categories</strong> and <strong>folders</strong>. Click a category to see its folders, then a folder to see its articles. It's a bit like flipping through labelled sections of a manual &mdash; good for exploring when you're not sure exactly what to search for.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-search text-primary me-2"></i>Searching</h5>
<p class="text-muted mb-2">If you know roughly what you're after, searching is faster than browsing. Type a keyword in the search box &mdash; it looks through every article. The same site-wide search (press <code>/</code> on any page) covers Knowledge Base articles too.</p>
<p class="text-muted small mb-0">You'll also see matching articles pop up automatically while you type the <strong>subject</strong> of a new request &mdash; a last chance to find the answer before you even submit.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hand-thumbs-up text-success me-2"></i>Was It Helpful?</h5>
<p class="text-muted mb-0">At the bottom of each article you can tell us whether it <strong>helped or not</strong>. This is genuinely useful &mdash; it tells us which articles are working and which ones need to be rewritten or expanded. It takes one click.</p>
</div>
</div>

<div class="alert alert-info border-0 d-flex gap-3 mb-0" style="background:#eff6ff;">
    <i class="bi bi-info-circle fs-4 text-primary flex-shrink-0"></i>
    <div class="small mb-0">
        Didn't find your answer? No problem &mdash; that's exactly what a <a href="/portal/help/submitting">help request</a> is for.
    </div>
</div>

</div><!-- col -->
</div><!-- row -->
