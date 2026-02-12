# LocalDesk

Self-hosted helpdesk system built on a LAMP stack (PHP 8.2+, MySQL/MariaDB, Apache).

## Quick Start

### 1. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your database credentials.

### 2. Create Database & Seed Users

```bash
php database/seed.php
```

This creates the `localdesk` database, applies the schema, and seeds three test users:

| Email                | Password      | Role  |
|----------------------|---------------|-------|
| admin@localdesk.user | Password123!  | Admin |
| agent@localdesk.user | Password 123! | Agent |
| user@localdesk.user  | Password123!  | User  |

### 3. Run with PHP Development Server

```bash
php -S localhost:8000 -t public
```

Visit http://localhost:8000 and sign in.

### 4. Apache Setup (Production)

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

**Important:**
- `DocumentRoot` must point to the `/public` directory.
- `mod_rewrite` is required for URL routing.

## Endpoints

| Path      | Description                  |
|-----------|------------------------------|
| `/`       | Home (redirects by role)     |
| `/login`  | Sign in                      |
| `/portal` | End-user portal              |
| `/agent`  | Agent dashboard              |
| `/admin`  | Admin dashboard              |
| `/health` | Health check (JSON)          |

## Project Structure

```
localdesk/
├── public/              # Web root (DocumentRoot)
│   ├── index.php        # Front controller
│   └── .htaccess        # Apache rewrite rules
├── src/                 # Application source
│   ├── bootstrap.php    # App initialization
│   ├── routes.php       # Route definitions
│   ├── Router.php       # URL router
│   ├── Database.php     # PDO wrapper
│   ├── Auth.php         # Authentication
│   └── helpers.php      # Utility functions
├── templates/           # View templates
│   ├── layouts/         # Page layouts
│   ├── partials/        # Reusable components
│   └── pages/           # Page content
├── database/            # Database files
│   ├── schema.sql       # Table definitions
│   └── seed.php         # Database seeder
├── .env.example         # Environment template
└── README.md
```
