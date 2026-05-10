<?php
declare(strict_types=1);

/**
 * Control Tower KPIs — MySQL aggregates for Nawras dashboard.
 *
 * Env: CT_SHIPMENTS_TABLE (default: shipments)
 *
 * Card «تكدس المخزن»: % of in_company where COALESCE(received_at, created_at) is ≥ 48 hours old.
 * Card «تأخر التحويلات»: % of rows in CT_TRANSFERS_TABLE (default transfers) with active transfer status
 * where TIMESTAMPDIFF(HOUR, updated_at, NOW()) > 48 (not received / stale > 48h).
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
    <?php
declare(strict_types=1);

require __DIR__ . '/db.php';

// منع الطلبات غير المسموحة
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$table = getenv('CT_SHIPMENTS_TABLE') ?: 'shipments';

try {
    $pdo = crm_pdo();

    // استعلام واحد شامل لجلب كل التصنيفات التي تظهر في الصورة
    $sql = "
        SELECT 
            COUNT(*) AS total_all,
            -- الطرود التي بالطريق (التحويلات)
            SUM(CASE WHEN status IN ('transferring', 'transfer_pending', 'in_transit') THEN 1 ELSE 0 END) AS in_transit_count,
            -- مع المندوب
            SUM(CASE WHEN status IN ('with_driver', 'out_for_delivery') THEN 1 ELSE 0 END) AS with_driver_count,
            -- المرتجع
            SUM(CASE WHEN status IN ('returned', 'returning') THEN 1 ELSE 0 END) AS returned_count,
            -- التكدس (المتأخر في المخزن > 48 ساعة)
            SUM(CASE WHEN status = 'in_company' AND TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 48 THEN 1 ELSE 0 END) AS delayed_warehouse_count
        FROM `$table`
    ";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // تجهيز الرد ليتوافق مع واجهة المستخدم (الكرتات الملونة)
    json_response([
        'ok' => true,
        'summary' => [
            'total' => (int)$data['total_all'],
            'transit' => (int)$data['in_transit_count'],    // ستظهر في كرت "بالطريق"
            'driver' => (int)$data['with_driver_count'],    // ستظهر في كرت "مع المندوب"
            'returned' => (int)$data['returned_count'],     // ستظهر في كرت "المرتجع"
            'stale' => (int)$data['delayed_warehouse_count'] // كرت التكدس
        ]
    ]);

} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
    }

    if (!isset($have['status'], $have['created_at'])) {
        json_response([
            'ok' => false,
            'message' => 'Table must have at least status and created_at columns',
            'table' => $table,
        ], 422);
    }

    $hasReceived = isset($have['received_at']);

    // Anchor for warehouse age: COALESCE(received_at, created_at) when received_at exists on table
    if ($hasReceived) {
        $anchorExpr = 'COALESCE(`received_at`, `created_at`)';
    } else {
        $anchorExpr = '`created_at`';
    }

    // Warehouse: statuses = in_company by default; override with CT_WAREHOUSE_STATUSES (comma, snake_case tokens)
    $whStatusCsv = (string) (getenv('CT_WAREHOUSE_STATUSES') ?: 'in_company');
    $whStatuses = array_values(array_unique(array_filter(
        array_map('trim', explode(',', $whStatusCsv)),
        static fn ($s) => $s !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $s)
    )));
    if ($whStatuses === []) {
        $whStatuses = ['in_company'];
    }
    $whPlaceholders = [];
    $whBind = [];
    foreach ($whStatuses as $i => $st) {
        $k = ':whst_' . $i;
        $whPlaceholders[] = $k;
        $whBind[$k] = $st;
    }
    $whInList = implode(',', $whPlaceholders);

    // Warehouse: stale ≥ 48h (TIMESTAMPDIFF HOUR from anchor to NOW)
    $sqlWh = sprintf(
        'SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN TIMESTAMPDIFF(HOUR, %s, NOW()) >= 48 THEN 1 ELSE 0 END) AS delayed_count
         FROM `%s`
         WHERE `status` IN (%s)',
        $anchorExpr,
        str_replace('`', '``', $table),
        $whInList
    );

    $stWh = $pdo->prepare($sqlWh);
    foreach ($whBind as $k => $v) {
        $stWh->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stWh->execute();
    $rowWh = $stWh->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'delayed_count' => 0];
    $whTotal = (int)($rowWh['total_count'] ?? 0);
    $whDelayed = (int)($rowWh['delayed_count'] ?? 0);
    $whPct = $whTotal > 0 ? round(($whDelayed / $whTotal) * 100, 2) : 0.0;

    $transferTable = getenv('CT_TRANSFERS_TABLE') ?: 'transfers';
    $trTotal = 0;
    $trDelayed = 0;
    $trPct = 0.0;
    $transferMeta = ['table' => $transferTable, 'found' => false];

    if (preg_match('/^[a-zA-Z0-9_]+$/', $transferTable)) {
        $existsTr = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :t'
        );
        $existsTr->execute([':t' => $transferTable]);
        if ((int) $existsTr->fetchColumn() > 0) {
            $colsTrStmt = $pdo->prepare(
                'SELECT column_name FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t'
            );
            $colsTrStmt->execute([':t' => $transferTable]);
            $haveTr = [];
            foreach ($colsTrStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $haveTr[strtolower((string)($row['column_name'] ?? ''))] = true;
            }
            if (isset($haveTr['status'], $haveTr['updated_at'])) {
                $statusCsv = (string) (getenv('CT_TRANSFER_ACTIVE_STATUSES') ?: 'transferring,transfer_pending');
                $statuses = array_values(array_unique(array_filter(array_map('trim', explode(',', $statusCsv)), static fn ($s) => $s !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $s))));
                if ($statuses === []) {
                    $statuses = ['transferring'];
                }
                $placeholders = [];
                $bind = [];
                foreach ($statuses as $i => $st) {
                    $k = ':st_' . $i;
                    $placeholders[] = $k;
                    $bind[$k] = $st;
                }
                $inList = implode(',', $placeholders);
                $sqlTr = sprintf(
                    'SELECT
                        COUNT(*) AS total_count,
                        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, `updated_at`, NOW()) > 48 THEN 1 ELSE 0 END) AS delayed_count
                     FROM `%s`
                     WHERE `status` IN (%s)',
                    str_replace('`', '``', $transferTable),
                    $inList
                );
                $stTr = $pdo->prepare($sqlTr);
                foreach ($bind as $k => $v) {
                    $stTr->bindValue($k, $v, PDO::PARAM_STR);
                }
                $stTr->execute();
                $rowTr = $stTr->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'delayed_count' => 0];
                $trTotal = (int) ($rowTr['total_count'] ?? 0);
                $trDelayed = (int) ($rowTr['delayed_count'] ?? 0);
                $trPct = $trTotal > 0 ? round(($trDelayed / $trTotal) * 100, 2) : 0.0;
                $transferMeta = [
                    'table' => $transferTable,
                    'found' => true,
                    'statuses' => $statuses,
                    'stale_hours_threshold' => 48,
                    'time_column' => 'updated_at',
                ];
            } elseif (isset($haveTr['status'], $haveTr['created_at'])) {
                $statusCsv = (string) (getenv('CT_TRANSFER_ACTIVE_STATUSES') ?: 'transferring,transfer_pending');
                $statuses = array_values(array_unique(array_filter(array_map('trim', explode(',', $statusCsv)), static fn ($s) => $s !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $s))));
                if ($statuses === []) {
                    $statuses = ['transferring'];
                }
                $placeholders = [];
                $bind = [];
                foreach ($statuses as $i => $st) {
                    $k = ':st_' . $i;
                    $placeholders[] = $k;
                    $bind[$k] = $st;
                }
                $inList = implode(',', $placeholders);
                $sqlTr = sprintf(
                    'SELECT
                        COUNT(*) AS total_count,
                        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, `created_at`, NOW()) > 48 THEN 1 ELSE 0 END) AS delayed_count
                     FROM `%s`
                     WHERE `status` IN (%s)',
                    str_replace('`', '``', $transferTable),
                    $inList
                );
                $stTr = $pdo->prepare($sqlTr);
                foreach ($bind as $k => $v) {
                    $stTr->bindValue($k, $v, PDO::PARAM_STR);
                }
                $stTr->execute();
                $rowTr = $stTr->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'delayed_count' => 0];
                $trTotal = (int) ($rowTr['total_count'] ?? 0);
                $trDelayed = (int) ($rowTr['delayed_count'] ?? 0);
                $trPct = $trTotal > 0 ? round(($trDelayed / $trTotal) * 100, 2) : 0.0;
                $transferMeta = [
                    'table' => $transferTable,
                    'found' => true,
                    'statuses' => $statuses,
                    'stale_hours_threshold' => 48,
                    'time_column' => 'created_at',
                ];
            }
        }
    }

    $transferPayload = [
        'pct' => $trPct,
        'delayed_count' => $trDelayed,
        'total_count' => $trTotal,
        'meta' => $transferMeta,
    ];

    json_response([
        'ok' => true,
        'source' => 'mysql',
        'table' => $table,
        'warehouse_statuses' => $whStatuses,
        'warehouse' => [
            'pct' => $whPct,
            'delayed_count' => $whDelayed,
            'total_count' => $whTotal,
            'anchor' => $hasReceived ? 'COALESCE(received_at, created_at)' : 'created_at',
        ],
        'transfer_delays' => $transferPayload,
        'transit' => $transferPayload,
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 500);
}
