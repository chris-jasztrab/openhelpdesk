# OpenHelpDesk Documentation

Complete guide for end users, agents, and administrators.

This is a summary-level reference. Each area has fuller, searchable help inside the app:

| Audience | In-app help |
|----------|-------------|
| Administrators | **Docs** on the admin rail (`/admin/docs`) |
| Agents and other staff | **Help** on the staff rail (`/agent/help`) |
| End users | **Help** on the portal rail (`/portal/help`) |

Installation, cron setup and server configuration are covered in [GETTING_STARTED.md](GETTING_STARTED.md). The mobile/REST API is documented in [docs/API.md](docs/API.md). Version history lives in [CHANGELOG.md](CHANGELOG.md).

---

## Table of Contents

- [Getting Started](#getting-started)
- [Portal (End Users)](#portal-end-users)
- [Agent Workspace](#agent-workspace)
- [Closing, Merging and Splitting Tickets](#closing-merging-and-splitting-tickets)
- [Time Tracking](#time-tracking)
- [Admin Area](#admin-area)
- [Settings](#settings)
- [Users and Permission Levels](#users-and-permission-levels)
- [Tickets: Statuses, Types, Forms and Routing](#tickets-statuses-types-forms-and-routing)
- [SLA System](#sla-system)
- [Automation](#automation)
- [AI Features](#ai-features)
- [Knowledge Base](#knowledge-base)
- [Notifications](#notifications)
- [Email](#email)
- [Satisfaction Surveys (CSAT)](#satisfaction-surveys-csat)
- [Reports](#reports)
- [Security and Integrations](#security-and-integrations)
- [Data: Imports and Backups](#data-imports-and-backups)
- [REST API](#rest-api)
- [Ticket Lifecycle](#ticket-lifecycle)

---

## Getting Started

### Signing In

Navigate to `/login` and enter your email and password, or click **Sign in with Microsoft** when Microsoft 365 single sign-on is configured. Staff accounts with two-factor authentication enabled are then asked for a 6-digit authenticator code.

- **Forgot password?** (below the password field) emails a one-time reset link that expires after 60 minutes. Accounts that only sign in through SSO have no password to reset.
- If you followed a link while signed out, you land on that page after signing in.
- Repeated failed sign-ins are throttled per email address and per IP address.

After signing in you land on your permission level's home area:

| Permission level | Lands on |
|------------------|----------|
| Admin | `/admin` |
| Agent, Power User, and custom staff levels | `/agent` |
| End User | `/portal` |

### Navigation

Each area has an icon rail down the left side:

| Area | Rail items |
|------|------------|
| Admin | Dashboard, Tickets, Settings, Docs |
| Staff (agents, power users, custom levels) | Dashboard, Tickets, Floor mode\*, Knowledge Base, Settings, Help |
| Portal (end users) | Dashboard, My Requests, Floor mode\*, Knowledge Base, Help |

\* Floor mode only appears on touch devices.

Everything that is not day-to-day ticket work (users, ticket forms, KB management, reports, automations, and so on) lives in the **Settings** area's left-hand menu. That menu only lists the pages your permission level can open, and has its own search box (press `/` on a settings page to focus it) that also finds individual settings.

The top navbar contains:

- **Admin** (admins) and **Agent Panel** (all staff) to switch areas, and **Help**, which opens the help for your role.
- **Global search** (press `/` to focus): searches tickets, contacts (staff only) and knowledge base articles. Type a ticket number to jump straight to it.
- **Notification bell** (staff): unread alert count, refreshed automatically.
- **Account menu**: Restart Tour, Manage My Team (group managers), Wallboard (staff), My Profile, Sign Out, and the app version.

On phones the rail is hidden and its links move into the navbar's hamburger menu.

### Guided Tours

Admins, staff and end users each get an interactive tour on first sign-in. Restart it any time from the account menu → **Restart Tour**.

### My Profile

Open **My Profile** from the account menu (`/profile`). Changes save instantly, except the password. Options include:

- Name, and Light or Dark appearance.
- **Ticket Timeline** order (newest or oldest first). You can also flip it by clicking the *Timeline* heading on any ticket.
- **System Timeline Notes** and **AI Timeline Notes** (shown when relevant to your role): the default for showing automated and AI-generated timeline entries.
- **Email Notifications**: per-event toggles, including notifications about tickets you submit yourself.
- **Change Password**.
- **Two-Factor Authentication** (staff): set up an authenticator app. An admin can reset it for a user who loses their device.
- **Canned Responses** (staff): manage your personal reply snippets.

### Install as an App (PWA)

The help desk can be installed to a phone, tablet or desktop home screen from the browser (an install prompt appears on supported browsers). It keeps an offline page and shows a *New version ready* prompt after updates.

---

## Portal (End Users)

The portal uses plain wording: tickets are called **requests**, and statuses use friendly labels (all renameable by an admin under **Settings → Labels**):

| Status | Portal label |
|--------|--------------|
| Open | Submitted |
| In Progress | We're working on it |
| Pending / Waiting on Third Party | We're waiting on someone else |
| Waiting on Customer | Waiting on you |
| Resolved | Done |
| Closed | Closed |

### Dashboard

`/portal` greets you, shows **Your Active Requests**, and has a **New Help Request** button. Active **status banners** (outage or incident notices posted by staff) appear at the top of every page. You can dismiss them.

### Submitting a Request

Click **New Help Request** (`/portal/tickets/create`). Each request type can have its own form, so the fields you see depend on the type you choose. Common fields:

| Field | Notes |
|-------|-------|
| Request type | Required. Picks the form and the team that receives the request. A "not sure who handles this" type may let AI route it to the right team. |
| Subject | As you type, matching knowledge base articles are suggested. Check them before submitting. |
| Description | Rich text. You can paste screenshots straight into it. |
| Location | Which branch or site the request is about. |
| How urgent is this? | Optional unless the type requires it. You can leave it as *Let our team decide*. |
| CC | Colleagues to keep informed. Type 2+ characters to search. |
| Custom fields | Whatever the admin has added to this type's form. Some appear only when another answer applies. |
| Attachments | Allowed file types and size limit are set by the administrator. |

Also on the form:

- **Start from a template**: pre-filled request templates shared by staff.
- **Response-time notice**: once a type and priority are chosen, the form can show the expected first-response and resolution targets.
- **Duplicate check**: on some types, clicking Submit checks for a similar open request at your location and lets you view it or **Create anyway**.
- **Drafts**: the form autosaves while you type and offers to restore an unsent draft. **Discard draft** clears it.
- **Undo send**: if enabled by the admin, a short countdown lets you cancel before the request is sent.

After submitting, a **What happens next?** window shows when to expect a response, and you receive a confirmation email.

### My Requests

`/portal/tickets` lists your requests with search, filters, sorting and paging. A **Draft** badge marks requests with an unsent comment. If your account has **Location Ticket Visibility**, a **My Location** toggle (it follows the Location label, e.g. *My Branch*) shows every request at your location. See [Location Ticket Visibility](#location-ticket-visibility).

### Request Detail

- **Timeline** of public activity (replies, status changes). Staff internal notes and system events are never shown to you. Click the *Timeline* heading to change the sort order.
- **Reply** box with attachments. You can also reply to any notification email and your reply is added as a comment.
- **Edit**: change the subject or description while the request is open.
- **Close this request**: close it yourself when you no longer need help.
- **Escalate**: shown when the request type has an escalation path. It sends the request to the next person in that path, optionally with a reason. An admin can choose to show it only after the response deadline has passed.
- **CC** card listing who else is kept informed.
- If staff mark a reply as the answer, a green banner with **Go to solution** appears at the top.
- A **What happens next?** card explains the process while the request is open.

### Knowledge Base

Browse `/portal/kb` by category and folder, or search. Articles can be rated as helpful or not. Categories marked public are also readable without signing in at `/kb`.

### Floor Mode

On touch devices, **Floor mode** (`/portal/floor`) is a large-button view with a **New help request** shortcut and your recent requests. A quick-capture sheet supports voice dictation and photos.

---

## Agent Workspace

The staff area (`/agent`) is used by agents, power users and any custom staff level. Admins can open it from **Agent Panel** in the navbar.

### Ticket Visibility

Staff see tickets belonging to their own group(s), plus any ticket they are assigned to, watching, or CC'd on. The **View all tickets** permission widens this to every group. **Confidential** ticket types are the exception: only members of that type's group (and the requester) can see them. See [Confidential Ticket Types](#confidential-ticket-types).

### Dashboard

The agent dashboard shows five stat cards. Each one links to the matching filtered ticket list:

| Stat | Description |
|------|-------------|
| **Unassigned** | Open tickets with no agent assigned. |
| **My Tickets** | Open tickets assigned to you. |
| **Pending** | Tickets in Pending status. |
| **Resolved Today** | Tickets resolved today. Tickets closed as duplicate, won't fix or no response are not counted. |
| **Escalated to Me** | Open tickets escalated to you. |

Below them, **Recent Tickets** is a sortable table with a **Columns** picker. Status, priority, type, assignee and group can be changed in place from the chevron (▾) next to each value.

### Ticket List

`/agent/tickets` (admins: `/admin/tickets`).

- **Views**: a segmented switcher on the toolbar picks **Table**, **Compact** (email-style, with hover cards offering Reply / Forward / Add Note) or **Card**. A **Kanban** button opens the board view. Your choice is remembered.
- **Columns** (Table view): choose which columns to show. Drag a column edge to resize it.
- **Inline quick-edit**: change status, priority, type, assignee or group from the chevron on each row without opening the ticket. These changes trigger the same timeline entries, notifications, SLA updates and automations as a full edit.
- **Filters**: the **Filters** panel applies instantly and shows each active filter as a removable pill. Filters include status, priority, type, location, assigned to (unassigned, mine, or a specific person), group, free-text search, watched tickets, resolved today, escalated to me, and has attachment. Wallboard drill-downs add filters such as created today, due today and SLA state.
- **Saved filters**: save a filter combination by name, mark one as your default, or share it with the team.
- **Bulk actions**: tick rows and a bulk bar appears above the list with Assign, Close, Merge, Change Status, Change Priority, Change Group and Delete. You only see the actions your permission level grants.
- **Export**: download the current filtered list as CSV. Confidential tickets you can't see are never exported.
- **Live hints**: a ticket that another staff member has open shows *Opened by …* or *Being replied to by …*. A **Draft** badge marks tickets where you have an unsent reply.

### Kanban Board

`/agent/tickets/board` shows tickets as cards in columns:

- **Built-in boards** group by Status, Priority or Assignee. Dragging a card changes that field, exactly like editing the ticket.
- **Custom boards** are personal organisers: you define the columns and drag tickets into them. Ticket fields are not changed. A custom board can be shared with your team.
- Filters include *My team's tickets only* and Group. A board shows up to 500 of the most recent tickets.

### Working a Ticket

The ticket page (`/agent/tickets/{id}`; admins `/admin/tickets/{id}`) has a sticky header band. It shows the ticket's status, priority and SLA badges, who else is viewing or replying, and the action buttons **Watch**, **Escalate**, **Split**, **Merge**, **Close** (see [Closing, Merging and Splitting](#closing-merging-and-splitting-tickets)) and **Back** (returns to the page you came from).

**Main column**

- **Description**, followed directly by the **Reply**, **Forward** and **Add Note** buttons (a second set sits below the last timeline entry; the composer opens under whichever set you click):
  - **Reply**: public message to the requester (and CCs).
  - **Add Note**: internal note, never shown to the requester.
  - **Forward**: email the ticket, its public conversation and its attachments to one or more addresses. Recipients are added as CCs, and their replies thread back into the ticket with an *External* badge. Forwarding to existing users and to outside addresses are separate permissions. Confidential tickets can never be forwarded.
- The rich-text composer supports attachments and pasted images, **@mentions** (autocomplete on staff names), and **Insert Canned Response** (tokens are filled in with ticket values).
- **Send & change status**: the arrow beside Send lets you reply and change the status in one step.
- Replies are autosaved as server-side drafts (they follow you to other computers). If another agent replies while you are typing, sending is paused so you can review their reply first.
- **Similar past tickets** (when AI is enabled): related tickets from the whole archive, each with a relevance note.
- **Timeline**: a full activity log that updates live while you are on the page. Click the heading to change the sort order. The **System notes** and **AI notes** switches show or hide automated entries. **Mark as solution** on a public reply highlights it for the requester. There is one solution per ticket, and marking it does not change the ticket's status.

**Sidebar**

- **Details**: status, priority, type, location, requester (with "filed by" when submitted on someone's behalf), group, assignee, dates and escalation level.
- **Custom Fields** that have values.
- **CC**: add or remove people who receive updates. Staff who are CC'd can open the ticket.
- **Update Ticket**: change status, priority, type, group and assignee. Changing the group re-filters the assignee list. Changing the type moves the ticket into that type's default group. Picking a group that doesn't match the type's group clears the type. If someone else changed the status while you were editing, the status change is refused rather than overwritten.
- **Time Tracking** (types that track time). See [Time Tracking](#time-tracking).
- **SLA**: first-response and resolution due times with on-track / warning / breached state.
- **Escalation History**, **Satisfaction Survey** result, and (admins) **AI Classification** details.

Other behaviour:

- Posting the first public reply on an unassigned ticket assigns it to you and records the first-response time.
- **Watch** sends you every update on the ticket, regardless of assignment. Watching is not available on confidential tickets.
- **Escalate** hands the ticket to the next person in the ticket type's escalation path. The previous assignee becomes a watcher.

### Filing a Ticket On Behalf Of Someone Else

Staff often take requests by phone or in person. The **On Behalf Of** picker on the staff *Create Ticket* page (`/agent/tickets/create`, `/admin/tickets/create`) records who the ticket is really for:

1. In the *Assignment* card, type at least two characters of the person's name or email in **On Behalf Of** and pick them.
2. Fill in the rest of the form and submit.

The chosen person becomes the **requester**. The ticket appears in their portal, and the confirmation email, satisfaction survey and updates go to them. The staff member who filed it is recorded separately: the sidebar shows *"Filed by [staff] on their behalf"* and the first timeline entry says so (staff-only). Leaving the picker blank, or picking yourself, files the ticket as your own. The requester must already have an account. Create one under **Settings → Users** first if needed.

The staff create form also offers the **Assign To** list (narrowed to the chosen group's members), status, due date, tags, CC, attachments and ticket templates.

### Floor Mode

On touch devices, **Floor mode** (`/agent/floor`) is a large-target queue for staff working away from a desk. It has *All open*, *Mine* and *Unassigned* tabs. The floating **+** opens quick capture: subject with voice dictation, type, location, photos and barcode scanning. Each floor ticket page has one-tap Claim / Release / status actions and a note box. **Full ticket details** opens the normal ticket page.

### Wallboard

**Wallboard** (account menu → Wallboard, `/agent/wallboard`) is a live, auto-refreshing dashboard suited to a wall screen. Choose from KPI cards (open, unassigned, SLA breached / at risk, due today, created / resolved today, average first response, CSAT, SLA compliance), breakdown charts, a created-vs-resolved trend, agent workload and queue lists. **Customize** lets you drag, resize, add or remove widgets and pick 2–4 columns. The layout is saved per user. Click any widget to drill into the matching ticket list. All figures respect your ticket visibility.

---

## Closing, Merging and Splitting Tickets

### The Close Button

The green **Close** button in the ticket header closes the ticket and emails the requester the standard "ticket closed" notice. Its arrow menu offers:

| Option | What it does |
|--------|--------------|
| Close without notifying | Closes with no email to the requester. |
| Close and notify | Same as the Close button. |
| Close and request feedback | Closes and sends a satisfaction survey (greyed out when surveys are off). |
| Close as duplicate… | Links the ticket to the original. |
| Close as won't fix… | Requires a reason. |
| Close as no response… | For requesters who stopped replying. |
| Close and merge… | Moves this conversation into another ticket, then closes it. |
| Close and create KB article | Closes, then opens a knowledge base draft pre-filled with the problem and resolution. |
| Close and reopen on reply… | Reopens automatically if the requester replies within N days, whether they reply in the portal, by email or through the API. |
| Close and schedule follow-up… | Closes and sends you an in-app reminder at the time you choose. |

Options that ask for input include a message box. With **Email the requester** ticked, the message is sent as a reply. Leave it blank to send the standard closed email instead. Unticked, the message is saved as an internal note.

Closed tickets show a badge such as *Duplicate of #…*, *Won't fix*, *No response* or *Reopens on reply until …*. Tickets closed as duplicate, won't fix or no response are **not** counted as resolved in reports or dashboard counts.

Ticket types can also prompt the assigned agent for a **resolution note** before closing (with a suggested *Action Taken → Verification → Next Steps* outline). Types that track time prompt for a time entry if none has been logged.

### Merging

**Merge** (header button, *Close and merge*, or the bulk Merge action) asks which ticket is the primary. The other ticket's replies, internal notes, forwards, attachments and original request move into the primary, interleaved by date. The merged ticket is then closed and links to the primary, and its requester is notified. Confidential tickets can only be merged with tickets of the same type.

### Splitting

**Split** moves selected timeline entries into a new ticket. Both tickets reference each other in their timelines.

---

## Time Tracking

Time tracking is enabled per ticket type (**Settings → Ticket Types → edit → Track time spent on these tickets**, off by default).

- The **Time Tracking** card on the ticket page has a start/stop timer and a **Log time** form (hours, minutes, date worked, optional note).
- The timer runs on the server, so it survives page reloads. Each agent has one live timer: starting a timer on another ticket stops the running one.
- The card shows the ticket's total and every entry with who logged it. Agents can edit or delete their own entries. Admins can edit or delete anyone's.
- Logged time feeds the **Time Spent** and **Agent Time** reports. Turning tracking off for a type later keeps existing entries visible.

---

## Admin Area

### Dashboard

`/admin` shows the 10 most recently updated **Recent Tickets** and **Quick Actions** (View All Tickets, Add User, Manage Locations, Manage Priorities, Settings). Non-admin staff who open `/admin` are sent to `/agent`.

### Managing Tickets

The admin ticket list and ticket page (`/admin/tickets`) work the same as the agent views, with CSV export. Admins can see every ticket. A confidential ticket outside the admin's own groups asks for a password re-check before it opens, and that access is logged.

---

## Settings

`/admin/settings` holds all configuration. The left menu groups pages as follows. Admins see everything. Other staff only see pages their permission level can open, and every staff member at least sees **Status Banners**.

| Group | Pages |
|-------|-------|
| Operations | Status Banners |
| Email | Email / SMTP, Email Templates, Email Notifications, Email Queue |
| Scheduling | Business Hours, Holidays, SLA Policies, Server Time |
| Users & Access | Users, Permission Levels |
| Organization | Organization Type, Locations, Ticket Priorities, Ticket Statuses, Ticket Types, Ticket Forms, Type Settings Matrix, Groups, Ticket Routing, Agent Skills |
| Knowledge Base | Manage Articles, Categories & Folders |
| Security | SSO / Microsoft 365 |
| Integrations | Microsoft Teams |
| Customization | Branding, Labels, Tags, Canned Responses, Ticket Templates, CSAT Surveys, Undo Send, Attachments |
| Automation | Automations, AI Classification, Escalation Paths, Escalation Rules, Stale Tickets, Out of Office, Recurring Tickets, Scheduled Reports, Cron Jobs |
| Reports | Reports |
| Data | Import Tickets, Import Users, Import KB, Backup |
| System | Audit Log, Danger Zone |

Selected pages at a glance:

- **Status Banners**: post an Info / Warning / Critical notice at the top of every page, targeted at all branches or selected ones, with an optional start and auto-hide time. Any staff member can post one.
- **Business Hours** and **Holidays**: the timezone, the weekly open hours and the closed days that SLA timers respect. Holidays can be auto-populated.
- **Server Time**: a read-only check that the OS, database and PHP clocks agree, with fix commands if they don't.
- **Organization Type**: your sector. AI features use it as context.
- **Ticket Priorities** and **Locations**: name and colour, and name / address / description. Lists are reordered by dragging. That order drives every dropdown.
- **Agent Skills**: skills used by skill-based assignment, with an optional **Suggest with AI** helper.
- **Branding**: logo, app name and colours. **Labels**: rename wording across the app (for example *Location* → *Branch*), including the portal's status and action labels. **Tags**: turn tags on or off and manage them.
- **Canned Responses** (shared) and **Ticket Templates**: reusable replies and pre-filled new-ticket templates. Templates marked shared appear on the portal form.
- **Undo Send**: an optional 3–120 second countdown before new tickets and replies are sent (off by default).
- **Attachments**: show or hide image thumbnails in attachment lists.
- **Danger Zone** (admins only): wipes all data and re-runs setup.

---

## Users and Permission Levels

### Permission Levels

Access is controlled by **permission levels** (**Settings → Permission Levels**, `/admin/roles`, admins only). Four levels are built in:

| Level | Can do |
|-------|--------|
| **End User** | Use the portal: submit, track, comment on and close their own requests; browse the knowledge base. |
| **Agent** | Staff baseline: work tickets in their groups, manage ticket templates and recurring tickets. |
| **Power User** | Everything an Agent can do, plus reports and seeing tickets in every group. |
| **Admin** | Everything, with no permission checks. |

Admins can create **custom staff levels** and grant any combination of permissions from the matrix:

- **Tickets**: manage ticket templates, recurring tickets, ticket types / forms / statuses, priorities and SLA policies; view all tickets; forward to internal or external contacts; one toggle per bulk action (assign, close, merge, status, priority, group, delete).
- **People and organisation**: manage users, groups, agent skills and locations.
- **Knowledge base**: create and delete articles; manage categories and folders.
- **Automation**: manage automations and escalations; view out-of-office status; manage CSAT surveys and AI classification.
- **Reports, data and system**: view reports; import data and manage backups; view the audit log; manage general settings.

Built-in levels can't be deleted, and a level still assigned to users can't be deleted. Managing levels is admin-only. Someone who can manage users can only assign levels that are no more powerful than their own, and can't edit, re-password or delete a user who outranks them.

### Managing Users

**Settings → Users** (`/admin/users`) lists all users with search-as-you-type, role, location and contact-type filters, and buttons for **Who's Online**, **Merge Users** and **Add User**.

| Field | Notes |
|-------|-------|
| First / Last Name, Email | Required. Email must be unique. |
| Password | Required on create. Leave blank on edit to keep the current one. |
| Permission Level | Any built-in or custom level you are allowed to assign. |
| Work Phone, Location, Avatar | Optional. Avatars accept JPG, PNG, GIF or WEBP up to 2 MB. |
| Location Ticket Visibility | Lets an end user see all requests at their location. See below. |
| Shared account | A label for shared desk logins. Grants no access, but forms can react to it (for example, to ask for a Person field). |
| Group Membership | Staff levels only. Tick groups, and tick **Manager** to make the user a group manager. Saving a staff user with no group warns that they will see no tickets. |

From the list you can bulk-enable or disable Location Ticket Visibility. A user's profile page (`/admin/users/{id}`) shows their tickets. Users can be deleted (optionally with their tickets), merged into another account, or have their two-factor authentication reset. People who reply to a forwarded ticket are created automatically as **External** contacts.

**Group managers** reach **Manage My Team** (`/manager`) from the account menu. There they can assign skills to their group's members and manage their group's own skills.

### Location Ticket Visibility

End users normally see only their own requests. With **Location Ticket Visibility** turned on, an end user (for example a branch manager) gets a **My Location** toggle in the portal. It shows every request at their location, and they can read, comment on and download attachments from those requests. Before they comment on a request they didn't open, the portal asks them to confirm.

Two ticket-type settings limit what they can see:

- **Confidential**: a hard restriction. Confidential tickets are never exposed through location visibility.
- **Visible to Location Ticket Visibility users**: a soft restriction. Unticking it hides a routine but sensitive type (for example Collections, HR or Payroll) from location-visibility users, without the confidential lock-down.

Requesters always see their own requests, whatever the type.

| Use case | Confidential | Visible to Loc. Vis. users = off |
|----------|:---:|:---:|
| Restrict viewing to one group's members | ✅ | — |
| Admins outside the group must re-authenticate | ✅ | — |
| Every access logged and the group notified | ✅ | — |
| Hidden from end users with location visibility | ✅ (automatic) | ✅ |
| Any staff member with group access can still work it | — | ✅ |
| Example types | HR investigations, legal holds | Collections, general HR, payroll |

### Who's Online and Audit Log

**Who's Online** (`/admin/users/online`) lists everyone currently signed in, with browser and IP address. The **Audit Log** (`/admin/audit-log`) records sign-ins, settings changes, user and permission changes, bulk ticket actions and key ticket-history events. You can filter by person, action, source and date, export to CSV, or prune old entries.

---

## Tickets: Statuses, Types, Forms and Routing

### Ticket Statuses

**Settings → Ticket Statuses** manages the status list. Each status has a label, a colour, a **bucket** (Open or Closed), and a **Pauses SLA** flag. The defaults are:

| Status | Bucket | Pauses SLA |
|--------|--------|:---:|
| Open | Open | — |
| In Progress | Open | — |
| Pending | Open | ✅ |
| Waiting on Customer | Open | ✅ |
| Waiting on Third Party | Open | ✅ |
| Resolved | Closed | — |
| Closed | Closed | — |

You can add custom statuses, reorder them by dragging, deactivate them, and choose which status is the default for new, resolved and closed tickets. A status's slug is permanent once created. Built-in statuses can be deactivated but not deleted. A status that tickets or rules still use can't be removed until those are reassigned.

### Ticket Types

**Settings → Ticket Types** (`/admin/types`). Each type has a **Direct Link** (`/portal/tickets/create?type_id=N`) that opens the portal form with that type pre-selected. **Type Settings Matrix** shows every type's settings side by side and prints on one page.

| Field | Notes |
|-------|-------|
| Name, Color, Sort Order | The colour is used for the type badge. |
| Default Group | The team that receives this type, and the group whose members can be assigned. Required before enabling **Confidential**. With AI routing on, it is also the fallback queue. |
| Confidential | Only the default group's members (and the requester) can see these tickets. Admins outside the group must re-authenticate, and every access is logged and notified. Excluded from AI, forwarding, watching and location visibility. Removing the flag requires re-authentication. |
| Let AI route this to the best group ("No Wrong Door") | AI picks the destination group using each group's description. Falls back to the Default Group when it isn't confident. Can't be combined with Confidential. |
| Check for duplicates on submit | AI warns the submitter about similar open tickets at the same location. The match threshold is adjustable. |
| Show similar past tickets to agents | Per-type switch. The global switch under **Settings → AI Classification** overrides it. |
| Visible to Location Ticket Visibility users | Untick to hide the type from location-visibility end users. |
| Prompt for a resolution note before the owner closes | Asks the assigned agent for a resolution note when closing. |
| Track time spent on these tickets | Adds the Time Tracking card. |
| Form Fields | **Edit this type's form** opens the form builder for this type. |
| Stale Threshold | Overrides the global stale-ticket threshold (for example `3d`, `72h`). Leave blank to inherit. |
| SLA on Reopen | Overrides the global reopen behaviour (see [SLA System](#sla-system)). |
| Required Skills | Used by skill-based auto-assignment. |
| Available Priorities | Limits which priorities this type offers. All ticked means no restriction. |

Saving a type that relies on AI first tests the AI connection and warns you if it fails.

### Ticket Forms

**Settings → Ticket Forms** (`/admin/workflows/ticket-fields`) is a per-type form builder. Pick a type on the left, then:

- Drag fields to reorder them, and click each field's pill to cycle **Required → Optional → Hidden**. Subject and Description are always required.
- **Add field** creates a custom field. **Add existing field** shares one already used on another type. Removing a field from a type keeps its data, while **Delete field entirely** removes it from every type.
- Field types: Text, Multi-line Text, Checkbox, Dropdown, Date, Date Range, Number, Decimal, Dependent (cascading dropdowns), Text Block (rich read-only text), Image, CC, and **Person** (a staff-directory lookup).
- **Show when…** conditions show a field only when another answer matches, or when the signed-in account is a shared account. Fields that are hidden by a condition are not required and are not saved.
- The **SLA notice** system field shows the response targets on the form.
- **Live Preview** shows the portal form as a requester would see it.

### Groups and Auto-Assignment

**Settings → Groups** (`/admin/groups`): name, description (also used by AI routing), sort order, **notify members of new tickets**, confidential flag, members with an optional **Manager** flag, and an **Auto-Assignment Strategy** for new tickets that have a group but no assignee:

| Strategy | Picks |
|----------|-------|
| Manual | Nobody. Agents claim tickets themselves. |
| Round Robin | The next member in rotation. |
| Load-Based | The member with the fewest open tickets. |
| Skill-Based | A member holding all of the type's required skills. |
| AI Skill-Based | A member holding the skills AI infers from the ticket. |
| First Available | A member who currently has the app open. |

When no member matches, the group's fallback setting applies: least-loaded member, round-robin, or leave unassigned.

### Ticket Routing

Every ticket ends up in a group. The group is chosen in this order: the group explicitly picked, then the ticket type's default group, AI routing or automations, and finally the system **Default Group** set under **Settings → Ticket Routing**. That page also has **Allow agents to assign tickets to groups they're not part of**.

---

## SLA System

### How It Works

1. **Settings → Business Hours** and **Holidays** define when SLA time counts.
2. **Settings → SLA Policies** sets **first response** and **resolution** targets for each priority. A **Default** tab applies to all types, and each ticket type has its own tab that can override any target. Enter durations as `90m`, `8h` or `2d`; a bare number means minutes. Blank type values inherit the default. A priority can have one target or both, and a priority with neither gets no SLA.
3. Each policy row has **SLA counts on** day toggles. Days that aren't ticked freeze that policy's timer. A type can also use its own business-hours schedule.
4. When a ticket gets a priority with a matching policy, its due dates are calculated in business time. Its state is **On Track**, **Warning** (80% of the allowed time used) or **Breached**.
5. A master **Enable SLA tracking** switch turns SLA off site-wide while keeping policies and history.

### Pausing, Changes and Reopening

- Moving a ticket into a status flagged **Pauses SLA** pauses the timers. Moving it back resumes them, extending the due dates by the paused business time.
- Changing a ticket's priority or type recalculates its due dates under the new policy.
- **When a closed ticket is reopened** (global setting, with per-type overrides): keep the original due dates (the default), resume, restart both clocks, or restart only the resolution clock.

### Recalculation and Alerts

The SLA cron job (every 5 minutes) updates SLA states and sends in-app warning and breach alerts. **Recalculate All** on the SLA Policies page runs a recalculation immediately. Email on breach is configured through **Escalation Rules**. The **Escalate** button can be set to appear only after a breach (see [Escalation Paths](#escalation-paths)).

---

## Automation

Background jobs are listed on **Settings → Cron Jobs**. That page shows each job's status and last run, gives the cron or Windows Task Scheduler command, and has **Dry run** and **Run for real** buttons.

| Job | Purpose |
|-----|---------|
| SLA Recalculation | SLA states, warning/breach alerts, follow-up reminders |
| Inbound Email Replies | Reads the Microsoft 365 mailbox into tickets |
| Escalation Rules | Time-based rules |
| Scheduled Reports | Emails due reports |
| Recurring Tickets | Creates scheduled tickets |
| Stale Ticket Notifications | Idle-ticket reminders |
| Out-of-Office Coverage | Covers tickets owned by away agents |
| App Secret Expiry Reminders | Warns admins before the Microsoft Graph secret expires |

### Automations

**Settings → Automations**: rules that run when a ticket is **created** or **updated**. Conditions on ticket fields (type, priority, status, location, group, assignee and more) can be combined in AND/OR groups. Actions: set group, assign to agent, set type, set priority, set status, add tag, CC a user. Rules run in the listed order and can be run on demand with **Run Now**.

### Escalation Rules

**Settings → Escalation Rules**: time-based rules checked by cron. Conditions include SLA state, hours open, hours since the last update, hours in the current status, assigned or not, priority, status and group. Actions: set priority, assign, set group, set status, notify a specific user, notify the assigned agent, send a reminder email to the customer, and add an internal note. A re-fire cooldown stops repeats.

### Escalation Paths

**Settings → Escalation Paths**: an ordered chain of people for each ticket type (Level 1, Level 2, …), each with an optional label. The **Escalate** button on the staff and portal ticket pages hands the ticket to the next person in the chain and emails them. The **Escalate Button Visibility** setting chooses between *Always show* and *Only after SLA breach*.

### Stale Tickets

**Settings → Stale Tickets**: finds active tickets that have been idle beyond a threshold (global, or per type) and can email the assigned agent (or the whole group if the ticket is unassigned), the requester, and the group manager(s). Idle time counts only the days the ticket's SLA policy counts. **Coverage** can be *Retroactive* or *New tickets only*. Turn notifications on carefully: a first run in Retroactive mode can email your whole backlog.

### Out of Office

**Settings → Out of Office**: reads each agent's Outlook automatic-reply status through Microsoft Graph. Tickets held by an away agent are reassigned to an available group member, or the requester receives an auto-reply when nobody is available. The mode can be reassign-then-reply, reassign only, or reply only, and can apply to unanswered tickets only or to all active tickets.

### Recurring Tickets

**Settings → Recurring Tickets**: create a ticket on a schedule (daily, weekly, monthly, yearly or custom), for example preventive maintenance. Recurring tickets go through normal routing, automations and notifications. **Run now** creates one immediately.

### Scheduled Reports

**Settings → Scheduled Reports** (or the **Schedule** button on any report): email a report on a schedule to a list of recipients. Schedules belong to their creator, and admins see all of them.

---

## AI Features

Configure AI under **Settings → AI Classification**. Choose Anthropic Claude or OpenAI, enter an API key, pick a model, and use **Test connection**. A debug page shows raw API calls. AI is optional and every feature fails safe: if the provider is unavailable, tickets are still created. **Confidential ticket types are never sent to an AI provider.**

| Feature | What it does |
|---------|--------------|
| Skill classification | Infers the skills a ticket needs, for **AI Skill-Based** auto-assignment. Can optionally raise the priority of angry or urgent tickets. |
| No Wrong Door routing | Per type: routes the ticket to the best group, with the type's default group as fallback. |
| Duplicate detection | Per type: warns at submit time about similar open tickets. |
| Similar past tickets | Shows related past tickets on the ticket page (global switch, plus a per-type switch). |
| Skill suggestions | **Suggest with AI** on Agent Skills. |

AI decisions are written to the timeline as admin-only **AI notes**. The **AI Token Usage** report shows token use and estimated cost. Full guidance: **Admin Docs → AI Classification**.

---

## Knowledge Base

The knowledge base is organised as **Category → Folder → Article**.

- **Managing**: **Settings → Manage Articles** and **Categories & Folders**. Articles are written in a rich-text editor, saved as **Draft** or **Published**, and keep a revision history you can compare with **View Diff**. Creating, editing and deleting articles needs the *Create & delete KB articles* permission; changing categories and folders needs *Manage KB categories & folders*.
- **Reading**: staff browse at `/agent/kb` and end users at `/portal/kb`, both with search. Categories marked **public** are readable without signing in at `/kb`. Readers can rate an article as helpful or not.
- **Suggestions**: matching articles are suggested while a subject is typed on the new-ticket form.
- **From tickets**: **Close and create KB article** starts a draft from a solved ticket.
- **Import / export**: **Settings → Import KB** imports articles from CSV (Markdown bodies are accepted). Articles can also be exported.

---

## Notifications

The bell in the navbar (staff) shows unread in-app alerts. The **Notifications** page (`/notifications`) updates live and has **Mark read** and **Mark All Read**. Alert types:

| Type | When |
|------|------|
| Mention | Someone @mentions you in a reply or note. |
| Assignment | A ticket is assigned to you or your group. |
| Ticket update | A ticket you're assigned to, watching or CC'd on changes or gets a public reply. |
| New ticket | A ticket is created in a group you're notified for. |
| Customer reply / Note added | The requester replies, or a note is added, on your ticket. |
| SLA warning / SLA breach | A deadline is approaching or has passed. |
| Follow-up reminder | A *Close and schedule follow-up* reminder is due. |

In-app alerts appear even if you have muted the matching email on your profile.

---

## Email

### Outbound Mail

Configure SMTP under **Settings → Email / SMTP** (host, port, encryption, credentials, From address and name, Reply-To) and use **Send Test Email** to check it. Two server `.env` switches also apply:

- `MAIL_ENABLED=false` stops all outbound mail (use it on test or staging copies).
- `MAIL_TRANSACTIONAL_ENABLED=true` still lets password-reset emails through while mail is off.

Mail that can't be delivered is held on **Settings → Email Queue**, where you can **Send all now** or **Flush queue**.

### Which Emails Are Sent

**Settings → Email Notifications** switches each event on or off site-wide, and users can opt out of most of them on their profile:

| Audience | Events |
|----------|--------|
| Staff | New ticket created (group members), ticket assigned to group, ticket assigned to agent, requester replies, note added, stale ticket, stale ticket (group manager) |
| Requester | New ticket confirmation, ticket assigned to an agent, agent adds a comment, ticket resolved, ticket closed, stale-ticket check-in |
| CC'd people | New ticket created, comment added, and a one-time "you were added as a CC" notice |

Other emails: ticket merged, satisfaction survey, escalation to the next person in a path, escalation-rule reminders, forwarded tickets, password reset, scheduled reports, confidential-access security alerts, and Graph secret-expiry reminders.

### Email Templates

**Settings → Email Templates** customises the subject, intro (rich text), button label and, where used, the message body. Templates: Ticket Created, Ticket Updated, Ticket Merged, CSAT Survey, Customer Reminder, Group Alerts, Agent Assigned, Group Assigned, Escalation Alert, and Stale (Requester, Agent, Manager). There is also a **Shared Footer**. Click a token chip such as `{{ticket_id}}`, `{{first_name}}` or `{{sla}}` to insert it. **Editing for:** sets per-group overrides of the requester-facing templates. **Reset to default** restores the built-in text.

### Inbound Email

With a Microsoft 365 mailbox connected through Microsoft Graph (**Settings → Email / SMTP**, which links to a setup guide):

- **Reply by email**: replies to notification emails are added to the ticket timeline, with signatures and quoted text stripped. Replies to a closed ticket are ignored unless it was closed with *reopen on reply* and the window is still open.
- **Email-to-ticket**: new messages become tickets, with a default type and priority. Unknown senders can optionally get accounts created for them.
- **Email commands**: staff can end a reply with a line such as `#close`, `#resolved`, `#pending` or `#high` to change the status or priority. Commands are ignored if the message fails sender authentication.

---

## Satisfaction Surveys (CSAT)

Configure surveys under **Settings → CSAT Surveys**: turn them on, choose the status that triggers the survey, and choose **Built-in** or **External**.

- **Built-in**: requesters rate on a hosted survey page and can ask to reopen the ticket. The reopen button can be turned off.
- **External**: the email links to your own survey tool, with placeholders such as `{ticket_id}` and `{token}`. Results can be posted back through a signed webhook (`POST /api/csat/webhook`).

Each ticket gets at most one survey. The ticket page's **Satisfaction Survey** panel shows the result, which also appears in the Satisfaction report. **Send Test Survey** previews the email. *Close and request feedback* sends a survey when you close a ticket.

---

## Reports

**Settings → Reports** (`/admin/reports`) is available to admins and to any level with **View reports**. Reports include:

Overview, Agent Performance, Agent Workload, Response Times, First Contact Resolution, SLA Compliance, SLA Violations, Ticket Volume, Ticket Lifecycle, Ticket Trends, By Location, Unresolved Tickets, Satisfaction (CSAT), Group Coverage, Time Spent, Agent Time, AI Token Usage, and a **Custom** report builder.

Date-scoped reports have presets (Today, Last 7/30/90 days, This quarter, …) and default to the last 30 days. Most can be exported as CSV, and every report has a **Schedule** button.

---

## Security and Integrations

### Confidential Ticket Types

A confidential type locks its tickets to the members of its default group and the requester. Admins outside the group must re-enter their password, every access is logged, and the group is emailed. Confidential tickets are redacted in lists, search, exports, notifications and the API. They are never sent to AI, forwarded or watched. Removing the flag, or deleting a confidential type or group, requires re-authentication and alerts the group.

### Sign-in Security

- **Microsoft 365 SSO** (**Settings → SSO / Microsoft 365**): Entra ID sign-in. A setup guide is linked from the page.
- **Two-factor authentication** (TOTP) for staff accounts, set up from My Profile.
- Sign-in attempts are throttled, sessions re-check the user's role on every request, and password changes revoke API tokens.

### Microsoft Teams

**Settings → Microsoft Teams** (admins only): post Adaptive Cards to a Teams channel through a Workflows webhook when a ticket is created, assigned or changes status, or when its SLA is breached. Each ticket type can post to its own channel, and other types fall back to the default channel. Each webhook has a **Test** button.

---

## Data: Imports and Backups

- **Import Tickets**: upload a CSV from another system, map its columns, preview, then import. You also choose how SLA applies to imported tickets: exclude them (the default), start clocks now, or apply from the original open date, optionally limited by type or date.
- **Import Users** and **Import KB**: CSV import with column mapping and a preview.
- **Freshdesk**: a command-line importer (`scripts/import-freshdesk-conversations.php`) pulls tickets, conversations and attachments through the Freshdesk API. It is a dry run unless `--apply` is given. See the script's header for its options.
- **Backup**: one click creates a ZIP of the whole application directory plus a database dump, which you can then download or delete.

---

## REST API

A token-authenticated REST API at `/api/v1` supports mobile and third-party clients:

- `POST /api/v1/auth/login` returns a Bearer token (2FA accounts must also send `totp_code`). Tokens can be rotated with `/api/v1/auth/rotate`.
- Endpoints cover tickets (list with filters, create, update, reply, timeline, attachments), the dashboard, notifications, the knowledge base, user search, canned responses, lookup metadata and push-device registration.
- Requests are rate-limited per user (`API_RATE_LIMIT_PER_MIN`, default 120). The same ticket-visibility and confidentiality rules apply as in the web app.

See [docs/API.md](docs/API.md) and `openapi.json`.

---

## Ticket Lifecycle

### Typical Workflow

1. **A ticket is created** from the portal, the staff form, email, floor mode, a recurring schedule or the API. Routing picks a group. AI, automations and auto-assignment run. SLA timers start if a policy matches.
2. **An agent picks it up**: it is assigned by a strategy or claimed by hand, and moved to In Progress.
3. **The agent replies**: the first public reply records the first-response time.
4. **Waiting**: moving the ticket to a status that pauses SLA (Pending, Waiting on Customer, Waiting on Third Party) pauses the timers.
5. **The requester responds**, and the agent moves the ticket back to an active status. Timers resume with extended deadlines.
6. **Resolved / Closed**: through the Close button and its options, the Update panel, *Send & change status*, the requester closing it, or an email command. A satisfaction survey may follow.

Statuses are configurable. See [Ticket Statuses](#ticket-statuses).

### Timeline

Every change is logged on the ticket's timeline. Entries include: Created, Comment (public reply), Internal Note, Status Changed, Priority Changed, Assigned, Escalated, Forwarded, Merged, Split, Edited by Requester, SLA Set / Paused / Resumed, Stale Reminder Sent, Satisfaction Rating, and AI entries (AI Classified, AI Routed, AI Routing Skipped, AI Duplicate Warning).

Requesters only see public entries. Internal notes, system events and AI entries are staff-only, and staff can hide system and AI entries with the timeline switches.

### Attachments

- Files can be attached when creating a ticket, replying, adding a note or forwarding. Images pasted into the editor are saved as attachments too.
- The maximum size (default 20 MB) and the allowed file types are set in the server `.env` (`UPLOAD_MAX_SIZE`, `UPLOAD_ALLOWED_TYPES`). Without a setting, only PDF, JPEG and PNG are allowed. SVG files are never accepted.
- Image attachments show as thumbnails (this can be turned off under **Settings → Attachments**).
- Files are stored outside the web root. Requesters never see attachments on internal notes.
