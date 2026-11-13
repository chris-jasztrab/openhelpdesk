<?php

declare(strict_types=1);

/**
 * Inventory expiry digest: one email, on a weekly or monthly schedule, listing
 * warranties about to end, warranties that ended since the last digest,
 * end-of-life dates coming up and how many audits are overdue.
 *
 * Scheduled on Inventory › Reports › Email digest; sent by
 * scripts/process-asset-digest.php, which cron runs daily and which does
 * nothing unless the digest is switched on and a scheduled day has arrived.
 * Takes an explicit PDO and needs only helpers.php, so the cron script can use
 * it without bootstrap.php.
 */
final class AssetDigest
{
    /** Rows listed per section before "…and N more". */
    private const LIST_MAX = 40;

    /** The saved schedule, with defaults (off, monthly on the 1st, 90-day window, all administrators). */
    public static function settings(): array
    {
        return [
            'enabled'    => getSetting('asset_digest_enabled', '0') === '1',
            'frequency'  => getSetting('asset_digest_frequency', 'monthly') === 'weekly' ? 'weekly' : 'monthly',
            'day'        => max(1, (int) getSetting('asset_digest_day', '1')),   // weekly: 1 = Monday … 7; monthly: 1–28
            'days'       => max(1, min(730, (int) getSetting('asset_digest_days', '90'))),
            'admins'     => getSetting('asset_digest_admins', '1') === '1',
            'recipients' => getSetting('asset_digest_recipients', ''),
            'last_sent'  => getSetting('asset_digest_last_sent', ''),
        ];
    }

    /** The most recent scheduled date on or before $today (YYYY-MM-DD). */
    public static function lastSlot(array $s, string $today): string
    {
        $t = new DateTimeImmutable($today);
        if ($s['frequency'] === 'weekly') {
            $back = ((int) $t->format('N') - min(7, $s['day']) + 7) % 7;
            return $t->modify("-{$back} days")->format('Y-m-d');
        }
        $slot = $t->setDate((int) $t->format('Y'), (int) $t->format('n'), min(28, $s['day']));
        return ($slot > $t ? $slot->modify('-1 month') : $slot)->format('Y-m-d');
    }

    /** The first scheduled date after $today. */
    public static function nextSlot(array $s, string $today): string
    {
        $d = new DateTimeImmutable($today);
        do {
            $d = $d->modify('+1 day');
        } while (self::lastSlot($s, $d->format('Y-m-d')) !== $d->format('Y-m-d'));
        return $d->format('Y-m-d');
    }

    /**
     * Due when a scheduled date has passed since the last send. Comparing with
     * the last slot (not "is today the day") means a day the server was down
     * is caught up on the next run instead of skipped for a week or a month.
     */
    public static function isDue(array $s, string $today): bool
    {
        return $s['enabled'] && $s['last_sent'] < self::lastSlot($s, $today);
    }

    /** What the digest would list today: warranties ending within $days, ended since $since, EOL dates within $days, audits overdue. */
    public static function data(PDO $db, int $days, string $since, string $today): array
    {
        $until = date('Y-m-d', strtotime("{$today} +{$days} days"));
        $rows  = static function (string $where, array $params) use ($db): array {
            $s = $db->prepare(
                "SELECT a.id, a.asset_tag, a.name, a.warranty_end, a.eol_date, m.name AS model_name,
                        COALESCE(CONCAT(u.first_name, ' ', u.last_name), l.name) AS place
                 FROM assets a JOIN asset_models m ON m.id = a.model_id
                 LEFT JOIN locations l ON l.id = a.location_id
                 LEFT JOIN users u ON a.assigned_type = 'user' AND u.id = a.assigned_id
                 WHERE a.deleted_at IS NULL AND {$where}, a.asset_tag"
            );
            $s->execute($params);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        };
        $overdue = $db->prepare('SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL AND next_audit_date < ?');
        $overdue->execute([$today]);
        return [
            'ending'  => $rows('a.warranty_end BETWEEN ? AND ? ORDER BY a.warranty_end', [$today, $until]),
            'expired' => $rows('a.warranty_end >= ? AND a.warranty_end < ? ORDER BY a.warranty_end DESC', [$since, $today]),
            'eol'     => $rows('a.eol_date BETWEEN ? AND ? ORDER BY a.eol_date', [$today, $until]),
            'audit_overdue' => (int) $overdue->fetchColumn(),
            'days' => $days, 'since' => $since, 'until' => $until,
        ];
    }

