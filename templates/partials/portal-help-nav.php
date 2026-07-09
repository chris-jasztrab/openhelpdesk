<?php
$portalHelpNav = [
    ['url' => '/portal/help',                 'label' => 'Overview',              'icon' => 'bi-house'],
    ['url' => '/portal/help/getting-started', 'label' => 'Getting Started',       'icon' => 'bi-compass'],
    ['url' => '/portal/help/submitting',      'label' => 'Submitting a Request',  'icon' => 'bi-send'],
    ['url' => '/portal/help/tracking',        'label' => 'Tracking Your Requests','icon' => 'bi-ticket-detailed'],
    ['url' => '/portal/help/managing',        'label' => 'Replying & Closing',    'icon' => 'bi-chat-dots'],
    ['url' => '/portal/help/knowledge-base',  'label' => 'Finding Answers',       'icon' => 'bi-book'],
    ['url' => '/portal/help/profile',         'label' => 'Profile & Emails',      'icon' => 'bi-person-gear'],
    ['url' => '/portal/help/floor',           'label' => 'Floor Mode',            'icon' => 'bi-grid-1x2'],
];
$cp = currentPath();
?>
<div class="card border-0 shadow-sm mb-4" style="position:sticky;top:76px;">
    <div class="card-body p-2">
        <div class="small fw-semibold text-muted px-2 py-1 mb-1" style="text-transform:uppercase;letter-spacing:.05em;font-size:.7rem;">
            Help
        </div>
        <?php foreach ($portalHelpNav as $item): ?>
        <a href="<?= e($item['url']) ?>"
           class="d-flex align-items-center gap-2 px-2 py-2 rounded text-decoration-none small <?= $cp === $item['url'] ? 'fw-semibold' : 'text-muted' ?>"
           style="<?= $cp === $item['url'] ? 'background:var(--bs-secondary-bg);color:var(--ld-primary)!important;' : '' ?>">
            <i class="bi <?= e($item['icon']) ?>"></i>
            <?= e($item['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
