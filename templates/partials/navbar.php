<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="height:var(--ld-navbar-height,56px);" aria-label="Primary">
    <div class="container-fluid">
        <?php $brandLogo = getSetting('branding_logo', ''); $brandName = getSetting('branding_app_name', 'OpenHelpDesk'); $brandIcon = getSetting('branding_navbar_icon', 'bi-headset'); ?>
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <?php if ($brandLogo && file_exists(ROOT_DIR . '/public/uploads/branding/' . $brandLogo)): ?>
                <img src="/uploads/branding/<?= e($brandLogo) ?>" alt="" style="height:32px;">
            <?php else: ?>
                <i class="bi <?= e($brandIcon) ?> fs-4" aria-hidden="true"></i>
            <?php endif; ?>
            <span><?= e($brandName) ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon" aria-hidden="true"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (Auth::check()): ?>
            <ul class="navbar-nav me-auto">
                <?php if (Auth::isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin') ? 'active' : '' ?>" href="/admin" <?= isActive('/admin') ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Admin
                    </a>
                </li>
                <?php endif; ?>
                <?php if (Auth::isStaff()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/agent') ? 'active' : '' ?>" href="/agent" <?= isActive('/agent') ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-headset me-1" aria-hidden="true"></i>Agent Panel
                    </a>
                </li>
                <?php endif; ?>
                <?php
                if (Auth::isAdmin()) {
                    $_helpUrl = '/admin/docs';
                } elseif (Auth::isStaff()) {
                    $_helpUrl = '/agent/help';
                } else {
                    $_helpUrl = '/portal/help';
                }
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= isActive($_helpUrl) ? 'active' : '' ?>" id="tour-nav-help" href="<?= e($_helpUrl) ?>" <?= isActive($_helpUrl) ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-life-preserver me-1" aria-hidden="true"></i><?= e(label('portal.nav.help', 'Help')) ?>
                    </a>
                </li>
            </ul>

            <!-- Global Search -->
            <div class="position-relative mx-3 flex-grow-1 d-none d-lg-block" style="max-width:420px;" id="ld-search-wrap"
                 data-role="<?= e(Auth::isAdmin() ? 'admin' : (Auth::isStaff() ? 'agent' : 'user')) ?>" role="search">
                <label for="ld-search-input" class="visually-hidden">Search tickets, contacts, and knowledge base</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text border-0 text-white text-opacity-50" style="background:rgba(255,255,255,.1);" aria-hidden="true">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="ld-search-input" class="form-control form-control-sm border-0 text-white"
                           placeholder="Search... ( / )" autocomplete="off"
                           role="combobox" aria-expanded="false"
                           aria-controls="ld-search-results"
                           aria-autocomplete="list" aria-haspopup="listbox"
                           style="background:rgba(255,255,255,.1);box-shadow:none;">
                </div>
                <div id="ld-search-dropdown" class="d-none position-absolute w-100 bg-white rounded-3 shadow-lg mt-1" style="z-index:1055;max-height:420px;overflow-y:auto;">
                    <div class="d-flex border-bottom px-3 pt-2 gap-1" id="ld-search-tabs" role="tablist" aria-label="Search filters">
                        <button type="button" class="btn btn-sm px-2 py-1 ld-search-tab active" data-type="all" role="tab" aria-selected="true">Everything</button>
                        <button type="button" class="btn btn-sm px-2 py-1 ld-search-tab" data-type="tickets" role="tab" aria-selected="false">Tickets</button>
                        <?php if (Auth::isStaff()): ?>
                        <button type="button" class="btn btn-sm px-2 py-1 ld-search-tab" data-type="contacts" role="tab" aria-selected="false">Contacts</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm px-2 py-1 ld-search-tab" data-type="kb" role="tab" aria-selected="false">Knowledge Base Articles</button>
                    </div>
                    <div id="ld-search-results" class="p-2" role="listbox" aria-label="Search results">
                        <div class="text-center text-muted py-3 small">Type to search...</div>
                    </div>
                </div>
            </div>

            <ul class="navbar-nav">
                <?php
                $notifCount = notificationCount();
                if (Auth::isStaff()):
                ?>
                <li class="nav-item" id="ld-notif-bell" data-count="<?= $notifCount ?>">
                    <?php $notifLabel = $notifCount === 0 ? 'Notifications, none unread' : 'Notifications, ' . ($notifCount > 99 ? '99+' : $notifCount) . ' unread'; ?>
                    <a class="nav-link position-relative <?= $notifCount > 0 ? 'ld-bell-active' : '' ?>" href="/notifications" aria-label="<?= e($notifLabel) ?>">
                        <i class="bi bi-bell fs-5" aria-hidden="true"></i>
                        <span class="position-absolute badge rounded-pill bg-danger <?= $notifCount > 0 ? '' : 'd-none' ?>"
                              id="ld-notif-badge" style="font-size:.55rem; top:.35rem; right:.15rem; padding:.15em .35em; line-height:1;" aria-hidden="true">
                            <?= $notifCount > 99 ? '99+' : $notifCount ?>
                        </span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#"
                       id="tour-nav-user"
                       role="button" data-bs-toggle="dropdown"
                       aria-haspopup="true" aria-expanded="false"
                       aria-label="<?= e(Auth::fullName()) ?> account menu">
                        <div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center"
                             style="width:32px;height:32px;font-size:.85rem;font-weight:600;" aria-hidden="true">
                            <?= Auth::initials() ?>
                        </div>
                        <span class="d-none d-lg-inline" aria-hidden="true"><?= e(Auth::fullName()) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small"><?= e(Auth::user()['email'] ?? '') ?></span></li>
                        <li><span class="dropdown-item-text"><span class="badge bg-primary"><?= e(roleLabel(Auth::role())) ?></span></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (Auth::isAdmin()): ?>
                        <li><a class="dropdown-item" href="/admin?tour=1"><i class="bi bi-play-circle me-2" aria-hidden="true"></i>Restart Tour</a></li>
                        <?php endif; ?>
                        <?php if (Auth::isStaff() && !Auth::isAdmin()): ?>
                        <li><a class="dropdown-item" href="/agent?tour=1"><i class="bi bi-play-circle me-2" aria-hidden="true"></i>Restart Tour</a></li>
                        <?php endif; ?>
                        <?php if (!Auth::isStaff()): ?>
                        <li><a class="dropdown-item" href="/portal?tour=1"><i class="bi bi-play-circle me-2" aria-hidden="true"></i>Restart Tour</a></li>
                        <?php endif; ?>
                        <?php
                        // "Manage My Team" appears for admins (always) and any user
                        // who is flagged as a manager on at least one group.
                        $_showManagerLink = false;
                        if (Auth::isAdmin()) {
                            $_showManagerLink = true;
                        } elseif (Auth::id()) {
                            try {
                                $_mStmt = Database::connect()->prepare(
                                    'SELECT 1 FROM group_user_map WHERE user_id = ? AND is_manager = 1 LIMIT 1'
                                );
                                $_mStmt->execute([Auth::id()]);
                                $_showManagerLink = (bool) $_mStmt->fetchColumn();
                            } catch (\Throwable $e) {
                                // Table column may be missing pre-migration; fail silent.
                                $_showManagerLink = false;
                            }
                        }
                        ?>
                        <?php if ($_showManagerLink): ?>
                        <li><a class="dropdown-item" href="/manager"><i class="bi bi-stars me-2" aria-hidden="true"></i>Manage My Team</a></li>
                        <?php endif; ?>
                        <?php if (Auth::isStaff() || Auth::isAdmin()): ?>
                        <li><a class="dropdown-item" href="/agent/wallboard"><i class="bi bi-display me-2" aria-hidden="true"></i>Wallboard</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="/profile"><i class="bi bi-person-circle me-2" aria-hidden="true"></i>My Profile</a></li>
                        <?php if (is_file(ROOT_DIR . '/src/routes/credits.php')): ?>
                        <li><a class="dropdown-item" href="/credits"><i class="bi bi-film me-2" aria-hidden="true"></i>Credits</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign Out</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><span class="dropdown-item-text text-muted" style="font-size:.7rem;">v<?= APP_VERSION ?></span></li>
                    </ul>
                </li>
            </ul>
            <?php else: ?>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/login"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Sign In</a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php if (Auth::check()): ?>
<script>
(function () {
    // --- Notification bell polling (admin/agent only) ---
    var bell = document.getElementById('ld-notif-bell');
    if (bell) {
        var link      = bell.querySelector('a');
        var badge     = document.getElementById('ld-notif-badge');
        var lastCount = parseInt(bell.dataset.count, 10) || 0;

        function poll() {
            fetch('/notifications/count', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var n = data.count || 0;
                    var display = n > 99 ? '99+' : String(n);
                    badge.textContent = display;
                    link.setAttribute('aria-label', n === 0 ? 'Notifications, none unread' : 'Notifications, ' + display + ' unread');
                    if (n > 0) {
                        badge.classList.remove('d-none');
                        link.classList.add('ld-bell-active');
                    } else {
                        badge.classList.add('d-none');
                        link.classList.remove('ld-bell-active');
                    }
                    if (n > lastCount) {
                        link.classList.remove('ld-bell-ring');
                        void link.offsetWidth;
                        link.classList.add('ld-bell-ring');
                    }
                    lastCount = n;
                    bell.dataset.count = n;
                })
                .catch(function () {});
        }
        setInterval(poll, 15000);

        // --- Easter egg: spam-click the bell for a brief disco party ---
        // Only armed on the notifications page, where clicking the bell would
        // otherwise just reload the page you're already on — so suppressing it
        // costs nothing and adds zero latency to the bell anywhere else. Arrive
        // from another page and the first click brings you here; seven fast
        // clicks after that pop the confetti. Visual-only, never touches state.
        if (link && location.pathname.replace(/\/+$/, '') === '/notifications') {
            var discoHits = 0;
            var discoLast = 0;

            function discoParty() {
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                link.classList.add('ld-bell-disco');
                var rect = link.getBoundingClientRect();
                var cx = rect.left + rect.width / 2;
                var cy = rect.top + rect.height / 2;
                var emojis = ['🎉', '🪩', '✨', '🎈', '💃', '🕺', '⭐'];
                for (var i = 0; i < 14; i++) {
                    var p = document.createElement('span');
                    p.className = 'ld-disco-particle';
                    p.textContent = emojis[i % emojis.length];
                    p.style.left = (cx - 8) + 'px';
                    p.style.top  = (cy - 8) + 'px';
                    p.style.setProperty('--dx', (Math.random() * 80 - 40).toFixed(0) + 'px');
                    p.style.setProperty('--dr', (Math.random() * 180 - 90).toFixed(0) + 'deg');
                    p.style.animationDelay = (Math.random() * 0.3).toFixed(2) + 's';
                    document.body.appendChild(p);
                    (function (el) { setTimeout(function () { el.remove(); }, 1800); })(p);
                }
                setTimeout(function () { link.classList.remove('ld-bell-disco'); }, 3200);
            }

            link.addEventListener('click', function (e) {
                // We're already on /notifications — the click would only reload.
                e.preventDefault();
                var now = Date.now();
                discoHits = (now - discoLast <= 1500) ? discoHits + 1 : 1;
                discoLast = now;
                if (discoHits >= 7) {
                    discoHits = 0;
                    discoParty();
                }
            });
        }
    }

    // --- Global Search ---
    var wrap     = document.getElementById('ld-search-wrap');
    if (!wrap) return;
    var input    = document.getElementById('ld-search-input');
    var dropdown = document.getElementById('ld-search-dropdown');
    var results  = document.getElementById('ld-search-results');
    var tabs     = document.querySelectorAll('.ld-search-tab');
    var role     = wrap.dataset.role;
    var timer    = null;
    var activeType = 'all';

    var statusColors = {
        open: 'primary', in_progress: 'warning', pending: 'info',
        resolved: 'success', closed: 'secondary'
    };
    var statusLabels = {
        open: 'Open', in_progress: 'In Progress', pending: 'Pending',
        resolved: 'Resolved', closed: 'Closed'
    };

    function ticketUrl(id) {
        if (role === 'admin') return '/admin/tickets/' + id;
        if (role === 'agent') return '/agent/tickets/' + id;
        return '/portal/tickets/' + id;
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function renderResults(data) {
        var html = '';
        var hasResults = false;

        // Tickets
        if (data.tickets && data.tickets.length > 0) {
            hasResults = true;
            html += '<div class="ld-search-group">';
            html += '<div class="px-2 py-1 text-muted small fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Tickets</div>';
            data.tickets.forEach(function (t) {
                var color = statusColors[t.status] || 'secondary';
                var label = statusLabels[t.status] || t.status;
                html += '<a href="' + ticketUrl(t.id) + '" class="ld-search-item d-flex align-items-center gap-2 px-2 py-2 rounded text-decoration-none text-dark">';
                html += '<i class="bi bi-ticket-perforated text-muted"></i>';
                html += '<div class="flex-grow-1 text-truncate">';
                html += '<span class="text-muted small me-1">#' + t.id + '</span>';
                html += '<span class="fw-medium">' + esc(t.subject) + '</span>';
                html += '</div>';
                html += '<span class="badge bg-' + color + '" style="font-size:.65rem;">' + esc(label) + '</span>';
                html += '</a>';
            });
            html += '</div>';
        }

        // Contacts
        if (data.contacts && data.contacts.length > 0) {
            hasResults = true;
            html += '<div class="ld-search-group">';
            html += '<div class="px-2 py-1 text-muted small fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Contacts</div>';
            var badgeColors = { admin: 'danger', agent: 'primary', user: 'secondary' };
            data.contacts.forEach(function (u) {
                var href = role === 'admin' ? '/admin/users/' + u.id : '#';
                html += '<a href="' + href + '" class="ld-search-item d-flex align-items-center gap-2 px-2 py-2 rounded text-decoration-none text-dark">';
                html += '<i class="bi bi-person text-muted"></i>';
                html += '<div class="flex-grow-1 text-truncate">';
                html += '<span class="fw-medium">' + esc(u.first_name + ' ' + u.last_name) + '</span>';
                html += '<span class="text-muted small ms-1">' + esc(u.email) + '</span>';
                html += '</div>';
                html += '<span class="badge bg-' + (badgeColors[u.role] || 'secondary') + '" style="font-size:.65rem;">' + esc(u.role) + '</span>';
                html += '</a>';
            });
            html += '</div>';
        }

        // KB Articles
        if (data.kb && data.kb.length > 0) {
            hasResults = true;
            html += '<div class="ld-search-group">';
            html += '<div class="px-2 py-1 text-muted small fw-semibold text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Knowledge Base</div>';
            data.kb.forEach(function (a) {
                html += '<a href="/portal/kb/articles/' + encodeURIComponent(a.slug) + '" class="ld-search-item d-flex align-items-center gap-2 px-2 py-2 rounded text-decoration-none text-dark">';
                html += '<i class="bi bi-book text-muted"></i>';
                html += '<div class="flex-grow-1 text-truncate">';
                html += '<span class="fw-medium">' + esc(a.title) + '</span>';
                var path = [a.category_name, a.folder_name].filter(Boolean).join(' / ');
                if (path) html += '<span class="text-muted small ms-1">' + esc(path) + '</span>';
                html += '</div>';
                html += '</a>';
            });
            html += '</div>';
        }

        if (!hasResults) {
            html = '<div class="text-center text-muted py-3 small">No results found.</div>';
        }

        results.innerHTML = html;
    }

    function doSearch() {
        var q = input.value.trim();
        if (q.length < 2) {
            results.innerHTML = '<div class="text-center text-muted py-3 small">Type to search...</div>';
            return;
        }
        results.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-muted" role="status"></div></div>';
        fetch('/search?q=' + encodeURIComponent(q) + '&type=' + activeType, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(renderResults)
            .catch(function () {
                results.innerHTML = '<div class="text-center text-muted py-3 small">Search failed.</div>';
            });
    }

    function setOpen(open) {
        if (open) dropdown.classList.remove('d-none');
        else dropdown.classList.add('d-none');
        input.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Debounced input
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(doSearch, 300);
    });

    // Show dropdown on focus
    input.addEventListener('focus', function () {
        setOpen(true);
    });

    // Tab switching
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            activeType = tab.dataset.type;
            doSearch();
        });
    });

    // Close on click outside
    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) {
            setOpen(false);
        }
    });

    // Close on Escape, focus on /
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            setOpen(false);
            input.blur();
        }
        // WCAG 2.1.4 — only fire single-key shortcut when no input is focused,
        // no editor (contenteditable) is focused, and no modifier is held.
        if (e.key === '/' && !e.altKey && !e.ctrlKey && !e.metaKey) {
            var ae = document.activeElement;
            var tag = ae && ae.tagName;
            if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT' && !(ae && ae.isContentEditable)) {
                e.preventDefault();
                input.focus();
            }
        }
    });
})();
</script>
<?php endif; ?>
