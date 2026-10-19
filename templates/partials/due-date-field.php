<?php
/**
 * Due Date field for the ticket page's Update form (agent + admin views).
 *
 * When the new date lands after the SLA resolution date, submitting opens a
 * modal asking the agent to tell the customer why and when — the reply goes out
 * with the save (applyTicketDueDateChange). Switched off by Settings → Ticket
 * Reminders → "Ask for a customer reply…". Include inside #updateTicketForm.
 *
 * Expects $ticket (due_date, resolution_due_at, creator_name).
 */
$ddSla       = !empty($ticket['resolution_due_at']) ? date('Y-m-d', strtotime($ticket['resolution_due_at'])) : '';
$ddPrompt    = $ddSla !== '' && getSetting('due_date_extension_prompt', '1') === '1';
$ddFirstName = strtok(trim((string) ($ticket['creator_name'] ?? '')), ' ') ?: 'there';
?>
<div class="mb-3">
    <label for="due_date" class="form-label fw-semibold small">Due Date</label>
    <input type="date" class="form-control form-control-sm" name="due_date" id="due_date"
           value="<?= e((string) ($ticket['due_date'] ?? '')) ?>">
    <?php if ($ddSla !== ''): ?>
    <div class="form-text small">SLA target: <?= date('M j, Y', strtotime($ddSla)) ?></div>
    <?php endif; ?>
</div>

<?php if ($ddPrompt): ?>
<input type="hidden" name="due_date_reply" id="dueDateReplyField" value="">
<div class="modal fade" id="dueDateExtendModal" tabindex="-1" aria-labelledby="dueDateExtendLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="dueDateExtendLabel"><i class="bi bi-calendar-x me-2"></i>New due date is past the SLA target</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-2">
                    The SLA says this ticket should be resolved by <strong><?= date('M j, Y', strtotime($ddSla)) ?></strong>.
                    You're moving the due date to <strong id="dueDateExtendNew"></strong>.
                </p>
                <p class="small text-muted mb-2">Let <?= e($ddFirstName) ?> know why it's taking longer and when to expect it. This goes out as a reply on the ticket.</p>
                <textarea class="form-control" id="dueDateReplyText" rows="9" aria-label="Reply to the customer"></textarea>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="dueDateSaveOnly">Save without replying</button>
                <button type="button" class="btn btn-sm text-white" style="background:var(--ld-primary);" id="dueDateSendSave">
                    <i class="bi bi-send me-1"></i>Send reply &amp; save
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var form    = document.getElementById('updateTicketForm');
    var input   = document.getElementById('due_date');
    var modalEl = document.getElementById('dueDateExtendModal');
    var text    = document.getElementById('dueDateReplyText');
    var field   = document.getElementById('dueDateReplyField');
    if (!form || !input || !modalEl) return;
    var slaDate  = <?= json_encode($ddSla) ?>;
    var touched  = false;
    // Bootstrap loads after this inline script; resolve the modal at submit time.
    function getModal() { return window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null; }
    function pretty(ymd) {
        return new Date(ymd + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    text.addEventListener('input', function () { touched = true; });

    // Capture phase, so this runs before the resolution-note gate's listener and
    // can hold it back: one modal at a time, and the resubmit runs the others.
    form.addEventListener('submit', function (e) {
        if (form._dueDateAck) return;
        var v = input.value;
        // defaultValue, not a load-time copy: the ticket page's live sync moves it.
        if (!v || v === input.defaultValue || v <= slaDate) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        var m = getModal();
        if (!m) { form._dueDateAck = true; form.requestSubmit(); return; } // fail open
        document.getElementById('dueDateExtendNew').textContent = pretty(v);
        if (!touched) {
            text.value = 'Hi ' + <?= json_encode($ddFirstName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> + ',\n\nA quick update on your ticket: it is going to take longer than we planned. '
                + 'We now expect to have it resolved by ' + pretty(v) + '.\n\n';
        }
        m.show();
        setTimeout(function () { text.focus(); text.setSelectionRange(text.value.length, text.value.length); }, 300);
    }, true);

    // The reply only reaches the form through the hidden field, and only from
    // "Send" — text left in a cancelled modal is never posted.
    function save(reply) {
        field.value = reply;
        form._dueDateAck = true;
        var m = getModal();
        if (m) m.hide();
        form.requestSubmit();
    }
    document.getElementById('dueDateSendSave').addEventListener('click', function () {
        if (!text.value.trim()) { text.focus(); return; }
        save(text.value.trim());
    });
    document.getElementById('dueDateSaveOnly').addEventListener('click', function () { save(''); });
})();
</script>
<?php endif; ?>
