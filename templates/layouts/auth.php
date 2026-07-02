<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &ndash; <?= e(getSetting('branding_app_name', 'OpenHelpDesk')) ?></title>
    <?php require ROOT_DIR . '/templates/partials/pwa-head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php require ROOT_DIR . '/templates/partials/pwa-install.php'; ?>
</body>
</html>
