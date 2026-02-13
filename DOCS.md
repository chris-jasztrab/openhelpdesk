# LocalDesk Documentation

Complete guide for end users, agents, and administrators.

---

## Table of Contents

- [Getting Started](#getting-started)
- [Portal (End Users)](#portal-end-users)
- [Agent Panel](#agent-panel)
- [Admin Area](#admin-area)
- [Settings](#settings)
- [SLA System](#sla-system)
- [Knowledge Base](#knowledge-base)
- [Notifications](#notifications)
- [Email Notifications](#email-notifications)
- [Ticket Lifecycle](#ticket-lifecycle)

---

## Getting Started

### Logging In

Navigate to `/login` and enter your email and password. After signing in you are redirected based on your role:

| Role | Redirect |
|------|----------|
| Admin | `/admin` |
| Agent | `/agent` |
| User | `/portal` |

### Navigation

The top navbar provides quick links between areas:

- **Admin** (admins only) -- full system management
- **Agent Panel** (agents and admins) -- ticket queue and workflow
- **Portal** (all roles) -- submit and track tickets, browse the knowledge base

A notification bell appears for agents and admins, showing unread @mention counts. It polls the server every 15 seconds.

### Roles

| Role | Can Do |
|------|--------|
| **User** | Submit tickets, track own tickets, comment on own tickets, browse the knowledge base |
| **Agent** | View all tickets, update status/priority/assignment, add public comments and internal notes, @mention colleagues |
| **Admin** | Everything agents can do, plus manage users, groups, locations, priorities, ticket types, KB content, email settings, business hours, and SLA policies |

---

## Portal (End Users)

### Dashboard

The portal dashboard at `/portal` shows:

- **Open Tickets** -- count of your tickets in open or in-progress status
- **Resolved** -- count of your resolved or closed tickets
- **Recent Tickets** -- your five most recent tickets with status, priority, and assigned agent

Click **New Ticket** to create a ticket or **View All** to see the full list.

### Viewing Your Tickets

The ticket list at `/portal/tickets` shows all tickets you have submitted, sorted newest first. Each row displays the ticket number, subject, status, priority, type, assigned agent, and creation date. Click any row to open the ticket detail view.

### Creating a Ticket

Navigate to `/portal/tickets/create`. Fill in the following fields:

| Field | Required | Description |
|-------|----------|-------------|
| Subject | Yes | Brief summary of the issue. As you type (3+ characters), matching knowledge base articles are suggested -- check these before submitting. |
| Description | Yes | Detailed explanation of the problem. |
| Ticket Type | No | Category such as IT, Facilities, Marketing, etc. |
| Location | No | Which branch or building the issue relates to. |
| Priority | No | Low, Medium, High, or Critical. |
| Assign To | No | Request a specific agent. Leave blank for automatic routing. |
| Tags | No | Select relevant tags (e.g. hardware, network, printing). |
| Attachments | No | Upload files (max 20 MB each). Allowed types: PDF, JPEG, PNG. |

Your browser and operating system are captured automatically for technical troubleshooting.

After submission you receive a confirmation email and are redirected to the ticket detail page.

### Ticket Detail View

The ticket detail page shows:

- **Description** -- the full issue text
- **Attachments** -- any files attached to the ticket or its comments, with download links
- **Tags** -- applied tags shown as badges
- **Timeline** -- chronological log of all public activity: creation, comments, status changes, assignments, and priority changes. Internal notes added by agents are not visible.
- **Add Comment** -- post a public reply with optional file attachments

The right sidebar shows ticket metadata: status, priority, type, location, assigned agent, and creation date.

### Knowledge Base

Browse the knowledge base at `/portal/kb`:

- **Categories** are shown as cards on the main page
- Click a category to see its **folders**
- Click a folder to see its **articles**
- Articles are rendered from Markdown with full formatting support

Use the **search bar** at the top to find articles by title or content. Results appear in real time.

---

## Agent Panel

### Dashboard

The agent dashboard at `/agent` shows four stat cards:

| Stat | Description |
|------|-------------|
| **Unassigned** | Tickets with no agent assigned (open, in-progress, or pending) |
| **My Tickets** | Tickets assigned to you that are still active |
| **Pending** | All tickets in pending status |
| **Resolved Today** | Tickets resolved today |

Below the stats is a table of the 10 most recent active tickets. Rows assigned to you are highlighted with a light blue background and a "Mine" badge.

### Ticket List

The full ticket list at `/agent/tickets` shows all tickets in the system with these columns:

| Column | Description |
|--------|-------------|
| # | Ticket ID |
| Subject | Issue title ("Mine" badge if assigned to you) |
| Status | Open, In Progress, Pending, Resolved, or Closed |
| Priority | Color-coded badge |
| Type | Ticket category |
| Assigned To | Agent name or "Unassigned" |
| Created By | The user who submitted the ticket |
| Location | Branch or building |
| SLA | Stopwatch icon -- green (on track), yellow (warning), or red (breached) |
| Created | Submission date |
| Due | Due date (bold red if overdue) |

Click any row to open the ticket.

### Working a Ticket

The ticket detail view at `/agent/tickets/{id}` has two columns.

**Left column:**

- **Description** -- the full issue
- **Attachments** -- files on the ticket and its comments. Internal-note attachments are labeled with an "Internal" badge.
- **Tags** -- applied tags
- **Timeline** -- full activity log including internal notes. Internal notes have a yellow background, a lock icon, and an "Internal" badge.
- **Add Comment** -- write a public reply or an internal note
  - Check **"Internal note (not visible to the user)"** to make it private to agents and admins
  - Use the **@mention** buttons to insert agent names. Mentioned agents receive a notification.
  - Attach files if needed

**Right column:**

- **Details** -- status, priority, type, assigned agent, creator (name and email), location, created date, due date, browser info, and OS info. Overdue dates appear in red.
- **SLA** (if configured) -- current SLA state, whether timers are paused, first response status (responded or due by), and resolution due date.
- **Update Ticket** -- change status, priority, or assignment. Each change is logged in the timeline.

### First Response Tracking

When you post the first non-internal comment on a ticket you did not create, the system records the first response timestamp. This is used by SLA tracking to determine whether the first-response target was met.

---

## Admin Area

### Dashboard

The admin dashboard at `/admin` shows:

- **Total Tickets** -- all tickets in the system
- **Open Tickets** -- tickets in open, in-progress, or pending status
- **Users** -- total user count
- **Agents** -- count of users with agent or admin role

Below the stats:

- **Recent Activity** -- the 10 most recent timeline entries across all tickets, linking to each ticket
- **Quick Actions** -- shortcuts to View All Tickets, Add User, Manage Locations, Manage Priorities, and Settings

### Managing Tickets

Admin ticket views at `/admin/tickets` and `/admin/tickets/{id}` work identically to the agent views. Admins can see all tickets and all internal notes.

### Managing Users

Navigate to `/admin/users` to see all users with their avatar, name, email, role, phone, location, and creation date.

**Creating a user** at `/admin/users/create`:

| Field | Required | Notes |
|-------|----------|-------|
| First Name | Yes | |
| Last Name | Yes | |
| Email | Yes | Must be unique |
| Password | Yes | |
| Permission Level | Yes | Admin, Agent, or End User |
| Work Phone | No | |
| Assigned Location | No | Select from configured locations |
| Avatar | No | JPG, PNG, GIF, or WEBP. Max 2 MB. |

**Editing a user** at `/admin/users/{id}/edit` -- same fields, but password is optional (leave blank to keep the current one). You can also remove the avatar via a checkbox.

**Deleting a user** -- click the trash icon on the user list. You cannot delete your own account.

---

## Settings

All configuration is managed under the Settings area. The settings page uses a tabbed navigation with these sections:

### Email / SMTP

Configure outbound email at `/admin/settings`:

| Field | Description |
|-------|-------------|
| SMTP Host | Server address (e.g. `smtp.gmail.com`) |
| Port | Default `587` |
| Encryption | TLS, SSL, or None |
| Username | SMTP login |
| Password | SMTP password (leave blank on edit to keep current) |
| From Address | Sender email address |
| From Name | Sender display name |

Click **Send Test Email** to verify your configuration. The test email is sent to your own address.

### Business Hours

Configure when SLA timers count at `/admin/settings/business-hours`:

- **Timezone** -- select your timezone (e.g. America/Toronto)
- **Weekly Schedule** -- for each day (Monday through Sunday):
  - Toggle whether the day is a business day
  - Set start and end times (e.g. 09:00 to 17:00)

SLA timers only count minutes that fall within business hours. Non-business time is skipped.

### SLA Policies

Define response and resolution targets at `/admin/settings/sla-policies`:

For each priority level, set:

- **First Response** -- target time in business minutes for the first agent reply
- **Resolution** -- target time in business minutes for ticket resolution

A human-readable conversion appears as you type (e.g. "2h 30m", "1d 4h" assuming 8-hour business days).

Set both values to 0 to disable SLA for a priority.

Click **Recalculate All** to recompute SLA state for all active tickets immediately.

### Locations

Manage branch/building locations at `/admin/locations`:

| Field | Required |
|-------|----------|
| Location Name | Yes |
| Address | No |
| Description | No |

Locations appear as options when creating tickets and assigning users.

### Priorities

Manage ticket priority levels at `/admin/priorities`:

| Field | Required | Notes |
|-------|----------|-------|
| Priority Name | Yes | e.g. Low, Medium, High, Critical |
| Color | Yes | Color picker with live preview badge |
| Sort Order | No | Lower numbers appear first |

### Ticket Types

Manage ticket categories at `/admin/types`:

| Field | Required |
|-------|----------|
| Type Name | Yes |
| Sort Order | No |

### Groups

Organize staff into departments at `/admin/groups`:

| Field | Required | Notes |
|-------|----------|-------|
| Group Name | Yes | e.g. IT, Facilities, Marketing |
| Description | No | |
| Sort Order | No | |
| Members | No | Checkboxes for all agents and admins. Users can belong to multiple groups. |

The group list shows a member count badge for each group.

---

## SLA System

### How It Works

1. **Admin configures business hours** and **SLA policies** per priority in Settings.
2. When a ticket is created with a priority that has an SLA policy, the system calculates two deadlines:
   - **First Response Due** -- when an agent must first reply
   - **Resolution Due** -- when the ticket must be resolved
3. Both deadlines are calculated in **business minutes**, skipping weekends and non-business hours.
4. The ticket's SLA state is one of:
   - **On Track** (green) -- within target
   - **Warning** (yellow) -- 80% or more of the allowed time has elapsed
   - **Breached** (red) -- past the deadline

### Pause and Resume

When a ticket's status changes to **Pending**, SLA timers pause. When the ticket moves back to an active status (open, in progress), timers resume and the due dates are extended by the paused duration (in business minutes).

### Priority Changes

If a ticket's priority is changed, SLA due dates are recalculated from the ticket's creation time using the new priority's policy.

### Periodic Recalculation

SLA states are updated by a cron job (recommended: every 5 minutes). Admins can also trigger a manual recalculation from the SLA Policies settings page.

---

## Knowledge Base

### Admin Management

The knowledge base has a three-level hierarchy:

```
Category
  └── Folder
        └── Article
```

Manage each level from the admin sidebar under Knowledge Base:

- **Categories** (`/admin/kb/categories`) -- top-level groupings with name, description, and sort order
- **Folders** (`/admin/kb/folders`) -- belong to a category, with name, description, and sort order
- **Articles** (`/admin/kb/articles`) -- belong to a folder, with:
  - Title
  - Folder assignment
  - Status: **Draft** (not visible to users) or **Published**
  - Markdown body with full formatting support
  - Sort order

Use the **Preview** button when editing an article to see the rendered output.

### Portal Access

All authenticated users can browse published articles at `/portal/kb`. The search bar performs real-time full-text search across article titles and content.

### Markdown Support

Articles support standard Markdown:

- Headings (`# H1` through `###### H6`)
- Bold (`**text**`), italic (`*text*`)
- Links (`[text](url)`)
- Lists (ordered and unordered)
- Code blocks (fenced with triple backticks)
- Blockquotes (`> text`)
- Tables
- Images (`![alt](url)`)

### KB Suggestions on Ticket Creation

When a user types a subject while creating a ticket, the system searches the knowledge base for matching published articles. Up to 5 results are shown as suggestions, potentially helping the user resolve their issue without submitting a ticket.

---

## Notifications

### @Mentions

In any ticket comment or internal note, type `@First Last` (matching an agent or admin's full name) to mention them. The mentioned user receives an in-app notification.

### Notification Page

View all notifications at `/notifications`. Each notification shows:

- Who mentioned you
- Which ticket and what they said
- When it happened
- Whether it has been read

Actions:

- **Mark read** -- dismiss a single notification
- **Mark All Read** -- dismiss all notifications at once

The notification bell in the navbar shows the unread count and updates automatically.

---

## Email Notifications

The system sends emails for two events (requires SMTP to be configured in Settings):

### Ticket Created

Sent to the **ticket creator** when they submit a new ticket. Contains:

- Ticket number and subject
- Status, type, location, and priority (if set)
- Full description text
- Link to view the ticket in the portal

### Ticket Updated

Sent to the **ticket creator** when an agent or admin adds a public comment. Not sent for internal notes or when the creator comments on their own ticket. Contains:

- Ticket number and subject
- The comment text and author name
- Link to view the ticket

Emails include a `Message-ID` header with the ticket ID for threading in email clients.

---

## Ticket Lifecycle

### Statuses

| Status | Meaning |
|--------|---------|
| **Open** | New ticket, not yet being worked on |
| **In Progress** | An agent is actively working on it |
| **Pending** | Waiting on external input or a third party. SLA timers pause. |
| **Resolved** | Issue has been addressed |
| **Closed** | Ticket is finalized and archived |

### Typical Workflow

1. **User creates ticket** -- status is set to Open. SLA timers start if a priority with an SLA policy is assigned.
2. **Agent picks up ticket** -- changes status to In Progress, assigns to self.
3. **Agent replies** -- first public reply records the first response time for SLA.
4. **Waiting on user** -- agent sets status to Pending. SLA timers pause.
5. **User responds** -- agent moves back to In Progress. SLA timers resume with extended deadlines.
6. **Issue fixed** -- agent sets status to Resolved.
7. **Ticket closed** -- admin or agent closes the ticket.

### Timeline

Every action on a ticket is logged in the timeline:

| Action | Description |
|--------|-------------|
| Created | Ticket was submitted |
| Comment | Public reply added |
| Internal Note | Private note (agents/admins only) |
| Status Changed | Status transition with old and new values |
| Priority Changed | Priority transition with old and new values |
| Assigned | Agent assignment with name |
| SLA Initialized | SLA due dates were set |
| SLA Paused | Timers paused (ticket entered pending) |
| SLA Resumed | Timers resumed with extended deadlines |
| First Response | First agent reply was recorded |

### Attachments

Files can be attached when creating a ticket or adding a comment. Limits:

- **Max size**: 20 MB per file (configurable)
- **Allowed types**: PDF, JPEG, PNG (configurable)
- Files are stored securely outside the web root
- Portal users cannot see attachments on internal notes
