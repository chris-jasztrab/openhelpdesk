<?php
/**
 * OpenHelpDesk — Account Rescue Script
 *
 * Canonical location: scripts/admin/rescue.php (NOT web-accessible).
 *
 * To use:
 *   1. COPY this file into the public/ directory of your installation
 *      (`cp scripts/admin/rescue.php public/rescue.php`).
 *   2. Visit http://yoursite/rescue.php in a browser and make your changes.
 *   3. DELETE the copy in public/ immediately afterwards
 *      (`rm public/rescue.php`). It is unauthenticated by design.
 *
 * `/public/rescue.php` is gitignored so a working copy can never be
 * committed by accident. This canonical copy lives outside the webroot.
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

$validRoles = ['admin', 'agent', 'user'];

// ── Handle POST ───────────────────────────────────────────────────────────────
$message     = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $newRole  = $_POST['role']     ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    $errors = [];

    if ($userId <= 0) {
        $errors[] = 'Please select an account.';
    }
    if (!in_array($newRole, $validRoles, true)) {
        $errors[] = 'Please select a valid access level.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($password !== '' && $password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if ($errors) {
        $message     = implode('<br>', $errors);
        $messageType = 'danger';
    } else {
        $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message     = 'User not found.';
            $messageType = 'danger';
        } else {
            $changes = [];

            // Always update role
            if ($newRole !== $user['role']) {
                $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $userId]);
                $changes[] = 'access level changed to <strong>' . htmlspecialchars($newRole) . '</strong>';
            }

            // Update password only if provided
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $userId]);
                $changes[] = 'password reset';
            }

            if ($changes) {
                $message = htmlspecialchars($user['first_name'] . ' ' . $user['last_name'])
                         . ' &lt;' . htmlspecialchars($user['email']) . '&gt;: '
                         . implode(', ', $changes) . '. '
                         . '<strong>Delete rescue.php from your server now!</strong>';
            } else {
                $message = 'No changes were made (role was already ' . htmlspecialchars($newRole) . ' and no password was entered).';
            }
            $messageType = 'success';
        }
    }
}

// ── Fetch all accounts ────────────────────────────────────────────────────────
$users = $pdo->query(
    "SELECT id, first_name, last_name, email, role
     FROM users
     ORDER BY
         FIELD(role, 'admin', 'agent', 'user'),
         first_name, last_name"
)->fetchAll(PDO::FETCH_ASSOC);

$roleBadgeClass = ['admin' => 'danger', 'agent' => 'primary', 'user' => 'secondary'];

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OpenHelpDesk — Account Rescue</title>
<link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:520px;">

    <div class="card border-danger shadow-sm">
        <div class="card-header bg-danger text-white fw-semibold">
            &#128274; OpenHelpDesk — Account Rescue
        </div>
        <div class="card-body">

            <div class="alert alert-warning small mb-4">
                <strong>Security notice:</strong> This script grants unrestricted account access.
                Delete <code>rescue.php</code> from your server immediately after use.
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> small"><?= $message ?></div>
            <?php endif; ?>

            <?php if (empty($users)): ?>
            <div class="alert alert-secondary small">No accounts found in the database.</div>
            <?php else: ?>

            <form method="POST">

                <div class="mb-3">
                    <label for="user_id" class="form-label fw-semibold small">Account</label>
                    <select name="user_id" id="user_id" class="form-select" required onchange="syncRole(this)">
                        <option value="">— select account —</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                                data-role="<?= htmlspecialchars($u['role']) ?>"
                                <?= (isset($_POST['user_id']) && (int)$_POST['user_id'] === (int)$u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            &lt;<?= htmlspecialchars($u['email']) ?>&gt;
                            [<?= htmlspecialchars($u['role']) ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label fw-semibold small">Access Level</label>
                    <select name="role" id="role" class="form-select" required>
                        <?php
                        $selectedRole = $_POST['role'] ?? '';
                        foreach ($validRoles as $r):
                        ?>
                        <option value="<?= $r ?>" <?= $selectedRole === $r ? 'selected' : '' ?>>
                            <?= ucfirst($r) ?>
                            <?php if ($r === 'admin'): ?> — full access<?php endif; ?>
                            <?php if ($r === 'agent'): ?> — can manage tickets<?php endif; ?>
                            <?php if ($r === 'user'): ?>  — portal / self-service only<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr class="my-4">
                <p class="text-muted small mb-3">Leave password fields blank to keep the existing password.</p>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold small">New Password <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="password" name="password" id="password" class="form-control"
                           autocomplete="new-password" placeholder="Leave blank to keep current password">
                    <div class="form-text">Minimum 8 characters if changing.</div>
                </div>

                <div class="mb-4">
                    <label for="confirm" class="form-label fw-semibold small">Confirm Password</label>
                    <input type="password" name="confirm" id="confirm" class="form-control"
                           autocomplete="new-password" placeholder="Repeat new password">
                </div>

                <button type="submit" class="btn btn-danger w-100">Save Changes</button>
            </form>

            <?php endif; ?>
        </div>
    </div>

    <p class="text-center text-muted small mt-3">
        After making changes, delete <code>rescue.php</code> from your server.
    </p>

</div>
<script>
// Pre-select the current role when an account is chosen
function syncRole(sel) {
    var opt = sel.options[sel.selectedIndex];
    var role = opt.dataset.role;
    if (role) {
        document.getElementById('role').value = role;
    }
}
// Run on page load if a user is already selected (e.g. after a failed POST)
(function() {
    var sel = document.getElementById('user_id');
    if (sel && sel.value) syncRole(sel);
})();
</script>
</body>
</html>
