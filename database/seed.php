<?php
/**
 * Database seeder — drops existing tables, applies schema, and seeds all data.
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │  DROPS every table and reseeds. NEVER run it against a database     │
 * │  whose data you want to keep.                                       │
 * │                                                                     │
 * │  There are NO default/generic passwords: you are prompted to choose │
 * │  a password for each of the three seeded accounts (or supply them   │
 * │  non-interactively via SEED_ADMIN_PASSWORD / SEED_AGENT_PASSWORD /  │
 * │  SEED_USER_PASSWORD).                                               │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * Usage:  php database/seed.php
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/helpers.php';

loadEnv(ROOT_DIR . '/.env');

// Choose account passwords up front — before anything destructive happens — so
// an aborted prompt never leaves you with a half-wiped database.
echo "Set passwords for the three seeded accounts (minimum 8 characters).\n\n";
$adminPw = promptForPassword('the Admin account (admin@localdesk.user)', 'SEED_ADMIN_PASSWORD');
$agentPw = promptForPassword('the Agent account (agent@localdesk.user)', 'SEED_AGENT_PASSWORD');
$userPw  = promptForPassword('the End User account (user@localdesk.user)', 'SEED_USER_PASSWORD');
echo "\n";

$host   = env('DB_HOST', '127.0.0.1');
$port   = env('DB_PORT', '3306');
$dbname = env('DB_NAME', 'localdesk');
$dbuser = env('DB_USER', 'root');
$dbpass = env('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host={$host};port={$port}", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");
    echo "[OK] Database '{$dbname}' ready.\n";

    // Drop tables in FK-safe order
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach (['notifications','ticket_attachments','ticket_tag_map','ticket_timeline','tickets','ticket_tags','ticket_types','ticket_priorities','group_user_map','groups','users','locations'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "[OK] Old tables dropped.\n";

    // Apply schema
    $schema = file_get_contents(ROOT_DIR . '/database/schema.sql');
    $pdo->exec($schema);
    echo "[OK] Schema applied.\n";

    // Stamp every committed migration as already applied. schema.sql is the
    // canonical snapshot — it already includes every column/table the
    // migrations add. Without this, the auto-migrator in src/bootstrap.php
    // would replay all migrations against the snapshot on first page load
    // and crash (some, e.g. 006, are deliberately destructive).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration`  VARCHAR(255) NOT NULL UNIQUE,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $migrationFiles = glob(ROOT_DIR . '/database/migrations/*.php') ?: [];
    sort($migrationFiles);
    $stampStmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations (migration) VALUES (?)");
    foreach ($migrationFiles as $file) {
        $stampStmt->execute([basename($file)]);
    }
    echo "[OK] " . count($migrationFiles) . " migrations stamped as baseline.\n";

    // ── Roles & permissions (RBAC) ───────────────────────────────
    // schema.sql ships the empty roles/permissions/role_permissions tables but
    // no rows, and the migrations that seed them (042 + later additions) are
    // stamped-not-run above. Without these rows roleIsAdmin()/roleIsStaff()
    // resolve every account to "no privileges", so the seeded admin and agent
    // get 403 on /admin and /agent. Mirror the web installer's consolidated
    // seed (public/install/index.php) — keep the two in sync if the built-in
    // roles or permission catalog ever change.
    $pdo->exec("INSERT IGNORE INTO roles (slug, name, description, is_system, is_admin, is_staff, landing, sort_order) VALUES
        ('admin',      'Admin',      'Full, unrestricted access to everything.',           1, 1, 1, 'admin',  10),
        ('agent',      'Agent',      'Works tickets, templates and the knowledge base.',   1, 0, 1, 'agent',  20),
        ('power_user', 'Power User', 'Everything an Agent can do, plus access to reports.', 1, 0, 1, 'agent',  30),
        ('user',       'End User',   'Portal access — submit and track their own tickets.', 1, 0, 0, 'portal', 40)");

    $pdo->exec("INSERT IGNORE INTO permissions (perm_key, label, category, description, sort_order) VALUES
        ('reports.view',             'View reports',                     'Reports',        'Open the reporting dashboards.',                                10),
        ('kb.articles.manage',       'Create & delete KB articles',      'Knowledge Base', 'Create, delete and view history of knowledge base articles. (All staff can already edit existing articles.)', 20),
        ('kb.structure.manage',      'Manage KB categories & folders',   'Knowledge Base', 'Add, rename, reorder and delete KB categories and folders.',    30),
        ('ticket_templates.manage',  'Manage ticket templates',          'Tickets',        'Create, edit and delete reusable ticket templates.',            40),
        ('tickets.view_all',         'View all tickets',                 'Tickets',        'See tickets across every group, even groups the user does not belong to. Never includes confidential ticket types.', 45),
        ('recurring_tickets.manage', 'Manage recurring tickets',         'Tickets',        'Create, edit and run scheduled recurring tickets.',             50),
        ('workflows.manage',         'Manage ticket types & forms',      'Tickets',        'Edit ticket types, custom form fields and ticket statuses.',    60),
        ('priorities.manage',        'Manage priorities',                'Tickets',        'Add, rename, recolor and reorder ticket priorities.',           70),
        ('sla.manage',               'Manage SLA policies',              'Tickets',        'Configure service-level agreement targets.',                    80),
        ('users.manage',             'Manage users',                     'People',         'Create, edit, delete, merge and import users.',                 90),
        ('groups.manage',            'Manage groups',                    'People',         'Create, edit and delete ticket groups.',                       100),
        ('skills.manage',            'Manage agent skills',              'People',         'Create and assign agent skills.',                              110),
        ('locations.manage',         'Manage locations',                 'Organization',   'Create, edit and delete locations / sites.',                   120),
        ('automations.manage',       'Manage automations & escalations', 'Automation',     'Configure automation rules, escalations, stale-ticket and scheduled-report jobs.', 130),
        ('csat.manage',              'Manage CSAT surveys',              'Automation',     'Configure customer satisfaction surveys.',                     140),
        ('ai.manage',                'Manage AI classification',         'Automation',     'Configure AI auto-classification and routing.',                150),
        ('import.manage',            'Import data & manage backups',     'Data',           'Import tickets/users/KB and create or restore backups.',       160),
        ('audit.view',               'View the audit log',               'System',         'Read and prune the security audit log.',                       170),
        ('settings.manage',          'Manage general settings',          'Settings',       'Email/SMTP, SSO, branding, labels, tags, canned responses, business hours, holidays and organization settings.', 180)");

    // admin bypasses every permission (is_admin), so it needs no grants.
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, perm_key)
        SELECT r.id, p.perm_key FROM roles r CROSS JOIN (
            SELECT 'ticket_templates.manage' AS perm_key
            UNION ALL SELECT 'recurring_tickets.manage'
        ) p WHERE r.slug IN ('agent','power_user')");
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, perm_key)
        SELECT id, p.perm_key FROM roles, (
            SELECT 'reports.view' AS perm_key
            UNION ALL SELECT 'tickets.view_all'
        ) p WHERE roles.slug = 'power_user'");
    echo "[OK] 4 roles + 19 permissions + grants seeded.\n";

    // ── Locations ────────────────────────────────────────────────
    $locStmt = $pdo->prepare('INSERT INTO locations (name, address, description) VALUES (?, ?, ?)');
    $locations = [
        ['Main Library', '100 Main St, Anytown',  'Central branch and administrative offices.'],
        ['East Branch',  '200 East Ave, Anytown', 'Eastside community branch.'],
        ['West Branch',  '300 West Rd, Anytown',  'Westside community branch.'],
    ];
    foreach ($locations as $loc) {
        $locStmt->execute($loc);
    }
    echo "[OK] 3 locations seeded.\n";

    // ── Users ────────────────────────────────────────────────────
    $userStmt = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, password, role, work_phone, location_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $users = [
        ['Admin', 'User',  'admin@localdesk.user', $adminPw, 'admin', '519-555-0001', 1],
        ['Agent', 'User',  'agent@localdesk.user', $agentPw, 'agent', '519-555-0002', 1],
        ['End',   'User',  'user@localdesk.user',  $userPw,  'user',  '519-555-0003', 2],
    ];
    foreach ($users as [$fn, $ln, $em, $pw, $role, $phone, $locId]) {
        $userStmt->execute([$fn, $ln, $em, password_hash($pw, PASSWORD_DEFAULT), $role, $phone, $locId]);
    }
    echo "[OK] 3 users seeded.\n";

    // ── Priorities ───────────────────────────────────────────────
    $priStmt = $pdo->prepare('INSERT INTO ticket_priorities (name, color, sort_order) VALUES (?, ?, ?)');
    $priorities = [
        ['Low',      '#198754', 1],
        ['Medium',   '#ffc107', 2],
        ['High',     '#fd7e14', 3],
        ['Critical', '#dc3545', 4],
    ];
    foreach ($priorities as $p) {
        $priStmt->execute($p);
    }
    echo "[OK] 4 priorities seeded.\n";

    // ── Ticket Types ───────────────────────────────────────────────
    $typeStmt = $pdo->prepare('INSERT INTO ticket_types (name, sort_order) VALUES (?, ?)');
    $types = [
        ['IT', 1],
        ['Marketing', 2],
        ['Customer Experience/Circulation', 3],
        ['Facilities', 4],
        ['Collections / Discover Job Requests', 5],
        ['Lifelong Learning', 6],
    ];
    foreach ($types as $type) {
        $typeStmt->execute($type);
    }
    echo "[OK] " . count($types) . " ticket types seeded.\n";

    // ── Tags ─────────────────────────────────────────────────────
    $tagStmt = $pdo->prepare('INSERT INTO ticket_tags (name) VALUES (?)');
    $tags = ['hardware', 'software', 'network', 'account', 'printing', 'email', 'vpn', 'password-reset'];
    foreach ($tags as $tag) {
        $tagStmt->execute([$tag]);
    }
    echo "[OK] " . count($tags) . " tags seeded.\n";

    // ── Groups ─────────────────────────────────────────────────────
    $grpStmt = $pdo->prepare('INSERT INTO `groups` (name, description, sort_order) VALUES (?, ?, ?)');
    $groups = [
        ['IT',                'Information Technology support and infrastructure.',            1],
        ['Collections',       'Library collections, cataloguing and acquisitions.',            2],
        ['Facilities',        'Building maintenance, safety and facilities management.',       3],
        ['Lifelong Learning', 'Programming, outreach and lifelong learning initiatives.',      4],
        ['Marketing',         'Communications, marketing and public relations.',               5],
        ['Circulation',       'Front-desk services, holds and circulation operations.',        6],
    ];
    foreach ($groups as $g) {
        $grpStmt->execute($g);
    }
    echo "[OK] " . count($groups) . " groups seeded.\n";

    // ── Group ↔ user mappings ──────────────────────────────────────
    $gumStmt = $pdo->prepare('INSERT INTO group_user_map (group_id, user_id) VALUES (?, ?)');
    $groupMappings = [[1, 1], [1, 2], [3, 1], [6, 2]]; // Admin in IT+Facilities, Agent in IT+Circulation
    foreach ($groupMappings as [$gid, $uid]) {
        $gumStmt->execute([$gid, $uid]);
    }
    echo "[OK] Group memberships seeded.\n";

    // ── Sample Tickets ───────────────────────────────────────────
    $ticketStmt = $pdo->prepare(
        'INSERT INTO tickets (subject, description, browser_info, os_info, created_by, due_date, type_id, location_id, status, priority_id, assigned_to, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $sampleTickets = [
        [
            'Cannot connect to WiFi in meeting room 3',
            "I've been unable to connect to the guest WiFi network in Meeting Room 3 since Monday morning. My laptop sees the network but cannot obtain an IP address. Other staff have the same issue.",
            'Chrome 120.0.6099.130', 'Windows 11 Pro 23H2',
            3, '2026-02-18', 1, 1, 'open', 3, 2, '2026-02-10 09:15:00',
        ],
        [
            'Printer on 2nd floor jamming repeatedly',
            "The HP LaserJet on the 2nd floor jams every 5-10 pages. I've cleared the paper path multiple times but it keeps happening. Tray 2 seems to be the culprit.",
            'Firefox 122.0', 'macOS 14.3 Sonoma',
            3, '2026-02-14', 4, 1, 'in_progress', 2, 2, '2026-02-08 14:30:00',
        ],
        [
            'Need VPN access for remote work',
            "I've been approved for remote work starting next week and need VPN credentials set up. My manager (Jane Smith) has already approved this. Please set up my account.",
            'Edge 121.0.2277.83', 'Windows 10 Enterprise 22H2',
            3, '2026-02-20', 1, 2, 'open', 1, null, '2026-02-11 11:00:00',
        ],
        [
            'Email not syncing on mobile device',
            "My work email stopped syncing on my iPhone since the last iOS update. I can still access it via webmail. I've tried removing and re-adding the account.",
            'Safari 17.3', 'iOS 17.3.1',
            3, '2026-02-15', 1, 2, 'resolved', 2, 2, '2026-02-05 08:45:00',
        ],
        [
            'Software license expired for Adobe Creative Suite',
            "Adobe Creative Suite is showing a license expiration notice on my workstation. I need this for the marketing materials we're producing this month.",
            'Chrome 120.0.6099.216', 'Windows 11 Pro 23H2',
            3, '2026-02-13', 2, 1, 'closed', 4, 2, '2026-02-01 10:20:00',
        ],
        [
            'New employee workstation setup',
            "We have a new hire starting on Feb 24. They will need a full workstation setup at the East Branch: computer, monitors, phone, and all standard software.",
            'Chrome 121.0.6167.85', 'Windows 11 Pro 23H2',
            3, '2026-02-24', 1, 2, 'open', 2, null, '2026-02-12 09:00:00',
        ],
    ];
    foreach ($sampleTickets as $t) {
        $ticketStmt->execute($t);
    }
    echo "[OK] 6 sample tickets seeded.\n";

    // ── Ticket-tag mappings ──────────────────────────────────────
    $mapStmt = $pdo->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)');
    $mappings = [[1,3],[1,1],[2,1],[2,5],[3,7],[3,4],[4,6],[5,2],[6,1]];
    foreach ($mappings as [$tid, $tagid]) {
        $mapStmt->execute([$tid, $tagid]);
    }
    echo "[OK] Ticket tags mapped.\n";

    // ── Timeline entries ─────────────────────────────────────────
    // is_internal: 0 = public (visible to everyone), 1 = internal (agents/admins only)
    $tlStmt = $pdo->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $timeline = [
        [1, 3, 'created',  'Ticket created.',                                       0, '2026-02-10 09:15:00'],
        [2, 3, 'created',  'Ticket created.',                                       0, '2026-02-08 14:30:00'],
        [2, 2, 'assigned', 'Ticket assigned to Agent User.',                        0, '2026-02-08 15:00:00'],
        [2, 2, 'status_changed', 'Status changed from Open to In Progress.',        0, '2026-02-08 15:01:00'],
        [2, 2, 'comment',  'Inspected the printer. Ordering replacement rollers.',  0, '2026-02-09 10:00:00'],
        [2, 2, 'comment',  'Internal: Rollers are on backorder, ETA 2 weeks. @Admin User can you approve the rush order?', 1, '2026-02-09 10:30:00'],
        [3, 3, 'created',  'Ticket created.',                                       0, '2026-02-11 11:00:00'],
        [4, 3, 'created',  'Ticket created.',                                       0, '2026-02-05 08:45:00'],
        [4, 2, 'assigned', 'Ticket assigned to Agent User.',                        0, '2026-02-05 09:00:00'],
        [4, 2, 'comment',  'Reconfigured Exchange ActiveSync profile. Email syncing now.', 0, '2026-02-05 11:30:00'],
        [4, 2, 'status_changed', 'Status changed from Open to Resolved.',           0, '2026-02-05 11:31:00'],
        [5, 3, 'created',  'Ticket created.',                                       0, '2026-02-01 10:20:00'],
        [5, 1, 'assigned', 'Ticket assigned to Agent User.',                        0, '2026-02-01 10:30:00'],
        [5, 1, 'priority_changed', 'Priority changed from High to Critical.',       0, '2026-02-01 10:31:00'],
        [5, 2, 'comment',  'Renewed license through Adobe admin portal.',           0, '2026-02-02 09:00:00'],
        [5, 2, 'status_changed', 'Status changed from Open to Closed.',             0, '2026-02-02 09:05:00'],
        [6, 3, 'created',  'Ticket created.',                                       0, '2026-02-12 09:00:00'],
    ];
    foreach ($timeline as $tl) {
        $tlStmt->execute($tl);
    }
    echo "[OK] Timeline entries seeded.\n";

    // ── Sample notification (from the @mention in the internal note above)
    // Timeline entry #6 is the internal note on ticket #2 mentioning Admin User
    $pdo->prepare(
        'INSERT INTO notifications (user_id, ticket_id, timeline_id, mentioned_by) VALUES (?, ?, ?, ?)'
    )->execute([1, 2, 6, 2]); // Admin User notified, ticket #2, timeline #6, mentioned by Agent User
    echo "[OK] Sample notification seeded.\n";

    echo "\nAll done! Database fully seeded.\n";

} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
