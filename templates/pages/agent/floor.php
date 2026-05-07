<?php
/** @var array $tickets */
/** @var string $activeView */
/** @var array $counts */
/** @var array $types */
/** @var array $locations */
?>
<style>
.floor-shell { padding-bottom: 6rem; }
.floor-tabs {
    display: flex;
    gap: .35rem;
    margin: 0 0 1rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: .25rem;
}
.floor-tab {
    flex: 0 0 auto;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    padding: .55rem 1rem;
    border-radius: 999px;
    font-size: .9rem;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    min-height: 44px;
    white-space: nowrap;
}
.floor-tab .count {
    background: #e2e8f0;
    color: #1e293b;
    border-radius: 999px;
    padding: .05rem .55rem;
    font-size: .75rem;
    font-weight: 600;
}
.floor-tab.active {
    background: var(--ld-primary, #4f46e5);
    color: #fff;
    border-color: var(--ld-primary, #4f46e5);
}
.floor-tab.active .count { background: rgba(255,255,255,.2); color: #fff; }
[data-bs-theme="dark"] .floor-tab { background: #2b3035; color: #dee2e6; border-color: #495057; }
[data-bs-theme="dark"] .floor-tab .count { background: #495057; color: #f8f9fa; }

.floor-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: .85rem;
}
.floor-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #94a3b8;
    border-radius: 12px;
    padding: 1rem;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    gap: .65rem;
    min-height: 140px;
    transition: transform .12s ease, box-shadow .12s ease;
    -webkit-tap-highlight-color: rgba(79,70,229,.18);
}
.floor-card:active { transform: scale(.98); }
.floor-card:hover { box-shadow: 0 4px 14px rgba(15,23,42,.08); color: inherit; }
[data-bs-theme="dark"] .floor-card { background: #212529; border-color: #373b3e; color: #f8f9fa; }
[data-bs-theme="dark"] .floor-card:hover { color: #fff; }

.floor-card .top-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .75rem; color: #64748b; font-weight: 500;
}
[data-bs-theme="dark"] .floor-card .top-row { color: #adb5bd; }
.floor-card .ticket-id { font-weight: 600; }
.floor-card .subject {
    font-weight: 600; font-size: 1rem; line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}
.floor-card .meta-row {
    display: flex; flex-wrap: wrap; gap: .35rem; align-items: center;
    font-size: .75rem;
}
.floor-card .pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .15rem .55rem; border-radius: 999px; font-weight: 500;
    border: 1px solid transparent;
}
.floor-card .pill.status-open        { background: #dbeafe; color: #1e40af; }
.floor-card .pill.status-in_progress { background: #fef3c7; color: #92400e; }
.floor-card .pill.status-pending     { background: #f3e8ff; color: #6b21a8; }
[data-bs-theme="dark"] .floor-card .pill.status-open        { background: #1e3a8a; color: #bfdbfe; }
[data-bs-theme="dark"] .floor-card .pill.status-in_progress { background: #78350f; color: #fde68a; }
[data-bs-theme="dark"] .floor-card .pill.status-pending     { background: #581c87; color: #e9d5ff; }

.floor-card .pill.location { background: #f1f5f9; color: #1e293b; }
[data-bs-theme="dark"] .floor-card .pill.location { background: #2b3035; color: #dee2e6; }
.floor-card .pill.unassigned { background: #fef2f2; color: #b91c1c; }
[data-bs-theme="dark"] .floor-card .pill.unassigned { background: #7f1d1d; color: #fecaca; }
.floor-card .pill.assigned { background: #ecfdf5; color: #065f46; }
[data-bs-theme="dark"] .floor-card .pill.assigned { background: #064e3b; color: #a7f3d0; }

/* Priority is a coloured left rail (driven by per-row inline style) */

/* Floating action button — quick-create */
.floor-fab {
    position: fixed;
    right: max(1rem, calc(env(safe-area-inset-right, 0) + 1rem));
    bottom: max(1.25rem, calc(env(safe-area-inset-bottom, 0) + 1.25rem));
    width: 60px; height: 60px;
    border-radius: 50%;
    background: var(--ld-primary, #4f46e5);
    color: #fff;
    border: none;
    box-shadow: 0 8px 22px rgba(79,70,229,.4);
    font-size: 1.7rem;
    z-index: 1040;
    cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
}
.floor-fab:hover { filter: brightness(1.05); }
.floor-fab:active { transform: scale(.95); }

/* Bottom-sheet quick-create */
.floor-sheet-backdrop {
    position: fixed; inset: 0;
    background: rgba(15,23,42,.5);
    z-index: 1060;
    opacity: 0; pointer-events: none;
    transition: opacity .18s ease;
}
.floor-sheet-backdrop.show { opacity: 1; pointer-events: auto; }
.floor-sheet {
    position: fixed;
    left: 0; right: 0; bottom: 0;
    z-index: 1070;
    background: #fff;
    border-top-left-radius: 18px;
    border-top-right-radius: 18px;
    padding: 1rem 1.1rem max(1.1rem, env(safe-area-inset-bottom, 1rem));
    transform: translateY(110%);
    transition: transform .22s cubic-bezier(.2,.8,.2,1);
    max-height: 92vh; overflow-y: auto;
    box-shadow: 0 -10px 30px rgba(0,0,0,.18);
}
.floor-sheet.show { transform: translateY(0); }
[data-bs-theme="dark"] .floor-sheet { background: #212529; color: #f8f9fa; }
.floor-sheet .grabber {
    width: 44px; height: 4px; background: #cbd5e1; border-radius: 999px;
    margin: 0 auto .85rem;
}
.floor-sheet h3 { font-size: 1.15rem; margin: 0 0 .85rem; font-weight: 600; }
.floor-sheet label { font-size: .8rem; color: #475569; font-weight: 500; margin-bottom: .25rem; display: block; }
[data-bs-theme="dark"] .floor-sheet label { color: #adb5bd; }
.floor-sheet .form-control,
.floor-sheet .form-select {
    font-size: 1rem; min-height: 44px;
    border-radius: 8px;
}
.floor-sheet .input-with-mic { position: relative; }
.floor-sheet .input-with-mic .mic-btn {
    position: absolute; right: .35rem; top: 50%;
    transform: translateY(-50%);
    width: 36px; height: 36px;
    border: none; background: transparent;
    color: var(--ld-primary, #4f46e5);
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
}
.floor-sheet .input-with-mic .mic-btn.recording { color: #dc2626; animation: floor-pulse 1s ease-in-out infinite; }
@keyframes floor-pulse {
    0%, 100% { transform: translateY(-50%) scale(1);    }
    50%      { transform: translateY(-50%) scale(1.18); }
}
.floor-sheet .photo-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
    margin-top: .25rem;
}
.floor-sheet .photo-btn,
.floor-sheet .scan-btn {
    background: #f1f5f9; color: #1e293b;
    border: 1px dashed #94a3b8;
    border-radius: 10px;
    padding: .8rem; text-align: center;
    font-weight: 500; font-size: .9rem;
    cursor: pointer;
    min-height: 56px;
}
[data-bs-theme="dark"] .floor-sheet .photo-btn,
[data-bs-theme="dark"] .floor-sheet .scan-btn {
    background: #2b3035; color: #f8f9fa; border-color: #495057;
}
.floor-sheet .photo-btn i, .floor-sheet .scan-btn i { font-size: 1.4rem; display: block; margin-bottom: .15rem; }
.floor-sheet .photo-thumbs {
    display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem;
}
.floor-sheet .photo-thumb {
    width: 60px; height: 60px; border-radius: 8px;
    background-size: cover; background-position: center;
    border: 1px solid #cbd5e1; position: relative;
}
.floor-sheet .photo-thumb .remove {
    position: absolute; top: -6px; right: -6px;
    width: 22px; height: 22px;
    border-radius: 50%; background: #dc2626; color: #fff;
    border: none; font-size: .8rem; line-height: 1;
    display: inline-flex; align-items: center; justify-content: center;
}
.floor-sheet .submit-row {
    display: flex; gap: .5rem; margin-top: 1rem;
}
.floor-sheet .submit-row button {
    flex: 1; min-height: 48px; border-radius: 10px;
    font-weight: 600; font-size: 1rem; border: none;
}
.floor-sheet .submit-row .cancel { background: #e2e8f0; color: #1e293b; }
.floor-sheet .submit-row .submit { background: var(--ld-primary, #4f46e5); color: #fff; }
.floor-sheet .submit-row .submit:disabled { opacity: .6; }
.floor-sheet .scan-result {
    background: #ecfdf5; color: #065f46; border-radius: 8px;
    padding: .55rem .75rem; font-size: .85rem; margin-top: .5rem;
    display: none;
}
.floor-sheet .scan-result.show { display: block; }
[data-bs-theme="dark"] .floor-sheet .scan-result { background: #064e3b; color: #a7f3d0; }

.floor-empty {
    text-align: center; padding: 3rem 1rem; color: #64748b;
}
.floor-empty i { font-size: 3rem; opacity: .35; }
[data-bs-theme="dark"] .floor-empty { color: #adb5bd; }

@media (max-width: 768px) {
    .main-content { padding: 1rem !important; }
    .floor-grid { grid-template-columns: 1fr; }
}
</style>

<div class="floor-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0" style="font-size:1.4rem;font-weight:700;">Floor mode</h2>
        <a href="/agent/tickets" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul"></i> Full list</a>
    </div>

    <nav class="floor-tabs" aria-label="Filter">
        <a class="floor-tab <?= $activeView === 'all'        ? 'active' : '' ?>" href="/agent/floor?view=all">All open <span class="count"><?= (int) $counts['all'] ?></span></a>
        <a class="floor-tab <?= $activeView === 'mine'       ? 'active' : '' ?>" href="/agent/floor?view=mine">Mine <span class="count"><?= (int) $counts['mine'] ?></span></a>
        <a class="floor-tab <?= $activeView === 'unassigned' ? 'active' : '' ?>" href="/agent/floor?view=unassigned">Unassigned <span class="count"><?= (int) $counts['unassigned'] ?></span></a>
    </nav>

    <?php if (empty($tickets)): ?>
        <div class="floor-empty">
            <i class="bi bi-check2-circle"></i>
            <p class="mt-2 mb-0" style="font-size:1.05rem;">Nothing in this queue right now.</p>
            <small>Tap the <i class="bi bi-plus-lg"></i> button if you spot something on the floor.</small>
        </div>
    <?php else: ?>
        <div class="floor-grid">
        <?php foreach ($tickets as $t):
            $createdTs = strtotime($t['created_at']);
            $diff      = max(0, time() - $createdTs);
            if ($diff < 60)            $age = 'just now';
            elseif ($diff < 3600)      $age = floor($diff / 60) . 'm ago';
            elseif ($diff < 86400)     $age = floor($diff / 3600) . 'h ago';
            elseif ($diff < 86400 * 7) $age = floor($diff / 86400) . 'd ago';
            else                       $age = date('M j', $createdTs);

            $rail = $t['priority_color'] ?: '#94a3b8';
        ?>
            <a class="floor-card" href="/agent/tickets/<?= (int) $t['id'] ?>" style="border-left-color:<?= e($rail) ?>;">
                <div class="top-row">
                    <span class="ticket-id">#<?= (int) $t['id'] ?>
                        <?php if (!empty($t['priority_name'])): ?>
                            &middot; <span style="color:<?= e($rail) ?>;font-weight:600;"><?= e($t['priority_name']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span><?= e($age) ?></span>
                </div>
                <div class="subject"><?= e($t['subject']) ?></div>
                <div class="meta-row">
                    <span class="pill status-<?= e($t['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?></span>
                    <?php if (!empty($t['type_name'])): ?>
                        <span class="pill" style="background:<?= e($t['type_color'] ?: '#f1f5f9') ?>26;color:<?= e($t['type_color'] ?: '#1e293b') ?>;"><i class="bi bi-bookmark"></i><?= e($t['type_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($t['location_name'])): ?>
                        <span class="pill location"><i class="bi bi-geo-alt"></i><?= e($t['location_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($t['agent_name']) && !empty($t['assigned_to'])): ?>
                        <span class="pill assigned"><i class="bi bi-person-check"></i><?= e($t['agent_name']) ?></span>
                    <?php else: ?>
                        <span class="pill unassigned"><i class="bi bi-person-dash"></i>Unassigned</span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<button type="button" class="floor-fab" id="floor-fab" aria-label="New ticket from floor">
    <i class="bi bi-plus-lg"></i>
</button>

<div class="floor-sheet-backdrop" id="floor-sheet-backdrop"></div>
<div class="floor-sheet" id="floor-sheet" role="dialog" aria-labelledby="floor-sheet-title" aria-modal="true">
    <div class="grabber" aria-hidden="true"></div>
    <h3 id="floor-sheet-title">Quick ticket</h3>
    <form id="floor-quick-form" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <label for="fq-subject">What happened?</label>
        <div class="input-with-mic">
            <input type="text" class="form-control" id="fq-subject" name="subject" required minlength="3"
                   placeholder="e.g. Public PC #4 frozen at sign-in screen" autocomplete="off">
            <button type="button" class="mic-btn" id="fq-mic" aria-label="Voice dictate" title="Voice dictate">
                <i class="bi bi-mic-fill"></i>
            </button>
        </div>

        <div class="row g-2 mt-2">
            <div class="col-7">
                <label for="fq-type">Type</label>
                <select id="fq-type" name="type_id" class="form-select" required>
                    <option value="">Pick a type…</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-5">
                <label for="fq-location">Location</label>
                <select id="fq-location" name="location_id" class="form-select">
                    <option value="">My branch</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= (int) $l['id'] ?>"><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label class="mt-3">Photo &amp; scan</label>
        <div class="photo-row">
            <button type="button" class="photo-btn" id="fq-photo-trigger">
                <i class="bi bi-camera-fill"></i>
                Take photo
            </button>
            <button type="button" class="scan-btn" id="fq-scan-trigger">
                <i class="bi bi-upc-scan"></i>
                Scan barcode
            </button>
        </div>
        <input type="file" id="fq-photo-input" name="attachments[]" accept="image/*" capture="environment" multiple style="display:none;">
        <div class="photo-thumbs" id="fq-photo-thumbs"></div>
        <div class="scan-result" id="fq-scan-result"></div>

        <div class="submit-row">
            <button type="button" class="cancel" id="fq-cancel">Cancel</button>
            <button type="submit" class="submit" id="fq-submit">Create ticket</button>
        </div>
    </form>
</div>

<script>
(function () {
    const fab        = document.getElementById('floor-fab');
    const sheet      = document.getElementById('floor-sheet');
    const backdrop   = document.getElementById('floor-sheet-backdrop');
    const cancelBtn  = document.getElementById('fq-cancel');
    const form       = document.getElementById('floor-quick-form');
    const subjectEl  = document.getElementById('fq-subject');
    const submitBtn  = document.getElementById('fq-submit');
    const photoTrig  = document.getElementById('fq-photo-trigger');
    const photoInput = document.getElementById('fq-photo-input');
    const thumbsBox  = document.getElementById('fq-photo-thumbs');
    const scanTrig   = document.getElementById('fq-scan-trigger');
    const scanResult = document.getElementById('fq-scan-result');
    const micBtn     = document.getElementById('fq-mic');

    function open() {
        sheet.classList.add('show');
        backdrop.classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { subjectEl.focus(); }, 160);
    }
    function close() {
        sheet.classList.remove('show');
        backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }
    fab.addEventListener('click', open);
    backdrop.addEventListener('click', close);
    cancelBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sheet.classList.contains('show')) close();
    });

    /* ── Camera capture ──────────────────────────────────────────── */
    photoTrig.addEventListener('click', function () { photoInput.click(); });

    function renderThumbs() {
        thumbsBox.innerHTML = '';
        Array.from(photoInput.files || []).forEach(function (file, idx) {
            if (!file.type.startsWith('image/')) return;
            const url = URL.createObjectURL(file);
            const div = document.createElement('div');
            div.className = 'photo-thumb';
            div.style.backgroundImage = 'url(' + url + ')';
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'remove';
            rm.setAttribute('aria-label', 'Remove photo');
            rm.textContent = '×';
            rm.addEventListener('click', function (e) {
                e.stopPropagation();
                const dt = new DataTransfer();
                Array.from(photoInput.files).forEach(function (f, i) { if (i !== idx) dt.items.add(f); });
                photoInput.files = dt.files;
                renderThumbs();
            });
            div.appendChild(rm);
            thumbsBox.appendChild(div);
        });
    }
    photoInput.addEventListener('change', renderThumbs);

    /* ── Voice dictate (subject) ─────────────────────────────────── */
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { micBtn.style.display = 'none'; }
    var recognizer = null, recording = false;

    micBtn.addEventListener('click', function () {
        if (!SR) return;
        if (recording) { recognizer && recognizer.stop(); return; }
        recognizer = new SR();
        recognizer.lang = (document.documentElement.lang || 'en') + '-US';
        recognizer.interimResults = false;
        recognizer.maxAlternatives = 1;
        recognizer.onresult = function (e) {
            var txt = e.results[0][0].transcript || '';
            if (txt) {
                subjectEl.value = subjectEl.value
                    ? (subjectEl.value.replace(/\s+$/, '') + ' ' + txt)
                    : txt.charAt(0).toUpperCase() + txt.slice(1);
            }
        };
        recognizer.onstart = function () { recording = true;  micBtn.classList.add('recording'); };
        recognizer.onend   = function () { recording = false; micBtn.classList.remove('recording'); };
        recognizer.onerror = function () { recording = false; micBtn.classList.remove('recording'); };
        try { recognizer.start(); } catch (e) { /* already started */ }
    });

    /* ── Barcode scan (BarcodeDetector when available) ───────────── */
    scanTrig.addEventListener('click', async function () {
        if (!('BarcodeDetector' in window)) {
            scanResult.textContent = 'Barcode scan needs Chrome on Android, or you can paste the asset code into the subject.';
            scanResult.classList.add('show');
            return;
        }
        try {
            var stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            var video  = document.createElement('video');
            video.srcObject = stream;
            video.setAttribute('playsinline', '');
            await video.play();
            var detector = new BarcodeDetector({ formats: ['code_128','ean_13','ean_8','qr_code','code_39'] });
            var attempts = 0, code = null;
            while (attempts < 40 && !code) {
                var canvas = document.createElement('canvas');
                canvas.width = video.videoWidth; canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                var found = await detector.detect(canvas).catch(function () { return []; });
                if (found && found.length) { code = found[0].rawValue; break; }
                await new Promise(function (r) { setTimeout(r, 120); });
                attempts++;
            }
            stream.getTracks().forEach(function (t) { t.stop(); });
            if (code) {
                subjectEl.value = (subjectEl.value || '').trim();
                subjectEl.value = (subjectEl.value ? subjectEl.value + ' ' : '') + '[Asset ' + code + ']';
                scanResult.textContent = 'Captured asset code ' + code + '.';
            } else {
                scanResult.textContent = 'No barcode detected. Try moving closer or improving the lighting.';
            }
            scanResult.classList.add('show');
        } catch (err) {
            scanResult.textContent = 'Camera unavailable: ' + (err && err.message ? err.message : 'permission denied');
            scanResult.classList.add('show');
        }
    });

    /* ── Submit ─────────────────────────────────────────────────── */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!form.reportValidity()) return;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating…';
        try {
            var fd = new FormData(form);
            var res = await fetch('/agent/floor/quick-create', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': '<?= e(csrfToken()) ?>' },
            });
            var data = await res.json();
            if (data && data.ok && data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }
            alert((data && data.error) || 'Something went wrong creating the ticket.');
        } catch (err) {
            alert('Network error: ' + (err && err.message ? err.message : 'unknown'));
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create ticket';
        }
    });
})();
</script>