    /** How many things the digest has to say. 0 = nothing worth an email. */
    public static function itemCount(array $d): int
    {
        return count($d['ending']) + count($d['expired']) + count($d['eol']) + $d['audit_overdue'];
    }

    /** [email, name] pairs: every administrator (when ticked) plus the extra addresses, each once. */
    public static function recipients(PDO $db, array $s): array
    {
        $out = [];
        if ($s['admins']) {
            foreach ($db->query("SELECT u.email, CONCAT(u.first_name, ' ', u.last_name) AS name FROM users u JOIN roles r ON r.slug = u.role
                                 WHERE r.is_admin = 1 AND u.email != '' ORDER BY u.first_name") as $u) {
                $out[strtolower((string) $u['email'])] = [(string) $u['email'], trim((string) $u['name'])];
            }
        }
        foreach (preg_split('/[\s,;]+/', $s['recipients'], -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($email)] ??= [$email, ''];
            }
        }
        return array_values($out);
    }

    /** [subject, html, text] for a data() result. */
    public static function message(array $d): array
    {
        $app  = getSetting('branding_app_name', 'OpenHelpDesk');
        $base = rtrim(getSetting('app_url', ''), '/');
        $day  = static fn(?string $x): string => $x ? date('M j, Y', strtotime($x)) : '';
        $h    = static fn(?string $x): string => htmlspecialchars((string) $x, ENT_QUOTES);
        $td   = 'padding:7px 10px;border-bottom:1px solid #e5e7eb;';

        $sections = [
            ['Warranties ending in the next ' . $d['days'] . ' days', $d['ending'], 'warranty_end', 'Ends',
                '/inventory?' . http_build_query(['warranty' => 'soon', 'warranty_days' => $d['days'], 'sort' => 'warranty_end'])],
            ['Warranties that ended since ' . $day($d['since']), $d['expired'], 'warranty_end', 'Ended',
                '/inventory?warranty=expired&sort=warranty_end&dir=desc'],
            ['End-of-life dates in the next ' . $d['days'] . ' days', $d['eol'], 'eol_date', 'End of life', '/inventory'],
        ];
        $html = '';
        $text = '';
        foreach ($sections as [$title, $rows, $col, $colTitle, $path]) {
            if ($rows === []) {
                continue;
            }
            $html .= '<h3 style="margin:24px 0 8px;font-size:16px;color:#111827;">' . $h($title) . ' (' . count($rows) . ')</h3>'
                   . '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;">'
                   . '<tr style="text-align:left;color:#6b7280;"><th style="' . $td . '">Tag</th><th style="' . $td . '">Model</th><th style="' . $td . '">With / at</th><th style="' . $td . '">' . $colTitle . '</th></tr>';
            $text .= "\n{$title} (" . count($rows) . ")\n";
            foreach (array_slice($rows, 0, self::LIST_MAX) as $a) {
                $html .= '<tr><td style="' . $td . '"><a href="' . $h($base . '/inventory/' . (int) $a['id']) . '" style="color:#4f46e5;text-decoration:none;font-family:monospace;">' . $h($a['asset_tag']) . '</a></td>'
                       . '<td style="' . $td . '">' . $h($a['model_name']) . ($a['name'] ? ' <span style="color:#6b7280;">' . $h($a['name']) . '</span>' : '') . '</td>'
                       . '<td style="' . $td . '">' . $h($a['place']) . '</td>'
                       . '<td style="' . $td . 'white-space:nowrap;">' . $day($a[$col]) . '</td></tr>';
                $text .= "- {$a['asset_tag']}  {$a['model_name']}  " . $day($a[$col]) . ($a['place'] ? "  ({$a['place']})" : '') . "\n";
            }
            $html .= '</table>';
            if (count($rows) > self::LIST_MAX) {
                $more  = count($rows) - self::LIST_MAX;
                $html .= '<p style="margin:8px 0 0;font-size:13px;">…and ' . $more . ' more. <a href="' . $h($base . $path) . '" style="color:#4f46e5;">See them all</a></p>';
                $text .= "…and {$more} more: {$base}{$path}\n";
            }
        }
        if ($d['audit_overdue'] > 0) {
            $n     = $d['audit_overdue'];
            $html .= '<h3 style="margin:24px 0 8px;font-size:16px;color:#111827;">Audits overdue</h3>'
                   . '<p style="margin:0;line-height:1.6;">' . $n . ' asset' . ($n === 1 ? ' is' : 's are') . ' past the next audit date. <a href="' . $h($base . '/inventory?audit=overdue') . '" style="color:#4f46e5;">See the list</a></p>';
            $text .= "\nAudits overdue: {$n}  {$base}/inventory?audit=overdue\n";
        }
        if ($html === '') {
            $html = '<p style="margin:16px 0 0;line-height:1.6;">Nothing to report: no warranty or end-of-life date falls in the next ' . $d['days'] . ' days and no audit is overdue.</p>';
            $text = "\nNothing to report.\n";
        }

        // Subject names only what there is: "3 warranties ending soon, 12 audits overdue".
        $parts = [];
        foreach ([[count($d['ending']), 'warranties ending soon'], [count($d['expired']), 'warranties ended'],
                  [count($d['eol']), 'reaching end of life'], [$d['audit_overdue'], 'audits overdue']] as [$n, $what]) {
            if ($n > 0) {
                $parts[] = "{$n} {$what}";
            }
        }
        $subject = "[{$app}] Inventory digest: " . ($parts === [] ? 'nothing to report' : implode(', ', $parts));
        $body = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head>'
              . '<body style="margin:0;padding:32px 16px;background:#f8fafc;font-family:sans-serif;color:#374151;">'
              . '<table width="680" cellpadding="0" cellspacing="0" align="center" style="background:#fff;border-radius:8px;max-width:680px;">'
              . '<tr><td style="padding:28px 32px;"><h2 style="margin:0;font-size:20px;color:#111827;">Inventory digest</h2>' . $html
              . '<p style="margin:28px 0 0;"><a href="' . $h($base . '/inventory/reports?tab=warranty') . '" style="display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:600;font-size:14px;">Open inventory reports</a></p></td></tr>'
              . '<tr><td style="padding:14px 32px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">Scheduled digest from ' . $h($app) . '. Change or stop it under Inventory › Reports › Email digest.</td></tr>'
              . '</table></body></html>';
        return [$subject, $body, "Inventory digest\n" . $text . "\n{$base}/inventory/reports\n"];
    }

