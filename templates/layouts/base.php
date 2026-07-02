<!DOCTYPE html>
<?php $userTheme = Auth::check() ? getSetting('ui_theme:' . Auth::id(), 'light') : 'light'; ?>
<html lang="en" data-bs-theme="<?= e($userTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &ndash; <?= e(getSetting('branding_app_name', 'OpenHelpDesk')) ?></title>
    <?php require ROOT_DIR . '/templates/partials/pwa-head.php'; ?>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --ld-primary: <?= e(getSetting('branding_primary_color', '#4f46e5')) ?>; --ld-primary-hover: <?= e(getSetting('branding_primary_hover', '#4338ca')) ?>; --ld-navbar-height: 56px; }
        body { background-color: #f1f5f9; }
        .navbar { background: linear-gradient(135deg, <?= e(getSetting('branding_navbar_start', '#1e1b4b')) ?> 0%, <?= e(getSetting('branding_navbar_end', '#312e81')) ?> 100%) !important; }
        .navbar-brand { font-weight: 700; letter-spacing: -0.5px; }
        /* Header sits above all page-level sticky content (Bootstrap .sticky-top = 1020)
           so its account dropdown is never covered. Stays below modals/offcanvas (1050+). */
        .navbar.sticky-top { z-index: 1040; }
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

        /* Dark mode overrides */
        [data-bs-theme="dark"] body { background-color: #1a1d21; }
        [data-bs-theme="dark"] .stat-card { box-shadow: 0 1px 3px rgba(0,0,0,.3); }
        [data-bs-theme="dark"] .ld-search-tab { color: #adb5bd; }
        [data-bs-theme="dark"] .ld-search-tab:hover { color: #dee2e6; }
        [data-bs-theme="dark"] .ld-search-item { color: var(--bs-body-color) !important; }
        [data-bs-theme="dark"] .ld-search-item:hover { background: #2b3035; }
        [data-bs-theme="dark"] .ld-search-group + .ld-search-group { border-top-color: #373b3e; }
        [data-bs-theme="dark"] .table-light { --bs-table-bg: #2b3035; --bs-table-color: #dee2e6; }
        [data-bs-theme="dark"] .shadow-sm { box-shadow: 0 .125rem .25rem rgba(0,0,0,.3) !important; }
        /* .text-dark is not theme-adaptive in Bootstrap 5.3 — override to readable body color */
        [data-bs-theme="dark"] .text-dark { color: var(--bs-body-color) !important; }
        /* bg-white is not theme-adaptive — fix card headers, dropdowns, and other bg-white elements */
        [data-bs-theme="dark"] .bg-white { background-color: var(--bs-secondary-bg) !important; }
        [data-bs-theme="dark"] input.bg-white,
        [data-bs-theme="dark"] textarea.bg-white,
        [data-bs-theme="dark"] select.bg-white { background-color: var(--bs-body-bg) !important; }
        [data-bs-theme="dark"] .badge.bg-light { border-color: #495057 !important; }

        /* Skip-to-main link (WCAG 2.4.1) */
        .skip-link {
            position: absolute; top: -40px; left: 0;
            background: var(--ld-primary); color: #fff;
            padding: 8px 16px; text-decoration: none; z-index: 2000;
            border-radius: 0 0 4px 0; font-weight: 600;
        }
        .skip-link:focus { top: 0; color: #fff; outline: 2px solid #fff; outline-offset: -4px; }
        main:focus { outline: none; }

        /* Respect prefers-reduced-motion (WCAG 2.3.3 / 2.2.2) */
        @media (prefers-reduced-motion: reduce) {
            .ld-bell-ring .bi-bell,
            .ld-bell-active .bi-bell { animation: none !important; }
            *, *::before, *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
        }
    </style>
    <?php require ROOT_DIR . '/templates/partials/badge-soft-styles.php'; ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php require ROOT_DIR . '/templates/partials/navbar.php'; ?>

    <main id="main-content" tabindex="-1" class="container py-4">
        <?php if (!empty($breadcrumbs)): ?>
        <nav aria-label="Breadcrumb">
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
    </main>

    <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <?php require ROOT_DIR . '/templates/partials/pwa-install.php'; ?>
    <?php require ROOT_DIR . '/templates/partials/table-resize.php'; ?>
</body>
</html>
