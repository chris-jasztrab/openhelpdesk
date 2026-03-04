<?php
$layout       = 'app';
$pageTitle    = 'Merge Users';
$sidebarItems = adminSidebar('users');
$breadcrumbs  = [
    ['label' => 'Admin',  'url' => '/admin'],
    ['label' => 'Users',  'url' => '/admin/users'],
    ['label' => 'Merge Users'],
];

$roleBadge = fn($r) => match ($r) { 'admin' => 'danger', 'agent' => 'primary', default => 'secondary' };

$suggestUser ??= null;
$keepUser    ??= null;
$deleteUser  ??= null;
$stats       ??= null;
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-0">Merge Users</h2>
        <p class="text-muted small mb-0">Combine two accounts into one. All tickets, comments, and history are transferred to the account you keep.</p>
    </div>
</div>

<?php if ($step === 'search'): ?>
<!-- ── Step 1: search ─────────────────────────────────────── -->

<?php
$suggestId    = $suggestUser ? (int) $suggestUser['id'] : 0;
$suggestLabel = $suggestUser
    ? e($suggestUser['first_name'] . ' ' . $suggestUser['last_name'] . ' (' . $suggestUser['email'] . ')')
    : '';
?>

<div class="card border-0 shadow-sm" style="max-width:640px;">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>Select Two Accounts</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-4">
            Search for both accounts below. After clicking <strong>Preview Merge</strong> you will
            confirm the details before anything is changed.
        </p>

        <form method="POST" action="/admin/users/merge" id="mergeSearchForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="preview">

            <!-- Account to Remove -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Account to <span class="text-danger">Remove</span></label>
                <div class="text-muted small mb-2">This account will be deleted after all data is transferred.</div>
                <input type="hidden" name="delete_id" id="delete_id_input" value="<?= $suggestId ?>">
                <div class="position-relative">
                    <input type="text" class="form-control" id="delete_search"
                           placeholder="Search by name or email…" autocomplete="off"
                           value="<?= $suggestLabel ?>">
                    <div id="delete_dropdown"
                         class="list-group shadow-sm position-absolute w-100"
                         style="display:none;z-index:1050;max-height:220px;overflow-y:auto;top:calc(100% + 2px);left:0;"></div>
                </div>
            </div>

            <!-- Account to Keep -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Account to <span class="text-success">Keep</span></label>
                <div class="text-muted small mb-2">This account will be retained with all data merged into it.</div>
                <input type="hidden" name="keep_id" id="keep_id_input" value="">
                <div class="position-relative">
                    <input type="text" class="form-control" id="keep_search"
                           placeholder="Search by name or email…" autocomplete="off">
                    <div id="keep_dropdown"
                         class="list-group shadow-sm position-absolute w-100"
                         style="display:none;z-index:1050;max-height:220px;overflow-y:auto;top:calc(100% + 2px);left:0;"></div>
                </div>
            </div>

            <button type="submit" id="previewBtn" class="btn text-white"
                    style="background:var(--ld-primary);"
                    <?= ($suggestId === 0) ? 'disabled' : '' ?>>
                <i class="bi bi-eye me-1"></i>Preview Merge
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var deleteInput    = document.getElementById('delete_search');
    var deleteIdInp    = document.getElementById('delete_id_input');
    var deleteDropdown = document.getElementById('delete_dropdown');

    var keepInput    = document.getElementById('keep_search');
    var keepIdInp    = document.getElementById('keep_id_input');
    var keepDropdown = document.getElementById('keep_dropdown');

    var previewBtn = document.getElementById('previewBtn');

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function roleBadge(r) {
        return r === 'admin' ? 'danger' : r === 'agent' ? 'primary' : 'secondary';
    }

    function checkReady() {
        previewBtn.disabled = !(deleteIdInp.value && keepIdInp.value);
    }

    checkReady(); // run on load to handle pre-populated state

    function buildDropdown(users, dropdown, idInput, otherIdInput, searchInput) {
        dropdown.innerHTML = '';
        var filtered = users.filter(function (u) { return String(u.id) !== otherIdInput.value; });
        if (!filtered.length) {
            dropdown.innerHTML = '<div class="list-group-item text-muted small">No users found.</div>';
        } else {
            filtered.forEach(function (u) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action small py-2';
                btn.innerHTML = '<strong>' + esc(u.first_name + ' ' + u.last_name) + '</strong>'
                    + ' <span class="text-muted">&mdash; ' + esc(u.email) + '</span>'
                    + ' <span class="badge bg-' + roleBadge(u.role) + ' ms-1">' + esc(u.role) + '</span>';
                btn.addEventListener('click', function () {
                    idInput.value      = u.id;
                    searchInput.value  = u.first_name + ' ' + u.last_name + ' (' + u.email + ')';
                    dropdown.style.display = 'none';
                    checkReady();
                });
                dropdown.appendChild(btn);
            });
        }
        dropdown.style.display = 'block';
    }

    function setupPicker(searchInput, idInput, otherIdInput, dropdown) {
        var debounce;
        searchInput.addEventListener('input', function () {
            idInput.value = '';
            checkReady();
            clearTimeout(debounce);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            debounce = setTimeout(function () {
                fetch('/api/user-search?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (users) { buildDropdown(users, dropdown, idInput, otherIdInput, searchInput); })
                    .catch(function () {
                        dropdown.innerHTML = '<div class="list-group-item text-muted small">Search unavailable.</div>';
                        dropdown.style.display = 'block';
                    });
            }, 250);
        });
    }

    setupPicker(deleteInput, deleteIdInp, keepIdInp, deleteDropdown);
    setupPicker(keepInput,   keepIdInp,   deleteIdInp, keepDropdown);

    document.addEventListener('click', function (e) {
        if (!deleteInput.contains(e.target) && !deleteDropdown.contains(e.target)) deleteDropdown.style.display = 'none';
        if (!keepInput.contains(e.target)   && !keepDropdown.contains(e.target))   keepDropdown.style.display = 'none';
    });

    document.getElementById('mergeSearchForm').addEventListener('submit', function (e) {
        if (!deleteIdInp.value || !keepIdInp.value) {
            e.preventDefault();
            alert('Please select both accounts before previewing.');
        }
    });
})();
</script>

