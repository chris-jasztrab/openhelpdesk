<?php
$layout    = 'public';
$pageTitle = 'Help Center';
$breadcrumbs = [
    ['label' => 'Help Center'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Help Center</h2>
    <p class="text-muted mb-0">Browse our knowledge base to find answers and guides.</p>
</div>

<?php if (empty($cats)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-book" style="font-size:3rem;"></i>
        <p class="mt-3">No articles are published yet. Check back soon.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($cats as $cat): ?>
        <div class="col-md-6 col-lg-4">
            <a href="/kb/<?= e($cat['slug']) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100" style="transition:transform .15s ease;"
                     onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-3 d-flex align-items-center justify-content-center me-3"
                                 style="width:44px;height:44px;background:#eef2ff;">
                                <i class="bi bi-collection" style="font-size:1.25rem;color:var(--ld-primary);"></i>
                            </div>
                            <div>
                                <h5 class="fw-semibold mb-0 text-dark"><?= e($cat['name']) ?></h5>
                                <small class="text-muted"><?= (int)$cat['article_count'] ?> article<?= (int)$cat['article_count'] !== 1 ? 's' : '' ?></small>
                            </div>
                        </div>
                        <?php if ($cat['description']): ?>
                            <p class="text-muted small mb-0"><?= e(mb_strimwidth($cat['description'], 0, 120, '…')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
