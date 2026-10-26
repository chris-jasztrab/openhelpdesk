<?php

declare(strict_types=1);

/**
 * Microsoft Teams helpdesk bot.
 *
 * Staff add the bot to a chat or channel and talk to it: "new Printer jammed",
 * "my tickets", "status 123", "comment 123 still broken", "close 123". Teams
 * delivers each message as a Bot Framework *activity* to POST /teams/bot/messages
 * (see routes.php); the bot answers by POSTing a reply activity back to the
 * serviceUrl named in the request.
 *
 * Trust model
 *  - Every inbound request carries a JWT issued by https://api.botframework.com
 *    for our Microsoft App ID. Signature (RS256 against Microsoft's JWKS), issuer,
 *    audience, expiry and the serviceurl claim are all checked before anything
 *    is read from the body. The serviceurl check is what stops a forged activity
 *    from pointing our reply (and the bot token in it) at an arbitrary host.
 *  - The person is identified by their Entra object id (from.aadObjectId), the
 *    same key SSO uses (users.azure_oid). An unknown oid is linked/created by
 *    email only when the message comes from the configured tenant — the same
 *    rule as the SSO callback.
 *  - Ticket access uses userCanAccessTicket(), the rule the REST API enforces.
 *    Confidential tickets are never shown: a channel is a shared surface.
 *
 * Settings (admin → Settings → Microsoft Teams → Helpdesk bot):
 *   teams_bot_enabled     '1' / '0'
 *   teams_bot_app_id      Microsoft App ID of the Azure Bot (blank = the SSO app)
 *   teams_bot_app_secret  its client secret           (blank = the SSO secret)
 *   teams_bot_tenant_id   Entra tenant GUID           (blank = the SSO tenant)
 *   teams_bot_token_cache / teams_bot_jwks_cache  — internal caches
 */
class TeamsBot
{
    private const ISSUER      = 'https://api.botframework.com';
    private const OPENID_CONF = 'https://login.botframework.com/v1/.well-known/openidconfiguration';
    private const TOKEN_SCOPE = 'https://api.botframework.com/.default';

    /* ── configuration ─────────────────────────────────────────────────── */

    public static function enabled(): bool
    {
        return getSetting('teams_bot_enabled', '0') === '1' && self::appId() !== '';
    }

    public static function appId(): string
    {
        $id = trim(getSetting('teams_bot_app_id', ''));
        return $id !== '' ? $id : trim(getSetting('sso_client_id', ''));
    }

    private static function appSecret(): string
    {
        $s = getSetting('teams_bot_app_secret', '');
        return $s !== '' ? $s : getSetting('sso_client_secret', '');
    }

    /** Entra tenant the bot serves; '' when the app is multi-tenant. */
    public static function tenantId(): string
    {
        $t = trim(getSetting('teams_bot_tenant_id', ''));
        if ($t === '') {
            $t = trim(getSetting('sso_tenant_id', ''));
        }
        return in_array(strtolower($t), ['common', 'organizations', 'consumers'], true) ? '' : $t;
    }

    /** The messaging endpoint to paste into the Azure Bot resource. */
    public static function endpointUrl(): string
    {
        return rtrim((string) env('APP_URL', ''), '/') . '/teams/bot/messages';
    }

    /* ── inbound ───────────────────────────────────────────────────────── */

