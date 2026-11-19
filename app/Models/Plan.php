<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'max_users',
        'max_menus',
        'max_monthly_transactions',
        'features',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'max_users' => 'integer',
        'max_menus' => 'integer',
        'max_monthly_transactions' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) (($this->features ?? [])[$feature] ?? false);
    }

    public function priceForCycle(string $cycle): float
    {
        return $cycle === 'yearly'
            ? (float) $this->price_yearly
            : (float) $this->price_monthly;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopePurchasable($query)
    {
        return $query->where('is_active', true)
            ->where('slug', '!=', 'trial')
            ->where('price_monthly', '>', 0)
            ->orderBy('sort_order');
    }
}
