<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'promo_code_id',
        'promo_code_snapshot',
        'subscription_id',
        'invoice_number',
        'billing_cycle',
        'base_amount',
        'discount_amount',
        'unique_code',
        'final_amount',
        'payment_method',
        'qris_payload',
        'qris_expired_at',
        'payment_reference',
        'payment_source_app',
        'status',
        'paid_at',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'unique_code' => 'integer',
        'final_amount' => 'decimal:2',
        'qris_expired_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    /**
     * Automatically mark overdue unpaid invoices as expired.
     */
    public static function expireOverdue(): int
    {
        return static::where('status', 'unpaid')
            ->whereNotNull('qris_expired_at')
            ->where('qris_expired_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
