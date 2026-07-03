/**
 * TicketDraft — server-side draft autosave for ticket forms.
 *
 * Watches a form (and optionally a CKEditor instance) and autosaves the
 * user's in-progress work to /drafts (debounced), so an accidentally closed
 * window or a "finish it later" both come back via the restore banner on the
 * next visit. Drafts live in the ticket_drafts table keyed to the logged-in
 * user — nothing is kept in localStorage, so shared machines don't leak
 * drafts between accounts.
 *
 * Usage:
 *   var draft = TicketDraft.init({
 *     context:   'reply',            // server-side whitelist: ticket_create,
 *                                    // portal_create, reply, portal_reply
 *     ticketId:  123,                // 0 / omit for create forms
 *     form:      formEl,
 *     exclude:   ['message'],        // names to skip in the generic capture
 *     getHtml:   fn -> string|null,  // rich-text content (CKEditor)
 *     setHtml:   fn(html),
 *     hasText:   fn -> bool,         // does the rich text have real content?
 *     getExtras: fn -> object|null,  // page-specific state (tags, CC, …)
 *     setExtras: fn(extras),
 *     isEmpty:   fn -> bool,         // "no meaningful content" test
 *     noteEl:    el, discardBtn: el, // restore banner + its Discard button
 *     statusAnchor: el,              // "Draft saved" line is inserted after it
 *     onRestored:   fn,              // e.g. open the collapsed reply panel
 *     onDiscarded:  fn,              // clear page-specific UI (badges, …)
 *   });
 */
