<?php
/**
 * Kanban board ticket view — shared by /agent/tickets/board and
 * /admin/tickets/board (one template, no agent/admin duplication).
 *
 * Built-in boards (status / priority / assignee) group tickets by a ticket
 * field; dragging a card calls the existing /api/tickets/{id}/<endpoint>.
 * Custom boards are a personal organizer: drag updates a stored placement via
 * /api/kanban/place and never touches the ticket. See src/routes/kanban.php.
 *
 * Expects: $boardBase, $isAdminBoard, $boardParam, $dimension, $dropEndpoint,
 * $dropPayloadKey, $columns, $cardColumn, $tickets, $board, $customBoards,
 * $builtIns, $search, $fGroup, $teammatesOnly, $groupOptions, $capped,
 * $kanbanCap, $ticketPresence, $confidentialTypeIds, $adminGroupIds.
 */
$csrf       = csrfToken();
$isCustom   = ($dimension === 'custom');
$ownsBoard  = $isCustom && $board && (int) $board['user_id'] === (int) Auth::id();
$boardLabel = $isCustom
    ? ($board['name'] ?? 'Board')
    : ($builtIns[$boardParam]['label'] ?? 'Status');

$fGroup        = $fGroup ?? [];
$teammatesOnly = $teammatesOnly ?? false;
$groupOptions  = $groupOptions ?? [];
$activeFilters = count($fGroup) + ($teammatesOnly ? 1 : 0);

// Build a board URL that preserves the active search + filters, swapping only
// the board key — so switching boards keeps the user's filters.
$boardUrl = function (string $boardKey) use ($boardBase, $search, $fGroup, $teammatesOnly): string {
    $qs = ['board' => $boardKey];
    if ($search !== '')   { $qs['q'] = $search; }
    if (!empty($fGroup))  { $qs['group'] = $fGroup; }
    if ($teammatesOnly)   { $qs['teammates'] = 1; }
    return $boardBase . '/board?' . http_build_query($qs);
};

