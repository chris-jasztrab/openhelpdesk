<?php
/**
 * Migration 042 — Roles & granular permissions
 *
 * Turns the hardcoded `users.role` ENUM (admin/agent/power_user/user) into an
 * admin-managed `roles` table with a granular permission catalog, so installers
 * can create their own permission levels and assign capabilities to each.
 *
 * The four built-in roles are seeded to reproduce today's behaviour EXACTLY:
 *   admin       → is_admin (bypasses every permission check), lands on /admin
 *   agent       → staff baseline + ticket templates + recurring tickets, lands /agent
 *   power_user  → everything agent has, plus reports.view, lands /agent
 *   user        → portal only, no permissions
 *
 * Permission model (see plan): any *staff* role can always do core ticket work
 * and edit/preview KB articles (gated by Auth::requireStaff at the call sites).
 * The rows below are the *grantable add-ons* shown in the role permission matrix.
 *
 * Steps:
 *   1. Create `roles`, `permissions`, `role_permissions`.
 *   2. Seed the 4 built-in roles (is_system=1, protected from deletion).
 *   3. Seed the permission catalog.
 *   4. Seed role_permissions grants reproducing current capabilities.
 *   5. Verify every distinct users.role value has a matching role (abort otherwise).
 *   6. ALTER users.role from ENUM → VARCHAR(64) and index it.
 *
 * Idempotent: schema_migrations prevents re-runs, but each step guards itself
 * with information_schema / INSERT IGNORE so a half-applied run is recoverable.
 */
