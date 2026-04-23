<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates X-API-TOKEN for machine-to-machine integrations.
 * Token is compared in constant time to the configured secret (timing-safe).
 */
class ValidateExternalApiToken
{
    private const HEADER = 'X-API-TOKEN';

    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('crm.external_api.token', '');

        if ($configured === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'External API is not configured.');
        }

        $provided = (string) $request->header(self::HEADER, '');

        // hash_equals avoids timing leaks when comparing secrets.
        if (! hash_equals($configured, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing API token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
