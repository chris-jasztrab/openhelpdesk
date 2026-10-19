<?php
/**
 * Close split-button for the ticket page — shared by the agent and admin views
 * so the two parallel templates can't drift. Include it three times:
 *   $closePart = 'badges' → close-reason / reopen-window badges (header)
 *   $closePart = 'menu'   → the Close button + dropdown (header actions)
 *   $closePart = 'modal'  → the options modal + script (bottom of the page)
 * Expects $ticket and $closeArea ('/agent' | '/admin').
 *
 * Every option drives the sidebar #updateTicketForm: it sets the status select
 * to Closed, adds `close_mode` (and the modal's fields, attached with the form=
 * attribute) and submits. So the optimistic lock, the resolution-note gate, SLA
 * and automations behave exactly like a sidebar close. Server side:
 * ticketCloseMode() / applyTicketCloseMode() in helpers.php.
 */
$closedSlug = ticketDefaultClosedStatusSlug();

if ($closePart === 'badges'):
    $reopenUntil = !empty($ticket['reopen_on_reply_until']) ? strtotime($ticket['reopen_on_reply_until']) : 0;
?>
<?php if (!empty($ticket['close_reason']) && in_array($ticket['status'], ticketClosedBucketSlugs(), true)): ?>
<span class="badge text-bg-light border fs-6">
    <?= e(ticketCloseReasonLabel($ticket['close_reason'])) ?>
    <?php if (!empty($ticket['duplicate_of_ticket_id'])): ?>
    of <a href="<?= e($closeArea) ?>/tickets/<?= (int) $ticket['duplicate_of_ticket_id'] ?>">#<?= (int) $ticket['duplicate_of_ticket_id'] ?></a>
    <?php endif; ?>
</span>
<?php endif; ?>
<?php if ($reopenUntil > time()): ?>
<span class="badge text-bg-light border fs-6" title="A reply from the requester before then reopens this ticket">
    <i class="bi bi-arrow-counterclockwise me-1"></i>Reopens on reply until <?= date('M j', $reopenUntil) ?>
</span>
<?php endif; ?>
<?php
    return;
endif;

// Nothing to close: already Closed (Resolved can still be closed) or merged away.
if (!empty($ticket['merged_into_ticket_id']) || $ticket['status'] === $closedSlug) {
    return;
}

if ($closePart === 'menu'):
    $closeOptions = [
        // [close_mode, icon, title, description]. '' = the plain Close button.
        ['silent',          'bi-x-circle',               'Close without notifying',       'Set status to Closed. No email to the requester.'],
        ['',                'bi-envelope-check',         'Close and notify',              'Close and email the requester. Same as the Close button.'],
        ['feedback',        'bi-star',                   'Close and request feedback',    'Close and send a satisfaction survey.'],
        ['duplicate',       'bi-files',                  'Close as duplicate…',           'Link to the original ticket; optionally tell the requester.'],
        ['wont_fix',        'bi-slash-circle',           'Close as won’t fix…',           'Close with a “not doing this” reason. Not counted as resolved.'],
        ['no_response',     'bi-hourglass-bottom',       'Close as no response…',         'The requester stopped replying. Not counted as resolved.'],
        ['merge',           'bi-arrow-right-circle',     'Close and merge…',              'Move this conversation into another ticket, then close it.'],
        ['kb',              'bi-journal-plus',           'Close and create KB article',   'Close, then open a KB draft prefilled from this ticket.'],
        ['reopen_on_reply', 'bi-arrow-counterclockwise', 'Close and reopen on reply…',    'Reopens automatically if the requester replies within N days.'],
        ['followup',        'bi-alarm',                  'Close and schedule follow-up…', 'Close and get a reminder to check back later.'],
    ];
    $csatOn = getSetting('csat_enabled') === '1';
    $canKb  = Auth::can('kb.articles.manage');
?>
<div class="btn-group" id="closeMenu">
    <button type="button" class="btn btn-success" data-close-mode="" title="Close the ticket and email the requester">
        <i class="bi bi-check2-circle me-1"></i>Close
    </button>
    <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split"
            data-bs-toggle="dropdown" aria-expanded="false" title="More ways to close">
        <span class="visually-hidden">More ways to close</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow py-1" style="width:21rem;max-width:calc(100vw - 2rem);">
        <?php foreach ($closeOptions as [$mode, $icon, $title, $desc]): ?>
        <?php if ($mode === 'kb' && !$canKb) continue; ?>
        <?php $off = $mode === 'feedback' && !$csatOn; ?>
        <li>
            <button type="button" class="dropdown-item d-flex gap-2 py-2 text-wrap" data-close-mode="<?= e($mode) ?>" <?= $off ? 'disabled' : '' ?>>
                <i class="bi <?= $icon ?> mt-1"></i>
                <span>
                    <span class="d-block fw-semibold"><?= e($title) ?></span>
                    <small class="d-block text-muted"><?= $off ? 'Satisfaction surveys are turned off in Settings.' : e($desc) ?></small>
                </span>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php
    return;
