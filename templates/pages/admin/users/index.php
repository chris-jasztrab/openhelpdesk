<?php
$layout      = 'app';
$pageTitle   = 'Users';
$sidebarItems = adminSidebar('users');
$breadcrumbs = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Users'],
];
$filterParams = [];
if (!empty($roleFilter)) $filterParams['role']     = $roleFilter;
if (!empty($locFilter))  $filterParams['location'] = $locFilter;
if ($qFilter !== '')     $filterParams['q']        = $qFilter;
if (!empty($externalFilter)) $filterParams['external'] = '1';
$hasFilters = !empty($filterParams);
// Search lives in its own toolbar box now, so it shouldn't inflate the Filters
// badge — that count is for the panel's role/location/contact-type checkboxes.
$panelFilterCount = count(array_diff_key($filterParams, ['q' => null]));
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0">Users</h2>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <span class="badge bg-secondary fs-6" id="userCountBadge"><?= count($users) ?><?= $hasFilters ? ' filtered' : ' total' ?></span>
        <div class="position-relative">
            <i class="bi bi-search position-absolute text-muted"
               style="left:.6rem;top:50%;transform:translateY(-50%);font-size:.8rem;pointer-events:none;"></i>
            <input type="search" class="form-control form-control-sm" id="userSearchBox"
                   value="<?= e($qFilter) ?>" placeholder="Search users…" autocomplete="off"
                   aria-label="Search users by name or email"
                   style="width:220px;padding-left:1.9rem;">
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="filterPanelBtn" onclick="filterPanelToggle()">
            <i class="bi bi-funnel me-1"></i>Filters
            <?php if ($panelFilterCount): ?><span class="badge bg-primary rounded-pill ms-1"><?= $panelFilterCount ?></span><?php endif; ?>
        </button>
        <a href="/admin/users/online" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-circle-fill text-success me-1" style="font-size:.6rem;"></i>Who's Online
        </a>
        <a href="/admin/users/merge" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left-right me-1"></i>Merge Users
        </a>
        <a href="/admin/users/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-person-plus me-1"></i>Add User
        </a>
    </div>
</div>

<!-- Filter Panel Backdrop -->
<div class="filter-panel-backdrop" id="filterPanelBackdrop" onclick="filterPanelClose()"></div>

<!-- Filter Panel -->
<div class="filter-panel" id="filterPanel">
    <div class="filter-panel-header">
        <span class="fw-semibold"><i class="bi bi-funnel me-1"></i>Filters</span>
        <button type="button" class="btn-close" onclick="filterPanelClose()" aria-label="Close"></button>
    </div>
    <div class="filter-panel-body">
        <form method="GET" action="/admin/users">
            <!-- Search moved to the toolbar box; carried through so applying a
                 filter from here doesn't silently drop the typed term. -->
            <input type="hidden" name="q" id="filterPanelQ" value="<?= e($qFilter) ?>">
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Role</label>
                <div class="filter-checklist">
                    <?php foreach (roleChoices() as $val => $lbl): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="role[]" value="<?= $val ?>" <?= in_array($val, $roleFilter, true) ? 'checked' : '' ?>>
                        <span><?= e($lbl) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1"><?= label('location.singular') ?></label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="location[]" value="none" <?= in_array('none', $locFilter, true) ? 'checked' : '' ?>>
                        <span class="text-muted fst-italic">No <?= label('location.singular') ?></span>
                    </label>
                    <?php foreach ($locations as $loc): ?>
                    <label class="filter-check-item">
                        <input type="checkbox" name="location[]" value="<?= $loc['id'] ?>" <?= in_array((string) $loc['id'], $locFilter, true) ? 'checked' : '' ?>>
                        <span><?= e($loc['name']) ?><?= isMetaLocationName((string) $loc['name']) ? ' <span class="text-muted fst-italic">(any)</span>' : '' ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold mb-1">Contact type</label>
                <div class="filter-checklist">
                    <label class="filter-check-item">
                        <input type="checkbox" name="external" value="1" <?= !empty($externalFilter) ? 'checked' : '' ?>>
                        <span>External contacts only <span class="text-muted fst-italic">(forwarded third parties)</span></span>
                    </label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm text-white flex-grow-1" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="/admin/users?reset=1" class="btn btn-sm btn-outline-secondary" title="Clear all filters">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<style>
    #usersTableRegion.is-loading { opacity: .55; pointer-events: none; transition: opacity .12s ease; }
</style>

<div id="usersTableRegion">
<?php require ROOT_DIR . '/templates/pages/admin/users/_table.php'; ?>
</div>

<!-- Bulk actions form (submitted programmatically) -->
<form id="userBulkForm" method="POST" action="/admin/users/bulk" class="d-none">
    <?= csrfField() ?>
    <input type="hidden" name="action" id="userBulkActionInput">