<?php elseif ($step === 'preview'): ?>
<!-- ── Step 2: preview / confirm ─────────────────────────── -->

<div class="row g-4 mb-4" style="max-width:860px;">

    <!-- Account to REMOVE -->
    <div class="col-md-6">
        <div class="card border-danger border-2 h-100">
            <div class="card-header bg-danger text-white py-2">
                <i class="bi bi-trash me-1"></i><strong>Account to Remove</strong>
            </div>
            <div class="card-body">
                <div class="fw-bold fs-6 mb-1"><?= e($deleteUser['first_name'] . ' ' . $deleteUser['last_name']) ?></div>
                <div class="text-muted small mb-2"><i class="bi bi-envelope me-1"></i><?= e($deleteUser['email']) ?></div>
                <span class="badge bg-<?= $roleBadge($deleteUser['role']) ?>"><?= e(ucfirst($deleteUser['role'])) ?></span>
                <hr class="my-3">
                <ul class="list-unstyled small text-muted mb-0">
                    <li><i class="bi bi-ticket-detailed me-1"></i><?= $stats['delete']['tickets_created'] ?> tickets created</li>
                    <li><i class="bi bi-person-check me-1"></i><?= $stats['delete']['tickets_assigned'] ?> tickets assigned</li>
                    <li><i class="bi bi-chat-left me-1"></i><?= $stats['delete']['comments'] ?> timeline entries</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Account to KEEP -->
    <div class="col-md-6">
        <div class="card border-success border-2 h-100">
            <div class="card-header bg-success text-white py-2">
                <i class="bi bi-check-circle me-1"></i><strong>Account to Keep</strong>
            </div>
            <div class="card-body">
                <div class="fw-bold fs-6 mb-1"><?= e($keepUser['first_name'] . ' ' . $keepUser['last_name']) ?></div>
                <div class="text-muted small mb-2"><i class="bi bi-envelope me-1"></i><?= e($keepUser['email']) ?></div>
                <span class="badge bg-<?= $roleBadge($keepUser['role']) ?>"><?= e(ucfirst($keepUser['role'])) ?></span>
                <hr class="my-3">
                <ul class="list-unstyled small text-muted mb-0">
                    <li><i class="bi bi-ticket-detailed me-1"></i><?= $stats['keep']['tickets_created'] ?> tickets created</li>
                    <li><i class="bi bi-person-check me-1"></i><?= $stats['keep']['tickets_assigned'] ?> tickets assigned</li>
                    <li><i class="bi bi-chat-left me-1"></i><?= $stats['keep']['comments'] ?> timeline entries</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-warning d-flex gap-2" style="max-width:860px;">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
    <div>
        <strong>This action cannot be undone.</strong> The account
        <strong><?= e($deleteUser['email']) ?></strong> will be permanently deleted
        and all its tickets, comments, group memberships, and history will be
        reassigned to <strong><?= e($keepUser['email']) ?></strong>.
    </div>
</div>

<div class="d-flex gap-2" style="max-width:860px;">
    <a href="/admin/users/merge" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <form method="POST" action="/admin/users/merge"
          onsubmit="return confirm('Are you sure you want to permanently merge these accounts? This cannot be undone.')">
        <?= csrfField() ?>
        <input type="hidden" name="action"    value="execute">
        <input type="hidden" name="keep_id"   value="<?= (int) $keepUser['id'] ?>">
        <input type="hidden" name="delete_id" value="<?= (int) $deleteUser['id'] ?>">
        <button type="submit" class="btn btn-danger">
            <i class="bi bi-arrow-left-right me-1"></i>Confirm Merge
        </button>
    </form>
</div>

<?php endif; ?>
