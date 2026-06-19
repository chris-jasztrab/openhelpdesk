<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\TestCase;

/**
 * CSRF protection on the form-builder JSON endpoints.
 *
 * These endpoints read their body from php://input and are invoked by fetch()
 * with an X-CSRF-Token header (see templates/.../ticket-fields.php). They must
 * reject a request that lacks a valid token, and accept one that carries it.
 */
class FormBuilderCsrfTest extends TestCase
{
    public function test_json_endpoint_rejects_missing_csrf_header(): void
    {
        // Non-mutating: an invalid field is rejected anyway, but the CSRF gate
        // fires first — without the header we must get a 403, not a 200/422.
        $r = $this->adminClient()->post('/admin/forms/system-label', [
            'json'            => ['field' => 'subject', 'label' => '[TEST] should not apply'],
            'http_errors'     => false,
            'allow_redirects' => false,
        ]);

        $this->assertSame(403, $r->getStatusCode(),
            ' — form-builder JSON endpoint must reject a request with no X-CSRF-Token');
    }

    public function test_json_endpoint_accepts_valid_csrf_header(): void
    {
        $client = $this->adminClient();

        // Pull the session CSRF token the front-end reads from the meta tag.
        $page = (string) $client->get('/admin/workflows/ticket-fields')->getBody();
        $this->assertSame(1, preg_match('/name="csrf-token" content="([^"]+)"/', $page, $m),
            ' — csrf-token meta tag should be present on the form-builder page');
        $csrf = $m[1];

        // layout/save with an empty order list is a valid no-op (no rows touched).
        $r = $client->post('/admin/forms/1/layout/save', [
            'json'            => ['order' => []],
            'headers'         => ['X-CSRF-Token' => $csrf],
            'http_errors'     => false,
            'allow_redirects' => false,
        ]);

        $this->assertSame(200, $r->getStatusCode(),
            ' — valid CSRF header should be accepted');
        $this->assertStringContainsString('"success":true', (string) $r->getBody(),
            ' — endpoint should still function with a valid token');
    }
}
