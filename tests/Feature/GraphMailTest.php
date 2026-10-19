<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Byte ranges graphAttach() PUTs to a Graph upload session: inclusive, gapless,
 * each chunk under Graph's 4 MB cap, the last one ending on the final byte.
 */
class GraphMailTest extends TestCase
{
    public function test_upload_ranges_cover_the_file_exactly(): void
    {
        $this->assertSame([[0, 9], [10, 19], [20, 24]], graphUploadRanges(25, 10));
        $this->assertSame([[0, 9], [10, 19]], graphUploadRanges(20, 10), 'exact multiple: no empty tail');
        $this->assertSame([], graphUploadRanges(0, 10));
    }

    public function test_default_chunk_stays_under_the_4mb_cap(): void
    {
        $size   = 9 * 1024 * 1024 + 7;
        $ranges = graphUploadRanges($size);
        $this->assertSame(0, $ranges[0][0]);
        $this->assertSame($size - 1, end($ranges)[1]);
        foreach ($ranges as $i => [$start, $end]) {
            $this->assertLessThan(4 * 1024 * 1024, $end - $start + 1);
            if ($i > 0) {
                $this->assertSame($ranges[$i - 1][1] + 1, $start, 'ranges are contiguous');
            }
        }
    }
}
