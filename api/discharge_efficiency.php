<?php
declare(strict_types=1);

/**
 * POST /api/discharge_efficiency.php
 * Body JSON: { "shipments": [ { "raw_status": "مرتجع مع المندوب", "assigned_staff": "Mohamed", "return_transition_ms": 1730000000000 }, ... ] }
 *
 * Response: {
 *   "success": true,
 *   "employees": [ {"employee": "Mohamed", "score": 95, "count": 3, "scored_count": 3, "average_age_days": 2.5, "needs_alert": false } ],
 *   "summary": { "filtered_count": 10, "scored_count": 8, "global_average_age_days": 2.8, "global_score": 92 }
 * }
 *
 * اختياري: عيّن متغير البيئة DISCHARGE_EFFICIENCY_API_KEY وأرسل الرأس X-Api-Key بنفس القيمة.
 *
 * من Laravel: require_once dirname(base_path()).'/api/discharge_efficiency_lib.php'; ثم discharge_compute_scores($shipments);
 */

require __DIR__ . '/db.php';
require_once __DIR__ . '/discharge_efficiency_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'message' => 'Use POST with JSON body: {"shipments":[...]}',
    ], 405);
}

$configuredKey = trim((string) (getenv('DISCHARGE_EFFICIENCY_API_KEY') ?: ''));
if ($configuredKey !== '') {
    $hdr = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($hdr === '' || !hash_equals($configuredKey, $hdr)) {
        json_response(['success' => false, 'message' => 'Invalid or missing X-Api-Key header'], 403);
    }
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    json_response(['success' => false, 'message' => 'Empty body'], 400);
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    json_response(['success' => false, 'message' => 'Invalid JSON'], 400);
}

$shipments = $body['shipments'] ?? null;
if (!is_array($shipments)) {
    json_response(['success' => false, 'message' => 'Missing shipments array'], 400);
}

try {
    $out = discharge_compute_scores($shipments);
    json_response([
        'success' => true,
        'employees' => $out['employees'],
        'summary' => $out['summary'],
    ], 200);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Computation failed',
        'error' => $e->getMessage(),
    ], 500);
}
