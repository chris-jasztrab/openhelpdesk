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

/**
 * Pop and validate the post-login return URL stashed by Auth::requireAuth()
 * (or the /login?next= GET handler). Returns "/" when nothing is stashed or
 * when the stashed value fails the safe-relative-URL check — prevents an
 * attacker from crafting an open-redirect via the session.
 *
 * Accepts only paths beginning with a single forward slash. Rejects:
 *   - empty or non-string values
 *   - absolute URLs ("http://..." / "https://...")
 *   - protocol-relative URLs ("//evil.com/x")
 *   - backslash variants ("/\evil.com/x") that some user-agents normalise
 *   - values longer than 2000 chars (defensive against odd cookie/state)
 */
function consumeIntendedUrl(string $fallback = '/'): string
{
    $intended = $_SESSION['intended_url'] ?? null;
    unset($_SESSION['intended_url']);

    if (!is_string($intended) || $intended === '' || strlen($intended) > 2000) {
        return $fallback;
    }
    if ($intended[0] !== '/') {
        return $fallback;
    }
    if (isset($intended[1]) && ($intended[1] === '/' || $intended[1] === '\\')) {
        return $fallback;
    }
    return $intended;
}

/**
 * Validate a caller-supplied redirect target, returning it only when it is a
 * safe same-site relative path (leading single "/", no "//" or "/\" that would
 * become a protocol-relative off-site URL). Anything else returns $fallback.
 * Use for any redirect built from request input (e.g. a `_redirect` form field).
 */
function safeRedirectPath(?string $url, string $fallback = '/'): string
{
    if (!is_string($url) || $url === '' || strlen($url) > 2000) {
        return $fallback;
    }
    if ($url[0] !== '/') {
        return $fallback;
    }
    if (isset($url[1]) && ($url[1] === '/' || $url[1] === '\\')) {
        return $fallback;
    }
    return $url;
}

function render(string $view, array $data = []): never
{
    // Defaults
    $layout       = 'base';
    $pageTitle    = 'OpenHelpDesk';
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

/**
 * Persist a custom sort_order for a list, given the desired row order.
 * Backs the drag-to-reorder + Save Order UI in templates/partials/sortable-list.php.
 *
 * Reads the POST body (JSON { ids: [...] }) and the X-CSRF-Token header,
 * writes sort_order = position * 10 for each matching row, and emits the
 * JSON response. Always exits after responding.
 */
function handleSortableReorder(string $table, string $idColumn = 'id'): void
{
    header('Content-Type: application/json');
    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit;
    }
    $body = json_decode(file_get_contents('php://input'), true);
    $ids  = $body['ids'] ?? null;
    if (!is_array($ids)) {
        echo json_encode(['success' => false, 'error' => 'Missing ids.']);
        exit;
    }
    // Whitelist table + column names against literal allow-list — these are
    // hardcoded in admin routes, never user-supplied, but defence in depth.
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $idColumn)) {
        echo json_encode(['success' => false, 'error' => 'Bad table.']);
        exit;
    }

    $db   = Database::connect();
    $stmt = $db->prepare("UPDATE `{$table}` SET sort_order = ? WHERE `{$idColumn}` = ?");
    $db->beginTransaction();
    try {
        foreach ($ids as $i => $id) {
            $stmt->execute([$i * 10, (int) $id]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Could not save order.']);
        exit;
    }
    echo json_encode(['success' => true]);
    exit;
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

/* ── Form builder: per-ticket-type layout ──────────────────────── */

/**
 * Stable list of system-field keys (in default order). The form layout
 * table allows admins to reorder them per ticket type, but these are the
 * only known keys — anything else is treated as a custom field id.
 */
const SYSTEM_FIELD_KEYS = ['subject','description','ticket_type','location','priority','tags','attachments'];

/**
 * Default presentation for a system field — the seed an admin gets the
 * first time they look at a new ticket type's form.
 */
function systemFieldDefaults(): array
{
    return [
        'subject'     => ['sort_order' => 0,   'visibility' => 'required', 'lockedVisibility' => true,  'lockedOrder' => true],
        'description' => ['sort_order' => 50,  'visibility' => 'required', 'lockedVisibility' => true,  'lockedOrder' => true],
        'ticket_type' => ['sort_order' => 100, 'visibility' => 'required', 'lockedVisibility' => true,  'lockedOrder' => false],
        'location'    => ['sort_order' => 200, 'visibility' => 'optional', 'lockedVisibility' => false, 'lockedOrder' => false],
        'priority'    => ['sort_order' => 300, 'visibility' => 'optional', 'lockedVisibility' => false, 'lockedOrder' => false],
        'tags'        => ['sort_order' => 400, 'visibility' => 'optional', 'lockedVisibility' => false, 'lockedOrder' => false],
        'attachments' => ['sort_order' => 900, 'visibility' => 'optional', 'lockedVisibility' => false, 'lockedOrder' => false],
    ];
}

/**
 * Seed default layout rows for a newly-created ticket type. Idempotent —
 * skips any row that already exists.
 */
function seedDefaultLayoutForType(PDO $db, int $typeId): void
{
    $insert = $db->prepare(
        'INSERT IGNORE INTO ticket_type_form_layout
            (type_id, field_kind, field_key, sort_order, visibility, label_override)
         VALUES (?, ?, ?, ?, ?, NULL)'
    );
    foreach (systemFieldDefaults() as $key => $cfg) {
        $insert->execute([$typeId, 'system', $key, $cfg['sort_order'], $cfg['visibility']]);
    }
}

/**
 * Return the full form layout for a single ticket type, in display order.
 *
 * Each row: [
 *   'kind'           => 'system'|'custom',
 *   'key'            => string                  (e.g. 'priority' or '42'),
 *   'sort_order'     => int,
 *   'visibility'     => 'required'|'optional'|'hidden',
 *   'label_override' => ?string,
 *   'label'          => string                  (resolved — override OR default),
 *   'field'          => ?array                  (ticket_form_fields row for custom; null for system),
 * ]
 *
 * If $visibleOnly is true, rows with visibility = 'hidden' are dropped — use
 * this when rendering the actual ticket-create form. Pass false for the
 * builder UI.
 */
function getFormLayoutForType(PDO $db, ?int $typeId, bool $visibleOnly = false): array
{
    if (!$typeId) {
        return [];
    }
    $stmt = $db->prepare(
        'SELECT l.type_id, l.field_kind, l.field_key, l.sort_order, l.visibility, l.label_override,
                f.id AS field_id, f.field_type, f.label AS field_label, f.placeholder, f.config
         FROM ticket_type_form_layout l
         LEFT JOIN ticket_form_fields f
            ON l.field_kind = "custom" AND f.id = CAST(l.field_key AS UNSIGNED) AND f.deleted_at IS NULL
         WHERE l.type_id = ?
            AND (l.field_kind = "system" OR f.id IS NOT NULL)
         ORDER BY l.sort_order, l.field_kind'
    );
    $stmt->execute([$typeId]);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        if ($visibleOnly && $r['visibility'] === 'hidden') continue;
        $kind = $r['field_kind'];
        $key  = $r['field_key'];

        if ($kind === 'system') {
            $defaultLabel = (string) getSetting('sys_field_label_' . $key, ucfirst(str_replace('_', ' ', $key)));
        } else {
            $defaultLabel = (string) $r['field_label'];
        }

        $field = null;
        if ($kind === 'custom' && $r['field_id'] !== null) {
            $field = [
                'id'          => (int) $r['field_id'],
                'field_type'  => $r['field_type'],
                'label'       => $r['field_label'],
                'placeholder' => $r['placeholder'],
                'config'      => $r['config'],
            ];
        }

        $out[] = [
            'kind'           => $kind,
            'key'            => $key,
            'sort_order'     => (int) $r['sort_order'],
            'visibility'     => $r['visibility'],
            'label_override' => $r['label_override'],
            'label'          => $r['label_override'] ?: $defaultLabel,
            'field'          => $field,
        ];
    }
    return $out;
}

/**
 * Resolve the visibility of a single field on a single ticket type's form.
 * Returns 'required' | 'optional' | 'hidden'. If no layout row exists for
 * this type/field, returns 'hidden' (defensive — caller should generally
 * see the layout it just rendered).
 */
function resolveFieldVisibility(PDO $db, ?int $typeId, string $kind, string $key): string
{
    if (!$typeId) return 'hidden';
    static $cache = [];
    $ck = "{$typeId}|{$kind}|{$key}";
    if (!array_key_exists($ck, $cache)) {
        $stmt = $db->prepare(
            'SELECT visibility FROM ticket_type_form_layout
             WHERE type_id = ? AND field_kind = ? AND field_key = ?'
        );
        $stmt->execute([$typeId, $kind, $key]);
        $cache[$ck] = $stmt->fetchColumn() ?: 'hidden';
    }
    return $cache[$ck];
}

/**
 * Look up the system "default" priority — the priority with the lowest
 * sort_order (tiebroken by id). Used to backfill when priority is hidden
 * for the chosen ticket type. Returns null only if no priorities exist.
 */
function getDefaultPriorityId(PDO $db): ?int
{
    static $cached = null;
    if ($cached === null) {
        $id = $db->query('SELECT id FROM ticket_priorities ORDER BY sort_order, id LIMIT 1')->fetchColumn();
        $cached = $id ? (int) $id : 0;
    }
    return $cached ?: null;
}

/**
 * Custom field ids that appear on each ticket type — used to filter
 * options/etc. queries. Returns [type_id => [field_id, ...]].
 */
function getCustomFieldIdsByType(PDO $db): array
{
    $rows = $db->query(
        'SELECT type_id, field_key FROM ticket_type_form_layout
         WHERE field_kind = "custom" AND visibility != "hidden"'
    )->fetchAll();
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r['type_id']][] = (int) $r['field_key'];
    }
    return $map;
}

/**
 * For every ticket type, the list of custom-field ids whose visibility is
 * 'required' for that type. Used server-side to know which custom fields
 * must be present in a submitted payload.
 */
