<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentListenerLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'is_platform_billing',
        'idempotency_key',
        'amount',
        'source_app',
        'reference',
        'raw_text',
        'status',
        'matched_transaction_id',
        'matched_invoice_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_platform_billing' => 'boolean',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }

    public function invoice()
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'matched_invoice_id');
    }
}
