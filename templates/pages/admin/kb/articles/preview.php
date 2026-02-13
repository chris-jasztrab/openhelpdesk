<?php
$layout       = 'app';
$pageTitle    = 'Preview: ' . $article['title'];
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'KB Articles', 'url' => '/admin/kb/articles'],
    ['label' => 'Preview'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><?= e($article['title']) ?></h2>
        <div class="text-muted small">
            <?php if ($article['status'] === 'draft'): ?>
                <span class="badge bg-secondary me-1">Draft</span>
            <?php else: ?>
                <span class="badge bg-success me-1">Published</span>
            <?php endif; ?>
            <span><?= e($article['category_name'] ?? '') ?></span>
            <i class="bi bi-chevron-right" style="font-size:.6rem;"></i>
            <span><?= e($article['folder_name'] ?? '') ?></span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/kb/articles/<?= $article['id'] ?>/edit" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="/admin/kb/articles" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="kb-article-content">
            <?= $article['body_html'] ?>
        </div>
    </div>
</div>

<style>
.kb-article-content h1 { font-size: 1.75rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: .75rem; }
.kb-article-content h2 { font-size: 1.4rem; font-weight: 600; margin-top: 1.25rem; margin-bottom: .5rem; }
.kb-article-content h3 { font-size: 1.15rem; font-weight: 600; margin-top: 1rem; margin-bottom: .5rem; }
.kb-article-content p { margin-bottom: .75rem; line-height: 1.7; }
.kb-article-content ul, .kb-article-content ol { margin-bottom: .75rem; padding-left: 1.5rem; }
.kb-article-content pre { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: .5rem; padding: 1rem; overflow-x: auto; }
.kb-article-content code { font-size: .875em; }
.kb-article-content pre code { background: none; padding: 0; }
.kb-article-content code:not(pre code) { background: #f1f5f9; padding: .15em .35em; border-radius: .25rem; }
.kb-article-content blockquote { border-left: 4px solid #e2e8f0; padding-left: 1rem; color: #64748b; margin-bottom: .75rem; }
.kb-article-content img { max-width: 100%; height: auto; border-radius: .5rem; }
.kb-article-content table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
.kb-article-content th, .kb-article-content td { border: 1px solid #e2e8f0; padding: .5rem .75rem; }
.kb-article-content th { background: #f8fafc; font-weight: 600; }
</style>
