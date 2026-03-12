<?php

declare(strict_types=1);

/**
 * Load environment variables from a .env file.
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        if (preg_match('/^"(.*)"$/s', $value, $m)) {
            $value = $m[1];
        } elseif (preg_match("/^'(.*)'$/s", $value, $m)) {
            $value = $m[1];
        }

        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? (getenv($key) ?: $default);
}

/**
 * Return the application base URL with the correct scheme.
 * Detects HTTPS from the live request (including reverse-proxy headers)
 * so the value is accurate even when APP_URL still says http://.
 */
function appUrl(): string
{
    // Detect scheme from the current request
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL']   ?? '') === 'on');

    $scheme = $isHttps ? 'https' : 'http';

    // If APP_URL is configured, just fix its scheme and return it
    $appUrl = env('APP_URL', '');
    if ($appUrl !== '') {
        return preg_replace('#^https?://#', $scheme . '://', rtrim($appUrl, '/'));
    }

    // Fall back to building the URL from server variables
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function redirect(string $url, int $status = 302): never
{
    header("Location: {$url}", true, $status);
    exit;
}

function render(string $view, array $data = []): never
{
    // Defaults
    $layout       = 'base';
    $pageTitle    = 'LocalDesk';
    $breadcrumbs  = [];
    $sidebarItems = [];
    $error        = '';
    $email        = '';

    // Caller overrides
    extract($data);

    // Always available
    $user = Auth::user();

    // Capture the page content
    ob_start();
    require ROOT_DIR . '/templates/pages/' . $view . '.php';
    $content = ob_get_clean();

    // Clear old-input after template has consumed it
    unset($_SESSION['_old_input']);

    // Render within layout
    require ROOT_DIR . '/templates/layouts/' . $layout . '.php';
    exit;
}

/* ── CSRF ─────────────────────────────────────────────────────── */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(?string $token): bool
{
    if ($token === null) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/* ── Output helpers ───────────────────────────────────────────── */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function linkify(?string $value): string
{
    $escaped = htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return preg_replace(
        '/(https?:\/\/[^\s\'"<>]+)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        $escaped
    );
}

function currentPath(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
}

function isActive(string $path): bool
{
    $current = currentPath();
    return $current === $path || str_starts_with($current, $path . '/');
}

/* ── Flash messages ───────────────────────────────────────────── */

function flash(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function getFlash(string $key): ?string
{
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function flashInput(array $data): void
{
    // Strip password fields for safety
    unset($data['password'], $data['password_confirmation'], $data['_token']);
    $_SESSION['_old_input'] = $data;
}

function old(string $key, string $default = ''): string
{
    return (string) ($_SESSION['_old_input'][$key] ?? $default);
}

/* ── Notification helpers ────────────────────────────────────── */

function notificationCount(): int
{
    if (!Auth::check()) {
        return 0;
    }
    static $count = null;
    if ($count === null) {
        $stmt = Database::connect()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([Auth::id()]);
        $count = (int) $stmt->fetchColumn();
    }
    return $count;
}

/**
 * Parse @mentions from a message and create notifications.
 * Matches "@FirstName LastName" against agents/admins in the database.
 */
function processAtMentions(PDO $db, string $message, int $ticketId, int $timelineId, int $mentionedBy): void
{
    $agents = $db->query("SELECT id, first_name, last_name FROM users WHERE role IN ('agent','admin')")->fetchAll();
    foreach ($agents as $agent) {
        $fullName = $agent['first_name'] . ' ' . $agent['last_name'];
        if (stripos($message, '@' . $fullName) !== false && (int) $agent['id'] !== $mentionedBy) {
            $db->prepare('INSERT INTO notifications (user_id, ticket_id, timeline_id, mentioned_by) VALUES (?, ?, ?, ?)')
                ->execute([$agent['id'], $ticketId, $timelineId, $mentionedBy]);
        }
    }
}

/* ── Attachment helpers ───────────────────────────────────────── */

function handleAttachmentUploads(string $fieldName = 'attachments'): array
{
    if (empty($_FILES[$fieldName]['tmp_name'])) {
        return [];
    }

    $attachments = [];
    $files = $_FILES[$fieldName];
    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;

    for ($i = 0; $i < $count; $i++) {
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $error   = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
        $name    = is_array($files['name'])      ? $files['name'][$i]    : $files['name'];
        $size    = is_array($files['size'])      ? $files['size'][$i]    : $files['size'];

        if (empty($tmpName) || $error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            flash('error', "Upload error for file: " . e($name));
            continue;
        }

        $mime = mime_content_type($tmpName);
        if (!in_array($mime, UPLOAD_ALLOWED_TYPES, true)) {
            flash('error', "File type not allowed: " . e($name));
            continue;
        }
        if ($size > UPLOAD_MAX_SIZE) {
            $maxMB = UPLOAD_MAX_SIZE / 1024 / 1024;
            flash('error', "File too large (max {$maxMB}MB): " . e($name));
            continue;
        }

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $storedName = uniqid('att_', true) . '.' . strtolower($ext);

        if (!is_dir(ATTACHMENT_STORAGE_PATH)) {
            mkdir(ATTACHMENT_STORAGE_PATH, 0755, true);
        }

        if (move_uploaded_file($tmpName, ATTACHMENT_STORAGE_PATH . $storedName)) {
            $attachments[] = [
                'original_name' => $name,
                'stored_name'   => $storedName,
                'mime_type'     => $mime,
                'file_size'     => $size,
            ];
        }
    }

    return $attachments;
}

function saveAttachments(PDO $db, int $ticketId, ?int $timelineId, int $uploadedBy, array $attachments): void
{
    if (empty($attachments)) {
        return;
    }
    $stmt = $db->prepare(
        'INSERT INTO ticket_attachments (ticket_id, timeline_id, uploaded_by, original_name, stored_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($attachments as $att) {
        $stmt->execute([$ticketId, $timelineId, $uploadedBy, $att['original_name'], $att['stored_name'], $att['mime_type'], $att['file_size']]);
    }
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

function getFileIcon(string $mimeType): string
{
    if (str_starts_with($mimeType, 'image/'))       return 'bi-file-image text-primary';
    if ($mimeType === 'application/pdf')             return 'bi-file-pdf text-danger';
    if (str_contains($mimeType, 'word'))             return 'bi-file-word text-primary';
    if (str_contains($mimeType, 'excel') || str_contains($mimeType, 'sheet')) return 'bi-file-excel text-success';
    if (str_starts_with($mimeType, 'text/'))         return 'bi-file-text text-secondary';
    if (str_contains($mimeType, 'zip'))              return 'bi-file-zip text-warning';
    return 'bi-file-earmark text-muted';
}

/* ── Markdown / slug helpers ──────────────────────────────────── */

function renderMarkdown(string $content): string
{
    // Content saved by the WYSIWYG editor is already HTML — return as-is.
    $trimmed = ltrim($content);
    if ($trimmed !== '' && $trimmed[0] === '<') {
        return $content;
    }

    // Legacy markdown content — convert to HTML.
    $config = [
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
    ];

    $environment = new \League\CommonMark\Environment\Environment($config);
    $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
    $environment->addExtension(new \League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension());

    $converter = new \League\CommonMark\MarkdownConverter($environment);

    return $converter->convert($content)->getContent();
}

function slugify(string $text): string
{
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?: $text;
    }
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/* ── Settings helpers ─────────────────────────────────────────── */

function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = Database::connect()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    $cache[$key] = $value !== false ? (string) $value : $default;
    return $cache[$key];
}

function setSetting(string $key, string $value): void
{
    Database::connect()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}

/* ── Timezone helpers ─────────────────────────────────────────── */

/**
 * Returns the effective PHP timezone identifier for a given location.
 *
 * Respects the location_timezone_mode setting:
 *   'shared'       — all locations use the location_timezone_shared value
 *   'per_location' — each location has its own timezone column
 *
 * Falls back to UTC when nothing is configured.
 *
 * Future: when geo-API auto-detection is added, update this function
 * to call the API and cache the result in locations.timezone.
 */
function getLocationTimezone(?int $locationId): string
{
    $mode = getSetting('location_timezone_mode', 'shared');
    if ($mode === 'shared') {
        $tz = getSetting('location_timezone_shared', 'UTC');
        return $tz !== '' ? $tz : 'UTC';
    }
    if ($locationId === null) {
        return 'UTC';
    }
    static $cache = [];
    if (!isset($cache[$locationId])) {
        $stmt = Database::connect()->prepare('SELECT timezone FROM locations WHERE id = ?');
        $stmt->execute([$locationId]);
        $tz = $stmt->fetchColumn();
        $cache[$locationId] = ($tz && $tz !== '') ? $tz : 'UTC';
    }
    return $cache[$locationId];
}

/**
 * Format a UTC datetime string in a given PHP timezone.
 */
function formatInTimezone(?string $utcDatetime, string $timezone, string $format = 'M j, Y g:i a'): string
{
    if ($utcDatetime === null || $utcDatetime === '') {
        return '';
    }
    try {
        $dt = new DateTime($utcDatetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($timezone));
        return $dt->format($format);
    } catch (\Exception $e) {
        return date($format, strtotime($utcDatetime));
    }
}

/**
 * Returns the list of common timezone identifiers used across the app's
 * timezone selectors. Centralised here so all selectors stay in sync.
 */
function commonTimezones(): array
{
    return [
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'America/Toronto',
        'America/Vancouver',
        'America/Winnipeg',
        'America/Halifax',
        'America/St_Johns',
        'America/Edmonton',
        'America/Regina',
        'Europe/London',
        'Europe/Paris',
        'Europe/Berlin',
        'Europe/Amsterdam',
        'Australia/Sydney',
        'Australia/Melbourne',
        'Asia/Tokyo',
        'Asia/Shanghai',
        'Asia/Kolkata',
        'Pacific/Auckland',
        'UTC',
    ];
}

/* ── Label helpers ────────────────────────────────────────────── */

function label(string $key, string $default = ''): string
{
    static $labels = null;
    if ($labels === null) {
        $defaultFile = ROOT_DIR . '/config/labels.default.json';
        $defaults    = is_file($defaultFile)
            ? (json_decode(file_get_contents($defaultFile), true) ?: [])
            : [];
        $custom      = json_decode(getSetting('custom_labels', '{}'), true) ?: [];
        $labels      = array_merge($defaults, $custom);
    }
    return $labels[$key] ?? ($default !== '' ? $default : $key);
}

/* ── User column preference helpers ──────────────────────────── */

/**
 * All toggleable ticket columns (id and subject are always shown).
 */
function ticketColumnDefinitions(): array
{
    return [
        'status'     => 'Status',
        'priority'   => 'Priority',
        'type'       => 'Type',
        'agent'      => 'Assigned To',
        'group'      => 'Group',
        'creator'    => 'Created By',
        'location'   => 'Location',
        'sla'        => 'SLA',
        'created_at' => 'Created',
        'due_date'   => 'Due',
    ];
}

function getUserColumns(int $userId): array
{
    $json = getSetting("ticket_columns:{$userId}", '');
    if ($json === '') {
        return array_keys(ticketColumnDefinitions());
    }
    $cols = json_decode($json, true);
    return is_array($cols) ? $cols : array_keys(ticketColumnDefinitions());
}

function setUserColumns(int $userId, array $columns): void
{
    $valid = array_keys(ticketColumnDefinitions());
    $columns = array_values(array_intersect($columns, $valid));
    setSetting("ticket_columns:{$userId}", json_encode($columns));
}

/* ── Process helpers ─────────────────────────────────────────── */

/**
 * Resolve a usable PHP CLI binary path.
 *
 * PHP_BINARY is empty (or points to the FPM daemon) when PHP runs under
 * PHP-FPM. Fall back to searching PATH for a CLI php binary.
 */
function phpBinary(): string
{
    $bin = PHP_BINARY;
    if ($bin !== '' && is_executable($bin) && !str_contains(basename($bin), 'fpm')) {
        return $bin;
    }
    // Search common names in PATH
    foreach (['php', 'php8.3', 'php8.2', 'php8.1', 'php8.0'] as $name) {
        $found = trim((string) shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null'));
        if ($found !== '' && is_executable($found)) {
            return $found;
        }
    }
    return 'php'; // last resort — rely on PATH at exec time
}

/* ── Email helpers ────────────────────────────────────────────── */

/**
 * Send an email using the configured SMTP settings.
 *
 * @param string      $toEmail   Recipient email address
 * @param string      $toName    Recipient display name
 * @param string      $subject   Email subject line
 * @param string      $htmlBody  HTML body
 * @param string      $textBody  Plain-text fallback body
 * @param int|null    $ticketId  If set, generates a ticket-specific Message-ID and X-Ticket-ID header
 * @return string|false  The Message-ID on success, false on failure
 */

/**
 * Render user-supplied content safely inside an HTML email.
 *
 * - If the content already contains HTML tags (e.g. from CKEditor rich-text),
 *   it is returned as-is so that formatting renders correctly in the email.
 * - If the content is plain text (e.g. from a <textarea> or inbound email),
 *   HTML special characters are escaped and newlines are converted to <br>.
 */
function emailContent(string $content): string
{
    if (strip_tags($content) !== $content) {
        // Contains HTML markup — output as-is (rich text from editor)
        return $content;
    }
    // Plain text — escape entities and preserve line breaks
    return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
}

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = '', ?int $ticketId = null): string|false
{
    $host = getSetting('smtp_host');
    if ($host === '') {
        return false; // SMTP not configured — silently skip
    }

    $port       = (int) getSetting('smtp_port', '587');
    $encryption = getSetting('smtp_encryption', 'tls');
    $username   = getSetting('smtp_username');
    $password   = getSetting('smtp_password');
    $fromAddr   = getSetting('mail_from_address');
    $fromName   = getSetting('mail_from_name', 'LocalDesk');

    if ($fromAddr === '') {
        return false;
    }

    // Build a Message-ID for threading
    $domain    = substr(strrchr($fromAddr, '@'), 1) ?: 'localhost';
    $messageId = $ticketId !== null
        ? sprintf('<ticket-%d-%d@%s>', $ticketId, time(), $domain)
        : sprintf('<localdesk-%s@%s>', bin2hex(random_bytes(12)), $domain);

    // SMTP debug log — captures full SMTP conversation to storage/logs/smtp.log
    $smtpLogDir  = ROOT_DIR . '/storage/logs';
    $smtpLogFile = $smtpLogDir . '/smtp.log';
    if (!is_dir($smtpLogDir)) {
        mkdir($smtpLogDir, 0755, true);
    }
    $smtpDebugEnabled = getSetting('smtp_debug', '0') === '1';
    $logEntry = sprintf("[%s] sendMail() to=%s host=%s port=%d enc=%s\n",
        date('Y-m-d H:i:s'), $toEmail, $host, $port, $encryption);
    file_put_contents($smtpLogFile, $logEntry, FILE_APPEND | LOCK_EX);

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->CharSet    = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->Timeout    = 15; // seconds — prevents indefinite hangs on unreachable hosts
        $mail->SMTPSecure = $encryption === 'none' ? '' : $encryption;
        if ($username !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
        }

        if ($smtpDebugEnabled) {
            $mail->SMTPDebug  = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function (string $str, int $level) use ($smtpLogFile): void {
                file_put_contents($smtpLogFile,
                    '[' . date('H:i:s') . '][L' . $level . '] ' . trim($str) . "\n",
                    FILE_APPEND | LOCK_EX);
            };
        }

        $mail->setFrom($fromAddr, $fromName);
        // If inbound reply processing is enabled and this is a ticket email,
        // set Reply-To to the dedicated reply inbox so replies are captured.
        $replyToAddr = ($ticketId !== null && getSetting('graph_enabled') === '1')
            ? getSetting('graph_reply_to')
            : '';
        $mail->addReplyTo($replyToAddr !== '' ? $replyToAddr : $fromAddr, $fromName);
        $mail->addAddress($toEmail, $toName);

        $mail->MessageID = $messageId;
        if ($ticketId !== null) {
            $mail->addCustomHeader('X-Ticket-ID', (string) $ticketId);
        }

        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        $mail->send();
        file_put_contents($smtpLogFile,
            '[' . date('H:i:s') . '] SUCCESS messageId=' . $messageId . "\n",
            FILE_APPEND | LOCK_EX);
        return $messageId;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $err = '[' . date('H:i:s') . '] ERROR ' . $mail->ErrorInfo . "\n";
        file_put_contents($smtpLogFile, $err, FILE_APPEND | LOCK_EX);
        error_log('LocalDesk mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Replace {{token}} placeholders in a string with values from the given map.
 */
function applyEmailTokens(string $template, array $tokens): string
{
    foreach ($tokens as $key => $value) {
        $template = str_replace('{{' . $key . '}}', (string) $value, $template);
    }
    return $template;
}

/**
 * Resolve the customisable parts of an outgoing email (subject, intro text,
 * button label, footer text) from admin settings, falling back to hard-coded
 * defaults. Token values are HTML-escaped when placed into HTML fields.
 *
 * @param string $name       Template slug: 'ticket-created', 'ticket-updated', or 'ticket-merged'
 * @param array  $rawTokens  Raw (unescaped) values keyed by token name (without braces)
 */
function getEmailTpl(string $name, array $rawTokens): array
{
    $key = str_replace('-', '_', $name); // e.g. 'ticket-created' → 'ticket_created'

    $defaults = [
        'ticket_created' => [
            'subject' => '[Ticket #{{ticket_id}}] {{subject}}',
            'intro'   => 'Your ticket has been created and our team will review it shortly.',
            'button'  => 'View Ticket',
        ],
        'ticket_updated' => [
            'subject' => '[Ticket #{{ticket_id}}] Update: {{subject}}',
            'intro'   => 'Your ticket has been updated.',
            'button'  => 'View Ticket',
        ],
        'ticket_merged' => [
            'subject' => '[Ticket #{{source_ticket_id}}] Your ticket has been merged',
            'intro'   => 'Ticket #{{source_ticket_id}} has been consolidated with a related ticket. You can view updates and add comments on the master ticket.',
            'button'  => 'View Master Ticket',
        ],
        'csat_survey' => [
            'subject' => 'How did we do? — [Ticket #{{ticket_id}}] {{subject}}',
            'intro'   => 'Your ticket has been resolved. We\'d love to hear how we did — it only takes one click!',
        ],
        'ticket_reminder' => [
            'subject' => 'Following up on your ticket [#{{ticket_id}}] {{subject}}',
            'intro'   => 'We\'re still waiting to hear back from you on your support ticket. Please reply with an update so we can continue helping you.',
            'button'  => 'View & Reply',
        ],
        'ticket_new_group' => [
            'subject' => '[Ticket #{{ticket_id}}] New Ticket: {{subject}}',
            'intro'   => 'A new support ticket has been submitted.',
            'button'  => 'View Ticket',
        ],
        'ticket_assigned_agent' => [
            'subject' => '[Ticket #{{ticket_id}}] Assigned to you: {{subject}}',
            'intro'   => 'A ticket has been assigned to you.',
            'button'  => 'View Ticket',
        ],
        'ticket_assigned_group' => [
            'subject' => '[Ticket #{{ticket_id}}] Assigned to your group: {{subject}}',
            'intro'   => 'A ticket has been assigned to your group.',
            'button'  => 'View Ticket',
        ],
        'ticket_status_resolved' => [
            'subject' => '[Ticket #{{ticket_id}}] Resolved: {{subject}}',
            'intro'   => 'Your support ticket has been resolved. If you have further questions, you can reopen it by replying.',
            'button'  => 'View Ticket',
        ],
        'ticket_status_closed' => [
            'subject' => '[Ticket #{{ticket_id}}] Closed: {{subject}}',
            'intro'   => 'Your support ticket has been closed. Thank you for contacting us.',
            'button'  => 'View Ticket',
        ],
    ];

    $d = $defaults[$key] ?? ['subject' => '', 'intro' => '', 'button' => 'View Ticket'];

    $subjectTpl = getSetting("email_subject_{$key}") ?: $d['subject'];
    $introTpl   = getSetting("email_intro_{$key}")   ?: $d['intro'];
    $button     = getSetting("email_button_{$key}")  ?: $d['button'];
    $footer     = getSetting('email_footer_text')    ?: 'This is an automated message from LocalDesk. Please do not reply directly to this email.';

    // Add common token aliases so custom templates using alternative names still work
    $aliases = [
        'ticket_subject'      => $rawTokens['subject']     ?? '',
        'customer_first_name' => $rawTokens['first_name']  ?? '',
        'customer_last_name'  => $rawTokens['last_name']   ?? '',
        'customer_name'       => $rawTokens['user_name']   ?? '',
        'customer_full_name'  => $rawTokens['user_name']   ?? '',
    ];
    $rawTokens = array_merge($aliases, $rawTokens);

    // Subject line: raw substitution (email headers are plain text)
    $subject = applyEmailTokens($subjectTpl, $rawTokens);

    // Intro text: the token VALUES going into HTML must be escaped
    $safeTokens = [];
    foreach ($rawTokens as $k => $v) {
        $safeTokens[$k] = htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
    $intro = applyEmailTokens($introTpl, $safeTokens);

    return [
        'subject' => $subject,
        'intro'   => $intro,  // already HTML-safe
        'button'  => htmlspecialchars($button, ENT_QUOTES, 'UTF-8'),
        'footer'  => htmlspecialchars($footer, ENT_QUOTES, 'UTF-8'),
    ];
}

/**
 * Render an email template and return the HTML string.
 */
function renderEmail(string $template, array $data = []): string
{
    extract($data);
    ob_start();
    require ROOT_DIR . '/templates/emails/' . $template . '.php';
    return ob_get_clean();
}

/**
 * Check whether a notification type is globally enabled by the admin.
 * Defaults to enabled (returns true) if no setting has been saved yet.
 */
function emailNotifyEnabled(string $type): bool
{
    return getSetting('email_notify:' . $type, '1') !== '0';
}

/**
 * Notify the ticket creator that their ticket was updated (comment added).
 * Skips if the updater IS the creator (no self-notifications).
 */
function notifyTicketCreator(PDO $db, int $ticketId, string $message, string $authorName): void
{
    if (!emailNotifyEnabled('requester_agent_comment')) {
        return;
    }
    $stmt = $db->prepare(
        "SELECT t.subject, t.created_by, u.email, u.first_name, u.last_name, u.notify_ticket_updated
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['created_by'] === Auth::id()) {
        return; // ticket not found or updater is the creator
    }
    if (!(bool)($row['notify_ticket_updated'] ?? 1)) {
        return; // user opted out of ticket reply emails
    }

    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $ticketUrl = $appUrl . '/portal/tickets/' . $ticketId;

    $tpl = getEmailTpl('ticket-updated', [
        'ticket_id'  => $ticketId,
        'subject'    => $row['subject'],
        'message'    => $message,
        'author'     => $authorName,
        'user_name'  => $row['first_name'] . ' ' . $row['last_name'],
        'first_name' => $row['first_name'],
        'last_name'  => $row['last_name'],
    ]);

    $emailHtml = renderEmail('ticket-updated', [
        'ticketId'    => $ticketId,
        'subject'     => $row['subject'],
        'message'     => $message,
        'authorName'  => $authorName,
        'ticketUrl'   => $ticketUrl,
        'introText'   => $tpl['intro'],
        'buttonLabel' => $tpl['button'],
        'footerText'  => $tpl['footer'],
    ]);

    sendMail(
        $row['email'],
        $row['first_name'] . ' ' . $row['last_name'],
        $tpl['subject'],
        $emailHtml,
        '',
        $ticketId
    );
}

/**
 * Email all CC'd users on a ticket about a new comment.
 * Skips the comment author and the ticket creator (already notified separately).
 */
function notifyCcUsers(PDO $db, int $ticketId, string $message, string $authorName): void
{
    if (!emailNotifyEnabled('cc_note_added')) {
        return;
    }
    // Get ticket subject and creator
    $ticket = $db->prepare('SELECT subject, created_by FROM tickets WHERE id = ?');
    $ticket->execute([$ticketId]);
    $ticketRow = $ticket->fetch();
    if (!$ticketRow) return;

    $creatorId = (int) $ticketRow['created_by'];
    $currentId = Auth::id();
    $appUrl    = env('APP_URL', 'http://localhost:8000');

    // Fetch CC'd users
    $cc = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.notify_ticket_cc
         FROM ticket_cc tc
         JOIN users u ON tc.user_id = u.id
         WHERE tc.ticket_id = ?'
    );
    $cc->execute([$ticketId]);

    foreach ($cc->fetchAll() as $user) {
        $uid = (int) $user['id'];
        if ($uid === $currentId || $uid === $creatorId) continue;
        if (!(bool)($user['notify_ticket_cc'] ?? 1)) continue; // opted out

        // Build the correct URL based on role
        $prefix = match ($user['role']) {
            'admin' => '/admin',
            'agent' => '/agent',
            default => '/portal',
        };
        $ticketUrl = $appUrl . $prefix . '/tickets/' . $ticketId;

        $tpl = getEmailTpl('ticket-updated', [
            'ticket_id'  => $ticketId,
            'subject'    => $ticketRow['subject'],
            'message'    => $message,
            'author'     => $authorName,
            'user_name'  => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
        ]);

        $emailHtml = renderEmail('ticket-updated', [
            'ticketId'    => $ticketId,
            'subject'     => $ticketRow['subject'],
            'message'     => $message,
            'authorName'  => $authorName,
            'ticketUrl'   => $ticketUrl,
            'introText'   => $tpl['intro'],
            'buttonLabel' => $tpl['button'],
            'footerText'  => $tpl['footer'],
        ]);

        sendMail(
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $tpl['subject'],
            $emailHtml,
            '',
            $ticketId
        );
    }
}

/**
 * Email all watchers of a ticket about a new public comment.
 * Skips the comment author, the ticket creator (notified separately),
 * and any CC'd users (notified separately).
 */
function notifyWatchers(PDO $db, int $ticketId, string $message, string $authorName): void
{
    $ticketStmt = $db->prepare('SELECT subject, created_by FROM tickets WHERE id = ?');
    $ticketStmt->execute([$ticketId]);
    $ticketRow = $ticketStmt->fetch();
    if (!$ticketRow) return;

    $creatorId = (int) $ticketRow['created_by'];
    $currentId = Auth::id();

    // Collect CC'd user IDs to avoid double-notifying
    $ccStmt = $db->prepare('SELECT user_id FROM ticket_cc WHERE ticket_id = ?');
    $ccStmt->execute([$ticketId]);
    $ccIdList = array_map('intval', array_column($ccStmt->fetchAll(), 'user_id'));

    $stmt = $db->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.role
         FROM ticket_watchers tw
         JOIN users u ON tw.user_id = u.id
         WHERE tw.ticket_id = ? AND u.role IN ('agent','admin')"
    );
    $stmt->execute([$ticketId]);

    foreach ($stmt->fetchAll() as $user) {
        $uid = (int) $user['id'];
        if ($uid === $currentId || $uid === $creatorId) continue;
        if (in_array($uid, $ccIdList, true)) continue;

        $prefix    = $user['role'] === 'admin' ? '/admin' : '/agent';
        $ticketUrl = appUrl() . $prefix . '/tickets/' . $ticketId;

        $tpl = getEmailTpl('ticket-updated', [
            'ticket_id'  => $ticketId,
            'subject'    => $ticketRow['subject'],
            'message'    => $message,
            'author'     => $authorName,
            'user_name'  => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
        ]);

        $emailHtml = renderEmail('ticket-updated', [
            'ticketId'    => $ticketId,
            'subject'     => $ticketRow['subject'],
            'message'     => $message,
            'authorName'  => $authorName,
            'ticketUrl'   => $ticketUrl,
            'introText'   => $tpl['intro'],
            'buttonLabel' => $tpl['button'],
            'footerText'  => $tpl['footer'],
        ]);

        sendMail(
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $tpl['subject'],
            $emailHtml,
            '',
            $ticketId
        );
    }
}

/**
 * Email all members of groups that have notify_new_ticket enabled when a ticket is created.
 * Skips members who have opted out via notify_group_new_ticket = 0.
 */
function notifyGroupMembers(PDO $db, int $ticketId): void
{
    if (!emailNotifyEnabled('agent_new_ticket')) {
        return;
    }
    // Fetch the ticket
    $tStmt = $db->prepare(
        'SELECT t.subject, t.description, t.type_id, t.location_id, t.priority_id, t.created_by
         FROM tickets t WHERE t.id = ?'
    );
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch();
    if (!$ticket) {
        return;
    }

    // Submitter name
    $uStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $uStmt->execute([$ticket['created_by']]);
    $submitter = $uStmt->fetch();
    $submitterName = $submitter ? trim($submitter['first_name'] . ' ' . $submitter['last_name']) : '';

    // Resolve type / location / priority names
    $typeName = $locationName = $priorityName = '';
    if ($ticket['type_id']) {
        $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
        $s->execute([$ticket['type_id']]);
        $typeName = $s->fetchColumn() ?: '';
    }
    if ($ticket['location_id']) {
        $s = $db->prepare('SELECT name FROM locations WHERE id = ?');
        $s->execute([$ticket['location_id']]);
        $locationName = $s->fetchColumn() ?: '';
    }
    if ($ticket['priority_id']) {
        $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
        $s->execute([$ticket['priority_id']]);
        $priorityName = $s->fetchColumn() ?: '';
    }

    // Find all groups with notify_new_ticket = 1
    $gStmt = $db->query('SELECT id FROM `groups` WHERE notify_new_ticket = 1');
    $groupIds = $gStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($groupIds)) {
        return;
    }

    // Collect unique members across all notified groups
    $members = [];
    foreach ($groupIds as $gid) {
        $mStmt = $db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.notify_group_new_ticket
             FROM group_user_map gum
             JOIN users u ON u.id = gum.user_id
             WHERE gum.group_id = ?'
        );
        $mStmt->execute([$gid]);
        foreach ($mStmt->fetchAll() as $m) {
            $members[(int) $m['id']] = $m; // deduplicate
        }
    }

    $appUrl = env('APP_URL', 'http://localhost:8000');

    foreach ($members as $user) {
        if (!(bool) ($user['notify_group_new_ticket'] ?? 1)) {
            continue;
        }

        $rolePrefix = match ($user['role']) {
            'admin' => '/admin',
            'agent' => '/agent',
            default => '/portal',
        };
        $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

        $tpl = getEmailTpl('ticket-new-group', [
            'ticket_id'  => $ticketId,
            'subject'    => $ticket['subject'],
            'type'       => $typeName,
            'location'   => $locationName,
            'priority'   => $priorityName,
            'submitter'  => $submitterName,
            'user_name'  => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
        ]);

        $emailHtml = renderEmail('ticket-opened-group', [
            'ticketId'      => $ticketId,
            'subject'       => $ticket['subject'],
            'description'   => $ticket['description'],
            'typeName'      => $typeName,
            'locationName'  => $locationName,
            'priorityName'  => $priorityName,
            'submitterName' => $submitterName,
            'ticketUrl'     => $ticketUrl,
            'introText'     => $tpl['intro'],
            'buttonLabel'   => $tpl['button'],
            'footerText'    => $tpl['footer'],
        ]);

        sendMail(
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $tpl['subject'],
            $emailHtml,
            '',
            $ticketId
        );
    }
}

/**
 * Email the agent when a ticket is assigned directly to them.
 */
function notifyAssignedAgent(PDO $db, int $ticketId, int $agentId): void
{
    if (!emailNotifyEnabled('agent_assigned_agent')) {
        return;
    }
    if ($agentId === Auth::id()) {
        return; // self-assignment
    }

    $stmt = $db->prepare(
        'SELECT id, first_name, last_name, email, role, notify_assigned_to_me
         FROM users WHERE id = ?'
    );
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch();
    if (!$agent || !(bool) ($agent['notify_assigned_to_me'] ?? 1)) {
        return;
    }

    $tStmt = $db->prepare(
        'SELECT t.subject, t.description, t.type_id, t.priority_id, t.created_by
         FROM tickets t WHERE t.id = ?'
    );
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch();
    if (!$ticket) {
        return;
    }

    $uStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $uStmt->execute([$ticket['created_by']]);
    $submitter = $uStmt->fetch();
    $submitterName = $submitter ? trim($submitter['first_name'] . ' ' . $submitter['last_name']) : '';

    $typeName = $priorityName = '';
    if ($ticket['type_id']) {
        $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
        $s->execute([$ticket['type_id']]);
        $typeName = $s->fetchColumn() ?: '';
    }
    if ($ticket['priority_id']) {
        $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
        $s->execute([$ticket['priority_id']]);
        $priorityName = $s->fetchColumn() ?: '';
    }

    $rolePrefix = match ($agent['role']) {
        'admin' => '/admin',
        default => '/agent',
    };
    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

    $tpl = getEmailTpl('ticket-assigned-agent', [
        'ticket_id'   => $ticketId,
        'subject'     => $ticket['subject'],
        'type'        => $typeName,
        'priority'    => $priorityName,
        'submitter'   => $submitterName,
        'user_name'   => $agent['first_name'] . ' ' . $agent['last_name'],
        'first_name'  => $agent['first_name'],
        'last_name'   => $agent['last_name'],
    ]);

    $emailHtml = renderEmail('ticket-assigned-agent', [
        'ticketId'      => $ticketId,
        'subject'       => $ticket['subject'],
        'description'   => $ticket['description'],
        'typeName'      => $typeName,
        'priorityName'  => $priorityName,
        'submitterName' => $submitterName,
        'ticketUrl'     => $ticketUrl,
        'introText'     => $tpl['intro'],
        'buttonLabel'   => $tpl['button'],
        'footerText'    => $tpl['footer'],
    ]);

    sendMail(
        $agent['email'],
        $agent['first_name'] . ' ' . $agent['last_name'],
        $tpl['subject'],
        $emailHtml,
        '',
        $ticketId
    );
}

/**
 * Email all members of a group when a ticket is assigned to that group.
 */
function notifyAssignedGroup(PDO $db, int $ticketId, int $groupId): void
{
    if (!emailNotifyEnabled('agent_assigned_group')) {
        return;
    }

    $gStmt = $db->prepare('SELECT name FROM `groups` WHERE id = ?');
    $gStmt->execute([$groupId]);
    $groupName = $gStmt->fetchColumn() ?: '';

    $tStmt = $db->prepare(
        'SELECT t.subject, t.description, t.type_id, t.priority_id, t.created_by
         FROM tickets t WHERE t.id = ?'
    );
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch();
    if (!$ticket) {
        return;
    }

    $uStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $uStmt->execute([$ticket['created_by']]);
    $submitter = $uStmt->fetch();
    $submitterName = $submitter ? trim($submitter['first_name'] . ' ' . $submitter['last_name']) : '';

    $typeName = $priorityName = '';
    if ($ticket['type_id']) {
        $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
        $s->execute([$ticket['type_id']]);
        $typeName = $s->fetchColumn() ?: '';
    }
    if ($ticket['priority_id']) {
        $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
        $s->execute([$ticket['priority_id']]);
        $priorityName = $s->fetchColumn() ?: '';
    }

    $mStmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.notify_assigned_to_group
         FROM group_user_map gum
         JOIN users u ON u.id = gum.user_id
         WHERE gum.group_id = ?'
    );
    $mStmt->execute([$groupId]);
    $appUrl = env('APP_URL', 'http://localhost:8000');

    foreach ($mStmt->fetchAll() as $member) {
        if ((int) $member['id'] === Auth::id()) {
            continue; // skip the person making the change
        }
        if (!(bool) ($member['notify_assigned_to_group'] ?? 1)) {
            continue;
        }

        $rolePrefix = match ($member['role']) {
            'admin' => '/admin',
            default => '/agent',
        };
        $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

        $tpl = getEmailTpl('ticket-assigned-group', [
            'ticket_id'   => $ticketId,
            'subject'     => $ticket['subject'],
            'group'       => $groupName,
            'type'        => $typeName,
            'priority'    => $priorityName,
            'submitter'   => $submitterName,
            'user_name'   => $member['first_name'] . ' ' . $member['last_name'],
            'first_name'  => $member['first_name'],
            'last_name'   => $member['last_name'],
        ]);

        $emailHtml = renderEmail('ticket-assigned-group', [
            'ticketId'      => $ticketId,
            'subject'       => $ticket['subject'],
            'description'   => $ticket['description'],
            'groupName'     => $groupName,
            'typeName'      => $typeName,
            'priorityName'  => $priorityName,
            'submitterName' => $submitterName,
            'ticketUrl'     => $ticketUrl,
            'introText'     => $tpl['intro'],
            'buttonLabel'   => $tpl['button'],
            'footerText'    => $tpl['footer'],
        ]);

        sendMail(
            $member['email'],
            $member['first_name'] . ' ' . $member['last_name'],
            $tpl['subject'],
            $emailHtml,
            '',
            $ticketId
        );
    }
}

/**
 * Email the assigned agent when the ticket requester (portal user) adds a comment.
 */
function notifyAgentRequesterReplied(PDO $db, int $ticketId, string $message): void
{
    if (!emailNotifyEnabled('agent_requester_reply')) {
        return;
    }

    $stmt = $db->prepare(
        'SELECT t.subject, t.assigned_to,
                u.id AS agent_id, u.first_name, u.last_name, u.email, u.role, u.notify_requester_replied
         FROM tickets t
         JOIN users u ON u.id = t.assigned_to
         WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || !(bool) ($row['notify_requester_replied'] ?? 1)) {
        return;
    }

    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $rolePrefix = match ($row['role']) {
        'admin' => '/admin',
        default => '/agent',
    };
    $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

    $tpl = getEmailTpl('ticket-updated', [
        'ticket_id'  => $ticketId,
        'subject'    => $row['subject'],
        'message'    => $message,
        'author'     => 'the requester',
        'user_name'  => $row['first_name'] . ' ' . $row['last_name'],
        'first_name' => $row['first_name'],
        'last_name'  => $row['last_name'],
    ]);

    // Override intro for agent context
    $introText = 'The requester has replied to a ticket assigned to you.';

    $emailHtml = renderEmail('ticket-updated', [
        'ticketId'    => $ticketId,
        'subject'     => $row['subject'],
        'message'     => $message,
        'authorName'  => 'The Requester',
        'ticketUrl'   => $ticketUrl,
        'introText'   => $introText,
        'buttonLabel' => $tpl['button'],
        'footerText'  => $tpl['footer'],
    ]);

    sendMail(
        $row['email'],
        $row['first_name'] . ' ' . $row['last_name'],
        '[Ticket #' . $ticketId . '] Requester replied: ' . $row['subject'],
        $emailHtml,
        '',
        $ticketId
    );
}

/**
 * Email the assigned agent when an internal note is added to their ticket.
 */
function notifyAgentNoteAdded(PDO $db, int $ticketId, string $message): void
{
    if (!emailNotifyEnabled('agent_note_added')) {
        return;
    }

    $stmt = $db->prepare(
        'SELECT t.subject, t.assigned_to,
                u.id AS agent_id, u.first_name, u.last_name, u.email, u.role, u.notify_note_added
         FROM tickets t
         JOIN users u ON u.id = t.assigned_to
         WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['assigned_to'] === Auth::id()) {
        return; // not assigned or self
    }
    if (!(bool) ($row['notify_note_added'] ?? 1)) {
        return;
    }

    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $rolePrefix = match ($row['role']) {
        'admin' => '/admin',
        default => '/agent',
    };
    $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

    $tpl = getEmailTpl('ticket-updated', [
        'ticket_id'  => $ticketId,
        'subject'    => $row['subject'],
        'message'    => $message,
        'author'     => Auth::fullName(),
        'user_name'  => $row['first_name'] . ' ' . $row['last_name'],
        'first_name' => $row['first_name'],
        'last_name'  => $row['last_name'],
    ]);

    $emailHtml = renderEmail('ticket-updated', [
        'ticketId'    => $ticketId,
        'subject'     => $row['subject'],
        'message'     => $message,
        'authorName'  => Auth::fullName(),
        'ticketUrl'   => $ticketUrl,
        'introText'   => 'An internal note was added to a ticket assigned to you.',
        'buttonLabel' => $tpl['button'],
        'footerText'  => $tpl['footer'],
    ]);

    sendMail(
        $row['email'],
        $row['first_name'] . ' ' . $row['last_name'],
        '[Ticket #' . $ticketId . '] Note added: ' . $row['subject'],
        $emailHtml,
        '',
        $ticketId
    );
}

/**
 * Email the ticket requester when their ticket status changes to resolved or closed.
 */
function notifyRequesterStatusChanged(PDO $db, int $ticketId, string $newStatus): void
{
    $settingKey = match ($newStatus) {
        'resolved' => 'requester_ticket_resolved',
        'closed'   => 'requester_ticket_closed',
        default    => null,
    };
    if ($settingKey === null || !emailNotifyEnabled($settingKey)) {
        return;
    }

    $userCol = $newStatus === 'resolved' ? 'notify_ticket_solved' : 'notify_ticket_closed';

    $stmt = $db->prepare(
        "SELECT t.subject, u.id AS user_id, u.email, u.first_name, u.last_name, u.{$userCol} AS opted_in
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || !(bool) ($row['opted_in'] ?? 1)) {
        return;
    }

    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $ticketUrl = $appUrl . '/portal/tickets/' . $ticketId;

    $defaultIntros = [
        'resolved' => 'Your support ticket has been resolved. If you have further questions, you can reopen it by replying.',
        'closed'   => 'Your support ticket has been closed. Thank you for contacting us.',
    ];

    $tpl = getEmailTpl('ticket-status-' . $newStatus, [
        'ticket_id'  => $ticketId,
        'subject'    => $row['subject'],
        'user_name'  => $row['first_name'] . ' ' . $row['last_name'],
        'first_name' => $row['first_name'],
        'last_name'  => $row['last_name'],
    ]);

    $emailHtml = renderEmail('ticket-status-changed', [
        'ticketId'    => $ticketId,
        'subject'     => $row['subject'],
        'newStatus'   => $newStatus,
        'ticketUrl'   => $ticketUrl,
        'introText'   => !empty($tpl['intro']) ? $tpl['intro'] : $defaultIntros[$newStatus],
        'buttonLabel' => !empty($tpl['button']) ? $tpl['button'] : 'View Ticket',
        'footerText'  => $tpl['footer'],
    ]);

    sendMail(
        $row['email'],
        $row['first_name'] . ' ' . $row['last_name'],
        $tpl['subject'] ?: '[Ticket #' . $ticketId . '] ' . $row['subject'],
        $emailHtml,
        '',
        $ticketId
    );
}

/**
 * Email the creator (and CC'd users) of a source ticket that it was merged into a master ticket.
 */
function notifyTicketMerged(PDO $db, int $sourceId, int $targetId): void
{
    // Fetch source ticket and creator
    $srcStmt = $db->prepare(
        "SELECT t.subject, t.created_by, u.email, u.first_name, u.last_name, u.notify_ticket_merged
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE t.id = ?"
    );
    $srcStmt->execute([$sourceId]);
    $src = $srcStmt->fetch();
    if (!$src) return;

    // Fetch target ticket subject
    $tgtStmt = $db->prepare('SELECT subject FROM tickets WHERE id = ?');
    $tgtStmt->execute([$targetId]);
    $tgt = $tgtStmt->fetch();
    if (!$tgt) return;

    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $ticketUrl = $appUrl . '/portal/tickets/' . $targetId;

    $mergedTokensBase = [
        'source_ticket_id' => $sourceId,
        'source_subject'   => $src['subject'],
        'target_ticket_id' => $targetId,
        'target_subject'   => $tgt['subject'],
    ];

    // Notify source ticket creator
    if ((bool)($src['notify_ticket_merged'] ?? 1)) {
        $tpl = getEmailTpl('ticket-merged', array_merge($mergedTokensBase, [
            'user_name'  => $src['first_name'] . ' ' . $src['last_name'],
            'first_name' => $src['first_name'],
            'last_name'  => $src['last_name'],
        ]));

        $emailHtml = renderEmail('ticket-merged', [
            'sourceTicketId' => $sourceId,
            'sourceSubject'  => $src['subject'],
            'targetTicketId' => $targetId,
            'targetSubject'  => $tgt['subject'],
            'ticketUrl'      => $ticketUrl,
            'introText'      => $tpl['intro'],
            'buttonLabel'    => $tpl['button'],
            'footerText'     => $tpl['footer'],
        ]);

        sendMail(
            $src['email'],
            $src['first_name'] . ' ' . $src['last_name'],
            $tpl['subject'],
            $emailHtml,
            '',
            $targetId
        );
    }

    // Notify CC'd users on the source ticket (skip creator)
    $ccStmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.notify_ticket_cc
         FROM ticket_cc tc
         JOIN users u ON tc.user_id = u.id
         WHERE tc.ticket_id = ?'
    );
    $ccStmt->execute([$sourceId]);

    $creatorId = (int) $src['created_by'];
    foreach ($ccStmt->fetchAll() as $user) {
        if ((int) $user['id'] === $creatorId) continue;
        if (!(bool)($user['notify_ticket_cc'] ?? 1)) continue; // opted out

        $prefix = match ($user['role']) {
            'admin' => '/admin',
            'agent' => '/agent',
            default => '/portal',
        };
        $ccTicketUrl = $appUrl . $prefix . '/tickets/' . $targetId;

        $ccTpl = getEmailTpl('ticket-merged', array_merge($mergedTokensBase, [
            'user_name'  => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
        ]));

        $ccHtml = renderEmail('ticket-merged', [
            'sourceTicketId' => $sourceId,
            'sourceSubject'  => $src['subject'],
            'targetTicketId' => $targetId,
            'targetSubject'  => $tgt['subject'],
            'ticketUrl'      => $ccTicketUrl,
            'introText'      => $ccTpl['intro'],
            'buttonLabel'    => $ccTpl['button'],
            'footerText'     => $ccTpl['footer'],
        ]);

        sendMail(
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $ccTpl['subject'],
            $ccHtml,
            '',
            $targetId
        );
    }
}

/* ── Escalation Rules ────────────────────────────────────────── */

/**
 * Evaluate a single escalation rule against a single ticket.
 * Returns true if ALL conditions match, false otherwise.
 */
function evaluateEscalationConditions(\PDO $db, array $conditions, array $ticket): bool
{
    foreach ($conditions as $cond) {
        $field    = $cond['field']    ?? '';
        $operator = $cond['operator'] ?? 'equals';
        $value    = $cond['value']    ?? '';

        switch ($field) {
            case 'sla_state':
                $actual = $ticket['sla_state'] ?? '';
                if ($operator === 'equals'     && $actual !== $value) return false;
                if ($operator === 'not_equals' && $actual === $value) return false;
                break;

            case 'hours_open':
                $stmt = $db->prepare('SELECT TIMESTAMPDIFF(HOUR, created_at, NOW()) FROM tickets WHERE id = ?');
                $stmt->execute([(int) $ticket['id']]);
                $hours = (int) $stmt->fetchColumn();
                if ($operator === 'greater_than' && $hours <= (int) $value) return false;
                break;

            case 'hours_since_update':
                $stmt = $db->prepare('SELECT TIMESTAMPDIFF(HOUR, updated_at, NOW()) FROM tickets WHERE id = ?');
                $stmt->execute([(int) $ticket['id']]);
                $hours = (int) $stmt->fetchColumn();
                if ($operator === 'greater_than' && $hours <= (int) $value) return false;
                break;

            case 'hours_in_status':
                $stmt = $db->prepare(
                    'SELECT TIMESTAMPDIFF(HOUR, created_at, NOW()) FROM ticket_timeline
                     WHERE ticket_id = ? AND action = \'status_changed\'
                     ORDER BY created_at DESC LIMIT 1'
                );
                $stmt->execute([(int) $ticket['id']]);
                $lastChange = $stmt->fetchColumn();
                // If no status change exists, measure from ticket creation
                if ($lastChange === false) {
                    $stmt2 = $db->prepare('SELECT TIMESTAMPDIFF(HOUR, created_at, NOW()) FROM tickets WHERE id = ?');
                    $stmt2->execute([(int) $ticket['id']]);
                    $lastChange = (int) $stmt2->fetchColumn();
                }
                if ($operator === 'greater_than' && (int) $lastChange <= (int) $value) return false;
                break;

            case 'is_assigned':
                $assigned = !empty($ticket['assigned_to']);
                $wantYes  = $value === 'yes';
                if ($operator === 'equals' && $assigned !== $wantYes) return false;
                break;

            case 'priority_id':
                $actual = (string) ($ticket['priority_id'] ?? '');
                if ($operator === 'equals'     && $actual !== $value) return false;
                if ($operator === 'not_equals' && $actual === $value) return false;
                break;

            case 'status':
                $actual = $ticket['status'] ?? '';
                if ($operator === 'equals'     && $actual !== $value) return false;
                if ($operator === 'not_equals' && $actual === $value) return false;
                break;

            case 'group_id':
                $actual = (string) ($ticket['group_id'] ?? '');
                if ($operator === 'equals'        && $actual !== $value)             return false;
                if ($operator === 'not_equals'    && $actual === $value)             return false;
                if ($operator === 'is_empty'      && !empty($ticket['group_id']))    return false;
                if ($operator === 'is_not_empty'  && empty($ticket['group_id']))     return false;
                break;

            default:
                return false;
        }
    }
    return true;
}

/**
 * Execute all actions for a matched escalation rule against a ticket.
 * Adds per-action timeline entries. Handles notifications via email + in-app.
 */
function runEscalationRule(\PDO $db, array $rule, array $ticket): void
{
    $ticketId  = (int) $ticket['id'];
    $ruleName  = $rule['name'];
    $actions   = is_string($rule['actions']) ? json_decode($rule['actions'], true) : $rule['actions'];

    // System user ID for timeline entries: use assigned agent or NULL
    $systemUserId = null;

    $appUrl     = env('APP_URL', 'http://localhost:8000');
    $appName    = getSetting('app_name', 'LocalDesk');
    $brandColor = getSetting('branding_primary_color', '#4f46e5');

    // Add a parent timeline entry marking the escalation fired
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
    )->execute([$ticketId, 'escalation_triggered', "Escalation rule \"{$ruleName}\" triggered"]);

    $validStatuses = ['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party', 'resolved', 'closed'];
    $pausingStatuses = ['pending', 'waiting_on_customer', 'waiting_on_third_party'];

    foreach ($actions as $act) {
        $actionType = $act['action'] ?? '';
        $actionVal  = $act['value']  ?? '';

        switch ($actionType) {
            case 'set_priority':
                $newPriority = $actionVal !== '' ? (int) $actionVal : null;
                $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')->execute([$newPriority, $ticketId]);
                $name = 'None';
                if ($newPriority) {
                    $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
                    $s->execute([$newPriority]);
                    $name = $s->fetchColumn() ?: 'None';
                }
                $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)')
                   ->execute([$ticketId, 'priority_changed', "Priority set to {$name} by escalation rule"]);
                if ($newPriority) {
                    \Sla::onPriorityChanged($db, $ticketId, $newPriority);
                }
                break;

            case 'set_assigned_to':
                $newAssigned = $actionVal !== '' ? (int) $actionVal : null;
                $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$newAssigned, $ticketId]);
                $agentName = 'Unassigned';
                if ($newAssigned) {
                    $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
                    $s->execute([$newAssigned]);
                    $agentName = $s->fetchColumn() ?: 'Unknown';
                }
                $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)')
                   ->execute([$ticketId, 'assigned', "Assigned to {$agentName} by escalation rule"]);
                break;

            case 'set_group':
                $newGroup = $actionVal !== '' ? (int) $actionVal : null;
                $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$newGroup, $ticketId]);
                $groupName = 'None';
                if ($newGroup) {
                    $s = $db->prepare('SELECT name FROM groups WHERE id = ?');
                    $s->execute([$newGroup]);
                    $groupName = $s->fetchColumn() ?: 'None';
                }
                $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)')
                   ->execute([$ticketId, 'group_changed', "Group set to {$groupName} by escalation rule"]);
                break;

            case 'set_status':
                if (!in_array($actionVal, $validStatuses, true)) break;
                $oldStatus = $ticket['status'];
                $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$actionVal, $ticketId]);
                $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)')
                   ->execute([$ticketId, 'status_changed', "Status changed from {$oldStatus} to {$actionVal} by escalation rule"]);
                if (in_array($actionVal, $pausingStatuses, true)) {
                    \Sla::pause($db, $ticketId);
                } elseif (in_array($oldStatus, $pausingStatuses, true)) {
                    \Sla::resume($db, $ticketId);
                }
                break;

            case 'add_internal_note':
                $note = trim($actionVal);
                if ($note === '') break;
                $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)')
                   ->execute([$ticketId, 'comment', $note]);
                break;

            case 'notify_user':
            case 'notify_assigned_agent':
                // Resolve the target user
                if ($actionType === 'notify_assigned_agent') {
                    // Re-fetch assigned_to in case a previous action changed it
                    $s = $db->prepare('SELECT assigned_to FROM tickets WHERE id = ?');
                    $s->execute([$ticketId]);
                    $targetUserId = (int) $s->fetchColumn();
                } else {
                    $targetUserId = (int) $actionVal;
                }
                if ($targetUserId <= 0) break;

                $s = $db->prepare('SELECT id, first_name, last_name, email, role, notify_escalation FROM users WHERE id = ?');
                $s->execute([$targetUserId]);
                $targetUser = $s->fetch();
                if (!$targetUser) break;
                if (!(bool)($targetUser['notify_escalation'] ?? 1)) break; // opted out

                // Fetch ticket subject for message
                $s = $db->prepare('SELECT subject FROM tickets WHERE id = ?');
                $s->execute([$ticketId]);
                $subject = $s->fetchColumn() ?: "(Ticket #{$ticketId})";

                $noteText = "Escalation alert: Rule \"{$ruleName}\" was triggered on this ticket.";

                // Timeline entry for the notification
                $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)')
                   ->execute([$ticketId, 'escalation_notification', $noteText]);
                $tlId = (int) $db->lastInsertId();

                // In-app notification (requires a valid mentioned_by user; use first admin as system sender)
                $s = $db->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
                $s->execute();
                $systemSenderId = (int) $s->fetchColumn();
                if ($systemSenderId > 0) {
                    $db->prepare('INSERT INTO notifications (user_id, ticket_id, timeline_id, mentioned_by) VALUES (?, ?, ?, ?)')
                       ->execute([$targetUserId, $ticketId, $tlId, $systemSenderId]);
                }

                // Email notification
                $rolePrefix = match ($targetUser['role']) {
                    'admin' => '/admin',
                    'agent' => '/agent',
                    default => '/portal',
                };
                $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

                $emailHtml = renderEmail('escalation', [
                    'ticketId'   => $ticketId,
                    'subject'    => $subject,
                    'ruleName'   => $ruleName,
                    'firstName'  => $targetUser['first_name'],
                    'ticketUrl'  => $ticketUrl,
                    'brandColor' => $brandColor,
                    'appName'    => $appName,
                    'footerText' => 'This is an automated escalation alert from ' . $appName . '.',
                ]);
                sendMail(
                    $targetUser['email'],
                    $targetUser['first_name'] . ' ' . $targetUser['last_name'],
                    "Escalation Alert: [Ticket #{$ticketId}] {$subject}",
                    $emailHtml,
                    '',
                    $ticketId
                );
                break;

            case 'notify_ticket_creator':
                // Send a reminder email to the person who submitted the ticket
                $s = $db->prepare(
                    'SELECT t.subject, u.id AS user_id, u.first_name, u.last_name, u.email, u.notify_ticket_updated
                     FROM tickets t
                     JOIN users u ON t.created_by = u.id
                     WHERE t.id = ?'
                );
                $s->execute([$ticketId]);
                $creator = $s->fetch();
                if (!$creator) break;
                if (!(bool)($creator['notify_ticket_updated'] ?? 1)) break; // user opted out

                $tpl        = getEmailTpl('ticket-reminder', [
                    'ticket_id'  => $ticketId,
                    'subject'    => $creator['subject'],
                    'first_name' => $creator['first_name'],
                    'last_name'  => $creator['last_name'],
                    'user_name'  => $creator['first_name'] . ' ' . $creator['last_name'],
                ]);
                $ticketUrl  = $appUrl . '/portal/tickets/' . $ticketId;
                $footerText = getSetting('email_footer_text') ?: 'This is an automated message from ' . $appName . '. Please do not reply directly to this email.';

                $emailHtml = renderEmail('ticket-reminder', [
                    'ticketId'    => $ticketId,
                    'subject'     => $creator['subject'],
                    'firstName'   => $creator['first_name'],
                    'introText'   => $tpl['intro'],
                    'buttonLabel' => $tpl['button'],
                    'ticketUrl'   => $ticketUrl,
                    'brandColor'  => $brandColor,
                    'appName'     => $appName,
                    'footerText'  => $footerText,
                ]);

                sendMail(
                    $creator['email'],
                    $creator['first_name'] . ' ' . $creator['last_name'],
                    $tpl['subject'],
                    $emailHtml,
                    '',
                    $ticketId
                );

                $db->prepare(
                    'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                )->execute([$ticketId, 'escalation_notification',
                    "Reminder email sent to ticket creator ({$creator['email']}) by escalation rule \"{$ruleName}\"."]);
                break;
        }
    }
}

