<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardAnalyticsService;
use App\Services\Orders\OrderOperationsStats;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * لوحة التحكم الداخلية: جلسة + CSRF على الطلبات غير الآمنة (واجهة الويب).
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly OrderOperationsStats $stats,
        private readonly DashboardAnalyticsService $analytics
    ) {}

    public function page(): View
    {
        return view('crm.dashboard');
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->stats->summary(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * بيانات الرسوم: توزيع الحالات، أفضل الكباتن، تأخير >5 يوم لكل كابتن.
     */
    public function analytics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->analytics->build(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
