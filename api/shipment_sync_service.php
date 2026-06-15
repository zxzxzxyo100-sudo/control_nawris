<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Queue-oriented sync service for external API shipment updates.
 */
final class ShipmentSyncService
{
    private const CHUNK_SIZE = 500;

    // Columns used for upsert — must match the `shipments` table schema.
    private const UPSERT_COLUMNS = [
        'tracking_code', 'customer_name', 'customer_phone',
        'status', 'delay_days', 'upload_date',
        'api_source', 'external_id',
        'created_at', 'updated_at',
    ];

    // Columns to overwrite on duplicate tracking_code.
    private const UPDATE_ON_CONFLICT = [
        'customer_name', 'customer_phone', 'status',
        'delay_days', 'upload_date', 'api_source', 'updated_at',
    ];

    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Enqueue ────────────────────────────────────────────────────────────

    public function enqueue(array $payload): string
    {
        $jobUuid = $this->uuidV4();
        $stmt    = $this->pdo->prepare(
            'INSERT INTO shipment_sync_jobs
             (job_uuid, payload_json, status, available_at)
             VALUES (:uuid, :payload, :status, :available_at)'
        );
        $stmt->execute([
            ':uuid'         => $jobUuid,
            ':payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status'       => 'pending',
            ':available_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        return $jobUuid;
    }

    // ── Large import with chunked upsert ───────────────────────────────────

    /**
     * Processes records from the import_data job in memory-efficient chunks.
     * Uses a PHP generator so the full array is never held twice in memory.
     * Each chunk is wrapped in a transaction for atomicity.
     *
     * @param  array  $records  Raw records from the job payload.
     * @param  string $jobUuid  Used in log lines for traceability.
     * @return array{processed:int, chunks:int, skipped:int}
     */
    public function importRecords(array $records, string $jobUuid): array
    {
        $total     = count($records);
        $processed = 0;
        $skipped   = 0;
        $chunkNum  = 0;

        $this->importLog($jobUuid, 'info', "Started import", [
            'total_records' => $total,
        ]);

        // Generator yields one mapped row at a time — O(1) memory per iteration.
        $rowGenerator = $this->mapRecordsGenerator($records);

        $buffer = [];
        foreach ($rowGenerator as $row) {
            if ($row === null) {
                $skipped++;
                continue;
            }
            $buffer[] = $row;

            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->upsertChunk($buffer, $jobUuid, ++$chunkNum);
                $processed += count($buffer);
                $buffer     = [];

                $this->importLog($jobUuid, 'info', "Chunk saved", [
                    'chunk'     => $chunkNum,
                    'processed' => $processed,
                    'of_total'  => $total,
                ]);
            }
        }

        // Flush remaining rows.
        if ($buffer !== []) {
            $this->upsertChunk($buffer, $jobUuid, ++$chunkNum);
            $processed += count($buffer);

            $this->importLog($jobUuid, 'info', "Final chunk saved", [
                'chunk'     => $chunkNum,
                'processed' => $processed,
            ]);
        }

        $this->importLog($jobUuid, 'info', "Completed import", [
            'processed' => $processed,
            'skipped'   => $skipped,
            'chunks'    => $chunkNum,
        ]);

        return ['processed' => $processed, 'chunks' => $chunkNum, 'skipped' => $skipped];
    }

    // ── KPI event logging ─────────────────────────────────────────────────

    public function logKpiEvent(array $event): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO shipment_kpi_events
            (tracking_code, employee_username, old_status, new_status,
             delay_days, processed_at, processing_minutes, source, metadata_json)
            VALUES
            (:tracking_code, :employee_username, :old_status, :new_status,
             :delay_days, :processed_at, :processing_minutes, :source, :metadata_json)'
        );
        $stmt->execute([
            ':tracking_code'      => (string) ($event['tracking_code'] ?? ''),
            ':employee_username'  => (string) ($event['employee_username'] ?? ''),
            ':old_status'         => $event['old_status'] ?? null,
            ':new_status'         => (string) ($event['new_status'] ?? ''),
            ':delay_days'         => isset($event['delay_days']) ? (int) $event['delay_days'] : null,
            ':processed_at'       => (string) ($event['processed_at'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s')),
            ':processing_minutes' => max(0, (int) ($event['processing_minutes'] ?? 0)),
            ':source'             => (string) ($event['source'] ?? 'api'),
            ':metadata_json'      => json_encode($event['metadata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Generator: yields one mapped DB row (or null for invalid records).
     * Keeps memory usage flat regardless of payload size.
     *
     * @return \Generator<int, array<string,mixed>|null>
     */
    private function mapRecordsGenerator(array $records): \Generator
    {
        foreach ($records as $record) {
            yield $this->mapRecord($record);
        }
    }

    /**
     * Maps a raw API record to a DB row.
     * Returns null for records that cannot be safely inserted.
     *
     * @return array<string,mixed>|null
     */
    private function mapRecord($record): ?array
    {
        if (!is_array($record)) {
            return null;
        }

        $trackingCode = trim((string) ($record['tracking_code'] ?? $record['code'] ?? ''));
        if ($trackingCode === '') {
            return null; // tracking_code is the unique key — cannot insert without it.
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        return [
            'tracking_code'  => strtoupper($trackingCode),
            'customer_name'  => trim((string) ($record['customer_name'] ?? $record['client_name'] ?? '—')),
            'customer_phone' => trim((string) ($record['customer_phone'] ?? $record['receiver_phone'] ?? '')),
            'status'         => trim((string) ($record['status'] ?? 'with_driver')),
            'delay_days'     => max(0, (int) ($record['delay_days'] ?? 0)),
            'upload_date'    => substr($now, 0, 10),
            'api_source'     => 'external_api',
            'external_id'    => trim((string) ($record['external_id'] ?? $record['id'] ?? $trackingCode)),
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
    }

    /**
     * Builds and executes one bulk INSERT … ON DUPLICATE KEY UPDATE statement.
     * Wrapped in a transaction so the chunk is atomic.
     */
    private function upsertChunk(array $rows, string $jobUuid, int $chunkNum): void
    {
        if ($rows === []) {
            return;
        }

        // Build: (?, ?, ?, ...), (?, ?, ?, ...), ...
        $colCount    = count(self::UPSERT_COLUMNS);
        $placeholder = '(' . implode(', ', array_fill(0, $colCount, '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($rows), $placeholder));

        $colList = '`' . implode('`, `', self::UPSERT_COLUMNS) . '`';

        $updateParts = array_map(
            static function (string $col): string { return "`{$col}` = VALUES(`{$col}`)"; },
            self::UPDATE_ON_CONFLICT
        );
        $onDuplicate = implode(', ', $updateParts);

        $sql = "INSERT INTO `shipments` ({$colList})
                VALUES {$allPlaceholders}
                ON DUPLICATE KEY UPDATE {$onDuplicate}";

        // Flatten rows into a single values array.
        $values = [];
        foreach ($rows as $row) {
            foreach (self::UPSERT_COLUMNS as $col) {
                $values[] = $row[$col] ?? null;
            }
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->importLog($jobUuid, 'error', "Chunk {$chunkNum} failed, rolled back", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Writes a structured log line to logs/import_YYYY-MM-DD.log
     * Equivalent to Laravel's Log::info / Log::error.
     *
     * @param array<string,mixed> $context
     */
    private function importLog(string $jobUuid, string $level, string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $date    = date('Y-m-d');
        $logFile = "{$logDir}/import_{$date}.log";
        $ts      = date('Y-m-d H:i:s');
        $ctx     = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line    = "[{$ts}] [{$level}] [job:{$jobUuid}] {$message}{$ctx}" . PHP_EOL;

        error_log($line, 3, $logFile);
    }

    // ── UUID helper ───────────────────────────────────────────────────────

    private function uuidV4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

// ── HTTP entry-point (legacy) — kept for backward compatibility ───────────
if (php_sapi_name() !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw     = file_get_contents('php://input') ?: '{}';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        json_response(['ok' => false, 'message' => 'Invalid JSON payload'], 422);
    }

    $service = new ShipmentSyncService(crm_pdo());
    $jobUuid = $service->enqueue($payload);
    json_response(['ok' => true, 'job_uuid' => $jobUuid]);
}
