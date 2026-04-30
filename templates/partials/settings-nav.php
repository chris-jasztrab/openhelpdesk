<?php
$settingsNavGroups = [
    'Email' => [
        ['label' => 'Email / SMTP',        'url' => '/admin/settings',                      'icon' => 'bi-envelope',       'exact' => true,
         'keywords' => 'smtp mail outgoing graph inbound reply-to azure office365 m365'],
        ['label' => 'Email Templates',     'url' => '/admin/settings/email-templates',      'icon' => 'bi-pencil-square',
         'keywords' => 'template message body subject placeholder'],
        ['label' => 'Email Notifications', 'url' => '/admin/settings/email-notifications',  'icon' => 'bi-bell',
         'keywords' => 'notify alert subscribe trigger event'],
    ],
    'Scheduling' => [
        ['label' => 'Business Hours', 'url' => '/admin/settings/business-hours', 'icon' => 'bi-clock',
         'keywords' => 'open close shift workday timezone'],
        ['label' => 'Holidays',       'url' => '/admin/settings/holidays',       'icon' => 'bi-calendar-x',
         'keywords' => 'closed vacation off-day stat holiday'],
        ['label' => 'SLA Policies',   'url' => '/admin/settings/sla-policies',   'icon' => 'bi-stopwatch',
         'keywords' => 'service level agreement response resolution deadline breach'],
    ],
    'Organization' => [
        ['label' => label('location.plural'), 'url' => '/admin/locations',  'icon' => 'bi-geo-alt',
         'keywords' => 'site branch office department location'],
        ['label' => 'Priorities',             'url' => '/admin/priorities', 'icon' => 'bi-flag',
         'keywords' => 'urgency severity high low critical'],
        ['label' => 'Ticket Types',           'url' => '/admin/types',      'icon' => 'bi-tags',
         'keywords' => 'category classification kind incident request'],
        ['label' => 'Groups',                 'url' => '/admin/groups',     'icon' => 'bi-people-fill',
         'keywords' => 'team queue assignee department'],
        ['label' => 'Agent Skills',           'url' => '/admin/skills',     'icon' => 'bi-mortarboard',
         'keywords' => 'skill expertise routing assignment competency'],
    ],
    'Security' => [
        ['label' => 'SSO / Microsoft 365', 'url' => '/admin/settings/sso', 'icon' => 'bi-shield-lock',
         'keywords' => 'sso saml oauth login azure entra m365 office365 single sign on'],
    ],
    'Customization' => [
        ['label' => 'Branding',         'url' => '/admin/settings/branding',         'icon' => 'bi-palette',
         'keywords' => 'logo color theme favicon appearance brand'],
        ['label' => 'Labels',           'url' => '/admin/settings/labels',           'icon' => 'bi-translate',
         'keywords' => 'rename text terminology language localization'],
        ['label' => 'Tags',             'url' => '/admin/settings/tags',             'icon' => 'bi-hash',
         'keywords' => 'tag keyword marker'],
        ['label' => 'Canned Responses', 'url' => '/admin/settings/canned-responses', 'icon' => 'bi-chat-square-text',
         'keywords' => 'macro snippet template reply boilerplate'],
        ['label' => 'CSAT Surveys',     'url' => '/admin/settings/csat',             'icon' => 'bi-star',
         'keywords' => 'satisfaction feedback rating score survey'],
    ],
    'Automation' => [
        ['label' => 'Automations',        'url' => '/admin/settings/automations',        'icon' => 'bi-lightning',
         'keywords' => 'rule trigger workflow if-then condition action'],
        ['label' => 'AI Classification',  'url' => '/admin/settings/ai',                 'icon' => 'bi-cpu',
         'keywords' => 'anthropic claude openai gpt llm machine learning routing auto-tag'],
        ['label' => 'Escalation Paths',   'url' => '/admin/settings/escalation-paths',   'icon' => 'bi-signpost-split',
         'keywords' => 'escalate path tier route handoff'],
        ['label' => 'Escalation Rules',   'url' => '/admin/settings/escalations',        'icon' => 'bi-alarm',
         'keywords' => 'escalate rule deadline overdue trigger'],
        ['label' => 'Stale Tickets',      'url' => '/admin/settings/stale-tickets',      'icon' => 'bi-hourglass-split',
         'keywords' => 'idle inactive aging close auto-close'],
        ['label' => 'Scheduled Reports',  'url' => '/admin/settings/scheduled-reports',  'icon' => 'bi-calendar-check',
         'keywords' => 'recurring report email digest weekly monthly'],
        ['label' => 'Cron Jobs',          'url' => '/admin/settings/cron-jobs',          'icon' => 'bi-clock-history',
         'keywords' => 'cron schedule background task job'],
    ],
    'Data' => [
        ['label' => 'Import Tickets', 'url' => '/admin/settings/import',       'icon' => 'bi-cloud-upload',
         'keywords' => 'import csv migrate upload tickets'],
        ['label' => 'Import Users',   'url' => '/admin/settings/import-users', 'icon' => 'bi-person-plus',
         'keywords' => 'import csv migrate upload users contacts portal'],
        ['label' => 'Import KB',      'url' => '/admin/settings/import-kb',    'icon' => 'bi-book',
         'keywords' => 'import knowledge base articles documentation'],
        ['label' => 'Backup',         'url' => '/admin/settings/backup',       'icon' => 'bi-archive',
         'keywords' => 'backup restore export database snapshot'],
    ],
    'System' => [
        ['label' => 'Danger Zone', 'url' => '/admin/settings/danger-zone', 'icon' => 'bi-exclamation-triangle',
         'keywords' => 'reset wipe purge delete-all destructive maintenance'],
    ],
];
$currentPath = currentPath();
?>
<div class="d-flex gap-4 align-items-start">
    <div class="flex-shrink-0" style="width:190px;">
        <div class="card border-0 shadow-sm" style="position:sticky;top:1rem;">
            <div class="card-body p-2">
                <div class="px-1 pb-2">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute"
                           style="left:.55rem;top:50%;transform:translateY(-50%);font-size:.75rem;color:var(--bs-secondary-color);pointer-events:none;"></i>
                        <input type="search" id="settingsNavSearch"
                               class="form-control form-control-sm"
                               placeholder="Search settings…"
                               aria-label="Search settings"
                               autocomplete="off"
                               style="padding-left:1.75rem;font-size:.8rem;">
                    </div>
                    <div id="settingsNavNoMatch" class="text-muted small px-2 pt-2" style="display:none;font-size:.75rem;">
                        No settings match.
                    </div>
                </div>
                <div id="settingsNavList">
                <?php foreach ($settingsNavGroups as $groupLabel => $items): ?>
                <div class="mb-2 settings-nav-group" data-group-label="<?= e(strtolower($groupLabel)) ?>">
                    <div class="px-2 py-1 text-uppercase fw-semibold settings-nav-group-label"
                         style="font-size:0.65rem;letter-spacing:.08em;color:var(--bs-secondary-color);">
                        <?= e($groupLabel) ?>
                    </div>
                    <?php foreach ($items as $item): ?>
                    <?php
                        $isActive    = $currentPath === $item['url'] || (!($item['exact'] ?? false) && str_starts_with($currentPath, $item['url'] . '/'));
                        $searchBlob  = strtolower($item['label'] . ' ' . $groupLabel . ' ' . ($item['keywords'] ?? ''));
                    ?>
                    <a class="d-flex align-items-center nav-link py-1 px-2 small rounded mb-0 settings-nav-item <?= $isActive ? 'fw-semibold' : 'text-muted' ?>"
                       href="<?= e($item['url']) ?>"
                       data-search="<?= e($searchBlob) ?>"
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
    </div>
    <div class="flex-grow-1 min-w-0">
