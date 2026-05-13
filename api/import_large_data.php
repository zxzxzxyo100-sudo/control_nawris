<?php
declare(strict_types=1);

/**
 * POST /api/import_large_data.php
 * Body JSON: { "records": [ {...}, ... ] }
 *
 * Returns 202 immediately with job_id.
 * Processing happens in the background via the CLI worker:
 *   php api/process_shipment_sync_jobs.php
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/shipment_sync_service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

session_start();

$employeeId   = $_SESSION['employee_id']   ?? null;
$employeeName = $_SESSION['employee_name'] ?? null;
if (!$employeeId || !$employeeName) {
    json_response(['success' => false, 'message' => 'Unauthorized session'], 401);
}

$raw  = (string) (file_get_contents('php://input') ?: '{}');
$body = json_decode($raw, true);
if (!is_array($body)) {
    json_response(['success' => false, 'message' => 'Invalid JSON body'], 400);
}

$records = $body['records'] ?? $body['data'] ?? [];
if (!is_array($records) || count($records) === 0) {
    json_response(['success' => false, 'message' => '"records" array is required and must not be empty'], 422);
}

const MAX_RECORDS_PER_REQUEST = 50000;
if (count($records) > MAX_RECORDS_PER_REQUEST) {
    json_response([
        'success' => false,
        'message' => sprintf('Too many records. Max %d per request.', MAX_RECORDS_PER_REQUEST),
    ], 422);
}

try {
    $pdo     = crm_pdo();
    $service = new ShipmentSyncService($pdo);

    $jobId = $service->enqueue([
        'job_type'       => 'import_data',
        'records'        => $records,
        'submitted_by'   => (int) $employeeId,
        'submitter_name' => (string) $employeeName,
        'submitted_at'   => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
    ]);

    json_response([
        'success'      => true,
        'status'       => 'accepted',
        'job_id'       => $jobId,
        'record_count' => count($records),
        'message'      => 'Import queued successfully. Processing in background.',
    ], 202);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Failed to enqueue import job',
        'error'   => $e->getMessage(),
    ], 500);
}
