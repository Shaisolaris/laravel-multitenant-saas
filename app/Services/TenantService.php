<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UsageRecord;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * Provision a new tenant with owner.
     */
    public function provision(User $owner, string $name): Tenant
    {
        $slug = Str::slug($name) . '-' . Str::random(6);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'owner_id' => $owner->id,
            'plan' => 'free',
            'status' => 'active',
            'settings' => [
                'timezone' => $owner->timezone ?? 'UTC',
                'date_format' => 'Y-m-d',
                'notifications_enabled' => true,
            ],
        ]);

        // Assign owner to tenant
        $owner->update(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');

        return $tenant;
    }

    /**
     * Upgrade tenant plan.
     */
    public function upgradePlan(Tenant $tenant, string $plan, string $stripePaymentMethodId): void
    {
        $priceMap = [
            'starter' => 'price_starter_monthly',
            'pro' => 'price_pro_monthly',
            'enterprise' => 'price_enterprise_monthly',
        ];

        $priceId = $priceMap[$plan] ?? null;

        if (!$priceId) {
            throw new \InvalidArgumentException("Invalid plan: {$plan}");
        }

        if (!$tenant->hasStripeId()) {
            $tenant->createAsStripeCustomer([
                'name' => $tenant->name,
                'metadata' => ['tenant_id' => $tenant->id],
            ]);
        }

        $tenant->updateDefaultPaymentMethod($stripePaymentMethodId);

        $tenant->newSubscription('default', $priceId)
            ->create($stripePaymentMethodId);

        $tenant->update(['plan' => $plan]);
    }

    /**
     * Cancel tenant subscription at period end.
     */
    public function cancelSubscription(Tenant $tenant): void
    {
        if ($tenant->subscribed('default')) {
            $tenant->subscription('default')->cancel();
        }

        // Don't change plan immediately — let webhook handle it
    }

    /**
     * Get usage summary for the current billing period.
     */
    public function getUsageSummary(Tenant $tenant): array
    {
        $periodStart = now()->startOfMonth()->toDateTimeString();
        $limits = $tenant->getPlanLimits();

        $metrics = ['api_calls', 'storage_mb'];
        $summary = [];

        foreach ($metrics as $metric) {
            $used = UsageRecord::getUsage($tenant->id, $metric, $periodStart);
            $limit = $limits[$metric] ?? 0;

            $summary[] = [
                'metric' => $metric,
                'used' => $used,
                'limit' => $limit,
                'percentage' => $limit > 0 ? round(($used / $limit) * 100, 1) : 0,
                'unlimited' => $limit === -1,
            ];
        }

        return $summary;
    }

    /**
     * Deactivate tenant (e.g., payment failure, manual suspension).
     */
    public function deactivate(Tenant $tenant, string $reason = 'manual'): void
    {
        $tenant->update([
            'status' => 'suspended',
            'settings' => array_merge($tenant->settings ?? [], [
                'suspended_at' => now()->toIso8601String(),
                'suspended_reason' => $reason,
            ]),
        ]);
    }

    /**
     * Reactivate tenant.
     */
    public function reactivate(Tenant $tenant): void
    {
        $tenant->update([
            'status' => 'active',
            'settings' => array_merge($tenant->settings ?? [], [
                'suspended_at' => null,
                'suspended_reason' => null,
            ]),
        ]);
    }
}
