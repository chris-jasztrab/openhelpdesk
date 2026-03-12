<?php
$layout      = 'app';
$pageTitle   = 'Agent Dashboard';
$breadcrumbs = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Dashboard'],
];
$sidebarItems   = agentSidebar('dashboard');
$statusColors   = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'waiting_on_customer' => 'warning', 'waiting_on_third_party' => 'dark', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels   = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'waiting_on_customer' => 'Waiting on Customer', 'waiting_on_third_party' => 'Waiting on Third Party', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$slaStateColors = ['on_track' => 'success', 'warning' => 'warning', 'breached' => 'danger'];
$allColumns     = ticketColumnDefinitions();
?>
<div class=" mb-4">
    <div>
        <h2 class="fw-bold mb-1">Agent Dashboard</h2>
        <p class="text-muted mb-0">Welcome back, <?= e($user['first_name'] ?? 'Agent') ?></p>
    </div>
</div>

<div class="row g-4 mb-4" id="tour-stat-cards">
    <div class="col-md-3">
        <a href="/agent/tickets?agent%5B%5D=unassigned&status%5B%5D=open&status%5B%5D=in_progress&status%5B%5D=pending" class="text-decoration-none">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Unassigned</div>
                    <div class="fs-4 fw-bold"><?= $unassigned ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/agent/tickets?agent%5B%5D=mine&status%5B%5D=open&status%5B%5D=in_progress&status%5B%5D=pending" class="text-decoration-none">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-person-check"></i>
                </div>
                <div>
                    <div class="text-muted small">My Tickets</div>
                    <div class="fs-4 fw-bold"><?= $myTickets ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/agent/tickets?status%5B%5D=pending" class="text-decoration-none">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="text-muted small">Pending</div>
                    <div class="fs-4 fw-bold"><?= $pending ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="/agent/tickets?resolved_today=1" class="text-decoration-none">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Resolved Today</div>
                    <div class="fs-4 fw-bold"><?= $resolvedToday ?></div>
                </div>
            </div>
        </div>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm" id="tour-recent-tickets">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-ticket-detailed me-2"></i>Recent Tickets</h5>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-layout-three-columns me-1"></i>Columns
            </button>
            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:200px;">
                <form method="POST" action="/agent/tickets/columns">
                    <?= csrfField() ?>
                    <input type="hidden" name="_redirect" value="/agent">
                    <h6 class="dropdown-header px-0 pt-0">Visible Columns</h6>
                    <?php foreach ($allColumns as $colKey => $colLabel): ?>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="columns[]"
                               value="<?= $colKey ?>" id="dash_col_<?= $colKey ?>"
                               <?= in_array($colKey, $visibleColumns) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="dash_col_<?= $colKey ?>"><?= e($colLabel) ?></label>
                    </div>
                    <?php endforeach; ?>
                    <hr class="my-2">
                    <button type="submit" class="btn btn-sm text-white w-100" style="background:var(--ld-primary);">Apply</button>
                </form>
            </div>
        </div>
    </div>
    <?php if (empty($recentTickets)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        <p class="mb-0">No tickets in the queue.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">#</th>
                    <th>Subject</th>
                    <?php if (in_array('status', $visibleColumns)): ?><th>Status</th><?php endif; ?>
                    <?php if (in_array('priority', $visibleColumns)): ?><th>Priority</th><?php endif; ?>
                    <?php if (in_array('type', $visibleColumns)): ?><th>Type</th><?php endif; ?>
                    <?php if (in_array('agent', $visibleColumns)): ?><th style="text-align:right;">Assigned To</th><?php endif; ?>
                    <?php if (in_array('group', $visibleColumns)): ?><th>Group</th><?php endif; ?>
                    <?php if (in_array('creator', $visibleColumns)): ?><th>Created By</th><?php endif; ?>
                    <?php if (in_array('location', $visibleColumns)): ?><th>Location</th><?php endif; ?>
                    <?php if (in_array('sla', $visibleColumns)): ?><th>SLA</th><?php endif; ?>
                    <?php if (in_array('created_at', $visibleColumns)): ?><th>Created</th><?php endif; ?>
                    <?php if (in_array('due_date', $visibleColumns)): ?><th>Due</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTickets as $t): ?>
                <?php
                    $qaGroupId = $t['type_group_id'] ? (int)$t['type_group_id'] : ($t['group_id'] ? (int)$t['group_id'] : null);
                    $qaAgents  = $qaGroupId ? ($groupAgents[$qaGroupId] ?? []) : $allAgentsForAssign;
                ?>
                <tr>
                    <td class="text-muted fw-bold">
                        <a href="/agent/tickets/<?= (int)$t['id'] ?>" class="text-muted text-decoration-none"><?= (int)$t['id'] ?></a>
                    </td>
                    <td>
                        <a href="/agent/tickets/<?= (int)$t['id'] ?>" class="text-decoration-none fw-semibold text-dark">
                            <?= e($t['subject']) ?>
                        </a>
                    </td>
                    <?php if (in_array('status', $visibleColumns)): ?>
                    <td>
                        <span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?>">
                            <?= e($statusLabels[$t['status']] ?? $t['status']) ?>
                        </span>
                    </td>
                    <?php endif; ?>
                    <?php if (in_array('priority', $visibleColumns)): ?>
                    <td>
                        <?php if ($t['priority_name']): ?>
                        <span class="badge" style="background:<?= e($t['priority_color']) ?>;"><?= e($t['priority_name']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if (in_array('type', $visibleColumns)): ?>
                    <td style="white-space:nowrap;">
                        <span class="d-inline-flex align-items-center gap-1 quick-type-wrap" data-ticket-id="<?= (int)$t['id'] ?>">
                            <span class="quick-type-badge"><?php if ($t['type_name']): ?><span class="badge" style="background:<?= e($t['type_color'] ?: '#6c757d') ?>;"><?= e($t['type_name']) ?></span><?php else: ?><span class="text-muted small">Not Set</span><?php endif; ?></span>
                            <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-type-btn" type="button" title="Change type" style="line-height:1;"><i class="bi bi-chevron-down" style="font-size:0.65rem;"></i></button>
                        </span>
                    </td>
                    <?php endif; ?>
                    <?php if (in_array('agent', $visibleColumns)): ?>
                    <td style="white-space:nowrap;text-align:right;">
                        <span class="d-inline-flex align-items-center gap-1 quick-assign-wrap" data-ticket-id="<?= (int)$t['id'] ?>">
                            <span class="quick-assign-name"><?= e($t['agent_name'] ?: 'Unassigned') ?></span>
                            <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-assign-btn"
                                    type="button"
                                    data-agents="<?= e(json_encode(array_values($qaAgents))) ?>"
                                    title="Assign agent"
                                    style="line-height:1;">
                                <i class="bi bi-chevron-down" style="font-size:0.65rem;"></i>
                            </button>
                        </span>
                    </td>
                    <?php endif; ?>
                    <?php if (in_array('group', $visibleColumns)): ?>
                    <td style="white-space:nowrap;">
                        <span class="d-inline-flex align-items-center gap-1 quick-group-wrap" data-ticket-id="<?= (int)$t['id'] ?>">
                            <span class="quick-group-name<?= $t['group_name'] ? '' : ' text-muted' ?>"><?= e($t['group_name'] ?: '—') ?></span>
                            <button class="btn btn-link btn-sm p-0 border-0 text-muted quick-group-btn" type="button" title="Change group" style="line-height:1;"><i class="bi bi-chevron-down" style="font-size:0.65rem;"></i></button>
                        </span>
                    </td>
                    <?php endif; ?>
                    <?php if (in_array('creator', $visibleColumns)): ?>
                    <td class="text-muted" style="white-space:nowrap;"><?= e($t['creator_name'] ?? '—') ?></td>
                    <?php endif; ?>
                    <?php if (in_array('location', $visibleColumns)): ?>
                    <td class="text-muted" style="white-space:nowrap;"><?= e($t['location_name'] ?? '—') ?></td>
                    <?php endif; ?>
                    <?php if (in_array('sla', $visibleColumns)): ?>
                    <td style="white-space:nowrap;">
                        <?php if ($t['sla_state']): ?>
                        <span class="badge bg-<?= $slaStateColors[$t['sla_state']] ?? 'secondary' ?>" title="<?= e(ucfirst(str_replace('_', ' ', $t['sla_state']))) ?>">
                            <i class="bi bi-stopwatch"></i>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if (in_array('created_at', $visibleColumns)): ?>
                    <td class="text-muted small" style="white-space:nowrap;"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                    <?php endif; ?>
                    <?php if (in_array('due_date', $visibleColumns)): ?>
                    <td class="text-muted small" style="white-space:nowrap;">
                        <?php if ($t['due_date']): ?>
                        <span class="<?= strtotime($t['due_date']) < time() ? 'text-danger fw-semibold' : '' ?>"><?= date('M j, Y', strtotime($t['due_date'])) ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var quickTypes      = <?= json_encode(array_values(array_map(fn($t) => ['id' => (int)$t['id'], 'name' => $t['name'], 'color' => $t['color'] ?: '#6c757d', 'group_id' => $t['group_id'] ? (int)$t['group_id'] : null], $types))) ?>;
    var quickGroups     = <?= json_encode(array_values(array_map(fn($g) => ['id' => (int)$g['id'], 'name' => $g['name']], $groups))) ?>;
    var quickGroupAgents = <?= json_encode(array_map(fn($agents) => array_values($agents), $groupAgents)) ?>;
    var quickAllAgents  = <?= json_encode(array_values(array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['name']], $allAgentsForAssign))) ?>;
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var activeMenu = null;
    var activeBtn  = null;
    var esc = function (s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

    function closeMenu() {
        if (activeMenu) { activeMenu.remove(); activeMenu = null; activeBtn = null; }
    }

    function openMenu(btn, kind) {
        closeMenu();
        var wrapEl   = btn.closest('[data-ticket-id]');
        var ticketId = wrapEl ? wrapEl.dataset.ticketId : '';
        var html = '<ul class="dropdown-menu show shadow-sm" style="position:fixed;z-index:1060;min-width:180px;max-height:260px;overflow-y:auto;font-size:0.85rem;">';
        if (kind === 'agent') {
            var agents = [];
            try { agents = JSON.parse(btn.dataset.agents || '[]'); } catch (_) {}
            html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="agent" data-val="" data-ticket-id="' + esc(ticketId) + '">Unassigned</a></li>';
            if (agents.length) {
                html += '<li><hr class="dropdown-divider"></li>';
                agents.forEach(function (a) {
                    html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="agent" data-val="' + esc(a.id) + '" data-ticket-id="' + esc(ticketId) + '">' + esc(a.name) + '</a></li>';
                });
            }
        } else if (kind === 'type') {
            html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="type" data-val="" data-ticket-id="' + esc(ticketId) + '"><span class="text-muted">Not Set</span></a></li>';
            if (quickTypes.length) {
                html += '<li><hr class="dropdown-divider"></li>';
                quickTypes.forEach(function (tp) {
                    html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="type" data-val="' + esc(tp.id) + '" data-ticket-id="' + esc(ticketId) + '"><span class="badge" style="background:' + esc(tp.color) + ';">' + esc(tp.name) + '</span></a></li>';
                });
            }
        } else if (kind === 'group') {
            html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="group" data-val="" data-ticket-id="' + esc(ticketId) + '"><span class="text-muted">None</span></a></li>';
            if (quickGroups.length) {
                html += '<li><hr class="dropdown-divider"></li>';
                quickGroups.forEach(function (g) {
                    html += '<li><a class="dropdown-item quick-menu-item" href="#" data-kind="group" data-val="' + esc(g.id) + '" data-ticket-id="' + esc(ticketId) + '">' + esc(g.name) + '</a></li>';
                });
            }
        }
        html += '</ul>';
        var div = document.createElement('div');
        div.innerHTML = html;
        var menu = div.firstChild;
        document.body.appendChild(menu);
        var rect = btn.getBoundingClientRect();
        menu.style.top  = (rect.bottom + 2) + 'px';
        menu.style.left = rect.left + 'px';
        activeMenu = menu;
        activeBtn  = btn;
    }

    function bindBtn(btn, kind) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (activeBtn === btn) { closeMenu(); return; }
            openMenu(btn, kind);
        });
    }

    document.querySelectorAll('.quick-assign-btn').forEach(function (b) { bindBtn(b, 'agent'); });
    document.querySelectorAll('.quick-type-btn').forEach(function (b) { bindBtn(b, 'type'); });
    document.querySelectorAll('.quick-group-btn').forEach(function (b) { bindBtn(b, 'group'); });

    document.addEventListener('click', function (e) {
        var item = e.target.closest('.quick-menu-item');
        if (item) {
            e.preventDefault();
            var kind     = item.dataset.kind;
            var val      = item.dataset.val;
            var ticketId = item.dataset.ticketId;
            closeMenu();
            if (kind === 'agent') {
                var assignWrap = document.querySelector('.quick-assign-wrap[data-ticket-id="' + ticketId + '"]');
                if (!assignWrap) return;
                var nameSpan = assignWrap.querySelector('.quick-assign-name');
                fetch('/api/tickets/' + ticketId + '/assign', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                    body: JSON.stringify({assigned_to: val === '' ? null : parseInt(val, 10)})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.success) { nameSpan.textContent = data.agent_name || 'Unassigned'; }
                }).catch(function () {});
            } else if (kind === 'type') {
                var typeWrap = document.querySelector('.quick-type-wrap[data-ticket-id="' + ticketId + '"]');
                if (!typeWrap) return;
                var badge = typeWrap.querySelector('.quick-type-badge');
                fetch('/api/tickets/' + ticketId + '/set-type', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                    body: JSON.stringify({type_id: val === '' ? null : parseInt(val, 10)})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.success) {
                        if (data.type_name) {
                            badge.innerHTML = '<span class="badge" style="background:' + esc(data.type_color || '#6c757d') + ';">' + esc(data.type_name) + '</span>';
                        } else {
                            badge.innerHTML = '<span class="text-muted small">Not Set</span>';
                        }
                        var tp = val === '' ? null : quickTypes.find(function (t) { return t.id === parseInt(val, 10); });
                        var newAgents = (tp && tp.group_id && quickGroupAgents[tp.group_id]) ? quickGroupAgents[tp.group_id] : quickAllAgents;
                        var assignBtn = document.querySelector('.quick-assign-wrap[data-ticket-id="' + ticketId + '"] .quick-assign-btn');
                        if (assignBtn) { assignBtn.dataset.agents = JSON.stringify(newAgents); }
                    }
                }).catch(function () {});
            } else if (kind === 'group') {
                var groupWrap = document.querySelector('.quick-group-wrap[data-ticket-id="' + ticketId + '"]');
                if (!groupWrap) return;
                var groupNameSpan = groupWrap.querySelector('.quick-group-name');
                fetch('/api/tickets/' + ticketId + '/set-group', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                    body: JSON.stringify({group_id: val === '' ? null : parseInt(val, 10)})
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (data.success) {
                        groupNameSpan.textContent = data.group_name || '—';
                        groupNameSpan.className = 'quick-group-name' + (data.group_name ? '' : ' text-muted');
                        var newAgents = data.agents || quickAllAgents;
                        var assignBtn = document.querySelector('.quick-assign-wrap[data-ticket-id="' + ticketId + '"] .quick-assign-btn');
                        if (assignBtn) { assignBtn.dataset.agents = JSON.stringify(newAgents); }
                    }
                }).catch(function () {});
            }
            return;
        }
        if (!e.target.closest('.quick-assign-btn') && !e.target.closest('.quick-type-btn') && !e.target.closest('.quick-group-btn') && !(activeMenu && activeMenu.contains(e.target))) {
            closeMenu();
        }
    });

    window.addEventListener('scroll', function (e) {
        if (activeMenu && (activeMenu === e.target || activeMenu.contains(e.target))) return;
        closeMenu();
    }, true);
    window.addEventListener('resize', closeMenu);
})();
</script>
