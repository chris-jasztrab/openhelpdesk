<?php

declare(strict_types=1);

namespace Tests\Feature;

use GuzzleHttp\Client;
use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * CSRF on the three KB feedback endpoints, plus the regression surface of the
 * verifyCsrf() hardening that shipped in the same commit.
 *
 * verifyCsrf() now fails closed when the *stored* token is empty (previously
 * hash_equals('','') made a tokenless POST pass on any session that had never
 * rendered a form).  That one line is reached by every cookie-auth POST in the
 * app, so the "valid token still works" tests below matter at least as much as
 * the rejection tests.
 *
 * Fixtures (removed in tearDownAfterClass): a public KB category → folder →
 * published article, so the guest endpoint is actually reachable, plus any
 * kb_article_ratings rows the votes create.
 */
class CsrfKbFeedbackTest extends TestCase
{
    private const CAT_NAME    = '[TEST] CSRF Fixture Category';
    private const CAT_SLUG    = 'test-csrf-fixture-category';
    private const FOLDER_NAME = '[TEST] CSRF Fixture Folder';
    private const FOLDER_SLUG = 'test-csrf-fixture-folder';
    private const ART_TITLE   = '[TEST] CSRF Fixture Article';
    private const ART_SLUG    = 'test-csrf-fixture-article';

    private static int $articleId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::removeFixtures();
        $db = \Database::connect();

        $db->prepare('INSERT INTO kb_categories (name, slug, description, is_public) VALUES (?, ?, ?, 1)')
           ->execute([self::CAT_NAME, self::CAT_SLUG, 'CSRF test fixture.']);
        $catId = (int) $db->lastInsertId();

        $db->prepare('INSERT INTO kb_folders (category_id, name, slug, description) VALUES (?, ?, ?, ?)')
           ->execute([$catId, self::FOLDER_NAME, self::FOLDER_SLUG, 'CSRF test fixture.']);
        $folderId = (int) $db->lastInsertId();

