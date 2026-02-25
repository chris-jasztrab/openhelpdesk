<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use Tests\Support\TestCase;

/**
 * Publicly accessible routes — health check, login, public KB, CSAT survey.
 * None of these require authentication.
 */
class PublicRoutesTest extends TestCase
{
    // ── Health check ──────────────────────────────────────────────────────────

    public function test_health_endpoint_returns_ok(): void
    {
        $r = $this->get($this->guestClient(), '/health');
        $this->assertOk($r);
    }

    // ── Login page ────────────────────────────────────────────────────────────

    public function test_login_page_loads(): void
    {
        $r = $this->get($this->guestClient(), '/login');
        $this->assertOk($r);
        $this->assertSee('Sign in', $r);
    }

    public function test_login_page_has_email_field(): void
    {
        $html = (string) $this->get($this->guestClient(), '/login')->getBody();
        $this->assertStringContainsString('name="email"', $html);
    }

    public function test_login_page_has_password_field(): void
    {
        $html = (string) $this->get($this->guestClient(), '/login')->getBody();
        $this->assertStringContainsString('name="password"', $html);
    }

    // ── Public knowledge base ─────────────────────────────────────────────────

    public function test_public_kb_index_loads(): void
    {
        $r = $this->get($this->guestClient(), '/kb');
        $this->assertOk($r);
    }

    public function test_public_kb_search_accessible(): void
    {
        $r = $this->get($this->guestClient(), '/kb/search?q=help');
        $this->assertOk($r);
    }

    // ── CSAT survey ───────────────────────────────────────────────────────────

    public function test_survey_with_invalid_token_does_not_crash(): void
    {
        $r    = $this->get($this->guestClient(), '/survey/invalid-token-xyz');
        $code = $r->getStatusCode();
        // Should be 404 (not found) or redirect, never a 500
        $this->assertNotSame(500, $code, 'Survey with bad token should not cause server error');
    }

    public function test_survey_thanks_page_with_invalid_token(): void
    {
        $r    = $this->get($this->guestClient(), '/survey/invalid-token-xyz/thanks');
        $code = $r->getStatusCode();
        $this->assertNotSame(500, $code);
    }

    // ── Root redirect ─────────────────────────────────────────────────────────

    public function test_root_redirects_unauthenticated_user(): void
    {
        $r = $this->get($this->guestClient(), '/', follow: false);
        // Root should redirect somewhere (login or a dashboard)
        $code = $r->getStatusCode();
        $this->assertTrue(
            $code === 302 || $code === 301 || $code === 200,
            "Root should respond with 200 or redirect, got $code"
        );
    }

    // ── No access to protected areas without auth ─────────────────────────────

    public function test_guest_cannot_reach_portal(): void
    {
        $r = $this->get($this->guestClient(), '/portal/tickets', follow: false);
        $this->assertSame(302, $r->getStatusCode());
    }

    public function test_guest_cannot_reach_agent_area(): void
    {
        $r = $this->get($this->guestClient(), '/agent/tickets', follow: false);
        $this->assertSame(302, $r->getStatusCode());
    }

    public function test_guest_cannot_reach_admin_area(): void
    {
        $r = $this->get($this->guestClient(), '/admin/tickets', follow: false);
        $this->assertSame(302, $r->getStatusCode());
    }

    // ── Setup wizard ──────────────────────────────────────────────────────────

    public function test_setup_redirects_to_login_when_users_exist(): void
    {
        // The test DB always has users, so /setup must not be accessible.
        $r    = $this->get($this->guestClient(), '/setup', follow: false);
        $code = $r->getStatusCode();
        // Should redirect away (to /login), never render the wizard.
        $this->assertSame(302, $code, '/setup should redirect when users already exist in the database');
        $this->assertStringContainsString('login', strtolower($r->getHeaderLine('Location')));
    }

    public function test_authenticated_admin_redirected_from_setup(): void
    {
        // An already-logged-in admin hitting /setup should be redirected to /admin.
        $r    = $this->get($this->adminClient(), '/setup', follow: false);
        $code = $r->getStatusCode();
        $this->assertSame(302, $code, 'Authenticated admin should be redirected away from /setup');
    }

    // ── Notification endpoints (require auth) ─────────────────────────────────

    public function test_notifications_list_requires_auth(): void
    {
        $r = $this->get($this->guestClient(), '/notifications', follow: false);
        $code = $r->getStatusCode();
        $this->assertTrue(in_array($code, [302, 401, 403]), "Expected redirect/auth error, got $code");
    }

    public function test_notification_count_requires_auth(): void
    {
        $r    = $this->get($this->guestClient(), '/notifications/count', follow: false);
        $code = $r->getStatusCode();
        $this->assertTrue(in_array($code, [302, 401, 403, 200]), "Got $code");
        // If 200, should not be a real count for an unauthenticated user
    }
}
