<?php

declare(strict_types=1);

require_once __DIR__ . '/Assets.php';

/**
 * Warranty lookup by serial number / service tag.
 *
 *   Lenovo — the official Warranty API (supportapi.lenovo.com v2.5) when a
 *            ClientID is saved in Settings › Inventory; otherwise the public
 *            lookup Lenovo's own support site uses (pcsupport.lenovo.com,
 *            keyless, unofficial — it can change without notice).
 *   Dell   — the TechDirect Warranty API (OAuth client credentials, up to 100
 *            tags a call). Idle until a client ID + secret are saved.
 *
 * Results land on the asset: warranty_start / warranty_end / warranty_service,
 * warranty_checked_at, warranty_error. Takes an explicit PDO so the cron
 * script can use it without bootstrap.php.
 *
 * A lookup result is ['ok' => true, 'start', 'end', 'service'] or
 * ['ok' => false, 'final' => bool, 'error' => string]; 'final' failures (bad
 * serial, unknown to the vendor) wait RECHECK_DAYS, others retry next run.
 */
final class Warranty
{
    public const RECHECK_DAYS = 30;
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
    /** Dell entitlements that are not hardware coverage (skipped the same way PSWarranty does). */
    private const DELL_SKIP = ['D', 'DL', 'PJ', 'PR'];

    private static ?string $dellToken = null;

    /** 'dell' | 'lenovo' | null, from a manufacturer name. */
    public static function vendor(?string $manufacturer): ?string
    {
        $m = strtolower((string) $manufacturer);
        return str_contains($m, 'lenovo') ? 'lenovo' : (str_contains($m, 'dell') ? 'dell' : null);
    }

    /**
     * The serial the vendor wants, or null when the stored one cannot be one.
     * Lenovo labels are often scanned whole — "1S" + machine type model + the
     * 8-character serial, or the model + serial without "1S" — so anything
     * longer than 10 characters is cut to its last 8.
     */
    public static function serial(?string $vendor, ?string $raw): ?string
    {
        $s = strtoupper((string) preg_replace('/\s+/', '', (string) $raw));
        if ($vendor === 'dell') {
            return preg_match('/^[A-Z0-9]{7}$/', $s) ? $s : null;
        }
        if ($vendor !== 'lenovo' || !preg_match('/^[A-Z0-9]{4,}$/', $s)) {
            return null;
        }
        return strlen($s) > 10 ? substr($s, -8) : $s;
    }

    public static function dellConfigured(): bool
    {
        return getSetting('warranty_dell_client_id', '') !== '' && getSetting('warranty_dell_client_secret', '') !== '';
    }

    /** Can this asset be looked up right now? (The asset page shows its button only then.) */
    public static function canLookUp(?string $manufacturer, ?string $serial): bool
    {
        $v = self::vendor($manufacturer);
        return self::serial($v, $serial) !== null && ($v === 'lenovo' || self::dellConfigured());
    }

    /** One lookup, or why it cannot be done. */
    public static function lookup(?string $vendor, ?string $raw): array
    {
        if ($vendor === null) {
            return self::fail('Warranty lookup covers Dell and Lenovo only', true);
        }
        $serial = self::serial($vendor, $raw);
        if ($serial === null) {
            return self::fail($vendor === 'dell' ? 'The serial is not a 7-character Dell service tag' : 'No usable Lenovo serial number', true);
        }
        if ($vendor === 'dell') {
            return self::dellConfigured() ? self::dell([$serial])[$serial] : self::fail('Dell lookups need a TechDirect API key (Settings › Inventory)', false);
        }
        return self::lenovo($serial);
    }

    /** Look up one asset now and save the result (the asset page's button). */
    public static function refresh(PDO $db, int $assetId, ?int $userId = null): array
    {
        $s = $db->prepare(
            'SELECT a.serial, mf.name FROM assets a JOIN asset_models m ON m.id = a.model_id
             LEFT JOIN asset_manufacturers mf ON mf.id = m.manufacturer_id WHERE a.id = ?'
        );
        $s->execute([$assetId]);
        [$serial, $mf] = $s->fetch(PDO::FETCH_NUM) ?: [null, null];
        $r = self::lookup(self::vendor($mf), $serial);
        if (self::vendor($mf) !== null) {
            self::save($db, $assetId, $r, $userId);
        }
        return $r;
    }

