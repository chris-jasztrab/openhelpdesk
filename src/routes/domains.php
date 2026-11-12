<?php

/**
 * Domains — Admin → Settings → Domains. Admins only: the page shows the whole
 * external DNS footprint and holds the Cloudflare token.
 */

/** Validated form fields for add/edit, or null (with a flash) when the input is bad. */
function _domainFormFields(?string $fixedName = null): ?array
{
    $name = $fixedName ?? strtolower(trim((string) ($_POST['name'] ?? '')));
    $name = rtrim((string) preg_replace('#^[a-z]+://|/.*$#', '', $name), '.');
    if (!preg_match(Domains::DOMAIN_RE, $name)) {
        flash('error', 'Enter a domain name like example.ca.');
        return null;
    }
    $expires = trim((string) ($_POST['expires_at'] ?? ''));
    if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
        flash('error', 'Expiry date must be a date.');
        return null;
    }
    $hosts = [];
    foreach (Domains::splitList($_POST['extra_hosts'] ?? '') as $h) {
        if (($full = Domains::hostUnder($h, $name)) === null) {
            flash('error', "\"{$h}\" is not a valid host name.");
            return null;
        }
        $hosts[] = $full;
    }
    return [
        'name'           => $name,
        'registrar'      => trim((string) ($_POST['registrar'] ?? '')) ?: null,
        'expires_at'     => $expires ?: null,
        'extra_hosts'    => implode("\n", array_unique($hosts)) ?: null,
        'dkim_selectors' => implode(', ', array_filter(Domains::splitList($_POST['dkim_selectors'] ?? ''), static fn ($s) => (bool) preg_match('/^[a-z0-9-]{1,63}$/', $s))) ?: null,
        'notes'          => trim((string) ($_POST['notes'] ?? '')) ?: null,
    ];
}

function _domainCsrfOrBack(string $back): void
{
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect($back);
    }
}

