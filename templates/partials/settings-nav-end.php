    </div><!-- /.flex-grow-1 (settings content) -->
</div><!-- /.d-flex (settings layout) -->

<script>
window.__settingsSearchIndex = <?= json_encode($settingsSearchIndex, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<style>
    /* Result rows in the Chrome-style settings search dropdown */
    .settings-result {
        display: block;
        padding: .5rem .75rem;
        border-bottom: 1px solid var(--bs-border-color-translucent, rgba(0,0,0,.075));
        text-decoration: none;
        color: inherit;
        cursor: pointer;
    }
    .settings-result:last-child { border-bottom: 0; }
    .settings-result:hover,
    .settings-result.is-active {
        background: var(--bs-secondary-bg, #f5f5f5);
        color: inherit;
    }
    .settings-result-label {
        font-weight: 600;
        margin-bottom: .15rem;
        line-height: 1.3;
    }
    .settings-result-crumb {
        font-size: .7rem;
        color: var(--bs-secondary-color, #6c757d);
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: .15rem;
        line-height: 1.2;
    }
    .settings-result-desc {
        font-size: .75rem;
        color: var(--bs-secondary-color, #6c757d);
        line-height: 1.35;
    }
    .settings-result mark {
        background: #fff3cd;
        padding: 0 1px;
        border-radius: 2px;
        color: inherit;
    }
    /* Flash-highlight target setting on landing */
    @keyframes settingFlashKf {
        0%   { box-shadow: 0 0 0 6px rgba(255, 215, 0, .55); background-color: rgba(255, 243, 205, .9); }
        70%  { box-shadow: 0 0 0 6px rgba(255, 215, 0, .25); background-color: rgba(255, 243, 205, .55); }
        100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0);     background-color: transparent; }
    }
    .setting-flash {
        animation: settingFlashKf 2.4s ease-out;
        border-radius: 4px;
    }
</style>

<script>
(function () {
    var input = document.getElementById('settingsNavSearch');
    if (!input) return;

    var groups       = document.querySelectorAll('.settings-nav-group');
    var noMatch      = document.getElementById('settingsNavNoMatch');
    var resultsBox   = document.getElementById('settingsSearchResults');
    var navList      = document.getElementById('settingsNavList');
    var STORE_KEY    = 'settingsNavSearchQuery';
    var INDEX        = (window.__settingsSearchIndex || []).map(function (e) {
        // Pre-compute a single haystack per entry for fast scoring.
        return Object.assign({}, e, {
            _hay: (
                (e.label       || '') + ' ' +
                (e.description || '') + ' ' +
                (e.section     || '') + ' ' +
                (e.page_label  || '') + ' ' +
                (e.group       || '') + ' ' +
                (e.keywords    || '')
            ).toLowerCase()
        });
    });

    var activeIdx = -1;
    var lastResults = [];

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }

    function highlight(text, query) {
        if (!query) return escapeHtml(text);
        var lower = text.toLowerCase();
        var idx = lower.indexOf(query);
        if (idx === -1) return escapeHtml(text);
        return escapeHtml(text.slice(0, idx))
             + '<mark>' + escapeHtml(text.slice(idx, idx + query.length)) + '</mark>'
             + escapeHtml(text.slice(idx + query.length));
    }

    function score(entry, query) {
        // Lower = better match; -1 = no match.
        if (!entry._hay.includes(query)) return -1;
        var label  = (entry.label || '').toLowerCase();
        var labelIdx = label.indexOf(query);
        if (labelIdx === 0)        return 0;     // label starts with query
        if (labelIdx > 0)          return 1;     // label contains query
        if ((entry.section || '').toLowerCase().includes(query))    return 2;
        if ((entry.page_label || '').toLowerCase().includes(query)) return 3;
        if ((entry.description || '').toLowerCase().includes(query)) return 4;
        return 5; // matched only via keywords/group
    }

    function searchIndex(query) {
        var scored = [];
        for (var i = 0; i < INDEX.length; i++) {
            var s = score(INDEX[i], query);
            if (s >= 0) scored.push({ entry: INDEX[i], score: s, ord: i });
        }
        scored.sort(function (a, b) { return a.score - b.score || a.ord - b.ord; });
        return scored.slice(0, 25).map(function (x) { return x.entry; });
    }

    function buildResultsHtml(entries, query) {
        if (!entries.length) {
            return '<div class="text-muted small px-3 py-2">No matching settings.</div>';
        }
        return entries.map(function (e, i) {
            var url = e.page_url + (e.anchor ? '#' + e.anchor : '');
            var crumb = (e.group ? e.group + ' › ' : '')
                      + (e.page_label || '')
                      + (e.section && e.section !== e.page_label ? ' › ' + e.section : '');
            return '<a class="settings-result' + (i === activeIdx ? ' is-active' : '') + '"'
                 + ' href="' + escapeHtml(url) + '"'
                 + ' role="option" data-idx="' + i + '">'
                 + '<div class="settings-result-label">' + highlight(e.label || '', query) + '</div>'
                 + '<div class="settings-result-crumb">' + escapeHtml(crumb) + '</div>'
                 + (e.description
                       ? '<div class="settings-result-desc">' + highlight(e.description, query) + '</div>'
                       : '')
                 + '</a>';
        }).join('');
    }

    function renderResults(query) {
        if (!query) {
            resultsBox.style.display = 'none';
            resultsBox.innerHTML = '';
            lastResults = [];
            activeIdx = -1;
            return;
        }
        lastResults = searchIndex(query);
        activeIdx = lastResults.length ? 0 : -1;
        resultsBox.innerHTML = buildResultsHtml(lastResults, query);
        resultsBox.style.display = '';
    }

    function applyNavFilter(raw) {
        var query = (raw || '').trim().toLowerCase();
        var anyVisible = false;

        groups.forEach(function (group) {
            var groupItems = group.querySelectorAll('.settings-nav-item');
            var groupHasMatch = false;

            groupItems.forEach(function (el) {
                if (!query) {
                    el.hidden = false;
                    return;
                }
                var hay = el.getAttribute('data-search') || '';
                var match = hay.indexOf(query) !== -1;
                el.hidden = !match;
                if (match) groupHasMatch = true;
            });

            group.hidden = query ? !groupHasMatch : false;
            if (groupHasMatch) anyVisible = true;
        });

        // Hide the "no nav matches" message when we have setting-level results to show instead.
        if (noMatch) {
            var hasSettingResults = lastResults.length > 0;
            noMatch.hidden = !(query && !anyVisible && !hasSettingResults);
            noMatch.style.display = noMatch.hidden ? 'none' : '';
        }
    }

    function runSearch(raw) {
        var query = (raw || '').trim().toLowerCase();
        renderResults(query);   // populate dropdown first so applyNavFilter can read lastResults
        applyNavFilter(raw);
    }

    // Restore persisted query (filter survives navigation between settings pages).
    try {
        var saved = sessionStorage.getItem(STORE_KEY);
        if (saved) {
            input.value = saved;
            runSearch(saved);
        }
    } catch (_) {}

    input.addEventListener('input', function () {
        try { sessionStorage.setItem(STORE_KEY, input.value); } catch (_) {}
        runSearch(input.value);
    });

    // Keyboard navigation in the results dropdown.
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            input.value = '';
            try { sessionStorage.removeItem(STORE_KEY); } catch (_) {}
            runSearch('');
            input.blur();
            return;
        }
        if (!lastResults.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIdx = (activeIdx + 1) % lastResults.length;
            updateActiveRow();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = (activeIdx - 1 + lastResults.length) % lastResults.length;
            updateActiveRow();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && lastResults[activeIdx]) {
                var e2 = lastResults[activeIdx];
                window.location.href = e2.page_url + (e2.anchor ? '#' + e2.anchor : '');
            }
        }
    });

    function updateActiveRow() {
        var rows = resultsBox.querySelectorAll('.settings-result');
        rows.forEach(function (r, i) {
            r.classList.toggle('is-active', i === activeIdx);
            if (i === activeIdx) {
                var rTop = r.offsetTop;
                var rBot = rTop + r.offsetHeight;
                if (rTop < resultsBox.scrollTop)                            resultsBox.scrollTop = rTop;
                else if (rBot > resultsBox.scrollTop + resultsBox.clientHeight) resultsBox.scrollTop = rBot - resultsBox.clientHeight;
            }
        });
    }

    // Hovering a row makes it the active one (keeps keyboard + mouse in sync).
    resultsBox.addEventListener('mousemove', function (e) {
        var row = e.target.closest('.settings-result');
        if (!row) return;
        var idx = parseInt(row.getAttribute('data-idx'), 10);
        if (idx !== activeIdx) {
            activeIdx = idx;
            updateActiveRow();
        }
    });

    // Close dropdown when focus/click leaves the search area.
    document.addEventListener('click', function (e) {
        if (!resultsBox || resultsBox.style.display === 'none') return;
        if (input.contains(e.target) || resultsBox.contains(e.target)) return;
        resultsBox.style.display = 'none';
    });
    input.addEventListener('focus', function () {
        if (lastResults.length) resultsBox.style.display = '';
    });

    // Global shortcuts — "/" or Ctrl/Cmd+K to focus, anywhere outside an input.
    document.addEventListener('keydown', function (e) {
        var tag = (e.target && e.target.tagName || '').toLowerCase();
        var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable);
        if (!typing && e.key === '/') {
            e.preventDefault();
            input.focus();
            input.select();
        } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });

    // ----- Flash-highlight the target setting on landing (e.g. /admin/settings#smtp_host) -----
    function flashHash() {
        var hash = window.location.hash.replace(/^#/, '');
        if (!hash) return;
        var target = document.getElementById(hash);
        if (!target) return;

        // For form fields, flash the closest visible row/card so the highlight is
        // actually visible (a bare <input> often has no background).
        var flashTarget = target.closest('.mb-3, .mb-4, .row, .form-check, .col-md-6, .col-md-4, .col-md-8, .card-body, .card') || target;

        try { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) { target.scrollIntoView(); }
        // Focus inputs (but not file inputs, which can throw on programmatic focus on some browsers)
        if (target.tagName && /^(INPUT|SELECT|TEXTAREA)$/.test(target.tagName) && target.type !== 'file') {
            try { target.focus({ preventScroll: true }); } catch (_) {}
        }
        flashTarget.classList.remove('setting-flash');
        // Force reflow so the animation re-triggers if the hash points at the same element again.
        // eslint-disable-next-line no-unused-expressions
        flashTarget.offsetWidth;
        flashTarget.classList.add('setting-flash');
        setTimeout(function () { flashTarget.classList.remove('setting-flash'); }, 2600);
    }
    if (window.location.hash) {
        // Defer so the page has laid out (some pages have heavy CSS / Bootstrap tabs).
        setTimeout(flashHash, 50);
    }
    window.addEventListener('hashchange', flashHash);
})();
</script>
