<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'metric', 'quantity', 'recorded_at', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function record(int $tenantId, string $metric, int $quantity = 1, array $metadata = []): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'metric' => $metric,
            'quantity' => $quantity,
            'recorded_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public static function getUsage(int $tenantId, string $metric, ?string $since = null): int
    {
        $query = self::where('tenant_id', $tenantId)->where('metric', $metric);

        if ($since) {
            $query->where('recorded_at', '>=', $since);
        }

        return (int) $query->sum('quantity');
    }
}