    /**
     * The daily run: every live Dell/Lenovo asset never looked up, or last
     * looked up RECHECK_DAYS+ ago, oldest first, at most $limit. Lenovo is
     * asked one serial at a time, half a second apart, and the run stops
     * asking it after three failures in a row (blocked or down).
     *
     * @return array{checked:int, found:int, failed:int}
     */
    public static function refreshDue(PDO $db, int $limit, ?callable $log = null): array
    {
        $vendors = "mf.name LIKE '%lenovo%'" . (self::dellConfigured() ? " OR mf.name LIKE '%dell%'" : '');
        $rows = $db->query(
            "SELECT a.id, a.asset_tag, a.serial, mf.name AS mf FROM assets a
             JOIN asset_models m ON m.id = a.model_id JOIN asset_manufacturers mf ON mf.id = m.manufacturer_id
             WHERE a.deleted_at IS NULL AND a.serial IS NOT NULL AND a.serial <> '' AND ($vendors)
               AND (a.warranty_checked_at IS NULL OR a.warranty_checked_at < NOW() - INTERVAL " . self::RECHECK_DAYS . " DAY)
             ORDER BY a.warranty_checked_at IS NOT NULL, a.warranty_checked_at, a.id LIMIT " . max(1, $limit)
        )->fetchAll(PDO::FETCH_ASSOC);

        $n    = ['checked' => 0, 'found' => 0, 'failed' => 0];
        $note = static function (array $row, array $r) use ($db, &$n, $log): void {
            self::save($db, (int) $row['id'], $r);
            $n['checked']++;
            $n[$r['ok'] ? 'found' : 'failed']++;
            if ($log) {
                $log("  {$row['asset_tag']}: " . ($r['ok'] ? 'warranty ends ' . $r['end'] : $r['error']));
            }
        };

        $dell = [];
        $lenovoFails = 0;
        foreach ($rows as $row) {
            $vendor = self::vendor($row['mf']);
            $serial = self::serial($vendor, $row['serial']);
            if ($vendor === 'dell' && $serial !== null) {
                $dell[$serial][] = $row;
                continue;
            }
            if ($vendor === 'lenovo' && $serial !== null) {
                if ($lenovoFails >= 3) {
                    continue;
                }
                usleep(500000);
            }
            $r = self::lookup($vendor, $row['serial']);
            $lenovoFails = !$r['ok'] && empty($r['final']) ? $lenovoFails + 1 : 0;
            if ($lenovoFails === 3 && $log) {
                $log('  Lenovo failed three times in a row; skipping the rest of Lenovo until the next run.');
            }
            $note($row, $r);
        }
        foreach (array_chunk(array_keys($dell), 100) as $tags) {
            foreach (self::dell(array_map('strval', $tags)) as $tag => $r) {
                foreach ($dell[$tag] ?? [] as $row) {
                    $note($row, $r);
                }
            }
        }
        return $n;
    }

    /** Write one result onto the asset; a changed end date also goes in its History. */
    public static function save(PDO $db, int $assetId, array $r, ?int $userId = null): void
    {
        if (!$r['ok']) {
            $db->prepare('UPDATE assets SET warranty_error = ?' . (!empty($r['final']) ? ', warranty_checked_at = NOW()' : '') . ' WHERE id = ?')
               ->execute([mb_substr((string) $r['error'], 0, 255), $assetId]);
            return;
        }
        $s = $db->prepare('SELECT warranty_end FROM assets WHERE id = ?');
        $s->execute([$assetId]);
        $prev = $s->fetchColumn() ?: null;
        $db->prepare(
            'UPDATE assets SET warranty_start = ?, warranty_end = ?, warranty_service = ?, warranty_checked_at = NOW(), warranty_error = NULL WHERE id = ?'
        )->execute([$r['start'], $r['end'], $r['service'] !== '' ? $r['service'] : null, $assetId]);
        if ($prev !== $r['end']) {
            Assets::logHistory($db, $assetId, 'update', ['warranty_end' => ['old' => $prev, 'new' => $r['end']]], $userId, $userId === null ? 'Warranty lookup' : null);
        }
    }

    /** pcsupport.lenovo.com getIbaseInfo: {code, msg:{desc}, data:{baseWarranties[], upgradeWarranties[], contractWarranties[]}}. */
    public static function parseLenovoSupport(array $j): array
    {
        if ((int) ($j['code'] ?? -1) !== 0 || !is_array($j['data'] ?? null)) {
            $msg = is_array($j['msg'] ?? null) ? (string) ($j['msg']['desc'] ?? '') : (string) ($j['msg'] ?? '');
            return self::fail('Lenovo: ' . ($msg !== '' ? $msg : 'serial number not found'), true);
        }
        $d = $j['data'];
        return self::span(
            array_merge((array) ($d['baseWarranties'] ?? []), (array) ($d['upgradeWarranties'] ?? []), (array) ($d['contractWarranties'] ?? [])),
            'startDate', 'endDate', 'name', 'Lenovo'
        );
    }

    /** supportapi.lenovo.com v2.5: {Serial, Warranty[{Name, Start, End}], Contract[{Start, End}]} (or a list of them). */
    public static function parseLenovoApi(mixed $j): array
    {
        if (is_array($j) && array_is_list($j)) {
            $j = $j[0] ?? null;
        }
        if (!is_array($j)) {
            return self::fail('Lenovo API: unexpected answer', false);
        }
        return self::span(array_merge((array) ($j['Warranty'] ?? []), (array) ($j['Contract'] ?? [])), 'Start', 'End', 'Name', 'Lenovo');
    }