    /** Route handler for POST /teams/bot/messages. Always exits. */
    public static function handleRequest(): void
    {
        header('Content-Type: application/json');
        if (!self::enabled()) {
            http_response_code(404);
            echo '{"error":"Teams bot is not enabled."}';
            exit;
        }
        $activity = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($activity) || empty($activity['type'])) {
            http_response_code(400);
            echo '{"error":"Bad activity."}';
            exit;
        }
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)
            || !self::verifyToken($m[1], (string) ($activity['serviceUrl'] ?? ''))) {
            http_response_code(401);
            echo '{"error":"Unauthorized."}';
            exit;
        }
        session_write_close(); // the bot never touches the session; don't hold the lock across calls to Microsoft

        try {
            $reply = self::handleActivity(Database::connect(), $activity);
        } catch (\Throwable $e) {
            error_log('Teams bot: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $reply = self::text('Sorry, something went wrong on the helpdesk side. Please try again in a moment.');
        }
        if ($reply !== null) {
            self::send($activity, $reply);
        }
        http_response_code(200);
        echo '{}';
        exit;
    }

    /**
     * Turn one activity into a reply message (or null for nothing to say).
     * Separated from the HTTP plumbing so it can be exercised directly.
     */
    public static function handleActivity(PDO $db, array $activity): ?array
    {
        $type  = (string) ($activity['type'] ?? '');
        $botId = (string) ($activity['recipient']['id'] ?? '');

        if ($type === 'conversationUpdate') {
            foreach ($activity['membersAdded'] ?? [] as $member) {
                if (($member['id'] ?? '') === $botId) {
                    return self::helpCard(); // just added to a chat/channel — introduce ourselves
                }
            }
            return null;
        }
        if ($type !== 'message') {
            return null;
        }

        $cmd = self::parseCommand(self::commandText($activity));
        if ($cmd['cmd'] === 'help' || $cmd['cmd'] === 'unknown') {
            return self::helpCard($cmd['cmd'] === 'unknown');
        }

        $user = self::resolveUser($db, $activity);
        if ($user === null) {
            $base = rtrim((string) env('APP_URL', ''), '/');
            return self::text("I couldn't match your Teams account to a helpdesk account. "
                . "Sign in to the helpdesk once with your Microsoft account" . ($base !== '' ? " at {$base}" : '')
                . ", then try again.");
        }

        return match ($cmd['cmd']) {
            'new'     => self::createTicket($db, $user, $cmd['text']),
            'list'    => self::listTickets($db, $user),
            'show'    => self::showTicket($db, $user, $cmd['id']),
            'comment' => self::addComment($db, $user, $cmd['id'], $cmd['text']),
            default   => self::changeStatus($db, $user, $cmd['id'], $cmd['cmd'], $cmd['text']), // close / resolve / reopen
        };
    }

    /**
     * The text the person typed, minus the @mention of the bot. A card button
     * (Action.Submit) arrives with no text and a `value` object instead; that is
     * rebuilt into the equivalent typed command so one parser serves both.
     */
    private static function commandText(array $activity): string
    {
        $value = $activity['value'] ?? null;
        if (is_array($value) && isset($value['cmd'])) {
            return trim((string) $value['cmd'] . ' ' . (string) ($value['id'] ?? '') . ' ' . (string) ($value['text'] ?? ''));
        }
        $text = (string) ($activity['text'] ?? '');
        foreach ($activity['entities'] ?? [] as $ent) {
            if (($ent['type'] ?? '') === 'mention' && !empty($ent['text'])) {
                $text = str_replace((string) $ent['text'], ' ', $text);
            }
        }
        $text = preg_replace('#<at>.*?</at>#is', ' ', $text) ?? $text;
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Parse a typed command. Pure; unit-tested.
     *
     * @return array{cmd:string,id:int,text:string}
     *   cmd ∈ help | list | show | new | close | resolve | reopen | comment | unknown
     */
    public static function parseCommand(string $text): array
    {
        $t = trim($text);
        $r = static fn (string $cmd, int $id = 0, string $text = ''): array =>
            ['cmd' => $cmd, 'id' => $id, 'text' => trim($text)];

        if ($t === '' || preg_match('/^(help|\?|hi|hello|hey|commands)[.!]?$/i', $t)) {
            return $r('help');
        }
        if (preg_match('/^(my tickets|my open tickets|mine|list|tickets)[.!]?$/i', $t)) {
            return $r('list');
        }
        if (preg_match('/^(?:status|show|ticket|info|view|get|check|open)?\s*#?(\d+)\s*\??$/i', $t, $m)) {
            return $r('show', (int) $m[1]);
        }
        if (preg_match('/^(close|resolve|reopen)\s+#?(\d+)\b\s*[:\-]?\s*(.*)$/is', $t, $m)) {
            return $r(strtolower($m[1]), (int) $m[2], $m[3]);
        }
        if (preg_match('/^(?:comment|reply|note|add|update)\s+(?:on\s+)?#?(\d+)\b\s*[:\-]?\s*(.+)$/is', $t, $m)) {
            return $r('comment', (int) $m[1], $m[2]);
        }
        if (preg_match('/^(?:new|open|create)(?:\s+(?:a\s+)?ticket)?\s*[:\-]?\s*(.+)$/is', $t, $m)) {
            return $r('new', 0, $m[1]);
        }
        return $r('unknown', 0, $t);
    }

    /* ── identity ──────────────────────────────────────────────────────── */

    /** The helpdesk user behind the message, or null when they can't be matched safely. */
    private static function resolveUser(PDO $db, array $activity): ?array
    {
        $from = is_array($activity['from'] ?? null) ? $activity['from'] : [];
        $oid  = trim((string) ($from['aadObjectId'] ?? ''));
        if ($oid === '') {
            return null;
        }
        $st = $db->prepare('SELECT * FROM users WHERE azure_oid = ? LIMIT 1');
        $st->execute([$oid]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return $user;
        }

        // No oid on file. Linking or creating by (mutable) email is only safe when
        // the message provably comes from our own tenant — same gate as SSO.
        $tenant    = self::tenantId();
        $msgTenant = (string) ($activity['conversation']['tenantId'] ?? $activity['channelData']['tenant']['id'] ?? '');
        if ($tenant === '' || strcasecmp($tenant, $msgTenant) !== 0) {
            return null;
        }

        $profile = self::member($activity, (string) ($from['id'] ?? ''));
        $email   = trim((string) ($profile['email'] ?? $profile['userPrincipalName'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $st = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $db->prepare('UPDATE users SET azure_oid = ? WHERE id = ?')->execute([$oid, $user['id']]);
            $user['azure_oid'] = $oid;
            return $user;
        }

        $first = trim((string) ($profile['givenName'] ?? ''));
        $last  = trim((string) ($profile['surname'] ?? ''));
        if ($first === '') {
            $parts = explode(' ', trim((string) ($profile['name'] ?? $from['name'] ?? 'Teams User')), 2);
            $first = $parts[0];
            $last  = $last !== '' ? $last : ($parts[1] ?? 'User');
        }
        $db->prepare(
            "INSERT INTO users (first_name, last_name, email, azure_oid, password, role) VALUES (?, ?, ?, ?, '', 'user')"
        )->execute([$first, $last !== '' ? $last : 'User', $email, $oid]);
        $st = $db->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([(int) $db->lastInsertId()]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Teams member profile (email, names) via the Bot Connector. */
    private static function member(array $activity, string $memberId): array
    {
        $serviceUrl = rtrim((string) ($activity['serviceUrl'] ?? ''), '/');
        $convId     = (string) ($activity['conversation']['id'] ?? '');
        $token      = self::botToken();
        if ($serviceUrl === '' || $convId === '' || $memberId === '' || $token === null) {
            return [];
        }
        [$code, $body] = self::http('GET', $serviceUrl . '/v3/conversations/' . rawurlencode($convId) . '/members/' . rawurlencode($memberId), null, ['Authorization: Bearer ' . $token]);
        $json = json_decode($body, true);
        return $code === 200 && is_array($json) ? $json : [];
    }

    /* ── commands ──────────────────────────────────────────────────────── */

    private static function createTicket(PDO $db, array $user, string $text): array
    {
        $parts       = preg_split('/\r?\n|\s\|\s/', $text, 2) ?: [$text];
        $subject     = trim(mb_substr(trim($parts[0]), 0, 255));
        $description = trim($parts[1] ?? '');
        if ($subject === '') {
            return self::text('Tell me what the ticket is about, e.g. **new Printer on the 2nd floor is jammed**.');
        }
        if ($description === '') {
            $description = $subject;
        }
        $userId     = (int) $user['id'];
        $priorityId = getDefaultPriorityId($db);
        $groupId    = resolveTicketGroup($db, null, null);
        $locationId = !empty($user['location_id']) ? (int) $user['location_id'] : null;

        $db->prepare(
            'INSERT INTO tickets (subject, description, created_by, status, type_id, priority_id, location_id, group_id)
             VALUES (?, ?, ?, ?, NULL, ?, ?, ?)'
        )->execute([$subject, $description, $userId, ticketDefaultNewStatusSlug(), $priorityId, $locationId, $groupId]);
        $ticketId = (int) $db->lastInsertId();

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$ticketId, $userId, 'created', 'Ticket created via Microsoft Teams']);

        if ($priorityId) {
            Sla::initializeForTicket($db, $ticketId, $priorityId, null);
        }
        $picked = runPostTicketCreateHooks($db, $ticketId); // AI classification + auto-assign
        notifyRequesterTicketCreated($db, $ticketId);
        if ($picked !== null) {
            notifyAssignedAgent($db, $ticketId, $picked);
            notifyRequesterTicketAssigned($db, $ticketId, $picked);
        }
        return self::ticketCard($db, $user, $ticketId, '✅ Ticket created');
    }

    private static function listTickets(PDO $db, array $user): array
    {
        $userId = (int) $user['id'];
        $staff  = roleIsStaff($user['role'] ?? null);
        $st = $db->prepare(
            "SELECT t.id, t.subject, t.status, t.assigned_to
               FROM tickets t
              WHERE (t.created_by = ?" . ($staff ? ' OR t.assigned_to = ?' : '') . ")
                AND t.status IN (SELECT slug FROM ticket_statuses WHERE bucket = 'open')
              ORDER BY t.updated_at DESC
              LIMIT 10"
        );
        $st->execute($staff ? [$userId, $userId] : [$userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return self::text('You have no open tickets. Say **new** followed by a subject to open one.');
        }
        $what = $staff ? 'Tickets you opened or are assigned' : 'Your open tickets';
        $body = [
            ['type' => 'TextBlock', 'size' => 'Medium', 'weight' => 'Bolder', 'wrap' => true,
             'text' => '🎫 ' . $what . ' (' . count($rows) . ')'],
        ];
        foreach ($rows as $r) {
            $mine = $staff && (int) $r['assigned_to'] === $userId ? ' · assigned to you' : '';
            $body[] = ['type' => 'TextBlock', 'wrap' => true, 'spacing' => 'Small',
                       'text' => '**#' . (int) $r['id'] . '** ' . self::md((string) $r['subject'])
                           . ' — _' . ticketStatusLabel((string) $r['status']) . $mine . '_'];
        }
        $body[] = ['type' => 'TextBlock', 'wrap' => true, 'isSubtle' => true, 'size' => 'Small', 'spacing' => 'Medium',
                   'text' => 'Say **status 123** for details on one of them.'];
        return Teams::wrap('Your tickets', $body);
    }

    private static function showTicket(PDO $db, array $user, int $id): array
    {
        $err = '';
        if (self::loadTicket($db, $user, $id, $err) === null) {
            return self::text($err);
        }
        return self::ticketCard($db, $user, $id, '🎫 Ticket #' . $id);
    }

    private static function changeStatus(PDO $db, array $user, int $id, string $action, string $note): array
    {
        $err    = '';
        $ticket = self::loadTicket($db, $user, $id, $err);
        if ($ticket === null) {
            return self::text($err);
        }
        $staff = roleIsStaff($user['role'] ?? null);
        if ($action === 'resolve' && !$staff) {
            return self::text("Only agents can mark a ticket resolved. You can **close {$id}** instead.");
        }
        $new = match ($action) {
            'resolve' => ticketDefaultResolvedStatusSlug(),
            'reopen'  => ticketDefaultNewStatusSlug(),
            default   => ticketDefaultClosedStatusSlug(),
        };
        $old = (string) $ticket['status'];
        if ($old === $new) {
            return self::text("Ticket #{$id} is already " . ticketStatusLabel($new) . '.');
        }
        $userId = (int) $user['id'];
        if ($note !== '') {
            self::insertComment($db, $user, $ticket, $note);
        }
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$new, $id]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, $userId, 'status_changed', "Status changed from {$old} to {$new}"]);
        notifyAgentStatusChanged($db, $id, $old, $new, $userId);
        if ($new === getSetting('csat_trigger_status', 'resolved')) {
            sendCsatSurvey($db, $id);
        }
        Sla::onStatusChanged($db, $id, $old, $new);
        return self::ticketCard($db, $user, $id, '✅ Ticket #' . $id . ' is now ' . ticketStatusLabel($new));
    }

    private static function addComment(PDO $db, array $user, int $id, string $message): array
    {
        $err    = '';
        $ticket = self::loadTicket($db, $user, $id, $err);
        if ($ticket === null) {
            return self::text($err);
        }
        if (trim($message) === '') {
            return self::text("What would you like to add to ticket #{$id}? e.g. **comment {$id} Still happening after a restart.**");
        }
        self::insertComment($db, $user, $ticket, $message);
        return self::ticketCard($db, $user, $id, '💬 Comment added to #' . $id);
    }

    /** Public reply on a ticket — the same steps as POST /api/v1/tickets/{id}/replies. */
    private static function insertComment(PDO $db, array $user, array $ticket, string $message): void
    {
        $ticketId = (int) $ticket['id'];
        $userId   = (int) $user['id'];
        $message  = trim($message);

        reopenOnRequesterReply($db, $ticketId, $userId);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$ticketId, $userId, 'comment', $message]);
        $timelineId = (int) $db->lastInsertId();
        processAtMentions($db, $message, $ticketId, $timelineId, $userId);

        if ((int) $ticket['created_by'] !== $userId && $ticket['first_responded_at'] === null) {
            $db->prepare('UPDATE tickets SET first_responded_at = NOW() WHERE id = ?')->execute([$ticketId]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
            )->execute([$ticketId, 'sla_set', 'First response recorded']);
        }
        $author = trim($user['first_name'] . ' ' . $user['last_name']);
        notifyTicketCreator($db, $ticketId, $message, $author);
        notifyCcUsers($db, $ticketId, $message, $author);
        notifyWatchers($db, $ticketId, $message, $author);
        notifyAssignedAgentReply($db, $ticketId, $message, $author, $userId);
    }

    /** Ticket row the user may act on, or null with a reason in $err. */
    private static function loadTicket(PDO $db, array $user, int $id, string &$err): ?array
    {
        $st = $db->prepare('SELECT * FROM tickets WHERE id = ?');
        $st->execute([$id]);
        $ticket = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            $err = "I couldn't find ticket #{$id}.";
            return null;
        }
        if (!empty($ticket['type_id'])) {
            $c = $db->prepare('SELECT is_confidential FROM ticket_types WHERE id = ?');
            $c->execute([$ticket['type_id']]);
            if ((int) $c->fetchColumn() === 1) {
                $err = "Ticket #{$id} is confidential — please open it in the helpdesk.";
                return null;
            }
        }
        if (!userCanAccessTicket($db, $user, $ticket)) {
            $err = "You don't have access to ticket #{$id}.";
            return null;
        }
        return $ticket;
    }

    /* ── cards ─────────────────────────────────────────────────────────── */

    private static function helpCard(bool $unknown = false): array
    {
        $app   = getSetting('branding_app_name', 'OpenHelpDesk');
        $intro = $unknown
            ? "I didn't catch that. Here's what I understand:"
            : "Hi! I'm the **{$app}** bot. In a channel, @mention me first. Here's what I can do:";
        $cmds = [
            ['new …',            'Open a ticket. Put extra details after a | or on a new line.'],
            ['my tickets',       'List your open tickets.'],
            ['status 123',       'Show ticket #123.'],
            ['comment 123 …',    'Add a reply to ticket #123.'],
            ['close 123 [note]', 'Close ticket #123, optionally with a note.'],
            ['reopen 123',       'Reopen a closed ticket.'],
            ['resolve 123',      'Mark a ticket resolved (agents).'],
        ];
        $facts = array_map(static fn (array $c) => ['title' => $c[0], 'value' => $c[1]], $cmds);
        return Teams::wrap('Helpdesk bot help', [
            ['type' => 'TextBlock', 'wrap' => true, 'text' => $intro],
            ['type' => 'FactSet', 'facts' => $facts],
        ]);
    }

    private static function ticketCard(PDO $db, array $user, int $ticketId, string $title): array
    {
        $info = Teams::ticketFacts($db, $ticketId);
        if ($info === null) {
            return self::text("I couldn't find ticket #{$ticketId}.");
        }
        $r    = $info['row'];
        $body = [
            ['type' => 'TextBlock', 'size' => 'Medium', 'weight' => 'Bolder', 'wrap' => true, 'text' => $title],
            ['type' => 'TextBlock', 'spacing' => 'None', 'wrap' => true, 'weight' => 'Bolder',
             'text' => '#' . (int) $r['id'] . ' — ' . self::md((string) $r['subject'])],
            ['type' => 'FactSet', 'facts' => $info['facts']],
        ];
        $desc = self::excerpt((string) $r['description']);
        if ($desc !== '') {
            $body[] = ['type' => 'TextBlock', 'wrap' => true, 'isSubtle' => true, 'text' => $desc];
        }
        $c = $db->prepare(
            "SELECT tl.details, tl.created_at, CONCAT(u.first_name, ' ', u.last_name) AS author
               FROM ticket_timeline tl LEFT JOIN users u ON u.id = tl.user_id
              WHERE tl.ticket_id = ? AND tl.action = 'comment' AND tl.is_internal = 0
              ORDER BY tl.id DESC LIMIT 1"
        );
        $c->execute([$ticketId]);
        if ($last = $c->fetch(PDO::FETCH_ASSOC)) {
            $body[] = ['type' => 'TextBlock', 'wrap' => true, 'spacing' => 'Medium', 'size' => 'Small',
                       'text' => '**Latest reply** — ' . self::md((string) ($last['author'] ?? 'Someone')) . ', ' . date('M j, g:i a', strtotime((string) $last['created_at']))];
            $body[] = ['type' => 'TextBlock', 'wrap' => true, 'spacing' => 'None', 'size' => 'Small', 'text' => self::excerpt((string) $last['details'], 300)];
        }
        $body[] = ['type' => 'Input.Text', 'id' => 'text', 'placeholder' => 'Add a reply…', 'isMultiline' => true];

        $actions = [
            ['type' => 'Action.Submit', 'title' => 'Send reply', 'data' => ['cmd' => 'comment', 'id' => $ticketId]],
        ];
        $bucket = ticketStatusMeta((string) $r['status'])['bucket'] ?? 'open';
        if ($bucket === 'closed') {
            $actions[] = ['type' => 'Action.Submit', 'title' => 'Reopen', 'data' => ['cmd' => 'reopen', 'id' => $ticketId]];
        } else {
            $actions[] = ['type' => 'Action.Submit', 'title' => 'Close ticket', 'data' => ['cmd' => 'close', 'id' => $ticketId]];
        }
        $url = self::ticketUrlFor($user, $ticketId);
        if ($url !== '') {
            $actions[] = ['type' => 'Action.OpenUrl', 'title' => 'Open in helpdesk', 'url' => $url];
        }
        $card = Teams::wrap($title . ' — #' . $ticketId, $body);
        $card['attachments'][0]['content']['actions'] = $actions;
        return $card;
    }

    private static function ticketUrlFor(array $user, int $ticketId): string
    {
        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($base === '') {
            return '';
        }
        return $base . (roleIsStaff($user['role'] ?? null) ? '/agent/tickets/' : '/portal/tickets/') . $ticketId;
    }

    private static function text(string $markdown): array
    {
        return ['type' => 'message', 'textFormat' => 'markdown', 'text' => $markdown];
    }

    /** Plain text safe to drop into card markdown. */
    private static function md(string $s): string
    {
        return str_replace(['*', '_', '`', '[', ']'], ['\*', '\_', '\`', '\[', '\]'], $s);
    }

    private static function excerpt(string $html, int $max = 400): string
    {
        $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (mb_strlen($t) > $max) {
            $t = rtrim(mb_substr($t, 0, $max)) . '…';
        }
        return self::md($t);
    }

    /* ── outbound ──────────────────────────────────────────────────────── */

    /** Reply in the conversation the activity came from. Fail-soft. */
    private static function send(array $activity, array $message): void
    {
        $serviceUrl = rtrim((string) ($activity['serviceUrl'] ?? ''), '/');
        $convId     = (string) ($activity['conversation']['id'] ?? '');
        if ($serviceUrl === '' || $convId === '' || stripos($serviceUrl, 'https://') !== 0) {
            return;
        }
        $token = self::botToken();
        if ($token === null) {
            error_log('Teams bot: no bot token — check the app id / secret under Settings → Microsoft Teams');
            return;
        }
        $message += ['type' => 'message'];
        $message['from']         = $activity['recipient'] ?? null;
        $message['recipient']    = $activity['from'] ?? null;
        $message['conversation'] = $activity['conversation'];
        $url = $serviceUrl . '/v3/conversations/' . rawurlencode($convId) . '/activities';
        if (!empty($activity['id'])) {
            $message['replyToId'] = (string) $activity['id'];
            $url .= '/' . rawurlencode((string) $activity['id']);
        }
        [$code, $body] = self::http('POST', $url, json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
        if ($code < 200 || $code >= 300) {
            error_log("Teams bot: reply failed HTTP {$code}: " . substr($body, 0, 300));
        }
    }

    /**
     * App token for the Bot Connector (client credentials). Cached in settings
     * until a minute before expiry. $error receives the reason on failure.
     */
    public static function botToken(bool $fresh = false, ?string &$error = null): ?string
    {
        $appId = self::appId();
        $cache = json_decode(getSetting('teams_bot_token_cache', ''), true);
        if (!$fresh && is_array($cache) && ($cache['app'] ?? '') === $appId && (int) ($cache['expires'] ?? 0) > time() + 60) {
            return (string) $cache['token'];
        }
        if ($appId === '' || self::appSecret() === '') {
            $error = 'App ID or client secret is missing.';
            return null;
        }
        $tenant = self::tenantId() ?: 'botframework.com';
        [$code, $body] = self::http('POST', "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $appId,
                'client_secret' => self::appSecret(),
                'scope'         => self::TOKEN_SCOPE,
            ]), ['Content-Type: application/x-www-form-urlencoded']);
        $json = json_decode($body, true);
        if ($code !== 200 || !is_array($json) || empty($json['access_token'])) {
            $error = is_array($json) && !empty($json['error_description'])
                ? (string) $json['error_description']
                : "Token request failed (HTTP {$code}).";
            return null;
        }
        setSetting('teams_bot_token_cache', json_encode([
            'app'     => $appId,
            'token'   => $json['access_token'],
            'expires' => time() + (int) ($json['expires_in'] ?? 3600),
        ]));
        return (string) $json['access_token'];
    }

    /** @return array{0:int,1:string} [status, body]; status 0 on transport failure */
    private static function http(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            error_log("Teams bot: {$method} {$url} failed: {$err}");
            return [0, ''];
        }
        return [$code, (string) $resp];
    }

    /* ── inbound token verification ────────────────────────────────────── */

    /**
     * Verify a Bot Framework bearer token for this app. The JWKS lookup is
     * injectable so the check can be tested with a locally generated key.
     *
     * @param callable|null $keyLookup fn(string $kid): ?string PEM public key
     */
    public static function verifyToken(string $jwt, string $serviceUrl, ?callable $keyLookup = null, ?int $now = null): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        [$h64, $p64, $s64] = $parts;
        $header = json_decode((string) self::b64url($h64), true);
        $claims = json_decode((string) self::b64url($p64), true);
        $sig    = self::b64url($s64);
        if (!is_array($header) || !is_array($claims) || $sig === false || $sig === '') {
            return false;
        }
        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            return false;
        }
        $now ??= time();
        if (($claims['iss'] ?? '') !== self::ISSUER || ($claims['aud'] ?? '') !== self::appId()) {
            return false;
        }
        if (!isset($claims['exp']) || (int) $claims['exp'] < $now - 300) {
            return false;
        }
        if (isset($claims['nbf']) && (int) $claims['nbf'] > $now + 300) {
            return false;
        }
        // The reply goes to the activity's serviceUrl with our bot token in it, so
        // the URL must be the one Microsoft signed into the token.
        if ($serviceUrl !== '' && rtrim((string) ($claims['serviceurl'] ?? ''), '/') !== rtrim($serviceUrl, '/')) {
            return false;
        }
        $pem = $keyLookup !== null ? $keyLookup((string) $header['kid']) : self::signingKeyPem((string) $header['kid']);
        if ($pem === null) {
            return false;
        }
        $key = openssl_pkey_get_public($pem);
        return $key !== false && openssl_verify($h64 . '.' . $p64, $sig, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * PEM public key for a JWKS key id. Microsoft publishes a few hundred keys
     * (with certificate chains), far more than the settings column holds, so
     * the cache keeps only the modulus/exponent of the key ids we have actually
     * seen: {fetched: ts, keys: {kid: [n, e]}}.
     */
    private static function signingKeyPem(string $kid): ?string
    {
        $cache = json_decode(getSetting('teams_bot_jwks_cache', ''), true);
        $keys  = is_array($cache) && is_array($cache['keys'] ?? null) ? $cache['keys'] : [];
        // Unknown kid: refetch, but not more than once every 5 minutes so a flood
        // of bad tokens can't turn into a flood of requests to Microsoft.
        if (!isset($keys[$kid]) && (int) ($cache['fetched'] ?? 0) < time() - 300) {
            $fresh = self::fetchJwks();
            if (isset($fresh[$kid])) {
                $keys[$kid] = $fresh[$kid];
                $keys = array_slice($keys, -20, null, true);
            }
            setSetting('teams_bot_jwks_cache', json_encode(['fetched' => time(), 'keys' => $keys]));
        }
        if (!isset($keys[$kid][0], $keys[$kid][1])) {
            return null;
        }
        return self::rsaPemFromJwk((string) $keys[$kid][0], (string) $keys[$kid][1]);
    }

    /** Microsoft's current Bot Framework signing keys as kid => [n, e]. */
    private static function fetchJwks(): array
    {
        [$c1, $conf] = self::http('GET', self::OPENID_CONF, null, []);
        $jwksUri = $c1 === 200 ? (string) (json_decode($conf, true)['jwks_uri'] ?? '') : '';
        if ($jwksUri === '' || !str_starts_with($jwksUri, 'https://login.botframework.com/')) {
            return [];
        }
        [$c2, $set] = self::http('GET', $jwksUri, null, []);
        $out = [];
        foreach ($c2 === 200 ? (json_decode($set, true)['keys'] ?? []) : [] as $k) {
            if (!empty($k['kid']) && !empty($k['n']) && !empty($k['e'])) {
                $out[(string) $k['kid']] = [(string) $k['n'], (string) $k['e']];
            }
        }
        return $out;
    }

    /** SubjectPublicKeyInfo PEM from a JWK's modulus / exponent (no library needed). */
    public static function rsaPemFromJwk(string $n64, string $e64): ?string
    {
        $n = self::b64url($n64);
        $e = self::b64url($e64);
        if ($n === false || $e === false || $n === '' || $e === '') {
            return null;
        }
        $int = static function (string $bin): string {
            $bin = ltrim($bin, "\x00");
            if ($bin === '' || (ord($bin[0]) & 0x80)) {
                $bin = "\x00" . $bin; // positive INTEGER
            }
            return self::der(0x02, $bin);
        };
        $rsaKey = self::der(0x30, $int($n) . $int($e));
        $algId  = self::der(0x30, self::der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00"); // rsaEncryption, NULL
        $spki   = self::der(0x30, $algId . self::der(0x03, "\x00" . $rsaKey));
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function der(int $tag, string $value): string
    {
        $len = strlen($value);
        if ($len < 0x80) {
            $lenBytes = chr($len);
        } else {
            $b        = ltrim(pack('N', $len), "\x00");
            $lenBytes = chr(0x80 | strlen($b)) . $b;
        }
        return chr($tag) . $lenBytes . $value;
    }

    private static function b64url(string $s): string|false
    {
        $s = strtr($s, '-_', '+/');
        return base64_decode(str_pad($s, strlen($s) + (4 - strlen($s) % 4) % 4, '='), true);
    }

    /* ── Teams app package ─────────────────────────────────────────────── */

    /**
     * The Teams app manifest (v1.16) for this bot. Admins upload the zip from
     * the settings page to Teams instead of hand-writing it in Developer Portal.
     */
    public static function manifest(): array
    {
        $app  = getSetting('branding_app_name', 'OpenHelpDesk');
        $base = rtrim((string) env('APP_URL', ''), '/');
        $host = (string) parse_url($base, PHP_URL_HOST);
        $cmds = [
            ['title' => 'help',       'description' => 'What the bot can do'],
            ['title' => 'new',        'description' => 'Open a ticket: new <subject> | <details>'],
            ['title' => 'my tickets', 'description' => 'List your open tickets'],
            ['title' => 'status',     'description' => 'Show a ticket: status <number>'],
            ['title' => 'comment',    'description' => 'Reply on a ticket: comment <number> <text>'],
            ['title' => 'close',      'description' => 'Close a ticket: close <number> [note]'],
        ];
        return [
            '$schema'         => 'https://developer.microsoft.com/en-us/json-schemas/teams/v1.16/MicrosoftTeams.schema.json',
            'manifestVersion' => '1.16',
            'version'         => '1.0.' . preg_replace('/\D/', '', APP_VERSION),
            'id'              => self::appId(),
            'developer'       => [
                'name'          => $app,
                'websiteUrl'    => $base,
                'privacyUrl'    => $base,
                'termsOfUseUrl' => $base,
            ],
            'name'        => ['short' => mb_substr($app . ' bot', 0, 30), 'full' => $app . ' helpdesk bot'],
            'description' => [
                'short' => mb_substr('Open and follow up on ' . $app . ' tickets from Teams', 0, 80),
                'full'  => 'Open tickets, check their status, add replies and close them without leaving Microsoft Teams. '
                         . 'Type "help" to the bot to see the commands.',
            ],
            'icons'       => ['color' => 'color.png', 'outline' => 'outline.png'],
            'accentColor' => '#FFFFFF',
            'bots'        => [[
                'botId'              => self::appId(),
                'scopes'             => ['personal', 'team', 'groupChat'],
                'supportsFiles'      => false,
                'isNotificationOnly' => false,
                'commandLists'       => [['scopes' => ['personal', 'team', 'groupChat'], 'commands' => $cmds]],
            ]],
            'permissions'  => ['identity', 'messageTeamMembers'],
            'validDomains' => $host !== '' ? [$host] : [],
        ];
    }

    /** Build the app package zip (manifest + icons). Returns the zip bytes or null. */
    public static function manifestZip(): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'teamsapp');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $zip->addFromString('manifest.json', json_encode(self::manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('color.png', (string) file_get_contents(PWA::iconPath(192)));
        $zip->addFromString('outline.png', self::outlineIcon());
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);
        return $bytes === false ? null : $bytes;
    }

    /** 32×32 transparent PNG with a white glyph, as Teams requires for the outline icon. */
    private static function outlineIcon(): string
    {
        $img = imagecreatetruecolor(32, 32);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledellipse($img, 16, 16, 28, 28, $white);
        imagefilledellipse($img, 16, 16, 18, 18, imagecolorallocatealpha($img, 0, 0, 0, 127));
        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }
}
