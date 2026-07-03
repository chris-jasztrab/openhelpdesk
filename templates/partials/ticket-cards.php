<?php
/**
 * Card-style ("card") ticket list.
 *
 * A third layout (alongside table and inbox), shared by the agent and admin
 * ticket lists. Each ticket is a roomy horizontal card that stacks the data
 * normally spread across the table columns: a requester avatar + the subject
 * up top, the group/location, age and SLA underneath, and priority, assignee
 * and status down the right edge. Reuses the same bulk-select checkbox as the
 * table so the existing bulk-action bar keeps working.
 *
 * Expects, in the including template's scope:
 *   $tickets             array  the ticket rows (same shape as the table view)
 *   $cardBase            string base path for links, e.g. '/agent/tickets'
 *   $confidentialTypeIds array  (optional) for redaction
 *   $adminGroupIds       array  (optional) for redaction
 */
$cardBase    = $cardBase ?? '/agent/tickets';
$closedSlugs = ticketClosedBucketSlugs();
$slaOn       = slaEnabled();
?>
<style>
    #ticketCards .ld-card { transition: background-color .12s ease; }
    #ticketCards .ld-card:hover { background: var(--bs-tertiary-bg, #f8f9fa); }
    #ticketCards .ld-card + .ld-card { border-top: 1px solid var(--bs-border-color, #e9ecef); }
    #ticketCards .ld-avatar {
        width: 40px; height: 40px; flex: 0 0 40px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .9rem; color: #fff; user-select: none;
    }
    #ticketCards .ld-card-subject { color: var(--bs-body-color); }
    #ticketCards .ld-card:hover .ld-card-subject { text-decoration: underline; }
    #ticketCards .ld-card-meta { font-size: .8rem; }
    #ticketCards .ld-card-right { min-width: 160px; font-size: .85rem; }
    #ticketCards .ld-prio-dot { width: 9px; height: 9px; border-radius: 2px; display: inline-block; }
</style>

