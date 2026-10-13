<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * aiModelPriceUsd() feeds the CAD cost on the AI Token Usage report. A wrong
 * match here silently misprices every call, so pin the lookup rules.
 */
class AiModelPriceTest extends TestCase
{
    public function test_alias_and_dated_snapshot_resolve_to_same_price(): void
    {
        $this->assertSame([1.00, 5.00], aiModelPriceUsd('claude-haiku-4-5'));
        $this->assertSame([1.00, 5.00], aiModelPriceUsd('claude-haiku-4-5-20251001'));
    }

    public function test_opus_4_snapshot_is_not_confused_with_newer_opus_4_x(): void
    {
        // claude-opus-4-20250514 is Opus 4.0 ($15/$75); Opus 4.7 is $5/$25.
        $this->assertSame([15.00, 75.00], aiModelPriceUsd('claude-opus-4-20250514'));
        $this->assertSame([5.00, 25.00], aiModelPriceUsd('claude-opus-4-7'));
    }

    public function test_unknown_model_has_no_price_rather_than_a_guess(): void
    {
        $this->assertNull(aiModelPriceUsd('gpt-4o-mini'));
        $this->assertNull(aiModelPriceUsd('claude-opus-4-9'));
    }
}