(function () {
    'use strict';

    var DEBOUNCE_MS       = 1500;
    var SUBMIT_QUIET_MS   = 8000;  // pause autosave around a form submit
    var KEEPALIVE_MAX     = 60000; // fetch keepalive bodies are capped at 64KB

    function csrfToken(form) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        var inp = form.querySelector('input[name="_token"]');
        return inp ? inp.value : '';
    }

    function textOf(html) {
        return (html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
    }

    function init(opts) {
        var form     = opts.form;
        var exclude  = {};
        ['_token', '_dup_matched_ids'].concat(opts.exclude || []).forEach(function (n) { exclude[n] = true; });
        var csrf     = csrfToken(form);
        var ticketId = opts.ticketId || 0;

        var timer         = null;
        var lastSaved     = null;   // JSON of the last state sent to the server
        var suppressUntil = 0;      // no autosaves while a submit is in flight
        var statusEl      = null;

        if (opts.statusAnchor) {
            statusEl = document.createElement('div');
            statusEl.className = 'text-muted small mt-1 ticket-draft-status';
            statusEl.setAttribute('aria-live', 'polite');
            statusEl.style.display = 'none';
            opts.statusAnchor.insertAdjacentElement('afterend', statusEl);
        }

        function setStatus(msg) {
            if (!statusEl) return;
            if (!msg) { statusEl.style.display = 'none'; return; }
            statusEl.textContent = msg;
            statusEl.style.display = '';
        }

        function controls() {
            return form.querySelectorAll('input[name], select[name], textarea[name]');
        }

        function capture() {
            var fields = {};
            if (opts.captureFields === false) {
                var payloadOnly = {};
                if (opts.getHtml)   payloadOnly.html   = opts.getHtml();
                if (opts.getExtras) payloadOnly.extras = opts.getExtras();
                payloadOnly.fields = fields;
                return payloadOnly;
            }
            controls().forEach(function (el) {
                var name = el.name;
                if (!name || exclude[name]) return;
                var t = el.type;
                if (t === 'file' || t === 'password' || t === 'submit' || t === 'button') return;
                if (t === 'radio') {
                    if (el.checked) fields[name] = el.value;
                    else if (!(name in fields)) fields[name] = '';
                } else if (t === 'checkbox') {
                    // Single checkboxes only (the app's checkbox custom field).
                    fields[name] = el.checked ? '1' : '';
                } else if (el.tagName === 'SELECT' && el.multiple) {
                    fields[name] = Array.prototype.filter.call(el.options, function (o) { return o.selected; })
                        .map(function (o) { return o.value; });
                } else {
                    fields[name] = el.value;
                }
            });
            var payload = { fields: fields };
            if (opts.getHtml)   payload.html   = opts.getHtml();
            if (opts.getExtras) payload.extras = opts.getExtras();
            return payload;
        }

        function isEmpty(payload) {
            if (opts.isEmpty) return opts.isEmpty();
            if (opts.hasText) return !opts.hasText();
            var fields = payload.fields || {};
            return Object.keys(fields).every(function (k) {
                var v = fields[k];
                return Array.isArray(v) ? v.length === 0 : String(v).trim() === '';
            });
        }

        function post(url, body, keepalive) {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: !!keepalive,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: body,
            });
        }

        function save(unloading) {
            if (Date.now() < suppressUntil) return;
            var payload = capture();
            var empty   = isEmpty(payload);
            var state   = empty ? '' : JSON.stringify(payload);
            if (state === lastSaved) return;

            var body = JSON.stringify({
                context:   opts.context,
                ticket_id: ticketId,
                payload:   empty ? null : payload,
            });
            var keepalive = unloading && body.length < KEEPALIVE_MAX;
            post('/drafts', body, keepalive).then(function (r) {
                if (!r.ok) {
                    if (r.status === 413) setStatus('Draft too large to autosave — submit or trim it.');
                    return;
                }
                lastSaved = state;
                if (empty) setStatus(null);
                else setStatus('Draft saved ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
            }).catch(function () { /* offline — retry on next edit */ });
        }

        function schedule() {
            if (Date.now() < suppressUntil) return;
            clearTimeout(timer);
            timer = setTimeout(function () { save(false); }, DEBOUNCE_MS);
        }

        function flushIfDirty(unloading) {
            clearTimeout(timer);
            save(unloading);
        }

        function clear() {
            clearTimeout(timer);
            lastSaved = '';
            post('/drafts', JSON.stringify({ context: opts.context, ticket_id: ticketId, payload: null }), true)
                .catch(function () {});
            if (opts.noteEl) opts.noteEl.style.display = 'none';
            setStatus(null);
        }

        function applyFields(fields) {
            var done = {};
            // DOM order matters: setting a cascading select and firing its
            // change handler populates the options of the next one.
            controls().forEach(function (el) {
                var name = el.name;
                if (!(name in fields) || done[name] || exclude[name]) return;
                done[name] = true;
                var val = fields[name];
                if (el.type === 'radio') {
                    var group = form.querySelectorAll('input[type="radio"][name="' + CSS.escape(name) + '"]');
                    group.forEach(function (r) { r.checked = (r.value === val); });
                } else if (el.type === 'checkbox') {
                    el.checked = (val === '1');
                } else if (el.tagName === 'SELECT' && el.multiple && Array.isArray(val)) {
                    Array.prototype.forEach.call(el.options, function (o) { o.selected = val.indexOf(o.value) !== -1; });
                } else if (el.type !== 'file') {
                    el.value = Array.isArray(val) ? (val[0] || '') : val;
                }
                el.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        function restore() {
            var qs = '?context=' + encodeURIComponent(opts.context) + '&ticket_id=' + ticketId;
            fetch('/drafts' + qs, { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data || !data.success || !data.draft) return;
                    // Don't clobber a form the user (or a validation redirect
                    // via old()) has already put content into.
                    if (!isEmpty(capture())) return;
                    var p = data.draft.payload || {};
                    if (p.fields) applyFields(p.fields);
                    if (opts.setHtml && typeof p.html === 'string') opts.setHtml(p.html);
                    if (opts.setExtras && p.extras) opts.setExtras(p.extras);
                    lastSaved = JSON.stringify(capture());
                    if (opts.noteEl) opts.noteEl.style.display = '';
                    if (opts.onRestored) opts.onRestored();
                })
                .catch(function () {});
        }

        form.addEventListener('input', schedule);
        form.addEventListener('change', schedule);

        form.addEventListener('submit', function () {
            // The server deletes the draft when the submit lands; go quiet so a
            // trailing autosave can't resurrect it. If the submit is blocked
            // (validation, duplicate-check panel), autosave resumes shortly.
            suppressUntil = Date.now() + SUBMIT_QUIET_MS;
            clearTimeout(timer);
        });

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') flushIfDirty(true);
        });
        window.addEventListener('pagehide', function () { flushIfDirty(true); });

        if (opts.discardBtn) {
            opts.discardBtn.addEventListener('click', function () {
                clear();
                if (opts.onDiscarded) opts.onDiscarded();
            });
        }

        restore();

        return {
            watchEditor: function (editor) {
                editor.model.document.on('change:data', schedule);
            },
            // Explicit flush (e.g. before opening the ticket in a new tab):
            // lift the post-submit quiet period — the user is still editing.
            flush: function () { suppressUntil = 0; flushIfDirty(false); },
            clear: clear,
            textOf: textOf,
        };
    }

    window.TicketDraft = { init: init, textOf: textOf };
})();