$router->get('/admin/settings/domains', function () {
    Auth::requireAdmin();
    $db = Database::connect();
    render('admin/domains/index', [
        'domains'  => $db->query('SELECT * FROM domains ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
        'alerts'   => Domains::alerts($db),
        'settings' => [
            'domain_alert_days'    => (string) Domains::alertDays(),
            'domain_alerts_email'  => getSetting('domain_alerts_email', '1'),
            'domain_dns_resolver'  => getSetting('domain_dns_resolver', '1.1.1.1'),
            'cloudflare_token_set' => getSetting('cloudflare_api_token', '') !== '',
        ],
    ]);
});

$router->post('/admin/settings/domains', function () {
    Auth::requireAdmin();
    _domainCsrfOrBack('/admin/settings/domains');
    $f = _domainFormFields();
    if ($f === null) {
        redirect('/admin/settings/domains');
    }
    $db = Database::connect();
    try {
        $db->prepare('INSERT INTO domains (name, registrar, expires_at, extra_hosts, dkim_selectors, notes) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute(array_values($f));
    } catch (PDOException $e) {
        flash('error', "{$f['name']} is already tracked.");
        redirect('/admin/settings/domains');
    }
    $id = (int) $db->lastInsertId();
    logAuditChange('domains.added', $id, 'domain', [], ['name' => $f['name']]);
    set_time_limit(300);
    Domains::refresh($db, $id);
    flash('success', "{$f['name']} added and checked.");
    redirect('/admin/settings/domains/' . $id);
});

$router->post('/admin/settings/domains/settings', function () {
    Auth::requireAdmin();
    _domainCsrfOrBack('/admin/settings/domains');

    $resolver = trim((string) ($_POST['domain_dns_resolver'] ?? ''));
    if ($resolver !== '' && !filter_var($resolver, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        flash('error', 'The public DNS resolver must be an IPv4 address, e.g. 1.1.1.1.');
        redirect('/admin/settings/domains');
    }
    $before = [
        'domain_alert_days'    => getSetting('domain_alert_days', '30'),
        'domain_alerts_email'  => getSetting('domain_alerts_email', '1'),
        'domain_dns_resolver'  => getSetting('domain_dns_resolver', '1.1.1.1'),
        'cloudflare_api_token' => getSetting('cloudflare_api_token', ''),
    ];
    $token = trim((string) ($_POST['cloudflare_api_token'] ?? ''));
    $after = [
        'domain_alert_days'    => (string) max(1, min(365, (int) ($_POST['domain_alert_days'] ?? 30))),
        'domain_alerts_email'  => isset($_POST['domain_alerts_email']) ? '1' : '0',
        'domain_dns_resolver'  => $resolver,
        // Blank keeps the stored token; the box is never pre-filled with it.
        'cloudflare_api_token' => isset($_POST['cloudflare_clear']) ? '' : ($token !== '' ? $token : $before['cloudflare_api_token']),
    ];
    foreach ($after as $k => $v) {
        setSetting($k, $v);
    }
    logAuditChange('domains.settings_changed', null, null, $before, $after, ['cloudflare_api_token']);
    flash('success', 'Domain settings saved.');
    redirect('/admin/settings/domains');
});

$router->post('/admin/settings/domains/refresh-all', function () {
    Auth::requireAdmin();
    _domainCsrfOrBack('/admin/settings/domains');
    $db = Database::connect();
    set_time_limit(900);
    $ids = $db->query('SELECT id FROM domains ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        Domains::refresh($db, (int) $id);
    }
    flash('success', count($ids) . ' domain(s) re-checked.');
    redirect('/admin/settings/domains');
});

$router->get('/admin/settings/domains/{id}', function (array $p) {
    Auth::requireAdmin();
    $st = Database::connect()->prepare('SELECT * FROM domains WHERE id = ?');
    $st->execute([(int) $p['id']]);
    $domain = $st->fetch(PDO::FETCH_ASSOC);
    if (!$domain) {
        flash('error', 'Domain not found.');
        redirect('/admin/settings/domains');
    }
    render('admin/domains/view', [
        'domain'    => $domain,
        'check'     => json_decode((string) $domain['check_data'], true) ?: null,
        'alertDays' => Domains::alertDays(),
    ]);
});

$router->post('/admin/settings/domains/{id}', function (array $p) {
    Auth::requireAdmin();
    $id   = (int) $p['id'];
    $back = '/admin/settings/domains/' . $id;
    _domainCsrfOrBack($back);
    $db = Database::connect();
    $st = $db->prepare('SELECT * FROM domains WHERE id = ?');
    $st->execute([$id]);
    $before = $st->fetch(PDO::FETCH_ASSOC);
    if (!$before) {
        redirect('/admin/settings/domains');
    }
    $f = _domainFormFields($before['name']); // the name is the identity; delete and re-add to change it
    if ($f === null) {
        redirect($back);
    }
    unset($f['name']);
    $db->prepare('UPDATE domains SET registrar = ?, expires_at = ?, extra_hosts = ?, dkim_selectors = ?, notes = ? WHERE id = ?')
       ->execute([...array_values($f), $id]);
    logAuditChange('domains.updated', $id, 'domain', $before, $f);
    set_time_limit(300);
    Domains::refresh($db, $id);
    flash('success', 'Saved and re-checked.');
    redirect($back);
});

$router->post('/admin/settings/domains/{id}/refresh', function (array $p) {
    Auth::requireAdmin();
    $back = '/admin/settings/domains/' . (int) $p['id'];
    _domainCsrfOrBack($back);
    set_time_limit(300);
    flash(...(Domains::refresh(Database::connect(), (int) $p['id']) ? ['success', 'Re-checked just now.'] : ['error', 'Domain not found.']));
    redirect($back);
});

$router->post('/admin/settings/domains/{id}/delete', function (array $p) {
    Auth::requireAdmin();
    _domainCsrfOrBack('/admin/settings/domains');
    $db = Database::connect();
    $st = $db->prepare('SELECT name FROM domains WHERE id = ?');
    $st->execute([(int) $p['id']]);
    if ($name = $st->fetchColumn()) {
        $db->prepare('DELETE FROM domains WHERE id = ?')->execute([(int) $p['id']]);
        logAuditChange('domains.deleted', (int) $p['id'], 'domain', ['name' => $name], ['name' => '']);
        flash('success', "{$name} removed.");
    }
    redirect('/admin/settings/domains');
});
