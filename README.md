# LocalDesk

A self-hosted IT helpdesk and ticketing system built for the Waterloo Public Library. Pure PHP on a LAMP stack (PHP 8.0+, MySQL, Apache) with a lightweight custom router, PDO, and Bootstrap 5.

## Features

### Ticket Management
- Create, assign, prioritize, and track tickets through their full lifecycle (open, in progress, pending, waiting on customer, waiting on third party, resolved, closed)
- File attachments on tickets and comments (PDF, JPEG, PNG, and more; configurable size limit)
- Tags, location tagging, due dates, and browser/OS auto-detection on creation
- Internal notes visible only to agents and admins; public comments visible to the submitter
- CC additional users on a ticket to keep them in the notification loop
- **Ticket Merge** — combine duplicate tickets; timeline entries, tags, and CC users are copied to the target ticket and the source is closed
- **Staff Ticket Creation** — admins and agents create tickets on behalf of users with full field control (status, assignment, group, due date, tags)
- **Bulk Actions** — select multiple tickets to assign, close, merge, or delete (admin only) in one operation from a persistent bulk action bar
- **Ticket Templates** — reusable templates that pre-fill subject, body, type, and priority; shared templates appear as starting points on the portal create form
- **Custom Form Fields** — drag-and-drop Workflows builder for adding custom fields to tickets (text, dropdown, checkbox, multi-select)
- **Concurrent Viewer Warning** — presence detection alerts agents when another user is already viewing the same ticket

### Filtering & Views
- **Slide-Out Filter Panel** — filters accessible via a collapsible side panel with state preserved across navigation
- **Saved & Default Filters** — save named filter presets, set a personal default view, and share filters with the team
- **Column Selector** — toggle which columns appear in the ticket list per user
- Filters persist when navigating from the ticket list to a ticket and back

### SLA Tracking
- Business-hours-aware service level agreements per priority level
- Tracks first response and resolution targets with automatic state transitions (on track, warning at 80%, breached)
- Timers pause when tickets enter pending/waiting status and resume on reactivation
- Recalculation on priority changes; cron script for periodic SLA state updates; admin recalculate button

### Reports & Analytics
Twelve built-in report types accessible from the Reports Overview:
- **Agent Performance** — tickets handled, response times, and resolution rates per agent
- **Response Times** — average first-response and resolution times by priority
- **SLA Compliance** — SLA met vs breached rates and breached ticket details
- **Unresolved Tickets** — all open, in-progress, and pending tickets with aging breakdown
- **Ticket Volume** — ticket creation trends over time by priority, type, and location
- **Ticket Lifecycle** — average time spent in each status stage and transition patterns
- **By Location** — ticket volume and resolution rates compared across locations
- **Satisfaction (CSAT)** — customer satisfaction survey results: response rates, average ratings, and feedback
- **Agent Workload** — heatmap of open tickets per agent broken down by status and SLA
- **Ticket Trends** — multi-line volume trend drilled down by type or location
- **FCR Rate** — first-contact resolution rate for tickets resolved without back-and-forth
- **Custom Builder** — pick any metric and group-by combination to build a custom report
- **Scheduled Reports** — configure reports to run automatically and email results on a schedule

### CSAT Surveys
- Satisfaction surveys sent automatically after ticket resolution
- Configurable survey settings under Admin → Settings → CSAT
- Ratings and comments displayed in the Satisfaction report

### Knowledge Base
- Three-tier hierarchy (categories, folders, articles) with Markdown rendering
- Draft and published article statuses; full-text search from the portal
- KB article suggestions when creating a ticket (subject autocomplete)
- **Article Feedback** — helpful/not helpful ratings from portal users
- **Version History** — every article save creates a revision; admins can view and restore prior versions
- **Public KB** — categories and articles can be marked public and browsed without logging in at `/kb`
- **KB Article Import** — bulk import articles via CSV from Admin → Settings → Import KB

