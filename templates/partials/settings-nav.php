<?php
$settingsNavGroups = [
    'Email' => [
        ['label' => 'Email / SMTP',        'url' => '/admin/settings',                      'icon' => 'bi-envelope'],
        ['label' => 'Email Templates',     'url' => '/admin/settings/email-templates',      'icon' => 'bi-pencil-square'],
        ['label' => 'Email Notifications', 'url' => '/admin/settings/email-notifications',  'icon' => 'bi-bell'],
    ],
    'Scheduling' => [
        ['label' => 'Business Hours', 'url' => '/admin/settings/business-hours', 'icon' => 'bi-clock'],
        ['label' => 'Holidays',       'url' => '/admin/settings/holidays',       'icon' => 'bi-calendar-x'],
        ['label' => 'SLA Policies',   'url' => '/admin/settings/sla-policies',   'icon' => 'bi-stopwatch'],
    ],
    'Organization' => [
        ['label' => label('location.plural'), 'url' => '/admin/locations',  'icon' => 'bi-geo-alt'],
        ['label' => 'Priorities',             'url' => '/admin/priorities', 'icon' => 'bi-flag'],
        ['label' => 'Ticket Types',           'url' => '/admin/types',      'icon' => 'bi-tags'],
        ['label' => 'Groups',                 'url' => '/admin/groups',     'icon' => 'bi-people-fill'],
    ],
    'Security' => [
        ['label' => 'SSO / Microsoft 365', 'url' => '/admin/settings/sso', 'icon' => 'bi-shield-lock'],
    ],
    'Customization' => [
        ['label' => 'Branding',         'url' => '/admin/settings/branding',         'icon' => 'bi-palette'],
        ['label' => 'Labels',           'url' => '/admin/settings/labels',           'icon' => 'bi-translate'],
        ['label' => 'Tags',             'url' => '/admin/settings/tags',             'icon' => 'bi-hash'],
        ['label' => 'Canned Responses', 'url' => '/admin/settings/canned-responses', 'icon' => 'bi-chat-square-text'],
        ['label' => 'CSAT Surveys',     'url' => '/admin/settings/csat',             'icon' => 'bi-star'],
    ],
    'Automation' => [
        ['label' => 'Automations',        'url' => '/admin/settings/automations',        'icon' => 'bi-lightning'],
        ['label' => 'Escalations',        'url' => '/admin/settings/escalations',        'icon' => 'bi-alarm'],
        ['label' => 'Scheduled Reports',  'url' => '/admin/settings/scheduled-reports',  'icon' => 'bi-calendar-check'],
        ['label' => 'Cron Jobs',          'url' => '/admin/settings/cron-jobs',          'icon' => 'bi-clock-history'],
    ],
    'Data' => [
        ['label' => 'Import Tickets', 'url' => '/admin/settings/import',       'icon' => 'bi-cloud-upload'],
        ['label' => 'Import Users',   'url' => '/admin/settings/import-users', 'icon' => 'bi-person-plus'],
        ['label' => 'Import KB',      'url' => '/admin/settings/import-kb',    'icon' => 'bi-book'],
        ['label' => 'Backup',         'url' => '/admin/settings/backup',       'icon' => 'bi-archive'],
    ],
    'System' => [
        ['label' => 'Danger Zone', 'url' => '/admin/settings/danger-zone', 'icon' => 'bi-exclamation-triangle'],
    ],
];
$currentPath = currentPath();
?>
<div class="d-flex gap-4 align-items-start">
    <div class="flex-shrink-0" style="width:190px;">
        <div class="card border-0 shadow-sm" style="position:sticky;top:1rem;">
            <div class="card-body p-2">
                <?php foreach ($settingsNavGroups as $groupLabel => $items): ?>
                <div class="mb-2">
                    <div class="px-2 py-1 text-uppercase fw-semibold"
                         style="font-size:0.65rem;letter-spacing:.08em;color:var(--bs-secondary-color);">
                        <?= e($groupLabel) ?>
                    </div>
                    <?php foreach ($items as $item): ?>
                    <?php $isActive = $currentPath === $item['url'] || str_starts_with($currentPath, $item['url'] . '/'); ?>
                    <a class="d-flex align-items-center nav-link py-1 px-2 small rounded mb-0 <?= $isActive ? 'fw-semibold' : 'text-muted' ?>"
                       href="<?= e($item['url']) ?>"
                       style="<?= $isActive ? 'background:var(--bs-secondary-bg);color:var(--ld-primary)!important;' : '' ?>">
                        <i class="bi <?= e($item['icon']) ?> me-2 flex-shrink-0" style="width:14px;"></i>
                        <?= e($item['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="flex-grow-1 min-w-0">