    /**
     * Build and send the digest. $only = [[email, name]] sends a test to just
     * those people and leaves the schedule alone; otherwise it goes to the
     * saved recipients and the send date is stamped — also when there was
     * nothing to report (no email then), so the schedule moves on.
     * Returns ['sent' => n, 'items' => n].
     */
    public static function send(PDO $db, array $s, string $today, ?array $only = null, ?callable $log = null): array
    {
        $log ??= static function (string $m): void {};
        $since = $s['last_sent'] !== '' ? $s['last_sent'] : date('Y-m-d', strtotime("{$today} -30 days"));
        $d     = self::data($db, $s['days'], $since, $today);
        $items = self::itemCount($d);
        $sent  = 0;
        if ($items === 0 && $only === null) {
            $log('Nothing to report — no email sent.');
        } else {
            [$subject, $html, $text] = self::message($d);
            foreach ($only ?? self::recipients($db, $s) as [$email, $name]) {
                if (sendMail($email, $name, $subject, $html, $text) !== false) {
                    $log("  emailed {$email}");
                    $sent++;
                } else {
                    $log("  FAILED to email {$email}");
                }
            }
        }
        // A mail outage (recipients but nobody reached) leaves the date alone, so tomorrow's run retries.
        if ($only === null && ($items === 0 || $sent > 0)) {
            setSetting('asset_digest_last_sent', $today);
        }
        return ['sent' => $sent, 'items' => $items];
    }
}
