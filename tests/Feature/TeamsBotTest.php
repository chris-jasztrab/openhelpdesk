<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

require_once ROOT_DIR . '/src/TeamsBot.php';

/**
 * Teams bot: the command parser and the Bot Framework token check. Both are
 * pure (the signing-key lookup is injected), so no HTTP server and no calls to
 * Microsoft are involved.
 */
class TeamsBotTest extends TestCase
{
    /** @dataProvider commands */
    public function test_parseCommand(string $text, string $cmd, int $id, string $rest): void
    {
        $this->assertSame(['cmd' => $cmd, 'id' => $id, 'text' => $rest], \TeamsBot::parseCommand($text));
    }

    public static function commands(): array
    {
        return [
            ['',                                   'help',    0,   ''],
            ['help',                               'help',    0,   ''],
            ['Hi!',                                'help',    0,   ''],
            ['my tickets',                         'list',    0,   ''],
            ['List',                               'list',    0,   ''],
            ['status 123',                         'show',    123, ''],
            ['#123',                               'show',    123, ''],
            ['ticket 45?',                         'show',    45,  ''],
            ['open 7',                             'show',    7,   ''],
            ['close 123',                          'close',   123, ''],
            ['Close #123 fixed by restart',        'close',   123, 'fixed by restart'],
            ['resolve 9: done',                    'resolve', 9,   'done'],
            ['reopen 9',                           'reopen',  9,   ''],
            ['comment 123 still broken',           'comment', 123, 'still broken'],
            ['reply on #5 - thanks',               'comment', 5,   'thanks'],
            ['new Printer jammed',                 'new',     0,   'Printer jammed'],
            ['Open a ticket: Projector | Room 2B', 'new',     0,   'Projector | Room 2B'],
            ['create: laptop wont boot',           'new',     0,   'laptop wont boot'],
            ['what is the weather',                'unknown', 0,   'what is the weather'],
            ['new',                                'unknown', 0,   'new'],
        ];
    }

    public function test_verifyToken_accepts_a_valid_token_and_rejects_everything_else(): void
    {
        $key    = self::rsaKey();
        $pub    = openssl_pkey_get_details($key)['key'];
        $lookup = static fn (string $kid): ?string => $kid === 'k1' ? $pub : null;
        $now    = time();
        $svc    = 'https://smba.trafficmanager.net/amer/';
        $claims = [
            'iss'        => 'https://api.botframework.com',
            'aud'        => \TeamsBot::appId(),
            'exp'        => $now + 600,
            'nbf'        => $now - 60,
            'serviceurl' => $svc,
        ];
        $jwt = self::sign($key, ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'k1'], $claims);

        $this->assertTrue(\TeamsBot::verifyToken($jwt, $svc, $lookup, $now));
        $this->assertTrue(\TeamsBot::verifyToken($jwt, rtrim($svc, '/'), $lookup, $now), 'trailing slash is not significant');

        $this->assertFalse(\TeamsBot::verifyToken($jwt, 'https://evil.example.com/', $lookup, $now), 'serviceUrl must match the signed claim');
        $this->assertFalse(\TeamsBot::verifyToken($jwt, $svc, $lookup, $now + 7200), 'expired');
        $this->assertFalse(\TeamsBot::verifyToken($jwt, $svc, static fn () => null, $now), 'unknown kid');
        $this->assertFalse(\TeamsBot::verifyToken('not.a.jwt', $svc, $lookup, $now));

        $wrongIssuer = self::sign($key, ['alg' => 'RS256', 'kid' => 'k1'], ['iss' => 'https://evil'] + $claims);
        $this->assertFalse(\TeamsBot::verifyToken($wrongIssuer, $svc, $lookup, $now), 'wrong issuer');

        $wrongAud = self::sign($key, ['alg' => 'RS256', 'kid' => 'k1'], ['aud' => 'someone-else'] + $claims);
        $this->assertFalse(\TeamsBot::verifyToken($wrongAud, $svc, $lookup, $now), 'wrong audience');

        $none = self::sign($key, ['alg' => 'none', 'kid' => 'k1'], $claims);
        $this->assertFalse(\TeamsBot::verifyToken($none, $svc, $lookup, $now), 'alg must be RS256');

        // Re-encode the payload with an extra claim but keep the old signature.
        [$h, , $s] = explode('.', $jwt);
        $forged = $h . '.' . self::b64(json_encode(['x' => 1] + $claims)) . '.' . $s;
        $this->assertFalse(\TeamsBot::verifyToken($forged, $svc, $lookup, $now), 'tampered payload');

        $otherKey = self::sign(self::rsaKey(), ['alg' => 'RS256', 'kid' => 'k1'], $claims);
        $this->assertFalse(\TeamsBot::verifyToken($otherKey, $svc, $lookup, $now), 'signed by a different key');
    }

    public function test_rsaPemFromJwk_rebuilds_the_public_key(): void
    {
        $key = self::rsaKey();
        $d   = openssl_pkey_get_details($key);
        $pem = \TeamsBot::rsaPemFromJwk(self::b64($d['rsa']['n']), self::b64($d['rsa']['e']));
        $this->assertNotNull($pem);
        $rebuilt = openssl_pkey_get_public((string) $pem);
        $this->assertNotFalse($rebuilt);
        $this->assertSame($d['key'], openssl_pkey_get_details($rebuilt)['key']);
    }

    private static function rsaKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        return $key;
    }

    private static function sign(\OpenSSLAsymmetricKey $key, array $header, array $claims): string
    {
        $data = self::b64(json_encode($header)) . '.' . self::b64(json_encode($claims));
        openssl_sign($data, $sig, $key, OPENSSL_ALGO_SHA256);
        return $data . '.' . self::b64($sig);
    }

    private static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
}
