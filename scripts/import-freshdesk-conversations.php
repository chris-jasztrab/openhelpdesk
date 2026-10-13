<?php

/**
 * OpenHelpDesk — Freshdesk ticket, conversation + attachment importer
 *
 * Freshdesk's CSV export leaves out archived tickets and carries only a
 * ticket's top-level fields. This walks every Freshdesk ticket id over the v2
 * API instead:
 *
 *   - a ticket not yet here (no tickets.legacy_id = its id) is created, mapped
 *     the way the CSV importer maps a row, SLA-exempt with no clock started;
 *   - every reply and note becomes a timeline comment (private notes stay
 *     internal), the ticket's own attachments hang off the ticket, each reply's
 *     attachments hang off its comment, and Freshdesk-hosted inline images are
 *     pulled down so they survive Freshdesk being switched off;
 *   - an archived Freshdesk ticket is never left in an open status — a new one
 *     is created closed, and an existing one still showing open is closed, with
 *     a timeline entry saying why.
 *
 * Dry run by default — reads Freshdesk, writes nothing, prints what it would do:
 *
 *   sudo -u www-data php scripts/import-freshdesk-conversations.php \
 *       --domain=example.freshdesk.com --key-file=/path/to/api-key [--apply] \
 *       [--type-field=cf_x] [--location-field=cf_y] [--alias="IT=Information Technology" ...] \
 *       [--ticket=<id>] [--from=<id>] [--limit=<n>]
 *
 * --type-field / --location-field name the Freshdesk custom fields holding the
 * ticket type and location (default: Freshdesk's built-in type, no location).
 * --alias maps a Freshdesk type/location/group/priority name onto a local one.
 * Freshdesk ids are processed in order and progress is logged, so an
 * interrupted run resumes with --from.
 *
 * Safe to re-run: a created ticket is found again by legacy_id, each comment is
 * stamped with an invisible <!-- fd-conv:ID --> marker, and each attachment is
 * stored as fd_{freshdesk attachment id}.ext, so a re-run only fills gaps. Rows
 * are inserted directly — no emails, Teams messages, notifications, SLA or
 * automation side effects.
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';

loadEnv(ROOT_DIR . '/.env');

// Same values src/bootstrap.php defines for web requests; the helpers below need them.
define('UPLOAD_MAX_SIZE', (int) env('UPLOAD_MAX_SIZE', '20971520'));
define('UPLOAD_ALLOWED_TYPES', array_map('trim', explode(',', env('UPLOAD_ALLOWED_TYPES', 'application/pdf,image/jpeg,image/png'))));
define('ATTACHMENT_STORAGE_PATH', ROOT_DIR . '/storage/attachments/');

$opt    = getopt('', ['domain:', 'key-file:', 'apply', 'ticket:', 'from:', 'limit:', 'alias:', 'type-field:', 'location-field:']);
$domain = preg_replace('#^https?://|/.*$#', '', (string) ($opt['domain'] ?? ''));
$key    = is_readable((string) ($opt['key-file'] ?? '')) ? trim((string) file_get_contents($opt['key-file'])) : '';
$apply  = isset($opt['apply']);
if ($domain === '' || $key === '') {
    fwrite(STDERR, "Usage: php scripts/import-freshdesk-conversations.php --domain=x.freshdesk.com --key-file=PATH [--apply] [--type-field=F] [--location-field=F] [--alias=A=B] [--ticket=ID] [--from=ID] [--limit=N]\n");
    exit(1);
}
$aliases = [];
foreach ((array) ($opt['alias'] ?? []) as $pair) {
    [$from, $to] = array_map('trim', explode('=', (string) $pair, 2)) + [1 => ''];
    $aliases[strtolower($from)] = strtolower($to);
}

function out(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

/**
 * GET with Freshdesk's rate limit honoured: a 429 sleeps for Retry-After and
 * tries again, so a long run just slows to the plan's limit instead of failing.
 * Credentials go only to the Freshdesk host — attachment URLs are pre-signed S3
 * links that reject an Authorization header, and libcurl drops CURLOPT_USERPWD
 * on a cross-host redirect anyway.
 *
 * @return array{0:int,1:string} [HTTP status, body]
 */
