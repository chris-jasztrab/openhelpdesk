<?php
$layout       = 'app';
$pageTitle    = $domain['name'];
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Domains', 'url' => '/admin/settings/domains'],
    ['label' => $domain['name']],
];
$id    = (int) $domain['id'];
$left  = $domain['expires_at'] !== null ? Domains::daysLeft($domain['expires_at']) : null;
$tone  = ['ok' => 'success', 'warn' => 'warning', 'fail' => 'danger', 'missing' => 'danger', 'none' => 'secondary'];
$words = ['ok' => 'OK', 'warn' => 'Weak', 'fail' => 'Broken', 'missing' => 'Missing'];
$badge = static function (?string $status, ?string $text = null) use ($tone, $words): string {
    $t = $tone[$status] ?? 'secondary';
    return '<span class="badge bg-' . $t . '-subtle text-' . $t . '-emphasis border border-' . $t . '-subtle">' . e($text ?? $words[$status] ?? '—') . '</span>';
};
$recStyle = 'word-break:break-all;white-space:pre-wrap;';
[$shownHosts, $ignoredHosts] = Domains::splitHosts($check['hosts'] ?? [], $domain['ignored_hosts'] ?? null);
// An ignored host that has since left the zone still needs a Restore row.
foreach (array_diff(Domains::splitList($domain['ignored_hosts'] ?? null), array_column($ignoredHosts, 'host')) as $_gone) {
    $ignoredHosts[] = ['host' => $_gone, 'ips' => [], 'cert' => []];
}
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="min-w-0">
        <h4 class="fw-bold mb-0"><i class="bi bi-globe2 me-2" aria-hidden="true"></i><?= e($domain['name']) ?></h4>
        <div class="small text-muted">
            <?= $domain['checked_at'] ? 'Last checked ' . e(date('M j, Y g:i a', strtotime($domain['checked_at']))) : 'Never checked' ?>
        </div>
    </div>
    <form method="POST" action="/admin/settings/domains/<?= $id ?>/refresh" onsubmit="this.querySelector('button').disabled = true;">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Re-check now</button>
    </form>
</div>

<?php if (!$check): ?>
<div class="alert alert-secondary">This domain has not been checked yet. Use <strong>Re-check now</strong>.</div>
<?php else: ?>

<?php if (!empty($check['cloudflare_error'])): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
    The Cloudflare token could not list this zone, so only looked-up records are shown. Check the token has <strong>Zone → DNS → Read</strong> for <?= e($domain['name']) ?>.
