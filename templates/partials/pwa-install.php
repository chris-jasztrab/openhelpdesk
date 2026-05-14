<?php
/**
 * PWA service-worker registration + install prompt UI.
 *
 * Renders a dismissible bottom banner that:
 *  - On Chromium/Edge/Android: captures `beforeinstallprompt` and shows
 *    a one-tap "Install app" button that fires the native prompt.
 *  - On iOS Safari (no install prompt API): shows a hint pointing at
 *    the Share → Add to Home Screen flow.
 *
 * Also wires:
 *  - SW registration on /sw.js with scope /
 *  - "Update available" toast when a new SW lands while the page is open
 *  - localStorage dismissal so we don't nag staff who said no
 */
$appName = getSetting('branding_app_name', 'OpenHelpDesk');
?>
<style>
.pwa-install-banner {
    position: fixed;
    left: 50%;
    bottom: 1rem;
    transform: translateX(-50%);
    width: calc(100vw - 2rem);
    max-width: 420px;
    background: #fff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,.18);
    padding: .85rem 1rem;
    z-index: 1080;
    display: none;
    align-items: center;
    gap: .75rem;
    font-size: .9rem;
}
.pwa-install-banner.show { display: flex; }
.pwa-install-banner .pwa-icon {
    width: 36px; height: 36px; border-radius: 8px;
    background: var(--ld-primary, #4f46e5);
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 700; flex-shrink: 0;
}
.pwa-install-banner .pwa-text { flex: 1; line-height: 1.25; }
.pwa-install-banner .pwa-text strong { display: block; font-size: .9rem; }
.pwa-install-banner .pwa-text small { color: #64748b; font-size: .75rem; }
.pwa-install-banner button.pwa-install-btn {
    border: none;
    background: var(--ld-primary, #4f46e5);
    color: #fff;
    padding: .45rem .85rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: .85rem;
    cursor: pointer;
}
.pwa-install-banner button.pwa-install-btn:hover { filter: brightness(1.05); }
.pwa-install-banner button.pwa-dismiss-btn {
    border: none; background: transparent; color: #94a3b8;
    cursor: pointer; padding: .25rem .4rem; font-size: 1.1rem; line-height: 1;
}
.pwa-install-banner button.pwa-dismiss-btn:hover { color: #475569; }
[data-bs-theme="dark"] .pwa-install-banner {
    background: #2b3035; color: #f8f9fa; border-color: #495057;
}
[data-bs-theme="dark"] .pwa-install-banner .pwa-text small { color: #adb5bd; }

.pwa-update-toast {
    position: fixed;
    left: 50%;
    top: 1rem;
    transform: translateX(-50%);
    background: #1e293b;
    color: #fff;
    padding: .65rem 1rem;
    border-radius: 999px;
    box-shadow: 0 8px 24px rgba(0,0,0,.25);
    font-size: .85rem;
    z-index: 1090;
    display: none;
    align-items: center;
    gap: .65rem;
}
.pwa-update-toast.show { display: inline-flex; }
.pwa-update-toast button {
    border: none; background: #fff; color: #1e293b;
    padding: .25rem .75rem; border-radius: 999px;
    font-weight: 600; font-size: .8rem; cursor: pointer;
}
@media (prefers-reduced-motion: reduce) {
    .pwa-install-banner, .pwa-update-toast { animation: none !important; }
}
</style>

<div class="pwa-install-banner" id="pwa-install-banner" role="dialog" aria-labelledby="pwa-install-title" aria-live="polite">
    <span class="pwa-icon" aria-hidden="true"><?= e(PWA::appInitial()) ?></span>
    <div class="pwa-text">
        <strong id="pwa-install-title">Install <?= e($appName) ?>?</strong>
        <small id="pwa-install-hint">Faster access, fewer taps.</small>
    </div>
    <button type="button" class="pwa-install-btn" id="pwa-install-btn">Install</button>
    <button type="button" class="pwa-dismiss-btn" id="pwa-install-dismiss" aria-label="Dismiss install prompt">&times;</button>
</div>

<div class="pwa-update-toast" id="pwa-update-toast" role="status">
    <span>New version ready.</span>
    <button type="button" id="pwa-update-btn">Reload</button>
</div>

<script>
(function () {
    if (!('serviceWorker' in navigator)) return;

    /* ── Service worker registration + update flow ───────────────── */
    var refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (refreshing) return;
        refreshing = true;
        window.location.reload();
    });

    function wireUpdate(reg) {
        if (!reg) return;
        if (reg.waiting) showUpdateToast(reg.waiting);
        reg.addEventListener('updatefound', function () {
            var sw = reg.installing;
            if (!sw) return;
            sw.addEventListener('statechange', function () {
                if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                    showUpdateToast(sw);
                }
            });
        });
    }

    function showUpdateToast(sw) {
        var toast = document.getElementById('pwa-update-toast');
        var btn = document.getElementById('pwa-update-btn');
        if (!toast || !btn) return;
        toast.classList.add('show');
        btn.addEventListener('click', function () {
            sw.postMessage('SKIP_WAITING');
        }, { once: true });
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then(wireUpdate)
            .catch(function () { /* swallow — SW is enhancement-only */ });
    });

    /* ── Install prompt (Chromium / Android) ─────────────────────── */
    var deferredPrompt = null;
    var DISMISS_KEY = 'ld_pwa_install_dismissed_v1';
    var DISMISS_DAYS = 14;

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }
    function isDismissedRecently() {
        try {
            var ts = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
            if (!ts) return false;
            return (Date.now() - ts) < DISMISS_DAYS * 24 * 3600 * 1000;
        } catch (e) { return false; }
    }
    function markDismissed() {
        try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
    }
    function showInstall() {
        var banner = document.getElementById('pwa-install-banner');
        if (!banner) return;
        banner.classList.add('show');
    }
    function hideInstall() {
        var banner = document.getElementById('pwa-install-banner');
        if (!banner) return;
        banner.classList.remove('show');
    }

    if (!isStandalone() && !isDismissedRecently()) {
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredPrompt = e;
            showInstall();
        });

        // iOS doesn't fire beforeinstallprompt — show an A2HS hint
        // instead. Only on iPhone/iPad Safari (not Chrome iOS, which
        // still can't install).
        var ua = navigator.userAgent;
        var isIOSSafari = /iPad|iPhone|iPod/.test(ua) && !/CriOS|FxiOS|OPiOS/.test(ua);
        if (isIOSSafari) {
            setTimeout(function () {
                var banner = document.getElementById('pwa-install-banner');
                var btn    = document.getElementById('pwa-install-btn');
                var hint   = document.getElementById('pwa-install-hint');
                if (!banner || !btn || !hint) return;
                hint.textContent = 'Tap Share ↗, then "Add to Home Screen".';
                btn.style.display = 'none';
                showInstall();
            }, 1500);
        }
    }

    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'pwa-install-btn') {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            deferredPrompt.userChoice.finally(function () {
                deferredPrompt = null;
                hideInstall();
            });
        }
        if (e.target && e.target.id === 'pwa-install-dismiss') {
            markDismissed();
            hideInstall();
        }
    });

    window.addEventListener('appinstalled', function () {
        hideInstall();
        markDismissed();
    });
})();
</script>
