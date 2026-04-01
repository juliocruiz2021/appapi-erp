<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\UserResource;
use App\Services\AuditTrailService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            $this->auditTrailService->record('tenant.auth.login_failed', [
                'status_code' => 401,
                'actor_email' => $credentials['email'],
                'meta' => [
                    'tenant' => tenant('id'),
                ],
            ]);

            return ApiResponse::error('Invalid credentials.', 401);
        }

        return $this->respondWithToken($token);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('api')->load(['roles.permissions', 'permissions']);

        return ApiResponse::success('Authenticated user retrieved successfully.', [
            'tenant' => tenant('id'),
            'user' => UserResource::make($user)->resolve(),
        ]);
    }

    private function respondWithToken(string $token): JsonResponse
    {
        $guard = Auth::guard('api');
        $user = $guard->user()->load(['roles.permissions', 'permissions']);
        $payload = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
            'tenant' => tenant('id'),
            'user' => UserResource::make($user)->resolve(),
        ];

        $this->auditTrailService->record('tenant.auth.login_succeeded', [
            'status_code' => 200,
            'actor' => $user,
            'auditable' => $user,
            'meta' => [
                'expires_in' => $payload['expires_in'],
            ],
        ]);

        return ApiResponse::success('Login successful.', $payload);
    }
}
