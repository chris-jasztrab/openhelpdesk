<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="height:var(--ld-navbar-height,56px);">
    <div class="container-fluid">
        <?php $brandLogo = getSetting('branding_logo', ''); $brandName = getSetting('branding_app_name', 'LocalDesk'); $brandIcon = getSetting('branding_navbar_icon', 'bi-headset'); ?>
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <?php if ($brandLogo && file_exists(ROOT_DIR . '/public/uploads/branding/' . $brandLogo)): ?>
                <img src="/uploads/branding/<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>" style="height:32px;">
            <?php else: ?>
                <i class="bi <?= e($brandIcon) ?> fs-4"></i>
            <?php endif; ?>
            <span><?= e($brandName) ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (Auth::check()): ?>
            <ul class="navbar-nav me-auto">
                <?php if (Auth::role() === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/admin') ? 'active' : '' ?>" href="/admin">
                        <i class="bi bi-shield-lock me-1"></i>Admin
                    </a>
                </li>
                <?php endif; ?>
                <?php if (in_array(Auth::role(), ['admin', 'agent'], true)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/agent') ? 'active' : '' ?>" href="/agent">
                        <i class="bi bi-headset me-1"></i>Agent Panel
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= isActive('/portal') ? 'active' : '' ?>" href="/portal">
                        <i class="bi bi-house me-1"></i>Portal
                    </a>
                </li>
            </ul>

            <!-- Global Search -->
            <div class="position-relative mx-3 flex-grow-1 d-none d-lg-block" style="max-width:420px;" id="ld-search-wrap"
                 data-role="<?= e(Auth::role() ?? 'user') ?>">
                <div class="input-group input-group-sm">
                    <span class="input-group-text border-0 text-white text-opacity-50" style="background:rgba(255,255,255,.1);">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="ld-search-input" class="form-control form-control-sm border-0 text-white"
                           placeholder="Search... ( / )" autocomplete="off"
                           style="background:rgba(255,255,255,.1);box-shadow:none;">
                </div>
                <div id="ld-search-dropdown" class="d-none position-absolute w-100 bg-white rounded-3 shadow-lg mt-1" style="z-index:1055;max-height:420px;overflow-y:auto;">
                    <div class="d-flex border-bottom px-3 pt-2 gap-1" id="ld-search-tabs">
                        <button type="button" class="btn btn-sm px-2 py-1 ld-search-tab active" data-type="all">Everything</button>
                        <button type="button" class="btn btn-sm px-2 py-1 ld-search-tab" data-type="tickets">Tickets</button>
                        <?php if (in_array(Auth::role(), ['admin', 'agent'], true)): ?>
                        <button type="button" class="btn btn-sm px-2 py-1 ld-search-tab" data-type="contacts">Contacts</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm px-2 py-1 ld-search-tab" data-type="kb">KB Articles</button>
                    </div>
                    <div id="ld-search-results" class="p-2">
                        <div class="text-center text-muted py-3 small">Type to search...</div>
                    </div>
                </div>
            </div>

            <ul class="navbar-nav">
                <?php
                $notifCount = notificationCount();
                if (in_array(Auth::role(), ['admin', 'agent'], true)):
                ?>
                <li class="nav-item" id="ld-notif-bell" data-count="<?= $notifCount ?>">
                    <a class="nav-link position-relative <?= $notifCount > 0 ? 'ld-bell-active' : '' ?>" href="/notifications" title="Notifications">
                        <i class="bi bi-bell fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $notifCount > 0 ? '' : 'd-none' ?>"
                              id="ld-notif-badge" style="font-size:.65rem;">
                            <?= $notifCount > 99 ? '99+' : $notifCount ?>
                        </span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#"
                       role="button" data-bs-toggle="dropdown">
                        <div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center"
                             style="width:32px;height:32px;font-size:.85rem;font-weight:600;">
                            <?= Auth::initials() ?>
                        </div>
                        <span class="d-none d-lg-inline"><?= e(Auth::fullName()) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small"><?= e(Auth::user()['email'] ?? '') ?></span></li>
                        <li><span class="dropdown-item-text"><span class="badge bg-primary"><?= e(ucfirst(Auth::role() ?? '')) ?></span></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (Auth::role() === 'admin'): ?>
                        <li><a class="dropdown-item" href="/admin?tour=1"><i class="bi bi-play-circle me-2"></i>Restart Tour</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="/profile"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                    </ul>
                </li>
            </ul>
            <?php else: ?>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/login"><i class="bi bi-box-arrow-in-right me-1"></i>Sign In</a>
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
                    badge.textContent = n > 99 ? '99+' : n;
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

    // Debounced input
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(doSearch, 300);
    });

    // Show dropdown on focus
    input.addEventListener('focus', function () {
        dropdown.classList.remove('d-none');
    });

    // Tab switching
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            activeType = tab.dataset.type;
            doSearch();
        });
    });

    // Close on click outside
    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });

    // Close on Escape, focus on /
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            dropdown.classList.add('d-none');
            input.blur();
        }
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA' && document.activeElement.tagName !== 'SELECT') {
            e.preventDefault();
            input.focus();
        }
    });
})();
</script>
<?php endif; ?>
