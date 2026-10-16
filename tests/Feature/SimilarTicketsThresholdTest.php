<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Similar past tickets "Minimum match to show" floor (ai_similar_min_relevance).
 * Drives findSimilarPastTickets() through its cache path, so no AI call is made.
 * Runs inside a rolled-back transaction.
 */
class SimilarTicketsThresholdTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = Database::connect();
        $this->db->beginTransaction();
        setSetting('ai_enabled', '1');
        setSetting('ai_similar_enabled', '1');
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function ticket(int $by): int
    {
        $this->db->prepare(
            "INSERT INTO tickets (subject, description, created_by, status) VALUES ('similar test', 'x', ?, 'open')"
        )->execute([$by]);
        return (int) $this->db->lastInsertId();
    }

    public function test_matches_below_the_floor_are_hidden_including_cached_ones(): void
    {
        $admin = $this->db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
        if ($admin === false) {
            $this->markTestSkipped('Needs an admin user.');
        }
        $admin = (int) $admin;

        $current = $this->ticket($admin);
        $strong  = $this->ticket($admin);
        $edge    = $this->ticket($admin);
        $weak    = $this->ticket($admin);
        $this->db->prepare(
            "INSERT INTO ai_similarity_classifications (ticket_id, provider, model, candidate_ticket_ids, matches)
             VALUES (?, 'anthropic', 'test', '[]', ?)"
        )->execute([$current, json_encode([
            ['ticket_id' => $strong, 'relevance' => 0.92,  'reasoning' => 'same'],
            ['ticket_id' => $edge,   'relevance' => 0.795, 'reasoning' => 'badge shows 80%'],
            ['ticket_id' => $weak,   'relevance' => 0.4,   'reasoning' => 'topical'],
        ])]);

        $ids = fn() => array_column(findSimilarPastTickets($current, $admin)['matches'], 'ticket_id');

        setSetting('ai_similar_min_relevance', '0');
        $this->assertSame([$strong, $edge, $weak], $ids());

        setSetting('ai_similar_min_relevance', '80');
        $this->assertSame([$strong, $edge], $ids());

        setSetting('ai_similar_min_relevance', '95');
        $this->assertSame([], $ids());
    }
}
