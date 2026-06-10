<?php
$settingsNavGroups = [
    'Operations' => [
        ['label' => 'Status Banners', 'url' => '/agent/banners', 'icon' => 'bi-megaphone',
         'keywords' => 'status banner incident notice outage alert announcement broadcast maintenance downtime wifi network down'],
    ],
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
    'Users & Access' => [
        ['label' => label('nav.users'),       'url' => '/admin/users',      'icon' => 'bi-people',
         'keywords' => 'user account agent staff requester contact people manage create edit'],
        ['label' => 'Permission Levels',      'url' => '/admin/roles',      'icon' => 'bi-shield-lock',
         'keywords' => 'role permission level access agent admin power user custom capability rbac'],
    ],
    'Organization' => [
        ['label' => 'Organization Type',      'url' => '/admin/settings/organization', 'icon' => 'bi-building',
         'keywords' => 'organization type sector industry library education government corporate non-profit healthcare'],
        ['label' => label('location.plural'), 'url' => '/admin/locations',  'icon' => 'bi-geo-alt',
         'keywords' => 'site branch office department location'],
        ['label' => 'Priorities',             'url' => '/admin/priorities', 'icon' => 'bi-flag',
         'keywords' => 'urgency severity high low critical'],
        ['label' => 'Ticket Statuses',        'url' => '/admin/settings/ticket-statuses', 'icon' => 'bi-circle-half',
         'keywords' => 'status workflow state open progress resolved closed pending waiting bucket color'],
        ['label' => 'Ticket Types',           'url' => '/admin/types',      'icon' => 'bi-tags',
         'keywords' => 'category classification kind incident request'],
        ['label' => 'Ticket Forms',           'url' => '/admin/workflows/ticket-fields', 'icon' => 'bi-input-cursor-text',
         'keywords' => 'form field layout builder custom field workflow ticket fields required'],
        ['label' => 'Type Settings Matrix',   'url' => '/admin/types/matrix', 'icon' => 'bi-grid-3x3-gap',
         'keywords' => 'matrix overview chart confidential ai routing duplicate threshold compare summary print'],
        ['label' => 'Groups',                 'url' => '/admin/groups',     'icon' => 'bi-people-fill',
         'keywords' => 'team queue assignee department'],
        ['label' => 'Ticket Routing',         'url' => '/admin/settings/ticket-routing', 'icon' => 'bi-diagram-3',
         'keywords' => 'routing default group triage fallback unrouted no group queue catch-all agent assign any'],
        ['label' => 'Agent Skills',           'url' => '/admin/skills',     'icon' => 'bi-mortarboard',
         'keywords' => 'skill expertise routing assignment competency'],
    ],
    'Knowledge Base' => [
        ['label' => 'Manage Articles',        'url' => '/admin/kb/articles',   'icon' => 'bi-journal-text',
         'keywords' => 'knowledge base kb article documentation help guide write publish'],
        ['label' => 'Categories & Folders',   'url' => '/admin/kb/categories', 'icon' => 'bi-folder',
         'keywords' => 'knowledge base kb category folder structure organize section'],
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
        ['label' => 'Ticket Templates', 'url' => '/admin/ticket-templates',          'icon' => 'bi-file-earmark-text',
         'keywords' => 'ticket template prefill boilerplate new ticket starter'],
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
        ['label' => 'Recurring Tickets',  'url' => '/admin/recurring-tickets',           'icon' => 'bi-arrow-clockwise',
         'keywords' => 'recurring repeat schedule cron periodic ticket generate'],
        ['label' => 'Scheduled Reports',  'url' => '/admin/settings/scheduled-reports',  'icon' => 'bi-calendar-check',
         'keywords' => 'recurring report email digest weekly monthly'],
        ['label' => 'Cron Jobs',          'url' => '/admin/settings/cron-jobs',          'icon' => 'bi-clock-history',
         'keywords' => 'cron schedule background task job'],
    ],
    'Reports' => [
        ['label' => label('nav.reports'), 'url' => '/admin/reports', 'icon' => 'bi-bar-chart',
         'keywords' => 'report analytics metrics dashboard insight statistics chart export'],
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
        ['label' => label('nav.audit_log'), 'url' => '/admin/audit-log', 'icon' => 'bi-shield-check',
         'keywords' => 'audit log history activity trail who changed event security'],
        ['label' => 'Danger Zone', 'url' => '/admin/settings/danger-zone', 'icon' => 'bi-exclamation-triangle',
         'keywords' => 'reset wipe purge delete-all destructive maintenance'],
    ],
];
// Hide nav items the current role can't open (admins see everything). The
// URL→permission map mirrors the route gates; longest-prefix wins.
$settingsNavPerm = static function (string $url): string {
    $map = [
        '/agent/banners'                    => '@staff',
        '/admin/settings/sla'               => 'sla.manage',
        '/admin/settings/csat'              => 'csat.manage',
        '/admin/settings/ai'                => 'ai.manage',
        '/admin/settings/automations'       => 'automations.manage',
        '/admin/settings/escalation-paths'  => 'automations.manage',
        '/admin/settings/escalations'       => 'automations.manage',
        '/admin/settings/stale-tickets'     => 'automations.manage',
        '/admin/settings/scheduled-reports' => 'automations.manage',
        '/admin/settings/cron-jobs'         => 'automations.manage',
        '/admin/settings/ticket-statuses'   => 'workflows.manage',
        '/admin/settings/import-users'      => 'users.manage',
        '/admin/settings/import-kb'         => 'import.manage',
        '/admin/settings/import'            => 'import.manage',
        '/admin/settings/backup'            => 'import.manage',
        '/admin/settings/danger-zone'       => '@admin',
        '/admin/roles'                      => '@admin',
        '/admin/settings'                   => 'settings.manage',
        '/admin/locations'                  => 'locations.manage',
        '/admin/priorities'                 => 'priorities.manage',
        '/admin/types'                      => 'workflows.manage',
        '/admin/workflows'                  => 'workflows.manage',
        '/admin/groups'                     => 'groups.manage',
        '/admin/skills'                     => 'skills.manage',
        '/admin/users'                      => 'users.manage',
        '/admin/kb/articles'                => 'kb.articles.manage',
        '/admin/kb/categories'              => 'kb.structure.manage',
        '/admin/kb/folders'                 => 'kb.structure.manage',
        '/admin/ticket-templates'           => 'ticket_templates.manage',
        '/admin/recurring-tickets'          => 'recurring_tickets.manage',
        '/admin/reports'                    => 'reports.view',
        '/admin/audit-log'                  => 'audit.view',
    ];
    $best = 'settings.manage';
    $bestLen = -1;
    foreach ($map as $prefix => $perm) {
        if (($url === $prefix || str_starts_with($url, $prefix . '/') || str_starts_with($url, $prefix . '-'))
            && strlen($prefix) > $bestLen) {
            $best = $perm;
            $bestLen = strlen($prefix);
        }
    }
    return $best;
};
foreach ($settingsNavGroups as $grpKey => $grpItems) {
    $settingsNavGroups[$grpKey] = array_values(array_filter($grpItems, static function (array $it) use ($settingsNavPerm) {
        $perm = $settingsNavPerm($it['url']);
        if ($perm === '@admin') return Auth::isAdmin();
        if ($perm === '@staff') return Auth::isStaff();
        return Auth::can($perm);
    }));
    if (empty($settingsNavGroups[$grpKey])) {
        unset($settingsNavGroups[$grpKey]);
    }
}
$currentPath          = currentPath();
$settingsSearchIndex  = require ROOT_DIR . '/config/settings_index.php';

