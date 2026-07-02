<?php
/**
 * OpenHelpDesk — Inbound Email Reply Processor (Microsoft Graph API)
 *
 * Polls a Microsoft 365 mailbox via the Graph API using OAuth 2.0
 * Client Credentials flow. Finds unread ticket reply emails, strips
 * quoted content, and posts the reply as a timeline comment.
 *
 * Usage (cron — every 5 minutes):
 *   * /5 * * * * php /path/to/app/scripts/process-replies.php >> /path/to/app/storage/logs/graph-mail.log 2>&1
 *   (remove the space between * and /5)
 *
 * Requirements:
 *   - PHP cURL extension
 *   - Azure App Registration with Mail.Read + Mail.ReadWrite permissions
 *   - graph_* settings configured in Admin → Settings
 */

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/graph.php';   // curlGet/Post/Patch + getAccessToken

loadEnv(ROOT_DIR . '/.env');

// ─── Logging helper ───────────────────────────────────────────────────────────
function logMsg(string $level, string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $msg . PHP_EOL;
}

// ─── Guard checks ─────────────────────────────────────────────────────────────
if (!extension_loaded('curl')) {
    logMsg('ERROR', 'PHP cURL extension is not loaded. Enable extension=curl in php.ini.');
    exit(1);
}

$graphEnabled = getSetting('graph_enabled');
if ($graphEnabled !== '1') {
    logMsg('INFO', 'Inbound mail processing is disabled. Enable it in Admin → Settings.');
    exit(0);
}

$tenantId     = getSetting('graph_tenant_id');
$clientId     = getSetting('graph_client_id');
$clientSecret = getSetting('graph_client_secret');
$mailbox      = getSetting('graph_mailbox');

if ($tenantId === '' || $clientId === '' || $clientSecret === '' || $mailbox === '') {
    logMsg('ERROR', 'Graph API credentials (Tenant ID, Client ID, Client Secret, Mailbox) are not fully configured.');
    exit(1);
}

// ─── Obtain OAuth2 access token (Client Credentials flow) ─────────────────────
logMsg('INFO', 'Requesting access token from Microsoft...');

$accessToken = getAccessToken($tenantId, $clientId, $clientSecret);
if ($accessToken === null) {
    logMsg('ERROR', 'Failed to obtain access token. Check Tenant ID, Client ID, and Client Secret.');
    exit(1);
}

logMsg('INFO', 'Access token obtained. Fetching unread messages for ' . $mailbox . '...');

// ─── Fetch unread messages ─────────────────────────────────────────────────────
$messages = getUnreadMessages($accessToken, $mailbox);
if ($messages === null) {
    logMsg('ERROR', 'Failed to fetch messages from Graph API. Check mailbox address and API permissions.');
    exit(1);
}

$count = count($messages);
logMsg('INFO', "{$count} unread message(s) found.");

if ($count === 0) {
    exit(0);
}

$db        = Database::connect();
$processed = 0;
$skipped   = 0;

