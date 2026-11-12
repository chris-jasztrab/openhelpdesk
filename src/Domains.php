<?php

declare(strict_types=1);

/**
 * Domain tracking: registrar + expiry, public DNS, mail authentication
 * (MX / SPF / DKIM / DMARC) and the TLS certificate on every host.
 *
 * DNS is asked of a public resolver directly (TCP/53) rather than through
 * dns_get_record(), because the app server sits behind internal split-horizon
 * DNS and would otherwise report the internal view of our own zones. If the
 * public resolver is unreachable it falls back to the system resolver and says
 * so in the result.
 *
 * With a Cloudflare API token the full zone is listed from Cloudflare, which
 * also discovers every host and DKIM selector; without one only the apex, the
 * hosts an admin listed and the common DKIM selectors can be looked up (DNS
 * has no "list this zone" query).
 */
final class Domains
{
    public const DEFAULT_SELECTORS = ['selector1', 'selector2', 'google', 'default', 'k1', 's1', 's2', 'dkim', 'mail'];
    public const DOMAIN_RE = '/^(?=.{1,253}$)([a-z0-9_]([a-z0-9_-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    private const TYPES = ['A' => 1, 'NS' => 2, 'CNAME' => 5, 'SOA' => 6, 'MX' => 15, 'TXT' => 16, 'AAAA' => 28, 'CAA' => 257];
    private const TIMEOUT = 4;
    private const MAX_HOSTS = 60;

    private static bool $publicDown = false;
    private static bool $usedSystem = false;
    /** @var resource|null */
    private static $sock = null;

    /* ───────────────────────── Settings ───────────────────────── */

    public static function alertDays(): int
    {
        return max(1, min(365, (int) getSetting('domain_alert_days', '30')));
    }

    private static function resolver(): string
    {
        $r = trim(getSetting('domain_dns_resolver', '1.1.1.1'));
        return filter_var($r, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $r : '';
    }

    /* ───────────────────────── DNS ───────────────────────── */

    public static function buildQuery(string $name, int $qtype, int $id): string
    {
        $q = pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0);
        foreach (explode('.', rtrim($name, '.')) as $label) {
            $q .= chr(strlen($label)) . $label;
        }
        return $q . "\0" . pack('nn', $qtype, 1);
    }

    /** @return array{rcode:int, answers:list<array{name:string,type:string,ttl:int,data:string}>}|null null = malformed */
    public static function parseResponse(string $pkt): ?array
    {
        $len = strlen($pkt);
        if ($len < 12) {
            return null;
        }
        $h   = unpack('nid/nflags/nqd/nan', $pkt);
        $off = 12;
        for ($i = 0; $i < $h['qd']; $i++) {
            self::readName($pkt, $off);
            $off += 4;
        }
        $out = [];
        for ($i = 0; $i < $h['an']; $i++) {
            $name = self::readName($pkt, $off);
            if ($off + 10 > $len) {
                return null;
            }
            $rr  = unpack('ntype/nclass/Nttl/nlen', substr($pkt, $off, 10));
            $off += 10;
            $end = $off + $rr['len'];
            if ($end > $len) {
                return null;
            }
            $type = array_search($rr['type'], self::TYPES, true);
            $p    = $off;
            $raw  = substr($pkt, $off, $rr['len']);
            $data = null;
            switch ($type) {
                case 'A':
                case 'AAAA':
                    $data = (string) @inet_ntop($raw);
                    break;
                case 'NS':
                case 'CNAME':
                    $data = self::readName($pkt, $p);
                    break;
                case 'MX':
                    $pref = unpack('n', $raw)[1];
                    $p   += 2;
                    $data = $pref . ' ' . self::readName($pkt, $p);
                    break;
                case 'TXT':
                    $data = '';
                    for ($j = 0; $j < $rr['len'];) {
                        $l     = ord($raw[$j]);
                        $data .= substr($raw, $j + 1, $l);
                        $j    += $l + 1;
                    }
                    break;
                case 'SOA':
                    $m    = self::readName($pkt, $p);
                    $r    = self::readName($pkt, $p);
                    $data = $m . ' ' . $r . ' ' . (unpack('N', substr($pkt, $p, 4))[1] ?? '');
                    break;
                case 'CAA':
                    $tl   = ord($raw[1] ?? "\0");
                    $data = ord($raw[0]) . ' ' . substr($raw, 2, $tl) . ' "' . substr($raw, 2 + $tl) . '"';
                    break;
            }
            $off = $end;
            if ($data !== null) {
                $out[] = ['name' => strtolower($name), 'type' => (string) $type, 'ttl' => (int) $rr['ttl'], 'data' => $data];
            }
        }
        return ['rcode' => $h['flags'] & 0xF, 'answers' => $out];
    }

