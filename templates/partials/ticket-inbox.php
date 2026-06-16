<?php
/**
 * Email-style ("inbox") ticket list.
 *
 * An alternative layout to the classic resizable table, shared by the agent
 * and admin ticket lists. Shows two columns — who the ticket is From and the
 * Subject — plus the same bulk-select checkbox the table uses, so the existing
 * bulk-action bar keeps working unchanged. Hovering a row pops up a detail card
 * (requester, submitted time, message snippet, status/priority/type/assignee).
 *
 * Expects, in the including template's scope:
 *   $tickets             array  the ticket rows (same shape as the table view)
 *   $inboxBase           string base path for links, e.g. '/agent/tickets'
 *   $confidentialTypeIds array  (optional) for redaction
 *   $adminGroupIds       array  (optional) for redaction
 *   $colCount            int    column span for the empty state
 */
$inboxBase = $inboxBase ?? '/agent/tickets';
?>
<style>
    /* Inbox rows read like email — generous padding, a quiet divider. */
    #ticketInbox td { padding-top: .6rem; padding-bottom: .6rem; }
    #ticketInbox .ld-inbox-from { white-space: nowrap; font-weight: 600; }
    #ticketInbox .ld-inbox-subject {
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 1px;
    }
    #ticketInbox .ld-inbox-subject-text { color: var(--bs-body-color); }
    #ticketInbox tr.ld-inbox-row:hover .ld-inbox-subject-text { text-decoration: underline; }

    /* Floating hover detail card. Interactive — the mouse can move into it. */
    #ldInboxPop {
        position: fixed; z-index: 1080; display: none;
        width: 360px; max-width: calc(100vw - 24px);
        background: var(--bs-body-bg, #fff);
        color: var(--bs-body-color, #212529);
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: .5rem;
        box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .18);
        padding: .85rem 1rem;
    }
    #ldInboxPop .ld-pop-head { border-bottom: 1px solid var(--bs-border-color, #dee2e6); padding-bottom: .55rem; margin-bottom: .55rem; }
    #ldInboxPop .ld-pop-body { font-size: .85rem; }
    #ldInboxPop .ld-pop-snippet {
        color: var(--bs-secondary-color, #6c757d);
        display: -webkit-box; -webkit-line-clamp: 4; line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;
    }
    #ldInboxPop .ld-pop-foot { margin-top: .6rem; display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
    #ldInboxPop .ld-pop-actions {
        margin-top: .7rem; padding-top: .6rem; border-top: 1px solid var(--bs-border-color, #dee2e6);
        display: flex; gap: .4rem;
    }
    #ldInboxPop .ld-pop-actions .btn { flex: 1; }
</style>

