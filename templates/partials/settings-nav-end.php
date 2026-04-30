    </div><!-- /.flex-grow-1 (settings content) -->
</div><!-- /.d-flex (settings layout) -->

<script>
(function () {
    var input = document.getElementById('settingsNavSearch');
    if (!input) return;

    var groups    = document.querySelectorAll('.settings-nav-group');
    var noMatch   = document.getElementById('settingsNavNoMatch');
    var STORE_KEY = 'settingsNavSearchQuery';

    function applyFilter(raw) {
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

        if (noMatch) {
            noMatch.hidden = !(query && !anyVisible);
            noMatch.style.display = noMatch.hidden ? 'none' : '';
        }
    }

    // Restore persisted query so filter survives page navigation between settings pages.
    try {
        var saved = sessionStorage.getItem(STORE_KEY);
        if (saved) {
            input.value = saved;
            applyFilter(saved);
        }
    } catch (_) { /* sessionStorage may be unavailable */ }

    input.addEventListener('input', function () {
        try { sessionStorage.setItem(STORE_KEY, input.value); } catch (_) {}
        applyFilter(input.value);
    });

    // ESC to clear, "/" or Ctrl/Cmd+K to focus.
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            input.value = '';
            try { sessionStorage.removeItem(STORE_KEY); } catch (_) {}
            applyFilter('');
            input.blur();
        }
    });
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
})();
</script>
