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
    ['label' => 'Escalations',       'url' => '/admin/settings/escalations',       'icon' => 'bi-alarm'],
    ['label' => 'Scheduled Reports', 'url' => '/admin/settings/scheduled-reports', 'icon' => 'bi-envelope-paper'],
    ['label' => 'Import Tickets', 'url' => '/admin/settings/import',         'icon' => 'bi-cloud-upload'],
    ['label' => 'Import KB',      'url' => '/admin/settings/import-kb',      'icon' => 'bi-book'],
    ['label' => 'CSAT Surveys',  'url' => '/admin/settings/csat',           'icon' => 'bi-star'],
    ['label' => 'Backup',        'url' => '/admin/settings/backup',         'icon' => 'bi-archive'],
    ['label' => 'Danger Zone',   'url' => '/admin/settings/danger-zone',    'icon' => 'bi-exclamation-triangle'],
];
$currentPath = currentPath();
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2 px-3">
        <nav class="nav flex-wrap gap-1">
            <?php foreach ($settingsNav as $item): ?>
            <?php $isActive = $currentPath === $item['url'] || str_starts_with($currentPath, $item['url'] . '/'); ?>
            <a class="nav-link py-1 px-2 small rounded <?= $isActive ? 'fw-semibold' : 'text-muted' ?>"
               href="<?= e($item['url']) ?>"
               style="<?= $isActive ? 'background:var(--bs-secondary-bg);color:var(--ld-primary)!important;' : '' ?>">
                <i class="bi <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
