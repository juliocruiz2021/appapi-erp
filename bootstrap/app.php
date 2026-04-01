<?php

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\EnsureJwtBelongsToTenant;
use App\Http\Middleware\InitializeTenantBySegment;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

$shouldRenderJson = static fn (Request $request): bool => $request->expectsJson()
    || $request->is('api/*')
    || $request->is('system/*')
    || $request->is('*/api/*');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(AssignRequestContext::class);
        $middleware->append(InitializeTenantBySegment::class);
        $middleware->redirectGuestsTo(static fn () => null);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'tenant.jwt' => EnsureJwtBelongsToTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($shouldRenderJson) {
        $exceptions->shouldRenderJsonWhen($shouldRenderJson);

        $exceptions->render(function (ValidationException $exception, Request $request) use ($shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::validationError($exception->errors(), $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error('Unauthenticated.', 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error($exception->getMessage() ?: 'This action is unauthorized.', 403);
        });

        $exceptions->render(function (SpatieUnauthorizedException $exception, Request $request) use ($shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error('This action is unauthorized.', 403);
        });

        $exceptions->render(function (TokenExpiredException|TokenInvalidException|JWTException $exception, Request $request) use ($shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error('Unable to validate the supplied token.', 401);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) use ($shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error('Too many requests. Please try again later.', 429);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) use ($shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error('The requested resource was not found.', 404);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) use ($shouldRenderJson) {
            if (! $shouldRenderJson($request)) {
                return null;
            }

            return ApiResponse::error(
                config('app.debug') ? $exception->getMessage() : 'Server error.',
                500,
            );
        });
    })->create();