/* ── CSAT Survey ──────────────────────────────────────────────── */

/**
 * Create a CSAT survey record and send the rating email to the ticket creator.
 * Silently skips if CSAT is disabled, email not configured, or survey already sent.
 */
function sendCsatSurvey(\PDO $db, int $ticketId): void
{
    if (getSetting('csat_enabled') !== '1') {
        return;
    }

    // Don't send twice for the same ticket
    $exists = $db->prepare('SELECT id FROM csat_surveys WHERE ticket_id = ?');
    $exists->execute([$ticketId]);
    if ($exists->fetchColumn()) {
        return;
    }

    // Fetch ticket subject + creator details
    $stmt = $db->prepare(
        'SELECT t.subject, u.id AS user_id, u.email, u.first_name, u.last_name, u.notify_csat
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['email'])) {
        return;
    }
    if (!(bool)($row['notify_csat'] ?? 1)) {
        return; // user opted out of satisfaction surveys
    }

    $token = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO csat_surveys (ticket_id, user_id, token) VALUES (?, ?, ?)')
       ->execute([$ticketId, (int) $row['user_id'], $token]);

    $appUrl     = env('APP_URL', 'http://localhost:8000');
    $surveyUrl  = $appUrl . '/survey/' . $token;
    $brandColor = getSetting('branding_primary_color', '#4f46e5');
    $appName    = getSetting('app_name', 'LocalDesk');

    $tpl = getEmailTpl('csat_survey', [
        'ticket_id'  => $ticketId,
        'subject'    => $row['subject'],
        'first_name' => $row['first_name'],
        'last_name'  => $row['last_name'],
        'user_name'  => $row['first_name'] . ' ' . $row['last_name'],
    ]);

    $emailHtml = renderEmail('csat-survey', [
        'ticketId'   => $ticketId,
        'subject'    => $row['subject'],
        'firstName'  => $row['first_name'],
        'surveyUrl'  => $surveyUrl,
        'brandColor' => $brandColor,
        'appName'    => $appName,
        'introText'  => $tpl['intro'],
        'footerText' => $tpl['footer'],
    ]);

    sendMail(
        $row['email'],
        $row['first_name'] . ' ' . $row['last_name'],
        $tpl['subject'],
        $emailHtml,
        '',
        $ticketId
    );
}

