<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class TenantContext
{
    protected static ?int $overrideTenantId = null;
    protected static ?Tenant $cachedTenant = null;
    protected static ?int $cachedTenantId = null;

    /**
     * Explicitly set the active tenant context (for webhooks, jobs, seeders, tests).
     */
    public static function set(Tenant|int|null $tenant): void
    {
        if ($tenant instanceof Tenant) {
            static::$overrideTenantId = (int) $tenant->id;
            static::$cachedTenant = $tenant;
            static::$cachedTenantId = (int) $tenant->id;
        } elseif (is_int($tenant)) {
            static::$overrideTenantId = $tenant;
            static::$cachedTenant = null;
            static::$cachedTenantId = null;
        } else {
            static::$overrideTenantId = null;
            static::$cachedTenant = null;
            static::$cachedTenantId = null;
        }
    }

    /**
     * Clear explicit tenant override and memory cache.
     */
    public static function clear(): void
    {
        static::$overrideTenantId = null;
        static::$cachedTenant = null;
        static::$cachedTenantId = null;
    }

    /**
     * Resolve the active tenant ID for the current request or execution context.
     */
    public static function id(): ?int
    {
        if (static::$overrideTenantId !== null) {
            return static::$overrideTenantId;
        }

        if (Auth::hasUser()) {
            $user = Auth::user();
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                if (function_exists('session') && session()->has('impersonating_tenant_id')) {
                    return (int) session('impersonating_tenant_id');
                }
                return null;
            }

            if ($user && !empty($user->tenant_id)) {
                return (int) $user->tenant_id;
            }
        }

        return null;
    }

    /**
     * Resolve the active Tenant model instance.
     */
    public static function current(): ?Tenant
    {
        $id = static::id();
        if ($id === null) {
            return null;
        }

        if (static::$cachedTenant !== null && static::$cachedTenantId === $id) {
            return static::$cachedTenant;
        }

        static::$cachedTenant = Tenant::with(['activeSubscription.plan'])->find($id);
        static::$cachedTenantId = $id;

        return static::$cachedTenant;
    }

    /**
     * Execute a callable under a specific tenant scope, then restore previous state.
     */
    public static function runForTenant(Tenant|int $tenant, callable $callback): mixed
    {
        $previousOverride = static::$overrideTenantId;
        $previousCached = static::$cachedTenant;
        $previousCachedId = static::$cachedTenantId;

        static::set($tenant);

        try {
            return $callback();
        } finally {
            static::$overrideTenantId = $previousOverride;
            static::$cachedTenant = $previousCached;
            static::$cachedTenantId = $previousCachedId;
        }
    }

    /**
     * Refresh the cached tenant model (e.g. after updating store settings or subscription).
     */
    public static function refresh(): void
    {
        static::$cachedTenant = null;
        static::$cachedTenantId = null;
    }
}
