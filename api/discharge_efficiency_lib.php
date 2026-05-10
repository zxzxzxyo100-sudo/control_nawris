<?php
declare(strict_types=1);

/**
 * حساب كفاءة تفريغ «مرتجع مع المندوب» لكل موظف — نفس منطق الواجهة (مرور واحد).
 * يُستدعى من discharge_efficiency.php أو من أي Controller بعد تمرير مصفوفة طرود.
 */

const DISCHARGE_RETURN_STATUS = 'مرتجع مع المندوب';

/**
 * @param array<string, mixed> $row
 */
function discharge_pick_return_transition_ms(array $row): ?int
{
    if (isset($row['return_transition_ms'])) {
        $x = (int) $row['return_transition_ms'];
        if ($x > 0) {
            return $x;
        }
    }
    $keys = [
        'returned_at', 'returnedAt', 'status_changed_at', 'statusChangedAt',
        'return_status_at', 'updated_at', 'updatedAt', 'last_status_change', 'lastStatusChange',
    ];
    foreach ($keys as $k) {
        if (!array_key_exists($k, $row) || $row[$k] === null || $row[$k] === '') {
            continue;
        }
        $v = $row[$k];
        if (is_numeric($v)) {
            $n = (float) $v;
            $t = $n > 1e12 ? (int) $n : (int) round($n * 1000);
            if ($t > 0) {
                return $t;
            }
        } elseif (is_string($v)) {
            $ts = strtotime($v);
            if ($ts !== false && $ts > 0) {
                return $ts * 1000;
            }
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $row
 */
function discharge_pick_employee_name(array $row): string
{
    $keys = [
        'assigned_staff', 'assigned_user', 'assignedUser', 'assigned_to', 'assignedTo',
        'assigned_employee', 'assignedEmployee', 'employee_name', 'employeeName',
        'staff_name', 'staffName', 'handler', 'handler_name', 'follow_up_by', 'followUpBy',
    ];
    foreach ($keys as $k) {
        if (!array_key_exists($k, $row) || $row[$k] === null) {
            continue;
        }
        $v = $row[$k];
        if (is_array($v)) {
            $n = trim((string) ($v['name'] ?? $v['full_name'] ?? $v['username'] ?? $v['title'] ?? $v['label'] ?? ''));
            if ($n !== '') {
                return $n;
            }
        } else {
            $s = trim((string) $v);
            if ($s !== '') {
                return $s;
            }
        }
    }

    return 'غير مسند';
}

/**
 * @param array<int, array<string, mixed>> $shipments
 * @return array{
 *   employees: list<array{employee: string, score: float, count: int, scored_count: int, average_age_days: float, needs_alert: bool}>,
 *   summary: array{filtered_count: int, scored_count: int, global_average_age_days: float, global_score: float}
 * }
 */
function discharge_compute_scores(array $shipments): array
{
    $groups = [];
    $nowMs = (int) round(microtime(true) * 1000);
    $filteredCount = 0;
    $globalAgeSum = 0.0;
    $globalAgeN = 0;

    foreach ($shipments as $row) {
        $raw = trim((string) ($row['raw_status'] ?? $row['status'] ?? ''));
        if ($raw !== DISCHARGE_RETURN_STATUS) {
            continue;
        }
        ++$filteredCount;
        $name = discharge_pick_employee_name($row);
        if (!isset($groups[$name])) {
            $groups[$name] = ['orderCount' => 0, 'ageSum' => 0.0, 'ageN' => 0];
        }
        ++$groups[$name]['orderCount'];
        $tms = discharge_pick_return_transition_ms($row);
        if ($tms !== null && $tms > 0) {
            $days = ($nowMs - $tms) / 86400000.0;
            if (is_finite($days) && $days >= 0) {
                $groups[$name]['ageSum'] += $days;
                ++$groups[$name]['ageN'];
                $globalAgeSum += $days;
                ++$globalAgeN;
            }
        }
    }

    $employees = [];
    foreach ($groups as $name => $g) {
        $scored = (int) $g['ageN'];
        $avgAge = $scored > 0 ? $g['ageSum'] / $scored : 0.0;
        $score = $scored === 0 ? 100.0 : max(0.0, 100.0 - ($avgAge - 3.0) * 10.0);
        $employees[] = [
            'employee' => $name,
            'score' => round($score, 2),
            'count' => (int) $g['orderCount'],
            'scored_count' => $scored,
            'average_age_days' => round($avgAge, 4),
            'needs_alert' => $score < 70.0,
        ];
    }

    usort($employees, static function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            if ($a['count'] === $b['count']) {
                return strcmp((string) $a['employee'], (string) $b['employee']);
            }

            return $b['count'] <=> $a['count'];
        }

        return $a['score'] <=> $b['score'];
    });

    $globalAvg = $globalAgeN > 0 ? $globalAgeSum / $globalAgeN : 0.0;
    $globalScore = $globalAgeN === 0 ? 100.0 : max(0.0, 100.0 - ($globalAvg - 3.0) * 10.0);

    return [
        'employees' => $employees,
        'summary' => [
            'filtered_count' => $filteredCount,
            'scored_count' => $globalAgeN,
            'global_average_age_days' => round($globalAvg, 4),
            'global_score' => round($globalScore, 2),
        ],
    ];
}
