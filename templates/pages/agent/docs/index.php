<?php
$layout       = 'app';
$pageTitle    = 'Agent Help';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help']];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Agent Help</h2>
    <p class="text-muted mb-0">Guides for everything you can do as a staff member in OpenHelpDesk.</p>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start" role="note">
    <i class="bi bi-sliders fs-5 mt-1"></i>
    <div>
        <strong>Where to find your tools.</strong> The left sidebar holds your day-to-day work &mdash; <strong>Dashboard</strong>, <strong>Tickets</strong>, <strong>Floor mode</strong> (on touch screens), the <strong>Knowledge Base</strong>, <strong><i class="bi bi-sliders"></i> Settings</strong> and <strong>Help</strong>. Under Settings you'll always find <strong>Status Banners</strong>, plus anything else your permission level lets you manage &mdash; such as Ticket Templates, Knowledge Base articles, Recurring Tickets or Reports. Its menu only lists areas you can open. Your account menu (your name, top-right) holds the <strong>Wallboard</strong>, <strong>My Profile</strong> (where your Canned Responses live), <strong>Restart Tour</strong>, and <strong>Manage My Team</strong> if you manage a group.
    </div>
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
         'title' => 'Dashboard & Getting Around',
         'desc'  => 'Your stat cards and recent tickets, the sidebar and top bar, search, the guided tour and installing the app.'],
        ['url' => '/agent/help/wallboard',          'icon' => 'bi-display',          'color' => '#2563eb', 'bg' => '#eff6ff',
         'title' => 'Live Wallboard',
         'desc'  => 'Real-time, customisable operations board: KPIs, charts and live queues that auto-refresh — great on a wall screen.'],
        ['url' => '/agent/help/ticket-list',        'icon' => 'bi-list-ul',          'color' => '#0891b2', 'bg' => '#f0f9ff',
         'title' => 'Ticket List & Filters',
         'desc'  => 'Which tickets you see, layouts, filters and saved filters, quick edits, bulk actions and export.'],
        ['url' => '/agent/help/kanban',             'icon' => 'bi-kanban',           'color' => '#db2777', 'bg' => '#fdf2f8',
         'title' => 'Kanban Board',
         'desc'  => 'Drag-and-drop board view: group tickets by status, priority or assignee, or build your own custom board.'],
        ['url' => '/agent/help/creating-tickets',   'icon' => 'bi-plus-square',      'color' => '#0d9488', 'bg' => '#f0fdfa',
         'title' => 'Creating Tickets',
         'desc'  => 'The New Ticket form, templates, filing on behalf of someone, CC, duplicate warnings and drafts.'],
        ['url' => '/agent/help/working-tickets',    'icon' => 'bi-ticket-detailed',  'color' => '#16a34a', 'bg' => '#f0fdf4',
         'title' => 'Working on Tickets',
         'desc'  => 'Replying, notes, forwarding, closing options, time tracking, escalating, merging, splitting and more.'],
        ['url' => '/agent/help/sla',                'icon' => 'bi-stopwatch',        'color' => '#dc2626', 'bg' => '#fff1f2',
         'title' => 'SLAs & Response Times',
         'desc'  => 'What the SLA clocks mean, how they run against business hours, when they warn or breach, and how to stay on track.'],
        ['url' => '/agent/help/floor',              'icon' => 'bi-grid-1x2',         'color' => '#7c3aed', 'bg' => '#f5f3ff',
         'title' => 'Floor Mode',
         'desc'  => 'Tablet-friendly queue and ticket view for working from the floor: photos, voice dictation, claim & resolve in one tap.'],
        ['url' => '/agent/help/canned-responses',   'icon' => 'bi-chat-square-text', 'color' => '#ca8a04', 'bg' => '#fefce8',
         'title' => 'Canned Responses',
         'desc'  => 'Create and manage reusable replies with tokens, and insert them into a reply.'],
        ['url' => '/agent/help/knowledge-base',     'icon' => 'bi-book',             'color' => '#9333ea', 'bg' => '#faf5ff',
         'title' => 'Knowledge Base',
         'desc'  => 'Browse and search articles, rate them, and turn a solved ticket into an article.'],
        ['url' => '/agent/help/profile',            'icon' => 'bi-person-gear',      'color' => '#4338ca', 'bg' => '#eef2ff',
         'title' => 'Profile & Notifications',
         'desc'  => 'Your settings, email choices, the notification bell, replying by email, two-factor sign-in and out-of-office.'],
        ['url' => '/agent/help/status-banners',     'icon' => 'bi-megaphone',        'color' => '#ea580c', 'bg' => '#fff7ed',
         'title' => 'Status Banners',
         'desc'  => 'Post an outage or maintenance notice at the top of every page, for everyone or for chosen branches.'],
        ['url' => '/agent/help/manager-tools',      'icon' => 'bi-stars',            'color' => '#0284c7', 'bg' => '#f0f9ff',
         'title' => 'Manager Tools',
         'desc'  => 'For group managers: assign skills to your team, curate group skills, and find reports.'],
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
        ["Dashboard stat cards unassigned my tickets pending resolved today escalated to me", "/agent/help/dashboard", "Dashboard & Getting Around"],
        ["Recent tickets table sort columns quick edit dashboard", "/agent/help/dashboard", "Dashboard & Getting Around"],
        ["Sidebar top bar navigation account menu settings help where is", "/agent/help/dashboard", "Dashboard & Getting Around"],
        ["Search slash keyboard shortcut / escape contacts tickets knowledge base", "/agent/help/dashboard", "Dashboard & Getting Around"],
        ["Guided tour restart tour walkthrough onboarding", "/agent/help/dashboard", "Dashboard & Getting Around"],
        ["Install app home screen PWA phone tablet desktop", "/agent/help/dashboard", "Dashboard & Getting Around"],
        ["Which tickets can I see visibility groups view all", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Ticket list browse search filter export CSV", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Ticket list layout view table compact card switcher", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Compact view from subject hover card reply forward add note person sender", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Open tickets and mentions person @mention page submitted by", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Filter status priority type location assigned to group watching attachment escalated", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Applied filters pills clear all remove filter", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Save current filter preset default shared team", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Column picker columns confidential column", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Quick edit inline status priority type assigned group chevron", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Bulk actions assign close merge status priority group delete", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Draft badge opened by being replied to amber subject", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Resize columns drag header edge width", "/agent/help/ticket-list", "Ticket List & Filters"],
        ["Wallboard live dashboard real-time auto refresh kpi widgets customize fullscreen", "/agent/help/wallboard", "Live Wallboard"],
        ["Wallboard widgets open unassigned SLA breached created resolved CSAT trend agent workload queue drill down", "/agent/help/wallboard", "Live Wallboard"],
        ["Wallboard filters location group type priority range interval pause TV screen columns", "/agent/help/wallboard", "Live Wallboard"],
        ["Kanban board drag drop view columns", "/agent/help/kanban", "Kanban Board"],
        ["Built-in board group by status priority assignee", "/agent/help/kanban", "Kanban Board"],
        ["Custom board buckets unsorted personal organiser share with team", "/agent/help/kanban", "Kanban Board"],
        ["Board filters my team teammates groups 500", "/agent/help/kanban", "Kanban Board"],
        ["New ticket create form template type priority", "/agent/help/creating-tickets", "Creating Tickets"],
        ["On behalf of phone walk-up requester filed by", "/agent/help/creating-tickets", "Creating Tickets"],
        ["Duplicate warning create anyway different issue", "/agent/help/creating-tickets", "Creating Tickets"],
        ["Ticket draft restored discard service level targets what happens next", "/agent/help/creating-tickets", "Creating Tickets"],
        ["Ticket layout header columns details back button", "/agent/help/working-tickets", "Working on Tickets"],
        ["Reply public comment internal note add note", "/agent/help/working-tickets", "Working on Tickets"],
        ["@mention colleague notify mention", "/agent/help/working-tickets", "Working on Tickets"],
        ["Attach file upload attachment paste image", "/agent/help/working-tickets", "Working on Tickets"],
        ["Send and change status resolved closed pending waiting", "/agent/help/working-tickets", "Working on Tickets"],
        ["Forward ticket external internal contacts permission third party vendor", "/agent/help/working-tickets", "Working on Tickets"],
        ["External contact reply badge timeline", "/agent/help/working-tickets", "Working on Tickets"],
        ["Reply draft autosave restored unsent discard", "/agent/help/working-tickets", "Working on Tickets"],
        ["Undo send countdown escape", "/agent/help/working-tickets", "Working on Tickets"],
        ["Live updates presence viewing replying collision post anyway", "/agent/help/working-tickets", "Working on Tickets"],
        ["Update ticket status priority assigned to group type due date SLA target", "/agent/help/working-tickets", "Working on Tickets"],
        ["Close button close without notifying feedback duplicate won't fix no response", "/agent/help/working-tickets", "Working on Tickets"],
        ["Close and reopen on reply follow-up reminder create KB article", "/agent/help/working-tickets", "Working on Tickets"],
        ["Resolution note before you close what resolved this", "/agent/help/working-tickets", "Working on Tickets"],
        ["Time tracking timer log time hours", "/agent/help/working-tickets", "Working on Tickets"],
        ["CC carbon copy add remove watch watching", "/agent/help/working-tickets", "Working on Tickets"],
        ["Escalate escalation path level history", "/agent/help/working-tickets", "Working on Tickets"],
        ["Timeline order newest oldest older updates", "/agent/help/working-tickets", "Working on Tickets"],
        ["Mark as solution go to solution unmark", "/agent/help/working-tickets", "Working on Tickets"],
        ["Merge tickets duplicate primary conversation moves", "/agent/help/working-tickets", "Working on Tickets"],
        ["Split ticket move replies new ticket", "/agent/help/working-tickets", "Working on Tickets"],
        ["Satisfaction survey CSAT rating similar past tickets", "/agent/help/working-tickets", "Working on Tickets"],
        ["Confidential ticket access group re-authenticate", "/agent/help/working-tickets", "Working on Tickets"],
        ["What is an SLA service level agreement response time meaning", "/agent/help/sla", "SLAs & Response Times"],
        ["SLA first response resolution deadline due clock", "/agent/help/sla", "SLAs & Response Times"],
        ["SLA on track warning breached paused badge", "/agent/help/sla", "SLAs & Response Times"],
        ["SLA business hours counted days holidays", "/agent/help/sla", "SLAs & Response Times"],
        ["SLA pause resume pending waiting on customer third party reopen", "/agent/help/sla", "SLAs & Response Times"],
        ["Floor mode tablet phone touch queue cards", "/agent/help/floor", "Floor Mode"],
        ["Quick create photo camera voice dictate barcode scan asset", "/agent/help/floor", "Floor Mode"],
        ["Floor ticket claim release in progress pending resolve reopen", "/agent/help/floor", "Floor Mode"],
        ["Canned responses create edit delete personal global", "/agent/help/canned-responses", "Canned Responses"],
        ["Tokens placeholders canned response customer name ticket", "/agent/help/canned-responses", "Canned Responses"],
        ["Knowledge base articles search browse helpful rating", "/agent/help/knowledge-base", "Knowledge Base"],
        ["Write edit KB article manage articles", "/agent/help/knowledge-base", "Knowledge Base"],
        ["Profile name dark mode password", "/agent/help/profile", "Profile & Notifications"],
        ["Email notifications preferences turn off emails", "/agent/help/profile", "Profile & Notifications"],
        ["Notification bell mark read alerts", "/agent/help/profile", "Profile & Notifications"],
        ["Reply by email commands hashtag #close #resolve", "/agent/help/profile", "Profile & Notifications"],
        ["Two-factor authentication 2FA authenticator app", "/agent/help/profile", "Profile & Notifications"],
        ["Out of office vacation away Outlook automatic reply", "/agent/help/profile", "Profile & Notifications"],
        ["Status banner incident outage notice post clear", "/agent/help/status-banners", "Status Banners"],
        ["Manage my team manager skills group", "/agent/help/manager-tools", "Manager Tools"],
        ["Reports time spent agent time scheduled reports", "/agent/help/manager-tools", "Manager Tools"],
        ["Settings icon sidebar templates knowledge base recurring tickets reports permissions where to find tools", "/agent/help", "Finding Your Tools"],
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
