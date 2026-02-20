<?php
/**
 * LocalDesk — Inbound Email Reply Processor
 *
 * Polls a configured IMAP mailbox, finds unread ticket reply emails,
 * strips quoted content, and posts the reply as a timeline comment.
 *
 * Usage (cron — every 5 minutes):
 *   */5 * * * * php /path/to/app/scripts/process-replies.php >> /path/to/app/storage/logs/imap.log 2>&1
 *
 * Requirements:
 *   - PHP IMAP extension (php-imap)
 *   - Inbound mail configured in Admin → Settings
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';

loadEnv(ROOT_DIR . '/.env');

// ─── Logging helper ───────────────────────────────────────────────────────────
function logMsg(string $level, string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $msg . PHP_EOL;
}

// ─── Guard checks ─────────────────────────────────────────────────────────────
if (!extension_loaded('imap')) {
    logMsg('ERROR', 'PHP IMAP extension is not loaded. Enable extension=imap in php.ini.');
    exit(1);
}

$imapEnabled = getSetting('imap_enabled');
if ($imapEnabled !== '1') {
    logMsg('INFO', 'Inbound mail processing is disabled. Enable it in Admin → Settings.');
    exit(0);
}

$imapHost       = getSetting('imap_host');
$imapPort       = (int) getSetting('imap_port', '993');
$imapEncryption = getSetting('imap_encryption', 'ssl');
$imapUsername   = getSetting('imap_username');
$imapPassword   = getSetting('imap_password');
$imapFolder     = getSetting('imap_folder', 'INBOX') ?: 'INBOX';

if ($imapHost === '' || $imapUsername === '' || $imapPassword === '') {
    logMsg('ERROR', 'IMAP host, username, or password is not configured.');
    exit(1);
}

// ─── Build IMAP connection string ─────────────────────────────────────────────
$encFlag = match ($imapEncryption) {
    'ssl'  => '/imap/ssl',
    'tls'  => '/imap/tls',
    default => '/imap',
};
// novalidate-cert allows self-signed certs (common in dev/testing)
$connStr = '{' . $imapHost . ':' . $imapPort . $encFlag . '/novalidate-cert}' . $imapFolder;

logMsg('INFO', 'Connecting to ' . $imapHost . ':' . $imapPort . ' as ' . $imapUsername);

$imap = @imap_open($connStr, $imapUsername, $imapPassword);
if ($imap === false) {
    logMsg('ERROR', 'Failed to connect: ' . imap_last_error());
    exit(1);
}

logMsg('INFO', 'Connected. Searching for unseen messages...');

// ─── Search for unread messages ───────────────────────────────────────────────
$msgNos = imap_search($imap, 'UNSEEN');
if ($msgNos === false) {
    logMsg('INFO', 'No new messages found.');
    imap_close($imap);
    exit(0);
}

logMsg('INFO', count($msgNos) . ' new message(s) found.');

$db        = Database::connect();
$processed = 0;
$skipped   = 0;

foreach ($msgNos as $msgNo) {
    $headers = imap_headerinfo($imap, $msgNo);
    if (!$headers) {
        logMsg('WARN', "Message #{$msgNo}: Could not read headers, skipping.");
        markSeen($imap, $msgNo);
        $skipped++;
        continue;
    }

    $subject  = isset($headers->subject) ? imap_utf8($headers->subject) : '';
    $fromAddr = '';
    $fromName = '';

    if (!empty($headers->from[0])) {
        $fromAddr = strtolower(trim($headers->from[0]->mailbox . '@' . $headers->from[0]->host));
        $fromName = isset($headers->from[0]->personal)
            ? imap_utf8($headers->from[0]->personal)
            : $fromAddr;
    }

    logMsg('INFO', "Message #{$msgNo}: From={$fromAddr} Subject=\"{$subject}\"");

    // ── Extract ticket ID from subject ────────────────────────────────────────
    $ticketId = null;
    if (preg_match('/\[Ticket\s*#(\d+)\]/i', $subject, $m)) {
        $ticketId = (int) $m[1];
    }

    if ($ticketId === null) {
        logMsg('WARN', "  No ticket ID found in subject, skipping.");
        markSeen($imap, $msgNo);
        $skipped++;
        continue;
    }

    // ── Verify the ticket exists and is not closed ────────────────────────────
    $ticketStmt = $db->prepare('SELECT id, subject, status, created_by FROM tickets WHERE id = ?');
    $ticketStmt->execute([$ticketId]);
    $ticket = $ticketStmt->fetch();

    if (!$ticket) {
        logMsg('WARN', "  Ticket #{$ticketId} not found, skipping.");
        markSeen($imap, $msgNo);
        $skipped++;
        continue;
    }

    if ($ticket['status'] === 'closed') {
        logMsg('WARN', "  Ticket #{$ticketId} is closed, skipping reply.");
        markSeen($imap, $msgNo);
        $skipped++;
        continue;
    }

    // ── Find the sender in the users table ────────────────────────────────────
    // Accept replies from the ticket creator or any registered user
    $userStmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE LOWER(email) = ?');
    $userStmt->execute([$fromAddr]);
    $user = $userStmt->fetch();

    if (!$user) {
        logMsg('WARN', "  Sender {$fromAddr} is not a registered user, skipping.");
        markSeen($imap, $msgNo);
        $skipped++;
        continue;
    }

    // ── Extract the reply body ────────────────────────────────────────────────
    $rawBody = getMessageBody($imap, $msgNo);
    $body    = extractReplyBody($rawBody);

    if (trim($body) === '') {
        logMsg('WARN', "  Reply body is empty after stripping quoted text, skipping.");
        markSeen($imap, $msgNo);
        $skipped++;
        continue;
    }

    // ── Insert timeline comment ───────────────────────────────────────────────
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
    )->execute([$ticketId, $user['id'], 'comment', $body]);

    logMsg('INFO', "  Added reply from {$fromAddr} to Ticket #{$ticketId} ✓");

    markSeen($imap, $msgNo);
    $processed++;
}

