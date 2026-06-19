# OpenHelpDesk Mobile REST API

**Version 1** · base path `/api/v1`

This is the reference for the token-authenticated REST API that backs the native
Android and iOS clients. It was generated from the route definitions in
[`src/routes/api.php`](../src/routes/api.php); the machine-readable equivalent
lives in [`openapi.json`](../openapi.json) at the repo root.

> **Do not confuse this with the `/api/...` routes (no `v1`).** Those are
> session-cookie + CSRF helpers for the web browser UI and will reject a mobile
> client. The mobile API is **only** the `/api/v1/...` tree documented here.

---

## Conventions

- All requests and responses are JSON. Send `Content-Type: application/json` on
  any request with a body.
- All endpoints **except `POST /auth/login`** require a bearer token:
  ```
  Authorization: Bearer <token>
  ```
- HTTP status codes follow REST conventions:

  | Status | Meaning |
  |--------|---------|
  | `200` | OK |
  | `201` | Created (ticket / reply) |
  | `401` | Missing/invalid token, bad credentials, or 2FA required |
  | `403` | Authenticated but not allowed (role or per-ticket access) |
  | `404` | Resource not found |
  | `422` | Validation error (missing/invalid field) |
  | `429` | Login throttled — too many failed attempts |

- Error bodies are always `{ "error": "<message>" }`.
- List endpoints return a consistent envelope:
  ```json
  { "data": [ ... ], "total": 100, "page": 1, "per_page": 25, "last_page": 4 }
  ```

### Roles & visibility

The API resolves roles through the same configurable-roles map the web app uses,
so any custom permission level an admin creates is honoured (not just the three
built-ins below).

| Role class | Ticket visibility |
|------------|-------------------|
| **admin** | All tickets, unrestricted |
| **staff** (agent / power_user / custom staff roles) | Tickets in their group(s), tickets they can `tickets.view_all`, plus tickets they created / are assigned / are watching. Confidential-type tickets are restricted to the type's confidential group or the creator. |
| **user** (portal) | Only tickets they created. No internal notes, no agent-only fields. |

---

## Authentication

### `POST /auth/login`
Exchange credentials for a 90-day bearer token. **No auth header required.**

**Request body**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `email` | string | yes | |
| `password` | string | yes | |
| `device_name` | string | no | Stored with the token for audit/"sign out device" (default `"Mobile App"`, max 255 chars) |
| `totp_code` | string | conditional | Required **only** if the account has 2FA enabled. `code` is accepted as an alias. |

**Responses**

- `200` — success:
  ```json
  {
    "token": "<64-char hex>",
    "user": { "id": 3, "first_name": "Jane", "last_name": "Smith",
              "email": "jane@example.com", "role": "agent", "avatar": null }
  }
  ```
- `401` — `{ "error": "Invalid credentials" }`.
- `401` — 2FA gate, when the account has 2FA on and the code is missing/wrong:
  ```json
  { "error": "A two-factor authentication code is required.", "totp_required": true }
  ```
  Re-submit the same email/password **plus** `totp_code`. Clients should treat
  the `totp_required` flag as the signal to prompt for the 6-digit code.
- `422` — `{ "error": "email and password are required" }`.
- `429` — `{ "error": "Too many failed login attempts. Please try again in a few minutes." }`
  Throttle: 5 failures per email **or** 10 per IP within a rolling 15-minute
  window. A clean login clears that email+IP's failure counter.

> The token is returned **once** and stored only as a SHA-256 hash server-side —
> there is no endpoint to retrieve it again. Persist it in the platform secure
> store (iOS Keychain / Android Keystore).

### `POST /auth/logout`
Revokes (deletes) the current bearer token. Returns `{ "message": "Logged out" }`.

### `POST /auth/rotate`
Issues a fresh token and invalidates the current one in a single atomic swap.
Call before the 90-day expiry to stay signed in without re-prompting for
credentials. Returns:
```json
{ "token": "<new 64-char hex>", "expires_at": "2026-09-17 12:00:00" }
```
`401` if the presented token is already invalid or expired.

---

## Profile

### `GET /me`
Returns the current user with their location (if any):
```json
{
  "id": 3, "first_name": "Jane", "last_name": "Smith", "email": "jane@example.com",
  "role": "agent", "avatar": null,
  "location": { "id": 1, "name": "Main Branch", "address": "100 Queen St N" }
}
```

