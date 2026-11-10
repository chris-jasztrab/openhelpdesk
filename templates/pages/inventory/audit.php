<?php
$layout       = 'app';
$pageTitle    = 'Audit mode – ' . label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory'), 'url' => '/inventory'],
    ['label' => 'Audit mode'],
];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-clipboard-check me-2"></i>Audit mode</h4>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="camBtn" hidden><i class="bi bi-camera me-1"></i><span>Use camera</span></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearBtn" title="Clear this device's list — audits already saved are kept"><i class="bi bi-x-lg me-1"></i>Clear list</button>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-sm-5">
                <label class="form-label small fw-semibold mb-1" for="auditLocation">I'm at</label>
                <select class="form-select" id="auditLocation">
                    <option value="">— choose a <?= label('location.singular', 'location') ?> —</option>
                    <?php foreach ($locations as $l): ?>
                    <option value="<?= (int) $l['id'] ?>" <?= (int) $l['id'] === $myLocationId ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label small fw-semibold mb-1" for="auditNext">Next audit date</label>
                <input type="date" class="form-control" id="auditNext" value="">
            </div>
            <div class="col-sm-3 small text-muted pb-2">
                <?php if ($intervalMonths > 0): ?>Blank = <?= e($defaultNext) ?> (<?= (int) $intervalMonths ?> months).<?php else: ?>Blank = unchanged.<?php endif; ?>
            </div>
        </div>
        <label class="form-label small fw-semibold mb-1" for="scanInput">Scan or type a tag, serial or QR code</label>
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
            <input type="text" class="form-control font-monospace" id="scanInput" autocomplete="off" autocapitalize="off" spellcheck="false" autofocus placeholder="Scanner input goes here">
        </div>
        <div id="scanMsg" class="small mt-2 text-muted">Scanning stamps the asset's last audit; its <?= label('location.singular', 'location') ?> is never changed, a mismatch is only flagged.</div>
    </div>
</div>

<div id="camWrap" class="mb-3" hidden>
    <video id="camVideo" playsinline muted class="w-100 rounded shadow-sm" style="max-height:45vh;object-fit:cover;background:#000;"></video>
    <canvas id="camCanvas" hidden></canvas>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Scanned this session</span>
        <span class="badge bg-secondary" id="scanCount">0</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" id="scanTable" data-no-resize>
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap d-none d-md-table-cell">Time</th>
                    <th class="d-none d-md-table-cell">Code</th>
                    <th>Tag</th>
                    <th>Name</th>
                    <th><?= label('location.singular', 'Location') ?></th>
                    <th>Checked out to</th>
                    <th class="text-nowrap d-none d-md-table-cell">Previous audit</th>
                </tr>
            </thead>
            <tbody id="scanBody">
                <tr id="scanEmpty"><td colspan="7" class="text-center text-muted py-4">Nothing scanned yet.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/vendor/jsqr/jsQR.js"></script>