<div id="ticketCards" style="overflow-y:auto;max-height:calc(100vh - 260px);">
    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-light" style="position:sticky;top:0;z-index:5;">
        <input type="checkbox" id="selectAll" class="form-check-input" title="Select all">
        <span class="text-muted small">Select all</span>
    </div>

    <?php if (empty($tickets)): ?>
    <div class="text-center py-5 text-muted">No tickets found.</div>
    <?php else: ?>
        <?php foreach ($tickets as $t): ?>
        <?php
            $isAssignedToMe = ($t['assigned_to'] == Auth::id());
            $isRedacted = isTicketRedactedForUser($t, $confidentialTypeIds ?? [], $adminGroupIds ?? []);
            $presence   = $ticketPresence[$t['id']] ?? null; // live "Opened by X" hint
            $isOpen     = !in_array($t['status'], $closedSlugs, true);
            $awaitingFirstResponse = $isOpen && empty($t['first_responded_at']);

            // Deterministic avatar tint from the requester name.
            $name    = $isRedacted ? '' : (string) ($t['creator_name'] ?? '');
            $palette = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];
            $avatarColor = $palette[crc32($name !== '' ? $name : (string) $t['id']) % count($palette)];

            // SLA snippet: first-response if still unanswered, else resolution.
            $slaText = ''; $slaOverdue = false;
            if ($slaOn && $isOpen) {
                if ($awaitingFirstResponse && !empty($t['first_response_due_at'])) {
                    $due = strtotime($t['first_response_due_at']);
                    $slaOverdue = $due < time();
                    $slaText = $slaOverdue ? 'First response overdue' : 'First response due ' . humanRelativeTime($due);
                } elseif (!empty($t['resolution_due_at'])) {
                    $due = strtotime($t['resolution_due_at']);
                    $slaOverdue = $due < time();
                    $slaText = $slaOverdue ? 'Resolution overdue' : 'Resolution due ' . humanRelativeTime($due);
                }
            }
        ?>
        <div class="ld-card d-flex align-items-center gap-3 px-3 py-3<?= $isAssignedToMe && !$isRedacted ? ' bg-primary bg-opacity-10' : '' ?>"
             style="cursor:pointer;<?= $isRedacted ? 'opacity:0.75;' : '' ?>"
             onclick="window.location='<?= $cardBase ?>/<?= (int) $t['id'] ?>'">
            <input type="checkbox" class="ticket-cb form-check-input flex-shrink-0" value="<?= (int) $t['id'] ?>"
                   data-subject="<?= $isRedacted ? 'Confidential' : e($t['subject']) ?>"
                   <?= $isRedacted ? 'data-confidential="1"' : '' ?>
                   onclick="event.stopPropagation()">

            <div class="ld-avatar" style="background:<?= $isRedacted ? '#adb5bd' : $avatarColor ?>;">
                <?php if ($isRedacted): ?><i class="bi bi-shield-lock"></i><?php else: ?><?= e(nameInitials($name ?: 'Unknown')) ?><?php endif; ?>
            </div>

            <div class="flex-grow-1<?= $isRedacted ? '' : ' ld-subject-cell' ?>"<?= $isRedacted ? '' : ' data-ticket-id="' . (int) $t['id'] . '"' ?> style="min-width:0;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?php if ($awaitingFirstResponse && !$isRedacted): ?>
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle" title="No agent reply yet">New</span>
                    <?php endif; ?>
                    <?php if (!$isRedacted && $t['type_name']): ?>
                    <span class="badge" style="background:<?= e($t['type_color'] ?: '#6c757d') ?>;"><?= e($t['type_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($isRedacted): ?>
                        <span class="fw-semibold text-muted fst-italic"><i class="bi bi-shield-lock me-1"></i>[Confidential]</span>
                        <span class="text-muted small">#<?= (int) $t['id'] ?></span>
                    <?php else: ?>
                        <span class="ld-card-subject ld-subject-link fw-semibold text-truncate" style="max-width:100%;"><?= e($t['subject']) ?></span>
                        <span class="text-muted small">#<?= (int) $t['id'] ?></span>
                        <?php if ($isAssignedToMe): ?><span class="badge bg-primary bg-opacity-10 text-primary">Mine</span><?php endif; ?>
                        <?php if (!empty($draftTicketIds[$t['id']])): ?><span class="badge bg-warning bg-opacity-25 text-dark" title="You have an unsent reply draft on this ticket"><i class="bi bi-pencil-square me-1"></i>Draft</span><?php endif; ?>
                        <?php if ($t['merged_into_ticket_id']): ?>
                        <span class="badge bg-secondary" title="Merged into #<?= (int) $t['merged_into_ticket_id'] ?>"><i class="bi bi-arrow-right-circle"></i> Merged</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$isRedacted): ?>
                <span class="ld-presence-hint d-block small fst-italic mt-1" style="color:#b45309;<?= $presence ? '' : 'display:none;' ?>" title="Another staff member currently has this ticket open">
                    <?php if ($presence): ?>
                    <i class="bi <?= $presence['replying'] ? 'bi-pencil-fill' : 'bi-eye-fill' ?> me-1"></i><?= $presence['replying'] ? 'Being replied to by ' : 'Opened by ' ?><?= e($presence['name']) ?>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
                <div class="ld-card-meta text-muted mt-1 d-flex align-items-center gap-2 flex-wrap">
                    <?php $place = $t['location_name'] ?: $t['group_name']; ?>
                    <?php if ($place): ?>
                    <span><i class="bi bi-geo-alt me-1"></i><?= e($place) ?></span>
                    <span class="text-secondary">&middot;</span>
                    <?php endif; ?>
                    <span>Created <?= humanRelativeTime($t['created_at']) ?></span>
                    <?php if ($slaText !== ''): ?>
                    <span class="text-secondary">&middot;</span>
                    <span class="<?= $slaOverdue ? 'text-danger fw-semibold' : '' ?>"><i class="bi bi-arrow-return-left me-1"></i><?= e($slaText) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ld-card-right flex-shrink-0 d-flex flex-column align-items-end gap-1">
                <span class="d-inline-flex align-items-center gap-1">
                    <?php if ($t['priority_name']): ?>
                    <span class="ld-prio-dot" style="background:<?= e($t['priority_color'] ?: '#6c757d') ?>;"></span>
                    <span><?= e($t['priority_name']) ?></span>
                    <?php else: ?><span class="text-muted">No priority</span><?php endif; ?>
                </span>
                <span class="text-muted d-inline-flex align-items-center gap-1" style="max-width:200px;">
                    <i class="bi bi-person"></i>
                    <span class="text-truncate"><?php
                        $who = trim(($t['group_name'] ? $t['group_name'] . ' / ' : '') . ($t['agent_name'] ?: 'Unassigned'));
                        echo e($who);
                    ?></span>
                </span>
                <span><?= ticketStatusBadgeHtml($t['status']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
