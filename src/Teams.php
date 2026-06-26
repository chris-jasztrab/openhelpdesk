<?php

declare(strict_types=1);

/**
 * Microsoft Teams integration.
 *
 * Posts ticket-event notifications to a Teams channel via an Incoming Webhook
 * URL (created with the "Workflows" app — Power Automate's "Post to a channel
 * when a webhook request is received" template, which is the supported
 * successor to the retired Office 365 connector). The payload is an Adaptive
 * Card wrapped in a `message` attachment, the shape that endpoint expects.
 *
 * Configuration lives in the settings table (admin → Settings → Teams):
 *   teams_enabled            '1' / '0'   master switch
 *   teams_webhook_url        the channel webhook URL
 *   teams_event_created      per-event toggles ('1' default)
 *   teams_event_assigned
 *   teams_event_status
 *   teams_event_sla
 *
 * Every public entry point is fail-soft: a misconfigured or slow webhook can
 * never break ticket creation / updates. Errors are swallowed (logged to the
 * PHP error log) so the calling request always completes.
 */
class Teams
{
    /** Event key → [settings suffix, card title]. */
    private const EVENTS = [
        'created'  => ['teams_event_created',  '🆕 New ticket'],
        'assigned' => ['teams_event_assigned', '👤 Ticket assigned'],
        'status'   => ['teams_event_status',   '🔄 Status changed'],
        'sla'      => ['teams_event_sla',      '⏰ SLA breached'],
    ];

    /**
     * Master switch. A specific channel still has to resolve to a non-empty
     * webhook at send time (per-type override or the default), so enabling with
     * no URLs configured simply posts nothing rather than erroring.
     */
    public static function enabled(): bool
    {
        return getSetting('teams_enabled', '0') === '1';
    }

    /**
     * Resolve the channel webhook for a ticket type: the type's own override if
     * set, otherwise the default channel. Returns '' when neither is configured
     * (that type's events are then silently skipped).
     */
    public static function webhookForType(?int $typeId): string
    {
        if ($typeId !== null && $typeId > 0) {
            $override = trim(getSetting('teams_webhook_type:' . $typeId, ''));
            if ($override !== '') {
                return $override;
            }
        }
        return trim(getSetting('teams_webhook_url', ''));
    }

    /** Whether a specific event is enabled (defaults on). */
    public static function eventEnabled(string $event): bool
    {
        if (!isset(self::EVENTS[$event])) {
            return false;
        }
        return getSetting(self::EVENTS[$event][0], '1') === '1';
    }

    /**
     * Build and post a ticket-event card. Safe to call unconditionally from
     * notification dispatch points — it returns immediately when the integration
     * or the specific event is disabled, and never throws.
     *
     * @param array $extra optional context, e.g. ['from_status' => 'open'] for a
     *                     status change, or ['assignee_id' => 5] for an assignment.
     */
    public static function notifyTicketEvent(PDO $db, int $ticketId, string $event, array $extra = []): void
    {
        try {
            if (!self::enabled() || !self::eventEnabled($event) || !isset(self::EVENTS[$event])) {
                return;
            }
            $built = self::buildTicketCard($db, $ticketId, $event, $extra);
            if ($built === null) {
                return;
            }
            [$card, $typeId] = $built;
            $url = self::webhookForType($typeId);
            if ($url === '') {
                return; // no channel for this ticket's type and no default — nothing to do
            }
            self::post($card, $url);
        } catch (\Throwable $e) {
            error_log('Teams notify failed: ' . $e->getMessage());
        }
    }

