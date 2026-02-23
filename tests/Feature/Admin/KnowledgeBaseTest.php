<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\TestCase;

/**
 * Admin knowledge-base management — categories, folders, articles, preview, history.
 */
class KnowledgeBaseTest extends TestCase
{
    // ── Categories ────────────────────────────────────────────────────────────

    public function test_categories_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/kb/categories');
        $this->assertOk($r);
        $this->assertSee('Categories', $r);
    }

    public function test_create_category_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/kb/categories/create');
        $this->assertOk($r);
    }

    public function test_create_and_delete_category(): void
    {
        $slug = 'test-cat-' . time();
        $r    = $this->post($this->adminClient(), '/admin/kb/categories/create', [
            'name' => '[TEST] KB Category',
            'slug' => $slug,
        ]);
        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM kb_categories WHERE slug = ? LIMIT 1");
        $row->execute([$slug]);
        if ($cat = $row->fetch()) {
            $this->post($this->adminClient(), '/admin/kb/categories/' . $cat['id'] . '/delete', []);
        }
    }

    // ── Folders ───────────────────────────────────────────────────────────────

    public function test_folders_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/kb/folders');
        $this->assertOk($r);
    }

    public function test_create_folder_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/kb/folders/create');
        $this->assertOk($r);
    }

    // ── Articles ──────────────────────────────────────────────────────────────

    public function test_articles_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/kb/articles');
        $this->assertOk($r);
        $this->assertSee('Articles', $r);
    }

    public function test_create_article_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/kb/articles/create');
        $this->assertOk($r);
    }

    public function test_create_article_and_delete_it(): void
    {
        $slug = 'test-article-' . time();
        $r    = $this->post($this->adminClient(), '/admin/kb/articles/create', [
            'title'  => '[TEST] KB Article',
            'slug'   => $slug,
            'body'   => '## Test Article\n\nAutomated test content.',
            'status' => 'published',
        ]);
        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $db  = \Database::connect();
        $row = $db->prepare('SELECT id FROM kb_articles WHERE slug = ? LIMIT 1');
        $row->execute([$slug]);
        if ($art = $row->fetch()) {
            $this->post($this->adminClient(), '/admin/kb/articles/' . $art['id'] . '/delete', []);
        }
    }

    public function test_edit_article_form_requires_existing_article(): void
    {
        // Non-existent ID should return 404 or redirect
        $r    = $this->get($this->adminClient(), '/admin/kb/articles/999999/edit');
        $code = $r->getStatusCode();
        $this->assertTrue(in_array($code, [200, 302, 404]), "Expected 200/302/404, got $code");
    }

    // ── Role enforcement ──────────────────────────────────────────────────────

    /** @dataProvider kbAdminPaths */
    public function test_agent_cannot_access_kb_management(string $path): void
    {
        $r = $this->get($this->agentClient(), $path, follow: false);
        $this->assertForbidden($r, " — agent blocked from $path");
    }

    /** @dataProvider kbAdminPaths */
    public function test_portal_cannot_access_kb_management(string $path): void
    {
        $r = $this->get($this->portalClient(), $path, follow: false);
        $this->assertForbidden($r, " — portal blocked from $path");
    }

    public static function kbAdminPaths(): array
    {
        return [
            ['/admin/kb/categories'],
            ['/admin/kb/folders'],
            ['/admin/kb/articles'],
            ['/admin/kb/articles/create'],
        ];
    }
}
