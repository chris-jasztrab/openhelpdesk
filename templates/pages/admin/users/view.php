<?php
$layout       = 'app';
$pageTitle    = $profileUser['first_name'] . ' ' . $profileUser['last_name'];
$sidebarItems = adminSidebar('users');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Users',  'url' => '/admin/users'],
    ['label' => $profileUser['first_name'] . ' ' . $profileUser['last_name']],
];

$badgeColors = ['admin' => 'danger', 'agent' => 'primary', 'power_user' => 'info', 'user' => 'secondary'];
$bc = $badgeColors[$profileUser['role']] ?? 'secondary';

// Status labels/colors defined inline in ticket section below
?>

<!-- User info card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="d-flex align-items-center gap-4">
                <!-- Avatar -->
                <?php if ($profileUser['avatar'] ?? null): ?>
                    <img src="/uploads/avatars/<?= e($profileUser['avatar']) ?>"
                         class="rounded-circle" width="72" height="72" style="object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold"
                         style="width:72px;height:72px;font-size:1.5rem;">
                        <?= strtoupper(mb_substr($profileUser['first_name'], 0, 1) . mb_substr($profileUser['last_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <!-- Details -->
                <div>
                    <h3 class="fw-bold mb-1">
                        <?= e($profileUser['first_name'] . ' ' . $profileUser['last_name']) ?>
                        <span class="badge bg-<?= $bc ?> ms-2 align-middle" style="font-size:.65rem;">
                            <?= e(ucfirst($profileUser['role'])) ?>
                        </span>
                    </h3>
                    <div class="text-muted small mb-1">
                        <i class="bi bi-envelope me-1"></i><?= e($profileUser['email']) ?>
                    </div>
                    <?php if ($profileUser['work_phone'] ?? null): ?>
                    <div class="text-muted small mb-1">
                        <i class="bi bi-telephone me-1"></i><?= e($profileUser['work_phone']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-3 mt-2 small text-muted">
                        <?php if ($profileUser['location_name'] ?? null): ?>
                        <span><i class="bi bi-geo-alt me-1"></i><?= e($profileUser['location_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($profileUser['created_at'] ?? null): ?>
                        <span><i class="bi bi-calendar3 me-1"></i>Member since <?= date('M j, Y', strtotime($profileUser['created_at'])) ?></span>
                        <?php endif; ?>
                        <?php if ($profileUser['totp_enabled'] ?? false): ?>
                        <span class="text-success"><i class="bi bi-shield-check me-1"></i>2FA enabled</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2 flex-wrap">
                <a href="/admin/users/<?= (int)$profileUser['id'] ?>/edit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-pencil me-1"></i>Edit User
                </a>
                <?php if ($profileUser['totp_enabled'] ?? false): ?>
                <form method="POST" action="/admin/users/<?= (int)$profileUser['id'] ?>/reset-2fa" class="d-inline"
                      onsubmit="return confirm('Reset 2FA for this user? They will need to set it up again.')">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-outline-warning">
                        <i class="bi bi-shield-x me-1"></i>Reset 2FA
                    </button>
                </form>
                <?php endif; ?>
                <?php if ($profileUser['id'] !== Auth::id()): ?>
                <a href="/admin/users/merge?suggest_delete=<?= (int)$profileUser['id'] ?>"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-right me-1"></i>Merge Into Another
                </a>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                    <i class="bi bi-trash me-1"></i>Delete User
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<?php if ($profileUser['id'] !== Auth::id()):
    $hasAssociated = ($createdCount + $assignedCount + $kbCount) > 0;
?>
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-semibold" id="deleteUserModalLabel">
                    <i class="bi bi-trash me-2"></i>Delete User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin/users/<?= (int)$profileUser['id'] ?>/delete" id="deleteUserForm">
                <?= csrfField() ?>
                <input type="hidden" name="transfer_to" id="transferToInput" value="">

                <!-- Step 1: info + optional transfer -->
                <div id="delStep1">
                    <div class="modal-body">
                        <p class="mb-3">You are about to permanently delete
                            <strong><?= e($profileUser['first_name'] . ' ' . $profileUser['last_name']) ?></strong>.
                            This action cannot be undone.
                        </p>

                        <?php if ($hasAssociated): ?>
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>This user has associated records that must be transferred:</strong>
                            <ul class="mb-0 mt-1">
                                <?php if ($createdCount > 0): ?>
                                <li><?= $createdCount ?> ticket<?= $createdCount !== 1 ? 's' : '' ?> they submitted</li>
                                <?php endif; ?>
                                <?php if ($assignedCount > 0): ?>
                                <li><?= $assignedCount ?> ticket<?= $assignedCount !== 1 ? 's' : '' ?> assigned to them</li>
                                <?php endif; ?>
                                <?php if ($kbCount > 0): ?>
                                <li><?= $kbCount ?> KB article<?= $kbCount !== 1 ? 's' : '' ?> they authored</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <label class="form-label fw-semibold">Transfer records to:</label>
                        <div class="position-relative mb-2">
                            <input type="text" id="transferSearch" class="form-control"
                                   placeholder="Search by name or email…" autocomplete="off">
                            <div id="transferDropdown"
                                 class="list-group position-absolute w-100 shadow-sm"
                                 style="z-index:1100;display:none;max-height:200px;overflow-y:auto;"></div>
                        </div>
                        <div id="selectedTransferUserDiv" style="display:none;" class="mb-1">
                            <span class="badge bg-primary fs-6 fw-normal py-2 px-3"
                                  id="selectedTransferUserBadge"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2"
                                    id="clearTransferBtn">
                                <i class="bi bi-x-circle me-1"></i>Remove
                            </button>
                        </div>
                        <?php else: ?>
                        <p class="text-muted small mb-0">
                            This user has no tickets or KB articles and can be safely deleted.
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="delStep1Btn"
                                <?= $hasAssociated ? 'disabled' : '' ?>>
                            Continue
                        </button>
                    </div>
                </div>

                <!-- Step 2: confirmation -->
                <div id="delStep2" style="display:none;">
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <span id="delConfirmText"></span>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" id="delBackBtn">Back</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Confirm &amp; Delete
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var hasAssociated = <?= $hasAssociated ? 'true' : 'false' ?>;
    var userName      = <?= json_encode($profileUser['first_name'] . ' ' . $profileUser['last_name']) ?>;
    var userId        = <?= (int) $profileUser['id'] ?>;
    var selectedUser  = null;

    var step1        = document.getElementById('delStep1');
    var step2        = document.getElementById('delStep2');
    var step1Btn     = document.getElementById('delStep1Btn');
    var backBtn      = document.getElementById('delBackBtn');
    var confirmTxt   = document.getElementById('delConfirmText');
    var transferInput = document.getElementById('transferToInput');
    var searchInput   = document.getElementById('transferSearch');
    var dropdown      = document.getElementById('transferDropdown');
    var selectedDiv   = document.getElementById('selectedTransferUserDiv');
    var selectedBadge = document.getElementById('selectedTransferUserBadge');
    var clearBtn      = document.getElementById('clearTransferBtn');

    // Auto-open when URL contains ?delete=1 (deferred so Bootstrap is loaded first)
    if (window.location.search.indexOf('delete=1') !== -1) {
        history.replaceState({}, '', window.location.pathname);
        window.addEventListener('load', function () {
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        });
    }

    // ── Autocomplete ──────────────────────────────────────────────────────────
    if (hasAssociated && searchInput) {
        var debounce;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounce);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            debounce = setTimeout(function () {
                fetch('/api/user-search?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) { throw new Error('HTTP ' + r.status); }
                        return r.json();
                    })
                    .then(function (users) {
                        dropdown.innerHTML = '';
                        users = users.filter(function (u) { return parseInt(u.id) !== userId; });
                        if (!users.length) {
                            dropdown.innerHTML = '<div class="list-group-item text-muted small">No users found.</div>';
                        } else {
                            users.forEach(function (u) {
                                var btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'list-group-item list-group-item-action small py-2';
                                btn.innerHTML = '<strong>' + esc(u.first_name + ' ' + u.last_name) + '</strong>'
                                    + ' <span class="text-muted">— ' + esc(u.email) + '</span>'
                                    + ' <span class="badge bg-' + roleBadge(u.role) + ' ms-1">' + esc(u.role) + '</span>';
                                btn.addEventListener('click', function () { selectUser(u); });
                                dropdown.appendChild(btn);
                            });
                        }
                        dropdown.style.display = 'block';
                    })
                    .catch(function () {
                        dropdown.innerHTML = '<div class="list-group-item text-muted small">Search unavailable. Please try again.</div>';
                        dropdown.style.display = 'block';
                    });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (searchInput && !searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }

    function selectUser(u) {
        selectedUser = u;
        transferInput.value = u.id;
        searchInput.value = '';
        searchInput.style.display = 'none';
        dropdown.style.display = 'none';
        selectedBadge.textContent = u.first_name + ' ' + u.last_name + ' (' + u.email + ')';
        selectedDiv.style.display = '';
        step1Btn.disabled = false;
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            selectedUser = null;
            transferInput.value = '';
            searchInput.value = '';
            searchInput.style.display = '';
            selectedDiv.style.display = 'none';
            if (hasAssociated) step1Btn.disabled = true;
        });
    }

    // ── Step navigation ───────────────────────────────────────────────────────
    if (step1Btn) {
        step1Btn.addEventListener('click', function () {
            var msg = 'Permanently delete <strong>' + esc(userName) + '</strong>?';
            if (selectedUser) {
                msg += ' Their tickets and KB articles will be transferred to <strong>'
                    + esc(selectedUser.first_name + ' ' + selectedUser.last_name) + '</strong>.';
            }
            confirmTxt.innerHTML = msg;
            step1.style.display = 'none';
            step2.style.display = '';
        });
    }

    if (backBtn) {
        backBtn.addEventListener('click', function () {
            step1.style.display = '';
            step2.style.display = 'none';
        });
    }

    // Reset on modal close
    document.getElementById('deleteUserModal').addEventListener('hidden.bs.modal', function () {
        step1.style.display = '';
        step2.style.display = 'none';
        if (hasAssociated && searchInput) {
            selectedUser = null;
            transferInput.value = '';
            searchInput.value = '';
            searchInput.style.display = '';
            if (dropdown) dropdown.style.display = 'none';
            if (selectedDiv) selectedDiv.style.display = 'none';
            step1Btn.disabled = true;
        }
    });

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function roleBadge(role) {
        return role === 'admin' ? 'danger' : role === 'agent' ? 'primary' : role === 'power_user' ? 'info' : 'secondary';
    }
})();
</script>
<?php endif; ?>

<?php
$_statusColors    = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'waiting_on_customer' => 'warning', 'waiting_on_third_party' => 'dark', 'resolved' => 'success', 'closed' => 'secondary'];
$_allStatusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'waiting_on_customer' => 'Waiting on Customer', 'waiting_on_third_party' => 'Waiting on Third Party', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$_hasFilters      = array_filter($userTicketFilters, fn($v) => is_array($v) ? !empty($v) : $v !== '');
$_baseUrl         = '/admin/users/' . (int)$profileUser['id'];
?>

<!-- User tickets section header -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-semibold mb-0">
        <?= $_hasFilters ? 'Tickets' : 'Open Tickets' ?>
        <span class="badge bg-secondary ms-1"><?= count($openTickets) ?><?= $_hasFilters ? ' filtered' : '' ?></span>
    </h5>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="userFilterPanelToggle()">
        <i class="bi bi-funnel me-1"></i>Filters
        <?php if ($_hasFilters): ?><span class="badge bg-primary rounded-pill ms-1"><?= count($_hasFilters) ?></span><?php endif; ?>
    </button>
</div>

<!-- User ticket filter panel backdrop -->
<div class="filter-panel-backdrop" id="userFilterPanelBackdrop" onclick="userFilterPanelClose()"></div>

<!-- User ticket filter panel -->
<div class="filter-panel" id="userFilterPanel">
    <div class="filter-panel-header">
        <span class="fw-semibold"><i class="bi bi-funnel me-1"></i>Filter Tickets</span>
        <button type="button" class="btn-close" onclick="userFilterPanelClose()" aria-label="Close"></button>
    </div>
    <div class="filter-panel-body">
        <form method="GET" action="<?= e($_baseUrl) ?>">
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <input type="text" class="form-control form-control-sm" name="q"
                       value="<?= e($userTicketFilters['q']) ?>" placeholder="Search subject&hellip;">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <div class="filter-checklist">
                    <?php foreach ($_allStatusLabels as $val => $lbl): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="status[]" value="<?= $val ?>" <?= in_array($val, $userTicketFilters['status'], true) ? 'checked' : '' ?>>
                        <span><?= e($lbl) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Priority</label>
                <div class="filter-checklist">
                    <?php foreach ($ticketPriorities as $p): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="priority[]" value="<?= $p['id'] ?>" <?= in_array((string)$p['id'], $userTicketFilters['priority'], true) ? 'checked' : '' ?>>
                        <span><?= e($p['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Type</label>
                <div class="filter-checklist">
                    <?php foreach ($ticketTypes as $tp): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="type[]" value="<?= $tp['id'] ?>" <?= in_array((string)$tp['id'], $userTicketFilters['type'], true) ? 'checked' : '' ?>>
                        <span><?= e($tp['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Assigned Agent</label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="agent[]" value="unassigned" <?= in_array('unassigned', $userTicketFilters['agent'], true) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic">Unassigned</span>
                    </label>
                    <?php foreach ($ticketAgents as $ag): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="agent[]" value="<?= $ag['id'] ?>" <?= in_array((string)$ag['id'], $userTicketFilters['agent'], true) ? 'checked' : '' ?>>
                        <span><?= e($ag['first_name'] . ' ' . $ag['last_name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm text-white flex-grow-1" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="<?= e($_baseUrl) ?>" class="btn btn-sm btn-outline-secondary" title="Clear all filters">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:70px">#</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($openTickets)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0"><?= $_hasFilters ? 'No tickets match the current filters.' : 'No open tickets.' ?></p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($openTickets as $t):
                        $_stLabel = $_allStatusLabels[$t['status']] ?? ucfirst(str_replace('_', ' ', $t['status']));
                        $_stColor = $_statusColors[$t['status']] ?? 'secondary';
                    ?>
                    <tr style="cursor:pointer;" onclick="window.location='/admin/tickets/<?= (int)$t['id'] ?>'">
                        <td class="text-muted small fw-semibold">#<?= (int)$t['id'] ?></td>
                        <td class="fw-semibold"><?= e($t['subject']) ?></td>
                        <td>
                            <span class="badge bg-<?= $_stColor ?>"><?= $_stLabel ?></span>
                        </td>
                        <td>
                            <?php if ($t['priority_name'] ?? null): ?>
                                <span class="badge" style="background:<?= e($t['priority_color'] ?: '#6c757d') ?>;">
                                    <?= e($t['priority_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($t['type_name'] ?? '&mdash;') ?></td>
                        <td class="text-muted small"><?= e($t['assigned_name'] ?: 'Unassigned') ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    function userFilterPanelOpen() {
        document.getElementById('userFilterPanel').classList.add('open');
        document.getElementById('userFilterPanelBackdrop').classList.add('open');
    }
    function userFilterPanelClose() {
        document.getElementById('userFilterPanel').classList.remove('open');
        document.getElementById('userFilterPanelBackdrop').classList.remove('open');
    }
    window.userFilterPanelToggle = function () {
        document.getElementById('userFilterPanel').classList.contains('open') ? userFilterPanelClose() : userFilterPanelOpen();
    };
    window.userFilterPanelClose = userFilterPanelClose;
    <?php if ($_hasFilters): ?>
    userFilterPanelOpen();
    <?php endif; ?>
})();
</script>
