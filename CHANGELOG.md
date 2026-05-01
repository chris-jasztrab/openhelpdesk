# Changelog

All notable changes to LocalDesk will be documented in this file.

Follows [Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`
- **MAJOR** — breaking changes
- **MINOR** — new backwards-compatible features
- **PATCH** — backwards-compatible bug fixes

To release a new version: update `config/version.php`, add a dated entry below under `## Unreleased`, then move it to a new versioned section.

---

## 2.20.2 — 2026-05-01

### Fixes
- **AI skill suggestion failed with "Control character error, possibly incorrectly encoded" when the model emitted a multi-line description.** Repro from a real Anthropic response: `{"skills": [{"name": "Vega Discover", "description": "Manages and troubleshoots\nthe Vega Discover ...", ...}]}`. JSON forbids raw control bytes (< 0x20) inside string literals — they have to be escaped as `\n` / `\t` / `\uXXXX` — but the model occasionally inlines a literal newline anyway, especially on longer description fields. `json_decode()` blew up before the parser ever saw the rows. Added a single-pass sanitizer that walks the response, tracks whether the cursor is inside a string literal (handling backslash escapes correctly), and re-escapes any raw control byte found there. The parser now tries `json_decode()` first, runs the sanitizer + retries on failure, and only surfaces an error if both passes fail. Already-valid JSON (escapes intact) is left byte-for-byte unchanged. The error_log() snippet on terminal failure was also widened to 1000 chars (was 500) so the offending part of longer responses is captured.

---

## 2.20.1 — 2026-05-01

### Fixes
- **AI skill suggestion failed with `AI suggestion JSON missing "skills" array` no matter which mode was picked.** The parser in `src/AI.php` required the model's response to be a JSON object with the exact top-level key `"skills"` and rejected everything else — but real model responses don't always honour the requested key name. Anthropic's Messages API has no JSON-mode enforcement at all, so Claude could (and apparently did) return the array under `"suggestions"`, `"agent_skills"`, or as a top-level array `[...]`. OpenAI with `response_format: json_object` was forced to return *some* object but the key name still wasn't constrained. Either way the parser blew up before any of the suggestions could be shown. Two fixes layered together: (1) Hardened the prompt — the system message now says "the top-level key MUST be exactly 'skills' (lowercase plural)" with the schema marked "EXACT key names". (2) Made the parser tolerant — it now accepts the documented `skills` key first, then falls back through `suggestions`, `suggested_skills`, `agent_skills`, `recommendations`, `items`, `results`, `data`, `list`; accepts a top-level array as well; and as a last resort scans every top-level array value for a list-of-objects with a `name`-ish field. Per-row keys are similarly tolerant (`name`/`skill`/`skill_name`/`title`/`label`, `description`/`desc`/`summary`/`details`, `group`/`group_name`/`owning_group`/`team`), and group names now match case-insensitively. When parsing still fails, the SoftAIException message now includes the top-level keys it found and the first 200 chars of the raw response so admins can see what the model actually returned, and the full raw response (truncated to 500 chars) is logged via `error_log()` for diagnosis.

---

## 2.20.0 — 2026-05-01

### Features
- **Admin → Settings → Agent Skills → "Suggest with AI" — added a "Data-mine past tickets" mode.** The "Suggest with AI" button now opens a launcher modal with two options. *Basic* (the default, unchanged from 2.19.0) feeds the AI only the org profile, ticket types, groups, and existing skills. *Data-mine past tickets* additionally feeds the AI a sample of recent ticket subjects so suggestions reflect the actual issues this install handles day-to-day — useful for established installs where the right skills aren't obvious from the org profile alone (e.g. surfacing "Sierra ILS" or "Polaris" from years of ticket history rather than just "ILS"). The admin picks the sample size from a dropdown — 100, 250, 500 (default), 1,000, 2,500, or 5,000 most recent tickets — so installs with millions of tickets can scope the analysis without dragging down the request. Confidential ticket types are always excluded (same hard rule `classifyTicketWithAI()` enforces — never send confidential bodies to a third-party API), only subjects are read (descriptions stay local), each subject is truncated to 140 chars, and the prompt block is hard-capped at 60 KB so even the 5,000-ticket option can't blow up the request — extra subjects are dropped before being sent. Mining mode also raises the per-call timeout floor to 45 s since larger prompts take longer for the model to digest. The results page surfaces a "Method" panel showing whether basic or mining mode was used and how many subjects were actually included, and the *Regenerate* link preserves the chosen mode + sample size so re-running gives a comparable result.

---

## 2.19.0 — 2026-05-01

### Features
- **Admin → Settings → Agent Skills → "Suggest with AI".** New button on the skills index page that asks the configured AI provider (Anthropic or OpenAI, whichever is set in `Settings → AI Classification`) to suggest a tailored set of agent skills for this organization. The prompt feeds the model the `organization_type` setting, every defined ticket type, every defined group, and the names of skills already in place — so suggestions are scoped to what the org actually triages and won't duplicate what already exists. The response page renders each suggestion as an editable row (name, description, owning group dropdown) with a checkbox for cherry-picking — admins can rename, retarget, or drop any suggestion before bulk-creating the lot. Group ownership defaults to the AI's recommendation when it matches an existing group name exactly, and falls back to "Global" when it doesn't. The route requires AI to be enabled and a key configured; if either is missing, the admin is bounced back with a flash explaining what to fix. Internals: `BaseAIClassifier` gained a generic `chat()` method (provider-agnostic free-form prompt) plus `skillSuggestionSystemPrompt()` / `skillSuggestionUserPrompt()` / `parseSkillSuggestions()` helpers; `AIClassifierFactory::suggestSkillsFromSettings()` is the one-call public entry point. The org-type slug→label table moved from a closure-scoped `$orgTypeGroups` in `routes/admin.php` into reusable `organizationTypeGroups()` / `organizationTypeLabel()` helpers in `helpers.php` so this and future features can resolve labels without redefining the list.

---

## 2.18.0 — 2026-05-01

### Features
- **Admin → Settings → Organization Type.** New page at `/admin/settings/organization` with a single dropdown letting admins pick the kind of organization they're running the helpdesk for. Options are grouped by sector (Library, Education, Government, Healthcare, Business, Community, Other) and cover the most common cases — Public Library, Academic Library, K–12 / School District, College / University, Federal / State / Municipal Government, Hospital, Clinic, Corporation, Small Business, Manufacturing, Retail, Financial Services, Legal, Hospitality, Technology, Non-Profit, Religious / Faith-Based, Museum, Association — with an `Other` fallback. Stored as the slug `organization_type` in the `settings` table (default `other`); the human label is kept in route code only so the list can be relabelled without orphaning saved data. Submitted values are validated against the in-route allowlist before being written. The new page is linked from the Organization group in the settings sidebar (alongside Locations, Priorities, Ticket Types, Groups, Agent Skills) and is registered in `config/settings_index.php` with sector keywords so the Chrome-style settings search finds it from queries like "library", "k12", "non-profit", or "industry".

---

## 2.17.4 — 2026-05-01

### Fixes
- **Resolved-ticket emails told requesters they could "reopen by replying" — replies didn't actually reopen anything.** The default intro for the `ticket_status_resolved` notification (in `src/helpers.php`, both the template registry and the `notifyRequesterStatusChanged()` fallback) said *"If you have further questions, you can reopen it by replying."* But `scripts/process-replies.php` only changes a ticket's status when the sender is an agent/admin AND includes an explicit `#open` hashtag command. A requester replying "thanks" or "actually one more question" got their reply appended as a comment with the ticket still sitting in `resolved` — and on `closed` tickets, the reply was silently dropped at the `status === 'closed'` skip-guard with no acknowledgement at all. Reworded the resolved intro to *"please reply to this email and we'll follow up"* (truthful: replies do become comments that agents see) and the closed intro to *"please submit a new ticket"* (truthful: replies to closed tickets are dropped, so directing users to the portal sets correct expectations). Note: admins who customised either template via the email-templates UI will keep their override; this only changes the default for installs that haven't edited it.

---

## 2.17.3 — 2026-04-30

### Fixes
- **"Reset to Fresh State" left 12 tables of orphaned data behind, including a working API-auth bypass.** The danger-zone reset in `src/routes/admin.php` truncated a hardcoded 33-table list that hadn't been updated since the early schema. Tables added by later migrations — `api_tokens` (005), `ticket_form_field_type_map` (016), `ticket_escalations` and `ticket_escalation_steps` (021), `login_attempts` (024), `agent_skills` / `user_skill_map` / `ticket_type_skill_map` (025), `ai_classifications` (027) — plus tables that had always been in `schema.sql` but were missed (`ticket_watchers`, `canned_responses`, `holidays`) survived the reset with all their rows intact. Because the route runs with `FOREIGN_KEY_CHECKS=0`, nothing errored; the data just lingered. The `api_tokens` case is the worst: tokens issued to the previous admin (`user_id=1`) stayed valid against the freshly-created admin (also `user_id=1` because AUTO_INCREMENT resets on truncate), so the mobile API would silently authenticate the old token as the new admin. Replaced the hardcoded list with `SHOW TABLES` (excluding `schema_migrations` so the migration tracker doesn't get wiped and re-run), so future migrations can't reintroduce this drift.

