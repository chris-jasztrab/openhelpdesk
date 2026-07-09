# OpenHelpDesk

**OpenHelpDesk** is a self-hosted IT helpdesk and ticketing system built for libraries and small IT teams who want a capable support platform without SaaS fees or vendor lock-in. It runs on a plain LAMP stack — pure PHP 8 with a lightweight custom router, PDO, MySQL, and Bootstrap 5 — so it installs anywhere from XAMPP on Windows to a standard Apache box, via either a six-step web installer or a one-command seed script. Every front-end library ships self-hosted with the app (zero CDN dependencies), and database migrations apply themselves automatically on upgrade. Despite the modest footprint, it covers the full ticket lifecycle: assignment, prioritization, fully configurable ticket statuses, SLA tracking with business-hours and per-location timezone awareness, internal notes, attachments, tags, merging and splitting, bulk actions, drafts with server-side autosave, Gmail-style undo send, custom form fields with a per-type drag-and-drop form builder, and four switchable ticket-list views including a drag-and-drop Kanban board.

Beyond core ticketing, OpenHelpDesk includes a three-tier knowledge base with a public help center and version history, fourteen built-in reports plus a custom report builder and scheduled emailed reports, a real-time customizable operations wallboard, CSAT surveys (built-in or via an external provider like HappyOrNot), a rule-based automation and escalation engine with skill-based auto-assignment, recurring/preventive-maintenance ticket schedules, stale-ticket follow-ups, out-of-office coverage that reads agents' Outlook auto-replies, and full inbound email integration over the Microsoft Graph API (email-to-ticket, reply threading, and hashtag commands). It ships with Microsoft 365 SSO, optional TOTP two-factor auth, granular role-based access control with custom permission levels, an end-user portal written in plain language, status/incident banners, an installable Progressive Web App with an iPad-friendly "floor mode" for roaming staff, Microsoft Teams channel notifications, customizable branding and email templates, one-click backups, and a Bearer-token REST API with an OpenAPI spec. Security is built in throughout — CSRF protection, prepared statements, bcrypt hashing, output sanitization, upload validation, security headers with a locked-down CSP, and per-route permission checks.

OpenHelpDesk also has optional AI-assisted triage, powered by your choice of Anthropic Claude or OpenAI (just paste an API key — no extra services to run). On arrival, a large language model reads each new ticket and suggests the agent skills it needs, scores its own confidence, and gauges sentiment; from there it can auto-route the ticket to the best-matching agent, hand an ambiguous "No Wrong Door" request off to the right team, bump priority when a requester sounds angry or urgent, and flag likely duplicate tickets before they're filed. The AI layer is built to fail safe — confidential ticket types are never sent to a provider, every call has a hard timeout with graceful fallback to manual routing, hallucinated skill IDs are stripped, and every verdict is audit-logged with provider, model, latency, and token counts. Released under the MIT License.

## Screenshots

| | |
|---|---|
| **Admin dashboard** | **All tickets** |
| ![Admin dashboard](docs/screenshots/admin-dashboard.png) | ![All tickets](docs/screenshots/tickets-list.png) |
| **Ticket detail** — timeline, SLA, custom fields | **Reports & analytics** |
| ![Ticket detail](docs/screenshots/ticket-detail.png) | ![Reports and analytics](docs/screenshots/reports-overview.png) |
| **Drag-and-drop form builder** | **Knowledge base** |
| ![Form builder](docs/screenshots/form-builder.png) | ![Knowledge base](docs/screenshots/knowledge-base.png) |
| **End-user portal** | **Sign in** |
| ![End-user portal](docs/screenshots/portal.png) | ![Sign in](docs/screenshots/login.png) |
| **Public Help Center** — branded, searchable | **Knowledge base article** — with helpfulness voting |
| ![Public help center](docs/screenshots/kb-public.png) | ![Knowledge base article](docs/screenshots/kb-article.png) |
| **Rich-text editing everywhere** — KB article editor | **Email templates** — rich-text with dynamic tokens |
| ![Knowledge base editor](docs/screenshots/kb-editor.png) | ![Email template editor](docs/screenshots/email-templates.png) |
| **Fully configurable branding** — logo, colors, live preview | **iPad "floor mode"** — touch-friendly card queue for roaming staff |
| ![Branding settings](docs/screenshots/branding.png) | ![Floor mode queue](docs/screenshots/floor-mode.png) |
| **Floor mode** — streamlined ticket detail with quick actions | |
| ![Floor mode ticket detail](docs/screenshots/floor-ticket.png) | |

*Screenshots show the bundled demo data (`php database/seed_test_data.php`).*

## Features

