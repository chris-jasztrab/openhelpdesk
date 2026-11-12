<?php
$layout       = 'app';
$pageTitle    = 'Domains';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Domains'],
];
$alertDays = (int) $settings['domain_alert_days'];
$badge = static function (?string $status): string {
    [$cls, $text] = match ($status) {
        'ok'      => ['bg-success-subtle text-success-emphasis border border-success-subtle', 'OK'],
        'warn'    => ['bg-warning-subtle text-warning-emphasis border border-warning-subtle', 'Weak'],
        'fail'    => ['bg-danger-subtle text-danger-emphasis border border-danger-subtle', 'Broken'],
        'missing' => ['bg-danger-subtle text-danger-emphasis border border-danger-subtle', 'Missing'],
        default   => ['bg-secondary-subtle text-secondary-emphasis border', '—'],
    };
    return '<span class="badge ' . $cls . '">' . $text . '</span>';
};
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<?php if ($alerts): ?>
<div class="alert alert-warning border-0 shadow-sm" role="alert">
    <h6 class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i><?= count($alerts) ?> expiring within <?= $alertDays ?> days</h6>
    <ul class="mb-0 ps-3">
        <?php foreach ($alerts as $a): ?>
        <li>
            <a href="/admin/settings/domains/<?= (int) $a['domain_id'] ?>" class="alert-link"><?= e($a['label']) ?></a>
            — <?= $a['kind'] === 'domain' ? 'domain registration' : 'SSL certificate' ?>
            <?= $a['days_left'] <= 0 ? '<strong>expired</strong> ' . e($a['expires_on']) : 'expires ' . e($a['expires_on']) . ' (' . (int) $a['days_left'] . ' day' . ($a['days_left'] === 1 ? '' : 's') . ')' ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-globe2 me-2" aria-hidden="true"></i>Domains</h5>
        <?php if ($domains): ?>
        <form method="POST" action="/admin/settings/domains/refresh-all" onsubmit="this.querySelector('button').disabled = true;">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Re-check all</button>
        </form>
        <?php endif; ?>
    </div>
    <?php if (!$domains): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-globe2 fs-1 d-block mb-2" aria-hidden="true"></i>
        <p class="mb-0">No domains tracked yet. Add the first one below.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-no-resize>
            <thead class="table-light">
                <tr>
                    <th>Domain</th><th>Registrar</th><th>Registration expires</th>
                    <th>SPF</th><th>DKIM</th><th>DMARC</th><th>SSL</th><th>Last checked</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($domains as $d):
                $c     = json_decode((string) $d['check_data'], true) ?: [];
                $left  = $d['expires_at'] !== null ? Domains::daysLeft($d['expires_at']) : null;
                $certs = array_map(static fn ($h) => Domains::certStatus($h['cert'] ?? [], $alertDays)[1], $c['hosts'] ?? []);
                $bad   = count(array_filter($certs, static fn ($s) => $s === 'fail'));
                $soon  = count(array_filter($certs, static fn ($s) => $s === 'warn'));
                $good  = count(array_filter($certs, static fn ($s) => $s === 'ok'));
            ?>
                <tr>
                    <td class="text-nowrap"><a href="/admin/settings/domains/<?= (int) $d['id'] ?>" class="fw-semibold text-decoration-none"><?= e($d['name']) ?></a></td>
                    <td><?= e($d['registrar'] ?? '—') ?></td>
                    <td class="text-nowrap">
                        <?php if ($left === null): ?>
                            <span class="text-muted">Unknown</span>
                        <?php else: ?>
                            <?= e($d['expires_at']) ?>
                            <span class="small <?= $left <= 7 ? 'text-danger fw-semibold' : ($left <= $alertDays ? 'text-warning-emphasis fw-semibold' : 'text-muted') ?>">
                                (<?= $left < 0 ? 'expired' : number_format($left) . ' days' ?>)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= $c ? $badge($c['spf']['status'] ?? null) : $badge(null) ?></td>
                    <td><?= $c ? $badge($c['dkim']['status'] ?? null) : $badge(null) ?></td>
                    <td><?= $c ? $badge($c['dmarc']['status'] ?? null) : $badge(null) ?></td>
                    <td class="text-nowrap">
                        <?php if ($bad): ?><span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle"><?= $bad ?> broken</span><?php endif; ?>
                        <?php if ($soon): ?><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><?= $soon ?> expiring</span><?php endif; ?>
                        <?php if ($good): ?><span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><?= $good ?> OK</span><?php endif; ?>
                        <?php if (!$bad && !$soon && !$good): ?><?= $badge(null) ?><?php endif; ?>
                    </td>
                    <td class="text-nowrap small text-muted"><?= $d['checked_at'] ? e(date('M j, g:i a', strtotime($d['checked_at']))) : 'Never' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3"><h5 class="mb-0 fw-semibold"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i>Add a domain</h5></div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/settings/domains" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="dom_name">Domain</label>
                        <input type="text" class="form-control" id="dom_name" name="name" placeholder="example.ca" required>
                        <div class="form-text">The root domain. Registrar and expiry are looked up for you.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="dom_hosts">Hosts to check</label>
                        <textarea class="form-control font-monospace" id="dom_hosts" name="extra_hosts" rows="4" placeholder="www&#10;mail&#10;catalogue"></textarea>
                        <div class="form-text">One per line; the domain is added for you. Not needed once a Cloudflare token is saved, which finds every host.</div>
                    </div>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add and check</button>
                    <span class="form-text ms-2">Checking takes a few seconds per host.</span>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3"><h5 class="mb-0 fw-semibold"><i class="bi bi-bell me-2" aria-hidden="true"></i>Alerts and lookups</h5></div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/settings/domains/settings">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="domain_alert_days">Alert window</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="number" class="form-control" style="width:100px;" id="domain_alert_days" name="domain_alert_days"
                                   min="1" max="365" value="<?= $alertDays ?>">
                            <span class="text-muted">days before a domain or certificate expires</span>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="domain_alerts_email" name="domain_alerts_email" value="1"
                               <?= $settings['domain_alerts_email'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="domain_alerts_email">Email all administrators</label>
                    </div>
                    <div class="form-text mb-4">
                        One email listing everything newly inside the window, then again at 7 days and once expired.
                        Sent by the daily <a href="/admin/settings/cron-jobs">Domain Checks</a> job. In-app alerts show here and on the admin dashboard either way.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="cloudflare_api_token">Cloudflare API token</label>
                        <input type="password" class="form-control" id="cloudflare_api_token" name="cloudflare_api_token" autocomplete="off"
                               placeholder="<?= $settings['cloudflare_token_set'] ? 'Saved — leave blank to keep it' : 'Optional' ?>">
                        <div class="form-text">
                            A read-only token (permission <strong>Zone → DNS → Read</strong>) lists every record in each zone and finds every host and DKIM selector automatically.
                            Without it, only the root, the hosts you list and common DKIM selectors are checked.
                        </div>
                        <?php if ($settings['cloudflare_token_set']): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="cloudflare_clear" name="cloudflare_clear" value="1">
                            <label class="form-check-label" for="cloudflare_clear">Remove the saved token</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="domain_dns_resolver">Public DNS resolver</label>
                        <input type="text" class="form-control" style="max-width:200px;" id="domain_dns_resolver" name="domain_dns_resolver"
                               value="<?= e($settings['domain_dns_resolver']) ?>" placeholder="1.1.1.1">
                        <div class="form-text">Lookups go here so results show what the internet sees, not the internal DNS view. Blank uses this server's own DNS.</div>
                    </div>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
