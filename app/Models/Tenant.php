<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'logo_path',
        'address',
        'phone',
        'receipt_footer',
        'timezone',
        'is_active',
        'is_lifetime',
        'trial_ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_lifetime' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'toko';
        $slug = $base;
        $counter = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class)->withoutGlobalScope('tenant');
    }

    public function menus()
    {
        return $this->hasMany(Menu::class)->withoutGlobalScope('tenant');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->withoutGlobalScope('tenant');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class)->withoutGlobalScope('tenant');
    }

    public function settings()
    {
        return $this->hasMany(Setting::class)->withoutGlobalScope('tenant');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class)->latest('id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany('id');
    }

    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class)->latest('id');
    }

    public function currentPlan(): ?Plan
    {
        return $this->activeSubscription?->plan;
    }

    /**
     * Evaluate effective subscription state: lifetime, active, trial, grace, expired, suspended.
     */
    public function subscriptionState(): string
    {
        if (!$this->is_active) {
            return 'suspended';
        }

        if ($this->is_lifetime) {
            return 'lifetime';
        }

        $sub = $this->activeSubscription;
        if (!$sub) {
            if ($this->trial_ends_at && $this->trial_ends_at->isFuture()) {
                return 'trial';
            }
            return 'expired';
        }

        if ($sub->billing_cycle === 'lifetime' && $sub->status === 'active') {
            return 'lifetime';
        }

        if ($sub->status === 'cancelled') {
            return 'expired';
        }

        $now = Carbon::now();

        if ($sub->ends_at === null) {
            return $sub->status === 'trial' ? 'trial' : 'active';
        }

        if ($now->lte($sub->ends_at)) {
            return $sub->status === 'trial' ? 'trial' : 'active';
        }

        $graceDays = (int) PlatformSetting::getValue('grace_days', config('platform.grace_days', 3));
        $graceEnds = $sub->grace_ends_at ?: $sub->ends_at->copy()->addDays($graceDays);

        if ($now->lte($graceEnds)) {
            return 'grace';
        }

        return 'expired';
    }

    public function canOperate(): bool
    {
        return in_array($this->subscriptionState(), ['lifetime', 'active', 'trial', 'grace'], true);
    }

    public function isInGracePeriod(): bool
    {
        return $this->subscriptionState() === 'grace';
    }

    public function isExpiredOrSuspended(): bool
    {
        return !$this->canOperate();
    }

    public function daysRemaining(): ?int
    {
        if ($this->is_lifetime) {
            return null;
        }

        $sub = $this->activeSubscription;
        if (!$sub || !$sub->ends_at) {
            return null;
        }

        return (int) Carbon::now()->diffInDays($sub->ends_at, false);
    }

    public function hasFeature(string $feature): bool
    {
        if ($this->is_lifetime) {
            return true;
        }

        if (!$this->canOperate()) {
            return false;
        }

        $plan = $this->currentPlan();
        if (!$plan) {
            return false;
        }

        return $plan->hasFeature($feature);
    }

    public function logoUrl(): string
    {
        if (!empty($this->logo_path)) {
            if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
                return $this->logo_path;
            }
            if (file_exists(public_path($this->logo_path))) {
                return asset($this->logo_path);
            }
            if (file_exists(public_path('storage/' . $this->logo_path))) {
                return asset('storage/' . $this->logo_path);
            }
        }

        return asset('images/logo.png');
    }
}
