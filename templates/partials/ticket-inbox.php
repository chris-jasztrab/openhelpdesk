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

    /* Floating hover detail card. */
    #ldInboxPop {
        position: fixed; z-index: 1080; display: none;
        width: 340px; max-width: calc(100vw - 24px);
        background: var(--bs-body-bg, #fff);
        color: var(--bs-body-color, #212529);
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: .5rem;
        box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .18);
        padding: .85rem 1rem;
        pointer-events: none;            /* purely informational — the row click opens the ticket */
    }
    #ldInboxPop .ld-pop-head { border-bottom: 1px solid var(--bs-border-color, #dee2e6); padding-bottom: .55rem; margin-bottom: .55rem; }
    #ldInboxPop .ld-pop-body { font-size: .85rem; }
    #ldInboxPop .ld-pop-snippet {
        color: var(--bs-secondary-color, #6c757d);
        display: -webkit-box; -webkit-line-clamp: 4; line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;
    }
    #ldInboxPop .ld-pop-foot { margin-top: .6rem; display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
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
                            <?= e($t['creator_name'] ?: 'Unknown') ?>
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
(function () {
    var table = document.getElementById('ticketInbox');
    if (!table) return;

    var pop = document.getElementById('ldInboxPop');
    if (!pop) { pop = document.createElement('div'); pop.id = 'ldInboxPop'; document.body.appendChild(pop); }

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

    function show(row, evt) {
        var src = row.querySelector('.ld-inbox-src');
        if (!src) return;
        pop.innerHTML = src.innerHTML;
        var rel = pop.querySelector('.ld-rel');
        if (rel) { rel.textContent = relTime(parseInt(rel.getAttribute('data-ts'), 10)) + ' '; }
        pop.style.display = 'block';
        position(evt);
    }

    function position(evt) {
        var pad = 12;
        var w = pop.offsetWidth, h = pop.offsetHeight;
        var x = evt.clientX + 16;
        var y = evt.clientY + 16;
        if (x + w + pad > window.innerWidth)  x = evt.clientX - w - 16;
        if (x < pad) x = pad;
        if (y + h + pad > window.innerHeight) y = evt.clientY - h - 16;
        if (y < pad) y = pad;
        pop.style.left = x + 'px';
        pop.style.top  = y + 'px';
    }

    function hide() { pop.style.display = 'none'; }

    table.querySelectorAll('tbody .ld-inbox-row').forEach(function (row) {
        if (!row.querySelector('.ld-inbox-src')) return; // redacted rows have no card
        row.addEventListener('mouseenter', function (e) { show(row, e); });
        row.addEventListener('mousemove', function (e) { if (pop.style.display === 'block') position(e); });
        row.addEventListener('mouseleave', hide);
    });
})();
</script>
