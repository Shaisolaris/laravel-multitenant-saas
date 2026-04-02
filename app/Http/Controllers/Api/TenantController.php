<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Invitation;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        return response()->json([
            'tenant' => $tenant,
            'usage' => $this->tenantService->getUsageSummary($tenant),
            'limits' => $tenant->getPlanLimits(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'settings' => 'sometimes|array',
        ]);

        $tenant->update($validated);

        return response()->json(['tenant' => $tenant->fresh()]);
    }

    public function members(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');
        $users = User::forTenant($tenant->id)
            ->select(['id', 'name', 'email', 'status', 'created_at'])
            ->paginate(20);

        return response()->json($users);
    }

    public function invite(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        $this->authorize('manage', $tenant);

        if ($tenant->hasReachedLimit('users')) {
            return response()->json([
                'error' => 'User limit reached for your plan. Please upgrade.',
                'limit' => $tenant->getPlanLimits()['users'],
            ], 422);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,member,viewer',
        ]);

        $existing = User::where('email', $validated['email'])
            ->where('tenant_id', $tenant->id)
            ->exists();

        if ($existing) {
            return response()->json(['error' => 'User is already a member'], 422);
        }

        $invitation = Invitation::createForEmail(
            $tenant->id,
            $validated['email'],
            $validated['role'],
        );

        // In production: dispatch InvitationCreated event -> sends email

        return response()->json(['invitation' => $invitation], 201);
    }

    public function removeMember(Request $request, int $userId): JsonResponse
    {
        $tenant = app('current_tenant');

        $this->authorize('manage', $tenant);

        if ($tenant->owner_id === $userId) {
            return response()->json(['error' => 'Cannot remove the tenant owner'], 422);
        }

        $user = User::forTenant($tenant->id)->findOrFail($userId);
        $user->update(['tenant_id' => null]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Member removed']);
    }

    public function billing(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        return response()->json([
            'plan' => $tenant->plan,
            'subscribed' => $tenant->subscribed('default'),
            'on_trial' => $tenant->isOnTrial(),
            'usage' => $this->tenantService->getUsageSummary($tenant),
            'invoices' => $tenant->subscribed('default')
                ? $tenant->invoices()->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'amount' => $invoice->total(),
                    'date' => $invoice->date()->toDateString(),
                    'status' => $invoice->status,
                    'pdf' => $invoice->invoicePdfUrl(),
                ])
                : [],
        ]);
    }
}
