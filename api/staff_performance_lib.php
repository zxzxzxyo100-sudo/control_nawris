<?php
declare(strict_types=1);

/**
 * Cumulative staff performance (month-scoped) — never decrements.
 * Events are deduped per (employee, type, code, month).
 */
function staff_perf_current_ym(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m');
}

function staff_perf_normalize_code(string $raw): string
{
    $s = strtoupper(trim($raw));
    $s = preg_replace('/^API_/i', '', $s) ?? $s;

    return $s;
}

/**
 * @return bool true if a new row was inserted
 */
function staff_perf_record_event(PDO $pdo, string $employeeUsername, string $eventType, string $trackingCode, ?string $yearMonth = null): bool
{
    $u = trim($employeeUsername);
    $c = staff_perf_normalize_code($trackingCode);
    if ($u === '' || $c === '') {
        return false;
    }
    if (!in_array($eventType, ['contacted', 'resolved'], true)) {
        return false;
    }
    $ym = $yearMonth !== null && preg_match('/^\d{4}-\d{2}$/', $yearMonth) ? $yearMonth : staff_perf_current_ym();

    $stmt = $pdo->prepare(
        'INSERT INTO staff_performance_events
            (employee_username, event_type, tracking_code, year_month)
         VALUES
            (:employee, :event_type, :code, :ym)
         ON DUPLICATE KEY UPDATE
            id = id'
    );
    $stmt->execute([
        ':employee' => $u,
        ':event_type' => $eventType,
        ':code' => $c,
        ':ym' => $ym,
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * @return array<string, array{contacted:int, resolved:int}>
 */
function staff_perf_totals_for_month(PDO $pdo, string $yearMonth): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT employee_username, event_type, COUNT(*) AS c
         FROM staff_performance_events
         WHERE year_month = :ym
         GROUP BY employee_username, event_type'
    );
    $stmt->execute([':ym' => $yearMonth]);
    $out = [];
    while ($row = $stmt->fetch()) {
        $name = (string) $row['employee_username'];
        $type = (string) $row['event_type'];
        if (!isset($out[$name])) {
            $out[$name] = ['contacted' => 0, 'resolved' => 0];
        }
        $out[$name][$type] = (int) $row['c'];
    }

    return $out;
}

/**
 * @return array{top: ?array<string, mixed>, month: string, rows: list<array<string, mixed>>}
 */
function staff_perf_leaderboard(PDO $pdo, string $yearMonth): array
{
    $totals = staff_perf_totals_for_month($pdo, $yearMonth);
    $rows = [];
    foreach ($totals as $name => $c) {
        $rows[] = [
            'name' => $name,
            'contacted' => (int) ($c['contacted'] ?? 0),
            'resolved' => (int) ($c['resolved'] ?? 0),
            'bonus_eligible' => ((int) ($c['contacted'] ?? 0)) >= 20000,
        ];
    }
    usort(
        $rows,
        static function (array $a, array $b): int {
            $ca = $a['contacted'] <=> $b['contacted'];
            if ($ca !== 0) {
                return -$ca;
            }
            $ra = $a['resolved'] <=> $b['resolved'];

            return -$ra;
        }
    );
    $top = $rows[0] ?? null;

    return [
        'month' => $yearMonth,
        'rows' => $rows,
        'top' => $top,
    ];
}
