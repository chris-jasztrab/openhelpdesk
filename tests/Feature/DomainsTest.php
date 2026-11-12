<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domains;
use PHPUnit\Framework\TestCase;

require_once ROOT_DIR . '/src/Domains.php';

/**
 * The pure parts of domain tracking: DNS wire parsing, SPF/DMARC verdicts,
 * registry parsing and certificate name matching. No network.
 */
class DomainsTest extends TestCase
{
    public function test_parses_a_compressed_dns_answer(): void
    {
        // Answer to "example.ca MX": one MX whose owner and target both use compression pointers.
        $q    = Domains::buildQuery('example.ca', 15, 7);
        $pkt  = substr($q, 0, 2) . pack('nnnnn', 0x8180, 1, 2, 0, 0) . substr($q, 12);
        $pkt .= "\xC0\x0C" . pack('nnNn', 15, 1, 300, 9) . pack('n', 10) . "\x04mail\xC0\x0C";
        $pkt .= "\xC0\x0C" . pack('nnNn', 16, 1, 60, 8) . "\x03abc\x03def";

        $r = Domains::parseResponse($pkt);

        $this->assertSame(0, $r['rcode']);
        $this->assertSame(['name' => 'example.ca', 'type' => 'MX', 'ttl' => 300, 'data' => '10 mail.example.ca'], $r['answers'][0]);
        $this->assertSame('abcdef', $r['answers'][1]['data'], 'TXT chunks are joined');
        $this->assertNull(Domains::parseResponse(substr($pkt, 0, 30)), 'truncated packet is rejected, not half-read');
    }

    public function test_spf_verdicts(): void
    {
        $this->assertSame('missing', Domains::analyzeSpf(['some-verification=abc'])['status']);
        $this->assertSame('ok', Domains::analyzeSpf(['v=spf1 include:a.example -all'])['status']);
        $this->assertSame('warn', Domains::analyzeSpf(['v=spf1 mx ?all'])['status']);
        $this->assertSame('fail', Domains::analyzeSpf(['v=spf1 -all', 'v=spf1 mx -all'])['status'], 'two SPF records is a permerror');
    }

    public function test_spf_counts_lookups_through_includes(): void
    {
        $zone = [
            'a.example' => ['v=spf1 include:b.example mx a -all'],          // 3 here
            'b.example' => ['v=spf1 a mx ptr exists:x.example include:a.example -all'], // 5 here; the loop back to a is counted once, not followed
        ];
        $spf = Domains::analyzeSpf(['v=spf1 include:a.example ip4:1.2.3.4 ~all'], static fn (string $n): array => $zone[$n] ?? []);

        $this->assertSame(9, $spf['lookups']);
        $this->assertSame('ok', $spf['status']);

        $zone['a.example'] = ['v=spf1 include:b.example mx a a:one.example a:two.example -all'];
        $this->assertSame('fail', Domains::analyzeSpf(['v=spf1 include:a.example ~all'], static fn (string $n): array => $zone[$n] ?? [])['status'], 'over 10 lookups');
    }

    public function test_dmarc_verdicts(): void
    {
        $this->assertSame('missing', Domains::analyzeDmarc([])['status']);
        $this->assertSame('warn', Domains::analyzeDmarc(['v=DMARC1; p=none; rua=mailto:a@example.ca'])['status']);
        $ok = Domains::analyzeDmarc(['v=DMARC1; p=reject; sp=none']);
        $this->assertSame(['ok', 'reject'], [$ok['status'], $ok['policy']], 'sp= must not be read as the policy');
    }

    public function test_registry_parsers(): void
    {
        $whois = "Domain Name: example.ca\nRegistry Expiry Date: 2030-04-05T04:00:00Z\nRegistrar: Example Registrar Inc.\nRegistrar IANA ID: not applicable\n";
        $this->assertSame(['registrar' => 'Example Registrar Inc.', 'expires_at' => '2030-04-05'], Domains::parseWhois($whois));

        $rdap = [
            'events'   => [['eventAction' => 'registration', 'eventDate' => '2001-04-05T12:00:00Z'], ['eventAction' => 'expiration', 'eventDate' => '2030-04-05T12:00:00Z']],
            'entities' => [['roles' => ['registrar'], 'vcardArray' => ['vcard', [['version', [], 'text', '4.0'], ['fn', [], 'text', 'Example Registrar Inc.']]]]],
        ];
        $this->assertSame(['registrar' => 'Example Registrar Inc.', 'expires_at' => '2030-04-05'], Domains::parseRdap($rdap));
    }

    public function test_certificate_name_matching_and_status(): void
    {
        $this->assertTrue(Domains::nameMatches('www.example.ca', ['*.example.ca']));
        $this->assertFalse(Domains::nameMatches('example.ca', ['*.example.ca']), 'a wildcard does not cover the bare domain');
        $this->assertFalse(Domains::nameMatches('a.b.example.ca', ['*.example.ca']), 'a wildcard covers one label only');

        $cert = ['found' => true, 'name_match' => true, 'trusted' => true, 'valid_to' => date('Y-m-d', strtotime('+20 days'))];
        $this->assertSame('warn', Domains::certStatus($cert, 30)[1]);
        $this->assertSame('ok', Domains::certStatus($cert, 14)[1]);
        $this->assertSame('fail', Domains::certStatus(['valid_to' => date('Y-m-d', strtotime('-1 day'))] + $cert, 30)[1]);
        $this->assertSame('none', Domains::certStatus(['found' => false], 30)[1]);
    }

    public function test_hosts_stay_under_their_domain_and_alert_tiers(): void
    {
        $this->assertSame('www.example.ca', Domains::hostUnder('WWW', 'example.ca'));
        $this->assertSame('www.example.ca', Domains::hostUnder('www.example.ca.', 'example.ca'));
        $this->assertSame('evil.com.example.ca', Domains::hostUnder('evil.com', 'example.ca'), 'a foreign name is forced under the domain');
        $this->assertNull(Domains::hostUnder('bad host!', 'example.ca'));
        $this->assertSame(['first', 'week', 'week', 'expired'], array_map([Domains::class, 'tier'], [30, 7, 1, 0]));
    }
}