### Ticket Management
- Create, assign, prioritize, and track tickets through their full lifecycle
- **Configurable Ticket Statuses** — add, rename, recolor, reorder, and deactivate statuses from Admin → Settings; each status has an open/closed bucket and an SLA-pause flag; default new/resolved/closed slots; deletion guardrails with reassign-on-delete
- File attachments on tickets and comments (PDF, Office documents, images, and more; configurable type whitelist and size limit); attachments shown inline within their timeline entry
- Tags, location tagging, due dates, and browser/OS auto-detection on creation
- Internal notes visible only to staff; public comments visible to the submitter
- CC additional users on a ticket (with autocomplete) to keep them in the notification loop; CC-added notification email
- **Mark as Solution** — flag a comment as the ticket's solution with a green "Go to solution" jump-link on staff and portal views (labels renameable)
- **Resolution note on close** — optional per-type flag that prompts the assigned owner to record what fixed a ticket when they close it without a comment; a framework-scaffolded modal (Action Taken → Verification → Next Steps) saves the note as an internal note, or they close with a logged reason instead. A soft nudge scoped to the owner closing from the ticket page — replies-then-close, list quick-edits, bulk changes, and automated closes skip it
- **Ticket Merge** — combine duplicate tickets; choose which is primary; timeline entries, tags, and CC users are copied; source ticket is closed with a link to the primary
- **Ticket Splitting** — split a single ticket into two, choosing which timeline entries move
- **Ticket Watching** — staff can watch tickets to receive all update notifications
- **Staff Ticket Creation** — admins and agents create tickets on behalf of users with full field control; on-behalf-of submissions carry a "Filed by X on their behalf" audit trail
- **Bulk Actions** — select multiple tickets to assign, change status, change priority, change group, close, merge, or delete in one operation; each bulk action is a separately grantable permission
- **Ticket Templates** — reusable templates that pre-fill subject, body (CKEditor 5 rich text), type, and priority; shared templates appear as starting points on the portal create form
- **Ticket Forwarding** — forward the full conversation (with all attachments) to external third parties; replies from outside are captured via auto-provisioned external contacts and threaded back onto the ticket with an **External** badge; internal vs external forwarding are separate permissions; blocked on confidential tickets
- **Drafts** — server-side autosave for new-ticket and reply forms on every surface (staff and portal); restore banner with Discard, 90-day expiry, works across machines; tickets with unsent drafts get a "✎ Draft" badge in ticket lists
- **Undo Send** — Gmail-style configurable countdown (3–120 s) holds new tickets and replies before they commit, with Undo/Escape to cancel
- **Recurring / Preventive-Maintenance Tickets** — schedules (daily, weekly, monthly, yearly, custom) that mint tickets automatically through the normal creation pipeline; anchored cadence, missed-tick safe, Run Now button
- **Reply / Forward / Note Panel** — tabbed panel on the ticket detail view; CKEditor 5 rich text everywhere (descriptions, replies, notes, KB, templates)
- **Auto-Assign on First Reply** — ticket automatically assigned to the staff member who posts the first public reply if previously unassigned
- **Stale-Write Protection** — optimistic concurrency on status changes; a "this ticket was updated" banner prompts a refresh instead of silently overwriting
- Type↔group consistency: changing group clears a mismatched type; changing type moves the ticket to the type's default group; assignee dropdowns are scoped to the relevant group's members
- Sticky ticket-page header keeps title, badges, presence, and actions pinned while scrolling; sortable timeline with a per-user default order
- Email-inbox commands: agents and admins can control tickets via hashtag commands (`#close`, `#resolve`, `#priority`, …) in email replies (DMARC spoof-protected)

### Ticket Views & Filtering
- **Four switchable list layouts** — Table (resizable columns), Compact (email-inbox style with interactive hover cards offering Reply/Forward/Add Note), Card (stacked cards), and **Kanban board**
- **Kanban Board** — built-in Status, Priority, and Assignee boards where dragging a card makes the real change, plus private or shared custom boards with user-defined buckets for personal organization; board filters (My team, Group)
- **Slide-Out Filter Panel** — filters in a collapsible side panel with state preserved across navigation; applied filters shown as removable pills with instant apply
- **AJAX Everything** — filtering, sorting, and pagination update the list without a full page reload, with shareable URLs
- **Saved & Default Filters** — save named filter presets, set a personal default view, and share filters with the team
- **Multi-Select Filters** — filter by multiple statuses, priorities, types, groups, and assignees simultaneously; watched-tickets filter; per-page selector (25/50/100)
- **URL Quick Filters** — `created_today`, `due_today`, `sla=breached|warning`, and `created_within` deep links
- **Column Selector & Resizable Columns** — per-user column visibility and drag-to-resize widths with per-page persistence; elastic Subject column fits the list to its container
- **Inline Quick Edits** — hover any row to change assignee, type, group, status, or priority without opening the ticket (dropdowns scoped to group members); also on the agent dashboard Recent Tickets widget
- **Search by Ticket ID** — enter a ticket number in the global search box to jump directly to that ticket
- Live "Opened by X" / "Being replied to by X" hints appear and clear on ticket lists without refreshing

### Live Collaboration & Presence
- Per-ticket presence: a subtle amber pill shows who else is viewing or replying to the ticket you have open
- **Reply-Collision Guard** — if someone replies while you're composing, you're warned at send time with their reply excerpt and Review / Post anyway options
- **Live Ticket Updates** — new replies, notes, and field changes stream into the open ticket's timeline (~8 s poll) without touching your draft
- Global online presence with an admin **Who's Online** page; presence drives the First Available auto-assignment strategy

### Wallboard
- **Live Operations Wallboard** — auto-refreshing (10 s–2 m), per-user customizable dashboard with 19 widgets: KPI cards, breakdown charts, a trend line, and live ticket lists
- Phone-home-screen style customize mode: drag-and-drop rearranging, per-widget height resize, independent 2/3/4-column layouts, add/remove widgets
- Every widget is clickable and drills into the matching filtered ticket list; board-level filters, fullscreen and pause controls; permission-scoped

