<?php
/**
 * Portal user onboarding tour (Driver.js spotlight).
 * Included by app.php for all portal-role pages.
 *
 * Tour flow (5 sections across different pages):
 *   dashboard → /portal
 *   tickets   → /portal/tickets
 *   create    → /portal/tickets/create?tour=1   (real form in tour-preview mode:
 *               never submits, KB suggestions live, duplicate check demoed)
 *   demo      → /portal/tour/demo-ticket        (synthetic practice ticket —
 *               lets the tour spotlight the escalate button, answer banner,
 *               attachments etc. without needing a real ticket to exist)
 *   profile   → /profile
 *
 * Robustness:
 *   - Hand-off + "live" + "resume" state lives in sessionStorage, so it is
 *     scoped to this browser session/tab and never leaks into a later visit.
 *   - If the user follows a suggestion that reloads the page (e.g. the
 *     "My Location" toggle), the tour RESUMES on the reloaded page instead of
 *     dying — it stores the next step index before the navigation.
 *   - If the tour is nonetheless left before finishing (✕, Esc, clicking the
 *     dimmed backdrop, or navigating somewhere the tour doesn't cover), a
 *     friendly toast tells the user it ended and how to restart it.
 *   - Closing the tour calls /portal/tour/dismiss, clearing the per-user
 *     show_portal_tour flag; closing the browser mid-tour leaves that flag
 *     set, so the tour re-offers from the start on the next portal visit.
 */