/* ── Name helpers ─────────────────────────────────────────────── */

/**
 * Split a "First Last" string into [first_name, last_name].
 */
function splitFullName(string $name): array
{
    $name = trim($name);
    if ($name === '') {
        return ['Unknown', 'User'];
    }
    $parts = preg_split('/\s+/', $name, 2);
    return [
        $parts[0],
        $parts[1] ?? '',
    ];
}

/* ── TOTP (RFC 6238) ──────────────────────────────────────────── */

/**
 * Generate a random 16-character base32 secret for TOTP.
 */
function totpGenerateSecret(int $length = 16): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bytes    = random_bytes($length);
    $secret   = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[ord($bytes[$i]) & 31];
    }
    return $secret;
}

/**
 * Decode a base32 string to raw bytes.
 */
function totpBase32Decode(string $base32): string
{
    static $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = strtoupper(rtrim($base32, '='));
    $binary = '';
    foreach (str_split($base32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($binary, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $bytes .= chr((int) bindec($chunk));
        }
    }
    return $bytes;
}

/**
 * Verify a 6-digit TOTP code. Allows ±$window time steps for clock drift.
 */
function totpVerify(string $secret, string $code, int $window = 1): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $rawSecret = totpBase32Decode($secret);
    $timeStep  = (int) floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $msg    = pack('J', $timeStep + $i);
        $hmac   = hash_hmac('sha1', $msg, $rawSecret, true);
        $offset = ord($hmac[19]) & 0x0f;
        $otp    = (
            ((ord($hmac[$offset])     & 0x7f) << 24) |
            ((ord($hmac[$offset + 1]) & 0xff) << 16) |
            ((ord($hmac[$offset + 2]) & 0xff) << 8)  |
             (ord($hmac[$offset + 3]) & 0xff)
        ) % 1_000_000;
        if (str_pad((string) $otp, 6, '0', STR_PAD_LEFT) === $code) {
            return true;
        }
    }
    return false;
}

