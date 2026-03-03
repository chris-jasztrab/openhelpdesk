<?php
/**
 * Test suite for parseEmailCommands() in src/helpers.php
 *
 * Run from project root:
 *   php scripts/test-email-commands.php
 */
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/src/helpers.php';

// ─── Minimal stub for getSetting() so helpers.php loads without a DB ──────────
// (parseEmailCommands has no DB dependency; this avoids loading Database.php)

$pass = 0;
$fail = 0;

function assert_eq(string $testName, mixed $expected, mixed $actual): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        echo "  PASS  {$testName}\n";
        $pass++;
    } else {
        echo "  FAIL  {$testName}\n";
        echo "        Expected: " . var_export($expected, true) . "\n";
        echo "        Actual:   " . var_export($actual, true) . "\n";
        $fail++;
    }
}

echo "\n=== parseEmailCommands() Tests ===\n\n";

// ─── 1. Command at end — status only ──────────────────────────────────────────
echo "1. Status command at end of body\n";
$r = parseEmailCommands("Please close this ticket.\n\n#close");
assert_eq('body trimmed',    'Please close this ticket.', $r['body']);
assert_eq('status=closed',   'closed',                    $r['status']);
assert_eq('priority=null',   null,                        $r['priority_slug']);

// ─── 2. Priority command at end ───────────────────────────────────────────────
echo "\n2. Priority command at end of body\n";
$r = parseEmailCommands("Bumping this up.\n#high");
assert_eq('body trimmed', 'Bumping this up.', $r['body']);
assert_eq('status=null',  null,              $r['status']);
assert_eq('priority=high', 'high',           $r['priority_slug']);

// ─── 3. Multiple commands on one line ─────────────────────────────────────────
echo "\n3. Multiple commands on one line\n";
$r = parseEmailCommands("Thanks, all done.\n#resolve #critical");
assert_eq('body trimmed',      'Thanks, all done.', $r['body']);
assert_eq('status=resolved',   'resolved',          $r['status']);
assert_eq('priority=critical', 'critical',          $r['priority_slug']);

// ─── 4. Commands on separate lines ────────────────────────────────────────────
echo "\n4. Commands on separate lines\n";
$r = parseEmailCommands("Working on it.\n#pending\n#medium");
assert_eq('body trimmed',    'Working on it.', $r['body']);
assert_eq('status=pending',  'pending',        $r['status']);
assert_eq('priority=medium', 'medium',         $r['priority_slug']);

// ─── 5. Blank lines between body and commands ─────────────────────────────────
echo "\n5. Blank lines between body and commands\n";
$r = parseEmailCommands("Got it, I'll look into this.\n\n\n#in_progress");
assert_eq('body trimmed',       "Got it, I'll look into this.", $r['body']);
assert_eq('status=in_progress', 'in_progress',                 $r['status']);

// ─── 6. Hashtag mid-sentence is ignored ───────────────────────────────────────
echo "\n6. Hashtag mid-sentence is NOT treated as command\n";
$r = parseEmailCommands("Please check ticket #123 for details.\nThanks.");
assert_eq('body unchanged', "Please check ticket #123 for details.\nThanks.", $r['body']);
assert_eq('status=null',    null,                                              $r['status']);
assert_eq('priority=null',  null,                                              $r['priority_slug']);

// ─── 7. Hashtag mid-body on its own line but NOT at end ───────────────────────
echo "\n7. Command-looking line in middle of body is ignored\n";
$r = parseEmailCommands("#close\nActually, keep it open.\nLet me know.");
assert_eq('body unchanged', "#close\nActually, keep it open.\nLet me know.", $r['body']);
assert_eq('status=null',    null, $r['status']);

// ─── 8. Commands-only email (no body text) ────────────────────────────────────
echo "\n8. Commands-only email produces empty body\n";
$r = parseEmailCommands("#close #high");
assert_eq('body empty',     '',       $r['body']);
assert_eq('status=closed',  'closed', $r['status']);
assert_eq('priority=high',  'high',   $r['priority_slug']);

// ─── 9. Unknown hashtags are silently ignored ─────────────────────────────────
echo "\n9. Unknown hashtags in command line are ignored\n";
$r = parseEmailCommands("Fixed!\n#done #close #unknowntag");
assert_eq('body trimmed',  'Fixed!',  $r['body']);
assert_eq('status=closed', 'closed',  $r['status']);
assert_eq('priority=null', null,      $r['priority_slug']);

// ─── 10. All status aliases ───────────────────────────────────────────────────
echo "\n10. Status aliases\n";
$aliases = [
    'close'                  => 'closed',
    'closed'                 => 'closed',
    'resolve'                => 'resolved',
    'resolved'               => 'resolved',
    'open'                   => 'open',
    'pending'                => 'pending',
    'in_progress'            => 'in_progress',
    'inprogress'             => 'in_progress',
    'waiting_on_customer'    => 'waiting_on_customer',
    'waitingoncustomer'      => 'waiting_on_customer',
    'waiting_on_third_party' => 'waiting_on_third_party',
    'waitingonthirdparty'    => 'waiting_on_third_party',
];
foreach ($aliases as $hashtag => $expected) {
    $r = parseEmailCommands("Body text.\n#{$hashtag}");
    assert_eq("#{$hashtag} → {$expected}", $expected, $r['status']);
}

// ─── 11. All priority aliases ─────────────────────────────────────────────────
echo "\n11. Priority aliases\n";
foreach (['low', 'medium', 'high', 'critical'] as $p) {
    $r = parseEmailCommands("Body text.\n#{$p}");
    assert_eq("#{$p} → {$p}", $p, $r['priority_slug']);
}

// ─── 12. Case insensitivity ───────────────────────────────────────────────────
echo "\n12. Case insensitivity\n";
$r = parseEmailCommands("Done.\n#CLOSE #HIGH");
assert_eq('status=closed (uppercase)', 'closed', $r['status']);
assert_eq('priority=high (uppercase)', 'high',   $r['priority_slug']);

// ─── 13. Body with only blank lines + commands ────────────────────────────────
echo "\n13. Blank-line-only body with commands\n";
$r = parseEmailCommands("\n\n\n#resolve");
assert_eq('body empty',      '', $r['body']);
assert_eq('status=resolved', 'resolved', $r['status']);

// ─── 14. Mixed sentence with # at start of word (not whole line) ──────────────
echo "\n14. #word mid-sentence not on its own line\n";
$input = "Use the #tag system to label issues.\n\nSee you later.";
$r = parseEmailCommands($input);
assert_eq('body unchanged', $input, $r['body']);
assert_eq('status=null',    null,   $r['status']);

// ─── Summary ─────────────────────────────────────────────────────────────────
echo "\n=== Results: {$pass} passed, {$fail} failed ===\n\n";
exit($fail > 0 ? 1 : 0);