---

## Dashboard

### `GET /dashboard` — *staff only (`403` for portal users)*
Summary counts plus the 10 most recent open tickets, all respecting the caller's
group visibility:
```json
{
  "unassigned": 4, "my_tickets": 7, "pending": 2, "resolved_today": 5,
  "recent_tickets": [ /* TicketSummary objects */ ]
}
```

---

## Tickets

### `GET /tickets`
Paginated, filterable, sortable list. Visibility is enforced server-side per the
role table above.

**Query parameters**

| Param | Type | Notes |
|-------|------|-------|
| `status` | string | Comma-separated slugs, e.g. `open,in_progress` |
| `priority_id` | int | |
| `type_id` | int | |
| `location_id` | int | |
| `group_id` | int | |
| `assigned_to` | int \| `"me"` | `"me"` = current user |
| `unassigned` | `1` | Only unassigned tickets |
| `search` | string | Keyword in subject |
| `page` | int | Default `1` |
| `per_page` | int | Default `25`, max `100` |
| `sort` | enum | `created_at` \| `updated_at` \| `subject` \| `status` \| `priority_id` (default `updated_at`) |
| `dir` | enum | `asc` \| `desc` (default `desc`) |

Returns the pagination envelope with `data` as an array of **TicketSummary**
objects (see [Schemas](#schemas)). Merged-away tickets are excluded.

### `POST /tickets`
Creates a ticket on behalf of the caller. Returns the full **TicketDetail**
(`201`).

**Request body**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `subject` | string | yes | |
| `description` | string | yes | |
| `type_id` | int | no | |
| `priority_id` | int | no | If set, SLA due dates initialise automatically |
| `location_id` | int | no | |
| `group_id` | int | no | **Staff only** — ignored for portal users; group then falls back to the type's default, then the system default |
| `assigned_to` | int | no | **Staff only** |
| `due_date` | date (`YYYY-MM-DD`) | no | **Staff only** |

Side effects on create: timeline entry, SLA init, AI classification + auto-assign
(if enabled and the type isn't confidential), and requester/assignee email
notifications.

### `GET /tickets/{id}`
Full **TicketDetail** including `tags`, `cc_users`, `watchers`, and an
`is_watching` flag for the caller. `403` if the caller can't access the ticket,
`404` if it doesn't exist. Confidential-type tickets are blocked from the API for
non–confidential-group members with:
`{ "error": "This ticket is confidential. Access via the web interface is required." }`

### `POST /tickets/{id}/update` — *staff only*
Partial update — only the fields present in the body change. Returns
`{ "updated": ["status", "assignment", ...] }`.

| Field | Type | Notes |
|-------|------|-------|
| `status` | enum | One of the seven status slugs |
| `priority_id` | int \| null | `null` clears |
| `assigned_to` | int \| null | `null` unassigns; must be a staff user |
| `group_id` | int \| null | `null` removes group |
| `due_date` | date \| null | `null` clears |

Each change is timelined. Status changes also drive SLA pause/resume and fire the
CSAT survey when the new status matches the configured trigger.

### `GET /tickets/{id}/timeline`
All events in chronological order, each with its author and attachments.

- `include_internal=1` — include internal notes. **Staff only**; ignored
  (silently filtered out) for portal users.

Returns `{ "data": [ /* TimelineEntry */ ] }`.

### `POST /tickets/{id}/replies`
Adds a comment to the timeline. Returns the created **TimelineEntry** (`201`).

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `message` | string | yes | |
| `is_internal` | bool | no | **Staff only**; portal replies are always public |
| `status_after` | enum | no | **Staff only** — change status in the same call |

Side effects: `@mention` processing, first-response SLA capture (first public
reply by a non-creator), and email to creator + CC + watchers on public replies.

---

## Knowledge Base

### `GET /kb/categories`
Categories with their folders and per-folder published-article counts. Portal
users only see `is_public = true` categories. Returns `{ "data": [ /* KbCategory */ ] }`.

### `GET /kb/articles`
Published articles only, paginated. Query: `folder_id`, `category_id`, `search`
(title), `page`, `per_page`. Portal users only see public-category articles.
Returns the pagination envelope of **KbArticleSummary**.

### `GET /kb/articles/{id}`
Full article including `body_markdown`, `author_name`, and a `ratings`
(`helpful` / `not_helpful`) count. `404` if not published; `403` if the article's
category is private and the caller is a portal user.

---

## Notifications

### `GET /notifications`
The caller's `@mention` notifications, newest first. Query: `read` (`0` unread /
`1` read / omit for all), `page`, `per_page`. The envelope additionally carries
`unread` — the total unread count regardless of the current filter:
```json
{ "data": [ /* Notification */ ], "unread": 3,
  "total": 12, "page": 1, "per_page": 25, "last_page": 1 }
```

### `POST /notifications/read-all`
Marks every notification read for the caller. Returns `{ "ok": true }`.

### `POST /notifications/{id}/read`
Marks one notification read (only if it belongs to the caller). Returns `{ "ok": true }`.

---

## Canned Responses

### `GET /canned-responses` — *staff only*
Global snippets (`user_id = null`) plus the caller's personal ones, sorted for
display. Returns `{ "data": [ /* CannedResponse */ ] }`.

---

## Meta

### `GET /meta`
All dropdown reference data in one request — ideal to cache at app startup:
```json
{
  "priorities": [ { "id": 2, "name": "High", "color": "#dc3545" } ],
  "types":      [ { "id": 1, "name": "Hardware" } ],
  "locations":  [ { "id": 1, "name": "Main Branch" } ],
  "groups":     [ { "id": 1, "name": "IT Support", "description": null } ],
  "agents":     [ /* UserMin — empty array for portal users */ ],
  "statuses":   [ { "value": "open", "label": "Open" }, ... ]
}
```

---

## Users

### `GET /users/search?q=<term>` — *staff only*
Up to 10 users matching `q` against first name, last name, or email. Useful for
CC / assignment pickers. `q` must be at least 1 character (otherwise returns `[]`).

---

## Schemas

### TicketSummary
`id`, `subject`, `status`, `priority_id`, `priority_name`, `priority_color`,
`type_id`, `type_name`, `location_id`, `location_name`, `group_id`, `group_name`,
`assigned_to`, `agent_name`, `created_by`, `creator_name`, `created_at`,
`updated_at`, `due_date`, `sla_state`, `merged_into_ticket_id`.

### TicketDetail
Everything in TicketSummary **plus** `description`, `creator_email`,
`agent_email`, `first_response_due_at`, `resolution_due_at`,
`first_responded_at`, `sla_paused_at`, `browser_info`, `os_info`,
`tags` (string array), `cc_users` (UserMin[]), `watchers` (UserMin[]),
`is_watching` (bool).

### TimelineEntry
`id`, `action` (`created`, `comment`, `status_changed`, `priority_changed`,
`assigned`, `group_changed`, `merged`, `split`, `sla_set`, …), `details`,
`is_internal`, `created_at`, `user` (UserMin or `null` for system events),
`attachments` (Attachment[]).

### UserMin
`id`, `first_name`, `last_name`, `email`, `role`, `avatar`.

### Attachment
`id`, `timeline_id`, `original_name`, `mime_type`, `file_size` (bytes),
`created_at`.

> **Note:** the timeline reports attachment metadata, but there is currently **no
> `/api/v1` endpoint that streams attachment bytes** — downloading a file still
> goes through the web app. See "Known gaps" below.

### Status slugs
`open`, `in_progress`, `pending`, `waiting_on_customer`,
`waiting_on_third_party`, `resolved`, `closed`.

### SLA state
`on_track`, `warning`, `breached`, or `null`.

---

## Known gaps (as of this version)

These are limitations in the current API surface, worth knowing before you scope
the mobile clients:

1. **No attachment upload or download over the API.** Tickets and replies are
   text-only via `/api/v1`; attachment *metadata* appears in the timeline but the
   bytes are only reachable through the web UI. Photo-attach from a phone would
   need a new endpoint.
2. **No push-notification registration endpoint.** Notifications must be polled
   (`GET /notifications`); there's no device-token registration for APNs/FCM.
3. **Login throttling only.** Per-login throttling exists, but the
   authenticated endpoints are not separately rate-limited.
4. **No "list / revoke my devices" endpoint.** Tokens carry a `device_name`, but
   a user can only revoke the token on the device they're currently holding
   (`/auth/logout`) or rotate it. There's no "sign out all other devices".
