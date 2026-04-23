<?php

namespace App\Services\Orders;

use App\Models\Captain;
use App\Models\Order;

/**
 * إحصائيات سريعة للوحة التحكم (عمليات + تعطّل الطلبات).
 */
final class OrderOperationsStats
{
    /**
     * @return array<string, int|array<string, int>>
     */
    public function summary(): array
    {
        $byStatus = Order::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $delayedQuery = new DelayedOrderWithCaptainQuery;
        $delayedWithCaptain = $delayedQuery->countDelayed(1);
        $delayedSla5d = $delayedQuery->countDelayed(5);

        return [
            'orders_by_status' => array_map('intval', $byStatus),
            'orders_total' => (int) array_sum($byStatus),
            'captains_active' => (int) Captain::query()->where('is_active', true)->count(),
            /** طلبات متأخرة ومسندة لكابتن (≥ يوم) */
            'delayed_with_captain_at_least_1d' => $delayedWithCaptain,
            /** نفس المنطق مع عتبة 5 أيام (متوافق مع واجهة الشركاء delay_days=5) */
            'delayed_with_captain_at_least_5d' => $delayedSla5d,
        ];
    }
}
