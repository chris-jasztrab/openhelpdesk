<?php

/**
 * One-off seed: 10 demo tickets with rich histories (public replies, internal
 * notes, @mentions) for recording training videos.
 *
 * Run:  php database/seed_training_tickets.php
 *
 * Idempotency: every ticket created here is tagged with a marker in its
 * description ("[[TRAINING-DEMO]]") so it can be found/removed later. Re-running
 * will create NEW tickets each time — delete the old ones first if needed
 * (see the cleanup query printed at the end).
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';

loadEnv(ROOT_DIR . '/.env');
$db = Database::connect();

$MARKER = '[[TRAINING-DEMO]]';

/* ── Preflight: the seed hard-codes user and type IDs taken from one DB.
 * Abort cleanly (before inserting anything) if those IDs don't resolve to the
 * expected names on THIS database, so we never silently mis-assign tickets to
 * the wrong person on a DB where the IDs drifted. ─────────────────────────── */
$expectedUsers = [
    1 => 'Chris Jasztrab',   157 => 'Josh deRuiter',     158 => 'Anjana Kipfer',
    161 => 'Susan Letkeman', 163 => 'Meaghan Gibbons',   165 => 'Corrine Denbok',
    166 => 'Jennifer Webb',  168 => 'Emma Stout',         169 => 'Robin Holmes',
    170 => 'Kim Sachs',      171 => 'Janet Seally',       172 => 'Natalie Fisk',
    173 => 'Ryan Snyder',    174 => 'Laura Peacock',      176 => 'Natalie Gallo',
    177 => 'Kelly Kipfer',   180 => 'Chelsea Telfer',     181 => 'Nancy Yee',
    182 => 'Julia Gingrich', 183 => 'Megan Roberts',      185 => 'Alex Geitz',
    186 => 'Teresa Zvonar',  196 => 'Becky Roi',          198 => 'Claire MacFarlane',
    228 => 'Liana Jupan',    231 => 'Matthew Jackow',     283 => 'Alison Schroeder',
    306 => 'Dylan Kellendonk', 312 => 'Allie Landy',
];
$expectedTypes = [
    1 => 'IT', 2 => 'Marketing', 3 => 'Facilities', 10 => 'Lifelong Learning',
    13 => 'Collections/Discover', 14 => 'Circulation', 15 => 'Human Resources',
    18 => 'Programs & Partnerships', 19 => 'Customer Experience',
];

$mismatches = [];
foreach ($expectedUsers as $uid => $name) {
    $got = $db->query("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=" . (int) $uid)->fetchColumn();
    if ($got === false) {
        $mismatches[] = "user id {$uid}: expected \"{$name}\", but no such user on this DB";
    } elseif ($got !== $name) {
        $mismatches[] = "user id {$uid}: expected \"{$name}\", got \"{$got}\"";
    }
}
foreach ($expectedTypes as $tid => $name) {
    $got = $db->query("SELECT name FROM ticket_types WHERE id=" . (int) $tid)->fetchColumn();
    if ($got === false) {
        $mismatches[] = "ticket_type id {$tid}: expected \"{$name}\", but no such type on this DB";
    } elseif ($got !== $name) {
        $mismatches[] = "ticket_type id {$tid}: expected \"{$name}\", got \"{$got}\"";
    }
}
if ($mismatches) {
    fwrite(STDERR, "ABORTED — ID assumptions don't match this database. Nothing was inserted.\n");
    foreach ($mismatches as $m) {
        fwrite(STDERR, "  - {$m}\n");
    }
    fwrite(STDERR, "\nRemap the IDs in this script to match this DB, then re-run.\n");
    exit(1);
}

/**
 * Insert a comment (reply or internal note) onto the timeline at a backdated
 * time, then fan out @mention notifications exactly like the live route does.
 */
function addComment(PDO $db, int $ticketId, int $userId, string $html, bool $internal, DateTimeImmutable $at): int
{
    $stmt = $db->prepare(
        "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at)
         VALUES (?, ?, 'comment', ?, ?, ?)"
    );
    $stmt->execute([$ticketId, $userId, $html, $internal ? 1 : 0, $at->format('Y-m-d H:i:s')]);
    $timelineId = (int) $db->lastInsertId();

    // Same mention handling as the agent reply/note route.
    processAtMentions($db, $html, $ticketId, $timelineId, $userId);

    return $timelineId;
}

