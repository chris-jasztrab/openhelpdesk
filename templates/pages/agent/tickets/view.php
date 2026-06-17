<?php
$layout       = 'app';
$pageTitle    = 'Ticket #' . $ticket['id'];
$sidebarItems = agentSidebar('tickets');
$breadcrumbs  = !empty($fromFloor) ? [] : [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Tickets', 'url' => '/agent/tickets'],
    ['label' => '#' . $ticket['id']],
];
$statusLabels = ticketStatusLabelMap();
$actionIcons  = ['created' => 'bi-plus-circle text-success', 'assigned' => 'bi-person-check text-primary', 'status_changed' => 'bi-arrow-repeat text-warning', 'priority_changed' => 'bi-flag text-danger', 'comment' => 'bi-chat-dots text-info', 'internal_note' => 'bi-lock text-secondary', 'sla_set' => 'bi-stopwatch text-primary', 'sla_paused' => 'bi-pause-circle text-warning', 'sla_resumed' => 'bi-play-circle text-success', 'merged' => 'bi-arrow-right-circle text-secondary', 'split' => 'bi-scissors text-warning', 'edited' => 'bi-pencil text-secondary', 'escalated' => 'bi-arrow-up-circle text-danger', 'forwarded' => 'bi-forward text-primary', 'stale_notification_sent' => 'bi-hourglass-split text-warning', 'ai_classified' => 'bi-robot text-info', 'ai_group_routed' => 'bi-signpost-split text-info', 'ai_group_routing_skipped' => 'bi-signpost text-muted', 'ai_duplicate_warned' => 'bi-files text-warning', 'csat_received' => 'bi-star-fill text-warning'];
$actionLabels = ['created' => 'Created', 'assigned' => 'Assigned', 'status_changed' => 'Status Changed', 'priority_changed' => 'Priority Changed', 'comment' => 'Comment', 'internal_note' => 'Internal Note', 'sla_set' => 'SLA Set', 'sla_paused' => 'SLA Paused', 'sla_resumed' => 'SLA Resumed', 'merged' => 'Merged', 'split' => 'Split', 'edited' => 'Edited by Requester', 'escalated' => 'Escalated', 'forwarded' => 'Forwarded', 'stale_notification_sent' => 'Stale Reminder Sent', 'ai_classified' => 'AI Classified', 'ai_group_routed' => 'AI Routed', 'ai_group_routing_skipped' => 'AI Routing Skipped', 'ai_duplicate_warned' => 'AI Duplicate Warning', 'csat_received' => 'Satisfaction Rating'];
$slaStateColors = ['on_track' => 'success', 'warning' => 'warning', 'breached' => 'danger'];
$slaStateLabels = ['on_track' => 'On Track', 'warning' => 'Warning', 'breached' => 'Breached'];

