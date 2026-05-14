<?php

declare(strict_types=1);

/**
 * OpenHelpDesk — Recurring / preventive-maintenance ticket schedules.
 *
 * Two responsibilities:
 *   1. Cadence math: given a schedule row, work out the next firing
 *      datetime — anchored to the configured day-of-week / day-of-month
 *      / month-of-year so the schedule doesn't drift off the calendar
 *      every time it fires (firing on 2026-01-15 → next 2026-04-15 for
 *      a quarterly schedule, regardless of *when* the cron actually
 *      processed it).
 *   2. Minting: build a real ticket from the schedule's payload, run it
 *      through the same post-create hooks (auto-assign, AI classify,
 *      automations, default-group fallback) as a hand-filed ticket so
 *      routing behaves the same way.
 *
 * Both surfaces are static — schedules are flat rows, no lifetime to
 * manage. Pulled out of `helpers.php` because it owns enough of the
 * domain to deserve its own file (same shape as `Sla.php`).
 */
final class RecurringTickets
{
    /**
     * Default time-of-day for firing. Schedules don't carry their own
     * clock — a "monthly" schedule fires *on* the 15th, not at 09:42 on
     * the 15th. We pick 06:00 local so the ticket is in the agent's
     * inbox before they sit down, but late enough that overnight batch
     * jobs (escalations, stale-ticket sweeps) won't trip over it.
     */
    private const DEFAULT_HOUR = 6;
    private const DEFAULT_MIN  = 0;

    /**
     * Compute the first or next firing datetime for a schedule.
     *
     * @param array                 $row           Recurring schedule row.
     * @param DateTimeImmutable     $reference     "After this point" — typically
     *                                             now() for first-run, or
     *                                             last_run_at for advancing.
     * @param bool                  $strictlyAfter When true, the returned
     *                                             datetime is > $reference.
     *                                             When false, == is allowed
     *                                             (used for the very first
     *                                             run from start_date).
     */
    public static function computeNextRun(
        array $row,
        DateTimeImmutable $reference,
        bool $strictlyAfter = true
    ): DateTimeImmutable {
        $frequency = (string) ($row['frequency'] ?? 'monthly');
        $interval  = max(1, (int) ($row['interval_value'] ?? 1));
        $startDate = (string) ($row['start_date'] ?? $reference->format('Y-m-d'));

        $candidate = (new DateTimeImmutable($startDate))
            ->setTime(self::DEFAULT_HOUR, self::DEFAULT_MIN);

        // Anchor to the configured day-of-week / day-of-month / month-of-year
        // before we start advancing. Without this, a "monthly on the 15th"
        // schedule whose start_date is the 1st would fire on the 1st first,
        // and only land on the 15th from the second cycle on.
        $candidate = self::anchorToCadence($candidate, $row);

        // Safety bound — even a daily schedule started ten years ago should
        // converge in a few thousand iterations, but bound anyway.
        $maxLoops = 5000;
        $loops    = 0;
        while ($loops++ < $maxLoops) {
            $past = $strictlyAfter
                ? ($candidate <= $reference)
                : ($candidate <  $reference);
            if (!$past) {
                return $candidate;
            }
            $candidate = self::advance($candidate, $frequency, $interval, $row);
        }

        // Should never happen — fall back to "+1 day from reference" so a
        // pathological row doesn't NULL out the column.
        return $reference->modify('+1 day')
            ->setTime(self::DEFAULT_HOUR, self::DEFAULT_MIN);
    }

