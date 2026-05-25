<?php
$layout       = 'app';
$pageTitle    = 'Agent Help';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help']];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Agent Help</h2>
    <p class="text-muted mb-0">Guides for everything you can do as an agent in OpenHelpDesk.</p>
</div>

<div class="mb-4">
    <div class="input-group input-group-lg" style="max-width:540px;">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="helpSearch" class="form-control border-start-0 ps-0"
               placeholder="Search help&hellip;" autocomplete="off">
    </div>
    <div id="helpSearchResults" class="list-group mt-2 shadow-sm" style="max-width:540px;display:none;"></div>
</div>

<div class="row g-3" id="helpCards">
    <?php
    $cards = [
        ['url' => '/agent/help/dashboard',          'icon' => 'bi-speedometer2',     'color' => '#4f46e5', 'bg' => '#eff6ff',
         'title' => 'Dashboard',
         'desc'  => 'Your personal overview: ticket stats, recent tickets, and quick navigation.'],
        ['url' => '/agent/help/ticket-list',        'icon' => 'bi-list-ul',          'color' => '#0891b2', 'bg' => '#f0f9ff',
         'title' => 'Ticket List & Filters',
         'desc'  => 'Browse, search and filter tickets. Save filter presets, customise columns, and use inline actions.'],
        ['url' => '/agent/help/working-tickets',    'icon' => 'bi-ticket-detailed',  'color' => '#16a34a', 'bg' => '#f0fdf4',
         'title' => 'Working on Tickets',
         'desc'  => 'Replying, adding notes, updating fields, attaching files, merging, splitting, and more.'],
        ['url' => '/agent/help/floor',              'icon' => 'bi-grid-1x2',         'color' => '#7c3aed', 'bg' => '#f5f3ff',
         'title' => 'Floor Mode',
         'desc'  => 'Tablet-friendly queue and ticket view for working from the floor: photos, voice dictation, claim & resolve in one tap.'],
        ['url' => '/agent/help/canned-responses',   'icon' => 'bi-chat-square-text', 'color' => '#ca8a04', 'bg' => '#fefce8',
         'title' => 'Canned Responses',
         'desc'  => 'Create and manage reusable reply templates with dynamic tokens.'],
    ];
    foreach ($cards as $card): ?>
    <div class="col-md-6 col-xl-4">
        <a href="<?= e($card['url']) ?>" class="card border-0 shadow-sm h-100 text-decoration-none" style="transition:box-shadow .15s;">
            <div class="card-body d-flex gap-3">
                <div class="flex-shrink-0 rounded d-flex align-items-center justify-content-center"
                     style="width:44px;height:44px;background:<?= $card['bg'] ?>;font-size:1.3rem;color:<?= $card['color'] ?>;">
                    <i class="bi <?= $card['icon'] ?>"></i>
                </div>
                <div>
                    <div class="fw-semibold text-body mb-1"><?= e($card['title']) ?></div>
                    <div class="text-muted small"><?= e($card['desc']) ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<script>
(function () {
    var idx = [
        ["Dashboard stats unassigned my tickets pending resolved today", "/agent/help/dashboard", "Dashboard"],
        ["Recent tickets widget column picker inline actions dashboard", "/agent/help/dashboard", "Dashboard"],
        ["Ticket list browse search filter", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Filter status priority type location agent group", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Save filter preset default shared", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Column picker customise visible columns", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Quick-assign inline agent type group chevron dropdown", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Bulk actions assign close merge delete", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Watched tickets filter my tickets", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Reply public comment ticket", "/agent/help/working-tickets", "Working on Tickets"],
        ["Internal note private agents only", "/agent/help/working-tickets", "Working on Tickets"],
        ["Forward ticket email", "/agent/help/working-tickets", "Working on Tickets"],
        ["Send and set status resolve close pending waiting", "/agent/help/working-tickets", "Working on Tickets"],
        ["Update status priority type group assigned agent", "/agent/help/working-tickets", "Working on Tickets"],
        ["Attach file upload attachment", "/agent/help/working-tickets", "Working on Tickets"],
        ["Canned response insert reply", "/agent/help/working-tickets", "Working on Tickets"],
        ["CC carbon copy add remove user", "/agent/help/working-tickets", "Working on Tickets"],
        ["Watch unwatch ticket notifications", "/agent/help/working-tickets", "Working on Tickets"],
        ["Merge tickets duplicate combine", "/agent/help/working-tickets", "Working on Tickets"],
        ["Split ticket move comments new ticket", "/agent/help/working-tickets", "Working on Tickets"],
        ["SLA timer first response resolution breach", "/agent/help/working-tickets", "Working on Tickets"],
        ["Timeline audit trail history replies notes system events attachments inline", "/agent/help/working-tickets", "Working on Tickets"],
        ["Canned responses create edit delete personal global", "/agent/help/canned-responses", "Canned Responses"],
        ["Tokens dynamic placeholders canned response customer name ticket", "/agent/help/canned-responses", "Canned Responses"],
        ["Floor mode tablet phone touch queue cards", "/agent/help/floor", "Floor Mode"],
        ["Quick create photo camera voice dictate barcode scan asset", "/agent/help/floor", "Floor Mode"],
        ["Floor ticket detail claim release status resolve in progress pending reopen", "/agent/help/floor", "Floor Mode"],
        ["Full ticket details floor close X return", "/agent/help/floor", "Floor Mode"],
        ["Resize columns drag header edge grip ticket list", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Column width remembered per page browser localStorage", "/agent/help/ticket-list", "Ticket List & Filters"],
    ];
    var input = document.getElementById("helpSearch");
    var box = document.getElementById("helpSearchResults");
    input.addEventListener("input", function () {
        var q = this.value.trim().toLowerCase();
        box.innerHTML = "";
        if (q.length < 2) { box.style.display = "none"; return; }
        var m = idx.filter(function (e) { return e[0].toLowerCase().indexOf(q) !== -1 || e[2].toLowerCase().indexOf(q) !== -1; }).slice(0, 8);
        if (!m.length) { box.style.display = "none"; return; }
        m.forEach(function (r) {
            var a = document.createElement("a"); a.href = r[1];
            a.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center";
            a.innerHTML = "<span>" + esc(r[0]) + "</span><small class=\"text-muted ms-3 text-nowrap\">" + esc(r[2]) + "</small>";
            box.appendChild(a);
        });
        box.style.display = "block";
    });
    document.addEventListener("click", function (e) {
        if (!input.contains(e.target) && !box.contains(e.target)) box.style.display = "none";
    });
    function esc(s) { return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }
})();
</script>
