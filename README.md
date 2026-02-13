# LocalDesk

A self-hosted IT helpdesk and ticketing system built for the Waterloo Public Library. Pure PHP on a LAMP stack (PHP 8.0+, MySQL, Apache) with a lightweight custom router, PDO, and Bootstrap 5.

## Features

- **Ticket Management** -- Create, assign, prioritize, and track tickets through their lifecycle (open, in progress, pending, resolved, closed). Supports file attachments, tags, location tagging, browser/OS info capture, and due dates.
- **SLA Tracking** -- Business-hours-aware service level agreements per priority level. Tracks first response and resolution targets with automatic state transitions (on track, warning, breached). Timers pause when tickets enter pending status and resume when reactivated.
- **Knowledge Base** -- Three-tier hierarchy (categories, folders, articles) with Markdown rendering. Published articles are searchable from the portal.
- **Notifications** -- @mention system in ticket comments creates in-app notifications for agents and admins.
- **Email Notifications** -- SMTP-based email alerts on ticket creation and updates with Message-ID threading for email client grouping.
- **Groups** -- Organize agents and admins into departmental groups (IT, Collections, Facilities, Lifelong Learning, Marketing, Circulation).
- **Role-Based Access** -- Three roles with distinct capabilities:
  - **Admin** -- Full system access: users, settings, KB management, all tickets, groups, SLA configuration
  - **Agent** -- Ticket queue, assignment, comments (public and internal notes), KB access
  - **User** -- Portal for submitting and tracking own tickets, KB browsing

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
- PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`

## Quick Start

```bash
# 1. Clone the repository
git clone <repo-url> localdesk
cd localdesk

# 2. Install PHP dependencies
composer install

# 3. Configure environment
cp .env.example .env
# Edit .env with your database credentials and app URL

# 4. Seed the database (creates DB, applies schema, inserts sample data)
php database/seed.php

# 5. Start the development server
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

## Default Accounts

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
| `DB_HOST` | MySQL host | `127.0.0.1` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name | `localdesk` |
| `DB_USER` | Database user | `root` |
| `DB_PASS` | Database password | *(empty)* |
| `UPLOAD_MAX_SIZE` | Max attachment size in bytes | `20971520` (20 MB) |
| `SLA_CRON_TOKEN` | Secret token for web-based SLA cron | *(empty)* |

SMTP settings are configured through the admin UI at **Settings > Email / SMTP**.

## Endpoints

| Path | Description |
|------|-------------|
| `/` | Home (redirects by role) |
| `/login` | Sign in |
| `/portal` | End-user portal and dashboard |
| `/portal/tickets` | User's tickets |
| `/portal/tickets/create` | Submit a new ticket |
| `/portal/kb` | Knowledge base |
| `/agent` | Agent dashboard |
| `/agent/tickets` | Agent ticket queue |
| `/admin` | Admin dashboard |
| `/admin/tickets` | All tickets |
| `/admin/users` | User management |
| `/admin/groups` | Group management |
| `/admin/kb/categories` | Knowledge base management |
| `/admin/settings` | Email, business hours, SLA, locations, priorities, types, groups |
| `/notifications` | Notification inbox |
| `/health` | Health check (JSON) |

## Project Structure

```
localdesk/
├── database/
│   ├── schema.sql              # Full database schema (17 tables)
│   └── seed.php                # Drop, recreate, and seed all tables
├── public/
│   ├── index.php               # Front controller
│   ├── .htaccess               # Apache rewrite rules
│   ├── css/style.css           # Custom styles
│   ├── sla-cron.php            # Standalone SLA recalculation script
│   └── uploads/avatars/        # User avatar storage
├── src/
│   ├── Auth.php                # Session-based authentication
│   ├── Database.php            # PDO singleton connection
│   ├── Router.php              # Lightweight request router
│   ├── Sla.php                 # SLA computation (business hours aware)
│   ├── bootstrap.php           # App initialization (env, session, constants)
│   ├── helpers.php             # CSRF, flash, render, email, sidebar helpers
│   ├── routes.php              # Top-level routes (home, auth, dashboards, notifications)
│   └── routes/
│       ├── admin.php           # Admin routes (users, settings, KB, tickets, groups)
│       ├── agent.php           # Agent routes (tickets, comments)
│       └── portal.php          # Portal routes (user tickets, KB, attachments)
├── storage/
│   └── attachments/            # Ticket file attachments (outside webroot)
├── templates/
│   ├── layouts/                # Base and app layouts
│   ├── pages/
│   │   ├── admin/              # Admin pages (dashboard, tickets, users, settings, KB, groups)
│   │   ├── agent/              # Agent pages (dashboard, tickets)
│   │   └── portal/             # Portal pages (dashboard, tickets, KB)
│   ├── partials/               # Reusable components (sidebar, nav, settings tabs)
│   └── emails/                 # HTML email templates (ticket-created, ticket-updated)
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

Admins can also manually recalculate from **Settings > SLA Policies > Recalculate All**.

## Security

- CSRF token protection on all POST forms
- Bcrypt password hashing (`PASSWORD_DEFAULT`)
- Prepared statements for all SQL queries
- HTML output escaping via `e()` helper
- Markdown rendered with HTML input escaping
- File upload validation (MIME type whitelist and size limit)
- Ticket attachments stored outside the webroot
- Internal notes hidden from portal users
- Role checks on every route (`Auth::requireRole()`)

## License

Internal project -- Waterloo Public Library.
