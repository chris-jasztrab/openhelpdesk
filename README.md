# LocalDesk

A self-hosted IT helpdesk and ticketing system built for the Waterloo Public Library. Pure PHP on a LAMP stack (PHP 8.0+, MySQL, Apache) with a lightweight custom router, PDO, and Bootstrap 5.

## Features

- **Ticket Management** — Create, assign, prioritize, and track tickets through their lifecycle (open, in progress, pending, resolved, closed). Supports file attachments, tags, location tagging, browser/OS info capture, and due dates.
- **SLA Tracking** — Business-hours-aware service level agreements per priority level. Tracks first response and resolution targets with automatic state transitions (on track, warning, breached). Timers pause when tickets enter pending status and resume when reactivated.
- **Knowledge Base** — Three-tier hierarchy (categories, folders, articles) with Markdown rendering. Published articles are searchable from the portal.
- **Automations** — Rule-based automation engine. Trigger on ticket create/update, match conditions (subject, priority, location, tag, etc.) and perform actions (assign, set priority, add tag, post internal note).
- **Groups** — Organise agents and admins into departmental groups. Agents who belong to groups only see tickets assigned to those groups; admins see all tickets.
- **Notifications** — @mention system in ticket comments creates in-app notifications for agents and admins.
- **Email Notifications** — SMTP-based email alerts on ticket creation and updates with Message-ID threading for email client grouping.
- **Ticket Import** — Import tickets from any CSV format with a flexible column-mapping step. Auto-detects common column name variations. Creates requester accounts automatically.
- **Backup** — One-click backup from Admin → Settings → Backup. Produces a `.zip` containing a full SQL dump of all tables plus uploaded files (attachments, branding assets, avatars).
- **Branding** — Customise application name, logo, primary colour, navbar gradient, and timeline entry colours (internal notes and system events styled independently).
- **Onboarding Tour** — Six-step walkthrough shown on first admin login. Replayable at any time from the user dropdown menu.
- **Admin Documentation** — Built-in docs at `/admin/docs` covering tickets, users, SLA, automations, branding, portal, import, and the knowledge base.
- **Role-Based Access** — Three roles with distinct capabilities:
  - **Admin** — Full system access: users, settings, KB management, all tickets, groups, SLA configuration, backups.
  - **Agent** — Ticket queue (scoped to their groups if applicable), assignment, public replies, internal notes, KB access.
  - **User** — Portal for submitting and tracking own tickets, KB browsing.

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
| `/portal` | End-user portal |
| `/portal/tickets` | User's tickets |
| `/portal/tickets/create` | Submit a new ticket |
| `/portal/kb` | Knowledge base |
| `/agent` | Agent dashboard |
| `/agent/tickets` | Agent ticket queue |
| `/admin` | Admin dashboard |
| `/admin/tickets` | All tickets |
| `/admin/users` | User management |
| `/admin/groups` | Group management |
| `/admin/kb` | Knowledge base management |
| `/admin/settings` | Email, business hours, SLA, branding, automations, import, backup |
| `/admin/docs` | Built-in documentation |
| `/notifications` | Notification inbox |
| `/health` | Health check (JSON) |

## Project Structure

```
localdesk/
├── database/
│   ├── schema.sql              # Full database schema (23 tables)
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
│   ├── routes.php              # Top-level routes (home, auth, dashboards)
│   └── routes/
│       ├── admin.php           # Admin routes (users, settings, KB, tickets, groups, backup)
│       ├── agent.php           # Agent routes (tickets, comments)
│       └── portal.php          # Portal routes (user tickets, KB, attachments)
├── storage/
│   ├── attachments/            # Ticket file attachments (outside webroot)
│   └── backups/                # Backup zip files (outside webroot)
├── templates/
│   ├── layouts/                # Base and app layouts
│   ├── pages/
│   │   ├── admin/
│   │   │   ├── docs/           # Built-in admin documentation pages
│   │   │   ├── settings/       # Settings pages (email, SLA, branding, backup, …)
│   │   │   ├── tickets/        # Admin ticket views
│   │   │   └── …
│   │   ├── agent/              # Agent pages (dashboard, tickets)
│   │   └── portal/             # Portal pages (dashboard, tickets, KB)
│   ├── partials/               # Reusable components (navbar, sidebar, onboarding tour, docs nav)
│   └── emails/                 # HTML email templates
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

## Security

- CSRF token protection on all POST forms
- Bcrypt password hashing (`PASSWORD_DEFAULT`)
- Prepared statements for all SQL queries
- HTML output escaping via `e()` helper
- Markdown rendered with HTML input escaping
- File upload validation (MIME type whitelist and size limit)
- Ticket attachments and backups stored outside the webroot
- Backup filenames validated against a strict regex before download or deletion
- Internal notes hidden from portal users
- Role checks on every route (`Auth::requireRole()`)
- Installer locked after first run via `storage/installed.lock`

## License

Internal project — Waterloo Public Library.
