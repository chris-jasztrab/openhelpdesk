<?php
$layout       = 'app';
$pageTitle    = 'Unresolved Tickets – Reports';
$sidebarItems = Auth::isAdmin() ? adminSidebar('reports') : staffSidebar('reports');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Reports', 'url' => '/admin/reports'],
    ['label' => 'Unresolved Tickets'],
];

$ageLabels = ['< 1 day', '1–3 days', '3–7 days', '7–14 days', '> 14 days'];
$ageColors = ['success', 'info', 'warning', 'danger', 'danger'];
?>
<style>
    .drill-tile {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        padding: .5rem .75rem;
        margin-bottom: .25rem;
        border: 1px solid transparent;
        background: transparent;
        border-radius: .375rem;
        cursor: pointer;
        transition: background-color .12s ease, border-color .12s ease;
    }
    .drill-tile:hover { background: rgba(13, 110, 253, .06); }
    .drill-tile.active {
        background: rgba(13, 110, 253, .10);
        border-color: rgba(13, 110, 253, .35);
    }
    .drill-tile.active::before {
        content: '\F26B'; /* bi-funnel-fill */
        font-family: 'bootstrap-icons';
        margin-right: .35rem;
        color: var(--ld-primary, #0d6efd);
        font-size: .75rem;
    }
    #unresolvedDrillRegion.is-loading { opacity: .55; pointer-events: none; transition: opacity .12s ease; }
</style>

<div class="mb-4">
    <h2 class="fw-bold mb-1">Reports &amp; Analytics</h2>
    <p class="text-muted mb-0">Unresolved Tickets</p>
</div>

<!-- Summary cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="text-muted small">Total Unresolved</div>
                    <div class="fs-4 fw-bold"><?= $totalUnresolved ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-person-x"></i></div>
                <div>
                    <div class="text-muted small">Unassigned</div>
                    <div class="fs-4 fw-bold"><?= $unassigned ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-stopwatch"></i></div>
                <div>
                    <div class="text-muted small">SLA Breached</div>
                    <div class="fs-4 fw-bold"><?= $breachedCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-clock"></i></div>
                <div>
                    <div class="text-muted small">Avg Age</div>
                    <div class="fs-4 fw-bold"><?= $avgAge ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Drilldown breakdown -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">By Status</h6>
                <span class="text-muted small">click to filter</span>
            </div>
            <div class="card-body">
                <?php if (empty($byStatus)): ?>
                <div class="text-muted small">No unresolved tickets.</div>
                <?php else: foreach ($byStatus as $s): ?>
                <button type="button" class="drill-tile" data-drill="status" data-value="<?= e($s['status']) ?>">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?></span>
                    <span class="fw-bold"><?= (int) $s['count'] ?></span>
                </button>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">By Age</h6>
                <span class="text-muted small">click to filter</span>
            </div>
            <div class="card-body">
                <?php foreach ($agingBuckets as $i => $count): ?>
                <button type="button" class="drill-tile" data-drill="age" data-value="<?= $i ?>">
                    <span class="text-muted"><?= $ageLabels[$i] ?></span>
                    <span class="badge bg-<?= $ageColors[$i] ?> bg-opacity-10 text-<?= $ageColors[$i] ?>"><?= (int) $count ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Filtered ticket table (AJAX-swappable) -->
<div id="unresolvedDrillRegion">
    <?php require __DIR__ . '/_unresolved-table.php'; ?>
</div>

<script>
(function () {
    const region = document.getElementById('unresolvedDrillRegion');
    if (!region) return;

    const DEFAULT_PER_PAGE = 25;
    const state = {
        status:   <?= json_encode($statusFilter, JSON_UNESCAPED_SLASHES) ?>,
        age:      <?= $ageFilter === null ? 'null' : (int) $ageFilter ?>,
        page:     <?= (int) $page ?>,
        per_page: <?= (int) $perPage ?>,
    };

    function buildParams(s, includeAjax) {
        const p = new URLSearchParams();
        if (s.status) p.set('status', s.status);
        if (s.age !== null && s.age !== '') p.set('age', String(s.age));
        if (s.page > 1) p.set('page', String(s.page));
        if (s.per_page !== DEFAULT_PER_PAGE) p.set('per_page', String(s.per_page));
        if (includeAjax) p.set('ajax', '1');
        return p.toString();
    }

    function paintTiles() {
        document.querySelectorAll('[data-drill="status"]').forEach(el => {
            el.classList.toggle('active', !!state.status && el.dataset.value === state.status);
        });
        document.querySelectorAll('[data-drill="age"]').forEach(el => {
            el.classList.toggle('active', state.age !== null && String(state.age) === el.dataset.value);
        });
    }

    let pendingController = null;
    async function refresh(pushHistory) {
        if (pendingController) pendingController.abort();
        pendingController = new AbortController();
        region.classList.add('is-loading');

        const ajaxUrl = '/admin/reports/unresolved?' + buildParams(state, true);
        try {
            const res = await fetch(ajaxUrl, { signal: pendingController.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            region.innerHTML = await res.text();
        } catch (err) {
            if (err.name !== 'AbortError') console.error('drilldown fetch failed', err);
        } finally {
            region.classList.remove('is-loading');
        }

        if (pushHistory) {
            const qs = buildParams(state, false);
            const newUrl = '/admin/reports/unresolved' + (qs ? '?' + qs : '');
            history.pushState({ ...state }, '', newUrl);
        }
        paintTiles();
    }

    // Tile clicks (top status/age tiles AND in-card filter-chip × buttons)
    document.addEventListener('click', e => {
        const el = e.target.closest('[data-drill]');
        if (!el) return;
        e.preventDefault();
        const dim = el.dataset.drill;
        const val = el.dataset.value;
        if (dim === 'status') {
            state.status = (state.status === val) ? '' : val;
        } else if (dim === 'age') {
            const num = parseInt(val, 10);
            state.age = (state.age === num) ? null : num;
        }
        state.page = 1;
        refresh(true);
    });

    // Clear-all chip
    document.addEventListener('click', e => {
        if (!e.target.closest('[data-drill-clear-all]')) return;
        e.preventDefault();
        state.status = '';
        state.age = null;
        state.page = 1;
        refresh(true);
    });

    // Per-page select + pagination links (delegated to the swappable region)
    region.addEventListener('change', e => {
        if (!e.target.matches('[name="per_page"]')) return;
        state.per_page = parseInt(e.target.value, 10) || DEFAULT_PER_PAGE;
        state.page = 1;
        refresh(true);
    });
    region.addEventListener('click', e => {
        const link = e.target.closest('[data-page]');
        if (!link || link.closest('.disabled')) return;
        e.preventDefault();
        const p = parseInt(link.dataset.page, 10);
        if (!p || p === state.page) return;
        state.page = p;
        refresh(true);
        region.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Browser back/forward
    window.addEventListener('popstate', e => {
        const s = e.state;
        if (!s) return;
        state.status = s.status || '';
        state.age = (s.age === undefined ? null : s.age);
        state.page = s.page || 1;
        state.per_page = s.per_page || DEFAULT_PER_PAGE;
        refresh(false);
    });

    // Seed initial history state so popstate has somewhere to return to
    history.replaceState({ ...state }, '', location.pathname + location.search);
    paintTiles();
})();
</script>
