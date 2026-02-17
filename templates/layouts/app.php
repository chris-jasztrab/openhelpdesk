<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &ndash; LocalDesk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ld-primary: #4f46e5;
            --ld-primary-hover: #4338ca;
            --ld-sidebar-width: 64px;
            --ld-navbar-height: 56px;
        }
        body { background-color: #f1f5f9; overflow-x: hidden; }
        .navbar { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%) !important; }
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
    </style>
</head>
<body>
    <?php require ROOT_DIR . '/templates/partials/navbar.php'; ?>

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