function http(string $url, bool $auth): array
{
    global $key;
    for ($attempt = 1; ; $attempt++) {
        $retryAfter = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $h) use (&$retryAfter): int {
                if (stripos($h, 'Retry-After:') === 0) {
                    $retryAfter = (int) trim(substr($h, 12));
                }
                return strlen($h);
            },
        ]);
        if ($auth) {
            curl_setopt($ch, CURLOPT_USERPWD, $key . ':X');
        }
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 429) {
            sleep(max($retryAfter, 5));
            continue;
        }
        if (($body === false || $status >= 500) && $attempt < 5) {
            sleep(10 * $attempt);
            continue;
        }
        return [$status, $body === false ? '' : (string) $body];
    }
}

/** Freshdesk API GET → decoded JSON, or null on 404. Anything else is fatal for the ticket. */
function fd(string $path): ?array
{
    global $domain;
    [$status, $body] = http("https://{$domain}/api/v2{$path}", true);
    if ($status === 404) {
        return null;
    }
    if ($status !== 200) {
        throw new RuntimeException("Freshdesk {$path} returned HTTP {$status}: " . substr($body, 0, 200));
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}

/** All pages of a Freshdesk list endpoint. */
function fdAll(string $path): array
{
    $rows = [];
    for ($page = 1; ; $page++) {
        $batch = fd($path . (str_contains($path, '?') ? '&' : '?') . "per_page=100&page={$page}") ?? [];
        $rows  = array_merge($rows, $batch);
        if (count($batch) < 100) {
            return $rows;
        }
    }
}

/** "Jane Doe <jane@x.ca>" | "jane@x.ca" → [email, name] */
function parseAddress(?string $raw): array
{
    $raw = trim((string) $raw);
    if (preg_match('/^"?([^"<]*)"?\s*<([^>]+)>$/', $raw, $m)) {
        return [strtolower(trim($m[2])), trim($m[1])];
    }
    return filter_var($raw, FILTER_VALIDATE_EMAIL) ? [strtolower($raw), ''] : [null, ''];
}

/** Freshdesk user id → [email, name]; agents preloaded, contacts looked up once and cached. */
function fdPerson(string $uid): ?array
{
    global $fdAgents, $fdContacts;
    if ($uid === '') {
        return null;
    }
    if (isset($fdAgents[$uid])) {
        return $fdAgents[$uid];
    }
    if (!array_key_exists($uid, $fdContacts)) {
        $contact = fd("/contacts/{$uid}");
        $fdContacts[$uid] = $contact && !empty($contact['email'])
            ? [strtolower($contact['email']), (string) ($contact['name'] ?? '')]
            : null;
    }
    return $fdContacts[$uid];
}

/** Who wrote a conversation. An incoming email names its sender, which saves a contact lookup. */
function fdAuthor(array $c): array
{
    global $fdAgents;
    $uid = (string) ($c['user_id'] ?? '');
    [$email, $name] = parseAddress($c['from_email'] ?? null);
    if (!isset($fdAgents[$uid]) && !empty($c['incoming']) && $email !== null) {
        return [$email, $name];
    }
    return fdPerson($uid) ?? [$email, $name];
}

/** Map a Freshdesk person to a local user id, creating a portal user (as the CSV importer does) when needed. */
function localUserId(PDO $db, ?string $email, string $name): ?int
{
    global $users, $apply, $stats, $domain;
    // Freshdesk's own mailbox (automations, system notes) is nobody — it shows as System.
    if ($email === null || str_ends_with($email, '@' . strtolower($domain))) {
        return null;
    }
    if (isset($users[$email])) {
        return $users[$email];
    }
    $stats['users_created'][$email] = $name;
    if (!$apply) {
        return $users[$email] = 0; // placeholder so the dry run lists each new user once
    }
    [$first, $last] = splitFullName($name !== '' ? $name : (string) strstr($email, '@', true));
    $db->prepare('INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)')
       ->execute([$first, $last, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'user']);
    return $users[$email] = (int) $db->lastInsertId();
}

/** Name → local id through --alias; an unknown non-empty name is tallied for the summary. */
function lookup(string $kind, array $map, ?string $name): ?int
{
    global $aliases, $stats;
    $k = strtolower(trim((string) $name));
    if ($k === '') {
        return null;
    }
    $id = $map[$aliases[$k] ?? $k] ?? null;
    if ($id === null) {
        $stats['unmatched']["{$kind}: {$name}"] = ($stats['unmatched']["{$kind}: {$name}"] ?? 0) + 1;
    }
    return $id;
}

/** Local status slug for a Freshdesk ticket. An archived ticket is finished, so it never lands in an open bucket. */
function localStatus(array $fdTicket): string
{
    global $fdStatuses, $statusBuckets, $closedSlug;
    $slug = str_replace(' ', '_', strtolower(trim($fdStatuses[(string) $fdTicket['status']] ?? '')));
    if (!isset($statusBuckets[$slug])) {
        $slug = ticketDefaultNewStatusSlug();
    }
    if (!empty($fdTicket['archived']) && $statusBuckets[$slug] !== 'closed') {
        $slug = $closedSlug;
    }
    return $slug;
}

/**
 * Pull Freshdesk-hosted inline images into attachment storage. Each one is
 * swapped for a data: URI and handed to inlineImagesToAttachments(), so it goes
 * through exactly the validation and src rewrite a pasted screenshot does.
 */
function localiseInlineImages(string $html, &$saved): string
{
    global $domain, $stats;
    $html = (string) preg_replace_callback(
        '/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])(https?:\/\/[^"\']+)\2/i',
        static function (array $m) use ($domain, &$stats): string {
            $url  = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5);
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (!preg_match('/(^|\.)(freshdesk\.com|freshworks\.com|freshworksapi\.com|amazonaws\.com)$/', $host)) {
                return $m[0];
            }
            [$status, $bytes] = http($url, $host === strtolower($domain));
            if ($status !== 200 || $bytes === '') {
                $stats['inline_failed']++;
                return $m[0];
            }
            $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            return $m[1] . $m[2] . 'data:' . $mime . ';base64,' . base64_encode($bytes) . $m[2];
        },
        $html
    );
    $html = inlineImagesToAttachments($html, $saved, $rejected);
    $stats['inline_images'] += count($saved);
    $stats['inline_failed'] += count($rejected);
    return $html;
}

