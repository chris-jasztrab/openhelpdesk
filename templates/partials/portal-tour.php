<?php
/**
 * Portal user onboarding tour (Driver.js spotlight).
 * Included by app.php for all portal-role pages.
 *
 * Tour flow (3 sections across different pages):
 *   dashboard → /portal
 *   tickets   → /portal/tickets
 *   create    → /portal/tickets/create
 */
$_portalTourCsrf     = csrfToken();
$_portalTourAutoShow = ($autoShowTour ?? false) ? 'true' : 'false';
?>
<script>
(function () {
    'use strict';

    var autoShow    = <?= $_portalTourAutoShow ?>;
    var path        = window.location.pathname;
    var storedPage  = localStorage.getItem('ld_portal_tour_page');
    var isNavigating = false;

    // Determine which section of the tour belongs on this page
    var tourSection = null;
    if (autoShow && path === '/portal') {
        tourSection = 'dashboard';
    } else if (storedPage === 'tickets' && path === '/portal/tickets') {
        tourSection = 'tickets';
    } else if (storedPage === 'create' && path === '/portal/tickets/create') {
        tourSection = 'create';
    } else if (storedPage === 'profile' && path === '/profile') {
        tourSection = 'profile';
    }

    if (!tourSection) return;

    var csrfToken = <?= json_encode($_portalTourCsrf) ?>;

    function dismissTour() {
        localStorage.removeItem('ld_portal_tour_page');
        fetch('/portal/tour/dismiss', {
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
                title:       '👋 Welcome to the Support Portal!',
                description: 'This quick tour shows you how to get help and track your support requests — it takes about 2 minutes.<br><br>' +
                             '<strong>Tip:</strong> You can restart this tour anytime by clicking your name in the top-right corner and choosing <strong>Restart Tour</strong>.'
            }
        },
        {
            element: '#tour-portal-stats',
            popover: {
                title:       'Your Summary',
                description: '<strong>Active requests</strong> are ones the team is still working on. ' +
                             '<strong>Done</strong> shows requests that have been completed.',
                side:  'bottom',
                align: 'start'
            }
        },
        {
            element: '#tour-portal-recent',
            popover: {
                title:       'Your Active Requests',
                description: 'Your most recent help requests appear here. ' +
                             'Click any row to open it and see updates from the team.',
                side:  'top',
                align: 'start'
            }
        },
        {
            element: '.sidebar',
            popover: {
                title:       'Navigation',
                description: 'Use the sidebar to switch between <strong>Dashboard</strong>, <strong>My Requests</strong>, and the <strong>Knowledge Base</strong> — ' +
                             'a library of help articles that may already answer your question.',
                side:  'right',
                align: 'center'
            }
        },
        {
            element: '#tour-portal-new-ticket',
            popover: {
                title:       'Start a New Request',
                description: 'Click <strong>New Help Request</strong> to reach the team. ' +
                             'Describe your issue and someone will reply as soon as possible.',
                side:  'bottom',
                align: 'end',
                onNextClick: function () {
                    isNavigating = true;
                    localStorage.setItem('ld_portal_tour_page', 'tickets');
                    window.location.href = '/portal/tickets';
                }
            }
        }
    ];

    var ticketsSteps = [
        {
            popover: {
                title:       'My Requests',
                description: 'This page lists all of your help requests. ' +
                             'You can search, filter by status or urgency, and sort the list to find what you need.'
            }
        },
        {
            element: '#tour-portal-filter-bar',
            popover: {
                title:       'Filter &amp; Search',
                description: 'Use the <strong>Search</strong> box to find a request by subject keyword. ' +
                             'Use the <strong>Status</strong> and <strong>Priority</strong> dropdowns to narrow the list, ' +
                             'then click <strong>Filter</strong> to apply.',
                side:  'bottom',
                align: 'start'
            }
        },
        {
            element: '#tour-portal-ticket-table',
            popover: {
                title:       'Your Requests',
                description: 'Each row shows the request number, subject, current status, priority, who it\'s assigned to, and when it was submitted. ' +
                             'Click any row to open the request and see updates.',
                side:  'top',
                align: 'start'
            }
        },
        {
            popover: {
                title:       'Let\'s Submit a Request',
                description: 'Now let\'s walk through submitting a help request.',
                onNextClick: function () {
                    isNavigating = true;
                    localStorage.setItem('ld_portal_tour_page', 'create');
                    window.location.href = '/portal/tickets/create';
                }
            }
        }
    ];

    var createSteps = [
        {
            popover: {
                title:       'New Help Request',
                description: 'Fill in this form to send a help request to the team. Let\'s walk through each field.'
            }
        },
        {
            element: '#tour-portal-subject',
            popover: {
                title:       'Subject',
                description: 'Enter a brief, clear summary of your issue. ' +
                             'A good subject helps the team understand and route your request quickly.',
                side:  'bottom',
                align: 'start'
            }
        },
        {
            element: '#tour-portal-description',
            popover: {
                title:       'Description',
                description: 'Describe the issue in detail — what happened, when it occurred, and any steps you\'ve already tried. ' +
                             'The more detail you provide, the faster we can help.',
                side:  'top',
                align: 'start'
            }
        },
        {
            element: '#tour-portal-type',
            popover: {
                title:       'Type &amp; Priority',
                description: 'Select the <strong>Type</strong> that best matches your request (e.g. Hardware, Software, Account). ' +
                             'Set the <strong>Priority</strong> to indicate how urgently you need assistance.',
                side:  'bottom',
                align: 'start'
            }
        },
        {
            popover: {
                title:       'Editing a Request',
                description: 'After submitting, you can update the <strong>Subject</strong> or <strong>Description</strong> at any time. ' +
                             'Just open the request and click the <strong>Edit</strong> button — useful if you forgot a detail or need to clarify something.'
            }
        },
        {
            popover: {
                title:       'Closing a Request',
                description: 'If your issue is resolved and the request is still open, you can close it yourself. ' +
                             'Open the request and click <strong>Close this request</strong> to let the team know it\'s taken care of.'
            }
        },
        {
            popover: {
                title:       'Almost done!',
                description: 'One last thing — let\'s look at your <strong>Profile</strong>, where you can control which emails you receive from the support team.',
                onNextClick: function () {
                    isNavigating = true;
                    localStorage.setItem('ld_portal_tour_page', 'profile');
                    window.location.href = '/profile';
                }
            }
        }
    ];

    var profileSteps = [
        {
            popover: {
                title:       'Your Profile',
                description: 'This page lets you update your name, change your password, and adjust your appearance and notification settings.'
            }
        },
        {
            element: '#tour-portal-notifications',
            popover: {
                title:       'Email Notifications',
                description: 'Use these toggles to control exactly which emails you receive from the team — ' +
                             'such as confirmation when you submit a request, replies, or satisfaction surveys.<br><br>' +
                             'Turn off anything you don\'t need.',
                side:  'top',
                align: 'start'
            }
        },
        {
            popover: {
                title:       '🎉 You\'re all set!',
                description: 'That\'s the full tour! You now know how to submit, edit, and close help requests, track their status, find help articles in the Knowledge Base, and manage your notification preferences.<br><br>' +
                             'You can also <strong>reply directly to request emails</strong> to add a comment without logging in.'
            }
        }
    ];

    // ── Choose steps for this page ──────────────────────────────────

    var steps;
    if (tourSection === 'dashboard') {
        steps = dashboardSteps;
    } else if (tourSection === 'tickets') {
        steps = ticketsSteps;
        localStorage.removeItem('ld_portal_tour_page');
    } else if (tourSection === 'create') {
        steps = createSteps;
        localStorage.removeItem('ld_portal_tour_page');
    } else {
        steps = profileSteps;
        localStorage.removeItem('ld_portal_tour_page');
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
                localStorage.removeItem('ld_portal_tour_page');
                dismissTour();
            }
            driverObj.destroy();
        }
    });

    driverObj.drive();
})();
</script>
