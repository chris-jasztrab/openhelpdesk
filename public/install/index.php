<?php
/**
 * LocalDesk / FreshWPL — Web Installer
 *
 * Access this file at /install/ before the application is configured.
 * Delete (or restrict access to) this directory after installation is complete.
 *
 * This file is entirely self-contained and does not depend on any application code.
 */

declare(strict_types=1);

session_start();

define('INSTALL_ROOT', dirname(dirname(__DIR__)));
$lockFile = INSTALL_ROOT . '/storage/installed.lock';

// ─── Already installed? ───────────────────────────────────────────────────────
if (file_exists($lockFile)) {
    renderAlreadyInstalled($lockFile);
    exit;
}

// ─── Step routing ─────────────────────────────────────────────────────────────
$step   = (int) ($_SESSION['install']['step'] ?? 1);
$data   = $_SESSION['install']['data']  ?? [];
$errors = $_SESSION['install']['errors'] ?? [];
$_SESSION['install']['errors'] = []; // clear on each load

// Handle POSTed steps
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedStep = (int) ($_POST['_step'] ?? 1);

    switch ($postedStep) {
        case 1: handleStep1(); break;
        case 2: handleStep2(); break;
        case 3: handleStep3(); break;
        case 4: handleStep4(); break;
        case 5: handleStep5(); break;
        case 6: handleInstall(); break;
    }
    // handleInstall() outputs its own page; other handlers redirect
    exit;
}

// ─── Handlers ────────────────────────────────────────────────────────────────

function handleStep1(): void
{
    $reqs = checkRequirements();
    if ($reqs['all_pass']) {
        advanceTo(2);
    } else {
        setErrors(['Please resolve all requirement issues before continuing.']);
        redirectToStep(1);
    }
}

function handleStep2(): void
{
    $host   = trim($_POST['db_host']   ?? '127.0.0.1');
    $port   = trim($_POST['db_port']   ?? '3306');
    $name   = trim($_POST['db_name']   ?? '');
    $user   = trim($_POST['db_user']   ?? '');
    $pass   = $_POST['db_pass']        ?? '';
    $create = isset($_POST['db_create']);

    $errs = [];
    if ($host === '') $errs[] = 'Database host is required.';
    if ($port === '' || !ctype_digit($port)) $errs[] = 'Database port must be a number.';
    if ($name === '') $errs[] = 'Database name is required.';
    if ($user === '') $errs[] = 'Database username is required.';

    if (!empty($errs)) {
        setErrors($errs);
        storeFormData(['db_host' => $host, 'db_port' => $port, 'db_name' => $name, 'db_user' => $user]);
        redirectToStep(2);
        return;
    }

    // Test connection
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );

        if ($create) {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Verify the DB is accessible
        $pdo->exec("USE `{$name}`");

    } catch (PDOException $e) {
        setErrors(['Could not connect to the database: ' . $e->getMessage()]);
        storeFormData(['db_host' => $host, 'db_port' => $port, 'db_name' => $name, 'db_user' => $user]);
        redirectToStep(2);
        return;
    }

    storeData(['db_host' => $host, 'db_port' => $port, 'db_name' => $name, 'db_user' => $user, 'db_pass' => $pass]);
    advanceTo(3);
}

function handleStep3(): void
{
    $appName  = trim($_POST['app_name']  ?? 'LocalDesk');
    $appUrl   = rtrim(trim($_POST['app_url'] ?? ''), '/');
    $timezone = trim($_POST['timezone']  ?? 'UTC');
    $debug    = isset($_POST['app_debug']) ? 'true' : 'false';

    $errs = [];
    if ($appName === '') $errs[] = 'Application name is required.';
    if ($appUrl  === '') $errs[] = 'Application URL is required.';
    if (!in_array($timezone, timezone_identifiers_list(), true)) {
        $timezone = 'UTC';
    }

    if (!empty($errs)) {
        setErrors($errs);
        storeFormData(['app_name' => $appName, 'app_url' => $appUrl, 'timezone' => $timezone]);
        redirectToStep(3);
        return;
    }

    storeData(['app_name' => $appName, 'app_url' => $appUrl, 'timezone' => $timezone, 'app_debug' => $debug]);
    advanceTo(4);
}

