<?php
$layout       = 'app';
$pageTitle    = 'Ticket #' . $ticket['id'];
$sidebarItems = portalSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Portal', 'url' => '/portal'],
    ['label' => 'My Tickets', 'url' => '/portal/tickets'],
    ['label' => '#' . $ticket['id']],
];
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'waiting_on_customer' => 'warning', 'waiting_on_third_party' => 'dark', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'waiting_on_customer' => 'Waiting on Customer', 'waiting_on_third_party' => 'Waiting on Third Party', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$actionIcons  = ['created' => 'bi-plus-circle text-success', 'assigned' => 'bi-person-check text-primary', 'status_changed' => 'bi-arrow-repeat text-warning', 'priority_changed' => 'bi-flag text-danger', 'comment' => 'bi-chat-dots text-info'];
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
    <a href="/portal/tickets" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row g-4">
    <!-- Left column: Description + Tags + Timeline -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-text-paragraph me-2"></i>Description</h5>
            </div>
            <div class="card-body">
                <div style="white-space:pre-wrap;"><?= e($ticket['description']) ?></div>
            </div>
        </div>

        <?php if (!empty($customFields)): ?>
        <?php
            // Helper: resolve option ID to label
            function resolveOptLabel(int $optId, array $opts): string {
                foreach ($opts as $o) {
                    if ((int) $o['id'] === $optId) return $o['label'];
                }
                return '';
            }
            $hasAnyValue = false;
            foreach ($customFields as $cf) {
                if (isset($fieldValues[$cf['id']]) && $fieldValues[$cf['id']] !== '') { $hasAnyValue = true; break; }
            }
        ?>
        <?php if ($hasAnyValue): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-list-check me-2"></i>Additional Details</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                <?php foreach ($customFields as $cf):
                    if (!isset($fieldValues[$cf['id']]) || $fieldValues[$cf['id']] === '') continue;
                    $raw = $fieldValues[$cf['id']];
                    $cfOpts = $fieldOptions[$cf['id']] ?? [];
                    if ($cf['field_type'] === 'checkbox') {
                        $display = $raw === '1' ? 'Yes' : 'No';
                    } elseif ($cf['field_type'] === 'dropdown') {
                        $display = resolveOptLabel((int) $raw, $cfOpts);
                        if (!$display) $display = $raw;
                    } elseif ($cf['field_type'] === 'dependent') {
                        $dep = json_decode($raw, true) ?? [];
                        $parts = [];
                        foreach (['l1','l2','l3'] as $lk) {
                            if (!empty($dep[$lk])) {
                                $lbl = resolveOptLabel((int) $dep[$lk], $cfOpts);
                                if ($lbl) $parts[] = $lbl;
                            }
                        }
                        $display = implode(' › ', $parts);
                    } else {
                        $display = $raw;
                    }
                ?>
                    <dt class="col-sm-4 text-muted fw-normal"><?= e($cf['label']) ?></dt>
                    <dd class="col-sm-8"><?= e($display) ?></dd>
                <?php endforeach; ?>
                </dl>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($attachments)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-paperclip me-2"></i>Attachments</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($attachments as $att): ?>
                <a href="/portal/attachments/<?= $att['id'] ?>/download"
                   class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                    <i class="bi <?= getFileIcon($att['mime_type']) ?> fs-4"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= e($att['original_name']) ?></div>
                        <small class="text-muted"><?= formatFileSize($att['file_size']) ?></small>
                    </div>
                    <i class="bi bi-download text-muted"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (getSetting('tags_enabled', '1') === '1' && !empty($ticket['tags'])): ?>
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
                <?php else:
                $tlHidden = max(0, count($timeline) - 10);
                ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($timeline as $tlIdx => $entry):
                        $isOlder = $tlIdx >= 10;
                    ?>
                    <div class="list-group-item px-4 py-3<?= $isOlder ? ' timeline-older-item' : '' ?>"
                         <?= $isOlder ? 'style="display:none;"' : '' ?>>
                        <div class="d-flex gap-3">
                            <div class="pt-1">
                                <i class="bi <?= $actionIcons[$entry['action']] ?? 'bi-circle text-muted' ?> fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="fw-semibold"><?= e($entry['user_name'] ?: 'System') ?></span>
                                        <span class="text-muted ms-1"><?= e(str_replace('_', ' ', ucfirst($entry['action']))) ?></span>
                                    </div>
                                    <small class="text-muted"><?= date('M j, Y g:i A', strtotime($entry['created_at'])) ?></small>
                                </div>
                                <?php if ($entry['details']): ?>
                                <div class="mt-1 text-muted"><?= e($entry['details']) ?></div>
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

        <!-- Add Comment -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-chat-dots me-2"></i>Add Comment</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/portal/tickets/<?= $ticket['id'] ?>/comment" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <textarea class="form-control" name="message" rows="3" required
                                  placeholder="Add an update or ask a question..."></textarea>
                    </div>
                    <div class="mb-3">
                        <input type="file" class="form-control" name="attachments[]" multiple>
                        <div class="form-text">Max <?= UPLOAD_MAX_SIZE / 1024 / 1024 ?>MB per file</div>
                    </div>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-send me-1"></i>Post Comment
                    </button>
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
                    <dd><?= e($ticket['type_name'] ?: 'Not Set') ?></dd>

                    <dt class="text-muted small"><?= label('location.singular') ?></dt>
                    <dd><?= e($ticket['location_name'] ?? 'Not set') ?></dd>

                    <dt class="text-muted small">Assigned To</dt>
                    <dd><?= e($ticket['agent_name'] ?: 'Unassigned') ?></dd>

                    <dt class="text-muted small">Created</dt>
                    <dd><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></dd>
                </dl>
            </div>
        </div>

        <?php if (!empty($ccUsers)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>CC</h5>
            </div>
            <div class="card-body">
                <?php foreach ($ccUsers as $cc): ?>
                <div class="mb-1">
                    <span class="fw-semibold small"><?= e($cc['first_name'] . ' ' . $cc['last_name']) ?></span>
                    <span class="text-muted small"><?= e($cc['email']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
