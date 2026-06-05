<?php
/**
 * Segmented control for switching the ticket-list layout (Table / Compact / Card).
 * Included in the agent and admin ticket index toolbars.
 *
 * Expects $ticketView (the user's current layout). Saves the choice via the
 * existing /profile/setting AJAX endpoint, then reloads so the server can
 * render the chosen layout. The current query string (filters, sort) is
 * preserved across the reload.
 */
$_tvCurrent = $ticketView ?? 'table';
$_tvModes = [
    'table' => ['icon' => 'bi-table',      'label' => 'Table',   'hint' => 'Sortable grid with inline controls'],
    'inbox' => ['icon' => 'bi-inbox',      'label' => 'Compact', 'hint' => 'Email-style compact list'],
    'card'  => ['icon' => 'bi-grid-1x2',   'label' => 'Card',    'hint' => 'Roomy cards with full details'],
];
?>
<div class="btn-group btn-group-sm" role="group" aria-label="Ticket list layout"
     data-ticket-view-switcher data-csrf="<?= e(csrfToken()) ?>">
    <?php foreach ($_tvModes as $_tvKey => $_tvMode):
        $_tvActive = ($_tvCurrent === $_tvKey); ?>
    <button type="button"
            class="btn <?= $_tvActive ? 'text-white' : 'btn-outline-secondary' ?>"
            <?= $_tvActive ? 'style="background:var(--ld-primary);border-color:var(--ld-primary);" aria-pressed="true"' : 'aria-pressed="false"' ?>
            data-ticket-view="<?= $_tvKey ?>"
            aria-label="<?= e($_tvMode['label']) ?> view"
            title="<?= e($_tvMode['label'] . ' — ' . $_tvMode['hint']) ?>">
        <i class="bi <?= $_tvMode['icon'] ?>"></i>
    </button>
    <?php endforeach; ?>
</div>
<script>
(function () {
    // Guard against double-binding if the partial is ever included twice.
    if (window.__ticketViewSwitcherBound) return;
    window.__ticketViewSwitcherBound = true;

    document.querySelectorAll('[data-ticket-view-switcher]').forEach(function (group) {
        var csrf = group.getAttribute('data-csrf');
        group.querySelectorAll('[data-ticket-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var view = btn.getAttribute('data-ticket-view');
                if (btn.getAttribute('aria-pressed') === 'true') return; // already active

                var body = new URLSearchParams();
                body.set('_token', csrf);
                body.set('field', 'ticket_view');
                body.set('value', view);

                group.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

                fetch('/profile/setting', {
                    method:  'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body:    body,
                })
                    .then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); })
                    .then(function (data) {
                        if (data && data.ok) {
                            window.location.reload();
                        } else {
                            group.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                            alert((data && data.message) || 'Could not switch view.');
                        }
                    })
                    .catch(function () {
                        group.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                        alert('Could not switch view.');
                    });
            });
        });
    });
})();
</script>
