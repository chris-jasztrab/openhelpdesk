<?php
$layout    = 'public';
$pageTitle = $category['name'] . ' – Help Center';
$breadcrumbs = [
    ['label' => 'Help Center', 'url' => '/kb'],
    ['label' => $category['name']],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1"><?= e($category['name']) ?></h2>
    <?php if ($category['description']): ?>
        <p class="text-muted mb-0"><?= e($category['description']) ?></p>
    <?php endif; ?>
</div>

<?php if (empty($folders)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-folder" style="font-size:3rem;"></i>
        <p class="mt-3">No articles in this category yet.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($folders as $folder): ?>
        <div class="col-md-6">
            <a href="/kb/<?= e($category['slug']) ?>/<?= e($folder['slug']) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100" style="transition:transform .15s ease;"
                     onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:44px;height:44px;background:#eef2ff;">
                                <i class="bi bi-folder2-open" style="font-size:1.25rem;color:var(--ld-primary);"></i>
                            </div>
                            <div>
                                <h5 class="fw-semibold mb-0 text-dark"><?= e($folder['name']) ?></h5>
                                <small class="text-muted"><?= (int)$folder['article_count'] ?> article<?= (int)$folder['article_count'] !== 1 ? 's' : '' ?></small>
                                <?php if ($folder['description'] ?? ''): ?>
                                    <p class="text-muted small mb-0 mt-1"><?= e(mb_strimwidth($folder['description'], 0, 100, '…')) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