foreach ($messages as $msg) {
    $msgId   = $msg['id'] ?? '';
    $subject = $msg['subject'] ?? '';
    $fromObj = $msg['from']['emailAddress'] ?? [];
    $fromAddr = strtolower(trim($fromObj['address'] ?? ''));
    $fromName = trim($fromObj['name'] ?? $fromAddr);

    logMsg('INFO', "Message {$msgId}: From={$fromAddr} Subject=\"{$subject}\"");

    // ── Skip automated / out-of-office replies ────────────────────────────────
    if (isAutoReply($msg)) {
        logMsg('INFO', '  Auto-reply detected — skipping.');
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    // ── Extract ticket ID from subject ────────────────────────────────────────
    // Matches both [Ticket #123] and [#123] to handle default and custom templates.
    $ticketId = null;
    if (preg_match('/\[(?:Ticket\s*)?#(\d+)\]/i', $subject, $m)) {
        $ticketId = (int) $m[1];
    }

    // Fallback: recover the ticket ID from the In-Reply-To / References headers.
    // Outbound ticket mail stamps a Message-ID of the form <ticket-{id}-{time}@…>,
    // which replying clients echo back here — so a forwarded third party whose
    // reply has lost the "[#id]" subject token still threads back correctly.
    if ($ticketId === null) {
        $ticketId = extractTicketIdFromHeaders($msg);
        if ($ticketId !== null) {
            logMsg('INFO', "  Recovered Ticket #{$ticketId} from In-Reply-To/References headers.");
        }
    }

    if ($ticketId === null) {
        // ── Email-to-Ticket: create a new ticket from this inbound email ─────────
        if (getSetting('email_to_ticket_enabled') !== '1') {
            logMsg('WARN', '  No ticket ID in subject; email-to-ticket disabled — skipping.');
            markMessageRead($accessToken, $mailbox, $msgId);
            $skipped++;
            continue;
        }

        logMsg('INFO', '  No ticket ID found — creating new ticket from inbound email.');

        // Find sender in users table
        $userStmt = $db->prepare('SELECT id, first_name, last_name, email FROM users WHERE LOWER(email) = ?');
        $userStmt->execute([$fromAddr]);
        $senderUser = $userStmt->fetch();

        if (!$senderUser) {
            if (getSetting('email_to_ticket_auto_create_users') !== '1') {
                logMsg('WARN', "  Sender {$fromAddr} is not a registered user; auto-create disabled — skipping.");
                markMessageRead($accessToken, $mailbox, $msgId);
                $skipped++;
                continue;
            }

            // Auto-create a portal user for this sender
            [$firstName, $lastName] = parseEmailName($fromName, $fromAddr);
            $hashedPw = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $db->prepare('INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)')
               ->execute([$firstName, $lastName, $fromAddr, $hashedPw, 'user']);
            $newUserId = (int) $db->lastInsertId();
            $senderUser = ['id' => $newUserId, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $fromAddr];
            logMsg('INFO', "  Auto-created user #{$newUserId} ({$fromAddr}).");
        }

        $ticketSubject = trim($subject) !== '' ? trim($subject) : '(No Subject)';
        $body          = trim(extractGraphBody($msg));
        if ($body === '') {
            $body = '(No message body)';
        }

        $typeId     = getSetting('email_to_ticket_default_type_id') !== '' ? (int) getSetting('email_to_ticket_default_type_id') : null;
        $priorityId = getSetting('email_to_ticket_default_priority_id') !== '' ? (int) getSetting('email_to_ticket_default_priority_id') : null;
        // Pre-2.23 the email path skipped group_id entirely, so every
        // inbound email landed with NULL group — invisible to auto-assign,
        // stuck in the no-group queue. resolveTicketGroup() now chains
        // type-default → system default_group_id → first existing group
        // so an inbound email is always routed somewhere.
        $groupId    = resolveTicketGroup($db, null, $typeId);

        // Create the ticket
        $db->prepare(
            'INSERT INTO tickets (subject, description, created_by, type_id, status, priority_id, group_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$ticketSubject, $body, $senderUser['id'], $typeId, 'open', $priorityId, $groupId]);
        $newTicketId = (int) $db->lastInsertId();

        // Timeline entry
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$newTicketId, $senderUser['id'], 'created', 'Ticket created via inbound email.']);

        logMsg('INFO', "  Created Ticket #{$newTicketId} (subject: \"{$ticketSubject}\").");

        // AI classification (if enabled, non-confidential type, and the
        // ai_classify_inbound_email toggle is on) + auto-assign.
        if (getSetting('ai_classify_inbound_email', '1') === '1') {
            runPostTicketCreateHooks($db, $newTicketId);
        } else {
            autoAssignTicket($db, $newTicketId);
        }

        // Send ticket-created confirmation email to sender (gated by global + user prefs)
        notifyRequesterTicketCreated($db, $newTicketId);
        logMsg('INFO', "  Confirmation email dispatched to {$fromAddr} (subject to prefs).");

        markMessageRead($accessToken, $mailbox, $msgId);
        $processed++;
        continue;
    }

    // ── Verify the ticket exists ──────────────────────────────────────────────
    $ticketStmt = $db->prepare('SELECT id, subject, status, created_by FROM tickets WHERE id = ?');
    $ticketStmt->execute([$ticketId]);
    $ticket = $ticketStmt->fetch();

    if (!$ticket) {
        logMsg('WARN', "  Ticket #{$ticketId} not found, skipping.");
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    // ── Find the sender in the users table (with role) ────────────────────────
    $userStmt = $db->prepare('SELECT id, first_name, last_name, role FROM users WHERE LOWER(email) = ?');
    $userStmt->execute([$fromAddr]);
    $user = $userStmt->fetch();

    if (!$user) {
        logMsg('WARN', "  Sender {$fromAddr} is not a registered user, skipping.");
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    // ── Extract the reply body and parse hashtag commands ─────────────────────
    $rawBody = extractGraphBody($msg);
    $body    = extractReplyBody($rawBody);
    $parsed  = parseEmailCommands($body);
    $body    = $parsed['body'];

    // Resolve commands (admin/agent only, and never from a spoofed sender).
    // messageAuthFailed() blocks the privileged #-commands when Exchange Online
    // flagged this From as failing DMARC/compauth — otherwise an attacker could
    // spoof an agent's address to close / reopen / reprioritise any ticket.
    $newStatus     = null;
    $newPriorityId = null;
    $authFailed    = messageAuthFailed($msg);
    if ($authFailed) {
        logMsg('WARN', "  Sender authentication FAILED for {$fromAddr} (possible spoof) — ignoring inline commands, posting reply as unprivileged.");
    }
    if (!$authFailed && in_array($user['role'], ['admin', 'agent'], true)) {
        if ($parsed['status'] !== null) {
            $newStatus = $parsed['status'];
        }
        if ($parsed['priority_slug'] !== null) {
            $pStmt = $db->prepare('SELECT id FROM ticket_priorities WHERE LOWER(name) = ? LIMIT 1');
            $pStmt->execute([$parsed['priority_slug']]);
            $pRow = $pStmt->fetch();
            if ($pRow) {
                $newPriorityId = (int) $pRow['id'];
            }
        }
    }

    // ── Guard: skip closed tickets unless an admin/agent is re-opening it ─────
    if ($ticket['status'] === 'closed' && $newStatus !== 'open') {
        logMsg('WARN', "  Ticket #{$ticketId} is closed, skipping reply.");
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    // ── Apply status change ───────────────────────────────────────────────────
    if ($newStatus !== null && $newStatus !== $ticket['status']) {
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')
           ->execute([$newStatus, $ticketId]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$ticketId, $user['id'], 'status_changed', $newStatus]);
        logMsg('INFO', "  Status changed to '{$newStatus}' via email command.");
    }

    // ── Apply priority change ─────────────────────────────────────────────────
    if ($newPriorityId !== null) {
        $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')
           ->execute([$newPriorityId, $ticketId]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$ticketId, $user['id'], 'priority_changed', $parsed['priority_slug']]);
        logMsg('INFO', "  Priority changed to '{$parsed['priority_slug']}' via email command.");
    }

    // ── Insert timeline comment (skip if body is empty after command stripping) ─
    if (trim($body) !== '') {
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$ticketId, $user['id'], 'comment', $body]);
        logMsg('INFO', "  Added reply from {$fromAddr} to Ticket #{$ticketId} ✓");
    } elseif ($newStatus !== null || $newPriorityId !== null) {
        logMsg('INFO', "  Commands-only email from {$fromAddr} on Ticket #{$ticketId} — no comment body.");
    } else {
        logMsg('WARN', '  Reply body is empty after stripping quoted text, skipping.');
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    markMessageRead($accessToken, $mailbox, $msgId);
    $processed++;
}

