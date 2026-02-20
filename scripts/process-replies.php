<?php
/**
 * LocalDesk — Inbound Email Reply Processor (Microsoft Graph API)
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

require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';

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

    // ── Extract ticket ID from subject ────────────────────────────────────────
    $ticketId = null;
    if (preg_match('/\[Ticket\s*#(\d+)\]/i', $subject, $m)) {
        $ticketId = (int) $m[1];
    }

    if ($ticketId === null) {
        logMsg('WARN', '  No ticket ID found in subject, skipping.');
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    // ── Verify the ticket exists and is not closed ────────────────────────────
    $ticketStmt = $db->prepare('SELECT id, subject, status, created_by FROM tickets WHERE id = ?');
    $ticketStmt->execute([$ticketId]);
    $ticket = $ticketStmt->fetch();

    if (!$ticket) {
        logMsg('WARN', "  Ticket #{$ticketId} not found, skipping.");
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    if ($ticket['status'] === 'closed') {
        logMsg('WARN', "  Ticket #{$ticketId} is closed, skipping reply.");
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    // ── Find the sender in the users table ────────────────────────────────────
    $userStmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE LOWER(email) = ?');
    $userStmt->execute([$fromAddr]);
    $user = $userStmt->fetch();

    if (!$user) {
        logMsg('WARN', "  Sender {$fromAddr} is not a registered user, skipping.");
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    // ── Extract the reply body ────────────────────────────────────────────────
    $rawBody = extractGraphBody($msg);
    $body    = extractReplyBody($rawBody);

    if (trim($body) === '') {
        logMsg('WARN', '  Reply body is empty after stripping quoted text, skipping.');
        markMessageRead($accessToken, $mailbox, $msgId);
        $skipped++;
        continue;
    }

    // ── Insert timeline comment ───────────────────────────────────────────────
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
    )->execute([$ticketId, $user['id'], 'comment', $body]);

    logMsg('INFO', "  Added reply from {$fromAddr} to Ticket #{$ticketId} ✓");

    markMessageRead($accessToken, $mailbox, $msgId);
    $processed++;
}

logMsg('INFO', "Done. Processed: {$processed}, Skipped: {$skipped}.");
exit(0);

// ─── Helper functions ─────────────────────────────────────────────────────────

/**
 * Request an OAuth2 access token using the Client Credentials grant.
 */
function getAccessToken(string $tenantId, string $clientId, string $clientSecret): ?string
{
    $url  = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
    $body = http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'scope'         => 'https://graph.microsoft.com/.default',
    ]);

    $response = curlPost($url, $body, ['Content-Type: application/x-www-form-urlencoded']);
    if ($response === null) {
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
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
         . '&$select=id,subject,from,body,bodyPreview'
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
 * Perform an HTTP GET request with cURL.
 */
function curlGet(string $url, array $headers = []): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '') {
        logMsg('ERROR', "cURL GET error: {$error}");
        return null;
    }

    if ($httpCode >= 400) {
        logMsg('ERROR', "Graph API GET returned HTTP {$httpCode}: " . substr((string) $response, 0, 300));
        return null;
    }

    return (string) $response;
}

/**
 * Perform an HTTP POST request with cURL.
 */
function curlPost(string $url, string $body, array $headers = []): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '') {
        logMsg('ERROR', "cURL POST error: {$error}");
        return null;
    }

    if ($httpCode >= 400) {
        logMsg('ERROR', "Token endpoint returned HTTP {$httpCode}: " . substr((string) $response, 0, 300));
        return null;
    }

    return (string) $response;
}

/**
 * Perform an HTTP PATCH request with cURL.
 */
function curlPatch(string $url, string $body, array $headers = []): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '') {
        logMsg('WARN', "cURL PATCH error: {$error}");
    } elseif ($httpCode >= 400) {
        logMsg('WARN', "Graph API PATCH returned HTTP {$httpCode}: " . substr((string) $response, 0, 200));
    }
}
