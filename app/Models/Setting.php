<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
    ];

    /**
     * Get a setting value by key for the active tenant.
     */
    public static function getValue(string $key, $default = null, ?int $tenantId = null)
    {
        $resolvedTenantId = $tenantId ?? TenantContext::id();

        $query = static::withoutGlobalScope('tenant')->where('key', $key);
        if ($resolvedTenantId !== null) {
            $query->where('tenant_id', $resolvedTenantId);
        }

        $setting = $query->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set or update a setting value by key for the active tenant.
     */
    public static function setValue(string $key, $value, ?int $tenantId = null): self
    {
        $resolvedTenantId = $tenantId ?? TenantContext::id();

        return static::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $resolvedTenantId,
                'key' => $key,
            ],
            [
                'value' => $value !== null ? (string) $value : null,
            ]
        );
    }
}