// Resolve the marked-solution timeline row (if any). Defensively re-checks
// is_internal in case the comment was flipped to internal after being
// marked — the "Go to solution" anchor would otherwise point at a row the
// requester cannot see, and we never want to surface internal notes here.
$solutionTimelineId = (int) ($ticket['solution_timeline_id'] ?? 0);
$solutionEntry      = null;
if ($solutionTimelineId > 0) {
    foreach ($timeline as $__solRow) {
        if ((int) $__solRow['id'] === $solutionTimelineId && empty($__solRow['is_internal'])) {
            $solutionEntry = $__solRow;
            break;
        }
    }
}
?>
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
<script type="importmap">
{"imports":{"ckeditor5":"https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.js","ckeditor5/":"https://cdn.ckeditor.com/ckeditor5/43.3.1/"}}
</script>
<style>
#replyBox { scroll-margin-bottom: 1.5rem; }
#replyEditor ~ .ck-editor .ck-editor__editable_inline { min-height: 150px !important; }
.ck.ck-toolbar { border-radius: .375rem .375rem 0 0 !important; border-color: #dee2e6 !important; }
.ck.ck-editor__editable { border-radius: 0 0 .375rem .375rem !important; border-color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-toolbar,
[data-bs-theme="dark"] .ck.ck-toolbar__separator { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-button:not(.ck-disabled):hover,
[data-bs-theme="dark"] .ck.ck-button.ck-on { background: #373b3e !important; }
[data-bs-theme="dark"] .ck.ck-button { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-icon { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-editor__editable { background: #212529 !important; color: #dee2e6 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-editor__editable:not(.ck-focused) { border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-list { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-list__item .ck-button:hover { background: #373b3e !important; }
[data-bs-theme="dark"] .ck.ck-dropdown__panel { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-label,
[data-bs-theme="dark"] .ck.ck-heading_paragraph,
[data-bs-theme="dark"] .ck.ck-list__item .ck-button .ck-button__label { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-input { background: #212529 !important; color: #dee2e6 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-balloon-panel { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-color-grid__tile:hover { border-color: #fff !important; }
.ld-timeline-solution { border-left: 4px solid #198754 !important; background: rgba(25,135,84,.06); }
[data-bs-theme="dark"] .ld-timeline-solution { background: rgba(25,135,84,.16); }
.ld-timeline-solution:target { box-shadow: inset 0 0 0 9999px rgba(25,135,84,.12); transition: box-shadow .8s ease; }
</style>
<?php if (!empty($fromFloor)): ?>
<style>
/* Floor-mode entry: chrome already stripped by embedMode in the layout —
   this just adds a close pill and tightens the top padding so the ✕
   sits within thumb reach on a tablet. */
.main-content { padding-top: 1rem !important; }
#floor-close {
    position: fixed;
    top: max(.85rem, env(safe-area-inset-top, .85rem));
    right: max(.85rem, env(safe-area-inset-right, .85rem));
    z-index: 1050;
    width: 48px; height: 48px; border-radius: 50%;
    background: #fff; color: #1e293b;
    border: 1px solid #cbd5e1;
    box-shadow: 0 4px 12px rgba(15,23,42,.18);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.4rem; text-decoration: none;
}
#floor-close:hover { background: #f1f5f9; color: #1e293b; }
[data-bs-theme="dark"] #floor-close { background: #212529; color: #f8f9fa; border-color: #495057; }
[data-bs-theme="dark"] #floor-close:hover { background: #2b3035; color: #fff; }
</style>
<a id="floor-close" href="/agent/floor/tickets/<?= (int) $ticket['id'] ?>"
   aria-label="Close detailed view and return to floor mode" title="Back to floor view">
    <i class="bi bi-x-lg" aria-hidden="true"></i>
</a>
<?php endif; ?>

<?php if ($ticket['merged_into_ticket_id']): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-arrow-right-circle-fill fs-5"></i>
    <div>
        This ticket was <strong>merged into <a href="/agent/tickets/<?= (int) $ticket['merged_into_ticket_id'] ?>">Ticket #<?= (int) $ticket['merged_into_ticket_id'] ?></a></strong> and is now closed. All further updates should be made on the master ticket.
    </div>
</div>
<?php endif; ?>

<?php if ($solutionEntry): ?>
<a href="#timeline-entry-<?= (int) $solutionEntry['id'] ?>"
   class="alert alert-success d-flex align-items-center gap-2 mb-4 text-decoration-none"
   role="note"
   style="border-left:4px solid #198754;">
    <i class="bi bi-check-circle-fill fs-5"></i>
    <div class="flex-grow-1">
        <strong>Solution available</strong> &middot;
        posted by <?= e($solutionEntry['user_name'] ?: 'an agent') ?>
        on <?= date('M j, Y g:i A', strtotime($solutionEntry['created_at'])) ?>
    </div>
    <span class="btn btn-sm btn-success">
        <i class="bi bi-arrow-down-circle me-1"></i>Go to solution
    </span>
</a>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4" id="tour-ticket-header">
    <div>
        <h2 class="fw-bold mb-1"><?= e($ticket['subject']) ?></h2>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?= ticketStatusBadgeHtml($ticket['status'], 'fs-6') ?>
            <?php if ($ticket['merged_into_ticket_id']): ?>
            <span class="badge bg-secondary fs-6"><i class="bi bi-arrow-right-circle me-1"></i>Merged</span>
            <?php endif; ?>
            <?php if ($ticket['priority_name']): ?>
            <span class="badge fs-6" style="background:<?= e($ticket['priority_color']) ?>;">
                <?= e($ticket['priority_name']) ?>
            </span>
            <?php endif; ?>
            <?php if (slaEnabled() && $ticket['sla_state']): ?>
            <span class="badge bg-<?= $slaStateColors[$ticket['sla_state']] ?? 'secondary' ?> fs-6">
                <i class="bi bi-stopwatch me-1"></i><?= e($slaStateLabels[$ticket['sla_state']] ?? $ticket['sla_state']) ?>
            </span>
            <?php endif; ?>
            <span class="text-muted">Ticket #<?= $ticket['id'] ?></span>
        </div>
    </div>
    <div class="d-flex gap-2" id="tour-ticket-actions">
        <?php if (empty($isConfidential)): ?>
        <form method="POST" action="/agent/tickets/<?= (int) $ticket['id'] ?>/watch">
            <?= csrfField() ?>
            <button type="submit" id="tour-watch-btn" class="btn <?= $isWatching ? 'btn-primary' : 'btn-outline-secondary' ?>"
                    title="<?= $isWatching ? 'Stop watching this ticket' : 'Watch this ticket to receive email updates' ?>">
                <i class="bi <?= $isWatching ? 'bi-eye-fill' : 'bi-eye' ?> me-1"></i><?= $isWatching ? 'Watching' : 'Watch' ?>
            </button>
        </form>
        <?php endif; ?>
        <?php if (!$ticket['merged_into_ticket_id']): ?>
        <?php if ($hasEscalationPath): ?>
        <?php
            $canEscalate = !in_array($ticket['status'], ['resolved', 'closed'], true);
            $escTitle = 'Escalate this ticket to the next person in the path';
            if (!$canEscalate) {
                $escTitle = 'Closed tickets cannot be escalated.';
            } elseif (!$nextEscalationStep) {
                $escTitle = 'No further escalation step is available — you are at the top of the chain (or the next step is you).';
            } else {
                $escTitle .= ' — next: ' . $nextEscalationStep['user_name'] . ' (Level ' . $nextEscalationStep['step_order'] . ')';
            }
            $escalateDisabled = !$canEscalate || !$nextEscalationStep;
        ?>
        <button type="button"
                id="escalateBtn"
                class="btn btn-outline-danger"
                title="<?= e($escTitle) ?>"
                <?= $escalateDisabled ? 'disabled' : '' ?>
                data-bs-toggle="modal"
                data-bs-target="#escalateModal">
            <i class="bi bi-arrow-up-circle me-1"></i>Escalate
        </button>
        <?php endif; ?>
        <a href="/agent/tickets/<?= (int) $ticket['id'] ?>/split" id="tour-split-btn" class="btn btn-outline-warning">
            <i class="bi bi-scissors me-1"></i>Split
        </a>
        <button type="button" id="tour-merge-btn" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mergeModal">
            <i class="bi bi-arrow-right-circle me-1"></i>Merge
        </button>
        <?php endif; ?>
        <a href="/agent/tickets" class="btn btn-outline-secondary" id="backBtn">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left column: Description + Timeline -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4" id="tour-ticket-description">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-text-paragraph me-2"></i>Description</h5>
            </div>
            <div class="card-body">
                <?php $desc = $ticket['description'] ?? ''; ?>
                <?php if ($desc !== '' && ltrim($desc)[0] === '<'): ?>
                <div class="ck-content"><?= sanitizeRichHtml($desc) ?></div>
                <?php else: ?>
                <div style="white-space:pre-wrap;"><?= linkify($desc) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // Split attachments: those linked to a timeline entry render inline; others show here
        $attachmentsByTimeline = [];
        $standaloneAttachments = [];
        foreach ($attachments as $att) {
            if ($att['timeline_id']) {
                $attachmentsByTimeline[$att['timeline_id']][] = $att;
            } else {
                $standaloneAttachments[] = $att;
            }
        }
        ?>
        <?php if (!empty($standaloneAttachments)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-paperclip me-2"></i>Attachments</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($standaloneAttachments as $att): ?>
                <a href="/agent/attachments/<?= $att['id'] ?>/download"
                   class="list-group-item list-group-item-action d-flex align-items-center gap-3 <?= !empty($att['is_internal']) ? 'bg-warning bg-opacity-10' : '' ?>">
                    <i class="bi <?= getFileIcon($att['mime_type']) ?> fs-4"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">
                            <?= e($att['original_name']) ?>
                            <?php if (!empty($att['is_internal'])): ?>
                            <span class="badge bg-warning text-dark ms-1">Internal</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?= formatFileSize($att['file_size']) ?></small>
                    </div>
                    <i class="bi bi-download text-muted"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (getSetting('tags_enabled', '1') === '1'): ?>
        <!-- Tags -->
        <div class="mb-4" id="ticketTags"
             data-ticket-id="<?= $ticket['id'] ?>"
             data-tags="<?= e(json_encode($ticket['tags'] ?? [])) ?>">
            <div class="d-flex flex-wrap gap-1 align-items-center" id="tagList"></div>
        </div>
        <?php endif; ?>

        <?php
        // Timeline-note visibility: admins can hide AI-generated notes, or all
        // automated system notes, from the timeline. The notes still render
        // into the DOM — CSS classes on the list hide them — so the sliders
        // toggle them without a reload. AI notes are a subset of system notes.
        $isAdmin        = Auth::isAdmin();
        $aiNotesOn      = !$isAdmin || aiNotesVisible();
        $systemNotesOn  = !$isAdmin || systemNotesVisible();
        $hasAiNotes     = false;
        $hasSystemNotes = false;
        if ($isAdmin) {
            foreach ($timeline as $__tl) {
                if (!$__tl['is_internal'] || $__tl['user_name']) {
                    continue;
                }
                $hasSystemNotes = true;
                if (str_starts_with((string) $__tl['action'], 'ai_')) {
                    $hasAiNotes = true;
                }
                if ($hasAiNotes) {
                    break; // both flags are now set
                }
            }
        }
        ?>
        <div class="card border-0 shadow-sm" id="tour-ticket-timeline">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Timeline</h5>
                <?php if ($isAdmin && $hasSystemNotes): ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="systemNotesToggle" <?= $systemNotesOn ? 'checked' : '' ?>>
                        <label class="form-check-label small text-muted" for="systemNotesToggle"
                               title="Show or hide automated system notes on this timeline">
                            <i class="bi bi-gear me-1"></i>System notes
                        </label>
                    </div>
                    <?php if ($hasAiNotes): ?>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="aiNotesToggle" <?= $aiNotesOn ? 'checked' : '' ?>>
                        <label class="form-check-label small text-muted" for="aiNotesToggle"
                               title="Show or hide AI-generated system notes on this timeline">
                            <i class="bi bi-robot me-1"></i>AI notes
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($timeline)): ?>
                <div class="text-center py-4 text-muted">No timeline entries.</div>
                <?php else:
                $tlHidden = max(0, count($timeline) - 10);
                ?>
                <div class="list-group list-group-flush<?= $aiNotesOn ? '' : ' ai-notes-hidden' ?><?= $systemNotesOn ? '' : ' system-notes-hidden' ?>" id="timelineList">
                    <?php foreach ($timeline as $tlIdx => $entry):
                        $isSystem   = $entry['is_internal'] && !$entry['user_name'];
                        $isAi       = $isSystem && str_starts_with((string) $entry['action'], 'ai_');
                        // Admins see AI/system notes; non-admins still don't.
                        if ($isSystem && !Auth::isAdmin()) continue;
                        $isSolution = $solutionTimelineId > 0 && (int) $entry['id'] === $solutionTimelineId;
                        // Never let the marked solution be hidden inside the
                        // older-updates collapser — it's the whole point of the
                        // "Go to solution" anchor that the target be there.
                        $isOlder    = !$isSolution && $tlIdx >= 10;
                        $isNote     = $entry['is_internal'] && $entry['user_name'];
                        // A public comment whose author is an auto-provisioned
                        // external contact (forwarded third party) — flag it so
                        // staff can tell it didn't come from the requester/staff.
                        $isExternal = $entry['action'] === 'comment' && empty($entry['is_internal']) && !empty($entry['author_is_external']);
                        $tlClass    = $isNote ? 'ld-timeline-note' : ($isSystem ? 'ld-timeline-system' : '');
                        if ($isAi)       $tlClass .= ' ld-timeline-ai';
                        if ($isSolution) $tlClass .= ' ld-timeline-solution';
                        $canMarkSolution = $entry['action'] === 'comment' && empty($entry['is_internal']);
                    ?>
                    <div class="list-group-item px-4 py-3 <?= $tlClass ?><?= $isOlder ? ' timeline-older-item' : '' ?>"
                         id="timeline-entry-<?= (int) $entry['id'] ?>"
                         <?= $isOlder ? 'style="display:none;"' : '' ?>>
                        <div class="d-flex gap-3">
                            <div class="pt-1">
                                <?php if ($isSolution): ?>
                                <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                                <?php elseif ($isNote): ?>
                                <i class="bi bi-lock-fill fs-5" style="color:var(--ld-timeline-note-accent);"></i>
                                <?php else: ?>
                                <i class="bi <?= $actionIcons[$entry['action']] ?? 'bi-circle text-muted' ?> fs-5"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="fw-semibold"><?= e($entry['user_name'] ?: 'System') ?></span>
                                        <span class="text-muted ms-1"><?= e($actionLabels[$entry['action']] ?? ucwords(str_replace('_', ' ', $entry['action']))) ?></span>
                                        <?php if ($isSolution): ?>
                                        <span class="badge ms-1 bg-success"><i class="bi bi-check-circle me-1"></i>Solution</span>
                                        <?php endif; ?>
                                        <?php if ($isNote): ?>
                                        <span class="badge ms-1" style="background:var(--ld-timeline-note-accent); color:#fff;">Internal</span>
                                        <?php elseif ($isAi): ?>
                                        <span class="badge ms-1 bg-info text-dark" title="Visible to admins only"><i class="bi bi-robot me-1"></i>AI &middot; Admin only</span>
                                        <?php endif; ?>
                                        <?php if ($isExternal): ?>
                                        <span class="badge ms-1 bg-secondary" title="Reply from an external contact (forwarded third party), not the requester or staff"><i class="bi bi-box-arrow-in-left me-1"></i>External</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($canMarkSolution): ?>
                                        <form method="POST" action="/agent/tickets/<?= (int) $ticket['id'] ?>/solution" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="timeline_id" value="<?= $isSolution ? '' : (int) $entry['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= $isSolution ? 'btn-outline-secondary' : 'btn-outline-success' ?> py-0 px-2"
                                                    title="<?= $isSolution ? 'Remove the solution flag from this reply' : 'Flag this reply as the solution to the ticket' ?>">
                                                <i class="bi bi-check-circle me-1"></i><?= $isSolution ? 'Unmark solution' : 'Mark as solution' ?>
                                            </button>
                                        </form>
                                        <?php elseif ($entry['action'] === 'comment' && $isNote): ?>
                                        <small class="text-muted fst-italic"
                                               title="The requester can't see internal notes, so we can't anchor a 'Go to solution' link at one. Reply publicly to mark a solution.">
                                            <i class="bi bi-info-circle me-1"></i>internal &mdash; can't be the solution
                                        </small>
                                        <?php endif; ?>
                                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($entry['created_at'])) ?></small>
                                    </div>
                                </div>
                                <?php if ($entry['details']): ?>
                                <?php
                                    $det = $entry['details'] ?? '';
                                    $isHtmlDet = $det !== '' && ltrim($det)[0] === '<';
                                    if (!$isHtmlDet) {
                                        $det = linkify($det);
                                        if ($entry['action'] === 'merged') {
                                            $det = preg_replace('/Ticket #(\d+)/', '<a href="/agent/tickets/$1" class="text-reset" target="_blank" rel="noopener">Ticket #$1</a>', $det);
                                            $det = preg_replace('/(merged into )#(\d+)/', '$1<a href="/agent/tickets/$2" class="text-reset" target="_blank" rel="noopener">#$2</a>', $det);
                                        }
                                    }
                                ?>
                                <?php if ($isHtmlDet): ?>
                                <div class="mt-1 ck-content"><?= sanitizeRichHtml($det) ?></div>
                                <?php else: ?>
                                <div class="mt-1 text-muted" style="white-space:pre-wrap;"><?= $det ?></div>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($attachmentsByTimeline[$entry['id']])): ?>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <?php foreach ($attachmentsByTimeline[$entry['id']] as $att): ?>
                                    <a href="/agent/attachments/<?= $att['id'] ?>/download"
                                       class="d-inline-flex align-items-center gap-1 text-decoration-none border rounded px-2 py-1 small bg-light text-dark">
                                        <i class="bi <?= getFileIcon($att['mime_type']) ?>"></i>
                                        <?= e($att['original_name']) ?>
                                        <span class="text-muted">(<?= formatFileSize($att['file_size']) ?>)</span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($tlHidden > 0): ?>
                    <div class="list-group-item px-3 py-2 text-center bg-light border-top-0" id="timeline-expand-row">
                        <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none" id="timeline-expand-btn">
                            <i class="bi bi-chevron-up me-1" id="timeline-expand-icon"></i>
                            <span id="timeline-expand-label">Show <?= $tlHidden ?> older update<?= $tlHidden !== 1 ? 's' : '' ?></span>
                        </button>
                    </div>
                    <script>
                    (function() {
                        var btn      = document.getElementById('timeline-expand-btn');
                        var icon     = document.getElementById('timeline-expand-icon');
                        var label    = document.getElementById('timeline-expand-label');
                        var items    = document.querySelectorAll('.timeline-older-item');
                        var n        = <?= $tlHidden ?>;
                        var expanded = false;
                        btn.addEventListener('click', function() {
                            expanded = !expanded;
                            items.forEach(function(el) { el.style.display = expanded ? '' : 'none'; });
                            icon.className    = expanded ? 'bi bi-chevron-down me-1' : 'bi bi-chevron-up me-1';
                            label.textContent = expanded ? 'Show fewer updates' : 'Show ' + n + ' older update' + (n !== 1 ? 's' : '');
                        });
                    })();
                    </script>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdmin && $hasSystemNotes): ?>
        <script>
        // Timeline-note sliders: each flips a CSS class on the timeline list so
        // the rows hide instantly, and persists the choice to the user's
        // profile so it sticks across tickets and sessions (same settings as
        // the profile page).
        (function () {
            var list = document.getElementById('timelineList');
            if (!list) return;
            var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            function wire(toggleId, cssClass, endpoint) {
                var toggle = document.getElementById(toggleId);
                if (!toggle) return;
                toggle.addEventListener('change', function () {
                    var on = toggle.checked;
                    list.classList.toggle(cssClass, !on);
                    fetch(endpoint, {
                        method:  'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body:    '_token=' + encodeURIComponent(csrf) + '&visible=' + (on ? '1' : '0'),
                    }).catch(function () {});
                });
            }
            wire('systemNotesToggle', 'system-notes-hidden', '/profile/system-notes');
            wire('aiNotesToggle',     'ai-notes-hidden',     '/profile/ai-notes');
        })();
        </script>
        <?php endif; ?>

        <!-- Reply / Forward / Note action bar -->
        <div class="d-flex gap-2 mt-4" id="replyActionBar">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnReply">
                <i class="bi bi-reply me-1"></i>Reply
            </button>
            <?php if (canForwardTickets()): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnForward">
                <i class="bi bi-forward me-1"></i>Forward
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-warning" id="btnNote">
                <i class="bi bi-lock me-1"></i>Add Note
            </button>
        </div>

        <!-- Collapsible reply / note panel -->
        <div id="replyBox" style="display:none;" class="mt-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header border-bottom d-flex align-items-center justify-content-between py-2 bg-white" id="replyBoxHeader">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold" id="replyBoxTitle"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCanned" title="Insert canned response">
                            <i class="bi bi-chat-square-text me-1"></i>Canned
                        </button>
                    </div>
                    <button type="button" class="btn-close" id="replyBoxClose" aria-label="Close"></button>
                </div>
                <div class="card-body">
                    <form method="POST" action="/agent/tickets/<?= $ticket['id'] ?>/comment" enctype="multipart/form-data" id="replyForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="is_internal" id="replyIsInternal" value="0">
                        <input type="hidden" name="status_after" id="replyStatusAfter" value="">
                        <?php
                        $canFwdInt = Auth::can('tickets.forward.internal');
                        $canFwdExt = Auth::can('tickets.forward.external');
                        $fwdHint = $canFwdInt && $canFwdExt
                            ? 'Enter internal contacts or external email addresses.'
                            : ($canFwdExt
                                ? 'Enter external email addresses.'
                                : 'Enter the email of an existing internal contact — you can&rsquo;t forward to external addresses.');
                        ?>
                        <div class="mb-3" id="forwardRecipientsRow" style="display:none;">
                            <label for="forwardTo" class="form-label small fw-semibold">Forward to <span class="text-muted fw-normal">(email addresses, separated by commas)</span></label>
                            <input type="text" class="form-control" name="forward_to" id="forwardTo" placeholder="vendor@example.com, jane.doe@partner.org" autocomplete="off">
                            <div class="form-text"><?= $fwdHint ?> Recipients are added as contacts and CC'd on the ticket; their email replies thread back in automatically. The full conversation and all attachments are included.</div>
                        </div>
                        <div class="mb-3" style="position:relative;">
                            <div id="replyEditor"></div>
                            <input type="hidden" name="message" id="replyMessageHidden">
                            <div id="mentionDropdown" class="mention-dropdown" style="display:none;"></div>
                        </div>
                        <div class="mb-3">
                            <input type="file" class="form-control" name="attachments[]" multiple>
                            <div class="form-text">Max <?= UPLOAD_MAX_SIZE / 1024 / 1024 ?>MB per file</div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <div class="btn-group">
                                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                                    <i class="bi bi-send me-1" id="replySubmitIcon"></i><span id="replySubmitLabel">Send Reply</span>
                                </button>
                                <button type="button" class="btn text-white dropdown-toggle dropdown-toggle-split"
                                        style="background:var(--ld-primary);border-left:1px solid rgba(255,255,255,.3);"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden">More send options</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><h6 class="dropdown-header small">Send &amp; change status to</h6></li>
                                    <li><a class="dropdown-item reply-status-opt" href="#" data-status="resolved"><i class="bi bi-check-circle me-2 text-success"></i>Resolved</a></li>
                                    <li><a class="dropdown-item reply-status-opt" href="#" data-status="closed"><i class="bi bi-x-circle me-2 text-secondary"></i>Closed</a></li>
                                    <li><a class="dropdown-item reply-status-opt" href="#" data-status="pending"><i class="bi bi-pause-circle me-2 text-info"></i>Pending</a></li>
                                    <li><a class="dropdown-item reply-status-opt" href="#" data-status="waiting_on_customer"><i class="bi bi-person-clock me-2 text-warning"></i>Waiting on Customer</a></li>
                                    <li><a class="dropdown-item reply-status-opt" href="#" data-status="waiting_on_third_party"><i class="bi bi-building-check me-2"></i>Waiting on Third Party</a></li>
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Escalate Ticket Modal -->
    <?php if (!$ticket['merged_into_ticket_id'] && $nextEscalationStep): ?>
    <div class="modal fade" id="escalateModal" tabindex="-1" aria-labelledby="escalateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold" id="escalateModalLabel">
                        <i class="bi bi-arrow-up-circle me-2 text-danger"></i>Escalate Ticket
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        This ticket will be reassigned to:
                    </p>
                    <div class="p-3 bg-body-tertiary rounded mb-3">
                        <div>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">
                                Level <?= (int) $nextEscalationStep['step_order'] ?>
                            </span>
                            <strong><?= e($nextEscalationStep['user_name']) ?></strong>
                        </div>
                        <?php if (!empty($nextEscalationStep['label'])): ?>
                        <div class="text-muted small mt-1"><?= e($nextEscalationStep['label']) ?></div>
                        <?php endif; ?>
                    </div>

                    <label for="escalateReason" class="form-label small text-muted">Reason <span class="text-muted">(optional, but recommended)</span></label>
                    <textarea id="escalateReason" class="form-control" rows="3" maxlength="2000"
                              placeholder="Why are you escalating? What have you tried?"></textarea>

                    <div id="escalateError" class="alert alert-danger mt-3 d-none" role="alert"></div>

                    <p class="small text-muted mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>The current assignee will be added as a watcher so they stay in the loop.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="escalateConfirmBtn" class="btn btn-danger">
                        <i class="bi bi-arrow-up-circle me-1"></i>Escalate to <?= e($nextEscalationStep['user_name']) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var btn  = document.getElementById('escalateConfirmBtn');
        var err  = document.getElementById('escalateError');
        var txt  = document.getElementById('escalateReason');
        if (!btn) return;
        var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        btn.addEventListener('click', function () {
            err.classList.add('d-none');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Escalating…';
            fetch('/api/tickets/<?= (int) $ticket['id'] ?>/escalate', {
                method:  'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body:    JSON.stringify({reason: (txt && txt.value) ? txt.value.trim() : ''}),
            }).then(function (r) { return r.json().then(function (b) { return {ok: r.ok, body: b}; }); })
              .then(function (res) {
                  if (!res.ok || !res.body.success) {
                      err.textContent = (res.body && res.body.error) || 'Escalation failed.';
                      err.classList.remove('d-none');
                      btn.disabled = false;
                      btn.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i>Retry';
                      return;
                  }
                  window.location.reload();
              })
              .catch(function () {
                  err.textContent = 'Network error. Please try again.';
                  err.classList.remove('d-none');
                  btn.disabled = false;
                  btn.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i>Retry';
              });
        });
    })();
    </script>
    <?php endif; ?>

    <!-- Canned Response Picker Modal -->
    <div class="modal fade" id="cannedModal" tabindex="-1" aria-labelledby="cannedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold" id="cannedModalLabel"><i class="bi bi-chat-square-text me-2"></i>Insert Canned Response</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="text" class="form-control mb-3" id="cannedSearch" placeholder="Search by title or content…" autocomplete="off">
                    <div id="cannedList" style="max-height:360px;overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Middle column: Details + Custom Fields + SLA -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm mb-4" id="tour-ticket-details">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Details</h5>
            </div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted small">Status</dt>
                    <dd>
                        <?= ticketStatusBadgeHtml($ticket['status']) ?>
                    </dd>

                    <dt class="text-muted small">Priority</dt>
                    <dd>
                        <?php if ($ticket['priority_name']): ?>
                        <span class="badge" style="background:<?= e($ticket['priority_color']) ?>;"><?= e($ticket['priority_name']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">Not set</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="text-muted small">Type</dt>
                    <dd><?php if ($ticket['type_name']): ?><span class="badge" style="background:<?= e($ticket['type_color'] ?: '#6c757d') ?>;"><?= e($ticket['type_name']) ?></span><?php else: ?><span class="text-muted">Not Set</span><?php endif; ?></dd>

                    <dt class="text-muted small">Assigned To</dt>
                    <dd>
                        <?php
                            $qaAgents = array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['first_name'] . ' ' . $a['last_name']], $agents);
                        ?>
                        <span class="d-inline-flex align-items-center gap-1 quick-assign-wrap" data-ticket-id="<?= (int)$ticket['id'] ?>">
                            <span class="quick-assign-name"><?= e($ticket['agent_name'] ?: 'Unassigned') ?></span>
                            <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-assign-btn"
                                    type="button"
                                    data-agents="<?= e(json_encode($qaAgents)) ?>"
                                    title="Change assignee"
                                    style="line-height:1;">
                                <i class="bi bi-chevron-down" style="font-size:0.65rem;"></i>
                            </button>
                        </span>
                    </dd>

                    <dt class="text-muted small">Group</dt>
                    <dd><?= e($ticket['group_name'] ?? 'None') ?></dd>

                    <?php if ((int) ($ticket['escalation_level'] ?? 0) > 0): ?>
                    <dt class="text-muted small">Escalation</dt>
                    <dd>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bi bi-arrow-up-circle me-1"></i>Level <?= (int) $ticket['escalation_level'] ?>
                        </span>
                    </dd>
                    <?php endif; ?>

                    <dt class="text-muted small">Created By</dt>
                    <dd>
                        <?= e($ticket['creator_name'] ?? '—') ?>
                        <?php if (!empty($ticket['creator_email'])): ?>
                        <br><small class="text-muted"><?= e($ticket['creator_email']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($ticket['submitted_by']) && !empty($ticket['submitter_name'])): ?>
                        <br>
                        <small class="text-muted d-inline-flex align-items-center gap-1 mt-1"
                               title="Filed via the helpdesk on the requester's behalf — the requester still owns the ticket and receives notifications.">
                            <i class="bi bi-telephone-forward"></i>
                            Filed by <?= e($ticket['submitter_name']) ?> on their behalf
                        </small>
                        <?php endif; ?>
                    </dd>

                    <dt class="text-muted small"><?= label('location.singular') ?></dt>
                    <dd><?= e($ticket['location_name'] ?? 'Not set') ?></dd>

                    <dt class="text-muted small">Created</dt>
                    <dd><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></dd>

                    <dt class="text-muted small">Due Date</dt>
                    <dd>
                        <?php if ($ticket['due_date']): ?>
                            <?php
                            $due = strtotime($ticket['due_date']);
                            $overdue = $due < time() && !in_array($ticket['status'], ['resolved', 'closed']);
                            ?>
                            <span class="<?= $overdue ? 'text-danger fw-bold' : '' ?>">
                                <?= date('M j, Y', $due) ?>
                                <?= $overdue ? ' (Overdue)' : '' ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">Not set</span>
                        <?php endif; ?>
                    </dd>

                    <?php if (roleIsAdmin($user['role'])): ?>
                    <hr>
                    <dt class="text-muted small">Browser</dt>
                    <dd class="small"><?= e($ticket['browser_info'] ?? 'Unknown') ?></dd>

                    <dt class="text-muted small">Operating System</dt>
                    <dd class="small"><?= e($ticket['os_info'] ?? 'Unknown') ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Escalation History -->
        <?php if (!empty($escalationHistory)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-arrow-up-circle me-2 text-danger"></i>Escalation History</h5>
            </div>
            <div class="card-body p-3 small">
                <ol class="list-unstyled mb-0">
                    <?php foreach ($escalationHistory as $h): ?>
                    <li class="mb-2 pb-2 border-bottom">
                        <div>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">Level <?= (int) $h['step_order'] ?></span>
                            <strong><?= e($h['to_name'] ?? '—') ?></strong>
                        </div>
                        <div class="text-muted" style="font-size:.8rem;">
                            by <?= e($h['by_name'] ?? '—') ?>
                            <?php if (!empty($h['from_name'])): ?> (from <?= e($h['from_name']) ?>)<?php endif; ?>
                            · <?= date('M j, Y g:i A', strtotime($h['created_at'])) ?>
                        </div>
                        <?php if (!empty($h['reason'])): ?>
                        <div class="mt-1" style="white-space:pre-wrap;"><?= e($h['reason']) ?></div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
        <?php endif; ?>

        <!-- Satisfaction Survey -->
        <?php require ROOT_DIR . '/templates/partials/ticket-csat.php'; ?>

        <!-- Custom Fields -->
        <?php if (!empty($customFields)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-list-check me-2"></i>Custom Fields</h5>
            </div>
            <div class="card-body p-3">
                <dl class="row mb-0 small">
                <?php foreach ($customFields as $cf):
                    $cfVal  = $fieldValues[$cf['id']] ?? '';
                    $cfOpts = $fieldOptions[$cf['id']] ?? [];
                ?>
                <dt class="col-5 text-muted fw-medium">
                    <?= e($cf['label']) ?>
                </dt>
                <dd class="col-7 mb-2">
                    <?php if ($cf['field_type'] === 'checkbox'): ?>
                        <?= $cfVal === '1' ? 'Yes' : 'No' ?>
                    <?php elseif ($cf['field_type'] === 'dropdown'): ?>
                        <?php
                            $selOpt = array_filter($cfOpts, fn($o) => (int)$o['id'] === (int)$cfVal);
                            $selOpt = reset($selOpt);
                        ?>
                        <?= $selOpt ? e($selOpt['label']) : '<span class="text-muted">&mdash;</span>' ?>
                    <?php elseif ($cf['field_type'] === 'dependent'): ?>
                        <?php
                            $dep   = $cfVal ? (json_decode($cfVal, true) ?? []) : [];
                            $parts = [];
                            foreach (['l1','l2','l3'] as $lk) {
                                if (!empty($dep[$lk])) {
                                    $opt = array_filter($cfOpts, fn($o) => (int)$o['id'] === (int)$dep[$lk]);
                                    $opt = reset($opt);
                                    if ($opt) $parts[] = e($opt['label']);
                                }
                            }
                        ?>
                        <?= $parts ? implode(' &rsaquo; ', $parts) : '<span class="text-muted">&mdash;</span>' ?>
                    <?php elseif ($cf['field_type'] === 'date_range'): ?>
                        <?php
                            $dr = $cfVal ? (json_decode($cfVal, true) ?? []) : [];
                            $drFrom = $dr['from'] ?? '';
                            $drTo   = $dr['to']   ?? '';
                        ?>
                        <?= ($drFrom || $drTo) ? e($drFrom) . ' &mdash; ' . e($drTo) : '<span class="text-muted">&mdash;</span>' ?>
                    <?php else: ?>
                        <?= $cfVal !== '' ? e($cfVal) : '<span class="text-muted">&mdash;</span>' ?>
                    <?php endif; ?>
                </dd>
                <?php endforeach; ?>
                </dl>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right column: CC + Update Ticket + SLA -->
    <div class="col-lg-3">
        <!-- CC -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>CC</h5>
            </div>
            <div class="card-body" id="ccSection"
                 data-ticket-id="<?= $ticket['id'] ?>"
                 data-cc="<?= e(json_encode($ccUsers ?? [])) ?>">
                <div id="ccList"></div>
                <div class="mt-2" style="position:relative;">
                    <input type="text" class="form-control form-control-sm" id="ccSearchInput"
                           placeholder="Search users to CC..." autocomplete="off">
                    <div id="ccDropdown" class="mention-dropdown" style="display:none;"></div>
                </div>
            </div>
        </div>

        <!-- Update Ticket -->
        <div class="card border-0 shadow-sm" id="tour-update-ticket">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-pencil-square me-2"></i>Update Ticket</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/agent/tickets/<?= $ticket['id'] ?>/update">
                    <?= csrfField() ?>
                    <?php /* Optimistic-lock token: the status this page rendered with. */ ?>
                    <input type="hidden" name="expected_status" value="<?= e($ticket['status']) ?>">

                    <div class="mb-3">
                        <label for="status" class="form-label fw-semibold small">Status</label>
                        <select class="form-select form-select-sm" name="status" id="status">
                            <?php foreach ($statusLabels as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $ticket['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="priority_id" class="form-label fw-semibold small">Priority</label>
                        <select class="form-select form-select-sm" name="priority_id" id="priority_id">
                            <option value="">None</option>
                            <?php foreach ($priorities as $pri): ?>
                            <option value="<?= $pri['id'] ?>" <?= (int) ($ticket['priority_id'] ?? 0) === (int) $pri['id'] ? 'selected' : '' ?>>
                                <?= e($pri['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="assigned_to" class="form-label fw-semibold small">Assigned To</label>
                        <select class="form-select form-select-sm" name="assigned_to" id="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($agents as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= (int) ($ticket['assigned_to'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
                                <?= e($a['first_name'] . ' ' . $a['last_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="group_id" class="form-label fw-semibold small">Group</label>
                        <select class="form-select form-select-sm" name="group_id" id="group_id">
                            <option value="">None</option>
                            <?php foreach ($groups as $grp): ?>
                            <option value="<?= $grp['id'] ?>" <?= (int) ($ticket['group_id'] ?? 0) === (int) $grp['id'] ? 'selected' : '' ?>>
                                <?= e($grp['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="type_id" class="form-label fw-semibold small">Type</label>
                        <select class="form-select form-select-sm" name="type_id" id="type_id">
                            <option value="">None</option>
                            <?php foreach ($ticketTypes as $tt): ?>
                            <option value="<?= $tt['id'] ?>" <?= (int) ($ticket['type_id'] ?? 0) === (int) $tt['id'] ? 'selected' : '' ?>>
                                <?= e($tt['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" id="updateTicketBtn" class="btn btn-sm text-white w-100" style="background:var(--ld-primary);" disabled>
                        <i class="bi bi-check-lg me-1"></i>Update
                    </button>
                </form>
            </div>
        </div>

        <!-- SLA Info -->
        <?php if (slaEnabled() && $ticket['sla_state']): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-stopwatch me-2"></i>SLA</h5>
            </div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted small">State</dt>
                    <dd>
                        <span class="badge bg-<?= $slaStateColors[$ticket['sla_state']] ?? 'secondary' ?>">
                            <?= e($slaStateLabels[$ticket['sla_state']] ?? $ticket['sla_state']) ?>
                        </span>
                        <?php if (!empty($ticket['sla_paused_at'])): ?>
                        <span class="badge bg-info ms-1"><i class="bi bi-pause-fill"></i> Paused</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="text-muted small">First Response</dt>
                    <dd>
                        <?php if ($ticket['first_responded_at']): ?>
                            <span class="text-success"><i class="bi bi-check-circle me-1"></i>Responded <?= date('M j, g:i A', strtotime($ticket['first_responded_at'])) ?></span>
                        <?php elseif ($ticket['first_response_due_at']): ?>
                            <?php
                            $frDue = new DateTimeImmutable($ticket['first_response_due_at']);
                            $frOverdue = $frDue < new DateTimeImmutable('now');
                            ?>
                            <span class="<?= $frOverdue ? 'text-danger fw-bold' : '' ?>">
                                Due <?= $frDue->format('M j, g:i A') ?>
                                <?= $frOverdue ? ' (Overdue)' : '' ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="text-muted small">Resolution</dt>
                    <dd>
                        <?php if ($ticket['resolution_due_at']): ?>
                            <?php
                            $resDue = new DateTimeImmutable($ticket['resolution_due_at']);
                            $resOverdue = $resDue < new DateTimeImmutable('now') && !in_array($ticket['status'], ['resolved', 'closed']);
                            ?>
                            <span class="<?= $resOverdue ? 'text-danger fw-bold' : '' ?>">
                                Due <?= $resDue->format('M j, g:i A') ?>
                                <?= $resOverdue ? ' (Overdue)' : '' ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$ticket['merged_into_ticket_id']): ?>
<!-- Merge Modal -->
<div class="modal fade" id="mergeModal" tabindex="-1" aria-labelledby="mergeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mergeModalLabel"><i class="bi bi-arrow-right-circle me-2"></i>Merge Ticket #<?= $ticket['id'] ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    The secondary ticket will be <strong>closed</strong> and linked to the primary. The original submitter and any CC'd users will be notified.
                </p>
                <div class="mb-3" style="position:relative;">
                    <label class="form-label fw-semibold small">Search for ticket to merge with</label>
                    <input type="text" id="mergeSearchInput" class="form-control" placeholder="Type ticket # or subject..." autocomplete="off">
                    <div id="mergeResults" class="list-group shadow-sm" style="display:none;position:absolute;z-index:1050;width:100%;max-height:240px;overflow-y:auto;"></div>
                </div>
                <div id="mergeSelectedPreview" class="alert alert-success py-2 small" style="display:none;"></div>
                <div id="mergePrimaryChoice" class="mt-2" style="display:none;">
                    <label class="form-label fw-semibold small">Which ticket should be the primary?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="mergePrimary" id="mergePrimarySelected" value="selected" checked>
                        <label class="form-check-label small" id="mergePrimarySelectedLabel" for="mergePrimarySelected">Selected ticket</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="mergePrimary" id="mergePrimaryThis" value="this">
                        <label class="form-check-label small" for="mergePrimaryThis">This ticket (#<?= $ticket['id'] ?>)</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/agent/tickets/<?= $ticket['id'] ?>/merge" class="d-inline" id="mergeForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="merge_into_id" id="mergeTargetId">
                    <button type="submit" id="mergeConfirmBtn" class="btn btn-danger" disabled>
                        <i class="bi bi-arrow-right-circle me-1"></i>Merge Tickets
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Presence Warning Modal -->
<div class="modal fade" id="presenceModal" tabindex="-1" aria-labelledby="presenceModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title" id="presenceModalLabel"><i class="bi bi-people-fill me-2"></i>Ticket Already Open</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="presenceModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Dismiss</button>
            </div>
        </div>
    </div>
</div>

<script>
var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
// Back button → the screen the user actually came from (notifications, dashboard,
// ticket list, ...). The breadcrumb "Tickets" link always points at the filtered
// ticket list so filters survive. Both fall back to /agent/tickets.
(function() {
    var saved = sessionStorage.getItem('agentTicketListUrl');

    // Breadcrumb "Tickets" → the previously visited (filtered) ticket list.
    if (saved) {
        document.querySelectorAll('.breadcrumb-item a').forEach(function (a) {
            if (a.getAttribute('href') === '/agent/tickets') a.href = saved;
        });
    }

    var backBtn = document.getElementById('backBtn');
    if (!backBtn) return;

    // Remember the referring page per-ticket so it survives reloads and in-page
    // POSTs (which make document.referrer the ticket itself).
    var ticketPath = window.location.pathname;
    var key = 'agentTicketBackUrl:' + ticketPath;
    if (document.referrer) {
        try {
            var u = new URL(document.referrer);
            if (u.origin === window.location.origin && u.pathname !== ticketPath) {
                sessionStorage.setItem(key, u.pathname + u.search + u.hash);
            }
        } catch (e) {}
    }
    var back = sessionStorage.getItem(key) || saved;
    if (back) backBtn.href = back;
})();


<?php if (getSetting('tags_enabled', '1') === '1'): ?>
// Tag management
(function() {
    var container = document.getElementById('ticketTags');
    var list      = document.getElementById('tagList');
    var ticketId  = container.dataset.ticketId;
    var tags      = JSON.parse(container.dataset.tags || '[]');

    function render() {
        var html = '';
        tags.forEach(function(tag) {
            html += '<span class="badge bg-light text-dark border d-flex align-items-center gap-1">'
                  + '<i class="bi bi-hash"></i>' + escHtml(tag)
                  + '<button type="button" class="btn-close" style="font-size:.5rem;" data-tag="' + escHtml(tag) + '" aria-label="Remove"></button>'
                  + '</span>';
        });
        html += '<span id="tagInputWrap" class="d-inline-flex align-items-center">'
              + '<input type="text" id="tagInputField" class="border-0" style="outline:none;width:120px;font-size:.875rem;background:transparent;" placeholder="#add tag">'
              + '</span>';
        list.innerHTML = html;

        list.querySelectorAll('.btn-close').forEach(function(btn) {
            btn.addEventListener('click', function() { removeTag(this.dataset.tag); });
        });

        var input = document.getElementById('tagInputField');
        input.addEventListener('keydown', function(ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                var val = this.value.trim();
                if (val) { addTag(val); this.value = ''; }
            }
        });
    }

    function addTag(name) {
        name = name.replace(/^#+/, '').replace(/[^a-zA-Z0-9_\-\s]/g, '').trim().toLowerCase();
        if (!name || tags.indexOf(name) !== -1) return;
        fetch('/api/tickets/' + ticketId + '/tags', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
            body: JSON.stringify({action: 'add', tag: name})
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.tags) { tags = data.tags; render(); }
        });
    }

    function removeTag(name) {
        fetch('/api/tickets/' + ticketId + '/tags', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
            body: JSON.stringify({action: 'remove', tag: name})
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.tags) { tags = data.tags; render(); }
        });
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    render();
})();
<?php endif; ?>

// CC management
(function() {
    var section   = document.getElementById('ccSection');
    var listEl    = document.getElementById('ccList');
    var input     = document.getElementById('ccSearchInput');
    var dropdown  = document.getElementById('ccDropdown');
    var ticketId  = section.dataset.ticketId;
    var ccUsers   = JSON.parse(section.dataset.cc || '[]');
    var debounce  = null;
    var activeIdx = -1;
    var results   = [];

    function renderList() {
        if (!ccUsers.length) {
            listEl.innerHTML = '<span class="text-muted small">No users CC\'d</span>';
            return;
        }
        var html = '';
        ccUsers.forEach(function(u) {
            html += '<div class="d-flex align-items-center justify-content-between mb-1">'
                  + '<div><span class="fw-semibold small">' + escHtml(u.first_name + ' ' + u.last_name) + '</span>'
                  + ' <span class="text-muted small">' + escHtml(u.email) + '</span></div>'
                  + '<button type="button" class="btn btn-sm btn-outline-danger border-0 py-0 px-1" data-uid="' + u.id + '" title="Remove"><i class="bi bi-x"></i></button>'
                  + '</div>';
        });
        listEl.innerHTML = html;
        listEl.querySelectorAll('[data-uid]').forEach(function(btn) {
            btn.addEventListener('click', function() { removeCc(parseInt(this.dataset.uid)); });
        });
    }

    function addCc(userId) {
        fetch('/api/tickets/' + ticketId + '/cc', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
            body: JSON.stringify({action: 'add', user_id: userId})
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.cc) { ccUsers = data.cc; renderList(); }
        });
        input.value = '';
        closeDropdown();
    }

    function removeCc(userId) {
        fetch('/api/tickets/' + ticketId + '/cc', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
            body: JSON.stringify({action: 'remove', user_id: userId})
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.cc) { ccUsers = data.cc; renderList(); }
        });
    }

    function renderDropdown(data) {
        results = data;
        activeIdx = -1;
        if (!data.length) { dropdown.style.display = 'none'; return; }
        var html = '';
        data.forEach(function(u, idx) {
            var roleBadge = u.role === 'admin' ? '<span class="badge bg-danger" style="font-size:.6rem;">Admin</span>'
                          : u.role === 'agent' ? '<span class="badge bg-primary" style="font-size:.6rem;">Agent</span>'
                          : '<span class="badge bg-secondary" style="font-size:.6rem;">User</span>';
            html += '<div class="mention-item" data-index="' + idx + '">'
                  + '<span class="mention-name">' + escHtml(u.first_name + ' ' + u.last_name) + '</span> '
                  + '<span class="text-muted" style="font-size:.75rem;">' + escHtml(u.email) + '</span> ' + roleBadge
                  + '</div>';
        });
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
        dropdown.querySelectorAll('.mention-item').forEach(function(el) {
            el.addEventListener('mousedown', function(ev) {
                ev.preventDefault();
                addCc(data[parseInt(this.dataset.index)].id);
            });
        });
    }

    function closeDropdown() {
        dropdown.style.display = 'none';
        results = [];
        activeIdx = -1;
    }

    function highlightItem(idx) {
        var els = dropdown.querySelectorAll('.mention-item');
        els.forEach(function(el) { el.classList.remove('active'); });
        if (idx >= 0 && idx < els.length) {
            els[idx].classList.add('active');
            els[idx].scrollIntoView({ block: 'nearest' });
        }
        activeIdx = idx;
    }

    input.addEventListener('input', function() {
        clearTimeout(debounce);
        var q = this.value.trim();
        if (q.length < 1) { closeDropdown(); return; }
        debounce = setTimeout(function() {
            fetch('/api/user-search?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var ids = ccUsers.map(function(u) { return u.id; });
                    data = data.filter(function(u) { return ids.indexOf(u.id) === -1; });
                    renderDropdown(data);
                });
        }, 250);
    });

    input.addEventListener('keydown', function(ev) {
        if (dropdown.style.display === 'none') return;
        if (ev.key === 'ArrowDown') {
            ev.preventDefault();
            highlightItem(Math.min(activeIdx + 1, results.length - 1));
        } else if (ev.key === 'ArrowUp') {
            ev.preventDefault();
            highlightItem(Math.max(activeIdx - 1, 0));
        } else if ((ev.key === 'Enter' || ev.key === 'Tab') && activeIdx >= 0) {
            ev.preventDefault();
            addCc(results[activeIdx].id);
        } else if (ev.key === 'Escape') {
            ev.preventDefault();
            closeDropdown();
        }
    });

    document.addEventListener('click', function(ev) {
        if (!dropdown.contains(ev.target) && ev.target !== input) closeDropdown();
    });

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    renderList();
})();

<?php if (!$ticket['merged_into_ticket_id']): ?>
// Merge modal typeahead
(function() {
    var searchInput      = document.getElementById('mergeSearchInput');
    var resultsEl        = document.getElementById('mergeResults');
    var selectedEl       = document.getElementById('mergeSelectedPreview');
    var primaryChoice    = document.getElementById('mergePrimaryChoice');
    var targetInput      = document.getElementById('mergeTargetId');
    var mergeForm        = document.getElementById('mergeForm');
    var confirmBtn       = document.getElementById('mergeConfirmBtn');
    var debounce         = null;
    var ticketId         = <?= (int) $ticket['id'] ?>;
    var selectedTicketId = null;
    var baseUrl          = '/agent/tickets/search';

    if (!searchInput) return;

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function updateFormAction() {
        var primaryThis = document.getElementById('mergePrimaryThis').checked;
        if (primaryThis) {
            mergeForm.action = '/agent/tickets/' + selectedTicketId + '/merge';
            targetInput.value = ticketId;
        } else {
            mergeForm.action = '/agent/tickets/' + ticketId + '/merge';
            targetInput.value = selectedTicketId || '';
        }
    }

    document.querySelectorAll('input[name="mergePrimary"]').forEach(function(r) {
        r.addEventListener('change', updateFormAction);
    });

    searchInput.addEventListener('input', function() {
        clearTimeout(debounce);
        var q = this.value.trim();
        resultsEl.innerHTML = '';
        resultsEl.style.display = 'none';
        selectedEl.style.display = 'none';
        primaryChoice.style.display = 'none';
        selectedTicketId = null;
        targetInput.value = '';
        confirmBtn.disabled = true;

        if (q.length < 1) return;
        debounce = setTimeout(function() {
            fetch(baseUrl + '?q=' + encodeURIComponent(q) + '&exclude=' + ticketId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.length) {
                        resultsEl.innerHTML = '<div class="px-3 py-2 text-muted small">No tickets found.</div>';
                        resultsEl.style.display = 'block';
                        return;
                    }
                    var html = '';
                    var statusLabels = {open:'Open',in_progress:'In Progress',pending:'Pending',resolved:'Resolved',closed:'Closed'};
                    data.forEach(function(t) {
                        html += '<button type="button" class="list-group-item list-group-item-action px-3 py-2" data-id="' + t.id + '" data-subject="' + escHtml(t.subject) + '">'
                              + '<div class="fw-semibold">#' + t.id + ' — ' + escHtml(t.subject) + '</div>'
                              + '<small class="text-muted">' + escHtml(t.creator_name) + ' &middot; ' + escHtml(statusLabels[t.status] || t.status) + '</small>'
                              + '</button>';
                    });
                    resultsEl.innerHTML = html;
                    resultsEl.style.display = 'block';
                    resultsEl.querySelectorAll('[data-id]').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            selectedTicketId = this.dataset.id;
                            selectedEl.textContent = 'Selected: #' + this.dataset.id + ' — ' + this.dataset.subject;
                            selectedEl.style.display = 'block';
                            document.getElementById('mergePrimarySelectedLabel').textContent =
                                'Selected ticket #' + this.dataset.id + ' — make it the primary';
                            document.getElementById('mergePrimarySelected').checked = true;
                            primaryChoice.style.display = 'block';
                            updateFormAction();
                            confirmBtn.disabled = false;
                            resultsEl.style.display = 'none';
                            searchInput.value = '#' + this.dataset.id + ' — ' + this.dataset.subject;
                        });
                    });
                });
        }, 300);
    });

    document.getElementById('mergeModal').addEventListener('hidden.bs.modal', function() {
        searchInput.value = '';
        resultsEl.innerHTML = '';
        resultsEl.style.display = 'none';
        selectedEl.style.display = 'none';
        primaryChoice.style.display = 'none';
        selectedTicketId = null;
        targetInput.value = '';
        confirmBtn.disabled = true;
        mergeForm.action = '/agent/tickets/' + ticketId + '/merge';
        document.getElementById('mergePrimarySelected').checked = true;
    });
})();
<?php endif; ?>

// Ticket presence — concurrent viewer warning
(function() {
    var ticketId = <?= (int) $ticket['id'] ?>;
    var pingUrl  = '/api/tickets/' + ticketId + '/presence';
    var leaveUrl = '/api/tickets/' + ticketId + '/presence/leave';
    var modal    = null;
    var knownViewerIds = new Set();
    var initialCheck   = true;

    // Stale-view detection: the ticket's last-modified timestamp at page render.
    // The presence poll returns the live value; if it advances past this, the
    // ticket changed under us (another agent, an automation, an inbound email),
    // so we surface a one-time, dismissible "refresh" banner. Agent edits here
    // (status/priority/reply) POST and reload, which re-seeds this baseline, so
    // this won't fire on the agent's own changes.
    var loadedUpdatedAt = <?= json_encode($ticket['updated_at'] ?? null) ?>;
    var staleShown = false;

    function showStaleBanner() {
        if (staleShown) return;
        staleShown = true;
        var bar = document.createElement('div');
        bar.className = 'alert alert-warning alert-dismissible fade show m-0 rounded-0 text-center';
        bar.setAttribute('role', 'alert');
        bar.style.position = 'sticky';
        bar.style.top = '0';
        bar.style.zIndex = '1050';
        bar.innerHTML =
            '<i class="bi bi-exclamation-triangle me-2"></i>' +
            'This ticket was updated since you opened it. ' +
            '<a href="" class="alert-link">Refresh</a> to see the latest.' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        document.body.prepend(bar);
    }

    function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function getModal() {
        if (!modal) modal = new bootstrap.Modal(document.getElementById('presenceModal'));
        return modal;
    }

    function ping() { fetch(pingUrl, {method: 'POST', headers: {'X-CSRF-Token': csrfToken}}).catch(function(){}); }

    function checkPresence() {
        fetch(pingUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // Stale-view check (timestamps are 'YYYY-MM-DD HH:MM:SS', so a
                // lexical compare is a valid chronological compare).
                if (data.updated_at && loadedUpdatedAt && data.updated_at > loadedUpdatedAt) {
                    showStaleBanner();
                }

                var viewers = data.viewers || [];
                var hasNew = initialCheck
                    ? viewers.length > 0
                    : viewers.some(function(v) { return !knownViewerIds.has(v.id); });

                if (hasNew && viewers.length > 0) {
                    var names = viewers.map(function(v) {
                        var role = v.role.charAt(0).toUpperCase() + v.role.slice(1);
                        return '<strong>' + escHtml(v.first_name + ' ' + v.last_name) + '</strong> (' + role + ')';
                    }).join(', ');
                    var intro = initialCheck
                        ? 'This ticket is currently also open by:'
                        : 'This ticket was just opened up by:';
                    document.getElementById('presenceModalBody').innerHTML =
                        '<p class="mb-1">' + intro + '</p>' +
                        '<p class="mb-0">' + names + '</p>' +
                        '<p class="mt-3 mb-0 text-muted small">Their changes will not appear on your screen until you refresh.</p>';
                    getModal().show();
                }

                knownViewerIds = new Set(viewers.map(function(v) { return v.id; }));
                initialCheck = false;
            })
            .catch(function(){});
    }

    ping();
    checkPresence();
    setInterval(function() { ping(); checkPresence(); }, 20000);

    window.addEventListener('beforeunload', function() {
        navigator.sendBeacon(leaveUrl);
    });
})();

// Reply / Forward / Note panel
(function() {
    var box          = document.getElementById('replyBox');
    var boxHeader    = document.getElementById('replyBoxHeader');
    var titleEl      = document.getElementById('replyBoxTitle');
    var isInternalEl = document.getElementById('replyIsInternal');
    var statusAfterEl = document.getElementById('replyStatusAfter');
    var submitLabel  = document.getElementById('replySubmitLabel');
    var submitIcon   = document.getElementById('replySubmitIcon');
    var closeBtn     = document.getElementById('replyBoxClose');
    var btnReply     = document.getElementById('btnReply');
    var btnForward   = document.getElementById('btnForward');
    var btnNote      = document.getElementById('btnNote');
    var currentMode  = null;

    var modes = {
        reply:   { title: '<i class="bi bi-reply me-2"></i>Reply',         internal: '0', label: 'Send Reply', icon: 'bi-send',    header: 'bg-white' },
        forward: { title: '<i class="bi bi-forward me-2"></i>Forward',     internal: '0', label: 'Forward',    icon: 'bi-forward', header: 'bg-white' },
        note:    { title: '<i class="bi bi-lock me-2"></i>Internal Note',  internal: '1', label: 'Add Note',   icon: 'bi-lock',    header: 'bg-warning-subtle' }
    };

    var form           = document.getElementById('replyForm');
    var recipientsRow  = document.getElementById('forwardRecipientsRow');
    var statusSplitBtn = form.querySelector('.dropdown-toggle-split');
    var commentAction  = '/agent/tickets/<?= (int) $ticket['id'] ?>/comment';
    var forwardAction  = '/agent/tickets/<?= (int) $ticket['id'] ?>/forward';

    function openMode(mode) {
        if (currentMode === mode && box.style.display !== 'none') { closePanel(); return; }
        currentMode = mode;
        var cfg = modes[mode];
        titleEl.innerHTML    = cfg.title;
        isInternalEl.value   = cfg.internal;
        submitLabel.textContent = cfg.label;
        submitIcon.className = 'bi ' + cfg.icon + ' me-1';
        boxHeader.className  = 'card-header border-bottom d-flex align-items-center justify-content-between py-2 ' + cfg.header;
        statusAfterEl.value  = '';
        // Forward mode posts to a different endpoint, shows the recipients field,
        // and hides the reply-only "send & change status" split button.
        var isForward = mode === 'forward';
        form.dataset.mode = mode;
        form.action = isForward ? forwardAction : commentAction;
        recipientsRow.style.display = isForward ? '' : 'none';
        if (statusSplitBtn) statusSplitBtn.style.display = isForward ? 'none' : '';
        box.style.display    = '';
        if (window._replyEditor) window._replyEditor.ui.update();
        // Focus the editor first (CKEditor scrolls the caret into view), THEN
        // scroll the whole panel's bottom — through the Send Reply button —
        // into view so this scroll wins over the focus scroll and the editor
        // has reflowed to its full height first.
        setTimeout(function() {
            if (window._replyEditor) window._replyEditor.editing.view.focus();
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    box.scrollIntoView({ behavior: 'smooth', block: 'end' });
                });
            });
        }, 80);
        [btnReply, btnForward, btnNote].forEach(function(b) { if (b) b.classList.remove('active'); });
        var activeBtn = ({ reply: btnReply, forward: btnForward, note: btnNote })[mode];
        if (activeBtn) activeBtn.classList.add('active');
    }

    function closePanel() {
        box.style.display = 'none';
        currentMode = null;
        statusAfterEl.value = '';
        [btnReply, btnForward, btnNote].forEach(function(b) { if (b) b.classList.remove('active'); });
    }

    btnReply.addEventListener('click',   function() { openMode('reply'); });
    if (btnForward) btnForward.addEventListener('click', function() { openMode('forward'); });
    btnNote.addEventListener('click',    function() { openMode('note'); });
    closeBtn.addEventListener('click',   closePanel);

    document.querySelectorAll('.reply-status-opt').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            statusAfterEl.value = this.dataset.status;
            document.getElementById('replyForm').requestSubmit();
        });
    });

    // Deep-link from the inbox-view hover card: /agent/tickets/123#reply|#forward|#note
    function openFromHash() {
        var h = (location.hash || '').replace('#', '');
        if (h === 'forward' && !btnForward) return; // no forward permission
        if (h === 'reply' || h === 'forward' || h === 'note') openMode(h);
    }
    if ((location.hash || '').replace('#', '').match(/^(reply|forward|note)$/)) {
        setTimeout(openFromHash, 150);
    }
    window.addEventListener('hashchange', openFromHash);
})();

