<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'No tenant associated with this account'], 403);
        }

        $tenant = $user->tenant;

        if (!$tenant || !$tenant->isActive()) {
            return response()->json(['error' => 'Tenant is inactive or suspended'], 403);
        }

        app()->instance('current_tenant', $tenant);

        return $next($request);
    }
}