logMsg('INFO', "Done. Processed: {$processed}, Skipped: {$skipped}.");
exit(0);

// ─── Helper functions ─────────────────────────────────────────────────────────

/**
 * Detect automated / out-of-office replies that should not create tickets.
 *
 * Checks:
 *  1. Auto-Submitted internet message header (RFC 3834) — any value other than "no"
 *  2. X-Auto-Response-Suppress header presence (Exchange / Outlook OOO)
 *  3. Precedence: bulk|auto_reply|list
 *  4. Common OOO / autoresponder subject keywords
 */
function isAutoReply(array $msg): bool
{
    $headers = $msg['internetMessageHeaders'] ?? [];
    foreach ($headers as $header) {
        $name  = strtolower(trim($header['name'] ?? ''));
        $value = strtolower(trim($header['value'] ?? ''));

        // RFC 3834 — any value other than "no" means it is automated
        if ($name === 'auto-submitted' && $value !== 'no') {
            return true;
        }

        // Exchange / Outlook out-of-office suppression header
        if ($name === 'x-auto-response-suppress') {
            return true;
        }

        // Mailing list / bulk senders
        if ($name === 'precedence' && in_array($value, ['bulk', 'auto_reply', 'list'], true)) {
            return true;
        }
    }

    // Subject-line heuristics as a last resort
    $subject = strtolower($msg['subject'] ?? '');
    $keywords = [
        'out of office',
        'automatic reply',
        'auto-reply',
        'auto reply',
        'autoreply',
        'vacation reply',
        'away from office',
        'on vacation',
        'autosvar',     // Swedish/Norwegian
        'abwesend',     // German
        'hors du bureau', // French
        'fuera de la oficina', // Spanish
    ];

    foreach ($keywords as $kw) {
        if (str_contains($subject, $kw)) {
            return true;
        }
    }

    return false;
}

