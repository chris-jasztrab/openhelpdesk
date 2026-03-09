<?php
$docsNav = [
    ['url' => '/admin/docs',                   'label' => 'Overview',              'icon' => 'bi-house'],
    ['url' => '/admin/docs/getting-started',   'label' => 'Getting Started',       'icon' => 'bi-rocket-takeoff'],
    ['url' => '/admin/docs/tickets',           'label' => 'Tickets',               'icon' => 'bi-ticket-detailed'],
    ['url' => '/admin/docs/users',             'label' => 'Users & Roles',         'icon' => 'bi-people'],
    ['url' => '/admin/docs/email',             'label' => 'Email & Notifications', 'icon' => 'bi-envelope'],
    ['url' => '/admin/docs/sla',               'label' => 'SLA Policies',          'icon' => 'bi-stopwatch'],
    ['url' => '/admin/docs/automations',       'label' => 'Automations',           'icon' => 'bi-lightning'],
    ['url' => '/admin/docs/branding',          'label' => 'Branding',              'icon' => 'bi-palette'],
    ['url' => '/admin/docs/portal',            'label' => 'Portal',                'icon' => 'bi-globe2'],
    ['url' => '/admin/docs/import',            'label' => 'Importing Tickets',     'icon' => 'bi-cloud-upload'],
    ['url' => '/admin/docs/kb',                'label' => 'Knowledge Base',        'icon' => 'bi-book'],
    ['url' => '/admin/docs/sso',               'label' => 'Single Sign-On',        'icon' => 'bi-shield-lock'],
];
$cp = currentPath();
?>
<div class="card border-0 shadow-sm mb-4" style="position:sticky;top:76px;">
    <div class="card-body p-2">
        <div class="small fw-semibold text-muted px-2 py-1 mb-1" style="text-transform:uppercase;letter-spacing:.05em;font-size:.7rem;">
            Documentation
        </div>
        <?php foreach ($docsNav as $item): ?>
        <a href="<?= e($item['url']) ?>"
           class="d-flex align-items-center gap-2 px-2 py-2 rounded text-decoration-none small <?= $cp === $item['url'] ? 'fw-semibold' : 'text-muted' ?>"
           style="<?= $cp === $item['url'] ? 'background:var(--bs-secondary-bg);color:var(--ld-primary)!important;' : '' ?>">
            <i class="bi <?= e($item['icon']) ?>"></i>
            <?= e($item['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
