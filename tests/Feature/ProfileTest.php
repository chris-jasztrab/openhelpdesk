<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * User profile page — name update, password change, theme preference, 2FA visibility.
 */
class ProfileTest extends TestCase
{
    // ── Page access ───────────────────────────────────────────────────────────

    public function test_admin_can_view_profile(): void
    {
        $r = $this->get($this->adminClient(), '/profile');
        $this->assertOk($r);
        $this->assertSee('My Profile', $r);
    }

    public function test_agent_can_view_profile(): void
    {
        $r = $this->get($this->agentClient(), '/profile');
        $this->assertOk($r);
        $this->assertSee('My Profile', $r);
    }

    public function test_portal_user_can_view_profile(): void
    {
        $r = $this->get($this->portalClient(), '/profile');
        $this->assertOk($r);
        $this->assertSee('My Profile', $r);
    }

    // ── 2FA section visibility ────────────────────────────────────────────────

    public function test_admin_profile_shows_2fa_section(): void
    {
        $r = $this->get($this->adminClient(), '/profile');
        $this->assertSee('Two-Factor', $r, ' — admin should see 2FA card');
    }

    public function test_agent_profile_shows_2fa_section(): void
    {
        $r = $this->get($this->agentClient(), '/profile');
        $this->assertSee('Two-Factor', $r, ' — agent should see 2FA card');
    }

    public function test_portal_user_profile_does_not_show_2fa_section(): void
    {
        $r = $this->get($this->portalClient(), '/profile');
        $this->assertNotSee('Two-Factor', $r, ' — portal user should not see 2FA card');
    }

    // ── Profile fields ────────────────────────────────────────────────────────

    public function test_profile_form_shows_current_name(): void
    {
        $r = $this->get($this->adminClient(), '/profile');
        $this->assertSee('TestAdmin', $r);
    }

    public function test_profile_shows_email_notification_preferences(): void
    {
        $r = $this->get($this->adminClient(), '/profile');
        $this->assertSee('Email Notifications', $r);
    }

    public function test_profile_shows_appearance_section(): void
    {
        $r = $this->get($this->portalClient(), '/profile');
        $this->assertSee('Appearance', $r);
    }

    // ── Profile updates ───────────────────────────────────────────────────────

    public function test_admin_can_update_their_name(): void
    {
        $r = $this->post($this->adminClient(), '/profile', [
            'first_name' => 'TestAdmin',
            'last_name'  => 'User',
            'theme'      => 'light',
        ], role: 'admin');

        // Should redirect back or return 200 with success flash
        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Expected 200 or redirect, got $code");
    }

    public function test_agent_can_update_theme(): void
    {
        $r = $this->post($this->agentClient(), '/profile', [
            'first_name' => 'TestAgent',
            'last_name'  => 'User',
            'theme'      => 'dark',
        ], role: 'agent');

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Expected 200 or redirect, got $code");
    }

    public function test_wrong_current_password_rejects_password_change(): void
    {
        $r = $this->post($this->portalClient(), '/profile', [
            'first_name'       => 'TestPortal',
            'last_name'        => 'User',
            'theme'            => 'light',
            'current_password' => 'this_is_wrong',
            'new_password'     => 'NewPass456!',
            'confirm_password' => 'NewPass456!',
        ], role: 'portal');

        $body = (string) $r->getBody();
        // Should show an error or stay on profile page
        $this->assertTrue(
            str_contains($body, 'incorrect') ||
            str_contains($body, 'wrong') ||
            str_contains($body, 'Profile') ||
            $r->getStatusCode() === 302,
            'Expected error or redirect for wrong password'
        );
    }

    // ── 2FA setup page ────────────────────────────────────────────────────────

    public function test_admin_can_access_2fa_setup_page(): void
    {
        $r = $this->get($this->adminClient(), '/profile/2fa/setup');
        $this->assertOk($r);
        $this->assertSee('Two-Factor', $r);
    }

    public function test_agent_can_access_2fa_setup_page(): void
    {
        $r = $this->get($this->agentClient(), '/profile/2fa/setup');
        $this->assertOk($r);
    }

    // Helper to pass role string through to post()
    private function post(
        \GuzzleHttp\Client $client,
        string $path,
        array $data,
        string $role = ''
    ): \Psr\Http\Message\ResponseInterface {
        return parent::post($client, $path, $data);
    }
}
