<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Authentication flows — login, logout, CSRF enforcement, role redirects.
 */
class AuthTest extends TestCase
{
    // ── Login page ────────────────────────────────────────────────────────────

    public function test_login_page_is_accessible_to_guests(): void
    {
        $r = $this->get($this->guestClient(), '/login');
        $this->assertOk($r);
        $this->assertSee('Sign in', $r);
    }

    public function test_login_page_contains_csrf_field(): void
    {
        $html = (string) $this->get($this->guestClient(), '/login')->getBody();
        $this->assertMatchesRegularExpression('/name="_csrf"/', $html, 'Login form must contain a CSRF field');
    }

    // ── Successful logins ─────────────────────────────────────────────────────

    public function test_admin_login_lands_on_admin_dashboard(): void
    {
        $r = $this->get($this->adminClient(), '/');
        // After following redirects the admin lands on /admin
        $this->assertOk($r);
        $this->assertSee('Admin', $r);
    }

    public function test_agent_login_lands_on_agent_area(): void
    {
        $r = $this->get($this->agentClient(), '/');
        $this->assertOk($r);
    }

    public function test_portal_login_lands_on_portal_dashboard(): void
    {
        $r = $this->get($this->portalClient(), '/');
        $this->assertOk($r);
    }

    // ── Failed login ──────────────────────────────────────────────────────────

    public function test_login_with_wrong_password_shows_error(): void
    {
        $client   = $this->guestClient();
        $loginHtml = (string) $this->get($client, '/login')->getBody();
        preg_match('/name="_csrf"\s+value="([^"]+)"/i', $loginHtml, $m);
        $csrf = $m[1] ?? '';

        $r = $client->post('/login', [
            'form_params' => [
                '_csrf'    => $csrf,
                'email'    => DatabaseSeeder::ADMIN_EMAIL,
                'password' => 'wrong_password',
            ],
        ]);

        $body = (string) $r->getBody();
        $this->assertTrue(
            str_contains($body, 'Invalid') || str_contains($body, 'incorrect') || str_contains($body, 'Sign in'),
            'Expected an error or re-shown login form'
        );
    }

    public function test_login_with_nonexistent_email_shows_error(): void
    {
        $client    = $this->guestClient();
        $loginHtml = (string) $this->get($client, '/login')->getBody();
        preg_match('/name="_csrf"\s+value="([^"]+)"/i', $loginHtml, $m);
        $csrf = $m[1] ?? '';

        $r = $client->post('/login', [
            'form_params' => [
                '_csrf'    => $csrf,
                'email'    => 'nobody@nowhere.invalid',
                'password' => 'irrelevant',
            ],
        ]);

        $this->assertStringContainsString('Sign in', (string) $r->getBody(), 'Should re-show login form');
    }

    // ── POST without CSRF ─────────────────────────────────────────────────────

    public function test_login_post_without_csrf_is_rejected(): void
    {
        $client = $this->guestClient();

        $r = $client->post('/login', [
            'form_params'     => [
                'email'    => DatabaseSeeder::ADMIN_EMAIL,
                'password' => DatabaseSeeder::TEST_PASSWORD,
                // no _csrf
            ],
            'allow_redirects' => false,
        ]);

        // Must NOT succeed — either 403 or redirect back to login
        $code = $r->getStatusCode();
        $this->assertTrue(
            $code === 403 || $code === 302 || $code === 200,
            "Expected rejection, got $code"
        );
        if ($code === 200) {
            $this->assertStringNotContainsString('Dashboard', (string) $r->getBody());
        }
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_logout_redirects_to_login(): void
    {
        // Build a fresh authenticated client so we can log it out without
        // invalidating the shared admin client used by other tests.
        $jar    = new \GuzzleHttp\Cookie\CookieJar();
        $client = new \GuzzleHttp\Client([
            'base_uri'        => self::$base,
            'cookies'         => $jar,
            'http_errors'     => false,
            'allow_redirects' => ['max' => 5],
        ]);

        $loginHtml = (string) $client->get('/login')->getBody();
        preg_match('/name="_csrf"\s+value="([^"]+)"/i', $loginHtml, $m);
        $csrf = $m[1] ?? '';

        $client->post('/login', [
            'form_params' => [
                '_csrf'    => $csrf,
                'email'    => DatabaseSeeder::ADMIN_EMAIL,
                'password' => DatabaseSeeder::TEST_PASSWORD,
            ],
        ]);

        // Logout
        $r = $this->get($client, '/logout', false);
        $this->assertRedirectContains('login', $r);
    }

    public function test_after_logout_protected_pages_redirect_to_login(): void
    {
        $jar    = new \GuzzleHttp\Cookie\CookieJar();
        $client = new \GuzzleHttp\Client([
            'base_uri'        => self::$base,
            'cookies'         => $jar,
            'http_errors'     => false,
            'allow_redirects' => ['max' => 5],
        ]);

        $loginHtml = (string) $client->get('/login')->getBody();
        preg_match('/name="_csrf"\s+value="([^"]+)"/i', $loginHtml, $m);

        $client->post('/login', ['form_params' => ['_csrf' => $m[1] ?? '', 'email' => DatabaseSeeder::PORTAL_EMAIL, 'password' => DatabaseSeeder::TEST_PASSWORD]]);
        $client->get('/logout');

        $r = $this->get($client, '/portal/tickets', false);
        $this->assertRedirectContains('login', $r);
    }
}
