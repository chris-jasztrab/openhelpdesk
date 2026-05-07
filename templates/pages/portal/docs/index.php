<?php
$layout       = 'app';
$pageTitle    = 'Help';
$sidebarItems = portalSidebar('help');
$breadcrumbs  = [['label' => 'Help']];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Help</h2>
    <p class="text-muted mb-0">Quick guides for using the help portal.</p>
</div>

<div class="row g-3">
    <?php
    $cards = [
        ['url'   => '/portal/help/floor',
         'icon'  => 'bi-grid-1x2',
         'color' => '#7c3aed',
         'bg'    => '#f5f3ff',
         'title' => 'Floor Mode',
         'desc'  => 'A simple, phone-friendly view of your help requests. See updates, reply, attach a photo, and close a request when it\'s sorted.'],
    ];
    foreach ($cards as $card): ?>
    <div class="col-md-6 col-xl-4">
        <a href="<?= e($card['url']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none" style="transition:box-shadow .15s;">
            <div class="card-body d-flex gap-3">
                <div class="flex-shrink-0 rounded d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;background:<?= $card['bg'] ?>;font-size:1.3rem;color:<?= $card['color'] ?>;">
                    <i class="bi <?= $card['icon'] ?>"></i>
                </div>
                <div>
                    <div class="fw-semibold text-body mb-1"><?= e($card['title']) ?></div>
                    <div class="text-muted small"><?= e($card['desc']) ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
