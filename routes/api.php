<?php

declare(strict_types=1);

use App\Http\Controllers\Api\System\TenantRegistrationController;
use App\Http\Controllers\Api\Tenant\AuthController;
use App\Http\Controllers\Api\Tenant\PermissionController;
use App\Http\Controllers\Api\Tenant\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Las rutas centrales y tenant-aware viven aqui sin prefijo automatico.
| El segmento `/{tenant}/api/v1/...` se resuelve via middleware global.
|
*/

Route::post('/system/register', [TenantRegistrationController::class, 'store'])
    ->middleware('throttle:system-register');

$tenantRoutes = function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:tenant-login');

    Route::middleware(['auth:api', 'tenant.jwt', 'throttle:tenant-api'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/users', [UserController::class, 'index'])
            ->middleware('role_or_permission:SuperAdmin|users.view');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('role_or_permission:SuperAdmin|users.create');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('role_or_permission:SuperAdmin|users.view');
        Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])
            ->middleware('role_or_permission:SuperAdmin|users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('role_or_permission:SuperAdmin|users.delete');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('role_or_permission:SuperAdmin|permissions.view');
        Route::post('/permissions', [PermissionController::class, 'store'])
            ->middleware('role_or_permission:SuperAdmin|permissions.create');
        Route::get('/permissions/{permissionId}', [PermissionController::class, 'show'])
            ->middleware('role_or_permission:SuperAdmin|permissions.view');
        Route::match(['put', 'patch'], '/permissions/{permissionId}', [PermissionController::class, 'update'])
            ->middleware('role_or_permission:SuperAdmin|permissions.update');
        Route::delete('/permissions/{permissionId}', [PermissionController::class, 'destroy'])
            ->middleware('role_or_permission:SuperAdmin|permissions.delete');
    });
};

Route::prefix('{tenant}/api/v1')
    ->where(['tenant' => '[A-Za-z0-9_-]+'])
    ->group($tenantRoutes);

Route::prefix('api/v1/{tenant}')
    ->where(['tenant' => '[A-Za-z0-9_-]+'])
    ->group($tenantRoutes);