</div>
<?php endif; ?>
<?php if (($check['dns_view'] ?? '') === 'system'): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
    The public DNS resolver could not be reached, so these lookups used this server's own DNS and may show the internal view.
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3"><h5 class="mb-0 fw-semibold"><i class="bi bi-card-text me-2" aria-hidden="true"></i>Registration</h5></div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt class="small text-muted fw-normal">Registrar</dt>
                    <dd class="fw-semibold"><?= e($domain['registrar'] ?? 'Unknown') ?></dd>
                    <dt class="small text-muted fw-normal">Expires</dt>
                    <dd class="fw-semibold">
                        <?php if ($left === null): ?>
                            Unknown
                        <?php else: ?>
                            <?= e(date('F j, Y', strtotime($domain['expires_at']))) ?>
                            <?= $badge($left <= 7 ? 'fail' : ($left <= $alertDays ? 'warn' : 'ok'), $left < 0 ? 'Expired' : number_format($left) . ' days') ?>
                        <?php endif; ?>
                    </dd>
                    <dt class="small text-muted fw-normal">Source</dt>
                    <dd class="mb-0">
                        <?= !empty($check['registry']['source'])
                            ? e($check['registry']['source']) . ' lookup'
                            : 'Entered by hand <span class="text-muted small">(the registry lookup returned nothing)</span>' ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3"><h5 class="mb-0 fw-semibold"><i class="bi bi-envelope-check me-2" aria-hidden="true"></i>Mail</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="fw-semibold mb-1">MX <?= $check['mx'] ? '' : $badge('missing', 'None') ?></div>
                    <?php foreach ($check['mx'] as $mx): ?>
                    <div><code><?= e($mx) ?></code></div>
                    <?php endforeach; ?>
                    <?php if (!$check['mx']): ?><div class="small text-muted">This domain does not receive mail.</div><?php endif; ?>
                </div>

                <?php foreach (['spf' => 'SPF', 'dmarc' => 'DMARC'] as $key => $title): $r = $check[$key]; ?>
                <div class="mb-3">
                    <div class="fw-semibold mb-1">
                        <?= $title ?> <?= $badge($r['status']) ?>
                        <?php if ($key === 'spf' && $r['record']): ?><span class="small text-muted fw-normal ms-1"><?= (int) $r['lookups'] ?> of 10 DNS lookups used</span><?php endif; ?>
                        <?php if ($key === 'dmarc' && $r['policy']): ?><span class="small text-muted fw-normal ms-1">policy: <?= e($r['policy']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($r['record']): ?><code class="d-block" style="<?= $recStyle ?>"><?= e($r['record']) ?></code><?php endif; ?>
                    <?php foreach ($r['notes'] as $n): ?><div class="small text-muted mt-1"><?= e($n) ?></div><?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <div>
                    <div class="fw-semibold mb-1">DKIM <?= $badge($check['dkim']['status']) ?></div>
                    <?php if (!$check['dkim']['selectors']): ?>
                    <div class="small text-muted">No key found at the common selectors. If this domain signs mail, add its selector under Edit below.</div>
                    <?php endif; ?>
                    <?php foreach ($check['dkim']['selectors'] as $s): ?>
                    <div class="mb-2">
                        <span class="font-monospace"><?= e($s['selector']) ?>._domainkey</span>
                        <?= $s['status'] === 'ok' ? '' : $badge($s['status'], $s['record'] === null ? 'Not found' : 'Revoked') ?>
                        <?php if ($s['record']): ?>
                        <details class="small"><summary class="text-muted">Show key</summary><code class="d-block" style="<?= $recStyle ?>"><?= e($s['record']) ?></code></details>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" id="hosts">
    <div class="card-header bg-white py-3"><h5 class="mb-0 fw-semibold"><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Hosts and SSL certificates <span class="badge bg-secondary-subtle text-secondary-emphasis border"><?= count($shownHosts) ?></span></h5></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr><th aria-label="Ignore"></th><th>Host</th><th>Address</th><th>SSL</th><th>Issued by</th><th>Issued to</th><th>Expires</th></tr></thead>
            <tbody>
            <?php if (!$shownHosts): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Every host is ignored. Restore one from the list below.</td></tr>
            <?php endif; ?>
            <?php foreach ($shownHosts as $h): $c = $h['cert']; [$label, $st] = Domains::certStatus($c, $alertDays); ?>
                <tr>
                    <td style="width:1%;">
                        <form method="POST" action="/admin/settings/domains/<?= $id ?>/hosts" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="host" value="<?= e($h['host']) ?>">
                            <input type="hidden" name="action" value="ignore">
                            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap" title="Hide <?= e($h['host']) ?> and leave it out of SSL verdicts and expiry alerts">
                                <i class="bi bi-eye-slash" aria-hidden="true"></i> Ignore
                            </button>
                        </form>
                    </td>
                    <td class="text-nowrap font-monospace"><?= e($h['host']) ?></td>
                    <td class="small"><?= $h['ips'] ? e(implode(', ', $h['ips'])) : '<span class="text-muted">No public address</span>' ?></td>
                    <td class="text-nowrap">
                        <?= $badge($st, $label) ?>
                        <?php if (!empty($c['internal'])): ?><span class="badge bg-light text-dark border" title="The public address did not answer from this server, so this certificate was read through internal DNS. It may be a different server than the internet reaches.">internal view</span><?php endif; ?>
                    </td>
                    <?php if (!empty($c['found'])): ?>
                    <td><?= e($c['issuer']) ?></td>
                    <td class="font-monospace small"><?= e($c['subject']) ?></td>
                    <td class="text-nowrap"><?= e($c['valid_to']) ?> <span class="small text-muted">(<?= number_format(Domains::daysLeft($c['valid_to'])) ?> days)</span></td>
                    <?php else: ?>
                    <td colspan="3" class="small text-muted">Nothing answered on port 443 from this server.</td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($ignoredHosts): ?>
    <details class="border-top" id="ignored-hosts" <?= isset($_GET['ignored']) ? 'open' : '' ?>>
        <summary class="px-3 py-3 fw-semibold">
            <i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Ignored hosts
            <span class="badge bg-secondary-subtle text-secondary-emphasis border"><?= count($ignoredHosts) ?></span>
            <span class="small text-muted fw-normal ms-1">left out of SSL verdicts and expiry alerts</span>
        </summary>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" data-no-resize>
                <tbody>
                <?php foreach ($ignoredHosts as $h): $c = $h['cert'] ?? []; [$label, $st] = Domains::certStatus($c, $alertDays); ?>
                    <tr>
                        <td class="text-nowrap font-monospace ps-3"><?= e($h['host']) ?></td>
                        <td class="text-nowrap"><?= $c ? $badge($st, $label) : '<span class="small text-muted">Not in the last check</span>' ?></td>
                        <td class="small text-muted"><?= !empty($c['found']) ? e($c['issuer']) . ', expires ' . e($c['valid_to']) : '' ?></td>
                        <td class="text-end pe-3">
                            <form method="POST" action="/admin/settings/domains/<?= $id ?>/hosts" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="host" value="<?= e($h['host']) ?>">
                                <input type="hidden" name="action" value="restore">
                                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-eye" aria-hidden="true"></i> Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>DNS records <span class="badge bg-secondary-subtle text-secondary-emphasis border"><?= count($check['records']) ?></span></h5>
        <span class="small text-muted">
            <?= $check['records_source'] === 'cloudflare'
                ? 'Full zone, from Cloudflare'
                : 'Looked up for the root and listed hosts only. Add a Cloudflare token to list the whole zone.' ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr><th>Name</th><th>Type</th><th>Value</th><th>TTL</th></tr></thead>
            <tbody>
            <?php
            $records = $check['records'];
            usort($records, static fn ($a, $b) => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);
            foreach ($records as $r): ?>
                <tr>
                    <td class="font-monospace small text-nowrap"><?= e($r['name']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= e($r['type']) ?></span></td>
                    <td class="font-monospace small" style="min-width:16rem;<?= $recStyle ?>"><?= e($r['data']) ?><?= !empty($r['proxied']) ? ' <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">proxied</span>' : '' ?></td>
                    <td class="small text-muted text-nowrap"><?= (int) $r['ttl'] === 1 ? 'auto' : (int) $r['ttl'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3"><h5 class="mb-0 fw-semibold"><i class="bi bi-pencil me-2" aria-hidden="true"></i>Edit</h5></div>
    <div class="card-body p-4">
        <form method="POST" action="/admin/settings/domains/<?= $id ?>" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="registrar">Registrar</label>
                    <input type="text" class="form-control" id="registrar" name="registrar" maxlength="255" value="<?= e($domain['registrar'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="expires_at">Registration expires</label>
                    <input type="date" class="form-control" id="expires_at" name="expires_at" value="<?= e($domain['expires_at'] ?? '') ?>">
                </div>
                <div class="col-12 form-text mt-1">Both are overwritten whenever the registry lookup succeeds; type them in only for a domain it cannot find.</div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="extra_hosts">Hosts to check</label>
                    <textarea class="form-control font-monospace" id="extra_hosts" name="extra_hosts" rows="6"><?= e($domain['extra_hosts'] ?? '') ?></textarea>
                    <div class="form-text">One per line. Hosts found through Cloudflare are checked as well.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="dkim_selectors">DKIM selectors</label>
                    <input type="text" class="form-control font-monospace" id="dkim_selectors" name="dkim_selectors" maxlength="255"
                           value="<?= e($domain['dkim_selectors'] ?? '') ?>" placeholder="selector1, selector2">
                    <div class="form-text mb-3">Selectors this domain must have; a missing one is flagged. Common ones (<?= e(implode(', ', Domains::DEFAULT_SELECTORS)) ?>) are always tried.</div>
                    <label class="form-label fw-semibold" for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($domain['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn text-white mt-3" style="background:var(--ld-primary);"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save and re-check</button>
        </form>
    </div>
</div>

<form method="POST" action="/admin/settings/domains/<?= $id ?>/delete" class="mb-4"
      onsubmit="return confirm('Stop tracking <?= e($domain['name']) ?>? This removes it from the helpdesk only; the domain itself is untouched.');">
    <?= csrfField() ?>
    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Stop tracking this domain</button>
</form>
<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
