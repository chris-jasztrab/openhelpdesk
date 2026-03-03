<?php
/**
 * LocalDesk — Admin Password Rescue Script
 *
 * DROP THIS FILE in the public/ directory of your LocalDesk installation,
 * visit  http://yoursite/rescue.php  in a browser, reset the password,
 * then DELETE THIS FILE immediately.
 *
 * This script is intentionally standalone — it does not use the app's
 * framework so it works even when the app itself is broken.
 */

declare(strict_types=1);

// ── Load .env ────────────────────────────────────────────────────────────────
// Works whether dropped in the project root or in the public/ webroot.
$envFile = file_exists(__DIR__ . '/.env')
    ? __DIR__ . '/.env'
    : dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {
    die('ERROR: .env file not found. Place rescue.php in the project root or in public/.');
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (str_contains($line, '=')) {
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_NAME'] ?? 'localdesk';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? '';

// ── Connect ───────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('ERROR: Could not connect to database: ' . htmlspecialchars($e->getMessage()));
}

// ── Handle POST ───────────────────────────────────────────────────────────────
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if ($userId <= 0) {
        $message = 'Please select an admin account.';
        $messageType = 'danger';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $messageType = 'danger';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $messageType = 'danger';
    } else {
        // Verify the selected user is actually an admin
        $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = ? AND role IN (\'admin\', \'agent\')');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = 'Invalid user selected.';
            $messageType = 'danger';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $userId]);
            $message = 'Password updated successfully for ' . htmlspecialchars($user['email']) . '. '
                     . '<strong>Delete this file (rescue.php) now!</strong>';
            $messageType = 'success';
        }
    }
}

// ── Fetch admin/agent accounts ────────────────────────────────────────────────
$admins = $pdo->query(
    "SELECT id, first_name, last_name, email, role
     FROM users
     WHERE role IN ('admin', 'agent')
     ORDER BY role = 'admin' DESC, first_name, last_name"
)->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LocalDesk — Password Rescue</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:480px;">

    <div class="card border-danger shadow-sm">
        <div class="card-header bg-danger text-white fw-semibold">
            &#128274; LocalDesk — Admin Password Rescue
        </div>
        <div class="card-body">

            <div class="alert alert-warning small mb-4">
                <strong>Security notice:</strong> This script grants unrestricted password reset access.
                Delete <code>rescue.php</code> from your server immediately after use.
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> small"><?= $message ?></div>
            <?php endif; ?>

            <?php if (empty($admins)): ?>
            <div class="alert alert-secondary small">No admin or agent accounts found in the database.</div>
            <?php else: ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="user_id" class="form-label fw-semibold small">Account</label>
                    <select name="user_id" id="user_id" class="form-select" required>
                        <option value="">— select account —</option>
                        <?php foreach ($admins as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (isset($_POST['user_id']) && (int)$_POST['user_id'] === (int)$u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            &lt;<?= htmlspecialchars($u['email']) ?>&gt;
                            [<?= htmlspecialchars($u['role']) ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold small">New Password</label>
                    <input type="password" name="password" id="password" class="form-control"
                           minlength="8" required autocomplete="new-password">
                    <div class="form-text">Minimum 8 characters.</div>
                </div>

                <div class="mb-4">
                    <label for="confirm" class="form-label fw-semibold small">Confirm Password</label>
                    <input type="password" name="confirm" id="confirm" class="form-control"
                           minlength="8" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-danger w-100">Reset Password</button>
            </form>

            <?php endif; ?>
        </div>
    </div>

    <p class="text-center text-muted small mt-3">
        After resetting, delete <code>rescue.php</code> from your server.
    </p>

</div>
</body>
</html>
