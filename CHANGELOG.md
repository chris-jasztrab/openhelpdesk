# Changelog

All notable changes to OpenHelpDesk will be documented in this file.

Follows [Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`
- **MAJOR** — breaking changes
- **MINOR** — new backwards-compatible features
- **PATCH** — backwards-compatible bug fixes

To release a new version: update `config/version.php`, add a dated entry below under `## Unreleased`, then move it to a new versioned section.

---

## 2.48.0 &mdash; 2026-05-19

### Changed
- **Audit log action names standardized on `<area>.<verb>` convention (Phase 4) with single-source-of-truth back-compat aliases so existing rows still filter correctly.** Seventeen legacy action names from before the Phase-1/2/3 work — `login`, `logout`, `2fa.enable`, `2fa.disable`, `2fa.admin_reset`, `ticket_escalated`, `confidential_ticket_viewed`, `group_managers_changed`, `ai_settings_saved`, `ai_classification_override`, `ai_backfill_run`, `manager_skill_assignments_changed` / `_created` / `_updated` / `_deleted`, `default_group_changed`, `escalation_path_saved` — are now written under their canonical equivalents (`auth.login`, `auth.logout`, `auth.2fa_enabled`, `auth.2fa_disabled`, `user.2fa_reset_by_admin`, `ticket.escalated`, `ticket.confidential_viewed`, `group.managers_changed`, `ai.settings_changed`, `ai.classification_override`, `ai.backfill_run`, `manager.skill_assignments_changed` / `_created` / `_updated` / `_deleted`, `settings.default_group_changed`, `escalation_path.saved`).
- **Single alias-map source of truth** in [`auditAliases()`](src/helpers.php) feeds three places at once: `logAudit()` rewrites legacy → canonical at write time (so even an un-renamed third-party callsite emitting an old name lands canonically in the table); the audit-log viewer route canonicalizes each fetched row before render so legacy entries display under their new name and badge color; and the action-filter dropdown deduplicates legacy + canonical into one entry while the WHERE clause expands the user's selection back out to `action IN (canonical, ...legacy)` so old rows still match. Result: rolling out the rename was a one-table change, no DB migration, no broken filter on historical data.
- **`auditCanonicalAction()`** and **`auditLegacyAliasesFor()`** helpers exposed for any future caller (e.g. analytics scripts, scheduled-report jobs) that needs to bridge the two namespaces.
- **The confidential_\* family** of action names (`confidential_flag_removed`, `confidential_delete_attempted`, `confidential_entity_deleted`, etc.) was intentionally **not** renamed — those names form a cohesive feature namespace with consistent semantics across the seven entries, and renaming a coherent set would have been churn for churn's sake. They stay as-is.
- **Audit-log viewer badge-color map** was rekeyed to canonical names only, with entries added for every new action introduced in Phases 1–3 (`auth.api_login`, `api_token.rotated`, `backup.deleted`, `audit_log.pruned`, etc.) so the colored chips on the results page reflect actual severity instead of falling through to the default `info` color.

---

## 2.47.0 &mdash; 2026-05-19

### Added
- **Audit log viewer — Phase 3: cross-references `ticket_timeline` so the admin gets a single pane of glass for "who did what" without forcing a double-write.** Per-ticket state-machine events (status, priority, assignment, group, type, merge, escalated, created) were already captured in `ticket_timeline` and shown on the per-ticket page; previously they were invisible at `/admin/audit-log` and any attempt to mirror them into `audit_log` would have doubled-up the write path for every ticket mutation. Now the viewer's `SELECT` builds a UNION-ALL subquery that pulls from `audit_log` *and* from a curated allowlist of `ticket_timeline` actions (the eight listed above; comments, notes, and automation-generated rows are excluded so routine reply traffic doesn't drown out actual admin events). Counts, pagination, ordering, actor list, and action list all run against the unified set; `ip_address` is `NULL` for timeline rows because the original write path doesn't capture it.
- **"Source" filter dropdown** at `/admin/audit-log` — `All sources` / `Audit log only` / `Ticket history only`. The viewer also auto-routes when the action filter is set: picking a `ticket.*` action implicitly hides `audit_log`, picking a non-prefixed action implicitly hides `ticket_history`, so the existing action dropdown still narrows correctly without the user needing to also touch the source filter. The action dropdown now lists both audit-log actions and `ticket.<verb>` actions in one sorted list, deduplicated across the union. A `Source` column on the results table renders each row with a colored badge (gray "Audit" for `audit_log`, blue "Timeline" for `ticket_timeline`) so it's never ambiguous which write path a row came from. Ticket targets are linkified — `ticket #N` in the Detail column is now a clickable badge that opens the ticket detail page in admin.
- **Pagination preserves the source filter** across page navigation (the prior commit's `from`/`to`/`user_id`/`action` round-tripping is now joined by `source`).

### Notes
- **No new writes added.** Per the Phase-1 design decision documented in the plan, ticket-scoped state changes continue to live in `ticket_timeline` exclusively; the audit log just quotes them at read time. This keeps each event in exactly one place and avoids the per-mutation write amplification that mirroring would have caused.
- **Audit-log prune still only touches `audit_log`** — `ticket_timeline` has its own retention story (it's tied to ticket lifetime via `ON DELETE CASCADE`).

---

## 2.46.0 &mdash; 2026-05-19

### Added
- **Audit log coverage — Phase 2: admin config CRUD pass.** Every create/update/delete on the admin settings surfaces that previously left no audit trail now emits one entry, with `logAuditChange()` used on update handlers so the detail field carries a `field: old→new` diff rather than just the row id. Areas instrumented in this pass:
  - **Catalog entities** — locations (`location.created`/`location.updated`/`location.deleted` + `location.timezone_settings_changed`), priorities, ticket types (full-row diff incl. `is_confidential`, `ai_route_group`, `ai_dup_check_enabled`, `ai_dup_threshold`, `show_to_location_visibility`, `stale_threshold_hours`; non-confidential deletes get a standard `ticket_type.deleted` row, confidential deletes continue to use the existing dedicated entry), canned responses, groups (full-row diff incl. `assign_strategy` / `assign_fallback` / `is_confidential`; non-confidential delete gets `group.deleted`), agent skills, ticket templates, recurring tickets (CRUD + `recurring_ticket.toggled` + `recurring_ticket.run_now`), KB categories/folders/articles, and the KB import-confirm step (`kb.import_confirmed` with article count + `publish_all` flag).
  - **Business-hours & SLA** — `business_hours.updated` and `sla.policies_saved` / `sla.recalculated`. Holidays now emit `holiday.created` / `holiday.deleted` / `holiday.toggled` / `holiday.auto_populated` (with country + year + added/skipped counts).
  - **Automations** — full CRUD (`automation.created` / `.updated` / `.deleted`) plus `automation.toggled` and `automation.run_now` (with `tickets_affected` count).
  - **Escalation rules** — full CRUD plus `escalation_rule.toggled` and a top-level `escalation.run_now` from the manual processor button.
  - **Scheduled reports** — CRUD plus `scheduled_report.toggled`.
  - **Form builder** — `form_field.created` (with shared-with type count), `form_field.updated`, `form_field.deleted`.
  - **Tenant settings** — SMTP (`smtp.settings_changed` with `smtp_password` redacted to `(rotated)`/`(unchanged)`), Microsoft Graph (`graph.settings_changed` with `graph_client_secret` redacted the same way), email-to-ticket, email templates (per-tab save + per-template reset + footer reset), email notifications, branding (full colour-palette + logo state diff), label upload/reset, tags, organization, CSAT, stale-tickets settings.
  - **Bulk imports** — `ticket.import_confirmed` and `user.import_confirmed` join the existing `kb.import_confirmed`, each carrying the imported and skipped row counts so admins can reconstruct any CSV-driven mass mutation from the audit log.
  - **Danger zone** — `system.factory_reset` is written immediately before `/admin/settings/danger-zone/reset` truncates every table (the row itself is wiped along with the rest, but the running request's record gives the trail one final entry in any external sink); `tables_truncated=<count>` is included for the same reason.

This is a wide, mechanical pass — no new infrastructure, just call-sites. Phase 3 (cross-referencing `ticket_history` for ticket-scoped actions in the audit-log viewer) and Phase 4 (naming standardization with back-compat aliases) are still pending.

---

## 2.45.0 &mdash; 2026-05-19

### Added
- **Audit log coverage — Phase 1: security & high-blast-radius gaps.** A wide swath of mutating actions that previously left no audit trail are now logged, with the granularity scaled to the actor's privilege (admin/power_user actions log everything; agents log ticket mutations; portal users log only auth + self-service changes). New entries:
  - **Authentication.** Web `auth.login_failed` (resolves the attempted email to a user_id when one exists so credential-stuffing patterns against real accounts are visible in the log); web `auth.password_changed` on `/profile/password`; web `user.profile_updated` on `/profile` with a per-field old→new diff via the new `logAuditChange()` helper.
  - **API authentication & tokens.** `auth.api_login` / `auth.api_login_failed` / `auth.api_logout` for `POST /api/v1/auth/login` and `POST /api/v1/auth/logout`, plus `api_token.created` (issuance) and `api_token.rotated` (rotate endpoint). Because these flows run before/without a session, `logAudit()` gained an optional `$userIdOverride` parameter so the row is attributed to the right user even when `Auth::id()` is `null`.
  - **SSO settings.** `POST /admin/settings/sso` now records `sso.settings_changed` with a per-field diff — the client secret is treated as sensitive and rendered as `(rotated)` / `(unchanged)` rather than logging the value.
  - **Bulk ticket operations.** `POST /admin/tickets/bulk` and `POST /agent/tickets/bulk` emit one summary audit row per action: `ticket.bulk_closed`, `ticket.bulk_assigned`, `ticket.bulk_merged`, `ticket.bulk_deleted` — each with the operation count and the affected ticket IDs in the detail field. Individual per-ticket state changes continue to live in `ticket_timeline` (no double-write).
  - **Mass-destructive admin operations.** `POST /admin/tickets/delete-all` writes `ticket.delete_all`; `POST /admin/settings/backup/create` and `POST /admin/settings/backup/delete` write `backup.created` / `backup.deleted` with the backup filename and (for create) the archive size.
- **`logAuditChange()` helper.** New `logAuditChange($action, $targetId, $targetType, array $before, array $after, array $sensitiveFields = [])` in [`src/helpers.php`](src/helpers.php). Diffs two snapshots and writes a single audit_log row with a `field: old→new; field2: old→new` summary; long values are truncated at 60 chars to keep the detail column readable, fields listed in `$sensitiveFields` render as `field: (changed)` so secrets and passwords never land in the log as plaintext, and the function returns silently (no row written) when nothing actually changed. Used today by the SSO settings handler and the profile update handler; will be the default change-recording mechanism for the Phase 2 config-CRUD pass.
- **Admin audit-log prune UI** at `/admin/audit-log`. The audit log retains entries forever by default, but admins can now permanently delete every entry older than a chosen date via a new red "Prune older entries" button in the page header which expands a confirmation card; submitting it `POST`s to `/admin/audit-log/prune` with a `before_date` (validated against `YYYY-MM-DD`) and clears `audit_log` rows whose `created_at < <date> 00:00:00`. The date picker's `min` is pinned to the oldest existing entry so it's impossible to pick a cutoff that would delete zero rows, the form requires a JS `confirm()` echoing the chosen date, and the prune action itself is logged as `audit_log.pruned` with the cutoff and the deleted-row count — so the record of pruning survives even after the rows it pruned are gone. CSRF-gated and admin-only like the rest of the audit log surface.

---

## 2.44.2 &mdash; 2026-05-15

### Documentation
- **GETTING_STARTED.md gains a new top-level §16 "Marking a comment as the solution"** covering the 2.44.0 / 2.44.1 feature end-to-end: where the **Mark as solution** button lives on agent + admin views (next to the timestamp on every customer-visible reply, absent on system events and on internal notes), how to unmark or reassign the flag (single solution per ticket; marking a different one replaces the previous), what changes visually when a comment is marked (green top-of-page alert with poster + timestamp, green left border on the marked row, **Solution** badge, brief :target highlight pulse, and force-show out of the "Show N older updates" collapser so the anchor never lands on a `display:none` element), the privacy reasoning behind excluding internal notes (the portal timeline filters out is_internal=1 rows, so allowing one would point the anchor at HTML the requester can't see *and* leak the existence of the note via the URL fragment) and the corresponding muted "internal — can't be the solution" hint that explains the absence in place, the requester-side experience on `/portal/tickets/{id}` (same green alert and badge, no Mark/Unmark control, default labels are **Answer** rather than **Solution** but renameable via the application label system at Settings → Application name & labels), the explicit non-coupling to ticket status (marking a solution does not auto-resolve — agents change status separately, which lets you flag a candidate fix on a ticket that's still **Waiting on Customer** without prematurely closing the SLA clock), and the recommended public-reply → mark → resolve → CSAT workflow. Subsequent sections renumbered 17–29 (was 16–28); appendix entries in the TOC bumped to 30–33; appendix labels A/B/C/D unchanged. No body cross-references used numbered anchors so no other internal link updates were needed.

---

## 2.44.1 &mdash; 2026-05-15

### Fixed
- **Explain why the "Mark as solution" button is missing on internal notes.** A ticket with only internal notes (or only system events — no public reply yet) showed no Mark button anywhere, which read as "the feature isn't deployed" when in fact the privacy gate from 2.44.0 was working correctly: internal notes are filtered out of the portal timeline, so flagging one would create a `Go to solution` anchor that lands on nothing for the requester. The agent and admin timelines now render a small muted hint in the slot the button would occupy on internal-note rows — `internal — can't be the solution` with a tooltip explaining that the requester can't see internal notes and a public reply is needed first. Portal view unchanged (it never sees internal notes). No change to the route gate or schema.

---

## 2.44.0 &mdash; 2026-05-15

### Added
- **Mark a comment as the ticket's solution + green "Go to solution" jump-link near the top of every ticket view.** Long-running tickets often end with the answer buried halfway down the timeline; now agents and admins see a small **Mark as solution** button on every customer-visible reply in the timeline, and once one is marked the ticket detail page (agent, admin, and the portal `/portal/tickets/{id}` requester view) renders a green alert at the top with a **Go to solution** button that anchors-and-scrolls to the marked comment, which itself gets a green left-border, "Solution" badge, and a brief :target highlight pulse so it's obvious where you landed. The marked comment is force-shown even when it would otherwise be hidden inside the "show N older updates" collapser, so the anchor never points at a `display:none` row. Internal notes are explicitly rejected by `POST /agent/tickets/{id}/solution` and `POST /admin/tickets/{id}/solution` — the portal timeline filters out `is_internal=1`, so allowing one would create a "Go to solution" link that anchors to nothing on the requester's view *and* leak the existence of the internal note via the URL fragment. Agents are still gated by the same group/confidential checks as every other ticket-action route (`_agentRequireTicketAccess`). Database side: migration 039 adds `tickets.solution_timeline_id INT UNSIGNED NULL` with `ON DELETE SET NULL` to `ticket_timeline.id`, so deleting the comment automatically clears the flag rather than leaving a dangling reference. Portal labels are wired through the `label()` helper (`portal.solution.available` / `portal.solution.go` / `portal.solution.badge`) so admins can rename "Answer" → "Solution" → "Resolution" to match their team's vocabulary.

---

## 2.43.4 &mdash; 2026-05-15

### Fixed
- **SMTP card no longer wipes itself when another Settings sub-form is posted to the wrong URL.** The SMTP form was the only card on `/admin/settings` whose POST handler lived at the bare page URL (`POST /admin/settings`) — every other card (ticket routing, email-to-ticket, Graph, test-email, run-reply-processor) already had its own scoped route. That meant any form on the page that submitted to `/admin/settings` instead of its intended sub-route would trip the SMTP handler, and because that handler reads every SMTP field with `$_POST['smtp_host'] ?? ''`, missing fields silently overwrote the saved values with empty strings — host, port, encryption, username, from-address, from-name all zeroed in one POST. Moved the SMTP card to its own `POST /admin/settings/email` route so the page is now consistent: each card owns its own URL, and a mis-targeted submit produces a 404 instead of corrupting unrelated settings.

---

## 2.43.3 &mdash; 2026-05-15

### Documentation
- **GETTING_STARTED.md &mdash; three gaps closed after a brand-new-user end-to-end test on a fresh DB.** Added a "Verifying a response was saved" subsection to §15 explaining that responses land in `csat_surveys.responded_at` and surface at **Reports → Satisfaction**, with the common-failure note that a mismatched `APP_URL` is what produces "the email went out but no one ever replied" (the survey link points at an unroutable URL). Documented the **per-ticket co-presence indicator** in §21 — the previously-undocumented "Marcus Lee is also viewing this ticket" banner, separate from the global online-presence list, polled from `/api/tickets/{id}/presence` every 15s, with 45-second pruning and portal-user exclusion. Added a "Required-field gotcha" callout to §16 explaining that custom fields marked Required are enforced on every creation path (portal, agent, REST, email-to-ticket, CSV import), aren't retroactive against pre-existing tickets, and that the **Staff-only** flag is the correct way to keep a field "required for agents but invisible on the portal" — overused Required fields are the most common cause of "Submit just reloads the page" complaints on the portal form.

---

## 2.43.2 &mdash; 2026-05-15

### Documentation
- **GETTING_STARTED.md §6 now has full Windows Task Scheduler instructions** in place of the previous one-paragraph "Windows alternative" stub. Three paths are documented: (A) the `schtasks /Create` one-liner from the admin page, (B) wrapper `.bat` files that redirect output to `storage/logs/` so the Cron Jobs status dashboard actually turns green, and (C) a click-through `taskschd.msc` walkthrough using **Create Task** with the right knobs (Run whether logged on or not, Run with highest privileges, sub-daily repeat with a typed "5 minutes" interval, **Start in** set so relative `.env`/`vendor` lookups work, **Do not start a new instance** so 5-minute jobs can't pile up). Also calls out the silent gotcha that the bare `schtasks` form leaves the dashboard sitting at "Not configured" forever because it doesn't redirect output, and gives `schtasks /Query` and **History** tab pointers for verification.

---

## 2.43.1 &mdash; 2026-05-15

### Fixed
- **Fresh installs can now create or edit ticket types again.** `database/schema.sql` was missing two columns (`ai_dup_check_enabled`, `ai_dup_threshold` on `ticket_types`) and the entire `ai_duplicate_classifications` audit table added by migration 036 — but the installer stamps all migrations as already-applied immediately after loading the schema, so the gap was never filled in. The result: any fresh install hit a fatal `Column 'ai_dup_check_enabled' not found` PDOException the moment an admin tried to add a ticket type. Schema.sql now matches the post-migration-038 canonical shape verified column-for-column against a long-lived production database.
- **Cron Jobs page emits real Windows Task Scheduler commands on Windows servers** instead of malformed Linux `*/5 * * * * php C:\xampp\…/script.php` lines that were neither valid cron syntax nor valid Windows command lines. The page detects the platform server-side and switches to ready-to-paste `schtasks /Create /TN "OpenHelpDesk …" /TR "'C:\xampp\php\php.exe' 'C:\…\script.php'" /SC … /F` commands, plus a one-shot combined block. Intro/help text, the per-card field label, and the combined-block heading all switch accordingly.
- **Group create/update form correctly treats `is_confidential=0` as off.** Previously the controller checked `isset($_POST['is_confidential'])` — fine for browser checkboxes (which simply omit unchecked fields), but any API client, CSV importer, or test harness that posts `is_confidential=0` explicitly was silently flipping the group into confidential mode, triggering all the membership-alert plumbing in Section 8.5. Switched to `!empty()` to match the ticket-types route's existing pattern.

### Documentation
- **GETTING_STARTED.md &mdash; rev pass after a brand-new-user install test on Windows/XAMPP.** Added a Section 7.0 covering the previously-undocumented **Organization Type** setting with its `k12_school`, `public_library`, `higher_education`, `government_*`, `hospital`, `corporation`, and `non_profit` options. Added an Option B `php -S` evaluation install path for laptop tyre-kicking (no Apache/IIS required). Documented the "switch the mode toggle first" prerequisite for per-location timezones (Section 7.4 and 8.1). Expanded the ticket types field reference (Section 8.2) to cover **AI duplicate check**, **Show to Location-Visibility users**, and **Sort order**; clarified that **Confidential** is locked until a Default Group is picked, and that **No Wrong Door** is stored as `ai_route_group` internally. Split the user-fields list (Section 8.7) into create-form fields vs edit-form fields. Reconciled the PHP-extension list between Sections 2 and 4 (both now include `fileinfo` and `zip`). Added a `ticket_priorities` table-name parenthetical to Section 8.3. Added XAMPP-on-Windows hints to Section 3.2: how to handle the antivirus-locked `composer install` retry, that `Include conf/extra/httpd-vhosts.conf` may need uncommenting, that editing `hosts` requires Administrator elevation, and that the bundled `public/.htaccess` covers the rewrite rules automatically. Same `.htaccess` note added to the LAMP path. Replaced the dead `https://github.com/yourorg/openhelpdesk.git` placeholder with a more obvious `REPLACE-WITH-YOUR-FORK` token. Rewrote Section 6's Windows alternative to match the new schtasks output and added a `schtasks /Query` verification recipe.
- **Renamed `claude.md` → `CLAUDE.md`** for the canonical capitalization. On a case-sensitive filesystem, fresh clones were silently shipping a lowercase `claude.md` that no other project documentation referenced.

---

## 2.43.0 &mdash; 2026-05-15

### Added
- **`GETTING_STARTED.md` &mdash; a comprehensive cradle-to-grave setup guide** covering prerequisites, server install on XAMPP / LAMP / IIS, the six-step web installer wizard, the cron job dashboard, every settings page (SMTP, branding, labels, business hours, holidays, locations, types, priorities, tags, groups, agent skills, users, SLA policies, ticket routing, inbound email via Microsoft Graph and IMAP, automations, time-based and manual escalations, stale-ticket notifications, AI triage with No-Wrong-Door routing, CSAT, custom form fields, ticket templates, the knowledge base, email templates, scheduled reports, notifications, 2FA, Microsoft 365 SSO, the portal, floor mode, the REST API, backups, importing data, maintenance, the danger zone), plus four appendices (cron reference, URL reference, environment variables, further reading). 1,700+ lines.
- **24 new screenshots** in `docs/screenshots/` captured against the bundled demo data: settings index, business hours, holidays, SLA policies, AI Classification, CSAT, escalation rules, cron jobs dashboard, label customisation, users, groups, ticket types, priorities, automations, locations, agent skills, the Microsoft Graph email-reply setup guide, SSO, danger zone, stale tickets, scheduled reports, email templates list, custom ticket fields builder, audit log. The guide embeds all of them inline.

---

## 2.42.11 &mdash; 2026-05-14

### Changed
- **Renamed the project from LocalDesk to OpenHelpDesk.** The GitHub repository is now `openhelpdesk`, and the brand name has been updated everywhere it was hardcoded: README, docs pages, the web installer, email templates, PWA manifest, page titles, and the default `app_name` / `branding_app_name` fallback strings throughout the codebase. The configurable branding setting is unaffected — existing installs keep their chosen name. The `localdesk` MySQL database name is unchanged. Every historical entry in this changelog has also been updated to read OpenHelpDesk.

---

## 2.42.10 &mdash; 2026-05-14

### Fixed
- **The header account dropdown is no longer covered by page content.** The navbar and Bootstrap's `.sticky-top` page elements (e.g. the Branding page's Live Preview card) shared the same stacking level (`z-index: 1020`), so later-rendered content painted over the header's dropdown menu. The sticky navbar now sits at `z-index: 1040` — above all page-level sticky content and the tour-resume pill, still below modals/offcanvas. Applied in both the `app` and `base` layouts.

---

## 2.42.9 &mdash; 2026-05-14

### Documentation
- **Replaced the one-line README intro with a three-paragraph project description** suitable for a GitHub release: core ticketing and lifecycle features, the broader feature set (KB, reports, email integration, SSO, REST API, security), and the optional AI-assisted triage layer (Anthropic/OpenAI skill routing, "No Wrong Door" group routing, sentiment-driven priority bumps, duplicate detection, and its fail-safe design).

---

## 2.42.8 &mdash; 2026-05-14

### Documentation
- **Expanded the README Screenshots section with seven more screenshots.** Public Help Center, a rendered KB article (with helpfulness voting), the KB article editor and email template editor (showing rich-text editing throughout), the Branding settings page (logo, colors, live preview), and the iPad "floor mode" card queue plus its streamlined ticket detail. Captured against the bundled demo data and stored in `docs/screenshots/`.

---

## 2.42.7 &mdash; 2026-05-14

### Bug fixes
- **`schema.sql` carried `AUTO_INCREMENT=N` counters on 28 of 49 tables**, leftovers from a database dump (e.g. `AUTO_INCREMENT=10214`). Fresh installs would start those tables' IDs at the dumped values instead of 1, breaking the seeders' hard-coded foreign-key references (locations 1/2/3, etc.) with FK constraint violations. Stripped every `AUTO_INCREMENT=N` table-option clause; column-level `AUTO_INCREMENT` definitions are untouched. This was the second half of the broken-manual-setup story alongside the migration-stamping fix in 2.42.6.

### Documentation
- **Added a Screenshots section to the README.** Eight screenshots of the running app (admin dashboard, ticket list, ticket detail, reports, form builder, knowledge base, end-user portal, sign-in) captured against the bundled demo data and stored in `docs/screenshots/`.
- Corrected the `/admin/kb` key-endpoint reference to `/admin/kb/articles` (the bare path 404s).

---

## 2.42.6 &mdash; 2026-05-14

### Bug fixes
- **Manual setup (`php database/seed.php`) crashed on first page load.** The seeder applied `schema.sql` (the full table snapshot) but never recorded the migrations as applied, so the auto-migrator in `src/bootstrap.php` replayed all 38 migrations against the already-complete schema on first request &mdash; some are deliberately destructive and would crash. `seed.php` and `seed_test_data.php` now stamp every committed migration into `schema_migrations` as a baseline immediately after applying the schema, matching what the web installer already does.

### Documentation
- **Corrected README inaccuracies.** `schema.sql` table count (35 &rarr; 49); `scripts/` listing now reflects the real layout (`scripts/admin/rescue.php` plus the five `process-*` cron scripts); `database/` listing now includes `migrate.php` and `seed_test_data.php`; `APP_TIMEZONE` default corrected to `UTC` to match `.env.example`; the PHP-extensions requirement list now matches what the installer checks; and the old single-job "SLA Cron Job" section is now a "Cron Jobs" section listing all seven background scripts.

---

## 2.42.5 &mdash; 2026-05-14

### Licensing
- **Released under the MIT License.** Added a top-level `LICENSE` file (MIT, &copy; 2026 Chris Jasztrab, Waterloo Public Library). The `README.md` licence section now states the project is open source and free to the world, and `openapi.json` / `composer.json` declare `MIT` instead of `Proprietary`.
- **Added credits & donation note.** `README.md` now records that OpenHelpDesk was vibe coded by Chris Jasztrab at the Waterloo Public Library, with a link to donate at <https://www.wpl.ca/your-library/donate/>.

---

## 2.42.4 &mdash; 2026-05-14

### Housekeeping (open-source prep)
- **Removed internal infrastructure from the tracked tree.** `scripts/deploy.py` hard-coded the production host IP, SSH user, and webroot path; it is now git-ignored and untracked (the working copy stays for local use). `.claude/settings.local.json` (a personal editor config) was likewise untracked &mdash; `.claude/*` was already ignored, it had just been committed before that rule existed.
- **Scrubbed organisation-identifying details from sample data.** All example/seed email addresses in `openapi.json`, `database/seed_test_data.php`, and `scripts/test-email-commands.php` now use `example.com`. The email-signature test fixtures, seed branch names and addresses, seed branding/footer strings, KB article titles, the OpenAPI contact name, and the `README.md` description/licence line no longer reference a specific organisation &mdash; they use generic placeholders (`Example Library`, `Anytown` addresses, `(555) 010-1234`). The backup-restore help page's example path is now `/var/www/localdesk` to match the product name.
- **Marked the demo seeders as dev-only.** `database/seed.php` and `database/seed_test_data.php` carry a prominent header warning that they drop all tables and insert well-known demo credentials, and must never be run against production &mdash; the real administrator account is created by the web installer (`public/install/`).

---

## 2.42.3 &mdash; 2026-05-13

### Bug fixes
- **Form Builder live preview still not opening (2.42.2 follow-up).** The previous attempt switched the iframe to `data-src` and copied it into `src` on toggle open, but that depended on the main builder IIFE running through to where the preview-toggle handler was bound &mdash; if anything earlier in the IIFE threw (e.g. a transient `Sortable` load issue or a missing modal element), the click handler was never wired and clicking "Live Preview" did nothing visible. **Fix:** the preview toggle now lives in its own separate IIFE before the main builder code, so it binds regardless of what happens later, and the iframe is rendered with a plain `src=` again (browsers will load it on page paint, even with the pane initially `display:none` &mdash; only rendering is suppressed). Every `getElementById` in the toggle is null-guarded; `localStorage` reads/writes are wrapped in `try/catch` for private-window safety. The main IIFE retains a `reloadPreview()` shim that delegates to the toggle's reload, so visibility-pill clicks and drag-reorders still refresh the preview when changes save.

---

## 2.42.2 &mdash; 2026-05-13

### Bug fixes
- **Form Builder's live preview never loaded.** The `<iframe id="previewFrame">` in [templates/pages/admin/workflows/ticket-fields.php](templates/pages/admin/workflows/ticket-fields.php) was rendered with both `src=` and `loading="lazy"`, while its parent pane started with `display: none` until the user clicked **Live Preview**. Chrome (and a few other browsers) decline to start loading a lazy iframe whose parent is `display: none`, and once the parent becomes visible the deferred load never fires for that DOM node &mdash; so the user clicked the toggle, saw the pane open, and got a blank rectangle. **Fix:** the iframe is now rendered with `data-src=` instead of `src=`, and the toggle's open path copies `data-src` into `src` on the first open (subsequent opens are no-ops; the reload button reassigns the existing src to force a refresh). Verified end-to-end against prod by forging a session, curling the builder page to confirm the iframe markup, then curling the embed URL itself to confirm headers + body were healthy &mdash; the page was rendering fine; the iframe just wasn't being asked to load. While here, encoded the `&` in the iframe URL as `&amp;` for HTML-attribute correctness.

---

## 2.42.1 &mdash; 2026-05-13

### Bug fixes
- **Form Builder page errored with "Invalid parameter number" instead of rendering.** The new builder route at [src/routes/admin.php](src/routes/admin.php) (`/admin/workflows/ticket-fields`) computed the "fields not on this type's form" list by passing `array_map(..., array_filter(...))` straight into `PDOStatement::execute()` for a `NOT IN (?, ?, ...)` query. `array_filter()` preserves the original (non-sequential) array keys, and `array_map()` carries them through, so the resulting positional-parameter array looked like `{"1": 40}` instead of `{"0": 40}` &mdash; which PDO rejects with `SQLSTATE[HY093]: Invalid parameter number: parameter was not defined`. **Fix:** wrap the chain in `array_values()` so the params are zero-indexed. The matching cleanup query on the field-update endpoint already used `array_values()` correctly. Confirmed via a probe script against prod's PHP runtime before re-deploying.

---

## 2.42.0 &mdash; 2026-05-13

### New features
- **Form Builder rewritten as a per-ticket-type editor.** Before, [templates/pages/admin/workflows/ticket-fields.php](templates/pages/admin/workflows/ticket-fields.php) presented a single global field list with a "filter by type" preview chip — so to hide priority on one type you had to leave the builder, edit the ticket type, come back; drag-and-drop while filtered didn't actually reorder per type (because `ticket_form_fields.sort_order` was one number for all types); and there was no visual indication on the builder when priority was hidden somewhere. **Fix:** new migration [database/migrations/038_form_builder_per_type_layout.php](database/migrations/038_form_builder_per_type_layout.php) introduces `ticket_type_form_layout (type_id, field_kind, field_key, sort_order, visibility, label_override)` — every ticket type now has its own authoritative form definition. The page picks a type from a left rail; the canvas shows that type's form. Drag handles reorder within that type (writes to `sort_order` for those rows only). Each field row has a clickable visibility pill that cycles **Required → Optional → Hidden** without leaving the page; hidden rows render with a hashed background so the state is obvious at a glance. Subject and Description are pinned at the top and locked to Required. **Add field** creates a new custom field and inserts a layout row; **Add existing field** picks from custom fields used on other types and shares them onto this one. Editing a custom field's definition (label, options, placeholder) opens a modal that surfaces every other ticket type the field appears on as checkboxes — toggle to share/unshare. Removing a row from a type's form preserves the field definition (and any saved values on past tickets); a separate "Delete field entirely" action soft-deletes it from every type. The live-preview pane in the right rail iframes `/portal/tickets/create?embed=1&type_id={N}` so admins see exactly what requesters will see for the type they're editing; preference is persisted in `localStorage`. Retired in the migration: `ticket_form_field_type_map`, `ticket_types.priority_visibility` (added briefly in v2.41), `ticket_form_fields.is_required` / `is_visible` / `sort_order`, and the global settings `sys_field_required_priority`, `sys_field_required_tags`, `sys_field_sort_order_*` — all of which became redundant once visibility/required/order moved per-type. Existing installs are auto-converted: the migration seeds every type's layout from the previous global sort order + the type's old field-type-map memberships + the v2.41 `priority_visibility` column, so behaviour is preserved end-to-end. The "Priority Field" dropdown that v2.41 added to [templates/pages/admin/types/form.php](templates/pages/admin/types/form.php) is replaced by an "Edit this type's form" button that deep-links to the builder for that type. New helpers in [src/helpers.php](src/helpers.php): `getFormLayoutForType()`, `resolveFieldVisibility()`, `seedDefaultLayoutForType()`, `getDefaultPriorityId()`. The portal / admin / agent ticket-create pages now render every field that appears on any type once, then JS reorders + toggles required/hidden as the user picks a type — driven by a per-type `formLayouts` map injected into the page. Server-side, [src/routes/portal.php](src/routes/portal.php) and [src/routes/admin.php](src/routes/admin.php) validate the submitted ticket against the chosen type's layout regardless of what the client did, so a hidden priority always backfills to the system default and a required custom field still fails closed even if the input was JS-stripped.

---

## 2.41.0 &mdash; 2026-05-13

### New features
- **Priority field can now be hidden per ticket type.** Before, the priority picker on the New Ticket form was governed by a single global setting (`sys_field_required_priority`) at [templates/pages/admin/workflows/ticket-fields.php](templates/pages/admin/workflows/ticket-fields.php) &mdash; either *Required* everywhere or *Optional* everywhere, no way to suppress it for ticket types where the requester shouldn't be picking severity (e.g. simple lost-and-found or "I need a key cut" requests, where every ticket of that type lands at the same urgency anyway). **Fix:** migration [database/migrations/037_ticket_types_priority_visibility.php](database/migrations/037_ticket_types_priority_visibility.php) adds `priority_visibility ENUM('inherit','required','optional','hidden') NOT NULL DEFAULT 'inherit'` to `ticket_types`. A new **Priority Field** dropdown on [templates/pages/admin/types/form.php](templates/pages/admin/types/form.php) lets admins override the global default per type with four states: *Use global setting* (the previous behaviour), *Required*, *Optional*, or *Hidden*. New helpers `resolvePriorityVisibility()`, `getDefaultPriorityId()`, and `getPriorityVisibilityMap()` in [src/helpers.php](src/helpers.php) centralise the resolution logic so portal, admin, and agent create forms behave identically. The portal form ([templates/pages/portal/tickets/create.php](templates/pages/portal/tickets/create.php)) and the staff form ([templates/pages/admin/tickets/create.php](templates/pages/admin/tickets/create.php)) now drive priority visibility from a `priorityVisibilityMap` injected into the page &mdash; on type-change the JS shows/hides `#priorityFieldWrap`, toggles the required attribute + asterisk + help text, and clears any submitted priority when the field becomes hidden so a stale value can't sneak through. **Server-side** ([src/routes/portal.php](src/routes/portal.php) and [src/routes/admin.php](src/routes/admin.php)) enforces the resolved visibility regardless of what the client posted: when the type's effective visibility is `hidden`, the submitted priority is discarded and replaced with the system default (lowest `sort_order`) so the ticket always has a priority on creation (SLA timers, escalation, list views all keep working) &mdash; agents can still change it after the fact. The system field edit modal at [templates/pages/admin/workflows/ticket-fields.php](templates/pages/admin/workflows/ticket-fields.php) shows a per-type-override hint only when editing the Priority field, pointing admins to Admin &rarr; Ticket Types for finer control. `'inherit'` is the install-wide default so existing installs see zero behaviour change until an admin opts a type in.

---

## 2.40.2 &mdash; 2026-05-12

### Bug fixes
- **Escalate button is now hidden on tickets whose type has no escalation path configured.** Before, [templates/pages/agent/tickets/view.php](templates/pages/agent/tickets/view.php) and [templates/pages/portal/tickets/view.php](templates/pages/portal/tickets/view.php) always rendered the **Escalate** button and merely disabled it with a tooltip when no path was wired up &mdash; which still drew the eye, invited hover-discovery, and suggested an action the user could never take. **Fix:** both views now gate the button on `$hasEscalationPath` (computed in [src/routes/agent.php](src/routes/agent.php) and [src/routes/portal.php](src/routes/portal.php) as `COUNT(*) > 0` against `ticket_escalation_steps` for the ticket's `type_id`), so the button is omitted entirely when the type has no steps &mdash; or when no type is set yet, since `$hasEscalationPath` is initialised to `false` in that case too. The "at the top of the chain" and "closed ticket" disabled states are preserved (path exists, but no `$nextEscalationStep` is reachable), since those are meaningful "you have an escalation lane, you just can't take it right now" signals rather than configuration gaps. The escalate modal is unchanged &mdash; it was already gated on `$nextEscalationStep`.

---

## 2.40.1 &mdash; 2026-05-11

### Bug fixes
- **Header search placeholder is now legible.** The "Search&hellip; ( / )" placeholder in the global search input was rendered at 45% white opacity against the dark indigo navbar gradient, which made it look washed out and easy to miss. **Fix:** bumped the placeholder colour in [templates/layouts/app.php](templates/layouts/app.php) from `rgba(255,255,255,.45)` to `rgba(255,255,255,.75)`. Typed text was already pure white via Bootstrap's `text-white` class on the input, so only the placeholder needed the boost.

---

## 2.40.0 &mdash; 2026-05-08

### New features
- **Unresolved Tickets report now paginates and supports drill-down filtering.** Before, [templates/pages/admin/reports/unresolved.php](templates/pages/admin/reports/unresolved.php) dumped *every* unresolved ticket onto a single page with no way to narrow the view &mdash; an instance with a few hundred unresolved tickets meant a slow render and an unusable wall of rows. The "By Status" and "By Age" tiles were also static read-outs, not navigation. **Fix:** the route at [src/routes/admin.php](src/routes/admin.php) (`/admin/reports/unresolved`) now (1) accepts `status`, `age` (0&ndash;4), `page`, and `per_page` (10/25/50/100, default 25) query params; (2) computes summary cards + tile counts from a single `SUM(CASE WHEN ...)` aggregate plus a `GROUP BY status` query against `tickets` (unfiltered &mdash; tiles are the navigation source, not filtered); (3) runs a separate `LIMIT/OFFSET`-paginated query against the same table for just the on-screen page; and (4) when called with `?ajax=1` returns only the new partial [templates/pages/admin/reports/_unresolved-table.php](templates/pages/admin/reports/_unresolved-table.php) instead of the full layout. The page template wraps the table card in `#unresolvedDrillRegion`; the inline JS intercepts tile clicks, the per-page `<select>`, and pagination links, fetches the partial, and swaps the region's innerHTML &mdash; with `history.pushState` so URLs stay bookmarkable and Back/Forward work via `popstate`. Status + age filters are combinable (e.g. `?status=open&age=4` for "open tickets older than 14 days"); clicking an already-active tile (or its `&times;` chip in the table header) toggles it off; "Clear all" wipes both at once. Tiles paint an active border + funnel icon when applied, and the region dims briefly during fetch so the swap doesn't feel ghosty. Aging buckets are computed in SQL with `TIMESTAMPDIFF(HOUR, created_at, NOW())` against fixed hour cutoffs (24 / 72 / 168 / 336) which matches the existing `< 1d / 1&ndash;3d / 3&ndash;7d / 7&ndash;14d / > 14d` labels exactly.

---

## 2.39.0 &mdash; 2026-05-08

### New features
- **Submit button now cycles playful "we are working on it" phrases while the AI and server do their thing.** Before, the only feedback while a ticket was being processed was a single "Checking for duplicates&hellip;" string &mdash; if the dup check + post-create AI tasks (skill classification, group routing, SLA setup) ran for more than a couple of seconds, the user had no idea anything was still happening. **Fix:** new shared partial [templates/partials/ticket-submit-progress.php](templates/partials/ticket-submit-progress.php) defines `window.startTicketSubmitProgress(btn)` which swaps the submit button for a spinner + rotating message every 1.8&nbsp;s, starting with "Checking for duplicates&hellip;" so the first frame still tells the truth, then shuffling through 13 phrases including "Phoning a friend&hellip;", "Flashing the bat signal&hellip;", "Putting your ticket in the paper shredder&hellip;", "Waking the help desk gnomes&hellip;", "Bribing the printer with cookies&hellip;", "Consulting the magic 8-ball&hellip;", "Sharpening every pencil in the building&hellip;", "Untangling the cables&hellip;", "Decoding ancient library scrolls&hellip;", "Polishing the crystal ball&hellip;", "Negotiating with the wifi router&hellip;", "Re-inking the stamp pad&hellip;", and "Looking under the rug&hellip;". **Wired in** all three create flows: [templates/pages/portal/tickets/create.php](templates/pages/portal/tickets/create.php), [templates/pages/admin/tickets/create.php](templates/pages/admin/tickets/create.php), [templates/pages/agent/floor.php](templates/pages/agent/floor.php), and the dup-preview modal's "Create anyway" button in [templates/partials/dup-preview-modal.php](templates/partials/dup-preview-modal.php). The helper returns a `stop()` so synchronous form-post flows let the cycling die naturally on navigation (no flicker), while async flows (floor mode dup-check &rarr; create chain, dup-warning re-render) explicitly stop the cycling the moment the server responds &mdash; so we never waste the user's time animating after the work is done.

---

## 2.38.3 &mdash; 2026-05-08

### Bug fixes
- **Dup-check modal now actually opens.** The "Click here to see this ticket" button was a silent no-op because the modal partial's inline script ran at parse time &mdash; before [templates/layouts/app.php](templates/layouts/app.php) loaded the Bootstrap bundle in the footer &mdash; so `typeof bootstrap === 'undefined'` and the IIFE returned without registering `window.openDupPreviewModal`. **Fix:** [templates/partials/dup-preview-modal.php](templates/partials/dup-preview-modal.php) now defers init to `DOMContentLoaded` (Bootstrap's parser-blocking script tag finishes by then) and falls back to a 50 ms retry if the script somehow runs even later. The button now opens the modal as designed.

---

## 2.38.2 &mdash; 2026-05-08

### New features
- **Friendlier dup-check warning + modal preview of the matched ticket.** The dup-check banner copy was reading like an internal error message ("Looks like someone may have already reported this") and the per-match action ("View this existing request") opened a new tab, pulling the requester out of their flow. **Fix:** new patron-friendly opening line &mdash; "Oops! It looks like someone else might have already submitted a ticket for this issue." &mdash; and every match now has a "Click here to see this ticket" button that opens a Bootstrap modal showing the existing ticket's subject, status, when it was opened, who reported it, the description, and the reply count, all without leaving the create form. The override button now reads "Create anyway &mdash; This is a Different Issue" everywhere (portal, agent, floor) and is rendered as a warning-coloured action so it doesn't look like the default. **Privacy:** new `getDupPreviewTicket()` helper in [src/helpers.php](src/helpers.php) re-validates each preview request server-side &mdash; non-confidential type, not merged-out, not closed, and (for portal users) at the user's branch &mdash; so the modal never leaks tickets the user wouldn't have been a candidate for, and portal users see only first name + last initial. **Backbone:** new shared partial [templates/partials/dup-preview-modal.php](templates/partials/dup-preview-modal.php) renders the modal once per page; the create form templates ([portal](templates/pages/portal/tickets/create.php), [agent/admin](templates/pages/admin/tickets/create.php), [floor](templates/pages/agent/floor.php)) include it with the right endpoint + view base. JSON endpoints `GET /portal/tickets/dup-preview` and `GET /agent/tickets/dup-preview` ([src/routes/portal.php](src/routes/portal.php), [src/routes/agent.php](src/routes/agent.php)) hand back the preview data. Floor mode's bottom sheet uses z-index 1070, so the partial pushes the modal + its backdrop to 1090/1085 on `shown.bs.modal` to render above the sheet. All user-facing copy is full sentences throughout the warning banner and modal.

---

## 2.38.1 &mdash; 2026-05-08

### New features
- **AI classification notes are now visible to admins in the ticket timeline.** AI-generated timeline entries (`ai_classified` skill verdicts, `ai_group_routed` "No Wrong Door" decisions, the new `ai_duplicate_warned` audit, plus any other system-generated internal entries) were always being written to `ticket_timeline` but the agent ticket view was filtering out everything `is_internal=1` with no `user_name` &mdash; meaning admins couldn't actually see the AI's reasoning, confidence, or sentiment. **Fix:** [templates/pages/agent/tickets/view.php](templates/pages/agent/tickets/view.php) now lets system entries through when `Auth::role() === 'admin'`, renders them with the existing `ld-timeline-system` blue stripe, and tags them with an "AI &middot; Admin only" badge so admins know what they're looking at and that requesters/agents don't see it. Action label + icon maps gained `ai_classified`, `ai_group_routed`, `ai_group_routing_skipped`, `ai_duplicate_warned` so each renders with a robot/signpost/files icon and a human-readable label instead of the generic "Ai Classified" fallback. Same label/icon map updates added to [templates/pages/admin/tickets/view.php](templates/pages/admin/tickets/view.php) which already renders system entries.
- **Dup-check overrides are now audited on the new ticket.** When the requester sees the AI duplicate warning and clicks _Submit anyway_, all three create flows ([src/routes/portal.php](src/routes/portal.php), [src/routes/admin.php](src/routes/admin.php), [src/routes/floor.php](src/routes/floor.php)) now write an `ai_duplicate_warned` timeline entry on the new ticket linking the matched ticket numbers + subjects, so admins can see overrides happened. Hidden field `_dup_matched_ids` is populated by JS on the override click; new `recordDupOverrideOnNewTicket()` helper in [src/helpers.php](src/helpers.php) writes the timeline entry and links the original `ai_duplicate_classifications` audit row to the new ticket id with `decision='suppressed'`.

---

## 2.38.0 &mdash; 2026-05-08

### New features
- **AI duplicate-ticket detection on submit.** Branches with multiple shifts kept filing the same ticket twice because staff don't routinely scan every open ticket before opening their own. **Fix:** new opt-in feature on `ticket_types`. When enabled, every Submit click runs the new ticket through AI against open non-confidential tickets at the same branch (last 14 days, capped at 30) and shows a warning card listing any matches above the type's confidence threshold; the user can click through to the existing ticket or click _Submit anyway_ to override. **Hard rule:** confidential ticket types are NEVER scanned and never appear as candidates &mdash; their bodies are not sent to a third-party provider, full stop. **Per-type config** lives in [templates/pages/admin/types/form.php](templates/pages/admin/types/form.php) (toggle + 0.50&ndash;0.99 threshold spinbox), persisted via the create/edit handlers in [src/routes/admin.php](src/routes/admin.php). **Wired into all three create flows:** portal ([templates/pages/portal/tickets/create.php](templates/pages/portal/tickets/create.php) + endpoint in [src/routes/portal.php](src/routes/portal.php)), agent/admin ticket form ([templates/pages/admin/tickets/create.php](templates/pages/admin/tickets/create.php) + endpoint in [src/routes/agent.php](src/routes/agent.php)), and floor mode quick-create ([templates/pages/agent/floor.php](templates/pages/agent/floor.php) reuses the same `/agent/tickets/check-duplicates` endpoint). All three soft-fail: provider timeout/error silently lets the ticket through, never blocking creation. **Backbone:** new `findDuplicates()` method on `AIClassifier` + Anthropic + OpenAI implementations in [src/AI.php](src/AI.php), and a shared `checkTicketDuplicates()` helper in [src/helpers.php](src/helpers.php) that gathers candidates, calls AI, filters by threshold, and audits to a new `ai_duplicate_classifications` table for tuning. **Migration 036** ([database/migrations/036_ai_duplicate_detection.php](database/migrations/036_ai_duplicate_detection.php)) adds `ai_dup_check_enabled` (default 0) + `ai_dup_threshold` (default 0.75) on `ticket_types` and creates the audit table. Admin form blocks the dup-check toggle when Confidential is checked.

---

## 2.37.0 — 2026-05-07

### New features
- **Floor mode now has help docs &mdash; agent side and a brand-new patron help section.** 2.36.x shipped the floor ticket detail views without any documentation; nothing in `/agent/help` or anywhere on the patron side mentioned floor mode existed. **Agent doc:** new page at `/agent/help/floor` ([templates/pages/agent/docs/floor.php](templates/pages/agent/docs/floor.php)) covering the queue + tabs (All open / Mine / Unassigned), the quick-create bottom sheet (subject + voice dictate, type, location, photo, barcode scan including the BarcodeDetector / Chrome-on-Android caveat), the floor ticket detail view (subject card, who-cards, collapsible description, Quick Actions, recent activity, add-a-note with photo + Internal toggle), the `?from=floor` full-detail escape hatch, permissions/visibility, and PWA-install tips. Wired into [src/routes/agent.php](src/routes/agent.php) by adding `floor` to `$validHelpPages` + the titles map; into [templates/partials/agent-help-nav.php](templates/partials/agent-help-nav.php) (sticky sidebar) and [templates/pages/agent/docs/index.php](templates/pages/agent/docs/index.php) (an extra card and four entries in the in-page search index). **Patron docs:** the portal had no docs section at all, so this stands one up: new routes `GET /portal/help` and `GET /portal/help/{page}` in [src/routes/portal.php](src/routes/portal.php), a `bi-question-circle` Help icon added to `portalSidebar()` in [src/helpers.php](src/helpers.php), and three new templates &mdash; [templates/partials/portal-help-nav.php](templates/partials/portal-help-nav.php) (sticky sidebar matching the agent partial), [templates/pages/portal/docs/index.php](templates/pages/portal/docs/index.php) (overview cards), and [templates/pages/portal/docs/floor.php](templates/pages/portal/docs/floor.php) (patron-friendly walkthrough: status pill labels, Helping you, What you reported, Updates, Reply, Close-this-request gating, and the Full request details escape hatch). Tone is patron-facing throughout &mdash; "request" not "ticket", plain-English status labels.

---

## 2.36.1 — 2026-05-07

### New features
- **Patron-side floor mode now has its own ticket detail view too.** 2.36.0 only built the simple detail view for the agent floor queue; the matching `/portal/floor` page (regular users tapping cards from a phone in the stacks) was still routing to the dense `/portal/tickets/{id}` page. **Fix:** new route `GET /portal/floor/tickets/{id}` rendering [templates/pages/portal/floor-ticket.php](templates/pages/portal/floor-ticket.php), with the same single-column layout as the agent version but tuned for patrons — patron-friendly status labels ("Submitted" / "We're working on it" / "Done"), a "Helping you" who-card showing the assigned agent, an Updates timeline that strips internal notes (patrons never see those), a Reply form with camera-capture photo input, and a Close-this-request button shown only when the user owns the ticket and it's not already closed/resolved. Companion `POST /portal/floor/tickets/{id}/action` handles `comment` (reuses `notifyCcUsers` + `notifyAgentRequesterReplied`) and `close` (writes the same internal audit-trail entry as the dense `/portal/tickets/{id}/close`). Access checks mirror the dense view exactly: own ticket OR merged-master OR location-visible non-confidential type. Cards in [templates/pages/portal/floor.php](templates/pages/portal/floor.php) now link here. The "Full request details" escape hatch goes to `/portal/tickets/{id}?from=floor` — the route handler ([src/routes/portal.php](src/routes/portal.php)) detects the flag and passes `embedMode=true` + `fromFloor=true`, so [templates/pages/portal/tickets/view.php](templates/pages/portal/tickets/view.php) drops the breadcrumbs (the layout already drops navbar/sidebar/banner) and overlays a fixed top-right ✕ pill that returns to `/portal/floor/tickets/{id}`. Same UX as the agent path, same chrome-stripping primitive.

---

## 2.36.0 — 2026-05-07

### New features
- **Floor mode now has its own ticket detail view.** Tapping a card in [/agent/floor](src/routes/floor.php) used to drop the user into the dense desktop ticket page ([templates/pages/agent/tickets/view.php](templates/pages/agent/tickets/view.php)) — sidebar, full edit form, dozens of small targets — which broke the touch-first design promise of the queue. **Fix:** new route `GET /agent/floor/tickets/{id}` rendering [templates/pages/agent/floor-ticket.php](templates/pages/agent/floor-ticket.php), a single-column card layout sized for thumbs: large subject + status/priority/type/location pills, two who-cards (Reported by, Assigned to), an auto-collapsing description, a Quick Actions grid (Claim/Release plus contextual In Progress / Pending / Resolve / Reopen — each a one-click form to `POST /agent/floor/tickets/{id}/action`), the most recent 8 timeline entries, and an Add-a-note form with camera-capture photo input and an "Internal only" toggle. The action endpoint reuses the existing helpers (`Sla::pause/resume`, `notifyAssignedAgent`, `notifyTicketCreator`, `notifyCcUsers`, `notifyWatchers`, `notifyAgentNoteAdded`, `notifyRequesterStatusChanged`, `processAtMentions`, `runAutomations`, `sendCsatSurvey`) so SLA timers, CSAT survey triggers, first-response marking, @-mention notifications, and auto-claim-on-reply all behave identically to the regular detail page. Floor cards in [templates/pages/agent/floor.php](templates/pages/agent/floor.php) now link here instead of `/agent/tickets/{id}`.
- **"Full ticket details" escape hatch with chrome-stripped, ✕-to-close return path.** The floor detail page links to `/agent/tickets/{id}?from=floor` for cases that need fields/merge/escalate the simple view doesn't expose. The route handler ([src/routes/agent.php](src/routes/agent.php) at `GET /agent/tickets/{id}`) detects `?from=floor` and passes `embedMode=true` plus `fromFloor=true` to `render()` — the layout's existing `embed-mode` body class hides navbar, sidebar, breadcrumbs, status banner, and PWA install prompt, and [templates/pages/agent/tickets/view.php](templates/pages/agent/tickets/view.php) overlays a fixed top-right ✕ pill that returns to `/agent/floor/tickets/{id}`. Breadcrumbs are also nulled in floor-entry mode (they were already CSS-hidden, but emptying the array is cleaner). Net effect: a staffer can drop into the dense view for one specific edit and bounce straight back to the simple view without ever seeing the desktop chrome.

---

## 2.35.4 — 2026-05-07

### Bug fixes
- **Regenerated [database/schema.sql](database/schema.sql) from the live database and switched the installer to a snapshot-based install instead of a migration replay.** 2.35.3 had the installer walk every file in `database/migrations/` after applying `schema.sql`, which exposed a latent problem: migration 006 isn't idempotent — it intentionally adds `api_tokens.token_hash`, backfills it from `api_tokens.token`, then DROPs `token`. Replaying it against a database where `token` was never present (because the regenerated `schema.sql` reflects the *post*-006 shape) crashes with `Unknown column 'token'`. **Fix:** `schema.sql` is now the canonical fresh-install snapshot — dumped from the live DB, comments stripped, every CREATE wrapped in `IF NOT EXISTS`, and the file wraps itself in `SET FOREIGN_KEY_CHECKS = 0/1`. The installer applies it in a single `PDO::exec()` (PDO+MySQL handles the multi-statement payload natively, which matters because one `COMMENT 'NULL = global; non-null = personal to that agent'` contains an embedded semicolon that broke the old naive splitter). Then it marks every committed migration as already-applied in `schema_migrations` *without* running them, so [database/migrate.php](database/migrate.php) on later upgrades correctly skips everything in the snapshot and only runs migrations added after the snapshot was cut. **Verified end-to-end** against a freshly created MySQL database: 48 tables, 399 columns, 88 FK constraints — exact match with the canonical DB; `migrate.php` immediately after install re-runs zero migrations.

---

## 2.35.3 — 2026-05-07

### Bug fixes
- **Web installer no longer produces a broken database on a fresh `/install/` run.** [public/install/index.php](public/install/index.php) used to read [database/schema.sql](database/schema.sql) and execute it directly, but `schema.sql` represents only the migration-001 baseline — every table added since (api_tokens, escalation_paths, login_attempts, ai_classifications, user_presence, status_banners, recurring_tickets, ai_group_classifications) plus a long list of column additions from migrations 011–035 lived in [database/migrations/](database/migrations/) and never made it into the fresh-install path. So a brand-new install booted with a half-built schema and would crash on first use of any of those features. **Fix:** the installer now runs the same migration loop as [database/migrate.php](database/migrate.php) — creates `schema_migrations`, walks every file in `database/migrations/` in order, executes each, and records it as applied. Migration 001 still applies the `schema.sql` baseline; migrations 002–035 are all idempotent (each guarded by an `information_schema` lookup) so they layer cleanly on top, and post-install runs of `migrate.php` correctly skip everything that's already recorded. **Cron list filled in:** the post-install "Next Steps" panel listed only 4 cron jobs, but the app's own [Admin → Settings → Cron Jobs](templates/pages/admin/settings/cron-jobs.php) page lists 6, plus `process-recurring-tickets.php` (added by migration 034) was missing from both. The installer panel now lists all 7 with their correct schedules, and the admin page also gained the recurring-tickets entry. **Storage dirs:** the installer only created `storage/attachments/`, but the app also writes to `storage/logs/`, `storage/imports/`, `storage/backups/`, and `storage/pwa/` — first-run permission errors on each of those would fail silently or surface as flash errors deep in admin flows. All five are now created at install time. **`.env.example` synced:** it was missing `APP_TIMEZONE`, `UPLOAD_MAX_SIZE`, and `UPLOAD_ALLOWED_TYPES` (which the installer already writes to `.env`); manual-setup users now get the same complete file.

---

## 2.35.2 — 2026-05-07

### Bug fixes
- **Fix 500 on /agent/floor caused by `extract($data)` clobbering the template path.** The floor route was passing `'view' => $view` (the All / Mine / Unassigned filter) to `render()`, but `render()` itself uses a `$view` parameter internally for the *template name* — `extract($data)` overwrote it, so PHP tried to `require .../templates/pages/all.php` (or `mine` / `unassigned`) which doesn't exist, and the request fataled before any output. Renamed the data key to `activeView` in [src/routes/floor.php](src/routes/floor.php) and the matching reads in [templates/pages/agent/floor.php](templates/pages/agent/floor.php). Added a comment at the call site warning that `view` is a reserved key for `render()` so future routes don't repeat the mistake. Affected only the agent-side floor route; portal floor never had a `view` filter and was always fine.

---

## 2.35.1 — 2026-05-07

### Bug fixes
- **Service worker no longer breaks page styling.** Shipping 2.34.0 caused every page (login, admin dashboard, agent UI) to render as unstyled raw HTML on browsers that had registered the new service worker — Bootstrap and Bootstrap-Icons were loading from `cdn.jsdelivr.net` but the SW was intercepting those cross-origin requests, calling `fetch()` from inside the worker, which is governed by the page's `connect-src` CSP directive (currently `'self' https://cdn.ckeditor.com` only — no jsdelivr). The cross-origin fetches inside the SW were therefore blocked, the worker returned an empty response, and the browser applied the empty stylesheet. Native `<link rel="stylesheet">` requests are governed by `style-src` (which DOES allow jsdelivr) and worked fine before the SW started intercepting. **Fix:** the SW now early-returns on any cross-origin request — those go to the browser's native loader, where the correct CSP directive applies. Cross-origin URLs were also dropped from the SW precache list. Same-origin caching (`/offline` shell, `/pwa/icons`, future own-origin CSS/JS) is unchanged. The fix self-deploys: the new sw.js content rotates the cache version, install + activate auto-skipWaiting + clients.claim, and the existing `controllerchange` listener reloads the page so users on a broken cached state recover on next page load. Affected versions were 2.34.0 (where the bug was introduced) and 2.35.0 (which inherited it). Anyone still seeing unstyled pages after 2.35.1 deploys should hard-reload (Ctrl+Shift+R) once to pick up the new worker; subsequent pages render normally.

---

## 2.35.0 — 2026-05-07

### Features
- **Floor mode: tablet-friendly card view of the ticket queue, with a single-screen quick-capture FAB.** Staff carrying an iPad through the building (printer jam in the makerspace, frozen public PC, lock-up at the self-checkout) and patrons reporting things from a phone in the stacks now have a touch-first surface that doesn't make them squint at a 12-column desktop table. New section in the agent / power-user / portal sidebars (key `floor`, `bi-grid-1x2` icon) above *Knowledge base* on every role: [/agent/floor](src/routes/floor.php) and [/portal/floor](src/routes/floor.php) ([src/helpers.php](src/helpers.php) `agentSidebar()` / `powerUserSidebar()` / `portalSidebar()`). **Agent view.** A pill-tab strip across the top — *All open / Mine / Unassigned* with live counts — drives the same group-restricted query that powers `/agent/tickets` (agents in groups only see their groups' tickets, agents in no groups see everything). Below the tabs, up to 100 tickets are rendered as a CSS-grid of cards (`auto-fill, minmax(280px, 1fr)`, single column on phones), each card 140 px+ tall with a coloured priority left rail, the subject 3-line-clamped at 1 rem, a status pill (open / in-progress / pending in distinct hues, dark-mode variants included), the type chip with the type's own colour, a location chip, and either an *assigned-agent* green pill or an *Unassigned* red pill so the next person to grab a ticket can see at a glance what's waiting. Tap-targets are 44 pt+ everywhere; pressing a card scales it 2 % for haptic feedback before navigating to `/agent/tickets/{id}`. **Portal view.** Patrons see a simpler version — a big gradient *New help request* CTA card linking to the existing rich create form (which has all the validation and custom-field handling), and below it a card for each of their last 50 tickets ordered open-first then most-recently-updated, so they can confirm something they reported earlier was acted on. **Floating action button + bottom-sheet quick-capture** ([templates/pages/agent/floor.php](templates/pages/agent/floor.php)). Bottom-right circular `+` button (60 px, brand-coloured shadow, safe-area-inset-aware so it doesn't disappear under iOS home-indicator gestures); tapping it slides a bottom-sheet up over a backdrop with three inputs and three media buttons. **Subject** with a microphone button on the right that uses the Web Speech API (`webkitSpeechRecognition`) to dictate — useful when staff are carrying a laptop and supplies in the other hand; the mic pulses red while listening and the transcript is appended (or capitalised when the field is empty) to whatever's already typed. **Type** dropdown (required, `is_confidential = 0` types only — so nothing private slips out of the streamlined path) and **Location** dropdown (defaults to "My branch" which falls back to the agent's profile location server-side). **Take photo** opens the camera directly via `<input type="file" accept="image/*" capture="environment" multiple>` — every modern phone respects that and goes straight to the rear camera, bypassing the gallery — captured shots are previewed as 60 × 60 thumbs you can tap × to remove before submit. **Scan barcode** uses the native Chromium `BarcodeDetector` API to read code_128 / EAN / QR / code_39 off the rear camera, then auto-prepends `[Asset {code}]` to the subject; on browsers without the API (iOS Safari today) it shows a friendly fallback message instead of failing silently. The form posts as multipart/form-data to a new [POST /agent/floor/quick-create](src/routes/floor.php) endpoint (`X-CSRF-TOKEN` header) which inserts the ticket through the **same backbone the regular create paths use** — `resolveTicketGroup()` picks the destination group from the type, `runPostTicketCreateHooks()` runs the AI No-Wrong-Door routing + skill classification + auto-assign, `saveAttachments()` persists the photo, `notifyRequesterTicketCreated()` / `notifyGroupMembers()` / `notifyAssignedAgent()` fire the right inboxes, and `Sla::initializeForTicket()` starts the timers — so a ticket born in floor mode is indistinguishable from one filed through the full form once it's in the system. **Custom field validation is intentionally skipped** in the quick-capture path so a tap-it-and-go floor capture isn't blocked by a 14-field type schema; the agent fleshes out the missing context on the standard ticket detail page after the ticket is created (the JSON response includes `redirect_url` to that page). The first timeline entry is recorded as *"Ticket created from floor mode quick-capture."* so the audit trail makes the origin obvious. **Defaults.** Subject ≥ 3 chars; description defaults to subject when blank (floor capture often only knows the headline); priority defaults to the lowest-sort_order priority; type falls back to the first non-confidential type if not picked. **Empty state.** When the queue is empty for the selected tab, staff get a green-check empty state ("Nothing in this queue right now. Tap the + button if you spot something on the floor.") instead of a blank page; patrons get an analogous "No requests yet" empty state. **Manifest shortcuts** (already in 2.34.0) now link to a real destination — long-press the home-screen icon and *Floor mode* / *New ticket* / *My tickets* are one tap away.

---

## 2.34.0 — 2026-05-07

### Features
- **Progressive Web App: install to home screen, offline shell, push-ready service worker.** Staff working off iPads on the floor (and patrons on phones at home) can now install the helpdesk as a real app — full-screen, app icon on the home screen, instant cold-start, last-visited screen still loads when wifi blips. The whole layer auto-themes from the existing Branding settings, so there's nothing to configure beyond uploading a logo. **Manifest.** A new dynamic Web App Manifest is served at [/manifest.webmanifest](public/index.php) ([src/routes/pwa.php](src/routes/pwa.php)) — name + short_name from `branding_app_name`, theme_color from `branding_navbar_start`, background_color from the app's slate-50 chrome, plus eight `icons[]` entries (192/256/384/512 in both `purpose: any` and `purpose: maskable` for Android adaptive masking) and three `shortcuts[]` (Floor mode, New ticket, My tickets) so a long-press on the home-screen icon shows a jump-list. **Icon generation.** A new [src/PWA.php](src/PWA.php) helper class renders icons on-demand using GD: rounded-square brand-colour background (radius 22% for iOS aesthetic) with the configured raster logo composited dead-centre at 80%-of-canvas, falling back to a single white-letter glyph (first character of `branding_app_name` rendered in DejaVu/Liberation/Arial Bold via `imagettftext`, or built-in font 5 as a last resort) when no logo is set or the upload is an SVG that GD can't ingest. Maskable variants drop the corner radius and tighten the safe-zone padding to ~80% so Android's circular/rounded mask doesn't clip the foreground. Generated PNGs are cached at `storage/pwa/icon-{key}-{size}{s|m}.png` keyed by a 10-char hash of (logo mtime, brand colour, app-name initial, app version) — branding edits invalidate cleanly without manual cache busting. **Service worker.** A version-baked SW is served at [/sw.js](src/routes/pwa.php) with `Service-Worker-Allowed: /` and the cache version stamped from `APP_VERSION` plus a hash of the manifest payload, so every deploy auto-rotates caches and a branding tweak (which doesn't bump the version number) still busts. Strategy is purposely conservative for a stateful CSRF-bearing PHP app: GETs only (POSTs always pass through so ticket replies and comment submits keep their CSRF + flash flow), navigations use **network-first → last-cached HTML → /offline shell** so staff keep reading the screen they had open through a wifi drop, static assets (CDN Bootstrap + Bootstrap Icons + own-origin CSS/JS/fonts/images, plus `/pwa/` icons) use **stale-while-revalidate**, and a hard-coded uncacheable list (`/api/`, `/admin/`, `/sso/`, `/login`, `/2fa`, attachment downloads, the SW itself) skips the worker entirely so per-user state never crosses sessions on shared tablets. **Update flow.** `controllerchange` reloads the page when a new SW takes control, and a "New version ready / Reload" toast appears at the top of the page when an updated SW is waiting — tapping Reload posts `SKIP_WAITING` so users get the fresh assets without having to fully close the app. **Offline shell.** [/offline](src/routes/pwa.php) renders a self-contained brand-coloured page (no DB calls, no auth, no flash partial) with a "Try again" button — that's what the SW shows when both the network and the runtime cache have nothing for a navigation. **Install banner.** A new partial at [templates/partials/pwa-install.php](templates/partials/pwa-install.php) captures `beforeinstallprompt` on Chromium/Edge/Android and renders a small bottom-centre dismissible banner with a single "Install" button that fires the native prompt; on iOS Safari (which has no install API) it shows a "Tap Share ↗, then *Add to Home Screen*" hint instead, since iOS PWA install is still a manual gesture. The banner self-suppresses for 14 days after dismissal (`localStorage` keyed by `ld_pwa_install_dismissed_v1`) and never appears in standalone display mode (i.e. when already installed). **Wired into every layout.** `<link rel="manifest">`, `theme-color` meta, `apple-touch-icon`, and the iOS `apple-mobile-web-app-*` capability metas are set via [templates/partials/pwa-head.php](templates/partials/pwa-head.php), included in all four layouts ([app.php](templates/layouts/app.php), [base.php](templates/layouts/base.php), [auth.php](templates/layouts/auth.php), [public.php](templates/layouts/public.php)), so the install + theming work no matter whether the user lands on the patron portal, the agent UI, or the public help center first. The install banner partial is wired in the same four places, except `embed-mode` (form-builder live preview iframe) where it would clutter the preview. **CSP.** No new directives needed — the existing `default-src 'self'` covers manifest, icon, and SW fetches; the SW does its own out-of-band fetches (cross-origin caching of CDN Bootstrap is fine — service workers run outside the page CSP). **Note for prod.** This release also depends on `php-gd` being installed on the prod box, which the user installed today (2026-05-07). Without GD the icon route writes a 1×1 transparent PNG instead of the branded glyph — the install still works, just with a blank icon.

---

## 2.33.1 — 2026-05-07

### Documentation
- **In-app docs catch up to the "No Wrong Door" AI group routing shipped in 2.33.0.** A new *No Wrong Door — Let AI Pick the Group* card is added to [Admin → Docs → AI Classification](/admin/docs/ai#no-wrong-door) ([templates/pages/admin/docs/ai.php](templates/pages/admin/docs/ai.php)) covering when to use it, the five-step setup walk-through (tick the type flag, set the Default Group as fallback queue, sharpen group descriptions, confidence threshold behaviour, downstream skill classifier), the audit trail badges shown on the ticket detail page (*Routed to X* / *No confident match* / *Suggested X (below threshold)*), and a tuning playbook for reading the audit to fix vague or overlapping group descriptions. Seven new search-index entries are added to [templates/pages/admin/docs/index.php](templates/pages/admin/docs/index.php) so admins typing "no wrong door", "ai group routing", "don't know who handles this", "ai_route_group", "ai group routing card", "group description ai signal", or "fallback queue default group" surface the new card. [DOCS.md](DOCS.md) gets a new *Let AI route this to the best group ("No Wrong Door")* row in the *Ticket Types* field reference, plus updated copy on the *Default Group* and *Confidential* rows explaining how each interacts with the new flag (Default Group doubles as fallback queue; Confidential is mutually exclusive because confidential bodies must never reach a third-party provider).

---

## 2.33.0 — 2026-05-06

### Features
- **"No Wrong Door" AI group routing for ticket types.** Some patron requests don't fit a clear category — the patron knows they need help but doesn't know which team handles it. A ticket type can now be flagged with **Let AI route this to the best group** (new `ai_route_group` column on `ticket_types`, migration **035** at [database/migrations/035_ai_group_routing.php](database/migrations/035_ai_group_routing.php)) and on submit the AI picks the most appropriate group from every non-confidential group, using each group's `description` to ground the choice. The flag surfaces as a checkbox below *Confidential* on [Admin → Settings → Ticket Types → Edit](/admin/types) ([templates/pages/admin/types/form.php](templates/pages/admin/types/form.php)) with a `bi-signpost-split` icon and copy explaining that AI uses group descriptions and that the type's *Default Group* doubles as the fallback queue when AI isn't confident — so admins should make that group their literal "No Wrong Door" team. The two flags are mutually exclusive (a confidential ticket body must never reach a third-party provider) and the form has client-side JS to enforce that, plus a server-side guard in both POST handlers ([src/routes/admin.php](src/routes/admin.php)). The types list at [/admin/types](/admin/types) gains a small `bi-signpost-split` indicator beside any AI-routed type so admins can spot them at a glance ([templates/pages/admin/types/index.php](templates/pages/admin/types/index.php)). **Routing flow.** A new `aiRouteTicketToGroup()` helper ([src/helpers.php](src/helpers.php)) is wired in as the FIRST step of `runPostTicketCreateHooks()` — it runs before the existing skill classification so when AI re-routes, the skill candidate list is filtered against the destination group's skills, not the launching queue's. The helper soft-fails on every error path (AI off, type not flagged, no candidate groups, provider error, ticket type confidential) and never blocks ticket creation. Confidence is checked against the existing `ai_confidence_threshold` setting; if the AI's pick clears the bar AND it differs from the current group, `tickets.group_id` is updated and an `ai_group_routed` timeline entry records the rationale. Below-threshold suggestions, "no confident match" responses, and same-group confirmations are all logged as `ai_group_routing_skipped` instead so admins can see exactly why a ticket stayed put. **Audit trail.** Every AI call (apply or skip) writes a row to a new `ai_group_classifications` table — provider, model, candidate group ids considered, suggested group, applied group (NULL when skipped), confidence, reasoning, full raw provider output, latency and token counts. `tickets.ai_group_classification_id` points at the latest record, and the admin ticket detail page renders a new *AI Group Routing* card in the sidebar above the existing AI Classification card ([templates/pages/admin/tickets/view.php](templates/pages/admin/tickets/view.php)) showing outcome (routed / no match / suggested-but-below-threshold), AI reasoning, provider and latency. **Provider abstraction.** `AIClassifier` interface in [src/AI.php](src/AI.php) gains a `classifyGroup()` method with shared prompt + parser in `BaseAIClassifier` (analogous to the existing skill classification) and per-provider HTTP implementations in `AnthropicClassifier` and `OpenAIClassifier` — so a future provider swap stays a one-line settings change. Candidate groups passed to the model are filtered to those with a non-empty description; a group with no description gives the AI nothing to route on, so excluding them keeps the prompt clean and the routing behaviour predictable.

---

## 2.32.0 — 2026-05-06

### Features
- **Recurring / preventive-maintenance tickets.** Monthly toner audits, quarterly HVAC inspections, annual fire-marshal walkthroughs, weekly website-content sweeps — work that has historically lived in someone's Outlook calendar and frequently slipped now lives in [Admin → Recurring Tickets](/admin/recurring-tickets) and auto-creates on cadence. New section in the admin sidebar between *Tickets* and *Users* (`bi-arrow-clockwise` icon, key `recurring-tickets`) opens a list view showing schedule name, cadence pill, type, group/assignee, next-run date (red + warning icon when overdue), last-run with a clickable link to the ticket the schedule spawned, total run count, an On/Off toggle button, and per-row *Run now / Edit / Delete* actions. The compose form has three cards — *Schedule Details* (internal name + notes), *How often should this ticket be created?* (frequency picker + interval + start-date + frequency-specific anchors that show/hide live with a JS-rendered "Every 3 months on the 15th." plain-language summary), and *Ticket That Will Be Created* (subject, description, type, priority, location, group, assignee, due-date offset in days, requester). **Schema.** Migration 034 ([database/migrations/034_recurring_tickets.php](database/migrations/034_recurring_tickets.php)) adds a single `recurring_tickets` table holding the ticket-mint payload (`subject`, `body`, `type_id`, `priority_id`, `location_id`, `assigned_to`, `group_id`, `requester_id`, `due_date_offset_days`), the cadence (`frequency` ENUM `daily`/`weekly`/`monthly`/`yearly`/`custom`, `interval_value`, `day_of_week`, `day_of_month`, `month_of_year`, `start_date`, `next_run_at`), the audit pair (`last_run_at`, `last_ticket_id`, `run_count`), and the lifecycle flags (`is_active`, `created_by`, `updated_by`, `created_at`, `updated_at`). All FKs `ON DELETE SET NULL` so deleting a referenced type/group/user doesn't take the schedule with it. An `idx_recurring_due (is_active, next_run_at)` composite index makes the cron's due-check cheap. **Cadence math.** A new helper class [src/RecurringTickets.php](src/RecurringTickets.php) (registered in [src/bootstrap.php](src/bootstrap.php)) owns `computeNextRun()`, which anchors firing to the configured day-of-week / day-of-month / month-of-year before stepping forward — so a "monthly on the 15th" schedule lands on the 15th every month even if the cron processes it three days late, and a "every 3 months" quarterly schedule preserves the anchor through year-rollover. Day-of-month is clamped to the target month's actual length (Feb 30 → Feb 28/29, never overflows into March). Default firing time is 06:00 local so the ticket is in the agent's inbox before they sit down without colliding with overnight batch jobs (escalations, stale-ticket sweeps). **Minting.** `RecurringTickets::mintTicket()` builds the ticket through the same path as a hand-filed one — `resolveTicketGroup()` so a stale type-default doesn't strand the ticket, `runPostTicketCreateHooks()` so AI classification + automations + auto-assign all run, `Sla::initializeForTicket()` so SLA timers start, plus `notifyGroupMembers()` / `notifyRequesterTicketCreated()` / `notifyAssignedAgent()` so the right inboxes fire. Each minted ticket gets a timeline entry *"Ticket auto-created by recurring schedule: {name}."* so the audit trail is legible — anyone reading the ticket can see "this is the Q2 HVAC inspection, not a one-off." **Cron.** New script [scripts/process-recurring-tickets.php](scripts/process-recurring-tickets.php) walks every active schedule whose `next_run_at <= NOW()` and mints + advances; designed to run every 15 minutes (`*/15 * * * * php /path/to/app/scripts/process-recurring-tickets.php >> /path/to/app/storage/logs/recurring-tickets.log 2>&1`). Missed-tick-safe by design: if the cron pauses for a day, each schedule fires *once* on the next pass and is then re-scheduled forward — it does NOT back-fill a year of "monthly toner audit" tickets if the box was offline. Schedules whose requester has been deleted (and whose `created_by` fallback is also gone) are auto-disabled rather than tight-looping on a permanently broken row. **Run now.** Each schedule has a *Run now* button (lightning-bolt icon) that mints a ticket immediately without resetting the cadence — useful for testing a brand-new schedule before its first scheduled run, or for an ad-hoc preventive-maintenance pass between scheduled cycles. **Edit semantics.** When the cadence inputs change on edit (frequency / interval / anchors / start-date), `next_run_at` is recomputed from now so a schedule moving from monthly-15th to monthly-1st actually starts firing on the 1st instead of waiting out the old slot.

---

## 2.31.0 — 2026-05-06

### Features
- **Pinned status banner / incident notice on the portal.** When the Wi-Fi at a branch goes down or the ILS is having a slow morning we historically take 12 duplicate tickets in 20 minutes; staff now post a pinned banner once and every help-page visitor sees the heads-up before they click *New Help Request*. New section at [Agent → Status Banners](/agent/banners) (megaphone icon in the agent sidebar, also wired into the `power_user` sidebar). Any agent — not just admins — can post, edit, clear, re-post, or permanently delete a banner; gate is `requireRole('agent', 'admin', 'power_user')` on the seven new routes in [src/routes/agent.php](src/routes/agent.php) (`/agent/banners`, `/agent/banners/create`, `/agent/banners/{id}/edit`, `/agent/banners/{id}/clear`, `/agent/banners/{id}/reactivate`, `/agent/banners/{id}/delete`). The compose form uses the same CKEditor 5 build that powers KB articles ([templates/pages/agent/banners/form.php](templates/pages/agent/banners/form.php)) — a slimmer toolbar (heading, bold/italic/underline, lists, link, blockquote, undo/redo) but the same import-map, dark-mode CSS, hidden-input round-trip and submit-time empty-body guard so authors can paste rich text, add a link to the ETA page, etc. **Severity** is a three-card radio strip (info / warning / critical) that drives the banner's Bootstrap colour and `bi-info-circle` / `bi-exclamation-triangle` / `bi-exclamation-octagon` icon, plus the ARIA live-region role (critical → `role="alert" aria-live="assertive"`, others → `role="status" aria-live="polite"`). Optional **Title** sits in bold above the body. **Branch scope** is a `locations` dropdown — leave blank for *All branches*, or pick a single branch and the banner is only shown to portal users whose `users.location_id` matches; agents/admins/power-users always see every active banner regardless of their own branch so they don't miss what's posted. **Show from** / **Auto-hide at** are optional `datetime-local` inputs that set the visibility window — both blank means *show now until cleared*, and *Auto-hide at* lets staff post a maintenance notice ahead of time and stop worrying about clearing it. **Display.** A new partial at [templates/partials/status-banner.php](templates/partials/status-banner.php) is included near the top of every authenticated `app`-layout page (between the flash-message stack and the page content) and renders all currently-active banners as a stack of Bootstrap alerts; the partial is suppressed under `?embed=1` so it doesn't show in the form-builder live-preview iframe. Each banner shows the title, the rich-text body, and a small footer line with the branch (or *All branches*), the auto-hide time if set, and — for staff only — the *Posted by Name · time* byline. Staff get inline *Edit* and *Clear* buttons on every banner so they can act on an incident without navigating away from the ticket they're working on. Patron-side dismissal is client-side only via `localStorage`, keyed by `ld_banner_{id}_{updated_at_unix}` — editing a banner bumps `updated_at` and the dismissal key changes, so the updated copy re-surfaces for users who'd hidden the previous version. **Schema.** Migration 033 ([database/migrations/033_status_banners.php](database/migrations/033_status_banners.php)) adds a single `status_banners` table — `title VARCHAR(255) NULL`, `body_html MEDIUMTEXT NOT NULL`, `severity ENUM('info','warning','critical')`, `location_id INT UNSIGNED NULL` (FK to `locations`, `ON DELETE SET NULL`), `is_active TINYINT(1)` (drives the *Clear* soft-delete so cleared incidents stay around for audit), `starts_at DATETIME NULL`, `expires_at DATETIME NULL`, `created_by` / `updated_by` (FK to `users`, `ON DELETE SET NULL`), the standard `created_at`/`updated_at` pair, and two indexes — `(is_active, expires_at, starts_at)` for the visibility query and `(location_id)` for the join. **Helpers** in [src/helpers.php](src/helpers.php): `getActiveBanners()` runs the visibility query with role-aware location scoping (lazily hydrates `$_SESSION['user']['location_id']` on cold sessions) and orders critical → warning → info, then most-recent first; `sanitizeBannerHtml()` strips `<script>`, `<style>`, `on*=` event-handler attributes and `javascript:` URLs as the defence-in-depth line — CKEditor itself never produces those tokens, but the route accepts raw POST so the sanitizer is the second wall. The management list at [/agent/banners](/agent/banners) shows every banner ever posted with a *Live / Scheduled / Expired / Cleared* status pill (computed from `is_active`, `starts_at`, `expires_at`), the severity badge, a 140-char strip-tags preview of the body, the branch, the visibility window, and the *Posted by · timestamp* — plus per-row Edit / Clear / Re-post / Delete actions. Trust model matches KB articles: agents are authenticated staff, raw HTML is stored, the sanitizer guards the obvious script-injection vectors and the alert renders without `e()` so author formatting (links, lists, headings, bold) actually renders.
- **Status Banners entry added to the agent and power-user sidebars** ([src/helpers.php](src/helpers.php)): `bi-megaphone` icon between *Canned Responses* and *Help*, key `banners`, points at `/agent/banners`. No change to the portal sidebar — patrons see the banner inline at the top of every help page, not as a nav destination, since the whole point is that they can't miss it.

---

## 2.29.1 — 2026-05-06

### Fixes
- **Tightened the Classification & Details grid on the staff Create Ticket page.** On wide screens the form sits in a `col-lg-8` shell (~880&nbsp;px) and the five system fields in [templates/pages/admin/tickets/create.php](templates/pages/admin/tickets/create.php) — Type, Status, Priority, Location, Due Date — were each `col-md-6`, so they laid out as Type|Status, Priority|*empty*, Location|Due Date with a half-row of dead space next to Priority and 420&nbsp;px-wide dropdowns holding single-word values like *Open* or *Medium*. Each field is now `col-md-6 col-lg-4` so on `lg+` screens they pack three-per-row (Type|Status|Priority, Location|Due Date|empty) — the leftover gap moves to the end of the section after Due Date where it doesn't break visual rhythm, dropdowns shrink to ~280&nbsp;px which suits the content, and the medium-screen layout is unchanged.

---

## 2.29.0 — 2026-05-06

### Features
- **"On behalf of" ticket creation, available to agents.** Public-library staff routinely phone the helpdesk; the staff *Create Ticket* page now lets you file the ticket directly for the caller so the right person owns it from the start. The **On Behalf Of** picker — already present on [Admin → Tickets → Create Ticket](/admin/tickets/create) — is now also rendered on [Agent → Tickets → Create Ticket](/agent/tickets/create) (the `<?php if (!$isAgent) ?>` guard around the field, label and live-search JS in [templates/pages/admin/tickets/create.php](templates/pages/admin/tickets/create.php) is removed; the section gets a `bi-telephone` icon and longer help text spelling out the requester-vs-submitter split). The POST handler at [/admin/tickets/create](src/routes/admin.php#L3324) used to accept `on_behalf_of_id` only when `Auth::role() === 'admin'`; that gate is widened to `admin`/`agent`/`power_user`, and a same-user guard short-circuits the picker when an agent searches for themselves so there's no spurious audit trail. **Audit trail.** Pre-2.29 the feature reused `tickets.created_by` to mean "requester" — the agent's identity was lost at insert time. Migration **032** ([database/migrations/032_tickets_submitted_by.php](database/migrations/032_tickets_submitted_by.php)) adds `tickets.submitted_by INT UNSIGNED NULL` (FK to `users.id`, `ON DELETE SET NULL`, indexed) so the actual submitter is captured whenever it differs from the requester; NULL means "self-submission" and existing tickets need no back-fill. The INSERT in the POST handler now writes both columns; the timeline's first `created` entry reads *"Ticket filed by [staff] on behalf of [requester]."* when delegating, falling back to the original *"Ticket created by [staff]."* for self-submission. **Visibility.** The two ticket-detail SELECTs ([src/routes/agent.php#L806](src/routes/agent.php#L806) and [src/routes/admin.php#L3617](src/routes/admin.php#L3617)) gain a `LEFT JOIN users s ON t.submitted_by = s.id` to surface `submitter_name`/`submitter_email`; the agent ticket sidebar at [templates/pages/agent/tickets/view.php](templates/pages/agent/tickets/view.php) renders a small *"Filed by [staff] on their behalf"* hint with a `bi-telephone-forward` icon under *Created By* whenever `submitted_by` is set. Email routing is unchanged: `notifyRequesterTicketCreated()` already joins on `created_by` so the confirmation goes to the requester's inbox; CSAT, watchers and escalations all key off the requester for the same reason. The submitter pill is agent/admin-only; end users see the ticket as theirs in the portal with no reference to who filed it. Tests: the agent-side `test_agent_create_form_does_not_show_on_behalf_of` assertion is flipped to `test_agent_create_form_shows_on_behalf_of`, and a new `test_agent_on_behalf_of_post_records_submitter_and_requester` round-trips a POST with `on_behalf_of_id` and asserts that the resulting ticket has `created_by = requester` and `submitted_by != requester`.

### Documentation
- **In-app docs catch up to the on-behalf-of work.** A new *Filing a Ticket On Behalf Of Someone Else* card is added to [templates/pages/admin/docs/tickets.php](templates/pages/admin/docs/tickets.php) (anchor `#on-behalf-of`) covering: the picker UI on both admin and agent create pages, a step-by-step walkthrough, the requester-vs-submitter data model with concrete consequences for ticket ownership / portal visibility / email routing / CSAT, the same-user-as-blank short-circuit, and the prerequisite that the requester must already have a user account (with a pointer to [Admin → Users → Add User](/admin/users/create) for new callers). The existing *Creating Tickets* card is reworded to point at both staff create-ticket entry points instead of just admin. Five new search-index entries are added to [templates/pages/admin/docs/index.php](templates/pages/admin/docs/index.php) so admins typing "on behalf of", "phone in", "submitter", "filed by", or "submitted_by" surface the new card. The user-facing [DOCS.md](DOCS.md) gets the same section under *Agent Panel* between *First Response Tracking* and *Admin Area*.

---

## 2.28.1 — 2026-05-06

### Documentation
- **In-app docs caught up to the form-builder and direct-link work shipped in 2.25–2.28.** None of yesterday's UX work was documented at [Admin → Docs](/admin/docs); only a thin five-line "Custom Form Fields" card existed in the *Tickets* page that listed five field types and missed the seven other supported types entirely. That card is replaced with a full *Ticket Form Builder* section in [templates/pages/admin/docs/tickets.php](templates/pages/admin/docs/tickets.php) covering: what the builder is for and the three row kinds it shows (Pinned / System / Custom), a table reference of all 12 supported field types with concrete usage examples for each (including the dependent cascade, date range, text block, image, and CC fields that weren't documented at all), the new *Global vs Specific to ticket type* scope model with an inline rendering of both pill colours and the segmented modal switch, the *Preview as* chip-strip filter (including how the row-border accent encodes Global vs Specific while filtered, and why drag-reorder is disabled in filtered mode), and the *Live Preview* iframe pane (chrome-less embed of `/portal/tickets/create?embed=1`, the three header controls, and the responsive collapse below 1200&nbsp;px). The form-builder section closes with a pointer to a new *Direct Links* heading in the Portal docs at [templates/pages/admin/docs/portal.php](templates/pages/admin/docs/portal.php#direct-links), which gets three new cards: how the `?type_id=N` and `?type=name` query parameters work and which one to pick for shareable links, where to grab a ready-made URL (the new *Direct Link* column at /admin/types with copy-to-clipboard and open-in-new-tab), and a step-by-step walk-through of the "remember where you were going" flow that runs when an anonymous visitor hits a direct link — including the survives-2FA behaviour, the explicit `/login?next=…` form for share-friendly direct-to-login URLs, and a callout explaining the open-redirect protection on both sides (relative path required, absolute / protocol-relative / backslash variants / the `/login` page itself / non-GET / >2&nbsp;000 chars all silently rejected). Twenty-five new entries are added to the docs hub search index in [templates/pages/admin/docs/index.php](templates/pages/admin/docs/index.php) so an admin typing "preview as", "scope", "direct link", "deep link", "type_id", "next", "open redirect", "live preview", "dependent", "CC field", or any of the other terms surfaces the right card.

---

## 2.28.0 — 2026-05-05

### Features
- **Direct-link column on the admin Ticket Types list.** [Admin → Settings → Ticket Types](/admin/types) now renders a *Direct Link* column for each type containing a read-only `font-monospace` input with the path `/portal/tickets/create?type_id={id}` (uses the `type_id` deep-link from 2.25.0 — stable, survives renames, no slug column required), a *Copy* icon-button that puts `window.location.origin + path` on the clipboard via `navigator.clipboard.writeText` (with a `document.execCommand('copy')` fallback for non-secure contexts) and shows a transient green check on success, plus an *Open in new tab* anchor with `target="_blank" rel="noopener"`. Clicking the input itself selects the path so an admin can `Ctrl+C` it without reaching for the icon. Tooltip on the input notes that anonymous visitors will be sent through login first.
- **Login remembers where you were going.** [Auth::requireAuth()](src/Auth.php#L92) now stashes the requested URL into `$_SESSION['intended_url']` before the redirect to `/login`, and a new [consumeIntendedUrl()](src/helpers.php#L84) helper pops + validates it on the read side. The login POST and the 2FA POST both call `redirect(consumeIntendedUrl())` instead of the previous hardcoded `redirect('/')`, so a visitor who hits a Ticket-Types direct link while signed out lands on the login page, signs in (and, if applicable, completes 2FA — `intended_url` is preserved across the `/2fa` round-trip and only consumed on final success), and is bounced to `/portal/tickets/create?type_id=N` with the type pre-selected. The `/login` GET handler also accepts `?next=/path` for share-friendly direct-to-login URLs and stashes that into the same session key. Open-redirect protection is applied on **both** the write side (`requireAuth` / `?next=`) and the read side (`consumeIntendedUrl`): only paths starting with a single `/` are accepted; absolute URLs (`http://…`), protocol-relative (`//evil.com/x`), backslash-prefix variants (`/\evil.com/x`) that some user-agents normalise, non-GET requests, length > 2000 chars, and `/login` itself are all silently rejected and fall back to `/`. Invalid stashes don't break the flow — they just bounce the user to the home page after login as before.

---

## 2.27.1 — 2026-05-05

### Fixes
- **Live preview iframe was being blocked by the site's framing-deny baseline.** 2.27.0 shipped the form-builder live preview but [src/bootstrap.php](src/bootstrap.php#L51) emits `X-Frame-Options: DENY` and a CSP that ends with `frame-ancestors 'none'` for every response — both block framing outright, even from the same origin, so the iframe rendered as the browser's "helpdeskvm.wpl.ca refused to connect" error page. The `/portal/tickets/create` handler now overrides both headers when `?embed=1` is set: `X-Frame-Options: SAMEORIGIN` and the CSP re-emitted with `frame-ancestors 'self'` (PHP's `header()` replaces same-name headers by default, so the baseline emitted in bootstrap is cleanly superseded). Non-embed page loads keep the strict deny baseline — the relaxation is scoped strictly to the read-only preview response.

---

## 2.27.0 — 2026-05-05

### Features
- **Live preview pane on the ticket form builder.** The Ticket Form Builder now has a *Live Preview* toggle button next to *Add Custom Field*. When toggled on the layout splits into two columns — the existing builder card on the left and a sticky right-side pane that iframes the actual portal page at `/portal/tickets/create?embed=1[&type_id=N]`, so the preview is the real form (no parallel render path to drift). Clicking any chip in the *Preview as* strip swaps the iframe's `type_id` so the preview shows exactly the form a user filling in that ticket type would see — including dependent cascades, image fields, text blocks, and the dynamic show/hide of type-scoped fields driven by the existing portal JS. Reordering, adding, deleting, and saving fields all auto-reload the iframe (a `?_t=…` cache-buster is appended on every load), and the pane has its own *Reload* and *Open in new tab* buttons. Below 1200px the layout collapses to stack vertically so the iframe stays readable.
- **`?embed=1` mode on `/portal/tickets/create`.** Adds a chrome-less rendering of the new-ticket form for use inside iframes. The layout in [templates/layouts/app.php](templates/layouts/app.php) now reads an `$embedMode` flag from the route data and, when set, adds a `body.embed-mode` class that hides the navbar, sidebar, breadcrumb, and the resume-tour overlay via CSS, plus zeroes out the sidebar margin and tightens padding on `.main-content`. The portal create handler in [src/routes/portal.php](src/routes/portal.php) reads `$_GET['embed']` and forwards the flag through `render()`. In the template itself the *Submit Request* button is rendered with `disabled` + a soft "submission disabled" tooltip and a one-line *Preview mode* alert appears at the top, plus a defensive JS submit-blocker handles keyboard submits. The *Cancel* link and template picker are suppressed in embed mode so nothing in the iframe navigates away. Read-only on purpose — embed mode never affects how portal users see the form.

---

## 2.26.0 — 2026-05-05

### Features
- **Ticket Form Builder rethought as a per-type form preview.** The builder at [Admin → Workflows → Ticket Fields](/admin/workflows/ticket-fields) was field-centric: a flat list with a tiny `bg-info` "3 types" badge per row that you had to hover individually to learn which fields ended up on which ticket type's intake form. The page is now type-centric. A horizontal **Preview as** chip strip sits at the top of the Form Fields card with one chip per ticket type plus an *All types* default; each chip shows a live count of how many fields appear on that type's form (pinned + system + global custom + type-scoped custom). Clicking a chip filters the list to exactly those fields — Subject, Description, the system rows, the global custom fields, and the custom fields scoped to the picked type — in the actual `sort_order` users will see them in. While a filter is active, drag-reorder is disabled with an inline indigo banner explaining that ordering is global ("switch to *All types* to reorder") so nobody mistakes per-type filtering for per-type ordering, and rows pick up a subtle left-border accent — gray for *Global*, indigo for *Specific to this type* — so global-vs-scoped is legible at a glance without reading badges. The cluttered `bg-info` "N types" pill in each row is replaced by one of two clearer **scope pills**: a gray *Globe · Global* pill when the field has no `ticket_form_field_type_map` rows, or an indigo *Tag · N types* pill (with a Bootstrap tooltip listing the type names on hover) when it does. The edit modal's *Show for Ticket Types* section is rebuilt as a Bootstrap-styled segmented control — *All ticket types* vs *Only specific types* — that matches the new pill vocabulary; the per-type checkboxes only appear when *Only specific types* is picked, and switching back to *All* clears them so a stale list can't be saved by accident. Saving with *Only specific types* and zero boxes ticked now blocks with a helpful message instead of silently making the field global. Quality-of-life: when a type filter is active and you click *Add Custom Field*, the new field's auto-opened modal is pre-scoped to the filtered type so the field you're previewing genuinely lands on that form. Pure additive UI work — no schema, no controller, no route changes; same Bootstrap 5 / indigo `--ld-primary` palette as the rest of the admin.

---

## 2.25.1 — 2026-05-05

### Fixes
- **Deep-link preselect now actually selects the type.** 2.25.0 shipped with a wrong null check: the template tested `old('type_id') === null` to decide whether to fall back to `?type_id` / `?type`, but the [old()](src/helpers.php#L305) helper returns `''` (string) when there's no flashed input, never `null` — so the fallback never fired and the dropdown stayed on "— Select type —" even with a valid query param. Switched the check to `=== ''` and cast `$preselectedTypeId` to string so the loose `==` comparison against `$t['id']` lights up the right `<option selected>`. The custom-field filter JS already runs on load, so the type-scoped fields appear automatically once the dropdown is preselected — no extra wiring needed.

---

## 2.25.0 — 2026-05-05

### Features
- **Deep-link to the new-ticket form with a pre-selected ticket type.** [/portal/tickets/create](src/routes/portal.php#L149) now reads two optional query params and pre-selects the matching `<option>` in the Ticket Type dropdown: `?type_id=N` (numeric ID — exact, survives renames, not human-friendly) and `?type=Name` (case-insensitive name match; hyphens and underscores are normalised to spaces, so `?type=hardware-issue` resolves the same as `?type=Hardware%20Issue` — shareable but breaks if the type is later renamed). ID wins when both are supplied. Unknown values are ignored silently (no error, no preselect) so a stale link still loads the form. `old('type_id')` from a failed POST still takes precedence over the query param so re-submits don't lose the user's pick.

---

## 2.24.1 — 2026-05-05

### Fixes
- **Showcase image now renders on production.** Migration 030 originally drew its placeholder image at apply-time via the GD extension (`imagecreatetruecolor`/`imagepng`), which silently no-op'd on the production PHP build because `php-gd` isn't installed there — the showcase `image` field landed with a NULL `config` and rendered as nothing. 030 has been rewritten to embed the same 480×140 PNG as a base64 string and write it via `file_put_contents`, removing the GD dependency entirely. New migration **031** repairs already-deployed installs by finding the showcase `image` field (matched on the `(example form)` label suffix used by 030, so it survives ticket-type renames), checking that its config is empty, and writing the placeholder PNG into [public/uploads/field-images/](public/uploads/field-images/) + setting `config.image_path`. Idempotent: skips when the field is already populated, when 030's showcase fields don't exist, or when the upload directory can't be created.

---

## 2.24.0 — 2026-05-05

### Features
- **"Example Ticket Form" showcase ticket type.** Migration **030** seeds a new ticket type called *Example Ticket Form* (purple `#6f42c1`, sorted last) and wires one custom field of every supported type to it via [ticket_form_field_type_map](database/migrations/016_ticket_form_field_type_map.php) so the showcase fields appear *only* on this type and don't pollute every other ticket form. The roster covers all twelve `field_type` enum values: `text_block` (intro paragraph), `text`, `textarea`, `number`, `decimal`, `date`, `date_range`, `checkbox`, `dropdown` (Option A/B/C), `dependent` (3-level Region → Country → City cascade), `cc`, and `image` (the migration draws a small placeholder PNG via GD into [public/uploads/field-images/](public/uploads/field-images/) at apply-time so the field renders immediately, with no asset file shipped in the repo). Idempotent — keyed off the type name, so re-running the migration is a no-op. Pick the type at [Portal → New Ticket](/portal/tickets/create) or [Admin → Tickets → New](/admin/tickets/new) to see all field types side-by-side. **To remove later:** delete the ticket type row at [Admin → Workflows → Ticket Types](/admin/workflows/ticket-types) — the FK CASCADE on the type-map drops the scoping; to also drop the showcase fields themselves, delete the rows in `ticket_form_fields` with labels matching `%(example form)%`.

---

## 2.23.0 — 2026-05-04

### Features
- **No ticket ever gets stuck in the "no group" queue again.** Pre-2.23, six different creation paths could each leave a ticket with `group_id = NULL` — most notoriously the email-to-ticket ingest in [scripts/process-replies.php](scripts/process-replies.php), which simply did not include `group_id` in its INSERT — and `autoAssignTicket()` short-circuits at line 596 of [src/helpers.php](src/helpers.php) the moment it sees a NULL group, so those tickets sat invisible in an unmonitored queue until someone happened to filter for `group_id IS NULL` in the agent list view. Three new layers of defence now prevent that:

  **Layer 1 — `resolveTicketGroup($db, $explicit, $typeId)` in [src/helpers.php](src/helpers.php).** Every creation path calls this helper and uses the result as the inserted `group_id`. The helper chains: caller's explicit choice (form field, API param) → the ticket type's default group → the new system-wide `default_group_id` setting → the lowest-id existing group. Each candidate is verified to reference a currently-existing group before being returned, so a stale `ticket_types.group_id` or a stale setting pointing at a deleted group falls through to the next layer instead of poisoning the new ticket. Wired into all seven creation paths: portal submit ([src/routes/portal.php](src/routes/portal.php)), admin create ([src/routes/admin.php](src/routes/admin.php)), admin split ([src/routes/admin.php](src/routes/admin.php)), agent split ([src/routes/agent.php](src/routes/agent.php)), public REST API ([src/routes/api.php](src/routes/api.php)), email-to-ticket ingest ([scripts/process-replies.php](scripts/process-replies.php)), and the legacy CSV importer ([src/routes/admin.php](src/routes/admin.php)).

  **Layer 2 — `backfillTicketGroupFromDefault()` inside `runPostTicketCreateHooks()`.** AI classification and "set group" automations both run *after* the INSERT, so it's still theoretically possible for a creation path to leave the row with NULL group (or for an automation to clear it — the automation engine supports `set_group` to NULL). The new backfill runs immediately before `autoAssignTicket()` and routes any still-NULL row to the system default, posting an internal timeline entry reading *"No group was matched by ticket type, AI, or automations — routed to the system default group so the ticket does not sit in the no-group queue."* That entry is the breadcrumb triage uses to spot unrouted arrivals.

  **Layer 3 — hourly cron sweep in [scripts/process-stale-tickets.php](scripts/process-stale-tickets.php).** A new section at the end of the existing cron job finds any active ticket (status NOT IN `resolved`/`closed`/`merged`) with `group_id IS NULL`, routes it to the configured default, and logs a `WARN:` line per orphan so the bug shows up in `storage/logs/stale-tickets.log`. If no default is configured *and* there are stuck tickets, it emits a different `WARN:` line pointing the admin at the new setting. In normal operation this finds zero tickets — it's the catch-all for legacy data, hand-edited rows, and any new creation path a future developer adds without remembering to call `resolveTicketGroup()`.

  **New setting at [Admin → Settings → Ticket Routing Defaults → Default Group](/admin/settings#default_group_id).** Migration **029** adds the row to the `settings` key-value store, auto-seeds it to the lowest-id existing group on first run, and back-fills any existing tickets sitting with `group_id IS NULL` into that default — so the upgrade itself drains whatever was already stuck. The save handler validates the picked group still exists before writing the setting (deleting a group used as the default is allowed; the runtime then falls through to the lowest-id-group last-resort layer in `resolveTicketGroup()` rather than failing).

  **Audit + observability.** Saving the setting writes an `default_group_changed` audit entry. Every layer-2 and layer-3 backfill writes the timeline entry described above. The flows are described step-by-step at the new [Admin → Docs → Assignment Flows](/admin/docs/flows) page (Diagram 8 covers the safety net specifically); the existing [Docs → Automations](/admin/docs/automations) page now has a "No Group Safety Net" card explaining the three layers and linking to the flows page. The standalone reference [TICKET_ASSIGNMENT_FLOWS.md](TICKET_ASSIGNMENT_FLOWS.md) and [TICKET_ASSIGNMENT_FLOWS.html](TICKET_ASSIGNMENT_FLOWS.html) have both been updated to match.

### Internal
- New help docs page [Admin → Docs → Assignment Flows](/admin/docs/flows) at [templates/pages/admin/docs/flows.php](templates/pages/admin/docs/flows.php) embeds all eight ticket-assignment Mermaid diagrams (master flow, the six strategies, the strategy-fallback flow, and the new default-group fallback) — same diagrams that ship in the `TICKET_ASSIGNMENT_FLOWS.{md,html}` reference docs, but now viewable from inside the helpdesk for support staff who never see the repo. Mermaid renders client-side via the same CDN script the standalone HTML uses; no asset commits required, and updates to a diagram's source automatically re-render. The page is reachable from the docs sidebar (new "Assignment Flows" entry between Automations and AI Classification) and from a new info box on the Automations docs page.

### Migrations
- **029 — default-group fallback.** Idempotent. Seeds `settings.default_group_id` with the lowest-id existing group if it's currently unset/empty, then `UPDATE tickets SET group_id = :default WHERE group_id IS NULL` to drain the historical no-group queue. Verifies the default still references a real group before doing the back-fill, so a stale or zero-group install is handled cleanly.

---

## 2.22.0 — 2026-05-04

### Features
- **Audit log now records when agents disappear because they closed the browser/tab.** Until now the audit log only captured `login` and `logout` (the deliberate logout link), so an agent who simply closed the helpdesk tab — by far the more common way of "going offline" since 2.21 made closing the tab the source of truth for availability — left no trace. Two new audit actions fill the gap. **`session.tab_closed`** is written from `POST /api/presence/leave` in [src/routes.php](src/routes.php), the endpoint the client already calls via `navigator.sendBeacon` on `pagehide`; the audit detail captures the user-agent string from the dying presence row so a stale "Chrome on Windows" close can be told apart from a quick mobile-Safari close. **`session.timed_out`** is written when the beacon never arrives (browser crash, OS kill, network drop, sleep with no wake before the row ages out) by a new `sweepStalePresence(120)` helper in [src/helpers.php](src/helpers.php) that runs on every heartbeat: it `SELECT … FOR UPDATE`s any `user_presence` rows whose `last_seen` is older than the 120s online window, inserts an audit row for each (with the disappeared user's own `user_id` and last-known `ip_address`, *not* the sweeper's, plus `last_seen=…; ua=…` in the detail field), then deletes the stale rows in the same transaction so concurrent heartbeats can't double-log the same disappearance. Combined, the two events give a complete trail: every appearance has a `login`, every clean departure has a `logout` or `session.tab_closed`, and every silent disappearance has a `session.timed_out` matched to the agent's last known IP.

### Internal
- **`Auth::logout()` now also clears the user's `user_presence` row** (in [src/Auth.php](src/Auth.php), guarded with try/catch so a DB failure never blocks logout). Without this, a clean `/logout` would leave the presence row sitting around for up to 120s and the new sweep would then write a spurious `session.timed_out` on top of the already-recorded `logout` event. Cleanup runs *before* the session is destroyed so `Auth::id()` is still available. This also fixes a pre-existing minor cosmetic issue where a freshly-logged-out user could keep showing up on **Admin → Users → Who's Online** for up to 120s.

---

## 2.21.2 — 2026-05-01

### Changes
- **Admin → Settings → Agent Skills → Edit/Add — "Agents with this skill" now filters to members of the selected owning group.** Previously the list always showed every agent / admin / power user in the system, so an admin scoping a skill to (say) "Circulation" still saw every IT and Cataloguing agent in the checkbox grid — easy to grant the skill to someone who isn't even in the group, which then made Skill-Based routing pick people who couldn't actually see those tickets. The form now ships a `[user_id => [group_id, ...]]` map (built by `_skillFormUserGroups()` in [src/routes/admin.php](src/routes/admin.php) from `group_user_map`, scoped to agent / admin / power user roles), tags each checkbox card with `data-group-ids`, and a small inline script in [templates/pages/admin/skills/form.php](templates/pages/admin/skills/form.php) hides + unchecks cards that don't belong to the group when the Scope dropdown changes. Selecting "Global — admin-only" restores the full list (global skills are admin-managed and not group-scoped). When a group is selected, the help text updates to "Showing only members of 'GroupName'…" so the filter is obvious. If the group has no members, an inline warning points at the Groups page to add members first. Filtering also runs on initial page load, so editing an existing group-scoped skill opens with the right list pre-filtered. Note: hidden cards are unchecked, so saving after a scope change cleanly removes any legacy memberships the skill picked up before this fix — that's the intended cleanup. The manager-side skill form (managers editing skills their own group owns) is naturally single-group and unchanged.

---

## 2.21.1 — 2026-05-01

### Fixes
- **Online presence dropped users who minimized their window or switched tabs.** Two compounding bugs from 2.21.0: (1) the heartbeat in [templates/layouts/app.php](templates/layouts/app.php) explicitly stopped its `setInterval` on `visibilitychange → hidden` — meaning a user who tabbed away or minimized Chrome would emit no further pings and fall off the "online" list 60s later; (2) even if the heartbeat had kept running, browsers throttle background-tab `setInterval` callbacks to roughly once per minute, so a single throttled ping could still land outside a 60s online window. Two-part fix: the visibility-change handler no longer stops the timer (it now only fires an *extra* immediate ping when the tab regains focus, to catch up after long throttling gaps), and the server-side online window has been widened from 60s to **120s** in both [_autoAssignFirstAvailable() in src/helpers.php](src/helpers.php) and the [/admin/users/online route](src/routes/admin.php). The 120s window is wide enough to absorb a throttled-to-once-per-minute background tab with one missed-ping margin, so a logged-in agent who minimizes their browser, parks the helpdesk in a background tab, or switches to another window still counts as online — closing the tab is now the only thing that takes them offline (sendBeacon to `/api/presence/leave` on `pagehide` clears the row immediately). Sleep / hibernate / laptop-lid-closed still stops the heartbeat (browsers freeze JS during sleep), but the agent reappears within 30s of waking. Group form description and admin docs ([users.php](templates/pages/admin/docs/users.php), [automations.php](templates/pages/admin/docs/automations.php)) updated to describe the new behaviour explicitly.

---

## 2.21.0 — 2026-05-01

### Features
- **Global online-presence tracking + admin "Who's Online" page.** Every authenticated user's browser now heartbeats `POST /api/presence` every 30 seconds (paused while the tab is hidden, cleared via `sendBeacon` to `/api/presence/leave` on `pagehide`). The new `user_presence` table — added by migration **028** — stores `(user_id, last_seen, ip_address, user_agent)` with `last_seen` indexed for fast online-window queries. Anyone whose `last_seen` is within the last **60 seconds** (one missed-ping headroom on the 30s cadence) is treated as currently online. New page at `/admin/users/online` (linked from the Users header and reachable as **Admin → Users → Who's Online**) lists everyone online right now with avatar/initials, name, role badge, "X seconds ago" last-seen, IP, and a parsed "Chrome on Windows"-style browser label, with a green pulsing dot indicator and a 15-second auto-refresh. Heartbeat code lives in [templates/layouts/app.php](templates/layouts/app.php) and only runs for authenticated users on the main `app` layout, so login/auth/public pages don't ping. The endpoint deliberately skips CSRF — it's idempotent, takes no body, and a `sendBeacon` from `pagehide` can't easily set custom headers; auth + same-origin cookie is sufficient.

### Changes
- **First Available auto-assign now reads online presence instead of the manual `is_available` toggle.** `_autoAssignFirstAvailable()` in [src/helpers.php](src/helpers.php) now picks group members whose `user_presence.last_seen` is within the last 60 seconds, then load-balances among them via `_autoAssignLeastLoaded()`. Falls back via the group's existing `assign_fallback` setting (round-robin / load-based / leave unassigned) when nobody is online. The pre-2.21 behaviour required agents to remember to flip an "I'm available for new tickets" switch on their profile and flip it back when they returned — easy to forget in either direction. Closing the tab is now the single source of truth; opening it again re-registers presence on the next heartbeat (≤ 30s).
- **Removed the manual "I'm available for new tickets" toggle from My Profile** (cards, save handler, and admin docs all updated). The `users.is_available` column is left in place — historical data is harmless and migration 025 still creates it for fresh installs — but nothing reads or writes it anymore.
- **Admin docs refreshed.** [Admin → Docs → Users](templates/pages/admin/docs/users.php) replaces the old "Agent Availability" card with an "Online Presence" card explaining the heartbeat cadence and pointing at the live page. [Admin → Docs → Automations](templates/pages/admin/docs/automations.php) updates the First Available strategy description and the fallback / skills-and-availability sections accordingly.

### Internal
- Migration **028** is idempotent (CREATE TABLE IF NOT EXISTS) and adds an index on `last_seen` so the 60s online-window query stays cheap as the table grows. A 24-hour cleanup runs lazily on every load of `/admin/users/online` to keep the table tidy without scheduling a separate job.

---

## 2.20.3 — 2026-05-01

### Fixes
- **AI skill suggestion still threw "Control character error, possibly incorrectly encoded" after 2.20.2.** The first 200 chars of the failing response were clean JSON, so the offending byte was deeper in. Three layered causes, each fixed:
  1. **Token-limit truncation.** `suggestSkillsFromSettings()` was capping output at `max(800, ai_max_tokens)` — but a real suggestion JSON (8–15 skills × name + description + group + structure) easily runs 1.5–3K tokens. Anthropic was cutting off mid-string, which often splits a multi-byte UTF-8 character — and `json_decode()` reports a split-multibyte fragment as exactly this control-char error. Raised the floor to 4000 tokens for suggestion calls (classification stays at the user's `ai_max_tokens` setting, default 500, since verdict JSON is tiny).
  2. **Invalid UTF-8 wasn't tolerated.** `json_decode()` was called without flags, so a single bad byte killed the whole response. Now layered: strict decode → retry with `JSON_INVALID_UTF8_SUBSTITUTE` → retry with sanitizer + flag → retry with `mb_scrub()` + sanitizer + flag. Each fallback is byte-identical for already-valid JSON, so this only kicks in when something is genuinely broken.
  3. **The sanitizer didn't handle invalid `\`-escapes.** Models occasionally emit Windows paths or stray escapes inside descriptions ("C:\Users\foo", "use \z to..."). `\U`, `\D`, `\z` etc. aren't valid JSON escape characters and `json_decode()` rejects them. The sanitizer now tracks string-literal context and re-escapes any backslash that isn't followed by a valid JSON escape char (`"`, `\`, `/`, `b`, `f`, `n`, `r`, `t`) or a well-formed `\uXXXX`.
- **Better diagnostics on terminal failure.** When all four decode passes fail, the full raw response (truncated at 8 KB instead of 500 chars) is now written to PHP's error log, and the user-facing exception message points at the log so admins know where to look.

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
- Flexible column-mapping step to match CSV headers to OpenHelpDesk fields
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
