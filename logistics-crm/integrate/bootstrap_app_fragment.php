<?php

/**
 * ألصق هذا داخل return Application::configure(...)
 * في bootstrap/app.php دالة withRouting، مثال Laravel 11:
 *
 * ->withRouting(
 *     web: __DIR__.'/../routes/web.php',
 *     api: __DIR__.'/../routes/api.php',
 *     commands: __DIR__.'/../routes/console.php',
 *     health: '/up',
 *     then: function () {
 *         \Illuminate\Support\Facades\Route::middleware('api')
 *             ->prefix('external-api')
 *             ->group(base_path('routes/external.php'));
 *     },
 * )
 *
 * النتيجة: نفس المسار الذي يعمل على backoffice.nawris... بدون بادئة /api
 */
