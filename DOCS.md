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

- **Description** — the full issue text.
- **Timeline** — chronological log of all public activity: creation, replies, and status changes. Internal notes and system events added by agents are **not** visible to portal users. File attachments are shown inline within the entry they were uploaded with.
- **Add Reply** — post a public reply with optional file attachments.
- **Edit** — update the subject and description of your own ticket (available while the ticket is not closed).
- **Close Ticket** — close your own open ticket if the issue is resolved or the request is no longer needed.

The sidebar shows ticket metadata: status, priority, type, location, assigned agent, and creation date.

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
| **Unassigned** | Tickets with no agent assigned (open, in-progress, or pending). Click to jump to the filtered ticket list. |
| **My Tickets** | Tickets assigned to you that are still active. Click to jump to your personal queue. |
| **Pending** | All tickets in pending, waiting on customer, or waiting on third party status. |
| **Resolved Today** | Tickets resolved or closed today. |

Below the stats is a **Recent Tickets** widget showing your most recently updated tickets. You can work on tickets directly from the widget:

- Click any row to open the ticket.
- Hover the **Agent**, **Type**, or **Group** cell to reveal a chevron (▾). Click it to change that field inline without leaving the dashboard.
- Use the **Columns** button (top-right of the widget) to choose which columns appear. Your selection is saved automatically.

### Ticket List

The full ticket list at `/agent/tickets` shows all permitted tickets. Use the **Columns** button to toggle which columns are displayed. Available columns include: Status, Priority, Type, Assigned To, Group, Created By, Location, SLA, Created, Due.

Click any row to open the ticket.

#### Inline Actions

Hover a cell in the **Agent**, **Type**, or **Group** column to reveal a chevron dropdown:

- **Agent** — quick-assign without opening the ticket. If the ticket has a group, only that group's members are shown.
- **Type** — change the ticket type in one click.
- **Group** — reassign the ticket to a different group.

#### Filtering

Click the **Filters** button to open the slide-out filter panel. Available filters:

| Filter | Options |
|--------|---------|
| Status | Multi-select |
| Priority | Multi-select |
| Type | Multi-select |
| Location | Multi-select |
| Agent | Unassigned, Mine, or a specific agent |
| Group | None or a specific group |
| Search | Free-text subject search |
| Watched | Your watched tickets only |
| Resolved Today | Tickets resolved or closed today |

**Saved Filters** — save any filter combination as a named preset. Mark one as your default to apply it automatically on page load. Share presets with the team using the share toggle.

#### Bulk Actions

Tick the checkbox column to select multiple tickets. A bulk action bar appears at the bottom with: Assign, Change Status, Change Priority, Change Group, Merge, Close.

### Working a Ticket

The ticket detail view at `/agent/tickets/{id}` has three columns.

**Left column — Conversation:**

- **Description** — the full issue text, rendered as rich text if entered with the editor.
- **Timeline** — full activity log. Replies have a white background. Internal notes are highlighted (configured colour) and never shown to portal users. System events (assignments, status changes, SLA updates) are highlighted separately and also hidden from portal users. File attachments are displayed inline within the entry they were uploaded with.
- **Reply / Add Note / Forward** — three buttons open the reply composer:
  - **Reply** — public message sent to the requester.
  - **Add Note** — private internal note (agents and admins only).
  - **Forward** — send ticket details to an external email address.
- The composer uses CKEditor 5 (bold, italic, lists, links). Attach files directly in the composer.
- **Send & Set Status** — click the arrow on the Send button to send a reply and change the ticket status in one action (Resolved, Closed, Pending, Waiting on Customer, Waiting on Third Party).
- **Canned Response** — click to pick a saved reply template. Tokens are substituted with real ticket values on insertion.

**Middle column — Details:**

- Status, priority, type, assigned agent, group, creator, location, created date, due date, browser, OS, and any custom fields.

**Right column — Actions:**

- **CC** — add or remove users who receive all notification emails for this ticket.
- **Update Ticket** — change status, priority, assigned agent, or group. Each change is logged in the timeline as a system event.
- **SLA** (if applicable) — first response due and resolution due with amber/red warning when deadlines approach or breach. Timers pause automatically when the ticket is set to Waiting on Customer or Waiting on Third Party.
- **Watch / Unwatch** — subscribe to all activity notifications on this ticket regardless of assignment.
- **Merge** — combine a duplicate ticket into this one. The duplicate is closed; its history links to this ticket.
- **Split** — move selected timeline entries to a new separate ticket.

### First Response Tracking

