<?php
/**
 * OpenHelpDesk — Shared Microsoft Graph API helpers
 *
 * Transport (cURL), OAuth2 client-credentials token acquisition, and the
 * read-only Graph calls shared by more than one CLI script:
 *
 *   - scripts/process-replies.php       (inbound email → ticket replies)
 *   - scripts/process-oof-coverage.php  (out-of-office reassign / auto-reply)
 *
 * Each script defines its own logMsg() before requiring this file; the
 * function_exists guard below is only a fallback for any other caller.
 *
 * Requires the PHP cURL extension.
 */

declare(strict_types=1);

if (!function_exists('logMsg')) {
    function logMsg(string $level, string $msg): void
    {
        error_log('[' . $level . '] ' . $msg);
    }
}

/**
 * Perform an HTTP GET request with cURL. Returns the body, or null on
 * transport error / HTTP >= 400.
 *
 * $statusCode is set to the HTTP status (0 on transport failure) so callers
 * can branch on it. Pass $logErrors = false to suppress the built-in ERROR
 * log when the caller wants to classify the failure itself (e.g. treat a 404
 * as benign while still surfacing a 403).
 */
function curlGet(string $url, array $headers = [], ?int &$statusCode = null, bool $logErrors = true): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '') {
        if ($logErrors) {
            logMsg('ERROR', "cURL GET error: {$error}");
        }
        return null;
    }

    if ($statusCode >= 400) {
        if ($logErrors) {
            logMsg('ERROR', "Graph API GET returned HTTP {$statusCode}: " . substr((string) $response, 0, 300));
        }
        return null;
    }

    return (string) $response;
}

/**
 * Perform an HTTP POST request with cURL. Returns the body, or null on
 * transport error / HTTP >= 400.
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
 * Perform an HTTP PATCH request with cURL. Fire-and-forget (logs failures).
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

/**
 * Request an OAuth2 access token using the client-credentials grant.
 * Returns the bearer token, or null on failure.
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
 * Read a mailbox's Outlook automatic-replies (out-of-office) setting.
 *
 *   GET /users/{mailbox}/mailboxSettings/automaticRepliesSetting
 *
 * Requires the application permission `MailboxSettings.Read` on the Azure app
 * registration (admin consent). Returns the decoded automaticRepliesSetting
 * object, or null on error. Shape:
 *   [
 *     'status' => 'disabled'|'alwaysEnabled'|'scheduled',
 *     'externalAudience' => 'none'|'contactsOnly'|'all',
 *     'scheduledStartDateTime' => ['dateTime' => '...', 'timeZone' => 'UTC'],
 *     'scheduledEndDateTime'   => ['dateTime' => '...', 'timeZone' => 'UTC'],
 *     'internalReplyMessage'   => '<html>',
 *     'externalReplyMessage'   => '<html>',
 *   ]
 */
function getAutomaticReplies(string $token, string $mailbox, ?int &$statusCode = null): ?array
{
    // @ is valid in a URL path segment. Request UTC so the dateTime strings are
    // unambiguous regardless of the mailbox owner's working-hours time zone.
    $url = 'https://graph.microsoft.com/v1.0/users/' . $mailbox
         . '/mailboxSettings/automaticRepliesSetting';

    // Suppress curlGet's generic ERROR log so we can classify failures here: a
    // 404 (no readable Exchange Online mailbox) is benign, but a 403 (consent
    // missing) or 5xx should still be surfaced loudly.
    $response = curlGet($url, [
        "Authorization: Bearer {$token}",
        'Prefer: outlook.timezone="UTC"',
    ], $statusCode, false);

    if ($response === null) {
        if ($statusCode !== 404) {
            logMsg('ERROR', "automaticRepliesSetting for {$mailbox} failed (HTTP {$statusCode}).");
        }
        return null; // caller inspects $statusCode (404 = no mailbox, skip quietly)
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['status'])) {
        logMsg('ERROR', 'Unexpected automaticRepliesSetting response for ' . $mailbox . ': ' . substr($response, 0, 300));
        return null;
    }

    return $data;
}
