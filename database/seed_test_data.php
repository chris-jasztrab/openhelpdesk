<?php
/**
 * Comprehensive test data seeder — replaces all data with two months of test tickets.
 *
 * ┌─────────────────────────────────────────────────────────────────────┐
 * │  DEV / DEMO SEEDER — LOCAL USE ONLY.                                │
 * │  This script DROPS every table and inserts demo data with           │
 * │  well-known credentials (e.g. admin@localdesk.user / Password123!). │
 * │  NEVER run it against a production database.                        │
 * │  The real administrator account is created by the web installer    │
 * │  (public/install/), not by this file.                              │
 * └─────────────────────────────────────────────────────────────────────┘
 *
 * Usage: php database/seed_test_data.php
 */
declare(strict_types=1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/helpers.php';
loadEnv(ROOT_DIR . '/.env');

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s', env('DB_HOST','127.0.0.1'), env('DB_PORT','3306')),
    env('DB_USER','root'), env('DB_PASS',''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$db = env('DB_NAME','localdesk');
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$db}`");

// ── Drop all tables ───────────────────────────────────────────────
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
    'audit_log','scheduled_reports','csat_surveys','escalation_log','escalation_rules',
    'automations','kb_article_revisions','kb_article_ratings','kb_articles','kb_folders',
    'kb_categories','notifications','ticket_attachments','ticket_tag_map','ticket_field_values',
    'ticket_form_field_options','ticket_form_fields','ticket_presence','ticket_timeline',
    'ticket_cc','ticket_watchers','ticket_templates','tickets','ticket_tags','ticket_types',
    'ticket_priorities','group_user_map','groups','sla_policies','holidays',
    'canned_responses','saved_filters','settings','users','locations',
] as $t) { $pdo->exec("DROP TABLE IF EXISTS `{$t}`"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
// Apply schema with FK checks off so table-creation order doesn't matter
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec(file_get_contents(ROOT_DIR . '/database/schema.sql'));
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "[OK] Schema applied.\n";

// ── Locations (1=Main, 2=East, 3=McCormick) ───────────────────────
$pdo->exec("INSERT INTO locations (name,address,description) VALUES
 ('Main Library','100 Main St, Anytown','Central branch and administrative offices.'),
 ('East Branch','200 East Ave, Anytown','Eastside community branch.'),
 ('West Branch','300 West Rd, Anytown','Westside community branch.')");

// ── Users ─────────────────────────────────────────────────────────
// 1=Admin  2=AgentUser  3=EndUser  4=Sarah(agent)  5=Mike(agent)  6=Emma(agent)
// 7=John   8=Jane       9=Robert   10=Lisa          11=David       12=Patricia  13=Tom  14=Mary
$pw = password_hash('Password123!', PASSWORD_DEFAULT);
$us = $pdo->prepare('INSERT INTO users (first_name,last_name,email,password,role,work_phone,location_id) VALUES(?,?,?,?,?,?,?)');
foreach ([
    ['Admin',    'User',      'admin@localdesk.user',  $pw,'admin','519-555-0001',1],
    ['Agent',    'User',      'agent@localdesk.user',  $pw,'agent','519-555-0002',1],
    ['End',      'User',      'user@localdesk.user',   $pw,'user', null,          2],
    ['Sarah',    'Chen',      'sarah.chen@example.com',     $pw,'agent','519-555-0010',1],
    ['Mike',     'Rodriguez', 'mike.rodriguez@example.com', $pw,'agent','519-555-0011',2],
    ['Emma',     'Wilson',    'emma.wilson@example.com',    $pw,'agent','519-555-0012',3],
    ['John',     'Smith',     'john.smith@patron.ca',  $pw,'user', null,          1],
    ['Jane',     'Doe',       'jane.doe@patron.ca',    $pw,'user', null,          2],
    ['Robert',   'Johnson',   'robert.j@patron.ca',    $pw,'user', null,          3],
    ['Lisa',     'Thompson',  'lisa.t@patron.ca',      $pw,'user', null,          1],
    ['David',    'Brown',     'david.b@patron.ca',     $pw,'user', null,          2],
    ['Patricia', 'Garcia',    'patricia.g@patron.ca',  $pw,'user', null,          1],
    ['Tom',      'Wilson',    'tom.w@patron.ca',       $pw,'user', null,          3],
    ['Mary',     'Anderson',  'mary.a@patron.ca',      $pw,'user', null,          2],
] as $u) { $us->execute($u); }
echo "[OK] 14 users seeded.\n";

// ── Priorities (1=Low 2=Medium 3=High 4=Critical) ─────────────────
$pdo->exec("INSERT INTO ticket_priorities (name,color,sort_order) VALUES
 ('Low','#198754',1),('Medium','#ffc107',2),('High','#fd7e14',3),('Critical','#dc3545',4)");

// ── Ticket Types (1=IT 2=Mktg 3=CustExp 4=Fac 5=Coll 6=LL) ──────
$pdo->exec("INSERT INTO ticket_types (name,sort_order) VALUES
 ('IT',1),('Marketing',2),('Customer Experience/Circulation',3),
 ('Facilities',4),('Collections / Discover Job Requests',5),('Lifelong Learning',6)");

// ── Tags ──────────────────────────────────────────────────────────
$pdo->exec("INSERT INTO ticket_tags (name) VALUES
 ('hardware'),('software'),('network'),('account'),('printing'),
 ('email'),('vpn'),('password-reset'),('scanner'),('catalog'),
 ('wifi'),('hvac'),('license'),('remote-access'),('security')");

// ── Groups (1=IT 2=Coll 3=Fac 4=LL 5=Mktg 6=Circ) ───────────────
$pdo->exec("INSERT INTO `groups` (name,description,sort_order) VALUES
 ('IT','Information Technology support and infrastructure.',1),
 ('Collections','Library collections, cataloguing and acquisitions.',2),
 ('Facilities','Building maintenance, safety and facilities management.',3),
 ('Lifelong Learning','Programming, outreach and lifelong learning initiatives.',4),
 ('Marketing','Communications, marketing and public relations.',5),
 ('Circulation','Front-desk services, holds and circulation operations.',6)");
$gm = $pdo->prepare('INSERT INTO group_user_map (group_id,user_id) VALUES (?,?)');
foreach ([[1,1],[1,2],[1,4],[2,6],[3,5],[4,6],[5,1],[6,6],[6,2]] as $m) { $gm->execute($m); }
echo "[OK] Groups, types, tags seeded.\n";

// ── SLA Policies (Low 8hr/48hr  Medium 4hr/24hr  High 1hr/8hr  Crit 15min/2hr) ──
$pdo->exec("INSERT INTO sla_policies (priority_id,first_response_minutes,resolution_minutes) VALUES
 (1,480,2880),(2,240,1440),(3,60,480),(4,15,120)");

// ── Holidays ──────────────────────────────────────────────────────
$pdo->exec("INSERT INTO holidays (holiday_date,name,exclude_from_sla) VALUES
 ('2025-12-25','Christmas Day',1),('2025-12-26','Boxing Day',1),
 ('2026-01-01','New Year\'s Day',1),('2026-02-16','Family Day (Ontario)',1),
 ('2026-05-18','Victoria Day',1),('2026-07-01','Canada Day',1)");

// ── Settings ──────────────────────────────────────────────────────
$bh = json_encode([
    'mon'=>['09:00','17:00'],'tue'=>['09:00','17:00'],'wed'=>['09:00','17:00'],
    'thu'=>['09:00','17:00'],'fri'=>['09:00','17:00'],'sat'=>null,'sun'=>null,
]);
$si = $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?)');
foreach ([
    ['app_name',               'Example Library Help Desk'],
    ['branding_app_name',      'Example Help Desk'],
    ['branding_primary_color', '#005a9c'],
    ['branding_primary_hover', '#004a7c'],
    ['branding_navbar_start',  '#003d6b'],
    ['branding_navbar_end',    '#005a9c'],
    ['branding_navbar_icon',   'bi-book'],
    ['business_hours_timezone','America/Toronto'],
    ['business_hours_schedule',$bh],
    ['csat_enabled',           '1'],
    ['csat_trigger_status',    'resolved'],
    ['show_onboarding',        '0'],
    ['email_footer_text',      'This message was sent by the Example Library Help Desk.'],
    ['email_subject_ticket_created',  '[#{{ticket_id}}] Request received – {{ticket_subject}}'],
    ['email_intro_ticket_created',    'Hi {{customer_first_name}}, we received your request and will be in touch shortly.'],
    ['email_button_ticket_created',   'View Your Ticket'],
    ['email_subject_ticket_updated',  '[#{{ticket_id}}] Update – {{ticket_subject}}'],
    ['email_intro_ticket_updated',    'Hi {{customer_first_name}}, there has been an update on your ticket.'],
    ['email_button_ticket_updated',   'View Update'],
    ['email_subject_ticket_resolved', '[#{{ticket_id}}] Resolved – {{ticket_subject}}'],
    ['email_intro_ticket_resolved',   'Hi {{customer_first_name}}, your request has been resolved by {{agent_full_name}}.'],
    ['email_button_ticket_resolved',  'View Ticket'],
] as [$k,$v]) { $si->execute([$k,$v]); }
echo "[OK] SLA, holidays, settings seeded.\n";

// ── Custom Ticket Fields ───────────────────────────────────────────
// id: 1=text  2=dropdown  3=checkbox  4=date  5=number  6=decimal  7=textarea  8=dependent
// (per-type visibility + sort order lives in ticket_type_form_layout, seeded
//  for each existing ticket type when the migration runs at the end of this file)
$pdo->exec("INSERT INTO ticket_form_fields (field_type,label,placeholder) VALUES
 ('text',     'Library Card Number',         'e.g. 29141001234567'),
 ('dropdown', 'IT Category',                 NULL),
 ('checkbox', 'Patron-Facing Issue',         NULL),
 ('date',     'Target Resolution Date',      NULL),
 ('number',   'Affected Users Count',        'e.g. 5'),
 ('decimal',  'Estimated Cost (\$)',         'e.g. 150.00'),
 ('textarea', 'Vendor / Supplier Notes',     ''),
 ('dependent','Location > Area > Workstation',NULL)");
// Dropdown options (field 2) → ids 1–6
$pdo->exec("INSERT INTO ticket_form_field_options (field_id,label,sort_order) VALUES
 (2,'Hardware',1),(2,'Software',2),(2,'Network',3),(2,'Account Management',4),(2,'Printing',5),(2,'Other',6)");
// Dependent L1 (field 8) → ids 7–9
$pdo->exec("INSERT INTO ticket_form_field_options (field_id,label,sort_order) VALUES
 (8,'Main Library',1),(8,'East Branch',2),(8,'McCormick Branch',3)");
// Dependent L2 (parents 7,8,9) → ids 10–16
$pdo->exec("INSERT INTO ticket_form_field_options (field_id,parent_option_id,label,sort_order) VALUES
 (8,7,'Public Floor',1),(8,7,'Staff Area',2),(8,7,'IT Room',3),
 (8,8,'Public Floor',1),(8,8,'Staff Area',2),
 (8,9,'Public Floor',1),(8,9,'Staff Area',2)");
// Dependent L3 (parents 10–16) → ids 17–30
$pdo->exec("INSERT INTO ticket_form_field_options (field_id,parent_option_id,label,sort_order) VALUES
 (8,10,'Desk 1',1),(8,10,'Desk 2',2),(8,10,'Desk 3',3),
 (8,11,'Desk 1',1),(8,11,'Desk 2',2),
 (8,12,'Server Rack A',1),(8,12,'Server Rack B',2),
 (8,13,'Desk 1',1),(8,13,'Desk 2',2),
 (8,14,'Desk 1',1),(8,14,'Desk 2',2),
 (8,15,'Desk 1',1),(8,15,'Desk 2',2),
 (8,16,'Desk 1',1)");
echo "[OK] Custom fields seeded.\n";

// ── Canned Responses ──────────────────────────────────────────────
$pdo->exec("INSERT INTO canned_responses (user_id,title,body,sort_order) VALUES
 (NULL,'Acknowledgement – Will Investigate',
  'Hi {{customer_first_name}},\n\nThank you for contacting the {{org_name}} Help Desk. We have received your request and will be in touch shortly.\n\nRegards,\n{{agent_full_name}}',1),
 (NULL,'Request for More Information',
  'Hi {{customer_first_name}},\n\nTo progress ticket #{{ticket_id}} we need some additional details. Please reply with the requested information at your earliest convenience.\n\nThank you,\n{{agent_full_name}}',2),
 (NULL,'Issue Resolved',
  'Hi {{customer_first_name}},\n\nTicket #{{ticket_id}} (\"{{ticket_subject}}\") has now been resolved. If you experience any further issues please do not hesitate to submit a new request.\n\nKind regards,\n{{agent_full_name}}\n{{org_name}} Help Desk',3),
 (NULL,'Escalation Notice',
  'Hi {{customer_first_name}},\n\nYour ticket #{{ticket_id}} requires specialist attention and has been escalated. You will receive an update within one business day.\n\n{{agent_full_name}}',4),
 (NULL,'Waiting on Third Party',
  'Hi {{customer_first_name}},\n\nWe are currently waiting on our vendor/supplier to resolve ticket #{{ticket_id}}. We will update you as soon as we have news.\n\n{{agent_full_name}}',5),
 (2,'Quick Password Reset Note',
  'Hi {{customer_first_name}},\n\nYour password has been reset. Please check your email for the temporary credentials and change your password on first login.\n\n{{agent_first_name}}',1),
 (4,'Workstation Setup Info Request',
  'Hi,\n\nFor the workstation setup I will need the following confirmed: user account name, required software list, location and desk number, and phone extension if applicable.\n\nThanks, Sarah',1),
 (5,'Facilities Inspection Complete',
  'Site inspection completed. Issue has been logged for maintenance scheduling. Ticket will be updated once the work order is processed.\n\n– Mike Rodriguez, Facilities',1)");

// ── Ticket Templates ──────────────────────────────────────────────
$pdo->exec("INSERT INTO ticket_templates (name,description,subject,body,type_id,priority_id,is_shared,created_by) VALUES
 ('New Staff Onboarding','Template for new employee setup.',
  'New staff setup – [Employee Name]',
  '**Name:** [Full Name]\n**Start Date:** [Date]\n**Location:** [Branch]\n\n- [ ] Workstation\n- [ ] Network account\n- [ ] Email\n- [ ] Required software\n- [ ] Phone extension\n- [ ] Building access card',
  1,2,1,1),
 ('Equipment Repair Request','For broken or damaged equipment.',
  'Equipment repair required – [Equipment Name]',
  '**Equipment:** [Name/Model]\n**Location:** [Branch/Room]\n**Issue:** [Describe problem]\n**Affected users:** [Count]\n**Asset number:** [If known]',
  4,2,1,1),
 ('Software Access Request','For new software or license requests.',
  'Software access request – [Software Name]',
  '**Software:** [Name]\n**Justification:** [Business reason]\n**Users:** [Who needs access]\n**Approved by:** [Manager]\n**Required by:** [Date]',
  1,1,1,1)");

// ── Escalation Rules ──────────────────────────────────────────────
$pdo->exec("INSERT INTO escalation_rules (name,conditions,actions,cooldown_hours,is_enabled,sort_order) VALUES
 ('High/Critical unassigned > 2 hours',
  '[{\"field\":\"priority\",\"op\":\"in\",\"value\":[\"3\",\"4\"]},{\"field\":\"assigned_to\",\"op\":\"empty\",\"value\":\"\"},{\"field\":\"age_minutes\",\"op\":\"gte\",\"value\":\"120\"}]',
  '[{\"type\":\"notify_admin\",\"value\":\"A High/Critical ticket has been unassigned for over 2 hours.\"}]',
  4,1,1),
 ('SLA Warning – action required',
  '[{\"field\":\"sla_state\",\"op\":\"eq\",\"value\":\"warning\"},{\"field\":\"status\",\"op\":\"not_in\",\"value\":[\"resolved\",\"closed\"]}]',
  '[{\"type\":\"notify_assigned\",\"value\":\"SLA warning: your ticket is approaching its resolution deadline. Please action immediately.\"}]',
  2,1,2)");

// ── Automations ───────────────────────────────────────────────────
$pdo->exec("INSERT INTO automations (name,trigger_event,conditions,actions,is_enabled,sort_order) VALUES
 ('Auto-assign IT tickets to IT group','ticket_created',
  '[{\"field\":\"type_id\",\"operator\":\"eq\",\"value\":\"1\"}]',
  '[{\"action\":\"set_group\",\"value\":\"1\"}]',1,1),
 ('Auto-close resolved tickets after 7 days','ticket_updated',
  '[{\"field\":\"status\",\"operator\":\"eq\",\"value\":\"resolved\"},{\"field\":\"age_since_resolved_days\",\"operator\":\"gte\",\"value\":\"7\"}]',
  '[{\"action\":\"set_status\",\"value\":\"closed\"}]',1,2)");
echo "[OK] Canned responses, templates, escalations, automations seeded.\n";

// ── Knowledge Base ────────────────────────────────────────────────
$pdo->exec("INSERT INTO kb_categories (name,slug,description,is_public,sort_order) VALUES
 ('IT Self-Help','it-self-help','Common IT issues you can resolve yourself.',1,1),
 ('Library Services','library-services','Information about library services and policies.',1,2),
 ('Staff Resources','staff-resources','Internal guides for library staff only.',0,3)");
$pdo->exec("INSERT INTO kb_folders (category_id,name,slug,sort_order) VALUES
 (1,'Account & Password','account-password',1),(1,'Network & WiFi','network-wifi',2),
 (2,'Borrowing & Holds','borrowing-holds',1),(2,'Digital Resources','digital-resources',2),
 (3,'IT Procedures','it-procedures',1),(3,'HR & Administration','hr-admin',2)");
$lbody = "## Overview\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\n\n## Steps\n\n1. Lorem ipsum dolor sit amet\n2. Consectetur adipiscing elit\n3. Sed do eiusmod tempor incididunt\n4. Ut labore et dolore magna aliqua\n\n## Notes\n\nUt enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.";
$art = $pdo->prepare('INSERT INTO kb_articles (folder_id,title,slug,body_markdown,status,published_at,created_by,sort_order) VALUES(?,?,?,?,?,?,?,?)');
foreach ([
    [1,'How to Reset Your Password',      'reset-password',         $lbody,'published','2025-11-01 09:00:00',1,1],
    [1,'Setting Up Multi-Factor Auth',    'setup-mfa',              $lbody,'published','2025-11-15 10:00:00',1,2],
    [2,'Connecting to Library WiFi',      'connect-library-wifi',   $lbody,'published','2025-12-01 09:00:00',4,1],
    [3,'How to Place a Hold',             'place-a-hold',           $lbody,'published','2025-10-15 09:00:00',1,1],
    [4,'Accessing OverDrive / Libby',     'access-overdrive',       $lbody,'published','2025-10-20 10:00:00',1,1],
    [5,'New Workstation Setup Checklist', 'workstation-setup',      $lbody,'published','2025-09-01 09:00:00',1,1],
    [5,'Printer Troubleshooting Guide',   'printer-troubleshooting',$lbody,'published','2025-09-15 09:00:00',4,2],
    [6,'Vacation Request Process',        'vacation-request',       $lbody,'draft',    null,                 1,1],
] as $a) { $art->execute($a); }
$pdo->exec("INSERT INTO kb_article_ratings (article_id,user_id,rating) VALUES
 (1,7,1),(1,8,1),(1,9,1),(1,10,-1),(1,12,1),
 (2,7,1),(2,11,1),(2,13,-1),
 (3,8,1),(3,9,1),(3,10,1),
 (4,7,1),(4,12,1),(4,13,1),(4,14,-1),
 (5,8,1),(5,11,1),(5,9,1),
 (6,1,1),(6,2,1),(6,4,1),(6,5,-1),
 (7,2,1),(7,4,1)");
$pdo->exec("INSERT INTO kb_article_revisions (article_id,title,body_markdown,edited_by) VALUES
 (1,'How to Reset Your Password','## Original draft\n\nLorem ipsum dolor sit amet, consectetur adipiscing elit.',1),
 (6,'New Workstation Setup Checklist','## Initial draft\n\nLorem ipsum dolor sit amet.',4)");
echo "[OK] Knowledge base seeded.\n";

// ════════════════════════════════════════════════════════════════════
// ── TICKETS ──────────────────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════
// Columns: subject, created_by, assigned_to, group_id, type_id, location_id,
//          priority_id, status, created_at,
//          first_responded_at, first_response_due_at, resolution_due_at, sla_state
// Agents: 2=AgentUser  4=Sarah(IT)  5=Mike(Facilities)  6=Emma(Circ/Coll/LL)
// Users:  7=John  8=Jane  9=Robert  10=Lisa  11=David  12=Patricia  13=Tom  14=Mary
// Ticket 17 will be merged into ticket 38 (newsletters)
// Ticket 21 will be merged into ticket 16 (laptop slow)
// Ticket 61 = split from ticket 10 | Ticket 62 = split from ticket 19
$lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation.';

$ticketData = [
 // ── January 2026 ─────────────────────────────────────────────────
 /*01*/['WiFi down in main floor meeting room',          7, 4,1,1,1,3,'resolved',        '2026-01-02 09:00:00','2026-01-02 10:00:00','2026-01-02 10:00:00','2026-01-02 17:00:00','on_track'],
 /*02*/['Printer jamming repeatedly on 1st floor',       8, 2,1,1,1,2,'resolved',        '2026-01-05 10:00:00','2026-01-05 13:30:00','2026-01-05 14:00:00','2026-01-08 10:00:00','on_track'],
 /*03*/['ILS catalog login failure for staff',           9, 4,1,1,2,3,'resolved',        '2026-01-06 09:30:00','2026-01-06 10:00:00','2026-01-06 10:30:00','2026-01-06 17:30:00','on_track'],
 /*04*/['Hold pickup notification not sending',         10, 6,6,3,1,2,'resolved',        '2026-01-07 11:00:00','2026-01-07 14:00:00','2026-01-07 15:00:00','2026-01-09 11:00:00','on_track'],
 /*05*/['Broken chair in public reading area',          11, 5,3,4,3,1,'in_progress',     '2026-01-08 14:00:00','2026-01-09 09:00:00','2026-01-09 13:00:00','2026-01-20 14:00:00','on_track'],
 /*06*/['Staff VPN credentials needed for remote work', 12, 2,1,1,2,2,'waiting_on_customer','2026-01-09 09:00:00','2026-01-09 11:00:00','2026-01-09 13:00:00','2026-01-14 09:00:00','on_track'],
 /*07*/['Digital signage display frozen on 2nd floor',  13, 4,1,1,1,3,'resolved',        '2026-01-12 08:30:00','2026-01-12 09:15:00','2026-01-12 09:30:00','2026-01-12 16:30:00','on_track'],
 /*08*/['Outlook email not syncing on staff laptop',    14, 2,1,1,2,2,'resolved',        '2026-01-13 09:15:00','2026-01-13 12:00:00','2026-01-13 13:15:00','2026-01-15 09:15:00','on_track'],
 /*09*/['Barcode scanner errors at circulation desk',    7,null,1,1,1,3,'open',           '2026-01-13 11:00:00',null,                 '2026-01-13 12:00:00','2026-01-13 19:00:00','breached'],
 /*10*/['New staff workstation setup – East Branch',     1, 4,1,1,2,2,'resolved',        '2026-01-14 09:00:00','2026-01-14 10:00:00','2026-01-14 13:00:00','2026-01-16 09:00:00','on_track'],
 /*11*/['Study room online booking system broken',       8, 6,6,3,1,1,'closed',          '2026-01-14 13:00:00','2026-01-15 09:00:00','2026-01-15 08:00:00','2026-01-22 13:00:00','on_track'],
 /*12*/['Server antivirus license expired – urgent',     1, 4,1,1,1,4,'resolved',        '2026-01-15 14:00:00','2026-01-15 17:30:00','2026-01-15 14:15:00','2026-01-15 16:00:00','breached'],
 /*13*/['Network share drive offline for staff',        10, 2,1,1,2,3,'resolved',        '2026-01-15 09:00:00','2026-01-15 10:00:00','2026-01-15 10:00:00','2026-01-15 17:00:00','on_track'],
 /*14*/['Heating not working at East Branch',           11, 5,3,4,2,3,'resolved',        '2026-01-16 08:00:00','2026-01-16 09:00:00','2026-01-16 09:00:00','2026-01-16 16:00:00','on_track'],
 /*15*/['ILS catalog search returning wrong results',   12, 4,1,1,1,4,'resolved',        '2026-01-19 09:00:00','2026-01-19 09:40:00','2026-01-19 09:15:00','2026-01-19 11:00:00','on_track'],
 /*16*/['Staff laptop running very slowly',             13, 2,1,1,3,1,'resolved',        '2026-01-20 10:00:00','2026-01-21 09:00:00','2026-01-20 18:00:00','2026-01-28 10:00:00','on_track'],
 /*17*/['Newsletter mailing list needs updating',       14,null,5,2,1,1,'closed',        '2026-01-20 09:00:00',null,                 '2026-01-20 17:00:00','2026-01-28 09:00:00','on_track'],
 /*18*/['New book display stand needed – East Branch',   7, 6,2,5,2,2,'resolved',        '2026-01-21 11:00:00','2026-01-21 14:00:00','2026-01-21 15:00:00','2026-01-23 11:00:00','on_track'],
 /*19*/['Water pipe leak near server room – Main',       1, 5,3,4,1,4,'resolved',        '2026-01-21 09:00:00','2026-01-21 09:20:00','2026-01-21 09:15:00','2026-01-21 11:00:00','on_track'],
 /*20*/['Password reset request',                        8, 2,1,1,2,1,'resolved',        '2026-01-22 09:30:00','2026-01-22 09:50:00','2026-01-22 17:30:00','2026-01-30 09:30:00','on_track'],
 /*21*/['Bluetooth headset not pairing to computer',     9, 2,1,1,3,1,'resolved',        '2026-01-22 14:00:00','2026-01-23 09:00:00','2026-01-22 22:00:00','2026-01-30 14:00:00','on_track'],
 /*22*/['Online event registration form – 500 error',   10, 4,1,1,1,3,'open',            '2026-01-23 09:00:00',null,                 '2026-01-23 10:00:00','2026-01-23 17:00:00','breached'],
 /*23*/['Accessible computer station not working',      11, 5,3,4,2,2,'resolved',        '2026-01-26 10:00:00','2026-01-26 13:00:00','2026-01-26 14:00:00','2026-01-29 10:00:00','on_track'],
 /*24*/['Library card printer not working',             12, 2,1,1,1,2,'resolved',        '2026-01-26 09:00:00','2026-01-26 11:00:00','2026-01-26 13:00:00','2026-01-29 09:00:00','on_track'],
 /*25*/['Event registration system needs update',       13, 4,1,1,1,3,'in_progress',     '2026-01-27 09:00:00','2026-01-27 10:00:00','2026-01-27 10:00:00','2026-01-27 17:00:00','warning'],
 /*26*/['Staff scheduling software access request',     14, 2,1,1,2,2,'resolved',        '2026-01-27 10:00:00','2026-01-27 13:00:00','2026-01-27 14:00:00','2026-01-30 10:00:00','on_track'],
 /*27*/['HVAC system failure at East Branch',            7, 5,3,4,2,3,'resolved',        '2026-01-28 09:00:00','2026-01-28 10:00:00','2026-01-28 10:00:00','2026-01-28 17:00:00','on_track'],
 /*28*/['OverDrive eBook platform access issues',        8, 4,1,1,1,2,'waiting_on_third_party','2026-01-29 11:00:00','2026-01-29 13:00:00','2026-01-29 15:00:00','2026-02-03 11:00:00','on_track'],
 /*29*/['Printer toner empty on 3rd floor',              9, 2,1,1,1,1,'closed',          '2026-01-29 09:30:00','2026-01-29 11:00:00','2026-01-29 17:30:00','2026-02-06 09:30:00','on_track'],
 /*30*/['Security camera offline at west entrance',     10, 5,3,4,1,3,'resolved',        '2026-01-30 08:30:00','2026-01-30 09:30:00','2026-01-30 09:30:00','2026-01-30 16:30:00','on_track'],
 // ── February 2026 ────────────────────────────────────────────────
 /*31*/['Self-checkout kiosk not working',              11, 4,1,1,1,4,'resolved',        '2026-02-02 09:00:00','2026-02-02 09:30:00','2026-02-02 09:15:00','2026-02-02 11:00:00','on_track'],
 /*32*/['Update library social media accounts',         12,null,5,2,1,1,'closed',        '2026-02-02 11:00:00',null,                 '2026-02-02 19:00:00','2026-02-10 11:00:00','on_track'],
 /*33*/['Children\'s program registration system',      13, 6,4,6,1,2,'resolved',        '2026-02-03 09:00:00','2026-02-03 12:00:00','2026-02-03 13:00:00','2026-02-06 09:00:00','on_track'],
 /*34*/['Computer lab booking system not working',      14, 2,1,1,2,2,'pending',         '2026-02-04 10:00:00','2026-02-04 14:00:00','2026-02-04 14:00:00','2026-02-09 10:00:00','on_track'],
 /*35*/['Supplies closet door mechanism stuck',          7,null,3,4,3,1,'closed',        '2026-02-05 14:00:00',null,                 '2026-02-06 13:00:00','2026-02-17 14:00:00','on_track'],
 /*36*/['Offsite database backup failing',               1, 4,1,1,1,4,'resolved',        '2026-02-05 09:00:00','2026-02-05 09:30:00','2026-02-05 09:15:00','2026-02-05 11:00:00','on_track'],
 /*37*/['Broken chair in staff reading room',            8, 5,3,4,1,1,'resolved',        '2026-02-06 13:00:00','2026-02-09 09:00:00','2026-02-07 08:00:00','2026-02-18 13:00:00','on_track'],
 /*38*/['Monthly newsletter design template needed',     9,null,5,2,1,2,'in_progress',   '2026-02-09 09:00:00',null,                 '2026-02-09 13:00:00','2026-02-16 09:00:00','on_track'],
 /*39*/['Remote desktop setup for new staff hire',      10, 2,1,1,2,3,'resolved',        '2026-02-09 09:00:00','2026-02-09 10:00:00','2026-02-09 10:00:00','2026-02-09 17:00:00','on_track'],
 /*40*/['Book donation sorting and processing',         11, 6,2,5,3,1,'resolved',        '2026-02-10 10:00:00','2026-02-11 09:00:00','2026-02-10 18:00:00','2026-02-18 10:00:00','on_track'],
 /*41*/['Patron complaint – study room noise level',    12, 6,6,3,1,2,'resolved',        '2026-02-11 09:30:00','2026-02-11 11:00:00','2026-02-11 13:30:00','2026-02-13 09:30:00','on_track'],
 /*42*/['Microfilm reader lamp needs replacement',      13, 5,3,4,1,3,'open',            '2026-02-11 11:00:00',null,                 '2026-02-11 12:00:00','2026-02-11 19:00:00','warning'],
 /*43*/['Staff online training platform access',        14, 6,4,6,2,2,'resolved',        '2026-02-12 09:00:00','2026-02-12 12:00:00','2026-02-12 13:00:00','2026-02-16 09:00:00','on_track'],
 /*44*/['Valentine\'s display setup materials',          7,null,2,5,2,1,'closed',        '2026-02-12 13:00:00',null,                 '2026-02-13 08:00:00','2026-02-20 13:00:00','on_track'],
 /*45*/['Network switch failure in server room',         1, 4,1,1,1,4,'resolved',        '2026-02-13 09:00:00','2026-02-13 11:30:00','2026-02-13 09:15:00','2026-02-13 11:00:00','breached'],
 /*46*/['Elevator out of service at East Branch',        8, 5,3,4,2,4,'resolved',        '2026-02-17 09:00:00','2026-02-17 10:00:00','2026-02-17 09:15:00','2026-02-17 11:00:00','on_track'],
 /*47*/['Overdue notice emails not being sent',          9, 2,1,1,1,3,'resolved',        '2026-02-17 09:00:00','2026-02-17 10:00:00','2026-02-17 10:00:00','2026-02-17 17:00:00','on_track'],
 /*48*/['Digital signage content not updating',         10, 4,1,1,3,2,'waiting_on_customer','2026-02-18 10:00:00','2026-02-18 13:00:00','2026-02-18 14:00:00','2026-02-23 10:00:00','on_track'],
 /*49*/['Staff meeting room setup for annual event',    11,null,3,4,1,1,'closed',        '2026-02-19 09:00:00',null,                 '2026-02-19 17:00:00','2026-02-27 09:00:00','on_track'],
 /*50*/['Programming room projector not working',       12, 2,1,1,1,3,'resolved',        '2026-02-19 09:00:00','2026-02-19 10:00:00','2026-02-19 10:00:00','2026-02-19 17:00:00','on_track'],
 /*51*/['New staff member phone extension setup',       13, 4,1,1,2,2,'resolved',        '2026-02-20 10:00:00','2026-02-20 13:00:00','2026-02-20 14:00:00','2026-02-25 10:00:00','on_track'],
 /*52*/['Book drop sensor malfunctioning',              14, 5,3,4,1,3,'pending',         '2026-02-23 09:00:00','2026-02-23 10:00:00','2026-02-23 10:00:00','2026-02-23 17:00:00','on_track'],
 /*53*/['HR payroll system access issue',                7, 2,1,1,1,3,'resolved',        '2026-02-23 09:00:00','2026-02-23 10:00:00','2026-02-23 10:00:00','2026-02-23 17:00:00','on_track'],
 /*54*/['Community board digital posting request',       8,null,5,2,2,1,'closed',        '2026-02-24 11:00:00',null,                 '2026-02-24 19:00:00','2026-03-04 11:00:00','on_track'],
 /*55*/['Tablet charging station not working',           9, 5,3,4,3,2,'resolved',        '2026-02-24 09:00:00','2026-02-24 12:00:00','2026-02-24 13:00:00','2026-02-27 09:00:00','on_track'],
 /*56*/['Database queries running extremely slow',       1, 4,1,1,1,4,'resolved',        '2026-02-25 09:00:00','2026-02-25 09:20:00','2026-02-25 09:15:00','2026-02-25 11:00:00','on_track'],
 /*57*/['Staff handbook digital version update',        10,null,5,2,1,1,'in_progress',   '2026-02-25 09:00:00',null,                 '2026-02-25 17:00:00','2026-03-06 09:00:00','on_track'],
 /*58*/['Self-checkout receipt paper empty',            11, 2,1,1,1,1,'resolved',        '2026-02-26 09:30:00','2026-02-26 10:00:00','2026-02-26 17:30:00','2026-03-10 09:30:00','on_track'],
 /*59*/['Wireless printer setup on 3rd floor',          12, 2,1,1,2,2,'resolved',        '2026-02-26 10:00:00','2026-02-26 14:00:00','2026-02-26 14:00:00','2026-03-03 10:00:00','on_track'],
 /*60*/['Annual report data export from ILS',           13, 4,1,1,1,2,'open',            '2026-02-27 09:00:00',null,                 '2026-02-27 13:00:00','2026-03-06 09:00:00','on_track'],
 // ── Split tickets ─────────────────────────────────────────────────
 /*61*/['New staff software installation – East Branch', 1, 4,1,1,2,2,'resolved',       '2026-01-15 10:00:00','2026-01-15 13:00:00','2026-01-15 14:00:00','2026-01-19 10:00:00','on_track'],
 /*62*/['Water damage assessment and remediation',       1, 5,3,4,1,3,'resolved',        '2026-01-21 10:00:00','2026-01-21 11:00:00','2026-01-21 11:00:00','2026-01-21 17:00:00','on_track'],
];

$tStmt = $pdo->prepare(
    'INSERT INTO tickets (subject,description,created_by,assigned_to,group_id,type_id,location_id,priority_id,status,created_at,first_responded_at,first_response_due_at,resolution_due_at,sla_state) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
foreach ($ticketData as $t) {
    [$subj,$cb,$at,$grp,$tp,$loc,$pri,$st,$ts,$fr,$frd,$rd,$sla] = $t;
    $tStmt->execute([$subj,$lorem,$cb,$at,$grp,$tp,$loc,$pri,$st,$ts,$fr,$frd,$rd,$sla]);
}
// Set merged_into_ticket_id: ticket 17 → 38, ticket 21 → 16
$pdo->exec("UPDATE tickets SET merged_into_ticket_id=38 WHERE id=17");
$pdo->exec("UPDATE tickets SET merged_into_ticket_id=16 WHERE id=21");
echo "[OK] 62 tickets inserted.\n";

// ════════════════════════════════════════════════════════════════════
// ── TIMELINE ENTRIES ─────────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════
$lc = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
$lc2 = 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.';
$tlStmt = $pdo->prepare(
    'INSERT INTO ticket_timeline (ticket_id,user_id,action,details,is_internal,created_at) VALUES(?,?,?,?,?,?)'
);
function tl(PDOStatement $s,int $tid,?int $uid,string $act,string $det,int $int,string $ts):void {
    $s->execute([$tid,$uid,$act,$det,$int,$ts]);
}

// Resolution timestamps for resolved/closed tickets [ticket_id => resolved_at]
$resolvedAt = [
     1=>'2026-01-02 15:00:00',  2=>'2026-01-06 14:00:00',  3=>'2026-01-06 13:00:00',
     4=>'2026-01-08 16:00:00',  7=>'2026-01-12 11:00:00',  8=>'2026-01-14 10:00:00',
    10=>'2026-01-16 15:00:00', 11=>'2026-01-15 17:00:00', 12=>'2026-01-15 17:30:00',
    13=>'2026-01-16 14:00:00', 14=>'2026-01-19 11:00:00', 15=>'2026-01-19 10:00:00',
    16=>'2026-01-23 14:00:00', 17=>'2026-01-21 17:00:00', 18=>'2026-01-22 15:00:00',
    19=>'2026-01-21 12:00:00', 20=>'2026-01-22 10:30:00', 21=>'2026-01-23 11:00:00',
    23=>'2026-01-27 14:00:00', 24=>'2026-01-27 11:00:00', 26=>'2026-01-28 14:00:00',
    27=>'2026-01-30 10:00:00', 29=>'2026-01-30 11:00:00', 30=>'2026-02-02 10:00:00',
    31=>'2026-02-02 09:45:00', 33=>'2026-02-04 15:00:00', 36=>'2026-02-05 09:50:00',
    37=>'2026-02-10 15:00:00', 39=>'2026-02-10 14:00:00', 40=>'2026-02-11 11:00:00',
    41=>'2026-02-11 14:00:00', 43=>'2026-02-13 15:00:00', 45=>'2026-02-13 16:00:00',
    46=>'2026-02-18 14:00:00', 47=>'2026-02-18 10:00:00', 50=>'2026-02-20 14:00:00',
    51=>'2026-02-23 11:00:00', 53=>'2026-02-24 13:00:00', 55=>'2026-02-26 10:00:00',
    56=>'2026-02-25 10:30:00', 58=>'2026-02-26 10:30:00', 59=>'2026-02-27 14:00:00',
    61=>'2026-01-19 14:00:00', 62=>'2026-01-26 15:00:00',
];
// Agent map: which agent is assigned per ticket (matches ticketData)
$agentMap = [
     1=>4, 2=>2, 3=>4, 4=>6, 5=>5, 6=>2, 7=>4, 8=>2, 10=>4, 11=>6,
    12=>4,13=>2,14=>5,15=>4,16=>2,18=>6,19=>5,20=>2,21=>2,23=>5,
    24=>2,25=>4,26=>2,27=>5,28=>4,29=>2,30=>5,31=>4,33=>6,34=>2,
    36=>4,37=>5,39=>2,40=>6,41=>6,43=>6,45=>4,46=>5,47=>2,48=>4,
    50=>2,51=>4,52=>5,53=>2,55=>5,56=>4,58=>2,59=>2,61=>4,62=>5,
];
// Creator map [ticket_id => user_id]
$creatorMap = [];
foreach ($ticketData as $i => $t) { $creatorMap[$i+1] = $t[1]; }

// Generate standard timeline for all tickets
foreach ($ticketData as $i => $t) {
    $tid  = $i + 1;
    [$subj,$cb,$at,$grp,$tp,$loc,$pri,$st,$ts,$fr,$frd,$rd,$sla] = $t;
    $agent = $agentMap[$tid] ?? null;

    // created
    tl($tlStmt, $tid, $cb, 'created', 'Ticket created.', 0, $ts);

    // SLA initialized (for tickets with SLA)
    if ($frd !== null) {
        tl($tlStmt, $tid, null, 'sla_initialized', "First response due: {$frd}. Resolution due: {$rd}.", 0, $ts);
    }

    // assigned
    if ($at !== null) {
        $agentName = match($at) { 2=>'Agent User',4=>'Sarah Chen',5=>'Mike Rodriguez',6=>'Emma Wilson',default=>'Agent' };
        $assignTs = date('Y-m-d H:i:s', strtotime($ts) + 1800);
        tl($tlStmt, $tid, 1, 'assigned', "Ticket assigned to {$agentName}.", 0, $assignTs);
    }

    // first response comment
    if ($fr !== null && $at !== null) {
        tl($tlStmt, $tid, $at, 'comment', $lc, 0, $fr);
        // Mark first_response in timeline
        tl($tlStmt, $tid, null, 'first_response', 'First response recorded.', 0, $fr);
    }

    // resolved/closed
    if (isset($resolvedAt[$tid])) {
        $finalStatus = ($st === 'closed') ? 'Closed' : 'Resolved';
        tl($tlStmt, $tid, $at ?? $cb, 'comment', $lc2, 0, date('Y-m-d H:i:s', strtotime($resolvedAt[$tid]) - 1800));
        tl($tlStmt, $tid, $at ?? 1, 'status_changed', "Status changed to {$finalStatus}.", 0, $resolvedAt[$tid]);
    }
}

// Extra timeline entries for richer history on select tickets
// Ticket 2 – priority changed, internal note
tl($tlStmt,  2, 1, 'priority_changed', 'Priority changed from Low to Medium.', 0, '2026-01-05 10:30:00');
tl($tlStmt,  2, 2, 'internal_note', $lc, 1, '2026-01-05 14:00:00');
// Ticket 5 – multiple comments (ongoing)
tl($tlStmt,  5, 5, 'comment', $lc, 0, '2026-01-15 10:00:00');
tl($tlStmt,  5, 5, 'internal_note', $lc2, 1, '2026-01-22 09:00:00');
tl($tlStmt,  5, 5, 'comment', $lc, 0, '2026-01-29 10:00:00');
// Ticket 6 – waiting on customer note
tl($tlStmt,  6, 2, 'status_changed', 'Status changed to Waiting on Customer.', 0, '2026-01-12 09:00:00');
// Ticket 9 – SLA breached note
tl($tlStmt,  9, 1, 'internal_note', 'SLA breached. Escalating to IT lead.', 1, '2026-01-16 09:00:00');
// Ticket 10 – SPLIT event
tl($tlStmt, 10, 1, 'split', 'Ticket split: software installation items moved to ticket #61.', 0, '2026-01-15 09:30:00');
// Ticket 12 – SLA breach note
tl($tlStmt, 12, 1, 'internal_note', 'SLA breached – critical ticket responded to after deadline. Post-incident review required.', 1, '2026-01-15 18:00:00');
// Ticket 16 – merge received from ticket 21
tl($tlStmt, 16, 1, 'comment', 'Ticket #21 (Bluetooth headset issue) has been merged into this ticket as it affects the same workstation.', 0, '2026-01-23 11:30:00');
// Ticket 17 – merged into 38
tl($tlStmt, 17, 1, 'merged', 'Ticket merged into ticket #38.', 0, '2026-01-21 17:00:00');
// Ticket 19 – SPLIT event
tl($tlStmt, 19, 1, 'split', 'Ticket split: remediation/cleanup items moved to ticket #62.', 0, '2026-01-21 10:30:00');
// Ticket 21 – merged into 16
tl($tlStmt, 21, 1, 'merged', 'Ticket merged into ticket #16.', 0, '2026-01-23 11:00:00');
// Ticket 22 – SLA breached, multiple comments
tl($tlStmt, 22, 1, 'internal_note', 'SLA breached. Investigating root cause of 500 error.', 1, '2026-01-30 09:00:00');
tl($tlStmt, 22, 4, 'comment', $lc, 0, '2026-02-06 10:00:00');
// Ticket 25 – SLA warning, extra comment
tl($tlStmt, 25, 4, 'comment', $lc2, 0, '2026-02-03 10:00:00');
tl($tlStmt, 25, 1, 'internal_note', 'Escalation triggered – SLA warning.', 1, '2026-02-10 09:00:00');
// Ticket 28 – waiting on third party update
tl($tlStmt, 28, 4, 'status_changed', 'Status changed to Waiting on Third Party.', 0, '2026-01-30 09:00:00');
tl($tlStmt, 28, 4, 'comment', $lc, 0, '2026-02-10 10:00:00');
// Ticket 34 – pending, extra comment
tl($tlStmt, 34, 2, 'status_changed', 'Status changed to Pending – awaiting patron confirmation.', 0, '2026-02-07 09:00:00');
// Ticket 38 – merged content from ticket 17
tl($tlStmt, 38, 1, 'comment', 'Ticket #17 (newsletter mailing list update) has been merged into this ticket.', 0, '2026-02-09 10:00:00');
// Ticket 42 – SLA warning, ongoing
tl($tlStmt, 42, 1, 'internal_note', 'SLA warning triggered. Parts ordered from supplier.', 1, '2026-02-18 09:00:00');
// Ticket 45 – SLA breach notes
tl($tlStmt, 45, 1, 'internal_note', 'SLA breached – critical network switch failure. All hands response.', 1, '2026-02-13 11:00:00');
tl($tlStmt, 45, 4, 'comment', $lc2, 0, '2026-02-13 13:00:00');
// Ticket 46 – note about Family Day holiday
tl($tlStmt, 46, 4, 'internal_note', 'Note: Feb 16 is Family Day (holiday). SLA clock paused on Feb 16.', 1, '2026-02-17 09:30:00');
// Ticket 52 – pending, extra comment
tl($tlStmt, 52, 5, 'status_changed', 'Status changed to Pending – parts ordered.', 0, '2026-02-24 10:00:00');
// Ticket 61 – split origin noted
tl($tlStmt, 61, 1, 'comment', 'This ticket was split from ticket #10 to handle software installation separately.', 0, '2026-01-15 10:30:00');
// Ticket 62 – split origin noted
tl($tlStmt, 62, 1, 'comment', 'This ticket was split from ticket #19 to manage water damage remediation as a separate work stream.', 0, '2026-01-21 10:30:00');

echo "[OK] Timeline seeded.\n";

// ── @mention notification (ticket 22, internal note mentioning admin) ──
$noteTid = $pdo->query("SELECT id FROM ticket_timeline WHERE ticket_id=9 AND is_internal=1 LIMIT 1")->fetchColumn();
if ($noteTid) {
    $pdo->prepare('INSERT INTO notifications (user_id,ticket_id,timeline_id,mentioned_by) VALUES(?,?,?,?)')->execute([1,9,$noteTid,4]);
}

// ════════════════════════════════════════════════════════════════════
// ── CSAT SURVEYS ─────────────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════
// For all resolved tickets (not closed — closed = no CSAT in this system)
// rating: 1–5 stars; NULL = survey sent but not yet responded
$csatStmt = $pdo->prepare(
    'INSERT INTO csat_surveys (ticket_id,user_id,token,rating,comment,sent_at,responded_at) VALUES(?,?,?,?,?,?,?)'
);
$csatData = [
    // [ticket_id, user_id, rating, comment, sent_offset_hours, responded (bool)]
    [ 1,  7, 5, 'Issue fixed very quickly, great service!',          0, true],
    [ 2,  8, 4, 'Resolved within a day, good communication.',         2, true],
    [ 3,  9, 5, 'Fixed within hours of reporting. Very impressed.',   1, true],
    [ 4, 10, 3, 'Took a couple of days but was eventually resolved.', 1, true],
    [ 7, 13, 5, 'Prompt response and quick fix.',                     1, true],
    [ 8, 14, 4, 'Good communication throughout.',                     2, true],
    [10,  1, 5, 'Sarah set everything up perfectly.',                  1, true],
    [12,  1, 2, 'Critical ticket took too long to respond to.',        0, true],
    [13, 10, 4, 'Network drive back up quickly.',                      1, true],
    [14, 11, 5, 'Mike had the heating working by next morning.',       1, true],
    [15, 12, 5, 'Catalog issue fixed in under an hour. Excellent.',    1, true],
    [16, 13, 3, 'Slow resolution but they got there eventually.',      2, true],
    [18,  7, 4, 'Display stand issue handled well.',                   1, true],
    [19,  1, 5, 'Emergency response was fast and professional.',       0, true],
    [20,  8, 5, 'Password reset done in minutes!',                     0, true],
    [21,  9, null, null,                                               1, false], // no response
    [23, 11, 4, 'Accessible station repaired next day.',               1, true],
    [24, 12, 4, 'Library card printer fixed quickly.',                 1, true],
    [26, 14, 4, 'Access granted same day.',                            1, true],
    [27,  7, 5, 'HVAC fixed by Mike within 2 days.',                   1, true],
    [30, 10, null, null,                                               1, false], // no response
    [31, 11, 5, 'Self-checkout back up within an hour!',               0, true],
    [33, 13, 4, 'Registration system issue sorted quickly.',           1, true],
    [36,  1, 5, 'Backup failure resolved before business impact.',     0, true],
    [37,  8, null, null,                                               2, false], // no response
    [39, 10, 5, 'Remote desktop set up perfectly for new hire.',       1, true],
    [40, 11, 3, 'Took a bit longer than expected.',                    2, true],
    [41, 12, 5, 'Noise complaint handled immediately and professionally.',1,true],
    [43, 14, 4, 'Training platform access granted next day.',          1, true],
    [45,  1, 1, 'Unacceptable response time for a critical outage.',   0, true],
    [46,  8, 4, 'Elevator back in service next day.',                  1, true],
    [47,  9, 4, 'Overdue notices working again quickly.',              1, true],
    [50, 12, 5, 'Projector fixed before our event. Thank you!',        1, true],
    [51, 13, 5, 'Phone set up same day as requested.',                 1, true],
    [53,  7, 5, 'Payroll access restored within hours.',               1, true],
    [55,  9, 4, 'Tablet station repaired within 2 days.',              1, true],
    [56,  1, 5, 'Database issue diagnosed and fixed in under 30 mins.',0, true],
    [58, 11, 5, 'Receipt paper refilled straight away.',               0, true],
    [59, 12, 4, 'Wireless printer working perfectly now.',             1, true],
    [61,  1, 5, 'All software installed correctly.',                   1, true],
    [62,  1, 5, 'Remediation handled quickly and thoroughly.',         1, true],
];
foreach ($csatData as [$tid,$uid,$rating,$comment,$offsetHrs,$responded]) {
    $ticketRow = $pdo->query("SELECT created_at FROM tickets WHERE id={$tid}")->fetch();
    $sentAt    = date('Y-m-d H:i:s', strtotime($resolvedAt[$tid] ?? $ticketRow['created_at']) + $offsetHrs * 3600);
    $respAt    = $responded && $rating !== null ? date('Y-m-d H:i:s', strtotime($sentAt) + 3600) : null;
    $token     = bin2hex(random_bytes(16));
    $csatStmt->execute([$tid,$uid,$token,$rating,$comment,$sentAt,$respAt]);
}
echo "[OK] CSAT surveys seeded.\n";

// ════════════════════════════════════════════════════════════════════
// ── TICKET TAGS ──────────────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════
// tag ids: 1=hardware 2=software 3=network 4=account 5=printing 6=email
//          7=vpn 8=password-reset 9=scanner 10=catalog 11=wifi 12=hvac 13=license 14=remote-access 15=security
$tagMap = $pdo->prepare('INSERT IGNORE INTO ticket_tag_map (ticket_id,tag_id) VALUES (?,?)');
foreach ([
    [1,11],[1,3],   // wifi + network
    [2,1],[2,5],    // hardware + printing
    [3,10],[3,2],   // catalog + software
    [4,6],          // email
    [6,7],[6,4],    // vpn + account
    [7,1],[7,3],    // hardware + network
    [8,6],[8,2],    // email + software
    [9,9],[9,1],    // scanner + hardware
    [10,1],[10,2],  // hardware + software
    [12,13],[12,2], // license + software
    [13,3],[13,15], // network + security
    [15,10],[15,2], // catalog + software
    [20,8],[20,4],  // password-reset + account
    [22,2],[22,3],  // software + network
    [25,2],[25,3],  // software + network
    [27,12],        // hvac
    [28,2],[28,14], // software + remote-access
    [31,1],[31,2],  // hardware + software
    [36,2],[36,15], // software + security
    [39,14],[39,2], // remote-access + software
    [45,1],[45,3],[45,15], // hardware + network + security
    [47,6],[47,2],  // email + software
    [50,1],         // hardware
    [53,4],[53,2],  // account + software
    [56,2],[56,3],  // software + network
    [59,1],[59,3],  // hardware + network
] as [$tid,$tagId]) { $tagMap->execute([$tid,$tagId]); }
echo "[OK] Ticket tags seeded.\n";

// ── Ticket CC ─────────────────────────────────────────────────────
$ccStmt = $pdo->prepare('INSERT IGNORE INTO ticket_cc (ticket_id,user_id,added_by) VALUES(?,?,?)');
foreach ([
    [10,1,4], [12,1,4], [19,1,5], [22,1,4], [25,1,4],
    [36,1,4], [45,1,4], [56,1,4],  // admin CC'd on critical/escalated tickets
    [2,1,2],  [13,10,2], [27,7,5],
] as [$tid,$uid,$by]) { $ccStmt->execute([$tid,$uid,$by]); }

// ── Ticket Watchers (agents watching important tickets) ───────────
$wStmt = $pdo->prepare('INSERT IGNORE INTO ticket_watchers (ticket_id,user_id) VALUES(?,?)');
foreach ([
    [9,1],[9,4],   // open/breached ticket watched by admin + Sarah
    [22,1],[22,4], // another breached ticket
    [25,1],[25,4], // warning ticket
    [42,1],[42,5], // open warning ticket
    [45,1],[45,4], // critical breached
    [12,1],        // critical breached server
] as [$tid,$uid]) { $wStmt->execute([$tid,$uid]); }
echo "[OK] CC and watchers seeded.\n";

// ════════════════════════════════════════════════════════════════════
// ── CUSTOM FIELD VALUES ───────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════
// field ids: 1=card# 2=category(dropdown) 3=patron-facing(checkbox) 4=target-date
//            5=affected-count 6=cost 7=vendor-notes 8=dependent(location>area>workstation)
// dropdown option ids: 1=Hardware 2=Software 3=Network 4=AccountMgmt 5=Printing 6=Other
// dependent L1: 7=Main 8=East 9=McCormick
// dependent L2: 10=Main/Public 11=Main/Staff 12=Main/IT  13=East/Public 14=East/Staff  15=McCormick/Public 16=McCormick/Staff
// dependent L3: 17-19=Main/Public Desks  20-21=Main/Staff Desks  22-23=Main/IT Racks
//               24-25=East/Public  26-27=East/Staff  28-29=McCormick/Public  30=McCormick/Staff
$fvStmt = $pdo->prepare('INSERT INTO ticket_field_values (ticket_id,field_id,value) VALUES(?,?,?)');
$fieldValues = [
    // IT tickets with all field types demonstrated
    [ 1, 2, '3'],                      // Network category
    [ 1, 3, '1'],                      // Patron-facing = Yes
    [ 1, 5, '12'],                     // 12 affected users
    [ 1, 8, json_encode(['l1'=>7,'l2'=>10,'l3'=>17])], // Main/PublicFloor/Desk1

    [ 2, 1, '29141001112233'],          // card number
    [ 2, 2, '5'],                       // Printing category
    [ 2, 3, '1'],                       // Patron-facing = Yes
    [ 2, 5, '3'],                       // 3 affected

    [ 3, 2, '2'],                       // Software category
    [ 3, 8, json_encode(['l1'=>8,'l2'=>13,'l3'=>24])], // East/PublicFloor/Desk1

    [ 7, 2, '1'],                       // Hardware category
    [ 7, 3, '1'],                       // Patron-facing
    [ 7, 5, '50'],                      // 50 users affected

    [ 9, 2, '1'],                       // Hardware (scanner)
    [ 9, 3, '1'],                       // Patron-facing
    [ 9, 5, '8'],                       // 8 affected (circulation staff)
    [ 9, 8, json_encode(['l1'=>7,'l2'=>10,'l3'=>18])], // Main/PublicFloor/Desk2

    [10, 2, '1'],                       // Hardware
    [10, 4, '2026-01-16'],             // target resolution date
    [10, 5, '1'],                       // 1 person
    [10, 8, json_encode(['l1'=>8,'l2'=>14,'l3'=>26])], // East/StaffArea/Desk1

    [12, 2, '2'],                       // Software (antivirus)
    [12, 6, '249.99'],                  // Cost of license renewal
    [12, 7, 'Renewed via Adobe Admin Console. Vendor ref: AV-2026-0115.'],
    [12, 8, json_encode(['l1'=>7,'l2'=>12,'l3'=>22])], // Main/ITRoom/ServerRackA

    [13, 2, '3'],                       // Network
    [13, 5, '25'],                      // 25 affected
    [13, 8, json_encode(['l1'=>7,'l2'=>12,'l3'=>23])], // Main/ITRoom/ServerRackB

    [22, 2, '2'],                       // Software
    [22, 3, '1'],                       // Patron-facing
    [22, 5, '100'],                     // all online patrons affected

    [25, 2, '2'],                       // Software
    [25, 4, '2026-02-07'],             // target date
    [25, 5, '200'],                     // many affected

    [36, 2, '2'],                       // Software
    [36, 6, '0'],                       // No cost (internal fix)
    [36, 7, 'Backup job reconfigured. Vendor support ticket #BC-20260205 closed.'],

    [39, 2, '4'],                       // Account Management
    [39, 8, json_encode(['l1'=>8,'l2'=>14,'l3'=>27])], // East/StaffArea/Desk2

    [45, 2, '3'],                       // Network
    [45, 5, '80'],                      // all staff affected
    [45, 6, '1250.00'],                 // cost of replacement switch
    [45, 7, 'Cisco switch model WS-C2960X replaced. Vendor: Network Pro Inc.'],
    [45, 8, json_encode(['l1'=>7,'l2'=>12,'l3'=>22])], // Main/ITRoom/ServerRackA

    [53, 2, '4'],                       // Account Management
    [53, 8, json_encode(['l1'=>7,'l2'=>11,'l3'=>20])], // Main/StaffArea/Desk1

    [56, 2, '2'],                       // Software
    [56, 6, '0'],                       // No external cost
    [56, 7, 'Slow queries caused by missing index. Added index on tickets.updated_at.'],
    [56, 8, json_encode(['l1'=>7,'l2'=>12,'l3'=>22])], // Main/ITRoom/ServerRackA
];
foreach ($fieldValues as [$tid,$fid,$val]) { $fvStmt->execute([$tid,$fid,$val]); }
echo "[OK] Custom field values seeded.\n";

// ════════════════════════════════════════════════════════════════════
// ── SCHEDULED REPORTS ────────────────────────────────────────────
// ════════════════════════════════════════════════════════════════════
$pdo->exec("INSERT INTO scheduled_reports (name,report_type,recipients,frequency,send_day,date_range_days,is_enabled) VALUES
 ('Weekly IT Overview',  'overview',          '[\"admin@localdesk.user\"]',          'weekly',  1,7, 1),
 ('Monthly SLA Report',  'sla',               '[\"admin@localdesk.user\",\"sarah.chen@example.com\"]','monthly',1,30,1),
 ('Weekly CSAT Summary', 'csat',              '[\"admin@localdesk.user\"]',          'weekly',  1,7, 1),
 ('Daily Open Tickets',  'unresolved',        '[\"admin@localdesk.user\"]',          'daily',   null,1,1)");

// ── Audit log entries ─────────────────────────────────────────────
$pdo->exec("INSERT INTO audit_log (user_id,action,target_type,target_id,detail,ip_address,created_at) VALUES
 (1,'create_user',  'user',   4,'Created agent account: Sarah Chen',     '192.168.1.10','2025-12-01 09:00:00'),
 (1,'create_user',  'user',   5,'Created agent account: Mike Rodriguez', '192.168.1.10','2025-12-01 09:05:00'),
 (1,'create_user',  'user',   6,'Created agent account: Emma Wilson',    '192.168.1.10','2025-12-01 09:10:00'),
 (1,'create_sla',   'sla',    1,'Created SLA policy for Low priority',   '192.168.1.10','2025-12-02 10:00:00'),
 (1,'create_sla',   'sla',    2,'Created SLA policy for Medium priority','192.168.1.10','2025-12-02 10:01:00'),
 (1,'create_sla',   'sla',    3,'Created SLA policy for High priority',  '192.168.1.10','2025-12-02 10:02:00'),
 (1,'create_sla',   'sla',    4,'Created SLA policy for Critical',       '192.168.1.10','2025-12-02 10:03:00'),
 (1,'create_holiday','holiday',1,'Added holiday: Christmas Day 2025',    '192.168.1.10','2025-12-03 11:00:00'),
 (1,'update_settings','settings',null,'Updated business hours schedule', '192.168.1.10','2025-12-05 14:00:00'),
 (1,'update_settings','settings',null,'Enabled CSAT surveys',            '192.168.1.10','2025-12-05 14:10:00'),
 (1,'delete_ticket', 'ticket',null,'Bulk deleted old test tickets',      '192.168.1.10','2025-12-10 09:00:00'),
 (1,'create_group',  'group', 1,'Created group: IT',                    '192.168.1.10','2025-11-01 10:00:00'),
 (4,'update_ticket', 'ticket',45,'Resolved network switch failure',      '192.168.1.11','2026-02-13 16:00:00'),
 (1,'create_field',  'field', 1,'Added custom field: Library Card Number','192.168.1.10','2025-12-15 09:00:00'),
 (1,'create_field',  'field', 8,'Added custom field: Location > Area > Workstation','192.168.1.10','2025-12-15 09:30:00')");

echo "[OK] Scheduled reports and audit log seeded.\n";

// Run pending migrations to bring this fresh schema up to current.
// schema.sql captures only an older snapshot; migrations 037+ etc still need to apply.
echo "[..] Running migrations...\n";
require ROOT_DIR . '/database/migrate.php';
echo "[OK] Migrations applied.\n";

echo "\n✓ All test data seeded successfully!\n";
echo "  Tickets:        62 (60 regular + 2 splits)\n";
echo "  Users:          14 (1 admin, 3 agents, 10 patrons)\n";
echo "  Timeline events: see above\n";
echo "  CSAT surveys:   " . count($csatData) . " (including " . count(array_filter($csatData,fn($c)=>$c[2]===null)) . " unanswered)\n";
echo "  KB articles:    8 (7 published, 1 draft)\n";
echo "  Custom fields:  8 (all types: text, dropdown, checkbox, date, number, decimal, textarea, dependent)\n";
echo "  Holidays:       6\n";
echo "  SLA breaches:   4 (tickets 9, 12, 22, 45)\n";
echo "  Merged tickets: 2 (17→38, 21→16)\n";
echo "  Split tickets:  2 (61 from 10, 62 from 19)\n";