    /**
     * Move $candidate to the next legal firing slot per the schedule's
     * day-of-week / day-of-month / month-of-year anchors. No-op for
     * cadences that don't define an anchor (daily, custom).
     */
    private static function anchorToCadence(DateTimeImmutable $candidate, array $row): DateTimeImmutable
    {
        $frequency = (string) ($row['frequency'] ?? 'monthly');

        if ($frequency === 'weekly' && isset($row['day_of_week']) && $row['day_of_week'] !== null && $row['day_of_week'] !== '') {
            $targetDow = (int) $row['day_of_week']; // 0=Sun … 6=Sat
            $currentDow = (int) $candidate->format('w');
            $delta = ($targetDow - $currentDow + 7) % 7;
            if ($delta > 0) {
                $candidate = $candidate->modify("+{$delta} days");
            }
        }

        if (in_array($frequency, ['monthly', 'yearly'], true)
            && isset($row['day_of_month']) && $row['day_of_month'] !== null && $row['day_of_month'] !== '') {
            $candidate = self::setDayOfMonth($candidate, (int) $row['day_of_month']);
        }

        if ($frequency === 'yearly'
            && isset($row['month_of_year']) && $row['month_of_year'] !== null && $row['month_of_year'] !== '') {
            $targetMonth = max(1, min(12, (int) $row['month_of_year']));
            $currentMonth = (int) $candidate->format('n');
            if ($currentMonth !== $targetMonth) {
                $candidate = $candidate->setDate(
                    (int) $candidate->format('Y'),
                    $targetMonth,
                    (int) $candidate->format('j')
                );
                if (isset($row['day_of_month']) && $row['day_of_month'] !== null && $row['day_of_month'] !== '') {
                    $candidate = self::setDayOfMonth($candidate, (int) $row['day_of_month']);
                }
            }
        }

        return $candidate;
    }

    /**
     * Step forward by one cadence cycle. Re-anchors after advancing so
     * monthly-on-the-15th lands on the 15th in the new month even when
     * the previous month had fewer days.
     */
    private static function advance(
        DateTimeImmutable $candidate,
        string $frequency,
        int $interval,
        array $row
    ): DateTimeImmutable {
        switch ($frequency) {
            case 'daily':
                return $candidate->modify("+{$interval} days");

            case 'weekly':
                $advanced = $candidate->modify('+' . ($interval * 7) . ' days');
                return self::anchorToCadence($advanced, $row);

            case 'monthly':
                $year  = (int) $candidate->format('Y');
                $month = (int) $candidate->format('n');
                $month += $interval;
                while ($month > 12) { $month -= 12; $year++; }
                $advanced = $candidate->setDate($year, $month, 1)
                    ->setTime(self::DEFAULT_HOUR, self::DEFAULT_MIN);
                return self::anchorToCadence($advanced, $row);

            case 'yearly':
                $year = (int) $candidate->format('Y') + $interval;
                $advanced = $candidate->setDate(
                    $year,
                    (int) $candidate->format('n'),
                    1
                )->setTime(self::DEFAULT_HOUR, self::DEFAULT_MIN);
                return self::anchorToCadence($advanced, $row);

            case 'custom':
                // "Every N days" — interval_value is days outright.
                return $candidate->modify("+{$interval} days");
        }

        return $candidate->modify('+1 day');
    }

    /**
     * Clamp day-of-month to the target month's actual length, e.g.
     * day_of_month=31 in February becomes the 28th/29th.
     */
    private static function setDayOfMonth(DateTimeImmutable $dt, int $dom): DateTimeImmutable
    {
        $year  = (int) $dt->format('Y');
        $month = (int) $dt->format('n');
        $maxDay = (int) $dt->setDate($year, $month, 1)->format('t');
        $clamped = max(1, min($maxDay, $dom));
        return $dt->setDate($year, $month, $clamped);
    }

    /**
     * Mint a ticket from a recurring schedule row. Returns the new
     * ticket id, or null if the row is unfit to fire (no requester +
     * no fallback, missing subject/body).
     *
     * Routing follows the same path as a hand-filed ticket:
     *   - resolveTicketGroup() so a stale type-default doesn't strand
     *     the ticket.
     *   - runPostTicketCreateHooks() so AI classify, automations,
     *     default-group fallback, and auto-assign all run.
     *   - Sla::initializeForTicket() so SLA timers start.
     */
    public static function mintTicket(PDO $db, array $row): ?int
    {
        $subject = trim((string) ($row['subject'] ?? ''));
        $body    = trim((string) ($row['body'] ?? ''));
        if ($subject === '' || $body === '') {
            return null;
        }

        $requesterId = !empty($row['requester_id']) ? (int) $row['requester_id'] : null;
        if (!$requesterId) {
            // Fall back to the schedule's creator. If that's gone too,
            // the row's been orphaned — the caller should disable it.
            $requesterId = !empty($row['created_by']) ? (int) $row['created_by'] : null;
        }
        if (!$requesterId) {
            return null;
        }

        $verifier = $db->prepare('SELECT id FROM users WHERE id = ?');
        $verifier->execute([$requesterId]);
        if (!$verifier->fetchColumn()) {
            return null;
        }

        $typeId     = !empty($row['type_id'])     ? (int) $row['type_id']     : null;
        $priorityId = !empty($row['priority_id']) ? (int) $row['priority_id'] : null;
        $locationId = !empty($row['location_id']) ? (int) $row['location_id'] : null;
        $assignedTo = !empty($row['assigned_to']) ? (int) $row['assigned_to'] : null;
        $groupId    = !empty($row['group_id'])    ? (int) $row['group_id']    : null;

        $offset = $row['due_date_offset_days'] ?? null;
        $dueDate = null;
        if ($offset !== null && $offset !== '') {
            $offsetDays = max(0, (int) $offset);
            $dueDate = (new DateTimeImmutable('now'))
                ->modify("+{$offsetDays} days")
                ->format('Y-m-d');
        }

        $groupId = resolveTicketGroup($db, $groupId, $typeId);

        $db->prepare(
            'INSERT INTO tickets
                (subject, description, created_by, submitted_by, type_id, location_id,
                 status, priority_id, assigned_to, group_id, due_date)
             VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $subject, $body, $requesterId,
            $typeId, $locationId, 'open', $priorityId, $assignedTo, $groupId, $dueDate,
        ]);
        $ticketId = (int) $db->lastInsertId();

