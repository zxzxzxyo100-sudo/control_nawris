<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$table = getenv('CT_SHIPMENTS_TABLE') ?: 'shipments';

try {
    $pdo = crm_pdo();

    // استعلام واحد شامل لجلب كل الأرقام بناءً على الحالات
    $sql = "
        SELECT 
            COUNT(*) AS total_all,
            -- حالات 'بالطريق' (أضفنا كلمات إضافية لضمان التغطية)
            SUM(CASE WHEN status IN ('transferring', 'transfer_pending', 'in_transit', 'shipped') THEN 1 ELSE 0 END) AS in_transit_count,
            -- حالات 'مع المندوب'
            SUM(CASE WHEN status IN ('with_driver', 'out_for_delivery', 'with_agent') THEN 1 ELSE 0 END) AS with_driver_count,
            -- حالات 'المرتجع'
            SUM(CASE WHEN status IN ('returned', 'returning', 'rejected') THEN 1 ELSE 0 END) AS returned_count,
            -- التكدس (أكبر من 48 ساعة في المخزن)
            SUM(CASE WHEN status = 'in_company' AND TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 48 THEN 1 ELSE 0 END) AS delayed_warehouse_count
        FROM `$table`
    ";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // هذا الجزء هو المسؤول عن إرسال البيانات للوحة التحكم بنفس الأسماء التي تتوقعها
    json_response([
        'ok' => true,
        'warehouse' => [
            'total_count' => (int)$data['total_all'],
            'delayed_count' => (int)$data['delayed_warehouse_count'],
            'pct' => (int)$data['total_all'] > 0 ? round(((int)$data['delayed_warehouse_count'] / (int)$data['total_all']) * 100, 2) : 0
        ],
        'transit' => [
            'total_count' => (int)$data['in_transit_count'],
            'delayed_count' => 0
        ],
        'transfer_delays' => [
            'total_count' => (int)$data['in_transit_count']
        ],
        // هذه الحقول إضافية لضمان عمل كرتات المندوب والمرتجع
        'with_driver' => (int)$data['with_driver_count'],
        'returned' => (int)$data['returned_count']
    ]);

} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