/**
 * Build the otpauth:// URI for a QR code.
 */
function totpGetUri(string $secret, string $email): string
{
    $appName = rawurlencode(getSetting('branding_app_name', 'LocalDesk'));
    $email   = rawurlencode($email);
    return "otpauth://totp/{$appName}:{$email}?secret={$secret}&issuer={$appName}&algorithm=SHA1&digits=6&period=30";
}

/**
 * Return a URL to a QR code image for the given otpauth:// URI.
 */
function totpGetQrUrl(string $uri): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?data=' . rawurlencode($uri) . '&size=200x200&margin=4';
}

/* ── Audit log ────────────────────────────────────────────────── */

/**
 * Record an admin audit event. Silently swallows all errors so that
 * a logging failure never breaks the main request.
 */
function logAudit(string $action, ?int $targetId = null, ?string $targetType = null, ?string $detail = null): void
{
    try {
        $db = Database::connect();
        $db->prepare(
            'INSERT INTO audit_log (user_id, action, target_type, target_id, detail, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            Auth::id(),
            $action,
            $targetType,
            $targetId,
            $detail,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (\Throwable $e) {
        // Never let audit logging break the application
    }
}

/* ── Sidebar helpers ──────────────────────────────────────────── */

function adminSidebar(string $active = ''): array
{
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',    'label' => label('nav.dashboard'),     'url' => '/admin',            'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed', 'label' => label('nav.tickets'),       'url' => '/admin/tickets',    'key' => 'tickets'],
        ['icon' => 'bi-people',          'label' => label('nav.users'),         'url' => '/admin/users',      'key' => 'users'],
        ['icon' => 'bi-book',            'label' => label('nav.knowledge_base'), 'url' => '/admin/kb/categories', 'key' => 'kb'],
        ['icon' => 'bi-diagram-3',       'label' => label('nav.workflows'),     'url' => '/admin/workflows/ticket-fields', 'key' => 'workflows'],
        ['icon' => 'bi-sliders',         'label' => label('nav.settings'),      'url' => '/admin/settings',   'key' => 'settings'],
        ['icon' => 'bi-bar-chart',       'label' => label('nav.reports'),       'url' => '/admin/reports',    'key' => 'reports'],
        ['icon' => 'bi-shield-check',    'label' => label('nav.audit_log'),     'url' => '/admin/audit-log',  'key' => 'audit-log'],
        ['icon' => 'bi-question-circle', 'label' => label('nav.docs'),          'url' => '/admin/docs',       'key' => 'docs'],
    ]);
}

