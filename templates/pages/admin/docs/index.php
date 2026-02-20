<?php
$layout       = 'app';
$pageTitle    = 'Documentation';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Docs']];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Documentation</h2>
    <p class="text-muted mb-0">Everything you need to know about configuring and using LocalDesk.</p>
</div>

<div class="row g-3">
    <?php
    $cards = [
        ['url' => '/admin/docs/getting-started', 'icon' => 'bi-rocket-takeoff', 'color' => '#4f46e5', 'bg' => '#eff6ff',
         'title' => 'Getting Started',
         'desc'  => 'Initial setup, first login, and recommended configuration steps.'],
        ['url' => '/admin/docs/tickets',         'icon' => 'bi-ticket-detailed', 'color' => '#0891b2', 'bg' => '#f0f9ff',
         'title' => 'Tickets',
         'desc'  => 'Creating, managing and resolving tickets. Statuses, priorities and timelines.'],
        ['url' => '/admin/docs/users',           'icon' => 'bi-people',          'color' => '#16a34a', 'bg' => '#f0fdf4',
         'title' => 'Users & Roles',
         'desc'  => 'Admin, Agent and User roles. Adding staff and managing locations.'],
        ['url' => '/admin/docs/email',           'icon' => 'bi-envelope',        'color' => '#7c3aed', 'bg' => '#fdf4ff',
         'title' => 'Email & Notifications',
         'desc'  => 'SMTP setup, email templates, tokens and the shared footer.'],
        ['url' => '/admin/docs/sla',             'icon' => 'bi-stopwatch',       'color' => '#dc2626', 'bg' => '#fff1f2',
         'title' => 'SLA Policies',
         'desc'  => 'Response and resolution targets, business hours integration, breach alerts.'],
        ['url' => '/admin/docs/automations',     'icon' => 'bi-lightning',       'color' => '#ca8a04', 'bg' => '#fefce8',
         'title' => 'Automations',
         'desc'  => 'Auto-assignment rules and trigger-based actions.'],
        ['url' => '/admin/docs/branding',        'icon' => 'bi-palette',         'color' => '#db2777', 'bg' => '#fdf2f8',
         'title' => 'Branding',
         'desc'  => 'Logo, colour scheme, navbar gradient and timeline colours.'],
        ['url' => '/admin/docs/portal',          'icon' => 'bi-globe2',          'color' => '#0284c7', 'bg' => '#f0f9ff',
         'title' => 'Portal',
         'desc'  => 'How end users submit and track tickets. Portal URL and access.'],
        ['url' => '/admin/docs/import',          'icon' => 'bi-cloud-upload',    'color' => '#059669', 'bg' => '#f0fdf4',
         'title' => 'Importing Tickets',
         'desc'  => 'CSV import with flexible column mapping. Supported fields and data formats.'],
        ['url' => '/admin/docs/kb',              'icon' => 'bi-book',            'color' => '#9333ea', 'bg' => '#fdf4ff',
         'title' => 'Knowledge Base',
         'desc'  => 'Creating categories, folders and articles. Markdown formatting and publishing.'],
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
