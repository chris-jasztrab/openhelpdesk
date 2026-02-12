<?php
$layout       = 'app';
$pageTitle    = 'Ticket #' . $ticket['id'];
$sidebarItems = agentSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Tickets', 'url' => '/agent/tickets'],
    ['label' => '#' . $ticket['id']],
];
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$actionIcons  = ['created' => 'bi-plus-circle text-success', 'assigned' => 'bi-person-check text-primary', 'status_changed' => 'bi-arrow-repeat text-warning', 'priority_changed' => 'bi-flag text-danger', 'comment' => 'bi-chat-dots text-info', 'internal_note' => 'bi-lock text-secondary'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><?= e($ticket['subject']) ?></h2>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-<?= $statusColors[$ticket['status']] ?? 'secondary' ?> fs-6">
                <?= e($statusLabels[$ticket['status']] ?? $ticket['status']) ?>
            </span>
            <?php if ($ticket['priority_name']): ?>
            <span class="badge fs-6" style="background:<?= e($ticket['priority_color']) ?>;">
                <?= e($ticket['priority_name']) ?>
            </span>
            <?php endif; ?>
            <span class="text-muted">Ticket #<?= $ticket['id'] ?></span>
        </div>
    </div>
    <a href="/agent/tickets" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
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

        <?php if (!empty($ticket['tags'])): ?>
        <div class="mb-4">
            <?php foreach ($ticket['tags'] as $tag): ?>
            <span class="badge bg-light text-dark border me-1 mb-1">
                <i class="bi bi-hash"></i><?= e($tag) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

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
                <form method="POST" action="/agent/tickets/<?= $ticket['id'] ?>/comment">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <textarea class="form-control" name="message" id="commentMessage" rows="3" required
                                  placeholder="Write a comment... Use @Name to mention an agent"></textarea>
                        <div class="form-text">
                            <i class="bi bi-at"></i> Mention:
                            <?php foreach ($agents as $a): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 me-1 mt-1 mention-btn"
                                    data-name="<?= e($a['first_name'] . ' ' . $a['last_name']) ?>">
                                @<?= e($a['first_name'] . ' ' . $a['last_name']) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="isInternal">
                            <label class="form-check-label" for="isInternal">
                                <i class="bi bi-lock me-1"></i>Internal note <span class="text-muted">(not visible to the user)</span>
                            </label>
                        </div>
                        <button type="submit" class="btn text-white" style="background:#4f46e5;">
                            <i class="bi bi-send me-1"></i>Post
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right column: Metadata -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
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
    </div>
</div>

<script>
document.querySelectorAll('.mention-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var textarea = document.getElementById('commentMessage');
        var mention = '@' + this.dataset.name + ' ';
        var pos = textarea.selectionStart;
        textarea.value = textarea.value.substring(0, pos) + mention + textarea.value.substring(pos);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = pos + mention.length;
    });
});
</script>