    private static function readName(string $pkt, int &$off): string
    {
        $labels = [];
        $pos    = $off;
        $jumped = false;
        $len    = strlen($pkt);
        for ($hops = 0; $pos < $len && $hops < 128; $hops++) {
            $l = ord($pkt[$pos]);
            if ($l === 0) {
                $pos++;
                break;
            }
            if (($l & 0xC0) === 0xC0) {
                if ($pos + 1 >= $len) {
                    break;
                }
                if (!$jumped) {
                    $off = $pos + 2;
                }
                $pos    = (($l & 0x3F) << 8) | ord($pkt[$pos + 1]);
                $jumped = true;
                continue;
            }
            $labels[] = substr($pkt, $pos + 1, $l);
            $pos     += $l + 1;
        }
        if (!$jumped) {
            $off = $pos;
        }
        return implode('.', $labels);
    }

    /**
     * Records of one type at one name, as the public internet sees them.
     *
     * @return list<array{name:string,type:string,ttl:int,data:string}>
     */
    public static function dnsQuery(string $name, string $type): array
    {
        $resolver = self::resolver();
        if ($resolver !== '' && !self::$publicDown) {
            $resp   = self::exchange($resolver, self::buildQuery($name, self::TYPES[$type], random_int(1, 65535)));
            $parsed = $resp !== null ? self::parseResponse($resp) : null;
            if ($parsed !== null) {
                return array_values(array_filter($parsed['answers'], static fn ($a) => $a['type'] === $type || $a['type'] === 'CNAME'));
            }
            self::$publicDown = true; // don't pay the timeout again on every later query
        }

        self::$usedSystem = true;
        $const = defined('DNS_' . $type) ? constant('DNS_' . $type) : null;
        $rows  = $const !== null ? @dns_get_record($name, $const) : false;
        $out   = [];
        foreach ($rows ?: [] as $r) {
            $data = match ($r['type']) {
                'A'           => $r['ip'],
                'AAAA'        => $r['ipv6'],
                'NS', 'CNAME' => $r['target'],
                'MX'          => $r['pri'] . ' ' . $r['target'],
                'TXT'         => $r['txt'],
                'SOA'         => $r['mname'] . ' ' . $r['rname'] . ' ' . $r['serial'],
                'CAA'         => $r['flags'] . ' ' . $r['tag'] . ' "' . $r['value'] . '"',
                default       => null,
            };
            if ($data !== null) {
                $out[] = ['name' => strtolower($r['host']), 'type' => $r['type'], 'ttl' => (int) $r['ttl'], 'data' => (string) $data];
            }
        }
        return $out;
    }

    /**
     * One query/answer over a kept-open TCP connection (TCP, not UDP: apex TXT
     * sets routinely exceed the 512-byte UDP answer). The resolver drops idle
     * connections, so a failed exchange reconnects and tries once more before
     * the caller gives up on the public view.
     */
    private static function exchange(string $resolver, string $pkt): ?string
    {
        for ($try = 0; $try < 2; $try++) {
            if (self::$sock === null) {
                self::$sock = @stream_socket_client("tcp://{$resolver}:53", $en, $es, self::TIMEOUT) ?: null;
                if (self::$sock === null) {
                    continue;
                }
                stream_set_timeout(self::$sock, self::TIMEOUT);
            }
            if (@fwrite(self::$sock, pack('n', strlen($pkt)) . $pkt) !== false) {
                $head = self::readN(2);
                $resp = $head !== null ? self::readN(unpack('n', $head)[1]) : null;
                if ($resp !== null) {
                    return $resp;
                }
            }
            @fclose(self::$sock);
            self::$sock = null;
        }
        return null;
    }