</form>

<script>
/* ── Bulk selection ──────────────────────────────────────────────────────
   Delegated and DOM-derived (no Set) so it survives the search box swapping
   #usersTableRegion; a swap simply leaves nothing ticked. */
(function () {
    function rowCbs() { return Array.prototype.slice.call(document.querySelectorAll('.user-cb')); }
    function refresh() {
        var all = rowCbs();
        var checked = all.filter(function (cb) { return cb.checked; });
        var bar = document.getElementById('userBulkBar');
        var cnt = document.getElementById('userBulkCount');
        var sa  = document.getElementById('userSelectAll');
        if (bar) bar.style.display = checked.length ? 'flex' : 'none';
        if (cnt) cnt.textContent = checked.length + ' selected';
        if (sa) {
            sa.checked       = all.length > 0 && checked.length === all.length;
            sa.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    }
    document.addEventListener('change', function (e) {
        if (e.target.id === 'userSelectAll') {
            rowCbs().forEach(function (cb) { cb.checked = e.target.checked; });
            refresh();
        } else if (e.target.classList.contains('user-cb')) {
            refresh();
        }
    });
    window.userBulkAction = function (action) {
        var ids = rowCbs().filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
        if (!ids.length) return;
        var form = document.getElementById('userBulkForm');
        document.getElementById('userBulkActionInput').value = action;
        form.querySelectorAll('input[name="user_ids[]"]').forEach(function (el) { el.remove(); });
        ids.forEach(function (id) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'user_ids[]'; inp.value = id;
            form.appendChild(inp);
        });
        form.submit();
    };
})();
</script>

<script>
(function () {
    function filterPanelOpen() {
        document.getElementById('filterPanel').classList.add('open');
        document.getElementById('filterPanelBackdrop').classList.add('open');
        sessionStorage.setItem('adminUserFilterPanelOpen', '1');
    }
    function filterPanelClose() {
        document.getElementById('filterPanel').classList.remove('open');
        document.getElementById('filterPanelBackdrop').classList.remove('open');
        sessionStorage.setItem('adminUserFilterPanelOpen', '0');
    }
    window.filterPanelToggle = function () {
        document.getElementById('filterPanel').classList.contains('open') ? filterPanelClose() : filterPanelOpen();
    };
    window.filterPanelClose = filterPanelClose;
    if (sessionStorage.getItem('adminUserFilterPanelOpen') === '1') filterPanelOpen();
})();

/* ── Toolbar search-as-you-type ────────────────────────────────────────
   Refetches the table partial from the same /admin/users route, so the
   panel's role/location filters and the current sort still apply. */
(function () {
    var box    = document.getElementById('userSearchBox');
    var region = document.getElementById('usersTableRegion');
    var badge  = document.getElementById('userCountBadge');
    var hiddenQ = document.getElementById('filterPanelQ');
    if (!box || !region) return;

    var DEBOUNCE_MS = 250;
    var timer = null;
    var inflight = null;
    var lastSent = box.value;

    function queryString(term, forAjax) {
        var p = new URLSearchParams(window.location.search);
        p.delete('reset');
        p.delete('ajax');
        if (term === '') p.delete('q'); else p.set('q', term);
        if (forAjax) p.set('ajax', '1');
        return p.toString();
    }

    async function search(term) {
        if (inflight) inflight.abort();
        inflight = new AbortController();
        region.classList.add('is-loading');
        try {
            var res = await fetch('/admin/users?' + queryString(term, true), {
                signal: inflight.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            region.innerHTML = await res.text();

            var card = region.querySelector('[data-user-count]');
            if (badge && card) {
                var n = card.getAttribute('data-user-count');
                // Any active filter (search included) means the count is a subset.
                var filtered = term !== '' || /[?&](role|location|external)/.test(window.location.search);
                badge.textContent = n + (filtered ? ' filtered' : ' total');
            }
            if (hiddenQ) hiddenQ.value = term;

            // Keep the URL shareable/reloadable without stacking history entries
            // for every keystroke.
            var qs = queryString(term, false);
            history.replaceState(null, '', '/admin/users' + (qs ? '?' + qs : ''));
        } catch (err) {
            if (err.name !== 'AbortError') console.error('user search failed', err);
        } finally {
            region.classList.remove('is-loading');
        }
    }

    box.addEventListener('input', function () {
        clearTimeout(timer);
        var term = box.value.trim();
        timer = setTimeout(function () {
            if (term === lastSent) return;
            lastSent = term;
            search(term);
        }, DEBOUNCE_MS);
    });

    // Enter shouldn't reload the page — results are already live.
    box.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            var term = box.value.trim();
            lastSent = term;
            search(term);
        }
    });
})();
</script>