$_portalTourCsrf     = csrfToken();
$_portalTourAutoShow = ($autoShowTour ?? false) ? 'true' : 'false';
?>
<script>
(function () {
    'use strict';

    var HANDOFF = 'ld_portal_tour_page';   // which section to auto-run on the next page
    var LIVE    = 'ld_portal_tour_live';    // a section is currently mid-flight
    var RESUME  = 'ld_portal_tour_resume';  // step index to resume at after a reload

    var autoShow  = <?= $_portalTourAutoShow ?>;
    var path      = window.location.pathname;
    var csrfToken = <?= json_encode($_portalTourCsrf) ?>;

    function store() { try { return window.sessionStorage; } catch (e) { return null; } }
    function ssGet(k) { var s = store(); return s ? s.getItem(k) : null; }
    function ssSet(k, v) { var s = store(); if (s) { try { s.setItem(k, v); } catch (e) {} } }
    function ssDel(k) { var s = store(); if (s) { try { s.removeItem(k); } catch (e) {} } }

    // ── Exit toast — shown when the tour ends before the finish line ────
    function showExitToast() {
        if (document.getElementById('ld-tour-exit-toast')) return;
        var t = document.createElement('div');
        t.id = 'ld-tour-exit-toast';
        t.setAttribute('role', 'status');
        // Start fully visible — a requestAnimationFrame fade-in is throttled to
        // nothing when the tab isn't foregrounded, which would leave the toast
        // invisible. The fade-OUT still animates via the transition property.
        t.style.cssText = 'position:fixed;left:50%;bottom:1.5rem;transform:translateX(-50%);' +
            'z-index:2147483000;max-width:min(92vw,30rem);background:#0f172a;color:#fff;' +
            'padding:.8rem 1rem;border-radius:.65rem;box-shadow:0 10px 30px rgba(2,6,23,.4);' +
            'font-size:.9rem;line-height:1.4;display:flex;gap:.65rem;align-items:flex-start;' +
            'opacity:1;transition:opacity .2s ease;';
        t.innerHTML =
            '<i class="bi bi-info-circle-fill" style="font-size:1.15rem;flex-shrink:0;"></i>' +
            '<div>You’ve left the tour — no problem. You can restart it anytime from your ' +
            'name in the top-right corner → <strong>Restart Tour</strong>.' +
            '<div style="margin-top:.55rem;text-align:right;">' +
            '<button type="button" id="ld-tour-exit-dismiss" class="btn btn-sm btn-light py-0 px-2">Got it</button>' +
            '</div></div>';
        document.body.appendChild(t);
        var kill = function () { t.style.opacity = '0'; setTimeout(function () { if (t.parentNode) t.remove(); }, 250); };
        var btn = document.getElementById('ld-tour-exit-dismiss');
        if (btn) btn.addEventListener('click', kill);
        setTimeout(kill, 10000);
    }

    // ── Slow, visible scrolling ─────────────────────────────────────────
    // Driver.js scrolls the page to each step with the browser's native smooth
    // scroll, which is usually so fast the user never sees the page move and
    // loses their place. While the tour is running we replace that one scroll
    // call with a slower eased animation, so the movement is obvious. Driver
    // repositions the spotlight and popover on every scroll event, so they
    // track the page as it glides down (or up) to the next step.
    var _nativeScrollIntoView = Element.prototype.scrollIntoView;
    var _slowScrollRAF = null;
    function _easeInOutQuad(t) { return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; }
    // Keep the popover out of sight while the page is gliding. Otherwise Driver
    // shows the next step's instruction mid-scroll and it visibly slides up as
    // the page settles — which reads as if a step was skipped. We reveal it only
    // once the page has arrived, so the sequence is: scroll to the section, THEN
    // show the instruction.
    function _setPopoverHidden(hidden) {
        var p = document.querySelector('.driver-popover');
        if (p) p.style.visibility = hidden ? 'hidden' : '';
    }
    function slowScrollIntoView(opts) {
        var el = this;
        if (_slowScrollRAF) { cancelAnimationFrame(_slowScrollRAF); _slowScrollRAF = null; }
        _setPopoverHidden(false);   // clear any leftover hide from an interrupted scroll
        var vh    = window.innerHeight || document.documentElement.clientHeight;
        var rect  = el.getBoundingClientRect();
        var startY = window.pageYOffset || document.documentElement.scrollTop || 0;
        var block = opts && opts.block;
        var targetTop = (block === 'start' || el.offsetHeight > vh)
            ? startY + rect.top - 24
            : startY + rect.top - Math.max(24, (vh - el.offsetHeight) / 2);
        var maxY = Math.max(0, (document.documentElement.scrollHeight || 0) - vh);
        targetTop = Math.max(0, Math.min(targetTop, maxY));
        var dist = targetTop - startY;
        if (Math.abs(dist) < 4) return;   // already in place — nothing to animate
        // Scale duration with distance so short hops stay snappy and long jumps
        // read clearly, capped so the tour never feels sluggish.
        var duration = Math.min(1000, Math.max(450, Math.abs(dist) * 1.4));
        var t0 = null;
        function frame(ts) {
            if (t0 === null) t0 = ts;
            var p = Math.min(1, (ts - t0) / duration);
            _setPopoverHidden(true);   // keep it hidden while the page moves
            window.scrollTo(0, startY + dist * _easeInOutQuad(p));
            if (p < 1) {
                _slowScrollRAF = requestAnimationFrame(frame);
            } else {
                _slowScrollRAF = null;
                _setPopoverHidden(false);   // arrived — now reveal the instruction
            }
        }
        _slowScrollRAF = requestAnimationFrame(frame);
    }
    function enableSlowScroll()  { Element.prototype.scrollIntoView = slowScrollIntoView; }
    function disableSlowScroll() {
        Element.prototype.scrollIntoView = _nativeScrollIntoView;
        if (_slowScrollRAF) { cancelAnimationFrame(_slowScrollRAF); _slowScrollRAF = null; }
        _setPopoverHidden(false);
    }

    // ── Which section (if any) belongs on this page ─────────────────────
    var storedPage = ssGet(HANDOFF);
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

    // No section here. If the tour was mid-flight, the user navigated somewhere
    // it doesn't cover (e.g. opened a search result) — let them know it ended.
    if (!tourSection) {
        if (ssGet(LIVE) === '1') {
            ssDel(LIVE); ssDel(HANDOFF); ssDel(RESUME);
            showExitToast();
        }
        return;
    }

    if (!window.driver || !window.driver.js || !window.driver.js.driver) return;

    ssSet(LIVE, '1');

    var isNavigating = false;   // set when the tour itself is driving a navigation
    var scopeStepIndex = -1;    // index of the "My Location" step (for resume)

    function dismissTour() {
        ssDel(HANDOFF); ssDel(LIVE); ssDel(RESUME);
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
            ssSet(HANDOFF, nextSection);
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

    // Escape admin-configured text (e.g. ticket-type names) before dropping it
    // into a popover, which Driver.js renders as HTML.
    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = (s == null ? '' : String(s));
        return d.innerHTML;
    }

    // The user clicked a real "My Location / My Requests" toggle mid-tour.
    // Let the browser follow it (so they SEE their branch's requests), but
    // arrange for the tour to resume on the reloaded page at the next step.
    function scopeClickHandler() {
        isNavigating = true;
        ssSet(HANDOFF, 'tickets');
        ssSet(RESUME, String((scopeStepIndex >= 0 ? scopeStepIndex : 0) + 1));
    }

    // ── Fake status banner (dashboard section only) ─────────────────────
    // A real-looking incident banner injected purely for the tour, so the
    // "Status Banners" step always has something concrete to point at. It is
    // client-side only, never saved, and vanishes on navigation/refresh.
    function injectFakeBanner() {
        if (document.getElementById('ld-tour-fake-banner')) return;
        var main = document.querySelector('.main-content') || document.body;
        var wrap = document.createElement('div');
        wrap.id = 'ld-tour-fake-banner';
        wrap.className = 'mb-3';
        wrap.innerHTML =
            '<div class="alert alert-warning d-flex align-items-start gap-3 shadow-sm" role="status">' +
                '<div class="flex-shrink-0 fs-4 lh-1 pt-1"><i class="bi bi-exclamation-triangle-fill"></i></div>' +
                '<div class="flex-grow-1 min-w-0">' +
                    '<div class="fw-bold mb-1">Example: Public Wi-Fi is temporarily down at the Main Branch</div>' +
                    '<div>Our team is already aware and working on it — there’s no need to submit a request about this. ' +
                    '(This is a sample banner shown only during the tour.)</div>' +
                    '<div class="small text-muted mt-2 d-flex flex-wrap gap-3">' +
                        '<span><i class="bi bi-globe me-1"></i>All branches</span>' +
                        '<span><i class="bi bi-clock me-1"></i>Example notice</span>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="btn-close flex-shrink-0" aria-label="Hide"></button>' +
            '</div>';
        main.insertBefore(wrap, main.firstChild);
        var x = wrap.querySelector('.btn-close');
        if (x) x.addEventListener('click', function () { wrap.remove(); });
    }
    function removeFakeBanner() {
        var b = document.getElementById('ld-tour-fake-banner');
        if (b) b.remove();
    }

    // ── Step definitions ────────────────────────────────────────────
    var driverObj;   // assigned below; referenced by step callbacks
    var steps = [];

    if (tourSection === 'dashboard') {

        injectFakeBanner();

        steps.push({
            popover: {
                title:       '👋 Welcome to the Support Portal!',
                description: 'This tour walks you through everything you can do here — submitting and tracking help requests, ' +
                             'finding answers yourself, and choosing which emails you get. It takes about five minutes.<br><br>' +
                             '<strong>Tip:</strong> You can restart it anytime — click your name in the top-right corner and choose <strong>Restart Tour</strong>.'
            }
        });

        steps.push({
            element: '#ld-tour-fake-banner',
            popover: {
                title:       '📢 Status Banners',
                description: 'When something is broken that affects lots of people — like the Wi-Fi being down at a branch — ' +
                             'we post a coloured banner at the top of every page. <strong>Here\'s an example.</strong><br><br>' +
                             'If a banner already describes your problem, <strong>we know about it</strong> — you don\'t need to ' +
                             'submit a request. Click the ✕ on a banner to hide it for the rest of your session. ' +
                             '(This sample banner disappears as soon as the tour moves on.)',
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
                                 '<strong>Shortcut:</strong> press <kbd>/</kbd> on any page to jump straight into the search box. ' +
                                 '(Feel free to give it a real try once the tour is finished.)',
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

        // A brand-new user taking the tour usually has no requests yet, so the
        // card shows its empty state. Match the copy to what's on screen instead
        // of telling them to "click a row" that isn't there.
        var hasRecentRows = document.querySelectorAll('#tour-portal-recent tbody tr').length > 0;
        steps.push({
            element: '#tour-portal-recent',
            popover: {
                title:       'Your Active Requests',
                description: hasRecentRows
                    ? 'Your most recent open requests appear here, with their status and who\'s handling them. ' +
                      'Click any row to open the request and see updates from the team.'
                    : 'Once you submit a request, it\'ll show up here — with its status and who\'s handling it — ' +
                      'so you can check on it at a glance. It\'s empty right now because you haven\'t submitted anything yet.',
                side:  'top',
                align: 'start'
            }
        });

        steps.push({
            element: '#tour-portal-new-ticket',
            popover: {
                title:       'Start a New Request',
                description: 'This button is how you reach the team.<br><br>' +
                             '➡️ <strong>Click Next</strong> and we\'ll leave the Dashboard and take you to your ' +
                             '<strong>My Requests</strong> page (you\'ll find it in the sidebar), which lists ' +
                             '<em>all</em> of your requests.',
                side:  'bottom',
                align: 'end',
                onNextClick: goTo('tickets', '/portal/tickets')
            }
        });

    } else if (tourSection === 'tickets') {

        steps.push({
            popover: {
                title:       '📍 New page: My Requests',
                description: '<strong>Notice the page changed</strong> — we\'ve moved you from the Dashboard to your ' +
                             '<strong>My Requests</strong> page (the one in the sidebar). Every help request you\'ve ' +
                             'ever submitted lives here — open ones and finished ones.'
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
                    description: 'Your account can also see requests from your whole branch. <strong>Go ahead and click ' +
                                 '"My Location"</strong> to view what <em>anyone</em> at your branch has reported — handy for checking ' +
                                 'whether someone already submitted the problem you\'re about to report, or for following an issue a ' +
                                 'colleague opened. You can open those tickets and even comment on them.<br><br>' +
                                 'The tour will keep going after the page refreshes. (Requests in confidential categories never appear here.)',
                    side:  'bottom',
                    align: 'start'
                },
                onHighlighted: function () {
                    document.querySelectorAll('#tour-portal-scope a').forEach(function (a) {
                        if (a.__tourBound) return;
                        a.__tourBound = true;
                        a.addEventListener('click', scopeClickHandler);
                    });
                },
                onDeselected: function () {
                    document.querySelectorAll('#tour-portal-scope a').forEach(function (a) {
                        a.removeEventListener('click', scopeClickHandler);
                        a.__tourBound = false;
                    });
                }
            });
            scopeStepIndex = steps.length - 1;
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
                description: 'Time for the main event — the new-request form.<br><br>' +
                             '➡️ <strong>Click Next</strong> and we\'ll take you to the <strong>New Request</strong> page ' +
                             '(the same one that <strong>"New Request"</strong> button opens). Don\'t worry, the tour won\'t ' +
                             'actually submit anything.',
                onNextClick: goTo('create', '/portal/tickets/create?tour=1')
            }
        });

    } else if (tourSection === 'create') {

        steps.push({
            popover: {
                title:       '📍 New page: New Request',
                description: 'We\'ve moved you to the <strong>New Request form</strong>. This form is smarter than it looks: it ' +
                             'adapts to what kind of help you need, suggests answers while you type, and checks for duplicate ' +
                             'requests before anything is created. Let\'s walk through it — nothing here will actually be submitted.'
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
                             'this box</strong> — your answer might already be written!<br><br>' +
                             'We\'ve typed <strong>"hotspot"</strong> for you as an example — take a look at the related articles that ' +
                             'appeared. On a real request, click one to read it in a new tab, or dismiss the suggestions with the ✕.',
                side:  'bottom',
                align: 'start'
            },
            onHighlighted: function () {
                var subj = document.getElementById('subject');
                if (subj && subj.value.trim() === '') {
                    subj.value = 'hotspot';
                    subj.dispatchEvent(new Event('input', { bubbles: true }));
                    // Give the debounced KB lookup time to return, then grow the
                    // spotlight so the suggestion box is included (not dimmed).
                    setTimeout(function () { if (driverObj && driverObj.refresh) driverObj.refresh(); }, 900);
                }
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

        var typeDesc = '<strong>Each request type has its own form.</strong> Choosing <em>Hardware</em> vs <em>Software</em> vs ' +
                       '<em>Account</em> (for example) rearranges the fields below — some appear, some disappear, and some become ' +
                       'required — so you\'re only ever asked what\'s relevant. Pick the closest match and watch the form adjust; ' +
                       'the type also routes your request to the right team.';

        // If the helpdesk has one or more "No Wrong Door" types (AI picks the
        // best team from what you wrote), explain them right here — this is set
        // by the create page and only present when AI routing is actually live.
        var nwdTypes = Array.isArray(window.__ldNoWrongDoorTypes) ? window.__ldNoWrongDoorTypes : [];
        if (nwdTypes.length) {
            var nwdNames = nwdTypes.map(function (n) { return '<strong>&ldquo;' + escHtml(n) + '&rdquo;</strong>'; });
            var nwdList = nwdNames.length === 1
                ? nwdNames[0]
                : nwdNames.slice(0, -1).join(', ') + ' or ' + nwdNames[nwdNames.length - 1];
            typeDesc += '<br><br><strong>🧭 Not sure which type to pick?</strong> Choose ' + nwdList + '. ' +
                        'It\'s a special <em>&ldquo;No Wrong Door&rdquo;</em> option — instead of you guessing who handles your issue, ' +
                        'our AI reads what you wrote and automatically sends the request to the team best suited to help. ' +
                        'If it isn\'t sure, a real person still steps in, so your request never gets lost.';
        }

        steps.push({
            element: '#tour-portal-type',
            popover: {
                title:       '🧩 Ticket Type — the Form Changes With It',
                description: typeDesc,
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
                             'branch</strong>. AI also works behind the scenes to suggest the right team and category so requests reach the ' +
                             'right people faster. Requests in confidential categories are <strong>never</strong> sent to the AI service.<br><br>' +
                             'Click <strong>Next</strong> and we\'ll show you exactly what that duplicate check looks like.',
                side:  'top',
                align: 'start',
                // onNextClick is a popover-level hook in Driver.js. Reveal the
                // (canned) duplicate warning, then advance to the step that
                // spotlights it.
                onNextClick: function () {
                    if (typeof window._tourShowDupDemo === 'function') {
                        window._tourShowDupDemo();
                        setTimeout(function () { if (driverObj) driverObj.moveNext(); }, 450);
                    } else if (driverObj) {
                        driverObj.moveNext();
                    }
                }
            }
        });

        steps.push({
            element: '#dup-warning',
            popover: {
                title:       '👀 What a Duplicate Looks Like',
                description: 'Because this request looks like an existing <em>"hotspot"</em> ticket at your branch, we flag it ' +
                             '<strong>before</strong> creating anything — with a similarity score and a peek at the existing ticket. ' +
                             'You can leave it with the team already working on it, or choose <em>"Create anyway"</em> if yours really is ' +
                             'different. This saves everyone from chasing the same problem twice.',
                side:  'top',
                align: 'start'
            },
            onHighlighted: function () {
                setTimeout(function () { if (driverObj && driverObj.refresh) driverObj.refresh(); }, 120);
            }
        });

        steps.push({
            popover: {
                title:       '💾 Drafts & Undo',
                description: 'Half-written requests are <strong>saved automatically</strong> — close the browser mid-sentence and ' +
                             'everything is restored the next time you open this form. The same goes for comments you start writing ' +
                             'on a ticket.<br><br>➡️ <strong>Click Next</strong> and we\'ll leave this form and open a ' +
                             '<strong>practice request</strong>, so you can see what a request looks like <em>after</em> you submit it.',
                onNextClick: goTo('demo', '/portal/tour/demo-ticket')
            }
        });

    } else if (tourSection === 'demo') {

        steps.push({
            popover: {
                title:       '📍 New page: A Practice Request',
                description: 'We\'ve opened a new page — this is a <strong>pretend ticket</strong> we use for the tour. It isn\'t real, ' +
                             'its buttons are disabled, and nothing here is saved or sent. But it looks exactly like your real requests ' +
                             'will, so let\'s take it apart piece by piece.'
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
                             '➡️ One last stop — <strong>click Next</strong> and we\'ll take you to your <strong>Profile</strong> page ' +
                             'and email preferences (normally reached from your name menu in the top-right corner).',
                side:  'bottom',
                align: 'end',
                onNextClick: goTo('profile', '/profile')
            }
        });

    } else {

        // The tour jumped straight to /profile, so first make it clear HOW a user
        // reaches this page on their own: the account menu (top-right). Point at
        // that menu when it's on screen; fall back to a centred note on mobile,
        // where it's tucked inside the collapsed navbar.
        if (visible('#tour-nav-user')) {
            steps.push({
                element: '#tour-nav-user',
                popover: {
                    title:       '📍 New page: Your Profile',
                    description: 'We brought you straight here, but on your own you\'d get to this page by clicking ' +
                                 '<strong>your name in the top-right corner</strong> and choosing <strong>My Profile</strong>. ' +
                                 'That same menu is also where <strong>Restart Tour</strong> and <strong>Sign Out</strong> live.',
                    side:  'bottom',
                    align: 'end'
                }
            });
        } else {
            steps.push({
                popover: {
                    title:       '📍 New page: Your Profile',
                    description: 'We brought you straight here, but on your own you\'d get to this page by opening the menu under ' +
                                 '<strong>your name in the top-right corner</strong> and choosing <strong>My Profile</strong>. ' +
                                 'That same menu is also where <strong>Restart Tour</strong> and <strong>Sign Out</strong> live.'
                }
            });
        }

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

    driverObj = window.driver.js.driver({
        showProgress:   true,
        progressText:   'Step {{current}} of {{total}}',
        allowClose:     true,
        smoothScroll:   true,
        doneBtnText:    tourSection === 'profile' ? 'Finish' : 'Continue →',
        steps:          steps,

        onDestroyStarted: function () {
            removeFakeBanner();
            disableSlowScroll();

            // The tour itself is navigating to the next section — keep state.
            if (isNavigating) { driverObj.destroy(); return; }

            // Distinguish "finished" (Done pressed on the last step) from a
            // genuine bail-out (✕ / Esc / backdrop click). Only warn on bail.
            var idx  = (typeof driverObj.getActiveIndex === 'function') ? driverObj.getActiveIndex() : 0;
            var last = steps.length - 1;
            var finished = (tourSection === 'profile') && idx >= last;

            if (!finished) showExitToast();
            dismissTour();
            driverObj.destroy();
        }
    });

    // Resume support: if we arrived here because the user followed a
    // reload-inducing suggestion (e.g. the My Location toggle), pick up at the
    // stored step instead of restarting the section.
    var startIndex = 0;
    var resumeRaw = ssGet(RESUME);
    if (resumeRaw !== null && resumeRaw !== '') {
        startIndex = Math.max(0, Math.min(steps.length - 1, parseInt(resumeRaw, 10) || 0));
        ssDel(RESUME);
    }

    enableSlowScroll();
    driverObj.drive(startIndex);
})();
</script>
