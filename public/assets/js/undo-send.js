/**
 * UndoSend — hold an outgoing ticket message for an "Undo" grace window.
 *
 * When the admin enables undo send, every real submit of a ticket-create or
 * reply form is held client-side for N seconds while a countdown toast with
 * an Undo button shows. Nothing reaches the server (so no notification email
 * goes out) until the window elapses; Undo cancels the timer and returns the
 * user to the still-filled form. Escape also triggers Undo.
 *
 * Usage at each real-send point, AFTER validation/duplicate/collision checks:
 *
 *   if (window.UndoSend && UndoSend.hold(form, {
 *       seconds: 10,                      // 0 / missing → returns false
 *       label:   'Sending reply',         // toast text prefix
 *       onSend:  fn,                      // default: native form.submit()
 *       onUndo:  fn,                      // e.g. re-save the draft
 *   })) { e.preventDefault(); return; }
 *
 * hold() returns false when the feature is off (seconds <= 0) so callers can
 * fall through to their normal submit path. The expiry send uses the native
 * form.submit(), which deliberately bypasses submit listeners — validation
 * already passed, and re-running duplicate/collision guards would loop.
 */
(function () {
    'use strict';

    var active = null; // one pending send at a time per page

    function buildToast(label, onUndo) {
        var toast = document.createElement('div');
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.style.cssText =
            'position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);' +
            'z-index:2100;display:flex;align-items:center;gap:1rem;' +
            'background:#323232;color:#fff;padding:.65rem 1.25rem;' +
            'border-radius:50rem;box-shadow:0 .5rem 1.5rem rgba(0,0,0,.35);' +
            'font-size:.9rem;max-width:92vw;';

        var text = document.createElement('span');
        text.textContent = label;
        toast.appendChild(text);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Undo';
        btn.style.cssText =
            'background:none;border:0;padding:0;color:#8ab4f8;' +
            'font-weight:600;font-size:.9rem;cursor:pointer;letter-spacing:.02em;';
        btn.addEventListener('click', onUndo);
        toast.appendChild(btn);

        document.body.appendChild(toast);
        return { el: toast, text: text, btn: btn };
    }

    /**
     * Hold `form` for opts.seconds, then send it. Returns true when the hold
     * is engaged (caller must preventDefault), false when the feature is off.
     */
    function hold(form, opts) {
        opts = opts || {};
        var seconds = parseInt(opts.seconds, 10) || 0;
        if (seconds <= 0) return false;
        if (active) return true; // already counting down — keep holding

        var label   = opts.label || 'Sending';
        var onSend  = opts.onSend || function () { form.submit(); };
        var timer   = null;
        var remaining = seconds;

        // Freeze the form's submit buttons so a second click can't race the
        // countdown; remember which ones we disabled so Undo only re-enables
        // those (a button disabled for other reasons stays disabled).
        var frozen = [];
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (b) {
            if (!b.disabled) { b.disabled = true; frozen.push(b); }
        });

        function cleanup() {
            clearInterval(timer);
            document.removeEventListener('keydown', onKey);
            toast.el.remove();
            active = null;
        }

        function undo() {
            cleanup();
            frozen.forEach(function (b) { b.disabled = false; });
            if (opts.onUndo) opts.onUndo();
        }

        function onKey(e) {
            if (e.key === 'Escape') { e.preventDefault(); undo(); }
        }

        function tick() {
            remaining--;
            if (remaining > 0) {
                toast.text.textContent = label + ' in ' + remaining + 's…';
                return;
            }
            // Time's up — show a terminal state and fire the real submit. The
            // page navigates away on the response, taking the toast with it.
            clearInterval(timer);
            document.removeEventListener('keydown', onKey);
            toast.btn.remove();
            toast.text.textContent = label + '…';
            active = null;
            // The native form.submit() below fires no 'submit' event, and the
            // countdown can outlive TicketDraft's post-submit autosave quiet
            // window. Announce the real send so the draft autosaver goes quiet
            // again — otherwise its pagehide flush re-saves the draft the
            // server is about to delete, resurrecting the sent message as an
            // "unsent draft".
            form.dispatchEvent(new CustomEvent('undosend:send'));
            onSend();
        }

        var toast = buildToast(label + ' in ' + remaining + 's…', undo);
        document.addEventListener('keydown', onKey);
        timer = setInterval(tick, 1000);
        active = { undo: undo };
        return true;
    }

    window.UndoSend = { hold: hold };
})();
