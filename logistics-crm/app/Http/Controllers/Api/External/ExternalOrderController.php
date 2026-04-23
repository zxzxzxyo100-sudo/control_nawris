<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Http\Resources\DelayedOrderWithCaptainResource;
use App\Services\Orders\DelayedOrderWithCaptainQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * External partner API (X-API-TOKEN). No session / CSRF — token-only access.
 */
class ExternalOrderController extends Controller
{
    public function __construct(
        private readonly DelayedOrderWithCaptainQuery $delayedOrderWithCaptainQuery
    ) {}

    /**
     * GET /external-api/orders/delayed/with-captain?delay_days=5
     *
     * Returns active orders that are assigned to a captain, past their promise date,
     * and late by at least the requested number of whole days (inclusive).
     */
    public function delayedWithCaptain(Request $request): JsonResponse
    {
        // Partners may omit the param; default aligns with common SLA review window.
        $validated = $request->validate([
            'delay_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ]);

        $delayDays = (int) ($validated['delay_days'] ?? 5);

        $orders = $this->delayedOrderWithCaptainQuery->fetch($delayDays);

        $payload = DelayedOrderWithCaptainResource::collection($orders)->resolve();

        return response()->json([
            'success' => true,
            'meta' => [
                'delay_days_threshold' => $delayDays,
                'count' => count($payload),
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => array_values($payload),
        ], JsonResponse::HTTP_OK);
    }
}