/* ── Update Ticket Button – enable only when a field has changed ── */
(function () {
    var btn    = document.getElementById('updateTicketBtn');
    var fields = ['status', 'priority_id', 'assigned_to', 'group_id', 'type_id'];
    var initial = {};
    fields.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) initial[id] = el.value;
    });
    function check() {
        var changed = fields.some(function (id) {
            var el = document.getElementById(id);
            return el && el.value !== initial[id];
        });
        btn.disabled = !changed;
    }
    fields.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', check);
    });
})();

/* ── Re-filter Assigned To when the Group changes (no save needed) ── */
(function () {
    var byGroup  = <?= json_encode($assignableByGroup ?? []) ?>;
    var groupSel = document.getElementById('group_id');
    var assignSel = document.getElementById('assigned_to');
    if (!groupSel || !assignSel) return;
    groupSel.addEventListener('change', function () {
        var list = byGroup[this.value || '0'] || byGroup['0'] || [];
        var current = assignSel.value;
        var keep = false;
        assignSel.innerHTML = '';
        var unassigned = document.createElement('option');
        unassigned.value = '';
        unassigned.textContent = 'Unassigned';
        assignSel.appendChild(unassigned);
        list.forEach(function (a) {
            var o = document.createElement('option');
            o.value = a.id;
            o.textContent = a.name;
            if (String(a.id) === current) { o.selected = true; keep = true; }
            assignSel.appendChild(o);
        });
        if (!keep) assignSel.value = '';
        // Recompute the Update button's enabled state.
        assignSel.dispatchEvent(new Event('change'));
    });
})();
</script>

