<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Encapsulates "delayed while assigned to captain" business rules (SRP).
 * Keeps controllers thin and makes the delay predicate testable / reusable.
 */
final class DelayedOrderWithCaptainQuery
{
    /**
     * Orders are delayed with captain when:
     * - Assigned to a captain (captain_id present).
     * - Not in a terminal state (still ops-relevant).
     * - Promised delivery time exists and is in the past.
     * - Calendar days late >= $minDelayDays (inclusive).
     */
    public function fetch(int $minDelayDays): Collection
    {
        return $this->baseFiltered($minDelayDays)
            ->with(['captain:id,code,full_name,phone', 'user:id,name,email'])
            ->orderBy('promised_delivery_at')
            ->get();
    }

    /** عدد الطلبات المتأخرة مع الكابتن دون تحميل الصفوف (للوحة التحكم). */
    public function countDelayed(int $minDelayDays): int
    {
        return (int) $this->baseFiltered($minDelayDays)->count();
    }

    private function baseFiltered(int $minDelayDays): Builder
    {
        $minDelayDays = max(0, $minDelayDays);

        return Order::query()
            ->whereNotNull('captain_id')
            ->whereNotIn('status', Order::TERMINAL_STATUSES)
            ->whereNotNull('promised_delivery_at')
            ->where('promised_delivery_at', '<', now())
            ->where(fn (Builder $q): void => OrderDelayScope::apply($q, $minDelayDays));
    }
}
