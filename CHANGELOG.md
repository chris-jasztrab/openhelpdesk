# Changelog

All notable changes to LocalDesk will be documented in this file.

Follows [Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`
- **MAJOR** — breaking changes
- **MINOR** — new backwards-compatible features
- **PATCH** — backwards-compatible bug fixes

To release a new version: update `config/version.php`, add a dated entry below under `## Unreleased`, then move it to a new versioned section.

---

## Unreleased

Planned features not yet implemented:

- Profile Avatars — allow users to upload their own avatar from the profile page (currently admin-managed only)
- Office 365 SSO — login with Microsoft 365 credentials
- Manage team dashboards — configurable per-team dashboard views

---

## 1.1.0 — 2026-03-02

### Bulk Ticket Actions
- Checkbox column on admin and agent ticket list pages for multi-select
- Bulk action bar appears when one or more tickets are selected
- Supported actions: **Assign** (opens agent picker modal), **Close**, **Merge** (lowest-numbered ticket becomes primary), **Delete** (admin only)
- Select-all checkbox in the table header with indeterminate state
- Sticky table header so column names stay visible while scrolling long lists
- Backend route handlers at `POST /admin/tickets/bulk` and `POST /agent/tickets/bulk`

### Reports Navigation Improvement
- Removed tab-strip nav from all report pages; navigation is now exclusively through the clickable report tiles on the Reports Overview page

### PHPUnit Integration Test Suite
- 314 automated tests covering authentication, admin and agent ticket flows, portal features, reports, KB, settings, and more
- Live HTTP tests using Guzzle against a PHP built-in server
- `DatabaseSeeder` creates and tears down isolated test fixtures
- Run with `./vendor/bin/phpunit`

### Slide-Out Filter Panel
- Ticket list filters moved from inline to a collapsible slide-out panel on the right
- Panel state (open/closed) persists in `sessionStorage` across navigation
- Applies to both admin (`/admin/tickets`) and agent (`/agent/tickets`) ticket lists

### Ticket Templates
- Reusable templates with name, description, subject, body, type, and priority fields
- Admins and agents can create, edit, and delete templates; agents can only edit/delete their own
- Mark a template as **shared** to make it available to portal users as a "Start from a Template" picker on the ticket create form
- JS auto-fills subject, body, type, and priority fields when a template is selected — no AJAX needed
- Managed at `/admin/ticket-templates`

### Staff Ticket Creation
- Admins and agents can create tickets directly at `/admin/tickets/create` and `/agent/tickets/create`
- Full field control: subject, description, type, priority, location, status, assigned agent, group, due date, tags
- Admins can create a ticket **on behalf of** another portal user (sets `created_by` to that user)
- Template picker with JS auto-fill available on the creation form

### Two-Factor Authentication (TOTP)
- Optional TOTP-based 2FA for admin and agent accounts
- Setup at `/profile/2fa/setup` using any authenticator app (Google Authenticator, Authy, etc.)
- QR code and manual entry key displayed on setup page
- Admins can reset a user's 2FA from the user management page (`POST /admin/users/{id}/reset-2fa`)
- Portal users do not have access to 2FA (not required for end-user accounts)

### Admin Audit Log
- Comprehensive trail of all admin actions: user creation/deletion, role changes, settings updates, and more
- Displayed at `/admin/audit-log` with actor, action, target, and timestamp
- Records stored in the `audit_log` table

### Per-User Email Notification Preferences
- Users can opt in or out of specific email notification types from the My Profile page
- Preference categories: ticket created, ticket updated, @mention
- Preferences stored per-user and checked before every outgoing notification email

### User Profile View (Admin)
- Admins can view any user's profile page at `/admin/users/{id}`
- Shows user details, role, and a list of open tickets created by that user

### KB Article Feedback, Version History, and Public KB
- **Feedback** — portal users can rate KB articles as helpful or not helpful; ratings stored in `kb_article_ratings`
- **Version History** — every article save creates a revision record in `kb_article_revisions`; admins can view the revision list and restore any prior version
- **Public KB** — KB categories and articles can be marked public; accessible at `/kb` without login; full-text article search available at `/kb/search`

### Five New Reports (Batch 2)
- **Agent Workload** (`/admin/reports/workload`) — heatmap of open ticket counts per agent, broken down by status and SLA state
- **Ticket Trends** (`/admin/reports/trends`) — multi-line volume trend over time, drillable by ticket type or location
- **FCR Rate** (`/admin/reports/fcr`) — first-contact resolution rate with agent breakdown
- **Custom Builder** (`/admin/reports/custom`) — pick any metric, group-by field, and date range to build an ad-hoc report
- **Scheduled Reports** — configure any report to run automatically on a schedule and email the results; managed at `/admin/settings/scheduled-reports`

### Five New Reports (Batch 1)
- **Response Times** (`/admin/reports/response-times`) — average first-response and resolution times by priority
- **Ticket Lifecycle** (`/admin/reports/lifecycle`) — average time tickets spend in each status stage
- **By Location** (`/admin/reports/location`) — ticket volume and resolution rates per location
- **SLA Compliance** (`/admin/reports/sla`) — SLA met vs breached rates with breached ticket list
- **Unresolved Tickets** (`/admin/reports/unresolved`) — all unresolved tickets with aging breakdown

### CSAT Surveys
- Satisfaction survey email sent automatically after a ticket is resolved
- Configurable under Admin → Settings → CSAT (enable/disable, survey message, rating scale)
- Survey responses stored in `csat_surveys` table
- Results shown in the Satisfaction report (`/admin/reports/csat`)

### Concurrent Viewer Warning
- Presence detection on ticket detail views using the `ticket_presence` table
- If another user opens the same ticket, a dismissible warning modal is shown
- Presence records updated on page load and cleared on navigation away

### Additional Ticket Statuses
- **Waiting on Customer** — ticket is pending a response from the submitter
- **Waiting on Third Party** — ticket is pending an external vendor or party
- Both statuses are selectable from the status dropdown and appear in filters and reports

### Escalation Rules
- Time-based escalation policies configurable at Admin → Settings → Escalation Rules
- Define an inactivity threshold (e.g. 24 hours without a response) and one or more actions (reassign agent, change priority, change status, add tag)
- Can be scoped to specific ticket types, priorities, or groups
- Escalation actions logged as internal timeline entries in `escalation_log`
- Rules can be run immediately via the "Run Now" button

### Custom Ticket Form Fields (Workflows)
- Drag-and-drop form builder at `/admin/workflows/ticket-fields`
- Supported field types: short text, long text, dropdown, multi-select, checkbox
- Fields appear on the ticket create and detail views for all roles
- Fields soft-deleted (hidden, not purged) to preserve historical data

### Ticket Merge
- Merge a duplicate ticket into a primary ticket from the ticket detail view
- Timeline entries, CC users, and tags copied from source to target
- Source ticket is closed and marked with a `merged_into_ticket_id` reference
- Merge badge displayed in the ticket list for merged tickets

### Saved & Default Ticket Filters
- Save the current filter set as a named preset on the ticket list
- Set any saved filter as the personal default (applied automatically on page load)
- Share saved filters with the team; shared filters shown with a team icon
- Filters stored in the `saved_filters` table per user

### Persist Filters on Back Navigation
- Ticket list URL (including all active filters and sort) stored in `sessionStorage`
- Breadcrumb "Tickets" link on the ticket detail view restores the previous list URL

### Knowledge Base Article CSV Import
- Bulk import KB articles from a CSV file at Admin → Settings → Import KB
- Required columns: `title`, `body_markdown`; optional: `category`, `status`, `tags`
- Preview page shows article count, category breakdown, and draft/published split before committing
- Auto-creates categories and "General" folders as needed
- Transaction-based: all-or-nothing import for safety

### Customizable Email Templates
- Admins can edit the HTML body of outgoing email templates at Admin → Settings → Email Templates
- Supported templates: ticket created, ticket updated, ticket merged
- Placeholder variables: `{{ticket_id}}`, `{{ticket_subject}}`, `{{user_name}}`, `{{first_name}}`, `{{last_name}}`, `{{agent_name}}`, `{{ticket_url}}`

### Microsoft Graph API (Email Reply Integration)
- Alternative to IMAP for inbound email processing, using Microsoft 365 / Exchange Online via OAuth2
- Configure client ID, tenant ID, and client secret in Settings → Email → Graph API tab
- "Run Now" button in Settings to process the inbox immediately without waiting for cron
- Replaces the earlier IMAP implementation while keeping the same `process-replies.php` interface

### Email Reply Integration (IMAP)
- Inbound email parsing to add replies to tickets automatically as comments
- Match incoming messages to tickets via `Message-ID` / `In-Reply-To` headers and `X-Ticket-ID` header
- Strip email signatures and quoted text to extract only the new reply content
- Respect role permissions: portal user replies become public comments; agent/admin replies follow their default visibility
- Reject replies from unknown senders

### Dark Mode / Light Mode
- Per-user theme preference (light or dark) selectable from My Profile
- Leverages Bootstrap 5.3 native `data-bs-theme` attribute for automatic component theming
- Custom dark mode overrides for sidebar, search dropdown, stat cards, and table headers
- Theme preference stored in the `settings` table and applied on every page load

### User Profile (Name & Password)
- "My Profile" page at `/profile` for all authenticated users
- Update first name and last name; session refreshed immediately so the navbar reflects changes
- Password change with current-password verification, minimum-length enforcement, and confirmation matching

### Ticket Export (CSV)
- Export the current filtered ticket list as a CSV file from the admin ticket list
- Respects all active filters: status, priority, type, location, agent, group, search query, date range
- Exported columns: ID, subject, status, priority, type, location, group, assigned agent, creator, tags, created date, due date, SLA state
- UTF-8 BOM for seamless Excel compatibility

### Branding Settings
- Admin-configurable branding page at Admin → Settings → Branding
- Custom logo upload (JPG, PNG, GIF, WEBP, SVG) displayed in the navbar
- Configurable application name shown in navbar, page titles, login page, and emails
- Color scheme: primary color, primary hover, navbar gradient start/end, timeline entry colors (internal notes, system events)
- Live preview panel showing navbar, button, badge, and login page appearance
- Reset to defaults button; all colors implemented as CSS variables

### Automations
- Rule-based ticket automation at Admin → Settings → Automations
- Triggers: ticket created, ticket updated
- Condition operators: equals, not equals, is empty, is not empty
- Actions: set group, assign agent, set priority, set status, add tag
- Multiple conditions (AND logic) and multiple actions per rule
- Enable/disable toggle and sort-order control per rule

### Database Backup
- One-click backup from Admin → Settings → Backup
- Produces a `.zip` containing a full `mysqldump` of all tables plus uploaded attachments, branding assets, and avatars
- Backups stored in `storage/backups/` (outside webroot)
- Download and delete existing backups from the same page

### Onboarding Tour & Admin Docs
- Six-step walkthrough shown automatically on first admin login
- Replayable from the user dropdown → "Take the Tour"
- Built-in admin documentation at `/admin/docs` with sections on tickets, users, SLA, automations, branding, portal, import, and the knowledge base

### Agent Group Scoping
- Agents who belong to one or more groups see only tickets assigned to those groups in their ticket queue and dashboard stats
- Admins always see all tickets regardless of group membership
- Group restriction banner shown in the agent ticket list when scoping is active

---

## 1.0.0 — 2026-02-13

Initial release.

### Ticket Management
- Create, view, and comment on tickets through the portal
- Ticket statuses: open, in progress, pending, resolved, closed
- Priority levels with color-coded badges (Low, Medium, High, Critical)
- Ticket types for categorization (IT, Marketing, Facilities, Collections, Lifelong Learning, Circulation)
- Location tagging for multi-branch support
- Tag/hashtag system for ticket classification
- File attachments on tickets and comments (PDF, JPEG, PNG; max 20 MB)
- Browser and OS auto-detection on ticket creation
- Due date tracking with overdue highlighting

### Agent Workflow
- Agent dashboard with real-time stats (unassigned, my tickets, pending, resolved today)
- Full ticket queue with SLA state indicators
- Update ticket status, priority, and assignment from the ticket detail view
- Public comments and internal notes (internal notes hidden from portal users)
- @mention system to notify colleagues in comments
- First response time tracking for SLA compliance

### SLA System
- Business hours configuration with timezone support and per-day scheduling
- SLA policies per priority level (first response and resolution targets in business minutes)
- Automatic SLA state computation: on track, warning (80% elapsed), breached
- Pause/resume on pending status with deadline extension
- Recalculation on priority changes
- Cron script for periodic SLA state updates
- Admin recalculate button for immediate refresh

### Knowledge Base
- Three-tier hierarchy: categories, folders, articles
- Markdown article editor with CommonMark rendering
- Draft and published article statuses
- Full-text search from the portal
- KB article suggestions when creating a ticket (subject autocomplete)

### User Management
- Three roles: admin, agent, end user
- User CRUD with avatar upload (JPG, PNG, GIF, WEBP; max 2 MB)
- Location assignment per user
- Work phone field

### Groups
- Organizational groups for agents and admins
- Multi-group membership
- Seeded groups: IT, Collections, Facilities, Lifelong Learning, Marketing, Circulation

### Notifications
- In-app notifications from @mentions in ticket comments
- Unread count badge with 15-second polling
- Mark read individually or all at once

### Email
- SMTP configuration through admin settings UI
- Ticket creation confirmation email to the submitter
- Ticket update email when agents add public comments
- Message-ID threading for email client grouping
- Test email button to verify SMTP configuration

### Settings
- Email / SMTP configuration
- Business hours and timezone
- SLA policies per priority
- Location management
- Priority management with color picker
- Ticket type management
- Group management with member assignment

### Security
- CSRF protection on all forms
- Bcrypt password hashing
- Prepared statements for all database queries
- HTML output escaping
- Role-based access control on every route
- File upload MIME type and size validation
- Attachments stored outside the web root
- Internal notes restricted to agents and admins