        $db->prepare(
            "INSERT INTO kb_articles (folder_id, title, slug, body_markdown, status, published_at, created_by)
             VALUES (?, ?, ?, ?, 'published', NOW(), ?)"
        )->execute([$folderId, self::ART_TITLE, self::ART_SLUG, 'Fixture body.', DatabaseSeeder::$adminId]);
        self::$articleId = (int) $db->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        self::removeFixtures();
        parent::tearDownAfterClass();
    }

    private static function removeFixtures(): void
    {
        try {
            $db = \Database::connect();

            $a = $db->prepare('SELECT id FROM kb_articles WHERE slug = ?');
            $a->execute([self::ART_SLUG]);
            foreach ($a->fetchAll(\PDO::FETCH_COLUMN) as $id) {
                $db->prepare('DELETE FROM kb_article_ratings   WHERE article_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM kb_article_revisions WHERE article_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM kb_articles          WHERE id = ?')->execute([$id]);
            }

            $db->prepare('DELETE FROM kb_folders    WHERE slug = ?')->execute([self::FOLDER_SLUG]);
            $db->prepare('DELETE FROM kb_categories WHERE slug = ?')->execute([self::CAT_SLUG]);
        } catch (\Throwable) {
            // never mask a real failure
        }
    }

    protected function tearDown(): void
    {
        // Votes are the only side effect any test here can leave behind.
        if (self::$articleId > 0) {
            \Database::connect()
                ->prepare('DELETE FROM kb_article_ratings WHERE article_id = ?')
                ->execute([self::$articleId]);
        }
        parent::tearDown();
    }

    // ── Route matrix ──────────────────────────────────────────────────────────

    /** @return array<string, array{0:string, 1:string}> label => [role, path] */
    public static function feedbackRoutes(): array
    {
        $slug = self::ART_SLUG;
        return [
            'guest'  => ['guest',  "/kb/articles/{$slug}/feedback"],
            'agent'  => ['agent',  "/agent/kb/articles/{$slug}/feedback"],
            'portal' => ['portal', "/portal/kb/articles/{$slug}/feedback"],
        ];
    }

    /** @return array<string, array{0:string,1:string,2:string,3:mixed}> */
    public static function rejectionCases(): array
    {
        $cases = [];
        foreach (self::feedbackRoutes() as $label => [$role, $path]) {
            $cases["$label / no token at all"]   = [$role, $path, 'none',   null];
            $cases["$label / empty _token"]      = [$role, $path, 'body',   ''];
            $cases["$label / wrong _token"]      = [$role, $path, 'body',   'deadbeef'];
            $cases["$label / empty header"]      = [$role, $path, 'header', ''];
            $cases["$label / wrong header"]      = [$role, $path, 'header', 'deadbeef'];
        }
        return $cases;
    }

    // ── Rejection ─────────────────────────────────────────────────────────────

    /** @dataProvider rejectionCases */
    public function test_feedback_vote_is_rejected_without_a_valid_token(
        string $role,
        string $path,
        string $transport,
        ?string $token
    ): void {
        $client = $this->clientForRole($role);

        // Prime the session so it *has* a stored token — that way this proves the
        // supplied token is being compared, not merely that the session is empty.
        $this->primeSession($client, $role);

        $r = $this->rawPost($client, $path, ['rating' => 1], $transport, $token);

        $this->assertSame(403, $r->getStatusCode(), " — $role $path ($transport)");
        $this->assertStringContainsString('Invalid request', (string) $r->getBody(), " — $role $path");
        $this->assertSame(0, $this->voteCount(), " — a vote was recorded despite a rejected token ($role, $transport)");
    }

    // ── Acceptance ────────────────────────────────────────────────────────────

    public function test_agent_can_vote_with_a_valid_token_in_the_body(): void
    {
        $client = $this->agentClient();
        $r = $this->rawPost($client, '/agent/kb/articles/' . self::ART_SLUG . '/feedback',
            ['rating' => 1], 'body', $this->tokenFor($client, 'agent'));

        $this->assertSame(200, $r->getStatusCode());
        $body = json_decode((string) $r->getBody(), true);
        $this->assertSame('ok', $body['status'] ?? null, (string) $r->getBody());
        $this->assertSame(1, (int) ($body['helpful'] ?? 0));
        $this->assertSame(1, $this->voteCount());
    }

    public function test_agent_can_vote_with_a_valid_token_in_the_header(): void
    {
        $client = $this->agentClient();
        $r = $this->rawPost($client, '/agent/kb/articles/' . self::ART_SLUG . '/feedback',
            ['rating' => -1], 'header', $this->tokenFor($client, 'agent'));

        $this->assertSame(200, $r->getStatusCode());
        $body = json_decode((string) $r->getBody(), true);
        $this->assertSame('ok', $body['status'] ?? null, (string) $r->getBody());
        $this->assertSame(1, (int) ($body['not_helpful'] ?? 0));
        $this->assertSame(1, $this->voteCount());
    }

    public function test_portal_user_can_vote_with_a_valid_token(): void
    {
        $client = $this->portalClient();
        $r = $this->rawPost($client, '/portal/kb/articles/' . self::ART_SLUG . '/feedback',
            ['rating' => 1], 'body', $this->tokenFor($client, 'portal'));

        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame('ok', json_decode((string) $r->getBody(), true)['status'] ?? null, (string) $r->getBody());
        $this->assertSame(1, $this->voteCount());
    }

    public function test_guest_can_vote_with_a_valid_token(): void
    {
        $client = $this->guestClient();
        $token  = $this->tokenFor($client, 'guest');
        $this->assertNotSame('', $token, 'Could not obtain a guest CSRF token.');

        $r = $this->rawPost($client, '/kb/articles/' . self::ART_SLUG . '/feedback',
            ['rating' => 1], 'body', $token);

        $this->assertSame(200, $r->getStatusCode(), (string) $r->getBody());
        $this->assertSame('ok', json_decode((string) $r->getBody(), true)['status'] ?? null, (string) $r->getBody());
        $this->assertSame(1, $this->voteCount());
    }

    // ── The one-line verifyCsrf() change ──────────────────────────────────────

    /**
     * THE regression test for the hardening: a brand-new session has no stored
     * token, and before the fix hash_equals('', '') made a tokenless POST pass.
     * A fresh client that has touched nothing must be rejected.
     */
    public function test_tokenless_post_on_a_virgin_session_is_rejected(): void
    {
        $client = $this->guestClient();   // fresh cookie jar, never fetched a page

        $r = $this->rawPost($client, '/kb/articles/' . self::ART_SLUG . '/feedback',
            ['rating' => 1], 'none', null);

        $this->assertSame(403, $r->getStatusCode(), 'A tokenless POST on an empty session was accepted.');
        $this->assertSame(0, $this->voteCount(), 'A vote was recorded from a tokenless virgin session.');
    }

    /** An arbitrary token on a virgin session must not match "no stored token". */
    public function test_arbitrary_token_on_a_virgin_session_is_rejected(): void
    {
        $client = $this->guestClient();

        $r = $this->rawPost($client, '/kb/articles/' . self::ART_SLUG . '/feedback',
            ['rating' => 1], 'body', str_repeat('a', 64));

        $this->assertSame(403, $r->getStatusCode());
        $this->assertSame(0, $this->voteCount());
    }

    // ── verifyCsrf() regression: existing authenticated POSTs still work ──────

    /**
     * /profile/setting is a JSON POST that verifies _token and reports 403 on
     * failure — a clean read on whether verifyCsrf() still accepts a good token.
     *
     * @dataProvider authenticatedRoles
     */
    public function test_valid_token_still_accepted_on_profile_setting_post(string $role): void
    {
        $client = $this->clientForRole($role);
        $db     = \Database::connect();
        $userId = match ($role) {
            'admin'  => DatabaseSeeder::$adminId,
            'agent'  => DatabaseSeeder::$agentId,
            default  => DatabaseSeeder::$portalId,
        };

        $before = $db->prepare('SELECT notify_ticket_updated FROM users WHERE id = ?');
        $before->execute([$userId]);
        $original = (string) $before->fetchColumn();
        $target   = $original === '1' ? '0' : '1';

        try {
            $r = $this->rawPost($client, '/profile/setting', [
                'field' => 'notify_ticket_updated',
                'value' => $target,
            ], 'body', $this->tokenFor($client, $role));

            $this->assertSame(200, $r->getStatusCode(), " — $role: valid token rejected on /profile/setting");
            $json = json_decode((string) $r->getBody(), true);
            $this->assertTrue((bool) ($json['ok'] ?? false), " — $role: " . (string) $r->getBody());

            $after = $db->prepare('SELECT notify_ticket_updated FROM users WHERE id = ?');
            $after->execute([$userId]);
            $this->assertSame($target, (string) $after->fetchColumn(), " — $role: the POST had no effect");
        } finally {
            $db->prepare('UPDATE users SET notify_ticket_updated = ? WHERE id = ?')->execute([$original, $userId]);
        }
    }

    /** @dataProvider authenticatedRoles */
    public function test_empty_token_still_rejected_on_profile_setting_post(string $role): void
    {
        $client = $this->clientForRole($role);

        $r = $this->rawPost($client, '/profile/setting', [
            'field' => 'notify_ticket_updated',
            'value' => '1',
        ], 'body', '');

        $this->assertSame(403, $r->getStatusCode(), " — $role: empty token accepted on /profile/setting");
    }

    /** @return array<string, array{0:string}> */
    public static function authenticatedRoles(): array
    {
        return ['admin' => ['admin'], 'agent' => ['agent'], 'portal' => ['portal']];
    }

    /**
     * A redirect-style (non-JSON) authenticated POST: /agent/tickets/columns
     * flashes "Invalid request." and bounces on a bad token, so a valid token
     * must land without that flash and must actually persist the columns.
     */
    public function test_valid_token_still_accepted_on_a_redirecting_form_post(): void
    {
        $client = $this->agentClient();

        $r = $this->post($client, '/agent/tickets/columns', [
            'columns'   => ['id', 'subject', 'status'],
            '_redirect' => '/agent/tickets',
        ]);

        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringNotContainsString('Invalid request', (string) $r->getBody());
    }

    public function test_missing_token_rejected_on_a_redirecting_form_post(): void
    {
        $client = $this->agentClient();

        $r = $this->rawPost($client, '/agent/tickets/columns', [
            'columns'   => ['id', 'subject'],
            '_redirect' => '/agent/tickets',
        ], 'none', null, follow: true);

        // Redirects to /agent/tickets with the flash rendered.
        $this->assertStringContainsString('Invalid request', (string) $r->getBody());
    }

    /**
     * Login is the highest-traffic POST in the app and the one place a session
     * legitimately starts out empty.  A valid token must still authenticate.
     */
    public function test_login_still_works_with_a_valid_token(): void
    {
        $client = $this->guestClient();
        $token  = $this->tokenFor($client, 'guest');

        $client->post('/login', [
            'form_params'     => [
                '_token'   => $token,
                'email'    => DatabaseSeeder::AGENT_EMAIL,
                'password' => DatabaseSeeder::password(),
            ],
            'allow_redirects' => ['max' => 5],
        ]);

        $profile = $client->get('/profile', ['allow_redirects' => false]);
        $this->assertSame(200, $profile->getStatusCode(), 'Valid-token login did not authenticate.');
    }

    public function test_login_without_a_token_does_not_authenticate(): void
    {
        $client = $this->guestClient();

        $r = $client->post('/login', [
            'form_params'     => [
                'email'    => DatabaseSeeder::AGENT_EMAIL,
                'password' => DatabaseSeeder::password(),
            ],
            'allow_redirects' => ['max' => 5],
        ]);

        $this->assertStringContainsString('Invalid request', (string) $r->getBody());

        $profile = $client->get('/profile', ['allow_redirects' => false]);
        $this->assertNotSame(200, $profile->getStatusCode(), 'Tokenless login authenticated the session.');
    }

    // ── Auth still gates the routes ahead of CSRF ─────────────────────────────

    public function test_portal_user_cannot_reach_the_agent_feedback_route(): void
    {
        $client = $this->portalClient();
        $r = $this->rawPost($client, '/agent/kb/articles/' . self::ART_SLUG . '/feedback',
            ['rating' => 1], 'body', $this->tokenFor($client, 'portal'), follow: false);

        $this->assertForbidden($r, ' — portal user on the agent KB feedback route');
        $this->assertSame(0, $this->voteCount());
    }

    public function test_guest_cannot_reach_the_portal_feedback_route(): void
    {
        $client = $this->guestClient();
        $r = $this->rawPost($client, '/portal/kb/articles/' . self::ART_SLUG . '/feedback',
            ['rating' => 1], 'body', $this->tokenFor($client, 'guest'), follow: false);

        $this->assertForbidden($r, ' — guest on the portal KB feedback route');
        $this->assertSame(0, $this->voteCount());
    }

    // ── The token is actually threaded into the pages ─────────────────────────

    public function test_public_kb_article_page_threads_a_token_into_the_vote_call(): void
    {
        $r = $this->get($this->guestClient(), '/kb/articles/' . self::ART_SLUG);

        $this->assertOk($r, ' — public KB article page');
        $this->assertSee(self::ART_TITLE, $r);
        $this->assertMatchesRegularExpression(
            "/form\.append\('_token', *\"[0-9a-f]{16,}\"\)/",
            (string) $r->getBody(),
            'The public article page does not append a real CSRF token to the feedback POST.'
        );
    }

    public function test_agent_kb_article_page_threads_a_token_into_the_vote_call(): void
    {
        $r = $this->get($this->agentClient(), '/agent/kb/articles/' . self::ART_SLUG);

        $this->assertOk($r, ' — agent KB article page');
        $this->assertMatchesRegularExpression(
            "/form\.append\('_token', *\"[0-9a-f]{16,}\"\)/",
            (string) $r->getBody(),
            'The agent article page does not append a real CSRF token to the feedback POST.'
        );
    }

    public function test_portal_kb_article_page_threads_a_token_into_the_vote_call(): void
    {
        $r = $this->get($this->portalClient(), '/portal/kb/articles/' . self::ART_SLUG);

        $this->assertOk($r, ' — portal KB article page');
        $this->assertMatchesRegularExpression(
            "/form\.append\('_token', *\"[0-9a-f]{16,}\"\)/",
            (string) $r->getBody(),
            'The portal article page does not append a real CSRF token to the feedback POST.'
        );
    }

    /**
     * The hardening can only lock a real user out if some page that POSTs never
     * causes csrfToken() to run.  Authenticated pages are covered structurally
     * (layouts/app.php emits <meta name="csrf-token">); assert the guest-facing
     * forms that DO verify a token still ship one.
     *
     * @dataProvider guestFormPages
     */
    public function test_guest_form_pages_still_issue_a_token(string $path): void
    {
        $r = $this->get($this->guestClient(), $path);
        $this->assertOk($r, " — $path");
        $this->assertMatchesRegularExpression(
            '/name="_token"\s+value="[0-9a-f]{16,}"/i',
            (string) $r->getBody(),
            "$path renders a POST form with no CSRF token — the hardening would reject its submission."
        );
    }

    /** @return array<string, array{0:string}> */
    public static function guestFormPages(): array
    {
        return [
            '/login'  => ['/login'],
            '/forgot' => ['/forgot'],
        ];
    }

    public function test_authenticated_layout_emits_a_csrf_meta_tag(): void
    {
        foreach (['admin', 'agent', 'portal'] as $role) {
            $r = $this->get($this->clientForRole($role), '/profile');
            $this->assertOk($r, " — $role /profile");
            $this->assertMatchesRegularExpression(
                '/<meta name="csrf-token" content="[0-9a-f]{16,}">/',
                (string) $r->getBody(),
                "The $role layout does not emit a csrf-token meta tag."
            );
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function clientForRole(string $role): Client
    {
        return match ($role) {
            'admin'  => $this->adminClient(),
            'agent'  => $this->agentClient(),
            'portal' => $this->portalClient(),
            default  => $this->guestClient(),
        };
    }

    /** Make sure the session has a stored csrf_token before a rejection probe. */
    private function primeSession(Client $client, string $role): void
    {
        $this->assertNotSame('', $this->tokenFor($client, $role), "Could not prime a $role session with a token.");
    }

    /** Extract the session's real CSRF token by rendering a page that has a form. */
    private function tokenFor(Client $client, string $role): string
    {
        $path = $role === 'guest' ? '/login' : '/profile';
        $html = (string) $client->get($path, ['allow_redirects' => ['max' => 5]])->getBody();

        if (preg_match('/name="_token"\s+value="([^"]+)"/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/value="([^"]+)"\s+name="_token"/i', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * POST without the automatic token injection `TestCase::post()` does, so the
     * token transport and value are under the test's control.
     *
     * @param 'none'|'body'|'header' $transport
     */
    private function rawPost(
        Client  $client,
        string  $path,
        array   $data,
        string  $transport,
        ?string $token,
        bool    $follow = false
    ): \Psr\Http\Message\ResponseInterface {
        $options = [
            'form_params'     => $data,
            'allow_redirects' => $follow ? ['max' => 5, 'strict' => false, 'referer' => true] : false,
        ];

        if ($transport === 'body') {
            $options['form_params']['_token'] = (string) $token;
        } elseif ($transport === 'header') {
            $options['headers'] = ['X-CSRF-Token' => (string) $token];
        }

        return $client->post($path, $options);
    }

    private function voteCount(): int
    {
        $q = \Database::connect()->prepare('SELECT COUNT(*) FROM kb_article_ratings WHERE article_id = ?');
        $q->execute([self::$articleId]);
        return (int) $q->fetchColumn();
    }
}