function getRequiredCustomFieldIdsForType(PDO $db, int $typeId): array
{
    $stmt = $db->prepare(
        'SELECT field_key FROM ticket_type_form_layout
         WHERE type_id = ? AND field_kind = "custom" AND visibility = "required"'
    );
    $stmt->execute([$typeId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Map of [field_id => [type_id, ...]] — used by the ticket-create page JS
 * to show/hide custom fields as the user changes ticket type. Mirrors the
 * old getFieldTypeMap() shape so callers can drop in without changes.
 */
function getCustomFieldTypeMap(PDO $db): array
{
    $rows = $db->query(
        'SELECT type_id, field_key FROM ticket_type_form_layout
         WHERE field_kind = "custom" AND visibility != "hidden"'
    )->fetchAll();
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r['field_key']][] = (int) $r['type_id'];
    }
    return $map;
}

/**
 * Whether a stored custom-field value is worth showing on the ticket-view
 * "Custom Fields" panel — i.e. it would render real data rather than the
 * bare "&mdash;" placeholder. A checkbox always counts (it renders Yes/No).
 * Mirrors the per-type rendering in the agent/portal ticket-view templates.
 */
function customFieldHasDisplayValue(string $fieldType, $value, array $options = []): bool
{
    if ($fieldType === 'checkbox') {
        return true;
    }
    $value = (string) ($value ?? '');
    if ($value === '') {
        return false;
    }
    if ($fieldType === 'dropdown') {
        foreach ($options as $o) {
            if ((int) $o['id'] === (int) $value) return true;
        }
        return false;
    }
    if ($fieldType === 'dependent') {
        $dep = json_decode($value, true) ?: [];
        foreach (['l1', 'l2', 'l3'] as $lk) {
            if (empty($dep[$lk])) continue;
            foreach ($options as $o) {
                if ((int) $o['id'] === (int) $dep[$lk]) return true;
            }
        }
        return false;
    }
    if ($fieldType === 'date_range') {
        $dr = json_decode($value, true) ?: [];
        return !empty($dr['from']) || !empty($dr['to']);
    }
    return true;
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
 * Fetch a user's notification feed rows for both the full page and the AJAX
 * polling endpoint, so the two never diverge. LEFT JOINs the actor + timeline
 * (only mentions/comments carry them) and prefers the denormalized body,
 * falling back to the linked timeline note for legacy mention rows.
 */
function notificationsFeedRows(PDO $db, int $userId, int $limit = 50): array
{
    $stmt = $db->prepare(
        "SELECT n.*, t.subject AS ticket_subject,
                CONCAT(m.first_name, ' ', m.last_name) AS mentioned_by_name,
                COALESCE(NULLIF(n.body, ''), tl.details) AS message
         FROM notifications n
         JOIN tickets t          ON n.ticket_id    = t.id
         LEFT JOIN users m       ON n.mentioned_by = m.id
         LEFT JOIN ticket_timeline tl ON n.timeline_id = tl.id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC
         LIMIT " . (int) $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * The ticket-link URL prefix for the current user's area, used by the
 * notifications feed to point at the role-appropriate ticket view.
 */
function notificationsAreaPrefix(): string
{
    if (Auth::isAdmin()) {
        return '/admin';
    }
    if (Auth::isStaff()) {
        return '/agent';
    }
    return '/portal';
}

/**
 * Create a single in-app notification on the recipient's notifications feed.
 *
 * This is the generic entry point behind every notification type (mentions,
 * assignments, ticket/status updates, SLA warnings & breaches, new tickets,
 * customer replies, notes). The `notifications` table was generalized in
 * migration 043 — `type` names the kind, `body` is a denormalized headline so
 * the feed renders without needing a `ticket_timeline` row, and both
 * `timeline_id` and `mentioned_by` (the acting user, if any) are optional.
 *
 * Self-notifications (recipient === actor) are skipped. Failures are swallowed
 * with an error_log entry: an in-app notification is never important enough to
 * break the ticket action that triggered it.
 */
function createNotification(
    PDO $db,
    int $userId,
    int $ticketId,
    string $type,
    ?string $body = null,
    ?int $actorId = null,
    ?int $timelineId = null
): void {
    if ($userId <= 0 || $ticketId <= 0) {
        return;
    }
    if ($actorId !== null && $userId === $actorId) {
        return; // never notify someone of their own action
    }

    // Trim a denormalized excerpt down to a sane length for the feed line.
    if ($body !== null) {
        $body = trim(html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8'));
        if (function_exists('mb_substr')) {
            $body = mb_substr($body, 0, 280);
        } else {
            $body = substr($body, 0, 280);
        }
        if ($body === '') {
            $body = null;
        }
    }

    try {
        $db->prepare(
            'INSERT INTO notifications (user_id, ticket_id, type, timeline_id, mentioned_by, body)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $ticketId, $type, $timelineId, $actorId, $body]);
    } catch (\Throwable $e) {
        error_log('createNotification failed: ' . $e->getMessage());
    }
}

/**
 * Per-type display metadata for the notifications feed: Bootstrap icon class,
 * a Bootstrap text-color class for the icon, and the verb shown next to the
 * actor / ticket subject. Unknown types fall back to a neutral bell.
 *
 * @return array{icon:string,color:string,label:string}
 */
function notificationMeta(string $type): array
{
    static $map = [
        'mention'        => ['icon' => 'bi-at',                 'color' => 'text-primary', 'label' => 'mentioned you in'],
        'assignment'     => ['icon' => 'bi-person-check',       'color' => 'text-success', 'label' => 'assigned to you:'],
        'ticket_update'  => ['icon' => 'bi-arrow-repeat',       'color' => 'text-info',    'label' => 'updated'],
        'sla_warning'    => ['icon' => 'bi-clock-history',      'color' => 'text-warning', 'label' => 'SLA deadline approaching on'],
        'sla_breach'     => ['icon' => 'bi-exclamation-triangle','color' => 'text-danger', 'label' => 'SLA breached on'],
        'new_ticket'     => ['icon' => 'bi-plus-circle',        'color' => 'text-primary', 'label' => 'new ticket:'],
        'customer_reply' => ['icon' => 'bi-chat-left-text',     'color' => 'text-info',    'label' => 'customer replied on'],
        'note_added'     => ['icon' => 'bi-sticky',             'color' => 'text-secondary','label' => 'added a note on'],
    ];
    return $map[$type] ?? ['icon' => 'bi-bell', 'color' => 'text-muted', 'label' => 'updated'];
}

/**
 * Parse @mentions from a message and create notifications.
 * Matches "@FirstName LastName" against agents/admins in the database.
 */
function processAtMentions(PDO $db, string $message, int $ticketId, int $timelineId, int $mentionedBy): void
{
    $agents = $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . "")->fetchAll();
    foreach ($agents as $agent) {
        $fullName = $agent['first_name'] . ' ' . $agent['last_name'];
        if (stripos($message, '@' . $fullName) !== false && (int) $agent['id'] !== $mentionedBy) {
            // body left NULL — the feed falls back to the linked timeline note.
            createNotification($db, (int) $agent['id'], $ticketId, 'mention', null, $mentionedBy, $timelineId);
        }
    }
}

/**
 * In-app notify the assigned agent + staff watchers when a ticket's status
 * changes. There is no email helper for agent-facing status changes (only the
 * requester gets resolved/closed mail), so this fans out the in-app
 * "Ticket Updates" notification directly. Called from every status-change site.
 */
function notifyAgentStatusChanged(PDO $db, int $ticketId, string $oldStatus, string $newStatus, int $actorId): void
{
    if ($oldStatus === $newStatus) {
        return;
    }
    $oldLabel = function_exists('ticketStatusLabel') ? ticketStatusLabel($oldStatus) : $oldStatus;
    $newLabel = function_exists('ticketStatusLabel') ? ticketStatusLabel($newStatus) : $newStatus;
    $body = "Status changed from {$oldLabel} to {$newLabel}";

    // Recipients: the assigned agent plus any staff watchers. Dedupe by id.
    $recipients = [];

    $aStmt = $db->prepare('SELECT assigned_to FROM tickets WHERE id = ?');
    $aStmt->execute([$ticketId]);
    $assignedTo = (int) ($aStmt->fetchColumn() ?: 0);
    if ($assignedTo > 0) {
        $recipients[$assignedTo] = true;
    }

    $wStmt = $db->prepare(
        "SELECT u.id FROM ticket_watchers tw
         JOIN users u ON u.id = tw.user_id
         WHERE tw.ticket_id = ? AND " . staffRoleSqlIn('u.role')
    );
    $wStmt->execute([$ticketId]);
    foreach ($wStmt->fetchAll(PDO::FETCH_COLUMN) as $wid) {
        $recipients[(int) $wid] = true;
    }

    foreach (array_keys($recipients) as $uid) {
        createNotification($db, (int) $uid, $ticketId, 'ticket_update', $body, $actorId);
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
    // Content saved by the WYSIWYG editor is already HTML — sanitize and return.
    // Sanitizing here is a stored-XSS boundary: it neutralises any malicious
    // markup already in the database, not just freshly-saved content.
    $trimmed = ltrim($content);
    if ($trimmed !== '' && $trimmed[0] === '<') {
        return sanitizeRichHtml($content);
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

/* ── Organization type ───────────────────────────────────────── */

/**
 * The single source of truth for the `organization_type` setting's
 * allowed values + sector groupings + display labels. Used by the
 * organization settings page, the AI skill-suggestion route, and any
 * future feature that needs to know what kind of org this install is.
 *
 * Keep slugs (array keys) stable — they're persisted in the `settings`
 * table — but labels can be relabelled freely.
 */
function organizationTypeGroups(): array
{
    return [
        'Library' => [
            'public_library'    => 'Public Library',
            'academic_library'  => 'Academic Library',
            'special_library'   => 'Special / Research Library',
        ],
        'Education' => [
            'k12_school'        => 'K–12 School / School District',
            'higher_education'  => 'College / University',
            'private_school'    => 'Private / Independent School',
        ],
        'Government' => [
            'government_federal'   => 'Government — Federal',
            'government_state'     => 'Government — State / Provincial',
            'government_municipal' => 'Government — Municipal / Local',
        ],
        'Healthcare' => [
            'hospital'        => 'Hospital / Health System',
            'clinic'          => 'Clinic / Medical Practice',
        ],
        'Business' => [
            'corporation'     => 'Corporation / Enterprise',
            'small_business'  => 'Small Business',
            'manufacturing'   => 'Manufacturing',
            'retail'          => 'Retail / E-commerce',
            'financial'       => 'Financial Services / Banking',
            'legal'           => 'Legal / Law Firm',
            'hospitality'     => 'Hospitality / Travel',
            'technology'      => 'Technology / Software',
        ],
        'Community' => [
            'non_profit'      => 'Non-Profit / Charity',
            'religious'       => 'Religious / Faith-Based',
            'museum'          => 'Museum / Cultural Institution',
            'association'     => 'Association / Membership Group',
        ],
        'Other' => [
            'other'           => 'Other',
        ],
    ];
}

function organizationTypeLabel(string $slug): string
{
    foreach (organizationTypeGroups() as $opts) {
        if (isset($opts[$slug])) {
            return $opts[$slug];
        }
    }
    return 'Other';
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

/**
 * Whether SLA tracking is enabled site-wide. Defaults to enabled.
 *
 * When off, SLA timers are not initialized, recalculated, paused/resumed, or
 * displayed anywhere (tickets, ticket lists, reports). Existing SLA policies
 * and historical SLA data are kept, so the feature can be re-enabled freely.
 */
function slaEnabled(): bool
{
    return getSetting('sla_enabled', '1') === '1';
}

/**
 * Whether the current user wants AI-generated system notes shown in ticket
 * timelines. Stored per-user as `ai_notes_visible:<id>`, defaults to visible.
 *
 * Only admins ever see AI notes, so this preference is meaningful for admins
 * only — but it is safe to call for any authenticated user. The notes are
 * never deleted; this only controls display.
 */
function aiNotesVisible(): bool
{
    return getSetting('ai_notes_visible:' . Auth::id(), '1') === '1';
}

/**
 * Whether the current user wants automated system notes shown in ticket
 * timelines. Stored per-user as `system_notes_visible:<id>`, defaults to
 * visible.
 *
 * "System notes" are timeline entries with no human author (SLA timer
 * events, escalation reminders, automatic group assignment, AI
 * classifications, ...). AI notes are a subset, so hiding system notes also
 * hides AI notes; the two preferences are otherwise independent.
 *
 * Only admins ever see system notes, so this preference is meaningful for
 * admins only — but it is safe to call for any authenticated user. The notes
 * are never deleted; this only controls display.
 */
function systemNotesVisible(): bool
{
    return getSetting('system_notes_visible:' . Auth::id(), '1') === '1';
}

/* ── Auto-assignment helpers ──────────────────────────────────── */

/**
 * Pick an agent for a ticket using the strategy configured on the ticket's group.
 *
 * Called from the portal/agent ticket-create paths after the row is inserted.
 * Returns the chosen user_id and updates `tickets.assigned_to` + writes a
 * timeline entry; returns null when nothing changes (no group, group set to
 * 'manual', or the strategy + fallback found no eligible agent).
 *
 * Strategies:
 *   round_robin     — rotate sequentially through group members; remembers the
 *                     last picked agent on `groups.assign_last_user_id`.
 *   load_based      — pick the member with the fewest open tickets
 *                     (status NOT IN resolved/closed). Ties broken by user_id.
 *   skill_based     — pick a member whose skills cover every skill required by
 *                     the ticket type. Ties broken by load. Falls back to the
 *                     group's assign_fallback when no member qualifies.
 *   first_available — pick a member who currently has the app open in a
 *                     browser (heartbeat within the last 120s — see
 *                     user_presence), load-balanced. Falls back to
 *                     assign_fallback when nobody is online.
 *
 * Only agent / admin / power_user group members are considered — portal-role
 * users in a group are ignored. The eligible pool intentionally ignores the
 * online status for round_robin and load_based; presence only affects the
 * "first_available" strategy. Skill-based requires the skill match but does
 * NOT also require online status — a configurable combination is a follow-up.
 */
function autoAssignTicket(PDO $db, int $ticketId): ?int
{
    $tStmt = $db->prepare(
        'SELECT t.id, t.group_id, t.assigned_to, t.type_id,
                g.assign_strategy, g.assign_last_user_id, g.assign_fallback
         FROM tickets t
         LEFT JOIN `groups` g ON t.group_id = g.id
         WHERE t.id = ?'
    );
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch();
    if (!$ticket || $ticket['group_id'] === null || $ticket['assigned_to'] !== null) {
        return null;
    }

    $strategy = $ticket['assign_strategy'] ?? 'manual';
    if ($strategy === 'manual') {
        return null;
    }

    $groupId = (int) $ticket['group_id'];
    $typeId  = $ticket['type_id'] !== null ? (int) $ticket['type_id'] : null;

    $members = _autoAssignGroupMembers($db, $groupId);
    if (empty($members)) {
        return null;
    }

    $picked = null;
    if ($strategy === 'round_robin') {
        $picked = _autoAssignRoundRobin($members, $ticket['assign_last_user_id'] !== null ? (int) $ticket['assign_last_user_id'] : null);
    } elseif ($strategy === 'load_based') {
        $picked = _autoAssignLeastLoaded($db, $members);
    } elseif ($strategy === 'skill_based') {
        $picked = _autoAssignBySkill($db, $members, $typeId);
    } elseif ($strategy === 'ai_skill_based') {
        $picked = _autoAssignByAiSkill($db, $members, $ticketId);
    } elseif ($strategy === 'first_available') {
        $picked = _autoAssignFirstAvailable($db, $members);
    }

    if ($picked === null) {
        $fallback = $ticket['assign_fallback'] ?? 'load_based';
        if ($fallback === 'round_robin') {
            $picked = _autoAssignRoundRobin($members, $ticket['assign_last_user_id'] !== null ? (int) $ticket['assign_last_user_id'] : null);
        } elseif ($fallback === 'load_based') {
            $picked = _autoAssignLeastLoaded($db, $members);
        }
    }

    if ($picked === null) {
        return null;
    }

    $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$picked, $ticketId]);
    $db->prepare('UPDATE `groups` SET assign_last_user_id = ? WHERE id = ?')->execute([$picked, $groupId]);

    $nameStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $nameStmt->execute([$picked]);
    $u = $nameStmt->fetch();
    $agentName = $u ? trim($u['first_name'] . ' ' . $u['last_name']) : "User #{$picked}";
    $strategyLabel = [
        'round_robin'     => 'round-robin',
        'load_based'      => 'least-loaded',
        'skill_based'     => 'skill match',
        'ai_skill_based'  => 'AI skill match',
        'first_available' => 'first available',
    ][$strategy] ?? $strategy;

    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
    )->execute([
        $ticketId,
        'auto_assigned',
        "Auto-assigned to {$agentName} via {$strategyLabel}.",
    ]);

    return (int) $picked;
}

/**
 * Internal: returns sorted list of [id => int] for agent/admin/power_user
 * group members, ordered by user_id so round-robin picks are deterministic.
 */
function _autoAssignGroupMembers(PDO $db, int $groupId): array
{
    $stmt = $db->prepare(
        "SELECT u.id
         FROM group_user_map gum
         JOIN users u ON gum.user_id = u.id
         WHERE gum.group_id = ? AND " . staffRoleSqlIn('u.role') . "
         ORDER BY u.id"
    );
    $stmt->execute([$groupId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function _autoAssignRoundRobin(array $members, ?int $lastUserId): ?int
{
    if (empty($members)) {
        return null;
    }
    if ($lastUserId === null) {
        return $members[0];
    }
    $idx = array_search($lastUserId, $members, true);
    if ($idx === false) {
        return $members[0];
    }
    return $members[($idx + 1) % count($members)];
}

function _autoAssignLeastLoaded(PDO $db, array $members): ?int
{
    if (empty($members)) {
        return null;
    }
    $placeholders = implode(',', array_fill(0, count($members), '?'));
    $notClosed    = ticketStatusSqlIn(ticketClosedBucketSlugs(), 'status', true);
    $stmt = $db->prepare(
        "SELECT u.id, COALESCE(c.cnt, 0) AS open_count
         FROM users u
         LEFT JOIN (
             SELECT assigned_to, COUNT(*) AS cnt
             FROM tickets
             WHERE assigned_to IN ($placeholders)
               AND $notClosed
             GROUP BY assigned_to
         ) c ON c.assigned_to = u.id
         WHERE u.id IN ($placeholders)
         ORDER BY open_count ASC, u.id ASC
         LIMIT 1"
    );
    $stmt->execute(array_merge($members, $members));
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function _autoAssignBySkill(PDO $db, array $members, ?int $typeId): ?int
{
    if (empty($members) || $typeId === null) {
        return null;
    }
    $reqStmt = $db->prepare('SELECT skill_id FROM ticket_type_skill_map WHERE ticket_type_id = ?');
    $reqStmt->execute([$typeId]);
    $required = array_map('intval', $reqStmt->fetchAll(PDO::FETCH_COLUMN));
    if (empty($required)) {
        return null;
    }
    $mPlace = implode(',', array_fill(0, count($members), '?'));
    $sPlace = implode(',', array_fill(0, count($required), '?'));
    $stmt = $db->prepare(
        "SELECT user_id
         FROM user_skill_map
         WHERE user_id IN ($mPlace) AND skill_id IN ($sPlace)
         GROUP BY user_id
         HAVING COUNT(DISTINCT skill_id) = ?"
    );
    $stmt->execute(array_merge($members, $required, [count($required)]));
    $eligible = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (empty($eligible)) {
        return null;
    }
    return _autoAssignLeastLoaded($db, $eligible);
}

/**
 * AI-Skill-Based: read the classification persisted by classifyTicketWithAI()
 * (called eagerly via runPostTicketCreateHooks before auto-assign runs).
 * If the stored confidence ≥ threshold and at least one suggested skill
 * matches a group member, pick the least-loaded among them. Otherwise
 * return null and let the caller fall back to the group's fallback
 * strategy. AI failures / low confidence / disabled-feature all degrade
 * cleanly through the same null path.
 */
function _autoAssignByAiSkill(PDO $db, array $members, int $ticketId): ?int
{
    if (empty($members)) {
        return null;
    }
    $stmt = $db->prepare(
        "SELECT c.suggested_skill_ids, c.overridden_skill_ids, c.confidence
         FROM ai_classifications c
         JOIN tickets t ON t.ai_classification_id = c.id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $threshold = (float) getSetting('ai_confidence_threshold', '0.7');
    if ((float) $row['confidence'] < $threshold) {
        return null;
    }
    // Admin override (if any) wins over the AI's original suggestion
    $overridden = $row['overridden_skill_ids'] !== null && $row['overridden_skill_ids'] !== ''
        ? json_decode((string) $row['overridden_skill_ids'], true)
        : null;
    $skillIds = is_array($overridden) ? $overridden : json_decode((string) $row['suggested_skill_ids'], true);
    if (!is_array($skillIds) || empty($skillIds)) {
        return null;
    }
    $required = array_values(array_filter(array_map('intval', $skillIds)));
    if (empty($required)) {
        return null;
    }
    $mPlace = implode(',', array_fill(0, count($members), '?'));
    $sPlace = implode(',', array_fill(0, count($required), '?'));
    $matchStmt = $db->prepare(
        "SELECT user_id
         FROM user_skill_map
         WHERE user_id IN ($mPlace) AND skill_id IN ($sPlace)
         GROUP BY user_id
         HAVING COUNT(DISTINCT skill_id) = ?"
    );
    $matchStmt->execute(array_merge($members, $required, [count($required)]));
    $eligible = array_map('intval', $matchStmt->fetchAll(PDO::FETCH_COLUMN));
    if (empty($eligible)) {
        return null;
    }
    return _autoAssignLeastLoaded($db, $eligible);
}

/**
 * Run all post-create hooks on a freshly-created ticket. Order matters:
 *
 *   1. AI group routing ("No Wrong Door") — for ticket types with
 *      ai_route_group=1, AI picks the best group from all non-confidential
 *      groups and updates ticket.group_id BEFORE skill classification, so
 *      the skill candidate list is filtered against the chosen group.
 *   2. AI skill classification — writes suggested_skill_ids/sentiment so
 *      ai_skill_based routing has data to work with.
 *   3. Automations (ticket_created) — may set group_id, type_id, etc.
 *      based on subject/description rules.
 *   4. Auto-assign — needs group_id (set by steps 1/3) and may consult
 *      the classification (set by step 2).
 *
 * Single chokepoint so every creation path (portal, admin, API,
 * email-to-ticket, agent split) gets identical behaviour.
 *
 * Returns the auto-assigned user_id (if any) so callers can fire
 * notification emails. Classification side-effects (timeline entry,
 * sentiment, priority bump) happen as part of classifyTicketWithAI();
 * automation side-effects (timeline entries per action) happen inside
 * runAutomations().
 */
function runPostTicketCreateHooks(PDO $db, int $ticketId): ?int
{
    aiRouteTicketToGroup($ticketId);
    classifyTicketWithAI($ticketId);
    runAutomations($db, $ticketId, 'ticket_created');
    backfillTicketGroupFromDefault($db, $ticketId);
    return autoAssignTicket($db, $ticketId);
}

/**
 * Resolve which group a brand-new ticket should land in. Layered so
 * every creation path (portal, agent, admin, email, API, splits) can
 * call the same helper and never end up with a NULL group:
 *
 *   1. Caller's explicit choice (form field, API param) — wins outright.
 *   2. The ticket type's default group, if one is configured.
 *   3. The system-wide `default_group_id` setting (Admin → Settings →
 *      Ticket Routing Defaults). This is the "no ticket gets stuck"
 *      safety net.
 *   4. Last-ditch fallback: lowest-id existing group. Only fires if
 *      `default_group_id` is unset or points at a deleted group AND a
 *      group exists at all. Returns null only on a pristine install
 *      with zero groups.
 *
 * The returned id is verified to reference a real, currently-existing
 * group — a stale type.group_id or a stale setting pointing at a
 * deleted group falls through to the next layer instead of poisoning
 * the ticket with a dangling FK.
 */
function resolveTicketGroup(PDO $db, ?int $explicit, ?int $typeId): ?int
{
    $groupExists = static function (PDO $db, ?int $id): bool {
        if (!$id) return false;
        $stmt = $db->prepare('SELECT 1 FROM `groups` WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    };

    if ($groupExists($db, $explicit)) {
        return $explicit;
    }

    if ($typeId !== null) {
        $stmt = $db->prepare('SELECT group_id FROM ticket_types WHERE id = ?');
        $stmt->execute([$typeId]);
        $typeGroup = $stmt->fetchColumn();
        if ($typeGroup !== false && $typeGroup !== null && $typeGroup !== '') {
            $tg = (int) $typeGroup;
            if ($groupExists($db, $tg)) {
                return $tg;
            }
        }
    }

    $configured = getSetting('default_group_id', '');
    if ($configured !== '' && $configured !== null) {
        $cg = (int) $configured;
        if ($groupExists($db, $cg)) {
            return $cg;
        }
    }

    // Last-ditch fallback: pick the lowest-id existing group. Better
    // than NULL, and self-heals an install where the admin deleted
    // every group the default_group_id setting could have pointed at.
    $first = $db->query('SELECT id FROM `groups` ORDER BY id ASC LIMIT 1')->fetchColumn();
    return $first ? (int) $first : null;
}

/**
 * Keep a ticket's type in sync with its group after a group change.
 *
 * A ticket type maps 1:1 to a default group, so once an agent moves a ticket
 * into a group that is NOT the type's default group, the type no longer
 * describes where the ticket lives. Rather than silently carry a mismatched
 * type, clear it to "Not Set" (NULL). Writes a timeline entry and recalculates
 * SLA when it clears, and returns true so callers can refresh their UI.
 *
 * No-ops when: the ticket has no type, the type has no default group (so it can
 * coexist with any group), or the new group still matches the type's default.
 * Call this AFTER the ticket's group_id has been persisted, and — where the same
 * request may also set the type explicitly — AFTER that type change, so an
 * intentional type+group pairing is never clobbered.
 */
function clearTicketTypeIfGroupMismatch(PDO $db, int $ticketId, ?int $newGroupId, ?int $actorId): bool
{
    $stmt = $db->prepare(
        'SELECT tt.name AS type_name, tt.group_id AS type_group_id
         FROM tickets t
         JOIN ticket_types tt ON tt.id = t.type_id
         WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false; // ticket has no type (or the type row is gone) — nothing to reconcile
    }

    $typeGroupId = $row['type_group_id'] !== null ? (int) $row['type_group_id'] : null;
    if ($typeGroupId === null || $typeGroupId === $newGroupId) {
        return false; // type isn't pinned to a group, or the group still matches it
    }

    $db->prepare('UPDATE tickets SET type_id = NULL WHERE id = ?')->execute([$ticketId]);
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
    )->execute([
        $ticketId,
        $actorId,
        'type_changed',
        'Type cleared (was ' . $row['type_name'] . ") — the new group does not match the type's default group",
    ]);
    Sla::onTypeChanged($db, $ticketId, null);
    return true;
}

/**
 * Keep a ticket's group in sync with its type after a type change — the
 * mirror image of clearTicketTypeIfGroupMismatch(). A type maps 1:1 to a
 * default group, so when an agent re-types a ticket, the ticket should move
 * into the new type's group; otherwise it stays visible to the old group's
 * agents even though it no longer belongs to them. Writes a timeline entry,
 * notifies the new group, and returns true when it moves the ticket.
 *
 * No-ops when: the new type is NULL, the type has no default group (it can
 * coexist with any group), the type's group no longer exists, or the ticket
 * is already in that group. Call this AFTER the type change is persisted, and
 * NOT when the same request also sets the group explicitly — an intentional
 * type+group pairing wins.
 */
function syncTicketGroupToType(PDO $db, int $ticketId, ?int $newTypeId, ?int $actorId): bool
{
    if ($newTypeId === null) {
        return false;
    }
    $stmt = $db->prepare(
        'SELECT t.group_id AS ticket_group_id, tt.name AS type_name,
                tg.id AS type_group_id, tg.name AS type_group_name
         FROM tickets t
         JOIN ticket_types tt ON tt.id = ?
         LEFT JOIN `groups` tg ON tg.id = tt.group_id
         WHERE t.id = ?'
    );
    $stmt->execute([$newTypeId, $ticketId]);
    $row = $stmt->fetch();
    if (!$row || $row['type_group_id'] === null) {
        return false; // type isn't pinned to a (still-existing) group
    }

    $typeGroupId = (int) $row['type_group_id'];
    $oldGroupId  = $row['ticket_group_id'] !== null ? (int) $row['ticket_group_id'] : null;
    if ($typeGroupId === $oldGroupId) {
        return false; // already where the type says it belongs
    }

    $oldGroupName = 'None';
    if ($oldGroupId) {
        $g = $db->prepare('SELECT name FROM `groups` WHERE id = ?');
        $g->execute([$oldGroupId]);
        $oldGroupName = $g->fetchColumn() ?: 'None';
    }

    $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$typeGroupId, $ticketId]);
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
    )->execute([
        $ticketId,
        $actorId,
        'group_changed',
        'Group changed from ' . $oldGroupName . ' to ' . $row['type_group_name']
            . " to match the new type's default group",
    ]);
    notifyAssignedGroup($db, $ticketId, $typeGroupId);
    return true;
}

/**
 * Final NULL-group safety net inside runPostTicketCreateHooks(). AI
 * classification and "set group" automations run BEFORE this — so by
 * the time we get here, anything that's still NULL has slipped past
 * every other rule. Drop it into the configured default group so
 * autoAssignTicket() can route it instead of returning null.
 *
 * No-op if the ticket already has a group, or if no default group can
 * be resolved (zero-group install).
 */
function backfillTicketGroupFromDefault(PDO $db, int $ticketId): void
{
    $stmt = $db->prepare('SELECT group_id, type_id FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || $row['group_id'] !== null) {
        return;
    }
    $typeId = $row['type_id'] !== null ? (int) $row['type_id'] : null;
    $resolved = resolveTicketGroup($db, null, $typeId);
    if ($resolved === null) {
        return;
    }
    $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$resolved, $ticketId]);
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
    )->execute([
        $ticketId,
        'group_set_from_default',
        'No group was matched by ticket type, AI, or automations — routed to the system default group so the ticket does not sit in the no-group queue.',
    ]);
}

/**
 * First-Available routing: pick a group member who currently has the app
 * open in a browser. "Currently online" = a row in user_presence whose
 * last_seen is within the last 120 seconds — generous enough to cover
 * background-tab throttling (browsers slow setInterval to ~once per
 * minute when the tab isn't focused) so a minimized window or tabbed-away
 * agent still counts as online. Replaces the pre-2.21 is_available
 * toggle, which required agents to manually flip themselves available;
 * now availability is inferred from real activity.
 */
function _autoAssignFirstAvailable(PDO $db, array $members): ?int
{
    if (empty($members)) {
        return null;
    }
    $placeholders = implode(',', array_fill(0, count($members), '?'));
    $stmt = $db->prepare(
        "SELECT u.id
           FROM users u
           JOIN user_presence p ON p.user_id = u.id
          WHERE u.id IN ($placeholders)
            AND p.last_seen >= DATE_SUB(NOW(), INTERVAL 120 SECOND)
          ORDER BY u.id"
    );
    $stmt->execute($members);
    $available = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (empty($available)) {
        return null;
    }
    return _autoAssignLeastLoaded($db, $available);
}

/* ── AI ticket classification ─────────────────────────────────── */

/**
 * Classify a ticket via the configured AI provider.
 *
 * Persists the result in `ai_classifications`, points
 * `tickets.ai_classification_id` and `tickets.ai_sentiment` at it,
 * applies the optional sentiment-driven priority bump, and posts an
 * internal timeline entry. Returns the verdict array on success or
 * null when AI is disabled / the ticket type is confidential / the
 * provider call fails — callers always proceed with the fallback flow
 * regardless.
 *
 * Idempotent for the same ticket: re-running classification creates a
 * new row and re-points the ticket; the previous row stays in place so
 * we have history.
 *
 * @return array|null Verdict on success, null on skip/failure.
 */
function classifyTicketWithAI(int $ticketId): ?array
{
    $classifier = AIClassifierFactory::fromSettings();
    if ($classifier === null) {
        return null;
    }

    $db = Database::connect();
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.description, t.type_id, t.group_id, t.priority_id,
                tt.is_confidential AS type_is_confidential,
                COALESCE(tt.group_id, t.group_id) AS effective_group_id
         FROM tickets t
         LEFT JOIN ticket_types tt ON tt.id = t.type_id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        return null;
    }
    if ((int) ($ticket['type_is_confidential'] ?? 0) === 1) {
        // Hard rule: never send confidential ticket bodies to a third party.
        return null;
    }

    // Candidate skill list = global skills + skills owned by the ticket's
    // destination group (same set the manager UI exposes).
    $groupId = $ticket['effective_group_id'] !== null ? (int) $ticket['effective_group_id'] : null;
    if ($groupId !== null) {
        $sStmt = $db->prepare(
            "SELECT id, name, description FROM agent_skills
             WHERE group_id IS NULL OR group_id = ?
             ORDER BY (group_id IS NULL) DESC, sort_order, name"
        );
        $sStmt->execute([$groupId]);
    } else {
        $sStmt = $db->query(
            "SELECT id, name, description FROM agent_skills WHERE group_id IS NULL ORDER BY sort_order, name"
        );
    }
    $skills = $sStmt->fetchAll();

    $maxTokens  = (int) getSetting('ai_max_tokens', '500');
    $timeoutSec = (int) getSetting('ai_timeout_seconds', '5');

    try {
        $verdict = $classifier->classify(
            (string) $ticket['subject'],
            (string) $ticket['description'],
            $skills,
            $maxTokens,
            $timeoutSec
        );
    } catch (\Throwable $e) {
        // Soft-fail: log but never break ticket creation.
        error_log('[AI classify] ticket #' . $ticketId . ' failed: ' . $e->getMessage());
        return null;
    }

    $providerKey = getSetting('ai_provider', 'anthropic');
    $modelKey    = $providerKey === 'anthropic'
        ? (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5')
        : (string) getSetting('ai_openai_model',    'gpt-4o-mini');

    $insertStmt = $db->prepare(
        'INSERT INTO ai_classifications
            (ticket_id, provider, model, suggested_skill_ids, confidence,
             sentiment, reasoning, raw_output, latency_ms, prompt_tokens, output_tokens)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStmt->execute([
        $ticketId,
        (string) $providerKey,
        $modelKey,
        json_encode($verdict['skill_ids'] ?? [], JSON_UNESCAPED_SLASHES),
        (float) ($verdict['confidence'] ?? 0),
        (string) ($verdict['sentiment'] ?? 'neutral'),
        (string) ($verdict['reasoning'] ?? ''),
        json_encode($verdict['raw'] ?? null, JSON_UNESCAPED_SLASHES),
        (int) ($verdict['latency_ms']    ?? 0),
        (int) ($verdict['prompt_tokens'] ?? 0),
        (int) ($verdict['output_tokens'] ?? 0),
    ]);
    $classificationId = (int) $db->lastInsertId();

    $db->prepare(
        'UPDATE tickets SET ai_classification_id = ?, ai_sentiment = ? WHERE id = ?'
    )->execute([$classificationId, (string) ($verdict['sentiment'] ?? 'neutral'), $ticketId]);

    // Sentiment-driven priority bump (configurable). Only fires when:
    //   - the toggle is on, AND
    //   - sentiment is angry or urgent, AND
    //   - the current priority is below the highest-sort_order priority
    if (getSetting('ai_sentiment_priority_bump', '1') === '1'
        && in_array($verdict['sentiment'] ?? 'neutral', ['angry', 'urgent'], true)) {
        _maybeBumpPriorityFromSentiment($db, $ticketId, (string) $verdict['sentiment']);
    }

    // Internal timeline entry — never visible to portal users.
    $skillNames = [];
    if (!empty($verdict['skill_ids']) && !empty($skills)) {
        $byId = [];
        foreach ($skills as $s) { $byId[(int) $s['id']] = $s['name']; }
        foreach ($verdict['skill_ids'] as $sid) {
            $sid = (int) $sid;
            if (isset($byId[$sid])) { $skillNames[] = $byId[$sid]; }
        }
    }
    $confPct = (int) round(((float) ($verdict['confidence'] ?? 0)) * 100);
    $details = 'AI ('
        . $providerKey . '/' . $modelKey . ') classified as: '
        . (empty($skillNames) ? '(no skill match)' : implode(', ', $skillNames))
        . ' — ' . $confPct . '% confidence, sentiment ' . ($verdict['sentiment'] ?? 'neutral')
        . '. ' . ($verdict['latency_ms'] ?? 0) . 'ms.';
    $db->prepare(
        "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, NULL, 'ai_classified', ?, 1)"
    )->execute([$ticketId, $details]);

    return $verdict;
}

/**
 * Check whether a not-yet-saved ticket looks like a duplicate of an
 * existing OPEN ticket at the same branch. Used by portal, agent, and
 * floor-quick-create flows so people don't open the same ticket twice
 * (different staff, different shifts, didn't scroll the open queue).
 *
 * Hard rules:
 *   - Confidential ticket types are NEVER scanned and NEVER appear as
 *     candidates — regardless of the new ticket's type. We don't send
 *     those bodies to a third-party AI under any circumstance.
 *   - Returns [] if the feature is off for this type, AI is disabled,
 *     no candidates exist, or the provider errors. Never throws.
 *
 * Result shape:
 *   [
 *     'matches' => [
 *       [
 *         'ticket_id'   => int,
 *         'subject'     => string,
 *         'status'      => string,
 *         'created_at'  => string,
 *         'requester'   => string,   // first name + last initial (privacy)
 *         'type_name'   => string,
 *         'confidence'  => float,
 *         'reasoning'   => string,
 *       ],
 *       ...
 *     ],
 *     'threshold' => float,
 *   ]
 *
 * @param int    $userId     Auth::id() of the user about to file
 * @param int    $typeId     Selected ticket type
 * @param ?int   $locationId Selected branch (NULL = scan all branches)
 * @param string $subject    Subject the user typed
 * @param string $body       Description the user typed
 */
function checkTicketDuplicates(int $userId, int $typeId, ?int $locationId, string $subject, string $body): array
{
    $empty = ['matches' => [], 'threshold' => 0.0];
    if ($subject === '' || mb_strlen(trim($subject)) < 3) { return $empty; }
    if ($typeId <= 0) { return $empty; }

    $db = Database::connect();

    $tStmt = $db->prepare(
        'SELECT id, name, ai_dup_check_enabled, ai_dup_threshold, is_confidential
         FROM ticket_types WHERE id = ?'
    );
    $tStmt->execute([$typeId]);
    $type = $tStmt->fetch();
    if (!$type)                                            { return $empty; }
    if ((int) $type['is_confidential'] === 1)              { return $empty; } // safety belt
    if ((int) $type['ai_dup_check_enabled'] !== 1)         { return $empty; }

    $threshold = (float) ($type['ai_dup_threshold'] ?? 0.75);
    if ($threshold < 0.0) { $threshold = 0.0; }
    if ($threshold > 1.0) { $threshold = 1.0; }

    $classifier = AIClassifierFactory::fromSettings();
    if (!$classifier instanceof AIClassifier) { return ['matches' => [], 'threshold' => $threshold]; }

    // Candidates: open tickets at the same branch, NOT a confidential type,
    // opened in the last 14 days, capped at 30. Same branch = same location_id;
    // when the user has no location set we fall back to "any branch" so we
    // still catch duplicates rather than silently skipping the check.
    $params = [];
    $where  = [
        ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status', true),
        "t.merged_into_ticket_id IS NULL",
        "COALESCE(tt.is_confidential, 0) = 0",
        "t.created_at >= NOW() - INTERVAL 14 DAY",
    ];
    if ($locationId !== null) {
        $where[]  = 't.location_id = ?';
        $params[] = $locationId;
    }
    $whereSql = implode(' AND ', $where);

    $cStmt = $db->prepare(
        "SELECT t.id, t.subject, t.description, t.status, t.created_at,
                tt.name AS type_name,
                u.first_name, u.last_name
         FROM tickets t
         LEFT JOIN ticket_types tt ON tt.id = t.type_id
         LEFT JOIN users u         ON u.id  = t.created_by
         WHERE {$whereSql}
         ORDER BY t.created_at DESC
         LIMIT 30"
    );
    $cStmt->execute($params);
    $candidates = $cStmt->fetchAll();
    if (empty($candidates)) { return ['matches' => [], 'threshold' => $threshold]; }

    $maxTokens  = max(600, (int) getSetting('ai_max_tokens', '500'));
    $timeoutSec = (int) getSetting('ai_timeout_seconds', '5');

    try {
        $verdict = $classifier->findDuplicates(
            $subject,
            $body,
            array_map(static fn($c) => [
                'id'          => (int) $c['id'],
                'subject'     => (string) $c['subject'],
                'description' => (string) ($c['description'] ?? ''),
                'created_at'  => (string) ($c['created_at'] ?? ''),
            ], $candidates),
            $maxTokens,
            $timeoutSec
        );
    } catch (\Throwable $e) {
        error_log('[AI dup-check] type ' . $typeId . ' user ' . $userId . ' failed: ' . $e->getMessage());
        return ['matches' => [], 'threshold' => $threshold];
    }

    $byId = [];
    foreach ($candidates as $c) {
        $byId[(int) $c['id']] = $c;
    }

    $hits = [];
    foreach (($verdict['matches'] ?? []) as $m) {
        if ($m['confidence'] < $threshold)        { continue; }
        if (!isset($byId[$m['ticket_id']]))       { continue; }
        $row = $byId[$m['ticket_id']];
        $first = trim((string) ($row['first_name'] ?? ''));
        $last  = trim((string) ($row['last_name']  ?? ''));
        $requester = $first;
        if ($last !== '') { $requester .= ' ' . mb_substr($last, 0, 1) . '.'; }
        $hits[] = [
            'ticket_id'  => (int) $m['ticket_id'],
            'subject'    => (string) $row['subject'],
            'status'     => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'requester'  => trim($requester),
            'type_name'  => (string) ($row['type_name'] ?? ''),
            'confidence' => (float) $m['confidence'],
            'reasoning'  => (string) ($m['reasoning'] ?? ''),
        ];
    }

    // Audit log — even no-match calls go in the table for tuning.
    $providerKey = (string) getSetting('ai_provider', 'anthropic');
    $modelKey    = $providerKey === 'anthropic'
        ? (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5')
        : (string) getSetting('ai_openai_model',    'gpt-4o-mini');
    $candidateIds = array_map(static fn($c) => (int) $c['id'], $candidates);

    try {
        $db->prepare(
            'INSERT INTO ai_duplicate_classifications
                (user_id, type_id, location_id, provider, model, subject,
                 candidate_ticket_ids, matches, threshold, decision,
                 raw_output, latency_ms, prompt_tokens, output_tokens)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $typeId,
            $locationId,
            $providerKey,
            $modelKey,
            mb_substr($subject, 0, 255),
            json_encode($candidateIds, JSON_UNESCAPED_SLASHES),
            json_encode($hits, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $threshold,
            empty($hits) ? 'no_match' : 'suggested',
            json_encode($verdict['raw'] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (int) ($verdict['latency_ms']    ?? 0),
            (int) ($verdict['prompt_tokens'] ?? 0),
            (int) ($verdict['output_tokens'] ?? 0),
        ]);
    } catch (\Throwable $e) {
        error_log('[AI dup-check] audit insert failed: ' . $e->getMessage());
    }

    return ['matches' => $hits, 'threshold' => $threshold];
}

/**
 * Fetch a small, presentation-safe view of a candidate-duplicate ticket
 * for the modal preview shown on the create form. Returns null when the
 * ticket should NOT be visible to the requesting user under the same
 * rules used by the dup-check candidate query (non-confidential type,
 * not-merged, recent, same branch for portal users).
 *
 * Result shape:
 *   [
 *     'id'              => int,
 *     'subject'         => string,
 *     'status'          => string,
 *     'created_at'      => string,
 *     'type_name'       => string,
 *     'requester'       => string,        // first + last initial (portal) or full (agent)
 *     'description_html'=> string,        // capped at 4 KB
 *     'comment_count'   => int,
 *   ]
 */
function getDupPreviewTicket(int $userId, int $ticketId, bool $agentScope): ?array
{
    if ($ticketId <= 0) { return null; }

    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.description, t.status, t.created_at,
                t.location_id, t.merged_into_ticket_id,
                tt.name AS type_name, COALESCE(tt.is_confidential, 0) AS type_is_confidential,
                u.first_name, u.last_name
         FROM tickets t
         LEFT JOIN ticket_types tt ON tt.id = t.type_id
         LEFT JOIN users u         ON u.id  = t.created_by
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row)                                                  { return null; }
    if ((int) $row['type_is_confidential'] === 1)               { return null; }
    if ($row['merged_into_ticket_id'] !== null)                 { return null; }
    if (in_array($row['status'], ticketClosedBucketSlugs(), true)) { return null; }

    if (!$agentScope) {
        // Portal users: only show tickets at the user's own branch.
        $userStmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
        $userStmt->execute([$userId]);
        $userLocationId = $userStmt->fetchColumn();
        $userLocationId = $userLocationId ? (int) $userLocationId : null;
        if ($userLocationId === null) { return null; }
        if ((int) ($row['location_id'] ?? 0) !== $userLocationId) { return null; }
    }

    $first = trim((string) ($row['first_name'] ?? ''));
    $last  = trim((string) ($row['last_name']  ?? ''));
    if ($agentScope) {
        $requester = trim($first . ' ' . $last);
    } else {
        $requester = $first;
        if ($last !== '') { $requester .= ' ' . mb_substr($last, 0, 1) . '.'; }
        $requester = trim($requester);
    }

    $cnt = $db->prepare(
        "SELECT COUNT(*) FROM ticket_timeline WHERE ticket_id = ? AND action = 'comment' AND is_internal = 0"
    );
    $cnt->execute([$ticketId]);
    $commentCount = (int) $cnt->fetchColumn();

    return [
        'id'               => (int) $row['id'],
        'subject'          => (string) $row['subject'],
        'status'           => (string) $row['status'],
        'created_at'       => (string) $row['created_at'],
        'type_name'        => (string) ($row['type_name'] ?? ''),
        'requester'        => $requester,
        'description_html' => sanitizeRichHtml(mb_substr((string) ($row['description'] ?? ''), 0, 4000)),
        'comment_count'    => $commentCount,
    ];
}

/**
 * Record that the requester saw AI duplicate-warning suggestions for this
 * ticket and chose to file it anyway. Writes a single internal timeline
 * entry on the new ticket so admins can audit overrides, and links the
 * matching `ai_duplicate_classifications` row (if found) to the new
 * ticket id with decision='suppressed'.
 *
 * Safe to call with an empty / malformed CSV — does nothing in that case.
 */
function recordDupOverrideOnNewTicket(PDO $db, int $newTicketId, int $userId, string $matchedIdsCsv): void
{
    $ids = array_values(array_unique(array_filter(array_map(
        static fn($s) => (int) trim($s),
        explode(',', $matchedIdsCsv)
    ), static fn($i) => $i > 0)));
    if (empty($ids)) { return; }

    // Resolve to subjects so the timeline entry is human-readable, not
    // just bare numbers. Skip any IDs that don't exist (stale form submit).
    $place = implode(',', array_fill(0, count($ids), '?'));
    $sStmt = $db->prepare("SELECT id, subject FROM tickets WHERE id IN ({$place})");
    $sStmt->execute($ids);
    $rows = $sStmt->fetchAll();
    if (empty($rows)) { return; }

    $links = array_map(
        static fn($r) => '#' . (int) $r['id'] . ' "' . mb_substr((string) $r['subject'], 0, 80) . '"',
        $rows
    );
    $details = 'AI flagged this as a possible duplicate of ' . implode(', ', $links)
             . ' &mdash; submitter chose to file anyway.';

    $db->prepare(
        "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, NULL, 'ai_duplicate_warned', ?, 1)"
    )->execute([$newTicketId, $details]);

    // Link the audit row (most recent 'suggested' for this user, last 5 min)
    // to the new ticket so admins can correlate the AI verdict with the
    // ticket that ultimately got created.
    try {
        $db->prepare(
            "UPDATE ai_duplicate_classifications
             SET chosen_ticket_id = ?, decision = 'suppressed'
             WHERE user_id = ? AND decision = 'suggested'
               AND created_at >= NOW() - INTERVAL 5 MINUTE
             ORDER BY id DESC LIMIT 1"
        )->execute([$newTicketId, $userId]);
    } catch (\Throwable $e) {
        error_log('[AI dup-check] audit link failed: ' . $e->getMessage());
    }
}

/**
 * "No Wrong Door" group routing. Run for ticket types flagged with
 * ai_route_group=1: ask the AI to pick the best group from every
 * non-confidential group (using the description column to ground the
 * pick) and move the ticket there if confidence clears the threshold.
 *
 * Always writes an `ai_group_classifications` row when AI is reached —
 * even when AI declines or confidence is too low — so admins have a full
 * audit trail of why each ticket did or didn't get routed.
 *
 * Soft-fail: returns null on any provider error; the ticket stays in its
 * current group (the type's default — typically the "No Wrong Door"
 * queue itself) so an agent can route it manually.
 *
 * Returns the applied group id on success, null on every other path
 * (AI off, type not flagged, confidence too low, no candidate groups,
 * provider error).
 */
function aiRouteTicketToGroup(int $ticketId): ?int
{
    $classifier = AIClassifierFactory::fromSettings();
    if ($classifier === null) {
        return null;
    }

    $db = Database::connect();
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.description, t.group_id AS current_group_id,
                tt.id AS type_id, tt.ai_route_group, tt.is_confidential AS type_is_confidential
         FROM tickets t
         LEFT JOIN ticket_types tt ON tt.id = t.type_id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        return null;
    }
    if ((int) ($ticket['ai_route_group'] ?? 0) !== 1) {
        return null; // Type isn't flagged for AI routing
    }
    if ((int) ($ticket['type_is_confidential'] ?? 0) === 1) {
        // Hard rule: never send confidential ticket bodies to a third party.
        return null;
    }

    // Candidate groups: every non-confidential group with a usable
    // description. We send the description to the AI as the routing
    // signal — a group with an empty description gives the model
    // nothing to work with, so skip it instead of polluting the prompt.
    $groups = $db->query(
        "SELECT id, name, description
         FROM `groups`
         WHERE COALESCE(is_confidential, 0) = 0
           AND description IS NOT NULL
           AND TRIM(description) <> ''
         ORDER BY sort_order, name"
    )->fetchAll();

    if (empty($groups)) {
        // Nothing to choose from — log a no-op classification so admins
        // can see why routing didn't fire (likely: descriptions missing).
        error_log('[AI route group] ticket #' . $ticketId . ': no candidate groups (none with description)');
        return null;
    }

    $maxTokens  = (int) getSetting('ai_max_tokens', '500');
    $timeoutSec = (int) getSetting('ai_timeout_seconds', '5');
    $threshold  = (float) getSetting('ai_confidence_threshold', '0.7');

    try {
        $verdict = $classifier->classifyGroup(
            (string) $ticket['subject'],
            (string) $ticket['description'],
            $groups,
            $maxTokens,
            $timeoutSec
        );
    } catch (\Throwable $e) {
        error_log('[AI route group] ticket #' . $ticketId . ' failed: ' . $e->getMessage());
        return null;
    }

    $providerKey = (string) getSetting('ai_provider', 'anthropic');
    $modelKey    = $providerKey === 'anthropic'
        ? (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5')
        : (string) getSetting('ai_openai_model',    'gpt-4o-mini');

    $suggestedGroupId = $verdict['group_id'] !== null ? (int) $verdict['group_id'] : null;
    $confidence       = (float) ($verdict['confidence'] ?? 0);
    $shouldApply      = $suggestedGroupId !== null
        && $confidence >= $threshold
        && $suggestedGroupId !== (int) ($ticket['current_group_id'] ?? 0);
    $appliedGroupId   = $shouldApply ? $suggestedGroupId : null;

    $candidateIds = array_map(static fn($g) => (int) $g['id'], $groups);

    $db->prepare(
        'INSERT INTO ai_group_classifications
            (ticket_id, provider, model, candidate_group_ids, suggested_group_id,
             applied_group_id, confidence, reasoning, raw_output,
             latency_ms, prompt_tokens, output_tokens)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $ticketId,
        $providerKey,
        $modelKey,
        json_encode($candidateIds, JSON_UNESCAPED_SLASHES),
        $suggestedGroupId,
        $appliedGroupId,
        $confidence,
        (string) ($verdict['reasoning'] ?? ''),
        json_encode($verdict['raw'] ?? null, JSON_UNESCAPED_SLASHES),
        (int) ($verdict['latency_ms']    ?? 0),
        (int) ($verdict['prompt_tokens'] ?? 0),
        (int) ($verdict['output_tokens'] ?? 0),
    ]);
    $classificationId = (int) $db->lastInsertId();

    $db->prepare('UPDATE tickets SET ai_group_classification_id = ? WHERE id = ?')
        ->execute([$classificationId, $ticketId]);

    // Resolve names for the timeline message
    $groupNameById = [];
    foreach ($groups as $g) { $groupNameById[(int) $g['id']] = $g['name']; }
    $confPct = (int) round($confidence * 100);

    if ($shouldApply) {
        $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')
            ->execute([$appliedGroupId, $ticketId]);

        $details = 'AI (' . $providerKey . '/' . $modelKey . ') routed to group "'
            . ($groupNameById[$appliedGroupId] ?? ('#' . $appliedGroupId)) . '"'
            . ' — ' . $confPct . '% confidence. '
            . ($verdict['reasoning'] !== '' ? '"' . $verdict['reasoning'] . '"' : '');
        $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
             VALUES (?, NULL, 'ai_group_routed', ?, 1)"
        )->execute([$ticketId, $details]);

        return $appliedGroupId;
    }

    // No-op cases get a different timeline entry so admins can spot the
    // pattern (low confidence vs. AI returned same group vs. AI declined).
    if ($suggestedGroupId === null) {
        $details = 'AI (' . $providerKey . '/' . $modelKey . ') could not confidently match a group'
            . ' — ' . $confPct . '% confidence; ticket left in its current queue.'
            . ($verdict['reasoning'] !== '' ? ' "' . $verdict['reasoning'] . '"' : '');
    } elseif ($suggestedGroupId === (int) ($ticket['current_group_id'] ?? 0)) {
        $details = 'AI (' . $providerKey . '/' . $modelKey . ') confirmed current group'
            . ' (' . ($groupNameById[$suggestedGroupId] ?? ('#' . $suggestedGroupId)) . ')'
            . ' — ' . $confPct . '% confidence.';
    } else {
        $details = 'AI (' . $providerKey . '/' . $modelKey . ') suggested "'
            . ($groupNameById[$suggestedGroupId] ?? ('#' . $suggestedGroupId)) . '"'
            . ' but ' . $confPct . '% confidence is below the ' . (int) round($threshold * 100) . '% threshold;'
            . ' ticket left in its current queue.';
    }
    $db->prepare(
        "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, NULL, 'ai_group_routing_skipped', ?, 1)"
    )->execute([$ticketId, $details]);

    return null;
}

/**
 * Bump a ticket's priority up one notch when AI flags angry/urgent
 * sentiment. Idempotent — never bumps past the highest-sort_order
 * priority, never re-bumps an already-handled ticket within the
 * same classification (timeline marker prevents loops).
 */
function _maybeBumpPriorityFromSentiment(PDO $db, int $ticketId, string $sentiment): void
{
    // Already bumped for this ticket recently? Skip.
    $dedup = $db->prepare(
        "SELECT 1 FROM ticket_timeline WHERE ticket_id = ? AND action = 'ai_priority_bumped' LIMIT 1"
    );
    $dedup->execute([$ticketId]);
    if ($dedup->fetchColumn()) {
        return;
    }

    $tStmt = $db->prepare('SELECT priority_id FROM tickets WHERE id = ?');
    $tStmt->execute([$ticketId]);
    $current = $tStmt->fetchColumn();
    $current = $current !== false ? (int) $current : 0;

    // Priorities ordered by sort_order — pick the next one above current
    $allP = $db->query('SELECT id, name, sort_order FROM ticket_priorities ORDER BY sort_order ASC')->fetchAll();
    if (empty($allP)) {
        return;
    }

    $currentSort = -1;
    foreach ($allP as $p) {
        if ((int) $p['id'] === $current) { $currentSort = (int) $p['sort_order']; break; }
    }
    // If no current priority, jump to the highest-sort priority. Otherwise
    // pick the immediate-next priority above the current one.
    $next = null;
    if ($currentSort === -1) {
        $next = end($allP) ?: null;
    } else {
        foreach ($allP as $p) {
            if ((int) $p['sort_order'] > $currentSort) {
                $next = $p; break;
            }
        }
    }
    if (!$next || (int) $next['id'] === $current) {
        return;
    }

    $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')
       ->execute([(int) $next['id'], $ticketId]);
    $db->prepare(
        "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, NULL, 'ai_priority_bumped', ?, 1)"
    )->execute([
        $ticketId,
        'AI sentiment "' . $sentiment . '" bumped priority to "' . $next['name'] . '".'
    ]);
}

/* ── Group manager / skill ownership helpers ──────────────────── */

/**
 * Returns the IDs of every group where $userId is flagged as a manager.
 * Admins implicitly manage every group, but this helper returns only the
 * explicit memberships so route-level "did the user actually get
 * delegated?" checks stay honest. Use canManageGroupSkills() for the
 * admin-bypass version.
 */
function userManagedGroupIds(int $userId): array
{
    $stmt = Database::connect()->prepare(
        'SELECT group_id FROM group_user_map WHERE user_id = ? AND is_manager = 1'
    );
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Authorisation gate for everything a group manager can do (assign
 * skills to their members, edit skills they own, etc.).
 *
 * Admins always pass. Otherwise the user must hold the is_manager
 * flag on the membership row for $groupId.
 */
function canManageGroupSkills(int $userId, int $groupId): bool
{
    if (Auth::isAdmin()) {
        return true;
    }
    $stmt = Database::connect()->prepare(
        'SELECT is_manager FROM group_user_map WHERE user_id = ? AND group_id = ?'
    );
    $stmt->execute([$userId, $groupId]);
    return (int) ($stmt->fetchColumn() ?: 0) === 1;
}

/**
 * Can $userId edit the skill row $skillId?
 *
 * Admins always pass. For non-admins, the skill must be group-scoped
 * (agent_skills.group_id IS NOT NULL) AND the user must manage that
 * group. Global skills (group_id NULL) are admin-only by design — they
 * represent the system-wide vocabulary.
 */
function canEditSkill(int $userId, int $skillId): bool
{
    if (Auth::isAdmin()) {
        return true;
    }
    $stmt = Database::connect()->prepare('SELECT group_id FROM agent_skills WHERE id = ?');
    $stmt->execute([$skillId]);
    $gid = $stmt->fetchColumn();
    if ($gid === false || $gid === null) {
        return false;
    }
    return canManageGroupSkills($userId, (int) $gid);
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
        'status'       => 'Status',
        'priority'     => 'Priority',
        'type'         => 'Type',
        'agent'        => 'Assigned To',
        'group'        => 'Group',
        'creator'      => 'Created By',
        'location'     => 'Location',
        'sla'          => 'SLA',
        'created_at'   => 'Created',
        'due_date'     => 'Due',
        'confidential' => 'Confidential',
    ];
}

/** Columns hidden by default — users must opt-in via the Columns picker. */
function ticketColumnsHiddenByDefault(): array
{
    return ['confidential'];
}

function getUserColumns(int $userId): array
{
    $defaults = array_diff(array_keys(ticketColumnDefinitions()), ticketColumnsHiddenByDefault());
    $json = getSetting("ticket_columns:{$userId}", '');
    if ($json === '') {
        $cols = $defaults;
    } else {
        $decoded = json_decode($json, true);
        $cols = is_array($decoded) ? $decoded : $defaults;
    }
    // SLA disabled site-wide — never expose the SLA column.
    if (!slaEnabled()) {
        $cols = array_values(array_filter($cols, static fn ($c) => $c !== 'sla'));
    }
    return $cols;
}

function setUserColumns(int $userId, array $columns): void
{
    $valid = array_keys(ticketColumnDefinitions());
    $columns = array_values(array_intersect($columns, $valid));
    setSetting("ticket_columns:{$userId}", json_encode($columns));
}

/* ── Ticket list view-style preference ───────────────────────── */

/**
 * Available ticket-list layouts. 'table' is the classic resizable grid;
 * 'inbox' is the "Compact" email-style two-column (From / Subject) list with
 * a hover detail card. The stored value stays 'inbox' for backwards
 * compatibility; only the label was renamed to "Compact".
 * Keyed by the value stored in `ticket_view:{userId}`.
 */
function ticketViewModes(): array
{
    return [
        'table' => 'Table',
        'inbox' => 'Compact',
        'card'  => 'Card',
    ];
}

/**
 * Human-friendly relative time, e.g. "3 hours ago" (past) or "in 7 days"
 * (future). Accepts a unix timestamp or a date string. Empty input → ''.
 */
function humanRelativeTime($when): string
{
    if ($when === null || $when === '') {
        return '';
    }
    $ts = is_numeric($when) ? (int) $when : strtotime((string) $when);
    if (!$ts) {
        return '';
    }
    $diff   = $ts - time();
    $future = $diff > 0;
    $s      = abs($diff);
    foreach ([[31536000, 'year'], [2592000, 'month'], [604800, 'week'], [86400, 'day'], [3600, 'hour'], [60, 'minute']] as [$secs, $label]) {
        if ($s >= $secs) {
            $n   = (int) floor($s / $secs);
            $txt = $n . ' ' . $label . ($n === 1 ? '' : 's');
            return $future ? 'in ' . $txt : $txt . ' ago';
        }
    }
    return $future ? 'in a few seconds' : 'just now';
}

/** Up-to-two-letter initials from a person's display name (e.g. "Kim Sachs" → "KS"). */
function nameInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$parts) {
        return '?';
    }
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** A user's chosen ticket-list layout; defaults to the classic table. */
function getUserTicketView(int $userId): string
{
    $view = getSetting("ticket_view:{$userId}", 'table');
    return array_key_exists($view, ticketViewModes()) ? $view : 'table';
}

function setUserTicketView(int $userId, string $view): void
{
    if (!array_key_exists($view, ticketViewModes())) {
        $view = 'table';
    }
    setSetting("ticket_view:{$userId}", $view);
}

/* ── "Tickets by user" page (from the inbox-view person card) ─── */

/**
 * Open tickets relevant to one user: tickets they created, plus open tickets
 * where they were @mentioned (notifications.type = 'mention'). Scoped to what
 * the viewing staff member is allowed to see. Rows match the ticket-list shape,
 * newest first. A row is a "mention" (not their own ticket) precisely when its
 * created_by is not the target user — the WHERE only admits created-or-mentioned.
 */
function userOpenTicketsForStaff(PDO $db, int $targetUserId, int $viewerId, ?string $viewerRole): array
{
    $vis  = ticketStaffVisibilitySql($db, $viewerId, $viewerRole, 't');
    $open = ticketStatusSqlIn(ticketOpenBucketSlugs(), 't.status');
    $sql = "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                tt.name AS type_name, tt.color AS type_color,
                tt.is_confidential AS type_confidential, tt.group_id AS type_group_id,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
            FROM tickets t
            LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
            LEFT JOIN ticket_types tt      ON t.type_id     = tt.id
            LEFT JOIN users a              ON t.assigned_to  = a.id
            WHERE {$open}
              AND {$vis['sql']}
              AND (t.created_by = ?
                   OR t.id IN (SELECT ticket_id FROM notifications WHERE user_id = ? AND type = 'mention'))
            ORDER BY t.created_at DESC";
    $params = array_merge($vis['params'], [$targetUserId, $targetUserId]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Render the "open tickets for this user" page. $base is the calling section's
 * ticket base path ('/agent/tickets' or '/admin/tickets'); the route guard has
 * already run. Mirrors the ticket lists' confidential-redaction context.
 */
function renderTicketsByUserPage(int $targetUserId, string $base): void
{
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT id, first_name, last_name, email FROM users WHERE id = ?');
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch();
    if (!$targetUser) {
        flash('error', 'That user no longer exists.');
        redirect($base);
        return;
    }

    $tickets = userOpenTicketsForStaff($db, $targetUserId, (int) Auth::id(), Auth::role());

    // Confidential-redaction context, same as the ticket lists build.
    $confidentialTypeIds = array_map(
        'intval',
        $db->query('SELECT id FROM ticket_types WHERE is_confidential = 1 AND group_id IS NOT NULL')
           ->fetchAll(PDO::FETCH_COLUMN)
    );
    $adminGroupIds = [];
    if ($confidentialTypeIds) {
        $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gs->execute([(int) Auth::id()]);
        $adminGroupIds = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));
    }

    render('staff/tickets-by-user', [
        'targetUser'          => $targetUser,
        'targetUserId'        => $targetUserId,
        'tickets'             => $tickets,
        'base'                => $base,
        'confidentialTypeIds' => $confidentialTypeIds,
        'adminGroupIds'       => $adminGroupIds,
    ]);
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
        // Contains HTML markup — sanitize the rich text before it reaches mail.
        return sanitizeRichHtml($content);
    }
    // Plain text — escape entities and preserve line breaks
    return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
}

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = '', ?int $ticketId = null): string|false
{
    // Outbound-mail kill switch. Dev and test instances set MAIL_ENABLED=false
    // in .env so running the suite (or local dev) never delivers real mail to
    // real recipients — the helpdesk DB carries live SMTP credentials, so a
    // single test ticket would otherwise email every member of a notified group.
    if (env('MAIL_ENABLED', 'true') === 'false') {
        $logDir = ROOT_DIR . '/storage/logs';
        if (is_dir($logDir)) {
            file_put_contents(
                $logDir . '/smtp.log',
                sprintf("[%s] sendMail() SKIPPED — MAIL_ENABLED=false — to=%s subject=%s\n",
                    date('Y-m-d H:i:s'), $toEmail, $subject),
                FILE_APPEND | LOCK_EX
            );
        }
        return false;
    }

    $host = getSetting('smtp_host');
    if ($host === '') {
        return false; // SMTP not configured — silently skip
    }

    $port       = (int) getSetting('smtp_port', '587');
    $encryption = getSetting('smtp_encryption', 'tls');
    $username   = getSetting('smtp_username');
    $password   = getSetting('smtp_password');
    $fromAddr   = getSetting('mail_from_address');
    $fromName   = getSetting('mail_from_name', 'OpenHelpDesk');

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
            // Trust model for the SMTP debug log:
            //   - Stays at DEBUG_SERVER. At this level PHPMailer's SMTP::client_send()
            //     replaces AUTH LOGIN / AUTH PLAIN / XOAUTH2 payloads with the literal
            //     string "[credentials hidden]" (vendor/phpmailer/phpmailer/src/SMTP.php).
            //     Bumping this to DEBUG_LOWLEVEL would defeat that and write the
            //     base64-encoded credentials to disk — DO NOT raise it.
            //   - As a belt-and-braces measure we also scrub the configured password
            //     literal from every debug line, in case a misbehaving server ever
            //     echoes it back in a banner, error, or response trailer.
            $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $passwordForRedaction = $password;
            $mail->Debugoutput = function (string $str, int $level) use ($smtpLogFile, $passwordForRedaction): void {
                $line = trim($str);
                if ($passwordForRedaction !== '') {
                    $line = str_replace($passwordForRedaction, '[redacted]', $line);
                }
                file_put_contents($smtpLogFile,
                    '[' . date('H:i:s') . '][L' . $level . '] ' . $line . "\n",
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
        error_log('OpenHelpDesk mail error: ' . $mail->ErrorInfo);
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
 * Format SLA minutes as a human-readable duration, e.g. "30 minutes",
 * "4 hours", "1 hour 30 minutes".
 */
function formatSlaDuration(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $parts = [];
    if ($h > 0) {
        $parts[] = $h . ' ' . ($h === 1 ? 'hour' : 'hours');
    }
    if ($m > 0 || $h === 0) {
        $parts[] = $m . ' ' . ($m === 1 ? 'minute' : 'minutes');
    }
    return implode(' ', $parts);
}

/**
 * Build the {{sla}}, {{sla_response}} and {{sla_resolution}} email tokens for
 * a ticket's type + priority combination. All three resolve to empty strings
 * when SLA tracking is disabled, business hours are not configured, or no
 * policy matches — so templates referencing them degrade gracefully.
 */
function slaEmailTokens(PDO $db, ?int $typeId, ?int $priorityId): array
{
    $empty = ['sla' => '', 'sla_response' => '', 'sla_resolution' => ''];
    if (!$priorityId || !slaEnabled() || Sla::getBusinessSchedule() === null) {
        return $empty;
    }
    $policy = Sla::findPolicy($db, $typeId, $priorityId);
    if (!$policy) {
        return $empty;
    }
    $response   = formatSlaDuration((int) $policy['first_response_minutes']);
    $resolution = formatSlaDuration((int) $policy['resolution_minutes']);
    return [
        'sla'            => "First response within {$response} and resolution within {$resolution} (business hours)",
        'sla_response'   => $response,
        'sla_resolution' => $resolution,
    ];
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
            'button'  => '',
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
        'ticket_assigned_requester' => [
            'subject' => '[Ticket #{{ticket_id}}] Assigned: {{subject}}',
            'intro'   => 'Your ticket has been assigned to {{agent_name}} and will be handled shortly.',
            'button'  => 'View Ticket',
        ],
        'ticket_assigned_group' => [
            'subject' => '[Ticket #{{ticket_id}}] Assigned to your group: {{subject}}',
            'intro'   => 'A ticket has been assigned to your group.',
            'button'  => 'View Ticket',
        ],
        'escalation_alert' => [
            'subject' => 'Escalation Alert: [Ticket #{{ticket_id}}] {{subject}}',
            'intro'   => 'An escalation rule has been triggered for a ticket that requires your attention.',
            'button'  => 'View Ticket',
        ],
        'ticket_escalated_agent' => [
            'subject' => '[Ticket #{{ticket_id}}] Escalated to you (Level {{step_order}}): {{subject}}',
            'intro'   => '{{escalated_by_name}} has escalated this ticket to you. Please review and take it forward.',
            'button'  => 'View Ticket',
        ],
        'ticket_stale_agent' => [
            'subject' => '[Ticket #{{ticket_id}}] Stale — {{hours_since_update}}h without activity: {{subject}}',
            'intro'   => 'A ticket assigned to you has had no activity for {{hours_since_update}} hours.',
            'button'  => 'View Ticket',
        ],
        'ticket_stale_requester' => [
            'subject' => '[Ticket #{{ticket_id}}] Status update: {{subject}}',
            'intro'   => 'We wanted to let you know that we\'re still tracking your ticket, even though there\'s no new update yet.',
            'button'  => 'View Ticket',
        ],
        'ticket_status_resolved' => [
            'subject' => '[Ticket #{{ticket_id}}] Resolved: {{subject}}',
            'intro'   => 'Your support ticket has been resolved. If you have further questions, please reply to this email and we\'ll follow up.',
            'button'  => 'View Ticket',
        ],
        'ticket_status_closed' => [
            'subject' => '[Ticket #{{ticket_id}}] Closed: {{subject}}',
            'intro'   => 'Your support ticket has been closed. Thank you for contacting us. If you need further assistance, please submit a new ticket.',
            'button'  => 'View Ticket',
        ],
        'confidential_ticket_accessed' => [
            'subject' => '[Security Notice] Confidential Ticket #{{ticket_id}} accessed by {{admin_name}}',
            'intro'   => '{{admin_name}} ({{admin_email}}) has accessed confidential ticket #{{ticket_id}} "{{subject}}" at {{timestamp}} from IP {{ip_address}}. This access has been recorded in the audit log.',
            'button'  => 'View Ticket',
        ],
        'confidential_group_membership_changed' => [
            'subject' => '[Security Notice] New members added to confidential group "{{group_name}}"',
            'intro'   => '{{actor_name}} ({{actor_email}}) added the following user(s) to the confidential group "{{group_name}}" at {{timestamp}} from IP {{ip_address}}: {{added_users}}. You are receiving this alert because you are a member of this confidential group.',
            'button'  => 'View Group',
        ],
        'confidential_flag_removed' => [
            'subject' => '[Security Alert] Confidential status removed from {{target_type}} "{{target_name}}"',
            'intro'   => '{{actor_name}} ({{actor_email}}) removed the confidential flag from {{target_type}} "{{target_name}}" at {{timestamp}} from IP {{ip_address}}. This action was authenticated and recorded in the audit log.',
            'button'  => 'View Details',
            'footer'  => '',
        ],
        'confidential_entity_deleted' => [
            'subject' => '[Security Alert] Confidential {{target_type}} "{{target_name}}" was deleted',
            'intro'   => '{{actor_name}} ({{actor_email}}) deleted the confidential {{target_type}} "{{target_name}}" at {{timestamp}} from IP {{ip_address}}. This action was authenticated and recorded in the audit log.',
            'button'  => '',
            'footer'  => '',
        ],
    ];

    $d = $defaults[$key] ?? ['subject' => '', 'intro' => '', 'button' => 'View Ticket'];

    $subjectTpl = getSetting("email_subject_{$key}") ?: $d['subject'];
    $introTpl   = getSetting("email_intro_{$key}")   ?: $d['intro'];
    $button     = getSetting("email_button_{$key}")  ?: $d['button'];
    $footer     = getSetting('email_footer_text')    ?: 'This is an automated message from OpenHelpDesk. Please do not reply directly to this email.';

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
 * Send a confirmation email to the requester when their ticket is created.
 * Works from any creation path (portal form, admin/agent form, API, email-to-ticket).
 * Gated by the global `requester_new_ticket` setting AND the user's
 * `notify_ticket_created` preference.
 */
function notifyRequesterTicketCreated(PDO $db, int $ticketId): void
{
    if (!emailNotifyEnabled('requester_new_ticket')) {
        return;
    }

    $stmt = $db->prepare(
        'SELECT t.subject, t.description, t.type_id, t.location_id, t.priority_id,
                u.id AS user_id, u.email, u.first_name, u.last_name, u.notify_ticket_created
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row || !(bool) ($row['notify_ticket_created'] ?? 1)) {
        return;
    }

    $typeName = $locationName = $priorityName = '';
    if ($row['type_id']) {
        $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
        $s->execute([$row['type_id']]);
        $typeName = $s->fetchColumn() ?: '';
    }
    if ($row['location_id']) {
        $s = $db->prepare('SELECT name FROM locations WHERE id = ?');
        $s->execute([$row['location_id']]);
        $locationName = $s->fetchColumn() ?: '';
    }
    if ($row['priority_id']) {
        $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
        $s->execute([$row['priority_id']]);
        $priorityName = $s->fetchColumn() ?: '';
    }

    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $ticketUrl = $appUrl . '/portal/tickets/' . $ticketId;

    $tpl = getEmailTpl('ticket-created', [
        'ticket_id'  => $ticketId,
        'subject'    => $row['subject'],
        'type'       => $typeName,
        'location'   => $locationName,
        'priority'   => $priorityName,
        'user_name'  => $row['first_name'] . ' ' . $row['last_name'],
        'first_name' => $row['first_name'],
        'last_name'  => $row['last_name'],
    ] + slaEmailTokens($db, $row['type_id'] ? (int) $row['type_id'] : null, $row['priority_id'] ? (int) $row['priority_id'] : null));

    $emailHtml = renderEmail('ticket-created', [
        'ticketId'     => $ticketId,
        'subject'      => $row['subject'],
        'description'  => $row['description'],
        'typeName'     => $typeName,
        'locationName' => $locationName,
        'priorityName' => $priorityName,
        'ticketUrl'    => $ticketUrl,
        'introText'    => $tpl['intro'],
        'buttonLabel'  => $tpl['button'],
        'footerText'   => $tpl['footer'],
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
 * Email the requester when their ticket is assigned to an agent, telling them
 * who will be handling it. Gated by the global `requester_ticket_assigned`
 * setting AND the requester's `notify_ticket_assigned` preference. Skips when
 * the requester is the assigned agent (they get the agent-side email).
 */
function notifyRequesterTicketAssigned(PDO $db, int $ticketId, int $agentId): void
{
    if (!emailNotifyEnabled('requester_ticket_assigned')) {
        return;
    }

    $stmt = $db->prepare(
        'SELECT t.subject, t.type_id, t.priority_id,
                u.id AS user_id, u.email, u.first_name, u.last_name, u.notify_ticket_assigned
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    if ((int) $row['user_id'] === $agentId) {
        return; // requester is the assigned agent
    }
    if (!(bool) ($row['notify_ticket_assigned'] ?? 1)) {
        return;
    }

    $a = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $a->execute([$agentId]);
    $agent = $a->fetch();
    if (!$agent) {
        return;
    }
    $agentName = trim($agent['first_name'] . ' ' . $agent['last_name']);

    $typeName = $priorityName = '';
    if ($row['type_id']) {
        $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
        $s->execute([$row['type_id']]);
        $typeName = $s->fetchColumn() ?: '';
    }
    if ($row['priority_id']) {
        $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
        $s->execute([$row['priority_id']]);
        $priorityName = $s->fetchColumn() ?: '';
    }

    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $ticketUrl = $appUrl . '/portal/tickets/' . $ticketId;

    $tpl = getEmailTpl('ticket-assigned-requester', [
        'ticket_id'  => $ticketId,
        'subject'    => $row['subject'],
        'agent_name' => $agentName,
        'type'       => $typeName,
        'priority'   => $priorityName,
        'user_name'  => $row['first_name'] . ' ' . $row['last_name'],
        'first_name' => $row['first_name'],
        'last_name'  => $row['last_name'],
    ]);

    $emailHtml = renderEmail('ticket-assigned-requester', [
        'ticketId'     => $ticketId,
        'subject'      => $row['subject'],
        'agentName'    => $agentName,
        'typeName'     => $typeName,
        'priorityName' => $priorityName,
        'ticketUrl'    => $ticketUrl,
        'introText'    => $tpl['intro'],
        'buttonLabel'  => $tpl['button'],
        'footerText'   => $tpl['footer'],
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
        // In-app "Ticket Update" for CC'd users — independent of email opt-out.
        createNotification($db, $uid, $ticketId, 'ticket_update', $message, $currentId);
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
         WHERE tw.ticket_id = ? AND " . staffRoleSqlIn('u.role') . ""
    );
    $stmt->execute([$ticketId]);

    foreach ($stmt->fetchAll() as $user) {
        $uid = (int) $user['id'];
        if ($uid === $currentId || $uid === $creatorId) continue;
        if (in_array($uid, $ccIdList, true)) continue;

        // In-app "Ticket Update" for watchers on a new public comment.
        createNotification($db, $uid, $ticketId, 'ticket_update', $message, $currentId);

        $prefix    = roleIsAdmin($user['role']) ? '/admin' : '/agent';
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
    $slaTokens = slaEmailTokens($db, $ticket['type_id'] ? (int) $ticket['type_id'] : null, $ticket['priority_id'] ? (int) $ticket['priority_id'] : null);

    foreach ($members as $user) {
        // In-app "New Ticket" alert for group members — independent of email opt-out.
        createNotification(
            $db,
            (int) $user['id'],
            $ticketId,
            'new_ticket',
            $ticket['subject'],
            (int) $ticket['created_by']
        );
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
        ] + $slaTokens);

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
    // In-app "New Assignment" feed notification — fires regardless of the
    // agent's email preferences (createNotification self-skips self-assignment).
    createNotification($db, $agentId, $ticketId, 'assignment', null, Auth::id());

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

    $tpl = getEmailTpl('ticket_assigned_agent', [
        'ticket_id'   => $ticketId,
        'subject'     => $ticket['subject'],
        'type'        => $typeName,
        'priority'    => $priorityName,
        'submitter'   => $submitterName,
        'user_name'   => $agent['first_name'] . ' ' . $agent['last_name'],
        'first_name'  => $agent['first_name'],
        'last_name'   => $agent['last_name'],
    ] + slaEmailTokens($db, $ticket['type_id'] ? (int) $ticket['type_id'] : null, $ticket['priority_id'] ? (int) $ticket['priority_id'] : null));

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
    $slaTokens = slaEmailTokens($db, $ticket['type_id'] ? (int) $ticket['type_id'] : null, $ticket['priority_id'] ? (int) $ticket['priority_id'] : null);

    foreach ($mStmt->fetchAll() as $member) {
        if ((int) $member['id'] === Auth::id()) {
            continue; // skip the person making the change
        }
        // In-app "New Assignment" for the group — independent of email opt-out.
        createNotification($db, (int) $member['id'], $ticketId, 'assignment', null, Auth::id());
        if (!(bool) ($member['notify_assigned_to_group'] ?? 1)) {
            continue;
        }

        $rolePrefix = match ($member['role']) {
            'admin' => '/admin',
            default => '/agent',
        };
        $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

        $tpl = getEmailTpl('ticket_assigned_group', [
            'ticket_id'   => $ticketId,
            'subject'     => $ticket['subject'],
            'group'       => $groupName,
            'type'        => $typeName,
            'priority'    => $priorityName,
            'submitter'   => $submitterName,
            'user_name'   => $member['first_name'] . ' ' . $member['last_name'],
            'first_name'  => $member['first_name'],
            'last_name'   => $member['last_name'],
        ] + $slaTokens);

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
    if ($row) {
        // In-app "Customer Reply" for the assigned agent — independent of email opt-out.
        createNotification($db, (int) $row['agent_id'], $ticketId, 'customer_reply', $message);
    }
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
    // In-app "Note Added" for the assigned agent — independent of email opt-out.
    createNotification($db, (int) $row['agent_id'], $ticketId, 'note_added', $message, Auth::id());
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
    // Match the slug against the two semantic default flags rather than the
    // literal strings 'resolved' / 'closed', so admins can rename either
    // status without breaking the email + opt-in wiring.
    if ($newStatus === ticketDefaultResolvedStatusSlug()) {
        $settingKey   = 'requester_ticket_resolved';
        $userCol      = 'notify_ticket_solved';
        $templateKind = 'resolved';
    } elseif ($newStatus === ticketDefaultClosedStatusSlug()) {
        $settingKey   = 'requester_ticket_closed';
        $userCol      = 'notify_ticket_closed';
        $templateKind = 'closed';
    } else {
        return;
    }
    if (!emailNotifyEnabled($settingKey)) {
        return;
    }

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
        'resolved' => 'Your support ticket has been resolved. If you have further questions, please reply to this email and we\'ll follow up.',
        'closed'   => 'Your support ticket has been closed. Thank you for contacting us. If you need further assistance, please submit a new ticket.',
    ];

    // $templateKind is the semantic flag ('resolved' / 'closed') and stays
    // stable even if an admin renames the underlying slug. $newStatus is the
    // actual slug stored on the ticket — that's what the email body shows.
    $tpl = getEmailTpl('ticket-status-' . $templateKind, [
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
        'introText'   => !empty($tpl['intro']) ? $tpl['intro'] : $defaultIntros[$templateKind],
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
    $appName    = getSetting('app_name', 'OpenHelpDesk');
    $brandColor = getSetting('branding_primary_color', '#4f46e5');

    // Add a parent timeline entry marking the escalation fired
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
    )->execute([$ticketId, 'escalation_triggered', "Escalation rule \"{$ruleName}\" triggered"]);

    $validStatuses   = ticketActiveStatusSlugs();
    $pausingStatuses = ticketSlaPausingSlugs();

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
                    \Sla::onPriorityChanged($db, $ticketId, $newPriority, $ticket['type_id'] ? (int) $ticket['type_id'] : null);
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

                // Ensure the recipient can actually open the ticket from the email link,
                // even if they're outside the ticket's group. Mirrors manual-escalation
                // behaviour. Confidential tickets are exempt: access there is restricted
                // to the confidential group, so we never auto-add an outside watcher.
                if (roleIsStaff($targetUser['role']) && !ticketIsConfidential($db, (int) $ticketId)) {
                    $db->prepare('INSERT IGNORE INTO ticket_watchers (ticket_id, user_id) VALUES (?, ?)')
                       ->execute([$ticketId, $targetUserId]);
                }

                // Fetch ticket subject for message
                $s = $db->prepare('SELECT subject FROM tickets WHERE id = ?');
                $s->execute([$ticketId]);
                $subject = $s->fetchColumn() ?: "(Ticket #{$ticketId})";

                $noteText = "Escalation alert: Rule \"{$ruleName}\" was triggered on this ticket.";

                // Timeline entry for the notification
                $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)')
                   ->execute([$ticketId, 'escalation_notification', $noteText]);
                $tlId = (int) $db->lastInsertId();

                // In-app SLA/escalation notification (no human actor; system-generated).
                createNotification($db, $targetUserId, $ticketId, 'sla_breach', $noteText, null, $tlId);

                // Email notification
                $rolePrefix = match ($targetUser['role']) {
                    'admin' => '/admin',
                    'agent' => '/agent',
                    default => '/portal',
                };
                $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

                $escTpl = getEmailTpl('escalation_alert', [
                    'ticket_id'  => $ticketId,
                    'subject'    => $subject,
                    'rule_name'  => $ruleName,
                    'first_name' => $targetUser['first_name'],
                    'last_name'  => $targetUser['last_name'],
                    'user_name'  => $targetUser['first_name'] . ' ' . $targetUser['last_name'],
                ]);
                $emailHtml = renderEmail('escalation', [
                    'ticketId'    => $ticketId,
                    'subject'     => $subject,
                    'ruleName'    => $ruleName,
                    'firstName'   => $targetUser['first_name'],
                    'ticketUrl'   => $ticketUrl,
                    'brandColor'  => $brandColor,
                    'appName'     => $appName,
                    'introText'   => $escTpl['intro'],
                    'buttonLabel' => $escTpl['button'],
                    'footerText'  => $escTpl['footer'],
                ]);
                sendMail(
                    $targetUser['email'],
                    $targetUser['first_name'] . ' ' . $targetUser['last_name'],
                    $escTpl['subject'],
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

    $mode = getSetting('csat_mode', 'internal');

    // External mode requires a configured URL — bail (and don't burn the
    // one-survey-per-ticket slot) if the admin has selected External but
    // left the URL blank.
    if ($mode === 'external' && trim(getSetting('csat_external_url', '')) === '') {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO csat_surveys (ticket_id, user_id, token) VALUES (?, ?, ?)')
       ->execute([$ticketId, (int) $row['user_id'], $token]);

    $appUrl     = env('APP_URL', 'http://localhost:8000');
    $brandColor = getSetting('branding_primary_color', '#4f46e5');
    $appName    = getSetting('app_name', 'OpenHelpDesk');
    $showReopen = getSetting('csat_show_reopen', '1') === '1';

    if ($mode === 'external') {
        $surveyUrl = csatSubstitutePlaceholders(getSetting('csat_external_url', ''), [
            'ticket_id'  => (string) $ticketId,
            'user_email' => $row['email'],
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'user_name'  => $row['first_name'] . ' ' . $row['last_name'],
            'subject'    => $row['subject'],
        ]);
    } else {
        $surveyUrl = $appUrl . '/survey/' . $token;
    }
    $reopenUrl = $showReopen ? $appUrl . '/survey/' . $token . '/reopen' : '';

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
        'reopenUrl'  => $reopenUrl,
        'showReopen' => $showReopen,
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

/**
 * Substitute {placeholder} tokens in a URL with URL-encoded values.
 * Unknown tokens are left as-is so they remain visible for debugging.
 */
function csatSubstitutePlaceholders(string $url, array $values): string
{
    $map = [];
    foreach ($values as $key => $value) {
        $map['{' . $key . '}'] = rawurlencode((string) $value);
    }
    return strtr($url, $map);
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
    $appName = rawurlencode(getSetting('branding_app_name', 'OpenHelpDesk'));
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
 * Map of legacy audit-log action names to their canonical, dotted, area-first
 * replacements. Lives in one place so both the write path (which canonicalizes
 * new rows) and the viewer (which canonicalizes display + expands filters to
 * also match the legacy names on old rows) read from the same table.
 *
 * Adding a new entry here is enough to roll out a rename — old rows continue
 * to filter correctly because the legacy name is still recognized as an alias
 * of the canonical one; new rows are stored under the canonical name from
 * here on out so eventually no legacy values remain in the table.
 */
function auditAliases(): array
{
    return [
        'login'                              => 'auth.login',
        'logout'                             => 'auth.logout',
        '2fa.enable'                         => 'auth.2fa_enabled',
        '2fa.disable'                        => 'auth.2fa_disabled',
        '2fa.admin_reset'                    => 'user.2fa_reset_by_admin',
        'ticket_escalated'                   => 'ticket.escalated',
        'confidential_ticket_viewed'         => 'ticket.confidential_viewed',
        'group_managers_changed'             => 'group.managers_changed',
        'ai_settings_saved'                  => 'ai.settings_changed',
        'ai_classification_override'         => 'ai.classification_override',
        'ai_backfill_run'                    => 'ai.backfill_run',
        'manager_skill_assignments_changed'  => 'manager.skill_assignments_changed',
        'manager_skill_created'              => 'manager.skill_created',
        'manager_skill_updated'              => 'manager.skill_updated',
        'manager_skill_deleted'              => 'manager.skill_deleted',
        'default_group_changed'              => 'settings.default_group_changed',
        'escalation_path_saved'              => 'escalation_path.saved',
    ];
}

/** Resolve a possibly-legacy action name to its canonical form. */
function auditCanonicalAction(string $action): string
{
    return auditAliases()[$action] ?? $action;
}

/** Reverse lookup: legacy names that map to a given canonical action. */
function auditLegacyAliasesFor(string $canonical): array
{
    $legacy = [];
    foreach (auditAliases() as $old => $new) {
        if ($new === $canonical) {
            $legacy[] = $old;
        }
    }
    return $legacy;
}

/**
 * Record an admin audit event. Silently swallows all errors so that
 * a logging failure never breaks the main request.
 *
 * $userIdOverride lets unauthenticated paths (API login, etc.) record the
 * acting user explicitly; when omitted the row uses Auth::id() (which may
 * be null for failed-login attempts, and that is intentional).
 *
 * Legacy action names are rewritten to their canonical equivalents on the
 * way in, so the table converges on the new naming convention even when an
 * older callsite is still emitting the old name.
 */
function logAudit(string $action, ?int $targetId = null, ?string $targetType = null, ?string $detail = null, ?int $userIdOverride = null): void
{
    $action = auditCanonicalAction($action);
    try {
        $db = Database::connect();
        $db->prepare(
            'INSERT INTO audit_log (user_id, action, target_type, target_id, detail, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $userIdOverride ?? Auth::id(),
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

/**
 * Diff two associative arrays and write an audit_log entry summarizing the
 * fields that changed, in the form "field: old→new; field2: old→new".
 *
 * Fields listed in $sensitiveFields are rendered as "field: (changed)" so
 * passwords, secrets, and tokens never land in the audit log as plaintext.
 * Returns silently (no log row) when nothing actually changed.
 */
function logAuditChange(
    string $action,
    ?int $targetId,
    ?string $targetType,
    array $before,
    array $after,
    array $sensitiveFields = []
): void {
    $parts = [];
    $sensitive = array_flip($sensitiveFields);
    foreach ($after as $field => $newVal) {
        $oldVal = $before[$field] ?? null;
        if ((string) $oldVal === (string) $newVal) {
            continue;
        }
        if (isset($sensitive[$field])) {
            $parts[] = "{$field}: (changed)";
            continue;
        }
        $oldStr = $oldVal === null ? '(null)' : (string) $oldVal;
        $newStr = $newVal === null ? '(null)' : (string) $newVal;
        // Truncate noisy values so the detail column stays readable
        if (strlen($oldStr) > 60) $oldStr = substr($oldStr, 0, 57) . '...';
        if (strlen($newStr) > 60) $newStr = substr($newStr, 0, 57) . '...';
        $parts[] = "{$field}: {$oldStr}→{$newStr}";
    }
    if (empty($parts)) {
        return;
    }
    logAudit($action, $targetId, $targetType, implode('; ', $parts));
}

/**
 * Find users whose presence row is older than the online window, log a
 * `session.timed_out` audit entry for each (with the disappeared user's
 * own user_id and last-known IP, not the sweeper's), and delete the stale
 * rows. Called on every heartbeat — runs in a transaction with FOR UPDATE
 * so concurrent heartbeats can't double-log the same disappearance.
 */
function sweepStalePresence(int $windowSeconds = 120): void
{
    try {
        $db = Database::connect();
        $db->beginTransaction();
        $stmt = $db->prepare(
            'SELECT user_id, ip_address, user_agent, last_seen
               FROM user_presence
              WHERE last_seen < DATE_SUB(NOW(), INTERVAL ? SECOND)
              FOR UPDATE'
        );
        $stmt->execute([$windowSeconds]);
        $stale = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($stale) {
            $ins = $db->prepare(
                'INSERT INTO audit_log (user_id, action, detail, ip_address)
                 VALUES (?, ?, ?, ?)'
            );
            $ids = [];
            foreach ($stale as $row) {
                $detail = 'last_seen=' . $row['last_seen']
                    . '; ua=' . (string) ($row['user_agent'] ?? '');
                $ins->execute([
                    (int) $row['user_id'],
                    'session.timed_out',
                    $detail,
                    $row['ip_address'],
                ]);
                $ids[] = (int) $row['user_id'];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("DELETE FROM user_presence WHERE user_id IN ($placeholders)")
               ->execute($ids);
        }
        $db->commit();
    } catch (\Throwable $e) {
        try {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
        } catch (\Throwable $ignored) {
            // ignore
        }
        // Never let presence sweeping break the heartbeat request
    }
}

/* ── Confidential ticket helpers ─────────────────────────────── */

/**
 * Determine if the current user needs re-authentication to view a confidential ticket.
 * Returns true if the ticket's type is confidential AND the current admin is NOT
 * a member of the type's group.
 */
function requiresConfidentialReAuth(PDO $db, array $ticket): bool
{
    if (!Auth::isAdmin()) {
        return false;
    }
    if (empty($ticket['type_id'])) {
        return false;
    }

    $stmt = $db->prepare('SELECT is_confidential, group_id FROM ticket_types WHERE id = ?');
    $stmt->execute([$ticket['type_id']]);
    $type = $stmt->fetch();

    if (!$type || !$type['is_confidential'] || !$type['group_id']) {
        return false;
    }

    $gs = $db->prepare('SELECT 1 FROM group_user_map WHERE group_id = ? AND user_id = ? LIMIT 1');
    $gs->execute([$type['group_id'], Auth::id()]);
    return !$gs->fetchColumn();
}

/**
 * Check if a ticket should appear redacted in listings for the current user.
 * Designed to be called in a loop without extra queries — pass preloaded data.
 *
 * @param array $ticket            A ticket row (must include type_id and type_group_id)
 * @param array $confidentialTypeIds  Array of int type IDs that are confidential
 * @param array $userGroupIds         Array of int group IDs the current user belongs to
 */
function isTicketRedactedForUser(array $ticket, array $confidentialTypeIds, array $userGroupIds): bool
{
    if (!Auth::isAdmin()) {
        return false;
    }
    if (empty($ticket['type_id']) || !in_array((int) $ticket['type_id'], $confidentialTypeIds, true)) {
        return false;
    }
    $typeGroupId = (int) ($ticket['type_group_id'] ?? 0);
    return $typeGroupId > 0 && !in_array($typeGroupId, $userGroupIds, true);
}

/**
 * Notify all members of a confidential ticket's group that an admin has accessed it.
 */
function notifyConfidentialAccess(PDO $db, int $ticketId): void
{
    $tStmt = $db->prepare('SELECT t.subject, t.type_id FROM tickets t WHERE t.id = ?');
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch();
    if (!$ticket || !$ticket['type_id']) {
        return;
    }

    $ttStmt = $db->prepare('SELECT group_id, name FROM ticket_types WHERE id = ? AND is_confidential = 1');
    $ttStmt->execute([$ticket['type_id']]);
    $type = $ttStmt->fetch();
    if (!$type || !$type['group_id']) {
        return;
    }

    $mStmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role
         FROM group_user_map gum
         JOIN users u ON u.id = gum.user_id
         WHERE gum.group_id = ?'
    );
    $mStmt->execute([$type['group_id']]);
    $members = $mStmt->fetchAll();
    if (empty($members)) {
        return;
    }

    $adminName  = Auth::fullName();
    $adminEmail = Auth::user()['email'] ?? '';
    $ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp  = date('Y-m-d H:i:s');
    $appUrl     = env('APP_URL', 'http://localhost:8000');

    foreach ($members as $user) {
        $rolePrefix = roleLandingPath($user['role']);
        $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

        $tpl = getEmailTpl('confidential-ticket-accessed', [
            'ticket_id'   => $ticketId,
            'subject'     => $ticket['subject'],
            'admin_name'  => $adminName,
            'admin_email' => $adminEmail,
            'ip_address'  => $ip,
            'timestamp'   => $timestamp,
            'user_name'   => $user['first_name'] . ' ' . $user['last_name'],
            'first_name'  => $user['first_name'],
            'last_name'   => $user['last_name'],
        ]);

        $emailHtml = renderEmail('confidential-access-alert', [
            'ticketId'    => $ticketId,
            'subject'     => $ticket['subject'],
            'typeName'    => $type['name'],
            'adminName'   => $adminName,
            'adminEmail'  => $adminEmail,
            'ipAddress'   => $ip,
            'timestamp'   => $timestamp,
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
 * Notify all current members of a confidential group when new members are added.
 *
 * Called from the admin group edit handler. Only fires when the group is
 * marked confidential AND there were existing members BEFORE the edit AND
 * at least one new member was added — that policy is enforced by the caller.
 *
 * @param PDO   $db
 * @param int   $groupId
 * @param int[] $addedUserIds  Newly added user IDs (i.e. were not in the group prior to this edit)
 */
function notifyConfidentialGroupMembership(PDO $db, int $groupId, array $addedUserIds): void
{
    if (empty($addedUserIds)) {
        return;
    }

    // Load group
    $gStmt = $db->prepare('SELECT id, name, is_confidential FROM `groups` WHERE id = ?');
    $gStmt->execute([$groupId]);
    $group = $gStmt->fetch();
    if (!$group || empty($group['is_confidential'])) {
        return;
    }

    // Current members (post-edit) — these are the recipients of the alert
    $mStmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role
         FROM group_user_map gum
         JOIN users u ON u.id = gum.user_id
         WHERE gum.group_id = ?'
    );
    $mStmt->execute([$groupId]);
    $members = $mStmt->fetchAll();
    if (empty($members)) {
        return;
    }

    // Names of the newly added users (for the email body)
    $placeholders = implode(',', array_fill(0, count($addedUserIds), '?'));
    $aStmt = $db->prepare(
        "SELECT id, first_name, last_name, email FROM users WHERE id IN ($placeholders)"
    );
    $aStmt->execute($addedUserIds);
    $addedUsers = $aStmt->fetchAll();
    if (empty($addedUsers)) {
        return;
    }

    $addedNamesList = array_map(
        static fn($u) => trim($u['first_name'] . ' ' . $u['last_name']) . ' <' . $u['email'] . '>',
        $addedUsers
    );
    $addedNamesText = implode(', ', $addedNamesList);

    $actorName  = Auth::fullName();
    $actorEmail = Auth::user()['email'] ?? '';
    $ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp  = date('Y-m-d H:i:s');
    $appUrl     = env('APP_URL', 'http://localhost:8000');

    $tpl = getEmailTpl('confidential-group-membership-changed', [
        'group_name'  => $group['name'],
        'group_id'    => $groupId,
        'added_users' => $addedNamesText,
        'actor_name'  => $actorName,
        'actor_email' => $actorEmail,
        'ip_address'  => $ip,
        'timestamp'   => $timestamp,
    ]);

    foreach ($members as $user) {
        $rolePrefix = roleLandingPath($user['role']);
        $groupUrl = $appUrl . $rolePrefix . '/groups/' . $groupId . '/edit';

        $emailHtml = renderEmail('confidential-group-membership-alert', [
            'groupId'     => $groupId,
            'groupName'   => $group['name'],
            'addedUsers'  => $addedUsers,
            'actorName'   => $actorName,
            'actorEmail'  => $actorEmail,
            'ipAddress'   => $ip,
            'timestamp'   => $timestamp,
            'groupUrl'    => $groupUrl,
            'introText'   => $tpl['intro'],
            'buttonLabel' => $tpl['button'],
            'footerText'  => $tpl['footer'] ?? '',
        ]);

        sendMail(
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $tpl['subject'],
            $emailHtml
        );
    }

    // Audit log
    logAudit(
        'confidential_group_membership_changed',
        $groupId,
        'group',
        $actorName . ' (ID: ' . Auth::id() . ') added ' . count($addedUsers)
            . ' member(s) to confidential group "' . $group['name'] . '": ' . $addedNamesText
    );
}

/**
 * Notify all members of a confidential group when the confidential flag is
 * removed from the group itself or from a ticket type linked to the group.
 *
 * @param PDO    $db
 * @param string $targetType  'group' or 'ticket type'
 * @param string $targetName  Name of the group or ticket type
 * @param int    $groupId     The group whose members should be notified
 * @param string $viewUrl     URL for the "View Details" button (empty to omit)
 */
function notifyConfidentialFlagRemoved(PDO $db, string $targetType, string $targetName, int $groupId, string $viewUrl = ''): void
{
    $mStmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role
         FROM group_user_map gum
         JOIN users u ON u.id = gum.user_id
         WHERE gum.group_id = ?'
    );
    $mStmt->execute([$groupId]);
    $members = $mStmt->fetchAll();
    if (empty($members)) {
        return;
    }

    $actorName  = Auth::fullName();
    $actorEmail = Auth::user()['email'] ?? '';
    $ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp  = date('Y-m-d H:i:s');

    $tpl = getEmailTpl('confidential_flag_removed', [
        'target_type' => $targetType,
        'target_name' => $targetName,
        'actor_name'  => $actorName,
        'actor_email' => $actorEmail,
        'ip_address'  => $ip,
        'timestamp'   => $timestamp,
    ]);

    foreach ($members as $user) {
        $emailHtml = renderEmail('confidential-flag-removed', [
            'targetType'  => $targetType,
            'targetName'  => $targetName,
            'actorName'   => $actorName,
            'actorEmail'  => $actorEmail,
            'ipAddress'   => $ip,
            'timestamp'   => $timestamp,
            'viewUrl'     => $viewUrl,
            'introText'   => $tpl['intro'],
            'buttonLabel' => $tpl['button'],
            'footerText'  => $tpl['footer'] ?? '',
        ]);

        sendMail(
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $tpl['subject'],
            $emailHtml
        );
    }
}

/**
 * Notify all members of a confidential group when the group or a linked
 * confidential ticket type is deleted entirely.
 *
 * @param PDO    $db
 * @param string $targetType  'group' or 'ticket type'
 * @param string $targetName  Name of the deleted entity
 * @param array  $members     Pre-fetched member rows (id, first_name, last_name, email, role)
 */
function notifyConfidentialEntityDeleted(PDO $db, string $targetType, string $targetName, array $members): void
{
    if (empty($members)) {
        return;
    }

    $actorName  = Auth::fullName();
    $actorEmail = Auth::user()['email'] ?? '';
    $ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp  = date('Y-m-d H:i:s');

    $tpl = getEmailTpl('confidential_entity_deleted', [
        'target_type' => $targetType,
        'target_name' => $targetName,
        'actor_name'  => $actorName,
        'actor_email' => $actorEmail,
        'ip_address'  => $ip,
        'timestamp'   => $timestamp,
    ]);

    foreach ($members as $user) {
        $emailHtml = renderEmail('confidential-entity-deleted', [
            'targetType'  => $targetType,
            'targetName'  => $targetName,
            'actorName'   => $actorName,
            'actorEmail'  => $actorEmail,
            'ipAddress'   => $ip,
            'timestamp'   => $timestamp,
            'introText'   => $tpl['intro'],
            'footerText'  => $tpl['footer'] ?? '',
        ]);

        sendMail(
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $tpl['subject'],
            $emailHtml
        );
    }
}

/* ── Sidebar helpers ──────────────────────────────────────────── */

/**
 * True if the viewer can open at least one Settings-area feature. Every staff
 * member qualifies: admins see everything, and Status Banners — gated only by
 * Auth::requireStaff — now lives in the Settings area, so even a permission-less
 * staff role has one item there. Non-staff (portal) users never qualify. Drives
 * the single "Settings" sidebar link and the GET /admin/settings landing gate;
 * the settings-nav itself still hides every item the role can't open.
 */
function canAccessSettingsArea(): bool
{
    return Auth::isAdmin() || Auth::isStaff();
}

function adminSidebar(string $active = ''): array
{
    // Admin-area pages are reachable by any custom staff level that holds the
    // page's specific permission (each route gates with Auth::requirePermission).
    // Such a non-admin must see THEIR permission-filtered sidebar — never the
    // full admin menu, whose links would 403. Admins get the full menu below.
    if (!Auth::isAdmin()) {
        return staffSidebar($active);
    }
    // Only standard day-to-day actions live on the rail; every management /
    // configuration area (Users, Ticket Forms, KB management, Recurring Tickets,
    // Audit Log, Reports, …) is reached through the Settings page's left nav.
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',    'label' => label('nav.dashboard'),     'url' => '/admin',            'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed', 'label' => label('nav.tickets'),       'url' => '/admin/tickets',    'key' => 'tickets'],
        ['icon' => 'bi-sliders',         'label' => label('nav.settings'),      'url' => '/admin/settings',   'key' => 'settings'],
        ['icon' => 'bi-question-circle', 'label' => label('nav.docs'),          'url' => '/admin/docs',       'key' => 'docs'],
    ]);
}

function portalSidebar(string $active = ''): array
{
    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), [
        ['icon' => 'bi-speedometer2',    'label' => label('portal.nav.dashboard'),     'url' => '/portal',         'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed', 'label' => label('portal.nav.my_tickets'),    'url' => '/portal/tickets', 'key' => 'tickets'],
        ['icon' => 'bi-grid-1x2',        'label' => 'Floor mode',                      'url' => '/portal/floor',   'key' => 'floor', 'touchOnly' => true],
        ['icon' => 'bi-book',            'label' => label('portal.nav.knowledge_base'), 'url' => '/portal/kb',     'key' => 'kb'],
        ['icon' => 'bi-question-circle', 'label' => 'Help',                            'url' => '/portal/help',    'key' => 'help'],
    ]);
}

/**
 * Sidebar for any non-admin staff role. Standard day-to-day actions plus a
 * single "Settings" link (shown when the role holds any grantable permission).
 * Every admin-area feature a role might be granted now lives on the Settings
 * page, whose own permission-filtered left nav reveals exactly what the role
 * can open — so the rail no longer grows one icon per granted permission.
 * Admins use adminSidebar() instead.
 */
function staffSidebar(string $active = ''): array
{
    $items = [
        ['icon' => 'bi-speedometer2',     'label' => label('agent.nav.dashboard'),      'url' => '/agent',                  'key' => 'dashboard'],
        ['icon' => 'bi-ticket-detailed',  'label' => label('agent.nav.tickets'),        'url' => '/agent/tickets',          'key' => 'tickets'],
        ['icon' => 'bi-grid-1x2',         'label' => 'Floor mode',                      'url' => '/agent/floor',            'key' => 'floor', 'touchOnly' => true],
        ['icon' => 'bi-book',             'label' => label('agent.nav.knowledge_base'), 'url' => '/agent/kb',               'key' => 'kb'],
    ];
    // Canned Responses (personal reply snippets) now lives on the profile page
    // for every staff member — see templates/pages/profile/edit.php.
    // Status Banners moved to the Settings area (Operations group) — it's open
    // to all staff, so canAccessSettingsArea() now admits every staff role.

    if (canAccessSettingsArea()) {
        $items[] = ['icon' => 'bi-sliders', 'label' => label('nav.settings'), 'url' => '/admin/settings', 'key' => 'settings'];
    }

    $items[] = ['icon' => 'bi-question-circle', 'label' => 'Help', 'url' => '/agent/help', 'key' => 'help'];

    return array_map(fn($item) => array_merge($item, ['active' => $item['key'] === $active]), $items);
}

// Back-compat aliases — both historical staff sidebars now resolve through the
// permission-aware builder, so existing call sites stay correct for custom roles.
function agentSidebar(string $active = ''): array
{
    return staffSidebar($active);
}

function powerUserSidebar(string $active = ''): array
{
    return staffSidebar($active);
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

    // Defensive: silently drop status values that no longer exist in the
    // lookup table. Saved filters from before a status was removed would
    // otherwise hit `status IN (...stale_slug...)` and quietly return zero
    // rows; the user never knew their filter was broken. Filtering at the
    // helper level means every ticket-list page benefits.
    if (!empty($fStatus)) {
        $fStatus = array_values(array_intersect($fStatus, ticketStatusSlugs()));
    }
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
                        Sla::onPriorityChanged($db, $ticketId, $priorityId, $ticket['type_id'] ? (int) $ticket['type_id'] : null);
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticketId, 'automation', "Automation '{$auto['name']}': Priority set to {$priorityName}"]);
                    break;

                case 'set_status':
                    if (!in_array($val, ticketActiveStatusSlugs(), true)) {
                        break;
                    }
                    $oldStatus = $ticket['status'];
                    $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$val, $ticketId]);
                    $pausingStatuses = ticketSlaPausingSlugs();
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

/**
 * Email the new assignee when a ticket is manually escalated to them.
 *
 * Mirrors notifyAssignedAgent() but uses the ticket_escalated_agent
 * template and includes escalation-specific context (who escalated it,
 * which level, optional reason, who it moved from).
 */
function notifyEscalation(
    PDO $db,
    int $ticketId,
    int $toUserId,
    int $escalatedById,
    int $stepOrder,
    ?string $stepLabel,
    ?string $reason,
    ?int $fromUserId
): void {
    if (!emailNotifyEnabled('agent_escalated')) {
        return;
    }

    $uStmt = $db->prepare(
        'SELECT id, first_name, last_name, email, role, notify_assigned_to_me
         FROM users WHERE id = ?'
    );
    $uStmt->execute([$toUserId]);
    $agent = $uStmt->fetch();
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

    $byStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $byStmt->execute([$escalatedById]);
    $by = $byStmt->fetch();
    $escalatedByName = $by ? trim($by['first_name'] . ' ' . $by['last_name']) : 'An agent';

    $fromName = '';
    if ($fromUserId) {
        $fStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
        $fStmt->execute([$fromUserId]);
        $f = $fStmt->fetch();
        $fromName = $f ? trim($f['first_name'] . ' ' . $f['last_name']) : '';
    }

    $submitterName = '';
    if ($ticket['created_by']) {
        $sStmt = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
        $sStmt->execute([$ticket['created_by']]);
        $s = $sStmt->fetch();
        $submitterName = $s ? trim($s['first_name'] . ' ' . $s['last_name']) : '';
    }

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

    $tpl = getEmailTpl('ticket_escalated_agent', [
        'ticket_id'         => $ticketId,
        'subject'           => $ticket['subject'],
        'type'              => $typeName,
        'priority'          => $priorityName,
        'submitter'         => $submitterName,
        'user_name'         => $agent['first_name'] . ' ' . $agent['last_name'],
        'first_name'        => $agent['first_name'],
        'last_name'         => $agent['last_name'],
        'escalated_by_name' => $escalatedByName,
        'step_order'        => $stepOrder,
        'step_label'        => (string) ($stepLabel ?? ''),
        'from_agent_name'   => $fromName,
        'reason'            => (string) ($reason ?? ''),
    ]);

    $emailHtml = renderEmail('ticket-escalated', [
        'ticketId'        => $ticketId,
        'subject'         => $ticket['subject'],
        'typeName'        => $typeName,
        'priorityName'    => $priorityName,
        'submitterName'   => $submitterName,
        'escalatedByName' => $escalatedByName,
        'stepOrder'       => $stepOrder,
        'stepLabel'       => (string) ($stepLabel ?? ''),
        'fromAgentName'   => $fromName,
        'reason'          => (string) ($reason ?? ''),
        'ticketUrl'       => $ticketUrl,
        'introText'       => $tpl['intro'],
        'buttonLabel'     => $tpl['button'],
        'footerText'      => $tpl['footer'],
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
 * Look up the next escalation step for a ticket, returning metadata
 * needed to perform the escalation. Skips steps whose target user no
 * longer exists or where the target is the current actor (per design:
 * can't escalate to yourself — jump to the next step).
 *
 * If the ticket's current assignee appears in the escalation matrix,
 * their step_order is treated as a floor so that escalation jumps to
 * the step *after* them (you never "escalate" sideways or downward to
 * someone already on the ticket).
 *
 * Returns null if no further step exists.
 *
 * @return array{user_id:int, step_order:int, label:?string, user_name:string}|null
 */
function nextEscalationStep(PDO $db, int $ticketTypeId, int $currentLevel, int $actorId, ?int $currentAssigneeId = null): ?array
{
    $effectiveLevel = $currentLevel;
    if ($currentAssigneeId) {
        $aStmt = $db->prepare(
            'SELECT step_order FROM ticket_escalation_steps
             WHERE ticket_type_id = ? AND user_id = ?
             ORDER BY step_order DESC LIMIT 1'
        );
        $aStmt->execute([$ticketTypeId, $currentAssigneeId]);
        $assigneeStep = $aStmt->fetchColumn();
        if ($assigneeStep !== false && (int) $assigneeStep > $effectiveLevel) {
            $effectiveLevel = (int) $assigneeStep;
        }
    }

    $stmt = $db->prepare(
        "SELECT s.user_id, s.step_order, s.label,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_escalation_steps s
         JOIN users u ON s.user_id = u.id
         WHERE s.ticket_type_id = ? AND s.step_order > ?
         ORDER BY s.step_order ASC"
    );
    $stmt->execute([$ticketTypeId, $effectiveLevel]);
    while ($row = $stmt->fetch()) {
        if ((int) $row['user_id'] === $actorId) {
            continue; // skip self
        }
        return [
            'user_id'    => (int) $row['user_id'],
            'step_order' => (int) $row['step_order'],
            'label'      => $row['label'] !== null && $row['label'] !== '' ? $row['label'] : null,
            'user_name'  => $row['user_name'],
        ];
    }
    return null;
}

/**
 * Group-based ticket visibility override: an agent who is the current
 * assignee or an explicit watcher of a ticket should always be able to
 * open it, even if the ticket's group is outside their group memberships.
 * Escalation and manual assignment can hand a ticket to someone outside
 * the group; without this exemption they'd get the notification email
 * but a 403 on the link.
 */
function ticketAccessExempt(PDO $db, int $userId, ?int $ticketId): bool
{
    if (!$userId || !$ticketId) {
        return false;
    }

    $s = $db->prepare('SELECT 1 FROM tickets WHERE id = ? AND assigned_to = ?');
    $s->execute([$ticketId, $userId]);
    if ($s->fetchColumn()) {
        return true;
    }

    $s = $db->prepare('SELECT 1 FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?');
    $s->execute([$ticketId, $userId]);
    return (bool) $s->fetchColumn();
}

/**
 * True if the ticket's type is flagged confidential. Used to gate watcher and
 * merge behaviour (confidential tickets get neither watchers nor cross-type
 * merges). Returns false for a missing ticket or a ticket with no type.
 */
function ticketIsConfidential(PDO $db, int $ticketId): bool
{
    if ($ticketId <= 0) {
        return false;
    }
    $s = $db->prepare(
        'SELECT COALESCE(tt.is_confidential, 0)
         FROM tickets t
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         WHERE t.id = ?'
    );
    $s->execute([$ticketId]);
    return (int) $s->fetchColumn() === 1;
}

/**
 * The set of group IDs a user belongs to (ints). Request-cached per user.
 */
function userGroupIds(PDO $db, int $userId): array
{
    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }
    $stmt = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $cache[$userId] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Build the SQL predicate limiting which tickets the CURRENT staff user may see
 * in a list/count query. Self-contained: it references only the tickets alias
 * ($t) plus correlated subqueries on ticket_types / ticket_watchers, so it can
 * be ANDed into any query shaped `FROM tickets $t ...`.
 *
 * Visibility rules (staff context, fail-closed):
 *   - Confidential ticket types are visible ONLY to members of the type's
 *     confidential group, or the ticket's creator. `tickets.view_all` does NOT
 *     reveal confidential tickets.
 *   - Non-confidential tickets are visible to holders of `tickets.view_all`, or
 *     if the ticket is in one of the user's groups, or is group-less and the
 *     user belongs to at least one group, or is assigned to / created by /
 *     watched by them.
 *   - A staff user with no group and no `tickets.view_all` sees only the tickets
 *     they are assigned to / created / watch.
 *
 * Admins are unrestricted (returns `1=1`): they reach confidential tickets via
 * the audited re-auth flow on the detail page, and lists redact confidential
 * content in the template. Returns ['sql' => '(...)', 'params' => [...]].
 *
 * Pass the acting user explicitly ($userId + role slug) so this works for both
 * the session web context (Auth::id()/Auth::role()) and token API callers.
 */
function ticketStaffVisibilitySql(PDO $db, int $userId, ?string $role, string $t = 't'): array
{
    if (roleIsAdmin($role)) {
        return ['sql' => '1=1', 'params' => []];
    }

    $groups    = userGroupIds($db, $userId);
    $hasGroups = !empty($groups);
    $seeAll    = roleCan($role, 'tickets.view_all');
    $groupPh   = $hasGroups ? implode(',', array_fill(0, count($groups), '?')) : '';

    $isConfidential =
        "EXISTS (SELECT 1 FROM ticket_types ct WHERE ct.id = {$t}.type_id AND ct.is_confidential = 1)";

    $params = [];

    // ── Confidential branch: confidential-group member OR creator only ───────
    $confVis = [];
    if ($hasGroups) {
        $confVis[] = "EXISTS (SELECT 1 FROM ticket_types cm WHERE cm.id = {$t}.type_id "
                   . "AND cm.is_confidential = 1 AND cm.group_id IS NOT NULL AND cm.group_id IN ($groupPh))";
    }
    $confVis[] = "{$t}.created_by = ?";
    $confClause = "($isConfidential AND (" . implode(' OR ', $confVis) . '))';

    // ── Non-confidential branch ──────────────────────────────────────────────
    if ($seeAll) {
        $nonConfClause = "(NOT $isConfidential)";
    } else {
        $nc = [];
        if ($hasGroups) {
            $nc[] = "{$t}.group_id IN ($groupPh)";
            $nc[] = "{$t}.group_id IS NULL";
        }
        $nc[] = "{$t}.assigned_to = ?";
        $nc[] = "{$t}.created_by = ?";
        $nc[] = "{$t}.id IN (SELECT ticket_id FROM ticket_watchers WHERE user_id = ?)";
        $nonConfClause = "(NOT $isConfidential AND (" . implode(' OR ', $nc) . '))';
    }

    // ── Assemble params in the same order the clauses appear in the SQL ──────
    if ($hasGroups) { foreach ($groups as $g) { $params[] = $g; } } // confVis member
    $params[] = $userId;                                            // confVis creator
    if (!$seeAll) {
        if ($hasGroups) { foreach ($groups as $g) { $params[] = $g; } } // nonConf group IN
        $params[] = $userId; // nonConf assigned
        $params[] = $userId; // nonConf created
        $params[] = $userId; // nonConf watcher
    }

    return ['sql' => "($confClause OR $nonConfClause)", 'params' => $params];
}

/* ── Stale ticket notifications ─────────────────────────────────────── */

/**
 * Resolve the effective stale threshold (hours) for a ticket.
 * Per-type override wins; otherwise the global setting; otherwise 72h.
 */
function staleThresholdHoursForType(PDO $db, ?int $typeId): int
{
    if ($typeId) {
        $stmt = $db->prepare('SELECT stale_threshold_hours FROM ticket_types WHERE id = ?');
        $stmt->execute([$typeId]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null && $val !== '') {
            return (int) $val;
        }
    }
    return (int) (getSetting('stale_threshold_hours', '72') ?: '72');
}

/**
 * Email the assigned agent (or all members of the assigned group) that a
 * ticket has gone stale. Respects global + per-user notification prefs.
 */
function notifyStaleTicketAgent(PDO $db, array $ticket, int $hoursSinceUpdate, int $thresholdHours): void
{
    if (!emailNotifyEnabled('ticket_stale_agent')) {
        return;
    }

    $ticketId = (int) $ticket['id'];
    $typeName = $priorityName = $submitterName = '';
    if (!empty($ticket['type_id'])) {
        $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
        $s->execute([$ticket['type_id']]);
        $typeName = $s->fetchColumn() ?: '';
    }
    if (!empty($ticket['priority_id'])) {
        $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
        $s->execute([$ticket['priority_id']]);
        $priorityName = $s->fetchColumn() ?: '';
    }
    if (!empty($ticket['created_by'])) {
        $s = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
        $s->execute([$ticket['created_by']]);
        $row = $s->fetch();
        $submitterName = $row ? trim($row['first_name'] . ' ' . $row['last_name']) : '';
    }

    // Determine recipients: assigned agent, or all members of the assigned group.
    $recipients = [];
    if (!empty($ticket['assigned_to'])) {
        $s = $db->prepare(
            'SELECT id, first_name, last_name, email, role, notify_assigned_to_me
             FROM users WHERE id = ?'
        );
        $s->execute([(int) $ticket['assigned_to']]);
        $row = $s->fetch();
        if ($row && (bool) ($row['notify_assigned_to_me'] ?? 1)) {
            $recipients[] = $row;
        }
    } elseif (!empty($ticket['group_id'])) {
        $s = $db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.notify_assigned_to_group
             FROM group_user_map gum
             JOIN users u ON u.id = gum.user_id
             WHERE gum.group_id = ?'
        );
        $s->execute([(int) $ticket['group_id']]);
        foreach ($s->fetchAll() as $row) {
            if ((bool) ($row['notify_assigned_to_group'] ?? 1)) {
                $recipients[] = $row;
            }
        }
    }

    if (empty($recipients)) {
        return;
    }

    $appUrl      = env('APP_URL', 'http://localhost:8000');
    $statusLabel = ticketStatusLabel((string) $ticket['status']);

    foreach ($recipients as $agent) {
        $rolePrefix = match ($agent['role']) {
            'admin' => '/admin',
            default => '/agent',
        };
        $ticketUrl = $appUrl . $rolePrefix . '/tickets/' . $ticketId;

        $tpl = getEmailTpl('ticket_stale_agent', [
            'ticket_id'           => $ticketId,
            'subject'             => $ticket['subject'],
            'type'                => $typeName,
            'priority'            => $priorityName,
            'submitter'           => $submitterName,
            'hours_since_update'  => (string) $hoursSinceUpdate,
            'threshold_hours'     => (string) $thresholdHours,
            'user_name'           => $agent['first_name'] . ' ' . $agent['last_name'],
            'first_name'          => $agent['first_name'],
            'last_name'           => $agent['last_name'],
        ]);

        $emailHtml = renderEmail('ticket-stale-agent', [
            'ticketId'         => $ticketId,
            'subject'          => $ticket['subject'],
            'typeName'         => $typeName,
            'priorityName'     => $priorityName,
            'submitterName'    => $submitterName,
            'hoursSinceUpdate' => $hoursSinceUpdate,
            'thresholdHours'   => $thresholdHours,
            'statusLabel'      => $statusLabel,
            'ticketUrl'        => $ticketUrl,
            'introText'        => $tpl['intro'],
            'buttonLabel'      => $tpl['button'],
            'footerText'       => $tpl['footer'],
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
}

/**
 * Email the requester that their ticket is stale — essentially a
 * "we haven't forgotten you" update even though nothing has changed.
 */
function notifyStaleTicketRequester(PDO $db, array $ticket, int $hoursSinceUpdate): void
{
    if (!emailNotifyEnabled('ticket_stale_requester')) {
        return;
    }

    $ticketId = (int) $ticket['id'];
    if (empty($ticket['created_by'])) {
        return;
    }

    $s = $db->prepare(
        'SELECT id, first_name, last_name, email, notify_ticket_updated
         FROM users WHERE id = ?'
    );
    $s->execute([(int) $ticket['created_by']]);
    $user = $s->fetch();
    if (!$user || !(bool) ($user['notify_ticket_updated'] ?? 1)) {
        return;
    }

    $assigneeName = '';
    if (!empty($ticket['assigned_to'])) {
        $a = $db->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
        $a->execute([(int) $ticket['assigned_to']]);
        $row = $a->fetch();
        $assigneeName = $row ? trim($row['first_name'] . ' ' . $row['last_name']) : '';
    }

    $appUrl      = env('APP_URL', 'http://localhost:8000');
    $ticketUrl   = $appUrl . '/portal/tickets/' . $ticketId;
    $statusLabel = ticketStatusLabel((string) $ticket['status']);

    $tpl = getEmailTpl('ticket_stale_requester', [
        'ticket_id'          => $ticketId,
        'subject'            => $ticket['subject'],
        'hours_since_update' => (string) $hoursSinceUpdate,
        'user_name'          => $user['first_name'] . ' ' . $user['last_name'],
        'first_name'         => $user['first_name'],
        'last_name'          => $user['last_name'],
    ]);

    $emailHtml = renderEmail('ticket-stale-requester', [
        'ticketId'         => $ticketId,
        'subject'          => $ticket['subject'],
        'statusLabel'      => $statusLabel,
        'assigneeName'     => $assigneeName,
        'hoursSinceUpdate' => $hoursSinceUpdate,
        'ticketUrl'        => $ticketUrl,
        'introText'        => $tpl['intro'],
        'buttonLabel'      => $tpl['button'],
        'footerText'       => $tpl['footer'],
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

/* ── Status Banners ───────────────────────────────────────────── */

/**
 * Fetch banners that should be shown to the current user right now.
 *
 * Visibility rules:
 *   - is_active = 1
 *   - within the optional starts_at / expires_at window (NULL bounds
 *     mean "no bound on that side")
 *   - location-scoped: a banner targets the branches listed in
 *     status_banner_locations (no rows = global). Portal users only see
 *     global banners or ones targeting their users.location_id;
 *     agents/admins/power_users see every active banner regardless of
 *     branch so they always know what's posted.
 *
 * Returns rows ordered by severity (critical first) then most-recent
 * first, with `posted_by_name` joined in for the byline.
 */
function getActiveBanners(): array
{
    if (!Auth::check()) {
        return [];
    }
    $db   = Database::connect();
    $role = Auth::role();

    $where  = ['b.is_active = 1'];
    $where[] = '(b.starts_at IS NULL OR b.starts_at <= NOW())';
    $where[] = '(b.expires_at IS NULL OR b.expires_at > NOW())';
    $params = [];

    // Portal users only see global banners or ones at their location.
    if (!roleIsStaff($role)) {
        $userLoc = (int) ($_SESSION['user']['location_id'] ?? 0);
        if (!$userLoc) {
            // Hydrate location_id lazily — older sessions may not have it.
            $stmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
            $stmt->execute([Auth::id()]);
            $userLoc = (int) ($stmt->fetchColumn() ?: 0);
            $_SESSION['user']['location_id'] = $userLoc;
        }
        if ($userLoc > 0) {
            $where[]  = '(NOT EXISTS (SELECT 1 FROM status_banner_locations sbl WHERE sbl.banner_id = b.id)
                          OR EXISTS (SELECT 1 FROM status_banner_locations sbl WHERE sbl.banner_id = b.id AND sbl.location_id = ?))';
            $params[] = $userLoc;
        } else {
            $where[] = 'NOT EXISTS (SELECT 1 FROM status_banner_locations sbl WHERE sbl.banner_id = b.id)';
        }
    }

    $sql = 'SELECT b.*,
                   (SELECT GROUP_CONCAT(l.name ORDER BY l.name SEPARATOR ", ")
                      FROM status_banner_locations sbl
                      JOIN locations l ON l.id = sbl.location_id
                     WHERE sbl.banner_id = b.id) AS location_names,
                   CONCAT(u.first_name, " ", u.last_name) AS posted_by_name
            FROM status_banners b
            LEFT JOIN users     u ON b.created_by  = u.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY FIELD(b.severity, "critical", "warning", "info"),
                     b.updated_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Per-session dismissal: writes to $_SESSION['dismissed_banners'][$key]
    // by POST /api/banners/{id}/dismiss. Key includes updated_at so editing
    // a banner re-surfaces it for users who'd dismissed the older copy.
    $dismissed = $_SESSION['dismissed_banners'] ?? [];
    if ($dismissed) {
        $rows = array_values(array_filter($rows, static function ($b) use ($dismissed) {
            $key = (int) $b['id'] . '_' . strtotime($b['updated_at']);
            return empty($dismissed[$key]);
        }));
    }
    return $rows;
}

/**
 * Sanitize banner body HTML (CKEditor output) before it is echoed raw into
 * every authenticated page — staff *and* portal patrons see banners, so this
 * is a hard stored-XSS boundary, not just defence-in-depth.
 *
 * Allowlist-by-construction via DOMDocument: we parse the markup, keep only a
 * small set of formatting tags, and on those keep only a tiny set of
 * attributes — so *every* event handler (on*) is dropped regardless of
 * quoting/casing/whitespace, and unknown tags are unwrapped (text preserved)
 * while script/style/iframe/svg-style tags are discarded outright. Links may
 * only carry http/https/mailto (or relative) URLs; javascript:/data: are
 * rejected structurally, so the old regex's reassembly bypass can't happen.
 * Unparseable input yields '' rather than passing raw HTML through.
 */
/**
 * Shared HTML allowlist sanitizer (DOMDocument-based). Parses the markup and
 * keeps only the tags in $allowed, and on those only the listed attributes — so
 * every event handler (on*) is dropped regardless of quoting/casing/whitespace,
 * unknown tags are unwrapped (text preserved), and the $dropSubtree tags
 * (script/style/iframe/svg/…) are discarded outright. Attributes named in
 * $urlAttrs (href/src) are scheme-checked against $okSchemes — relative URLs are
 * always allowed; javascript:/data:/etc. are rejected structurally. When
 * $filterStyle is true a permitted `style` attribute is property-filtered via
 * sanitizeStyleAttr(). Unparseable input yields '' rather than passing through.
 *
 * @param array<string,string[]> $allowed     tag => allowed attribute names
 * @param string[]               $dropSubtree tags whose entire subtree is discarded
 * @param string[]               $urlAttrs    attributes validated against $okSchemes
 * @param string[]               $okSchemes   permitted URL schemes (relative always allowed)
 */
function sanitizeHtmlAllowlist(string $html, array $allowed, array $dropSubtree, array $urlAttrs, array $okSchemes, bool $filterStyle = false): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $drop = array_flip($dropSubtree);

    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    // Wrap in a marked div + force UTF-8 so multibyte text survives intact.
    $ok = $dom->loadHTML(
        '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="__ld_root__">' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) {
        return '';
    }

    $xp   = new DOMXPath($dom);
    $root = $xp->query('//*[@id="__ld_root__"]')->item(0);
    if (!$root instanceof DOMElement) {
        return '';
    }

    $clean = function (DOMNode $node) use (&$clean, $allowed, $drop, $urlAttrs, $okSchemes, $filterStyle): void {
        // Snapshot children first: we mutate the live NodeList as we go.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue; // text is serialized with entity-encoding — safe
            }
            if (!$child instanceof DOMElement) {
                $node->removeChild($child); // comments, PIs, CDATA → gone
                continue;
            }

            $tag = strtolower($child->localName ?? $child->nodeName);

            if (isset($drop[$tag])) {
                $node->removeChild($child);
                continue;
            }

            if (!isset($allowed[$tag])) {
                // Unknown-but-benign tag: clean its subtree, then unwrap it so
                // any legitimate text content is preserved.
                $clean($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Allowed tag: drop every attribute not on its own allowlist. This
            // is what kills on*= handlers (quoted, unquoted, or weirdly spaced).
            $allowAttrs = $allowed[$tag];
            foreach (iterator_to_array($child->attributes) as $attr) {
                $an = strtolower($attr->nodeName);
                if (!in_array($an, $allowAttrs, true)) {
                    $child->removeAttribute($attr->nodeName);
                    continue;
                }
                if (in_array($an, $urlAttrs, true)) {
                    // Decode entities, strip control/whitespace chars, then
                    // enforce the scheme allowlist (relative URLs are fine).
                    $val = html_entity_decode((string) $attr->nodeValue, ENT_QUOTES | ENT_HTML5);
                    $val = preg_replace('/[\x00-\x20]+/', '', $val) ?? '';
                    if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $val, $m)
                        && !in_array(strtolower($m[1]), $okSchemes, true)) {
                        $child->removeAttribute($attr->nodeName);
                    }
                } elseif ($an === 'style' && $filterStyle) {
                    $safe = sanitizeStyleAttr((string) $attr->nodeValue);
                    if ($safe === '') {
                        $child->removeAttribute('style');
                    } else {
                        $child->setAttribute('style', $safe);
                    }
                }
            }
            // Any link that opens a new tab must not leak window.opener.
            if ($tag === 'a' && $child->hasAttribute('target')) {
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
            }

            $clean($child); // recurse into the kept element
        }
    };

    $clean($root);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $c) {
        $out .= $dom->saveHTML($c);
    }
    return trim($out);
}

/**
 * Filter a CSS `style` attribute down to a small allowlist of presentational
 * properties with script-free values. Preserves common CKEditor output (table
 * borders, image sizing, text alignment) while dropping anything that could
 * carry a script vector (url(), expression(), @import, backslash escapes).
 */
function sanitizeStyleAttr(string $css): string
{
    static $allowedProps = [
        'text-align', 'width', 'height', 'max-width', 'min-width',
        'background-color', 'background', 'color', 'border', 'border-color',
        'border-width', 'border-style', 'border-collapse', 'padding', 'margin',
        'vertical-align', 'font-weight', 'font-style', 'text-decoration', 'float',
    ];
    $out = [];
    foreach (explode(';', $css) as $decl) {
        if (strpos($decl, ':') === false) {
            continue;
        }
        [$prop, $val] = explode(':', $decl, 2);
        $prop = strtolower(trim($prop));
        $val  = trim($val);
        if ($val === '' || !in_array($prop, $allowedProps, true)) {
            continue;
        }
        // Reject any value that could smuggle script/resource loads.
        if (preg_match('/url\s*\(|expression|javascript:|@import|<|\\\\/i', $val)) {
            continue;
        }
        // Allow only a safe character set (covers rgb()/rgba()/hsl() colours).
        if (!preg_match('/^[#a-z0-9 .,%()\-]+$/i', $val)) {
            continue;
        }
        $out[] = $prop . ': ' . $val;
    }
    return implode('; ', $out);
}

/**
 * Sanitize the restrictive set of formatting allowed in status banners.
 */
function sanitizeBannerHtml(string $html): string
{
    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'span' => [], 'div' => [], 'ul' => [], 'ol' => [],
        'li' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [], 'blockquote' => [],
        'code' => [], 'pre' => [],
        'a' => ['href', 'title', 'target', 'rel'],
    ];
    $drop = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template',
        'noscript', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'link', 'meta', 'base', 'frame', 'frameset', 'applet', 'audio', 'video', 'source',
    ];
    return sanitizeHtmlAllowlist($html, $allowed, $drop, ['href'], ['http', 'https', 'mailto'], false);
}

/**
 * Sanitize rich CKEditor content for ticket descriptions, timeline comments,
 * and KB article bodies. This is a hard stored-XSS boundary applied at render
 * time — it neutralises any malicious markup that may already be in the
 * database, not just new writes. The allowlist covers the tag/attribute set the
 * WYSIWYG editor actually emits (headings, lists, tables, images, links).
 */
function sanitizeRichHtml(string $html): string
{
    $allowed = [
        'p' => ['class', 'style'], 'br' => [], 'hr' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'sub' => [], 'sup' => [], 'mark' => [], 'small' => [], 'del' => [], 'ins' => [], 'abbr' => ['title'],
        'span' => ['class', 'style'], 'div' => ['class', 'style'],
        'ul' => ['class'], 'ol' => ['class', 'start', 'type'], 'li' => ['class'],
        'h1' => ['class'], 'h2' => ['class'], 'h3' => ['class'], 'h4' => ['class'],
        'h5' => ['class'], 'h6' => ['class'],
        'blockquote' => ['class'], 'code' => ['class'], 'pre' => ['class'],
        'a' => ['href', 'title', 'target', 'rel', 'class'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'class', 'style'],
        'figure' => ['class', 'style'], 'figcaption' => ['class'],
        'table' => ['class', 'style', 'border', 'cellpadding', 'cellspacing'],
        'thead' => ['class'], 'tbody' => ['class'], 'tfoot' => ['class'],
        'tr' => ['class', 'style'],
        'th' => ['class', 'style', 'colspan', 'rowspan', 'scope'],
        'td' => ['class', 'style', 'colspan', 'rowspan'],
        'caption' => ['class'],
    ];
    $drop = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template',
        'noscript', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'link', 'meta', 'base', 'frame', 'frameset', 'applet', 'audio', 'video', 'source',
    ];
    return sanitizeHtmlAllowlist($html, $allowed, $drop, ['href', 'src'], ['http', 'https', 'mailto', 'tel'], true);
}

/* ── Ticket status helpers ───────────────────────────────────────────────────
 *
 * All ticket statuses now come from the `ticket_statuses` lookup table
 * (see migration 041_ticket_statuses.php). These helpers read once per
 * request and cache the result in a static, so callers can use them as
 * cheaply as a constant lookup.
 *
 * Naming convention:
 *   - "ActiveSlugs"  → only is_active=1 (use for accepting new writes / dropdowns)
 *   - "BucketSlugs"  → all states, active and inactive (use for SQL filters that
 *                      need to bucket historical rows whose slug may have been
 *                      deactivated since)
 *   - Label/color/meta helpers also accept inactive slugs so historical tickets
 *     still render their badge.
 * ──────────────────────────────────────────────────────────────────────────── */

/* ────────────────────────────────────────────────────────────────────────────
 * Roles & granular permissions (migration 042)
 *
 * Roles live in the `roles` table; each role grants a set of permission keys
 * via `role_permissions`. These resolvers are keyed by role *slug* (the value
 * stored in users.role) so both the session-based Auth class and the
 * token-based REST API (`_apiIsStaff` etc.) can share one source of truth.
 *
 * The whole map is loaded once per request and memoised — at helpdesk scale
 * that single indexed query is negligible, and resolving fresh each request
 * means an admin's permission change takes effect on the user's next request
 * (no stale-until-logout window from snapshotting perms into the session).
 * ──────────────────────────────────────────────────────────────────────────── */

/**
 * Internal: load every role with its granted permission keys, indexed by slug.
 * Cached for the request. Returns [] if the tables are missing (paranoid guard
 * for code paths that somehow run before migration 042 applies — callers then
 * fail closed: no perms, not staff, not admin).
 */
function _rolesCache(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    try {
        $db    = Database::connect();
        $roles = $db->query(
            "SELECT id, slug, name, is_admin, is_staff, landing
             FROM roles ORDER BY sort_order, id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $perms = $db->query(
            "SELECT role_id, perm_key FROM role_permissions"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return $cache = [];
    }
    $cache  = [];
    $idToSlug = [];
    foreach ($roles as $r) {
        $cache[$r['slug']] = [
            'id'       => (int) $r['id'],
            'slug'     => (string) $r['slug'],
            'name'     => (string) $r['name'],
            'is_admin' => (int) $r['is_admin'] === 1,
            'is_staff' => (int) $r['is_staff'] === 1,
            'landing'  => (string) $r['landing'],
            'perms'    => [],
        ];
        $idToSlug[(int) $r['id']] = $r['slug'];
    }
    foreach ($perms as $p) {
        $slug = $idToSlug[(int) $p['role_id']] ?? null;
        if ($slug !== null) {
            $cache[$slug]['perms'][(string) $p['perm_key']] = true;
        }
    }
    return $cache;
}

/** The full role record for a slug, or null if the role is unknown/deleted. */
function roleRecord(?string $slug): ?array
{
    if ($slug === null || $slug === '') {
        return null;
    }
    return _rolesCache()[$slug] ?? null;
}

/**
 * Does this role slug grant a permission? Admin roles (is_admin) grant
 * everything; an unknown/deleted slug grants nothing (fail closed).
 */
function roleCan(?string $slug, string $perm): bool
{
    $r = roleRecord($slug);
    if ($r === null) {
        return false;
    }
    return $r['is_admin'] || isset($r['perms'][$perm]);
}

/** Is this role a full-access admin role? */
function roleIsAdmin(?string $slug): bool
{
    $r = roleRecord($slug);
    return $r !== null && $r['is_admin'];
}

/** Is this role a staff (agent-interface) role? */
function roleIsStaff(?string $slug): bool
{
    $r = roleRecord($slug);
    return $r !== null && $r['is_staff'];
}

/** Where the home route ('/') should send this role. Unknown → portal. */
function roleLandingPath(?string $slug): string
{
    $r = roleRecord($slug);
    return match ($r['landing'] ?? 'portal') {
        'admin' => '/admin',
        'agent' => '/agent',
        default => '/portal',
    };
}

/** The permission catalog grouped by category, for the role-edit matrix UI. */
function rolePermissionCatalog(): array
{
    try {
        $rows = Database::connect()->query(
            "SELECT perm_key, label, category, description FROM permissions ORDER BY sort_order, id"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
    $byCategory = [];
    foreach ($rows as $r) {
        $byCategory[$r['category']][] = $r;
    }
    return $byCategory;
}

/** Flat list of every valid permission key — used to validate posted grants. */
function rolePermissionKeys(): array
{
    try {
        return Database::connect()
            ->query('SELECT perm_key FROM permissions')
            ->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        return [];
    }
}

/** Human-readable name for a role slug (falls back to a prettified slug). */
function roleLabel(?string $slug): string
{
    $r = roleRecord($slug);
    if ($r !== null) {
        return $r['name'];
    }
    return ucwords(str_replace('_', ' ', (string) $slug));
}

/** Ordered [slug => display name] of every role — for user-form dropdowns. */
function roleChoices(): array
{
    $out = [];
    foreach (_rolesCache() as $slug => $r) {
        $out[$slug] = $r['name'];
    }
    return $out;
}

/** Does a role slug exist? Used to validate role values on user writes. */
function roleExists(?string $slug): bool
{
    return roleRecord($slug) !== null;
}

/**
 * May a user holding $actorSlug assign $targetSlug to someone? Admins may assign
 * any level; everyone else only a level whose capabilities are a subset of their
 * own — so a non-admin with users.manage can never grant a permission they don't
 * themselves hold, and never the admin level. Closes privilege escalation via
 * the user editor. A role is always assignable by itself (equal set) and 'user'
 * (empty set) is assignable by anyone staff.
 */
function roleAssignableBy(?string $actorSlug, ?string $targetSlug): bool
{
    $actor = roleRecord($actorSlug);
    if ($actor === null) {
        return false;
    }
    if ($actor['is_admin']) {
        return true;
    }
    $target = roleRecord($targetSlug);
    if ($target === null || $target['is_admin']) {
        return false;
    }
    foreach (array_keys($target['perms'] ?? []) as $perm) {
        if (!isset($actor['perms'][$perm])) {
            return false;
        }
    }
    return true;
}

/** [slug => name] of the roles $actorSlug may assign — for user-form dropdowns. */
function assignableRoleChoices(?string $actorSlug): array
{
    $out = [];
    foreach (roleChoices() as $slug => $name) {
        if (roleAssignableBy($actorSlug, $slug)) {
            $out[$slug] = $name;
        }
    }
    return $out;
}

/**
 * Slugs of every staff role (is_staff=1). Replaces the hardcoded
 * ['agent','admin','power_user'] lists scattered through the app. Falls back to
 * the three built-in staff slugs if the table can't be read.
 */
function staffRoleSlugs(): array
{
    $out = [];
    foreach (_rolesCache() as $slug => $r) {
        if ($r['is_staff']) {
            $out[] = $slug;
        }
    }
    return $out ?: ['admin', 'agent', 'power_user'];
}

/**
 * A safe SQL `IN (...)` predicate of quoted staff-role slugs for a column, e.g.
 *   staffRoleSqlIn('u.role')  →  "u.role IN ('admin','agent','power_user')"
 *
 * Slugs originate only from the roles table (never user input) and are scrubbed
 * to the slug charset here, so inlining them is injection-safe and avoids
 * threading variadic placeholders through the ~40 existing "staff" queries.
 */
function staffRoleSqlIn(string $column): string
{
    $quoted = array_map(static function (string $s): string {
        return "'" . preg_replace('/[^a-z0-9_]/i', '', $s) . "'";
    }, staffRoleSlugs());
    return $column . ' IN (' . implode(',', $quoted) . ')';
}

/**
 * Internal: fetch all status rows, indexed by slug, ordered by sort_order.
 * Cached for the request. Pass $refresh=true after writing to the table
 * within the same request to re-query (the admin settings save handler uses
 * this so its own POST-then-redirect cycle reflects fresh data if it ever
 * needs to read back within the same script).
 *
 * Returns [] if the table is missing (paranoid guard for code paths that
 * somehow run before migration 041 applies).
 */
function _ticketStatusCache(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    try {
        $rows = Database::connect()
            ->query(
                "SELECT id, slug, label, bucket, pauses_sla, sort_order, color,
                        is_default_new, is_default_resolved, is_default_closed,
                        is_system, is_active
                 FROM ticket_statuses
                 ORDER BY sort_order, id"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return $cache = [];
    }
    $cache = [];
    foreach ($rows as $r) {
        $cache[$r['slug']] = [
            'id'                  => (int) $r['id'],
            'slug'                => (string) $r['slug'],
            'label'               => (string) $r['label'],
            'bucket'              => (string) $r['bucket'],
            'pauses_sla'          => (int) $r['pauses_sla'] === 1,
            'sort_order'          => (int) $r['sort_order'],
            'color'               => (string) $r['color'],
            'is_default_new'      => (int) $r['is_default_new'] === 1,
            'is_default_resolved' => (int) $r['is_default_resolved'] === 1,
            'is_default_closed'   => (int) $r['is_default_closed'] === 1,
            'is_system'           => (int) $r['is_system'] === 1,
            'is_active'           => (int) $r['is_active'] === 1,
        ];
    }
    return $cache;
}

/** All status rows (active + inactive), ordered by sort_order. */
function ticketStatuses(): array
{
    return array_values(_ticketStatusCache());
}

/** Active status rows only, ordered. Use for dropdowns and admin pickers. */
function ticketActiveStatuses(): array
{
    return array_values(array_filter(
        _ticketStatusCache(),
        static fn(array $s) => $s['is_active']
    ));
}

/** All slugs (active + inactive). Use for "does this slug exist?" checks. */
function ticketStatusSlugs(): array
{
    return array_keys(_ticketStatusCache());
}

/** Active slugs only. Use for validating incoming status writes. */
function ticketActiveStatusSlugs(): array
{
    return array_map(static fn(array $s) => $s['slug'], ticketActiveStatuses());
}

/**
 * Slugs in the "open" bucket — replaces the hardcoded
 * `IN ('open','in_progress','pending')` filters scattered through the codebase.
 * Includes inactive rows so historical tickets keep counting.
 */
function ticketOpenBucketSlugs(): array
{
    return array_values(array_map(
        static fn(array $s) => $s['slug'],
        array_filter(_ticketStatusCache(), static fn(array $s) => $s['bucket'] === 'open')
    ));
}

/**
 * Slugs in the "closed" bucket — replaces hardcoded `IN ('resolved','closed')`
 * and `NOT IN ('resolved','closed')` filters.
 */
function ticketClosedBucketSlugs(): array
{
    return array_values(array_map(
        static fn(array $s) => $s['slug'],
        array_filter(_ticketStatusCache(), static fn(array $s) => $s['bucket'] === 'closed')
    ));
}

/** Slugs that pause SLA timers — replaces the inline $pausingStatuses arrays. */
function ticketSlaPausingSlugs(): array
{
    return array_values(array_map(
        static fn(array $s) => $s['slug'],
        array_filter(_ticketStatusCache(), static fn(array $s) => $s['pauses_sla'])
    ));
}

/**
 * Slug assigned to new tickets. Replaces hardcoded `'open'` fallbacks.
 * Always returns a slug; falls back to the original 'open' if the flag was
 * somehow cleared (defensive — guardrails prevent this in normal use).
 */
function ticketDefaultNewStatusSlug(): string
{
    foreach (_ticketStatusCache() as $s) {
        if ($s['is_default_new']) {
            return $s['slug'];
        }
    }
    return 'open';
}

/** Slug that fires the "ticket resolved" email template. */
function ticketDefaultResolvedStatusSlug(): string
{
    foreach (_ticketStatusCache() as $s) {
        if ($s['is_default_resolved']) {
            return $s['slug'];
        }
    }
    return 'resolved';
}

/** Slug that fires the "ticket closed" email template. */
function ticketDefaultClosedStatusSlug(): string
{
    foreach (_ticketStatusCache() as $s) {
        if ($s['is_default_closed']) {
            return $s['slug'];
        }
    }
    return 'closed';
}

/**
 * Friendly label for a status slug. Falls back to a humanized version of the
 * slug if the row doesn't exist (so an orphan `tickets.status` value still
 * renders something readable instead of "" or a 500).
 */
function ticketStatusLabel(string $slug): string
{
    $row = _ticketStatusCache()[$slug] ?? null;
    if ($row !== null) {
        return $row['label'];
    }
    return ucwords(str_replace('_', ' ', $slug));
}

/** Hex color for a status slug. Falls back to a neutral gray for orphans. */
function ticketStatusColor(string $slug): string
{
    return _ticketStatusCache()[$slug]['color'] ?? '#6c757d';
}

/**
 * Full row for a slug (or null). Use when a template needs more than just the
 * label/color — e.g., the bucket or pauses_sla flag.
 */
function ticketStatusMeta(string $slug): ?array
{
    return _ticketStatusCache()[$slug] ?? null;
}

/**
 * Re-query the lookup table from disk and replace the request-scoped cache.
 * Call after writing to ticket_statuses inside the same request.
 */
function ticketStatusCacheRefresh(): void
{
    _ticketStatusCache(true);
}

/**
 * Find every place a status slug is referenced, so the admin settings UI can
 * block destructive actions (delete / deactivate) on still-referenced rows.
 *
 * Returns an array keyed by reference kind:
 *   [
 *     'tickets'         => int,                                  // count of rows
 *     'automations'     => [['id'=>int,'name'=>string], ...],    // names for the error UI
 *     'escalation_rules'=> [['id'=>int,'name'=>string], ...],
 *     'csat_trigger'    => bool,                                 // csat_trigger_status setting points here
 *     'is_default_new'      => bool,
 *     'is_default_resolved' => bool,
 *     'is_default_closed'   => bool,
 *   ]
 *
 * Automations and escalation_rules store their conditions/actions as JSON
 * strings in longtext columns, so we walk the parsed structure rather than
 * relying on LIKE — that's both correct (no false positives from substrings
 * inside other field names) and resilient to future schema additions.
 */
function ticketStatusReferences(string $slug): array
{
    $db = Database::connect();

    $refs = [
        'tickets'              => 0,
        'automations'          => [],
        'escalation_rules'     => [],
        'csat_trigger'         => false,
        'is_default_new'       => false,
        'is_default_resolved'  => false,
        'is_default_closed'    => false,
    ];

    // 1. Tickets currently holding this slug
    $s = $db->prepare('SELECT COUNT(*) FROM tickets WHERE status = ?');
    $s->execute([$slug]);
    $refs['tickets'] = (int) $s->fetchColumn();

    // 2. Default flags on the row itself
    $s = $db->prepare(
        'SELECT is_default_new, is_default_resolved, is_default_closed
         FROM ticket_statuses WHERE slug = ?'
    );
    $s->execute([$slug]);
    if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
        $refs['is_default_new']      = (int) $row['is_default_new']      === 1;
        $refs['is_default_resolved'] = (int) $row['is_default_resolved'] === 1;
        $refs['is_default_closed']   = (int) $row['is_default_closed']   === 1;
    }

    // 3. CSAT trigger setting
    if (getSetting('csat_trigger_status', '') === $slug) {
        $refs['csat_trigger'] = true;
    }

    // 4. Automations — walk conditions + actions JSON looking for status refs
    foreach (['automations', 'escalation_rules'] as $table) {
        $rows = $db->query("SELECT id, name, conditions, actions FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $conds   = json_decode((string) $r['conditions'], true) ?: [];
            $actions = json_decode((string) $r['actions'],    true) ?: [];
            if (_statusAppearsInRules($conds, $slug) || _statusAppearsInRules($actions, $slug)) {
                $refs[$table][] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
            }
        }
    }

    return $refs;
}

/**
 * Internal: does a parsed conditions/actions array reference the given slug
 * as a status value? Match shapes:
 *   [{"field":"status","value":"<slug>"}, ...]   (conditions)
 *   [{"action":"set_status","value":"<slug>"}, ...]   (actions)
 */
function _statusAppearsInRules(array $rules, string $slug): bool
{
    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $value = (string) ($rule['value'] ?? '');
        if ($value !== $slug) {
            continue;
        }
        $isStatusCondition = ($rule['field']  ?? '') === 'status';
        $isStatusAction    = ($rule['action'] ?? '') === 'set_status';
        if ($isStatusCondition || $isStatusAction) {
            return true;
        }
    }
    return false;
}

/**
 * Build a SQL `<col> IN (...)` (or `NOT IN (...)`) fragment from a slug array,
 * with each value PDO-quoted so it's safe to string-concatenate into a query.
 * Replaces the hardcoded `status IN ('open','in_progress','pending')` patterns
 * scattered through the codebase. If $slugs is empty the fragment evaluates to
 * a safe false (or true, for negate=true) so callers don't have to special-case
 * the empty list.
 */
function ticketStatusSqlIn(array $slugs, string $col = 'status', bool $negate = false): string
{
    if (empty($slugs)) {
        return $negate ? '1=1' : '1=0';
    }
    $pdo = Database::connect();
    $quoted = array_map(static fn(string $s) => $pdo->quote($s), array_values($slugs));
    $op = $negate ? 'NOT IN' : 'IN';
    return "{$col} {$op} (" . implode(',', $quoted) . ")";
}

/**
 * Pick a readable text color (black or white) for the given hex background.
 * Uses ITU-R BT.601 luma; the 140 threshold is the same one Bootstrap uses
 * internally for its `text-bg-*` utility classes, so admin-picked colors look
 * consistent with the built-in ones.
 */
function ticketStatusTextColor(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#ffffff';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luma = 0.299 * $r + 0.587 * $g + 0.114 * $b;
    return $luma > 140 ? '#000000' : '#ffffff';
}

/**
 * Slug → label map for active statuses, in sort_order. Use in template
 * dropdown loops that previously iterated an inline $statusLabels array.
 */
function ticketStatusLabelMap(): array
{
    $map = [];
    foreach (ticketActiveStatuses() as $s) {
        $map[$s['slug']] = $s['label'];
    }
    return $map;
}

/**
 * `style=""` attribute value for a status badge — background + auto-contrast
 * text color. Use directly in templates that need a custom label (e.g., portal
 * uses friendlier names than the slug suggests). When the label is the default,
 * prefer `ticketStatusBadgeHtml()` instead.
 */
function ticketStatusBadgeStyle(string $slug): string
{
    $bg = ticketStatusColor($slug);
    $fg = ticketStatusTextColor($bg);
    return sprintf(
        'background-color: %s; color: %s',
        htmlspecialchars($bg, ENT_QUOTES),
        $fg
    );
}

/**
 * Render the full status badge HTML for a slug. Replaces the per-template
 * `$statusColors[$slug]` / `$statusLabels[$slug]` lookup tables. Uses inline
 * style so admin-picked colors render without requiring CSS regeneration.
 */
function ticketStatusBadgeHtml(string $slug, string $extraClass = ''): string
{
    $bg = ticketStatusColor($slug);
    $fg = ticketStatusTextColor($bg);
    $label = ticketStatusLabel($slug);
    $class = trim('badge ' . $extraClass);
    return sprintf(
        '<span class="%s" style="background-color: %s; color: %s">%s</span>',
        htmlspecialchars($class, ENT_QUOTES),
        htmlspecialchars($bg, ENT_QUOTES),
        $fg,
        htmlspecialchars($label, ENT_QUOTES)
    );
}