---

## 2.17.2 — 2026-04-30

### Fixes
- **AI Classification doc page returned an empty docs index instead of the doc itself.** The page at `templates/pages/admin/docs/ai.php` shipped in 2.14.x along with the index card, side-nav link, and 11 search-keyword entries — but `/admin/docs/ai` 302'd back to `/admin/docs` because the dynamic doc route in `src/routes/admin.php` validates the URL slug against an explicit allowlist (`$validDocPages`) and `'ai'` was never added. Anyone who clicked the AI Classification card on the docs index, the AI Classification entry in the sidebar, or any of the AI search results landed on the docs index with no error and no doc — looked like the content was missing. Added `'ai'` to the allowlist and `'ai' => 'AI Classification'` to the `$titles` map so the breadcrumb and `<title>` render correctly.

---

## 2.17.1 — 2026-04-30

### Fixes
- **AI skill-based auto-assignment never fired when group was set by an automation rule.** The `runPostTicketCreateHooks()` chokepoint ran AI classification and auto-assign back-to-back, but `runAutomations(..., 'ticket_created')` was called separately *after* the hook in admin.php and portal.php. So automation rules that set `group_id` (e.g. "Assign IT Tickets to IT Group") fired too late: `autoAssignTicket()` had already short-circuited at the early-return for null `group_id` and never read the AI classification. Folded `runAutomations()` into the hook between classification and auto-assign so the order is `classify → run rules → assign`. Removed the duplicate calls from admin.php and portal.php. Side benefit: tickets created via the API path and via inbound email (`scripts/process-replies.php`) now also evaluate `ticket_created` automations, which the existing function-level comment already promised ("single chokepoint so every creation path gets identical behaviour") but didn't deliver. Repro case was ticket #44973: classified as Sierra ILS at 0.78 confidence, IT group set by automation, but `assigned_to` stayed NULL because routing ran before the group was set.

---

## 2.17.0 — 2026-04-30

### Features
- **Chrome-style settings search — finds individual settings, not just pages.** Building on 2.16.0's nav-page filter, the sidebar search box now also searches a registry of every individual configurable setting (86 entries across all admin pages). Typing "smtp host" finds the SMTP Host field on the Email / SMTP page; "azure secret" finds both the Graph client secret and the SSO client secret with a breadcrumb showing which is which; "primary color" jumps straight to the Branding color picker. Results appear in a floating Chrome-style dropdown next to the sidebar showing label + breadcrumb (group › page › section) + short description, with the matched query highlighted (`<mark>`). Sorting prioritizes label-prefix matches, then label-contains, then section/page/description matches, then keyword-only matches.
- **Click a result → land on the field.** Result rows link to the destination page with the field's `id` as the URL hash (e.g. `/admin/settings#smtp_host`). On the destination page, JS scrolls the field into view and flashes a yellow highlight for ~2.4s so it's instantly findable, even on dense pages like Inbound Mail or Branding. Works for any anchor on any settings page (re-triggers on `hashchange` too).
- **Keyboard-driven.** ArrowUp/ArrowDown to move through results, Enter to navigate, Esc to clear. `/` or Ctrl/Cmd+K to focus from anywhere on the page (already-typing inputs are ignored). Mouse-hover and keyboard selection stay in sync.
- **Settings registry is declarative and easy to extend.** New file `config/settings_index.php` lists every searchable field as `{label, description, group, page_label, page_url, section, anchor, keywords}`. Adding a new setting means: give the input a stable `id`, then append one row to the registry. No JS or template changes needed for new settings — the search just finds them.

---

## 2.16.0 — 2026-04-30

### Features
- **Search box on the settings sidebar.** As the list of admin settings has grown (40+ pages across Email, Scheduling, Organization, Security, Customization, Automation, Data, System), finding the right page meant scanning the whole sidebar. Added a live-filter search input pinned to the top of the settings nav. Filters by label, group name, or synonym keywords (e.g. "smtp" / "graph" / "azure" → Email pages, "saml" / "oauth" / "entra" → SSO, "logo" / "favicon" → Branding, "csv" / "migrate" → Import pages, "claude" / "gpt" / "llm" → AI Classification, "macro" / "snippet" → Canned Responses). Group headers auto-hide when no children match; a "No settings match." message shows when nothing does. Query persists across page navigation via `sessionStorage`. Keyboard shortcuts: `/` or `Ctrl/Cmd+K` to focus, `Esc` to clear and unfocus. Available on every page that uses the settings sidebar.

---

## 2.15.4 — 2026-04-30

### Fixes
- **Description editor missing from new-ticket form (agent + admin).** The CSP added in 2.14.x only allowed scripts/styles from `'self'` and `cdn.jsdelivr.net`, but the new-ticket form loads CKEditor 5 from `cdn.ckeditor.com` (introduced earlier in 2.x). The browser silently blocked the editor's JS and CSS, leaving the "Description *" label visible with empty space below it — looking like the field had been removed. Added `https://cdn.ckeditor.com` to `script-src`, `style-src`, `font-src`, and `connect-src`. Affects every page that uses the WYSIWYG editor (admin/agent new-ticket forms).

---

## 2.15.3 — 2026-04-30

### Documentation
- **AI Classification doc page expanded** with three new sections capturing real-world setup pain points:
  - **Testing It Works** — the three-tier validation path (single-ticket Classify-now button → 25-ticket UI backfill → CLI sweep with `--dry-run`).
  - **Debugging Connection Issues** — explains the new `/admin/settings/ai/debug` page added in 2.15.1 / 2.15.2, with a Models-vs-Message-vs-Both decision and a status-code-to-root-cause cheatsheet (401 / 403 / 400 credit-balance / 404 model-not-found / 429 / 5xx / cURL).
  - **Workspace Spend Caps** — documents the gotcha where org-level credits look fine but a workspace's $0 default spend cap blocks every billable call. Two fixes: raise the workspace cap, or generate a new key in the Default workspace.
  - **Cost comparison table** added to the Choosing a Provider section — Haiku / Sonnet / Opus per-ticket and per-1,000-ticket pricing with guidance on when to upgrade. Surfaces the easy-to-make mistake of saving Opus by accident.
  - 4 new search-keyword entries on the docs index landing on these new sections.

---

## 2.15.2 — 2026-04-30

### Fixes
- **AI debug page eating its own responses.** The `/admin/settings/ai/debug` header-capture callback was returning `strlen(rtrim($line)) + 2` instead of the original byte count cURL handed it. cURL rejects any return value that doesn't match exactly and aborts with "Failed writing header" — which surfaced on the page as a 200 status with empty headers + empty body, masking the actual successful response from Anthropic. Capture original length before trimming and return it unconditionally. The classifier itself (used in production routing) was never affected — different cURL helper, no header callback.

---

## 2.15.1 — 2026-04-30

### Tooling
- **AI API debug page** at `Admin → Settings → AI Classification → Debug` (also `/admin/settings/ai/debug`). Bypasses the classifier abstraction and makes raw HTTP calls so admins can see exactly what the provider returns: status code, response headers, full body, latency, cURL error text. Helps diagnose the hard-to-untangle cases where Test Connection comes back with a generic message — workspace spend caps, expired keys, model-not-found, regional 403s, network egress blocks, etc. Lets the admin paste a one-off key/model without saving (so they can test a candidate before committing it to settings) and choose between `models` (free), `messages` (verifies billing), or both. Decision-tree cheatsheet at the bottom of the page maps common HTTP codes to root causes.

---

## 2.15.0 — 2026-04-29

