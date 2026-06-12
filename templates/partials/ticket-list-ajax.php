<?php
/**
 * AJAX filter / sort / pagination for the agent + admin ticket lists.
 *
 * Clicking a filter checkbox, applied-filter pill, saved filter, sort header,
 * or pagination link re-fetches the list URL in the background and swaps the
 * updated regions into the page instead of doing a full reload:
 *
 *   #ticketCount         header badge ("N filtered of M total")
 *   #filterCount         filter-button badge (active filter count)
 *   #filterPanelDynamic  applied pills + saved filters + save button
 *   #ticketResults       the table / inbox / card list
 *   #ticketPager         "Showing X–Y of Z" + per-page + page links
 *
 * The URL is kept in sync via history.pushState (so links stay shareable and
 * back/forward still work), each update is announced through an aria-live
 * region, and focus is preserved — or moved somewhere predictable when the
 * focused element was swapped out — instead of being reset by a page load.
 * Any fetch failure falls back to a normal full navigation.
 *
 * Expects $ticketListScope ('agent' | 'admin') in the including template.
 */
$ticketListScope = $ticketListScope ?? 'agent';
?>
<div id="ticketListLive" class="visually-hidden" role="status" aria-live="polite"></div>
<script>
(function () {
    var SCOPE = <?= json_encode($ticketListScope) ?>;
    var BASE  = '/' + SCOPE + '/tickets';
    var FILTER_KEY  = 'ticketFilter_' + SCOPE;
    var SESSION_KEY = SCOPE + 'TicketListUrl';
    var REGIONS = ['ticketCount', 'filterCount', 'filterPanelDynamic', 'ticketResults', 'ticketPager'];
    var NON_FILTER_PARAMS = ['page', 'sort', 'dir', 'per_page', 'reset'];
    var seq = 0; // newest refresh wins when clicks overlap in flight

    // Mirror what a full page load records (see the script at the top of the
    // template) so remember-today's-filter behaviour survives ajax updates.
    function rememberUrl() {
        var today = new Date().toISOString().slice(0, 10);
        var search = window.location.search;
        var cleared = search === '' || new URLSearchParams(search).get('reset') === '1';
        try {
            localStorage.setItem(FILTER_KEY, JSON.stringify({ date: today, query: cleared ? '' : search, cleared: cleared }));
            sessionStorage.setItem(SESSION_KEY, window.location.href);
        } catch (e) {}
    }

    // Links/inputs outside the swapped regions that embed the filter set: the
    // columns-form return URL and the admin CSV export link.
    function syncStaticRefs() {
        document.querySelectorAll('input[name="_redirect"]').forEach(function (inp) {
            inp.value = window.location.pathname + window.location.search;
        });
        var exportLink = document.querySelector('a[href^="' + BASE + '/export"]');
        if (exportLink) {
            var qs = new URLSearchParams(window.location.search);
            ['page', 'per_page', 'reset'].forEach(function (k) { qs.delete(k); });
            exportLink.setAttribute('href', BASE + '/export' + (qs.toString() ? '?' + qs.toString() : ''));
        }
    }

    // The save-filter modal posts the filters as hidden inputs; rebuild them
    // from the live URL each time it opens so it saves what's applied *now*.
    var saveModal = document.getElementById('saveFilterModal');
    if (saveModal) {
        saveModal.addEventListener('show.bs.modal', function () {
            var box = document.getElementById('saveFilterParams');
            if (!box) return;
            box.innerHTML = '';
            new URLSearchParams(window.location.search).forEach(function (v, k) {
                if (v === '' || NON_FILTER_PARAMS.indexOf(k.replace(/\[\]$/, '')) !== -1) return;
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = k; inp.value = v;
                box.appendChild(inp);
            });
        });
    }

    function reinit() {
        if (window.ldMeasureTicketTable) window.ldMeasureTicketTable();
        if (window.LDColResize && window.LDColResize.init) window.LDColResize.init();
        if (window.ldBindQuickEdit) window.ldBindQuickEdit();
        if (window.ldInboxHoverInit) window.ldInboxHoverInit();
        if (window.clearSelection) window.clearSelection(); // selected rows may no longer be listed
    }

    function announce() {
        var live = document.getElementById('ticketListLive');
        var count = document.getElementById('ticketCount');
        if (live) live.textContent = 'Ticket list updated. ' + (count ? count.textContent.replace(/\s+/g, ' ').trim() : '');
    }

    function refresh(url, opts) {
        opts = opts || {};
        var mySeq = ++seq;
        var results = document.getElementById('ticketResults');
        if (results) { results.setAttribute('aria-busy', 'true'); results.style.opacity = '0.5'; }
        fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) {
                if (mySeq !== seq) return; // superseded by a newer refresh
                var doc = new DOMParser().parseFromString(html, 'text/html');
                // e.g. session expired and this is the login page — bail to a real navigation
                if (!doc.getElementById('ticketResults')) throw new Error('unexpected response');
                REGIONS.forEach(function (id) {
                    var src = doc.getElementById(id);
                    var dst = document.getElementById(id);
                    if (src && dst) dst.innerHTML = src.innerHTML;
                });
                if (opts.syncForm) {
                    // Change came from outside the filter form (pill, saved
                    // filter, clear, back/forward) — bring its inputs in line.
                    var srcForm = doc.getElementById('filterForm');
                    var dstForm = document.getElementById('filterForm');
                    if (srcForm && dstForm) dstForm.innerHTML = srcForm.innerHTML;
                }
                if (opts.push !== false) history.pushState({ ldTicketList: true }, '', url);
                rememberUrl();
                syncStaticRefs();
                reinit();
                var res = document.getElementById('ticketResults');
                if (res) { res.removeAttribute('aria-busy'); res.style.opacity = ''; }
                if (opts.refocus && (!document.activeElement || document.activeElement === document.body)) {
                    var el = opts.refocus();
                    if (el) {
                        if (!el.matches('a, button, input, select, textarea, [tabindex]')) el.setAttribute('tabindex', '-1');
                        el.focus();
                    }
                }
                announce();
            })
            .catch(function () {
                if (mySeq === seq) window.location.href = url;
            });
    }

    function formUrl(form) {
        var qs = new URLSearchParams();
        new FormData(form).forEach(function (v, k) { if (v !== '') qs.append(k, v); });
        var s = qs.toString();
        return (form.getAttribute('action') || BASE) + (s ? '?' + s : '');
    }

    var filterForm = document.getElementById('filterForm');
    if (filterForm) {
        // Auto-apply on checkbox/date change (search still uses Enter / Apply),
        // now without the full page reload. Focus stays on the input the user
        // just toggled.
        filterForm.addEventListener('change', function (e) {
            if (e.target.matches('input[type="checkbox"], input[type="date"]')) refresh(formUrl(filterForm));
        });
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            refresh(formUrl(filterForm));
        });
    }

    function focusPanel() {
        var panel = document.getElementById('filterPanel');
        if (panel && panel.classList.contains('open')) return panel.querySelector('.filter-panel-header .btn-close');
        return document.getElementById('filterPanelBtn');
    }

    // After a sort/page click the equivalent link usually still exists (same
    // href); refocus it, else land on the results container.
    function sameLinkOrResults(href) {
        return function () {
            var links = document.querySelectorAll('#ticketResults a, #ticketPager a');
            for (var i = 0; i < links.length; i++) {
                if (links[i].getAttribute('href') === href) return links[i];
            }
            return document.getElementById('ticketResults');
        };
    }

    // Applied-filter pills, Clear, saved filters, sort headers, page links —
    // any same-page list link inside the panel, results, or pager. Ticket
    // links (/tickets/123) have a different pathname and navigate normally.
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest('a');
        if (!a || a.target || !a.closest('#filterPanel, #ticketResults, #ticketPager')) return;
        var u;
        try { u = new URL(a.href, window.location.origin); } catch (_) { return; }
        if (u.origin !== window.location.origin || u.pathname !== BASE) return;
        e.preventDefault();
        var fromPanel = !!a.closest('#filterPanel');
        refresh(u.pathname + u.search, {
            syncForm: fromPanel,
            refocus: fromPanel ? focusPanel : sameLinkOrResults(a.getAttribute('href'))
        });
    });

    // Per-page selector (agent list) — replaces its form's inline submit.
    document.addEventListener('change', function (e) {
        if (e.target.matches('#ticketPager select[name="per_page"]') && e.target.form) {
            refresh(formUrl(e.target.form), {
                refocus: function () { return document.querySelector('#ticketPager select[name="per_page"]'); }
            });
        }
    });

    window.addEventListener('popstate', function () {
        if (window.location.pathname !== BASE) return;
        refresh(window.location.pathname + window.location.search, { push: false, syncForm: true });
    });
})();
</script>