function portalSidebar(string $active = ''): array
{
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',    'label' => label('portal.nav.dashboard'),     'url' => '/portal',         'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed', 'label' => label('portal.nav.my_tickets'),    'url' => '/portal/tickets', 'key' => 'tickets'],
        ['icon' => 'bi-book',            'label' => label('portal.nav.knowledge_base'), 'url' => '/portal/kb',     'key' => 'kb'],
    ]);
}

function agentSidebar(string $active = ''): array
{
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',    'label' => label('agent.nav.dashboard'),      'url' => '/agent',          'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed', 'label' => label('agent.nav.tickets'),        'url' => '/agent/tickets',  'key' => 'tickets'],
        ['icon' => 'bi-book',            'label' => label('agent.nav.knowledge_base'),  'url' => '/agent/kb',                   'key' => 'kb'],
        ['icon' => 'bi-chat-square-text', 'label' => 'Canned Responses',                 'url' => '/agent/canned-responses',     'key' => 'canned-responses'],
    ]);
}

function powerUserSidebar(string $active = ''): array
{
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',     'label' => label('agent.nav.dashboard'),      'url' => '/agent',                  'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed',  'label' => label('agent.nav.tickets'),        'url' => '/agent/tickets',          'key' => 'tickets'],
        ['icon' => 'bi-book',             'label' => label('agent.nav.knowledge_base'), 'url' => '/agent/kb',               'key' => 'kb'],
        ['icon' => 'bi-chat-square-text', 'label' => 'Canned Responses',                'url' => '/agent/canned-responses', 'key' => 'canned-responses'],
        ['icon' => 'bi-bar-chart',        'label' => label('nav.reports'),              'url' => '/admin/reports',          'key' => 'reports'],
    ]);
}