    private static function readN(int $n): ?string
    {
        $buf = '';
        while (strlen($buf) < $n) {
            $chunk = @fread(self::$sock, $n - strlen($buf));
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $buf .= $chunk;
        }
        return $buf;
    }

    /** @return list<string> */
    private static function txt(string $name): array
    {
        return array_column(array_filter(self::dnsQuery($name, 'TXT'), static fn ($a) => $a['type'] === 'TXT'), 'data');
    }

    /* ───────────────────────── Mail authentication ───────────────────────── */

    /**
     * @param list<string> $txts   TXT records at the apex
     * @param callable|null $lookup fn(string $name): list<string> — TXT lookup used to follow include:/redirect=
     * @return array{record:?string,status:string,notes:list<string>,lookups:int}
     */
    public static function analyzeSpf(array $txts, ?callable $lookup = null): array
    {
        $spf = array_values(array_filter($txts, static fn ($t) => stripos($t, 'v=spf1') === 0));
        if ($spf === []) {
            return ['record' => null, 'status' => 'missing', 'notes' => ['No SPF record. Any server can claim to send mail for this domain.'], 'lookups' => 0];
        }
        $notes  = [];
        $status = 'ok';
        if (count($spf) > 1) {
            $status  = 'fail';
            $notes[] = count($spf) . ' SPF records published. Receivers treat more than one as a permanent error.';
        }
        $rec = $spf[0];
        if (preg_match('/(?:^|\s)([-~?+]?)all\b/i', $rec, $m)) {
            if ($m[1] === '?' || $m[1] === '+' || $m[1] === '') {
                $status  = $status === 'fail' ? 'fail' : 'warn';
                $notes[] = 'Ends in "' . $m[1] . 'all", which does not tell receivers to reject or flag other senders.';
            }
        } elseif (stripos($rec, 'redirect=') === false) {
            $status  = $status === 'fail' ? 'fail' : 'warn';
            $notes[] = 'No "all" mechanism, so unlisted senders get a neutral result.';
        }
        $seen    = [];
        $lookups = self::spfLookups($rec, $lookup, $seen, 0);
        if ($lookups > 10) {
            $status  = 'fail';
            $notes[] = "Needs {$lookups} DNS lookups. The limit is 10; past it SPF fails for everyone.";
        }
        return ['record' => $rec, 'status' => $status, 'notes' => $notes, 'lookups' => $lookups];
    }

