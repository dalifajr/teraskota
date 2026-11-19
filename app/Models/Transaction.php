<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'sync_id',
        'transaction_number',
        'transaction_date',
        'transaction_time',
        'total_quantity',
        'subtotal_amount',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total_sales',
        'total_profit',
        'estimated_cost',
        'payment_method',
        'status',
        'unique_code',
        'final_amount',
        'qris_payload',
        'qris_expired_at',
        'paid_at',
        'payment_reference',
        'payment_source_app',
        'notified_at',
        'cash_tendered',
        'change_returned',
        'customer_name',
        'source_device_id',
        'sync_status',
        'synced_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'synced_at' => 'datetime',
        'qris_expired_at' => 'datetime',
        'paid_at' => 'datetime',
        'notified_at' => 'datetime',
        'unique_code' => 'integer',
        'total_quantity' => 'integer',
        'subtotal_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'cash_tendered' => 'decimal:2',
        'change_returned' => 'decimal:2',
    ];

    /**
     * Mutator to guarantee discount_type is always valid for ENUM or VARCHAR columns.
     */
    public function setDiscountTypeAttribute($value)
    {
        $this->attributes['discount_type'] = in_array($value, ['nominal', 'percentage'], true) ? $value : 'none';
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentListenerLogs()
    {
        return $this->hasMany(PaymentListenerLog::class, 'matched_transaction_id');
    }

    /**
     * Scope a query to only include paid transactions.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Automatically mark overdue pending QRIS transactions as expired.
     */
    public static function expireOverdueQris(): int
    {
        try {
            return static::where('status', 'pending')
                ->whereNotNull('qris_expired_at')
                ->where('qris_expired_at', '<', now())
                ->update(['status' => 'expired']);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
