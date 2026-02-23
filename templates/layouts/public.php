<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &ndash; <?= e(getSetting('branding_app_name', 'LocalDesk')) ?> Help Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ld-primary: <?= e(getSetting('branding_primary_color', '#4f46e5')) ?>;
            --ld-primary-hover: <?= e(getSetting('branding_primary_hover', '#4338ca')) ?>;
        }
        body { background-color: #f8fafc; }
        .pub-navbar {
            background: linear-gradient(135deg, <?= e(getSetting('branding_navbar_start', '#1e1b4b')) ?> 0%, <?= e(getSetting('branding_navbar_end', '#312e81')) ?> 100%);
            padding: .75rem 0;
        }
        .pub-navbar .navbar-brand { font-weight: 700; letter-spacing: -.5px; color: #fff !important; font-size: 1.1rem; }
        .pub-navbar .nav-link { color: rgba(255,255,255,.8) !important; font-size: .875rem; }
        .pub-navbar .nav-link:hover { color: #fff !important; }
        .pub-navbar .btn-login {
            border: 1px solid rgba(255,255,255,.35); color: #fff; font-size: .8rem; padding: .3rem .85rem;
            border-radius: 6px; text-decoration: none;
        }
        .pub-navbar .btn-login:hover { background: rgba(255,255,255,.15); color: #fff; }
        .pub-search-wrap { max-width: 420px; }
        .pub-search-wrap input { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; border-radius: 6px; }
        .pub-search-wrap input::placeholder { color: rgba(255,255,255,.5); }
        .pub-search-wrap input:focus { background: rgba(255,255,255,.2); border-color: rgba(255,255,255,.4); color: #fff; box-shadow: none; }
        #pub-search-results { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); max-height: 320px; overflow-y: auto; }
        #pub-search-results a { display: block; padding: .6rem 1rem; text-decoration: none; color: #1e293b; font-size: .875rem; border-bottom: 1px solid #f1f5f9; }
        #pub-search-results a:last-child { border-bottom: none; }
        #pub-search-results a:hover { background: #f8fafc; }
        #pub-search-results .text-muted { font-size: .75rem; }
        .pub-footer { background: #1e293b; color: rgba(255,255,255,.5); font-size: .8rem; padding: 1.5rem 0; margin-top: 3rem; }
    </style>
</head>
<body>
    <nav class="pub-navbar">
        <div class="container d-flex align-items-center gap-3">
            <a class="navbar-brand me-3" href="/kb">
                <i class="bi bi-book me-1"></i><?= e(getSetting('branding_app_name', 'LocalDesk')) ?> Help Center
            </a>

            <div class="pub-search-wrap flex-grow-1 position-relative">
                <input type="text" id="pubSearchInput" class="form-control form-control-sm"
                       placeholder="Search articles…" autocomplete="off">
                <div id="pub-search-results" style="display:none;"></div>
            </div>

            <a class="btn-login ms-2" href="/login">Staff Login</a>
        </div>
    </nav>

    <div class="container py-4">
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

        <?php if (!empty($_SESSION['_flash_success'] ?? null)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($_SESSION['_flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['_flash_success']); ?>
        <?php endif; ?>

        <?= $content ?>
    </div>

    <footer class="pub-footer">
        <div class="container d-flex justify-content-between align-items-center">
            <span><?= e(getSetting('branding_app_name', 'LocalDesk')) ?> Help Center</span>
            <span>Powered by LocalDesk</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        const input   = document.getElementById('pubSearchInput');
        const results = document.getElementById('pub-search-results');
        if (!input) return;
        let timer;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            const q = this.value.trim();
            if (q.length < 2) { results.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch('/kb/search?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.length) { results.style.display = 'none'; return; }
                        results.innerHTML = data.map(a =>
                            `<a href="/kb/articles/${a.slug}">
                                <div>${a.title}</div>
                                <div class="text-muted">${a.category_name} › ${a.folder_name}</div>
                            </a>`
                        ).join('');
                        results.style.display = 'block';
                    });
            }, 250);
        });
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    })();
    </script>
</body>
</html>
