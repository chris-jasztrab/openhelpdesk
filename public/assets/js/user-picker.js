/**
 * `user_picker` custom field — directory autocomplete.
 *
 * The visible input is only ever a search box. What gets submitted is the
 * hidden input beside it, holding the chosen user's id, and it is cleared the
 * moment the text stops matching the person it was chosen for. That is the
 * whole point of the field type: a typed name is worth nothing for correlation,
 * an id resolves to a current name and a working address every time.
 *
 * So a half-typed name submits as nothing rather than as a wrong guess. When
 * the field is required, the server rejects that — which is the intended
 * outcome, since "sarah" is not an answer to "who are you".
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function initOne(root) {
        var fieldId = root.dataset.fieldId;
        var input   = root.querySelector('.user-picker-search');
        var hidden  = root.querySelector('.user-picker-value');
        var drop    = document.getElementById('up_drop_' + fieldId);
        if (!input || !hidden || !drop) return;

        var timer   = null;
        var results = [];
        var active  = -1;
        // What the box read when a user was last chosen. Any edit away from it
        // invalidates the stored id.
        var chosenLabel = input.value;

        function close() {
            drop.style.display = 'none';
            drop.innerHTML = '';
            results = [];
            active = -1;
        }

        /**
         * Text in the box with no id behind it is the failure mode worth
         * catching early: it looks answered and submits as blank. Flag it
         * inline instead of letting the server bounce the whole form back.
         */
        function syncValidity() {
            if (input.value.trim() !== '' && hidden.value === '') {
                input.setCustomValidity('Pick a name from the list so we know who to contact.');
            } else {
                input.setCustomValidity('');
            }
        }

        function choose(u) {
            hidden.value = String(u.id);
            input.value  = u.first_name + ' ' + u.last_name + ' <' + u.email + '>';
            chosenLabel  = input.value;
            close();
            syncValidity();
            // Let a field-condition gated on this picker re-evaluate.
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function setActive(i) {
            active = i;
            drop.querySelectorAll('.mention-item').forEach(function (el, idx) {
                el.classList.toggle('active', idx === i);
            });
        }

        function render(data) {
            results = data;
            active  = -1;
            if (!data.length) {
                drop.innerHTML = '<div class="px-3 py-2 text-muted small">No match — check the spelling, or ask the desk to add them.</div>';
                drop.style.display = 'block';
                return;
            }
            drop.innerHTML = data.map(function (u, i) {
                return '<div class="mention-item" data-index="' + i + '">'
                     +   '<span class="mention-name">' + escapeHtml(u.first_name + ' ' + u.last_name) + '</span> '
                     +   '<span class="text-muted" style="font-size:.75rem;">' + escapeHtml(u.email) + '</span>'
                     + '</div>';
            }).join('');
            drop.style.display = 'block';
            drop.querySelectorAll('.mention-item').forEach(function (el) {
                el.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    choose(data[parseInt(el.dataset.index, 10)]);
                });
            });
        }

        input.addEventListener('input', function () {
            // Typing after a selection means the selection no longer stands.
            if (input.value !== chosenLabel && hidden.value !== '') {
                hidden.value = '';
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }

            syncValidity();

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
            if (ev.key === 'ArrowDown')      { ev.preventDefault(); setActive(Math.min(active + 1, results.length - 1)); }
            else if (ev.key === 'ArrowUp')   { ev.preventDefault(); setActive(Math.max(active - 1, 0)); }
            else if (ev.key === 'Enter')     {
                if (drop.style.display !== 'none') {
                    ev.preventDefault();
                    if (active >= 0 && results[active]) choose(results[active]);
                }
            }
            else if (ev.key === 'Escape')    { close(); }
        });

        document.addEventListener('click', function (ev) {
            if (!root.contains(ev.target)) close();
        });

        // A stale id with text that no longer matches the person it was chosen
        // for must not reach the server as an answer.
        if (input.form) {
            input.form.addEventListener('submit', function () {
                if (input.value !== chosenLabel) hidden.value = '';
            });
        }

        syncValidity();
    }

    function initAll() {
        document.querySelectorAll('.user-picker').forEach(function (root) {
            if (root.dataset.upInit === '1') return;
            root.dataset.upInit = '1';
            initOne(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    window.UserPicker = { initAll: initAll };
})();
