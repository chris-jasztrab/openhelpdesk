<?php
/**
 * Status banner — pinned incident notice rendered at the top of every
 * authenticated page (portal + agent + admin) so a wave of duplicate
 * tickets gets stopped before it starts. Managed at /agent/banners.
 *
 * `body_html` is the raw output of CKEditor (same trust model as KB
 * articles); `sanitizeBannerHtml()` strips <script>, inline event
 * handlers and `javascript:` URLs as a defence-in-depth measure.
 *
 * Per-user dismissal is stored server-side in $_SESSION (keyed by
 * banner id + updated_at) so the choice persists across tabs and page
 * loads for the rest of the session. Editing a banner bumps updated_at
 * and re-surfaces it for users who'd previously dismissed the older
 * copy. Agents and admins also get a Clear/Edit row of buttons inline
 * so they can act on a posted incident without navigating away.
 */
$_banners = getActiveBanners();
if (empty($_banners)) {
    return;
}
$_isAgent = in_array(Auth::role(), ['agent', 'admin', 'power_user'], true);

$_severityClass = [
    'info'     => 'alert-info',
    'warning'  => 'alert-warning',
    'critical' => 'alert-danger',
];
$_severityIcon = [
    'info'     => 'bi-info-circle-fill',
    'warning'  => 'bi-exclamation-triangle-fill',
    'critical' => 'bi-exclamation-octagon-fill',
];
?>
<div id="ld-status-banners" class="mb-3">
    <?php foreach ($_banners as $_b): ?>
        <?php
        $_cls  = $_severityClass[$_b['severity']] ?? 'alert-warning';
        $_icon = $_severityIcon[$_b['severity']] ?? 'bi-exclamation-triangle-fill';
        ?>
        <div class="alert <?= $_cls ?> d-flex align-items-start gap-3 shadow-sm ld-banner"
             role="<?= $_b['severity'] === 'critical' ? 'alert' : 'status' ?>"
             aria-live="<?= $_b['severity'] === 'critical' ? 'assertive' : 'polite' ?>"
             data-banner-id="<?= (int) $_b['id'] ?>">
            <div class="flex-shrink-0 fs-4 lh-1 pt-1">
                <i class="bi <?= $_icon ?>" aria-hidden="true"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
                <?php if (!empty($_b['title'])): ?>
                    <div class="fw-bold mb-1"><?= e($_b['title']) ?></div>
                <?php endif; ?>
                <div class="ld-banner-body"><?= sanitizeBannerHtml($_b['body_html']) ?></div>
                <div class="small text-muted mt-2 d-flex flex-wrap gap-3">
                    <?php if (!empty($_b['location_name'])): ?>
                        <span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($_b['location_name']) ?></span>
                    <?php else: ?>
                        <span><i class="bi bi-globe me-1" aria-hidden="true"></i>All branches</span>
                    <?php endif; ?>
                    <?php if (!empty($_b['expires_at'])): ?>
                        <span><i class="bi bi-clock me-1" aria-hidden="true"></i>Expires <?= e(date('M j, g:i A', strtotime($_b['expires_at']))) ?></span>
                    <?php endif; ?>
                    <?php if ($_isAgent && !empty($_b['posted_by_name'])): ?>
                        <span><i class="bi bi-person me-1" aria-hidden="true"></i>Posted by <?= e($_b['posted_by_name']) ?> · <?= e(date('M j, g:i A', strtotime($_b['updated_at']))) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($_isAgent): ?>
                    <div class="mt-2 d-flex gap-2 flex-wrap">
                        <a href="/agent/banners/<?= (int) $_b['id'] ?>/edit"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit
                        </a>
                        <form method="POST" action="/agent/banners/<?= (int) $_b['id'] ?>/clear"
                              class="d-inline"
                              onsubmit="return confirm('Clear this incident banner? Portal users will no longer see it.');">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Clear
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button"
                    class="btn-close ld-banner-dismiss flex-shrink-0"
                    aria-label="Hide for me"
                    title="Hide for me"></button>
        </div>
    <?php endforeach; ?>
</div>
<style>
.ld-banner .ld-banner-body p:last-child,
.ld-banner .ld-banner-body ul:last-child,
.ld-banner .ld-banner-body ol:last-child { margin-bottom: 0; }
.ld-banner .ld-banner-body img { max-width: 100%; height: auto; }
.ld-banner .ld-banner-body a { font-weight: 600; }
</style>
<script>
(function () {
    var wrap  = document.getElementById('ld-status-banners');
    if (!wrap) return;
    var csrf  = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    wrap.querySelectorAll('.ld-banner').forEach(function (el) {
        var btn = el.querySelector('.ld-banner-dismiss');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var id = el.getAttribute('data-banner-id');
            // Fire-and-forget — server records the dismissal in $_SESSION so
            // it sticks across tabs/page loads. If the request fails, the
            // banner just reappears on the next load (acceptable).
            try {
                fetch('/api/banners/' + encodeURIComponent(id) + '/dismiss', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': csrf },
                    keepalive: true
                }).catch(function () {});
            } catch (e) { /* ignore */ }

            el.style.transition = 'opacity .15s ease';
            el.style.opacity = '0';
            setTimeout(function () {
                el.remove();
                // When the last banner goes, drop the wrapper too so its
                // mb-3 doesn't leave a gap at the top of the page.
                if (!wrap.querySelector('.ld-banner')) wrap.remove();
            }, 150);
        });
    });
})();
</script>
