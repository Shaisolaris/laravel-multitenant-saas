<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use HasFactory, Billable;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'owner_id',
        'plan',
        'status',
        'settings',
        'trial_ends_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByPlan($query, string $plan)
    {
        return $query->where('plan', $plan);
    }

    // ─── Helpers ────────────────────────────────────────

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getPlanLimits(): array
    {
        $limits = [
            'free' => ['users' => 3, 'teams' => 1, 'api_calls' => 1000, 'storage_mb' => 100],
            'starter' => ['users' => 10, 'teams' => 5, 'api_calls' => 50000, 'storage_mb' => 5000],
            'pro' => ['users' => 50, 'teams' => 20, 'api_calls' => 500000, 'storage_mb' => 50000],
            'enterprise' => ['users' => -1, 'teams' => -1, 'api_calls' => -1, 'storage_mb' => -1],
        ];

        return $limits[$this->plan] ?? $limits['free'];
    }

    public function hasReachedLimit(string $resource): bool
    {
        $limits = $this->getPlanLimits();
        $limit = $limits[$resource] ?? 0;

        if ($limit === -1) {
            return false;
        }

        return match ($resource) {
            'users' => $this->users()->count() >= $limit,
            'teams' => $this->teams()->count() >= $limit,
            default => false,
        };
    }
}