### SLA Tracking
- Business-hours-aware SLA policies per priority (and per ticket type + priority), with a site-wide SLA on/off toggle
- Per-location timezone support for multi-branch SLA calculations
- Tracks first response and resolution targets with automatic state transitions (on track, warning at 80%, breached)
- Timers pause on any status flagged as SLA-pausing and resume on reactivation
- **Per-policy counted weekdays** — choose which days of the week each SLA timer counts (Mon–Sun)
- Durations accepted in `d/h/m` syntax in every duration field (stored as minutes, displayed rolled up)
- Recalculation on priority changes; cron script for periodic SLA state updates; admin recalculate button
- **Holidays / Closed Days** — configure public holidays and custom closed days with an auto-populate button; per-holiday option to exclude from SLA calculations
- SLA tokens (`{{sla}}`, `{{sla_response}}`, `{{sla_resolution}}`) available in email templates

### Escalations & Stale Tickets
- **Escalation Rules** — time-based policies that automatically reassign, change priority, or update status when tickets remain unresolved past configurable thresholds; includes a waiting-on-customer reminder email action; Run Now button
- **Manual Escalation Paths** — ordered per-type agent chains with an Escalate button and preview, escalation level badges and history, an "Escalated to Me" dashboard stat, and red escalation alert emails; skips the current assignee when they're in the chain
- Requesters can escalate their own tickets from the portal, with escalation state surfaced to them
- **Escalate Button Visibility** setting — always, or only after an SLA breach
- **Stale-Ticket Follow-Ups** — hourly cron notifies agents and sends requesters a "we haven't forgotten you" email when tickets sit idle past a threshold; global and inline-editable per-type thresholds, re-notify window, deduplication, Run Now, coverage option (retroactive vs new-tickets-only), and idle time counted only on the SLA policy's counted days

### Automations & Routing
- **Automations** — rule-based engine triggered on ticket create or update; nested AND/OR condition groups; conditions match on type, priority, status, location, group, or assigned agent; actions include assign, set priority/status/group, add tag, add CC; manual run against existing tickets
- **Auto-Assignment Strategies** per group — Manual, Round Robin, Load-Based, Skill-Based, AI Skill-Based, or First Available (driven by live presence), with fallbacks
- **Agent Skills** — a skills catalogue mapped to agents and ticket types powers skill-based routing; group managers can be delegated skill management for their own team (`/manager` area)
- **Default-Group Safety Net** — a resolution chain plus an hourly sweep guarantee no ticket is ever left without a group
- **Ticket Routing Defaults** settings page — default group, agent group-assign scope, and related routing behavior
- **Out-of-Office Coverage** — reads agents' Outlook automatic replies via Microsoft Graph and either reassigns their tickets to available group members or auto-replies to the requester once (three action modes, templated messages, admin status page, read-only `oof.view` permission, 15-minute cron)

### AI Features (optional)
- **AI Ticket Classification** — Anthropic Claude or OpenAI (pluggable provider abstraction) reads each new ticket and infers required agent skills, confidence, and sentiment; sentiment can bump priority for angry/urgent requesters
- **AI Skill-Based Auto-Assignment** — routes tickets to the best-matching agent above a configurable confidence threshold, with graceful fallback to manual routing
- **"No Wrong Door" Group Routing** — an ambiguous catch-all ticket type is routed by AI to the best team using group descriptions, with a fallback queue and full audit trail
- **AI Duplicate Detection** — flags likely duplicate tickets before submission on all create flows (per-type toggle and threshold, friendly warning with a preview of the matched ticket, override audited)
- **AI Skill Suggestions** — generate a starter skills catalogue from your organization profile, or data-mine past ticket subjects
- Built to fail safe: confidential ticket types are never sent to a provider, hard timeouts, hallucinated skill IDs stripped, and every verdict audit-logged with provider, model, latency, and token counts
- Admin tools: AI settings page with model refresh and test, raw-HTTP debug page, per-ticket re-classify/override, CLI backfill script; per-user toggle to hide AI notes from timelines

### Ticket Forms (Custom Fields)
- **Per-Type Form Builder** — drag-and-drop editor that designs each ticket type's form: interleave custom and system fields, set Required/Optional/Hidden per field (including per-type priority visibility), share fields across types, and see a live portal-form preview; layouts enforced server-side
- Twelve custom field types including text, dropdown, checkbox, multi-select, dependent cascades, date range, rich-text text block, image, and CC
- Custom field values shown in a dedicated sidebar column on the ticket detail view, scoped to the type's form with empty fields hidden

### Knowledge Base
- Three-tier hierarchy (categories, folders, articles) with rich-text editing (CKEditor 5) and full-text search
- Draft and published article statuses; KB article suggestions when creating a ticket (subject autocomplete)
- **Article Feedback** — helpful/not helpful ratings from portal users
- **Version History** — every article save creates a revision with diff view; admins can view and restore prior versions
- **Public KB** — categories and articles can be marked public and browsed without logging in at `/kb`
- Import articles via CSV; export/import as JSON; granular `kb.articles.manage` permission

### Reports & Analytics
Fourteen built-in report pages accessible from the Reports Overview, each with date-range presets (Today … Last year):
- **Agent Performance** — tickets handled, response times, and resolution rates per agent
- **Response Times** — average first-response and resolution times by priority
- **SLA Compliance** — SLA met vs breached rates and breached ticket details
- **SLA Violations** — catches overdue tickets not yet flagged by the cron
- **Unresolved Tickets** — all open tickets with aging breakdown, pagination, and drill-down tiles
- **Ticket Volume** — creation trends over time by priority, type, and location
- **Ticket Lifecycle** — average time spent in each status stage and transition patterns
- **By Location** — ticket volume and resolution rates compared across locations
- **Satisfaction (CSAT)** — survey response rates, average ratings, and feedback
- **Agent Workload** — heatmap of open tickets per agent broken down by status and SLA
- **Ticket Trends** — multi-line volume trend drilled down by type or location
- **FCR Rate** — first-contact resolution rate for tickets resolved without back-and-forth
- **Group Coverage** — ticket type → default group → members mapping, print-friendly
- **Custom Builder** — pick any metric and group-by combination to build a custom report
- **Scheduled Reports** — configure reports to run automatically and email results on a schedule; "Schedule" button on each report page
- **Ticket Types Settings Matrix** — printable at-a-glance grid of every per-type setting