### Automations & Escalations
- **Automations** — rule-based engine triggered on ticket create or update; conditions match on type, priority, status, location, group, or assigned agent; actions: assign, set priority/status/group, add tag
- **Escalation Rules** — time-based policies that automatically reassign, change priority, or update status when tickets remain unresolved past configurable thresholds
- Both automations and escalation actions are logged as internal timeline entries

### User Profiles & Authentication
- **User Profile** — all users can update name, change password (with current-password verification), set light/dark theme, and manage 2FA from `/profile`
- **Two-Factor Authentication (TOTP)** — optional TOTP-based 2FA for admin and agent accounts using any authenticator app; admins can reset 2FA from the user management page
- **Dark Mode / Light Mode** — per-user theme preference stored in the database and applied on every page load

### Notifications & Email
- **In-App Notifications** — @mention system in ticket comments creates in-app notifications; unread badge with 15-second polling; mark read individually or all at once
- **Email Notification Preferences** — per-user opt-in/out controls for ticket creation, ticket update, and @mention emails
- **Email Notifications** — SMTP-based alerts on ticket creation and updates with Message-ID threading for email client grouping
- **Customizable Email Templates** — edit the HTML/text of outgoing email templates with placeholder variable support (`{{ticket_id}}`, `{{user_name}}`, `{{agent_name}}`, etc.)
- **Email Reply Integration** — inbound email replies are automatically added as ticket comments; supports both IMAP and Microsoft Graph API (OAuth2 / Exchange Online)

### Groups
- Organise agents and admins into departmental groups
- Agents who belong to groups see only tickets assigned to those groups; admins see all tickets

### Admin Tools
- **Audit Log** — admin-accessible trail of all admin actions (user CRUD, role changes, settings changes) with timestamp and actor, at `/admin/audit-log`
- **Admin Documentation** — built-in docs at `/admin/docs` covering tickets, users, SLA, automations, branding, portal, import, and the knowledge base
- **Onboarding Tour** — six-step walkthrough on first admin login, replayable from the user dropdown

### Ticket Import & Export
- **Import** — bulk-import tickets and users from CSV with a flexible column-mapping step; auto-creates requester accounts; dry-run preview before committing
- **Export** — export the current filtered ticket list as CSV (UTF-8 BOM for Excel compatibility)

### Branding & Settings
- Customise application name, logo, primary colour, navbar gradient, and timeline entry colours
- Live preview panel; reset to defaults button
- Email and SMTP configuration through the admin UI
- Business hours and timezone configuration
- SLA policies per priority with recalculate button
- Priority, type, group, and location management

### Backup
- One-click backup from Admin → Settings → Backup
- Produces a `.zip` containing a full SQL dump plus uploaded files (attachments, branding assets, avatars)

### Security
- CSRF token protection on all POST forms
- Bcrypt password hashing (`PASSWORD_DEFAULT`)
- Prepared statements for all SQL queries (no raw interpolation)
- HTML output escaping via `e()` helper throughout all templates
- File upload validation (MIME type whitelist and size limit; attachments stored outside webroot)
- Role checks on every route (`Auth::requireRole()`)
- Installer locked after first run via `storage/installed.lock`

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.0+ (strict types) |
| Database | MySQL 5.7+ (InnoDB, utf8mb4) |
| Frontend | Bootstrap 5.3.3, Bootstrap Icons 1.11.3 (CDN) |
| Email | PHPMailer 6 |
| Markdown | League CommonMark 2 |
| Routing | Custom `Router.php` (no framework) |
| Auth | Session-based with bcrypt password hashing |

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Composer
- PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `zip` (for backup)

## Installation

### Option A — Web Installer (recommended)

1. Clone the repository and install dependencies:
   ```bash
   git clone <repo-url> localdesk
   cd localdesk
   composer install
   ```
