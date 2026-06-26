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
      <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#wbCustomize">
        <i class="bi bi-sliders me-1"></i>Customize
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
        <h5 class="modal-title"><i class="bi bi-sliders me-2"></i>Customize wallboard</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Choose which widgets to show. Drag the <i class="bi bi-grip-vertical"></i> handle to reorder; enabled widgets appear in this order.</p>
        <ul id="wbWidgetList" class="list-group"></ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="wbSaveConfig" class="btn btn-primary" data-bs-dismiss="modal">Save layout</button>
      </div>
    </div>
  </div>
</div>

<style>
  #wallboard.wb-fs { background: var(--bs-body-bg); padding: 1rem; overflow-y: auto; }
  .wb-card { height: 100%; }
  .wb-kpi-value { font-size: 2.4rem; font-weight: 700; line-height: 1.1; }
  .wb-kpi-sub { font-size: .8rem; }
  .wb-card .card-header { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
  .wb-list { max-height: 320px; overflow-y: auto; }
  .wb-chart-wrap { position: relative; height: 240px; }
  .wb-fs .wb-kpi-value { font-size: 3rem; }
  #wbWidgetList .wb-grip { cursor: grab; color: var(--bs-secondary-color); }
  #wbWidgetList li.dragging { opacity: .5; }
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
      col.className = sizeClass(meta.size);
      let inner = '';
      if (meta.kind === 'kpi') {
        inner =
          '<div class="card wb-card border-0 shadow-sm">' +
            '<div class="card-body">' +
              '<div class="text-muted wb-kpi-sub mb-1">' + esc(meta.title) + '</div>' +
              '<div class="wb-kpi-value" data-slot="value">–</div>' +
              '<div class="text-muted wb-kpi-sub" data-slot="sub"></div>' +
            '</div>' +
          '</div>';
      } else if (meta.kind === 'chart') {
        inner =
          '<div class="card wb-card border-0 shadow-sm">' +
            '<div class="card-header bg-transparent text-muted">' + esc(meta.title) + '</div>' +
            '<div class="card-body"><div class="wb-chart-wrap"><canvas></canvas></div>' +
              '<div class="text-muted small text-center mt-2 d-none" data-slot="empty">No data</div></div>' +
          '</div>';
      } else { // list
        inner =
          '<div class="card wb-card border-0 shadow-sm">' +
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
      li.draggable = true;
      li.dataset.widget = id;
      li.innerHTML =
        '<i class="bi bi-grip-vertical wb-grip"></i>' +
        '<div class="form-check form-switch flex-grow-1 mb-0">' +
          '<input class="form-check-input" type="checkbox" id="wbw_' + id + '" ' + (enabled.includes(id) ? 'checked' : '') + '>' +
          '<label class="form-check-label" for="wbw_' + id + '">' + esc(meta.title) +
            ' <span class="badge text-bg-light border ms-1">' + esc(meta.group) + '</span></label>' +
        '</div>';
      list.appendChild(li);
    });

    // drag-to-reorder
    let dragEl = null;
    list.querySelectorAll('li').forEach(li => {
      li.addEventListener('dragstart', () => { dragEl = li; li.classList.add('dragging'); });
      li.addEventListener('dragend', () => { li.classList.remove('dragging'); dragEl = null; });
      li.addEventListener('dragover', (e) => {
        e.preventDefault();
        const after = [...list.querySelectorAll('li:not(.dragging)')].find(el => {
          const r = el.getBoundingClientRect();
          return e.clientY < r.top + r.height / 2;
        });
        if (after) list.insertBefore(dragEl, after); else list.appendChild(dragEl);
      });
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
  buildGrid();
  refresh();
  startTimer();
})();
</script>