### CSAT Surveys
- Satisfaction surveys sent automatically after ticket resolution, with emoji ratings and an issue-resolved/reopen flow
- **External survey provider mode** — hand off to HappyOrNot, Typeform, SurveyGizmo, etc. via a templated URL, with an HMAC-signed response webhook (`POST /api/csat/webhook`) to pull results back in
- Satisfaction panel and timeline rating entry on the ticket itself; Send Test Survey button; results in the Satisfaction report

### Email & Notifications
- **In-App Notification Feed** — seven notification types (@mentions, assignments, ticket updates, SLA warnings/breaches, new tickets, customer replies/notes) with an unread badge, live-updating feed, and mark-read individually or all at once
- **Email Notification Preferences** — per-user opt-in/out controls, including a "Tickets You Submit" group so staff get requester-side emails on their own tickets
- **Email Notifications Settings** — admin page to manage all outgoing notification hooks; optional group-wide email alert when a new ticket is assigned to a group
- **Requester Emails** — submission acknowledgement and agent-assigned notifications with two-level (admin + user) gating
- **Email Reply Integration** — inbound email replies are threaded onto tickets as comments via the Microsoft Graph API (OAuth2 / Exchange Online), with out-of-office/auto-reply and spoofed-sender detection
- **Email-to-Ticket** — inbound emails to a monitored Graph mailbox are automatically converted to new tickets
- **Customizable Email Templates** — edit the subject, rich-text intro, and button label for every outgoing email (created, updated, merged, assigned, group alert, escalation, CSAT, reminders, …), a shared footer, and **per-group template overrides** for requester-facing emails
- **Canned Responses** — personal and admin-managed global snippets with token substitution, insertable from the reply panel
- **Microsoft Teams Integration** — Adaptive Card posts to a Teams channel webhook on ticket created / assigned / status changed / SLA breached, with per-ticket-type channel routing, a test button, and fail-soft delivery
- **Microsoft Graph App Secret Expiry Reminders** — warning banner and email alerts when the Graph client secret is nearing expiry

### Users, Auth & Permissions
- **Granular RBAC** — built-in Admin, Agent, Power User, and End User roles plus admin-created custom permission levels with per-permission grants; sidebars and features are permission-driven; privilege-escalation guards (staff can only assign/edit roles at or below their own level); role changes and deletions take effect immediately on live sessions
- Fail-closed ticket visibility: agents see their groups' tickets, `tickets.view_all` grants everything, and there is one shared visibility rule across list, search, dashboards, API, and exports
- **Two-Factor Authentication (TOTP)** — optional TOTP 2FA for staff accounts with attempt caps and replay protection; admins can reset 2FA; enforced on API login too
- **Microsoft 365 SSO** — OAuth 2.0 / Microsoft Entra ID sign-in with SSO debug logging
- **Forgot Password** — self-service reset flow with hashed single-use tokens, throttled and audited
- **User Profile** — name, password, light/dark theme, timeline preferences, notification preferences, personal canned responses, and 2FA at `/profile`; every setting auto-saves on change
- User management: CRUD, avatars, merge duplicate accounts, delete-with-data or transfer tickets on deletion, multi-select filters, Who's Online, external-contact badges
- Brute-force throttling on web and API login; login timing-leak equalization

### Confidential Tickets
- Mark ticket types confidential: visibility locked to the creator and the assigned group, subjects redacted everywhere else (lists, feeds, exports, API)
- Admin access to confidential tickets requires re-authentication (5-minute window) and generates access alerts and audit entries
- Tamper protection: removing the confidential flag or deleting confidential entities requires re-auth and emails an alert; confidential groups alert on membership changes
- Confidential ticket content is never sent to AI providers and confidential tickets cannot be forwarded

### Portal (End Users)
- Plain-language experience — "Help Requests" instead of tickets, friendly status labels, "How urgent is this?" prompts, and a "What happens next?" callout
- Portal ticket list defaults to own open tickets; **My Location** toggle for multi-location users; per-type location-visibility opt-out
- Requesters can edit, close, and escalate their own tickets
- Deep-link the new-ticket form with a pre-selected type (`?type=`); login remembers where you were going
- KB search and article suggestions while typing a subject; AI duplicate warnings before filing
- Onboarding tour (Driver.js) and a built-in portal help section (`/portal/help`)

### Status Banners
- Pinned incident/status banners shown across the portal and staff views: rich text, severity levels (info/warning/critical), scheduling windows, and multi-branch targeting
- Any agent can post, edit, clear, or reactivate a banner; patrons can dismiss per-session (server-side)

### Floor Mode & PWA
- **Progressive Web App** — installable with a dynamic manifest, branded auto-generated icons, offline shell, update toast, and home-screen shortcuts
- **Floor Mode** — touch-first card queue for tablets (agent and portal variants) with a quick-capture FAB supporting voice dictation, camera capture, and barcode scanning; streamlined floor ticket detail with an escape hatch to the full view; floor icon shown only on touch devices

