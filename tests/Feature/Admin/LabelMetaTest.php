<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Keeps config/labels.meta.php honest against the real label() callsites.
 *
 * The Labels admin page tells admins which labels are wired up and which are
 * inert, and it decides that purely from whether the key has a `where` note in
 * the meta file. That's only trustworthy if the note and the callsite can't
 * drift apart — hence this test. It scans the app for label() calls and fails
 * if the two disagree in either direction:
 *
 *   - a key that IS called but has no `where` note would be shown to admins
 *     as "renaming this does nothing", which is wrong and actively unhelpful;
 *   - a key with a `where` note that is NOT called anymore promises an effect
 *     the app no longer delivers.
 *
 * Static analysis only: no DB, no HTTP.
 */
class LabelMetaTest extends TestCase
{
    /** Directories that are not application code. */
    private const SKIP = ['/vendor/', '/tests/', '/node_modules/', '/storage/'];

    /**
     * @return array<string, list<string>> label key => files that call it
     */
    private function calledKeys(): array
    {
        $called = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(ROOT_DIR, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            foreach (self::SKIP as $skip) {
                if (strpos($path, $skip) !== false) {
                    continue 2;
                }
            }
            $src = file_get_contents($path);
            // Only literal keys — assert there are no dynamic ones separately.
            if (preg_match_all('/label\(\s*[\'"]([a-z0-9_.]+)[\'"]/i', $src, $m)) {
                foreach ($m[1] as $key) {
                    $called[$key][] = str_replace(ROOT_DIR . '/', '', $path);
                }
            }
        }

        return $called;
    }

    private function defaults(): array
    {
        $defaults = json_decode(file_get_contents(ROOT_DIR . '/config/labels.default.json'), true);
        $this->assertIsArray($defaults, 'labels.default.json must be valid JSON');
        unset($defaults['_readme']);

        return $defaults;
    }

    private function meta(): array
    {
        return require ROOT_DIR . '/config/labels.meta.php';
    }

    public function test_every_called_label_key_has_a_where_note(): void
    {
        $meta    = $this->meta();
        $missing = array_diff(array_keys($this->calledKeys()), array_keys($meta['keys']));

        $this->assertSame(
            [],
            array_values($missing),
            "These keys are read by label() but have no `where` note in config/labels.meta.php, so the "
            . "Labels page will wrongly show them under \"Not wired up yet\". Add a note for each."
        );
    }

    public function test_every_documented_key_is_actually_called(): void
    {
        $meta   = $this->meta();
        $called = $this->calledKeys();
        $stale  = array_diff(array_keys($meta['keys']), array_keys($called));

        $this->assertSame(
            [],
            array_values($stale),
            "These keys have a `where` note but nothing calls label() for them anymore. Either restore the "
            . "callsite or move the key out of `keys` in config/labels.meta.php."
        );
    }

    public function test_every_documented_key_exists_in_the_default_file(): void
    {
        $meta    = $this->meta();
        $missing = array_diff(array_keys($meta['keys']), array_keys($this->defaults()));

        $this->assertSame(
            [],
            array_values($missing),
            'Documented keys must exist in labels.default.json, or the Labels page and the download/upload '
            . 'flow cannot reach them.'
        );
    }

    public function test_retired_keys_are_gone_from_the_default_file(): void
    {
        $meta    = $this->meta();
        $overlap = array_intersect(array_keys($meta['retired'] ?? []), array_keys($this->defaults()));

        $this->assertSame(
            [],
            array_values($overlap),
            'A key cannot be both retired and live. Remove it from labels.default.json or from `retired`.'
        );
    }

    public function test_no_dynamic_label_keys_exist(): void
    {
        // The wired/inert split is computed by scanning for literal keys, so a
        // concatenated or interpolated key would silently escape the analysis
        // and get mislabelled as inert.
        $offenders = [];
        $iterator  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(ROOT_DIR, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            foreach (self::SKIP as $skip) {
                if (strpos($path, $skip) !== false) {
                    continue 2;
                }
            }
            $src = file_get_contents($path);
            // label($var), label('a.' . $b), label("a.$b")
            if (preg_match('/label\(\s*(\$|[\'"][^\'"]*[\'"]\s*\.|"[^"]*\$)/', $src)) {
                $offenders[] = str_replace(ROOT_DIR . '/', '', $path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'label() is being called with a dynamic key in these files. Use literal keys, or teach '
            . 'LabelMetaTest and the Labels page how to resolve them.'
        );
    }

    public function test_group_ids_referenced_by_keys_are_defined(): void
    {
        $meta = $this->meta();
        foreach ($meta['keys'] as $key => $entry) {
            $this->assertArrayHasKey(
                $entry['group'],
                $meta['groups'],
                "key «{$key}» references undefined group «{$entry['group']}»"
            );
            $this->assertNotSame('', trim((string) ($entry['where'] ?? '')), "key «{$key}» has an empty `where`");
        }
    }
}
