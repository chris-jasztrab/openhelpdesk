<!DOCTYPE html>
<?php $userTheme = Auth::check() ? getSetting('ui_theme:' . Auth::id(), 'light') : 'light'; ?>
<html lang="en" data-bs-theme="<?= e($userTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &ndash; <?= e(getSetting('branding_app_name', 'LocalDesk')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ld-primary: <?= e(getSetting('branding_primary_color', '#4f46e5')) ?>;
            --ld-primary-hover: <?= e(getSetting('branding_primary_hover', '#4338ca')) ?>;
            --ld-sidebar-width: 64px;
            --ld-navbar-height: 56px;
            --ld-timeline-note-bg:      <?= e(getSetting('branding_timeline_note_bg',      '#fefce8')) ?>;
            --ld-timeline-note-accent:  <?= e(getSetting('branding_timeline_note_accent',  '#ca8a04')) ?>;
            --ld-timeline-system-bg:    <?= e(getSetting('branding_timeline_system_bg',    '#eff6ff')) ?>;
            --ld-timeline-system-accent:<?= e(getSetting('branding_timeline_system_accent','#3b82f6')) ?>;
        }
        .ld-timeline-note {
            background-color: var(--ld-timeline-note-bg) !important;
            border-left: 3px solid var(--ld-timeline-note-accent) !important;
        }
        .ld-timeline-system {
            background-color: var(--ld-timeline-system-bg) !important;
            border-left: 3px solid var(--ld-timeline-system-accent) !important;
        }
        body { background-color: #f1f5f9; overflow-x: hidden; }
        .navbar { background: linear-gradient(135deg, <?= e(getSetting('branding_navbar_start', '#1e1b4b')) ?> 0%, <?= e(getSetting('branding_navbar_end', '#312e81')) ?> 100%) !important; }
        .navbar-brand { font-weight: 700; letter-spacing: -0.5px; }

        /* Sidebar – icon-only */
        .sidebar {
            width: var(--ld-sidebar-width);
            min-height: calc(100vh - var(--ld-navbar-height));
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            position: fixed;
            top: var(--ld-navbar-height);
            left: 0;
            overflow-y: auto;
            padding-top: .75rem;
            z-index: 100;
        }
        .sidebar .nav-link {
            color: #475569;
            padding: .75rem 0;
            font-size: 1.35rem;
            border-left: 3px solid transparent;
            transition: all .15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar .nav-link:hover {
            background-color: #f8fafc;
            color: var(--ld-primary);
        }
        .sidebar .nav-link.active {
            background-color: #eef2ff;
            color: var(--ld-primary);
            border-left-color: var(--ld-primary);
        }

        /* Main content */
        .main-content {
            margin-left: var(--ld-sidebar-width);
            padding: 1.5rem 2rem;
            min-height: calc(100vh - var(--ld-navbar-height));
        }

        /* Cards */
        .stat-card {
            border: none; border-radius: .75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .stat-card .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
        }
        @keyframes ld-bell-ring {
            0%   { transform: rotate(0); }
            10%  { transform: rotate(14deg); }
            20%  { transform: rotate(-14deg); }
            30%  { transform: rotate(10deg); }
            40%  { transform: rotate(-10deg); }
            50%  { transform: rotate(6deg); }
            60%  { transform: rotate(-4deg); }
            70%  { transform: rotate(2deg); }
            80%  { transform: rotate(0); }
            100% { transform: rotate(0); }
        }
        @keyframes ld-bell-glow {
            0%, 100% { filter: drop-shadow(0 0 0 transparent); }
            50%      { filter: drop-shadow(0 0 6px rgba(255,220,40,.8)); }
        }
        .ld-bell-ring .bi-bell { animation: ld-bell-ring .8s ease; transform-origin: top center; }
        .ld-bell-active .bi-bell { color: #fbbf24 !important; animation: ld-bell-glow 2s ease-in-out infinite; }

        /* Global Search */
        #ld-search-input::placeholder { color: rgba(255,255,255,.45); }
        #ld-search-input:focus { background: rgba(255,255,255,.18) !important; }
        .ld-search-tab {
            font-size: .75rem; color: #64748b; border-radius: .375rem .375rem 0 0;
            border: none; background: none; border-bottom: 2px solid transparent; padding-bottom: .4rem;
        }
        .ld-search-tab:hover { color: #1e293b; }
        .ld-search-tab.active { color: var(--ld-primary); border-bottom-color: var(--ld-primary); font-weight: 600; }
        .ld-search-item { transition: background .1s ease; }
        .ld-search-item:hover { background: #f1f5f9; }
        .ld-search-group + .ld-search-group { border-top: 1px solid #e2e8f0; margin-top: .25rem; padding-top: .25rem; }

        /* Mention autocomplete */
        .mention-dropdown {
            position: absolute; z-index: 1050; background: #fff; border: 1px solid #e2e8f0;
            border-radius: .5rem; box-shadow: 0 4px 16px rgba(0,0,0,.12); max-height: 240px;
            overflow-y: auto; min-width: 220px;
        }
        .mention-dropdown .mention-item {
            padding: .5rem .75rem; cursor: pointer; display: flex; align-items: center; gap: .5rem;
        }
        .mention-dropdown .mention-item:hover,
        .mention-dropdown .mention-item.active { background: #eef2ff; }
        .mention-dropdown .mention-item .mention-name { font-weight: 500; font-size: .875rem; }
        .mention-dropdown .mention-hint { padding: .5rem .75rem; font-size: .8rem; color: #94a3b8; }

        /* Dark mode overrides */
        [data-bs-theme="dark"] body { background-color: #1a1d21; }
        [data-bs-theme="dark"] .sidebar { background: #212529; border-right-color: #373b3e; }
        [data-bs-theme="dark"] .sidebar .nav-link { color: #adb5bd; }
        [data-bs-theme="dark"] .sidebar .nav-link:hover { background-color: #2b3035; color: var(--ld-primary); }
        [data-bs-theme="dark"] .sidebar .nav-link.active { background-color: #2b3035; }
        [data-bs-theme="dark"] .stat-card { box-shadow: 0 1px 3px rgba(0,0,0,.3); }
        [data-bs-theme="dark"] .ld-search-tab { color: #adb5bd; }
        [data-bs-theme="dark"] .ld-search-tab:hover { color: #dee2e6; }
        [data-bs-theme="dark"] .ld-search-item { color: var(--bs-body-color) !important; }
        [data-bs-theme="dark"] .ld-search-item:hover { background: #2b3035; }
        [data-bs-theme="dark"] .ld-search-group + .ld-search-group { border-top-color: #373b3e; }
        [data-bs-theme="dark"] .mention-dropdown { background: #212529; border-color: #373b3e; }
        [data-bs-theme="dark"] .mention-dropdown .mention-item:hover,
        [data-bs-theme="dark"] .mention-dropdown .mention-item.active { background: #2b3035; }
        [data-bs-theme="dark"] .mention-dropdown .mention-hint { color: #6c757d; }
        [data-bs-theme="dark"] .table-light { --bs-table-bg: #2b3035; --bs-table-color: #dee2e6; }
        [data-bs-theme="dark"] .shadow-sm { box-shadow: 0 .125rem .25rem rgba(0,0,0,.3) !important; }
        /* .text-dark is not theme-adaptive in Bootstrap 5.3 — override to readable body color */
        [data-bs-theme="dark"] .text-dark { color: var(--bs-body-color) !important; }
        /* bg-white is not theme-adaptive — fix card headers, dropdowns, and other bg-white elements */
        [data-bs-theme="dark"] .bg-white { background-color: var(--bs-secondary-bg) !important; }
        /* Ensure form inputs don't inherit the bg-white override (Bootstrap handles them natively) */
        [data-bs-theme="dark"] input.bg-white,
        [data-bs-theme="dark"] textarea.bg-white,
        [data-bs-theme="dark"] select.bg-white { background-color: var(--bs-body-bg) !important; }
        /* Tag/KB badges using bg-light need a readable border in dark mode */
        [data-bs-theme="dark"] .badge.bg-light { border-color: #495057 !important; }
        /* Hardcoded light backgrounds in filter/stat areas */
        [data-bs-theme="dark"] .bg-f8fafc,
        [data-bs-theme="dark"] [style*="background:#f8fafc"],
        [data-bs-theme="dark"] [style*="background-color:#f8fafc"] { background-color: var(--bs-tertiary-bg) !important; }

        /* Filter slide-out panel */
        .filter-panel {
            position: fixed;
            left: 0;
            top: var(--ld-navbar-height);
            bottom: 0;
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            z-index: 150;
            display: flex;
            flex-direction: column;
            transform: translateX(-100%);
            transition: transform .25s ease;
        }
        .filter-panel.open { transform: translateX(var(--ld-sidebar-width)); }
        .filter-panel-header {
            padding: .875rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .filter-panel-body {
            padding: 1rem;
            overflow-y: auto;
            flex: 1;
        }
        .filter-panel-backdrop {
            position: fixed;
            left: var(--ld-sidebar-width);
            top: var(--ld-navbar-height);
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,.2);
            z-index: 149;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease;
        }
        .filter-panel-backdrop.open { opacity: 1; pointer-events: auto; }
        [data-bs-theme="dark"] .filter-panel { background: #212529; border-right-color: #373b3e; }
        [data-bs-theme="dark"] .filter-panel-header { border-bottom-color: #373b3e; }

        /* Tour resume pill */
        #ld-tour-resume {
            position: fixed;
            top: calc(var(--ld-navbar-height) + 12px);
            right: 16px;
            z-index: 1039;
            background: var(--ld-primary);
            color: #fff;
            border-radius: 50px;
            padding: 6px 14px 6px 8px;
            font-size: .8rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            box-shadow: 0 4px 14px rgba(79,70,229,.45);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        #ld-tour-resume:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(79,70,229,.55);
        }
        .ld-tour-pulse-wrap {
            position: relative;
            width: 10px;
            height: 10px;
            flex-shrink: 0;
        }
        .ld-tour-pulse-wrap::before,
        .ld-tour-pulse-wrap::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: #fff;
        }
        .ld-tour-pulse-wrap::before {
            opacity: .35;
            animation: ld-tour-ping 1.4s cubic-bezier(0,0,.2,1) infinite;
        }
        .ld-tour-pulse-wrap::after {
            transform: scale(.7);
        }
        @keyframes ld-tour-ping {
            0%   { transform: scale(1);   opacity: .35; }
            75%, 100% { transform: scale(2.2); opacity: 0; }
        }
    </style>
</head>
<body>
    <?php require ROOT_DIR . '/templates/partials/navbar.php'; ?>

    <?php if (Auth::role() === 'admin' && getSetting('show_onboarding', '0') === '1'): ?>
    <a href="/admin?tour=1" id="ld-tour-resume" title="Continue setup tour">
        <span class="ld-tour-pulse-wrap"></span>
        <span id="ld-tour-resume-label">Setup Tour</span>
    </a>
    <script>
    (function () {
        var step = parseInt(localStorage.getItem('ld_tour_step') || '0', 10);
        if (step > 1) {
            document.getElementById('ld-tour-resume-label').textContent = 'Resume Tour (step ' + step + '/6)';
            document.getElementById('ld-tour-resume').href = '/admin?tour=1&step=' + step;
        }
    })();
    </script>
    <?php endif; ?>

    <!-- Sidebar -->
    <aside class="sidebar">
        <nav class="nav flex-column">
            <?php foreach ($sidebarItems as $item): ?>
            <a class="nav-link <?= ($item['active'] ?? false) ? 'active' : '' ?>"
               href="<?= e($item['url']) ?>"
               title="<?= e($item['label']) ?>"
               data-bs-toggle="tooltip" data-bs-placement="right">
                <i class="bi <?= e($item['icon']) ?>"></i>
            </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Main content -->
    <div class="main-content">
        <?php if (!empty($breadcrumbs)): ?>
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <?php if (isset($crumb['url'])): ?>
                        <li class="breadcrumb-item"><a href="<?= e($crumb['url']) ?>" class="text-decoration-none"><?= e($crumb['label']) ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= e($crumb['label']) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php endif; ?>

        <?php require ROOT_DIR . '/templates/partials/flash.php'; ?>
        <?= $content ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){new bootstrap.Tooltip(el)});</script>
</body>
</html>