function handleStep4(): void
{
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $pass      = $_POST['password']        ?? '';
    $confirm   = $_POST['password_confirm'] ?? '';

    $errs = [];
    if ($firstName === '') $errs[] = 'First name is required.';
    if ($lastName  === '') $errs[] = 'Last name is required.';
    if ($email     === '') $errs[] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Please enter a valid email address.';
    if (strlen($pass) < 8) $errs[] = 'Password must be at least 8 characters.';
    if ($pass !== $confirm) $errs[] = 'Passwords do not match.';

    if (!empty($errs)) {
        setErrors($errs);
        storeFormData(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email]);
        redirectToStep(4);
        return;
    }

    storeData([
        'admin_first_name' => $firstName,
        'admin_last_name'  => $lastName,
        'admin_email'      => $email,
        'admin_pass_hash'  => password_hash($pass, PASSWORD_DEFAULT),
    ]);
    advanceTo(5);
}

function handleStep5(): void
{
    // If "Skip" was clicked, just advance
    if (isset($_POST['skip'])) {
        advanceTo(6);
        return;
    }

    $smtpHost  = trim($_POST['smtp_host']         ?? '');
    $smtpPort  = trim($_POST['smtp_port']         ?? '587');
    $smtpEnc   = $_POST['smtp_encryption']        ?? 'tls';
    $smtpUser  = trim($_POST['smtp_username']     ?? '');
    $smtpPass  = $_POST['smtp_password']          ?? '';
    $fromAddr  = trim($_POST['mail_from_address'] ?? '');
    $fromName  = trim($_POST['mail_from_name']    ?? '');

    $errs = [];
    if ($smtpHost !== '') {
        if ($smtpPort === '' || !ctype_digit($smtpPort)) $errs[] = 'SMTP port must be a number.';
        if (!in_array($smtpEnc, ['none', 'tls', 'ssl'], true)) $smtpEnc = 'tls';
        if ($fromAddr === '') $errs[] = 'From email address is required when SMTP host is provided.';
        elseif (!filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) $errs[] = 'From email address is not valid.';
    }

    if (!empty($errs)) {
        setErrors($errs);
        storeFormData([
            'smtp_host'         => $smtpHost,
            'smtp_port'         => $smtpPort,
            'smtp_encryption'   => $smtpEnc,
            'smtp_username'     => $smtpUser,
            'mail_from_address' => $fromAddr,
            'mail_from_name'    => $fromName,
        ]);
        redirectToStep(5);
        return;
    }

    if ($smtpHost !== '') {
        storeData([
            'smtp_host'         => $smtpHost,
            'smtp_port'         => $smtpPort,
            'smtp_encryption'   => $smtpEnc,
            'smtp_username'     => $smtpUser,
            'smtp_password'     => $smtpPass,
            'mail_from_address' => $fromAddr,
            'mail_from_name'    => $fromName,
        ]);
    }

    advanceTo(6);
}

