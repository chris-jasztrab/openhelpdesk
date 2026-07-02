<?php

declare(strict_types=1);

class Auth
{
    /**
     * A valid bcrypt hash of a throwaway string. When no user matches the
     * supplied email we still run password_verify() against this so the
     * response time does not reveal whether the account exists (timing-based
     * user enumeration). The literal value is irrelevant — only its cost.
     */
    public const DUMMY_PASSWORD_HASH = '$2y$10$s5XI8w2iEwhiUMT/acOjEOgRaZNY6zQghfkyWo0mDt8lJNMw/V0rW';

    public static function attempt(string $email, string $password): bool
    {
        $db   = Database::connect();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always run a verify (against a dummy hash when the user is missing) so
        // a non-existent account can't be distinguished by response latency.
        $valid = password_verify($password, $user['password'] ?? self::DUMMY_PASSWORD_HASH);

        if ($user && $valid) {
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

    /**
     * Re-sync the session user against the database on each authenticated
     * request. Without this, a role stored in the session at login stays in
     * effect until the user logs out — so demoting or deleting a rogue
     * admin/agent leaves their live session fully privileged. Re-reading the
     * role here means a permission change (or account deletion) takes effect on
     * the user's very next request. One cheap indexed lookup; negligible at
     * helpdesk scale (and consistent with how roleCan/_rolesCache already
     * resolve permissions fresh per request rather than snapshotting them).
     */
    private static function refreshCurrentUser(): void
    {
        $uid = self::id();
        if ($uid === null) {
            return;
        }
        try {
            $stmt = Database::connect()->prepare(
                'SELECT role, first_name, last_name, email, avatar FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
        } catch (\Throwable $e) {
            return; // transient DB error: don't lock the user out
        }
        if (!$row) {
            self::logout(); // account deleted → drop the session entirely
            return;
        }
        $_SESSION['user']['role']       = $row['role'];
        $_SESSION['user']['first_name'] = $row['first_name'];
        $_SESSION['user']['last_name']  = $row['last_name'];
        $_SESSION['user']['email']      = $row['email'];
        $_SESSION['user']['avatar']     = $row['avatar'];
    }

    public static function requireAuth(): void
    {
        if (self::check()) {
            self::refreshCurrentUser();
        }
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
            self::forbid();
        }
    }

    /* ------------------------------------------------------------------
     * Granular permissions (migration 042)
     *
     * Roles are resolved by slug through the helpers in src/helpers.php
     * (roleCan / roleIsAdmin / roleIsStaff), which read the cached
     * roles + role_permissions map. Admin roles bypass every permission
     * check, so re-gating admin-only routes with requirePermission() can
     * never lock an admin out — it only ever widens access to custom roles.
     * ------------------------------------------------------------------ */

    /** True if the current user's role grants $perm (admins always true). */
    public static function can(string $perm): bool
    {
        return roleCan(self::role(), $perm);
    }

    /** True if the current user holds a full-access admin role. */
    public static function isAdmin(): bool
    {
        return roleIsAdmin(self::role());
    }

    /** True if the current user holds a staff (agent-interface) role. */
    public static function isStaff(): bool
    {
        return roleIsStaff(self::role());
    }

    /** Require auth + at least one of the given permissions, else 403. */
    public static function requirePermission(string ...$perms): void
    {
        self::requireAuth();
        foreach ($perms as $perm) {
            if (self::can($perm)) {
                return;
            }
        }
        self::forbid();
    }

    /** Require auth + a full-access admin role, else 403. */
    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (!self::isAdmin()) {
            self::forbid();
        }
    }

    /** Require auth + any staff role, else 403. */
    public static function requireStaff(): void
    {
        self::requireAuth();
        if (!self::isStaff()) {
            self::forbid();
        }
    }

    /** Emit the shared 403 page and stop. */
    private static function forbid(): never
    {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        echo '<title>403 Forbidden</title>';
        echo '<link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">';
        echo '</head><body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f1f5f9">';
        echo '<div class="text-center"><h1 class="display-1 fw-bold text-danger">403</h1>';
        echo '<p class="lead text-muted">You do not have permission to access this page.</p>';
        echo '<a href="/" class="btn btn-primary">Go Home</a></div></body></html>';
        exit;
    }
}
