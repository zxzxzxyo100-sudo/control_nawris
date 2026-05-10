<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
require_once __DIR__ . '/staff_performance_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

session_start();

$sessionName = trim((string) ($_SESSION['employee_name'] ?? ''));
if ($sessionName === '') {
    json_response(['success' => false, 'message' => 'Unauthorized session'], 401);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'message' => 'Invalid JSON body'], 400);
}

$code = trim((string) ($payload['tracking_code'] ?? ''));
$emp = trim((string) ($payload['employee_username'] ?? ''));

if ($code === '' || $emp === '') {
    json_response(['success' => false, 'message' => 'tracking_code and employee_username are required'], 400);
}

if (!hash_equals($sessionName, $emp)) {
    json_response(['success' => false, 'message' => 'Employee must match the logged-in session user'], 403);
}

try {
    $pdo = crm_pdo();
    $inserted = staff_perf_record_event($pdo, $emp, 'resolved', $code, null);
    json_response([
        'success' => true,
        'inserted' => $inserted,
    ], 200);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Failed to record',
        'error' => $e->getMessage(),
    ], 500);
}
