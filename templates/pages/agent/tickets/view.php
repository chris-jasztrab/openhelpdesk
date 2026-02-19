<?php
$layout       = 'app';
$pageTitle    = 'Ticket #' . $ticket['id'];
$sidebarItems = agentSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Tickets', 'url' => '/agent/tickets'],
    ['label' => '#' . $ticket['id']],
];
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$actionIcons  = ['created' => 'bi-plus-circle text-success', 'assigned' => 'bi-person-check text-primary', 'status_changed' => 'bi-arrow-repeat text-warning', 'priority_changed' => 'bi-flag text-danger', 'comment' => 'bi-chat-dots text-info', 'internal_note' => 'bi-lock text-secondary', 'sla_set' => 'bi-stopwatch text-primary', 'sla_paused' => 'bi-pause-circle text-warning', 'sla_resumed' => 'bi-play-circle text-success', 'merged' => 'bi-arrow-right-circle text-secondary'];
$slaStateColors = ['on_track' => 'success', 'warning' => 'warning', 'breached' => 'danger'];
$slaStateLabels = ['on_track' => 'On Track', 'warning' => 'Warning', 'breached' => 'Breached'];
?>
<?php if ($ticket['merged_into_ticket_id']): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-arrow-right-circle-fill fs-5"></i>
    <div>
        This ticket was <strong>merged into <a href="/agent/tickets/<?= (int) $ticket['merged_into_ticket_id'] ?>">Ticket #<?= (int) $ticket['merged_into_ticket_id'] ?></a></strong> and is now closed. All further updates should be made on the master ticket.
    </div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><?= e($ticket['subject']) ?></h2>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-<?= $statusColors[$ticket['status']] ?? 'secondary' ?> fs-6">
                <?= e($statusLabels[$ticket['status']] ?? $ticket['status']) ?>
            </span>
            <?php if ($ticket['merged_into_ticket_id']): ?>
            <span class="badge bg-secondary fs-6"><i class="bi bi-arrow-right-circle me-1"></i>Merged</span>
            <?php endif; ?>
            <?php if ($ticket['priority_name']): ?>
            <span class="badge fs-6" style="background:<?= e($ticket['priority_color']) ?>;">
                <?= e($ticket['priority_name']) ?>
            </span>
            <?php endif; ?>
            <?php if ($ticket['sla_state']): ?>
            <span class="badge bg-<?= $slaStateColors[$ticket['sla_state']] ?? 'secondary' ?> fs-6">
                <i class="bi bi-stopwatch me-1"></i><?= e($slaStateLabels[$ticket['sla_state']] ?? $ticket['sla_state']) ?>
            </span>
            <?php endif; ?>
            <span class="text-muted">Ticket #<?= $ticket['id'] ?></span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$ticket['merged_into_ticket_id']): ?>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mergeModal">
            <i class="bi bi-arrow-right-circle me-1"></i>Merge
        </button>
        <?php endif; ?>
        <a href="/agent/tickets" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left column: Description + Timeline -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-text-paragraph me-2"></i>Description</h5>
            </div>
            <div class="card-body">
                <div style="white-space:pre-wrap;"><?= e($ticket['description']) ?></div>
            </div>
        </div>

        <?php if (!empty($attachments)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-paperclip me-2"></i>Attachments</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($attachments as $att): ?>
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

        <!-- Tags -->
        <div class="mb-4" id="ticketTags"
             data-ticket-id="<?= $ticket['id'] ?>"
             data-tags="<?= e(json_encode($ticket['tags'] ?? [])) ?>">
            <div class="d-flex flex-wrap gap-1 align-items-center" id="tagList"></div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Timeline</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($timeline)): ?>
                <div class="text-center py-4 text-muted">No timeline entries.</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($timeline as $entry): ?>
                    <div class="list-group-item px-4 py-3 <?= $entry['is_internal'] ? 'bg-warning bg-opacity-10 border-start border-warning border-3' : '' ?>">
                        <div class="d-flex gap-3">
                            <div class="pt-1">
                                <?php if ($entry['is_internal']): ?>
                                <i class="bi bi-lock-fill text-warning fs-5"></i>
                                <?php else: ?>
                                <i class="bi <?= $actionIcons[$entry['action']] ?? 'bi-circle text-muted' ?> fs-5"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="fw-semibold"><?= e($entry['user_name'] ?: 'System') ?></span>
                                        <span class="text-muted ms-1"><?= e(str_replace('_', ' ', ucfirst($entry['action']))) ?></span>
                                        <?php if ($entry['is_internal']): ?>
                                        <span class="badge bg-warning text-dark ms-1">Internal</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= date('M j, Y g:i A', strtotime($entry['created_at'])) ?></small>
                                </div>
                                <?php if ($entry['details']): ?>
                                <div class="mt-1 text-muted" style="white-space:pre-wrap;"><?= e($entry['details']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Comment / Internal Note -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-chat-dots me-2"></i>Add Comment</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/agent/tickets/<?= $ticket['id'] ?>/comment" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="mb-3" style="position:relative;">
                        <textarea class="form-control" name="message" id="commentMessage" rows="3" required
                                  placeholder="Write a comment... Type @ to mention someone"></textarea>
                        <div id="mentionDropdown" class="mention-dropdown" style="display:none;"></div>
                    </div>
                    <div class="mb-3">
                        <input type="file" class="form-control" name="attachments[]" multiple>
                        <div class="form-text">Max <?= UPLOAD_MAX_SIZE / 1024 / 1024 ?>MB per file</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="isInternal">
                            <label class="form-check-label" for="isInternal">
                                <i class="bi bi-lock me-1"></i>Internal note <span class="text-muted">(not visible to the user)</span>
                            </label>
                        </div>
                        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                            <i class="bi bi-send me-1"></i>Post
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right column: Metadata + Update -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Details</h5>
            </div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt class="text-muted small">Status</dt>
                    <dd>
                        <span class="badge bg-<?= $statusColors[$ticket['status']] ?? 'secondary' ?>">
                            <?= e($statusLabels[$ticket['status']] ?? $ticket['status']) ?>
                        </span>
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
                    <dd><?= e($ticket['type_name'] ?? 'Not set') ?></dd>

                    <dt class="text-muted small">Assigned To</dt>
                    <dd><?= e($ticket['agent_name'] ?: 'Unassigned') ?></dd>

                    <dt class="text-muted small">Group</dt>
                    <dd><?= e($ticket['group_name'] ?? 'None') ?></dd>

                    <dt class="text-muted small">Created By</dt>
                    <dd>
                        <?= e($ticket['creator_name'] ?? '—') ?>
                        <?php if (!empty($ticket['creator_email'])): ?>
                        <br><small class="text-muted"><?= e($ticket['creator_email']) ?></small>
                        <?php endif; ?>
                    </dd>

                    <dt class="text-muted small">Location</dt>
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

                    <hr>
                    <dt class="text-muted small">Browser</dt>
                    <dd class="small"><?= e($ticket['browser_info'] ?? 'Unknown') ?></dd>

                    <dt class="text-muted small">Operating System</dt>
                    <dd class="small"><?= e($ticket['os_info'] ?? 'Unknown') ?></dd>
                </dl>
            </div>
        </div>

        <!-- SLA Info -->
        <?php if ($ticket['sla_state']): ?>
        <div class="card border-0 shadow-sm mb-4">
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
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-pencil-square me-2"></i>Update Ticket</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/agent/tickets/<?= $ticket['id'] ?>/update">
                    <?= csrfField() ?>

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

                    <button type="submit" class="btn btn-sm text-white w-100" style="background:var(--ld-primary);">
                        <i class="bi bi-check-lg me-1"></i>Update
                    </button>
                </form>
            </div>
        </div>
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
                    This ticket will be <strong>closed</strong> and linked to the master ticket. The original submitter and any CC'd users will be notified and will be able to follow the master ticket.
                </p>
                <div class="mb-3" style="position:relative;">
                    <label class="form-label fw-semibold small">Search for master ticket</label>
                    <input type="text" id="mergeSearchInput" class="form-control" placeholder="Type ticket # or subject..." autocomplete="off">
                    <div id="mergeResults" class="list-group shadow-sm" style="display:none;position:absolute;z-index:1050;width:100%;max-height:240px;overflow-y:auto;"></div>
                </div>
                <div id="mergeSelectedPreview" class="alert alert-success py-2 small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/agent/tickets/<?= $ticket['id'] ?>/merge" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="merge_into_id" id="mergeTargetId">
                    <button type="submit" id="mergeConfirmBtn" class="btn btn-danger" disabled>
                        <i class="bi bi-arrow-right-circle me-1"></i>Merge &amp; Close This Ticket
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    var textarea = document.getElementById('commentMessage');
    var dropdown = document.getElementById('mentionDropdown');
    var debounceTimer = null;
    var activeIndex = -1;
    var items = [];
    var mentionStart = -1;

    function getQuery() {
        var val = textarea.value, pos = textarea.selectionStart;
        for (var i = pos - 1; i >= 0; i--) {
            if (val[i] === '@') {
                if (i === 0 || /\s/.test(val[i - 1])) {
                    mentionStart = i;
                    return val.substring(i + 1, pos);
                }
                break;
            }
            if (/\s/.test(val[i]) && i < pos - 1) break;
        }
        mentionStart = -1;
        return null;
    }

    function render(results) {
        items = results;
        activeIndex = -1;
        if (!results.length) { dropdown.style.display = 'none'; return; }
        var html = '';
        results.forEach(function(u, idx) {
            var badge = u.role === 'admin' ? '<span class="badge bg-danger" style="font-size:.65rem;">Admin</span>'
                                           : '<span class="badge bg-primary" style="font-size:.65rem;">Agent</span>';
            html += '<div class="mention-item" data-index="' + idx + '">'
                  + '<span class="mention-name">' + u.first_name + ' ' + u.last_name + '</span> ' + badge
                  + '</div>';
        });
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.mention-item').forEach(function(el) {
            el.addEventListener('mousedown', function(ev) {
                ev.preventDefault();
                select(parseInt(this.dataset.index));
            });
        });
    }

    function select(idx) {
        if (idx < 0 || idx >= items.length) return;
        var u = items[idx];
        var name = '@' + u.first_name + ' ' + u.last_name + ' ';
        var val = textarea.value;
        var pos = textarea.selectionStart;
        textarea.value = val.substring(0, mentionStart) + name + val.substring(pos);
        var newPos = mentionStart + name.length;
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = newPos;
        close();
    }

    function close() {
        dropdown.style.display = 'none';
        items = [];
        activeIndex = -1;
        mentionStart = -1;
    }

    function highlight(idx) {
        var els = dropdown.querySelectorAll('.mention-item');
        els.forEach(function(el) { el.classList.remove('active'); });
        if (idx >= 0 && idx < els.length) {
            els[idx].classList.add('active');
            els[idx].scrollIntoView({ block: 'nearest' });
        }
        activeIndex = idx;
    }

    textarea.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        var q = getQuery();
        if (q === null || q.length < 1) { close(); return; }
        debounceTimer = setTimeout(function() {
            fetch('/api/mention-search?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) { render(data); });
        }, 250);
    });

    textarea.addEventListener('keydown', function(ev) {
        if (dropdown.style.display === 'none') return;
        if (ev.key === 'ArrowDown') {
            ev.preventDefault();
            highlight(Math.min(activeIndex + 1, items.length - 1));
        } else if (ev.key === 'ArrowUp') {
            ev.preventDefault();
            highlight(Math.max(activeIndex - 1, 0));
        } else if ((ev.key === 'Enter' || ev.key === 'Tab') && activeIndex >= 0) {
            ev.preventDefault();
            select(activeIndex);
        } else if (ev.key === 'Escape') {
            ev.preventDefault();
            close();
        }
    });

    document.addEventListener('click', function(ev) {
        if (!dropdown.contains(ev.target) && ev.target !== textarea) close();
    });
})();

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
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'add', tag: name})
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.tags) { tags = data.tags; render(); }
        });
    }

    function removeTag(name) {
        fetch('/api/tickets/' + ticketId + '/tags', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
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
            headers: {'Content-Type': 'application/json'},
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
            headers: {'Content-Type': 'application/json'},
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
    var searchInput  = document.getElementById('mergeSearchInput');
    var resultsEl    = document.getElementById('mergeResults');
    var selectedEl   = document.getElementById('mergeSelectedPreview');
    var targetInput  = document.getElementById('mergeTargetId');
    var confirmBtn   = document.getElementById('mergeConfirmBtn');
    var debounce     = null;
    var ticketId     = <?= (int) $ticket['id'] ?>;
    var baseUrl      = '/agent/tickets/search';

    if (!searchInput) return;

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    searchInput.addEventListener('input', function() {
        clearTimeout(debounce);
        var q = this.value.trim();
        resultsEl.innerHTML = '';
        resultsEl.style.display = 'none';
        selectedEl.style.display = 'none';
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
                            targetInput.value = this.dataset.id;
                            selectedEl.textContent = 'Merging into: #' + this.dataset.id + ' — ' + this.dataset.subject;
                            selectedEl.style.display = 'block';
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
        targetInput.value = '';
        confirmBtn.disabled = true;
    });
})();
<?php endif; ?>
</script>
