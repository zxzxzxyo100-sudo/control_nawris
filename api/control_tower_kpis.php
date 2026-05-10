<?php
declare(strict_types=1);

/**
 * Control Tower KPIs — MySQL aggregates for Nawras dashboard.
 *
 * Env: CT_SHIPMENTS_TABLE (default: shipments)
 *
 * Card «تكدس المخزن»: % of in_company where COALESCE(received_at, created_at) is ≥ 48 hours old.
 * Card «تأخر الطريق»: % of in_transit / with_delegate / with_driver where updated_at is > 4 days old (96h).
 */
require __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$table = getenv('CT_SHIPMENTS_TABLE') ?: 'shipments';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    json_response(['ok' => false, 'message' => 'Invalid CT_SHIPMENTS_TABLE'], 400);
}

try {
    $pdo = crm_pdo();

    $colsStmt = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $colsStmt->execute([':t' => $table]);
    $have = [];
    foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $have[strtolower((string)($row['column_name'] ?? ''))] = true;
    }

    if (!isset($have['status'], $have['created_at'])) {
        json_response([
            'ok' => false,
            'message' => 'Table must have at least status and created_at columns',
            'table' => $table,
        ], 422);
    }

    $hasReceived = isset($have['received_at']);
    $hasUpdated = isset($have['updated_at']);

    // Anchor for warehouse age: COALESCE(received_at, created_at) when received_at exists on table
    if ($hasReceived) {
        $anchorExpr = 'COALESCE(`received_at`, `created_at`)';
    } else {
        $anchorExpr = '`created_at`';
    }

    // Spec: TIMESTAMPDIFF(HOUR, updated_at, NOW()); fallback to created_at only if updated_at column is absent
    $lastTouchExpr = $hasUpdated ? '`updated_at`' : '`created_at`';

    // Warehouse: in_company, stale ≥ 48h (TIMESTAMPDIFF HOUR from anchor to NOW)
    $sqlWh = sprintf(
        'SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN TIMESTAMPDIFF(HOUR, %s, NOW()) >= 48 THEN 1 ELSE 0 END) AS delayed_count
         FROM `%s`
         WHERE `status` = :st_in_company',
        $anchorExpr,
        str_replace('`', '``', $table)
    );

    $stWh = $pdo->prepare($sqlWh);
    $stWh->execute([':st_in_company' => 'in_company']);
    $rowWh = $stWh->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'delayed_count' => 0];
    $whTotal = (int)($rowWh['total_count'] ?? 0);
    $whDelayed = (int)($rowWh['delayed_count'] ?? 0);
    $whPct = $whTotal > 0 ? round(($whDelayed / $whTotal) * 100, 2) : 0.0;

    // Transit: last touch via TIMESTAMPDIFF(HOUR, updated_at, NOW()) per spec; COALESCE to created_at if needed
    $sqlTr = sprintf(
        'SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN TIMESTAMPDIFF(HOUR, %s, NOW()) > 96 THEN 1 ELSE 0 END) AS delayed_count
         FROM `%s`
         WHERE `status` IN (:st1, :st2, :st3)',
        $lastTouchExpr,
        str_replace('`', '``', $table)
    );

    $stTr = $pdo->prepare($sqlTr);
    $stTr->execute([
        ':st1' => 'in_transit',
        ':st2' => 'with_delegate',
        ':st3' => 'with_driver',
    ]);
    $rowTr = $stTr->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'delayed_count' => 0];
    $trTotal = (int)($rowTr['total_count'] ?? 0);
    $trDelayed = (int)($rowTr['delayed_count'] ?? 0);
    $trPct = $trTotal > 0 ? round(($trDelayed / $trTotal) * 100, 2) : 0.0;

    json_response([
        'ok' => true,
        'source' => 'mysql',
        'table' => $table,
        'warehouse' => [
            'pct' => $whPct,
            'delayed_count' => $whDelayed,
            'total_count' => $whTotal,
            'anchor' => $hasReceived ? 'COALESCE(received_at, created_at)' : 'created_at',
        ],
        'transit' => [
            'pct' => $trPct,
            'delayed_count' => $trDelayed,
            'total_count' => $trTotal,
            'stale_hours_threshold' => 96,
            'time_column' => $hasUpdated ? 'updated_at' : 'created_at',
        ],
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 500);
}