        // Audit trail — cron-minted tickets carry a timeline entry that
        // points back at the schedule that spawned them, so anyone
        // reading the ticket can see "this is the Q2 HVAC inspection,
        // not a one-off."
        $scheduleName = (string) ($row['name'] ?? ('Schedule #' . (int) ($row['id'] ?? 0)));
        $details = 'Ticket auto-created by recurring schedule: ' . $scheduleName . '.';
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details) VALUES (?, NULL, ?, ?)'
        )->execute([$ticketId, 'created', $details]);

        runPostTicketCreateHooks($db, $ticketId);

        if ($priorityId) {
            Sla::initializeForTicket($db, $ticketId, $priorityId, $typeId);
        }

        notifyGroupMembers($db, $ticketId);
        notifyRequesterTicketCreated($db, $ticketId);

        if ($assignedTo) {
            notifyAssignedAgent($db, $ticketId, $assignedTo);
            notifyRequesterTicketAssigned($db, $ticketId, $assignedTo);
        }

        return $ticketId;
    }

    /**
     * Human-readable cadence summary for the index list and timeline.
     */
    public static function describeCadence(array $row): string
    {
        $freq     = (string) ($row['frequency'] ?? 'monthly');
        $interval = max(1, (int) ($row['interval_value'] ?? 1));

        $dows = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        switch ($freq) {
            case 'daily':
                return $interval === 1 ? 'Every day' : "Every {$interval} days";

            case 'weekly':
                $dow = isset($row['day_of_week']) && $row['day_of_week'] !== null && $row['day_of_week'] !== ''
                    ? ($dows[(int) $row['day_of_week']] ?? '')
                    : '';
                $base = $interval === 1 ? 'Weekly' : "Every {$interval} weeks";
                return $dow !== '' ? "{$base} on {$dow}" : $base;

            case 'monthly':
                $dom = isset($row['day_of_month']) && $row['day_of_month'] !== null && $row['day_of_month'] !== ''
                    ? (int) $row['day_of_month']
                    : null;
                $base = $interval === 1 ? 'Monthly' : "Every {$interval} months";
                return $dom !== null ? "{$base} on day {$dom}" : $base;

            case 'yearly':
                $dom = isset($row['day_of_month']) && $row['day_of_month'] !== null && $row['day_of_month'] !== ''
                    ? (int) $row['day_of_month']
                    : null;
                $moy = isset($row['month_of_year']) && $row['month_of_year'] !== null && $row['month_of_year'] !== ''
                    ? (int) $row['month_of_year']
                    : null;
                $base = $interval === 1 ? 'Yearly' : "Every {$interval} years";
                if ($moy !== null && $dom !== null) {
                    return "{$base} on " . ($months[$moy] ?? '?') . " {$dom}";
                }
                if ($moy !== null) {
                    return "{$base} in " . ($months[$moy] ?? '?');
                }
                return $base;

            case 'custom':
                return $interval === 1 ? 'Every day' : "Every {$interval} days";
        }
        return ucfirst($freq);
    }
}
