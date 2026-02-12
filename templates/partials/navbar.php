<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="height:var(--ld-navbar-height,56px);">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <i class="bi bi-headset fs-4"></i>
            <span>LocalDesk</span>
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
<?php if (Auth::check() && in_array(Auth::role(), ['admin', 'agent'], true)): ?>
<script>
(function () {
    var bell      = document.getElementById('ld-notif-bell');
    if (!bell) return;
    var link      = bell.querySelector('a');
    var badge     = document.getElementById('ld-notif-badge');
    var lastCount = parseInt(bell.dataset.count, 10) || 0;

    function poll() {
        fetch('/notifications/count', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var n = data.count || 0;
                // Update badge text
                badge.textContent = n > 99 ? '99+' : n;

                if (n > 0) {
                    badge.classList.remove('d-none');
                    link.classList.add('ld-bell-active');
                } else {
                    badge.classList.add('d-none');
                    link.classList.remove('ld-bell-active');
                }

                // Jiggle when count increases
                if (n > lastCount) {
                    link.classList.remove('ld-bell-ring');
                    // Force reflow so re-adding the class restarts the animation
                    void link.offsetWidth;
                    link.classList.add('ld-bell-ring');
                }

                lastCount = n;
                bell.dataset.count = n;
            })
            .catch(function () { /* silently ignore network errors */ });
    }

    setInterval(poll, 15000); // poll every 15 seconds
})();
</script>
<?php endif; ?>
