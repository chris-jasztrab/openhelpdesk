<?php
$layout       = 'app';
$pageTitle    = $category['name'] . ' – Knowledge Base';
$kbBase       ??= '/portal/kb';
$kbPanelLabel ??= 'Portal';
$kbPanelUrl   ??= '/portal';
$sidebarItems ??= portalSidebar('kb');
$breadcrumbs  = [
    ['label' => $kbPanelLabel, 'url' => $kbPanelUrl],
    ['label' => 'Knowledge Base', 'url' => $kbBase],
    ['label' => $category['name']],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><?= e($category['name']) ?></h2>
        <?php if ($category['description']): ?>
            <p class="text-muted mb-0"><?= e($category['description']) ?></p>
        <?php endif; ?>
    </div>
    <a href="<?= $kbBase ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if (empty($folders)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-folder" style="font-size:3rem;"></i>
        <p class="mt-2">No folders in this category yet.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($folders as $f): ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= $kbBase ?>/<?= e($category['slug']) ?>/<?= e($f['slug']) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-3 d-flex align-items-center justify-content-center me-3"
                                 style="width:44px; height:44px; background:#fef3c7;">
                                <i class="bi bi-folder" style="font-size:1.25rem; color:#d97706;"></i>
                            </div>
                            <div>
                                <h5 class="fw-semibold mb-0 text-dark"><?= e($f['name']) ?></h5>
                                <small class="text-muted"><?= (int) $f['article_count'] ?> article<?= (int) $f['article_count'] !== 1 ? 's' : '' ?></small>
                            </div>
                        </div>
                        <?php if ($f['description']): ?>
                            <p class="text-muted small mb-0"><?= e(mb_strimwidth($f['description'], 0, 120, '...')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