### Admin Tools
- **Audit Log** — comprehensive trail covering admin actions, auth/SSO/API events, config CRUD (with before/after diffs and secret redaction), bulk operations, and backups; unified viewer that cross-references the ticket timeline; filterable, exportable to CSV/Excel, and prunable
- **Settings Search** — Chrome-style search across all settings pages that finds individual fields and scrolls/highlights them
- **Cron Jobs Dashboard** — live Running/Stale/Not-configured status per job, with copy-paste Linux crontab and Windows `schtasks` commands generated with your real paths
- **Admin / Agent / Portal Documentation** — built-in searchable docs at `/admin/docs`, `/agent/help`, and `/portal/help`, plus a comprehensive `GETTING_STARTED.md`
- **Onboarding Tours** — permission-aware walkthroughs for admins, agents, and portal users, replayable from the user menu
- **Admin Password Rescue Script** — CLI break-glass script to reset a password or role when locked out
- **Danger Zone Full Reset** — wipe all data and re-run the setup wizard
- Drag-to-reorder plus click-to-sort on every admin list (types, groups, priorities, skills, automations, statuses, KB, …)

### Import & Export
- **Import Tickets** — bulk-import from CSV with flexible column mapping, dry-run preview, and a skipped-rows download
- **Import Users** — bulk-import accounts from CSV with column mapping, dry-run preview, duplicate detection, and a sample CSV
- **Import KB Articles** — bulk import via CSV; JSON export/import for whole-KB moves
- **Export** — current filtered ticket list as CSV (UTF-8 BOM for Excel); audit log to CSV/Excel; all exports neutralize CSV formula injection

### Branding & Settings
- Customize application name, logo, primary color, navbar gradient, timeline entry colors, and navbar fallback icon, with live preview and reset-to-defaults
- **Label Customization** — rename "Location" (e.g. Branch/Site), portal vocabulary, solution labels, and more; export/import label sets as JSON
- Organization profile (sector/type) that also feeds AI skill suggestions
- Email/SMTP configuration through the admin UI with test send and SMTP debug logging; `MAIL_ENABLED` env kill-switch for dev/test instances
- Business hours, per-location timezones, holidays, SLA policies, ticket priorities, types, groups, locations, tags, statuses, and routing defaults all managed in Settings

### Backup
- One-click backup from Admin → Settings → Backup: full SQL dump plus uploaded files, or a **full-website snapshot** (source, `.env`, vendor — everything) as a `.zip`; download and delete past backups

### REST API (Mobile)
- Full REST API v1 with Bearer token authentication (tokens hashed at rest, expiring, rotatable) for mobile and third-party integrations
- Endpoints covering auth, profile, dashboard, tickets (list/create/update/timeline/replies), attachments (upload/download), KB, notifications, canned responses, users, meta, and push-device registration
- Per-user rate limiting with `X-RateLimit-*` headers and 429/Retry-After; login throttling; 2FA enforced at login
- OpenAPI 3.0.3 specification at `openapi.json` and human-readable docs at `docs/API.md`

### Security
- CSRF token protection on all POST forms and session-authenticated JSON endpoints
- Bcrypt password hashing (`PASSWORD_DEFAULT`); server-side prepared statements for all SQL
- HTML output escaping via `e()` throughout; rich CKEditor content sanitized through an allowlist (`sanitizeRichHtml()`) on render
- Baseline security headers (X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS under HTTPS) and a locked-down Content-Security-Policy
- **All front-end libraries self-hosted** — no third-party CDNs at runtime
- File upload validation (MIME whitelist, size limit, extension derived from validated MIME; attachments stored outside webroot); SVG disallowed for logos
- Permission checks on every route (`Auth::requirePermission()` / `Auth::requireRole()`); fail-closed ticket visibility
- Brute-force throttling on web and API login; TOTP attempt cap and replay protection; timing-leak-equalized login
- Host-header poisoning guard; SSRF restrictions on outbound webhooks; DMARC verification on inbound email commands; CSV formula-injection neutralization
- Installer locked after first run via `storage/installed.lock` **and** a live check that the database has no admin user yet

> ⚠️ **Operational warning — the admin rescue script.** `scripts/admin/rescue.php` resets any user's password or role **with no authentication** (it is an emergency break-glass tool, run from the CLI). Keep it **outside the web root** and **delete any copy you place under `public/`** the moment you are done. A copy left web-accessible is an instant account-takeover endpoint. `public/rescue.php` and `/rescue.php` are gitignored to discourage this, but the responsibility is operational.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.0+ (strict types) |
| Database | MySQL 5.7+ / 8.0 (InnoDB, utf8mb4) |
| Frontend | Bootstrap 5.3, Bootstrap Icons, Chart.js, CKEditor 5, Driver.js, Sortable.js — all self-hosted under `public/assets/vendor/` (no CDNs) |
| Email | PHPMailer 6 (SMTP out), Microsoft Graph API (inbound + OOF + Teams) |
| Markdown | League CommonMark 2 |
| Routing | Custom `Router.php` (no framework) |
| Auth | Session-based with bcrypt hashing; RBAC permission system; optional TOTP 2FA and Microsoft 365 SSO |

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher (MySQL 8 supported)
- Composer
- PHP extensions: `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `zip`, `curl` (Microsoft Graph / AI / Teams integrations), `gd` (PWA icon generation)

## Installation

### Option A — Web Installer (recommended)

1. Clone the repository and install dependencies:
   ```bash
   git clone https://github.com/chris-jasztrab/openhelpdesk localdesk
   cd localdesk
   composer install
   ```
2. Point your web server's document root at the `public/` directory.
3. Visit **`/install/`** in your browser and follow the six-step wizard:
   - **Requirements** — checks PHP extensions, directory permissions, and URL rewriting.
   - **Database** — enter credentials; optionally create the database automatically.
   - **Application** — set app name, URL, and timezone.
   - **Admin Account** — create the first administrator.
   - **Mail Server** — configure SMTP (skippable; can be set later in Settings).
   - **Review & Install** — confirm and run the installation.
4. After installation, delete or restrict access to the `/install/` directory. The success page lists the recommended cron jobs.

### Option B — Manual Setup

```bash
# 1. Clone and install dependencies
git clone https://github.com/chris-jasztrab/openhelpdesk localdesk
cd localdesk
composer install

