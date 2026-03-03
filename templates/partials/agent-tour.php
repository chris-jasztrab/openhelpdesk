<?php
/**
 * Agent onboarding tour (Driver.js spotlight).
 * Included by app.php for all agent-role pages.
 *
 * Variables expected from layout scope:
 *   $autoShowTour (bool) — true when the tour should auto-open on this page load.
 *
 * The tour spans three sections across different pages:
 *   dashboard  → /agent
 *   tickets    → /agent/tickets
 *   templates  → /admin/ticket-templates
 *   (email steps are appended to the templates section)
 */
$_agentTourCsrf     = csrfToken();
$_agentTourAutoShow = ($autoShowTour ?? false) ? 'true' : 'false';
?>
<script>
(function () {
    'use strict';

    var autoShow    = <?= $_agentTourAutoShow ?>;
    var path        = window.location.pathname;
    var storedPage  = localStorage.getItem('ld_agent_tour_page');
    var isNavigating = false;

    // Determine which section of the tour belongs on this page
    var tourSection = null;
    if (autoShow && path === '/agent') {
        tourSection = 'dashboard';
    } else if (storedPage === 'tickets' && path === '/agent/tickets') {
        tourSection = 'tickets';
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
                title:       '👋 Welcome to LocalDesk!',
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
                             '<strong>Knowledge Base</strong>, and <strong>Canned Responses</strong> (pre-written reply snippets). ' +
                             'Hover over an icon to see its label.',
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
            element: '#tour-templates-link',
            popover: {
                title:       'Ticket Templates',
                description: 'Click <strong>Templates</strong> to manage your saved templates. ' +
                             'Templates store a pre-filled subject, description, type, and priority for common requests — ' +
                             'so you do not have to type the same details every time.',
                side:        'bottom',
                align:       'end',
                onNextClick: function () {
                    isNavigating = true;
                    localStorage.setItem('ld_agent_tour_page', 'templates');
                    window.location.href = '/admin/ticket-templates';
                }
            }
        }
    ];

    var templatesSteps = [
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
        },
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

    // ── Choose steps for this page ──────────────────────────────────

    var steps;
    if (tourSection === 'dashboard') {
        steps = dashboardSteps;
    } else if (tourSection === 'tickets') {
        steps = ticketsSteps;
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