imap_expunge($imap);
imap_close($imap);

logMsg('INFO', "Done. Processed: {$processed}, Skipped: {$skipped}.");
exit(0);

// ─── Helper functions ─────────────────────────────────────────────────────────

/**
 * Get the plain-text body of an email message.
 * Handles simple (type 0) and multipart messages.
 */
function getMessageBody(\IMAP\Connection|false $imap, int $msgNo): string
{
    $structure = imap_fetchstructure($imap, $msgNo);

    if (!$structure) {
        return '';
    }

    // Simple single-part text message
    if ($structure->type === TYPETEXT) {
        $body = imap_fetchbody($imap, $msgNo, '1');
        return decodeBody($body, $structure->encoding);
    }

    // Multipart — walk parts to find text/plain, fallback to text/html
    if (!empty($structure->parts)) {
        $plainBody = '';
        $htmlBody  = '';

        foreach ($structure->parts as $i => $part) {
            $section = (string) ($i + 1);

            if ($part->type === TYPETEXT && strtolower($part->subtype) === 'plain') {
                $plainBody = decodeBody(imap_fetchbody($imap, $msgNo, $section), $part->encoding);
            } elseif ($part->type === TYPETEXT && strtolower($part->subtype) === 'html' && $plainBody === '') {
                $htmlBody = decodeBody(imap_fetchbody($imap, $msgNo, $section), $part->encoding);
            }

            // Look one level deeper (e.g. multipart/alternative inside multipart/mixed)
            if (!empty($part->parts)) {
                foreach ($part->parts as $j => $subpart) {
                    $subSection = $section . '.' . ($j + 1);
                    if ($subpart->type === TYPETEXT && strtolower($subpart->subtype) === 'plain') {
                        $plainBody = decodeBody(imap_fetchbody($imap, $msgNo, $subSection), $subpart->encoding);
                    } elseif ($subpart->type === TYPETEXT && strtolower($subpart->subtype) === 'html' && $plainBody === '') {
                        $htmlBody = decodeBody(imap_fetchbody($imap, $msgNo, $subSection), $subpart->encoding);
                    }
                }
            }
        }

        if ($plainBody !== '') {
            return $plainBody;
        }
        if ($htmlBody !== '') {
            return strip_tags(html_entity_decode($htmlBody, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    return '';
}

/**
 * Decode a message body part based on its transfer encoding.
 */
function decodeBody(string $body, int $encoding): string
{
    return match ($encoding) {
        ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
        ENCBASE64          => base64_decode($body),
        default            => $body,
    };
}

/**
 * Strip quoted reply content from an email body.
 *
 * Stops at common reply markers:
 * - Lines starting with > (standard quoting)
 * - "On [date] ... wrote:" patterns
 * - Outlook-style "-----Original Message-----" dividers
 * - "From:", "Sent:", "To:" headers in forwarded blocks
 */
function extractReplyBody(string $body): string
{
    // Normalise line endings
    $body  = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    $result = [];

    // Patterns that signal the start of the quoted original
    $stopPatterns = [
        '/^>/',                                        // Quoted line
        '/^On\s.+\bwrote\s*:\s*$/is',                 // "On Mon, 1 Jan wrote:"
        '/^-{3,}\s*Original Message\s*-{3,}/i',       // -----Original Message-----
        '/^-{3,}\s*Forwarded\s*-{3,}/i',
        '/^_{5,}$/',                                   // ____________
        '/^From:\s+.+/i',                              // From: header in a reply block
    ];

    // "On [date] ..." can span two lines; buffer one line to look ahead
    $prevLine = '';
    foreach ($lines as $line) {
        $stripped = rtrim($line);

        foreach ($stopPatterns as $pattern) {
            if (preg_match($pattern, $stripped)) {
                goto done;
            }
        }

        // Outlook two-line "On <date>\n<person> wrote:" — stop on second line
        if ($prevLine !== '' && preg_match('/^On\s/i', $prevLine) && preg_match('/wrote\s*:\s*$/i', $stripped)) {
            // Remove the "On …" line we already added
            if (!empty($result)) {
                array_pop($result);
            }
            goto done;
        }

        $result[]  = $stripped;
        $prevLine  = $stripped;
    }

    done:
    // Trim trailing blank lines
    while (!empty($result) && trim(end($result)) === '') {
        array_pop($result);
    }

    return implode("\n", $result);
}

/**
 * Mark a message as seen (read) without deleting it.
 */
function markSeen(\IMAP\Connection|false $imap, int $msgNo): void
{
    imap_setflag_full($imap, (string) $msgNo, '\\Seen');
}
