# Changelog

All notable changes to LocalDesk will be documented in this file.

---

## Unreleased
- Work on outgoing email templates.
- Profile Avatars
- Ticket Builder
- Office 365 SSO Login.
- Login with your Office 365 credentials.


### Knowledge Base Import
- CSV import tool for KB articles under Admin > Settings > Import KB
- Upload CSV with `title` and `body_markdown` columns (plus optional `category`, `status`, `tags`)
- Preview page showing article count, categories, draft/published breakdown
- Auto-creates categories and "General" folders as needed
- Unique slug generation with collision avoidance
- Transaction-based import for all-or-nothing safety

### User Profile
- "My Profile" page accessible from the navbar user dropdown for all authenticated users
- Users can update their first name and last name
- Password change with current password verification, minimum 8 characters, and confirmation matching
- Session is refreshed on save so the navbar reflects name changes immediately

### Dark Mode / Light Mode
- Per-user theme preference (light or dark) selectable from the My Profile page
- Leverages Bootstrap 5.3 native `data-bs-theme` attribute for automatic component theming
- Custom dark mode overrides for sidebar, search dropdown, mention dropdown, stat cards, and table headers
- Theme preference stored per-user in the settings table and applied on every page load

### Ticket Export
- CSV export of tickets from the admin ticket list
- Export respects all active filters: status, priority, type, location, agent, group, search, and date range
- Date range filter (From/To) added to the admin ticket list filter bar
- Export includes: ID, subject, status, priority, type, location, group, assigned agent, creator, tags, created date, due date, and SLA state
- UTF-8 BOM for seamless Excel compatibility

### Branding Settings
- Admin-configurable branding page under Settings > Branding
- Custom logo upload (JPG, PNG, GIF, WEBP, SVG) displayed in the navbar
- Configurable application name shown in navbar, page titles, login page, and emails
- Color scheme customization: primary color, primary hover, navbar gradient start/end
- Live preview panel showing navbar, button, badge, and login page appearance
- Reset to defaults button for restoring original color scheme
- All hardcoded color values replaced with CSS variables for consistent theming
- Email templates use dynamic brand color for call-to-action buttons

### Automations
- Rule-based ticket automation system for admins (Settings > Automations)
- Trigger on ticket creation or ticket update events
- Condition builder: match on ticket type, priority, status, location, group, or assigned agent with equals/not equals/is empty/is not empty operators
- Action builder: set group, assign agent, set priority, set status, or add tag
- Multiple conditions (AND logic) and multiple actions per rule
- Enable/disable toggle per automation
- Sort order for controlling execution priority
- Automation actions logged in ticket timeline as internal system entries

### Email Reply Integration
- Inbound email parsing so users and agents can reply to notification emails and have responses automatically added as comments on the corresponding ticket
- Match incoming messages to tickets via `Message-ID` / `In-Reply-To` headers and `X-Ticket-ID`
- Strip email signatures and quoted text to extract only the new reply content
- Respect role permissions: replies from portal users are added as public comments; replies from agents/admins follow their default visibility setting
- Reject replies from unknown senders with a bounce notification

### Canned Responses
- Admin-configurable library of reusable response templates for agents
- New `canned_responses` table with title, body, category, and sort order
- Settings page under Admin > Settings for full CRUD management of canned responses
- Quick-insert dropdown in the agent and admin ticket comment forms
- Category grouping for organizing templates (e.g. Greetings, Troubleshooting, Closures)
- Placeholder variable support (e.g. `{{ticket_id}}`, `{{user_name}}`, `{{agent_name}}`) with automatic substitution on insert

### Hashtag System
- Dedicated tag management page under Admin > Settings for creating, editing, and deleting tags
- Color and description fields on tags for better visual organization
- Tag autocomplete in ticket forms: type `#` to search and select from existing tags
- Tag-based filtering on admin and agent ticket list views
- Tag usage counts displayed on the management page
- Prevent duplicate tag creation with case-insensitive matching

### @Mention Improvements
- Autocomplete dropdown when typing `@` in comment fields, searching agents and admins by name in real time
- Highlight @mentioned names in rendered comments with a distinct style
- Mention validation: only create notifications for valid, matched user names
- Support mentioning by first name only (fuzzy match with disambiguation if multiple matches)
- Notification preference: allow users to mute @mention notifications per-ticket

### CSV Import Tool
- New admin page at Admin > Import for bulk importing users and tickets from CSV files
- Support uploading a single CSV or a ZIP archive containing multiple CSVs (e.g. `users.csv`, `tickets.csv`, `comments.csv`)
- **Dry-run preview mode**: parse and validate the CSV, display a summary of what will be created, updated, or skipped -- without writing to the database
- **Idempotency**: use a unique external ID column (e.g. `external_id`) to detect duplicates; re-importing the same CSV updates existing records rather than creating duplicates
- Field mapping interface showing CSV column headers matched to database fields
- Validation report: row-by-row error and warning list (missing required fields, invalid emails, unknown references)
- Support for importing:
  - **Users**: first name, last name, email, role, location, work phone
  - **Tickets**: subject, description, status, priority, type, location, assigned agent (by email), created by (by email), tags, created date
  - **Comments**: ticket reference, author (by email), message, internal flag, created date
- Transaction-based writes: all-or-nothing per CSV to prevent partial imports
- Import history log showing who imported what and when, with row counts

### Security Hardening
- Rate limiting on login attempts (lockout after repeated failures per IP and per account)
- Content Security Policy (CSP) headers to mitigate XSS
- `X-Frame-Options`, `X-Content-Type-Options`, and `Referrer-Policy` response headers
- Session fixation protection: regenerate session ID on privilege changes beyond login
- Password complexity requirements (minimum length, mixed characters) enforced on user creation and password change
- Secure cookie flags (`HttpOnly`, `SameSite=Lax`, `Secure` when over HTTPS)
- Audit log for admin actions (user creation/deletion, settings changes, role changes)
- Input length limits on all text fields to prevent oversized payloads
- File upload hardening: re-validate MIME type from file contents (not just extension), scan for embedded scripts in uploaded files

---

## 1.0.0 -- 2026-02-13

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