2. Point your web server's document root at the `public/` directory.
3. Visit **`/install/`** in your browser and follow the six-step wizard:
   - **Requirements** — checks PHP extensions and directory permissions.
   - **Database** — enter credentials; optionally create the database automatically.
   - **Application** — set app name, URL, and timezone.
   - **Admin Account** — create the first administrator.
   - **Mail Server** — configure SMTP (skippable; can be set later in Settings).
   - **Review & Install** — confirm and run the installation.
4. After installation, delete or restrict access to the `/install/` directory.

### Option B — Manual Setup

```bash
# 1. Clone and install dependencies
git clone <repo-url> localdesk
cd localdesk
composer install

# 2. Configure environment
cp .env.example .env
# Edit .env with your database credentials and app URL

# 3. Seed the database (creates DB, applies schema, inserts sample data)
php database/seed.php

# 4. Start the development server
php -S localhost:8000 -t public
```

Visit **http://localhost:8000** and log in with one of the seed accounts below.

### Apache Setup (Production)

Enable `mod_rewrite`:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName localdesk.example.com
    DocumentRoot /path/to/localdesk/public

    <Directory /path/to/localdesk/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`DocumentRoot` must point to the `/public` directory. `mod_rewrite` is required for URL routing.

## Default Accounts (seed only)

| Email | Password | Role |
|-------|----------|------|
| `admin@localdesk.user` | `Password123!` | Admin |
| `agent@localdesk.user` | `Password 123!` | Agent |
| `user@localdesk.user` | `Password123!` | User |

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application display name | `LocalDesk` |
| `APP_URL` | Base URL for email links | `http://localhost:8000` |
| `APP_DEBUG` | Show detailed errors | `true` |
| `APP_TIMEZONE` | PHP timezone | `America/Toronto` |
| `DB_HOST` | MySQL host | `127.0.0.1` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name | `localdesk` |
| `DB_USER` | Database user | `root` |
| `DB_PASS` | Database password | *(empty)* |
| `UPLOAD_MAX_SIZE` | Max attachment size in bytes | `20971520` (20 MB) |
| `SLA_CRON_TOKEN` | Secret token for web-based SLA cron | *(empty)* |

SMTP settings are configured through the admin UI at **Settings → Email / SMTP**.

## Key Endpoints

| Path | Description |
|------|-------------|
| `/install/` | Web-based setup wizard (removed after install) |
| `/` | Home (redirects by role) |
| `/login` | Sign in |
| `/profile` | User profile (name, password, theme, 2FA) |
| `/kb` | Public knowledge base (no login required) |
| `/portal` | End-user portal |
| `/portal/tickets` | User's tickets |
| `/portal/tickets/create` | Submit a new ticket |
| `/portal/kb` | Knowledge base (authenticated portal view) |
| `/agent` | Agent dashboard |
| `/agent/tickets` | Agent ticket queue |
| `/agent/tickets/create` | Create a new ticket (agent) |
| `/admin` | Admin dashboard |
| `/admin/tickets` | All tickets |
| `/admin/tickets/create` | Create a new ticket (admin) |
| `/admin/ticket-templates` | Ticket template management |
| `/admin/users` | User management |
| `/admin/groups` | Group management |
| `/admin/kb` | Knowledge base management |
| `/admin/reports` | Reports & Analytics overview |
| `/admin/audit-log` | Admin audit log |
| `/admin/workflows/ticket-fields` | Custom ticket form field builder |
| `/admin/settings` | Email, business hours, SLA, branding, automations, import, backup, CSAT, escalation rules, email templates, scheduled reports |
| `/admin/docs` | Built-in documentation |
| `/notifications` | Notification inbox |
| `/health` | Health check (JSON) |

## Role-Based Access