endif;

// $closePart === 'modal'
?>
<input type="hidden" name="close_mode" id="closeModeInput" value="" form="updateTicketForm">
<div class="modal fade" id="closeTicketModal" tabindex="-1" aria-labelledby="closeTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="closeTicketModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3" data-close-section="duplicate" style="position:relative;">
                    <label for="closeDupSearch" class="form-label fw-semibold small">Original ticket</label>
                    <input type="text" id="closeDupSearch" class="form-control" placeholder="Type ticket # or subject..." autocomplete="off">
                    <div id="closeDupResults" class="list-group shadow-sm" style="display:none;position:absolute;z-index:1060;width:100%;max-height:240px;overflow-y:auto;"></div>
                    <input type="hidden" name="duplicate_of" id="closeDupId" value="" form="updateTicketForm">
                </div>
                <div class="mb-3" data-close-section="reopen_on_reply">
                    <label for="closeReopenDays" class="form-label fw-semibold small">Reopen if the requester replies within</label>
                    <div class="input-group" style="max-width:12rem;">
                        <input type="number" name="reopen_days" id="closeReopenDays" class="form-control" min="1" max="90" value="7" form="updateTicketForm">
                        <span class="input-group-text">days</span>
                    </div>
                </div>
                <div class="mb-3" data-close-section="followup">
                    <label for="closeFollowupAt" class="form-label fw-semibold small">Remind me on</label>
                    <input type="datetime-local" name="followup_at" id="closeFollowupAt" class="form-control" form="updateTicketForm">
                    <input type="text" name="followup_note" id="closeFollowupNote" class="form-control mt-2" maxlength="500"
                           placeholder="What to check (optional)" form="updateTicketForm">
                    <div class="form-text">You'll get a notification on this ticket when it's due.</div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="close_notify" value="1" id="closeNotify" checked form="updateTicketForm">
                    <label class="form-check-label" for="closeNotify">Email the requester</label>
                </div>
                <label for="closeMessage" class="form-label fw-semibold small" id="closeMessageLabel">Message</label>
                <textarea name="close_message" id="closeMessage" class="form-control" rows="4" form="updateTicketForm"></textarea>
                <div class="form-text" id="closeMessageHelp"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="closeConfirmBtn">
                    <i class="bi bi-check2-circle me-1"></i>Close ticket
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var form      = document.getElementById('updateTicketForm');
    var statusSel = document.getElementById('status');
    var modeInput = document.getElementById('closeModeInput');
    var modalEl   = document.getElementById('closeTicketModal');
    if (!form || !statusSel || !modeInput || !modalEl) return;

    function $(id) { return document.getElementById(id); }
    // Bootstrap's bundle loads after inline scripts — resolve modals at click time.
    function modal(el) { return window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null; }

    var closedSlug = <?= json_encode($closedSlug) ?>;
    var ticketId   = <?= (int) $ticket['id'] ?>;
    var searchUrl  = <?= json_encode($closeArea . '/tickets/search') ?>;
    var msgEl = $('closeMessage'), notifyEl = $('closeNotify'), daysEl = $('closeReopenDays');
    var dupId = $('closeDupId'), dupSearch = $('closeDupSearch'), dupResults = $('closeDupResults');
    var fuAt  = $('closeFollowupAt');

    // Options that ask for input first. {id} / {days} fill in until the message is edited.
    var MODES = {
        duplicate:       { title: 'Close as duplicate', msg: "We're already tracking this request in ticket #{id}, so we've closed this one as a duplicate. Updates will happen on that ticket." },
        wont_fix:        { title: "Close as won't fix", label: 'Reason', required: true, msg: '' },
        no_response:     { title: 'Close as no response', msg: "We haven't heard back from you, so we're closing this ticket. If you still need help, please submit a new request." },
        reopen_on_reply: { title: 'Close and reopen on reply', msg: "We've closed this ticket. If the problem comes back within {days} days, just reply and it will reopen automatically." },
        followup:        { title: 'Close and schedule follow-up', msg: '' }
    };
    var current = null, edited = false, confirmed = false;

    function submitClose(mode) {
        modeInput.value = mode;
        statusSel.value = closedSlug;
        form.requestSubmit(); // the resolution-note gate may step in first
    }

    // A cancelled close (this modal, or the resolution-note gate after it) must
    // not stay staged, or a later sidebar Update would close the ticket too.
    function unstage() {
        modeInput.value = '';
        statusSel.value = form.dataset.originalStatus; // read live: the ticket page's live sync moves it
        daysEl.value = 7;
    }

    function fillMessage() {
        if (!edited) {
            msgEl.value = MODES[current].msg.replace('{id}', dupId.value || '…').replace('{days}', daysEl.value);
        }
    }

    function fillHelp() {
        $('closeMessageHelp').textContent = !notifyEl.checked
            ? 'Not emailed — saved as an internal note on the ticket.'
            : MODES[current].required
                ? 'Sent to the requester as a reply.'
                : 'Sent to the requester as a reply. Leave it blank to send the standard "ticket closed" email instead.';
    }

    function openModal(mode) {
        current = mode; edited = false; confirmed = false;
        $('closeTicketModalLabel').textContent = MODES[mode].title + ' — #' + ticketId;
        modalEl.querySelectorAll('[data-close-section]').forEach(function (s) {
            s.classList.toggle('d-none', s.dataset.closeSection !== mode);
        });
        $('closeMessageLabel').textContent = MODES[mode].label || 'Message';
        notifyEl.checked = true;
        if (mode === 'followup' && !fuAt.value) {
            var d = new Date(); d.setDate(d.getDate() + 7);
            var pad = function (n) { return String(n).padStart(2, '0'); };
            fuAt.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T09:00';
        }
        fillMessage();
        fillHelp();
        var m = modal(modalEl);
        if (m) m.show();
    }

    document.querySelectorAll('[data-close-mode]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.dataset.closeMode;
            if (mode === 'merge') {
                var mm = $('mergeModal') && modal($('mergeModal'));
                if (mm) mm.show();
            } else if (MODES[mode]) {
                openModal(mode);
            } else {
                submitClose(mode);
            }
        });
    });

    msgEl.addEventListener('input', function () { edited = true; });
    notifyEl.addEventListener('change', fillHelp);
    daysEl.addEventListener('input', fillMessage);

    $('closeConfirmBtn').addEventListener('click', function () {
        if (current === 'duplicate' && !dupId.value) { dupSearch.focus(); return; }
        if (current === 'reopen_on_reply' && !daysEl.reportValidity()) return;
        if (current === 'followup' && !fuAt.value) { fuAt.focus(); return; }
        if (MODES[current].required && !msgEl.value.trim()) { msgEl.focus(); return; }
        confirmed = true;
        var m = modal(modalEl);
        if (m) { m.hide(); } else { submitClose(current); }
    });

    // Submit only once this modal is fully gone, so the resolution-note gate can
    // open cleanly. Bootstrap 5 modal events bubble, so this sees the gate too.
    document.addEventListener('hidden.bs.modal', function (e) {
        if (e.target === modalEl) { confirmed ? submitClose(current) : unstage(); }
        if (e.target.id === 'resolutionNoteModal' && !form._resolutionAck) unstage();
    });

    // Original-ticket picker — same search endpoint as the merge modal.
    var debounce = null;
    dupSearch.addEventListener('input', function () {
        clearTimeout(debounce);
        dupId.value = '';
        fillMessage();
        dupResults.style.display = 'none';
        var q = dupSearch.value.trim();
        if (!q) return;
        debounce = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q) + '&exclude=' + ticketId)
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    dupResults.innerHTML = rows.length ? '' : '<div class="px-3 py-2 text-muted small">No tickets found.</div>';
                    rows.forEach(function (t) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'list-group-item list-group-item-action px-3 py-2 small';
                        b.textContent = '#' + t.id + ' — ' + t.subject;
                        b.addEventListener('click', function () {
                            dupId.value = t.id;
                            dupSearch.value = b.textContent;
                            dupResults.style.display = 'none';
                            fillMessage();
                        });
                        dupResults.appendChild(b);
                    });
                    dupResults.style.display = 'block';
                });
        }, 300);
    });
})();
</script>