/** Rich text for a TEXT column: sanitized HTML, or its plain-text twin when Outlook markup won't fit. */
function fitText(string $prefix, string $html, string $plain): string
{
    $html = sanitizeRichHtml($html);
    if (!textColumnOverflows($prefix . $html)) {
        return $prefix . $html;
    }
    $text = nl2br(e($plain));
    if (textColumnOverflows($prefix . $text)) {
        $text = nl2br(e(mb_strcut($plain, 0, 50000))) . '<p><em>[truncated during import]</em></p>';
    }
    return $prefix . $text;
}

/**
 * Download one Freshdesk attachment into storage as fd_{id}.ext. A failed
 * download is logged and skipped (the next run retries it) rather than
 * discarding the whole ticket; only a storage problem aborts.
 */
function downloadAttachment(array $att, array &$written): ?array
{
    global $stats;
    if (disk_free_space(ATTACHMENT_STORAGE_PATH) < 1024 ** 3) {
        throw new RuntimeException('Less than 1 GB free in attachment storage — stopping.');
    }
    [$status, $bytes] = http((string) ($att['attachment_url'] ?? ''), false);
    if ($status !== 200) {
        $stats['attachments_failed']++;
        out("  attachment {$att['id']} ({$att['name']}) download returned HTTP {$status} — skipped");
        return null;
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: (string) ($att['content_type'] ?? 'application/octet-stream');
    $stored = 'fd_' . $att['id'] . '.' . safeUploadExtension($mime, (string) $att['name']);
    if (file_put_contents(ATTACHMENT_STORAGE_PATH . $stored, $bytes) === false) {
        throw new RuntimeException("could not write {$stored}");
    }
    $written[] = ATTACHMENT_STORAGE_PATH . $stored;
    $stats['attachments']++;
    $stats['attachment_bytes'] += strlen($bytes);
    return [
        'original_name' => mb_substr((string) $att['name'], 0, 255),
        'stored_name'   => $stored,
        'mime_type'     => $mime,
        'file_size'     => strlen($bytes),
    ];
}

/** Count a keyed tally in $stats. */
function tally(string $bucket, string $key): void
{
    global $stats;
    $stats[$bucket][$key] = ($stats[$bucket][$key] ?? 0) + 1;
}

/* ── Local lookups ────────────────────────────────────────────────────── */

$db = Database::connect();
if (!is_dir(ATTACHMENT_STORAGE_PATH)) {
    mkdir(ATTACHMENT_STORAGE_PATH, 0755, true);
}

$byName = static function (string $sql) use ($db): array {
    $map = [];
    foreach ($db->query($sql) as $r) {
        $map[strtolower($r['name'])] = (int) $r['id'];
    }
    return $map;
};
$users = [];
foreach ($db->query('SELECT id, LOWER(email) AS email FROM users') as $u) {
    $users[$u['email']] = (int) $u['id'];
}
$types      = $byName('SELECT id, name FROM ticket_types');
$locations  = $byName('SELECT id, name FROM locations');
$groups     = $byName('SELECT id, name FROM `groups`');
$priorities = $byName('SELECT id, name FROM ticket_priorities');
$tags       = $byName('SELECT id, name FROM ticket_tags');
$statusBuckets = [];
$closedSlug    = 'closed';
foreach ($db->query('SELECT slug, bucket, is_default_closed FROM ticket_statuses') as $s) {
    $statusBuckets[$s['slug']] = $s['bucket'];
    if ((int) $s['is_default_closed'] === 1) {
        $closedSlug = $s['slug'];
    }
}
$local = []; // freshdesk id → local ticket
foreach ($db->query("SELECT id, legacy_id, created_by, status, group_id FROM tickets WHERE legacy_id REGEXP '^[0-9]+$'") as $t) {
    $local[(int) $t['legacy_id']] = $t;
}

/* ── Freshdesk lookups ────────────────────────────────────────────────── */

$fdAgents = $fdContacts = $fdGroups = $fdStatuses = [];
foreach (fdAll('/agents') as $a) {
    $fdAgents[(string) $a['id']] = [strtolower((string) $a['contact']['email']), (string) $a['contact']['name']];
}
foreach (fdAll('/groups') as $g) {
    $fdGroups[(string) $g['id']] = (string) $g['name'];
}
foreach (fd('/ticket_fields?type=default_status')[0]['choices'] ?? [] as $id => $labels) {
    $fdStatuses[(string) $id] = (string) $labels[0];
}
$fdPriorities = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
$newest       = fd('/tickets?order_by=created_at&order_type=desc&per_page=1&updated_since=2000-01-01T00:00:00Z');
$maxId        = (int) ($newest[0]['id'] ?? 0);

$ids = isset($opt['ticket']) ? [(int) $opt['ticket']] : range(max(1, (int) ($opt['from'] ?? 1)), $maxId);
if (isset($opt['limit'])) {
    $ids = array_slice($ids, 0, (int) $opt['limit']);
}

$stats = [
    'ids' => 0, 'not_in_freshdesk' => 0, 'deleted_or_spam' => 0, 'errors' => 0,
    'tickets_existing' => 0, 'tickets_created' => 0, 'status_closed_archived' => 0,
    'comments' => 0, 'comments_private' => 0, 'comments_existing' => 0,
    'attachments' => 0, 'attachment_bytes' => 0, 'attachments_existing' => 0, 'attachments_failed' => 0,
    'inline_images' => 0, 'inline_failed' => 0,
    'created_status' => [], 'status_differs' => [], 'group_differs' => [], 'unmatched' => [], 'users_created' => [],
];

out(($apply ? 'APPLY' : 'DRY RUN') . ' — Freshdesk ids ' . reset($ids) . '–' . end($ids) . " of {$maxId}, "
    . count($local) . ' already here, ' . count($fdAgents) . " agents, source {$domain}");

$insertTicket = $db->prepare(
    'INSERT INTO tickets (subject, description, legacy_id, created_by, created_at, due_date, type_id, location_id, status, priority_id, assigned_to, group_id, updated_at, sla_exempt)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
);
$insertTimeline = $db->prepare(
    'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at) VALUES (?, ?, ?, ?, ?, ?)'
);
$insertAttachment = $db->prepare(
    'INSERT INTO ticket_attachments (ticket_id, timeline_id, uploaded_by, original_name, stored_name, mime_type, file_size, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$insertTag    = $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)');
$insertTagMap = $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)');
// updated_at = updated_at keeps the ON UPDATE column from jumping to "now".
$closeTicket  = $db->prepare('UPDATE tickets SET status = ?, updated_at = updated_at WHERE id = ?');
$findDone     = $db->prepare("SELECT id, SUBSTRING(details, 1, 40) AS head FROM ticket_timeline WHERE ticket_id = ? AND details LIKE '<!-- fd-conv:%'");
$findHave     = $db->prepare("SELECT stored_name FROM ticket_attachments WHERE ticket_id = ? AND stored_name LIKE 'fd!_%' ESCAPE '!'");