    private static function spfLookups(string $rec, ?callable $lookup, array &$seen, int $depth): int
    {
        $n = 0;
        foreach (preg_split('/\s+/', trim($rec)) ?: [] as $term) {
            $term = ltrim(strtolower($term), '+-~?');
            if (preg_match('/^(?:include:|redirect=)(\S+)$/', $term, $m)) {
                $n++;
                if ($lookup !== null && $depth < 10 && !isset($seen[$m[1]])) {
                    $seen[$m[1]] = true;
                    foreach ($lookup($m[1]) as $t) {
                        if (stripos($t, 'v=spf1') === 0) {
                            $n += self::spfLookups($t, $lookup, $seen, $depth + 1);
                            break;
                        }
                    }
                }
            } elseif (preg_match('/^(a|mx|ptr)([:\/].*)?$|^exists:/', $term)) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * @param list<string> $txts TXT records at _dmarc.<domain>
     * @return array{record:?string,status:string,policy:?string,notes:list<string>}
     */
    public static function analyzeDmarc(array $txts): array
    {
        $rec = null;
        foreach ($txts as $t) {
            if (stripos($t, 'v=DMARC1') === 0) {
                $rec = $t;
                break;
            }
        }
        if ($rec === null) {
            return ['record' => null, 'status' => 'missing', 'policy' => null, 'notes' => ['No DMARC record. Receivers decide for themselves what to do with mail that fails SPF and DKIM.']];
        }
        $policy = preg_match('/(?:^|;)\s*p=\s*(\w+)/i', $rec, $m) ? strtolower($m[1]) : null;
        $notes  = [];
        $status = 'ok';
        if ($policy === 'none' || $policy === null) {
            $status  = 'warn';
            $notes[] = 'Policy is "none": failures are reported but nothing is quarantined or rejected.';
        }
        if (stripos($rec, 'rua=') === false) {
            $notes[] = 'No rua address, so no aggregate reports are being collected.';
        }
        return ['record' => $rec, 'status' => $status, 'policy' => $policy, 'notes' => $notes];
    }

    /* ───────────────────────── Registry (registrar + expiry) ───────────────────────── */

    /** @return array{registrar:?string,expires_at:?string,source:?string} */
    public static function registry(string $domain): array
    {
        $json = self::httpGet('https://rdap.org/domain/' . rawurlencode($domain));
        $data = $json !== null ? json_decode($json, true) : null;
        if (is_array($data)) {
            $r = self::parseRdap($data);
            if ($r['registrar'] !== null || $r['expires_at'] !== null) {
                return $r + ['source' => 'RDAP'];
            }
        }
        $tld   = substr($domain, (int) strrpos($domain, '.') + 1);
        $refer = preg_match('/^whois:\s*(\S+)/mi', self::whois('whois.iana.org', $tld), $m) ? $m[1] : null;
        if ($refer !== null) {
            $r = self::parseWhois(self::whois($refer, $domain));
            if ($r['registrar'] !== null || $r['expires_at'] !== null) {
                return $r + ['source' => 'WHOIS'];
            }
        }
        return ['registrar' => null, 'expires_at' => null, 'source' => null];
    }

    /** @return array{registrar:?string,expires_at:?string} */
    public static function parseRdap(array $d): array
    {
        $expires = null;
        foreach ($d['events'] ?? [] as $ev) {
            if (($ev['eventAction'] ?? '') === 'expiration' && ($ts = strtotime((string) ($ev['eventDate'] ?? '')))) {
                $expires = date('Y-m-d', $ts);
            }
        }
        $registrar = null;
        foreach ($d['entities'] ?? [] as $ent) {
            if (in_array('registrar', $ent['roles'] ?? [], true)) {
                foreach ($ent['vcardArray'][1] ?? [] as $v) {
                    if (($v[0] ?? '') === 'fn' && is_string($v[3] ?? null) && $v[3] !== '') {
                        $registrar = $v[3];
                    }
                }
            }
        }
        return ['registrar' => $registrar, 'expires_at' => $expires];
    }

    /** @return array{registrar:?string,expires_at:?string} */
    public static function parseWhois(string $text): array
    {
        $expires = null;
        if (preg_match('/^\s*(?:Registry Expiry Date|Registrar Registration Expiration Date|Expiry Date|Expiration Date|Expires(?: On)?|paid-till)\s*:\s*(.+)$/mi', $text, $m)
            && ($ts = strtotime(trim($m[1])))) {
            $expires = date('Y-m-d', $ts);
        }
        $registrar = preg_match('/^\s*Registrar(?: Name)?\s*:\s*(\S.*)$/mi', $text, $m) ? trim($m[1]) : null;
        return ['registrar' => $registrar, 'expires_at' => $expires];
    }

    private static function whois(string $server, string $query): string
    {
        $fp = @fsockopen($server, 43, $en, $es, self::TIMEOUT);
        if (!$fp) {
            return '';
        }
        stream_set_timeout($fp, self::TIMEOUT * 2);
        fwrite($fp, $query . "\r\n");
        $out = (string) stream_get_contents($fp, 65536);
        fclose($fp);
        return $out;
    }

    /** @param list<string> $headers */
    private static function httpGet(string $url, array $headers = []): ?string
    {
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
        ]);
        $body = curl_exec($c);
        $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return is_string($body) && $code >= 200 && $code < 300 ? $body : null;
    }

    /* ───────────────────────── Cloudflare ───────────────────────── */

