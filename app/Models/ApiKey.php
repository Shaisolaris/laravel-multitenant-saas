<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'key', 'tenant_id', 'scopes', 'expires_at', 'last_used_at', 'is_active'];

    protected $hidden = ['key'];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function generate(int $tenantId, string $name, array $scopes = ['*']): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'key' => 'sk_' . Str::random(48),
            'scopes' => $scopes,
            'is_active' => true,
        ]);
    }

    public function hasScope(string $scope): bool
    {
        return in_array('*', $this->scopes ?? []) || in_array($scope, $this->scopes ?? []);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function markUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