function addEvent(PDO $db, int $ticketId, ?int $userId, string $action, string $details, bool $internal, DateTimeImmutable $at): void
{
    $stmt = $db->prepare(
        "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$ticketId, $userId, $action, $details, $internal ? 1 : 0, $at->format('Y-m-d H:i:s')]);
}

/* ── The 10 tickets ──────────────────────────────────────────────────── */
// Each event: ['+90 min', userId, 'reply'|'note', html]
// status changes are listed separately and applied at the end.

$tickets = [
    [
        'subject'   => 'Laptop won\'t connect to staff WiFi after password change',
        'desc'      => '<p>Since the network password reset yesterday my work laptop refuses to join "WPL-Staff". It keeps saying "incorrect password" even though I\'m using the new one from the email. I can still get on the guest network. Can someone help? I have a presentation at 2pm.</p>',
        'type_id'   => 1, 'group_id' => 2, 'priority_id' => 7,
        'requester' => 166, 'assigned' => 157,
        'start'     => '2026-05-29 09:12:00',
        'final'     => 'resolved',
        'events'    => [
            ['+35 min', 157, 'reply', '<p>Hi Jennifer, thanks for flagging this. The staff WiFi profile on your laptop is caching the old credentials. Can you tell me which building you\'re in right now and whether this is your laptop or a shared loaner?</p>'],
            ['+50 min', 157, 'note', '<p>@Chris Jasztrab the RADIUS logs show 3 failed auths from her MAC this morning — looks like the saved profile didn\'t pick up the new shared secret after the rotation. Did the GPO push go out to all the older Latitude models?</p>'],
            ['+75 min', 1, 'note', '<p>@Josh deRuiter the GPO only hit machines that checked in after 6am. Hers was off overnight. Easiest fix is to have her forget the WPL-Staff network and rejoin — that\'ll pull the new profile. I\'ll force a gpupdate on the fleet tonight so nobody else hits this.</p>'],
            ['+95 min', 166, 'reply', '<p>I\'m at Central, it\'s my assigned laptop (the Dell). Happy to try forgetting the network.</p>'],
            ['+110 min', 157, 'reply', '<p>Perfect. Go to WiFi settings &rarr; right-click "WPL-Staff" &rarr; Forget, then reconnect and enter the new password. That should do it. Let me know if it sticks!</p>'],
            ['+165 min', 166, 'reply', '<p>That worked instantly. Connected and on the shared drives again. Thank you so much!</p>'],
            ['+175 min', 157, 'reply', '<p>Great, glad that did it. I\'ll mark this resolved — reopen if it drops again.</p>'],
        ],
        'status_at' => '+178 min',
    ],
    [
        'subject'   => 'Leaking ceiling tile in second-floor reading area',
        'desc'      => '<p>There\'s water dripping from a ceiling tile above the comfortable chairs in the second-floor reading area at Central. The tile is sagging and discoloured. We\'ve put a bucket under it and roped off the area for safety. Please send someone soon.</p>',
        'type_id'   => 3, 'group_id' => 5, 'priority_id' => 7,
        'requester' => 171, 'assigned' => 169,
        'start'     => '2026-05-30 08:05:00',
        'final'     => 'in_progress',
        'events'    => [
            ['+20 min', 169, 'reply', '<p>Thanks Janet — good call roping it off. I\'m heading up to take a look now. Please keep the area closed to the public until I\'ve assessed it.</p>'],
            ['+60 min', 169, 'note', '<p>@Kim Sachs went up to look. It\'s coming from the HVAC condensate line above the ceiling grid, not the roof. I can mop up but we\'ll need the mechanical contractor to re-pitch the drain line. Do we still have the PO open with Conestoga Mechanical, or do I need to raise a new one?</p>'],
            ['+95 min', 170, 'note', '<p>@Robin Holmes the Conestoga PO is still open until end of fiscal. I\'ll call them this morning and get someone out. In the meantime can you swap the stained tile and set up a fan so the area dries? I don\'t want mould in the grid.</p>'],
            ['+130 min', 169, 'reply', '<p>Update: the leak is from an HVAC drain line, not the roof. Our mechanical contractor has been called and we\'re drying the area out now. I\'ll keep the reading nook closed until the tile is replaced — hoping to have it reopened by tomorrow. Thanks for your patience.</p>'],
        ],
        'status_at' => '+62 min',
    ],
    [
        'subject'   => 'Need event poster for Summer Reading Club kickoff',
        'desc'      => '<p>Hi Marketing! We\'re launching the Summer Reading Club on June 21st and need a poster for the branches plus a social media version. Theme this year is "Read Around the World". Could you put something together? We\'d love to have it printed by June 14th.</p>',
        'type_id'   => 2, 'group_id' => 1, 'priority_id' => 6,
        'requester' => 168, 'assigned' => 196,
        'start'     => '2026-05-31 10:30:00',
        'final'     => 'waiting_on_customer',
        'events'    => [
            ['+45 min', 196, 'reply', '<p>Hi Emma! Love the theme. To get started I\'ll need a few details: the age range you\'re targeting, any sponsor logos that need to go on it, and the exact print sizes for the branches (are we doing 11x17 again?). Once I have those I\'ll get a first draft over to you.</p>'],
            ['+70 min', 196, 'note', '<p>@Julia Gingrich can you take the design lead on this one? I\'m slammed with the annual report. "Read Around the World" — thinking passport/stamps motif, bright and kid-friendly. I\'ll handle the client comms, just need you on the artwork.</p>'],
            ['+100 min', 182, 'note', '<p>@Becky Roi yes! I actually have a passport-stamp illustration set we can reuse from the 2024 campaign. I\'ll mock up two directions — one playful, one map-based — and drop them in the shared drive by Thursday. Do we have a final tagline or just the theme?</p>'],
            ['+1 day', 196, 'reply', '<p>Thanks for the theme details! Just need a couple more things from you before we finalize: can you confirm the age range and whether the Friends of the Library logo needs to appear on the print version? I\'ve set this to "Waiting on Customer" for now.</p>'],
        ],
        'status_at' => '+1 day +5 min',
    ],
    [
        'subject'   => 'Question about vacation carryover policy',
        'desc'      => '<p>Hi HR, I have about 4 unused vacation days from last year and I\'m not sure if they carry over or if I lose them at the end of June. Could you clarify the carryover policy for part-time staff? Thanks.</p>',
        'type_id'   => 15, 'group_id' => 8, 'priority_id' => 5,
        'requester' => 173, 'assigned' => 161,
        'start'     => '2026-06-02 13:15:00',
        'final'     => 'resolved',
        'events'    => [
            ['+90 min', 161, 'reply', '<p>Hi Ryan, happy to help. Let me confirm the current policy for part-time staff and get back to you shortly.</p>'],
            ['+120 min', 161, 'note', '<p>@Matthew Jackow quick check before I reply — for part-time staff, is the carryover still capped at 5 days and does it have to be used by the end of the fiscal (June 30) or the calendar year? Want to make sure I give Ryan the right deadline.</p>'],
            ['+1 day', 231, 'note', '<p>@Susan Letkeman correct — up to 5 days carry over for part-time, and they must be used by June 30 or they\'re forfeited. There\'s no payout for unused carryover days under the current agreement.</p>'],
            ['+1 day +30 min', 161, 'reply', '<p>Good news, Ryan — part-time staff can carry over up to 5 vacation days, so your 4 days are safe. Just note they need to be used by June 30th or they\'ll be forfeited (there\'s no payout for unused days). I\'d recommend booking them soon. Let me know if you\'d like help scheduling them with your supervisor!</p>'],
        ],
        'status_at' => '+1 day +35 min',
    ],
    [
        'subject'   => 'Self-checkout machine #3 at McCormick rejecting library cards',
        'desc'      => '<p>The third self-checkout station at McCormick branch is rejecting every library card we scan — both barcode and the new RFID cards. Patrons are getting frustrated and lining up at the desk. Stations 1 and 2 are fine. Can we get this looked at today?</p>',
        'type_id'   => 14, 'group_id' => 3, 'priority_id' => 7,
        'requester' => 172, 'assigned' => 180,
        'start'     => '2026-06-03 09:40:00',
        'final'     => 'in_progress',
        'events'    => [
            ['+25 min', 180, 'reply', '<p>Hi Natalie, sorry about the lineup! Quick question — when a card is scanned, does the screen show an error message, or does it just do nothing? And is it only patron cards failing, or do staff cards fail too?</p>'],
            ['+55 min', 172, 'reply', '<p>It flashes "Card not recognized — please see staff". Staff cards fail the same way. The barcode scanner light still comes on though.</p>'],
            ['+80 min', 180, 'note', '<p>@Josh deRuiter can IT take a look at McCormick SCO #3? Scanner light works but every card reads "not recognized", staff cards included. Feels like the unit lost its connection to the patron database rather than a scanner fault. Stations 1 &amp; 2 are fine on the same network.</p>'],
            ['+115 min', 157, 'note', '<p>@Chelsea Telfer good diagnosis — I remoted in and SCO #3\'s SIP2 service to the ILS had died, so it couldn\'t validate any card. I\'ve restarted the service and it\'s authenticating test cards now. Can you have staff run a real patron card through it to confirm before we reopen it?</p>'],
            ['+140 min', 180, 'reply', '<p>Update: IT found the station had lost its connection to the catalogue system and have restarted the service. We\'re testing it at the desk now — I\'ll confirm once a few live cards go through cleanly, then put it back in service.</p>'],
        ],
        'status_at' => '+82 min',
    ],
    [
        'subject'   => 'Vega Discover not showing new DVD releases in catalogue',
        'desc'      => '<p>I\'ve added about 30 new DVD titles to the collection this week but none of them are appearing when patrons search Vega Discover. They show up fine in the staff client. Is there an indexing delay, or is something broken with the DVD format mapping?</p>',
        'type_id'   => 13, 'group_id' => 7, 'priority_id' => 6,
        'requester' => 177, 'assigned' => 165,
        'start'     => '2026-06-04 11:00:00',
        'final'     => 'pending',
        'events'    => [
            ['+60 min', 165, 'reply', '<p>Hi Kelly, thanks for the detail — the fact they\'re in the staff client but not Vega points to the harvest/index rather than the records themselves. Let me dig into the format mapping for DVDs.</p>'],
            ['+95 min', 165, 'note', '<p>@Liana Jupan have you seen this before? New DVD records aren\'t surfacing in Vega Discover but they\'re fine in Sierra. I\'m wondering if the recent FRBR grouping change dropped the "DVD" material type from the harvest profile. Where do I check the format-to-facet mapping on the Vega side?</p>'],
            ['+1 day', 228, 'note', '<p>@Corrine Denbok yes — this happened after the May config push. The harvest is fine but the new "Moving Image" facet rule isn\'t catching records where the 007 is coded for Blu-ray vs standard DVD. I\'ve opened a ticket with the vendor; for now a manual re-harvest will pull them in within a few hours. I\'ll re-run it this afternoon.</p>'],
            ['+1 day +90 min', 165, 'reply', '<p>Thanks for your patience, Kelly. This turned out to be a catalogue indexing rule that wasn\'t picking up the newer DVD format coding after a recent system update. A colleague is re-running the harvest this afternoon, which should bring all 30 titles into Vega within a few hours, and we\'ve logged it with the vendor for a permanent fix. I\'ll set this to Pending while we wait for the re-index — I\'ll follow up to confirm once they appear.</p>'],
        ],
        'status_at' => '+1 day +95 min',
    ],
    [
        'subject'   => 'Request: tablets for digital literacy workshop series',
        'desc'      => '<p>I\'m planning a fall digital literacy workshop series for older adults and would love to borrow 8&ndash;10 tablets for the sessions. Do we have a tablet kit available to book, and would there be any budget to add a couple more if the current ones are spoken for? Sessions would run Tuesdays in September and October.</p>',
        'type_id'   => 10, 'group_id' => 6, 'priority_id' => 6,
        'requester' => 181, 'assigned' => 163,
        'start'     => '2026-06-05 14:20:00',
        'final'     => 'open',
        'events'    => [
            ['+40 min', 163, 'reply', '<p>Hi Nancy, this sounds like a wonderful series! We do have a tablet lending kit — let me check its availability for Tuesdays in September/October and whether all 10 are functional, then I\'ll get back to you on numbers and any budget options.</p>'],
            ['+70 min', 163, 'note', '<p>@Alison Schroeder do you know the current state of the tablet kit? I think a couple were pulled out of rotation with cracked screens. Nancy needs 8&ndash;10 for a fall workshop series and I want to give her a realistic number before she builds her schedule.</p>'],
            ['+1 day', 283, 'note', '<p>@Meaghan Gibbons the kit has 12 tablets but 3 are out for repair, so 9 are usable right now. The repairs should be back by mid-August. If she needs a firm 10 we have a little left in the programming tech line to buy one more — let\'s talk numbers before committing.</p>'],
        ],
        'status_at' => null,
    ],
    [
        'subject'   => 'Partnership inquiry: school board wants class visits',
        'desc'      => '<p>Forwarding an inquiry from the Waterloo Region District School Board — they\'d like to set up recurring class visits to the library for grades 3&ndash;5 this fall, roughly two classes per week. They\'re asking about programming, capacity, and whether we can offer library card sign-ups during the visits. Who should take the lead on this?</p>',
        'type_id'   => 18, 'group_id' => 9, 'priority_id' => 6,
        'requester' => 183, 'assigned' => 198,
        'start'     => '2026-06-06 10:10:00',
        'final'     => 'in_progress',
        'events'    => [
            ['+50 min', 198, 'reply', '<p>Hi Megan, thanks for forwarding this — fantastic opportunity. I\'ll take the lead on coordinating with the school board. Before I reach out, do you know if they have specific dates/times in mind, and roughly how many students per class?</p>'],
            ['+85 min', 198, 'note', '<p>@Allie Landy two classes per week of grades 3&ndash;5 plus card sign-ups is going to lean heavily on programming staff and the desk. Can your team handle the card registrations during the visits, or should we set up a dedicated sign-up table so it doesn\'t back up circulation? Want to scope staffing before I commit to a cadence.</p>'],
            ['+1 day', 312, 'note', '<p>@Claire MacFarlane a dedicated sign-up table is the way to go — if we route 50+ kids through the regular desk it\'ll be chaos. I can have a programming staffer run registrations alongside the class visit. Let\'s cap it at two classes/week to start and reassess after October. Happy to join the call with the board.</p>'],
            ['+1 day +40 min', 198, 'reply', '<p>Good news, Megan — we\'d love to make this happen. We\'re proposing two classes per week to start, with a dedicated library-card sign-up table staffed by our programming team so it doesn\'t slow down the regular desk. I\'ll reach out to the board this week to lock in dates. Could you let them know we\'ll be in touch directly?</p>'],
        ],
        'status_at' => '+52 min',
    ],
    [
        'subject'   => 'Patron complaint about noise in quiet study zone',
        'desc'      => '<p>We\'ve had several complaints this week about noise in the designated quiet study zone on the third floor — mostly group conversations and phone calls. Regular quiet-study patrons are frustrated. Can Customer Experience help us figure out a better approach? The "Quiet Zone" signs don\'t seem to be enough.</p>',
        'type_id'   => 19, 'group_id' => 10, 'priority_id' => 6,
        'requester' => 186, 'assigned' => 174,
        'start'     => '2026-06-08 09:30:00',
        'final'     => 'resolved',
        'events'    => [
            ['+40 min', 174, 'reply', '<p>Hi Teresa, thanks for raising this — quiet space is something we hear about a lot and it\'s worth getting right. Could you tell me what times of day the noise is worst? That\'ll help us decide whether it\'s a signage issue, a layout issue, or a staffing/presence issue.</p>'],
            ['+75 min', 174, 'note', '<p>@Natalie Gallo this comes up at every branch. Before I reply to Teresa — do you still have that "tiered zones" floor plan you piloted at McCormick (silent / quiet / collaborative)? I think clearer zoning plus better-placed signage worked better there than just policing it. Worth proposing the same approach for Central\'s third floor.</p>'],
            ['+2 hours', 176, 'note', '<p>@Laura Peacock yes, I\'ll send you the McCormick zoning map. The big win was moving the collaborative tables away from the quiet end so noise doesn\'t travel, plus floor decals (not just wall signs) marking the silent zone. Staff doing a quick walk-through once an hour also helped a lot more than relying on signs alone.</p>'],
            ['+3 hours', 174, 'reply', '<p>Thanks Teresa — here\'s what we\'re recommending. Rather than just adding more signs, we\'d like to re-zone the third floor: move the collaborative tables to the far end so conversation doesn\'t carry into the quiet area, add floor decals marking the silent zone, and have staff do a brief hourly walk-through. This combination worked well at McCormick. I\'ll coordinate the table move and order the decals. I\'ll resolve this ticket for now, but please reopen it if the noise continues after the changes go in.</p>'],
        ],
        'status_at' => '+3 hours +10 min',
    ],
    [
        'subject'   => 'Third-floor printer printing blank pages',
        'desc'      => '<p>The public printer on the third floor is spitting out completely blank pages for everyone today. The print job goes through and it pulls the paper, but nothing\'s on it. We swapped in a fresh paper ream and it didn\'t help. Patrons have already paid for prints that came out blank.</p>',
        'type_id'   => 1, 'group_id' => 2, 'priority_id' => 6,
        'requester' => 185, 'assigned' => 306,
        'start'     => '2026-06-09 10:00:00',
        'final'     => 'closed',
        'events'    => [
            ['+30 min', 306, 'reply', '<p>Hi Alex, sorry about the wasted prints — we\'ll sort out refunds for those. Quick question: is it blank for both black-and-white and colour jobs, and does the printer\'s own test/config page (printed from the panel) also come out blank?</p>'],
            ['+55 min', 306, 'note', '<p>@Anjana Kipfer this is the HP on 3 again. Blank pages on every job, fresh paper already tried. Classic sign the toner/imaging drum is shot or a sealing tape got left on a new cartridge. Do we have a spare black toner for that model in the storage cabinet, or do I need to order one?</p>'],
            ['+90 min', 158, 'note', '<p>@Dylan Kellendonk there\'s one spare black toner for that HP on the second shelf of the IT cabinet. Grab it — and if a cartridge was swapped recently, check the orange pull-tab wasn\'t left in. I\'ll order a replacement spare so we don\'t run out.</p>'],
            ['+2 hours', 306, 'reply', '<p>Found it — a recently installed toner cartridge still had its protective seal strip in place, so nothing was transferring to the page. Removed the strip and ran a test print; it\'s back to normal. We\'ll refund any prints that came out blank today — just send the desk the count. Closing this one out, but reopen if it acts up again.</p>'],
        ],
        'status_at' => '+2 hours +10 min',
    ],
];

/* ── Insert loop ─────────────────────────────────────────────────────── */

$created = [];

foreach ($tickets as $t) {
    $start = new DateTimeImmutable($t['start']);

    // Insert the ticket itself (starts as 'open', first_responded_at set later).
    $ins = $db->prepare(
        "INSERT INTO tickets
            (subject, description, created_by, submitted_by, created_at, type_id, group_id, status, priority_id, assigned_to, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?)"
    );
    $desc = $t['desc'] . "\n<!-- {$GLOBALS['MARKER']} -->";
    $ins->execute([
        $t['subject'], $desc, $t['requester'], $t['requester'],
        $start->format('Y-m-d H:i:s'), $t['type_id'], $t['group_id'],
        $t['priority_id'], $t['assigned'], $start->format('Y-m-d H:i:s'),
    ]);
    $ticketId = (int) $db->lastInsertId();

    // "created" + "assigned" timeline rows.
    $reqName = $db->query("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=" . (int) $t['requester'])->fetchColumn();
    $agName  = $db->query("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=" . (int) $t['assigned'])->fetchColumn();
    addEvent($db, $ticketId, $t['requester'], 'created', "Ticket created by {$reqName}.", false, $start);
    addEvent($db, $ticketId, $t['assigned'], 'assigned', "Assigned to {$agName}", false, $start->modify('+2 min'));

    // Comment thread.
    $firstReplyAt = null;
    foreach ($t['events'] as $ev) {
        [$offset, $uid, $kind, $html] = $ev;
        $at = $start->modify($offset);
        $internal = ($kind === 'note');
        addComment($db, $ticketId, (int) $uid, $html, $internal, $at);
        if (!$internal && $firstReplyAt === null) {
            $firstReplyAt = $at;
        }
    }
    if ($firstReplyAt !== null) {
        $db->prepare("UPDATE tickets SET first_responded_at=? WHERE id=?")
           ->execute([$firstReplyAt->format('Y-m-d H:i:s'), $ticketId]);
    }

    // Final status change (if not staying 'open').
    if ($t['final'] !== 'open' && $t['status_at'] !== null) {
        $statusAt = $start->modify($t['status_at']);
        addEvent($db, $ticketId, $t['assigned'], 'status_changed', "Status changed from open to {$t['final']}", false, $statusAt);
        $db->prepare("UPDATE tickets SET status=?, updated_at=? WHERE id=?")
           ->execute([$t['final'], $statusAt->format('Y-m-d H:i:s'), $ticketId]);
    }

    $created[] = "#{$ticketId} — {$t['subject']} ({$t['final']})";
}

echo "Created " . count($created) . " training tickets:\n";
foreach ($created as $c) {
    echo "  {$c}\n";
}
echo "\nCleanup (removes ONLY these demo tickets and their timeline/notifications):\n";
echo "  DELETE n FROM notifications n JOIN tickets t ON t.id=n.ticket_id WHERE t.description LIKE '%{$MARKER}%';\n";
echo "  DELETE tl FROM ticket_timeline tl JOIN tickets t ON t.id=tl.ticket_id WHERE t.description LIKE '%{$MARKER}%';\n";
echo "  DELETE FROM tickets WHERE description LIKE '%{$MARKER}%';\n";