foreach ($ids as $n => $fdId) {
    $stats['ids']++;
    $here    = $local[$fdId] ?? null;
    $written = []; // files this ticket wrote, removed again if its transaction rolls back
    $before  = [$stats['comments'], $stats['attachments']];

    try {
        // Closed tickets move to Freshdesk's archive under a different path. A ticket
        // that isn't here yet is most likely archived (that's what the CSV export
        // misses), so try the archive first for those and save a request.
        $paths = $here ? ["/tickets/{$fdId}", "/tickets/archived/{$fdId}"] : ["/tickets/archived/{$fdId}", "/tickets/{$fdId}"];
        $base  = null;
        $fdTicket = null;
        foreach ($paths as $p) {
            if (($fdTicket = fd($p)) !== null) {
                $base = $p;
                break;
            }
        }
        if ($fdTicket === null) {
            $stats['not_in_freshdesk']++;
            continue;
        }
        if (!$here && (!empty($fdTicket['deleted']) || !empty($fdTicket['spam']))) {
            $stats['deleted_or_spam']++;
            continue;
        }
        $convs  = fdAll("{$base}/conversations");
        $status = localStatus($fdTicket);

        // Resolve (and if need be create) every person before the transaction,
        // so a rollback can never leave a cached id for a user that isn't there.
        $authors = [];
        foreach ($convs as $c) {
            [$email, $name] = fdAuthor($c);
            $authors[(string) $c['id']] = localUserId($db, $email, $name) ?: null;
        }
        if (!$here) {
            [$reqEmail, $reqName] = fdPerson((string) ($fdTicket['requester_id'] ?? '')) ?? [null, ''];
            // Freshdesk wipes a deleted agent/contact down to "Deleted Agent" with no
            // email; those tickets get one shared placeholder (.invalid never receives mail).
            $requester = localUserId($db, $reqEmail, $reqName)
                ?? localUserId($db, 'deleted-user@import.invalid', 'Deleted Freshdesk user');
        }

        if ($apply) {
            $db->beginTransaction();
        }

        $ticketCreated = parseImportDateTime((string) $fdTicket['created_at']);
        if ($here) {
            $ticketId  = (int) $here['id'];
            $createdBy = (int) $here['created_by'];
            $stats['tickets_existing']++;
            if ($here['status'] !== $status) {
                // Only an archived ticket still showing open is corrected; any other
                // difference may be deliberate work done here, so it is just reported.
                if (!empty($fdTicket['archived']) && ($statusBuckets[$here['status']] ?? 'open') !== 'closed') {
                    $stats['status_closed_archived']++;
                    out("#{$ticketId} (fd {$fdId}): {$here['status']} → {$status} (archived in Freshdesk)" . ($apply ? '' : ' [dry run]'));
                    if ($apply) {
                        $closeTicket->execute([$status, $ticketId]);
                        $insertTimeline->execute([$ticketId, null, 'status_changed',
                            'Status changed from ' . ticketStatusLabel($here['status']) . ' to ' . ticketStatusLabel($status)
                            . ' — the ticket is archived in Freshdesk (import sync)', 0, date('Y-m-d H:i:s')]);
                    }
                } else {
                    tally('status_differs', "{$here['status']} here, {$status} in Freshdesk");
                }
            }
            // Reported, never changed: the group decides who sees a live ticket.
            $fdGroup = lookup('group', $groups, $fdGroups[(string) ($fdTicket['group_id'] ?? '')] ?? null);
            if ($fdGroup !== null && $fdGroup !== (int) $here['group_id']) {
                $groupNames = array_flip($groups);
                tally('group_differs', ($groupNames[(int) $here['group_id']] ?? 'none') . " here, {$groupNames[$fdGroup]} in Freshdesk");
            }
        } else {
            $typeName = isset($opt['type-field']) ? ($fdTicket['custom_fields'][$opt['type-field']] ?? null) : ($fdTicket['type'] ?? null);
            $typeId   = lookup('type', $types, $typeName);
            $groupId  = resolveTicketGroup($db, lookup('group', $groups, $fdGroups[(string) ($fdTicket['group_id'] ?? '')] ?? null), $typeId);
            $agent    = fdPerson((string) ($fdTicket['responder_id'] ?? ''));
            $assignee = $agent !== null ? ($users[$agent[0]] ?? null) : null;
            if ($agent !== null && !$assignee) {
                tally('unmatched', "agent: {$agent[0]}");
            }
            $createdBy = (int) $requester;
            $stats['tickets_created']++;
            tally('created_status', $status);

            $ticketId = 0;
            if ($apply) {
                $description = localiseInlineImages((string) ($fdTicket['description'] ?? ''), $descInline);
                $written     = array_merge($written, array_map(static fn (array $s): string => ATTACHMENT_STORAGE_PATH . $s['stored_name'], $descInline));
                $description = fitText('', $description, (string) ($fdTicket['description_text'] ?? ''));
                $dueBy       = parseImportDateTime((string) ($fdTicket['due_by'] ?? ''));
                $insertTicket->execute([
                    mb_substr(trim((string) $fdTicket['subject']) ?: '(No subject)', 0, 255),
                    $description !== '' ? $description : '(Imported from legacy system)',
                    (string) $fdId,
                    $createdBy,
                    $ticketCreated,
                    $dueBy !== null ? substr($dueBy, 0, 10) : null,
                    $typeId,
                    isset($opt['location-field']) ? lookup('location', $locations, $fdTicket['custom_fields'][$opt['location-field']] ?? null) : null,
                    $status,
                    lookup('priority', $priorities, $fdPriorities[(int) ($fdTicket['priority'] ?? 0)] ?? null),
                    $assignee,
                    $groupId,
                    parseImportDateTime((string) $fdTicket['updated_at']) ?? $ticketCreated,
                ]);
                $ticketId = (int) $db->lastInsertId();
                $insertTimeline->execute([$ticketId, $createdBy, 'created', 'Ticket created (imported from legacy system).', 0, $ticketCreated]);
                foreach ($descInline as $s) {
                    $insertAttachment->execute([$ticketId, null, $createdBy, $s['original_name'], $s['stored_name'], $s['mime_type'], $s['file_size'], $ticketCreated]);
                }
                foreach ($fdTicket['tags'] ?? [] as $tagName) {
                    $tagKey = strtolower(trim((string) $tagName));
                    if ($tagKey === '') {
                        continue;
                    }
                    if (!isset($tags[$tagKey])) {
                        $insertTag->execute([trim((string) $tagName)]);
                        $tags[$tagKey] = (int) $db->lastInsertId();
                    }
                    $insertTagMap->execute([$ticketId, $tags[$tagKey]]);
                }
            } else {
                // Dry run: still resolve the mappings so unmatched names get reported.
                if (isset($opt['location-field'])) {
                    lookup('location', $locations, $fdTicket['custom_fields'][$opt['location-field']] ?? null);
                }
                lookup('priority', $priorities, $fdPriorities[(int) ($fdTicket['priority'] ?? 0)] ?? null);
            }
        }

        // What earlier runs already brought in for this ticket.
        $doneConvs = $doneAtts = [];
        if ($ticketId) {
            $findDone->execute([$ticketId]);
            foreach ($findDone->fetchAll() as $r) {
                if (preg_match('/fd-conv:(\d+)/', $r['head'], $m)) {
                    $doneConvs[$m[1]] = (int) $r['id'];
                }
            }
            $findHave->execute([$ticketId]);
            foreach ($findHave->fetchAll(PDO::FETCH_COLUMN) as $name) {
                $doneAtts[(string) strtok(substr($name, 3), '.')] = true;
            }
        }

        // Pending attachments: [freshdesk attachment, timeline id or null, uploader, created_at].
        $pending = [];
        foreach ($fdTicket['attachments'] ?? [] as $att) {
            $pending[] = [$att, null, $createdBy, $ticketCreated];
        }

        foreach ($convs as $c) {
            $convId     = (string) $c['id'];
            $createdAt  = parseImportDateTime((string) $c['created_at']);
            $userId     = $authors[$convId];
            $timelineId = $doneConvs[$convId] ?? null;

            if ($timelineId !== null) {
                $stats['comments_existing']++;
            } else {
                $stats['comments']++;
                $stats['comments_private'] += !empty($c['private']) ? 1 : 0;
                if ($apply) {
                    $html    = localiseInlineImages((string) ($c['body'] ?? ''), $inline);
                    $written = array_merge($written, array_map(static fn (array $s): string => ATTACHMENT_STORAGE_PATH . $s['stored_name'], $inline));
                    $insertTimeline->execute([$ticketId, $userId, 'comment',
                        fitText('<!-- fd-conv:' . $convId . " -->\n", $html, (string) ($c['body_text'] ?? '')),
                        !empty($c['private']) ? 1 : 0, $createdAt]);
                    $timelineId = (int) $db->lastInsertId();
                    foreach ($inline as $s) {
                        $insertAttachment->execute([$ticketId, $timelineId, $userId ?? $createdBy, $s['original_name'], $s['stored_name'], $s['mime_type'], $s['file_size'], $createdAt]);
                    }
                }
            }
            // Checked even on an already-imported comment, so a re-run fills in a
            // download that failed the first time.
            foreach ($c['attachments'] ?? [] as $att) {
                $pending[] = [$att, $timelineId, $userId ?? $createdBy, $createdAt];
            }
        }

        foreach ($pending as [$att, $timelineId, $uploader, $createdAt]) {
            if (isset($doneAtts[(string) $att['id']])) {
                $stats['attachments_existing']++;
            } elseif (!$apply) {
                $stats['attachments']++;
                $stats['attachment_bytes'] += (int) $att['size'];
            } elseif ($a = downloadAttachment($att, $written)) {
                $insertAttachment->execute([$ticketId, $timelineId, $uploader, $a['original_name'], $a['stored_name'], $a['mime_type'], $a['file_size'], $createdAt]);
            }
        }

        if ($apply) {
            $db->commit();
        }
        if (!$here) {
            out(sprintf('fd %d: created #%d (%s), %d comment(s), %d attachment(s)%s', $fdId, $ticketId, $status,
                $stats['comments'] - $before[0], $stats['attachments'] - $before[1], $apply ? '' : ' [dry run]'));
        } elseif ($stats['comments'] > $before[0] || $stats['attachments'] > $before[1]) {
            out(sprintf('fd %d: #%d +%d comment(s), +%d attachment(s)%s', $fdId, $ticketId,
                $stats['comments'] - $before[0], $stats['attachments'] - $before[1], $apply ? '' : ' [dry run]'));
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($written as $f) {
            @unlink($f);
        }
        $stats['errors']++;
        out("fd {$fdId}: ERROR " . $e->getMessage() . ' — rolled back, retry with --ticket=' . $fdId);
        if (str_contains($e->getMessage(), 'free in attachment storage')) {
            break;
        }
    }

    if (($n + 1) % 100 === 0) {
        out("… progress: fd {$fdId} done (" . ($n + 1) . '/' . count($ids) . ") — resume with --from=" . ($fdId + 1));
    }
}

$newUsers = $stats['users_created'];
$stats['users_created']    = count($newUsers);
$stats['attachment_bytes'] = formatFileSize($stats['attachment_bytes']);
out(($apply ? 'DONE ' : 'DRY RUN DONE ') . json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
foreach ($newUsers as $email => $name) {
    out('  ' . ($apply ? 'created' : 'would create') . " user: {$name} <{$email}>");
}
