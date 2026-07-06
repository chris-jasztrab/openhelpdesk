<?php
$agentHelpNav = [
    ['url' => '/agent/help',                  'label' => 'Overview',             'icon' => 'bi-house'],
    ['url' => '/agent/help/dashboard',        'label' => 'Dashboard',            'icon' => 'bi-speedometer2'],
    ['url' => '/agent/help/wallboard',        'label' => 'Live Wallboard',       'icon' => 'bi-display'],
    ['url' => '/agent/help/ticket-list',      'label' => 'Ticket List & Filters','icon' => 'bi-list-ul'],
    ['url' => '/agent/help/kanban',           'label' => 'Kanban Board',         'icon' => 'bi-kanban'],
    ['url' => '/agent/help/working-tickets',  'label' => 'Working on Tickets',   'icon' => 'bi-ticket-detailed'],
    ['url' => '/agent/help/sla',              'label' => 'SLAs & Response Times','icon' => 'bi-stopwatch'],
    ['url' => '/agent/help/floor',            'label' => 'Floor Mode',           'icon' => 'bi-grid-1x2'],
    ['url' => '/agent/help/canned-responses', 'label' => 'Canned Responses',     'icon' => 'bi-chat-square-text'],
];
$cp = currentPath();
?>
<div class="card border-0 shadow-sm mb-4" style="position:sticky;top:76px;">
    <div class="card-body p-2">
        <div class="small fw-semibold text-muted px-2 py-1 mb-1" style="text-transform:uppercase;letter-spacing:.05em;font-size:.7rem;">
            Agent Help
        </div>
        <?php foreach ($agentHelpNav as $item): ?>
        <a href="<?= e($item['url']) ?>"
           class="d-flex align-items-center gap-2 px-2 py-2 rounded text-decoration-none small <?= $cp === $item['url'] ? 'fw-semibold' : 'text-muted' ?>"
           style="<?= $cp === $item['url'] ? 'background:var(--bs-secondary-bg);color:var(--ld-primary)!important;' : '' ?>">
            <i class="bi <?= e($item['icon']) ?>"></i>
            <?= e($item['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
