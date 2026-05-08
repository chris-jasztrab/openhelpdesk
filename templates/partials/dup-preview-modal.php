<?php
/**
 * Shared duplicate-preview modal used by every ticket-create flow
 * (portal, agent, floor). Populated by JS via the
 * /portal/tickets/dup-preview or /agent/tickets/dup-preview endpoint.
 *
 * The page that includes this partial must define $dupPreviewEndpoint —
 * either '/portal/tickets/dup-preview' or '/agent/tickets/dup-preview' —
 * and $dupViewBase used as the prefix for the "Open the full ticket"
 * link (e.g. '/portal/tickets' or '/agent/tickets').
 */
$dupPreviewEndpoint = $dupPreviewEndpoint ?? '/portal/tickets/dup-preview';
$dupViewBase        = $dupViewBase        ?? '/portal/tickets';
?>
<div class="modal fade" id="dupPreviewModal" tabindex="-1" aria-labelledby="dupPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dupPreviewModalLabel">
                    <i class="bi bi-files me-2"></i>Existing ticket details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="dup-preview-loading" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                    Loading the existing ticket so you can compare it to yours.
                </div>
                <div id="dup-preview-error" class="alert alert-warning" style="display:none;">
                    We could not load that ticket. It may have been resolved, merged, or moved out of your branch's open queue. Please refresh and try again.
                </div>
                <div id="dup-preview-content" style="display:none;">
                    <h6 class="fw-semibold mb-1" id="dup-preview-subject">&nbsp;</h6>
                    <div class="small text-muted mb-3" id="dup-preview-meta">&nbsp;</div>
                    <div class="ck-content border rounded p-3 bg-light" id="dup-preview-description"></div>
                    <div class="small text-muted mt-2" id="dup-preview-comment-count"></div>
                </div>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <a href="#" id="dup-preview-open-full" class="btn btn-outline-primary" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Open the full ticket in a new tab
                </a>
                <div class="ms-auto d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Yes, this looks like my issue. I will not file a new ticket.
                    </button>
                    <button type="button" class="btn btn-warning" id="dup-preview-create-anyway">
                        Create anyway &mdash; This is a Different Issue
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function _initDupPreviewModal() {
    const modalEl   = document.getElementById('dupPreviewModal');
    if (!modalEl) return;
    if (typeof bootstrap === 'undefined') {
        // Bootstrap hasn't loaded yet — try again on the next tick.
        setTimeout(_initDupPreviewModal, 50);
        return;
    }
    const modal     = new bootstrap.Modal(modalEl);
    const loadEl    = document.getElementById('dup-preview-loading');
    const errEl     = document.getElementById('dup-preview-error');
    const contentEl = document.getElementById('dup-preview-content');
    const subjEl    = document.getElementById('dup-preview-subject');
    const metaEl    = document.getElementById('dup-preview-meta');
    const descEl    = document.getElementById('dup-preview-description');
    const cmtEl     = document.getElementById('dup-preview-comment-count');
    const openLink  = document.getElementById('dup-preview-open-full');
    const createBtn = document.getElementById('dup-preview-create-anyway');
    const labelEl   = document.getElementById('dupPreviewModalLabel');
    const PREVIEW_ENDPOINT = <?= json_encode($dupPreviewEndpoint, JSON_HEX_TAG | JSON_HEX_QUOT) ?>;
    const VIEW_BASE        = <?= json_encode($dupViewBase, JSON_HEX_TAG | JSON_HEX_QUOT) ?>;

    function escH(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

    // The floor-mode bottom sheet uses z-index 1070, which would otherwise
    // hide the modal behind it. Lift our modal + its backdrop above the
    // sheet on every show, then let Bootstrap restore them on hide.
    modalEl.addEventListener('shown.bs.modal', function () {
        modalEl.style.zIndex = '1090';
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length) {
            backdrops[backdrops.length - 1].style.zIndex = '1085';
        }
    });

    // Public API: window.openDupPreviewModal(ticketId, allMatchedIds)
    // - ticketId: the ticket the user clicked to inspect
    // - allMatchedIds: every match the AI flagged on this submit, used so
    //   "Create anyway" still records the full audit trail.
    window.openDupPreviewModal = function (ticketId, allMatchedIds) {
        loadEl.style.display    = '';
        errEl.style.display     = 'none';
        contentEl.style.display = 'none';
        subjEl.textContent      = '';
        metaEl.textContent      = '';
        descEl.innerHTML        = '';
        cmtEl.textContent       = '';
        openLink.href           = VIEW_BASE + '/' + ticketId;
        labelEl.textContent     = 'Existing ticket details';
        modal.show();

        fetch(PREVIEW_ENDPOINT + '?id=' + encodeURIComponent(ticketId), { credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return null; }).then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                loadEl.style.display = 'none';
                if (!res.ok || !res.json || !res.json.ok || !res.json.ticket) {
                    errEl.style.display = '';
                    return;
                }
                const t = res.json.ticket;
                labelEl.textContent = 'Ticket #' + t.id;
                subjEl.textContent  = t.subject || '';
                let metaParts = [];
                if (t.status)     { metaParts.push('Status: ' + t.status.replace(/_/g, ' ')); }
                if (t.created_at) {
                    const d = new Date(String(t.created_at).replace(' ', 'T'));
                    if (!isNaN(d.getTime())) metaParts.push('Opened ' + d.toLocaleString());
                }
                if (t.requester) { metaParts.push('Reported by ' + t.requester); }
                if (t.type_name) { metaParts.push('Type: ' + t.type_name); }
                metaEl.textContent = metaParts.join(' · ');
                descEl.innerHTML   = t.description_html || '<em class="text-muted">No description was provided.</em>';
                if (typeof t.comment_count === 'number') {
                    cmtEl.textContent = t.comment_count === 0
                        ? 'There are no replies on this ticket yet.'
                        : 'There ' + (t.comment_count === 1 ? 'is 1 reply' : 'are ' + t.comment_count + ' replies') + ' on this ticket so far.';
                }
                contentEl.style.display = '';

                createBtn.dataset.matchedIds = (allMatchedIds || []).join(',');
            })
            .catch(function () {
                loadEl.style.display = 'none';
                errEl.style.display  = '';
            });
    };

    createBtn.addEventListener('click', function () {
        const form = document.querySelector('form[id$="ticket-form"], #floor-quick-form');
        if (!form) { modal.hide(); return; }
        const ids = createBtn.dataset.matchedIds || '';
        const idsField = form.querySelector('input[name="_dup_matched_ids"]');
        if (idsField && ids) idsField.value = ids;
        form.dataset.dupOverride = '1';
        modal.hide();
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _initDupPreviewModal);
} else {
    _initDupPreviewModal();
}
</script>
