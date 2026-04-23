<?php

use App\Http\Controllers\Api\External\ExternalOrderController;
use App\Http\Middleware\ValidateExternalApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External partner API (token header)
|--------------------------------------------------------------------------
|
| Default Laravel 11 serves file routes/api.php under the "api" prefix, so the
| operational URL is typically:
|   GET /api/external-api/orders/delayed/with-captain?delay_days=5
|
| To expose the same routes at /external-api/... without the /api prefix,
| register an additional route group in bootstrap/app.php (see Laravel docs:
| "Routing" → custom route files) pointing at this group or a dedicated file.
|
| Optional: mirror `crm.external_api.token` into config/services.php as
| `external_api.token` if you prefer a single services config.
|--------------------------------------------------------------------------
*/

Route::prefix('external-api')
    ->middleware([
        ValidateExternalApiToken::class,
        'throttle:120,1',
    ])
    ->group(function (): void {
        Route::get('orders/delayed/with-captain', [ExternalOrderController::class, 'delayedWithCaptain'])
            ->name('api.external.orders.delayed_with_captain');
    });

/*
|--------------------------------------------------------------------------
| Backoffice JSON API (session + Sanctum / CSRF)
|--------------------------------------------------------------------------
|
| Consume these from your React/Vue SPA using axios/fetch with credentials,
| X-XSRF-TOKEN header from the encrypted XSRF-TOKEN cookie, and SameSite cookies.
| Install Laravel Sanctum (or Breeze API stack) and uncomment/adjust as needed.
|--------------------------------------------------------------------------
*/

// Route::middleware(['auth:sanctum'])->prefix('backoffice')->group(function (): void {
//     Route::get('/user', fn (\Illuminate\Http\Request $r) => $r->user());
// });