| Capability | Admin | Agent | User |
|---|:---:|:---:|:---:|
| Submit tickets (portal) | — | — | ✓ |
| Create tickets on behalf of users | ✓ | ✓ | — |
| View & reply to all tickets | ✓ | ✓ | — |
| View own tickets (portal) | — | — | ✓ |
| Internal notes | ✓ | ✓ | — |
| Two-Factor Authentication (TOTP) | ✓ | ✓ | — |
| User management | ✓ | — | — |
| Settings & branding | ✓ | — | — |
| KB management | ✓ | — | — |
| Reports & Analytics | ✓ | — | — |
| Audit log | ✓ | — | — |
| Automations & escalation rules | ✓ | — | — |
| Backup | ✓ | — | — |

## Project Structure

```
localdesk/
├── database/
│   ├── schema.sql              # Full database schema (32 tables)
│   └── seed.php                # Drop, recreate, and seed all tables
├── public/
│   ├── index.php               # Front controller
│   ├── install/                # Web installer (delete after setup)
│   ├── .htaccess               # Apache rewrite rules
│   ├── css/style.css           # Custom styles
│   ├── sla-cron.php            # Standalone SLA recalculation script
│   └── uploads/                # Branding assets and user avatars
├── src/
│   ├── Auth.php                # Session-based authentication
│   ├── Database.php            # PDO singleton connection
│   ├── Router.php              # Lightweight request router
│   ├── Sla.php                 # SLA computation (business hours aware)
│   ├── bootstrap.php           # App initialisation (env, session, constants)
│   ├── helpers.php             # CSRF, flash, render, email, sidebar helpers
│   ├── routes.php              # Top-level routes (home, auth, profile, public KB)
│   └── routes/
│       ├── admin.php           # Admin routes (users, settings, KB, tickets, reports, audit log, …)
│       ├── agent.php           # Agent routes (tickets, comments)
│       └── portal.php          # Portal routes (user tickets, KB, attachments)
├── scripts/
│   └── process-replies.php     # Inbound email processor (IMAP or Microsoft Graph API)
├── storage/
│   ├── attachments/            # Ticket file attachments (outside webroot)
│   └── backups/                # Backup zip files (outside webroot)
├── templates/
│   ├── layouts/                # Base and app layouts
│   ├── pages/
│   │   ├── admin/
│   │   │   ├── docs/           # Built-in admin documentation pages
│   │   │   ├── reports/        # Report pages (overview + 12 individual reports)
│   │   │   ├── settings/       # Settings pages (email, SLA, branding, escalations, CSAT, …)
│   │   │   ├── tickets/        # Admin ticket list and detail views
│   │   │   └── …
│   │   ├── agent/              # Agent pages (dashboard, tickets)
│   │   └── portal/             # Portal pages (dashboard, tickets, KB)
│   ├── partials/               # Reusable components (navbar, sidebar, onboarding tour, docs nav)
│   └── emails/                 # HTML email templates
├── tests/
│   ├── Feature/                # PHPUnit integration tests (authenticated HTTP)
│   └── Support/                # Test base class and database seeder
├── vendor/                     # Composer dependencies
├── composer.json
├── .env.example
└── README.md
```

## SLA Cron Job

SLA states are recalculated periodically to detect warning and breached conditions. Set up a cron job to run every 5 minutes:

```bash
*/5 * * * * php /path/to/localdesk/public/sla-cron.php
```

Or trigger via HTTP with a secret token (set `SLA_CRON_TOKEN` in `.env`):

```
GET https://yoursite.com/sla-cron.php?token=YOUR_SLA_CRON_TOKEN
```

Admins can also manually recalculate from **Settings → SLA Policies → Recalculate All**.

## Inbound Email (Reply-to-Ticket)

Replies to ticket notification emails are automatically added as comments. Two backends are supported:

- **IMAP** — configure a mailbox in Settings → Email; run `scripts/process-replies.php` via cron
- **Microsoft Graph API** — OAuth2-based connection to Microsoft 365 / Exchange Online mailboxes; configure under Settings → Email → Graph API tab; run `scripts/process-replies.php` via cron or trigger from Settings → Run Now

## License

Internal project — Waterloo Public Library.
