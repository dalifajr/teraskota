<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentListenerLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'idempotency_key',
        'amount',
        'source_app',
        'reference',
        'raw_text',
        'status',
        'matched_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }
}
