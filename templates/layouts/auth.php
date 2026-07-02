<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &ndash; <?= e(getSetting('branding_app_name', 'OpenHelpDesk')) ?></title>
    <?php require ROOT_DIR . '/templates/partials/pwa-head.php'; ?>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ld-primary: <?= e(getSetting('branding_primary_color', '#4f46e5')) ?>;
            --ld-primary-hover: <?= e(getSetting('branding_primary_hover', '#4338ca')) ?>;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, <?= e(getSetting('branding_navbar_start', '#1e1b4b')) ?> 0%, <?= e(getSetting('branding_navbar_end', '#312e81')) ?> 50%, <?= e(getSetting('branding_primary_color', '#4f46e5')) ?> 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <main id="main-content">
        <?= $content ?>
    </main>
    <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <?php require ROOT_DIR . '/templates/partials/pwa-install.php'; ?>
</body>
</html>
