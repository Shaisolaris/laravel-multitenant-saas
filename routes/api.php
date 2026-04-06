<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\ApiKeyController;
use Illuminate\Support\Facades\Route;

// ─── Public ─────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// ─── Authenticated ──────────────────────────────────────

Route::middleware(['auth:sanctum', 'tenant.access'])->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Tenant
    Route::get('/tenant', [TenantController::class, 'show']);
    Route::patch('/tenant', [TenantController::class, 'update']);
    Route::get('/tenant/members', [TenantController::class, 'members']);
    Route::post('/tenant/invite', [TenantController::class, 'invite']);
    Route::delete('/tenant/members/{userId}', [TenantController::class, 'removeMember']);
    Route::get('/tenant/billing', [TenantController::class, 'billing']);

    // Teams
    Route::apiResource('teams', TeamController::class)->except(['update']);
    Route::post('/teams/{id}/members', [TeamController::class, 'addMember']);
    Route::delete('/teams/{teamId}/members/{userId}', [TeamController::class, 'removeMember']);

    // API Keys
    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::post('/api-keys/{id}/revoke', [ApiKeyController::class, 'revoke']);
});

// ─── API Key Authenticated ──────────────────────────────

Route::middleware(['api.key'])->prefix('v1')->group(function () {
    Route::get('/tenant', [TenantController::class, 'show']);
    Route::get('/teams', [TeamController::class, 'index']);
});
// Demo mode: seed data available via php artisan db:seed
