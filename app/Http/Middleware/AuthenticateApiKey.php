<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\UsageRecord;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $key = $request->header('X-API-Key') ?? $request->query('api_key');

        if (!$key) {
            return response()->json(['error' => 'API key is required'], 401);
        }

        $apiKey = ApiKey::where('key', $key)->first();

        if (!$apiKey || !$apiKey->isValid()) {
            return response()->json(['error' => 'Invalid or expired API key'], 401);
        }

        foreach ($scopes as $scope) {
            if (!$apiKey->hasScope($scope)) {
                return response()->json(['error' => "Missing required scope: {$scope}"], 403);
            }
        }

        $apiKey->markUsed();

        $tenant = $apiKey->tenant;
        app()->instance('current_tenant', $tenant);

        // Rate limiting
        $limits = $tenant->getPlanLimits();
        $apiCallLimit = $limits['api_calls'] ?? 0;

        if ($apiCallLimit !== -1) {
            $currentUsage = UsageRecord::getUsage($tenant->id, 'api_calls', now()->startOfMonth()->toDateTimeString());

            if ($currentUsage >= $apiCallLimit) {
                return response()->json([
                    'error' => 'API rate limit exceeded',
                    'limit' => $apiCallLimit,
                    'used' => $currentUsage,
                ], 429);
            }
        }

        UsageRecord::record($tenant->id, 'api_calls');

        return $next($request);
    }
}