/**
 * Did this inbound message fail sender authentication (a spoofed From)?
 *
 * Exchange Online stamps an `Authentication-Results` header on every message it
 * accepts from outside the organization, recording the SPF / DKIM / DMARC and
 * composite-auth (compauth) verdicts. An external attacker cannot strip or forge
 * that header — it is added by the service at the trust boundary, after the
 * sender no longer controls the message. So a message whose Authentication-
 * Results shows an explicit failing DMARC or compauth verdict is a spoof.
 *
 * Returns true ONLY on an explicit failure verdict. Missing header (intra-org
 * mail, which EXO does not stamp) and pass verdicts both return false, so
 * legitimate internal staff mail and DMARC-passing external replies are never
 * blocked. Forwarded mail that fails SPF but passes DMARC is likewise allowed.
 */
function messageAuthFailed(array $msg): bool
{
    foreach (($msg['internetMessageHeaders'] ?? []) as $header) {
        if (strtolower(trim($header['name'] ?? '')) !== 'authentication-results') {
            continue;
        }
        $value = strtolower((string) ($header['value'] ?? ''));
        // dmarc=fail / dmarc=quarantine / dmarc=reject, or compauth=fail, are
        // authoritative "this From is not who it claims" signals from EXO.
        if (preg_match('/\bdmarc=(fail|quarantine|reject)\b/', $value)
            || preg_match('/\bcompauth=fail\b/', $value)) {
            return true;
        }
    }
    return false;
}

/**
 * Recover a ticket ID from a reply's threading headers.
 *
 * Outbound ticket mail (sendMail) uses a Message-ID of the form
 * <ticket-{id}-{timestamp}@domain>. RFC 5322-compliant clients echo that ID
 * into In-Reply-To and References when replying, so we can pull the ticket ID
 * back out even when the "[#id]" subject token has been stripped or altered.
 *
 * Returns the ticket ID, or null if no ticket-stamped Message-ID is present.
 */
