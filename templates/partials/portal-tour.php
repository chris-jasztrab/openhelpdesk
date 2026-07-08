<?php
/**
 * Portal user onboarding tour (Driver.js spotlight).
 * Included by app.php for all portal-role pages.
 *
 * Tour flow (5 sections across different pages):
 *   dashboard → /portal
 *   tickets   → /portal/tickets
 *   create    → /portal/tickets/create
 *   demo      → /portal/tour/demo-ticket   (synthetic practice ticket —
 *               lets the tour spotlight the escalate button, answer banner,
 *               attachments etc. without needing a real ticket to exist)
 *   profile   → /profile
 *
 * Section hand-off uses localStorage (ld_portal_tour_page): the last step of
 * each section stores the next section's key and navigates. Closing the tour
 * (✕ / Esc / overlay click) calls /portal/tour/dismiss, which clears the
 * per-user show_portal_tour flag; closing the browser mid-tour leaves the
 * flag set, so the tour re-offers from the start on the next portal visit.
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
    } else if (storedPage === 'demo' && path === '/portal/tour/demo-ticket') {
        tourSection = 'demo';
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

    function goTo(nextSection, url) {
        return function () {
            isNavigating = true;
            localStorage.setItem('ld_portal_tour_page', nextSection);
            window.location.href = url;
        };
    }

    // True when the element exists AND is actually rendered (skips steps for
    // permission- or config-gated UI this user doesn't have, and for navbar
    // items hidden inside the collapsed mobile menu).
    function visible(sel) {
        var el = document.querySelector(sel);
        return !!(el && (el.offsetParent !== null || getComputedStyle(el).position === 'fixed'));
    }

    // ── Step definitions ────────────────────────────────────────────

    var steps = [];

    if (tourSection === 'dashboard') {

        steps.push({
            popover: {
                title:       '👋 Welcome to the Support Portal!',
                description: 'This tour walks you through everything you can do here — submitting and tracking help requests, ' +
                             'finding answers yourself, and choosing which emails you get. It takes about five minutes.<br><br>' +
                             '<strong>Tip:</strong> You can restart it anytime — click your name in the top-right corner and choose <strong>Restart Tour</strong>.'
            }
        });

        var bannerPresent = !!document.getElementById('ld-status-banners');
        steps.push({
            element: bannerPresent ? '#ld-status-banners' : undefined,
            popover: {
                title:       '📢 Status Banners',
                description: 'When something is broken that affects lots of people — say, the Wi-Fi is down at a branch — ' +
                             'we post a coloured banner at the very top of every page.' +
                             (bannerPresent
                                ? ' Like this one!'
                                : ' (None is active right now, so there\'s nothing to point at — they only appear during an incident.)') +
                             '<br><br>If a banner already describes your problem, <strong>we know about it</strong> — you don\'t need to ' +
                             'submit a request. Click the ✕ on a banner to hide it for the rest of your session.',
                side:  'bottom',
                align: 'center'
            }
        });

        steps.push({
            element: '.sidebar',
            popover: {
                title:       'Navigation',
                description: 'The sidebar gets you everywhere: <strong>Dashboard</strong> (this page), <strong>My Requests</strong>, ' +
                             'the <strong>Knowledge Base</strong> — a library of help articles that may already answer your question — ' +
                             'and <strong>Help</strong> guides for this portal.',
                side:  'right',
                align: 'center'
            }
        });

        if (visible('#ld-search-wrap')) {
            steps.push({
                element: '#ld-search-wrap',
                popover: {
                    title:       '🔍 Search Everything',
                    description: 'Search your requests <em>and</em> Knowledge Base articles from anywhere. ' +
                                 'Use the tabs in the results to narrow down to just tickets or just articles.<br><br>' +
                                 '<strong>Shortcut:</strong> press <kbd>/</kbd> on any page to jump straight into the search box.',
                    side:  'bottom',
                    align: 'center'
                }
            });
        }

        if (visible('#tour-nav-help')) {
            steps.push({
                element: '#tour-nav-help',
                popover: {
                    title:       'The Help Menu',
                    description: 'Stuck on how the portal itself works? The <strong>Help</strong> menu up here has short guides — ' +
                                 'it\'s always one click away, on every page.',
                    side:  'bottom',
                    align: 'start'
                }
            });
        }

        if (visible('#tour-nav-user')) {
            steps.push({
                element: '#tour-nav-user',
                popover: {
                    title:       'Your Account Menu',
                    description: 'Click your name for <strong>My Profile</strong> (name, password, dark mode, and email preferences), ' +
                                 '<strong>Restart Tour</strong> to see this walkthrough again, and <strong>Sign Out</strong>.',
                    side:  'bottom',
                    align: 'end'
                }
            });
        }

        steps.push({
            element: '#tour-portal-recent',
            popover: {
                title:       'Your Active Requests',
                description: 'Your most recent open requests appear here, with their status and who\'s handling them. ' +
                             'Click any row to open the request and see updates from the team.',
                side:  'top',
                align: 'start'
            }
        });

        steps.push({
            element: '#tour-portal-new-ticket',
            popover: {
                title:       'Start a New Request',
                description: 'This button is how you reach the team. Next, let\'s look at the page that lists ' +
                             '<em>all</em> of your requests.',
                side:  'bottom',
                align: 'end',
                onNextClick: goTo('tickets', '/portal/tickets')
            }
        });

    } else if (tourSection === 'tickets') {

        steps.push({
            popover: {
                title:       'My Requests',
                description: 'Every help request you\'ve ever submitted lives on this page — open ones and finished ones.'
            }
        });

        steps.push({
            element: '#tour-portal-filter-bar',
            popover: {
                title:       'Search & Filter',
                description: 'Type a keyword in <strong>Search</strong> to find a request by its subject. ' +
                             'Narrow the list with the <strong>Status</strong> dropdown (statuses are in plain words — ' +
                             '<em>Submitted</em>, <em>We\'re working on it</em>, <em>Waiting on you</em>, <em>Done</em>) ' +
                             'or by <strong>Priority</strong>, then click <strong>Filter</strong>.',
                side:  'bottom',
                align: 'start'
            }
        });

        if (visible('#tour-portal-scope')) {
            steps.push({
                element: '#tour-portal-scope',
                popover: {
                    title:       '🏢 My Location',
                    description: 'Your account can also see requests from your whole branch. Flip to <strong>My Location</strong> to ' +
                                 'view what <em>anyone</em> at your branch has reported — handy for checking whether someone already ' +
                                 'submitted the problem you\'re about to report, or for following an issue a colleague opened. ' +
                                 'You can open those tickets and even add comments to them.<br><br>' +
                                 'Requests in confidential categories never show up in this view.',
                    side:  'bottom',
                    align: 'start'
                }
            });
        }

        steps.push({
            element: '#tour-portal-ticket-table',
            popover: {
                title:       'Your Requests',
                description: 'Each row shows the request number, subject, status, priority, type, who it\'s assigned to, and when ' +
                             'it was submitted — click any column heading to sort, or click a row to open it.<br><br>' +
                             'Two badges you might spot next to a subject: <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Escalated</span> ' +
                             'means it\'s been raised up the chain, and <span class="badge bg-warning bg-opacity-25 text-dark">✏️ Draft</span> means ' +
                             'you have an unsent comment saved on that request.',
                side:  'top',
                align: 'start'
            }
        });

        steps.push({
            popover: {
                title:       'Let\'s Write One',
                description: 'Time for the main event — the new-request form. Don\'t worry, the tour won\'t actually submit anything.',
                onNextClick: goTo('create', '/portal/tickets/create')
            }
        });

    } else if (tourSection === 'create') {

        steps.push({
            popover: {
                title:       'New Help Request',
                description: 'This form is smarter than it looks: it adapts to what kind of help you need, suggests answers while ' +
                             'you type, and checks for duplicate requests before anything is created. Let\'s walk through it.'
            }
        });

        if (visible('#portalTemplateSelect')) {
            steps.push({
                element: '#portalTemplateSelect',
                popover: {
                    title:       'Start From a Template',
                    description: 'For common requests, pick a template here and the form pre-fills itself — subject, description, ' +
                                 'and type. You just adjust the details and submit.',
                    side:  'bottom',
                    align: 'end'
                }
            });
        }

        steps.push({
            element: '#tour-portal-subject',
            popover: {
                title:       'Subject — and Instant Answers',
                description: 'Give a brief, clear summary. As you type, matching <strong>Knowledge Base articles pop up right below ' +
                             'this box</strong> — your answer might already be written! Click one to read it in a new tab, ' +
                             'or dismiss the suggestions with the ✕.',
                side:  'bottom',
                align: 'start'
            }
        });

        steps.push({
            element: '#tour-portal-description',
            popover: {
                title:       'Description',
                description: 'Describe what happened, when, and what you\'ve already tried. This is a full editor — you can format text, ' +
                             'add lists and links, and <strong>paste screenshots straight in</strong>. ' +
                             'The more detail you give, the faster the team can help.',
                side:  'top',
                align: 'start'
            }
        });

        steps.push({
            element: '#tour-portal-type',
            popover: {
                title:       '🧩 Ticket Type — the Form Changes With It',
                description: '<strong>Each request type has its own form.</strong> Choosing <em>Hardware</em> vs <em>Software</em> vs ' +
                             '<em>Account</em> (for example) rearranges the fields below — some appear, some disappear, and some become ' +
                             'required — so you\'re only ever asked what\'s relevant. Pick the closest match and watch the form adjust; ' +
                             'the type also routes your request to the right team.',
                side:  'bottom',
                align: 'start'
            }
        });

        if (visible('#tour-portal-priority')) {
            steps.push({
                element: '#tour-portal-priority',
                popover: {
                    title:       'How Urgent Is This?',
                    description: 'Pick a priority if you know it — or leave it on <em>"Let our team decide"</em> and we\'ll set it ' +
                                 'when we review your request.',
                    side:  'bottom',
                    align: 'start'
                }
            });
        }

        if (visible('#tour-portal-location')) {
            steps.push({
                element: '#tour-portal-location',
                popover: {
                    title:       'Location',
                    description: 'Pre-filled from your profile. Change it if you\'re reporting something at a different branch — ' +
                                 'it helps the team know where to go.',
                    side:  'bottom',
                    align: 'start'
                }
            });
        }

        if (visible('#tour-portal-tags')) {
            steps.push({
                element: '#tour-portal-tags',
                popover: {
                    title:       'Tags (Optional)',
                    description: 'Add <strong>#keywords</strong> to help categorize your request — type a word and press Enter.',
                    side:  'bottom',
                    align: 'start'
                }
            });
        }

        if (visible('#tour-portal-attachments')) {
            steps.push({
                element: '#tour-portal-attachments',
                popover: {
                    title:       '📎 Attach Files',
                    description: 'Photos, screenshots, PDFs, documents — attach anything that shows the problem. ' +
                                 'A picture of an error message saves a lot of back-and-forth. ' +
                                 'You can also attach more files later when adding comments.',
                    side:  'top',
                    align: 'start'
                }
            });
        }

        steps.push({
            element: '#tour-portal-submit',
            popover: {
                title:       '🤖 Submit — With a Duplicate Check',
                description: 'When you press Submit, an <strong>AI assistant first compares your request with open requests from your ' +
                             'branch</strong>. If it spots likely matches, you\'ll see them <em>before</em> anything is created — each with a ' +
                             'similarity score and a peek at the existing ticket — so you can leave it with the team already working on it, ' +
                             'or click <em>"Create anyway"</em> if yours really is different.<br><br>' +
                             'AI also works behind the scenes to suggest the right team and category so requests get to the right people ' +
                             'faster. Requests in confidential categories are <strong>never</strong> sent to the AI service.',
                side:  'top',
                align: 'start'
            }
        });

        steps.push({
            popover: {
                title:       '💾 Drafts & Undo',
                description: 'Half-written requests are <strong>saved automatically</strong> — close the browser mid-sentence and ' +
                             'everything is restored the next time you open this form. The same goes for comments you start writing ' +
                             'on a ticket.<br><br>Next: let\'s look at what a request looks like <em>after</em> you submit it, ' +
                             'using a practice ticket.',
                onNextClick: goTo('demo', '/portal/tour/demo-ticket')
            }
        });

    } else if (tourSection === 'demo') {

        steps.push({
            popover: {
                title:       '🎓 A Practice Request',
                description: 'This is a <strong>pretend ticket</strong> we use for the tour — it isn\'t real, its buttons are disabled, ' +
                             'and nothing here is saved or sent. But it looks exactly like your real requests will, ' +
                             'so let\'s take it apart piece by piece.'
            }
        });

        steps.push({
            element: '#tour-ticket-status',
            popover: {
                title:       'Status at a Glance',
                description: 'The badges under the title tell you where things stand, in plain words: ' +
                             '<em>Submitted</em> → <em>We\'re working on it</em> → sometimes <em>Waiting on you</em> (we need your reply!) ' +
                             '→ <em>Done</em>. The coloured badge is the priority. ' +
                             'If a request ever gets escalated, a red <em>Escalated</em> badge appears here too.',
                side:  'bottom',
                align: 'start'
            }
        });

        if (visible('#tour-ticket-whatnext')) {
            steps.push({
                element: '#tour-ticket-whatnext',
                popover: {
                    title:       'What Happens Next?',
                    description: 'Brand-new requests show this reassurance box: your request is in the queue, someone will review it and ' +
                                 'reply right on this page, and <strong>you\'ll get an email whenever there\'s an update</strong> — ' +
                                 'no need to phone or email to check in.',
                    side:  'bottom',
                    align: 'start'
                }
            });
        }

        if (visible('#tour-ticket-solution')) {
            steps.push({
                element: '#tour-ticket-solution',
                popover: {
                    title:       '✅ The Answer Banner',
                    description: 'When the team marks one of their replies as <strong>the answer</strong> to your request, this green banner ' +
                                 'appears at the top. Click it to jump straight to the marked answer — no scrolling through the whole ' +
                                 'conversation.',
                    side:  'bottom',
                    align: 'start'
                }
            });
        }

        steps.push({
            element: '#tour-ticket-description',
            popover: {
                title:       'Your Description',
                description: 'What you wrote when you submitted — screenshots and formatting included. ' +
                             'Spotted a typo or forgot a detail? You can edit it; we\'ll get to the Edit button in a moment.',
                side:  'top',
                align: 'start'
            }
        });

        if (visible('#tour-ticket-attachments')) {
            steps.push({
                element: '#tour-ticket-attachments',
                popover: {
                    title:       'Attachments',
                    description: 'Every file on the request — yours and the team\'s — is collected here. ' +
                                 'On a real request, clicking one downloads it.',
                    side:  'top',
                    align: 'start'
                }
            });
        }

        steps.push({
            element: '#tour-ticket-timeline',
            popover: {
                title:       'The Timeline',
                description: 'The full history of the request: comments, status changes, assignments. The reply marked as the ' +
                             '<strong>answer</strong> is highlighted in green. Click the <strong>Timeline</strong> heading to flip between ' +
                             'newest-first and oldest-first, and long histories tuck older updates behind a "show more" link.',
                side:  'top',
                align: 'start'
            }
        });

        steps.push({
            element: '#tour-ticket-comment',
            popover: {
                title:       '💬 Add a Comment — Here or by Email',
                description: 'Reply to the team, answer their questions, or attach more files right here.<br><br>' +
                             '<strong>Even easier:</strong> just <strong>reply to any email</strong> you receive about a request — ' +
                             'your reply is automatically added to the ticket as a comment, exactly as if you\'d typed it here. ' +
                             'No logging in required.',
                side:  'top',
                align: 'start'
            }
        });

        if (visible('#tour-ticket-cc')) {
            steps.push({
                element: '#tour-ticket-cc',
                popover: {
                    title:       'CC — Keep Colleagues in the Loop',
                    description: 'People CC\'d on a request can see it and get email updates as it progresses. ' +
                                 'Some request forms let you CC colleagues when you submit.',
                    side:  'left',
                    align: 'start'
                }
            });
        }

        steps.push({
            element: '#tour-ticket-details',
            popover: {
                title:       'The Details Panel',
                description: 'A quick summary: status, priority, type, branch, and — importantly — <strong>Assigned To</strong>: ' +
                             'the person handling your request. "Unassigned" just means it\'s still in the queue.',
                side:  'left',
                align: 'start'
            }
        });

        steps.push({
            element: '#tour-ticket-edit',
            popover: {
                title:       '✏️ Edit Your Request',
                description: 'Forgot a detail or want to clarify? <strong>Edit</strong> lets you update the subject or description of any ' +
                             'of your requests while they\'re still open. The team sees a note that it was updated.',
                side:  'bottom',
                align: 'end'
            }
        });

        steps.push({
            element: '#escalateBtn',
            popover: {
                title:       '🚨 Escalate — and What an SLA Is',
                description: 'Behind the scenes, every request has <strong>response-time targets called SLAs</strong> ' +
                             '(service-level agreements) — promises like "first reply within a set number of business hours," based on ' +
                             'priority. The clock only runs during business hours and pauses while we\'re waiting on you.<br><br>' +
                             'If your request type has an escalation contact, this <strong>Escalate</strong> button can appear on your open ' +
                             'requests — depending on settings, it may only show up <em>after</em> a response-time target has been missed. ' +
                             'Clicking it sends the request to the next person up the chain, notifies them, and lets you add a short reason. ' +
                             'So if you don\'t see it on a real request, that usually means things are still on track.',
                side:  'bottom',
                align: 'end'
            }
        });

        steps.push({
            element: '#tour-ticket-close',
            popover: {
                title:       'Close It Yourself',
                description: 'Problem sorted itself out, or the answer worked? You can close any of your own requests with this button — ' +
                             'it tells the team it\'s taken care of. Afterwards you may get a short <strong>satisfaction survey</strong> by ' +
                             'email; if the issue ever comes back, just reply or submit a new request.<br><br>' +
                             'One last stop: your profile and email preferences.',
                side:  'bottom',
                align: 'end',
                onNextClick: goTo('profile', '/profile')
            }
        });

    } else {

        steps.push({
            popover: {
                title:       'Your Profile',
                description: 'Update your name, change your password, switch between light and dark mode, and choose whether ticket ' +
                             'timelines show newest or oldest updates first. Everything here saves automatically as you change it.'
            }
        });

        steps.push({
            element: '#tour-portal-notifications',
            popover: {
                title:       '📬 Email Notifications',
                description: 'Choose exactly which emails you receive: confirmation when you submit, when your request is assigned, ' +
                             'when the team replies, CC updates, when a request is resolved or closed, and satisfaction surveys. ' +
                             'Turn off anything you don\'t need — and remember, <strong>any email we send can be replied to directly</strong> ' +
                             'to add a comment to the request.',
                side:  'top',
                align: 'start'
            }
        });

        steps.push({
            popover: {
                title:       '🎉 You\'re All Set!',
                description: 'That\'s the full tour! You now know how to submit a request (and dodge duplicates), read a ticket, ' +
                             'comment here or by email, escalate when needed, close things yourself, and tune your notifications.<br><br>' +
                             'Quick reminders: the <strong>Knowledge Base</strong> in the sidebar may already have your answer, ' +
                             '<kbd>/</kbd> opens search anywhere, <strong>Help</strong> lives in the top menu, and you can replay this tour ' +
                             'from your name menu → <strong>Restart Tour</strong>.'
            }
        });
    }

    // ── Initialise Driver.js ────────────────────────────────────────

    var driverObj = window.driver.js.driver({
        showProgress:   true,
        progressText:   'Step {{current}} of {{total}}',
        allowClose:     true,
        smoothScroll:   true,
        doneBtnText:    tourSection === 'profile' ? 'Finish' : 'Continue →',
        steps:          steps,

        onDestroyStarted: function () {
            if (!isNavigating) {
                localStorage.removeItem('ld_portal_tour_page');
                dismissTour();
            }
            driverObj.destroy();
        }
    });

    // Sections that auto-play on arrival consume their hand-off flag so a
    // later organic visit to the page doesn't unexpectedly restart mid-tour.
    if (tourSection !== 'dashboard') {
        localStorage.removeItem('ld_portal_tour_page');
    }

    driverObj.drive();
})();
</script>
