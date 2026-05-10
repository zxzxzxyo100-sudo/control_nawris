<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
require_once __DIR__ . '/staff_performance_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

session_start();

$sessionName = trim((string) ($_SESSION['employee_name'] ?? ''));
if ($sessionName === '') {
    json_response(['success' => false, 'message' => 'Unauthorized session'], 401);
}

$ym = trim((string) ($_GET['month'] ?? ''));
if ($ym === '' || !preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $ym = staff_perf_current_ym();
}

try {
    $pdo = crm_pdo();
    $board = staff_perf_leaderboard($pdo, $ym);
    json_response([
        'success' => true,
        'month' => $board['month'],
        'top' => $board['top'],
        'rows' => $board['rows'],
    ], 200);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Failed to load leaderboard',
        'error' => $e->getMessage(),
    ], 500);
}
