<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/shipment_sync_service.php';

/**
 * CLI worker — processes pending jobs from shipment_sync_jobs table.
 *
 * Usage:
 *   php api/process_shipment_sync_jobs.php [max_batch]
 *
 * Supported job types:
 *   - status_updates : log KPI events per shipment update (original)
 *   - import_data    : bulk upsert shipments in 500-row chunks (new)
 */
if (php_sapi_name() !== 'cli') {
    json_response(['ok' => false, 'message' => 'CLI only endpoint'], 405);
}

$pdo      = crm_pdo();
$service  = new ShipmentSyncService($pdo);
$maxBatch = (int) ($argv[1] ?? 50);
$processed = 0;

for ($i = 0; $i < $maxBatch; $i++) {
    $pdo->beginTransaction();
    try {
        $job = lockNextPendingJob($pdo);
        if ($job === null) {
            $pdo->commit();
            break; // no more pending jobs
        }

        $payload = json_decode((string) $job['payload_json'], true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid job payload JSON');
        }

        $jobType = (string) ($payload['job_type'] ?? 'status_updates');
        $jobUuid = (string) ($job['job_uuid'] ?? (string) $job['id']);

        // ── Route to the appropriate handler ──────────────────────────────
        if ($jobType === 'import_data') {
            handleImportDataJob($service, $payload, $jobUuid, $pdo);
        } else {
            handleStatusUpdatesJob($service, $payload);
        }

        markJobDone($pdo, (int) $job['id']);
        $pdo->commit();
        $processed++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        failJob($pdo, isset($job['id']) ? (int) $job['id'] : 0, $e->getMessage());
    }
}

echo "Processed jobs: {$processed}" . PHP_EOL;

// ── Job handlers ──────────────────────────────────────────────────────────

/**
 * Handles: job_type = 'import_data'
 * Delegates chunked upsert to ShipmentSyncService::importRecords().
 */
function handleImportDataJob(
    ShipmentSyncService $service,
    array               $payload,
    string              $jobUuid,
    PDO                 $pdo
): void {
    $records = $payload['records'] ?? [];
    if (!is_array($records) || $records === []) {
        throw new RuntimeException('import_data job has empty or missing records array');
    }

    // importRecords() uses a generator internally — memory stays flat.
    $result = $service->importRecords($records, $jobUuid);

    echo sprintf(
        "import_data [%s] — processed: %d, skipped: %d, chunks: %d%s",
        $jobUuid,
        $result['processed'],
        $result['skipped'],
        $result['chunks'],
        PHP_EOL
    );
}

/**
 * Handles: job_type = 'status_updates' (original behavior — unchanged)
 */
function handleStatusUpdatesJob(ShipmentSyncService $service, array $payload): void
{
    $statusUpdates = $payload['status_updates'] ?? [];
    if (!is_array($statusUpdates)) {
        return;
    }

    foreach ($statusUpdates as $u) {
        if (!is_array($u)) {
            continue;
        }
        $service->logKpiEvent([
            'tracking_code'      => $u['tracking_code'] ?? '',
            'employee_username'  => $u['employee_username'] ?? '',
            'old_status'         => $u['old_status'] ?? null,
            'new_status'         => $u['new_status'] ?? '',
            'delay_days'         => $u['delay_days'] ?? null,
            'processed_at'       => $u['processed_at'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'processing_minutes' => $u['processing_minutes'] ?? 0,
            'source'             => 'api',
            'metadata'           => $u,
        ]);
    }
}

// ── Queue helpers ─────────────────────────────────────────────────────────

function lockNextPendingJob(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        "SELECT * FROM shipment_sync_jobs
         WHERE status = 'pending' AND available_at <= NOW()
         ORDER BY id ASC
         LIMIT 1
         FOR UPDATE"
    );
    $job = $stmt->fetch();
    if (!$job) {
        return null;
    }

    $update = $pdo->prepare(
        "UPDATE shipment_sync_jobs
         SET status = 'processing', attempts = attempts + 1, reserved_at = NOW()
         WHERE id = :id"
    );
    $update->execute([':id' => (int) $job['id']]);

    return $job;
}

function markJobDone(PDO $pdo, int $id): void
{
    if ($id <= 0) {
        return;
    }
    $stmt = $pdo->prepare(
        "UPDATE shipment_sync_jobs
         SET status = 'completed', completed_at = NOW(), last_error = NULL
         WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);
}

function failJob(PDO $pdo, int $id, string $error): void
{
    if ($id <= 0) {
        return;
    }
    // Retry up to 5 times with 1-minute back-off; then permanently fail.
    $stmt = $pdo->prepare(
        "UPDATE shipment_sync_jobs
         SET status        = CASE WHEN attempts >= 5 THEN 'failed' ELSE 'pending' END,
             available_at  = DATE_ADD(NOW(), INTERVAL 1 MINUTE),
             last_error    = :error
         WHERE id = :id"
    );
    $stmt->execute([
        ':id'    => $id,
        ':error' => mb_substr($error, 0, 2000),
    ]);

    // Print to stderr so cron logs capture failures.
    fwrite(STDERR, "Job {$id} failed: {$error}" . PHP_EOL);
}