<script>
/* ── Canned Response Picker ─────────────────────────────────────── */
(function () {
    var ctx = <?= json_encode([
        'customer_first_name' => $ticket['creator_first_name'] ?? '',
        'customer_last_name'  => $ticket['creator_last_name']  ?? '',
        'customer_full_name'  => $ticket['creator_name']       ?? '',
        'customer_email'      => $ticket['creator_email']      ?? '',
        'ticket_id'           => '#' . $ticket['id'],
        'ticket_subject'      => $ticket['subject']            ?? '',
        'agent_first_name'    => Auth::user()['first_name']    ?? '',
        'agent_full_name'     => Auth::fullName(),
        'org_name'            => getSetting('app_name', 'OpenHelpDesk'),
    ]) ?>;

    function resolveTokens(body) {
        return body
            .replace(/\{\{customer_first_name\}\}/g, ctx.customer_first_name)
            .replace(/\{\{customer_last_name\}\}/g,  ctx.customer_last_name)
            .replace(/\{\{customer_full_name\}\}/g,  ctx.customer_full_name)
            .replace(/\{\{customer_email\}\}/g,      ctx.customer_email)
            .replace(/\{\{ticket_id\}\}/g,           ctx.ticket_id)
            .replace(/\{\{ticket_subject\}\}/g,      ctx.ticket_subject)
            .replace(/\{\{agent_first_name\}\}/g,    ctx.agent_first_name)
            .replace(/\{\{agent_full_name\}\}/g,     ctx.agent_full_name)
            .replace(/\{\{org_name\}\}/g,            ctx.org_name);
    }

    var responses  = null;
    var modal      = null;
    var listEl     = document.getElementById('cannedList');
    var searchEl   = document.getElementById('cannedSearch');
    var btnCanned  = document.getElementById('btnCanned');

    btnCanned.addEventListener('click', function () {
        if (!modal) {
            modal = new bootstrap.Modal(document.getElementById('cannedModal'));
        }
        searchEl.value = '';
        if (responses === null) {
            listEl.innerHTML = '<p class="text-muted text-center py-3 small">Loading…</p>';
            fetch('/agent/canned-responses/json')
                .then(function (r) { return r.json(); })
                .then(function (data) { responses = data; renderList(''); });
        } else {
            renderList('');
        }
        modal.show();
        setTimeout(function () { searchEl.focus(); }, 300);
    });

    searchEl.addEventListener('input', function () { renderList(this.value); });

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function renderList(query) {
        if (!responses) return;
        var q        = query.toLowerCase();
        var filtered = responses.filter(function (r) {
            return !q || r.title.toLowerCase().includes(q) || r.body.toLowerCase().includes(q);
        });
        if (!filtered.length) {
            listEl.innerHTML = '<p class="text-muted text-center py-3 small">No matching responses.</p>';
            return;
        }
        var personal = filtered.filter(function (r) { return !parseInt(r.is_global); });
        var global   = filtered.filter(function (r) { return  parseInt(r.is_global); });
        var html = '';
        if (personal.length) {
            html += '<p class="text-uppercase text-muted small fw-semibold mb-1 px-1">My Responses</p>';
            html += '<div class="list-group list-group-flush mb-3">';
            personal.forEach(function (r) { html += itemHtml(r); });
            html += '</div>';
        }
        if (global.length) {
            html += '<p class="text-uppercase text-muted small fw-semibold mb-1 px-1">Global</p>';
            html += '<div class="list-group list-group-flush">';
            global.forEach(function (r) { html += itemHtml(r); });
            html += '</div>';
        }
        listEl.innerHTML = html;
        listEl.querySelectorAll('.cr-item').forEach(function (el) {
            el.addEventListener('click', function () {
                var resolved = resolveTokens(this.dataset.body);
                if (window._replyEditor) {
                    window._replyEditor.model.change(function(writer) {
                        window._replyEditor.model.insertContent(writer.createText(resolved));
                    });
                    window._replyEditor.editing.view.focus();
                }
                modal.hide();
            });
        });
    }

    function itemHtml(r) {
        var preview = r.body.length > 120 ? r.body.substring(0, 120) + '…' : r.body;
        return '<button type="button" class="list-group-item list-group-item-action cr-item px-3 py-2" data-body="' + esc(r.body) + '">'
             + '<div class="fw-semibold small">' + esc(r.title) + '</div>'
             + '<div class="text-muted" style="font-size:.8rem;white-space:pre-wrap;">' + esc(preview) + '</div>'
             + '</button>';
    }
})();
</script>

