<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use SnipeItImport;

/**
 * The pure mapping rules of the Snipe-IT importer (v2.211.0). The end-to-end
 * path is exercised by a dry run against a real backup, which needs a staging
 * schema and the backup files — too heavy for the suite.
 */
class SnipeItImportTest extends TestCase
{
    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/SnipeItImport.php';
    }

    public function test_name_key_ignores_case_and_whitespace(): void
    {
        $this->assertSame('main library', SnipeItImport::key("  Main   Library \n"));
        $this->assertSame(SnipeItImport::key('Harper Branch'), SnipeItImport::key('harper  branch'));
    }

    public function test_tag_settings_continue_the_numeric_sequence_at_its_common_width(): void
    {
        $s = SnipeItImport::tagSettingsFor(['00001', '00891', '00500', '970882174-8', 'ABC', '12']);
        $this->assertSame(['next' => 892, 'zerofill' => 5], $s);
        $this->assertNull(SnipeItImport::tagSettingsFor(['SELFCHECK-1', '']));
    }

    public function test_original_name_strips_snipes_stored_prefix(): void
    {
        $this->assertSame('applecare-030725.pdf', SnipeItImport::originalName('hardware-735-dv1Dnsjb-applecare-030725.pdf'));
        $this->assertSame('receipt.pdf', SnipeItImport::originalName('receipt.pdf'));
    }
}
