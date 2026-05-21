<?php
$layout       = 'app';
$pageTitle    = 'Documentation';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Docs']];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-1">Documentation</h2>
    <p class="text-muted mb-0">Everything you need to know about configuring and using OpenHelpDesk.</p>
</div>

<div class="mb-4">
    <div class="input-group input-group-lg" style="max-width:540px;">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="docsSearch" class="form-control border-start-0 ps-0"
               placeholder="Search documentation&hellip;" autocomplete="off">
    </div>
    <div id="docsSearchResults" class="list-group mt-2 shadow-sm" style="max-width:540px;display:none;"></div>
</div>

<div class="row g-3" id="docsCards">
    <?php
    $cards = [
        ['url' => '/admin/docs/getting-started', 'icon' => 'bi-rocket-takeoff', 'color' => '#4f46e5', 'bg' => '#eff6ff',
         'title' => 'Getting Started',
         'desc'  => 'Initial setup, first login, and recommended configuration steps.'],
        ['url' => '/admin/docs/tickets',         'icon' => 'bi-ticket-detailed', 'color' => '#0891b2', 'bg' => '#f0f9ff',
         'title' => 'Tickets',
         'desc'  => 'Creating, managing and resolving tickets. Statuses, priorities and timelines.'],
        ['url' => '/admin/docs/users',           'icon' => 'bi-people',          'color' => '#16a34a', 'bg' => '#f0fdf4',
         'title' => 'Users & Roles',
         'desc'  => 'Admin, Agent and User roles. Adding staff, 2FA, audit log, and managing locations.'],
        ['url' => '/admin/docs/email',           'icon' => 'bi-envelope',        'color' => '#7c3aed', 'bg' => '#fdf4ff',
         'title' => 'Email & Notifications',
         'desc'  => 'SMTP setup, inbound email, customisable templates, hashtag commands, and notification preferences.'],
        ['url' => '/admin/docs/sla',             'icon' => 'bi-stopwatch',       'color' => '#dc2626', 'bg' => '#fff1f2',
         'title' => 'SLA Policies',
         'desc'  => 'Response and resolution targets, business hours integration, breach alerts.'],
        ['url' => '/admin/docs/automations',     'icon' => 'bi-lightning',       'color' => '#ca8a04', 'bg' => '#fefce8',
         'title' => 'Automations & Escalations',
         'desc'  => 'Event-based automation rules and time-based escalation rules including customer reminder emails.'],
        ['url' => '/admin/docs/ai',              'icon' => 'bi-cpu',             'color' => '#7c3aed', 'bg' => '#f5f3ff',
         'title' => 'AI Classification',
         'desc'  => 'Use Claude or GPT to read each ticket, infer required skills, drive sentiment-based priority bumps, and feed Skill-Based auto-assign.'],
        ['url' => '/admin/docs/branding',        'icon' => 'bi-palette',         'color' => '#db2777', 'bg' => '#fdf2f8',
         'title' => 'Branding',
         'desc'  => 'Logo, colour scheme, navbar gradient and timeline colours.'],
        ['url' => '/admin/docs/portal',          'icon' => 'bi-globe2',          'color' => '#0284c7', 'bg' => '#f0f9ff',
         'title' => 'Portal',
         'desc'  => 'How end users submit and track tickets. Portal URL and access.'],
        ['url' => '/admin/docs/import',          'icon' => 'bi-cloud-upload',    'color' => '#059669', 'bg' => '#f0fdf4',
         'title' => 'Importing Tickets',
         'desc'  => 'CSV import with flexible column mapping. Supported fields and data formats.'],
        ['url' => '/admin/docs/kb',              'icon' => 'bi-book',            'color' => '#9333ea', 'bg' => '#fdf4ff',
         'title' => 'Knowledge Base',
         'desc'  => 'Creating categories, folders and articles. Version history, feedback, and public KB.'],
        ['url' => '/admin/docs/sso',             'icon' => 'bi-shield-lock',     'color' => '#0369a1', 'bg' => '#f0f9ff',
         'title' => 'Single Sign-On',
         'desc'  => 'Microsoft 365 SSO setup, Azure app registration, location prompt, and troubleshooting.'],
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
        ["Configure SMTP email", "/admin/docs/getting-started", "Getting Started"],
        ["Add locations", "/admin/docs/getting-started", "Getting Started"],
        ["Create agent accounts", "/admin/docs/getting-started", "Getting Started"],
        ["Customise branding logo colour", "/admin/docs/getting-started", "Getting Started"],
        ["Reset to fresh state danger zone", "/admin/docs/getting-started", "Getting Started"],
        ["Creating tickets portal admin", "/admin/docs/tickets", "Tickets"],
        ["Ticket statuses open in progress pending resolved closed waiting", "/admin/docs/tickets", "Tickets"],
        ["Priorities urgency SLA", "/admin/docs/tickets", "Tickets"],
        ["Replies internal notes", "/admin/docs/tickets", "Tickets"],
        ["Assigning tickets agent", "/admin/docs/tickets", "Tickets"],
        ["On behalf of phone caller file ticket for requester", "/admin/docs/tickets#on-behalf-of", "Tickets"],
        ["Submitter audit trail submitted_by created_by separate", "/admin/docs/tickets#on-behalf-of", "Tickets"],
        ["Filed by staff on their behalf hint sidebar timeline", "/admin/docs/tickets#on-behalf-of", "Tickets"],
        ["Phone in helpdesk requester ownership confirmation email", "/admin/docs/tickets#on-behalf-of", "Tickets"],
        ["Picker search name email autocomplete clear", "/admin/docs/tickets#on-behalf-of", "Tickets"],
        ["Tags labels ticket", "/admin/docs/tickets", "Tickets"],
        ["Merging duplicate tickets", "/admin/docs/tickets", "Tickets"],
        ["Ticket timeline audit trail history", "/admin/docs/tickets", "Tickets"],
        ["SLA on tickets deadlines timers", "/admin/docs/tickets", "Tickets"],
        ["Bulk actions assign close merge delete", "/admin/docs/tickets", "Tickets"],
        ["Filter panel saved filters default preset", "/admin/docs/tickets", "Tickets"],
        ["Ticket templates shared portal", "/admin/docs/tickets", "Tickets"],
        ["Custom form fields workflows", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Ticket form builder edit add fields New Ticket form", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Pinned fields subject description always top", "/admin/docs/tickets#form-builder", "Tickets"],
        ["System fields ticket type location priority tags attachments", "/admin/docs/tickets#form-builder", "Tickets"],
        ["12 field types catalog reference text textarea dropdown date number decimal", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Dependent cascading dropdown 2 3 levels hierarchy region country city", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Date range from to picker custom field", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Text block read-only instructions warning paragraph form", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Image field upload form logo diagram JPEG PNG WebP", "/admin/docs/tickets#form-builder", "Tickets"],
        ["CC field email copy carbon recipients form", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Field visibility required optional hidden pill per ticket type", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Hide priority field per ticket type", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Per-type form layout each ticket type its own form builder", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Share field across ticket types add existing field", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Ticket type rail pick type form canvas builder", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Remove field from type delete field entirely preserve values", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Live preview iframe pane form builder portal", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Drag reorder fields form builder sort order", "/admin/docs/tickets#form-builder", "Tickets"],
        ["Direct link ticket type form portal URL share", "/admin/docs/portal#direct-links", "Portal"],
        ["Deep link type_id query parameter portal create ticket", "/admin/docs/portal#direct-links", "Portal"],
        ["Pre-select ticket type from URL link", "/admin/docs/portal#direct-links", "Portal"],
        ["Direct link column admin ticket types copy clipboard open new tab", "/admin/docs/portal#direct-links", "Portal"],
        ["Login return URL after sign in remember intended next", "/admin/docs/portal#direct-links", "Portal"],
        ["Anonymous visitor direct link bounce login then form", "/admin/docs/portal#direct-links", "Portal"],
        ["Open redirect protection relative path safe URL", "/admin/docs/portal#direct-links", "Portal"],
        ["next query parameter login redirect after authentication", "/admin/docs/portal#direct-links", "Portal"],
        ["Export tickets CSV download", "/admin/docs/tickets", "Tickets"],
        ["Concurrent viewer warning duplicate", "/admin/docs/tickets", "Tickets"],
        ["Per-page row count pagination", "/admin/docs/tickets", "Tickets"],
        ["Quick-assign agent from ticket list inline", "/admin/docs/tickets", "Tickets"],
        ["Inline type group change ticket list chevron", "/admin/docs/tickets", "Tickets"],
        ["Column picker agent dashboard recent tickets", "/admin/docs/tickets", "Tickets"],
        ["Attachments inline timeline entry", "/admin/docs/tickets", "Tickets"],
        ["System events hidden portal users timeline", "/admin/docs/tickets", "Tickets"],
        ["Group filter assignee dropdown members", "/admin/docs/tickets", "Tickets"],
        ["Portal user close edit own ticket self-service", "/admin/docs/portal", "Portal"],
        ["Rich text intro message email template CKEditor", "/admin/docs/email", "Email and Notifications"],
        ["Group alerts email template notification", "/admin/docs/email", "Email and Notifications"],
        ["Roles admin agent user overview", "/admin/docs/users", "Users and Roles"],
        ["Creating user accounts", "/admin/docs/users", "Users and Roles"],
        ["Self-registration portal signup", "/admin/docs/users", "Users and Roles"],
        ["Editing users profile", "/admin/docs/users", "Users and Roles"],
        ["Locations branch department", "/admin/docs/users", "Users and Roles"],
        ["Location ticket visibility supervisor", "/admin/docs/users", "Users and Roles"],
        ["Groups agent ticket visibility scoping", "/admin/docs/users", "Users and Roles"],
        ["Two-factor authentication 2FA TOTP authenticator", "/admin/docs/users", "Users and Roles"],
        ["Email notification preferences opt-in", "/admin/docs/users", "Users and Roles"],
        ["Admin user profile view tickets submitted", "/admin/docs/users", "Users and Roles"],
        ["Deleting users transfer records", "/admin/docs/users", "Users and Roles"],
        ["Dark mode light mode theme appearance", "/admin/docs/users", "Users and Roles"],
        ["Audit log admin actions", "/admin/docs/users", "Users and Roles"],
        ["Confidential groups security membership alerts", "/admin/docs/users", "Users and Roles"],
        ["Confidential ticket types redaction re-authentication", "/admin/docs/users", "Users and Roles"],
        ["Confidential flag removal tamper protection", "/admin/docs/users", "Users and Roles"],
        ["SMTP configuration mail server setup", "/admin/docs/email", "Email and Notifications"],
        ["Inbound email reply integration IMAP", "/admin/docs/email", "Email and Notifications"],
        ["Microsoft Graph API email 365 Exchange", "/admin/docs/email", "Email and Notifications"],
        ["Email reply hashtag commands resolve status priority", "/admin/docs/email", "Email and Notifications"],
        ["Customisable email templates tokens placeholders", "/admin/docs/email", "Email and Notifications"],
        ["When emails are sent notifications", "/admin/docs/email", "Email and Notifications"],
        ["SLA policies create configure", "/admin/docs/sla", "SLA Policies"],
        ["SLA timers first response resolution breach warning", "/admin/docs/sla", "SLA Policies"],
        ["Business hours timezone schedule", "/admin/docs/sla", "SLA Policies"],
        ["Pause resume SLA pending waiting", "/admin/docs/sla", "SLA Policies"],
        ["Automation rules triggers conditions actions", "/admin/docs/automations", "Automations and Escalations"],
        ["Escalation rules time-based inactivity", "/admin/docs/automations", "Automations and Escalations"],
        ["Customer reminder email follow-up", "/admin/docs/automations", "Automations and Escalations"],
        ["Auto-assign tickets group strategy round robin", "/admin/docs/automations#group-auto-assign", "Automations and Escalations"],
        ["Load-based assignment least loaded agent", "/admin/docs/automations#group-auto-assign", "Automations and Escalations"],
        ["Skill-based ticket routing required skills", "/admin/docs/automations#group-auto-assign", "Automations and Escalations"],
        ["First available agent online off duty shift", "/admin/docs/automations#group-auto-assign", "Automations and Escalations"],
        ["Auto-assignment fallback unassigned", "/admin/docs/automations#group-auto-assign", "Automations and Escalations"],
        ["Manual escalation paths per ticket type chain Tier", "/admin/docs/automations#escalation-paths", "Automations and Escalations"],
        ["Escalate button portal agent ticket reassign", "/admin/docs/automations#escalation-paths", "Automations and Escalations"],
        ["Skip current assignee escalation level matrix", "/admin/docs/automations#escalation-paths", "Automations and Escalations"],
        ["Escalation watcher 403 access notification email link", "/admin/docs/automations#escalation-paths", "Automations and Escalations"],
        ["Stale ticket notifications threshold reminder cron", "/admin/docs/automations#stale-tickets", "Automations and Escalations"],
        ["Stale recheck hours re-notify dedup", "/admin/docs/automations#stale-tickets", "Automations and Escalations"],
        ["Per-type stale threshold override ticket type", "/admin/docs/automations#stale-tickets", "Automations and Escalations"],
        ["Agent skills catalog mortarboard expertise", "/admin/docs/users", "Users and Roles"],
        ["Required skills per ticket type", "/admin/docs/tickets", "Tickets"],
        ["Agent availability flag online off duty toggle", "/admin/docs/users", "Users and Roles"],
        ["AI classification ticket routing Claude GPT Anthropic OpenAI", "/admin/docs/ai", "AI Classification"],
        ["AI skill-based auto-assign LLM model dropdown", "/admin/docs/ai", "AI Classification"],
        ["AI confidence threshold provider key API settings", "/admin/docs/ai", "AI Classification"],
        ["AI sentiment priority bump angry urgent frustrated", "/admin/docs/ai", "AI Classification"],
        ["AI override re-classify backfill existing tickets", "/admin/docs/ai", "AI Classification"],
        ["AI confidential ticket privacy never sent third party", "/admin/docs/ai", "AI Classification"],
        ["Test connection refresh model list anthropic openai", "/admin/docs/ai", "AI Classification"],
        ["AI debug page raw HTTP response status headers body", "/admin/docs/ai", "AI Classification"],
        ["AI testing classification single ticket smoke batch backfill", "/admin/docs/ai", "AI Classification"],
        ["AI workspace spend cap credit balance too low gotcha", "/admin/docs/ai", "AI Classification"],
        ["Claude model cost comparison Haiku Sonnet Opus pricing", "/admin/docs/ai", "AI Classification"],
        ["No Wrong Door AI routes ticket to best group", "/admin/docs/ai#no-wrong-door", "AI Classification"],
        ["AI group routing pick department team queue from description", "/admin/docs/ai#no-wrong-door", "AI Classification"],
        ["Don't know who handles this generic catch-all ticket type", "/admin/docs/ai#no-wrong-door", "AI Classification"],
        ["ai_route_group flag ticket type signpost split icon", "/admin/docs/ai#no-wrong-door", "AI Classification"],
        ["AI Group Routing card audit confidence reasoning ticket detail", "/admin/docs/ai#no-wrong-door", "AI Classification"],
        ["Group description used as AI routing signal patron triage", "/admin/docs/ai#no-wrong-door", "AI Classification"],
        ["Fallback queue Default Group No Wrong Door team unmatched", "/admin/docs/ai#no-wrong-door", "AI Classification"],
        ["Group manager delegate skills team Manage My Team", "/admin/docs/users#group-managers", "Users and Roles"],
        ["Skill scope global group-owned ownership delegation", "/admin/docs/users#group-managers", "Users and Roles"],
        ["Branch lead supervisor curate skills team members", "/admin/docs/users#group-managers", "Users and Roles"],
        ["is_manager flag group user map manager checkbox", "/admin/docs/users#group-managers", "Users and Roles"],
        ["Plain-language portal vocabulary help request library", "/admin/docs/portal", "Portal"],
        ["Portal label customisation labels.default.json edit", "/admin/docs/portal", "Portal"],
        ["Requester portal escalate own ticket button", "/admin/docs/portal", "Portal"],
        ["Escalation level badge requester visibility portal", "/admin/docs/portal", "Portal"],
        ["What happens next callout portal submitted", "/admin/docs/portal", "Portal"],
        ["Requester submission acknowledgement assignment email", "/admin/docs/portal", "Portal"],
        ["Location ticket visibility opt-out per type hide collections HR payroll", "/admin/docs/tickets", "Tickets"],
        ["Logo upload branding", "/admin/docs/branding", "Branding"],
        ["Colour scheme primary color navbar gradient", "/admin/docs/branding", "Branding"],
        ["Application name helpdesk name", "/admin/docs/branding", "Branding"],
        ["Portal URL end users submit tickets", "/admin/docs/portal", "Portal"],
        ["CSAT satisfaction survey", "/admin/docs/portal", "Portal"],
        ["Import tickets CSV column mapping", "/admin/docs/import", "Importing Tickets"],
        ["Knowledge base categories folders articles", "/admin/docs/kb", "Knowledge Base"],
        ["Public knowledge base no login", "/admin/docs/kb", "Knowledge Base"],
        ["Article feedback ratings helpful", "/admin/docs/kb", "Knowledge Base"],
        ["Article version history restore revision", "/admin/docs/kb", "Knowledge Base"],
        ["KB article suggestions ticket creation", "/admin/docs/kb", "Knowledge Base"],
        ["Import KB articles CSV bulk upload", "/admin/docs/kb", "Knowledge Base"],
        ["Single sign-on SSO enable disable", "/admin/docs/sso", "Single Sign-On"],
        ["Microsoft 365 Azure AD login OAuth", "/admin/docs/sso", "Single Sign-On"],
        ["Register Azure app tenant client ID secret", "/admin/docs/sso", "Single Sign-On"],
        ["SSO redirect URI callback", "/admin/docs/sso", "Single Sign-On"],
        ["API permissions openid User.Read", "/admin/docs/sso", "Single Sign-On"],
        ["SSO location prompt pick location", "/admin/docs/sso", "Single Sign-On"],
        ["SSO debug logging troubleshoot AADSTS", "/admin/docs/sso", "Single Sign-On"],
        ["Mark comment as solution go to solution jump link answer", "/admin/docs/tickets#solution", "Tickets"],
        ["Hide system notes AI notes from timeline declutter switch", "/admin/docs/tickets", "Tickets"],
        ["Enable disable SLA tracking site-wide toggle", "/admin/docs/sla", "SLA Policies"],
        ["Turn off SLA timers due dates whole site", "/admin/docs/sla", "SLA Policies"],
        ["Forgot password self-service reset link login page", "/admin/docs/users", "Users and Roles"],
        ["Reset forgotten password email token expires", "/admin/docs/users", "Users and Roles"],
        ["Audit log prune delete old entries retention cutoff", "/admin/docs/users#audit-log", "Users and Roles"],
        ["Audit log source filter ticket timeline history unified pane", "/admin/docs/users#audit-log", "Users and Roles"],
        ["Audit log coverage config CRUD field diff old new", "/admin/docs/users#audit-log", "Users and Roles"],
        ["AI duplicate ticket detection on submit warning", "/admin/docs/ai#duplicate-detection", "AI Classification"],
        ["Duplicate check per ticket type confidence threshold", "/admin/docs/ai#duplicate-detection", "AI Classification"],
    ];
    var input = document.getElementById("docsSearch");
    var box = document.getElementById("docsSearchResults");
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