    /**
     * Every record in the zone, or null if the token can't see this zone.
     *
     * @return list<array{name:string,type:string,ttl:int,data:string,proxied:bool}>|null
     */
    public static function cloudflareRecords(string $domain, string $token): ?array
    {
        $auth = ['Authorization: Bearer ' . $token];
        $base = 'https://api.cloudflare.com/client/v4/zones';
        $z    = json_decode((string) self::httpGet($base . '?name=' . rawurlencode($domain), $auth), true);
        $zone = $z['result'][0]['id'] ?? null;
        if (!is_string($zone) || !preg_match('/^[a-f0-9]{32}$/', $zone)) {
            return null;
        }
        $out  = [];
        $page = 1;
        do {
            $r = json_decode((string) self::httpGet("{$base}/{$zone}/dns_records?per_page=100&page={$page}", $auth), true);
            if (empty($r['success'])) {
                return null;
            }
            foreach ($r['result'] as $rec) {
                $out[] = [
                    'name'    => strtolower((string) $rec['name']),
                    'type'    => (string) $rec['type'],
                    'ttl'     => (int) ($rec['ttl'] ?? 0),
                    'data'    => (isset($rec['priority']) ? $rec['priority'] . ' ' : '') . ($rec['content'] ?? ''),
                    'proxied' => !empty($rec['proxied']),
                ];
            }
            $pages = (int) ($r['result_info']['total_pages'] ?? 1);
        } while ($page++ < $pages && $page <= 50);
        return $out;
    }

    /* ───────────────────────── TLS certificates ───────────────────────── */

