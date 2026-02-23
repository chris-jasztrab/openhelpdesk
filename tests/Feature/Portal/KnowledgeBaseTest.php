<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use Tests\Support\TestCase;

/**
 * Knowledge base — public access (guest + portal user) and search.
 */
class KnowledgeBaseTest extends TestCase
{
    // ── Public KB (no login required) ─────────────────────────────────────────

    public function test_public_kb_home_loads_for_guests(): void
    {
        $r = $this->get($this->guestClient(), '/kb');
        $this->assertOk($r);
    }

    public function test_public_kb_search_endpoint_returns_json(): void
    {
        $r = $this->get($this->guestClient(), '/kb/search?q=test');
        $this->assertOk($r);
        $ct = $r->getHeaderLine('Content-Type');
        $this->assertStringContainsString('json', $ct);
    }

    public function test_public_kb_search_with_empty_query(): void
    {
        $r = $this->get($this->guestClient(), '/kb/search?q=');
        $this->assertOk($r);
    }

    // ── Portal KB (authenticated user) ────────────────────────────────────────

    public function test_portal_kb_index_loads(): void
    {
        $r = $this->get($this->portalClient(), '/portal/kb');
        $this->assertOk($r);
    }

    public function test_portal_kb_search_returns_json(): void
    {
        $r = $this->get($this->portalClient(), '/portal/kb/search?q=test');
        $this->assertOk($r);
        $ct = $r->getHeaderLine('Content-Type');
        $this->assertStringContainsString('json', $ct);
    }

    // ── Admin and agent can also browse the portal KB ────────────────────────

    public function test_admin_can_browse_portal_kb(): void
    {
        $r = $this->get($this->adminClient(), '/portal/kb');
        $this->assertOk($r);
    }

    public function test_agent_can_browse_portal_kb(): void
    {
        $r = $this->get($this->agentClient(), '/portal/kb');
        $this->assertOk($r);
    }

    // ── Non-existent category/article returns 404 or redirect ────────────────

    public function test_unknown_kb_category_slug(): void
    {
        $r    = $this->get($this->guestClient(), '/kb/totally-unknown-category-xyz');
        $code = $r->getStatusCode();
        $this->assertTrue(in_array($code, [200, 302, 404]), "Expected 200/302/404, got $code");
    }

    public function test_unknown_kb_article_slug(): void
    {
        $r    = $this->get($this->guestClient(), '/kb/articles/totally-unknown-article-xyz');
        $code = $r->getStatusCode();
        $this->assertTrue(in_array($code, [200, 302, 404]), "Expected 200/302/404, got $code");
    }

    // ── Article feedback voting (authenticated) ───────────────────────────────

    public function test_article_feedback_endpoint_reachable(): void
    {
        // We don't have a real article slug to vote on, so we expect 404 or
        // redirect — the important thing is that it's not a 500 or 403 for
        // authenticated portal users.
        $r    = $this->post($this->portalClient(), '/kb/articles/nonexistent/feedback', ['vote' => '1']);
        $code = $r->getStatusCode();
        $this->assertTrue(
            in_array($code, [200, 302, 404]),
            "Expected 200/302/404, got $code"
        );
    }
}
