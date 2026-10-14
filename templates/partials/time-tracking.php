<?php
/**
 * Time Tracking card for the agent and admin ticket views (include once, below
 * the Update Ticket card). The body re-renders from POST /agent/tickets/{id}/time
 * and is swapped into #ttCard; this script sits outside it so it binds once.
 *
 * Also owns the "no time logged" reminder: closing a ticket whose type tracks
 * time, with nothing logged, asks first — from the Update form or the reply
 * box's "Send & change status". The agent can still close without logging.
 */
?>
<div id="ttCard"><?php require __DIR__ . '/time-tracking-body.php'; ?></div>
<?php if (empty($ttVisible)) { return; } ?>

<div class="modal fade" id="ttCloseReminder" tabindex="-1" aria-labelledby="ttCloseReminderLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold" id="ttCloseReminderLabel">
                    <i class="bi bi-hourglass-split me-2 text-primary"></i>No time logged on this ticket
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body small">
                This ticket type tracks the time spent on it, and nothing has been logged yet. Log your time now, or close without it.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="ttCloseAnyway">Close without logging</button>
                <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" id="ttLogNow">
                    <i class="bi bi-plus-lg me-1"></i>Log time
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var card = document.getElementById('ttCard');

    // Live timer readouts. The starting value comes from the server's clock, so
    // a wrong PC clock can't skew the total; the browser only ticks it along.
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
        card.querySelectorAll('[data-tt-elapsed]').forEach(function (el) {
            if (!el._t0) el._t0 = Date.now() - Number(el.dataset.ttElapsed) * 1000;
            var s = Math.floor((Date.now() - el._t0) / 1000);
            el.textContent = pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s / 60) % 60) + ':' + pad(s % 60);
        });
    }
    setInterval(tick, 1000);

    // Every card form posts in the background and the card is swapped in place.
    card.addEventListener('submit', function (e) {
        var form = e.target.closest('form.tt-form');
        if (!form) return;
        e.preventDefault();
        if (form.dataset.confirm && !confirm(form.dataset.confirm)) return;
        var btn = form.querySelector('[type=submit]');
        if (btn) btn.disabled = true;
        // getAttribute: the hidden input named "action" shadows form.action.
        fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
            .then(function (r) {
                // A redirect means the access check bounced us (e.g. lost access).
                if (!r.ok || r.redirected) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) { card.innerHTML = html; tick(); })
            .catch(function () {
                if (btn) btn.disabled = false;
                alert('The time entry could not be saved. Reload the page and try again.');
            });
    });

    // ── "No time logged" reminder on close ──
    // Registered before the resolution-note gate further down the page, so this
    // asks first; "Close without logging" resubmits and lets that gate run.
    var update   = document.getElementById('updateTicketForm');
    var closed   = update ? (update.dataset.closedSlugs || '').split(',').filter(Boolean) : [];
    var modalEl  = document.getElementById('ttCloseReminder');
    var pending  = null;   // { proceed, cancel } for the submit being held
    var go       = false;

    function needsReminder(status) {
        var body = document.getElementById('ttBody');
        return !!body && body.dataset.enabled === '1' && body.dataset.count === '0'
            && closed.indexOf(status) !== -1;
    }
    function modal() { return bootstrap.Modal.getOrCreateInstance(modalEl); }

    function guard(form, statusOf, cancel) {
        if (!form) return;
        form.addEventListener('submit', function (e) {
            if (form._ttAck || !needsReminder(statusOf())) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            var proceed = function () { form._ttAck = true; form.requestSubmit(); };
            if (!window.bootstrap) { proceed(); return; } // fail open
            pending = { proceed: proceed, cancel: cancel };
            modal().show();
        });
    }

    guard(update, function () {
        var s = document.getElementById('status');
        return s && s.value !== update.dataset.originalStatus ? s.value : '';
    });
    var statusAfter = document.getElementById('replyStatusAfter');
    guard(document.getElementById('replyForm'), function () {
        return statusAfter ? statusAfter.value : '';
    }, function () {
        // Otherwise the next plain "Send" would still carry the close.
        if (statusAfter) statusAfter.value = '';
    });

    // Act once the modal is fully hidden, so the resolution-note modal (if this
    // type also asks for one) never opens on top of a closing modal.
    modalEl.addEventListener('hidden.bs.modal', function () {
        var p = pending;
        pending = null;
        if (!p) return;
        if (go) { go = false; p.proceed(); } else if (p.cancel) { p.cancel(); }
    });
    document.getElementById('ttCloseAnyway').addEventListener('click', function () {
        go = true;
        modal().hide();
    });
    document.getElementById('ttLogNow').addEventListener('click', function () {
        modal().hide();
        var logForm = document.getElementById('ttLogForm');
        if (!logForm) return;
        bootstrap.Collapse.getOrCreateInstance(logForm, { toggle: false }).show();
        logForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('ttHours').focus({ preventScroll: true });
    });
})();
</script>
