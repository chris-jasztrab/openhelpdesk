<?php

declare(strict_types=1);

class Sla
{
    /**
     * Get the configured business schedule (timezone + weekly hours).
     * Returns null if not configured.
     *
     * @return array{tz: string, schedule: array<string, array{0: string, 1: string}|null>}|null
     */
    public static function getBusinessSchedule(): ?array
    {
        $tz = getSetting('business_hours_timezone');
        $json = getSetting('business_hours_schedule');
        if ($tz === '' || $json === '') {
            return null;
        }
        $schedule = json_decode($json, true);
        if (!is_array($schedule)) {
            return null;
        }
        return ['tz' => $tz, 'schedule' => $schedule];
    }

    /**
     * Return Y-m-d strings for all holidays marked as excluded from SLA.
     *
     * @return string[]
     */
    public static function getExcludedDates(PDO $db): array
    {
        $stmt = $db->query("SELECT holiday_date FROM holidays WHERE exclude_from_sla = 1");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Add business minutes to a starting datetime, skipping non-business time.
     *
     * @param DateTimeImmutable $start    Start time (will be converted to business TZ)
     * @param int               $minutes  Business minutes to add
     * @param string            $tz       Timezone identifier
     * @param array             $schedule Weekly schedule (mon-sun => [start, end] or null)
     * @return DateTimeImmutable The resulting datetime in the business timezone
     */
    public static function addBusinessMinutes(DateTimeImmutable $start, int $minutes, string $tz, array $schedule, array $excludedDates = []): DateTimeImmutable
    {
        $timezone = new DateTimeZone($tz);
        $current = $start->setTimezone($timezone);
        $remaining = $minutes;

        // Safety limit: don't loop more than 365 days
        $maxIterations = 365;
        $iteration = 0;

        while ($remaining > 0 && $iteration < $maxIterations) {
            $iteration++;
            $dayKey = strtolower(substr($current->format('D'), 0, 3)); // mon, tue, etc.

            // Holiday/closed day — skip to next day without consuming SLA minutes
            if (in_array($current->format('Y-m-d'), $excludedDates, true)) {
                $current = $current->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }

            $daySchedule = $schedule[$dayKey] ?? null;

            // Non-business day — skip to next day
            if ($daySchedule === null || !is_array($daySchedule) || count($daySchedule) < 2) {
                $current = $current->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }

            [$startTime, $endTime] = $daySchedule;
            [$startH, $startM] = array_map('intval', explode(':', $startTime));
            [$endH, $endM] = array_map('intval', explode(':', $endTime));

            $dayStart = $current->setTime($startH, $startM, 0);
            $dayEnd = $current->setTime($endH, $endM, 0);

            // If current time is before business start, jump to start
            if ($current < $dayStart) {
                $current = $dayStart;
            }

            // If current time is at or after business end, skip to next day
            if ($current >= $dayEnd) {
                $current = $current->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }

            // Calculate available minutes until end of business day
            $availableSeconds = $dayEnd->getTimestamp() - $current->getTimestamp();
            $availableMinutes = (int) floor($availableSeconds / 60);

            if ($remaining <= $availableMinutes) {
                // All remaining minutes fit in this business day
                $current = $current->modify("+{$remaining} minutes");
                $remaining = 0;
            } else {
                // Use all available minutes and move to next day
                $remaining -= $availableMinutes;
                $current = $current->modify('+1 day')->setTime(0, 0, 0);
            }
        }

        return $current;
    }

    /**
     * Compute the SLA state for a single ticket.
     *
     * @param array $ticket Ticket row with SLA columns
     * @return string|null 'on_track', 'warning', 'breached', or null if no SLA
     */
    public static function computeSlaState(array $ticket): ?string
    {
        $responseDue = $ticket['first_response_due_at'] ?? null;
        $resolutionDue = $ticket['resolution_due_at'] ?? null;

        // No SLA configured for this ticket
        if ($responseDue === null && $resolutionDue === null) {
            return null;
        }

        // If paused, keep existing state
        if (!empty($ticket['sla_paused_at'])) {
            return $ticket['sla_state'] ?? 'on_track';
        }

        $now = new DateTimeImmutable('now');

        // Check for breach: either due date is past
        $responseBreach = false;
        $resolutionBreach = false;

        if ($responseDue !== null && empty($ticket['first_responded_at'])) {
            $responseDueAt = new DateTimeImmutable($responseDue);
            if ($now > $responseDueAt) {
                $responseBreach = true;
            }
        }

        if ($resolutionDue !== null) {
            $resolutionDueAt = new DateTimeImmutable($resolutionDue);
            if ($now > $resolutionDueAt) {
                $resolutionBreach = true;
            }
        }

        if ($responseBreach || $resolutionBreach) {
            return 'breached';
        }

        // Check for warning: within 80% of elapsed time for either SLA
        $createdAt = new DateTimeImmutable($ticket['created_at']);

        // First response warning
        if ($responseDue !== null && empty($ticket['first_responded_at'])) {
            $responseDueAt = new DateTimeImmutable($responseDue);
            $totalSeconds = $responseDueAt->getTimestamp() - $createdAt->getTimestamp();
            $elapsedSeconds = $now->getTimestamp() - $createdAt->getTimestamp();
            if ($totalSeconds > 0 && ($elapsedSeconds / $totalSeconds) >= 0.8) {
                return 'warning';
            }
        }

        // Resolution warning
        if ($resolutionDue !== null) {
            $resolutionDueAt = new DateTimeImmutable($resolutionDue);
            $totalSeconds = $resolutionDueAt->getTimestamp() - $createdAt->getTimestamp();
            $elapsedSeconds = $now->getTimestamp() - $createdAt->getTimestamp();
            if ($totalSeconds > 0 && ($elapsedSeconds / $totalSeconds) >= 0.8) {
                return 'warning';
            }
        }

        return 'on_track';
    }

    /**
     * Recalculate SLA state for all active tickets.
     *
     * @return int Number of tickets updated
     */
    public static function recalculateAll(PDO $db): int
    {
        if (!slaEnabled()) {
            return 0; // SLA disabled site-wide
        }

        $openIn = ticketStatusSqlIn(ticketOpenBucketSlugs(), 'status');
        $stmt = $db->query(
            "SELECT id, subject, assigned_to, created_at, first_response_due_at, resolution_due_at,
                    first_responded_at, sla_state, sla_paused_at
             FROM tickets
             WHERE $openIn
               AND (first_response_due_at IS NOT NULL OR resolution_due_at IS NOT NULL)"
        );

        $updated = 0;
        $updateStmt = $db->prepare('UPDATE tickets SET sla_state = ? WHERE id = ?');

        while ($ticket = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $newState = self::computeSlaState($ticket);
            if ($newState !== null && $newState !== ($ticket['sla_state'] ?? '')) {
                $updateStmt->execute([$newState, $ticket['id']]);
                $updated++;

                // On the transition *into* warning/breached, drop an in-app
                // notification for the assigned agent so SLA risk surfaces on
                // their notifications feed (not just the dashboard colour).
                $assignedTo = (int) ($ticket['assigned_to'] ?? 0);
                if ($assignedTo > 0 && function_exists('createNotification')) {
                    if ($newState === 'warning') {
                        createNotification(
                            $db,
                            $assignedTo,
                            (int) $ticket['id'],
                            'sla_warning',
                            'SLA deadline approaching for "' . ($ticket['subject'] ?? ('Ticket #' . $ticket['id'])) . '"'
                        );
                    } elseif ($newState === 'breached') {
                        createNotification(
                            $db,
                            $assignedTo,
                            (int) $ticket['id'],
                            'sla_breach',
                            'SLA breached on "' . ($ticket['subject'] ?? ('Ticket #' . $ticket['id'])) . '"'
                        );
                    }
                }
            }
        }

        return $updated;
    }

    /**
     * Find the best-matching SLA policy for a type+priority combination.
     * Checks for a type-specific policy first, then falls back to the default (NULL type).
     */
    public static function findPolicy(PDO $db, ?int $typeId, int $priorityId): ?array
    {
        if ($typeId !== null) {
            $stmt = $db->prepare(
                'SELECT first_response_minutes, resolution_minutes FROM sla_policies WHERE type_id = ? AND priority_id = ?'
            );
            $stmt->execute([$typeId, $priorityId]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($policy) {
                return $policy;
            }
        }
        // Fallback: default policy (type_id IS NULL)
        $stmt = $db->prepare(
            'SELECT first_response_minutes, resolution_minutes FROM sla_policies WHERE type_id IS NULL AND priority_id = ?'
        );
        $stmt->execute([$priorityId]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        return $policy ?: null;
    }

    /**
     * Initialize SLA for a newly created ticket.
     * Sets first_response_due_at and resolution_due_at based on the best-matching SLA policy.
     */
    public static function initializeForTicket(PDO $db, int $ticketId, int $priorityId, ?int $typeId = null): void
    {
        if (!slaEnabled()) {
            return; // SLA disabled site-wide
        }

        $biz = self::getBusinessSchedule();
        if ($biz === null) {
            return; // No business hours configured
        }

        $sla = self::findPolicy($db, $typeId, $priorityId);
        if (!$sla) {
            return; // No SLA policy for this type+priority
        }

        $excluded = self::getExcludedDates($db);
        $now = new DateTimeImmutable('now', new DateTimeZone($biz['tz']));
        $responseDue = self::addBusinessMinutes($now, (int) $sla['first_response_minutes'], $biz['tz'], $biz['schedule'], $excluded);
        $resolutionDue = self::addBusinessMinutes($now, (int) $sla['resolution_minutes'], $biz['tz'], $biz['schedule'], $excluded);

        $db->prepare(
            'UPDATE tickets SET first_response_due_at = ?, resolution_due_at = ?, sla_state = ? WHERE id = ?'
        )->execute([
            $responseDue->format('Y-m-d H:i:s'),
            $resolutionDue->format('Y-m-d H:i:s'),
            'on_track',
            $ticketId,
        ]);

        // Add internal timeline entry
        $details = sprintf(
            'SLA initialized: First response due by %s, Resolution due by %s',
            $responseDue->format('M j, Y g:i A'),
            $resolutionDue->format('M j, Y g:i A')
        );
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
        )->execute([$ticketId, 'sla_set', $details]);
    }

    /**
     * Pause SLA timers when ticket enters pending status.
     */
    public static function pause(PDO $db, int $ticketId): void
    {
        if (!slaEnabled()) {
            return; // SLA disabled site-wide
        }

        $db->prepare('UPDATE tickets SET sla_paused_at = NOW() WHERE id = ? AND sla_paused_at IS NULL')
            ->execute([$ticketId]);

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
        )->execute([$ticketId, 'sla_paused', 'SLA timers paused (ticket pending)']);
    }

    /**
     * Resume SLA timers when ticket leaves pending status.
     * Extends due dates by the paused duration in business minutes.
     */
    public static function resume(PDO $db, int $ticketId): void
    {
        if (!slaEnabled()) {
            return; // SLA disabled site-wide
        }

        $stmt = $db->prepare(
            'SELECT sla_paused_at, first_response_due_at, resolution_due_at, first_responded_at FROM tickets WHERE id = ?'
        );
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket || empty($ticket['sla_paused_at'])) {
            return;
        }

        $biz = self::getBusinessSchedule();
        if ($biz === null) {
            // Just clear the pause without extending
            $db->prepare('UPDATE tickets SET sla_paused_at = NULL WHERE id = ?')->execute([$ticketId]);
            return;
        }

        $excluded = self::getExcludedDates($db);
        $pausedAt = new DateTimeImmutable($ticket['sla_paused_at'], new DateTimeZone($biz['tz']));
        $now = new DateTimeImmutable('now', new DateTimeZone($biz['tz']));

        // Calculate paused business minutes (holidays are excluded — they don't count as paused time either)
        $pausedMinutes = self::countBusinessMinutes($pausedAt, $now, $biz['tz'], $biz['schedule'], $excluded);

        // Extend due dates
        $updates = [];
        if ($ticket['first_response_due_at'] !== null && empty($ticket['first_responded_at'])) {
            $oldDue = new DateTimeImmutable($ticket['first_response_due_at'], new DateTimeZone($biz['tz']));
            $newDue = $oldDue->modify("+{$pausedMinutes} minutes");
            $updates['first_response_due_at'] = $newDue->format('Y-m-d H:i:s');
        }
        if ($ticket['resolution_due_at'] !== null) {
            $oldDue = new DateTimeImmutable($ticket['resolution_due_at'], new DateTimeZone($biz['tz']));
            $newDue = $oldDue->modify("+{$pausedMinutes} minutes");
            $updates['resolution_due_at'] = $newDue->format('Y-m-d H:i:s');
        }

        $setParts = ['sla_paused_at = NULL'];
        $params = [];
        foreach ($updates as $col => $val) {
            $setParts[] = "{$col} = ?";
            $params[] = $val;
        }
        $params[] = $ticketId;
        $db->prepare('UPDATE tickets SET ' . implode(', ', $setParts) . ' WHERE id = ?')->execute($params);

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
        )->execute([$ticketId, 'sla_resumed', "SLA timers resumed (paused {$pausedMinutes} business minutes)"]);
    }

    /**
     * Recalculate SLA due dates when priority changes.
     */
    public static function onPriorityChanged(PDO $db, int $ticketId, int $newPriorityId, ?int $typeId = null): void
    {
        if (!slaEnabled()) {
            return; // SLA disabled site-wide
        }

        $biz = self::getBusinessSchedule();
        if ($biz === null) {
            return;
        }

        $sla = self::findPolicy($db, $typeId, $newPriorityId);

        if (!$sla) {
            // No policy for new priority — clear SLA
            $db->prepare(
                'UPDATE tickets SET first_response_due_at = NULL, resolution_due_at = NULL, sla_state = NULL WHERE id = ?'
            )->execute([$ticketId]);
            return;
        }

        // Recalculate from ticket creation time
        $stmt = $db->prepare('SELECT created_at, first_responded_at FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            return;
        }

        $excluded = self::getExcludedDates($db);
        $createdAt = new DateTimeImmutable($ticket['created_at'], new DateTimeZone($biz['tz']));
        $responseDue = self::addBusinessMinutes($createdAt, (int) $sla['first_response_minutes'], $biz['tz'], $biz['schedule'], $excluded);
        $resolutionDue = self::addBusinessMinutes($createdAt, (int) $sla['resolution_minutes'], $biz['tz'], $biz['schedule'], $excluded);

        $db->prepare(
            'UPDATE tickets SET first_response_due_at = ?, resolution_due_at = ? WHERE id = ?'
        )->execute([
            $responseDue->format('Y-m-d H:i:s'),
            $resolutionDue->format('Y-m-d H:i:s'),
            $ticketId,
        ]);

        // Recalculate state immediately
        $stmt = $db->prepare(
            'SELECT id, created_at, first_response_due_at, resolution_due_at, first_responded_at, sla_state, sla_paused_at FROM tickets WHERE id = ?'
        );
        $stmt->execute([$ticketId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($updated) {
            $newState = self::computeSlaState($updated);
            if ($newState !== null) {
                $db->prepare('UPDATE tickets SET sla_state = ? WHERE id = ?')->execute([$newState, $ticketId]);
            }
        }
    }

    /**
     * Recalculate SLA due dates when ticket type changes.
     */
    public static function onTypeChanged(PDO $db, int $ticketId, ?int $newTypeId): void
    {
        if (!slaEnabled()) {
            return; // SLA disabled site-wide
        }

        $biz = self::getBusinessSchedule();
        if ($biz === null) {
            return;
        }

        $stmt = $db->prepare('SELECT created_at, priority_id, first_responded_at FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket || empty($ticket['priority_id'])) {
            return; // No priority set — no SLA to calculate
        }

        $priorityId = (int) $ticket['priority_id'];
        $sla = self::findPolicy($db, $newTypeId, $priorityId);

        if (!$sla) {
            // No policy for new type+priority — clear SLA
            $db->prepare(
                'UPDATE tickets SET first_response_due_at = NULL, resolution_due_at = NULL, sla_state = NULL WHERE id = ?'
            )->execute([$ticketId]);
            return;
        }

        // Recalculate from ticket creation time
        $excluded = self::getExcludedDates($db);
        $createdAt = new DateTimeImmutable($ticket['created_at'], new DateTimeZone($biz['tz']));
        $responseDue = self::addBusinessMinutes($createdAt, (int) $sla['first_response_minutes'], $biz['tz'], $biz['schedule'], $excluded);
        $resolutionDue = self::addBusinessMinutes($createdAt, (int) $sla['resolution_minutes'], $biz['tz'], $biz['schedule'], $excluded);

        $db->prepare(
            'UPDATE tickets SET first_response_due_at = ?, resolution_due_at = ? WHERE id = ?'
        )->execute([
            $responseDue->format('Y-m-d H:i:s'),
            $resolutionDue->format('Y-m-d H:i:s'),
            $ticketId,
        ]);

        // Recalculate state immediately
        $stmt = $db->prepare(
            'SELECT id, created_at, first_response_due_at, resolution_due_at, first_responded_at, sla_state, sla_paused_at FROM tickets WHERE id = ?'
        );
        $stmt->execute([$ticketId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($updated) {
            $newState = self::computeSlaState($updated);
            if ($newState !== null) {
                $db->prepare('UPDATE tickets SET sla_state = ? WHERE id = ?')->execute([$newState, $ticketId]);
            }
        }
    }

    /**
     * Count business minutes between two datetimes.
     */
    public static function countBusinessMinutes(DateTimeImmutable $from, DateTimeImmutable $to, string $tz, array $schedule, array $excludedDates = []): int
    {
        $timezone = new DateTimeZone($tz);
        $current = $from->setTimezone($timezone);
        $end = $to->setTimezone($timezone);
        $totalMinutes = 0;

        $maxIterations = 365;
        $iteration = 0;

        while ($current < $end && $iteration < $maxIterations) {
            $iteration++;
            $dayKey = strtolower(substr($current->format('D'), 0, 3));

            // Holiday/closed day — skip without counting minutes
            if (in_array($current->format('Y-m-d'), $excludedDates, true)) {
                $current = $current->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }

            $daySchedule = $schedule[$dayKey] ?? null;

            if ($daySchedule === null || !is_array($daySchedule) || count($daySchedule) < 2) {
                $current = $current->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }

            [$startTime, $endTime] = $daySchedule;
            [$startH, $startM] = array_map('intval', explode(':', $startTime));
            [$endH, $endM] = array_map('intval', explode(':', $endTime));

            $dayStart = $current->setTime($startH, $startM, 0);
            $dayEnd = $current->setTime($endH, $endM, 0);

            // Effective start: max of current and day start
            $effectiveStart = ($current > $dayStart) ? $current : $dayStart;

            // Effective end: min of $end and day end
            $effectiveEnd = ($end < $dayEnd) ? $end : $dayEnd;

            if ($effectiveStart < $effectiveEnd) {
                $diffSeconds = $effectiveEnd->getTimestamp() - $effectiveStart->getTimestamp();
                $totalMinutes += (int) floor($diffSeconds / 60);
            }

            // Move to next day
            $current = $current->modify('+1 day')->setTime(0, 0, 0);
        }

        return $totalMinutes;
    }
}
