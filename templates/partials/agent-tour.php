<?php
/**
 * Agent onboarding tour (Driver.js spotlight).
 * Included by app.php for all agent-role pages.
 *
 * Tour flow (4 sections across different pages):
 *   dashboard  → /agent
 *   tickets    → /agent/tickets
 *   ticket     → /agent/tickets/{id}   ← new section
 *   templates  → /admin/ticket-templates
 */
$_agentTourCsrf     = csrfToken();
$_agentTourAutoShow = ($autoShowTour ?? false) ? 'true' : 'false';
// The Templates section lives at /admin/ticket-templates, which requires
// ticket_templates.manage. Don't route a tour-taker who lacks it into a 403.
$_agentTourCanTemplates = Auth::can('ticket_templates.manage') ? 'true' : 'false';
?>
<script>
(function () {
    'use strict';

    var autoShow           = <?= $_agentTourAutoShow ?>;
    var canManageTemplates = <?= $_agentTourCanTemplates ?>;
    var path        = window.location.pathname;
    var storedPage  = localStorage.getItem('ld_agent_tour_page');
    var isNavigating = false;

    // Determine which section of the tour belongs on this page
    var tourSection = null;
    if (autoShow && path === '/agent') {
        tourSection = 'dashboard';
    } else if (storedPage === 'tickets' && path === '/agent/tickets') {
        tourSection = 'tickets';
    } else if (storedPage === 'ticket' && /^\/agent\/tickets\/\d+$/.test(path)) {
        tourSection = 'ticket';
    } else if (storedPage === 'templates' && path === '/admin/ticket-templates') {
        tourSection = 'templates';
    }

    if (!tourSection) return;

    var csrfToken = <?= json_encode($_agentTourCsrf) ?>;

    function dismissTour() {
        localStorage.removeItem('ld_agent_tour_page');
        fetch('/agent/tour/dismiss', {
            method:      'POST',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body:        '_token=' + encodeURIComponent(csrfToken)
        }).catch(function () {});
    }

    // ── Step definitions ────────────────────────────────────────────

    var dashboardSteps = [
        {
            popover: {
                title:       '👋 Welcome to OpenHelpDesk!',
                description: 'This quick tour shows you the key parts of your agent interface — it takes about 2 minutes.<br><br>' +
                             '<strong>Tip:</strong> You can restart this tour anytime by clicking your name in the top-right corner and choosing <strong>Restart Tour</strong>.'
            }
        },
        {
            element: '#tour-stat-cards',
            popover: {
                title:       'Your Dashboard',
                description: 'At a glance: <strong>Unassigned</strong> shows tickets waiting to be picked up, ' +
                             '<strong>My Tickets</strong> are assigned to you, <strong>Pending</strong> are awaiting a response, ' +
                             'and <strong>Resolved Today</strong> tracks your progress.',
                side:  'bottom',
                align: 'start'
            }
        },
        {
            element: '#tour-recent-tickets',
            popover: {
                title:       'Recent Tickets',
                description: 'Your most recent active tickets appear here. Click any row to open a ticket. ' +
                             'Use <strong>View All</strong> to go to the full tickets screen.',
                side:  'top',
                align: 'start'
            }
        },
        {
            element: '.sidebar',
            popover: {
                title:       'Navigation',
                description: 'Use the left sidebar to move between <strong>Dashboard</strong>, <strong>Tickets</strong>, ' +
                             'the <strong>Knowledge Base</strong>, and more. Hover over an icon to see its label.<br><br>' +
                             'Your <strong>Canned Responses</strong> (pre-written reply snippets) now live on your ' +
                             '<strong>profile page</strong> — open it from your name in the top-right corner.',
                side:  'right',
                align: 'center'
            }
        },
        {
            element: '#ld-notif-bell',
            popover: {
                title:       'Notifications',
                description: 'When someone <strong>@mentions</strong> you in a ticket comment, a red badge appears here. ' +
                             'Click the bell to view your notifications.',
                side:        'bottom',
                align:       'end',
                onNextClick: function () {
                    isNavigating = true;
                    localStorage.setItem('ld_agent_tour_page', 'tickets');
                    window.location.href = '/agent/tickets';
                }
            }
        }
    ];

    var ticketsSteps = [
        {
            popover: {
                title:       'Your Tickets',
                description: 'This is your main workspace. Every support ticket appears here. ' +
                             'You can search, filter, sort, and manage all your work from this screen.'
            }
        },
        {
            element: '#filterPanelBtn',
            popover: {
                title:       'Filtering Tickets',
                description: 'Click <strong>Filters</strong> to narrow the list by status, priority, type, location, or agent. ' +
                             'You can also search by subject keyword.<br><br>' +
                             'Save a set of filters as your <strong>default filter</strong> — it applies automatically every time you visit.',
                side:  'bottom',
                align: 'end'
            }
        },
        {
            element: '#tour-columns-btn',
            popover: {
                title:       'Customise Columns',
                description: 'Use the <strong>Columns</strong> dropdown to show or hide columns in the ticket list — ' +
                             'such as Priority, Type, Agent, Location, SLA, and Due Date. Your selection is saved automatically.',
                side:  'bottom',
                align: 'end'
            }
        },
        {
            element: '.sidebar a[href="/portal/kb"]',
            popover: {
                title:       'Knowledge Base',
                description: 'The <strong>Knowledge Base</strong> contains articles written by your team. ' +
                             'Before creating a ticket, check if there is already a documented solution. ' +
                             'You can also search and reference articles while helping customers.',
                side:  'right',
                align: 'center'
            }
        },
        {
            element: '#tour-new-ticket-btn',
            popover: {
                title:       'Creating a New Ticket',
                description: 'Click <strong>New Ticket</strong> to manually create a ticket on behalf of a customer. ' +
                             'Fill in the subject, description, type, priority, and location.<br><br>' +
                             'At the top of the New Ticket form there is a <strong>Template…</strong> dropdown — ' +
                             'select a template to pre-fill common fields automatically.',
                side:  'bottom',
                align: 'end'
            }
        },
        {
            popover: {
                title:       'Let\'s Open a Ticket',
                description: 'Now let\'s look inside a ticket to see how to work with it.',
                onNextClick: function () {
                    var firstLink = document.querySelector('tbody tr a[href^="/agent/tickets/"]');
                    if (firstLink) {
                        isNavigating = true;
                        localStorage.setItem('ld_agent_tour_page', 'ticket');
                        window.location.href = firstLink.href;
                    } else if (canManageTemplates) {
                        // No tickets yet — skip straight to templates
                        isNavigating = true;
                        localStorage.setItem('ld_agent_tour_page', 'templates');
                        window.location.href = '/admin/ticket-templates';
                    } else {
                        // No tickets and no template access — finish with the
                        // page-agnostic closing steps appended to this section.
                        driverObj.moveNext();
                    }
                }
            }
        }
    ];

    var ticketSteps = [
        {
            popover: {
                title:       'Inside a Ticket',
                description: 'This is the ticket detail page — where you read, respond to, and manage a support request. ' +
                             'Let\'s walk through each part.'
            }
        },
        {
            element: '#tour-ticket-header',
            popover: {
                title:       'Ticket Header',
                description: 'The subject line sits at the top, with colour-coded badges showing the current ' +
                             '<strong>Status</strong>, <strong>Priority</strong>, and <strong>SLA state</strong> (On Track / Warning / Breached). ' +
                             'The ticket number is also shown here for reference.',
                side:  'bottom',
                align: 'start'
            }
        },
        {
            element: '#tour-ticket-description',
            popover: {
                title:       'Description',
                description: 'The full description submitted by the user appears here, along with any file attachments they included.',
                side:  'bottom',
                align: 'start'
            }
        },
        {
            element: '#tour-ticket-timeline',
            popover: {
                title:       'Timeline',
                description: 'Every action on the ticket is logged here in chronological order — replies, internal notes, ' +
                             'status changes, assignments, and SLA events. ' +
                             'Internal notes (shown with a lock icon) are visible to agents only.',
                side:  'top',
                align: 'start'
            }
        },
        {
            element: '#replyActionBar',
            popover: {
                title:       'Reply, Note & Forward',
                description: '<strong>Reply</strong> sends a visible response to the user by email.<br>' +
                             '<strong>Note</strong> adds an internal-only comment — the user cannot see it, ' +
                             'useful for leaving context for other agents.<br>' +
                             '<strong>Forward</strong> routes the message to a third party (e.g. a vendor) ' +
                             'while keeping the ticket open and tracked.',
                side:  'top',
                align: 'start'
            }
        },
        {
            element: '#tour-ticket-details',
            popover: {
                title:       'Ticket Details',
                description: 'The Details panel shows who created the ticket, the assigned agent, group, type, location, ' +
                             'created date, and due date. It also captures the browser and OS the request came from.',
                side:  'left',
                align: 'start'
            }
        },
        {
            element: '#tour-update-ticket',
            popover: {
                title:       'Update Ticket',
                description: 'Use this panel to change the <strong>Status</strong>, <strong>Priority</strong>, ' +
                             '<strong>Assigned Agent</strong>, or <strong>Group</strong>, then click <strong>Update</strong> to save.<br><br>' +
                             'When sending a reply you can also change the status in one step using the dropdown ' +
                             'next to the Send button — for example, reply and mark as Resolved at the same time.',
                side:  'left',
                align: 'start'
            }
        },
        {
            element: '#tour-watch-btn',
            popover: {
                title:       'Watch',
                description: 'Click <strong>Watch</strong> to subscribe to email updates for this ticket even if it is ' +
                             'not assigned to you — useful when you want to stay informed without being the primary agent. ' +
                             'Click again to stop watching.',
                side:  'bottom',
                align: 'end'
            }
        },
        {
            element: '#tour-split-btn',
            popover: {
                title:       'Split',
                description: 'Use <strong>Split</strong> when a ticket contains more than one unrelated issue. ' +
                             'It creates a new ticket from the original so each issue can be tracked and resolved separately.',
                side:  'bottom',
                align: 'end'
            }
        },
        {
            element: '#tour-merge-btn',
            popover: {
                title:       'Merge',
                description: 'Use <strong>Merge</strong> to combine duplicate or closely related tickets into one. ' +
                             'Search for the target ticket, confirm, and all activity is consolidated under the master ticket. ' +
                             'The merged ticket is then closed automatically.',
                side:  'bottom',
                align: 'end',
                onNextClick: function () {
                    if (canManageTemplates) {
                        isNavigating = true;
                        localStorage.setItem('ld_agent_tour_page', 'templates');
                        window.location.href = '/admin/ticket-templates';
                    } else {
                        // Can't open Templates — advance to the closing steps
                        // appended to this section instead of navigating.
                        driverObj.moveNext();
                    }
                }
            }
        }
    ];

    // The three template-management steps only make sense on the Templates page.
    var templateIntroSteps = [
        {
            popover: {
                title:       'Ticket Templates',
                description: 'Templates save common ticket setups — subject, description, type, priority, and more. ' +
                             'They are a big time-saver for recurring requests like password resets, hardware orders, or maintenance reports.'
            }
        },
        {
            element: '#tour-create-template-btn',
            popover: {
                title:       'Creating a Template',
                description: 'Click <strong>New Template</strong> to create one. ' +
                             'Give it a name, fill in the fields that are common to that type of request, and save. ' +
                             'It will appear in the <strong>Template…</strong> dropdown when creating a ticket.',
                side:  'bottom',
                align: 'end'
            }
        },
        {
            element: '#tour-templates-card',
            popover: {
                title:       'Sharing Templates',
                description: 'Templates can be <strong>Private</strong> (only you can use them) or <strong>Shared</strong> ' +
                             'with the whole team. Tick <em>Share with all agents</em> when creating or editing a template ' +
                             'so every agent can benefit from it.',
                side:  'top',
                align: 'start'
            }
        }
    ];

    // Page-agnostic wrap-up steps. They normally close out the Templates
    // section, but are appended to the last ticket section instead for agents
    // who can't open Templates — so everyone still sees the email tips.
    var closingSteps = [
        {
            popover: {
                title:       '📧 Email Notifications',
                description: 'When a new ticket is submitted, you will receive an email notification. ' +
                             'You will also be emailed when a ticket you are assigned to is updated or receives a reply.<br><br>' +
                             '<strong>Reply directly to the email</strong> to add a comment to the ticket — no need to log in.'
            }
        },
        {
            popover: {
                title:       '⚡ Email Commands',
                description: 'Add hashtag commands at the <strong>end</strong> of your email reply to update a ticket without logging in:<br><br>' +
                             '<code>#close</code> — Close the ticket<br>' +
                             '<code>#resolve</code> — Mark as resolved<br>' +
                             '<code>#open</code> — Reopen the ticket<br>' +
                             '<code>#pending</code> — Set to pending<br>' +
                             '<code>#high</code> &nbsp;/&nbsp; <code>#low</code> &nbsp;/&nbsp; <code>#medium</code> &nbsp;/&nbsp; <code>#critical</code> — Change priority<br><br>' +
                             'Combine them freely: <em>Thanks, all fixed! #resolve #high</em><br>' +
                             'Commands are case-insensitive and work even if followed by your email signature.'
            }
        }
    ];

    // Full Templates-page section = the template steps plus the wrap-up.
    var templatesSteps = templateIntroSteps.concat(closingSteps);

    // When Templates is unreachable, the ticket sections become the tour's end,
    // so the wrap-up steps ride along on whichever one the agent finishes on.
    if (!canManageTemplates) {
        ticketsSteps = ticketsSteps.concat(closingSteps);
        ticketSteps  = ticketSteps.concat(closingSteps);
    }

    // ── Choose steps for this page ──────────────────────────────────

    var steps;
    if (tourSection === 'dashboard') {
        steps = dashboardSteps;
    } else if (tourSection === 'tickets') {
        steps = ticketsSteps;
        localStorage.removeItem('ld_agent_tour_page');
    } else if (tourSection === 'ticket') {
        steps = ticketSteps;
        localStorage.removeItem('ld_agent_tour_page');
    } else {
        steps = templatesSteps;
        localStorage.removeItem('ld_agent_tour_page');
    }

    // ── Initialise Driver.js ────────────────────────────────────────

    var driverObj = window.driver.js.driver({
        showProgress:   true,
        progressText:   'Step {{current}} of {{total}}',
        allowClose:     true,
        smoothScroll:   true,
        steps:          steps,

        onDestroyStarted: function () {
            if (!isNavigating) {
                // User closed the tour manually or clicked Done on the last step
                localStorage.removeItem('ld_agent_tour_page');
                dismissTour();
            }
            driverObj.destroy();
        }
    });

    driverObj.drive();
})();
</script>
