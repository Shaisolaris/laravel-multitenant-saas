<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(): JsonResponse
    {
        $tenant = app('current_tenant');
        $keys = ApiKey::where('tenant_id', $tenant->id)
            ->select(['id', 'name', 'scopes', 'is_active', 'last_used_at', 'expires_at', 'created_at'])
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['api_keys' => $keys]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $apiKey = ApiKey::generate($tenant->id, $validated['name'], $validated['scopes'] ?? ['*']);

        if (isset($validated['expires_at'])) {
            $apiKey->update(['expires_at' => $validated['expires_at']]);
        }

        return response()->json([
            'api_key' => $apiKey,
            'key' => $apiKey->key, // Only shown once at creation
        ], 201);
    }

    public function revoke(int $id): JsonResponse
    {
        $tenant = app('current_tenant');
        $apiKey = ApiKey::where('tenant_id', $tenant->id)->findOrFail($id);
        $apiKey->update(['is_active' => false]);
        return response()->json(['message' => 'API key revoked']);
    }
}