<div style="overflow-x:auto;overflow-y:auto;max-height:calc(100vh - 260px);">
    <table class="table table-hover align-middle mb-0" id="ticketInbox" data-no-resize style="width:100%;">
        <thead class="table-light" style="position:sticky;top:0;z-index:5;box-shadow:0 1px 2px rgba(0,0,0,.06);">
            <tr>
                <th style="width:36px;"><input type="checkbox" id="selectAll" class="form-check-input" title="Select all"></th>
                <th style="width:30%;white-space:nowrap;"><a href="<?= sortUrl('creator', $sort, $dir, $sortParams, $inboxBase) ?>" class="text-decoration-none text-dark">From <?= sortIcon('creator', $sort, $dir) ?></a></th>
                <th><a href="<?= sortUrl('subject', $sort, $dir, $sortParams, $inboxBase) ?>" class="text-decoration-none text-dark">Subject <?= sortIcon('subject', $sort, $dir) ?></a></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tickets)): ?>
            <tr><td colspan="3" class="text-center py-4 text-muted">No tickets found.</td></tr>
            <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                <?php
                    $isAssignedToMe = ($t['assigned_to'] == Auth::id());
                    $isRedacted = isTicketRedactedForUser($t, $confidentialTypeIds ?? [], $adminGroupIds ?? []);
                    $ts = strtotime($t['created_at']);
                    $absDate = date('D, j M Y \a\t g:i A', $ts);
                    // The description is stored as HTML; flatten it to plain text for the snippet.
                    $snippet = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) ($t['description'] ?? '')), ENT_QUOTES | ENT_HTML5)));
                    if (mb_strlen($snippet) > 280) { $snippet = mb_substr($snippet, 0, 280) . '…'; }
                ?>
                <tr class="ld-inbox-row" style="cursor:pointer;<?= $isRedacted ? 'opacity:0.75;' : ($isAssignedToMe ? 'background:#eef2ff;' : '') ?>"
                    onclick="window.location='<?= $inboxBase ?>/<?= $t['id'] ?>'">
                    <td onclick="event.stopPropagation()">
                        <input type="checkbox" class="ticket-cb form-check-input" value="<?= $t['id'] ?>"
                               data-subject="<?= $isRedacted ? 'Confidential' : e($t['subject']) ?>"
                               <?= $isRedacted ? 'data-confidential="1"' : '' ?>>
                    </td>
                    <td class="ld-inbox-from<?= $isRedacted ? ' text-muted' : '' ?>">
                        <?php if ($isRedacted): ?>
                            <span class="fst-italic">—</span>
                        <?php else: ?>
                            <span class="ld-inbox-from-name"><?= e($t['creator_name'] ?: 'Unknown') ?></span>
                            <!-- Hidden source for the person hover card -->
                            <div class="ld-inbox-src-person d-none">
                                <div class="ld-pop-head">
                                    <div class="fw-semibold"><i class="bi bi-person-circle me-1 text-muted"></i><?= e($t['creator_name'] ?: 'Unknown') ?></div>
                                    <?php if (!empty($t['creator_email'])): ?>
                                    <div class="small mt-1"><a href="mailto:<?= e($t['creator_email']) ?>" class="text-decoration-none"><i class="bi bi-envelope me-1"></i><?= e($t['creator_email']) ?></a></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ld-pop-actions">
                                    <a href="<?= $inboxBase ?>/by-user/<?= (int) $t['created_by'] ?>" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="bi bi-card-list me-1"></i>Open tickets &amp; mentions
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="ld-inbox-subject">
                        <?php if ($isRedacted): ?>
                            <span class="text-muted fst-italic"><i class="bi bi-shield-lock me-1"></i>[Confidential]</span>
                        <?php else: ?>
                            <span class="text-muted small me-1">#<?= $t['id'] ?></span>
                            <span class="ld-inbox-subject-text fw-semibold"><?= e($t['subject']) ?></span>
                            <?php if ($isAssignedToMe): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Mine</span>
                            <?php endif; ?>
                            <?php if ($t['merged_into_ticket_id']): ?>
                            <span class="badge bg-secondary ms-1" title="Merged into #<?= (int) $t['merged_into_ticket_id'] ?>"><i class="bi bi-arrow-right-circle"></i> Merged</span>
                            <?php endif; ?>
                            <!-- Hidden source for the hover detail card -->
                            <div class="ld-inbox-src d-none">
                                <div class="ld-pop-head">
                                    <div><strong><?= e($t['creator_name'] ?: 'Unknown') ?></strong> submitted a new ticket</div>
                                    <div class="text-muted small"><span class="ld-rel" data-ts="<?= $ts ?>"></span><?= '(' . $absDate . ')' ?></div>
                                </div>
                                <div class="ld-pop-body">
                                    <div class="fw-semibold mb-1">#<?= $t['id'] ?> &middot; <?= e($t['subject']) ?></div>
                                    <?php if ($snippet !== ''): ?>
                                    <div class="ld-pop-snippet"><?= e($snippet) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ld-pop-foot">
                                    <?= ticketStatusBadgeHtml($t['status']) ?>
                                    <?php if ($t['priority_name']): ?><span class="badge" style="background:<?= e($t['priority_color']) ?>;"><?= e($t['priority_name']) ?></span><?php endif; ?>
                                    <?php if ($t['type_name']): ?><span class="badge" style="background:<?= e($t['type_color'] ?: '#6c757d') ?>;"><?= e($t['type_name']) ?></span><?php endif; ?>
                                    <span class="text-muted small ms-auto"><i class="bi bi-person-check me-1"></i><?= e($t['agent_name'] ?: 'Unassigned') ?></span>
                                </div>
                                <div class="ld-pop-actions">
                                    <a href="<?= $inboxBase ?>/<?= $t['id'] ?>#reply" class="btn btn-sm btn-primary"><i class="bi bi-reply me-1"></i>Reply</a>
                                    <?php if (Auth::can('tickets.forward')): ?>
                                    <a href="<?= $inboxBase ?>/<?= $t['id'] ?>#forward" class="btn btn-sm btn-outline-secondary"><i class="bi bi-forward me-1"></i>Forward</a>
                                    <?php endif; ?>
                                    <a href="<?= $inboxBase ?>/<?= $t['id'] ?>#note" class="btn btn-sm btn-outline-secondary"><i class="bi bi-lock me-1"></i>Add Note</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// (Re)bind the hover detail cards; re-run after an ajax swap replaces the rows.
