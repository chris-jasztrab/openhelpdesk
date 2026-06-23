<?php
/**
 * Segmented control for switching the ticket-list layout (Table / Compact / Card).
 * Included in the agent and admin ticket index toolbars and the kanban board.
 *
 * Expects $ticketView (the user's current layout). Saves the choice via the
 * existing /profile/setting AJAX endpoint, then reloads so the server can
 * render the chosen layout. The current query string (filters, sort) is
 * preserved across the reload.
 *
 * Optional overrides:
 *   $tvBoardUrl  — if set, append a Kanban button to the same segmented control
 *                  that links here. Pass it on every page so the control is one
 *                  consistent unit (Table / Compact / Card / Kanban) everywhere.
 *   $tvActiveKey — which button to highlight; pass 'board' (matches none of the
 *                  list modes) so the Kanban button is the selected one and no
 *                  list layout appears active. Used by the kanban board, which
 *                  is not itself a list layout.
 *   $tvNavBase   — if set, navigate here after saving instead of reloading the
 *                  current page (so the switcher can jump from the board back to
 *                  the list rendered in the chosen layout).
 */
$_tvCurrent  = $tvActiveKey ?? ($ticketView ?? 'table');
$_tvNavBase  = $tvNavBase ?? null;
$_tvBoardUrl = $tvBoardUrl ?? null;
$_tvModes = [
    'table' => ['icon' => 'bi-table',      'label' => 'Table',   'hint' => 'Sortable grid with inline controls'],
    'inbox' => ['icon' => 'bi-inbox',      'label' => 'Compact', 'hint' => 'Email-style compact list'],
    'card'  => ['icon' => 'bi-grid-1x2',   'label' => 'Card',    'hint' => 'Roomy cards with full details'],
];
?>
<div class="btn-group btn-group-sm" role="group" aria-label="Ticket list layout"
     data-ticket-view-switcher data-csrf="<?= e(csrfToken()) ?>"
     data-nav-base="<?= e($_tvNavBase ?? '') ?>">
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
    <?php if ($_tvBoardUrl !== null):
        // Kanban lives in the same segmented control, but it's a separate page
        // (a link), not one of the saved list layouts.
        $_tvKActive = ($_tvCurrent === 'board'); ?>
    <a class="btn <?= $_tvKActive ? 'text-white' : 'btn-outline-secondary' ?>"
       <?= $_tvKActive ? 'style="background:var(--ld-primary);border-color:var(--ld-primary);" aria-pressed="true"' : 'aria-pressed="false"' ?>
       href="<?= e($_tvBoardUrl) ?>"
       aria-label="Kanban board view"
       title="Kanban — drag-and-drop board">
        <i class="bi bi-kanban"></i>
    </a>
    <?php endif; ?>
</div>
<script>
(function () {
    // Guard against double-binding if the partial is ever included twice.
    if (window.__ticketViewSwitcherBound) return;
    window.__ticketViewSwitcherBound = true;

    document.querySelectorAll('[data-ticket-view-switcher]').forEach(function (group) {
        var csrf    = group.getAttribute('data-csrf');
        var navBase = group.getAttribute('data-nav-base') || '';
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
                            if (navBase) { window.location = navBase; }
                            else { window.location.reload(); }
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
