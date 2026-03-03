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

// ─── 7. Command deeper than threshold (9 non-blank lines from end) — ignored ──
// The signature-tolerance threshold is 8. If a command is > 8 non-blank lines
// from the end, it is not treated as a command (truly "in the middle").
echo "\n7. Command > 8 non-blank lines from end is ignored (truly mid-body)\n";
$deepBody = "#close\nL1\nL2\nL3\nL4\nL5\nL6\nL7\nL8\nL9";
$r = parseEmailCommands($deepBody);
assert_eq('body unchanged (deep)', $deepBody, $r['body']);
assert_eq('status=null (deep)',    null,       $r['status']);

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

// ─── 15. Command followed by typical email signature ─────────────────────────
echo "\n15. Command followed by email signature (Outlook style)\n";
$r = parseEmailCommands(
    "This issue is resolved.\n\n#close\n\nThanks,\nJohn Smith\nIT Support\nWaterloo Public Library"
);
assert_eq('status=closed with sig',  'closed',                    $r['status']);
// The blank before AND after #close both remain; the signature is preserved in body
assert_eq('body has message+sig',    "This issue is resolved.\n\n\nThanks,\nJohn Smith\nIT Support\nWaterloo Public Library", $r['body']);

// ─── 16. Command followed by longer corporate signature (6 lines) ─────────────
echo "\n16. Command followed by 6-line corporate signature\n";
$sig = "Kind regards,\nJohn Smith\nSenior IT Technician\nWaterloo Public Library\n(519) 886-1310\njsmith@wpl.ca";
$r   = parseEmailCommands("Please close.\n\n#resolve #high\n\n{$sig}");
assert_eq('status=resolved with long sig', 'resolved', $r['status']);
assert_eq('priority=high with long sig',   'high',     $r['priority_slug']);

// ─── 17. Signature longer than threshold — command NOT beyond max sig lines ───
echo "\n17. Signature longer than max threshold (9 non-blank lines) — command not found\n";
$longSig = implode("\n", ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8', 'L9']);
$r = parseEmailCommands("Body text.\n\n#close\n\n{$longSig}");
// With 9 non-blank non-command lines, parser gives up (threshold=8)
assert_eq('no command past threshold', null, $r['status']);

// ─── 18. Multiple commands across two lines before signature ──────────────────
echo "\n18. Two command lines before signature\n";
$r = parseEmailCommands("Done.\n#resolve\n#critical\n\nRegards,\nJohn");
assert_eq('status=resolved multi-line', 'resolved', $r['status']);
assert_eq('priority=critical multi-line', 'critical', $r['priority_slug']);

// ─── Summary ─────────────────────────────────────────────────────────────────
echo "\n=== Results: {$pass} passed, {$fail} failed ===\n\n";
exit($fail > 0 ? 1 : 0);