function handleInstall(): void
{
    $data     = $_SESSION['install']['data'] ?? [];
    $messages = [];
    $fatalError = null;

    try {
        // 1. Write .env
        $envContent = buildEnvContent($data);
        if (file_put_contents(INSTALL_ROOT . '/.env', $envContent) === false) {
            throw new RuntimeException('Could not write .env file. Check directory permissions.');
        }
        $messages[] = ['ok', 'Configuration file (.env) written successfully.'];

        // 2. Connect to DB
        $pdo = new PDO(
            "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_name']};charset=utf8mb4",
            $data['db_user'],
            $data['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // 3. Run schema
        $schema = file_get_contents(INSTALL_ROOT . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Could not read database/schema.sql.');
        }
        // Split and execute each statement
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $messages[] = ['ok', 'Database tables created successfully.'];

        // 4. Seed default priorities
        $pdo->exec("INSERT IGNORE INTO ticket_priorities (name, color, sort_order) VALUES
            ('Low',      '#198754', 1),
            ('Medium',   '#ffc107', 2),
            ('High',     '#fd7e14', 3),
            ('Critical', '#dc3545', 4)");
        $messages[] = ['ok', 'Default ticket priorities seeded.'];

        // 5. Create admin user
        $stmt = $pdo->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role)
             VALUES (?, ?, ?, ?, \'admin\')
             ON DUPLICATE KEY UPDATE id = id'
        );
        $stmt->execute([
            $data['admin_first_name'],
            $data['admin_last_name'],
            $data['admin_email'],
            $data['admin_pass_hash'],
        ]);
        $messages[] = ['ok', 'Administrator account created.'];

        // 6. Store SMTP settings (if provided) + initial app settings
        $settingStmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        if (!empty($data['smtp_host'])) {
            $smtpKeys = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'mail_from_address', 'mail_from_name'];
            foreach ($smtpKeys as $key) {
                if (isset($data[$key]) && $data[$key] !== '') {
                    $settingStmt->execute([$key, $data[$key]]);
                }
            }
            $messages[] = ['ok', 'Mail server settings saved.'];
        }

        // Seed app name from installer into branding settings
        $settingStmt->execute(['branding_app_name', $data['app_name'] ?? 'LocalDesk']);

        // Flag to trigger the first-login onboarding tour
        $settingStmt->execute(['show_onboarding', '1']);

        // 7. Create storage directories
        @mkdir(INSTALL_ROOT . '/storage/attachments/', 0755, true);
        $messages[] = ['ok', 'Storage directories verified.'];

        // 8. Write lockfile
        file_put_contents(INSTALL_ROOT . '/storage/installed.lock', date('c') . "\n");
        $messages[] = ['ok', 'Installation locked to prevent re-running.'];

        // 9. Clear session
        unset($_SESSION['install']);

    } catch (Exception $e) {
        $fatalError = $e->getMessage();
    }

    // Render result page
    renderResultPage($messages, $fatalError, $data['app_url'] ?? '/');
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function checkRequirements(): array
{
    $checks = [
        ['PHP ≥ 8.0',            PHP_VERSION_ID >= 80000,                                  'PHP ' . PHP_VERSION . ' installed'],
        ['PDO extension',         extension_loaded('pdo'),                                   'Required for database access'],
        ['PDO MySQL driver',      extension_loaded('pdo_mysql'),                             'Required for MySQL connections'],
        ['mbstring extension',    extension_loaded('mbstring'),                              'Required for multi-byte string handling'],
        ['JSON extension',        extension_loaded('json'),                                  'Required for data encoding'],
        ['OpenSSL extension',     extension_loaded('openssl'),                               'Required for secure connections'],
        ['Composer autoloader',   file_exists(INSTALL_ROOT . '/vendor/autoload.php'),        'Run `composer install` if missing'],
        ['.env file writable',    is_writable(INSTALL_ROOT) || (file_exists(INSTALL_ROOT . '/.env') && is_writable(INSTALL_ROOT . '/.env')), 'Project root must be writable'],
        ['storage/ writable',     is_writable(INSTALL_ROOT . '/storage') || is_writable(INSTALL_ROOT), 'storage/ directory must be writable'],
    ];

    $allPass = true;
    foreach ($checks as &$c) {
        if (!$c[1]) $allPass = false;
    }

    return ['checks' => $checks, 'all_pass' => $allPass];
}

function buildEnvContent(array $data): string
{
    $appName  = $data['app_name']  ?? 'LocalDesk';
    $appUrl   = $data['app_url']   ?? 'http://localhost';
    $debug    = $data['app_debug'] ?? 'false';
    $timezone = $data['timezone']  ?? 'UTC';
    $dbHost   = $data['db_host']   ?? '127.0.0.1';
    $dbPort   = $data['db_port']   ?? '3306';
    $dbName   = $data['db_name']   ?? 'localdesk';
    $dbUser   = $data['db_user']   ?? 'root';
    $dbPass   = $data['db_pass']   ?? '';

    return "# Application\n"
         . "APP_NAME={$appName}\n"
         . "APP_URL={$appUrl}\n"
         . "APP_DEBUG={$debug}\n"
         . "APP_TIMEZONE={$timezone}\n"
         . "\n"
         . "# Database\n"
         . "DB_HOST={$dbHost}\n"
         . "DB_PORT={$dbPort}\n"
         . "DB_NAME={$dbName}\n"
         . "DB_USER={$dbUser}\n"
         . "DB_PASS={$dbPass}\n"
         . "\n"
         . "# File uploads\n"
         . "UPLOAD_MAX_SIZE=20971520\n"
         . "UPLOAD_ALLOWED_TYPES=application/pdf,image/jpeg,image/png,image/gif,image/webp,"
         . "application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,"
         . "application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,"
         . "text/plain,application/zip\n";
}

function storeData(array $new): void
{
    $_SESSION['install']['data'] = array_merge($_SESSION['install']['data'] ?? [], $new);
}

function storeFormData(array $form): void
{
    $_SESSION['install']['form'] = $form;
}

function setErrors(array $errs): void
{
    $_SESSION['install']['errors'] = $errs;
}

function advanceTo(int $step): void
{
    $_SESSION['install']['step'] = $step;
    header('Location: ' . installUrl() . '?step=' . $step);
    exit;
}

function redirectToStep(int $step): void
{
    header('Location: ' . installUrl() . '?step=' . $step);
    exit;
}

function installUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/install/';
}

function detectAppUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Sync step from GET param ──────────────────────────────────────────────────
if (isset($_GET['step'])) {
    $requestedStep = (int) $_GET['step'];
    // Only allow navigating backwards or to current step
    if ($requestedStep >= 1 && $requestedStep <= ($step)) {
        $step = $requestedStep;
        $_SESSION['install']['step'] = $step;
    }
}

$errors   = $_SESSION['install']['errors'] ?? [];
$formData = $_SESSION['install']['form']   ?? [];
$data     = $_SESSION['install']['data']   ?? [];
$_SESSION['install']['errors'] = [];
$_SESSION['install']['form']   = [];

// ─── Common timezones for selector ───────────────────────────────────────────
$timezoneGroups = [
    'UTC'    => ['UTC'],
    'America' => ['America/New_York','America/Chicago','America/Denver','America/Los_Angeles',
                  'America/Anchorage','America/Adak','America/Halifax','America/Toronto',
                  'America/Vancouver','America/Winnipeg','America/Edmonton','America/Regina',
                  'America/St_Johns','America/Phoenix','America/Bogota','America/Lima',
                  'America/Sao_Paulo','America/Buenos_Aires','America/Santiago','America/Mexico_City'],
    'Europe' => ['Europe/London','Europe/Paris','Europe/Berlin','Europe/Madrid','Europe/Rome',
                 'Europe/Amsterdam','Europe/Brussels','Europe/Zurich','Europe/Vienna',
                 'Europe/Stockholm','Europe/Warsaw','Europe/Athens','Europe/Istanbul',
                 'Europe/Moscow','Europe/Helsinki','Europe/Lisbon'],
    'Asia'   => ['Asia/Dubai','Asia/Kolkata','Asia/Dhaka','Asia/Bangkok','Asia/Singapore',
                 'Asia/Shanghai','Asia/Tokyo','Asia/Seoul','Asia/Karachi','Asia/Riyadh',
                 'Asia/Jakarta','Asia/Taipei','Asia/Hong_Kong'],
    'Pacific/Australia' => ['Australia/Sydney','Australia/Melbourne','Australia/Brisbane',
                            'Australia/Perth','Pacific/Auckland','Pacific/Honolulu','Pacific/Fiji'],
    'Africa' => ['Africa/Cairo','Africa/Johannesburg','Africa/Lagos','Africa/Nairobi'],
];

$currentTz = $formData['timezone'] ?? $data['timezone'] ?? 'UTC';

// ─── Page rendering ──────────────────────────────────────────────────────────
$stepTitles = ['Requirements', 'Database', 'Application', 'Admin Account', 'Mail Server', 'Review & Install'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer — LocalDesk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --ld-primary: #4f46e5; }
        body  { background: #f1f5f9; min-height: 100vh; padding: 40px 16px 60px; }

        .installer-wrap { max-width: 640px; margin: 0 auto; }

        .installer-brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .installer-brand .brand-icon {
            width: 56px; height: 56px;
            background: var(--ld-primary);
            border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-size: 28px;
            margin-bottom: 12px;
        }
        .installer-brand h1 { font-size: 1.5rem; font-weight: 700; margin: 0; }
        .installer-brand p  { color: #64748b; margin: 4px 0 0; font-size: .9rem; }

        /* Step indicator */
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            gap: 0;
        }
        .step-indicator .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .step-indicator .step-item + .step-item::before {
            content: '';
            position: absolute;
            top: 16px;
            right: 50%;
            left: -50%;
            height: 2px;
            background: #cbd5e1;
            z-index: 0;
        }
        .step-indicator .step-item.done::before,
        .step-indicator .step-item.active::before {
            background: var(--ld-primary);
        }
        .step-circle {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #94a3b8;
            font-size: .8rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            position: relative; z-index: 1;
            transition: background .2s, color .2s;
        }
        .step-item.done .step-circle   { background: var(--ld-primary); color: #fff; }
        .step-item.active .step-circle { background: var(--ld-primary); color: #fff; box-shadow: 0 0 0 4px #e0e7ff; }
        .step-label {
            font-size: .7rem;
            margin-top: 4px;
            color: #94a3b8;
            white-space: nowrap;
        }
        .step-item.done .step-label,
        .step-item.active .step-label  { color: var(--ld-primary); font-weight: 600; }

        .card          { border: 0; box-shadow: 0 2px 16px rgba(0,0,0,.08); border-radius: 14px; }
        .card-header   { background: #fff; border-bottom: 1px solid #f1f5f9; border-radius: 14px 14px 0 0 !important; padding: 20px 24px 16px; }
        .card-body     { padding: 24px; }
        .card-footer   { background: #f8fafc; border-top: 1px solid #f1f5f9; border-radius: 0 0 14px 14px !important; padding: 16px 24px; }

        .req-row        { display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; gap: 10px; }
        .req-row:last-child { border-bottom: 0; }
        .req-icon       { width: 22px; text-align: center; font-size: 1rem; }
        .req-name       { font-weight: 500; flex: 1; }
        .req-note       { font-size: .8rem; color: #94a3b8; }

        .pass-toggle    { cursor: pointer; }
        .summary-table  { font-size: .9rem; }
        .summary-table td:first-child { color: #64748b; width: 130px; }
        .summary-table td:last-child  { font-weight: 500; word-break: break-all; }
        .summary-section { margin-bottom: 20px; }
        .summary-section h6 { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin-bottom: 8px; }
    </style>
</head>
<body>
<div class="installer-wrap">

    <!-- Brand -->
    <div class="installer-brand">
        <div class="brand-icon"><i class="bi bi-headset"></i></div>
        <h1>LocalDesk Installer</h1>
        <p>Follow the steps below to configure your help desk.</p>
    </div>

    <!-- Step indicator -->
    <div class="step-indicator mb-4">
        <?php foreach ($stepTitles as $i => $title):
            $n = $i + 1;
            $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
        ?>
        <div class="step-item <?= $cls ?>" style="width: <?= round(100 / count($stepTitles)) ?>%;">
            <div class="step-circle">
                <?php if ($n < $step): ?>
                    <i class="bi bi-check-lg"></i>
                <?php else: ?>
                    <?= $n ?>
                <?php endif; ?>
            </div>
            <div class="step-label d-none d-sm-block"><?= h($title) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Error alert -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger d-flex gap-2 align-items-start mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <?php if (count($errors) === 1): ?>
                <?= h($errors[0]) ?>
            <?php else: ?>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══════════ STEP CARD ══════════ -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">
                Step <?= $step ?>: <?= h($stepTitles[$step - 1]) ?>
            </h5>
        </div>

        <?php if ($step === 1): /* ── Requirements ── */ ?>
        <?php $reqs = checkRequirements(); ?>
        <div class="card-body">
            <p class="text-muted mb-3">Checking that your server meets the minimum requirements.</p>
            <?php foreach ($reqs['checks'] as [$label, $pass, $note]): ?>
            <div class="req-row">
                <span class="req-icon <?= $pass ? 'text-success' : 'text-danger' ?>">
                    <i class="bi bi-<?= $pass ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                </span>
                <span class="req-name"><?= h($label) ?></span>
                <span class="req-note"><?= h($note) ?></span>
            </div>
            <?php endforeach; ?>

            <?php if (!$reqs['all_pass']): ?>
            <div class="alert alert-warning mt-3 mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Please resolve the failed checks above, then refresh this page.
            </div>
            <?php endif; ?>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <form method="POST">
                <input type="hidden" name="_step" value="1">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);"
                        <?= !$reqs['all_pass'] ? 'disabled' : '' ?>>
                    Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </form>
        </div>

        <?php elseif ($step === 2): /* ── Database ── */ ?>
        <form method="POST">
            <input type="hidden" name="_step" value="2">
            <div class="card-body">
                <p class="text-muted mb-3">Enter your MySQL database connection details.</p>
                <div class="row g-3">
                    <div class="col-sm-8">
                        <label class="form-label fw-medium">Database Host <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="db_host"
                               value="<?= h($formData['db_host'] ?? $data['db_host'] ?? '127.0.0.1') ?>" required>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label fw-medium">Port <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="db_port"
                               value="<?= h($formData['db_port'] ?? $data['db_port'] ?? '3306') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Database Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="db_name"
                               value="<?= h($formData['db_name'] ?? $data['db_name'] ?? 'localdesk') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="db_user"
                               value="<?= h($formData['db_user'] ?? $data['db_user'] ?? 'root') ?>"
                               autocomplete="off" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="db_pass" id="db_pass"
                                   autocomplete="new-password">
                            <button class="btn btn-outline-secondary pass-toggle" type="button"
                                    onclick="togglePass('db_pass', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="db_create" id="db_create" checked>
                            <label class="form-check-label" for="db_create">
                                Create database if it doesn't exist
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="?step=1" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    Test &amp; Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </form>

        <?php elseif ($step === 3): /* ── Application ── */ ?>
        <form method="POST">
            <input type="hidden" name="_step" value="3">
            <div class="card-body">
                <p class="text-muted mb-3">Configure the basic application settings.</p>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-medium">Application Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="app_name"
                               value="<?= h($formData['app_name'] ?? $data['app_name'] ?? 'LocalDesk') ?>" required>
                        <div class="form-text">Shown in the browser title bar and emails.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Application URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" name="app_url"
                               value="<?= h($formData['app_url'] ?? $data['app_url'] ?? detectAppUrl()) ?>" required>
                        <div class="form-text">The full URL where your app is hosted (no trailing slash).</div>
                    </div>
                    <div class="col-sm-8">
                        <label class="form-label fw-medium">Timezone</label>
                        <select class="form-select" name="timezone">
                            <?php foreach ($timezoneGroups as $group => $tzList): ?>
                            <optgroup label="<?= h($group) ?>">
                                <?php foreach ($tzList as $tz): ?>
                                <option value="<?= h($tz) ?>" <?= $currentTz === $tz ? 'selected' : '' ?>>
                                    <?= h($tz) ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="app_debug" id="app_debug"
                                   <?= ($data['app_debug'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="app_debug">Debug mode</label>
                            <div class="form-text">Disable in production.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="?step=2" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </form>

        <?php elseif ($step === 4): /* ── Admin Account ── */ ?>
        <form method="POST">
            <input type="hidden" name="_step" value="4">
            <div class="card-body">
                <p class="text-muted mb-3">Create the first administrator account. You'll use these credentials to log in.</p>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name"
                               value="<?= h($formData['first_name'] ?? $data['admin_first_name'] ?? '') ?>"
                               autocomplete="given-name" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name"
                               value="<?= h($formData['last_name'] ?? $data['admin_last_name'] ?? '') ?>"
                               autocomplete="family-name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email"
                               value="<?= h($formData['email'] ?? $data['admin_email'] ?? '') ?>"
                               autocomplete="email" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="admin_pass"
                                   autocomplete="new-password" minlength="8" required>
                            <button class="btn btn-outline-secondary pass-toggle" type="button"
                                    onclick="togglePass('admin_pass', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password_confirm" id="admin_pass2"
                                   autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary pass-toggle" type="button"
                                    onclick="togglePass('admin_pass2', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="?step=3" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </form>

        <?php elseif ($step === 5): /* ── Mail Server ── */ ?>
        <form method="POST">
            <input type="hidden" name="_step" value="5">
            <div class="card-body">
                <p class="text-muted mb-3">
                    Configure outgoing email so the help desk can send notifications and alerts.
                    All fields are optional — you can configure this later in <strong>Admin → Settings</strong>.
                </p>
                <div class="row g-3">
                    <div class="col-sm-8">
                        <label class="form-label fw-medium">SMTP Host</label>
                        <input type="text" class="form-control" name="smtp_host"
                               value="<?= h($formData['smtp_host'] ?? $data['smtp_host'] ?? '') ?>"
                               placeholder="smtp.example.com">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label fw-medium">SMTP Port</label>
                        <input type="number" class="form-control" name="smtp_port"
                               value="<?= h($formData['smtp_port'] ?? $data['smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label fw-medium">Encryption</label>
                        <select class="form-select" name="smtp_encryption">
                            <?php foreach (['tls' => 'TLS (recommended)', 'ssl' => 'SSL', 'none' => 'None'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($formData['smtp_encryption'] ?? $data['smtp_encryption'] ?? 'tls') === $val ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-8">
                        <label class="form-label fw-medium">SMTP Username</label>
                        <input type="text" class="form-control" name="smtp_username"
                               value="<?= h($formData['smtp_username'] ?? $data['smtp_username'] ?? '') ?>"
                               autocomplete="off">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">SMTP Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="smtp_password" id="smtp_pass"
                                   autocomplete="new-password">
                            <button class="btn btn-outline-secondary pass-toggle" type="button"
                                    onclick="togglePass('smtp_pass', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">From Email Address</label>
                        <input type="email" class="form-control" name="mail_from_address"
                               value="<?= h($formData['mail_from_address'] ?? $data['mail_from_address'] ?? '') ?>"
                               placeholder="helpdesk@example.com">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">From Name</label>
                        <input type="text" class="form-control" name="mail_from_name"
                               value="<?= h($formData['mail_from_name'] ?? $data['mail_from_name'] ?? '') ?>"
                               placeholder="Help Desk">
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="?step=4" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <div class="d-flex gap-2">
                    <button type="submit" name="skip" value="1" class="btn btn-outline-secondary">
                        Skip for now
                    </button>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                        Continue <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </form>

        <?php elseif ($step === 6): /* ── Review & Install ── */ ?>
        <div class="card-body">
            <p class="text-muted mb-4">Review your configuration below. Click <strong>Install Now</strong> when you're ready.</p>

            <!-- Database -->
            <div class="summary-section">
                <h6><i class="bi bi-database me-1"></i>Database</h6>
                <table class="summary-table w-100">
                    <tr><td>Connection</td><td><?= h($data['db_user'] ?? '') ?>@<?= h($data['db_host'] ?? '') ?>:<?= h($data['db_port'] ?? '') ?></td></tr>
                    <tr><td>Database</td><td><?= h($data['db_name'] ?? '') ?></td></tr>
                </table>
            </div>
            <hr class="my-3">

            <!-- Application -->
            <div class="summary-section">
                <h6><i class="bi bi-gear me-1"></i>Application</h6>
                <table class="summary-table w-100">
                    <tr><td>Name</td><td><?= h($data['app_name'] ?? '') ?></td></tr>
                    <tr><td>URL</td><td><?= h($data['app_url'] ?? '') ?></td></tr>
                    <tr><td>Timezone</td><td><?= h($data['timezone'] ?? 'UTC') ?></td></tr>
                    <tr><td>Debug mode</td><td><?= ($data['app_debug'] ?? 'false') === 'true' ? '<span class="text-warning">Enabled</span>' : '<span class="text-success">Disabled</span>' ?></td></tr>
                </table>
            </div>
            <hr class="my-3">

            <!-- Admin -->
            <div class="summary-section">
                <h6><i class="bi bi-person-badge me-1"></i>Administrator Account</h6>
                <table class="summary-table w-100">
                    <tr><td>Name</td><td><?= h(($data['admin_first_name'] ?? '') . ' ' . ($data['admin_last_name'] ?? '')) ?></td></tr>
                    <tr><td>Email</td><td><?= h($data['admin_email'] ?? '') ?></td></tr>
                    <tr><td>Password</td><td>••••••••</td></tr>
                </table>
            </div>
            <hr class="my-3">

            <!-- Mail -->
            <div class="summary-section mb-0">
                <h6><i class="bi bi-envelope me-1"></i>Mail Server</h6>
                <?php if (!empty($data['smtp_host'])): ?>
                <table class="summary-table w-100">
                    <tr><td>SMTP</td><td><?= h($data['smtp_host']) ?>:<?= h($data['smtp_port'] ?? '587') ?> (<?= h(strtoupper($data['smtp_encryption'] ?? 'TLS')) ?>)</td></tr>
                    <tr><td>From</td><td><?= h($data['mail_from_name'] ?? '') ?> &lt;<?= h($data['mail_from_address'] ?? '') ?>&gt;</td></tr>
                </table>
                <?php else: ?>
                <p class="text-muted mb-0 small">Not configured — you can set this up later in <strong>Admin → Settings</strong>.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="?step=5" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
            <form method="POST">
                <input type="hidden" name="_step" value="6">
                <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                    <i class="bi bi-rocket-takeoff me-1"></i>Install Now
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div><!-- /card -->

    <p class="text-center text-muted small mt-4">
        LocalDesk &bull; Web Installer &bull; <?= h(PHP_VERSION) ?>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass(id, btn) {
    var input = document.getElementById(id);
    var icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
<?php

// ─── Special page renderers ───────────────────────────────────────────────────

function renderAlreadyInstalled(string $lockFile): void
{
    $installedAt = trim(file_get_contents($lockFile) ?: '');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Already Installed — LocalDesk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --ld-primary: #4f46e5; }
        body  { background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { border: 0; box-shadow: 0 2px 16px rgba(0,0,0,.08); border-radius: 14px; max-width: 480px; width: 100%; }
    </style>
</head>
<body>
    <div class="card p-4 text-center">
        <div class="mb-3">
            <span style="font-size:3rem; color:var(--ld-primary);"><i class="bi bi-shield-lock-fill"></i></span>
        </div>
        <h4 class="fw-bold mb-2">Already Installed</h4>
        <p class="text-muted mb-1">LocalDesk is already installed and configured.</p>
        <?php if ($installedAt): ?>
        <p class="text-muted small mb-3">Installed: <?= htmlspecialchars($installedAt, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="alert alert-warning text-start small mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            For security, delete or restrict access to the <code>/install/</code> directory.
        </div>
        <a href="/" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-house me-1"></i>Go to LocalDesk
        </a>
    </div>
</body>
</html>
    <?php
}

function renderResultPage(array $messages, ?string $fatalError, string $appUrl): void
{
    $success = $fatalError === null;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $success ? 'Installation Complete' : 'Installation Failed' ?> — LocalDesk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --ld-primary: #4f46e5; }
        body  { background: #f1f5f9; min-height: 100vh; padding: 40px 16px 60px; }
        .result-wrap { max-width: 580px; margin: 0 auto; }
        .card { border: 0; box-shadow: 0 2px 16px rgba(0,0,0,.08); border-radius: 14px; }
        .step-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .step-row:last-child { border-bottom: 0; }
    </style>
</head>
<body>
<div class="result-wrap">
    <div class="text-center mb-4">
        <?php if ($success): ?>
        <div style="font-size:4rem; color:#22c55e;"><i class="bi bi-check-circle-fill"></i></div>
        <h2 class="fw-bold mt-2">Installation Complete!</h2>
        <p class="text-muted">LocalDesk has been successfully installed.</p>
        <?php else: ?>
        <div style="font-size:4rem; color:#ef4444;"><i class="bi bi-x-circle-fill"></i></div>
        <h2 class="fw-bold mt-2">Installation Failed</h2>
        <p class="text-muted">An error occurred during installation.</p>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <?php foreach ($messages as [$status, $msg]): ?>
            <div class="step-row">
                <span class="text-<?= $status === 'ok' ? 'success' : 'danger' ?>" style="font-size:1.1rem;">
                    <i class="bi bi-<?= $status === 'ok' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                </span>
                <span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endforeach; ?>

            <?php if ($fatalError): ?>
            <div class="alert alert-danger mt-3 mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($fatalError, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-warning">
        <i class="bi bi-shield-exclamation me-2"></i>
        <strong>Security reminder:</strong> Delete or restrict access to the <code>/install/</code> directory before going live.
    </div>
    <div class="text-center">
        <a href="<?= htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') ?>/login"
           class="btn text-white btn-lg px-5" style="background:var(--ld-primary);">
            <i class="bi bi-box-arrow-in-right me-2"></i>Go to your site
        </a>
    </div>
    <?php else: ?>
    <div class="text-center">
        <a href="?step=6" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Review
        </a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
    <?php
}
