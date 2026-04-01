<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Throwable;

class EnsureJwtBelongsToTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (! tenancy()->initialized) {
            return $next($request);
        }

        try {
            $payload = JWTAuth::parseToken()->getPayload();
        } catch (Throwable) {
            return ApiResponse::error('Unable to validate the supplied token.', 401);
        }

        if ($payload->get('tenant_id') !== tenant('id')) {
            return ApiResponse::error('The supplied token does not belong to this tenant.', 401);
        }

        return $next($request);
    }
}
