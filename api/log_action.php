<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
require_once __DIR__ . '/staff_performance_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

session_start();

$employeeId = $_SESSION['employee_id'] ?? null;
$employeeName = $_SESSION['employee_name'] ?? null;

if (!$employeeId || !$employeeName) {
    json_response(['success' => false, 'message' => 'Unauthorized session'], 401);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    json_response(['success' => false, 'message' => 'Invalid JSON body'], 400);
}

$packageRaw = (string)($payload['package_id'] ?? '');
$trackingFull = trim((string)($payload['tracking_code'] ?? $packageRaw));
$packageId = preg_replace('/[^0-9]/', '', $packageRaw);
if ($packageId === '' && $trackingFull !== '') {
    $packageId = (string) sprintf('%u', crc32(staff_perf_normalize_code($trackingFull)));
}
$actionType = trim((string)($payload['action_type'] ?? ''));
$status = trim((string)($payload['status'] ?? ''));

if ($packageId === '' || $actionType === '') {
    json_response(['success' => false, 'message' => 'package_id and action_type are required'], 400);
}

try {
    $pdo = crm_pdo();
    $ins = $pdo->prepare(
        'INSERT INTO package_logs (package_id, employee_id, employee_name, action_type, status)
         VALUES (:package_id, :employee_id, :employee_name, :action_type, :status)'
    );
    $params = [
        ':package_id' => $packageId,
        ':employee_id' => (int) $employeeId,
        ':employee_name' => (string) $employeeName,
        ':action_type' => $actionType,
        ':status' => $status !== '' ? $status : null,
    ];
    $hasTcCol = false;
    try {
        $chk = $pdo->query(
            "SELECT COUNT(*) AS c FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'package_logs' AND column_name = 'tracking_code'"
        );
        $row = $chk ? $chk->fetch() : null;
        $hasTcCol = $row && (int) $row['c'] > 0;
    } catch (Throwable) {
        $hasTcCol = false;
    }
    if ($hasTcCol) {
        $ins = $pdo->prepare(
            'INSERT INTO package_logs (package_id, tracking_code, employee_id, employee_name, action_type, status)
             VALUES (:package_id, :tracking_code, :employee_id, :employee_name, :action_type, :status)'
        );
        $params[':tracking_code'] = $trackingFull !== '' ? $trackingFull : null;
    }
    $ins->execute($params);

    // Server-side monthly counters (all devices) — no decrement.
    if ($actionType === 'follow_up_update' && in_array($status, ['contacted', 'solved'], true) && $trackingFull !== '') {
        try {
            if ($status === 'contacted') {
                staff_perf_record_event($pdo, (string) $employeeName, 'contacted', $trackingFull, null);
            } elseif ($status === 'solved') {
                staff_perf_record_event($pdo, (string) $employeeName, 'resolved', $trackingFull, null);
            }
        } catch (Throwable $e) {
            if (getenv('STAFF_PERF_DEBUG') === '1') {
                error_log('staff_perf_record_event: ' . $e->getMessage());
            }
        }
    }

    json_response([
        'success' => true,
        'message' => 'Action logged',
        'id' => (int) $pdo->lastInsertId(),
    ], 200);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Failed to log action',
        'error' => $e->getMessage(),
    ], 500);
}