    /**
     * POST an arbitrary message payload to the configured webhook.
     * Returns ['ok' => bool, 'error' => string]. Used by notifyTicketEvent and
     * by the admin "Send test message" button.
     */
    public static function post(array $payload, ?string $overrideUrl = null): array
    {
        $url = $overrideUrl !== null ? trim($overrideUrl) : trim(getSetting('teams_webhook_url', ''));
        if ($url === '') {
            return ['ok' => false, 'error' => 'No webhook URL configured.'];
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || stripos($url, 'https://') !== 0) {
            return ['ok' => false, 'error' => 'Webhook URL must be a valid https:// URL.'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'error' => $err !== '' ? $err : 'Request failed.'];
        }
        // Workflows returns 202 Accepted; the legacy connector returned 200.
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'error' => ''];
        }
        return ['ok' => false, 'error' => 'HTTP ' . $code . ': ' . substr((string) $resp, 0, 200)];
    }

    /** A minimal card for the admin test button. */
    public static function testCard(): array
    {
        $app = getSetting('branding_app_name', 'OpenHelpDesk');
        return self::wrap('✅ Test message', [
            ['type' => 'TextBlock', 'wrap' => true,
             'text' => "Teams notifications from **{$app}** are working. You'll receive ticket updates in this channel."],
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────── */

    /**
     * Build the Adaptive Card for a ticket event.
     * Returns [cardPayload, typeId|null], or null if the ticket is gone.
     * The type id is returned so the caller can route to that type's channel.
     */
    private static function buildTicketCard(PDO $db, int $ticketId, string $event, array $extra): ?array
    {
        $stmt = $db->prepare(
            "SELECT t.id, t.subject, t.status, t.created_at, t.type_id,
                    COALESCE(ts.label, t.status)          AS status_label,
                    tp.name                                AS priority,
                    tt.name                                AS type,
                    l.name                                 AS location,
                    g.name                                 AS group_name,
                    CONCAT(req.first_name, ' ', req.last_name) AS requester,
                    CASE WHEN asg.id IS NULL THEN NULL
                         ELSE CONCAT(asg.first_name, ' ', asg.last_name) END AS assignee
               FROM tickets t
               LEFT JOIN ticket_statuses   ts  ON ts.slug = t.status
               LEFT JOIN ticket_priorities tp  ON tp.id = t.priority_id
               LEFT JOIN ticket_types      tt  ON tt.id = t.type_id
               LEFT JOIN locations         l   ON l.id = t.location_id
               LEFT JOIN `groups`          g   ON g.id = t.group_id
               LEFT JOIN users             req ON req.id = t.created_by
               LEFT JOIN users             asg ON asg.id = t.assigned_to
              WHERE t.id = ?"
        );
        $stmt->execute([$ticketId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }

        $facts = [];
        $fact = static function (string $k, $v) use (&$facts): void {
            $v = trim((string) $v);
            if ($v !== '') {
                $facts[] = ['title' => $k, 'value' => $v];
            }
        };

        if ($event === 'status') {
            $from = isset($extra['from_status'])
                ? self::statusLabel($db, (string) $extra['from_status'])
                : '';
            $fact('Status', $from !== '' ? "{$from} → {$r['status_label']}" : (string) $r['status_label']);
        } else {
            $fact('Status', (string) $r['status_label']);
        }
        $fact('Priority',  $r['priority']);
        $fact('Type',      $r['type']);
        $fact('Group',     $r['group_name']);
        $fact('Location',  $r['location']);
        $fact('Requester', $r['requester']);
        $fact('Assignee',  $r['assignee'] ?? 'Unassigned');

        $title = self::EVENTS[$event][1];
        $body = [
            ['type' => 'TextBlock', 'size' => 'Medium', 'weight' => 'Bolder', 'text' => $title, 'wrap' => true],
            ['type' => 'TextBlock', 'spacing' => 'None', 'wrap' => true,
             'text' => '#' . $r['id'] . ' — ' . $r['subject']],
            ['type' => 'FactSet', 'facts' => $facts],
        ];

        $card   = self::wrap($title, $body, self::ticketUrl($ticketId));
        $typeId = $r['type_id'] !== null ? (int) $r['type_id'] : null;
        return [$card, $typeId];
    }

    private static function statusLabel(PDO $db, string $slug): string
    {
        $st = $db->prepare('SELECT label FROM ticket_statuses WHERE slug = ?');
        $st->execute([$slug]);
        $label = $st->fetchColumn();
        return $label !== false ? (string) $label : ucwords(str_replace('_', ' ', $slug));
    }

    private static function ticketUrl(int $ticketId): string
    {
        $base = rtrim((string) env('APP_URL', ''), '/');
        return $base !== '' ? $base . '/agent/tickets/' . $ticketId : '';
    }

    /**
     * Wrap card body elements in the message/attachment envelope the Teams
     * webhook expects. Adds an "Open ticket" action when a URL is supplied.
     */
    private static function wrap(string $summary, array $body, string $openUrl = ''): array
    {
        $content = [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type'    => 'AdaptiveCard',
            'version' => '1.4',
            'body'    => $body,
        ];
        if ($openUrl !== '') {
            $content['actions'] = [
                ['type' => 'Action.OpenUrl', 'title' => 'Open ticket', 'url' => $openUrl],
            ];
        }
        return [
            'type'        => 'message',
            'summary'     => $summary,
            'attachments' => [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'content'     => $content,
            ]],
        ];
    }
}
