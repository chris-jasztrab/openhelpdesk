<?php
$settingsNav = [
    ['label' => 'Email / SMTP',      'url' => '/admin/settings',                       'icon' => 'bi-envelope'],
    ['label' => 'Email Templates',   'url' => '/admin/settings/email-templates',        'icon' => 'bi-pencil-square'],
    ['label' => 'Business Hours',    'url' => '/admin/settings/business-hours',         'icon' => 'bi-clock'],
    ['label' => 'SLA Policies',   'url' => '/admin/settings/sla-policies',   'icon' => 'bi-stopwatch'],
    ['label' => 'Locations',      'url' => '/admin/locations',               'icon' => 'bi-geo-alt'],
    ['label' => 'Priorities',     'url' => '/admin/priorities',              'icon' => 'bi-flag'],
    ['label' => 'Ticket Types',   'url' => '/admin/types',                   'icon' => 'bi-tags'],
    ['label' => 'Groups',         'url' => '/admin/groups',                  'icon' => 'bi-people-fill'],
    ['label' => 'Branding',       'url' => '/admin/settings/branding',       'icon' => 'bi-palette'],
    ['label' => 'Automations',    'url' => '/admin/settings/automations',    'icon' => 'bi-lightning'],
    ['label' => 'Import Tickets', 'url' => '/admin/settings/import',         'icon' => 'bi-cloud-upload'],
    ['label' => 'Import KB',      'url' => '/admin/settings/import-kb',      'icon' => 'bi-book'],
    ['label' => 'Backup',        'url' => '/admin/settings/backup',         'icon' => 'bi-archive'],
    ['label' => 'Danger Zone',   'url' => '/admin/settings/danger-zone',    'icon' => 'bi-exclamation-triangle'],
];
$currentPath = currentPath();
?>
<ul class="nav nav-tabs mb-4">
    <?php foreach ($settingsNav as $item): ?>
    <li class="nav-item">
        <a class="nav-link <?= ($currentPath === $item['url'] || str_starts_with($currentPath, $item['url'] . '/')) ? 'active' : '' ?>"
           href="<?= e($item['url']) ?>">
            <i class="bi <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
