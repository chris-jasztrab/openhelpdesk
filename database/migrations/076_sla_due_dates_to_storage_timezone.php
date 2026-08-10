<?php
/**
 * Migration 076 — Re-express stored SLA due dates in server local time
 *
 * Sla::dueDatesFor() used to format `first_response_due_at` / `resolution_due_at`
 * in the *business* timezone, but everything that reads them treats them as
 * server local time: the ticket views parse them with a bare
 * `new DateTimeImmutable()`, the ticket cards use `strtotime()`, and the SLA
 * reports compare with SQL `NOW()`. Where the two timezones differed, every
 * comparison was off by the offset — enough to mark a brand-new ticket breached
 * the moment it was created.
 *
 * Sla now writes storage time. This migration brings existing rows into line by
 * reinterpreting each stored value in the business timezone and re-expressing
 * the same instant in the server timezone. Done per row in PHP rather than as
 * one SQL offset so DST is handled correctly for each individual date.
 *
 * NO-OP on any install where the two timezones already agree — which includes
 * every install that never changed Business Hours away from the server's own
 * timezone. Nothing is written in that case.
 *
 * `sla_started_at` is deliberately left alone: it shipped one version earlier and
 * its backfill populated it from `created_at`, which is already storage time, so
 * a blanket conversion would corrupt the majority of those rows.
 *
 * Idempotent in the sense that matters: once the timezones agree (or once the
 * rows are converted and Sla writes storage time), re-running changes nothing.
 */
return static function (PDO $pdo): void {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute(['business_hours_timezone']);
    $bizTzName = (string) ($stmt->fetchColumn() ?: '');

    if ($bizTzName === '') {
        return; // Business hours never configured — no SLA clocks exist
    }

    try {
        $bizTz = new DateTimeZone($bizTzName);
    } catch (Exception $e) {
        return; // Unparseable timezone; leave the data untouched
    }
    $storageTz = new DateTimeZone(date_default_timezone_get());

    if ($bizTz->getName() === $storageTz->getName()) {
        return; // Same frame — nothing was ever skewed
    }

    $rows = $pdo->query(
        'SELECT id, first_response_due_at, resolution_due_at FROM tickets
          WHERE first_response_due_at IS NOT NULL OR resolution_due_at IS NOT NULL'
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        return;
    }

    $update = $pdo->prepare(
        'UPDATE tickets SET first_response_due_at = ?, resolution_due_at = ? WHERE id = ?'
    );

    $shift = static function (?string $value) use ($bizTz, $storageTz): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value, $bizTz))
                ->setTimezone($storageTz)
                ->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return $value; // Unparseable — better left as-is than nulled
        }
    };

    foreach ($rows as $row) {
        $update->execute([
            $shift($row['first_response_due_at']),
            $shift($row['resolution_due_at']),
            $row['id'],
        ]);
    }
};
