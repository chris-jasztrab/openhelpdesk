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

function renderMarkdown(string $markdown): string
{
    $config = [
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
    ];

    $environment = new \League\CommonMark\Environment\Environment($config);
    $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
    $environment->addExtension(new \League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension());

    $converter = new \League\CommonMark\MarkdownConverter($environment);

    return $converter->convert($markdown)->getContent();
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

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->SMTPSecure = $encryption === 'none' ? '' : $encryption;
        if ($username !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
        }

        $mail->setFrom($fromAddr, $fromName);
        $mail->addReplyTo($fromAddr, $fromName);
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
        return $messageId;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('LocalDesk mail error: ' . $mail->ErrorInfo);
        return false;
    }
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
 * Notify the ticket creator that their ticket was updated (comment added).
 * Skips if the updater IS the creator (no self-notifications).
 */
function notifyTicketCreator(PDO $db, int $ticketId, string $message, string $authorName): void
{
    $stmt = $db->prepare(
        "SELECT t.subject, t.created_by, u.email, u.first_name, u.last_name
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['created_by'] === Auth::id()) {
        return; // ticket not found or updater is the creator
    }

    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $ticketUrl = $appUrl . '/portal/tickets/' . $ticketId;

    $emailHtml = renderEmail('ticket-updated', [
        'ticketId'   => $ticketId,
        'subject'    => $row['subject'],
        'message'    => $message,
        'authorName' => $authorName,
        'ticketUrl'  => $ticketUrl,
    ]);

    sendMail(
        $row['email'],
        $row['first_name'] . ' ' . $row['last_name'],
        '[Ticket #' . $ticketId . '] Update: ' . $row['subject'],
        $emailHtml,
        '',
        $ticketId
    );
}

/* ── Sidebar helpers ──────────────────────────────────────────── */

function adminSidebar(string $active = ''): array
{
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',    'label' => 'Dashboard',  'url' => '/admin',            'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed', 'label' => 'Tickets',    'url' => '/admin/tickets',    'key' => 'tickets'],
        ['icon' => 'bi-people',          'label' => 'Users',      'url' => '/admin/users',      'key' => 'users'],
        ['icon' => 'bi-book',            'label' => 'Knowledge Base', 'url' => '/admin/kb/categories', 'key' => 'kb'],
        ['icon' => 'bi-sliders',         'label' => 'Settings',     'url' => '/admin/settings', 'key' => 'settings'],
        ['icon' => 'bi-bar-chart',       'label' => 'Reports',    'url' => '#', 'badge' => 'Soon', 'key' => 'reports'],
    ]);
}

function portalSidebar(string $active = ''): array
{
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',    'label' => 'Dashboard',  'url' => '/portal',            'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed', 'label' => 'My Tickets', 'url' => '/portal/tickets',    'key' => 'tickets'],
        ['icon' => 'bi-book',            'label' => 'Knowledge Base', 'url' => '/portal/kb',        'key' => 'kb'],
    ]);
}

function agentSidebar(string $active = ''): array
{
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',    'label' => 'Dashboard',     'url' => '/agent',          'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed', 'label' => 'Tickets',       'url' => '/agent/tickets',  'key' => 'tickets'],
        ['icon' => 'bi-book',            'label' => 'Knowledge Base', 'url' => '/portal/kb',          'key' => 'kb'],
        ['icon' => 'bi-people',          'label' => 'Customers',      'url' => '#', 'badge' => 'Soon', 'key' => 'customers'],
    ]);
}
