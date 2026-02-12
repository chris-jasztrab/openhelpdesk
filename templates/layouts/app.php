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
            --ld-sidebar-width: 260px;
            --ld-navbar-height: 56px;
        }
        body { background-color: #f1f5f9; overflow-x: hidden; }
        .navbar { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%) !important; }
        .navbar-brand { font-weight: 700; letter-spacing: -0.5px; }

        /* Sidebar */
        .sidebar {
            width: var(--ld-sidebar-width);
            min-height: calc(100vh - var(--ld-navbar-height));
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            position: fixed;
            top: var(--ld-navbar-height);
            left: 0;
            overflow-y: auto;
            padding-top: 1rem;
            z-index: 100;
        }
        .sidebar .nav-link {
            color: #475569;
            padding: .7rem 1.25rem;
            font-size: .9rem;
            border-left: 3px solid transparent;
            transition: all .15s ease;
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .sidebar .nav-link:hover {
            background-color: #f8fafc;
            color: var(--ld-primary);
        }
        .sidebar .nav-link.active {
            background-color: #eef2ff;
            color: var(--ld-primary);
            border-left-color: var(--ld-primary);
            font-weight: 600;
        }
        .sidebar .sidebar-heading {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #94a3b8;
            padding: 1rem 1.25rem .5rem;
            font-weight: 600;
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
    </style>
</head>
<body>
    <?php require ROOT_DIR . '/templates/partials/navbar.php'; ?>

    <!-- Sidebar -->
    <aside class="sidebar">
        <nav class="nav flex-column">
            <?php foreach ($sidebarItems as $item): ?>
            <a class="nav-link <?= ($item['active'] ?? false) ? 'active' : '' ?>"
               href="<?= e($item['url']) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <span><?= e($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                    <span class="badge bg-secondary ms-auto"><?= e($item['badge']) ?></span>
                <?php endif; ?>
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
</body>
</html>