    /** One item of Dell's asset-entitlements answer: {serviceTag, invalid, entitlements[{startDate, endDate, serviceLevelCode, serviceLevelDescription}]}. */
    public static function parseDell(array $item): array
    {
        if (!empty($item['invalid'])) {
            return self::fail('Dell: service tag not recognised', true);
        }
        $rows = array_filter(
            (array) ($item['entitlements'] ?? []),
            static fn($e) => is_array($e) && !in_array((string) ($e['serviceLevelCode'] ?? ''), self::DELL_SKIP, true)
        );
        return self::span($rows, 'startDate', 'endDate', 'serviceLevelDescription', 'Dell');
    }

    /** Earliest start, latest end and the coverage names across warranty rows. */
    private static function span(array $rows, string $startKey, string $endKey, string $nameKey, string $vendor): array
    {
        $start = $end = null;
        $names = [];
        foreach ($rows as $r) {
            $e = is_array($r) ? self::day($r[$endKey] ?? null) : null;
            if ($e === null) {
                continue;
            }
            $s = self::day($r[$startKey] ?? null);
            if ($s !== null && ($start === null || $s < $start)) {
                $start = $s;
            }
            if ($end === null || $e > $end) {
                $end = $e;
            }
            $name = trim((string) ($r[$nameKey] ?? ''));
            if ($name !== '') {
                $names[$name] = true;
            }
        }
        if ($end === null) {
            return self::fail("{$vendor}: no warranty on record", true);
        }
        return ['ok' => true, 'start' => $start, 'end' => $end, 'service' => mb_substr(implode('; ', array_keys($names)), 0, 255)];
    }

    private static function day(mixed $v): ?string
    {
        $d = substr((string) $v, 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    private static function fail(string $error, bool $final): array
    {
        return ['ok' => false, 'final' => $final, 'error' => $error];
    }

    private static function lenovo(string $serial): array
    {
        $clientId = trim(getSetting('warranty_lenovo_client_id', ''));
        if ($clientId !== '') {
            [$code, $body] = self::http('GET', 'https://supportapi.lenovo.com/v2.5/warranty?Serial=' . rawurlencode($serial), ['ClientID: ' . $clientId, 'Accept: application/json']);
            return $code === 200 ? self::parseLenovoApi(json_decode($body, true)) : self::httpFail('Lenovo API', $code, $body);
        }
        [$code, $body] = self::http(
            'POST', 'https://pcsupport.lenovo.com/us/en/api/v4/upsell/redport/getIbaseInfo',
            ['Content-Type: application/json', 'Accept: application/json'],
            (string) json_encode(['serialNumber' => $serial, 'machineType' => '', 'country' => 'us', 'language' => 'en'])
        );
        $j = $code === 200 ? json_decode($body, true) : null;
        return is_array($j) ? self::parseLenovoSupport($j) : self::httpFail('Lenovo', $code, $body);
    }

    /** @param string[] $tags  @return array<string, array> result per tag */
    private static function dell(array $tags): array
    {
        if (self::$dellToken === null) {
            [$code, $body] = self::http('POST', 'https://apigtwb2c.us.dell.com/auth/oauth/v2/token', [
                'Authorization: Basic ' . base64_encode(getSetting('warranty_dell_client_id', '') . ':' . getSetting('warranty_dell_client_secret', '')),
                'Content-Type: application/x-www-form-urlencoded',
            ], 'grant_type=client_credentials');
            $token = $code === 200 ? (json_decode($body, true)['access_token'] ?? null) : null;
            if (!is_string($token) || $token === '') {
                return array_fill_keys($tags, self::httpFail('Dell sign-in', $code, $body));
            }
            self::$dellToken = $token;
        }
        [$code, $body] = self::http(
            'GET', 'https://apigtwb2c.us.dell.com/PROD/sbil/eapi/v5/asset-entitlements?servicetags=' . rawurlencode(implode(',', $tags)),
            ['Authorization: Bearer ' . self::$dellToken, 'Accept: application/json']
        );
        $list = $code === 200 ? json_decode($body, true) : null; // errors come back as XML, so check the code first
        if (!is_array($list)) {
            if ($code === 401) {
                self::$dellToken = null;
            }
            return array_fill_keys($tags, self::httpFail('Dell', $code, $body));
        }
        $out = [];
        foreach ($list as $item) {
            if (is_array($item) && isset($item['serviceTag'])) {
                $out[strtoupper((string) $item['serviceTag'])] = self::parseDell($item);
            }
        }
        foreach ($tags as $t) {
            $out[$t] ??= self::fail('Dell: service tag not recognised', true);
        }
        return $out;
    }

    private static function httpFail(string $who, int $code, string $body): array
    {
        return self::fail($code === 0 ? "{$who} unreachable: " . mb_substr($body, 0, 120) : "{$who} answered HTTP {$code}", false);
    }

    /** @return array{0:int, 1:string} HTTP status (0 on a network error) and body (or the curl error). */
    private static function http(string $method, string $url, array $headers, ?string $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => self::UA, // Lenovo's site answers "Access Denied" without a browser user agent
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out = curl_exec($ch);
        return $out === false ? [0, curl_error($ch)] : [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string) $out];
    }
}