# 2. Configure environment
cp .env.example .env
# Edit .env with your database credentials and app URL

# 3. Seed the database (creates DB, applies schema, stamps migrations, inserts sample data)
#    You will be prompted to choose a password for each of the three accounts.
php database/seed.php

# 4. Configure your web server (see platform instructions below)
```

The seeder **prompts you to set a password** for each of the three accounts below — there are no default or generic passwords anywhere. For an unattended/scripted install you can instead supply them via the `SEED_ADMIN_PASSWORD`, `SEED_AGENT_PASSWORD`, and `SEED_USER_PASSWORD` environment variables. Then visit your configured site URL and log in with one of those accounts.

Database migrations apply themselves automatically on every request, so upgrading is just `git pull` (plus `composer install` when dependencies change).

> **Windows note — `composer install` failing to delete a temp file.** On Windows you may see Composer abort with `Could not delete …\vendor\composer\tmp-xxxx.zip: This can be due to an antivirus or the Windows Search Indexer locking the file while they are analyzed.` This is a harmless race: a real-time scanner (typically Windows Defender) or the Search Indexer opens the freshly-extracted archive before Composer can remove it. Simply **re-run `composer install`** — it resumes and completes. To prevent it entirely, exclude the project directory and Composer's cache (`%LOCALAPPDATA%\Composer`) from real-time scanning and Search indexing.

---

## Web Server Configuration

The `DocumentRoot` (or equivalent) must point to the **`public/`** subdirectory, not the project root. URL rewriting must be enabled so that all requests are routed through `public/index.php`.

### XAMPP (Windows)

1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and add:

   ```apache
   <VirtualHost *:80>
       ServerName localdesk.test
       DocumentRoot "C:/xampp/htdocs/localdesk/public"

       <Directory "C:/xampp/htdocs/localdesk/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

2. Add `127.0.0.1 localdesk.test` to `C:\Windows\System32\drivers\etc\hosts`.
3. Restart Apache from the XAMPP Control Panel.
4. Make sure `mod_rewrite` is enabled — in `httpd.conf` the line `LoadModule rewrite_module modules/mod_rewrite.so` must be uncommented.
5. Set `APP_URL=http://localdesk.test` in your `.env` file.

### LAMP Stack (Ubuntu / Debian)

Enable `mod_rewrite` and create a virtual host:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

```bash
sudo nano /etc/apache2/sites-available/localdesk.conf
```

```apache
<VirtualHost *:80>
    ServerName openhelpdesk.example.com
    DocumentRoot /var/www/localdesk/public

    <Directory /var/www/localdesk/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

```bash
sudo a2ensite localdesk.conf
sudo systemctl reload apache2
```

Set `APP_URL=http://openhelpdesk.example.com` in your `.env` file.

### Windows IIS

1. Install the **URL Rewrite** module from the IIS website (required for routing).
2. Install **PHP** via the Web Platform Installer or manually, and register it with IIS as a FastCGI handler.
3. In **IIS Manager**, create a new site pointing its **Physical path** to the `public\` subdirectory of the project.
4. The `public\.htaccess` rules are not read by IIS. Create a `web.config` file inside `public\` with equivalent rewrite rules:

   ```xml
   <?xml version="1.0" encoding="UTF-8"?>
   <configuration>
     <system.webServer>
       <rewrite>
         <rules>
           <rule name="OpenHelpDesk Front Controller" stopProcessing="true">
             <match url="^(.*)$" />
             <conditions>
               <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
               <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
             </conditions>
             <action type="Rewrite" url="index.php" />
           </rule>
         </rules>
       </rewrite>
     </system.webServer>
   </configuration>
   ```

5. Ensure the IIS application pool identity has **read/write** access to the `storage\` directory.
6. Set `APP_URL` in your `.env` to match the IIS site binding URL.

## Seed Accounts (manual setup only)

`php database/seed.php` creates these three accounts. **It has no default passwords** — you choose one for each account at the prompt (or supply them via the environment variables shown).

| Email | Role | Password |
|-------|------|----------|
| `admin@localdesk.user` | Admin | you set it (`SEED_ADMIN_PASSWORD`) |
| `agent@localdesk.user` | Agent | you set it (`SEED_AGENT_PASSWORD`) |
| `user@localdesk.user` | End User | you set it (`SEED_USER_PASSWORD`) |

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application display name | `OpenHelpDesk` |
| `APP_URL` | Base URL for email links | `http://localhost:8000` |
| `APP_DEBUG` | Show detailed errors (keep `false` in production) | `false` |
| `APP_TIMEZONE` | PHP timezone | `UTC` |
| `MAIL_ENABLED` | Master switch for all outbound email — set `false` on dev/test copies of a production database | `true` |
| `DB_HOST` | MySQL host | `127.0.0.1` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name | `localdesk` |
| `DB_USER` | Database user | `root` |
| `DB_PASS` | Database password | *(empty)* |
| `UPLOAD_MAX_SIZE` | Max attachment size in bytes | `20971520` (20 MB) |
| `UPLOAD_ALLOWED_TYPES` | Comma-separated MIME whitelist for attachments | PDF, images, Office docs, text, zip |
| `SLA_CRON_TOKEN` | Secret token for web-based SLA cron | *(empty)* |

