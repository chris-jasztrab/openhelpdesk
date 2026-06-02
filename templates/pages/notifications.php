<?php
$layout       = 'app';
$pageTitle    = 'Notifications';
$sidebarItems = $sidebarFn();
$breadcrumbs  = [
    ['label' => 'Notifications'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Notifications</h2>
    <form method="POST" action="/notifications/read-all" class="d-inline js-notif-read-all <?= empty($notifications) ? 'd-none' : '' ?>" id="ld-notif-readall">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-check2-all me-1"></i>Mark All Read
        </button>
    </form>
</div>

<div class="card border-0 shadow-sm" id="ld-notif-card">
    <div id="ld-notif-list">
        <?php require ROOT_DIR . '/templates/partials/notifications-list.php'; ?>
    </div>
</div>

<script>
(function () {
    var list    = document.getElementById('ld-notif-list');
    var readAll = document.getElementById('ld-notif-readall');
    if (!list) return;

    var POLL_MS = 15000;
    var lastHtml = list.innerHTML;

    function csrfToken() {
        var f = list.querySelector('input[name="_token"]') ||
                (readAll ? readAll.querySelector('input[name="_token"]') : null);
        return f ? f.value : '';
    }

    // Replace the feed body only when it actually changed, so an idle page
    // doesn't flicker or lose focus every poll.
    function applyFeed(data) {
        if (typeof data.html === 'string' && data.html !== lastHtml) {
            list.innerHTML = data.html;
            lastHtml = data.html;
        }
        if (readAll) {
            readAll.classList.toggle('d-none', !data.has_items);
        }
    }

    function refresh() {
        if (document.hidden) return;
        fetch('/notifications/feed', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(applyFeed)
            .catch(function () {});
    }

    function postRead(url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: '_token=' + encodeURIComponent(csrfToken())
        }).then(function () { return refresh(); });
    }

    // Event delegation: handles current and re-rendered "mark read" forms.
    list.addEventListener('submit', function (e) {
        var form = e.target.closest('.js-notif-read');
        if (!form) return;
        e.preventDefault();
        postRead(form.getAttribute('action'));
    });

    if (readAll) {
        readAll.addEventListener('submit', function (e) {
            e.preventDefault();
            postRead(readAll.getAttribute('action'));
        });
    }

    setInterval(refresh, POLL_MS);
    // Catch up immediately when the tab regains focus.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refresh();
    });
})();
</script>
