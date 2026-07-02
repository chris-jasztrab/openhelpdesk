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
      <div class="input-group input-group-sm d-none" id="wbColumnsCtl" style="width:auto;">
        <label class="input-group-text" for="wbColumns" title="Number of columns"><i class="bi bi-layout-three-columns"></i></label>
        <select id="wbColumns" class="form-select form-select-sm" style="width:auto;" title="Number of columns">
          <option value="2">2 cols</option>
          <option value="3">3 cols</option>
          <option value="4">4 cols</option>
        </select>
      </div>
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
    <span class="small">Customising — drag a widget to move it between the independent columns; drag its bottom edge to resize its height. Set the number of columns at the top. Use <i class="bi bi-x-circle"></i> to remove one, <strong>Add widget</strong> to add more. Your layout saves automatically — click <strong>Done</strong> when finished.</span>
  </div>
  <div id="wbGrid"></div>
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

  /* Independent columns: the board is a row of vertical stacks. Each column
     flows on its own, so a tall widget in one column never pushes widgets in
     the next column down — the row breaks in one column don't cross to others.
     Collapses to a single stack on narrow screens. */
  #wbGrid { display: flex; align-items: flex-start; gap: 1rem; }
  .wb-column { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; gap: 1rem; }
  .wb-col { width: 100%; }
  @media (max-width: 767.98px) { #wbGrid { flex-direction: column; } }
  .wb-edit .wb-column { min-height: 90px; border-radius: 10px; outline: 2px dashed transparent; outline-offset: 4px; transition: outline-color .12s; }
  .wb-edit .wb-column:empty { outline-color: var(--bs-border-color); }

  /* Each widget's height is explicit (set per card from its saved/default
     height). The card is a flex column so its inner content (chart canvas,
     scrolling list) fills the height it's given, and resizing it resizes them. */
  .wb-card { height: 100%; position: relative; display: flex; flex-direction: column; }
  .wb-card .card-body { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
  .wb-card .card-header { flex: 0 0 auto; font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; padding-right: 1.75rem; }
  .wb-kpi-value { font-size: 2.4rem; font-weight: 700; line-height: 1.1; }
  .wb-kpi-sub { font-size: .8rem; }
  .wb-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
  .wb-chart-wrap { position: relative; flex: 1 1 auto; min-height: 0; }
  .wb-fs .wb-kpi-value { font-size: 3rem; }

  /* Click-to-drill affordance (view mode only — edit mode shows grab/jiggle). */
  #wallboard:not(.wb-edit) .wb-clickable .wb-card { cursor: pointer; transition: box-shadow .12s ease, transform .12s ease; }
  #wallboard:not(.wb-edit) .wb-clickable:hover .wb-card { box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important; transform: translateY(-2px); }
  #wallboard:not(.wb-edit) .wb-row-link { cursor: pointer; }
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

  /* Resize grip along the card's bottom edge — drag to set the card's height.
     Only shown (and only interactive) in edit mode. */
  .wb-resize {
    position: absolute; left: 0; right: 0; bottom: -4px; height: 16px; z-index: 6;
    display: none; align-items: flex-end; justify-content: center;
    cursor: ns-resize; touch-action: none;
  }
  .wb-resize::after {
    content: ''; width: 44px; height: 4px; border-radius: 4px;
    background: var(--ld-primary, #0d6efd); opacity: .45; transition: opacity .12s;
  }
  .wb-resize:hover::after { opacity: 1; }
  .wb-edit .wb-resize { display: flex; }

  /* In edit mode the whole card is the drag grip; inner links/charts go inert
     so a grab never navigates or interacts. */
  .wb-edit .wb-col { touch-action: none; }
  .wb-edit .wb-card { cursor: grab; user-select: none; }
  .wb-edit .wb-card a,
  .wb-edit .wb-card canvas,
  .wb-edit .wb-card .wb-list { pointer-events: none; }

  /* While actively resizing a card, freeze its jiggle and flag it. */
  .wb-col.wb-resizing .wb-card { animation: none !important; outline: 2px solid var(--ld-primary, #0d6efd); outline-offset: 1px; }
  body.wb-ns-resize, body.wb-ns-resize * { cursor: ns-resize !important; }

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

<script src="/assets/vendor/chartjs/chart.umd.min.js"></script>
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

  // Per-widget height. PHP encodes an empty map as [] (a JS array), on which
  // string-keyed writes vanish through JSON.stringify — normalise to an object.
  if (config.heights === null || typeof config.heights !== 'object' || Array.isArray(config.heights)) {
    config.heights = {};
  }
  const DEFAULT_H = { kpi: 120, chart: 300, list: 260 };
  const MIN_H = 90, MAX_H = 900;
  const MIN_COLS = 2, MAX_COLS = 4;
  const widgetHeight = (id) => {
    const h = config.heights[id];
    return (typeof h === 'number' && h > 0) ? h : (DEFAULT_H[(CATALOG[id] || {}).kind] || 220);
  };

  // Independent-column layout. The server normalises columns to match the
  // enabled widgets, but guard here too (legacy configs, hand-edited data).
  const clampCols = (n) => Math.max(MIN_COLS, Math.min(MAX_COLS, n | 0));
  function normalizeLayout() {
    config.columnCount = clampCols(config.columnCount || 2);
    const enabled = (config.widgets || []).filter(id => CATALOG[id]);
    let cols = Array.isArray(config.columns) ? config.columns.map(c => Array.isArray(c) ? c.slice() : []) : [];
    // Trim/grow to columnCount (overflow merges into the last column).
    while (cols.length > config.columnCount) cols[config.columnCount - 1].push(...cols.pop());
    while (cols.length < config.columnCount) cols.push([]);
    // Keep only enabled, de-duplicated ids.
    const seen = new Set();
    cols = cols.map(c => c.filter(id => CATALOG[id] && enabled.includes(id) && !seen.has(id) && seen.add(id)));
    // Append any enabled widget not yet placed to the shortest column.
    enabled.forEach(id => {
      if (seen.has(id)) return;
      seen.add(id);
      shortestColumn(cols).push(id);
    });
    config.columns = cols;
  }
  const shortestColumn = (cols) => cols.reduce((a, c) => c.length < a.length ? c : a, cols[0]);
  // Keep config.widgets in sync as the flat enabled set (used by the poll/modal).
  const syncWidgets = () => { config.widgets = config.columns.flat(); };

  /* ---- drill-down links ------------------------------------------------
     Clicking a widget opens the agent ticket list filtered to the tickets it
     represents, carrying the wallboard's own filters. Built lazily at click
     time so they always reflect the current filter bar. */
  const OPEN_STATUSES = <?= json_encode($openStatuses ?? [], JSON_UNESCAPED_SLASHES) ?>;
  const openPairs   = () => OPEN_STATUSES.map(s => ['status[]', s]);
  function wallPairs() {
    const f = config.filters || {}, p = [];
    if (+f.location_id) p.push(['location[]', f.location_id]);
    if (+f.group_id)    p.push(['group[]', f.group_id]);
    if (+f.type_id)     p.push(['type[]', f.type_id]);
    if (+f.priority_id) p.push(['priority[]', f.priority_id]);
    return p;
  }
  function ticketsUrl(pairs) {
    const u = new URLSearchParams();
    pairs.forEach(([k, v]) => u.append(k, v));
    const s = u.toString();
    return '/agent/tickets' + (s ? '?' + s : '');
  }
  const rangeDays = () => (config.filters && +config.filters.range) || 30;

  // The whole-widget link (a KPI card, or a chart card clicked off its bars).
  function drillUrl(id) {
    const w = wallPairs();
    switch (id) {
      case 'open':           return ticketsUrl([...openPairs(), ...w]);
      case 'unassigned':     return ticketsUrl([...openPairs(), ['agent[]', 'unassigned'], ...w]);
      case 'breached':       return ticketsUrl([...openPairs(), ['sla', 'breached'], ...w]);
      case 'at_risk':        return ticketsUrl([...openPairs(), ['sla', 'warning'], ...w]);
      case 'due_today':      return ticketsUrl([...openPairs(), ['due_today', '1'], ...w]);
      case 'created_today':  return ticketsUrl([['created_today', '1'], ...w]);
      case 'resolved_today': return ticketsUrl([['resolved_today', '1'], ...w]);
      case 'avg_response':
      case 'csat':
      case 'sla_compliance': return ticketsUrl([['created_within', rangeDays()], ...w]);
      case 'by_status': case 'by_priority': case 'by_type':
      case 'by_group':  case 'by_location':  return ticketsUrl([...openPairs(), ...w]);
      case 'volume_trend':   return ticketsUrl([['created_within', rangeDays()], ...w]);
      default: return null;
    }
  }

  // A single chart segment → that slice's tickets.
  function segmentUrl(id, gid) {
    if (gid === null || gid === undefined || gid === '') return drillUrl(id);
    const w = wallPairs();
    switch (id) {
      case 'by_status':   return ticketsUrl([['status[]', gid], ...w]);   // gid is the status slug
      case 'by_priority': return ticketsUrl([...openPairs(), ['priority[]', gid], ...w]);
      case 'by_type':     return ticketsUrl([...openPairs(), ['type[]', gid], ...w]);
      case 'by_group':    return ticketsUrl([...openPairs(), ['group[]', gid], ...w]);
      case 'by_location': return ticketsUrl([...openPairs(), ['location[]', gid], ...w]);
      default: return drillUrl(id);
    }
  }

  /* ---- helpers ---------------------------------------------------- */
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

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
  function buildCard(id) {
    const meta = CATALOG[id];
    const col = document.createElement('div');
    col.className = 'wb-col' + (meta.kind === 'list' ? '' : ' wb-clickable');
    col.style.height = widgetHeight(id) + 'px';
    col.dataset.widget = id;
    // Remove badge + bottom resize grip — only visible in edit mode (CSS-gated).
    const remove = '<button type="button" class="wb-remove" title="Remove widget" aria-label="Remove widget"><i class="bi bi-x-lg"></i></button>';
    const resize = '<div class="wb-resize" title="Drag to resize" aria-label="Drag to resize widget"></div>';
    if (meta.kind === 'kpi') {
      col.innerHTML =
        '<div class="card wb-card border-0 shadow-sm">' + remove +
          '<div class="card-body">' +
            '<div class="text-muted wb-kpi-sub mb-1">' + esc(meta.title) + '</div>' +
            '<div class="wb-kpi-value" data-slot="value">–</div>' +
            '<div class="text-muted wb-kpi-sub" data-slot="sub"></div>' +
          '</div>' + resize +
        '</div>';
    } else if (meta.kind === 'chart') {
      col.innerHTML =
        '<div class="card wb-card border-0 shadow-sm">' + remove +
          '<div class="card-header bg-transparent text-muted">' + esc(meta.title) + '</div>' +
          '<div class="card-body"><div class="wb-chart-wrap"><canvas></canvas></div>' +
            '<div class="text-muted small text-center mt-2 d-none" data-slot="empty">No data</div></div>' + resize +
        '</div>';
    } else { // list
      col.innerHTML =
        '<div class="card wb-card border-0 shadow-sm">' + remove +
          '<div class="card-header bg-transparent text-muted">' + esc(meta.title) + '</div>' +
          '<div class="card-body p-0"><div class="wb-list" data-slot="list">' +
            '<div class="text-muted small p-3">Loading…</div></div></div>' + resize +
        '</div>';
    }
    return col;
  }

  function buildGrid() {
    Object.values(charts).forEach(c => c.destroy());
    for (const k in charts) delete charts[k];
    normalizeLayout();
    grid.innerHTML = '';

    const total = config.columns.flat().filter(id => CATALOG[id]).length;
    empty.classList.toggle('d-none', total > 0);

    config.columns.forEach((ids, ci) => {
      const colEl = document.createElement('div');
      colEl.className = 'wb-column';
      colEl.dataset.col = ci;
      ids.forEach(id => { if (CATALOG[id]) colEl.appendChild(buildCard(id)); });
      grid.appendChild(colEl);
    });
  }

  /* ---- edit mode + pointer-drag reordering (phone home-screen style) -----
     In edit mode every widget jiggles and can be grabbed anywhere. Dragging
     uses Pointer Events (mouse + touch + pen) so we control the motion: the
     dragged card floats under the cursor while the others reflow around the
     gap and animate into place with a FLIP transition. New order persists. */
  let editMode = false;
  let drag     = null;   // { el, offX, offY, pointerId, lastX, lastY, scrollDir }
  let resizing = null;   // { el, id, startY, startH, curH, pointerId }
  let scrollRAF = null;

  // Read the live column layout back out of the DOM and persist it.
  function persistLayout() {
    config.columns = [...grid.querySelectorAll('.wb-column')].map(colEl =>
      [...colEl.querySelectorAll('.wb-col')].map(c => c.dataset.widget));
    syncWidgets();
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
  let slotCache = [];   // non-dragged widget boxes (doc coords)
  let colCache  = [];   // column-container boxes (doc coords)

  function boxOf(el) {
    const r = el.getBoundingClientRect();
    return { el, left: r.left + window.scrollX, right: r.right + window.scrollX,
                 top:  r.top  + window.scrollY, bottom: r.bottom + window.scrollY };
  }
  function rebuildColCache() {
    colCache = [...grid.querySelectorAll('.wb-column')].map(boxOf);
  }
  function rebuildSlotCache() {
    slotCache = [...grid.querySelectorAll('.wb-col:not(.wb-dragging)')].map(boxOf);
    rebuildColCache();
  }

  // The non-dragged card whose box contains the pointer, or null.
  function slotUnder(docX, docY) {
    for (const s of slotCache) {
      if (docX >= s.left && docX <= s.right && docY >= s.top && docY <= s.bottom) return s.el;
    }
    return null;
  }
  // The column container under the pointer's X (nearest by centre if between).
  function columnUnderX(docX) {
    let best = null, bestDist = Infinity;
    for (const c of colCache) {
      if (docX >= c.left && docX <= c.right) return c.el;
      const cx = (c.left + c.right) / 2, d = Math.abs(docX - cx);
      if (d < bestDist) { bestDist = d; best = c.el; }
    }
    return best;
  }
  const cachedBox = (el) => slotCache.find(s => s.el === el);

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
    rebuildColCache();   // column boxes can change height when a card moves in/out
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

  // Move the dragged card into a (column, position) computed from the pointer
  // against the settled-layout cache. Within a column we "hop past" the card
  // the pointer is inside; entering another column we insert before/after by
  // its midpoint; below a column's last card we append. When the pointer is
  // over the dragged card's own footprint (or a gutter) nothing moves — that
  // dead zone is what stops a held, shaking hand from oscillating.
  function reorderTo(x, y) {
    const docX = x + window.scrollX, docY = y + window.scrollY;

    // The dragged card is excluded from the cache, so when the pointer is over
    // its own slot slotUnder() returns null and nothing moves — that's the
    // dead zone that prevents oscillation. (Checking the dragged element's own
    // live box would be wrong: it floats under the cursor, so the pointer is
    // always "inside" it.)
    let targetCol, ref;
    const over = slotUnder(docX, docY);
    if (over) {
      targetCol = over.parentElement;
      if (over.parentElement === drag.el.parentElement) {
        // same column: hop just past the hovered card
        const kids = [...targetCol.children];
        ref = kids.indexOf(over) < kids.indexOf(drag.el) ? over : over.nextElementSibling;
      } else {
        // entering another column: before/after the hovered card by its midpoint
        const b = cachedBox(over) || boxOf(over);
        ref = docY < (b.top + b.bottom) / 2 ? over : over.nextElementSibling;
      }
    } else {
      // not over any card — decide by column, then top / bottom / ignore
      targetCol = columnUnderX(docX);
      if (!targetCol) return;
      const cards = [...targetCol.querySelectorAll('.wb-col')].filter(c => c !== drag.el);
      if (!cards.length) { ref = null; }                                   // empty column → place
      else {
        const firstTop = (cachedBox(cards[0]) || boxOf(cards[0])).top;
        const lastBot  = (cachedBox(cards[cards.length - 1]) || boxOf(cards[cards.length - 1])).bottom;
        if (docY <= firstTop)      ref = cards[0];                          // above the column → top
        else if (docY >= lastBot)  ref = null;                             // below the column → append
        else return;                                                       // in a gutter → ignore
      }
    }

    // No-op guards (already in that exact spot).
    if (ref === drag.el) return;
    if (ref && ref.parentElement === targetCol && drag.el.parentElement === targetCol && drag.el.nextElementSibling === ref) return;
    if (ref === null && targetCol.lastElementChild === drag.el) return;

    finalizeAnims();                  // clear transforms so boxes read true
    const prev = new Map();           // FLIP "first" (resting boxes)
    grid.querySelectorAll('.wb-col').forEach(el => { if (el !== drag.el) prev.set(el, el.getBoundingClientRect()); });
    if (ref) targetCol.insertBefore(drag.el, ref);
    else targetCol.appendChild(drag.el);
    flipPlayAndCache(prev);           // animate + refresh the caches
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

  /* ---- height resizing (drag the bottom grip) --------------------------- */
  function startResize(e, handle) {
    const el = handle.closest('.wb-col');
    if (!el) return;
    e.preventDefault();
    resizing = { el, id: el.dataset.widget, startY: e.clientY,
                 startH: el.getBoundingClientRect().height, curH: 0, pointerId: e.pointerId, handle };
    el.classList.add('wb-resizing');
    document.body.classList.add('wb-ns-resize');
    try { handle.setPointerCapture(e.pointerId); } catch (_) {}
  }
  function doResize(e) {
    let h = Math.round(resizing.startH + (e.clientY - resizing.startY));
    h = Math.max(MIN_H, Math.min(MAX_H, h));
    resizing.curH = h;
    resizing.el.style.height = h + 'px';
    const ch = charts[resizing.id];
    if (ch) ch.resize();              // keep the chart canvas filling its card live
  }
  function endResize(e) {
    const { el, id, handle } = resizing;
    try { handle.releasePointerCapture(e.pointerId); } catch (_) {}
    el.classList.remove('wb-resizing');
    document.body.classList.remove('wb-ns-resize');
    const h = resizing.curH || Math.round(el.getBoundingClientRect().height);
    config.heights[id] = h;
    resizing = null;
    if (charts[id]) charts[id].resize();
    saveConfig();
  }

  function onPointerDown(e) {
    if (!editMode || drag || resizing) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    if (e.target.closest('.wb-remove')) return;        // let the remove click through
    const grip = e.target.closest('.wb-resize');
    if (grip) { startResize(e, grip); return; }        // resize takes priority over drag
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
    if (resizing && e.pointerId === resizing.pointerId) { doResize(e); return; }
    if (!drag || e.pointerId !== drag.pointerId) return;
    positionDragged(e.clientX, e.clientY);
    reorderTo(e.clientX, e.clientY);
    edgeAutoScroll(e.clientY);
  }
  function endDrag(e) {
    if (resizing && e.pointerId === resizing.pointerId) { endResize(e); return; }
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
    persistLayout();
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
      persistLayout();   // re-reads columns from the DOM (the card is now gone)
      empty.classList.toggle('d-none', config.widgets.filter(x => CATALOG[x]).length > 0);
    });

    // View-mode navigation: a click drills into the tickets the widget shows.
    grid.addEventListener('click', (e) => {
      if (editMode) return;                              // edit mode owns clicks
      if (e.target.closest('a')) return;                 // list rows link to a ticket
      const agentRow = e.target.closest('tr[data-agent]');
      if (agentRow) { window.location.href = ticketsUrl([...openPairs(), ['agent[]', agentRow.dataset.agent], ...wallPairs()]); return; }
      if (e.target.closest('canvas')) return;            // charts handle their own clicks (segments)
      const col = e.target.closest('.wb-col.wb-clickable');
      if (!col) return;
      const href = drillUrl(col.dataset.widget);
      if (href) window.location.href = href;
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
    document.getElementById('wbColumnsCtl').classList.toggle('d-none', !on);
    if (!on) persistLayout();
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
      const ser = d.series || { labels: [], colors: [], data: [], ids: [] };
      labels = ser.labels || [];
      datasets = [{ data: ser.data || [], backgroundColor: ser.colors || [], borderWidth: 0 }];
    }
    // Segment ids (status slug / priority id / …) for per-slice drill-down.
    const segIds = (id !== 'volume_trend' && d.series && d.series.ids) ? d.series.ids : null;

    const total = datasets.reduce((a, ds) => a + (ds.data || []).reduce((x, y) => x + y, 0), 0);
    if (emptySlot) emptySlot.classList.toggle('d-none', total > 0);

    const isPie = meta.chart === 'doughnut' || meta.chart === 'pie';
    if (charts[id]) {
      charts[id].$ids = segIds;
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
        onClick: (evt, els, chart) => {
          if (editMode) return;                          // edit mode: dragging, not drilling
          const wid = chart.$wid;
          const href = (els && els.length && chart.$ids)
            ? segmentUrl(wid, chart.$ids[els[0].index])
            : drillUrl(wid);
          if (href) window.location.href = href;
        },
        plugins: { legend: { display: isPie || id === 'volume_trend', position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: isPie ? {} : {
          x: { ticks: { font: { size: 10 } }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
        },
      },
    });
    charts[id].$wid = id;
    charts[id].$ids = segIds;
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
        html += '<tr class="wb-row-link" data-agent="' + esc(r.id) + '">' +
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
    // Reconcile the column layout with the new enabled set: drop unchecked
    // widgets, keep the rest in place, add newly-checked ones to the shortest
    // column. Existing positions and per-column order are preserved.
    const chosenSet = new Set(chosen);
    config.columns = (config.columns || []).map(col => col.filter(id => chosenSet.has(id)));
    const present = new Set(config.columns.flat());
    chosen.forEach(id => { if (!present.has(id)) shortestColumn(config.columns).push(id); });
    syncWidgets();
    saveConfig();
    buildGrid();
    refresh();
  });

  document.getElementById('wbColumns').addEventListener('change', (e) => {
    config.columnCount = clampCols(parseInt(e.target.value, 10) || 2);
    normalizeLayout();   // merge overflow / pad, keeping placements
    syncWidgets();
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
  normalizeLayout();
  syncWidgets();
  document.getElementById('wbColumns').value = String(config.columnCount);
  attachGridDnD();
  buildGrid();
  refresh();
  startTimer();
})();
</script>
