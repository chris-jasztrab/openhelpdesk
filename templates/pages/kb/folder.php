<?php
$layout    = 'public';
$pageTitle = $folder['name'] . ' – Help Center';
$breadcrumbs = [
    ['label' => 'Help Center',       'url' => '/kb'],
    ['label' => $category['name'],   'url' => '/kb/' . $category['slug']],
    ['label' => $folder['name']],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1"><?= e($folder['name']) ?></h2>
    <?php if ($folder['description'] ?? ''): ?>
        <p class="text-muted mb-0"><?= e($folder['description']) ?></p>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($articles)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-text" style="font-size:3rem;"></i>
                <p class="mt-3">No articles here yet.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($articles as $art): ?>
                <a href="/kb/articles/<?= e($art['slug']) ?>"
                   class="list-group-item list-group-item-action py-3 px-4 d-flex align-items-center gap-3">
                    <i class="bi bi-file-earmark-text text-muted"></i>
                    <span class="fw-semibold flex-grow-1"><?= e($art['title']) ?></span>
                    <small class="text-muted d-none d-md-block"><?= date('M j, Y', strtotime($art['created_at'])) ?></small>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
