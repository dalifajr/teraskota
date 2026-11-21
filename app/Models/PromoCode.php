<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'plan_id',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * Validate whether this promo code can be applied to the given plan right now.
     *
     * @return array{valid: bool, message: ?string}
     */
    public function checkValidity(?Plan $plan = null): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'Kode promo sedang tidak aktif.'];
        }

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return ['valid' => false, 'message' => 'Kode promo belum mulai berlaku.'];
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return ['valid' => false, 'message' => 'Kode promo telah kedaluwarsa.'];
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return ['valid' => false, 'message' => 'Kuota pemakaian kode promo sudah habis.'];
        }

        if ($this->plan_id !== null && $plan !== null && (int) $this->plan_id !== (int) $plan->id) {
            $targetName = $this->plan?->name ?? 'paket tertentu';
            return ['valid' => false, 'message' => "Kode promo ini hanya berlaku untuk paket {$targetName}."];
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * Compute discount Rupiah for a given gross base amount.
     */
    public function calculateDiscount(float $grossAmount): float
    {
        if ($grossAmount <= 0) {
            return 0.0;
        }

        if ($this->discount_type === 'percent') {
            $pct = min(100.0, max(0.0, (float) $this->discount_value));
            $discount = round(($grossAmount * $pct) / 100, 0);
            if ($this->max_discount_amount !== null && (float) $this->max_discount_amount > 0) {
                $discount = min($discount, (float) $this->max_discount_amount);
            }
        } else {
            $discount = max(0.0, (float) $this->discount_value);
        }

        // Keep minimum payable base of Rp1.000 so unique-code verification stays valid
        $maxAllowed = max(0.0, $grossAmount - 1000.0);
        return round(min($discount, $maxAllowed), 0);
    }
}
