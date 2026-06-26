<?php
/**
 * Live Wallboard / Dashboard.
 *
 * A real-time, per-user-customisable operations board. Data is fetched from
 * /api/dashboard/metrics on a poll (default 30s) and is already scoped to the
 * viewer's ticket visibility server-side. Widget choice, order, filters and
 * refresh interval persist per user via /api/dashboard/config.
 *
 * Expects: $catalog, $config, $filterOptions  (set by the route handler).
 */
$rangeOptions = [7 => 'Last 7 days', 14 => 'Last 14 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year'];
$intervalOptions = [10 => '10s', 15 => '15s', 30 => '30s', 60 => '1m', 120 => '2m'];
?>
<div id="wallboard" data-csrf="<?= e(csrfToken()) ?>">

  <!-- Header / toolbar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
      <h1 class="h4 mb-0">Live Wallboard</h1>
      <span id="wbStatus" class="badge rounded-pill text-bg-light border" title="Auto-refresh status">
        <span id="wbDot" class="d-inline-block rounded-circle align-middle" style="width:8px;height:8px;background:#22c55e;"></span>
        <span id="wbUpdated" class="align-middle ms-1 text-muted">connecting…</span>
      </span>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="width:auto;">
        <label class="input-group-text" for="wbInterval"><i class="bi bi-arrow-repeat"></i></label>
        <select id="wbInterval" class="form-select form-select-sm" style="width:auto;" title="Refresh interval">
          <?php foreach ($intervalOptions as $sec => $lbl): ?>
            <option value="<?= $sec ?>" <?= (int)$config['interval'] === $sec ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="button" id="wbPause" class="btn btn-sm btn-outline-secondary" title="Pause auto-refresh">
        <i class="bi bi-pause-fill"></i>
      </button>
      <button type="button" id="wbRefresh" class="btn btn-sm btn-outline-secondary" title="Refresh now">
        <i class="bi bi-arrow-clockwise"></i>
      </button>
      <button type="button" id="wbFullscreen" class="btn btn-sm btn-outline-secondary" title="Fullscreen">
        <i class="bi bi-arrows-fullscreen"></i>
      </button>
      <button type="button" id="wbAddWidget" class="btn btn-sm btn-outline-primary d-none" data-bs-toggle="modal" data-bs-target="#wbCustomize">
        <i class="bi bi-plus-lg me-1"></i>Add widget
      </button>
      <button type="button" id="wbEditToggle" class="btn btn-sm btn-primary">
        <i class="bi bi-grid-1x2 me-1"></i>Customize
      </button>
    </div>
  </div>

  <!-- Filter bar -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
      <span class="text-muted small me-1"><i class="bi bi-funnel"></i> Filters</span>
      <select class="form-select form-select-sm wb-filter" data-filter="location_id" style="width:auto;">
        <option value="0">All locations</option>
        <?php foreach ($filterOptions['locations'] as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (int)$config['filters']['location_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm wb-filter" data-filter="group_id" style="width:auto;">
        <option value="0">All groups</option>
        <?php foreach ($filterOptions['groups'] as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (int)$config['filters']['group_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm wb-filter" data-filter="type_id" style="width:auto;">
        <option value="0">All types</option>
        <?php foreach ($filterOptions['types'] as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (int)$config['filters']['type_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select form-select-sm wb-filter" data-filter="priority_id" style="width:auto;">
        <option value="0">All priorities</option>
        <?php foreach ($filterOptions['priorities'] as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (int)$config['filters']['priority_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="vr d-none d-md-inline"></span>
      <select class="form-select form-select-sm wb-filter" data-filter="range" style="width:auto;" title="Time range for trend / CSAT / response widgets">
        <?php foreach ($rangeOptions as $d => $lbl): ?>
          <option value="<?= $d ?>" <?= (int)$config['filters']['range'] === $d ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" id="wbClear" class="btn btn-sm btn-link text-decoration-none">Clear</button>
    </div>
  </div>

  <!-- Widget grid -->
  <div class="alert alert-primary d-flex align-items-center gap-2 py-2 px-3 mb-3 d-none" id="wbDragHint">
    <i class="bi bi-arrows-move"></i>
    <span class="small">Customising — drag any widget to move it; the others slide out of the way. Use <i class="bi bi-x-circle"></i> to remove one, <strong>Add widget</strong> to add more. Your layout saves automatically — click <strong>Done</strong> when finished.</span>
  </div>
  <div id="wbGrid" class="row g-3"></div>
  <div id="wbEmpty" class="text-center text-muted py-5 d-none">
    <i class="bi bi-grid-3x3-gap fs-1 d-block mb-2"></i>
    No widgets selected. Click <strong>Customize</strong> to add some.
  </div>
</div>

<!-- Customize modal -->
<div class="modal fade" id="wbCustomize" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-grid-1x2 me-2"></i>Add or remove widgets</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Switch widgets on or off. Then drag them around the board to arrange them — just close this and grab any widget.</p>
        <ul id="wbWidgetList" class="list-group"></ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="wbSaveConfig" class="btn btn-primary" data-bs-dismiss="modal">Apply</button>
      </div>
    </div>
  </div>
</div>

<style>
  #wallboard.wb-fs { background: var(--bs-body-bg); padding: 1rem; overflow-y: auto; }
  .wb-card { height: 100%; position: relative; }
  .wb-kpi-value { font-size: 2.4rem; font-weight: 700; line-height: 1.1; }
  .wb-kpi-sub { font-size: .8rem; }
  .wb-card .card-header { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; padding-right: 1.75rem; }
  .wb-list { max-height: 320px; overflow-y: auto; }
  .wb-chart-wrap { position: relative; height: 240px; }
  .wb-fs .wb-kpi-value { font-size: 3rem; }
  #wbWidgetList li.dragging { opacity: .5; }

  /* --- Customise (edit) mode: rearrange widgets like phone home-screen icons ---
     Pointer-driven drag with FLIP animation handled in JS; CSS provides the
     "edit mode" affordances: a gentle jiggle, a remove badge, and lift-on-drag. */
  .wb-remove {
    position: absolute; top: -9px; left: -9px; z-index: 6;
    width: 24px; height: 24px; padding: 0; border: 2px solid var(--bs-body-bg);
    border-radius: 50%; background: var(--bs-danger); color: #fff;
    display: none; align-items: center; justify-content: center;
    font-size: .8rem; line-height: 1; cursor: pointer;
    box-shadow: 0 1px 4px rgba(0,0,0,.3);
  }
  .wb-remove:hover { filter: brightness(1.1); }
  .wb-edit .wb-remove { display: flex; }

  /* In edit mode, stop cards stretching to their row's tallest sibling.
     With the default align-items:stretch, dragging a short widget into a row
     with a tall one balloons the short card to match — which reflows the
     board under the cursor and makes the drag oscillate. Top-aligning in edit
     mode keeps every card at its own content height, so a reorder only moves
     cards, never resizes them. */
  .wb-edit #wbGrid { align-items: flex-start; }
  .wb-edit #wbGrid .wb-card { height: auto; }

  /* In edit mode the whole card is the drag grip; inner links/charts go inert
     so a grab never navigates or interacts. */
  .wb-edit .wb-col { touch-action: none; }
  .wb-edit .wb-card { cursor: grab; user-select: none; }
  .wb-edit .wb-card a,
  .wb-edit .wb-card canvas,
  .wb-edit .wb-card .wb-list { pointer-events: none; }

  @keyframes wbJiggle {
    0%, 100% { transform: rotate(-.55deg); }
    50%      { transform: rotate(.55deg); }
  }
  /* Jiggle the inner card so it never fights the FLIP transform on .wb-col. */
  .wb-edit .wb-col:not(.wb-dragging) .wb-card { animation: wbJiggle .35s ease-in-out infinite; }
  .wb-edit .wb-col:nth-child(even):not(.wb-dragging) .wb-card { animation-delay: -.18s; }

  .wb-col.wb-dragging { position: relative; z-index: 1000; }
  .wb-col.wb-dragging .wb-card {
    cursor: grabbing; animation: none;
    box-shadow: 0 14px 34px rgba(0,0,0,.28);
    outline: 2px solid var(--ld-primary, #0d6efd); outline-offset: 1px;
  }
  body.wb-grabbing, body.wb-grabbing * { cursor: grabbing !important; }
  @media (prefers-reduced-motion: reduce) {
    .wb-edit .wb-col .wb-card { animation: none !important; }
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';
  const root    = document.getElementById('wallboard');
  const CSRF    = root.dataset.csrf;
  const CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_SLASHES) ?>;
  let   config  = <?= json_encode($config, JSON_UNESCAPED_SLASHES) ?>;

  const grid    = document.getElementById('wbGrid');
  const empty   = document.getElementById('wbEmpty');
  const charts  = {};            // widgetId -> Chart instance
  let   timer   = null;
  let   paused  = false;
  let   inFlight = false;

  /* ---- helpers ---------------------------------------------------- */
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const sizeClass = (n) => ({
    3: 'col-12 col-sm-6 col-xl-3',
    4: 'col-12 col-md-6 col-xl-4',
    6: 'col-12 col-lg-6',
    8: 'col-12 col-xl-8',
    12: 'col-12',
  }[n] || 'col-12 col-md-6 col-xl-4');

  const toneColor = (tone) => ({
    danger: 'var(--bs-danger)', warning: 'var(--bs-warning-text-emphasis, #997404)',
    success: 'var(--bs-success)',
  }[tone] || 'var(--ld-primary, #0d6efd)');

  function timeAgo(iso) {
    const t = new Date(iso).getTime();
    if (isNaN(t)) return '';
    const s = Math.max(0, Math.round((Date.now() - t) / 1000));
    if (s < 60) return s + 's ago';
    const m = Math.round(s / 60);
    if (m < 60) return m + 'm ago';
    const h = Math.round(m / 60);
    if (h < 24) return h + 'h ago';
    return Math.round(h / 24) + 'd ago';
  }

  /* ---- grid construction ----------------------------------------- */
  function buildGrid() {
    Object.values(charts).forEach(c => c.destroy());
    for (const k in charts) delete charts[k];
    grid.innerHTML = '';

    const widgets = (config.widgets || []).filter(id => CATALOG[id]);
    empty.classList.toggle('d-none', widgets.length > 0);

    widgets.forEach(id => {
      const meta = CATALOG[id];
      const col = document.createElement('div');
      col.className = sizeClass(meta.size) + ' wb-col';
      // Remove badge — only visible in edit mode (CSS-gated by .wb-edit).
      const remove = '<button type="button" class="wb-remove" title="Remove widget" aria-label="Remove widget"><i class="bi bi-x-lg"></i></button>';
      let inner = '';
      if (meta.kind === 'kpi') {
        inner =
          '<div class="card wb-card border-0 shadow-sm">' + remove +
            '<div class="card-body">' +
              '<div class="text-muted wb-kpi-sub mb-1">' + esc(meta.title) + '</div>' +
              '<div class="wb-kpi-value" data-slot="value">–</div>' +
              '<div class="text-muted wb-kpi-sub" data-slot="sub"></div>' +
            '</div>' +
          '</div>';
      } else if (meta.kind === 'chart') {
        inner =
          '<div class="card wb-card border-0 shadow-sm">' + remove +
            '<div class="card-header bg-transparent text-muted">' + esc(meta.title) + '</div>' +
            '<div class="card-body"><div class="wb-chart-wrap"><canvas></canvas></div>' +
              '<div class="text-muted small text-center mt-2 d-none" data-slot="empty">No data</div></div>' +
          '</div>';
      } else { // list
        inner =
          '<div class="card wb-card border-0 shadow-sm">' + remove +
            '<div class="card-header bg-transparent text-muted">' + esc(meta.title) + '</div>' +
            '<div class="card-body p-0"><div class="wb-list" data-slot="list">' +
              '<div class="text-muted small p-3">Loading…</div></div></div>' +
          '</div>';
      }
      col.innerHTML = inner;
      col.dataset.widget = id;
      grid.appendChild(col);
    });
  }

  /* ---- edit mode + pointer-drag reordering (phone home-screen style) -----
     In edit mode every widget jiggles and can be grabbed anywhere. Dragging
     uses Pointer Events (mouse + touch + pen) so we control the motion: the
     dragged card floats under the cursor while the others reflow around the
     gap and animate into place with a FLIP transition. New order persists. */
  let editMode = false;
  let drag     = null;   // { el, offX, offY, pointerId, lastX, lastY, scrollDir }
  let scrollRAF = null;

  function persistOrder() {
    config.widgets = [...grid.querySelectorAll('.wb-col')].map(c => c.dataset.widget);
    saveConfig();
  }

  /* Reordering is decided against a CACHED snapshot of the settled layout —
     never against live rects. Two things would otherwise make the board twitch:
       1. While cards FLIP-animate they carry a transform, so a live
          getBoundingClientRect() reports a mid-flight position. The cache holds
          resting boxes (document coords, scroll-invariant), rebuilt only when a
          move commits, so geometry is stable between commits.
       2. A "which side of the centre line" test flips state on sub-pixel hand
          jitter. Instead we only move the dragged card when the pointer is
          actually INSIDE another card's box, hopping it just past that card.
          After each hop the pointer sits over the dragged card's own footprint
          (which is excluded), so there's a natural dead zone and a held,
          shaking hand can't oscillate. */
  let slotCache = [];

  function boxOf(el) {
    const r = el.getBoundingClientRect();
    return { el, left: r.left + window.scrollX, right: r.right + window.scrollX,
                 top:  r.top  + window.scrollY, bottom: r.bottom + window.scrollY };
  }
  function rebuildSlotCache() {
    slotCache = [...grid.querySelectorAll('.wb-col:not(.wb-dragging)')].map(boxOf);
  }

  // The non-dragged card whose box contains the pointer, or null (over the
  // dragged card's own slot, or in a gutter — either way, don't move).
  function slotUnder(docX, docY) {
    for (const s of slotCache) {
      if (docX >= s.left && docX <= s.right && docY >= s.top && docY <= s.bottom) return s.el;
    }
    return null;
  }

  // Snap any in-flight FLIP animation to its resting position so the next
  // measurement reads true layout, not an animated frame.
  function finalizeAnims() {
    grid.querySelectorAll('.wb-col').forEach(el => {
      if (el === drag.el) return;
      el.style.transition = 'none';
      el.style.transform  = '';
    });
    grid.getBoundingClientRect(); // force the snap to apply before we measure
  }

  // FLIP "last + play": measure each card's new resting box (also refreshing
  // the slot cache from those same reads), then animate from old box to new.
  function flipPlayAndCache(prev) {
    slotCache = [];
    grid.querySelectorAll('.wb-col').forEach(el => {
      if (el === drag.el) return;               // dragged card tracks the pointer
      const r = el.getBoundingClientRect();     // resting layout (transforms already cleared)
      slotCache.push({ el, left: r.left + window.scrollX, right: r.right + window.scrollX,
                           top: r.top + window.scrollY, bottom: r.bottom + window.scrollY });
      const p = prev.get(el); if (!p) return;
      const dx = p.left - r.left, dy = p.top - r.top;
      if (!dx && !dy) return;
      el.style.transition = 'none';
      el.style.transform  = 'translate(' + dx + 'px,' + dy + 'px)';
      el.getBoundingClientRect();               // force reflow so the next frame animates
      requestAnimationFrame(() => {
        el.style.transition = 'transform .2s cubic-bezier(.2,.7,.3,1)';
        el.style.transform  = '';
      });
    });
  }

  // Keep the dragged card's top-left pinned under the cursor regardless of its
  // current position in the flow (recomputed from the untransformed box).
  function positionDragged(x, y) {
    const el = drag.el;
    el.style.transition = 'none';
    el.style.transform  = 'none';
    const r = el.getBoundingClientRect();
    const tx = (x - drag.offX) - r.left;
    const ty = (y - drag.offY) - r.top;
    el.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(1.03)';
    drag.lastX = x; drag.lastY = y;
  }

  function reorderTo(x, y) {
    const over = slotUnder(x + window.scrollX, y + window.scrollY);
    if (!over) return;                                   // dead zone — no move
    const kids = [...grid.children];
    const di = kids.indexOf(drag.el), oi = kids.indexOf(over);
    if (di < 0 || oi < 0) return;
    // Hop the dragged card just past the card the pointer is inside.
    const ref = oi < di ? over : over.nextElementSibling;
    if (ref === drag.el) return;
    if (ref && drag.el.nextElementSibling === ref) return;
    if (ref === null && grid.lastElementChild === drag.el) return;
    finalizeAnims();                  // clear transforms so boxes read true
    const prev = new Map();           // FLIP "first" (resting boxes)
    grid.querySelectorAll('.wb-col').forEach(el => { if (el !== drag.el) prev.set(el, el.getBoundingClientRect()); });
    if (ref === null) grid.appendChild(drag.el);
    else grid.insertBefore(drag.el, ref);
    flipPlayAndCache(prev);           // animate + refresh the slot cache
    positionDragged(x, y);            // re-pin: the dragged card's base position just changed
  }

  // Auto-scroll when dragging near the top/bottom edge (boards can be tall).
  function edgeAutoScroll(y) {
    const margin = 64;
    drag.scrollDir = y < margin ? -1 : (y > window.innerHeight - margin ? 1 : 0);
    if (!drag.scrollDir || scrollRAF) return;
    const scroller = root.classList.contains('wb-fs') ? root : document.scrollingElement;
    const step = () => {
      if (!drag || !drag.scrollDir) { scrollRAF = null; return; }
      scroller.scrollTop += drag.scrollDir * 12;
      positionDragged(drag.lastX, drag.lastY);
      reorderTo(drag.lastX, drag.lastY);
      scrollRAF = requestAnimationFrame(step);
    };
    scrollRAF = requestAnimationFrame(step);
  }

  function onPointerDown(e) {
    if (!editMode || drag) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    if (e.target.closest('.wb-remove')) return;        // let the remove click through
    const el = e.target.closest('.wb-col');
    if (!el) return;
    e.preventDefault();
    const r = el.getBoundingClientRect();
    drag = { el, offX: e.clientX - r.left, offY: e.clientY - r.top, pointerId: e.pointerId, lastX: e.clientX, lastY: e.clientY, scrollDir: 0 };
    el.classList.add('wb-dragging');
    document.body.classList.add('wb-grabbing');
    try { el.setPointerCapture(e.pointerId); } catch (_) {}
    rebuildSlotCache();              // snapshot the settled layout before lifting the card
    positionDragged(e.clientX, e.clientY);
  }
  function onPointerMove(e) {
    if (!drag || e.pointerId !== drag.pointerId) return;
    positionDragged(e.clientX, e.clientY);
    reorderTo(e.clientX, e.clientY);
    edgeAutoScroll(e.clientY);
  }
  function endDrag(e) {
    if (!drag || e.pointerId !== drag.pointerId) return;
    const el = drag.el;
    try { el.releasePointerCapture(e.pointerId); } catch (_) {}
    el.classList.remove('wb-dragging');
    el.style.transition = 'transform .18s cubic-bezier(.2,.7,.3,1)';
    el.style.transform  = 'none';                       // settle into the open slot
    const clear = () => { el.style.transition = ''; el.style.transform = ''; el.removeEventListener('transitionend', clear); };
    el.addEventListener('transitionend', clear);
    setTimeout(clear, 240);
    document.body.classList.remove('wb-grabbing');
    drag = null;
    if (scrollRAF) { cancelAnimationFrame(scrollRAF); scrollRAF = null; }
    persistOrder();
  }

  function attachGridDnD() {
    grid.addEventListener('pointerdown', onPointerDown);
    grid.addEventListener('pointermove', onPointerMove);
    grid.addEventListener('pointerup', endDrag);
    grid.addEventListener('pointercancel', endDrag);
    // Remove a widget (× badge) — animate the gap closing (standalone FLIP,
    // no active drag here so it can't lean on the drag-time cache).
    grid.addEventListener('click', (e) => {
      const btn = e.target.closest('.wb-remove');
      if (!btn || !editMode) return;
      const col = btn.closest('.wb-col');
      const id  = col.dataset.widget;
      if (charts[id]) { charts[id].destroy(); delete charts[id]; }
      const prev = new Map();
      grid.querySelectorAll('.wb-col').forEach(el => prev.set(el, el.getBoundingClientRect()));
      col.remove();
      grid.querySelectorAll('.wb-col').forEach(el => {
        const p = prev.get(el); if (!p) return;
        const n = el.getBoundingClientRect();
        const dx = p.left - n.left, dy = p.top - n.top;
        if (!dx && !dy) return;
        el.style.transition = 'none';
        el.style.transform  = 'translate(' + dx + 'px,' + dy + 'px)';
        el.getBoundingClientRect();
        requestAnimationFrame(() => {
          el.style.transition = 'transform .2s cubic-bezier(.2,.7,.3,1)';
          el.style.transform  = '';
        });
      });
      config.widgets = (config.widgets || []).filter(w => w !== id);
      empty.classList.toggle('d-none', config.widgets.filter(x => CATALOG[x]).length > 0);
      saveConfig();
    });
  }

  function setEditMode(on) {
    editMode = on;
    root.classList.toggle('wb-edit', on);
    document.getElementById('wbAddWidget').classList.toggle('d-none', !on);
    document.getElementById('wbDragHint').classList.toggle('d-none', !on);
    const btn = document.getElementById('wbEditToggle');
    btn.innerHTML = on ? '<i class="bi bi-check-lg me-1"></i>Done' : '<i class="bi bi-grid-1x2 me-1"></i>Customize';
    btn.classList.toggle('btn-primary', !on);
    btn.classList.toggle('btn-success', on);
    if (!on) persistOrder();
  }

  /* ---- renderers -------------------------------------------------- */
  function renderWidget(id, data) {
    const col = grid.querySelector('[data-widget="' + CSS.escape(id) + '"]');
    if (!col || !data) return;
    const meta = CATALOG[id];
    if (data.error) {
      const body = col.querySelector('.card-body');
      if (body) body.innerHTML = '<div class="text-danger small">Failed to load</div>';
      return;
    }
    if (meta.kind === 'kpi') return renderKpi(col, data);
    if (meta.kind === 'chart') return renderChart(id, col, meta, data);
    return renderList(id, col, data);
  }

  function renderKpi(col, d) {
    const v = col.querySelector('[data-slot="value"]');
    const s = col.querySelector('[data-slot="sub"]');
    v.textContent = (d.value === undefined || d.value === null) ? '–' : d.value;
    v.style.color = toneColor(d.tone);
    s.textContent = d.sub || '';
  }

  function renderChart(id, col, meta, d) {
    const canvas = col.querySelector('canvas');
    const emptySlot = col.querySelector('[data-slot="empty"]');
    let labels, datasets;

    if (id === 'volume_trend') {
      labels = (d.labels || []).map(x => x.slice(5)); // MM-DD
      datasets = [
        { label: 'Created', data: d.created || [], borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.1)', tension: .3, fill: true },
        { label: 'Resolved', data: d.resolved || [], borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,.1)', tension: .3, fill: true },
      ];
    } else {
      const ser = d.series || { labels: [], colors: [], data: [] };
      labels = ser.labels || [];
      datasets = [{ data: ser.data || [], backgroundColor: ser.colors || [], borderWidth: 0 }];
    }

    const total = datasets.reduce((a, ds) => a + (ds.data || []).reduce((x, y) => x + y, 0), 0);
    if (emptySlot) emptySlot.classList.toggle('d-none', total > 0);

    const isPie = meta.chart === 'doughnut' || meta.chart === 'pie';
    if (charts[id]) {
      charts[id].data.labels = labels;
      charts[id].data.datasets.forEach((ds, i) => {
        ds.data = datasets[i] ? datasets[i].data : [];
        if (datasets[i] && datasets[i].backgroundColor) ds.backgroundColor = datasets[i].backgroundColor;
      });
      charts[id].update('none');
      return;
    }
    charts[id] = new Chart(canvas.getContext('2d'), {
      type: meta.chart,
      data: { labels, datasets },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: isPie || id === 'volume_trend', position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: isPie ? {} : {
          x: { ticks: { font: { size: 10 } }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
        },
      },
    });
  }

  function renderList(id, col, d) {
    const slot = col.querySelector('[data-slot="list"]');
    const rows = d.rows || [];
    if (!rows.length) { slot.innerHTML = '<div class="text-muted small p-3">Nothing to show</div>'; return; }
    let html = '';

    if (id === 'agent_workload') {
      html = '<table class="table table-sm table-hover mb-0 align-middle"><tbody>';
      rows.forEach(r => {
        const breached = Number(r.breached) || 0;
        html += '<tr>' +
          '<td>' + esc(r.name) + '</td>' +
          '<td class="text-end"><span class="badge text-bg-secondary">' + (Number(r.open_total) || 0) + '</span></td>' +
          '<td class="text-end" style="width:70px;">' + (breached > 0 ? '<span class="badge text-bg-danger">' + breached + ' SLA</span>' : '') + '</td>' +
          '</tr>';
      });
      html += '</tbody></table>';
    } else if (id === 'unassigned_queue') {
      rows.forEach(r => {
        html += '<a href="/agent/tickets/' + encodeURIComponent(r.id) + '" class="d-flex align-items-center gap-2 px-3 py-2 border-bottom text-decoration-none text-body">' +
          '<span class="rounded-circle flex-shrink-0" style="width:9px;height:9px;background:' + esc(r.color) + ';"></span>' +
          '<span class="text-truncate flex-grow-1">#' + esc(r.id) + ' ' + esc(r.subject) + '</span>' +
          '<span class="text-muted small flex-shrink-0">' + timeAgo(r.created_at) + '</span>' +
          '</a>';
      });
    } else { // recent_activity
      rows.forEach(r => {
        html += '<a href="/agent/tickets/' + encodeURIComponent(r.id) + '" class="d-flex align-items-center gap-2 px-3 py-2 border-bottom text-decoration-none text-body">' +
          '<span class="badge flex-shrink-0" style="background:' + esc(r.color) + ';">' + esc(r.status) + '</span>' +
          '<span class="text-truncate flex-grow-1">#' + esc(r.id) + ' ' + esc(r.subject) + '</span>' +
          '<span class="text-muted small flex-shrink-0">' + timeAgo(r.updated_at) + '</span>' +
          '</a>';
      });
    }
    slot.innerHTML = html;
  }

  /* ---- polling ---------------------------------------------------- */
  function setStatus(text, ok) {
    document.getElementById('wbUpdated').textContent = text;
    document.getElementById('wbDot').style.background = ok ? '#22c55e' : '#ef4444';
  }

  function refresh() {
    if (inFlight) return;
    const ids = (config.widgets || []).filter(id => CATALOG[id]);
    if (!ids.length) { setStatus('no widgets', true); return; }
    inFlight = true;
    const f = config.filters || {};
    const qs = new URLSearchParams({
      widgets: ids.join(','),
      location_id: f.location_id || 0, group_id: f.group_id || 0,
      type_id: f.type_id || 0, priority_id: f.priority_id || 0, range: f.range || 30,
    });
    fetch('/api/dashboard/metrics?' + qs.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(res => {
        Object.keys(res.metrics || {}).forEach(id => renderWidget(id, res.metrics[id]));
        setStatus('updated ' + new Date().toLocaleTimeString(), true);
      })
      .catch(() => setStatus('refresh failed', false))
      .finally(() => { inFlight = false; });
  }

  function startTimer() {
    if (timer) clearInterval(timer);
    const ms = (Number(config.interval) || 30) * 1000;
    timer = setInterval(() => { if (!paused && !document.hidden) refresh(); }, ms);
  }

  /* ---- persistence ------------------------------------------------ */
  let saveT = null;
  function saveConfig() {
    clearTimeout(saveT);
    saveT = setTimeout(() => {
      fetch('/api/dashboard/config', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(config),
      }).then(r => r.ok ? r.json() : null).then(res => { if (res && res.config) config = res.config; });
    }, 400);
  }

  /* ---- customize modal -------------------------------------------- */
  function buildCustomizeList() {
    const list = document.getElementById('wbWidgetList');
    list.innerHTML = '';
    const enabled = config.widgets.filter(id => CATALOG[id]);
    const rest = Object.keys(CATALOG).filter(id => !enabled.includes(id));
    const ordered = enabled.concat(rest);

    ordered.forEach(id => {
      const meta = CATALOG[id];
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex align-items-center gap-2';
      li.dataset.widget = id;
      li.innerHTML =
        '<div class="form-check form-switch flex-grow-1 mb-0">' +
          '<input class="form-check-input" type="checkbox" id="wbw_' + id + '" ' + (enabled.includes(id) ? 'checked' : '') + '>' +
          '<label class="form-check-label" for="wbw_' + id + '">' + esc(meta.title) +
            ' <span class="badge text-bg-light border ms-1">' + esc(meta.group) + '</span></label>' +
        '</div>';
      list.appendChild(li);
    });
  }

  document.getElementById('wbCustomize').addEventListener('show.bs.modal', buildCustomizeList);

  document.getElementById('wbSaveConfig').addEventListener('click', () => {
    const list = document.getElementById('wbWidgetList');
    const chosen = [];
    list.querySelectorAll('li').forEach(li => {
      const cb = li.querySelector('input[type=checkbox]');
      if (cb && cb.checked) chosen.push(li.dataset.widget);
    });
    config.widgets = chosen;
    saveConfig();
    buildGrid();
    refresh();
  });

  /* ---- filter / toolbar wiring ------------------------------------ */
  root.querySelectorAll('.wb-filter').forEach(sel => {
    sel.addEventListener('change', () => {
      config.filters = config.filters || {};
      config.filters[sel.dataset.filter] = parseInt(sel.value, 10) || 0;
      saveConfig();
      refresh();
    });
  });

  document.getElementById('wbClear').addEventListener('click', () => {
    config.filters = { location_id: 0, group_id: 0, type_id: 0, priority_id: 0, range: 30 };
    root.querySelectorAll('.wb-filter').forEach(sel => {
      sel.value = sel.dataset.filter === 'range' ? '30' : '0';
    });
    saveConfig();
    refresh();
  });

  document.getElementById('wbInterval').addEventListener('change', (e) => {
    config.interval = parseInt(e.target.value, 10) || 30;
    saveConfig();
    startTimer();
  });

  document.getElementById('wbPause').addEventListener('click', (e) => {
    paused = !paused;
    e.currentTarget.innerHTML = paused ? '<i class="bi bi-play-fill"></i>' : '<i class="bi bi-pause-fill"></i>';
    e.currentTarget.classList.toggle('btn-outline-secondary', !paused);
    e.currentTarget.classList.toggle('btn-warning', paused);
    setStatus(paused ? 'paused' : 'resumed', !paused);
    if (!paused) refresh();
  });

  document.getElementById('wbRefresh').addEventListener('click', refresh);

  document.getElementById('wbEditToggle').addEventListener('click', () => setEditMode(!editMode));

  document.getElementById('wbFullscreen').addEventListener('click', () => {
    if (!document.fullscreenElement) {
      root.classList.add('wb-fs');
      root.requestFullscreen?.().catch(() => {});
    } else {
      document.exitFullscreen?.();
    }
  });
  document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement) root.classList.remove('wb-fs');
  });

  // Catch up immediately when the tab regains focus.
  document.addEventListener('visibilitychange', () => { if (!document.hidden && !paused) refresh(); });

  /* ---- boot ------------------------------------------------------- */
  attachGridDnD();
  buildGrid();
  refresh();
  startTimer();
})();
</script>