return static function (PDO $pdo): void {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $tableExists = static function (string $table) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([$db, $table]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $indexExists = static function (string $table, string $index) use ($pdo, $db): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $stmt->execute([$db, $table, $index]);
        return (int) $stmt->fetchColumn() > 0;
    };

    // ── 1. Create the tables ─────────────────────────────────────────────────
    if (!$tableExists('roles')) {
        $pdo->exec(
            "CREATE TABLE `roles` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `slug`        VARCHAR(64)  NOT NULL,
                `name`        VARCHAR(64)  NOT NULL,
                `description` VARCHAR(255) NOT NULL DEFAULT '',
                `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
                `is_admin`    TINYINT(1)   NOT NULL DEFAULT 0,
                `is_staff`    TINYINT(1)   NOT NULL DEFAULT 0,
                `landing`     ENUM('admin','agent','portal') NOT NULL DEFAULT 'portal',
                `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_roles_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('permissions')) {
        $pdo->exec(
            "CREATE TABLE `permissions` (
                `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `perm_key`    VARCHAR(64)  NOT NULL,
                `label`       VARCHAR(128) NOT NULL,
                `category`    VARCHAR(64)  NOT NULL DEFAULT 'General',
                `description` VARCHAR(255) NOT NULL DEFAULT '',
                `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY `uniq_permissions_key` (`perm_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('role_permissions')) {
        // perm_key is stored as a string (not an FK to permissions) so a perm can
        // be honoured in code before its catalog row exists, and editing the
        // catalog never cascade-deletes grants. role_id cascades on role delete.
        $pdo->exec(
            "CREATE TABLE `role_permissions` (
                `role_id`  INT UNSIGNED NOT NULL,
                `perm_key` VARCHAR(64)  NOT NULL,
                PRIMARY KEY (`role_id`, `perm_key`),
                CONSTRAINT `fk_role_permissions_role`
                    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ── 2. Seed the 4 built-in roles ─────────────────────────────────────────
    // [slug, name, description, is_admin, is_staff, landing, sort_order]
    $roleSeeds = [
        ['admin',      'Admin',      'Full, unrestricted access to everything.',          1, 1, 'admin',  10],
        ['agent',      'Agent',      'Works tickets, templates and the knowledge base.',  0, 1, 'agent',  20],
        ['power_user', 'Power User', 'Everything an Agent can do, plus access to reports.', 0, 1, 'agent', 30],
        ['user',       'End User',   'Portal access — submit and track their own tickets.', 0, 0, 'portal', 40],
    ];
    $roleInsert = $pdo->prepare(
        "INSERT IGNORE INTO `roles`
            (slug, name, description, is_system, is_admin, is_staff, landing, sort_order)
         VALUES (?, ?, ?, 1, ?, ?, ?, ?)"
    );
    foreach ($roleSeeds as $r) {
        $roleInsert->execute($r);
    }

    // ── 3. Seed the permission catalog (grantable add-ons) ───────────────────
    // [perm_key, label, category, description, sort_order]
    $permSeeds = [
        ['reports.view',              'View reports',                    'Reports',       'Open the reporting dashboards.',                              10],
        ['kb.articles.manage',        'Create & delete KB articles',     'Knowledge Base','Create, delete and view history of knowledge base articles. (All staff can already edit existing articles.)', 20],
        ['kb.structure.manage',       'Manage KB categories & folders',  'Knowledge Base','Add, rename, reorder and delete KB categories and folders.',  30],
        ['ticket_templates.manage',   'Manage ticket templates',         'Tickets',       'Create, edit and delete reusable ticket templates.',          40],
        ['recurring_tickets.manage',  'Manage recurring tickets',        'Tickets',       'Create, edit and run scheduled recurring tickets.',           50],
        ['workflows.manage',          'Manage ticket types & forms',     'Tickets',       'Edit ticket types, custom form fields and ticket statuses.',  60],
        ['priorities.manage',         'Manage priorities',               'Tickets',       'Add, rename, recolor and reorder ticket priorities.',         70],
        ['sla.manage',                'Manage SLA policies',             'Tickets',       'Configure service-level agreement targets.',                  80],
        ['users.manage',              'Manage users',                    'People',        'Create, edit, delete, merge and import users.',               90],
        ['groups.manage',             'Manage groups',                   'People',        'Create, edit and delete ticket groups.',                     100],
        ['skills.manage',             'Manage agent skills',             'People',        'Create and assign agent skills.',                            110],
        ['locations.manage',          'Manage locations',                'Organization',  'Create, edit and delete locations / sites.',                 120],
        ['automations.manage',        'Manage automations & escalations','Automation',    'Configure automation rules, escalations, stale-ticket and scheduled-report jobs.', 130],
        ['csat.manage',               'Manage CSAT surveys',             'Automation',    'Configure customer satisfaction surveys.',                   140],
        ['ai.manage',                 'Manage AI classification',        'Automation',    'Configure AI auto-classification and routing.',              150],
        ['import.manage',             'Import data & manage backups',    'Data',          'Import tickets/users/KB and create or restore backups.',     160],
        ['audit.view',                'View the audit log',              'System',        'Read and prune the security audit log.',                     170],
        ['settings.manage',           'Manage general settings',         'Settings',      'Email/SMTP, SSO, branding, labels, tags, canned responses, business hours, holidays and organization settings.', 180],
    ];
    $permInsert = $pdo->prepare(
        "INSERT IGNORE INTO `permissions`
            (perm_key, label, category, description, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($permSeeds as $p) {
        $permInsert->execute($p);
    }

    // ── 4. Seed grants reproducing current capabilities ──────────────────────
    // admin bypasses all checks (is_admin) so it needs no rows. agent and
    // power_user keep the staff-triple add-ons they have today; power_user adds
    // reports.view (its only delta over agent). user (portal) gets nothing.
    $grants = [
        'agent'      => ['ticket_templates.manage', 'recurring_tickets.manage'],
        'power_user' => ['ticket_templates.manage', 'recurring_tickets.manage', 'reports.view'],
    ];
    $roleIdStmt = $pdo->prepare('SELECT id FROM `roles` WHERE slug = ?');
    $grantInsert = $pdo->prepare(
        'INSERT IGNORE INTO `role_permissions` (role_id, perm_key) VALUES (?, ?)'
    );
    foreach ($grants as $slug => $keys) {
        $roleIdStmt->execute([$slug]);
        $roleId = (int) $roleIdStmt->fetchColumn();
        if ($roleId <= 0) {
            continue;
        }
        foreach ($keys as $key) {
            $grantInsert->execute([$roleId, $key]);
        }
    }

    // ── 5. Verify no orphan role values in users ─────────────────────────────
    $orphans = $pdo->query(
        "SELECT DISTINCT u.role
         FROM users u
         LEFT JOIN roles r ON r.slug = u.role
         WHERE r.slug IS NULL"
    )->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($orphans)) {
        throw new RuntimeException(
            'Migration 042 aborted: users contain role values not in the roles table: '
            . implode(', ', array_map(static fn($v) => "'{$v}'", $orphans))
            . '. Add these slugs to the role seed before re-running.'
        );
    }

    // ── 6. ALTER users.role from ENUM → VARCHAR(64) and index it ─────────────
    // Existing string values ('admin','agent','power_user','user') are preserved
    // bit-for-bit; the column stays the link to roles.slug (validated in the app).
    $stmt = $pdo->prepare(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'"
    );
    $stmt->execute([$db]);
    $colType = (string) $stmt->fetchColumn();

    if (stripos($colType, 'enum') === 0) {
        $pdo->exec(
            "ALTER TABLE `users`
             MODIFY COLUMN `role` VARCHAR(64) NOT NULL DEFAULT 'user'"
        );
    }

    if (!$indexExists('users', 'idx_users_role')) {
        $pdo->exec("ALTER TABLE `users` ADD INDEX `idx_users_role` (`role`)");
    }
};
