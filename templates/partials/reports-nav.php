<?php
$reportsNav = [
    ['label' => 'Overview',           'url' => '/admin/reports',                    'icon' => 'bi-grid'],
    ['label' => 'Agent Performance',  'url' => '/admin/reports/agent-performance',  'icon' => 'bi-person-badge'],
    ['label' => 'Response Times',     'url' => '/admin/reports/response-times',     'icon' => 'bi-clock-history'],
    ['label' => 'SLA Compliance',     'url' => '/admin/reports/sla',               'icon' => 'bi-stopwatch'],
    ['label' => 'Unresolved Tickets', 'url' => '/admin/reports/unresolved',        'icon' => 'bi-exclamation-triangle'],
    ['label' => 'Ticket Volume',      'url' => '/admin/reports/ticket-volume',     'icon' => 'bi-graph-up'],
    ['label' => 'Ticket Lifecycle',   'url' => '/admin/reports/lifecycle',         'icon' => 'bi-arrow-repeat'],
    ['label' => 'By Location',        'url' => '/admin/reports/location',          'icon' => 'bi-geo-alt'],
    ['label' => 'Satisfaction',       'url' => '/admin/reports/csat',              'icon' => 'bi-star'],
];
$currentPath = currentPath();
?>
<ul class="nav nav-tabs mb-4">
    <?php foreach ($reportsNav as $item): ?>
    <li class="nav-item">
        <a class="nav-link <?= $currentPath === $item['url'] ? 'active' : '' ?>"
           href="<?= e($item['url']) ?>">
            <i class="bi <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>