function sortUrl(string $col, string $currentSort, string $currentDir, array $baseParams, string $basePath): string
{
    $newDir = ($col === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
    return e($basePath . '?' . http_build_query(array_merge($baseParams, ['sort' => $col, 'dir' => $newDir])));
}

function sortIcon(string $col, string $currentSort, string $currentDir): string
{
    if ($col !== $currentSort) {
        return '';
    }
    $icon = $currentDir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down';
    return '<i class="bi ' . $icon . ' ms-1" style="font-size:.7rem;color:var(--ld-primary);"></i>';
}

/**
 * Build WHERE clause and bindings for ticket filtering.
 * Returns ['where' => string, 'params' => array].
 */
function buildTicketFilterQuery(array $filters): array
{
    $where  = [];
    $params = [];

    // Normalize: accept both scalar (legacy) and array values
    $fStatus   = array_values(array_filter(array_map('trim', (array) ($filters['status']   ?? []))));
    $fPriority = array_values(array_filter(array_map('trim', (array) ($filters['priority'] ?? []))));
    $fType     = array_values(array_filter(array_map('trim', (array) ($filters['type']     ?? []))));
    $fLocation = array_values(array_filter(array_map('trim', (array) ($filters['location'] ?? []))));
    $fAgent    = array_values(array_filter(array_map('trim', (array) ($filters['agent']    ?? []))));
    $fGroup     = array_values(array_filter(array_map('trim', (array) ($filters['group']     ?? []))));
    $fRequester = array_values(array_filter(array_map('trim', (array) ($filters['requester'] ?? []))));
    $fSearch    = trim($filters['q'] ?? '');
    $fDateFrom = trim($filters['date_from'] ?? '');
    $fDateTo   = trim($filters['date_to'] ?? '');
    $fWatched  = !empty($filters['watched']);

    if (!empty($fStatus)) {
        $placeholders = implode(',', array_fill(0, count($fStatus), '?'));
        $where[]  = 't.status IN (' . $placeholders . ')';
        $params   = array_merge($params, $fStatus);
    }
    if (!empty($fPriority)) {
        $ids = array_map('intval', $fPriority);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[]  = 't.priority_id IN (' . $placeholders . ')';
        $params   = array_merge($params, $ids);
    }
    if (!empty($fType)) {
        $ids = array_map('intval', $fType);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[]  = 't.type_id IN (' . $placeholders . ')';
        $params   = array_merge($params, $ids);
    }
    if (!empty($fLocation)) {
        $ids = array_map('intval', $fLocation);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[]  = 't.location_id IN (' . $placeholders . ')';
        $params   = array_merge($params, $ids);
    }
    if (!empty($fAgent)) {
        $agentConds  = [];
        $otherAgents = [];
        foreach ($fAgent as $v) {
            if ($v === 'unassigned') {
                $agentConds[] = 't.assigned_to IS NULL';
            } else {
                $otherAgents[] = (int) $v;
            }
        }
        if (!empty($otherAgents)) {
            $placeholders = implode(',', array_fill(0, count($otherAgents), '?'));
            $agentConds[] = 't.assigned_to IN (' . $placeholders . ')';
            $params       = array_merge($params, $otherAgents);
        }
        if (!empty($agentConds)) {
            $where[] = '(' . implode(' OR ', $agentConds) . ')';
        }
    }
    if (!empty($fGroup)) {
        $groupConds  = [];
        $otherGroups = [];
        foreach ($fGroup as $v) {
            if ($v === 'none') {
                $groupConds[] = 't.group_id IS NULL';
            } else {
                $otherGroups[] = (int) $v;
            }
        }
        if (!empty($otherGroups)) {
            $placeholders = implode(',', array_fill(0, count($otherGroups), '?'));
            $groupConds[] = 't.group_id IN (' . $placeholders . ')';
            $params       = array_merge($params, $otherGroups);
        }
        if (!empty($groupConds)) {
            $where[] = '(' . implode(' OR ', $groupConds) . ')';
        }
    }
    if (!empty($fRequester)) {
        $ids = array_map('intval', $fRequester);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[]  = 't.created_by IN (' . $placeholders . ')';
        $params   = array_merge($params, $ids);
    }
    if ($fSearch !== '') {
        $where[]  = 't.subject LIKE ?';
        $params[] = '%' . $fSearch . '%';
    }
    if ($fDateFrom !== '') {
        $where[]  = 't.created_at >= ?';
        $params[] = $fDateFrom . ' 00:00:00';
    }
    if ($fDateTo !== '') {
        $where[]  = 't.created_at <= ?';
        $params[] = $fDateTo . ' 23:59:59';
    }

    if ($fWatched) {
        $where[]  = 't.id IN (SELECT ticket_id FROM ticket_watchers WHERE user_id = ?)';
        $params[] = Auth::id();
    }

    $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    return ['where' => $whereClause, 'params' => $params];
}

/**
 * Evaluate a single automation condition against a ticket row.
 */
function evalAutomationCondition(array $cond, array $ticket): bool
{
    $actual = $ticket[$cond['field'] ?? ''] ?? null;
    return match ($cond['operator'] ?? '') {
        'equals'       => (string) $actual === (string) ($cond['value'] ?? ''),
        'not_equals'   => (string) $actual !== (string) ($cond['value'] ?? ''),
        'is_empty'     => $actual === null || $actual === '',
        'is_not_empty' => $actual !== null && $actual !== '',
        default        => false,
    };
}

/**
 * Evaluate the conditions block of an automation against a ticket.
 *
 * Supports v2 (group-based: OR between groups, ALL/ANY within each group)
 * and v1 (flat array, treated as a single ALL group for backward compatibility).
 */
function evalAutomationConditions(array $conditions, array $ticket): bool
{
    // v1 backward compat: flat indexed array of condition objects
    if (isset($conditions[0]['field'])) {
        $conditions = ['groups' => [['match' => 'all', 'conditions' => $conditions]]];
    }

    $groups = $conditions['groups'] ?? [];
    if (empty($groups)) {
        return true; // no conditions — always fires
    }

    // OR between groups: automation fires if any single group passes
    foreach ($groups as $group) {
        $anyMatch = ($group['match'] ?? 'all') === 'any';
        $groupPasses = !$anyMatch; // 'all' starts true, 'any' starts false
        foreach ($group['conditions'] as $cond) {
            $result = evalAutomationCondition($cond, $ticket);
            if ($anyMatch) {
                if ($result) { $groupPasses = true; break; }
            } else {
                if (!$result) { $groupPasses = false; break; }
            }
        }
        if ($groupPasses) {
            return true;
        }
    }
    return false;
}

/**
 * Run all enabled automations for a given trigger event against a ticket.
 */
function runAutomations(PDO $db, int $ticketId, string $triggerEvent): void
{
    $rules = $db->prepare(
        'SELECT * FROM automations WHERE trigger_event = ? AND is_enabled = 1 ORDER BY sort_order, id'
    );
    $rules->execute([$triggerEvent]);
    $automations = $rules->fetchAll();

    if (empty($automations)) {
        return;
    }

    foreach ($automations as $auto) {
        // Re-fetch ticket for each automation so earlier automations' changes are visible
        $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            return;
        }

        $conditions = json_decode($auto['conditions'], true) ?: [];
        $actions    = json_decode($auto['actions'], true) ?: [];

        $match = evalAutomationConditions($conditions, $ticket);

        if (!$match) {
            continue;
        }

        // Execute actions
        foreach ($actions as $act) {
            $action = $act['action'] ?? '';
            $val    = $act['value'] ?? '';

            switch ($action) {
                case 'set_group':
                    $groupId = $val === '' ? null : (int) $val;
                    $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$groupId, $ticketId]);
                    $groupName = 'None';
                    if ($groupId) {
                        $s = $db->prepare('SELECT name FROM groups WHERE id = ?');
                        $s->execute([$groupId]);
                        $groupName = $s->fetchColumn() ?: 'Unknown';
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticketId, 'automation', "Automation '{$auto['name']}': Group set to {$groupName}"]);
                    break;

                case 'set_assigned_to':
                    $agentId = $val === '' ? null : (int) $val;
                    $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$agentId, $ticketId]);
                    $agentName = 'Unassigned';
                    if ($agentId) {
                        $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
                        $s->execute([$agentId]);
                        $agentName = $s->fetchColumn() ?: 'Unknown';
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticketId, 'automation', "Automation '{$auto['name']}': Assigned to {$agentName}"]);
                    break;

                case 'set_priority':
                    $priorityId = $val === '' ? null : (int) $val;
                    $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')->execute([$priorityId, $ticketId]);
                    $priorityName = 'None';
                    if ($priorityId) {
                        $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
                        $s->execute([$priorityId]);
                        $priorityName = $s->fetchColumn() ?: 'Unknown';
                        Sla::onPriorityChanged($db, $ticketId, $priorityId);
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticketId, 'automation', "Automation '{$auto['name']}': Priority set to {$priorityName}"]);
                    break;

                case 'set_status':
                    $validStatuses = ['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party', 'resolved', 'closed'];
                    if (!in_array($val, $validStatuses, true)) {
                        break;
                    }
                    $oldStatus = $ticket['status'];
                    $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$val, $ticketId]);
                    $pausingStatuses = ['pending', 'waiting_on_customer', 'waiting_on_third_party'];
                    if (in_array($val, $pausingStatuses, true)) {
                        Sla::pause($db, $ticketId);
                    } elseif (in_array($oldStatus, $pausingStatuses, true)) {
                        Sla::resume($db, $ticketId);
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticketId, 'automation', "Automation '{$auto['name']}': Status set to {$val}"]);
                    break;

                case 'add_tag':
                    $tagName = trim(strtolower(preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $val)));
                    if ($tagName === '') {
                        break;
                    }
                    $findTag = $db->prepare('SELECT id FROM ticket_tags WHERE name = ?');
                    $findTag->execute([$tagName]);
                    $tagId = $findTag->fetchColumn();
                    if (!$tagId) {
                        $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)')->execute([$tagName]);
                        $tagId = (int) $db->lastInsertId();
                    }
                    // Only add if not already tagged
                    $exists = $db->prepare('SELECT 1 FROM ticket_tag_map WHERE ticket_id = ? AND tag_id = ?');
                    $exists->execute([$ticketId, $tagId]);
                    if (!$exists->fetchColumn()) {
                        $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)')->execute([$ticketId, $tagId]);
                        $db->prepare(
                            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                        )->execute([$ticketId, 'automation', "Automation '{$auto['name']}': Tag #{$tagName} added"]);
                    }
                    break;

                case 'add_cc':
                    $ccUserId = (int) $val;
                    if ($ccUserId <= 0) {
                        break;
                    }
                    // Only add if not already CC'd
                    $exists = $db->prepare('SELECT 1 FROM ticket_cc WHERE ticket_id = ? AND user_id = ?');
                    $exists->execute([$ticketId, $ccUserId]);
                    if (!$exists->fetchColumn()) {
                        $db->prepare(
                            'INSERT INTO ticket_cc (ticket_id, user_id, added_by) VALUES (?, ?, ?)'
                        )->execute([$ticketId, $ccUserId, $ccUserId]);
                        $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
                        $s->execute([$ccUserId]);
                        $ccName = $s->fetchColumn() ?: 'Unknown';
                        $db->prepare(
                            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                        )->execute([$ticketId, 'automation', "Automation '{$auto['name']}': CC'd {$ccName}"]);
                    }
                    break;
            }
        }
    }
}