// Pick a single active URL via longest-prefix match, so a sub-page like
// /admin/types/matrix doesn't also highlight its parent /admin/types.
$activeNavUrl = '';
foreach ($settingsNavGroups as $_grp) {
    foreach ($_grp as $_it) {
        $url   = $_it['url'];
        $exact = $_it['exact'] ?? false;
        $hit   = $currentPath === $url || (!$exact && str_starts_with($currentPath, $url . '/'));
        if ($hit && strlen($url) > strlen($activeNavUrl)) {
            $activeNavUrl = $url;
        }
    }
}
unset($_grp, $_it);
?>
<div class="d-flex gap-4 align-items-start">
    <div class="flex-shrink-0" style="width:190px;">
        <div class="card border-0 shadow-sm" style="position:sticky;top:1rem;">
            <div class="card-body p-2">
                <div class="px-1 pb-2 position-relative">
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

                    <!-- Chrome-style results dropdown for individual settings (floats over page content) -->
                    <div id="settingsSearchResults" class="card border shadow-lg"
                         role="listbox" aria-label="Settings search results"
                         style="display:none;position:absolute;top:calc(100% + 4px);left:.25rem;z-index:1050;width:380px;max-height:60vh;overflow-y:auto;font-size:.825rem;"></div>

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
                        $isActive    = $item['url'] === $activeNavUrl;
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
