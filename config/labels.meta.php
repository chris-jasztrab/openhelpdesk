<?php

/**
 * Presentation metadata for the Label Customisation page.
 *
 * Kept OUT of config/labels.default.json on purpose: that file is a plain
 * key => value map that admins download, edit and re-upload, and the upload
 * validator rejects anything that isn't a string value. Metadata lives here
 * instead so the download stays a clean list of labels.
 *
 * ── The contract ──────────────────────────────────────────────────────
 * A key listed in `keys` with a `where` note is WIRED UP: some template or
 * route actually calls label() for it, and renaming it changes the app.
 * A key absent from `keys` is INERT — it exists in labels.default.json but
 * nothing reads it, so renaming it does nothing. The Labels page separates
 * the two so nobody spends ten minutes renaming a key with no effect.
 *
 * `where` is therefore not decoration — it IS the wired-up flag, which is
 * why the two can't drift apart by accident. When you wire up an inert key,
 * add its `where` note in the same commit.
 *
 * tests/Feature/Admin/LabelMetaTest.php enforces this against the real
 * label() callsites, so a forgotten note fails the suite rather than
 * quietly mislabelling a working key as inert.
 */

return [

    /*
     * Ordered sections for the wired-up keys. Order here is render order.
     */
    'groups' => [
        'organisation' => [
            'title' => 'Organisation',
            'blurb' => 'What you call the places your team works.',
        ],
        'staff_nav' => [
            'title' => 'Staff navigation',
            'blurb' => 'The icon rail down the left of the staff and admin areas, plus the Settings page menu.',
        ],
        'portal_nav' => [
            'title' => 'Portal navigation',
            'blurb' => 'The icon rail your end users see.',
        ],
        'portal_status' => [
            'title' => 'Portal status wording',
            'blurb' => 'Plain-English status names shown to end users. Staff see the real status names from '
                     . 'Admin → Settings → Ticket Statuses instead, so these two can differ on purpose.',
        ],
        'portal_requests' => [
            'title' => 'Portal requests & actions',
            'blurb' => 'Buttons, headings and helper text on the portal request list and form.',
        ],
        'portal_request_page' => [
            'title' => 'Portal request page',
            'blurb' => 'Wording inside a single request as the requester sees it.',
        ],
    ],

    /*
     * key => ['group' => <group id>, 'where' => <plain-English location>]
     */
    'keys' => [

        /* ── Organisation ─────────────────────────────────────────── */
        'location.singular' => [
            'group' => 'organisation',
            'where' => 'Used in ~30 places: the Location filter and column on the staff and admin ticket lists, '
                     . 'the Location row on a ticket, the Location field on user and ticket forms, '
                     . 'Admin → Settings → Locations, the reports pages, the SSO location prompt, '
                     . 'new-ticket emails, and the portal request form.',
        ],
        'location.plural' => [
            'group' => 'organisation',
            'where' => 'Admin → Settings → Locations (page heading and its Settings-menu entry), the Admin '
                     . 'dashboard shortcut, the CSV import previews, and the first-run setup tour.',
        ],

        /* ── Staff navigation ─────────────────────────────────────── */
        'nav.dashboard' => [
            'group' => 'staff_nav',
            'where' => 'Admin icon rail — first item.',
        ],
        'nav.tickets' => [
            'group' => 'staff_nav',
            'where' => 'Admin icon rail — second item.',
        ],
        'nav.settings' => [
            'group' => 'staff_nav',
            'where' => 'Icon rail — the Settings cog, on both the admin and the staff rail.',
        ],
        'nav.docs' => [
            'group' => 'staff_nav',
            'where' => 'Admin icon rail — the Docs question mark.',
        ],
        'nav.users' => [
            'group' => 'staff_nav',
            'where' => 'Settings page left menu, under Users & Access.',
        ],
        'nav.reports' => [
            'group' => 'staff_nav',
            'where' => 'Settings page left menu, the Reports entry.',
        ],
        'nav.audit_log' => [
            'group' => 'staff_nav',
            'where' => 'Settings page left menu, the Audit Log entry.',
        ],
        'agent.nav.dashboard' => [
            'group' => 'staff_nav',
            'where' => 'Staff (non-admin) icon rail — first item.',
        ],
        'agent.nav.tickets' => [
            'group' => 'staff_nav',
            'where' => 'Staff (non-admin) icon rail — second item.',
        ],
        'agent.nav.knowledge_base' => [
            'group' => 'staff_nav',
            'where' => 'Staff (non-admin) icon rail — the Knowledge Base book.',
        ],

        /* ── Portal navigation ────────────────────────────────────── */
        'portal.nav.dashboard' => [
            'group' => 'portal_nav',
            'where' => 'Portal icon rail — first item.',
        ],
        'portal.nav.my_tickets' => [
            'group' => 'portal_nav',
            'where' => 'Portal icon rail — the request list. Note this names the rail entry only; the toggle on '
                     . 'the list itself is portal.request.my_plural.',
        ],
        'portal.nav.knowledge_base' => [
            'group' => 'portal_nav',
            'where' => 'Portal icon rail — the Knowledge Base book.',
        ],
        'portal.nav.help' => [
            'group' => 'portal_nav',
            'where' => 'Portal icon rail — the Help entry, and the Help link in the top navbar.',
        ],

        /* ── Portal status wording ────────────────────────────────── */
        'portal.status.open' => [
            'group' => 'portal_status',
            'where' => 'Status badge and filter dropdown on the portal request list, dashboard and request page.',
        ],
        'portal.status.in_progress' => [
            'group' => 'portal_status',
            'where' => 'Status badge and filter dropdown on the portal request list, dashboard and request page.',
        ],
        'portal.status.pending' => [
            'group' => 'portal_status',
            'where' => 'Status badge and filter dropdown on the portal request list, dashboard and request page.',
        ],
        'portal.status.waiting_on_customer' => [
            'group' => 'portal_status',
            'where' => 'Status badge and filter dropdown on the portal request list, dashboard and request page. '
                     . 'This is the one requesters act on, so the default is "Waiting on you".',
        ],
        'portal.status.waiting_on_third_party' => [
            'group' => 'portal_status',
            'where' => 'Status badge and filter dropdown on the portal request list, dashboard and request page.',
        ],
        'portal.status.resolved' => [
            'group' => 'portal_status',
            'where' => 'Status badge and filter dropdown on the portal request list, dashboard and request page.',
        ],
        'portal.status.closed' => [
            'group' => 'portal_status',
            'where' => 'Status badge and filter dropdown on the portal request list, dashboard and request page.',
        ],

        /* ── Portal requests & actions ────────────────────────────── */
        'portal.request.my_plural' => [
            'group' => 'portal_requests',
            'where' => 'The "My Requests" scope toggle on the portal request list, and the page heading / '
                     . 'breadcrumb on the list, request, create and edit pages.',
        ],
        'portal.action.new' => [
            'group' => 'portal_requests',
            'where' => 'The New Help Request button — portal dashboard, request list, and the create form heading.',
        ],
        'portal.action.submit' => [
            'group' => 'portal_requests',
            'where' => 'The submit button at the bottom of the portal request form.',
        ],
        'portal.action.close' => [
            'group' => 'portal_requests',
            'where' => 'The "Close this request" button on a portal request.',
        ],
        'portal.field.priority_label' => [
            'group' => 'portal_requests',
            'where' => 'The priority question on the portal request form.',
        ],
        'portal.field.priority_help' => [
            'group' => 'portal_requests',
            'where' => 'The grey helper line under the priority question on the portal request form.',
        ],
        'portal.dashboard.recent_tickets' => [
            'group' => 'portal_requests',
            'where' => 'Heading above the active-requests list on the portal dashboard.',
        ],
        'portal.no_tickets' => [
            'group' => 'portal_requests',
            'where' => 'Empty-state line on the portal dashboard when the user has no active requests.',
        ],

        /* ── Portal request page ──────────────────────────────────── */
        'portal.what_next.title' => [
            'group' => 'portal_request_page',
            'where' => 'Heading of the "What happens next?" panel on a portal request.',
        ],
        'portal.what_next.line1' => [
            'group' => 'portal_request_page',
            'where' => 'First bullet of the "What happens next?" panel on a portal request.',
        ],
        'portal.what_next.line2' => [
            'group' => 'portal_request_page',
            'where' => 'Second bullet of the "What happens next?" panel on a portal request.',
        ],
        'portal.what_next.line3' => [
            'group' => 'portal_request_page',
            'where' => 'Third bullet of the "What happens next?" panel on a portal request.',
        ],
        'portal.solution.available' => [
            'group' => 'portal_request_page',
            'where' => 'Heading of the green answer banner on a portal request once staff post a solution.',
        ],
        'portal.solution.badge' => [
            'group' => 'portal_request_page',
            'where' => 'Badge on the answer banner on a portal request.',
        ],
        'portal.solution.posted_by' => [
            'group' => 'portal_request_page',
            'where' => 'The "posted by" credit line on the answer banner.',
        ],
        'portal.solution.staff' => [
            'group' => 'portal_request_page',
            'where' => 'Stand-in name on the answer banner when the author is not shown to requesters.',
        ],
        'portal.solution.on' => [
            'group' => 'portal_request_page',
            'where' => 'The word joining author and date on the answer banner ("posted by X on 3 Jul").',
        ],
        'portal.solution.go' => [
            'group' => 'portal_request_page',
            'where' => 'The jump-to-answer link on the answer banner.',
        ],
    ],

    /*
     * Keys that used to exist and have since been removed from
     * labels.default.json. An uploaded file that still contains them is
     * accepted with a notice instead of failing validation, so an admin's
     * previously downloaded labels.json keeps working after an upgrade.
     *
     * status.* went when ticket statuses became configurable rows in the
     * database (v2.61.4) — staff-side status names are edited at
     * Admin → Settings → Ticket Statuses now, and portal.status.* overrides
     * them for end users.
     */
    'retired' => [
        'status.open'                   => 'Admin → Settings → Ticket Statuses',
        'status.in_progress'            => 'Admin → Settings → Ticket Statuses',
        'status.pending'                => 'Admin → Settings → Ticket Statuses',
        'status.waiting_on_customer'    => 'Admin → Settings → Ticket Statuses',
        'status.waiting_on_third_party' => 'Admin → Settings → Ticket Statuses',
        'status.resolved'               => 'Admin → Settings → Ticket Statuses',
        'status.closed'                 => 'Admin → Settings → Ticket Statuses',
    ],
];