function extractTicketIdFromHeaders(array $msg): ?int
{
    $headers = $msg['internetMessageHeaders'] ?? [];
    foreach ($headers as $header) {
        $name = strtolower(trim($header['name'] ?? ''));
        if ($name !== 'in-reply-to' && $name !== 'references') {
            continue;
        }
        // References can list several IDs; take the last ticket-stamped one,
        // which corresponds to the most recent message in the thread.
        if (preg_match_all('/ticket-(\d+)-\d+@/i', (string) ($header['value'] ?? ''), $m)) {
            return (int) end($m[1]);
        }
    }
    return null;
}

/**
 * Split a display name / email address into [first_name, last_name].
 */
function parseEmailName(string $fromName, string $fromAddr): array
{
    $name = trim($fromName);
    // If the display name is just the email address, use the local part
    if (strtolower($name) === strtolower($fromAddr) || $name === '') {
        $name = strstr($fromAddr, '@', true);
    }
    if (str_contains($name, ' ')) {
        $parts = explode(' ', $name, 2);
        return [trim($parts[0]), trim($parts[1])];
    }
    return [$name !== '' ? $name : 'Unknown', ''];
}

/**
 * Fetch unread messages from the mailbox via Graph API.
 * Returns an array of message objects, or null on failure.
 */
function getUnreadMessages(string $token, string $mailbox): ?array
{
    // Use the mailbox address directly — @ is valid in a URL path segment.
    // OData query parameter values with spaces must be percent-encoded.
    $url = 'https://graph.microsoft.com/v1.0/users/' . $mailbox
         . '/mailFolders/Inbox/messages'
         . '?$filter=isRead%20eq%20false'
         . '&$select=id,subject,from,body,bodyPreview,internetMessageHeaders'
         . '&$top=50'
         . '&$orderby=receivedDateTime%20asc';

    $response = curlGet($url, ["Authorization: Bearer {$token}"]);
    if ($response === null) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['value']) || !is_array($data['value'])) {
        logMsg('ERROR', 'Unexpected Graph API response: ' . substr($response, 0, 300));
        return null;
    }

    return $data['value'];
}

/**
 * Mark a message as read via Graph API PATCH.
 */
function markMessageRead(string $token, string $mailbox, string $messageId): void
{
    // @ is valid in the URL path; message IDs may contain + and = which are safe to encode.
    $url = 'https://graph.microsoft.com/v1.0/users/' . $mailbox . '/messages/' . rawurlencode($messageId);

    curlPatch($url, json_encode(['isRead' => true]), [
        "Authorization: Bearer {$token}",
        'Content-Type: application/json',
    ]);
}

/**
 * Extract plain-text body from a Graph API message object.
 * Prefers text/plain; falls back to stripping HTML.
 */
function extractGraphBody(array $msg): string
{
    $contentType = strtolower($msg['body']['contentType'] ?? 'text');
    $content     = $msg['body']['content'] ?? '';

    if ($contentType === 'html') {
        // Convert <br> and <p> to newlines before stripping tags
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/p>/i', "\n\n", $content);
        $content = strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return $content;
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

    // Patterns that signal the start of the quoted original or a signature block
    $stopPatterns = [
        '/^>/',                                        // Quoted line
        '/^On\s.+\bwrote\s*:\s*$/is',                 // "On Mon, 1 Jan wrote:"
        '/^-{3,}\s*Original Message\s*-{3,}/i',       // -----Original Message-----
        '/^-{3,}\s*Forwarded\s*-{3,}/i',
        '/^_{5,}$/',                                   // ____________
        '/^From:\s+.+/i',                              // From: header in a reply block
        '/^--\s*$/',                                   // RFC 3676 email signature delimiter (-- or "-- ")
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