<script type="module">
import {
    ClassicEditor,
    Essentials,
    Heading,
    Bold, Italic, Underline, Strikethrough,
    FontColor, FontBackgroundColor, FontSize,
    Alignment,
    List, ListProperties,
    Link, AutoLink,
    Image, ImageUpload, Base64UploadAdapter,
    ImageCaption, ImageStyle, ImageToolbar, ImageResize,
    Table, TableToolbar, TableProperties, TableCellProperties,
    BlockQuote,
    Code, CodeBlock,
    HorizontalLine,
    Indent, IndentBlock,
    FindAndReplace,
    RemoveFormat
} from 'ckeditor5';

ClassicEditor.create(document.querySelector('#replyEditor'), {
    plugins: [
        Essentials,
        Heading,
        Bold, Italic, Underline, Strikethrough,
        FontColor, FontBackgroundColor, FontSize,
        Alignment,
        List, ListProperties,
        Link, AutoLink,
        Image, ImageUpload, Base64UploadAdapter,
        ImageCaption, ImageStyle, ImageToolbar, ImageResize,
        Table, TableToolbar, TableProperties, TableCellProperties,
        BlockQuote,
        Code, CodeBlock,
        HorizontalLine,
        Indent, IndentBlock,
        FindAndReplace,
        RemoveFormat
    ],
    toolbar: {
        items: [
            'heading', '|',
            'fontSize', 'fontColor', 'fontBackgroundColor', '|',
            'bold', 'italic', 'underline', 'strikethrough', 'removeFormat', '|',
            'alignment', '|',
            'bulletedList', 'numberedList', 'outdent', 'indent', '|',
            'link', 'insertImage', 'insertTable', 'blockQuote', 'codeBlock', 'horizontalLine', '|',
            'findAndReplace', 'undo', 'redo'
        ],
        shouldNotGroupWhenFull: true
    },
    heading: {
        options: [
            { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
            { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
            { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
            { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
        ]
    },
    image: {
        toolbar: [
            'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|',
            'toggleImageCaption', 'imageTextAlternative', '|',
            'resizeImage'
        ]
    },
    table: {
        contentToolbar: [
            'tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties'
        ]
    },
    initialData: ''
}).then(function(editor) {
    window._replyEditor = editor;
    editor.ui.view.editable.element.style.minHeight = '150px';

    // Form submit: validate and populate hidden field
    document.getElementById('replyForm').addEventListener('submit', function(e) {
        var data = editor.getData();
        var text = data.replace(/<[^>]*>/g, '').trim();
        if (this.dataset.mode === 'forward') {
            // The conversation thread is the payload; the note is optional. Just
            // require at least one recipient.
            var to = (document.getElementById('forwardTo').value || '').trim();
            if (!to) {
                e.preventDefault();
                document.getElementById('forwardTo').focus();
                return;
            }
        } else if (!text) {
            e.preventDefault();
            editor.editing.view.focus();
            return;
        }
        document.getElementById('replyMessageHidden').value = data;
    });

    // @mention autocomplete
    var dropdown = document.getElementById('mentionDropdown');
    var debounceTimer = null;
    var activeIndex = -1;
    var items = [];

    function getMentionInfo() {
        var sel = editor.model.document.selection;
        var pos = sel.getFirstPosition();
        if (!pos) return null;
        // pos.textNode is null when the cursor sits at the end of a text node,
        // which is exactly where it lands after typing a character. Fall back
        // to nodeBefore so we can still read what the user just typed.
        var textNode = pos.textNode || pos.nodeBefore;
        if (!textNode || !textNode.is || !textNode.is('$text')) return null;
        var textNodeStart = textNode.startOffset;
        var endInText = pos.offset - textNodeStart;
        if (endInText < 0 || endInText > textNode.data.length) return null;
        var text = textNode.data.substring(0, endInText);
        for (var i = text.length - 1; i >= 0; i--) {
            if (text[i] === '@') {
                if (i === 0 || /\s/.test(text[i - 1])) {
                    return { start: textNodeStart + i, query: text.substring(i + 1), parent: textNode.parent, endOffset: pos.offset };
                }
                return null;
            }
            if (/\s/.test(text[i])) return null;
        }
        return null;
    }

    // Anchor the dropdown to the caret inside CKEditor instead of letting it
    // fall to its static position below the whole editor.
    function positionDrop() {
        try {
            var range = editor.editing.view.document.selection.getFirstRange();
            if (!range) return;
            var domRange = editor.editing.view.domConverter.viewRangeToDom(range);
            if (!domRange) return;
            var rect = domRange.getBoundingClientRect();
            var parent = dropdown.offsetParent || document.body;
            var pRect = parent.getBoundingClientRect();
            dropdown.style.left = (rect.left - pRect.left) + 'px';
            // Flip above the caret when there isn't room below it in the viewport.
            var dropH = dropdown.offsetHeight;
            var roomBelow = window.innerHeight - rect.bottom;
            if (roomBelow < dropH + 8 && rect.top > roomBelow) {
                dropdown.style.top = (rect.top - pRect.top - dropH - 4) + 'px';
            } else {
                dropdown.style.top = (rect.bottom - pRect.top + 4) + 'px';
            }
        } catch (e) { /* leave default position on any conversion failure */ }
    }

    function renderDrop(results) {
        items = results;
        activeIndex = -1;
        if (!results.length) { closeDrop(); return; }
        var html = '';
        results.forEach(function(u, idx) {
            var badge = u.role === 'admin'
                ? '<span class="badge bg-danger" style="font-size:.65rem;">Admin</span>'
                : '<span class="badge bg-primary" style="font-size:.65rem;">Agent</span>';
            html += '<div class="mention-item" data-index="' + idx + '">'
                  + '<span class="mention-name">' + u.first_name + ' ' + u.last_name + '</span> ' + badge
                  + '</div>';
        });
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
        positionDrop();
        dropdown.querySelectorAll('.mention-item').forEach(function(el) {
            el.addEventListener('mousedown', function(ev) {
                ev.preventDefault();
                selectMention(parseInt(this.dataset.index));
            });
        });
    }

    function selectMention(idx) {
        if (idx < 0 || idx >= items.length) return;
        var u = items[idx];
        var text = '@' + u.first_name + ' ' + u.last_name + ' ';
        var info = getMentionInfo();
        editor.model.change(function(writer) {
            if (info && info.parent) {
                // Remove the typed "@query" then insert the full mention at the same spot.
                // We can't pass a Selection to writer.insertText — only Position or attrs.
                var startPos = writer.createPositionAt(info.parent, info.start);
                var endPos = writer.createPositionAt(info.parent, info.endOffset);
                writer.remove(writer.createRange(startPos, endPos));
                writer.insertText(text, writer.createPositionAt(info.parent, info.start));
                writer.setSelection(writer.createPositionAt(info.parent, info.start + text.length));
            } else {
                var pos = editor.model.document.selection.getFirstPosition();
                if (pos) writer.insertText(text, pos);
            }
        });
        closeDrop();
        editor.editing.view.focus();
    }

    function closeDrop() {
        dropdown.style.display = 'none';
        items = [];
        activeIndex = -1;
    }

    function highlightDrop(idx) {
        var els = dropdown.querySelectorAll('.mention-item');
        els.forEach(function(el) { el.classList.remove('active'); });
        if (idx >= 0 && idx < els.length) {
            els[idx].classList.add('active');
            els[idx].scrollIntoView({ block: 'nearest' });
        }
        activeIndex = idx;
    }

    editor.model.document.on('change:data', function() {
        clearTimeout(debounceTimer);
        var info = getMentionInfo();
        if (!info || info.query.length < 1) { closeDrop(); return; }
        debounceTimer = setTimeout(function() {
            fetch('/api/mention-search?q=' + encodeURIComponent(info.query))
                .then(function(r) { return r.json(); })
                .then(function(data) { renderDrop(data); });
        }, 250);
    });

    editor.editing.view.document.on('keydown', function(evt, data) {
        if (dropdown.style.display === 'none') return;
        if (data.keyCode === 40) {
            data.preventDefault(); evt.stop();
            highlightDrop(Math.min(activeIndex + 1, items.length - 1));
        } else if (data.keyCode === 38) {
            data.preventDefault(); evt.stop();
            highlightDrop(Math.max(activeIndex - 1, 0));
        } else if ((data.keyCode === 13 || data.keyCode === 9) && activeIndex >= 0) {
            data.preventDefault(); evt.stop();
            selectMention(activeIndex);
        } else if (data.keyCode === 27) {
            data.preventDefault(); evt.stop();
            closeDrop();
        }
    }, { priority: 'high' });

    document.addEventListener('click', function(ev) {
        if (!dropdown.contains(ev.target)) closeDrop();
    });

}).catch(console.error);
</script>

<script>
// Quick-assign dropdown for the Assigned To detail row
(function () {
    var activeMenu = null;
    var activeBtn  = null;
    var esc = function (s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

    function closeMenu() {
        if (activeMenu) { activeMenu.remove(); activeMenu = null; activeBtn = null; }
    }

    function openMenu(btn) {
        closeMenu();
        var wrap = btn.closest('.quick-assign-wrap');
        var ticketId = wrap ? wrap.dataset.ticketId : '';
        var agents = [];
        try { agents = JSON.parse(btn.dataset.agents || '[]'); } catch (_) {}
        var html = '<ul class="dropdown-menu show shadow-sm" style="position:fixed;z-index:1060;min-width:180px;max-height:260px;overflow-y:auto;font-size:0.85rem;">';
        html += '<li><a class="dropdown-item quick-assign-floating-item" href="#" data-agent-id="" data-ticket-id="' + esc(ticketId) + '">Unassigned</a></li>';
        if (agents.length) {
            html += '<li><hr class="dropdown-divider"></li>';
            agents.forEach(function (a) {
                html += '<li><a class="dropdown-item quick-assign-floating-item" href="#" data-agent-id="' + esc(a.id) + '" data-ticket-id="' + esc(ticketId) + '">' + esc(a.name) + '</a></li>';
            });
        }
        html += '</ul>';
        var div = document.createElement('div');
        div.innerHTML = html;
        var menu = div.firstChild;
        document.body.appendChild(menu);
        var rect = btn.getBoundingClientRect();
        menu.style.top = (rect.bottom + 2) + 'px';
        menu.style.left = rect.left + 'px';
        activeMenu = menu;
        activeBtn  = btn;
    }

    document.querySelectorAll('.quick-assign-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (activeBtn === btn) { closeMenu(); return; }
            openMenu(btn);
        });
    });

    document.addEventListener('click', function (e) {
        var item = e.target.closest('.quick-assign-floating-item');
        if (item) {
            e.preventDefault();
            var agentId  = item.dataset.agentId;
            var ticketId = item.dataset.ticketId;
            var wrap = document.querySelector('.quick-assign-wrap[data-ticket-id="' + ticketId + '"]');
            closeMenu();
            if (!wrap) return;
            var nameSpan = wrap.querySelector('.quick-assign-name');
            // Also update the sidebar select
            var sidebarSelect = document.getElementById('assigned_to');
            fetch('/api/tickets/' + ticketId + '/assign', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body: JSON.stringify({assigned_to: agentId === '' ? null : parseInt(agentId, 10)})
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.success) {
                    nameSpan.textContent = data.agent_name || 'Unassigned';
                    if (sidebarSelect) { sidebarSelect.value = agentId; }
                }
            }).catch(function () {});
            return;
        }
        if (!e.target.closest('.quick-assign-btn')) { closeMenu(); }
    });

    window.addEventListener('scroll', function (e) {
        if (activeMenu && (activeMenu === e.target || activeMenu.contains(e.target))) return;
        closeMenu();
    }, true);
    window.addEventListener('resize', closeMenu);
})();
</script>