<script>
(function () {
    var input = document.getElementById('scanInput');
    var loc = document.getElementById('auditLocation');
    var next = document.getElementById('auditNext');
    var body = document.getElementById('scanBody');
    var msg = document.getElementById('scanMsg');
    var count = document.getElementById('scanCount');
    var camBtn = document.getElementById('camBtn');
    var camWrap = document.getElementById('camWrap');
    var video = document.getElementById('camVideo');
    var canvas = document.getElementById('camCanvas');
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var KEY = 'ldAuditRows';
    var rows = [];
    try { rows = JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch (e) { rows = []; }
    var camOn = false, stream = null, lastCode = '', lastAt = 0, busy = false;

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function save() { try { sessionStorage.setItem(KEY, JSON.stringify(rows.slice(0, 500))); } catch (e) {} }
    function focusInput() {
        if (camOn) return;
        var a = document.activeElement;
        if (a && a !== input && a.matches('select,input,button,a,textarea')) return;
        input.focus();
    }
    function render() {
        count.textContent = rows.length;
        if (!rows.length) { body.innerHTML = '<tr id="scanEmpty"><td colspan="7" class="text-center text-muted py-4">Nothing scanned yet.</td></tr>'; return; }
        body.innerHTML = rows.map(function (r) {
            if (!r.found) {
                return '<tr class="table-danger"><td class="text-nowrap small d-none d-md-table-cell">' + esc(r.time) + '</td><td class="font-monospace small text-truncate d-none d-md-table-cell" style="max-width:16rem;" title="' + esc(r.code) + '">' + esc(r.code) + '</td><td colspan="5"><i class="bi bi-exclamation-triangle me-1"></i>Not found<div class="d-md-none small font-monospace text-truncate" style="max-width:60vw;">' + esc(r.code) + '</div></td></tr>';
            }
            return '<tr' + (r.mismatch ? ' class="table-warning"' : '') + '>'
                + '<td class="text-nowrap small d-none d-md-table-cell">' + esc(r.time) + '</td>'
                + '<td class="font-monospace small text-truncate d-none d-md-table-cell" style="max-width:16rem;" title="' + esc(r.code) + '">' + esc(r.code) + '</td>'
                + '<td><a href="/inventory/' + r.id + '" class="font-monospace text-decoration-none text-nowrap">' + esc(r.tag) + '</a><div class="d-md-none small text-muted text-truncate" style="max-width:7rem;" title="' + esc(r.code) + '">' + esc(r.code) + '</div></td>'
                + '<td>' + esc(r.name || r.model) + '</td>'
                + '<td>' + esc(r.location || '—') + (r.mismatch ? ' <span class="badge bg-warning text-dark">not here</span>' : '') + '</td>'
                + '<td>' + esc(r.assigned || '—') + '</td>'
                + '<td class="text-nowrap small d-none d-md-table-cell">' + esc(r.prev_audit || 'Never') + '</td></tr>';
        }).join('');
    }
    function beep(ok) {
        try {
            var ctx = beep.ctx || (beep.ctx = new (window.AudioContext || window.webkitAudioContext)());
            var o = ctx.createOscillator(), g = ctx.createGain();
            o.frequency.value = ok ? 1200 : 300; g.gain.value = 0.15;
            o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + (ok ? 0.08 : 0.3));
        } catch (e) {}
        if (navigator.vibrate) navigator.vibrate(ok ? 80 : [120, 60, 120]);
    }
    function submit(code) {
        code = (code || '').trim();
        if (!code || busy) return;
        if (!loc.value) { msg.className = 'small mt-2 text-danger'; msg.textContent = 'Choose where you are first.'; loc.focus(); beep(false); return; }
        busy = true;
        var fd = new URLSearchParams({ _token: token, code: code, location_id: loc.value, next_audit_date: next.value });
        fetch('/inventory/audit/scan', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.error) { msg.className = 'small mt-2 text-danger'; msg.textContent = j.error; beep(false); return; }
                j.time = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                rows = rows.filter(function (r) { return !(j.found && r.found && r.id === j.id); });
                rows.unshift(j); save(); render();
                msg.className = 'small mt-2 ' + (j.found ? (j.mismatch ? 'text-warning' : 'text-success') : 'text-danger');
                msg.textContent = j.found ? ('✓ ' + j.tag + (j.name ? ' — ' + j.name : '') + (j.mismatch ? ' (recorded at ' + (j.location || '?') + ')' : '')) : 'No asset matches "' + code + '"';
                beep(j.found);
            })
            .catch(function () { msg.className = 'small mt-2 text-danger'; msg.textContent = 'Network error — scan again.'; beep(false); })
            .finally(function () { busy = false; input.value = ''; focusInput(); });
    }

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); submit(input.value); }
    });
    input.addEventListener('blur', function () { setTimeout(focusInput, 0); });
    document.addEventListener('click', function (e) { if (!e.target.closest('select,input,button,a,textarea,video')) focusInput(); });
    [loc, next].forEach(function (el) { el.addEventListener('change', function () { setTimeout(focusInput, 0); }); });
    document.getElementById('clearBtn').addEventListener('click', function () { rows = []; save(); render(); focusInput(); });

    // Camera: phones/tablets (coarse pointer) start it automatically; laptops with a USB scanner keep the text input.
    var canCam = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.jsQR);
    if (canCam) camBtn.hidden = false;
    function tick() {
        if (!camOn) return;
        if (video.readyState >= 2) {
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            var ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(video, 0, 0);
            var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var q = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
            var now = Date.now();
            if (q && q.data && (q.data !== lastCode || now - lastAt > 3000)) { lastCode = q.data; lastAt = now; submit(q.data); }
        }
        requestAnimationFrame(tick);
    }
    function startCam() {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (s) {
            stream = s; video.srcObject = s; video.play();
            camOn = true; camWrap.hidden = false; input.blur();
            camBtn.innerHTML = '<i class="bi bi-camera-video-off me-1"></i><span>Stop camera</span>';
            requestAnimationFrame(tick);
        }).catch(function (err) { msg.className = 'small mt-2 text-danger'; msg.textContent = 'Camera unavailable: ' + err.message; focusInput(); });
    }
    function stopCam() {
        camOn = false; camWrap.hidden = true;
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        camBtn.innerHTML = '<i class="bi bi-camera me-1"></i><span>Use camera</span>';
        focusInput();
    }
    camBtn.addEventListener('click', function () { camOn ? stopCam() : startCam(); });
    if (canCam && window.matchMedia('(pointer: coarse)').matches) startCam();

    render();
    focusInput();
})();
</script>
