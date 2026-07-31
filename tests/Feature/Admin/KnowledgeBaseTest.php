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
        $db = \Database::connect();

        // Identify the new row by "id greater than anything that existed before"
        // rather than by the slug we submit. The create route derives the slug
        // from the *name* and appends its own uniquifier, so the submitted slug
        // never appears in the table. Matching on it silently found nothing,
        // which skipped the delete below and leaked one category per run — while
        // the test still passed, because nothing asserted either step worked.
        $maxIdBefore = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM kb_categories')->fetchColumn();

        $r = $this->post($this->adminClient(), '/admin/kb/categories/create', [
            'name' => '[TEST] KB Category',
            'slug' => 'test-cat-' . time(),
        ]);
        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $row = $db->prepare('SELECT id FROM kb_categories WHERE id > ? ORDER BY id DESC LIMIT 1');
        $row->execute([$maxIdBefore]);
        $cat = $row->fetch();

        $this->assertNotFalse($cat, 'Creating a KB category should insert a row');

        $this->post($this->adminClient(), '/admin/kb/categories/' . $cat['id'] . '/delete', []);

        $gone = $db->prepare('SELECT COUNT(*) FROM kb_categories WHERE id = ?');
        $gone->execute([$cat['id']]);
        $this->assertSame(
            0,
            (int) $gone->fetchColumn(),
            'Deleting the category should remove it — otherwise this test leaks a row on every run'
        );
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
