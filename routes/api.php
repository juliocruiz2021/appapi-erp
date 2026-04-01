<?php

declare(strict_types=1);

use App\Http\Controllers\Api\System\TenantRegistrationController;
use App\Http\Controllers\Api\Tenant\AuthController;
use App\Http\Controllers\Api\Tenant\BranchController;
use App\Http\Controllers\Api\Tenant\PermissionController;
use App\Http\Controllers\Api\Tenant\PointOfSaleController;
use App\Http\Controllers\Api\Tenant\TaxController;
use App\Http\Controllers\Api\Tenant\UserController;
use App\Http\Controllers\Api\Tenant\UserOperationalConfigController;
use App\Http\Controllers\Api\Tenant\WarehouseController;
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

        // ── Sucursales ────────────────────────────────────────────────────────
        Route::get('/branches', [BranchController::class, 'index'])
            ->middleware('role_or_permission:SuperAdmin|branches.view');
        Route::post('/branches', [BranchController::class, 'store'])
            ->middleware('role_or_permission:SuperAdmin|branches.create');
        Route::get('/branches/{branch}', [BranchController::class, 'show'])
            ->middleware('role_or_permission:SuperAdmin|branches.view');
        Route::match(['put', 'patch'], '/branches/{branch}', [BranchController::class, 'update'])
            ->middleware('role_or_permission:SuperAdmin|branches.update');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])
            ->middleware('role_or_permission:SuperAdmin|branches.delete');

        // ── Bodegas ───────────────────────────────────────────────────────────
        Route::get('/warehouses', [WarehouseController::class, 'index'])
            ->middleware('role_or_permission:SuperAdmin|warehouses.view');
        Route::post('/warehouses', [WarehouseController::class, 'store'])
            ->middleware('role_or_permission:SuperAdmin|warehouses.create');
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])
            ->middleware('role_or_permission:SuperAdmin|warehouses.view');
        Route::match(['put', 'patch'], '/warehouses/{warehouse}', [WarehouseController::class, 'update'])
            ->middleware('role_or_permission:SuperAdmin|warehouses.update');
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])
            ->middleware('role_or_permission:SuperAdmin|warehouses.delete');

        // ── Puntos de Venta ───────────────────────────────────────────────────
        Route::get('/pos', [PointOfSaleController::class, 'index'])
            ->middleware('role_or_permission:SuperAdmin|pos.view');
        Route::post('/pos', [PointOfSaleController::class, 'store'])
            ->middleware('role_or_permission:SuperAdmin|pos.create');
        Route::get('/pos/{pointOfSale}', [PointOfSaleController::class, 'show'])
            ->middleware('role_or_permission:SuperAdmin|pos.view');
        Route::match(['put', 'patch'], '/pos/{pointOfSale}', [PointOfSaleController::class, 'update'])
            ->middleware('role_or_permission:SuperAdmin|pos.update');
        Route::delete('/pos/{pointOfSale}', [PointOfSaleController::class, 'destroy'])
            ->middleware('role_or_permission:SuperAdmin|pos.delete');

        // ── Impuestos ─────────────────────────────────────────────────────────
        Route::get('/taxes', [TaxController::class, 'index'])
            ->middleware('role_or_permission:SuperAdmin|taxes.view');
        Route::post('/taxes', [TaxController::class, 'store'])
            ->middleware('role_or_permission:SuperAdmin|taxes.create');
        Route::get('/taxes/{tax}', [TaxController::class, 'show'])
            ->middleware('role_or_permission:SuperAdmin|taxes.view');
        Route::match(['put', 'patch'], '/taxes/{tax}', [TaxController::class, 'update'])
            ->middleware('role_or_permission:SuperAdmin|taxes.update');
        Route::delete('/taxes/{tax}', [TaxController::class, 'destroy'])
            ->middleware('role_or_permission:SuperAdmin|taxes.delete');

        // ── Configuración operacional de usuario ──────────────────────────────
        Route::get('/me/config', [UserOperationalConfigController::class, 'myConfig']);
        Route::put('/me/config', [UserOperationalConfigController::class, 'updateMyConfig']);
        Route::get('/users/{user}/operational-config', [UserOperationalConfigController::class, 'show'])
            ->middleware('role_or_permission:SuperAdmin|user-config.view');
        Route::put('/users/{user}/operational-config', [UserOperationalConfigController::class, 'update'])
            ->middleware('role_or_permission:SuperAdmin|user-config.update');
    });
};

Route::prefix('{tenant}/api/v1')
    ->where(['tenant' => '[A-Za-z0-9_-]+'])
    ->group($tenantRoutes);

Route::prefix('api/v1/{tenant}')
    ->where(['tenant' => '[A-Za-z0-9_-]+'])
    ->group($tenantRoutes);