### Features
- **AI ticket classification (Phase 1).** Each new ticket can now be read by a large language model that infers which agent skills it needs, lets the existing Skill-Based auto-assign machinery route it accordingly, and (optionally) bumps priority when sentiment reads "angry" or "urgent". Pluggable provider — ships with Anthropic Claude (default `claude-haiku-4-5`) and OpenAI (default `gpt-4o-mini`). Settings page at `Admin → Settings → AI Classification`.
  - **Provider abstraction** in `src/AI.php`: `AIClassifier` interface + `AnthropicClassifier` (Messages API) + `OpenAIClassifier` (Chat Completions with `response_format=json_object`) + `AIClassifierFactory`. Both providers send the same system prompt and emit the same verdict shape: `skill_ids`, `confidence`, `sentiment`, `reasoning`, `latency_ms`, plus prompt/output token counts. `SoftAIException` + a centralised cURL wrapper means the feature degrades cleanly on any failure.
  - **Auto-populated model dropdowns.** The settings page lists models from each provider's `/v1/models` endpoint and caches them in the `settings` table. Admin clicks **Refresh model list** and new releases appear without a code change. Built-in fallback options are always present so a fresh install isn't empty.
  - **Test connection** button per-provider sends a 1-skill probe ticket and reports the round-trip latency.
  - **New `ai_skill_based` group strategy.** Reads `tickets.ai_classification_id` (set eagerly during ticket creation), takes the AI's suggested `skill_ids` if `confidence ≥ ai_confidence_threshold` (default 0.7), and assigns the least-loaded group member who holds all of them. Below threshold or no match → falls through to the group's existing fallback (load-based / round-robin / leave unassigned). An admin override on the ticket wins over the AI suggestion.
  - **Eager classify-on-create, even for non-AI strategies.** A new `runPostTicketCreateHooks()` chokepoint replaces the direct `autoAssignTicket()` calls in every creation path (portal, admin, agent, API, email-to-ticket). This means sentiment-driven priority bumps fire whether or not the destination group is on `ai_skill_based`.
  - **Privacy is non-negotiable.** Ticket types marked `is_confidential` are short-circuited *before* any HTTP call — there is no admin override. Subjects are truncated to 200 chars, bodies to 4,000, HTML stripped. API keys live in the `settings` table (same as SMTP password) and are masked after first save.
  - **Audit + telemetry.** Every classification persists provider, model, latency, prompt/output tokens, raw response, suggested skills, override (if any) — with `overridden_by` / `overridden_at` / `override_reason`. Internal timeline entries on every classification (`ai_classified`), priority bump (`ai_priority_bumped`), and human override (`ai_override`). `logAudit` entries for `ai_settings_saved`, `ai_backfill_run`, `ai_classification_override`. Recent Classifications strip on the settings page surfaces the last 10 verdicts at a glance.
  - **Sentiment-driven priority bump.** When the AI flags `angry` or `urgent` and the toggle is on, ticket priority moves up one notch (or to the highest priority if it had none). Idempotent — a timeline marker prevents double-bumping. "Frustrated" alone surfaces for reporting but does not bump.
  - **Override modal on the ticket detail page.** Sidebar gains an **AI Classification** card showing the suggestion, sentiment, confidence, reasoning, and provider/model/latency. Buttons: **Override** (modal with skill checkboxes scoped to the ticket's group + global skills, optional reason field) and **Re-classify** (re-runs the call after a subject/body edit; creates a fresh row, history preserved). When AI is enabled but the ticket has no verdict yet, a **Classify now** button appears.
  - **Backfill** — `scripts/ai-classify-backfill.php --limit=N --statuses=open,in_progress,pending [--dry-run]` plus a UI button on the settings page (capped at 200 in-request, schedule the script via cron for larger runs).

### Database
- Migration **027** adds `ai_classifications` (one row per classification, with override columns), `tickets.ai_classification_id`, `tickets.ai_sentiment` (denormalised + indexed for filtering), and widens `groups.assign_strategy` to include `ai_skill_based`. Seeds default settings keys (provider, threshold, max-tokens, timeout, sentiment-bump toggle, inbound-email toggle). Idempotent.

### Documentation
- New **AI Classification** doc at `/admin/docs/ai` covering setup, privacy, providers, confidence threshold, sentiment bump, override / re-classify, backfill, failure modes, audit/telemetry, and a cost sanity-check. Linked from the docs index, the docs side-nav, and the Group Auto-Assignment strategies table. 7 new search-keyword entries on the docs index landing on the AI page.

---

## 2.14.1 — 2026-04-29

### Fixes
- **Navbar "Help" link is now role-aware.** The plain-language portal rename in 2.13 / f0e5955 ("Portal" → "Help") was applied globally, so admins and agents who clicked Help got dropped onto the patron-facing portal instead of staff documentation. Help now routes to `/admin/docs` for admins, `/agent/help` for agents and power users, and `/portal` for end users (unchanged — this is the patron support screen by design).

---

## 2.13.0 — 2026-04-29

### Features
- **Group Managers — delegated agent-skill management.** Lets a regular group member with the new `is_manager` flag maintain agent skills for their own team without going through an admin. Use case: the librarian who runs Reference can decide who's qualified for "Cataloguing" or "French" without filing a ticket with IT. Built in two layers:
  - **Skill ownership scope** — `agent_skills.group_id` (nullable). NULL = global skill (admin-only, system-wide vocabulary, default for all pre-existing rows). Set = skill owned by one group; managers of that group can edit it. The admin Skill editor at `/admin/skills` now has a Scope dropdown (Global / Owned by group: …); the index gains a Scope column.
  - **Manager flag on group membership** — `group_user_map.is_manager`. The group editor at `/admin/groups/{id}/edit` now has a per-member sub-checkbox **Manager**. JS auto-disables it when Member is unticked. Admin changes are audit-logged as `group_managers_changed`.
- **New `/manager` area** for delegated skill management. Surfaces in the user-dropdown menu as **Manage My Team** for any user flagged on at least one membership row (admins always see it).
  - **`/manager`** — landing page listing the groups the current user manages with member + skill counts.
  - **`/manager/groups/{id}/team`** — checkbox grid of the group's members (rows) × assignable skills (columns: skills owned by this group plus global skills). Tick to grant, untick to revoke. Skills owned by *other* groups are intentionally hidden — managers never see another team's vocabulary. Server re-derives the allowed skill / member set on POST so a manager can't sneak in IDs they don't own. One bulk save; per-user `manager_skill_assignments_changed` audit entries record what changed.
  - **`/manager/groups/{id}/skills`** — group's own skill catalogue. List view shows owned skills (editable) and global skills (read-only with an Admin-only lock badge for context). Create / edit / delete routes underneath, all gated by `canEditSkill()` so a manager can't reach across into a sibling group's skills via URL fiddling. `manager_skill_created` / `manager_skill_updated` / `manager_skill_deleted` audit entries.
- **Permission helpers in `src/helpers.php`** — `userManagedGroupIds()`, `canManageGroupSkills($userId, $groupId)`, `canEditSkill($userId, $skillId)`. Admins always pass; non-admins must hold `is_manager = 1` on the relevant membership row. Global skills (`group_id IS NULL`) never become editable for non-admins regardless of group membership.

### Permission boundaries (intentional)
Managers can: assign existing skills (their group's + global) to their members, create/edit/delete skills owned by their group. Managers cannot: edit global skills, edit other groups' skills, edit ticket-type required-skills (still admin-only — that's a routing-policy decision), add/remove group members, promote/demote other managers.

### Database
- Migration **026** adds `group_user_map.is_manager` (default 0 — preserves today's behaviour) and `agent_skills.group_id` (nullable FK to `groups`, ON DELETE SET NULL — orphan-safe). Idempotent.

### Documentation
- New **Group Managers** section in `/admin/docs/users` (anchor `#group-managers`) covering designation, what managers can/can't do, auto-assign integration, and the audit-log entries to look for. Updated **Agent Skills** card to describe the new Scope concept (Global vs group-owned). Cross-link added from the Group Auto-Assignment doc to the Group Managers section.
- 4 new search-keyword entries on the docs index landing on `#group-managers`.

---

## 2.12.1 — 2026-04-29

### Documentation
- **Help docs caught up with the last 19 days of feature work.** Previously the in-app docs at `/admin/docs/*` lagged behind 22 user-facing commits dating back to 2026-04-10. Updated:
  - **Automations & Escalations** (`automations.php`) — restructured into five clearly-distinct sections (Automation Rules, Group Auto-Assignment, Escalation Rules, Manual Escalation Paths, Stale Ticket Notifications) so admins stop conflating the four time/skill/event/manual systems. New full coverage of the 5 auto-assign strategies (manual / round-robin / load-based / skill-based / first-available) and their fallback behaviour, the per-ticket-type escalation chain that drives the Escalate button (skip-current-assignee logic, watcher exemption, distinction from time-based rules), and the stale-ticket cron with global + per-type thresholds and dedup window. Replaced the misleading "Escalate stale tickets" example that was actually about unassigned tickets.
  - **User Portal** (`portal.php`) — documented the plain-language vocabulary translation (Help Requests / Submitted / We're working on it), the requester self-escalation button (with owner check), the new Escalated-L# badge on portal list rows + ticket detail, the "What happens next?" callout for newly-submitted requests, and the requester-side submit-ack and assignment-notification emails. Pointed admins at `/admin/settings/labels` for label customisation.
  - **Tickets** (`tickets.php`) — added Escalating a Ticket card explaining the red Escalate button and where to configure paths, a new card describing the lighter location-visibility opt-out flag with a comparison table against the heavier `is_confidential` flow, and a per-type stale-threshold note linking back to the stale-tickets section.
  - **Users & Roles** (`users.php`) — new Agent Skills card (catalogue management, mapping to agents, required-skills-per-type, future manager-delegation note), new Agent Availability card explaining the `is_available` toggle and which strategies read it, added auto-assignment cross-link inside the existing Groups section, and added the requester-side "My ticket assigned to an agent" preference to the notification table.
  - **Docs index search** (`index.php`) — added 24 new keyword entries so search lands on the right anchor for skills, availability, auto-assign strategies, manual escalation paths, stale-ticket settings, requester escalation, and the location-visibility opt-out.

---

## 2.12.0 — 2026-04-29

### Features
- **Auto-assign tickets to a group's members** — each Group now has an Auto-Assignment Strategy (Settings → Groups → Edit). A new ticket that arrives with a group set but no assignee runs the group's strategy:
  - **Round Robin** — rotate sequentially through group members. Distribution is even by ticket *count* and remembers the last picked agent on `groups.assign_last_user_id`.
  - **Load-Based** — pick the member with the fewest open (non-resolved/closed) tickets. Best when work items vary in length.
  - **Skill-Based** — pick a member whose declared skills cover every skill required by the ticket type. Configure the global skill list under Settings → Agent Skills, attach skills to agents on the same screen, and mark required skills on each Ticket Type form. Falls back to the group's configured fallback (load-based / round-robin / leave unassigned) when nobody qualifies.
  - **First Available** — pick a member who has flipped the new "I'm available for new tickets" switch on their profile. Useful for shift / follow-the-sun coverage. Same fallback chain as Skill-Based.
  - **Manual** (default) — preserves today's behaviour: ticket is left unassigned for an agent to claim.
  
  Wiring details: portal-created tickets now inherit `group_id` from `ticket_types.group_id` (which already drove confidentiality / which-agents-can-be-assigned, but wasn't being copied to the ticket itself). Auto-assign fires from the portal `POST /portal/tickets/create` path, the API `POST /api/v1/tickets` path, and from the agent / admin "split ticket" paths whenever the assignee is left blank but a group is set. Auto-assignments are recorded as an internal timeline entry ("Auto-assigned to NAME via STRATEGY"), and the standard "ticket assigned" emails go out to the chosen agent and to the requester. Direct manual assignment is unaffected.
  
- **Agent Skills** — new `agent_skills` table + admin CRUD at Settings → Agent Skills. Each skill carries a name + description and is mapped to agents (`user_skill_map`) and to ticket types (`ticket_type_skill_map`). Only used by the Skill-Based strategy today; can be repurposed later for routing rules / search filters.

- **Agent availability flag** — new `users.is_available` toggle on the My Profile page (agents / admins / power users only). Defaults to on. Only the First Available strategy reads it; round-robin, load-based, and skill-based ignore it so flipping yourself "away" doesn't break direct assignment from elsewhere.

### Database
- Migration **025** adds `groups.assign_strategy` / `assign_last_user_id` / `assign_fallback`, `users.is_available`, and the `agent_skills`, `user_skill_map`, `ticket_type_skill_map` tables. Idempotent (guarded column adds + `CREATE TABLE IF NOT EXISTS`).

---

## 2.11.0 — 2026-04-28

### Accessibility (AODA / WCAG 2.0 AA — foundational pass)
- **Skip-to-main-content link** added as the first focusable element in every layout (`base.php`, `app.php`, `public.php`). Auth layout, which is a single-screen with no nav, gets a `<main>` landmark only. Keyboard and screen-reader users can now bypass the navbar and sidebar on every page.
- **`<main>` landmark** wraps the primary content region of every layout. The agent/admin layout's icon-only sidebar `<nav>` got `aria-label="Section navigation"`; sidebar links got `aria-label`/`aria-current="page"` so screen readers announce the destination instead of just "link". The shared navbar now carries `aria-label="Primary"` and the public help-center navbar matches.
- **Navbar icon and decorative-element fixes** — the brand `<img>` alt is now empty (the brand name is rendered in the adjacent `<span>`), all decorative Bootstrap Icons (`<i class="bi …">`) inside nav links, dropdown items, alert blocks, and modal titles got `aria-hidden="true"`, and the avatar circle / hamburger toggle got proper `aria-label`s.
- **Notification bell announces its count** — replaced `title="Notifications"` with `aria-label="Notifications, N unread"` (or "none unread") on initial render and on every poll, so screen-reader users hear the unread count without visiting the page. The numeric badge is now `aria-hidden` since the count lives in the link's accessible name.
- **Global search box is now a labelled combobox** — both the in-app navbar search and the public KB search got a visually-hidden `<label>`, `role="combobox"`, `aria-expanded` toggling on open/close, `aria-controls`, `aria-autocomplete="list"`, and `aria-haspopup="listbox"`. The results dropdown is `role="listbox"` and tabs in the in-app search now have `role="tab"` + `aria-selected` wired to clicks.
- **`/` keyboard shortcut tightened** to comply with WCAG 2.1.4 (Character Key Shortcuts): it no longer fires when a `contenteditable` element (CKEditor toolbar focus) is active, and it now bails when any modifier key is held — both common conflict points with screen-reader virtual cursors. Power-user behaviour is unchanged when no input is focused.
- **Flash messages get explicit live regions** — `role="status"` + `aria-live="polite"` on success/info, `role="alert"` + `aria-live="assertive"` on error, plus `aria-atomic="true"` everywhere so screen readers re-announce the full message instead of just the diff. Dismiss buttons now have `aria-label="Dismiss"`.
- **`prefers-reduced-motion` honoured** — the bell-ring, bell-glow, and admin tour-pulse animations are disabled, and the filter-panel slide-in is suppressed, when the user has Reduce Motion enabled in their OS. A general `transition`/`animation` clamp catches any other animated elements in the layout. Addresses WCAG 2.3.3 (animation from interactions) and 2.2.2 (pause, stop, hide).
- **Login form** — added `autocomplete="email"` and `autocomplete="current-password"` to help password managers, marked alert icons `aria-hidden`, and gave the success/error alerts proper live-region semantics. Form labels were already paired correctly via `<label for>`.
- **Out of scope for this pass (next iteration if requested)**: per-page heading hierarchy, table-header `scope`/`aria-sort` on the agent ticket list, form-level `aria-invalid`/`aria-describedby` for inline errors, CKEditor reply-composer labelling, mention-autocomplete keyboard navigation, color-contrast review of the admin-configurable branding palette, and KB Markdown image alt enforcement.

---

## 2.10.6 — 2026-04-28

### Security Hardening
- **Defense-in-depth scrub on the SMTP debug log** — `src/helpers.php` writes a `storage/logs/smtp.log` transcript when an admin enables `smtp_debug`. PHPMailer's own `SMTP::client_send()` already replaces AUTH LOGIN / AUTH PLAIN / XOAUTH2 payloads with the literal "`[credentials hidden]`" at the `DEBUG_SERVER` level we run at, so credentials were not actually being written to disk under the supported configuration. The remaining risk was a future change quietly raising the level to `DEBUG_LOWLEVEL`. Hardened the debug callback to also scrub the configured SMTP password from every log line (in case it ever appears verbatim in a server banner, response trailer, or unexpected echo), and added a load-bearing comment on the `SMTPDebug` line spelling out the trust model and the "do not raise this" rule. SSO debug logging in `src/routes.php` was reviewed at the same time — it already only emits token *lengths*, `expires_in`, `token_type`, and a `secret_set=yes/NO` boolean, so no change was needed there.

---

## 2.10.5 — 2026-04-28

### Security Hardening
- **API ticket and timeline responses no longer use `SELECT *`** — three JSON-emitting endpoints in `src/routes/api.php` were echoing raw `t.*` / `tl.*` rows back to clients: `POST /api/v1/tickets` (create), `GET /api/v1/tickets/{id}` (detail), and `POST /api/v1/tickets/{id}/replies` (the created timeline row). Today every column in those tables happens to be safe to expose, so this was not an active leak — but the moment someone adds an internal-only column (an admin note, an audit blob, a sensitive flag) it would silently start showing up in API responses with no code change. Replaced `t.*` and `tl.*` with explicit column lists matching the existing `GET /api/v1/tickets` (list) and `GET /api/v1/tickets/{id}/timeline` whitelists; the on-the-wire payload is unchanged for current clients. The remaining two internal `SELECT * FROM tickets` queries inside the update and reply handlers are left in place but commented as "internal — never returned to the client" so a future change can't quietly turn them into responses.

---

## 2.10.4 — 2026-04-28

### Security Hardening
- **Rate limit on the API login endpoint** — `POST /api/v1/auth/login` was previously unthrottled, so an attacker could grind credentials against it (or against a single account) at full request speed. Added a sliding-window throttle backed by a new `login_attempts` table (migration 024): inside any 15-minute window, an email may have at most 5 failed attempts and a single IP may have at most 10 failed attempts before the endpoint short-circuits with HTTP 429. A successful login from the same email/IP pair clears that pair's failure count, so a legitimate user who fat-fingers the password a couple of times then logs in cleanly is not locked out for the rest of the window. The `login_attempts` rows persist as an audit trail; pruning policy is a TODO. Note: the web `/login` form (`src/Auth.php::attempt`) is still unthrottled — covering it is a separate follow-up.

---

## 2.10.3 — 2026-04-28

### Security Hardening
- **SSO client secret no longer reaches the browser DOM** — `templates/pages/admin/settings/sso.php` was rendering the saved Microsoft 365 client secret into the `value` attribute of the password input on the SSO settings page. Although the field was `type="password"`, the plaintext secret was visible to anyone with admin access via DevTools, "View source", the eye-toggle button next to the input, browser history, or screenshots. Fixed by always rendering an empty `value`; the existing "Leave blank to keep it unchanged" UX (handled in `src/routes/admin.php:52`) means saving the form without retyping the secret continues to preserve the stored value. Also added `autocomplete="new-password"` so password managers don't auto-fill or memoize the field.
- **Rescue script removed from project root** — `rescue.php` was committed at the repository root. It is unauthenticated by design (lists all users and resets admin passwords) and only meant to be temporarily dropped into `public/` during emergency recovery. Moved the canonical copy to `scripts/admin/rescue.php` (alongside the other admin CLI scripts, outside the webroot) and updated the file's header to document the copy-into-public/use/delete workflow. `.gitignore` now blocks `/rescue.php` at the project root in addition to the existing `/public/rescue.php` rule, so a working copy can't be re-introduced in either location by accident.

---

## 2.10.2 — 2026-04-28

### Security Hardening
- **Baseline HTTP security response headers** — `src/bootstrap.php` now emits `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (geolocation/microphone/camera disabled), `Strict-Transport-Security` (when the request is HTTPS), and a baseline `Content-Security-Policy` (`default-src 'self'`, scripts/styles limited to `'self'` + the jsDelivr CDN we already load Bootstrap from, `frame-ancestors 'none'`, `form-action 'self'`, `base-uri 'self'`). The CSP currently permits `'unsafe-inline'` for scripts and styles to accommodate existing inline event handlers and styles in templates; this can be tightened to a nonce-based policy in a follow-up. Closes the clickjacking and MIME-sniffing exposure surfaced by a security review — the rest of the baseline (CSRF, prepared statements, `password_hash`, `session_regenerate_id`, `HttpOnly`/`SameSite=Lax`/`Secure` cookies, `htmlspecialchars`-based output encoding, MIME-whitelisted out-of-webroot uploads, SHA-256-hashed API tokens) was already in place.

---

## 2.10.1 — 2026-04-27

### Bug Fixes
- **Invisible submit buttons on auth pages** — the `auth` layout (used by login, 2FA, the SSO location picker, surveys, and the setup wizard) was not defining the `--ld-primary` and `--ld-primary-hover` CSS custom properties. Buttons styled with `background: var(--ld-primary)` and `text-white` therefore rendered as white text on a transparent background sitting on a white card, making them effectively invisible. Reported when a brand-new SSO user reached `/sso/pick-location` and saw no Continue button. Fixed by adding the same `:root` declaration the other layouts already use.

---

## 2.10.0 — 2026-04-24

### Portal Vocabulary — Phase 1 of the UI Plain-Language Pass
- **"Ticket" → "Help Request" on the portal** — all portal-facing copy now reads as a non-technical staff member would expect. "New Ticket" → "New Help Request", "My Tickets" → "My Requests", "Submit Ticket" → "Submit Request", "Edit Ticket" → "Edit request", "Close Ticket" → "Close this request". Agent and admin views are unchanged — "Ticket" is still the internal term for staff who handle the queue. Motivation: the LLM Council surfaced that front-line library staff associate the word "ticket" with patron fines, creating a hesitation point on the portal submit button.
- **Portal status badges in plain English** — the portal dashboard, ticket list, and ticket detail view now translate the internal status codes to natural phrases: `open` → "Submitted", `in_progress` → "We're working on it", `pending`/`waiting_on_third_party` → "We're waiting on someone else", `waiting_on_customer` → "Waiting on you", `resolved` → "Done", `closed` stays "Closed". Internal codes and agent/admin-facing labels are untouched.
- **Navbar "Portal" → "Help"** — the top-nav link that points to `/portal` now shows a life-preserver icon and reads **Help**, matching how staff think about the feature ("I need help" vs. "Let me go to the portal").
- **Portal priority field demoted** — the priority dropdown on the portal submit form now labels as **"How urgent is this?"**, defaults to "— Let our team decide —" when not required, and includes a hint that the team will set it if left blank. Reduces the "everyone picks High" failure mode the council flagged. Required/optional behaviour is unchanged.
- **"What happens next?" callout on portal ticket view** — when a portal user opens their own help request and it's still in `open` status, a blue info card now explains in plain language what to expect: request is queued, team will reply here, email notifications will follow. Closes the post-submit "did it actually go through?" anxiety and prevents the "I'll call IT to confirm" follow-up call.
- **Portal onboarding tour rewritten** — Driver.js tour steps for portal users now use the new vocabulary throughout (dashboard, list, submit, edit, close, notifications).
- **Label system extended** — new `portal.status.*`, `portal.request.*`, `portal.action.*`, `portal.field.priority_label`, `portal.what_next.*`, and `portal.nav.help` keys added to `config/labels.default.json` so admins can further customize the wording without touching templates.

---

## 2.9.0 — 2026-04-23

### Portal Escalation Visibility
- **"Escalated" Badge on Portal Ticket List** — rows for tickets with `escalation_level > 0` now show a red **"Escalated L#"** pill next to the subject, so requesters can see at a glance which of their tickets have been escalated (whether by themselves or by an automated rule). Previously the ticket list gave no indication at all.
- **Escalation Indicators on Portal Ticket View** — the ticket header now shows an **"Escalated — Level N"** badge alongside the status pill, and the Details sidebar gains a new **Escalation** row mirroring what agents see. Previously, the only portal-side signal was a buried timeline entry that was easy to miss.

---

## 2.8.4 — 2026-04-23

### Escalations
- **Escalation Rule Recipients Can Open the Ticket** — when an escalation rule's `notify_user` or `notify_assigned_agent` action fires, the target agent/admin is now auto-added to `ticket_watchers` so they can actually open the ticket from the email link. Previously, if the recipient wasn't in the ticket's group (and wasn't the current assignee), clicking through produced a 403 — a confusing experience for a system-generated alert. Mirrors the behaviour of manual matrix-based escalation, which already added watchers. Portal users are not added (they use a separate access path).

---

## 2.8.3 — 2026-04-23

### Portal Privacy
- **Per-Type Opt-Out from Location Ticket Visibility** — ticket types now have a **"Visible to Location Ticket Visibility users"** checkbox (checked by default). Uncheck it for routine-but-sensitive categories like *Collections*, *Human Resources*, or *Payroll* so those tickets don't surface to end users who have location visibility enabled, without having to invoke the heavier **Confidential** flag (group-lock + re-auth + access-log). Requesters still see their own ticket regardless of this setting.
- **Portal Query Alignment** — the portal ticket list, detail view, comment endpoint, and attachment download all now exclude tickets whose type has `show_to_location_visibility = 0` from the location-based access path, alongside the existing `is_confidential = 1` exclusion.
- **Documentation** — [DOCS.md](DOCS.md) gains a new **Location Ticket Visibility** subsection and a fleshed-out **Ticket Types** field reference, including a side-by-side comparison of when to use *Confidential* versus *Visible to Location Ticket Visibility users*.

---

## 2.8.2 — 2026-04-22

### Portal Privacy
- **Confidential Tickets Excluded from Location Visibility** — users with `can_view_location_tickets = 1` no longer see confidential-type tickets submitted by others at their location. The portal ticket list, ticket detail view, comment endpoint, and attachment download now all exclude tickets whose `ticket_type.is_confidential = 1` from the location-based access path. Confidential types remain restricted to their assigned group (and the requester still sees their own ticket via `created_by`, regardless of confidentiality).
- **Attachment Download Alignment** — the portal attachment download previously checked ownership only, so users with location visibility could view a ticket but not download its files. The endpoint now mirrors the view-access rule, allowing downloads for location-visible tickets while still respecting the new confidentiality carve-out and internal-note exclusion.

---

## 2.8.1 — 2026-04-22

### Cron Jobs
- **Run Status on Cron Jobs Page** — **Admin → Settings → Cron Jobs** now shows live status for every scheduled script. Each card gets a `Running`, `Stale`, or `Not configured` badge and a "Last run: YYYY-MM-DD HH:MM:SS" line, detected by the modified time of the job's log file. A summary row at the top of the page counts how many jobs fall into each bucket, so a misconfigured server is obvious at a glance.
- **Detection Logic** — a job is "Running" if its log was touched within 2× its expected interval (e.g. SLA cron = 5m interval → 10m window), "Stale" if older, "Not configured" if the log file doesn't exist yet. No new dependencies; the existing log files the scripts already write are the source of truth.

---

## 2.8.0 — 2026-04-21

### Stale Ticket Notifications
- **Stale-Ticket Cron** — new `scripts/process-stale-tickets.php` runs hourly, finds active tickets (`open`, `in_progress`, `pending`) that have had no activity for longer than a configurable threshold, and emails both the assigned agent (or group if unassigned) and the requester. Tickets in `waiting_on_customer`, `waiting_on_third_party`, `resolved`, or `closed` are skipped — the clock only runs while the ticket is genuinely waiting on the team.
- **Requester Reassurance** — the requester gets a "we haven't forgotten you" check-in email even when there's no real update, closing the communication gap that silent tickets create.
- **Per-Type Overrides** — each ticket type can override the global stale threshold (leave blank to inherit). Useful for urgent types (e.g. "Facilities Emergency" at 4h) versus slow-burn types (e.g. "Project Intake" at 14 days).
- **Configuration UI** — new **Admin → Settings → Stale Tickets** page sets the global threshold, re-notify window, and toggles for agent/requester emails. Includes a one-click "Run Now" button and visibility into the current per-type overrides. A new stale-threshold field also appears on the Ticket Type editor.
- **Smart Re-Nag** — subsequent runs only re-notify a given ticket after the configured re-check window (default 24h), dedup'd via a `stale_notification_sent` entry in the ticket timeline, so stale tickets don't spam inboxes every hour.
- **Timeline Entry** — every stale notification writes an internal timeline entry so agents can see when the reminder fired and how stale the ticket was at that moment.

---

## 2.7.3 — 2026-04-21

### Access Control
- **Assignees and Watchers Bypass the Group Wall** — agents who are the current assignee or an explicit watcher of a ticket can now open it even if the ticket's group sits outside their own group memberships. Previously, escalating (or manually assigning) to someone outside the group emailed them a link that 403'd. The exemption applies everywhere group-based visibility is enforced: the ticket view, all JSON API endpoints, and agent attachment downloads. Group-based filtering on the ticket list is unchanged.

---

## 2.7.2 — 2026-04-21

### Escalation
- **Skip Current Assignee in Escalation Matrix** — if a ticket is already assigned to someone who appears in the escalation path for its type, escalating now jumps to the step *after* that person instead of routing back to an earlier level. The assignee's position in the matrix is treated as a floor on top of the ticket's `escalation_level`, so escalation always moves the ticket up the chain.

---

## 2.7.1 — 2026-04-21

### Portal Escalation
- **Requesters Can Escalate** — the red `Escalate` button now also appears on the end-user portal ticket view (`/portal/tickets/{id}`), not just the agent panel. Requesters can escalate their own tickets when they feel a ticket needs more attention, matching the "empowerment with visible backup" theme of the business-continuity report.
- **Same Modal & Confirmation** — the portal uses the same confirmation pattern as the agent view: it shows the exact person (name + level + label) the ticket will be escalated to, and accepts an optional reason.
- **Owner-Only** — only the ticket's creator can escalate it from the portal. Users viewing a ticket via the location-view permission see no Escalate button. The API endpoint was opened up to the `user` role but now enforces a `created_by === Auth::id()` check for non-agent callers.
- **Timeline Icon** — the portal timeline now renders a red arrow-up-circle icon for `escalated` events so requesters can see when they (or anyone) escalated the ticket.

---

## 2.7.0 — 2026-04-21

### Manual Ticket Escalation
- **Escalation Paths per Ticket Type** — admins can now define an ordered chain of agents per ticket type under Admin → Settings → Escalation Paths. The topmost row is Level 1; drag-to-reorder is supported. Each step accepts an optional label such as "Branch Supervisor" or "Service Manager" so agents know who they're escalating to.
- **Escalate Button** — agents see a red `Escalate` button in the ticket action bar. Clicking it opens a confirmation modal showing the next person in the path (with level and label) and an optional reason textarea. Confirming reassigns the ticket, increments the escalation level, and notifies the new assignee.
- **Previous Assignee Kept in the Loop** — when a ticket is escalated, the prior assignee is automatically added as a watcher so they continue to receive updates without having to ask for them.
- **No-Escalate Guards** — the button is disabled (with a tooltip explanation) on tickets that are closed, merged, have no type set, have no path configured, or are already at the top of their chain. The server enforces the same rules.
- **Self-Skip** — if the current user appears in the chain at or beyond their current level, the logic skips past themselves to the next step (you can't escalate to yourself).
- **Escalation History in Ticket View** — the ticket detail page now shows an Escalation History card listing every escalation event, who triggered it, who it went to, any reason, and the timestamp. An Escalation Level badge appears in the Details sidebar when a ticket has been escalated.
- **"Escalated to Me" Stat Card** — agents see a red stat card on the dashboard counting open tickets that have been escalated to them, linking to a pre-filtered ticket list. A matching checkbox appears in the ticket-list filter panel under "Escalation".
- **Email Notification** — the new assignee receives a distinct red-themed `ticket-escalated` email that names who escalated it, the level, the previous assignee, and the reason. Template customizable in Admin → Settings → Email Templates via the `ticket_escalated_agent` key.
- **Audit Trail** — every escalation writes a timeline entry, a row in `ticket_escalations`, and an `audit_log` entry under the action `ticket_escalated`.
- **Separate from SLA-Driven "Escalation Rules"** — this manual path feature is a distinct settings page from the existing time-driven automated escalation engine. Both coexist; the settings nav now lists them as "Escalation Paths" and "Escalation Rules".

### Database
- Migration `021_escalation_paths.php` adds `tickets.escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0`, the `ticket_escalation_steps` table (ordered chain per ticket type), and the `ticket_escalations` table (audit log of every escalation event).

---

## 2.6.0 — 2026-04-21

### Requester Email Notifications — Acknowledgement & Assignment
- **Ticket Submitted Acknowledgement** — the ticket creator now reliably receives a confirmation email from every creation path (portal form, admin/agent form, email-to-ticket, mobile API). Previously only the portal form and inbound email dispatched an ack, and the portal path bypassed the global toggle.
- **Ticket Assigned Notification** — when an agent is assigned to a ticket (either at creation or later via the ticket detail page), the requester now receives an email naming the agent who will be handling their ticket. Uses a new `ticket-assigned-requester` email template customizable in Admin → Settings → Email Templates.
- Assignment email is skipped when the requester is also the assignee (they already receive the agent-side assignment email).
- **Per-User Opt-Out** — new `Ticket assigned` toggle added to the profile notification preferences for portal users, and `My ticket assigned` added to the "Other Notifications" section for agents/admins who also submit tickets.
- **Global Default** — new `Ticket Assigned to Agent` switch added to Admin → Settings → Email Notifications under Ticket Requester Notifications, alongside the existing new-ticket/resolved/closed toggles.
- Both notifications use the project's standard two-level gating: the admin-level switch must be on AND the user's individual preference must be on.
- Consolidated the previously duplicated inline acknowledgement logic in `portal.php` and `process-replies.php` into a single `notifyRequesterTicketCreated()` helper.

### Database
- Migration `020_notify_ticket_assigned.php` adds a `notify_ticket_assigned TINYINT(1) NOT NULL DEFAULT 1` column to `users`.

---

## 2.5.0 — 2026-04-21

### Full-Website Backup
- **Entire Site Snapshot** — the admin backup now archives the complete application directory under a `website/` prefix in the zip, not just attachments and uploads
- Includes PHP source, templates, `config/`, `.env`, `vendor/`, scripts, ticket attachments, branding assets, avatars, and logs — everything needed to restore the site to a fresh server alongside `database.sql`
- Only `storage/backups/` itself is excluded, to prevent the in-progress archive from recursing into itself and pulling in prior backups
- Bumped the backup request time limit from 300 s to 1800 s and raised the memory limit to 512 MB to accommodate larger archives
- Admin → Settings → Backup page copy, "What's Included" card, and restore instructions updated to reflect the new scope

---

## 2.4.0 — 2026-04-10

### Confidential Tamper Protection
- **Re-Authentication Required** — removing the confidential flag from a group or ticket type now requires the admin to re-enter their password before the change is applied
- **Re-Authentication on Delete** — deleting a confidential group or confidential ticket type also requires password re-authentication
- **Attempt Logging** — every attempt to remove a confidential flag or delete a confidential entity is recorded in the audit log the moment the re-auth page is shown, even if the admin cancels without authenticating
- **Failed Auth Logging** — incorrect password attempts during re-authentication are separately logged in the audit log
- **Email Alerts on Flag Removal** — all members of the affected group receive an email alert when the confidential flag is removed from their group or a linked ticket type, including the admin's name, email, IP address, and timestamp
- **Email Alerts on Deletion** — all members receive an email alert when a confidential group or confidential ticket type is deleted, with the same identity details
- **Red Security Alert Emails** — new email templates (`confidential-flag-removed`, `confidential-entity-deleted`) with red danger styling, distinct from the amber membership-change alerts
- **Documentation** — admin help documentation (Admin → Docs → Users & Roles) updated with comprehensive Confidential Groups section covering all security measures: membership alerts, ticket redaction, re-authentication gates, access notifications, audit logging, and the new tamper protection

### Audit Log Actions Added
- `confidential_flag_removal_attempted` — admin unchecked the confidential flag (re-auth page shown)
- `confidential_flag_removal_auth_failed` — admin entered wrong password on re-auth
- `confidential_flag_removed` — admin successfully removed the confidential flag after re-auth
- `confidential_delete_attempted` — admin attempted to delete a confidential entity (re-auth page shown)
- `confidential_delete_auth_failed` — admin entered wrong password when deleting
- `confidential_entity_deleted` — admin successfully deleted a confidential entity after re-auth

---

## 2.3.0 — 2026-04-01

### Confidential Ticket Types
- **Confidential Flag** — admins can mark ticket types as "Confidential" in Settings → Ticket Types; requires a group to be assigned
- Tickets of a confidential type are only visible to members of the type's assigned group
- Agents not in the group cannot see confidential tickets in listings or search results
- Admins outside the group see confidential tickets in listings with redacted subjects ("[Confidential]")
- **Admin Re-Authentication** — viewing a confidential ticket requires re-entering the admin's password; a warning explains that access is logged and group members will be notified
- Access grants a 5-minute session window before re-authentication is required again
- **Audit Logging** — every admin access to a confidential ticket is recorded in the audit log (`confidential_ticket_viewed`) and as an internal timeline entry on the ticket
- **Group Email Notification** — all members of the confidential type's group receive an email alert with the admin's name, email, IP address, and timestamp when a confidential ticket is accessed
- **API Enforcement** — confidential tickets are excluded from API listings for non-group members; individual ticket endpoints return 403 with a message to use the web interface
- **Edge Case Protection** — bulk actions exclude inaccessible confidential tickets; merge and split operations are blocked; search/typeahead results redact subjects; CSV exports replace sensitive fields with "[Confidential]"
- Admin and agent help documentation updated with Confidential Ticket Types section

---

## 2.2.0 — 2026-04-01

### CSAT Redesign
- **Emoji Ratings** — customer satisfaction surveys redesigned with emoji-based ratings and an issue-resolved flow
- **Send Test Survey** — admins can send a test CSAT survey from the admin settings page
- Fix: emoji rating section removed from CSAT email (ratings are on the survey page)
- Fix: missing button key added to `csat_survey` email template default

### Custom Labels
- **System Field Labels** — admins can customise the display labels for system fields (e.g. rename "Location" to "Branch") and control whether Priority and Tags are required

### Email Templates
- **Agent Assigned, Group Assigned, and Escalation Alert** email templates added to admin settings for customisation

### UX Improvements
- **Bootstrap Modals** — all browser `confirm()` dialogs replaced with styled Bootstrap confirmation modals
- **Auto-Populate Holidays** — button added to admin holidays settings to auto-populate public holidays
- **Type Field on Update Card** — ticket type can now be changed from the Update Ticket card for agents and admins
- Browser and OS info hidden from non-admin users on ticket details
- UI improvements for ticket listing pages (spacing, alignment, responsive)
- Fix: columns resize correctly after quick-change dropdown selection

---

## 2.1.0 — 2026-03-12

### Ticket Detail Page Redesign
- **Three-Column Layout** — ticket detail page reorganised into three columns: main content, ticket actions, and a metadata/SLA sidebar
- **SLA Card** — SLA status card moved to the third column, below the Update Ticket panel
- **Inline Attachments** — file attachments are now displayed inline within their parent timeline entry rather than in a separate section
- System-generated timeline events (e.g. SLA state changes) hidden from portal users and non-admin views

### WYSIWYG Editing (CKEditor 5)
- **New Ticket Forms** — description field on the portal, admin, and agent ticket creation forms replaced with a full CKEditor 5 rich-text editor
- **Reply / Comment Area** — reply and comment textarea on admin and agent ticket views replaced with CKEditor 5
- **KB Article Editor** — knowledge base article editor upgraded from Quill to CKEditor 5 with font color, background color, image upload, and table support
- Ticket descriptions created with the WYSIWYG editor now render as HTML throughout the UI
- Fix: CKEditor data correctly populated in the hidden field when using Send & Set Status

### Ticket Management
- **Group-Scoped Assignee Dropdown** — when a ticket belongs to a group, the assignee dropdown is filtered to members of that group only
- **Ticket Type Color Picker** — color picker added to ticket type create and edit forms
- **Ticket Type Deletion Safety** — attempting to delete a ticket type that has tickets now prompts to either reassign affected tickets to another type or delete them
- **Clickable URLs** — URLs in ticket descriptions and timeline entries are automatically rendered as clickable links

### Custom Form Fields
- **CC Field Type** — new CC field type added to the ticket form builder, allowing tickets to include a list of CC'd users
- CC field rendered with user autocomplete on the ticket create form
- Fix: image field configuration preserved correctly when saving field properties in the form builder

### Portal
- **Requester Self-Close** — portal users can close their own open tickets directly from the ticket detail page
- **Requester Self-Edit** — portal users can edit the subject and description of their own open tickets
- Both self-service actions write internal-only timeline entries (visible to agents and admins only) recording what changed
- Portal users can now change their location assignment when creating a ticket
- Open tickets and Resolved Today stat cards removed from the portal dashboard for a cleaner view

### Groups & Notifications
- Group new-ticket email notification toggle moved from the admin groups list to the individual group edit form

### Email Templates
- **Rich Text Intro Messages** — the Intro Message field on all email templates now uses a CKEditor 5 rich-text editor (bold, italic, lists, links); formatted HTML renders directly in outgoing emails
- **Group Alerts Template** — new tab for customising the subject, intro message, and button label of the group alert email sent when a new ticket is assigned to a group with alerts enabled
- **Shared Footer Tab** — dedicated tab for customising the shared footer text that appears on all outgoing ticket emails

### Agent Help Documentation
- Built-in help pages for agents at `/agent/help`, covering the dashboard, ticket list & filters, working on tickets, and canned responses
- Accessible from a **Help** link in the agent sidebar (question-circle icon)
- Includes a search widget on the overview page

### Knowledge Base
- Edit Article button added to KB article pages for admins and agents (no longer requires navigating back to the article list)
- Fix: agents can now access the KB article preview route

### Dashboard & Ticket List
- Agent dashboard stat cards now link directly to the filtered ticket list for that stat
- Ticket list displays a filtered count vs total (e.g. "Showing 12 of 47") when filters are active
- **Inline Ticket List Actions** — hovering the Agent, Type, or Group column on any ticket row reveals a chevron; clicking it opens a dropdown to reassign the agent, change the type, or change the group without opening the ticket
- Agent quick-assign dropdown is filtered to members of the ticket's group when a group is set
- **Column Picker on Agent Dashboard** — the Recent Tickets widget on the agent dashboard includes the column picker and the same inline Agent/Type/Group chevron actions

### Tags
- Tags feature can now be toggled on or off from Admin → Settings

### Location Management
- **Bulk Reassign on Location Delete** — deleting a location that has tickets now prompts to reassign those tickets to another location before deletion

### Bug Fixes
- Fix: profile page now correctly saves notification preferences
- Fix: password change and notification preferences separated into independent form submissions to prevent conflicts
- Fix: "View All" link on the dashboard Recent Tickets card correctly clears filters and shows all permitted tickets
- Fix: ticket detail header action buttons wrap correctly on mobile screens instead of causing horizontal scroll
- Fix: tickets without a type assigned display "Not Set" rather than a blank value
- Fix: "Knowledge Base Articles" label corrected in the global search box

---

## 2.0.0 — 2026-03-03

### Mobile REST API
- Full REST API with Bearer token authentication for mobile and third-party integrations
- Endpoints covering tickets, comments, users, groups, knowledge base articles, and notifications
- OpenAPI 3.0.3 specification at `openapi.json`
- API tokens stored as hashes at rest with configurable expiry and rotation support
- CSRF protection on all session-authenticated JSON API endpoints
- Group-based ticket access enforced on all individual-ticket API endpoints

### Power User Role
- New `power_user` role — full agent access plus access to Reports & Analytics
- Selectable on user create, edit, and import flows
- Fully supported throughout the API and all role-guarded routes

### Portal Improvements
- Portal ticket list defaults to own tickets and open status on first load
- **My Location** toggle for users eligible to view tickets from their location
- Portal users without a location assignment are prompted to choose one when creating a ticket
- Portal onboarding tour (Driver.js) covering key pages; extended to include the profile page

### Ticket List Enhancements
- **Multi-Select Filter Checkboxes** — filter by multiple statuses, priorities, types, groups, and agents simultaneously on the admin and agent ticket lists
- **Multi-Select Filters on Admin Users Page** — filter users by multiple roles and locations at once
- **Per-Page Selector** — choose how many tickets appear per page (25 / 50 / 100)
- **Watched Tickets Filter** — filter the ticket list to tickets you are watching
- **Search by Ticket ID** — enter a ticket number in the global search box to jump directly to that ticket
- Saved filters moved to the top of the filter panel for quicker access
- Filter state persisted across navigation within the same browser session

### Ticket Actions & Workflow
- **Auto-Assign on First Reply** — ticket automatically assigned to the agent or admin who posts the first public reply if previously unassigned
- **Choose Primary Ticket on Merge** — modal prompts the user to designate which ticket is primary; primary ticket inherits the highest priority of either ticket
- **Linkify Merged Ticket Numbers** — merged ticket reference numbers in the timeline are rendered as clickable links
- **Ticket Filter Panel on User Profile** — admin user profile pages include a filterable ticket list showing all tickets created by that user

### User Management
- **Delete User with Data** — option to delete a user along with all their tickets, comments, and attachments in a single operation
- **Admin User Merge** — merge duplicate user accounts, reassigning all tickets and comments to the surviving account
- **Ticket Transfer on Deletion** — when deleting a user, choose to reassign their open tickets to another agent before the account is removed
- Group membership shown on agent and admin edit pages

### Email & Notifications
- **Email Notifications Settings Page** — dedicated page at Admin → Settings → Email Notifications to manage all outgoing notification hooks
- **Group Email Notifications** — optional email alert to all group members when a new ticket is assigned to that group
- **Microsoft Graph App Secret Expiry Reminders** — warning banner and scheduled reminder emails when the configured Graph API client secret is nearing expiry

### Location & Timezone
- **Per-Location Timezone** — each location can have its own timezone used for SLA business-hours calculations; falls back to the global app timezone if not set
- **Location Ticket Visibility Flag** — optionally restrict a user's ticket view to tickets from their assigned location only

### Branding & Settings
- Configurable navbar fallback icon selectable from the Branding settings page
- CSAT survey email template added to the customisable email templates list

---

## 1.1.1 — 2026-03-02

### Microsoft 365 SSO
- Single sign-on via OAuth 2.0 Authorization Code flow using Microsoft Entra ID (Azure AD)
- Configure client ID, tenant ID, and client secret under Settings → Microsoft 365 SSO
- "Sign in with Microsoft" button on the login page; HTTPS scheme auto-detected for redirect URI
- SSO debug logging available for troubleshooting login failures

### Email-to-Ticket
- Inbound emails to a monitored Microsoft Graph mailbox are automatically converted to new tickets
- Sender looked up by email address; new portal user account created if no match found
- Subject becomes the ticket subject; plain-text body becomes the description
- Runs as part of `scripts/process-replies.php`

### Ticket Watching
- Agents and admins can watch any ticket to receive notifications on all updates
- Watch/unwatch toggle on the ticket detail view
- Watched tickets appear in the **Watched** filter on the ticket list

### Ticket Splitting
- Split a single ticket into two separate tickets from the ticket detail view (admin and agent panels)
- Choose which timeline entries to move to the new ticket
- Both tickets retain the original metadata; a system note links them

### Canned Responses
- Admins and agents can create personal canned response snippets for common replies
- Insert into the reply box from a dropdown picker on the ticket detail view
- Token substitution: `{{ticket_id}}`, `{{ticket_subject}}`, `{{user_name}}`, `{{agent_name}}`

### Reply / Forward / Note Panel
- Ticket detail reply area replaced with a tabbed panel supporting **Reply**, **Forward**, and **Note** modes
- Forward mode sends the ticket content to an external email address
- Note mode creates an internal note (hidden from portal users)

### User Import from CSV
- Bulk import user accounts from a CSV file at Admin → Settings → Import Users
- Flexible column-mapping step to match CSV headers to LocalDesk fields
- Dry-run preview shows row count, role breakdown, and detected duplicates before committing
- Skipped rows downloadable as a CSV after import
- Sample CSV available for download from the import page

### Holidays / Closed Days Management
- Configure public holidays and custom closed days at Admin → Settings → Holidays
- Per-holiday toggle to exclude the day from SLA business-hours calculations
- Integrated into the business-hours-aware SLA timer

### Waiting-on-Customer Reminder Email
- Escalation rule action that sends an automated reminder to the ticket submitter when a ticket has been in **Waiting on Customer** status for a configurable period

### Automatic Database Migration System
- Schema changes applied automatically on each request if new migration files are present
- Migrations tracked in the `migrations` table; idempotent and safe to re-run
- New migrations added via numbered PHP files in `database/migrations/`

### Versioning System
- `APP_VERSION` constant defined in `config/version.php` following Semantic Versioning
- Current version displayed in the user dropdown menu

### Timeline Improvements
- Timeline entries shown in reverse chronological order (most recent first)
- Long timelines collapsed to the 10 most recent entries with an **Expand** button to show all

### Agent Onboarding Tour
- Spotlight-style step-by-step tour for new agent accounts covering the dashboard, ticket list, and ticket detail view
- Replayable from the user dropdown

### Automations — Nested Boolean Logic
- Automation conditions now support nested AND / OR groups for complex rule matching

### SMTP Debug Logging
- SMTP session log written to `storage/logs/smtp.log` with a toggle in Settings → Email
- Useful for diagnosing delivery issues without exposing credentials

### Label Customisation
- The word "Location" throughout the UI is driven by a configurable label, allowing sites to substitute their preferred term (e.g. "Branch", "Site")

### Scheduled Reports — Schedule Button
- "Schedule" button added to each individual report page as a shortcut to create a scheduled report for that report type

### Users Filter Slide-Out Panel
- Admin users list filter bar replaced with a slide-out panel matching the ticket list panel style

### Admin Tools
- **Admin Password Rescue Script** — command-line script to reset an admin password or change a user's role when locked out of the UI
- **Danger Zone Full Reset** — settings page option to wipe all application data and re-run the setup wizard
- Post-install success page now shows next-steps checklist including cron job setup instructions

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
