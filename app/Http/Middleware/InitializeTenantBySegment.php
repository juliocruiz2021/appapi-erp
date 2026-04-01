<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantBySegment
{
    public function handle(Request $request, Closure $next): Response
    {
        [$tenantId, $isTenantApiRequest] = $this->resolveTenantContext($request);

        if (! $isTenantApiRequest) {
            return $next($request);
        }

        $tenantModel = config('tenancy.tenant_model');
        $tenant = $tenantModel::query()->whereKey($tenantId)->first();

        if (! $tenant) {
            $domainModel = config('tenancy.domain_model');
            $domain = $domainModel::query()
                ->with('tenant')
                ->where('domain', $tenantId)
                ->first();

            $tenant = $domain?->tenant;
        }

        if (! $tenant) {
            return ApiResponse::error("Tenant [{$tenantId}] was not found.", 404);
        }

        tenancy()->initialize($tenant);

        try {
            return $next($request);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * @return array{0: string|null, 1: bool}
     */
    private function resolveTenantContext(Request $request): array
    {
        $segment1 = $request->segment(1);
        $segment2 = $request->segment(2);
        $segment3 = $request->segment(3);
        $segment4 = $request->segment(4);

        if ($segment1 !== null && $segment2 === 'api' && $segment3 === 'v1') {
            return [$segment1, true];
        }

        if ($segment1 === 'api' && $segment2 === 'v1' && $segment3 !== null && $segment4 !== null) {
            return [$segment3, true];
        }

        return [null, false];
    }
}