SMTP settings are configured through the admin UI at **Settings → Email / SMTP**.

## Key Endpoints

| Path | Description |
|------|-------------|
| `/install/` | Web-based setup wizard (removed after install) |
| `/` | Home (redirects by role) |
| `/login` · `/forgot` | Sign in and self-service password reset |
| `/auth/microsoft` | Microsoft 365 SSO entry point |
| `/profile` | User profile (name, password, theme, notifications, canned responses, 2FA) |
| `/kb` | Public knowledge base (no login required) |
| `/portal` | End-user portal (tickets, KB, help) |
| `/agent` | Agent dashboard |
| `/agent/tickets` | Agent ticket queue (Table / Compact / Card views) |
| `/agent/tickets/board` | Kanban board (also `/admin/tickets/board`) |
| `/agent/wallboard` | Live operations wallboard |
| `/agent/floor` | Floor mode (touch-first tablet queue) |
| `/manager` | Group manager area (team skill management) |
| `/admin` | Admin dashboard |
| `/admin/tickets` | All tickets |
| `/admin/users` · `/admin/groups` · `/admin/roles` | User, group, and permission-level management |
| `/admin/recurring-tickets` | Recurring / preventive-maintenance schedules |
| `/admin/kb/articles` | Knowledge base management |
| `/admin/reports` | Reports & Analytics overview (14 reports + custom builder) |
| `/admin/audit-log` | Audit log (filter, export, prune) |
| `/admin/forms` | Per-type ticket form builder |
| `/admin/settings` | Everything else: email, Graph, SSO, AI, SLA, business hours, holidays, statuses, routing, automations, escalations, stale tickets, undo send, CSAT, Teams, branding, labels, imports, backup, cron jobs, danger zone |
| `/admin/docs` · `/agent/help` · `/portal/help` | Built-in documentation |
| `/notifications` | Notification inbox |
| `/survey/{token}` | Public CSAT survey |
| `/api/v1/…` | REST API (Bearer token; see `openapi.json`) |
| `/health` | Health check (JSON) |

## Roles & Permissions

Access control is a granular RBAC system. Four roles ship built in — **Admin** (unrestricted), **Agent** (works tickets, templates, and the KB), **Power User** (Agent plus reports), and **End User** (portal only) — and admins can create **custom permission levels** at `/admin/roles`, granting individual permissions such as `tickets.view_all`, per-action bulk permissions, internal/external forwarding, `kb.articles.manage`, `reports.view`, or read-only `oof.view`. Staff can only assign or edit roles at or below their own level, and role changes take effect immediately on live sessions.

| Capability (default grants) | Admin | Power User | Agent | End User |
|---|:---:|:---:|:---:|:---:|
| Submit & track own tickets (portal) | — | — | — | ✓ |
| Work tickets / internal notes / create on behalf of users | ✓ | ✓ | ✓ | — |
| Ticket templates & recurring schedules | ✓ | ✓ | ✓ | — |
| Reports & Analytics | ✓ | ✓ | — | — |
| Two-Factor Authentication (TOTP) | ✓ | ✓ | ✓ | — |
| User / group / role management | ✓ | — | — | — |
| Settings, branding, automations, escalations | ✓ | — | — | — |
| KB management | ✓ | grantable | grantable | — |
| Audit log, backup, danger zone | ✓ | — | — | — |

## Project Structure