/**
 * Parse hashtag commands from the end of an email reply body.
 *
 * Scans backward through the body lines. A line is a "command line" if its
 * entire trimmed content consists only of one or more #word tokens (e.g. "#close #high").
 * Blank lines are always skipped.
 *
 * To handle email signatures that appear after the command (e.g. "Thanks, John / IT Support"),
 * the scan allows up to MAX_SIG_LINES non-command non-blank lines at the tail before giving up.
 * Once any command line is found, the scan stops immediately at the next non-command non-blank
 * line going further up — this prevents scanning through the entire email body.
 *
 * Only lines that are purely "#word" tokens qualify as commands — hashtags embedded in
 * normal sentences (e.g. "check ticket #123") are never treated as commands.
 *
 * Returns an array:
 *   'body'          => string  — body with command lines removed and trailing blanks trimmed
 *   'status'        => string|null — validated status slug (e.g. 'closed') or null
 *   'priority_slug' => string|null — lowercase priority name (e.g. 'high') or null
 */
function parseEmailCommands(string $body): array
{
    $statusMap = [
        'open'                   => 'open',
        'close'                  => 'closed',
        'closed'                 => 'closed',
        'resolve'                => 'resolved',
        'resolved'               => 'resolved',
        'pending'                => 'pending',
        'in_progress'            => 'in_progress',
        'inprogress'             => 'in_progress',
        'waiting_on_customer'    => 'waiting_on_customer',
        'waitingoncustomer'      => 'waiting_on_customer',
        'waiting_on_third_party' => 'waiting_on_third_party',
        'waitingonthirdparty'    => 'waiting_on_third_party',
    ];
    $prioritySlugs = ['low', 'medium', 'high', 'critical'];

    // Max non-blank non-command trailing lines to skip before giving up.
    // Sized to cover typical corporate email signatures (name / title / company /
    // phone / email / website = ~6 lines) plus a small buffer.
    $maxSigLines = 8;

    $body  = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);

    // Walk backward looking for command-only lines.
    // - Before any command is found: skip up to $maxSigLines non-blank non-command lines
    //   (the email signature that appears after the command in the raw body).
    // - Once a command is found: stop immediately at the next non-blank non-command line
    //   (that's where the real message body starts).
    $commandIdx    = [];
    $trailingCount = 0;

    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $trimmed = trim($lines[$i]);
        if ($trimmed === '') {
            continue; // blank lines never count
        }
        if (preg_match('/^(#[a-z_]+\s*)+$/i', $trimmed)) {
            $commandIdx[]  = $i;
            $trailingCount = 0; // reset — keep looking for more commands going upward
        } else {
            if (!empty($commandIdx)) {
                break; // already found commands; this is real body text — stop
            }
            $trailingCount++;
            if ($trailingCount > $maxSigLines) {
                break; // too many trailing non-command lines; give up
            }
        }
    }

    if (empty($commandIdx)) {
        return ['body' => $body, 'status' => null, 'priority_slug' => null];
    }

    $status       = null;
    $prioritySlug = null;
    foreach ($commandIdx as $idx) {
        preg_match_all('/#([a-z_]+)/i', $lines[$idx], $m);
        foreach ($m[1] as $tag) {
            $tag = strtolower($tag);
            if (isset($statusMap[$tag])) {
                $status = $statusMap[$tag];
            } elseif (in_array($tag, $prioritySlugs, true)) {
                $prioritySlug = $tag;
            }
        }
        unset($lines[$idx]);
    }

    // Trim trailing blank lines from remaining body
    $lines = array_values($lines);
    while (!empty($lines) && trim(end($lines)) === '') {
        array_pop($lines);
    }

    return [
        'body'          => implode("\n", $lines),
        'status'        => $status,
        'priority_slug' => $prioritySlug,
    ];
}