// Pre-bucket the tickets into their columns so each column renders its own list.
$byColumn = [];
foreach ($columns as $col) {
    $byColumn[$col['key']] = [];
}
foreach ($tickets as $t) {
    $key = $cardColumn[(int) $t['id']] ?? null;
    if ($key !== null && array_key_exists($key, $byColumn)) {
        $byColumn[$key][] = $t;
    } elseif (!empty($columns)) {
        // A value with no matching column (e.g. an inactive status) — drop it in
        // the first column rather than vanishing it.
        $byColumn[$columns[0]['key']][] = $t;
    }
}
?>
<style>
    /* The board wants the full width the table view doesn't. */
    main#main-content { max-width: none; }
    #kanbanBoard { --ld-col-w: 300px; }
    #kanbanColumns { display: flex; gap: .75rem; overflow-x: auto; padding-bottom: .5rem; align-items: flex-start; }
    .ld-kcol {
        flex: 0 0 var(--ld-col-w); width: var(--ld-col-w);
        background: var(--bs-tertiary-bg, #f1f5f9); border-radius: .6rem;
        display: flex; flex-direction: column; max-height: calc(100vh - 210px);
    }
    .ld-kcol-head {
        display: flex; align-items: center; gap: .5rem; padding: .6rem .75rem;
        font-weight: 600; font-size: .85rem; position: sticky; top: 0;
        background: inherit; border-radius: .6rem .6rem 0 0; z-index: 2;
    }
    .ld-kcol-dot { width: 10px; height: 10px; border-radius: 3px; flex: 0 0 10px; }
    .ld-kcol-count { margin-left: auto; font-weight: 600; color: var(--bs-secondary-color); font-size: .8rem; }
    .ld-kcol-body { padding: 0 .55rem .55rem; overflow-y: auto; flex: 1 1 auto; min-height: 60px; }
    .ld-kcard {
        background: var(--bs-body-bg, #fff); border: 1px solid var(--bs-border-color, #e2e8f0);
        border-radius: .5rem; padding: .55rem .6rem; margin-top: .5rem; cursor: grab;
        box-shadow: 0 1px 2px rgba(0,0,0,.05);
    }
    .ld-kcard:active { cursor: grabbing; }
    .ld-kcard.dragging { opacity: .45; }
    .ld-kcard-subj { font-weight: 600; font-size: .85rem; line-height: 1.25; }
    .ld-kcard-subj a { color: var(--bs-body-color); text-decoration: none; }
    .ld-kcard-subj a:hover { text-decoration: underline; }
    .ld-kcard-meta { font-size: .73rem; color: var(--bs-secondary-color); }
    .ld-kprio-dot { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }
    .ld-kcol-body.drop-target { outline: 2px dashed var(--ld-primary); outline-offset: -4px; border-radius: .4rem; }
    .ld-kcol-empty { color: var(--bs-secondary-color); font-size: .8rem; text-align: center; padding: 1rem .5rem; }
    .ld-addbucket { flex: 0 0 240px; width: 240px; }
</style>

<div id="kanbanBoard"
     data-board-base="<?= e($boardBase) ?>"
     data-board-param="<?= e($boardParam) ?>"
     data-dimension="<?= e($dimension) ?>"
     data-drop-endpoint="<?= e($dropEndpoint) ?>"
     data-drop-payload="<?= e($dropPayloadKey) ?>"
     data-board-id="<?= $isCustom && $board ? (int) $board['id'] : '' ?>"
     data-owns="<?= $ownsBoard ? '1' : '0' ?>"
     data-csrf="<?= e($csrf) ?>">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 me-2"><i class="bi bi-kanban me-1"></i>Ticket Board</h1>

        <!-- Board picker -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?= e($boardLabel) ?>
                <?php if ($isCustom && $board && (int) $board['is_shared'] === 1): ?>
                    <i class="bi bi-people-fill ms-1" title="Shared board"></i>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu shadow-sm" style="min-width:240px;">
                <li><h6 class="dropdown-header">Group by</h6></li>
                <?php foreach ($builtIns as $key => $bi): ?>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2 <?= (!$isCustom && $boardParam === $key) ? 'active' : '' ?>"
                       href="<?= e($boardUrl($key)) ?>">
                        <i class="bi <?= e($bi['icon']) ?>"></i><?= e($bi['label']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">My &amp; shared boards</h6></li>
                <?php if (empty($customBoards)): ?>
                <li><span class="dropdown-item-text text-muted small">No custom boards yet</span></li>
                <?php else: foreach ($customBoards as $cb): ?>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2 <?= ($isCustom && $board && (int) $board['id'] === (int) $cb['id']) ? 'active' : '' ?>"
                       href="<?= e($boardUrl('c' . (int) $cb['id'])) ?>">
                        <i class="bi bi-columns-gap"></i>
                        <span class="text-truncate"><?= e($cb['name']) ?></span>
                        <?php if ((int) $cb['is_shared'] === 1): ?><i class="bi bi-people-fill text-muted ms-auto" title="Shared"></i><?php endif; ?>
                        <?php if ((int) $cb['is_owner'] !== 1): ?><span class="badge bg-light text-muted ms-auto">by <?= e($cb['owner_name']) ?></span><?php endif; ?>
                    </a>
                </li>
                <?php endforeach; endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item" type="button" id="kanbanNewBoard"><i class="bi bi-plus-lg me-1"></i>New board…</button></li>
            </ul>
        </div>

        <!-- Custom board actions (owner only) -->
        <?php if ($ownsBoard): ?>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" title="Board settings"><i class="bi bi-gear"></i></button>
            <ul class="dropdown-menu shadow-sm">
                <li><button class="dropdown-item" type="button" id="kanbanRenameBoard"><i class="bi bi-pencil me-2"></i>Rename board</button></li>
                <li>
                    <label class="dropdown-item d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" class="form-check-input mt-0" id="kanbanShareBoard" <?= (int) $board['is_shared'] === 1 ? 'checked' : '' ?>>
                        Share with team
                    </label>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item text-danger" type="button" id="kanbanDeleteBoard"><i class="bi bi-trash me-2"></i>Delete board</button></li>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Search + filters -->
        <form class="d-flex align-items-center gap-2" method="get" action="<?= e($boardBase) ?>/board" role="search">
            <input type="hidden" name="board" value="<?= e($boardParam) ?>">
            <div class="input-group input-group-sm" style="width:210px;">
                <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search subject…" aria-label="Search tickets">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </div>

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-funnel me-1"></i>Filters<?php if ($activeFilters > 0): ?> <span class="badge bg-primary"><?= (int) $activeFilters ?></span><?php endif; ?>
                </button>
                <div class="dropdown-menu shadow-sm p-3" style="min-width:260px;">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="teammates" value="1" id="filterTeammates" <?= $teammatesOnly ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="filterTeammates">My team&rsquo;s tickets only</label>
                        <div class="form-text mt-0">Only tickets assigned to a teammate (someone in your groups) or left unassigned.</div>
                    </div>
                    <?php if (!empty($groupOptions)): ?>
                    <hr class="my-2">
                    <div class="fw-semibold small mb-1"><?= Auth::isAdmin() ? 'Groups' : 'My groups' ?></div>
                    <div style="max-height:220px;overflow-y:auto;">
                        <?php $fGroupInts = array_map('intval', $fGroup); foreach ($groupOptions as $g): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="group[]" value="<?= (int) $g['id'] ?>" id="filterGroup<?= (int) $g['id'] ?>" <?= in_array((int) $g['id'], $fGroupInts, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="filterGroup<?= (int) $g['id'] ?>"><?= e($g['name']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-sm btn-primary" type="submit">Apply</button>
                        <a class="btn btn-sm btn-link" href="<?= e($boardBase) ?>/board?board=<?= e($boardParam) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">Clear</a>
                    </div>
                </div>
            </div>
        </form>

        <!-- Same Table / Compact / Card / Kanban control as the list pages. The
             board is not a list layout, so 'board' highlights the Kanban button
             and the three layout buttons save the layout then navigate to the
             list (preserving the current search). -->
        <div class="ms-auto">
            <?php
            $tvActiveKey = 'board';
            $tvNavBase   = $boardBase . ($search !== '' ? '?q=' . urlencode($search) : '');
            $tvBoardUrl  = $boardUrl($boardParam);
            require ROOT_DIR . '/templates/partials/ticket-view-switcher.php';
            ?>
        </div>
    </div>

    <?php if ($capped): ?>
    <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>Showing the most recent <?= (int) $kanbanCap ?> tickets. Use search or the list view's filters to narrow down.</div>
    <?php endif; ?>

    <?php if ($isCustom && !$board): ?>
    <div class="alert alert-danger">That board doesn't exist or isn't shared with you. <a href="<?= e($boardBase) ?>/board?board=status">Back to the Status board</a>.</div>
    <?php else: ?>

    <div id="kanbanColumns">
        <?php foreach ($columns as $col):
            $cards = $byColumn[$col['key']] ?? [];
            $isBucket = $isCustom && $col['key'] !== 'unsorted';
        ?>
        <div class="ld-kcol" data-col-key="<?= e($col['key']) ?>"<?= $isBucket ? ' data-bucket-id="' . e($col['key']) . '"' : '' ?>>
            <div class="ld-kcol-head">
                <span class="ld-kcol-dot" style="background:<?= e($col['color'] ?: '#6c757d') ?>;"></span>
                <span class="text-truncate" title="<?= e($col['label']) ?>"><?= e($col['label']) ?></span>
                <span class="ld-kcol-count"><?= count($cards) ?></span>
                <?php if ($ownsBoard && $isBucket): ?>
                <div class="dropdown">
                    <button class="btn btn-link btn-sm p-0 text-muted border-0" type="button" data-bs-toggle="dropdown" title="Bucket settings" style="line-height:1;"><i class="bi bi-three-dots-vertical"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><button class="dropdown-item kanban-rename-bucket" type="button" data-bucket-id="<?= e($col['key']) ?>" data-name="<?= e($col['label']) ?>"><i class="bi bi-pencil me-2"></i>Rename</button></li>
                        <li>
                            <label class="dropdown-item d-flex align-items-center gap-2" style="cursor:pointer;">
                                <i class="bi bi-palette"></i>Color
                                <input type="color" class="form-control form-control-color form-control-sm ms-auto kanban-color-bucket" value="<?= e($col['color'] ?: '#6c757d') ?>" data-bucket-id="<?= e($col['key']) ?>" title="Bucket color">
                            </label>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-danger kanban-delete-bucket" type="button" data-bucket-id="<?= e($col['key']) ?>" data-name="<?= e($col['label']) ?>"><i class="bi bi-trash me-2"></i>Delete</button></li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <div class="ld-kcol-body" data-col-key="<?= e($col['key']) ?>"<?= $isBucket ? ' data-bucket-id="' . e($col['key']) . '"' : '' ?>>
                <?php if (empty($cards)): ?>
                <div class="ld-kcol-empty">Drop tickets here</div>
                <?php endif; ?>
                <?php foreach ($cards as $t):
                    $isRedacted = isTicketRedactedForUser($t, $confidentialTypeIds ?? [], $adminGroupIds ?? []);
                ?>
                <?php if ($isRedacted): ?>
                <div class="ld-kcard" style="cursor:default;opacity:.75;">
                    <div class="ld-kcard-subj text-muted fst-italic"><i class="bi bi-shield-lock me-1"></i>[Confidential] #<?= (int) $t['id'] ?></div>
                </div>
                <?php else:
                    $presence = $ticketPresence[$t['id']] ?? null;
                ?>
                <div class="ld-kcard" draggable="true" data-ticket-id="<?= (int) $t['id'] ?>">
                    <div class="ld-kcard-subj mb-1">
                        <a href="<?= e($boardBase) ?>/<?= (int) $t['id'] ?>"><?= e($t['subject']) ?></a>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="text-muted small">#<?= (int) $t['id'] ?></span>
                        <?php if ($t['type_name']): ?>
                        <span class="badge" style="background:<?= e($t['type_color'] ?: '#6c757d') ?>;font-size:.65rem;"><?= e($t['type_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($dimension !== 'status'): ?>
                        <span class="ms-auto"><?= ticketStatusBadgeHtml($t['status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ld-kcard-meta d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($dimension !== 'priority'): ?>
                            <?php if ($t['priority_name']): ?>
                            <span class="d-inline-flex align-items-center gap-1"><span class="ld-kprio-dot" style="background:<?= e($t['priority_color'] ?: '#6c757d') ?>;"></span><?= e($t['priority_name']) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($dimension !== 'assignee'): ?>
                        <span class="d-inline-flex align-items-center gap-1 text-truncate"><i class="bi bi-person"></i><?= e($t['agent_name'] ?: 'Unassigned') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($presence): ?>
                    <div class="ld-kcard-meta mt-1" style="color:#b45309;" title="Another staff member has this open"><i class="bi <?= $presence['replying'] ? 'bi-pencil-fill' : 'bi-eye-fill' ?> me-1"></i><?= $presence['replying'] ? 'Replying' : 'Viewing' ?>: <?= e($presence['name']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($ownsBoard): ?>
        <div class="ld-addbucket">
            <button class="btn btn-sm btn-outline-secondary w-100" type="button" id="kanbanAddBucketBtn"><i class="bi bi-plus-lg me-1"></i>Add bucket</button>
            <div id="kanbanAddBucketForm" class="card card-body p-2 mt-2 d-none">
                <input type="text" class="form-control form-control-sm mb-2" id="kanbanBucketName" placeholder="Bucket name" maxlength="100">
                <div class="d-flex align-items-center gap-2">
                    <input type="color" class="form-control form-control-color form-control-sm" id="kanbanBucketColor" value="#6c757d" title="Color">
                    <button class="btn btn-sm btn-primary ms-auto" type="button" id="kanbanBucketSave">Add</button>
                    <button class="btn btn-sm btn-link" type="button" id="kanbanBucketCancel">Cancel</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var root = document.getElementById('kanbanBoard');
    if (!root) return;

    var csrf          = root.dataset.csrf;
    var boardBase     = root.dataset.boardBase;
    var dimension     = root.dataset.dimension;
    var dropEndpoint  = root.dataset.dropEndpoint;
    var dropPayload   = root.dataset.dropPayload;
    var boardId       = root.dataset.boardId;
    var owns          = root.dataset.owns === '1';

    function jsonPost(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body || {})
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }

    /* ── Drag & drop ─────────────────────────────────────────────── */
    var dragCard = null;
    var dragFrom = null;

    function updateCounts() {
        root.querySelectorAll('.ld-kcol').forEach(function (col) {
            var n = col.querySelectorAll('.ld-kcard[data-ticket-id]').length;
            var c = col.querySelector('.ld-kcol-count');
            if (c) c.textContent = n;
            var body = col.querySelector('.ld-kcol-body');
            var empty = body.querySelector('.ld-kcol-empty');
            if (n === 0 && !empty) {
                var e = document.createElement('div');
                e.className = 'ld-kcol-empty';
                e.textContent = 'Drop tickets here';
                body.appendChild(e);
            } else if (n > 0 && empty) {
                empty.remove();
            }
        });
    }

    root.addEventListener('dragstart', function (e) {
        var card = e.target.closest('.ld-kcard[draggable="true"]');
        if (!card) return;
        dragCard = card;
        dragFrom = card.closest('.ld-kcol-body');
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', card.dataset.ticketId); } catch (err) {}
    });
    root.addEventListener('dragend', function () {
        if (dragCard) dragCard.classList.remove('dragging');
        root.querySelectorAll('.drop-target').forEach(function (el) { el.classList.remove('drop-target'); });
        dragCard = null; dragFrom = null;
    });
    root.addEventListener('dragover', function (e) {
        var body = e.target.closest('.ld-kcol-body');
        if (!body || !dragCard) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (!body.classList.contains('drop-target')) {
            root.querySelectorAll('.drop-target').forEach(function (el) { el.classList.remove('drop-target'); });
            body.classList.add('drop-target');
        }
    });
    root.addEventListener('drop', function (e) {
        var body = e.target.closest('.ld-kcol-body');
        if (!body || !dragCard) return;
        e.preventDefault();
        body.classList.remove('drop-target');

        var targetKey = body.dataset.colKey;
        var fromBody  = dragFrom;
        if (body === fromBody) return; // no-op

        var ticketId = dragCard.dataset.ticketId;
        var card     = dragCard;

        // Optimistic move, then persist; revert on failure.
        var empty = body.querySelector('.ld-kcol-empty');
        if (empty) empty.remove();
        body.appendChild(card);
        updateCounts();

        var revert = function (msg) {
            fromBody.appendChild(card);
            updateCounts();
            if (msg) alert(msg);
        };

        var req;
        if (dimension === 'custom') {
            req = jsonPost('/api/kanban/place', { board_id: parseInt(boardId, 10), ticket_id: parseInt(ticketId, 10), bucket_id: targetKey });
        } else {
            var payload = {};
            payload[dropPayload] = targetKey; // '' means unassign / no-priority
            req = jsonPost('/api/tickets/' + ticketId + '/' + dropEndpoint, payload);
        }
        req.then(function (res) {
            if (!res.ok || (res.data && res.data.error)) {
                revert((res.data && (res.data.error || res.data.message)) || 'Could not move the ticket.');
            }
        }).catch(function () { revert('Network error — the ticket was not moved.'); });
    });

    /* ── Board create / rename / share / delete ──────────────────── */
    function nav(param) { window.location = boardBase + '/board?board=' + encodeURIComponent(param); }

    var newBtn = document.getElementById('kanbanNewBoard');
    if (newBtn) newBtn.addEventListener('click', function () {
        var name = prompt('Name for the new board:');
        if (!name) return;
        jsonPost('/api/kanban/boards', { name: name.trim() }).then(function (res) {
            if (res.ok && res.data.success) nav(res.data.param);
            else alert((res.data && res.data.error) || 'Could not create the board.');
        });
    });

    var renameBtn = document.getElementById('kanbanRenameBoard');
    if (renameBtn) renameBtn.addEventListener('click', function () {
        var name = prompt('Rename board:', '');
        if (!name) return;
        jsonPost('/api/kanban/boards/' + boardId, { name: name.trim() }).then(function (res) {
            if (res.ok && res.data.success) window.location.reload();
            else alert((res.data && res.data.error) || 'Could not rename.');
        });
    });

    var shareBox = document.getElementById('kanbanShareBoard');
    if (shareBox) shareBox.addEventListener('change', function () {
        jsonPost('/api/kanban/boards/' + boardId, { is_shared: shareBox.checked ? 1 : 0 }).then(function (res) {
            if (!res.ok || !res.data.success) { shareBox.checked = !shareBox.checked; alert((res.data && res.data.error) || 'Could not update sharing.'); }
        });
    });

    var deleteBtn = document.getElementById('kanbanDeleteBoard');
    if (deleteBtn) deleteBtn.addEventListener('click', function () {
        if (!confirm('Delete this board and its buckets? Tickets are not affected.')) return;
        jsonPost('/api/kanban/boards/' + boardId + '/delete', {}).then(function (res) {
            if (res.ok && res.data.success) nav('status');
            else alert((res.data && res.data.error) || 'Could not delete.');
        });
    });

    /* ── Bucket add / rename / recolor / delete ──────────────────── */
    var addBtn = document.getElementById('kanbanAddBucketBtn');
    var addForm = document.getElementById('kanbanAddBucketForm');
    if (addBtn) addBtn.addEventListener('click', function () {
        addForm.classList.toggle('d-none');
        if (!addForm.classList.contains('d-none')) document.getElementById('kanbanBucketName').focus();
    });
    var cancelBtn = document.getElementById('kanbanBucketCancel');
    if (cancelBtn) cancelBtn.addEventListener('click', function () { addForm.classList.add('d-none'); });
    var saveBtn = document.getElementById('kanbanBucketSave');
    if (saveBtn) saveBtn.addEventListener('click', function () {
        var name = document.getElementById('kanbanBucketName').value.trim();
        var color = document.getElementById('kanbanBucketColor').value;
        if (!name) { alert('Enter a bucket name.'); return; }
        jsonPost('/api/kanban/boards/' + boardId + '/buckets', { name: name, color: color }).then(function (res) {
            if (res.ok && res.data.success) window.location.reload();
            else alert((res.data && res.data.error) || 'Could not add the bucket.');
        });
    });

    root.querySelectorAll('.kanban-rename-bucket').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var name = prompt('Rename bucket:', btn.dataset.name);
            if (!name) return;
            jsonPost('/api/kanban/buckets/' + btn.dataset.bucketId, { name: name.trim() }).then(function (res) {
                if (res.ok && res.data.success) window.location.reload();
                else alert((res.data && res.data.error) || 'Could not rename.');
            });
        });
    });
    root.querySelectorAll('.kanban-color-bucket').forEach(function (inp) {
        inp.addEventListener('change', function () {
            jsonPost('/api/kanban/buckets/' + inp.dataset.bucketId, { color: inp.value }).then(function (res) {
                if (res.ok && res.data.success) window.location.reload();
                else alert((res.data && res.data.error) || 'Could not update color.');
            });
        });
    });
    root.querySelectorAll('.kanban-delete-bucket').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete bucket "' + btn.dataset.name + '"? Its cards return to Unsorted.')) return;
            jsonPost('/api/kanban/buckets/' + btn.dataset.bucketId + '/delete', {}).then(function (res) {
                if (res.ok && res.data.success) window.location.reload();
                else alert((res.data && res.data.error) || 'Could not delete.');
            });
        });
    });
})();
</script>
