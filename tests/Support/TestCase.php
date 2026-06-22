<?php

declare(strict_types=1);

namespace Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

/**
 * Base test case for HTTP integration tests.
 *
 * Each role gets one lazily-created Guzzle client (with its own cookie jar)
 * that is shared across all tests in a single PHPUnit run.  The CSRF token is
 * extracted once after login and cached — it stays valid for the whole session.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    // ── Shared state ──────────────────────────────────────────────────────────
    protected static string $base;

    /** @var array<string, Client> */
    private static array $authClients = [];

    /** @var array<string, string> cached CSRF token per role */
    private static array $csrfCache = [];

    // ──────────────────────────────────────────────────────────────────────────

    public static function setUpBeforeClass(): void
    {
        self::$base = rtrim((string) (getenv('TEST_BASE_URL') ?: 'http://localhost:8000'), '/');
    }

    // ── Role clients ──────────────────────────────────────────────────────────

    protected function adminClient(): Client
    {
        return $this->clientFor('admin');
    }

    protected function agentClient(): Client
    {
        return $this->clientFor('agent');
    }

    protected function portalClient(): Client
    {
        return $this->clientFor('portal');
    }

    /** A fresh unauthenticated client (new jar each call). */
    protected function guestClient(): Client
    {
        return new Client([
            'base_uri'        => self::$base,
            'cookies'         => new CookieJar(),
            'http_errors'     => false,
            'allow_redirects' => ['max' => 5, 'strict' => false, 'referer' => true],
        ]);
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    /**
     * GET a path, following redirects (default) or not.
     */
    protected function get(Client $client, string $path, bool $follow = true): ResponseInterface
    {
        return $client->get($path, [
            'allow_redirects' => $follow
                ? ['max' => 5, 'strict' => false, 'referer' => true]
                : false,
        ]);
    }

    /**
     * POST form data to $path with a valid CSRF token.
     *
     * The CSRF token for authenticated clients is cached from login.
     * For guest clients, it is extracted from the login page.
     */
    protected function post(
        Client $client,
        string $path,
        array  $data,
        bool   $follow = true
    ): ResponseInterface {
        // Find the role this client belongs to (if any) for the cached CSRF
        $csrf = '';
        foreach (['admin', 'agent', 'portal'] as $role) {
            if (isset(self::$authClients[$role]) && self::$authClients[$role] === $client) {
                $csrf = self::$csrfCache[$role] ?? '';
                break;
            }
        }

        // If no cached token, try extracting it from the target page first
        if ($csrf === '') {
            $page = $client->get($path, ['allow_redirects' => ['max' => 5]]);
            $csrf = $this->extractCsrf((string) $page->getBody()) ?? '';
        }

        return $client->post($path, [
            'form_params'     => array_merge(['_token' => $csrf], $data),
            'allow_redirects' => $follow
                ? ['max' => 5, 'strict' => false, 'referer' => true]
                : false,
        ]);
    }

    // ── Assertion helpers ─────────────────────────────────────────────────────

    protected function assertOk(ResponseInterface $r, string $context = ''): void
    {
        $this->assertSame(200, $r->getStatusCode(), "Expected HTTP 200{$context}");
    }

    protected function assertSee(string $text, ResponseInterface $r, string $context = ''): void
    {
        $this->assertStringContainsString(
            $text,
            (string) $r->getBody(),
            "Expected to see «{$text}»{$context}"
        );
    }

    protected function assertNotSee(string $text, ResponseInterface $r, string $context = ''): void
    {
        $this->assertStringNotContainsString(
            $text,
            (string) $r->getBody(),
            "Did not expect to see «{$text}»{$context}"
        );
    }

    protected function assertForbidden(ResponseInterface $r, string $context = ''): void
    {
        $code = $r->getStatusCode();
        $this->assertTrue(
            $code === 403 || $code === 302,
            "Expected 403 or redirect, got {$code}{$context}"
        );
    }

    protected function assertRedirectContains(string $fragment, ResponseInterface $r, string $context = ''): void
    {
        $code = $r->getStatusCode();
        $this->assertContains($code, [301, 302, 303], "Expected redirect{$context}");
        $this->assertStringContainsString(
            $fragment,
            $r->getHeaderLine('Location'),
            "Expected redirect to contain «{$fragment}»{$context}"
        );
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function clientFor(string $role): Client
    {
        if (!isset(self::$authClients[$role])) {
            self::$authClients[$role] = $this->buildAuthClient($role);
        }
        return self::$authClients[$role];
    }

    private function buildAuthClient(string $role): Client
    {
        $emails = [
            'admin'  => DatabaseSeeder::ADMIN_EMAIL,
            'agent'  => DatabaseSeeder::AGENT_EMAIL,
            'portal' => DatabaseSeeder::PORTAL_EMAIL,
        ];

        $jar    = new CookieJar();
        $client = new Client([
            'base_uri'        => self::$base,
            'cookies'         => $jar,
            'http_errors'     => false,
            'allow_redirects' => ['max' => 5, 'strict' => false, 'referer' => true],
        ]);

        // 1. Get CSRF from login page
        $loginHtml = (string) $client->get('/login')->getBody();
        $csrf      = $this->extractCsrf($loginHtml) ?? '';

        // 2. Log in
        $client->post('/login', [
            'form_params' => [
                '_token'   => $csrf,
                'email'    => $emails[$role],
                'password' => DatabaseSeeder::password(),
            ],
        ]);

        // 3. Cache CSRF from profile page (always has a form; token is session-scoped)
        $profileHtml          = (string) $client->get('/profile')->getBody();
        self::$csrfCache[$role] = $this->extractCsrf($profileHtml) ?? '';

        return $client;
    }

    private function extractCsrf(string $html): ?string
    {
        // Handles both attribute orderings (field is name="_token")
        if (preg_match('/name="_token"\s+value="([^"]+)"/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/value="([^"]+)"\s+name="_token"/i', $html, $m)) {
            return $m[1];
        }
        return null;
    }
}