    /** @param list<string> $names certificate SANs (or the CN when there are none) */
    public static function nameMatches(string $host, array $names): bool
    {
        $host = strtolower($host);
        foreach ($names as $n) {
            $n = strtolower($n);
            if ($n === $host || (str_starts_with($n, '*.') && str_contains($host, '.') && substr($host, (int) strpos($host, '.')) === substr($n, 1))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Where to read $host's certificate, in order: its first public address,
     * then the name as this server resolves it. $internalFirst reverses that,
     * for a split-horizon name whose public address reaches the wrong box from inside.
     *
     * @param list<string> $ips public A records for the host
     * @return list<string>
     */
    public static function certTargets(string $host, array $ips, bool $internalFirst = false): array
    {
        $t = array_merge(array_slice($ips, 0, 1), [$host]);
        return $internalFirst ? array_reverse($t) : $t;
    }

    /**
     * The certificate served on :443 for $host, from the first of certTargets()
     * that answers.
     *
     * @param list<string> $ips public A records for the host
     */
    public static function certificate(string $host, array $ips = [], ?array $reachable = null, bool $internalFirst = false): array
    {
        foreach (self::certTargets($host, $ips, $internalFirst) as $target) {
            if ($reachable !== null && !isset($reachable[$target])) {
                continue; // already known not to answer on 443 — don't wait out a timeout per host
            }
            $cert = self::fetchCert($target, $host, false);
            if ($cert === null) {
                continue;
            }
            $p    = openssl_x509_parse($cert) ?: [];
            $sans = preg_match_all('/DNS:([^,\s]+)/', (string) ($p['extensions']['subjectAltName'] ?? ''), $m) ? $m[1] : [];
            $str  = static fn ($v): string => is_array($v) ? implode(', ', $v) : (string) $v;
            $cn   = $str($p['subject']['CN'] ?? '');
            return [
                'found'      => true,
                'via'        => $target,
                // Read through this server's own DNS, not the public address: on a split-horizon
                // network that can be a different (internal) server than the internet reaches.
                'internal'   => $target === $host,
                'subject'    => $cn,
                'issuer'     => $str($p['issuer']['O'] ?? $p['issuer']['CN'] ?? ''),
                'valid_from' => isset($p['validFrom_time_t']) ? date('Y-m-d', $p['validFrom_time_t']) : null,
                'valid_to'   => isset($p['validTo_time_t']) ? date('Y-m-d', $p['validTo_time_t']) : null,
                'name_match' => self::nameMatches($host, $sans ?: [$cn]),
                // A second, verifying handshake: chain, hostname and dates as a browser would judge them.
                'trusted'    => self::fetchCert($target, $host, true) !== null,
            ];
        }
        return ['found' => false];
    }

    /**
     * Which of these addresses / names accept a TCP connection on :443, probed
     * all at once. A zone is mostly hosts with no web server, and waiting out a
     * timeout for each in turn made a 40-host check take minutes.
     *
     * @param list<string> $targets
     * @return array<string, true>
     */
    public static function reachable(array $targets): array
    {
        $socks = [];
        foreach (array_unique($targets) as $t) {
            $s = @stream_socket_client("tcp://{$t}:443", $en, $es, self::TIMEOUT, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
            if ($s) {
                stream_set_blocking($s, false);
                $socks[$t] = $s;
            }
        }
        $ok       = [];
        $deadline = microtime(true) + self::TIMEOUT;
        while ($socks !== [] && ($left = $deadline - microtime(true)) > 0) {
            $r = null;
            $w = $e = array_values($socks);
            if (!@stream_select($r, $w, $e, (int) $left, (int) (($left - (int) $left) * 1e6))) {
                break;
            }
            // Writable = connect finished (a refused connect has no peer name); except = failed (Windows).
            foreach (array_merge($w, $e) as $s) {
                $t = array_search($s, $socks, true);
                if ($t === false) {
                    continue;
                }
                if (in_array($s, $w, true) && @stream_socket_get_name($s, true) !== false) {
                    $ok[$t] = true;
                }
                fclose($s);
                unset($socks[$t]);
            }
        }
        array_map('fclose', $socks);
        return $ok;
    }

    /**
     * One-word verdict on a stored certificate result.
     *
     * @return array{0:string,1:string} [label, status] — status is ok | warn | fail | none
     */
    public static function certStatus(array $cert, int $alertDays): array
    {
        if (empty($cert['found']) || empty($cert['valid_to'])) {
            return ['No HTTPS', 'none'];
        }
        $d = self::daysLeft($cert['valid_to']);
        return match (true) {
            $d < 0                      => ['Expired', 'fail'],
            empty($cert['name_match'])  => ['Wrong name', 'fail'],
            empty($cert['trusted'])     => ['Untrusted', 'fail'],
            $d <= $alertDays            => ["Expires in {$d}d", 'warn'],
            default                     => ['OK', 'ok'],
        };
    }

    /** @return \OpenSSLCertificate|null */
    private static function fetchCert(string $target, string $host, bool $verify)
    {
        $ctx = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => $verify,
            'verify_peer_name'  => $verify,
            'peer_name'         => $host,
            'SNI_enabled'       => true,
        ]]);
        $fp = @stream_socket_client("ssl://{$target}:443", $en, $es, self::TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            return null;
        }
        $cert = stream_context_get_params($fp)['options']['ssl']['peer_certificate'] ?? null;
        fclose($fp);
        return $cert ?: null;
    }

    /* ───────────────────────── The check ───────────────────────── */

    /** Lines / commas → clean lowercase list. */
    public static function splitList(?string $raw): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($s) => strtolower(trim($s)),
            preg_split('/[\s,]+/', (string) $raw) ?: []
        ))));
    }

    /** "www" or "www.example.ca" → "www.example.ca"; null if it isn't a valid name under $domain. */
    public static function hostUnder(string $host, string $domain): ?string
    {
        $host = rtrim(strtolower(trim($host)), '.');
        if ($host !== $domain && !str_ends_with($host, '.' . $domain)) {
            $host .= '.' . $domain;
        }
        return preg_match(self::DOMAIN_RE, $host) ? $host : null;
    }

    /** Run every lookup for one `domains` row. Network only — touches no tables. */
    public static function check(array $row): array
    {
        self::$usedSystem = false;
        self::$publicDown = false; // a blip on one domain must not push the next one onto internal DNS
        $domain = (string) $row['name'];
        $token  = getSetting('cloudflare_api_token', '');
        $zone   = $token !== '' ? self::cloudflareRecords($domain, $token) : null;

        $hosts     = [$domain];
        $selectors = self::DEFAULT_SELECTORS;
        $required  = array_values(array_filter(self::splitList($row['dkim_selectors'] ?? ''), static fn ($s) => (bool) preg_match('/^[a-z0-9-]{1,63}$/', $s)));
        foreach (self::splitList($row['extra_hosts'] ?? '') as $h) {
            if (($h = self::hostUnder($h, $domain)) !== null) {
                $hosts[] = $h;
            }
        }
        foreach ($zone ?? [] as $r) {
            if (in_array($r['type'], ['A', 'AAAA', 'CNAME'], true) && !str_contains($r['name'], '*') && !str_contains($r['name'], '_')) {
                $hosts[] = $r['name'];
            }
            if (preg_match('/^([a-z0-9-]+)\._domainkey\./', $r['name'], $m)) {
                $selectors[] = $m[1];
            }
        }
        $hosts     = array_slice(array_values(array_unique($hosts)), 0, self::MAX_HOSTS);
        $selectors = array_values(array_unique(array_merge($selectors, $required)));

        $public = [];
        $ask    = static function (string $name, string $type) use (&$public): array {
            $answers = self::dnsQuery($name, $type);
            foreach ($answers as $a) {
                $public[$a['name'] . '|' . $a['type'] . '|' . $a['data']] = $a;
            }
            return $answers;
        };
        $only = static fn (array $answers, string $type): array => array_column(array_filter($answers, static fn ($a) => $a['type'] === $type), 'data');

        foreach (['NS', 'SOA', 'CAA'] as $t) {
            $ask($domain, $t);
        }
        $mx    = $only($ask($domain, 'MX'), 'MX');
        $spf   = self::analyzeSpf($only($ask($domain, 'TXT'), 'TXT'), [self::class, 'txt']);
        $dmarc = self::analyzeDmarc($only($ask('_dmarc.' . $domain, 'TXT'), 'TXT'));

        $dkim = [];
        foreach ($selectors as $sel) {
            $keys = array_values(array_filter($only($ask("{$sel}._domainkey.{$domain}", 'TXT'), 'TXT'), static fn ($t) => stripos($t, 'p=') !== false));
            if ($keys !== []) {
                $revoked = (bool) preg_match('/(?:^|;)\s*p=\s*(;|$)/i', $keys[0]);
                $dkim[]  = ['selector' => $sel, 'record' => $keys[0], 'status' => $revoked ? 'warn' : 'ok'];
            } elseif (in_array($sel, $required, true)) {
                $dkim[] = ['selector' => $sel, 'record' => null, 'status' => 'fail'];
            }
        }
        $dkimStatus = $dkim === [] ? 'missing' : (in_array('fail', array_column($dkim, 'status'), true) ? 'fail' : (in_array('ok', array_column($dkim, 'status'), true) ? 'ok' : 'warn'));

        $hostIps = [];
        foreach ($hosts as $h) {
            $hostIps[$h] = $only($ask($h, 'A'), 'A');
            $ask($h, 'AAAA');
        }
        $targets = $hosts;
        foreach ($hostIps as $ips) {
            if ($ips !== []) {
                $targets[] = $ips[0];
            }
        }
        $reachable   = self::reachable($targets);
        $internal    = array_flip(self::splitList($row['internal_hosts'] ?? ''));
        $hostResults = [];
        foreach ($hosts as $h) {
            $hostResults[] = ['host' => $h, 'ips' => $hostIps[$h], 'cert' => self::certificate($h, $hostIps[$h], $reachable, isset($internal[$h]))];
        }

        return [
            'checked_at'       => date('Y-m-d H:i:s'),
            'registry'         => self::registry($domain),
            'records_source'   => $zone !== null ? 'cloudflare' : 'lookup',
            'cloudflare_error' => $token !== '' && $zone === null,
            'dns_view'         => self::$usedSystem ? 'system' : self::resolver(),
            'records'          => $zone ?? array_values($public),
            'mx'               => $mx,
            'spf'              => $spf,
            'dmarc'            => $dmarc,
            'dkim'             => ['status' => $dkimStatus, 'selectors' => $dkim],
            'hosts'            => $hostResults,
        ];
    }

    /** Check one domain and store the result. A failed registry lookup keeps whatever was there. */
    public static function refresh(PDO $db, int $id): bool
    {
        $st = $db->prepare('SELECT * FROM domains WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $data = self::check($row);
        $db->prepare(
            'UPDATE domains SET check_data = ?, checked_at = NOW(),
                    registrar = COALESCE(?, registrar), expires_at = COALESCE(?, expires_at)
             WHERE id = ?'
        )->execute([
            json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES),
            $data['registry']['registrar'],
            $data['registry']['expires_at'],
            $id,
        ]);
        return true;
    }

    /** Re-read one host's certificate into the stored check, so a per-host setting shows without a full re-check. */
    public static function recheckHost(PDO $db, int $id, string $host, bool $internalFirst): void
    {
        $st = $db->prepare('SELECT check_data FROM domains WHERE id = ?');
        $st->execute([$id]);
        $data = json_decode((string) $st->fetchColumn(), true);
        if (!is_array($data)) {
            return;
        }
        foreach ($data['hosts'] ?? [] as $i => $h) {
            if ($h['host'] === $host) {
                $data['hosts'][$i]['cert'] = self::certificate($host, $h['ips'], null, $internalFirst);
            }
        }
        $db->prepare('UPDATE domains SET check_data = ? WHERE id = ?')
           ->execute([json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES), $id]);
    }

    /* ───────────────────────── Expiry alerts ───────────────────────── */

    public static function daysLeft(string $date): int
    {
        return (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date))->format('%r%a');
    }

    /**
     * Everything expiring within the alert window, soonest first. Reads only
     * what the last check stored — no network — so it is cheap enough for the
     * dashboard.
     *
     * @return list<array{key:string,kind:string,label:string,domain_id:int,domain:string,expires_on:string,days_left:int}>
     */
    public static function alerts(PDO $db, ?int $days = null): array
    {
        $days ??= self::alertDays();
        $out  = [];
        try {
            $rows = $db->query('SELECT id, name, expires_at, check_data, ignored_hosts FROM domains ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
        foreach ($rows as $r) {
            $base = ['domain_id' => (int) $r['id'], 'domain' => $r['name']];
            if ($r['expires_at'] !== null && ($d = self::daysLeft($r['expires_at'])) <= $days) {
                $out[] = $base + ['key' => 'domain:' . $r['name'], 'kind' => 'domain', 'label' => $r['name'], 'expires_on' => $r['expires_at'], 'days_left' => $d];
            }
            foreach (self::splitHosts(json_decode((string) $r['check_data'], true)['hosts'] ?? [], $r['ignored_hosts'])[0] as $h) {
                $to = $h['cert']['valid_to'] ?? null;
                if ($to !== null && ($d = self::daysLeft($to)) <= $days) {
                    $out[] = $base + ['key' => 'cert:' . $h['host'], 'kind' => 'cert', 'label' => $h['host'], 'expires_on' => $to, 'days_left' => $d, 'internal' => !empty($h['cert']['internal'])];
                }
            }
        }
        usort($out, static fn ($a, $b) => $a['days_left'] <=> $b['days_left']);
        return $out;
    }

    /**
     * Split a check's hosts into the ones to show and the ones this domain ignores.
     *
     * @param list<array> $hosts check_data['hosts']
     * @return array{0:list<array>,1:list<array>} [shown, ignored]
     */
    public static function splitHosts(array $hosts, ?string $ignoredRaw): array
    {
        $ignored = array_flip(self::splitList($ignoredRaw));
        $out     = [[], []];
        foreach ($hosts as $h) {
            $out[isset($ignored[$h['host']]) ? 1 : 0][] = $h;
        }
        return $out;
    }

    /** Which reminder an alert is due: once on entering the window, again at a week, again once expired. */
    public static function tier(int $daysLeft): string
    {
        return $daysLeft <= 0 ? 'expired' : ($daysLeft <= 7 ? 'week' : 'first');
    }
}