window.ldInboxHoverInit = function () {
    var table = document.getElementById('ticketInbox');
    if (!table) return;

    // Recreate the card element so listeners from a previous init don't linger.
    var oldPop = document.getElementById('ldInboxPop');
    if (oldPop) oldPop.remove();
    var pop = document.createElement('div'); pop.id = 'ldInboxPop'; document.body.appendChild(pop);

    function relTime(ts) {
        var diff = Math.floor((Date.now() / 1000) - ts);
        if (diff < 45)    return 'just now';
        if (diff < 90)    return 'a minute ago';
        var m = Math.round(diff / 60);
        if (m < 60)       return m + ' minute' + (m === 1 ? '' : 's') + ' ago';
        var h = Math.round(m / 60);
        if (h < 24)       return h + ' hour' + (h === 1 ? '' : 's') + ' ago';
        var d = Math.round(h / 24);
        if (d < 30)       return d + ' day' + (d === 1 ? '' : 's') + ' ago';
        var mo = Math.round(d / 30);
        if (mo < 12)      return mo + ' month' + (mo === 1 ? '' : 's') + ' ago';
        return Math.round(mo / 12) + ' year(s) ago';
    }

    var hideTimer = null, showTimer = null;
    var SHOW_DELAY = 500; // ms to linger before the card appears, so skimming the
                          // list doesn't flash a card under every row.

    // Show a card (cloned from a hidden per-row source) anchored under the cell
    // the cursor is over: the Subject cell shows the ticket card, the From cell
    // shows the person card.
    function show(srcEl, cell) {
        if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        if (!srcEl) return;
        pop.innerHTML = srcEl.innerHTML;
        var rel = pop.querySelector('.ld-rel');
        if (rel) { rel.textContent = relTime(parseInt(rel.getAttribute('data-ts'), 10)) + ' '; }
        // Render first (hidden) so we can measure, then anchor statically.
        pop.style.visibility = 'hidden';
        pop.style.display = 'block';
        anchor(cell);
        pop.style.visibility = '';
    }

    // Pin the card under the hovered text rather than chasing the cursor, so the
    // user can move the mouse straight down into it.
    function anchor(target) {
        var pad = 12;
        var r = target.getBoundingClientRect();
        var w = pop.offsetWidth, h = pop.offsetHeight;
        var x = r.left;
        if (x + w + pad > window.innerWidth) x = window.innerWidth - w - pad;
        if (x < pad) x = pad;
        // Prefer directly below the text (flush, so there's no gap to fall
        // through); flip above if it would overflow the bottom of the viewport.
        var y = r.bottom;
        if (y + h + pad > window.innerHeight) y = r.top - h;
        if (y < pad) y = pad;
        pop.style.left = x + 'px';
        pop.style.top  = y + 'px';
    }

    function scheduleHide() {
        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(function () { pop.style.display = 'none'; }, 180);
    }

    // Arm the show after a short linger. Note we do NOT cancel a pending hide
    // here: if a card is already up and the cursor skims onto another row, that
    // card hides on schedule rather than sticking — only moving the cursor into
    // the card itself (pop mouseenter, below) keeps it open.
    function requestShow(srcEl, cell) {
        if (showTimer) clearTimeout(showTimer);
        showTimer = setTimeout(function () { showTimer = null; show(srcEl, cell); }, SHOW_DELAY);
    }
    function cancelShow() {
        if (showTimer) { clearTimeout(showTimer); showTimer = null; }
    }

    // Keep the card open while the cursor is over it; close only when it leaves.
    pop.addEventListener('mouseenter', function () { if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; } });
    pop.addEventListener('mouseleave', scheduleHide);

    // Trigger on the actual text (the subject and the requester name), not the
    // whole cell — hovering the empty part of a column shows nothing.
    table.querySelectorAll('tbody .ld-inbox-row').forEach(function (row) {
        var subjectText = row.querySelector('.ld-inbox-subject-text');
        var subjectSrc  = row.querySelector('.ld-inbox-src');
        if (subjectText && subjectSrc) {
            subjectText.addEventListener('mouseenter', function () { requestShow(subjectSrc, subjectText); });
            subjectText.addEventListener('mouseleave', function () { cancelShow(); scheduleHide(); });
        }
        var fromName  = row.querySelector('.ld-inbox-from-name');
        var personSrc = row.querySelector('.ld-inbox-src-person');
        if (fromName && personSrc) {
            fromName.addEventListener('mouseenter', function () { requestShow(personSrc, fromName); });
            fromName.addEventListener('mouseleave', function () { cancelShow(); scheduleHide(); });
        }
    });
};
window.ldInboxHoverInit();
</script>
