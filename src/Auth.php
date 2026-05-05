<?php

declare(strict_types=1);

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $db   = Database::connect();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id'         => (int) $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'avatar'     => $user['avatar'],
            ];
            return true;
        }

        return false;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function fullName(): string
    {
        $u = self::user();
        return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    }

    public static function initials(): string
    {
        $u = self::user();
        return strtoupper(
            mb_substr($u['first_name'] ?? '?', 0, 1) .
            mb_substr($u['last_name'] ?? '', 0, 1)
        );
    }

    public static function logout(): void
    {
        $uid = self::id();
        if ($uid) {
            try {
                Database::connect()
                    ->prepare('DELETE FROM user_presence WHERE user_id = ?')
                    ->execute([$uid]);
            } catch (\Throwable $e) {
                // Never let presence cleanup block logout
            }
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            // Stash the requested URL so the user lands back here after login.
            // Only capture safe-relative GET targets — POSTs can't be replayed
            // and absolute / protocol-relative URLs are open-redirect risks.
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri    = $_SERVER['REQUEST_URI']    ?? '';
            if (
                $method === 'GET'
                && is_string($uri) && $uri !== '' && strlen($uri) <= 2000
                && $uri[0] === '/'
                && (!isset($uri[1]) || ($uri[1] !== '/' && $uri[1] !== '\\'))
                && $uri !== '/login'
                && strncmp($uri, '/login?', 7) !== 0
            ) {
                $_SESSION['intended_url'] = $uri;
            }
            redirect('/login');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireAuth();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
            echo '<title>403 Forbidden</title>';
            echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
            echo '</head><body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f1f5f9">';
            echo '<div class="text-center"><h1 class="display-1 fw-bold text-danger">403</h1>';
            echo '<p class="lead text-muted">You do not have permission to access this page.</p>';
            echo '<a href="/" class="btn btn-primary">Go Home</a></div></body></html>';
            exit;
        }
    }
}
