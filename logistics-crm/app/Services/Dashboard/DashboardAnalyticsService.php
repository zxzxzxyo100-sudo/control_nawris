<?php

namespace App\Services\Dashboard;

use App\Models\Captain;
use App\Models\Order;
use App\Services\Orders\OrderDelayScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * بيانات الرسوم البيانية والتقارير للوحة التحكم.
 */
final class DashboardAnalyticsService
{
    private const TOP_CAPTAINS_LIMIT = 5;

    private const DELAY_REPORT_MIN_DAYS = 5;

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'orders_by_status' => $this->ordersPerStatus(),
            'top_captains' => $this->topCaptainsByPerformance(),
            'delay_over_5d_by_captain' => $this->delayCountPerCaptain(self::DELAY_REPORT_MIN_DAYS),
            /** طلبات تم تسجيل متابعة لها اليوم (آخر وقت متابعة ضمن اليوم الحالي بتوقيت التطبيق). */
            'daily_followup_count' => $this->dailyFollowUpCount(),
        ];
    }

    private function dailyFollowUpCount(): int
    {
        return (int) Order::query()
            ->whereDate('last_follow_up_at', now()->toDateString())
            ->count();
    }

    /**
     * @return array<string, int>
     */
    private function ordersPerStatus(): array
    {
        $rows = Order::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        return array_map('intval', $rows);
    }

    /**
     * أداء الكابتن: أفضل 5 حسب عدد الطلبات المكتملة + الطلبات النشطة حالياً.
     *
     * @return list<array{captain_id: int, code: string, full_name: string, completed_orders: int, active_orders: int}>
     */
    private function topCaptainsByPerformance(): array
    {
        $topCompleted = Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('captain_id')
            ->selectRaw('captain_id, COUNT(*) as completed_orders')
            ->groupBy('captain_id')
            ->orderByDesc('completed_orders')
            ->limit(self::TOP_CAPTAINS_LIMIT)
            ->pluck('completed_orders', 'captain_id');

        if ($topCompleted->isEmpty()) {
            return [];
        }

        $captainIds = $topCompleted->keys()->all();

        $activeCounts = Order::query()
            ->whereNotNull('captain_id')
            ->whereIn('captain_id', $captainIds)
            ->whereNotIn('status', Order::TERMINAL_STATUSES)
            ->selectRaw('captain_id, COUNT(*) as active_orders')
            ->groupBy('captain_id')
            ->pluck('active_orders', 'captain_id');

        $captains = Captain::query()->whereIn('id', $captainIds)->get()->keyBy('id');

        return $topCompleted->map(function (int $completed, $captainId) use ($activeCounts, $captains) {
            $c = $captains->get($captainId);

            return [
                'captain_id' => (int) $captainId,
                'code' => $c ? (string) $c->code : '',
                'full_name' => $c ? (string) $c->full_name : '',
                'completed_orders' => $completed,
                'active_orders' => (int) ($activeCounts[$captainId] ?? 0),
            ];
        })->values()->sortByDesc('completed_orders')->values()->all();
    }

    /**
     * عدد الطلبات المتأخرة ≥ N يوماً لكل كابتن (مسندة فقط).
     *
     * @return list<array{captain_id: int, code: string, full_name: string, delayed_orders: int}>
     */
    private function delayCountPerCaptain(int $minDelayDays): array
    {
        $minDelayDays = max(0, $minDelayDays);

        $q = Order::query()
            ->whereNotNull('captain_id')
            ->whereNotIn('status', Order::TERMINAL_STATUSES)
            ->whereNotNull('promised_delivery_at')
            ->where('promised_delivery_at', '<', now())
            ->where(fn (Builder $sub): void => OrderDelayScope::apply($sub, $minDelayDays))
            ->selectRaw('captain_id, COUNT(*) as delayed_orders')
            ->groupBy('captain_id');

        $counts = $q->pluck('delayed_orders', 'captain_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $captains = Captain::query()
            ->whereIn('id', $counts->keys())
            ->get(['id', 'code', 'full_name'])
            ->keyBy('id');

        return $counts->map(function (int $count, $captainId) use ($captains) {
            $c = $captains->get($captainId);

            return [
                'captain_id' => (int) $captainId,
                'code' => $c ? (string) $c->code : '',
                'full_name' => $c ? (string) $c->full_name : '',
                'delayed_orders' => (int) $count,
            ];
        })->values()->sortByDesc('delayed_orders')->values()->all();
    }
}
