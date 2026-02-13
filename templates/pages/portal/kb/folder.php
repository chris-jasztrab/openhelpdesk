<?php
$layout       = 'app';
$pageTitle    = $folder['name'] . ' – Knowledge Base';
$sidebarItems = portalSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Portal', 'url' => '/portal'],
    ['label' => 'Knowledge Base', 'url' => '/portal/kb'],
    ['label' => $category['name'], 'url' => '/portal/kb/' . $category['slug']],
    ['label' => $folder['name']],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><?= e($folder['name']) ?></h2>
        <?php if ($folder['description']): ?>
            <p class="text-muted mb-0"><?= e($folder['description']) ?></p>
        <?php endif; ?>
    </div>
    <a href="/portal/kb/<?= e($category['slug']) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if (empty($articles)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-file-text" style="font-size:3rem;"></i>
        <p class="mt-2">No articles in this folder yet.</p>
    </div>
<?php else: ?>
    <div class="list-group shadow-sm">
        <?php foreach ($articles as $a): ?>
        <a href="/portal/kb/articles/<?= e($a['slug']) ?>" class="list-group-item list-group-item-action border-0 py-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-file-text text-muted me-3" style="font-size:1.25rem;"></i>
                <div>
                    <div class="fw-semibold"><?= e($a['title']) ?></div>
                    <?php if ($a['published_at']): ?>
                        <small class="text-muted">Published <?= date('M j, Y', strtotime($a['published_at'])) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