When you post the first public reply on a ticket you did not create, the system records the first response timestamp for SLA compliance reporting.

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
| Location Ticket Visibility | No | When enabled, this user can view all tickets at their assigned location, even as an end user. See [Location Ticket Visibility](#location-ticket-visibility) below. |

**Editing a user** at `/admin/users/{id}/edit` -- same fields, but password is optional (leave blank to keep the current one). You can also remove the avatar via a checkbox.

**Deleting a user** -- click the trash icon on the user list. You cannot delete your own account.

#### Location Ticket Visibility

End users normally only see tickets they submitted themselves. The **Location Ticket Visibility** toggle on the user edit page lets an admin promote a specific end user to see every ticket at their assigned location — useful for branch managers, department heads, or administrative staff who need situational awareness without being made agents.

When enabled, the portal ticket list at `/portal/tickets` gains a **scope switcher**: *My tickets* (default) vs. *All tickets at my location*. The user can open, read, comment on, and download attachments for any ticket at their location — subject to the per-ticket-type carve-outs below.

**Ticket types are not all shared equally.** Two flags on the ticket type control whether a type participates in location visibility:

- **Confidential** (`is_confidential`) — hard restriction. Confidential tickets are only visible to members of the type's assigned group and are **never** exposed via location visibility. Admins outside the group must re-authenticate to access them, and all access is logged.
- **Visible to Location Ticket Visibility users** (`show_to_location_visibility`) — soft restriction. Unchecking this hides the type from location-visibility users without imposing the heavier group-lock / re-auth / audit-log machinery. Intended for routine-but-sensitive categories like **Collections**, **Human Resources**, or **Payroll**, where the tickets shouldn't be broadcast to everyone at the site but don't need the full confidential lockdown.

In both cases the requester still sees their own ticket in the *My tickets* scope, regardless of type.

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

| Field | Required | Notes |
|-------|----------|-------|
| Type Name | Yes | e.g. IT, Facilities, Collections |
| Color | No | Used for the coloured badge on ticket lists and detail views. |
| Sort Order | No | Lower numbers appear first in dropdowns and lists. |
| Default Group | No | When set, only agents in this group are offered as assignees for tickets of this type. Required before enabling **Confidential**. |
| Confidential | No | Hard group-lock. Only members of the **Default Group** can view tickets of this type; admins outside the group must re-authenticate and all access is logged and notified to the group. Confidential types are automatically excluded from Location Ticket Visibility. Removing this flag from an existing type also requires re-authentication. |
| Visible to Location Ticket Visibility users | No | Checked by default. Uncheck for types that should stay restricted to agents — e.g. **Collections**, **Human Resources**, **Payroll** — so they don't surface to end users who have [Location Ticket Visibility](#location-ticket-visibility) enabled. This is a lighter-weight alternative to **Confidential** when you don't need the group-lock, re-auth, or access-log machinery. |
| Stale Threshold (hours) | No | Per-type override for the global stale-ticket threshold. Leave blank to inherit the global setting from **Settings → Stale Tickets**. |

**Confidential vs. Visible to Location Ticket Visibility users — which should I use?**

| Use case | Confidential | Visible to Loc. Vis. users |
|----------|:---:|:---:|
| Restrict view to one group's agents | ✅ | — |
| Force admins outside the group to re-auth | ✅ | — |
| Log and notify on every access | ✅ | — |
| Hide from end users with location visibility | ✅ (automatic) | ✅ (if unchecked) |
| Still visible to any agent in the system | — | ✅ |
| Example types | HR investigations, discipline, legal holds | Collections, general HR, payroll |

Pick **Confidential** when the tickets must stay inside a specific department and every access needs to be auditable. Pick **Visible to Location Ticket Visibility users = off** when you simply don't want the tickets broadcast to non-agent staff at the location, but any agent may still work them.

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

Requires SMTP to be configured in Settings → Email / SMTP.

### When Emails Are Sent

| Event | Recipient |
|-------|-----------|
| New ticket submitted | Ticket creator (confirmation) |
| Agent posts a public reply | Ticket creator |
| Ticket merged | Ticket creator of the merged (closed) ticket |
| Ticket resolved | Ticket creator (includes CSAT survey link if enabled) |
| New ticket assigned to agent | Assigned agent |
| New ticket assigned to group (if alerts enabled) | All group members |
| Escalation rule fires (notify creator action) | Ticket creator |
| Welcome email | Newly created user account |
| Password reset | User who requested the reset |

### Customising Email Templates

Admins can customise the subject line, intro message, and button label for each outgoing template at **Admin → Settings → Email Templates**. The intro message field uses a rich-text editor (CKEditor 5) — bold, italic, lists, and links render in the outgoing email. Tokens such as `{{first_name}}`, `{{ticket_id}}`, and `{{subject}}` are replaced with live data when the email is sent.

A **Shared Footer** tab lets you customise the footer text that appears on all outgoing ticket emails.

Each template has a **Reset to default** button to restore the original built-in content.

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
