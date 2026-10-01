/**
 * `cc` custom field — multi-person directory autocomplete.
 *
 * Type a name or an email; pick a match with Enter, Tab, `;` or `,` (or a
 * click) and it becomes a badge with a hidden `cc_field_<id>[]` input behind
 * it. The box clears so the next person can be typed straight away.
 *
 * Shared by the portal and the agent/admin create forms. The partial that
 * draws the markup is templates/partials/custom-field-input.php.
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function initOne(input) {
        var fieldId = input.dataset.fieldId;
        var drop    = document.getElementById('cc_drop_' + fieldId);
        var badges  = document.getElementById('cc_badges_' + fieldId);
        var hidden  = document.getElementById('cc_hidden_' + fieldId);
        if (!drop || !badges || !hidden) return;

        var ccSet   = {};
        var timer   = null;
        var results = [];
        var active  = -1;

        function renderBadges() {
            badges.innerHTML = '';
            Object.values(ccSet).forEach(function (u) {
                var b = document.createElement('span');
                b.className = 'badge bg-secondary d-inline-flex align-items-center gap-1 py-1 px-2';
                b.innerHTML = escapeHtml(u.first_name + ' ' + u.last_name)
                            + ' <span class="opacity-75 small">&lt;' + escapeHtml(u.email) + '&gt;</span>'
                            + ' <button type="button" class="btn-close btn-close-white ms-1" style="font-size:.55rem;" aria-label="Remove"></button>';
                b.querySelector('.btn-close').addEventListener('click', function () {
                    delete ccSet[u.id]; renderBadges(); renderHidden();
                });
                badges.appendChild(b);
            });
        }

        function renderHidden() {
            hidden.innerHTML = '';
            Object.keys(ccSet).forEach(function (id) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'cc_field_' + fieldId + '[]';
                inp.value = id;
                hidden.appendChild(inp);
            });
        }

        function close() {
            drop.style.display = 'none'; drop.innerHTML = ''; active = -1; results = [];
        }

        function addUser(u) {
            if (!ccSet[u.id]) { ccSet[u.id] = u; renderBadges(); renderHidden(); }
            input.value = ''; close();
        }

        function setActive(idx) {
            active = idx;
            drop.querySelectorAll('.mention-item').forEach(function (el, i) {
                el.classList.toggle('active', i === idx);
            });
        }

        function render(data) {
            results = data; active = -1;
            if (!data.length) { close(); return; }
            drop.innerHTML = data.map(function (u, i) {
                return '<div class="mention-item" data-index="' + i + '">'
                     +   '<span class="mention-name">' + escapeHtml(u.first_name + ' ' + u.last_name) + '</span> '
                     +   '<span class="text-muted" style="font-size:.75rem;">' + escapeHtml(u.email) + '</span>'
                     + '</div>';
            }).join('');
            drop.style.display = 'block';
            drop.querySelectorAll('.mention-item').forEach(function (el) {
                el.addEventListener('mousedown', function (ev) {
                    ev.preventDefault(); addUser(data[parseInt(el.dataset.index, 10)]);
                });
            });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { close(); return; }
            timer = setTimeout(function () {
                fetch('/api/user-search?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : []; })
                    .then(render)
                    .catch(function () { close(); });
            }, 250);
        });

        input.addEventListener('keydown', function (ev) {
            var open = results.length > 0;
            if (ev.key === 'ArrowDown')    { ev.preventDefault(); setActive(Math.min(active + 1, results.length - 1)); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); setActive(Math.max(active - 1, 0)); }
            else if (ev.key === 'Escape')  { close(); }
            else if (ev.key === 'Enter' || ev.key === 'Tab' || ev.key === ';' || ev.key === ',') {
                // Tab with nothing to pick keeps its normal job of leaving the box.
                if (ev.key === 'Tab' && !open) return;
                ev.preventDefault();
                if (open) addUser(results[active >= 0 ? active : 0]);
            }
        });

        document.addEventListener('click', function (ev) {
            if (!input.contains(ev.target) && !drop.contains(ev.target)) close();
        });

        // Draft autosave hooks: per-field CC state lives in this closure, so
        // the draft glue reads/rebuilds it through this registry.
        window._ccFieldDraftHooks = window._ccFieldDraftHooks || {};
        window._ccFieldDraftHooks[fieldId] = {
            get: function () {
                return Object.values(ccSet).map(function (u) {
                    return { id: u.id, first_name: u.first_name, last_name: u.last_name, email: u.email };
                });
            },
            add: addUser,
        };
    }

    function initAll() {
        document.querySelectorAll('.cc-field-input').forEach(function (input) {
            if (input.dataset.ccInit === '1') return;
            input.dataset.ccInit = '1';
            initOne(input);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
