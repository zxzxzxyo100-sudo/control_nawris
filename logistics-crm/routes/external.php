<?php

/**
 * مسارات شركاء خارجيون على جذر التطبيق:
 *   GET /external-api/orders/delayed/with-captain
 *
 * تُحمَّل من bootstrap/app.php داخل withRouting(..., then: ...) — انظر integrate/bootstrap_app_fragment.php
 */

use App\Http\Controllers\Api\External\ExternalOrderController;
use App\Http\Middleware\ValidateExternalApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware([
    ValidateExternalApiToken::class,
    'throttle:120,1',
])->group(function (): void {
    Route::get('orders/delayed/with-captain', [ExternalOrderController::class, 'delayedWithCaptain'])
        ->name('external.orders.delayed_with_captain');
});