```
localdesk/
├── config/
│   ├── version.php             # APP_VERSION constant (Semantic Versioning)
│   ├── settings_index.php      # Search index for the settings UI
│   └── labels.default.json     # Default label set for the label customizer
├── database/
│   ├── migrations/             # Numbered migration files (auto-applied on startup)
│   ├── migrate.php             # Migration runner (invoked by src/bootstrap.php)
│   ├── schema.sql              # Full database schema snapshot (57 tables)
│   ├── seed.php                # Drop, recreate, and seed demo data (dev only)
│   ├── seed_test_data.php      # Seed two months of realistic test data (dev only)
│   └── seed_training_tickets.php  # Seed training tickets (dev only)
├── public/
│   ├── index.php               # Front controller
│   ├── install/                # Web installer (delete after setup)
│   ├── .htaccess               # Apache rewrite rules
│   ├── assets/                 # CSS/JS + self-hosted vendor libraries (no CDNs)
│   ├── sla-cron.php            # Standalone SLA recalculation script
│   └── uploads/                # Branding assets and user avatars
├── scripts/
│   ├── admin/rescue.php               # CLI admin password reset / role change
│   ├── process-replies.php            # Inbound email processor (Microsoft Graph)
│   ├── process-escalations.php        # Time-based escalation rule runner
│   ├── process-recurring-tickets.php  # Recurring ticket generator
│   ├── process-scheduled-reports.php  # Scheduled report mailer
│   ├── process-stale-tickets.php      # Stale-ticket follow-ups
│   ├── process-oof-coverage.php       # Out-of-office coverage (Graph)
│   ├── process-secret-reminders.php   # Graph secret-expiry reminders
│   └── ai-classify-backfill.php       # Backfill AI classification on old tickets
├── src/
│   ├── AI.php                  # AI classifier abstraction (Anthropic / OpenAI)
│   ├── Auth.php                # Session auth + RBAC permission checks
│   ├── Dashboard.php           # Dashboard metrics
│   ├── Database.php            # PDO singleton connection
│   ├── Holidays.php            # Holiday calendar / business-day logic
│   ├── PWA.php                 # Manifest, service worker, icon generation
│   ├── RecurringTickets.php    # Recurring schedule logic
│   ├── Router.php              # Lightweight request router
│   ├── Sla.php                 # SLA computation (business hours aware)
│   ├── Teams.php               # Microsoft Teams webhook notifications
│   ├── graph.php               # Microsoft Graph API client helpers
│   ├── bootstrap.php           # App init (env, session, security headers, migrations)
│   ├── helpers.php             # CSRF, flash, render, email, notification helpers
│   ├── routes.php              # Top-level routes (home, auth, profile, public KB, surveys)
│   └── routes/
│       ├── admin.php           # Admin routes (users, settings, KB, tickets, reports, …)
│       ├── agent.php           # Agent routes (tickets, banners, canned responses)
│       ├── api.php             # REST API v1 (Bearer token)
│       ├── drafts.php          # Server-side draft autosave
│       ├── floor.php           # Floor mode (tablet)
│       ├── kanban.php          # Kanban board views + API
│       ├── manager.php         # Group manager area
│       ├── portal.php          # Portal routes (user tickets, KB, attachments)
│       └── pwa.php             # PWA manifest / service worker / offline shell
├── storage/
│   ├── attachments/            # Ticket file attachments (outside webroot)
│   ├── backups/                # Backup zip files (outside webroot)
│   └── logs/                   # smtp.log and other runtime logs
├── templates/
│   ├── layouts/                # Base and app layouts
│   ├── pages/                  # Admin, agent, portal, and public views
│   ├── partials/               # Reusable components (navbar, sidebar, tours, …)
│   └── emails/                 # HTML email templates
├── tests/
│   ├── Feature/                # PHPUnit integration tests (authenticated HTTP)
│   └── Support/                # Test base class and database seeder
├── docs/
│   ├── API.md                  # Human-readable REST API docs
│   └── screenshots/            # README screenshots
├── openapi.json                # OpenAPI 3.0.3 specification for the REST API
├── GETTING_STARTED.md          # Full administrator walkthrough
├── composer.json
├── .env.example
└── README.md
```

## Cron Jobs

Background scripts keep SLA states, escalations, recurring tickets, stale-ticket follow-ups, out-of-office coverage, scheduled reports, and email processing up to date. **Admin → Settings → Cron Jobs** shows a live Running/Stale/Not-configured status for each job and generates copy-paste commands for both Linux (`crontab`) and Windows (`schtasks`) with your real installation paths. The Linux versions:

```bash
*/5  * * * * php /path/to/localdesk/public/sla-cron.php
*/5  * * * * php /path/to/localdesk/scripts/process-replies.php
*/15 * * * * php /path/to/localdesk/scripts/process-escalations.php
*/15 * * * * php /path/to/localdesk/scripts/process-recurring-tickets.php
*/15 * * * * php /path/to/localdesk/scripts/process-oof-coverage.php
*/30 * * * * php /path/to/localdesk/scripts/process-scheduled-reports.php
0    * * * * php /path/to/localdesk/scripts/process-stale-tickets.php
0    8 * * * php /path/to/localdesk/scripts/process-secret-reminders.php
```

Only the SLA recalculation is required; the rest are needed only if you use the corresponding feature. The SLA recalculation can alternatively be triggered via HTTP with a secret token (set `SLA_CRON_TOKEN` in `.env`):

```
GET https://yoursite.com/sla-cron.php?token=YOUR_SLA_CRON_TOKEN
```

Admins can also manually recalculate SLA states from **Settings → SLA Policies → Recalculate All**.

## Inbound Email (Microsoft Graph)

Inbound email runs over the **Microsoft Graph API** (OAuth2 client-credentials against Microsoft 365 / Exchange Online), configured under **Settings → Email → Graph API**:

- **Reply-to-ticket** — replies to notification emails are threaded onto the ticket as comments, with out-of-office/auto-reply detection and spoofed-sender protection
- **Email-to-ticket** — mail sent to a monitored mailbox is converted into new tickets
- **Hashtag commands** — staff can control tickets from their inbox (`#close`, `#resolve`, `#priority high`, …), verified via DMARC

Run `scripts/process-replies.php` via cron, or trigger it from **Settings → Run Now**. The same Graph app powers out-of-office coverage and secret-expiry reminders.

## License

Open source and free to the world under the [MIT License](LICENSE) — use it, modify it, ship it, no strings attached.

### Third-Party Software

OpenHelpDesk stands on a number of open-source projects. A full inventory with
versions and licenses is in [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).
As of v2.132.6 all front-end libraries are self-hosted under
`public/assets/vendor/` — nothing is fetched from a CDN at runtime.

> **Note on CKEditor 5:** the bundled rich-text editor is licensed
> **GPL-2.0-or-later** (or commercially) by CKSource — separately from this
> project's MIT license. If that doesn't suit your distribution needs, obtain a
> commercial CKEditor license or swap in a permissively-licensed editor
> (e.g. Quill, TipTap, Trix).

## Credits & Donations

OpenHelpDesk was vibe coded by Chris Jasztrab at the Waterloo Public Library.

If this software helped you out, please consider making a donation to the Waterloo Public Library: <https://www.wpl.ca/your-library/donate/>
